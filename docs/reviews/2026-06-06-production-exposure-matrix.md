# 生产暴露面与路由安全矩阵

审查日期：2026-06-06

范围：`cmd/server/main.go` 中当前注册的公开路由、受保护路由、JWT/限流边界，以及公开 handler 输出的敏感运行信息。

## 总体结论

当前路由分组不适合直接用于生产外网暴露。`public := r.Group("/")` 下注册了健康检查、JWT 测试 token 生成、Redis 明细、调试状态、模型配置、Web 请求指标、OpenAI Responses 状态、Azure 状态和 Web 静态页面。公开组不经过 JWT 鉴权，也不经过限流中间件。

这意味着即使 `jwt.enabled=true`、`rate_limit.enabled=true`，这些公开接口仍然可以被匿名访问。部分接口虽然做了 API Key 脱敏，但仍暴露了部署 endpoint、模型启用状态、Redis key/value、请求明细、User-Agent、运行时内存、goroutine、代理配置来源和内部路由清单。

生产启动和配置强校验缺口见 `docs/reviews/2026-06-06-production-startup-security-readiness-matrix.md`。该矩阵覆盖 prod JWT secret、公开调试开关、Origin、Trusted Proxy、上游 query key、Redis 强依赖和日志保留等启动 gate。

## 当前路由分组证据

公开路由证据：

- `cmd/server/main.go:62` 创建 `public := r.Group("/")`。
- `cmd/server/main.go:65-104` 在公开组注册 `/health`、`/test/generate-token`、`/api/redis/keys`、`/api/debug/status`、`/api/openai/responses/status`、`/api/web/models`、`/api/web/metrics`、`/api/azure/status`、`/web/*filepath`。
- 公开组没有 `middleware.Auth()`。
- 公开组没有 `middleware.RateLimit()`。

受保护路由证据：

- `cmd/server/main.go:108` 创建 `auth := r.Group("/")`。
- `cmd/server/main.go:112-118` 只有该组按配置加载 JWT 和限流。
- `cmd/server/main.go:122-143` 注册 OpenAI/Azure Realtime、Responses、Workspace、Azure HTTP 和 Fallback 路由。

## 路由矩阵

| 路由 | 当前分组 | 当前鉴权 | 当前限流 | 暴露内容 | 生产判断 | 修复建议 |
|---|---|---:|---:|---|---|---|
| `GET /health` | public | 无 | 无 | 服务存活、时间、活跃会话数 | 可保留，但字段应最小化 | 生产只返回 `ok`；详细容量放入受保护诊断接口 |
| `GET /test/generate-token` | public | 无 | 无 | 任意 `userId` 的 JWT token | P0，不可生产公开 | 仅 dev 注册；prod 禁止注册或返回 404 |
| `GET /api/redis/keys` | public | 无 | 无 | Redis key、type、TTL、value；支持 `full=1` | P0，不可生产公开 | 仅 dev 注册；prod 必须鉴权，并默认禁用 value/full |
| `GET /api/debug/status` | public | 无 | 无 | Go runtime、内存、Redis、代理、OpenAI/Azure 配置、路由清单、metrics 快照 | P0，不可生产公开 | 放入受保护组；生产只给管理员；敏感字段最小化 |
| `GET /api/openai/responses/status` | public | 无 | 无 | Responses endpoint、API Key 是否配置、默认参数 | P1，生产不应匿名公开 | 放入受保护组或并入诊断接口 |
| `GET /api/web/models` | public | 无 | 无 | 模型名、endpoint、默认模型、rate、API Key 是否配置和脱敏片段 | P1，生产不应匿名公开 | 放入受保护组；只返回当前用户可用模型 |
| `GET /api/web/metrics` | public | 无 | 无 | 最近 500 条请求明细、token、费用、endpoint、User-Agent、错误 | P0，不可生产公开 | 放入受保护组；按管理员权限查看；长期统计改 Redis/DB |
| `GET /api/azure/status` | public | 无 | 无 | Azure endpoint、deployment、模块状态、API Key 是否配置 | P1，生产不应匿名公开 | 放入受保护组或管理员诊断接口 |
| `GET/HEAD /web/*filepath` | public | 无 | 无 | Web 测试面板、聊天看板、诊断页面、Redis 页面 | P1，生产需区分静态页面和敏感 API | 静态页可公开，但页面调用的 API 必须鉴权；生产可关闭测试页 |
| `GET /ws/realtime/openai` | auth | 按配置 | 按配置 | OpenAI Realtime WS 网关 | 必须受保护 | 保持受保护；补 Origin 白名单和上游 key query 策略 |
| `GET /ws/realtime/azure` | auth | 按配置 | 按配置 | Azure Realtime WS 网关 | 必须受保护 | 保持受保护；补 Origin 白名单和真实 IP |
| `POST /api/openai/responses` | auth | 按配置 | 按配置 | Responses HTTP 代理 | 必须受保护 | 保持受保护；错误返回避免泄露过多 upstream raw |
| `GET/POST /api/workspace/*` | auth | 按配置 | 按配置 | 项目文件列表、读取、写入；细化证据见 `docs/reviews/2026-06-06-workspace-write-safety-matrix.md` | P0，高风险能力 | 保持受保护；写文件改为 pending diff + 用户确认 + 审计 + 回滚 |
| `POST /api/azure/*` | auth | 按配置 | 按配置 | Azure HTTP 代理能力 | 必须受保护 | 保持受保护；限制 body、记录审计和错误 |
| `POST /v1/chat/completions` | auth | 按配置 | 按配置 | HTTP 降级代理 | 必须受保护 | 仅 fallback enabled 时注册；生产需限流和配额 |

