# Go + OpenAI Realtime WebSocket 架构审查报告

审查日期：2026-06-06

审查范围：当前仓库中的 Go WebSocket 网关、OpenAI/Azure Realtime 接入、Redis/Session/限流/计费/监控、Web 调试页面、Workspace 文件修改能力、日志和统计实现。

## 结论

当前项目不能证明“百万并发 + 1 秒内稳定响应”。现有实现适合作为本地调试、开发验证和中小规模 Realtime 网关雏形；生产百万并发必须依赖多实例集群、负载均衡、Redis/指标分片、OpenAI 或第三方中转配额、系统参数调优和真实压测报告共同证明。

当前阶段已完成“审查并列出不合理点”。按用户要求，在明确确认前不进入统一修改和修复阶段。

## 官方依据

- OpenAI Realtime WebSocket 是服务端到 OpenAI Realtime API 的连接形态，生产系统应避免把 API Key 暴露给不可信客户端。
- OpenAI API 生产最佳实践要求安全保存 API Key、控制访问面、规划限流和高流量架构。
- OpenAI 速率限制按组织、项目和模型生效，百万并发必须纳入上游配额模型。

参考：

- https://platform.openai.com/docs/guides/realtime-websocket
- https://platform.openai.com/docs/guides/production-best-practices
- https://platform.openai.com/docs/guides/rate-limits/usage-tiers

## P0 必须先修

### 1. 单会话直连一条上游 WebSocket，不能单机百万并发

证据：

- `internal/provider/openai/client_ws.go:284` 使用 `dialer.DialContext` 为当前会话建立上游 Realtime WebSocket。
- `internal/provider/openai/client_ws.go:342-346` 每个会话启动 4 个主 goroutine：读 App、写 OpenAI、读 OpenAI、写 App。
- `internal/provider/openai/client_ws.go:82-86` 每个会话有两个 512 缓冲队列。
- `conf/config.yaml:47` 单实例 `max_active_sessions` 是 100000，不是百万。
- `internal/service/session/capacity.go:11-32` 容量控制是单进程计数，不是集群容量控制。

影响：

- 百万 App 会话会对应百万上游连接和数百万 goroutine 级别调度压力。
- 单机文件描述符、内存、网络带宽、Redis、日志、OpenAI/中转配额都会成为瓶颈。

建议：

- 明确单实例容量上限。
- 增加压测工具和容量报告。
- 使用多实例、负载均衡、集群级准入控制和上游配额规划。

### 2. 公开调试路由暴露敏感能力

证据：

- `cmd/server/main.go:61-89` public group 注册无需 JWT 和限流的调试接口。
- `cmd/server/main.go:62-75` `/test/generate-token` 可生成任意用户 token。
- `cmd/server/main.go:77-82` `/api/redis/keys`、`/api/debug/status`、`/api/web/models`、`/api/web/metrics`、`/api/azure/status` 公开。
- `internal/handler/redis_handler.go:31` 支持 `full=1` 拉完整 Redis value。

影响：

- 生产环境可能泄露 Redis 业务 key、会话元数据、计费数据、运行状态。
- 攻击者可自助生成 token 并访问受保护接口。

建议：

- 生产禁用 `/test/generate-token`。
- 调试接口仅 dev 开启；生产必须鉴权或内网/IP 白名单。
- Redis 明细接口默认禁止 `full=1`，生产必须关闭。

### 3. JWT 默认密钥兜底不安全

证据：

- `internal/middleware/auth.go:36-39` JWT secret 为空时回退 `default-jwt-secret-123456`。
- `internal/middleware/auth.go:137-141` GenerateToken 同样回退默认 secret。
- `conf/config_prod.yaml:15-17` 生产 JWT secret 默认为空。

影响：

- 生产误配置时会用固定默认密钥启动，任何人可伪造 token。

建议：

- 生产环境 JWT secret 为空必须启动失败。
- 移除默认密钥兜底。
- JWT claim 增加用户名称字段，便于监控展示。

### 4. 上游 API Key 允许通过 URL query 传入

证据：

- `internal/handler/openai_handler.go:139-157` 读取 `upstream_api_key` / `api_key` query 参数覆盖配置。
- `web/index.html:159`、`web/chat.html:711` 前端会把 token/key 类参数拼入连接 URL。

