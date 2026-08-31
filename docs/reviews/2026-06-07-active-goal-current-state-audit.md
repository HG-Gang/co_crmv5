# 长期目标当前状态审计

审计日期：2026-06-07

## 结论

长期目标不能标记完成。当前证据只能证明生产安全边界、Workspace 写入确认、监控快照、后台周期监控日志、基础日志清理调度与 `log_cleanup` 摘要、钉钉容量告警、用户真实 IP/所在地展示、day/week/month 最近窗口统计和压测工具已经分批推进；百万并发、1 秒响应、跨实例长期统计、复合过载告警、容量报告实测数据、完整日志审计闭环和全项目中文详细注释仍未完成。

本文件用于把用户 8 条目标映射到当前源码证据，避免后续把阶段性通过误判为总目标完成。

## 当前已改善的部分

| 项 | 当前证据 | 判断 |
| --- | --- | --- |
| 公开 token/debug 路由收紧 | `cmd/server/main.go:127` 按 `security.public_token_enabled` 决定是否公开 `/test/generate-token`；`cmd/server/main_test.go:14` 覆盖生产路由行为 | Task 1-4 范围已推进 |
| Trusted Proxy | `cmd/server/main.go:91` 配置 `SetTrustedProxies`；`cmd/server/main_test.go:40` 覆盖 `X-Forwarded-For` | Task 1-4 范围已推进 |
| JWT 默认密钥移除 | `internal/middleware/auth.go:120` 解析 JWT secret，空值返回错误；`internal/middleware/auth.go:135` 生成 token 前同样校验 | Task 1-4 范围已推进 |
| user_name 传递 | `internal/middleware/auth.go:88` 读取 claim；`internal/service/session/manager.go:79` 写入会话日志；`internal/service/metrics/metrics.go:733` 返回 metrics | 已有基础，但还未覆盖所有审计事件 |
| Realtime Origin 和 query key 策略 | `internal/handler/openai_handler.go:58` 使用配置化 allowed origins；相关测试在 `internal/handler/openai_handler_test.go:101` | Task 1-4 范围已推进 |

## 用户目标逐项审计

