# 监控、日志、告警、统计能力差距矩阵

审查日期：2026-06-06

范围：当前 Go 进程指标、Redis 计费统计、按天日志、Web 诊断看板、Redis 监控页，以及用户要求的详细监控面板、全量日志落点、钉钉过载告警、天/周/月统计图。

## 总体结论

当前项目已经有一套“开发调试级”并向生产观测演进的能力：`metrics.Snapshot()` 保存当前进程内的 App/Go/OpenAI/业务/错误指标，`billing` 写入 Redis daily token 和音频时长，`logger` 按模型和日期写日志并已有基础清理调度，`monitor` 会周期写快照，`web/diagnostics.html` 能展示 runtime、内存、Redis、OpenAI、Azure、链路统计和最近会话。

但它还没有达到用户目标中的“生产详细监控面板 + 全部信息写日志 + 钉钉过载告警 + 天/周/月资源统计图”。主要缺口已经从“没有服务”转为“未闭环”：monitor/alert/stats 已有初始切片，日志清理已有 `log_cleanup` 摘要，但跨实例长期统计、完整告警恢复、统一审计 schema、归档压缩、敏感字段脱敏、真实 IP/所在地和用户名称全链路覆盖仍不完整。

## 已有能力证据

### 1. 进程内实时链路指标

证据：

- `internal/service/metrics/metrics.go:31-39` 定义当前进程级 collector，包含 app、go、openai、errors、business、sessions。
- `internal/service/metrics/metrics.go:42-63` App 侧连接、断开、消息、字节、心跳、慢消费者、JSON 错误。
- `internal/service/metrics/metrics.go:65-72` Go 队列、容量拒绝、队列超时。
- `internal/service/metrics/metrics.go:75-105` OpenAI 连接、重连、事件、响应、流式文本/音频、延迟。
- `internal/service/metrics/metrics.go:108-124` 错误和业务 token、音频、限流、billing 错误。
- `internal/service/metrics/metrics.go:655-700` `Snapshot()` 导出调试页面 JSON。

限制：

- 指标只在当前进程内存中，重启丢失。
- 多实例不可聚合。
- 所有热路径都经过全局 `sync.Mutex`。
- monitor snapshot 已能定时写入按天日志，但 metrics 其他事件还没有全量统一审计 schema。
- day/week/month 聚合已有进程内初始切片，但不是跨实例长期统计。

### 2. 会话元数据和 Redis session

证据：

- `internal/service/session/manager.go:107` 会话启动时调用 `metrics.SessionStarted(...)`。
- `internal/service/session/manager.go:111-123` 写入 Redis `session:{id}`，包含 `user_id`、`device_id`、`request_id`、`model`、`remote_addr`、`user_agent`、`start_time`、`status`、`max_ttl`。
- `internal/service/session/manager.go:171-174` 关闭时写 `status=closed`、`end_time`。

限制：

- 没有用户名称字段。
- `remote_addr` 来自 `c.ClientIP()`，但当前没有配置 trusted proxies，真实 IP 不可靠。
- 没有 IP 所在地解析。
- Redis session 不是长期统计结构。

### 3. Billing daily 统计

证据：

- `internal/service/billing/billing.go:71-87` 音频时长写用户模块维度和 `billing:daily_duration:{model}:{date}`。
- `internal/service/billing/billing.go:203-237` token 明细写 `billing:{model}:{sessionID}`、`billing:daily:{model}:{date}`、`billing:daily_detail:{model}:{date}`。
- `internal/service/billing/billing.go:241-258` response 维度写 `billing:response:{model}:{sessionID}:{responseID}`。
- `internal/service/billing/billing.go:128-142`、`300-315` 提供 daily 查询。

限制：

- 没有 weekly/monthly key。
- 没有通用 `stats` service。
- 没有错误、告警、Workspace 写入、容量拒绝、限流拒绝等多资源维度聚合。
- Redis TTL 当前 32 天，不满足月度/长期统计归档。
- 统计、计费、缓存命中和成本模型的细化证据见 `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md`。

### 4. 按天日志基础能力

证据：