## 重点问题

### 1. `/test/generate-token` 可匿名签发任意用户 token

证据：

- `cmd/server/main.go:75-88` 公开注册 `/test/generate-token`。
- `cmd/server/main.go:76` 从 query 读取 `userId`，默认 `test_user_001`。
- `internal/middleware/auth.go:137-145` `GenerateToken(userID)` 使用全局 JWT secret 签发 token。
- `internal/middleware/auth.go:36-39` 和 `internal/middleware/auth.go:139-141` secret 为空时回退 `default-jwt-secret-123456`。

影响：

- 生产误暴露时，攻击者可以自助生成任意用户 token。
- 如果生产 JWT secret 为空，固定默认密钥会让伪造 token 更容易。

修复：

- 新增 `security.public_token_enabled`，prod 必须为 false。
- prod 下 JWT secret 为空必须启动失败。
- `GenerateToken` 支持 `user_name` claim，但不允许公开匿名签发。

### 2. Redis 明细接口可匿名读取 key 和 value

证据：

- `cmd/server/main.go:92` 公开注册 `/api/redis/keys`。
- `internal/handler/redis_handler.go:26-31` 支持 `pattern`、`cursor`、`count`、`full=1`。
- `internal/handler/redis_handler.go:78-116` 对 string/hash/list/set/zset 返回 value。
- `web/redis.html:267` 前端默认请求 `/api/redis/keys?...&full=1`。
- `web/diagnostics.html:334` 诊断页面也请求 `/api/redis/keys?pattern=*&count=1000&full=1`。

影响：

- 可能暴露 session、billing、rate limit、token 相关 key。
- `full=1` 会拉完整 list/zset，生产高基数 key 可能拖慢 Redis 和 Go 实例。

修复：

- prod 默认不注册该接口，或仅管理员鉴权可访问。
- prod 禁用 `full=1`，value 默认只返回摘要、长度、类型和 TTL。
- Redis 明细接口加入审计日志和访问频率限制。

### 3. 调试状态接口匿名暴露运行与配置画像

证据：

- `cmd/server/main.go:93` 公开注册 `/api/debug/status`。
- `internal/handler/debug_handler.go:35-44` 返回 server、memory、capacity、features、redis、network、openai、responses、azure、metrics。
- `internal/handler/debug_handler.go:258-295` 返回 OpenAI endpoint、ws_url、rate、organization、API Key 是否配置和脱敏片段。
- `internal/handler/debug_handler.go:303-359` 返回 Azure endpoint、deployment、模块状态、API Key 是否配置和脱敏片段。
- `internal/handler/debug_handler.go:225-233` 返回代理配置来源和脱敏代理地址。

影响：