| 用户目标 | 当前状态 | 当前证据 | 未完成门槛 |
| --- | --- | --- | --- |
| 1. 审查 Go + OpenAI WebSocket 架构是否正确，是否能顶住百万并发和 1 秒响应 | 已审查，不能证明满足 | `internal/provider/openai/client_ws.go:210`、`211` 每会话两个有界队列；`internal/provider/openai/client_ws.go:343` 到 `346` 每会话四个主 goroutine；`internal/provider/openai/client_ws.go:315` 附近每会话连接一个上游 Realtime WS；`internal/service/session/capacity.go` 是单进程 atomic 容量 | 需要 Task 6 长连接韧性、Task 11 压测工具和 `docs/production-capacity.md`，并定义 P50/P95/P99 与上游配额 |
| 2. 不合理地方全部列出并支持问题所在 | 第一轮已列出，需随代码变化维护 | 现有矩阵覆盖容量、Workspace、安全、监控、告警、统计、注释：`docs/reviews/2026-06-06-*.md`；当前补充见本文件 | 修复后每个问题需要用测试或运行证据关闭，不能只保留旧审查结论 |
| 3. 待确认后统一修改和修复 | 正在遵守 | 只收到 Task 1-4 明确确认；Task 5-6 仅有确认清单 `docs/reviews/2026-06-07-task5-6-execution-confirmation.md` | 需要用户确认后再改 Task 5-12 业务逻辑 |
| 4. 全部项目代码中文详细注释逻辑、参数和功能 | 未满足 | `internal/service/workspace/workspace.go:142`、`internal/handler/workspace_handler.go:44` 等关键写入边界缺少参数、安全边界和审计语义说明 | 需要按安全边界、热路径、handler 入参、配置字段、WebSocket 状态机逐步补齐，并做编码/乱码扫描 |
| 5. 更详细监控面板，并且所有字段都写日志 | 部分满足 | `internal/service/monitor` 输出统一 monitor 快照；`cmd/server/main.go` 启动 `monitor.StartPeriodicLogger`；`internal/service/metrics/metrics.go` 输出 recent sessions、user_id、user_name、real_ip、ip_location；`web/diagnostics.html` 展示 Go/Redis/OpenAI/Azure/metrics/用户位置/资源统计；2026-06-07 23:06 运行时烟测在只访问 `/health` 的情况下，于 `logs/openai/openai-2026-06-07.log` 写入 `event=monitor_snapshot`、`event_date=2026-06-07`、`instance_id=monitor-smoke-8098` 和 `addr=:8098` 的 `monitor snapshot`；`logger.RedactField`、Web metrics API Key 脱敏和 Workspace content/diff 不落日志已有测试保护 | 仍需跨实例聚合、告警状态汇总、业务缓存命中总量长期化、其他审计事件统一 schema、全入口敏感字段脱敏和所有监控字段稳定写入按天日志 |
| 6. 日志沿用按天记录逻辑 | 基础存在，审计闭环未满足 | `internal/logger/logger.go` 按模型缓存 logger，`dailyFileWriteSyncer` 写入 `{model}-{date}.log` 并支持跨零点轮换；`logger.RedactField` 对 key/token/webhook/secret/value/content/diff 做基础脱敏；`internal/service/monitor/sampler.go` 会周期写 monitor 快照；`internal/logger/logger.go` 已提供 `StartCleanupScheduler`、`CleanExpiredLogs` 和 `WriteLogCleanupAudit`；`cmd/server/main.go` 已按 `logs.retention_days` 和 `logs.cleanup_interval` 启动清理调度；`internal/logger/logger_test.go` 覆盖跨零点轮换、脱敏 helper、过期日志删除、`log_cleanup` 摘要、相对路径和 context 取消退出 | 需要统一 `event` schema、全入口敏感字段脱敏、归档压缩，以及 audit/stats/alert 事件长期可对账 |
| 7. 系统过载预警，使用钉钉机器人通知 | 部分满足 | `internal/service/alert/dingtalk.go` 实现钉钉 webhook、签名、冷却和发送结果审计；`internal/handler/alert_helper.go` 在容量拒绝时发送告警；告警包含 user、remote_addr、ip_location；成功写 `alert_firing`、`dingtalk_sent`，失败写 `dingtalk_failed`，测试覆盖 webhook 脱敏 | 仍需覆盖更多队列持续满、错误率升高、Redis 异常、OpenAI 重连失败、内存/GC 超阈值和恢复通知 |
| 8. 天、周、月资源统计图 | 部分满足 | `internal/service/stats` 已提供进程内 day/week/month 统一聚合；Realtime `response.done`、Responses/Web 请求、容量拒绝、限流拒绝、运行错误、告警触发、Workspace 写入审计和业务缓存 hit/miss 资源事件已写入同一口径；`conf.GetModel` 已把模型配置缓存 hit/miss 接入 stats；`/api/stats/resources` 提供独立查询 API；`web/diagnostics.html` 已改用该 API 展示三组资源统计图 | 当前仍是进程内内存统计；仍需跨实例长期 Redis/DB stats、更多真实业务缓存生产者和更完整的告警恢复状态 |

## 当前最高优先级风险

### P0-01 Workspace 仍可直接落盘

证据：

- `internal/handler/workspace_handler.go:44` 是 HTTP 写入口。
- `internal/handler/workspace_handler.go:50` 直接调用 `workspace.Write(...)`。
- `internal/provider/openai/tool_execution.go:376` 处理 `workspace_write_file`。
- `internal/provider/openai/tool_execution.go:377` 模型工具直接调用 `workspace.Write(...)`。
- `internal/service/workspace/workspace.go:142` 定义 `Write(...)`。
- `internal/service/workspace/workspace.go:160` 最终调用 `os.WriteFile(...)`。

影响：

模型工具、前端手动保存或被 prompt 注入诱导的工具调用都可能直接修改当前项目文件。这个问题必须在进入“允许模型更改项目文件”的目标前修复。

### P0-02 Realtime 背压和关键事件语义仍不足

证据：

- `internal/provider/openai/client_ws.go:793` `enqueueOpenAIOutbound` 处理上游写队列。
- `internal/provider/openai/client_ws.go:800` 队列满后等待超时并返回错误。
- `internal/provider/openai/client_ws.go:1158` `safeSend` 处理 App 下行队列。
- `internal/provider/openai/client_ws.go:1164` 下行队列满后等待超时。
- `internal/provider/openai/client_ws.go:1165` 之后记录并丢弃消息。

影响：

队列是有界的，这是必要保护；但当前关键事件和可丢弃事件没有明确分级。`response.done`、`error`、`reconnect_required`、workspace tool result 这类关键事件如果和文本/音频 delta 用同一策略，会导致客户端看不到完整状态。

### P0-03 OpenAI Ping 配置与实现仍不一致

证据：

