# 生产启动与配置安全就绪矩阵

审查日期：2026-06-06

当前阶段：只审查并固化证据，不修改业务逻辑。

## 总体结论

当前项目缺少生产启动前的强制安全校验。`conf.Load()` 负责加载基础配置、环境配置、模型配置和环境变量占位符，但没有发现 `ValidateProductionConfig`、`validateSecurityConfig` 或等价启动 gate。服务启动后再由 `cmd/server/main.go` 注册路由和中间件，这会让生产误配置在启动期直接漏过去。

这类问题必须优先于监控、告警、统计和面板增强修复。否则后续加入更多诊断字段、日志字段和统计 API，会把当前匿名公开调试面扩大为更完整的生产画像暴露面。

## 当前启动路径证据

| 启动步骤 | 证据 | 当前判断 |
| --- | --- | --- |
| 加载配置 | `conf/loader.go:28` 到 `conf/loader.go:83` 读取 `config.yaml`、`config_{env}.yaml`、模型配置和环境变量占位符 | 只有加载和合并，没有生产安全校验 |
| 设置 Gin mode | `cmd/server/main.go:45` 到 `cmd/server/main.go:49` 根据 `conf.Global.Env == "prod"` 切 release | 只设置 Gin 模式，不校验安全配置 |
| 初始化日志/Redis/模型缓存 | `cmd/server/main.go:52` 到 `cmd/server/main.go:54` | Redis 初始化在配置校验前没有强依赖策略 |
| 注册公开路由 | `cmd/server/main.go:62` 到 `cmd/server/main.go:104` | 敏感调试 API 在 public 组匿名注册 |
| 注册受保护路由 | `cmd/server/main.go:108` 到 `cmd/server/main.go:143` | JWT/限流是否启用完全依赖配置开关 |
| 启动服务 | `cmd/server/main.go:148` 到 `cmd/server/main.go:188` | 启动日志只输出少量开关状态，不输出配置校验结果 |

## 生产安全 gate 缺口矩阵

| 编号 | 问题 | 证据 | 风险 | 修复方向 |
| --- | --- | --- | --- | --- |
| P0-START-01 | prod JWT secret 为空不会在加载期失败 | `conf/config_prod.yaml:15` 到 `conf/config_prod.yaml:17` 设置 `jwt.enabled: true` 且 `secret: ""`；`conf/loader.go` 未发现 secret 校验 | 生产误配置仍可能启动 | `conf.Load()` 后执行 `ValidateProductionConfig()`，prod 下 secret 为空直接返回错误 |
| P0-START-02 | JWT 空密钥有固定默认值兜底 | `internal/middleware/auth.go:35` 到 `internal/middleware/auth.go:39`；`internal/middleware/auth.go:138` 到 `internal/middleware/auth.go:142` | token 可被固定默认密钥伪造，`/test/generate-token` 风险放大 | 移除默认密钥；GenerateToken/Auth 都要求显式 secret |
| P0-START-03 | 匿名 token 生成接口没有 prod gate | `cmd/server/main.go:75` 到 `cmd/server/main.go:88` 在 public 组注册 `/test/generate-token` | 任意人可生成任意 userId token | 新增 `security.public_token_enabled`，prod 必须 false；prod 下不注册路由 |
| P0-START-04 | 匿名诊断和 Redis 明细接口没有 prod gate | `cmd/server/main.go:92` 到 `cmd/server/main.go:97` 公开 `/api/redis/keys`、`/api/debug/status`、`/api/web/metrics` 等 | 匿名泄露 Redis、配置、模型、请求、错误和运行状态 | 新增 `security.public_debug_enabled`，prod 必须 false；敏感 API 放入受保护管理员组 |
| P0-START-05 | 上游 API Key 可通过 URL query 覆盖 | `internal/handler/openai_handler.go:152` 到 `internal/handler/openai_handler.go:174`；`internal/handler/openai_handler_test.go:13` 到 `internal/handler/openai_handler_test.go:42` 当前测试证明允许覆盖 | key 进入浏览器历史、代理日志、访问日志和错误日志 | 新增 `security.allow_upstream_query_key`，prod 必须 false；dev 显式允许 |
| P1-START-06 | Origin 白名单硬编码占位域名 | `internal/handler/openai_handler.go:36` 到 `internal/handler/openai_handler.go:38` | prod 只能允许 `https://your-app-domain.com`，真实部署不可控 | 新增 `security.allowed_origins`，prod 必填且不能包含占位值 |
| P1-START-07 | 真实 IP 依赖 `ClientIP()`，未配置 Trusted Proxies | `internal/handler/openai_handler.go:72`、`internal/handler/azureai_handler.go:47`；仓库未发现 `SetTrustedProxies` | 反向代理/LB 后 IP 不可信，无法满足真实 IP/所在地目标 | 新增 `security.trusted_proxies`，启动时调用 `r.SetTrustedProxies()` |
| P1-START-08 | prod Redis 地址为空但 Redis enabled 继承基础配置 | `conf/config.yaml:21` 到 `conf/config.yaml:28` 默认 `redis.enabled: true`；`conf/config_prod.yaml:11` 到 `conf/config_prod.yaml:13` 只覆盖 `addr: ""` | 生产 Redis 强依赖不清晰，启动可能 Fatal 或以错误地址尝试连接 | prod 下 Redis enabled 时 `redis.addr` 必填；弱依赖需显式配置 degraded 策略 |
| P1-START-09 | Redis 初始化失败直接 Fatal，缺少强/弱依赖策略 | `internal/service/redis/redis.go:56` 到 `internal/service/redis/redis.go:60` | Redis 短时异常会导致服务不可启动；关闭 Redis 又会让计费/统计/限流退化 | 增加 `redis.required` 或 `dependency.redis_mode`；prod 明确 fail-closed/fail-open |
| P1-START-10 | 生产限流只靠开关，没有 Redis 故障策略 gate | `cmd/server/main.go:117` 到 `cmd/server/main.go:119`；`internal/middleware/rate.go` 已有 Redis 失败降级本地限流 | 多实例下 Redis 异常会破坏全局限流 | prod 需要 `rate_limit.enabled=true` 且明确 Redis 异常策略、告警策略 |
| P1-START-11 | 日志配置已有基础保留策略，仍缺审计和脱敏开关 | `conf/config.go` 中 `logs` 已包含 `root_dir`、`retention_days`、`cleanup_interval`；`conf/config_prod.yaml` 生产默认保留 180 天并每日清理 | 基础磁盘保留风险已降低，但无审计开关、敏感字段脱敏开关和归档压缩策略 | 继续扩展 `audit_enabled`、`redact_sensitive_fields`、归档/压缩配置 |
| P1-START-12 | 缺少 monitor/alert/stats 配置结构 | `rg` 未发现可用 `monitor`、`alert`、`stats` 配置结构；实施计划已列但代码未实现 | 后续面板、钉钉、统计无法由生产配置统一控制 | 增加 `monitor`、`alert`、`stats` 配置，并写 prod 必填/可选校验 |
| P2-START-13 | 启动日志没有配置校验摘要 | `cmd/server/main.go:157` 到 `cmd/server/main.go:162` 只输出 addr/env/jwt/rate/fallback | 运维无法从日志确认生产 gate 是否通过 | 启动日志写 `config_validation_passed`，只输出脱敏摘要 |