- 匿名用户可获得部署拓扑、模型提供商、中转站、代理、容量、运行时和路由清单。
- 这些信息可帮助攻击者定位弱点、绕过限流或探测上游 provider。

修复：

- prod 放入受保护组并要求管理员权限。
- 公开健康检查只保留最小字段。
- debug/status 的 endpoint、proxy、organization、routes 字段按权限分级。

### 4. Web metrics 匿名暴露请求明细

证据：

- `cmd/server/main.go:96` 公开注册 `/api/web/metrics`。
- `internal/handler/web_metrics_handler.go:19-25` 使用进程内 slice 保留最近 500 条。
- `internal/handler/web_metrics_handler.go:30-51` 记录 model、token、费用、endpoint、User-Agent、error 等字段。
- `internal/handler/web_metrics_handler.go:129-183` 对外返回 summary、records、charts。

影响：

- 请求明细和错误信息可能包含用户行为、模型调用画像和内部 endpoint。
- 进程内 500 条不适合长期审计，也不能跨实例聚合。

修复：

- prod 必须鉴权。
- 展示字段按角色裁剪。
- 长期统计写入 Redis/DB/日志平台，Web metrics 只保留最近调试视图。

### 5. 公开配置状态接口虽脱敏但仍泄露部署画像

证据：

- `cmd/server/main.go:94` 公开 `/api/openai/responses/status`。
- `cmd/server/main.go:95` 公开 `/api/web/models`。
- `cmd/server/main.go:97` 公开 `/api/azure/status`。
- `internal/handler/web_models_handler.go:25-40` 返回模型名、endpoint、rate、API Key 配置状态、organization_set。
- `internal/provider/openairesponses/client.go:103-116` 返回 Responses endpoint、默认模型、timeout、store 等状态。
- `internal/provider/azureai/client.go:89-112` 返回 Azure endpoint、模块和 deployment 配置状态。

影响：

- 即使 API Key 脱敏，endpoint、部署名、模型名和启用状态仍属于生产内部画像。

修复：

- prod 放入受保护组。
- 普通用户只返回可选模型列表，不返回 endpoint、deployment、key 状态。

### 6. 受保护路由依赖配置开关，生产缺少强校验

证据：

- `cmd/server/main.go:112-118` 只有 `conf.Global.JWT.Enabled` 和 `conf.Global.RateLimit.Enabled` 为 true 时才加载中间件。
- `conf/config_prod.yaml:15-17` prod JWT secret 默认为空。
- 当前未发现 `validateProductionConfig` 一类生产强校验。

影响：

- prod 误配置时服务仍可能启动。
- 如果 JWT 关闭或 secret 为空，受保护路由边界会失效或退回默认密钥。

修复：

- `conf.Load()` 后执行生产配置校验。
- prod 强制 JWT enabled、secret 非空、公开 token/debug disabled、allowed origins 非空。
- prod 下 Redis/限流策略需要明确 fail-open/fail-closed。

## 推荐修复顺序

1. 新增生产安全配置结构：`security.public_debug_enabled`、`security.public_token_enabled`、`security.allowed_origins`、`security.trusted_proxies`、`security.allow_upstream_query_key`。
2. 新增生产配置校验：prod 下 JWT secret 为空、公开 token/debug 开启、Origin 白名单为空时启动失败。
3. 抽出路由注册函数：让测试可以验证 prod 下敏感公开路由不注册。
4. 按配置注册公开调试路由：dev 开启，prod 关闭或进入受保护管理员组。
5. 移除 JWT 默认密钥兜底。
6. 收紧 Redis 明细和 Web metrics：prod 禁止匿名访问，禁用 `full=1`。
7. 配置 `SetTrustedProxies`，修正真实 IP 采集。
8. Realtime Origin 改为配置化白名单。
9. 上游 API Key query override 仅 dev 允许。

## 与实施计划的对应关系

- Task 1：生产安全配置与启动校验。
- Task 2：公开路由收紧与 Trusted Proxy。
- Task 3：JWT 默认密钥移除与用户名称采集。
- Task 4：Realtime Origin 与上游 Key 策略。
- Task 6：长连接生命周期、背压和重连韧性修复。
- Task 7：监控数据采集与日志落点。
- Task 8：钉钉过载告警。
- Task 9：天/周/月统计聚合。