- `internal/provider/openai/config.go:295` 有 `GetApiPingInterval()`。
- `internal/provider/openai/client_ws.go:496` 只看到 App WS 的 `websocket.PingMessage`。
- 当前未发现基于 `GetApiPingInterval()` 的 OpenAI 上游 Ping ticker。

影响：

配置存在但未驱动真实上游 Ping，会造成诊断页面和运维判断偏差。Task 6 需要二选一：实现 OpenAI Ping ticker，或删除该配置和页面展示。

### P0-04 metrics 热路径全局锁仍是容量风险

证据：

- `internal/service/metrics/metrics.go:29` `collector` 使用单个 `sync.Mutex`。
- `internal/service/metrics/metrics.go:342`、`354`、`393`、`410` 等 WebSocket 热路径事件进入同一把锁。
- `internal/service/metrics/metrics.go:659` 附近 `Snapshot()` 同样需要读取全局状态。

影响：

在高并发下，业务链路、心跳、队列水位、OpenAI 事件和诊断页面轮询会争用同一把锁。百万并发目标必须把高频计数拆成 atomic 或分片指标，不能让监控反向拖慢业务。

## 下一步执行顺序

在用户确认继续修复前，不应直接修改 Task 5-12 业务逻辑。确认后建议按以下顺序：

1. Task 5：Workspace pending diff、confirm、reject、audit，先关掉直接落盘风险。
2. Task 6：Realtime 集中关闭、关键事件投递、背压分级、OpenAI Ping。
3. Task 7：monitor snapshot、真实 IP/所在地、PID/FD/socket、按天日志。
4. Task 8：钉钉过载告警。
5. Task 9：day/week/month stats。
6. Task 10：诊断面板图表。
7. Task 11：WebSocket 压测工具和容量报告。
8. Task 12：中文注释与编码防回归。

## 最新容量审查补充

当前容量审查见 `docs/reviews/2026-06-07-realtime-capacity-current-review.md`。该文件按当前源码重新列出每会话资源模型、已具备能力和仍不能声明百万并发的原因，避免继续引用 2026-06-06 旧矩阵中的过期状态。

## 当前完成判定

总目标保持未完成。当前最接近可执行的下一批是 Task 5-6，已有确认清单和 TDD 计划，但仍需要用户明确确认后再改业务逻辑。

## 2026-06-07 补充：Web 统计日志落点

本轮新增了 Responses/Web 看板统计的按天日志出口，但不改变“总目标未完成”的判定：

- `internal/handler/web_metrics_handler.go` 在每条 Web/Responses 请求进入最近窗口后发出 `web_request_metric` 审计事件，字段包含模型、状态、输入/输出/缓存/推理/总 token、费用、首包耗时、总耗时、脱敏 API Key、endpoint、reasoning effort、User-Agent 和错误文本。
- `internal/handler/web_metrics_handler.go` 在 `/api/web/metrics` 生成 `charts.resources.day/week/month` 后发出 `stats_rollup` 审计事件，日志内包含三组资源聚合。
- `internal/handler/web_metrics_handler_test.go` 新增 `TestAddWebRequestRecordWritesAuditLogEvent` 和 `TestWebMetricsHandlerWritesStatsRollupAuditEvent`，证明上述两个审计事件可以被测试捕获。

仍未完成的部分：这些统计已经开始进入统一 stats service，但仍然是进程内内存统计，不是跨实例、可长期保留的 Redis/DB stats；百万并发与 1 秒响应仍缺真实压测和上游配额证据。

## 2026-06-07 补充：后台 monitor snapshot 日志落点

本轮复核了后台监控采样链路，并补充运行时证据：

- `cmd/server/main.go` 在服务启动后创建 `monitorCtx`，调用 `monitor.StartPeriodicLogger(monitorCtx, time.Now(), 30*time.Second)`，停机时取消并等待采样协程退出。
- `internal/service/monitor/sampler.go` 会先立即调用一次 `writePeriodicSnapshot`，随后按 interval 周期采集；每次采集会调用 `LogSnapshotThrottled` 和 `AlertSnapshotOverload`。
- `internal/service/monitor/monitor_test.go` 已覆盖“无 debug 请求也会写周期 monitor snapshot”和“采样后评估过载告警”。
- 2026-06-07 23:06 本地 8098 烟测设置 `TOZO_INSTANCE_ID=monitor-smoke-8098` 且只访问 `/health`，当天日志 `logs/openai/openai-2026-06-07.log` 出现 `monitor snapshot`，其中包含 `event=monitor_snapshot`、`event_date=2026-06-07`、`instance_id=monitor-smoke-8098`，`server.addr` 为 `:8098`，`process.pid` 为真实 Go server 进程。