影响：

- API Key 容易进入浏览器历史、代理日志、接入日志、错误日志。

建议：

- 生产禁用 query key override。
- 第三方中转 key 改为服务端配置或一次性加密凭证。

### 5. 全局 metrics mutex 会成为高并发热锁

证据：

- `internal/service/metrics/metrics.go:34` 使用全局 `sync.Mutex`。
- `internal/service/metrics/metrics.go:233-650` 大量高频事件持有全局锁。
- `internal/service/metrics/metrics.go:657-700` `Snapshot()` 也持有全局锁。

影响：

- 高频 App/OpenAI 事件、监控轮询和错误记录会争同一把锁。
- 高并发下指标系统可能反过来拖慢业务路径。

建议：

- 高频计数改为 atomic 或分片结构。
- 最近会话/响应保留逻辑与总量计数分离。
- 跨实例指标写 Redis/Prometheus/日志平台。

### 6. Workspace 工具允许模型直接写文件

证据：

- `internal/provider/openai/tool_execution.go:376-383` `workspace_write_file` 工具调用会直接写文件。
- `internal/service/workspace/workspace.go:142-160` 创建目录并 `os.WriteFile`。
- `internal/service/workspace/workspace.go:13-15` 只限制 512 KiB 和列表数量。
- `internal/service/workspace/workspace.go:178-196` 有根目录逃逸防护，但没有确认、diff、回滚。
- `docs/reviews/2026-06-06-workspace-write-safety-matrix.md` 已单独列出模型工具写入、HTTP 写接口、前端保存、审计、回滚和敏感路径策略缺口。

影响：

- 模型输出错误或 prompt 注入时可改项目文件。
- 缺少审计记录和用户确认。

建议：

- 写文件改成 pending diff。
- 用户确认后再写。
- 写入前后记录审计日志和可回滚快照。

## P1 生产稳定性与观测缺口

### 7. Origin 校验硬编码

证据：

- `internal/handler/openai_handler.go:29-31` dev 放开，prod 只允许 `https://your-app-domain.com`。

建议：

- Origin 白名单配置化。
- dev 允许 localhost，prod 必须显式配置。

### 8. 真实 IP 不可靠

证据：

- `internal/handler/openai_handler.go:61` 使用 `c.ClientIP()`。
- `internal/handler/azureai_handler.go:47` 使用 `c.ClientIP()`。
- 全仓库未发现 `SetTrustedProxies`。

影响：

- 负载均衡或反向代理后无法可靠得到真实用户 IP。
- “真实 IP 和所在地”目标无法完成。

建议：

- 配置 trusted proxies。
- 采集 `X-Forwarded-For` / `X-Real-IP` 必须基于可信代理。
- 增加 IP 地理位置解析和日志字段。

### 9. Redis 限流降级会破坏集群限流

证据：

- `internal/middleware/rate.go:136-147` Redis 不可用或操作失败时降级本地限流。
- `internal/middleware/rate.go:158-170` Redis 计数超过 burst 后才全局拒绝。

影响：

- Redis 不可用时多实例全局限流失效。

建议：

- 区分 dev 降级和 prod fail-closed/fail-open 策略。
- 生产应有告警和熔断策略。

### 10. Redis 启动失败直接 Fatal

证据：

- `internal/service/redis/redis.go:59-60` `Ping` 失败直接 `Fatal`。

影响：

- Redis 短暂不可用会导致服务启动失败。

建议：

- 生产按配置决定是否强依赖 Redis。
- 强依赖时输出清晰启动失败；弱依赖时启动但标记 degraded，并触发告警。

### 11. 缺少钉钉过载告警

证据：

- 全仓库未发现 `dingtalk`、`DingTalk`、`webhook`、`alert`、`overload` 实现。
- `internal/handler/openai_handler.go:89-94` 过载只返回 503。
- `internal/handler/azureai_handler.go:64-69` Azure 过载也只返回 503。
- `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md` 已单独列出容量拒绝、限流、Redis 异常、OpenAI 重连失败、冷却恢复和日志落点缺口。

建议：

- 增加告警配置、阈值、冷却、恢复通知。
- 接入容量拒绝、Redis 异常、OpenAI 重连失败、错误率升高、内存过高。

### 12. 监控面板仍缺目标字段

已有：