## 建议新增配置边界

后续修复阶段建议新增独立 `Security` 配置，避免把生产安全条件散落在 handler 和路由注册里：

```yaml
security:
  public_debug_enabled: false
  public_token_enabled: false
  allowed_origins:
    - "https://app.example.com"
  trusted_proxies:
    - "10.0.0.0/8"
  allow_upstream_query_key: false
  admin_diagnostics_enabled: true
  workspace_audit_retention_days: 180
```

配套 Go 结构应放在 `conf.GlobalConfig` 中，并由 `ValidateProductionConfig()` 统一校验。

## 生产配置校验规则

| 规则 | prod 下要求 | dev 下要求 |
| --- | --- | --- |
| JWT | `jwt.enabled=true` 且 `jwt.secret` 非空、非默认值、长度达标 | 可启用本地调试 secret |
| 测试 token | `security.public_token_enabled=false` | 可显式开启 |
| 公开调试 | `security.public_debug_enabled=false` | 可显式开启 |
| Origin | `security.allowed_origins` 非空，不能是 `https://your-app-domain.com` | 允许 localhost |
| 上游 query key | `security.allow_upstream_query_key=false` | 可显式开启 |
| Trusted Proxy | 真实反代部署必须配置；未配置时日志明确标记 `client_ip_untrusted` | 可为空 |
| Redis | 若 `redis.enabled=true`，`redis.addr` 必填；若关闭，billing/stats/rate limit 必须有降级说明 | 可关闭或本地地址 |
| 限流 | `rate_limit.enabled=true`，并明确 Redis 异常策略 | 可关闭 |
| 日志 | `logs.root_dir`、`retention_days`、`cleanup_interval` 有明确值；后续仍需 `audit_enabled`、`redact_sensitive_fields` | 可用默认值 |
| 告警 | 若 `alert.enabled=true`，钉钉 webhook/secret 按规则配置且日志脱敏 | 可关闭发送但保留日志 |

## 测试门槛

确认进入修复后，Task 1-4 至少需要补以下测试：

| 测试 | 证明内容 |
| --- | --- |
| `TestProdRequiresJWTSecret` | prod 下 `jwt.secret=""` 时 `conf.Load()` 或 `ValidateProductionConfig()` 返回错误 |
| `TestProdRejectsDefaultJWTSecret` | prod 下固定默认 secret 或过短 secret 被拒绝 |
| `TestProdDoesNotRegisterPublicTokenRoute` | prod 路由表没有 `/test/generate-token` |
| `TestProdDoesNotRegisterAnonymousDebugRoutes` | prod 下 `/api/debug/status`、`/api/redis/keys`、`/api/web/metrics` 不在 public 组 |
| `TestProdRequiresAllowedOrigins` | prod 下 Origin 白名单为空或占位域名启动失败 |
| `TestProdRejectsUpstreamQueryKey` | prod 下 `upstream_api_key` / `api_key` query 被拒绝 |
| `TestDevCanExplicitlyAllowUpstreamQueryKey` | dev 下只有显式配置允许时才能 query override |
| `TestTrustedProxiesConfiguredInProd` | prod 配置 trusted proxies 后 `ClientIP()` 来源可信 |
| `TestProdRequiresRedisAddressWhenRedisEnabled` | prod `redis.enabled=true` 且 `addr=""` 启动失败 |
| `TestLogsProductionRetentionConfigured` | prod 缺日志保留天数或审计开关时启动失败 |

## 执行顺序影响

本矩阵对应实施计划 Task 1-4。只有这些启动 gate 和路由安全测试先通过，后续才适合继续实现 Workspace 写入确认、长连接韧性、监控日志、钉钉告警、统计图表和容量压测。

当前生产安全状态不满足进入监控增强阶段的前置条件。