- `internal/logger/logger.go:42-72` `GetModelLogger()` 按模型和日期生成日志文件。
- `internal/logger/logger.go:77-111` 开发环境输出到文件和 stdout，生产输出到文件。
- `internal/logger/logger.go` 有 `StartCleanupScheduler` 和 `CleanExpiredLogs(days, model)`。
- `cmd/server/main.go` 按 `logs.retention_days` 和 `logs.cleanup_interval` 启动日志清理调度。

限制：

- 基础清理调度和 `log_cleanup` 摘要已有，但缺归档压缩策略。
- 监控快照已定时写日志，但其他监控/审计事件未全部统一。
- 没有告警事件统一日志。
- `normalizeModelName("global")` 会优先写入 openai 日志目录，全局日志和模型日志边界不够清晰。
- 按天日志、跨零点轮换、清理调度、审计事件 schema 和敏感字段脱敏的细化证据见 `docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md`。

### 5. Web 诊断看板

证据：

- `web/diagnostics.html:151-177` 展示 Go 运行状态、功能开关、内存、GC、容量。
- `web/diagnostics.html:179-187` 展示 Redis 可用性、连接池、命中/等待。
- `web/diagnostics.html:190-222` 展示 OpenAI Realtime、Responses、Azure 配置。
- `web/diagnostics.html:243-263` 展示链路统计。
- `web/diagnostics.html:265-285` 展示最近会话明细。
- `web/diagnostics.html:332-334` 请求 `/api/debug/status`、`/health`、`/api/redis/keys?pattern=*&count=1000&full=1`。
- `web/diagnostics.html:447-489` 渲染 `metrics.Snapshot()`。

限制：

- 页面调用的 API 当前在 public 组，生产暴露风险高。
- Redis 扫描默认 `full=1`，生产不适合。
- 没有 PID、FD/socket/handle、系统进程数。
- 没有真实 IP 所在地。
- 没有用户名称。
- 没有告警状态。
- 没有天/周/月统计图 tab。
- 没有“监控快照已按天写日志”的落点时间。

## 用户目标差距矩阵

| 目标字段/能力 | 当前状态 | 当前证据 | 缺口 | 修复任务 |
|---|---|---|---|---|
| 在线人数 | 部分已有 | `session.ActiveCount()`、`capacity.active_sessions`、`metrics.recent_sessions` | 多实例不可聚合，缺用户名称和长期在线历史 | Task 7、Task 9 |
| 内存实时使用 | 部分已有 | `debug_handler.go` 读取 `runtime.MemStats`，诊断页展示 Alloc/Heap/Sys/GC | 没有趋势图和定时日志快照 | Task 7、Task 10 |
| 进程数/PID | 未满足 | 当前只展示 Go version、OS/Arch、CPU、goroutines | 缺 PID、系统进程数、FD/socket/handle | Task 7 |
| 实时 token 总览 | 部分已有 | `metrics.business` 和 `billing:daily*` | 多实例不可聚合，缺 week/month，缺用户/模型图表 | Task 9、Task 10 |
| 缓存命中总量 | 部分已有 | Redis pool hits/misses 展示连接池命中 | 缺业务缓存命中/未命中定义和累计 | Task 7、Task 9 |
| 用户真实 IP | 部分已有但不可靠 | `session.remote_addr`、handler 使用 `c.ClientIP()` | 未配置 trusted proxies，代理后不可靠 | Task 2、Task 7 |
| 用户所在地 | 未满足 | 未发现 IP geo 服务 | 缺 `ResolveIP`、provider 配置、缓存和日志字段 | Task 7 |
| 用户 ID | 已有 | JWT sub、session Redis、metrics recent_sessions | 公开诊断接口风险高，多实例聚合不足 | Task 2、Task 7 |
| 用户名称 | 未满足 | JWT claims 当前只有 `sub` | 缺 `user_name` claim、session/metrics/日志字段 | Task 3、Task 7 |
| Go 服务信息 | 部分已有 | Go version、OS/Arch、CPU、goroutines、内存、GC、容量 | 缺 PID、FD/socket、构建版本、启动参数、配置校验状态 | Task 7 |
| OpenAI 信息 | 部分已有 | endpoint、ws_url、model、voice、连接/重连/事件/响应/延迟 | 缺上游限流响应头、配额、错误率窗口、告警阈值 | Task 7、Task 8 |
| 错误信息 | 部分已有 | `metrics.errors`、日志 Warn/Error | 缺错误率窗口、分模块错误中心、按天落日志快照 | Task 7、Task 8 |
| 全部信息写入日志 | 未满足 | 部分业务日志已有，logger 按天写文件 | 缺监控快照定时落日志、告警/统计/Workspace 审计统一日志 | Task 7、Task 8、Task 9 |
| 钉钉过载预警 | 未满足 | 未发现 `internal/service/alert`、DingTalk webhook，细化证据见 `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md` | 缺签名、冷却、恢复通知、过载规则、告警日志和面板状态 | Task 8 |
| 天/周/月统计 | 未满足 | daily token/audio 已有，细化证据见 `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md` | 缺 week/month，缺统一 stats API、业务缓存命中、成本模型和图表 | Task 9、Task 10 |
| 多种资源图表 | 部分已有 | Web metrics 有 timeline，诊断页是数字指标 | 缺天/周/月 tab、折线/柱状图、空状态/非空状态 | Task 10 |