- `internal/handler/debug_handler.go:37-44` 输出 server、memory、capacity、features、redis、network、openai、responses、azure、metrics。
- `web/diagnostics.html:145-257` 展示运行时、Redis、OpenAI、Azure、业务 token、错误等。

缺失：

- PID、FD/socket 数量、系统进程数。
- 真实 IP 和所在地。
- 用户名称。
- 业务缓存命中总量。
- 钉钉告警状态。
- 监控快照按天落日志的确认字段。

### 13. 统计只有 daily 为主，没有 day/week/month 完整聚合

证据：

- `internal/service/billing/billing.go:220-237` 写 `billing:daily` 和 `billing:daily_detail`。
- 未发现 `billing:weekly`、`billing:monthly` 或通用 stats day/week/month service。
- `internal/service/metrics/metrics.go:124` 只有 `TokensByDay`。
- `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md` 已单独列出 Realtime billing、Responses metrics、Redis billing 页面、业务缓存命中和成本模型缺口。

建议：

- 新增 stats service。
- 每次 response、session、错误、告警、workspace 写入都写 day/week/month。
- Web 图表支持天/周/月切换。

### 14. Web 请求看板数据仅内存 500 条

证据：

- `internal/handler/web_metrics_handler.go:19` `maxWebRequestRecords = 500`。
- `internal/handler/web_metrics_handler.go:21-25` 使用进程内 slice。

影响：

- 重启丢失。
- 多实例不可聚合。
- 不能作为长期统计和审计依据。

建议：

- 保留内存最近记录用于调试。
- 长期统计写 Redis/数据库/日志平台。

## P2 维护性与验证缺口

### 15. 旧事件处理器死代码

证据：

- `internal/provider/openai/events_client.go:23-80` 定义 `ClientEventProcessor`。
- `internal/provider/openai/events_server.go:26-104` 定义 `ServerEventProcessor`。
- 全仓库未发现当前主链路引用 `NewClientEventProcessor` / `NewServerEventProcessor`。

影响：

- 容易误导维护者，以为事件处理走这些文件。

建议：

- 删除或合并到当前 `client_ws.go` / `gateway_protocol.go` 主链路。

### 16. 中文注释覆盖不均，需建立补齐与编码防回归清单

证据：

- 以 UTF-8 读取 `cmd`、`conf`、`internal`、`web`、`README.md` 后，未发现 `锛`、`鍙`、`鏃`、`閰`、`鈥` 等典型 mojibake 片段。
- `cmd/server/main.go`、`internal/provider/openai/client_ws.go`、`internal/service/metrics/metrics.go`、`web/index.html`、`web/ws-test.js` 等核心文件已有中文说明，但详细程度不一致。
- 部分关键接口、配置字段、Web 页面脚本和工具调用链路仍未逐一解释参数含义、状态流转和边界条件。
- `docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md` 已列出 Workspace、Web 静态主题注入、Web metrics、聊天看板、主题脚本和测试文件的具体注释缺口。

影响：

- 仍不满足“所有项目代码必须中文详细注释逻辑含义、参数含义和功能作用”的目标。
- 如果在大规模修复时不建立规则，容易出现重复注释、过时注释或终端编码误判。

建议：

- 先要求所有新增和修改文件使用 UTF-8，并补齐关键函数、配置结构、handler 入参、WebSocket 事件和监控字段说明。
- 建立全仓库中文注释补齐清单，按生产安全、Realtime 主链路、监控告警统计、Web 页面、配置文档分阶段处理。
- 保留 mojibake 扫描命令作为防回归验证，避免把终端编码展示问题误判为文件损坏。

### 17. 测试通过但覆盖不足

已执行：

- `go test ./...` 通过。
- `go test ./... -cover` 通过。

覆盖缺口：

- `internal/middleware` 0.0%
- `internal/service/billing` 0.0%
- `internal/service/metrics` 0.0%
- `internal/service/redis` 0.0%
- `internal/service/session` 0.0%
- `internal/service/workspace` 0.0%
- `internal/provider/azureai` 0.0%
- `internal/provider/openairesponses` 0.0%

影响：

- 当前测试只能证明部分协议适配、配置、工具调用和静态页面注入逻辑。
- 不能证明生产安全、监控、计费、告警、容量和长连接稳定性。

建议：