仍未完成的部分：monitor snapshot 已有统一 `event/event_date/instance_id` 字段，日志清理调度、`log_cleanup` 摘要、跨零点长生命周期 logger 轮换、基础敏感字段脱敏 helper 和钉钉触发/发送结果日志已有实现；跨实例聚合、其他审计事件统一 schema、全入口敏感字段脱敏、归档压缩和恢复告警仍需继续补齐。

## 2026-06-07 补充：统一 stats service 初始切片

本轮新增 `internal/service/stats`，开始把 Realtime WebSocket 和 HTTP Responses 的资源用量收敛到同一统计口径：

- `stats.RecordUsage` 接收统一 `UsageRecord`，字段覆盖 source、provider、model、user、status、token、cached/reasoning、音频时长、费用和延迟。
- `stats.ResourcePeriods` 输出 `day/week/month` 三个窗口，汇总 requests、failed_requests、token、cached/reasoning、费用、平均延迟，并保留 `by_source` 与 `by_model`。
- `OpenAI response.done` 已写入 `SourceRealtime`；`addResponsesMetric` 已写入 `SourceResponses`。
- `/api/web/metrics` 的 `charts.resources` 已切换到 `stats.ResourcePeriods`，因此诊断页资源图可以看到 Realtime 与 Responses 的统一窗口数据。
- 新增测试覆盖 `stats` 聚合、Responses 接入、Realtime response.done 接入，以及 WebMetricsHandler 使用统一 stats 资源图。

仍未完成的部分：该服务当前仍是进程内内存窗口，不是跨实例、可长期保留的 Redis/DB stats；业务缓存命中/未命中已有 stats 字段和资源事件口径，模型配置缓存已作为第一个真实生产者写入，但 GeoIP、Workspace 文件缓存和响应缓存等更多业务生产者仍未接入；告警恢复还没有真实状态机事件。

## 2026-06-07 补充：统一 stats 多资源事件切片

本轮继续扩展 `internal/service/stats` 的进程内统计口径：

- 新增 `stats.ResourceEvent` / `stats.RecordResourceEvent`，支持不带 token 的运行资源事件。
- `ResourceSummary` 与 `ResourceTimelinePoint` 新增 `capacity_rejected`、`rate_limit_rejected`、`errors`、`alerts_firing`、`alerts_recovered`、`workspace_write_pending`、`workspace_write_confirmed`、`workspace_write_rejected`、`workspace_write_failed` 和 `by_kind`。
- `metrics.CapacityRejected`、`RateLimitRejected`、`OpenAIError`、`BillingError`、`APISendQueueTimeout`、`CriticalAppEventQueueTimeout` 已写入统一 stats。
- `alert.NotifyOverload` 在钉钉 webhook 成功返回 2xx 后记录 stats `alert_firing`，并写按天日志 `alert_firing` / `dingtalk_sent`；失败时写 `dingtalk_failed`。
- `workspace` 写入审计在 preview/confirm/reject/fail 时记录 Workspace 资源事件；即使当前日志目录未配置，也会进入进程内 stats。

仍未完成的部分：该切片没有解决长期持久化、跨实例聚合和业务缓存命中统计；`alert_recovered` 只是 stats 字段和 API 口径，当前监控还没有清晰恢复事件可接入。

## 2026-06-07 补充：独立 stats resources API

本轮补齐了独立统计查询入口：

- 新增 `internal/handler/stats_handler.go`，提供 `GET /api/stats/resources?period=day|week|month&model=...&source=...&kind=...`。
- `StatsResourcesHandler` 返回 `periods.day/week/month`、当前 `selected` 窗口、`filters` 和 `generated_at`。
- `internal/service/stats` 新增 `ResourcePeriodsWithFilter`，支持按 source/model/kind 过滤；model 会同时匹配 `Model` 和 `Provider`。
- `cmd/server/main.go` 已把 `/api/stats/resources` 注册到 debug 路由组；生产默认不匿名公开。
- `web/diagnostics.html` 的资源统计图已从 `/api/web/metrics` 切换到 `/api/stats/resources`，并展示容量拒绝、限流、错误、告警和 Workspace 写入计数。

仍未完成的部分：API 当前读取的是进程内 collector，重启丢失且多实例不能聚合；业务缓存命中/未命中事件口径已接入 API 和 `stats_rollup` 日志，模型配置缓存生产者已接入，但更多业务缓存生产者与告警恢复事件仍未接入。