## 建议的监控数据模型

建议新增 `internal/service/monitor`、`internal/service/stats`、`internal/service/alert` 三个边界清晰的服务。

### monitor snapshot

每 30 秒生成一次快照并写按天日志：

- `timestamp`
- `instance_id`
- `pid`
- `go_version`
- `os`
- `arch`
- `num_cpu`
- `goroutines`
- `fd_count` 或 `handle_count`
- `memory.alloc_mb`
- `memory.heap_mb`
- `memory.sys_mb`
- `gc.count`
- `gc.pause_total_ms`
- `sessions.active`
- `sessions.started_total`
- `sessions.ended_total`
- `redis.available`
- `redis.pool_hits`
- `redis.pool_misses`
- `redis.pool_timeouts`
- `openai.connect_success`
- `openai.connect_failures`
- `openai.reconnect_failures`
- `business.total_tokens`
- `business.input_audio_ms`
- `business.output_audio_ms`
- `errors.total`
- `alerts.status`

### stats day/week/month

建议 key：

- `stats:day:{model}:{YYYY-MM-DD}`
- `stats:week:{model}:{YYYY-Www}`
- `stats:month:{model}:{YYYY-MM}`

建议字段：

- `sessions_started`
- `sessions_ended`
- `active_peak`
- `capacity_rejected`
- `rate_limit_rejected`
- `openai_connect_failures`
- `openai_reconnect_failures`
- `errors_total`
- `input_tokens`
- `output_tokens`
- `total_tokens`
- `cached_tokens`
- `reasoning_tokens`
- `input_audio_ms`
- `output_audio_ms`
- `workspace_write_preview`
- `workspace_write_confirmed`
- `alert_firing`
- `alert_recovered`

### alert rules

建议首批规则：

- 容量使用率超过 80%/90%。
- 内存超过配置阈值。
- Redis ping 连续失败。
- OpenAI 连接失败或重连失败连续超过阈值。
- 最近 1 分钟错误数超过阈值。
- send queue 或 apiSend queue 连续满。
- slow consumer drop 持续增长。

告警链路细化证据和验收口径见 `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md`。

每条规则需要：

- `status`: normal/firing/recovering
- `first_fired_at`
- `last_fired_at`
- `last_recovered_at`
- `cooldown_seconds`
- `dedupe_key`
- `dingtalk_sent`
- `last_error`

## 下一步执行边界

确认进入统一修复后，必须先完成生产安全 Task 1-4，再实现观测能力。原因是当前诊断接口和 Redis 明细接口仍公开，直接增强面板会扩大生产暴露面。

推荐顺序：

1. Task 1-4：先收紧公开路由、JWT、Origin、上游 key。
2. Task 6：修复长连接生命周期、背压和重连韧性。
3. Task 7：新增 monitor snapshot、真实 IP/所在地、按天日志落点。
4. Task 8：新增 alert/DingTalk。
5. Task 9：新增 day/week/month stats。
6. Task 10：升级诊断面板和图表。