- 安全、限流、告警、统计、Workspace 写文件、监控 handler 都要补测试。
- 增加 WebSocket 压测工具和容量报告。

### 18. 没有容量证明体系

证据：

- 未发现 `wrk`、`vegeta`、`k6`、`locust`、自研 WebSocket load tool。
- 未发现 pprof 注册。
- 未发现 FD/socket 指标。
- README 和历史报告明确写明百万并发不能靠单机完成。

建议：

- 新增 `tools/wsload`。
- 新增 `docs/production-capacity.md`。
- 容量验收必须包含实例数、每实例连接数、LB、Redis、OpenAI/中转配额、P95/P99 延迟、错误率。

### 19. Realtime 协议兼容和第三方中转缺少端到端证明

证据：

- `conf/config.yaml:57-70` 和 `conf/models/openai.yaml:8-33` 当前默认模型是 `gpt-realtime`，默认上游地址是第三方中转 `wss://dxb.huifei.net/v1/realtime`。
- `internal/provider/openai/config.go:133-167` 会为 OpenAI 风格 URL 自动补 `?model=...`，并使用 `Authorization: Bearer ...`；Azure 分支使用 `api-key`。
- `internal/handler/openai_handler.go:152-170` 允许通过 query 覆盖 `upstream_ws_url`、`upstream_api_key` 和 `upstream_model`。
- `internal/provider/openai/gateway_protocol.go:424-447` Go 主链路已构造 GA 风格 `session.update`。
- `web/audio.html:708-721` 语音测试页仍发送旧版 `modalities`、`input_audio_format`、`output_audio_format` 和顶层 `turn_detection` 字段。

影响：

- 第三方中转只有在完整支持 OpenAI Realtime WebSocket 长连接、事件协议、工具调用、音频流、错误事件和限流语义时才可用；普通 HTTP Chat/Completions 中转不能直接复用。
- 前端 query 传上游地址、模型和 Key 会扩大生产暴露面，也会让协议兼容问题和安全问题混在一起。
- 语音测试页与后端主链路事件结构不一致，可能导致“页面失败”被误判为 Go WebSocket 主链路失败。

建议：

- 把第三方中转分为“Realtime WebSocket 兼容中转”和“普通 HTTP 中转”两类配置。
- 修复阶段先收紧上游 Key 策略，再补协议冒烟测试。
- 统一 `web/audio.html`、`web/ws-test.js`、`web/chat.html` 的 `session.update` 字段结构。
- 不贸然替换默认模型；确认后基于官方文档和中转商能力表统一升级。

### 20. 长连接运行时韧性和背压处理不能证明 1 秒内稳定响应

证据：

- `internal/provider/openai/client_ws.go:388-455` App 断开后 `readPump` 调用 `cancel()`，但 `internal/provider/openai/client_ws.go:625-694` 中 `recvPump` 进入 `conn.ReadMessage()` 后不会被 `ctx.Done()` 直接打断。
- `internal/provider/openai/client_ws.go:341-348` 会等待 4 个主协程全部退出，handler 中 `internal/handler/openai_handler.go:89-97` 的容量释放要等 `sess.Start()` 返回。
- `internal/provider/openai/client_ws.go:1156-1171` App 下行队列满时等待 `send_queue_timeout_ms` 后直接丢弃消息。
- `internal/provider/openai/client_ws.go:793-803` OpenAI 上行队列满时返回 `openai outbound queue full`。
- `conf/config.yaml:78` 配置了 `api_ping_interval`，`internal/provider/openai/config.go:295-302` 有读取函数，但仓库只看到 `internal/provider/openai/client_ws.go:496` 向 App 发送 Ping，没有看到向 OpenAI 周期发送 Ping。

影响：

- App 已断开时，上游读协程可能阻塞到 `api_read_timeout/api_pong_timeout`，导致会话和容量释放延迟。
- 慢消费者会造成下行消息丢失，文本、音频、工具结果或错误事件可能不完整。
- 上游队列满和重连路径默认会超过严格的 1 秒口径。
- 配置和实现不一致会误导运维判断上游半开连接检测能力。

建议：

- App 断开或 context cancel 时主动关闭上游连接，让 OpenAI 读协程立即退出。
- 区分可丢弃流式片段和不可丢弃控制事件，关键事件队列满时应关闭会话或返回明确错误。
- 明确实现或移除 `api_ping_interval` 语义。
- 增加断网、慢客户端、上游半开、代理卡死和高频音频输入压测。

## 已形成的实施计划

已保存统一修复实施计划：

- `docs/superpowers/plans/2026-06-06-realtime-production-hardening.md`

补充审查证据：

- `docs/reviews/2026-06-06-production-exposure-matrix.md`：生产暴露面与路由安全矩阵，逐项列出公开路由、鉴权/限流状态、暴露内容和修复建议。
- `docs/reviews/2026-06-06-production-startup-security-readiness-matrix.md`：生产启动与配置安全就绪矩阵，明确 prod JWT、公开调试、Origin、Trusted Proxy、上游 query key、Redis 和日志配置的启动 gate。
- `docs/reviews/2026-06-06-observability-gap-matrix.md`：监控、日志、告警、统计能力差距矩阵，逐项映射已有字段、缺口和后续实现任务。
- `docs/reviews/2026-06-06-monitoring-log-audit-matrix.md`：监控字段与按天日志落点审计矩阵，明确哪些字段已写日志、只在 Redis、只在内存/API 返回或完全缺失。
- `docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md`：按天日志、跨零点轮换、日志清理调度、审计事件 schema、敏感字段脱敏和面板字段持久化就绪矩阵。
- `docs/reviews/2026-06-06-capacity-readiness-matrix.md`：百万并发与 1 秒延迟容量就绪矩阵，拆解每会话资源模型、缺失压测证据和验收口径。
- `docs/reviews/2026-06-06-realtime-protocol-compatibility-matrix.md`：Realtime 协议兼容、Azure URL/Header、旧版语音页字段和第三方中转风险矩阵。
- `docs/reviews/2026-06-06-runtime-resilience-backpressure-matrix.md`：长连接生命周期、容量释放、队列背压、重连恢复和 OpenAI Ping 配置一致性矩阵。
- `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md`：统计、计费、缓存命中和成本模型就绪矩阵，明确 daily billing 已有能力与 day/week/month 缺口。
- `docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md`：中文注释覆盖、注释质量和 UTF-8 编码防回归矩阵。
- `docs/reviews/2026-06-06-source-commentary-coverage-inventory.md`：源码中文注释覆盖文件级清单，记录零中文注释、低注释覆盖和 Task 12 优先级。
- `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md`：系统过载预警、钉钉机器人通知、冷却恢复和告警日志落点矩阵。
- `docs/reviews/2026-06-06-workspace-write-safety-matrix.md`：Workspace 文件修改、模型工具直接写入、pending diff、确认、审计和回滚就绪矩阵。
- `docs/reviews/2026-06-06-completion-acceptance-gates.md`：主目标 8 条需求的完成验收门槛，明确哪些证据缺失时不能声明目标完成。
- `docs/reviews/2026-06-06-test-quality-gates-readiness-matrix.md`：测试覆盖、覆盖率缺口、关键包 0 覆盖和各阶段质量门槛矩阵。
- `docs/reviews/2026-06-06-current-p0-evidence-snapshot.md`：当前工作区 P0/P1 问题源码行号快照，用于修复前校准旧报告中可能漂移的证据位置。
- `docs/reviews/2026-06-06-pre-fix-confirmation-brief.md`：统一修复确认前收口索引，汇总 8 条目标状态、第一阶段 Task 1-4 范围和验收命令。

建议执行顺序：

1. 生产安全配置与启动校验。
2. 公开路由收紧与 Trusted Proxy。
3. JWT 默认密钥移除与用户名称采集。
4. Realtime Origin 与上游 Key 策略。
5. Workspace 写文件预览、确认与审计。
6. 长连接生命周期、背压和重连韧性修复。
7. 监控数据采集与日志落点。
8. 钉钉过载告警。
9. 天/周/月统计聚合。
10. 监控面板与图表。
11. 压测工具与容量报告。
12. 中文注释补齐与编码防回归。

## 等待确认

确认进入修复阶段后，建议先执行实施计划中的 Task 1-4。未确认前不应修改业务代码。

确认前可直接参考 `docs/reviews/2026-06-06-pre-fix-confirmation-brief.md` 中的建议确认语句和第一阶段验收命令。
