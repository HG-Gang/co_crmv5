# Task 7-10 执行确认清单

日期：2026-06-07

## 当前状态

Task 7-10 对应用户目标中的监控面板、按天日志、钉钉过载预警、天/周/月统计和图表展示。当前项目已经补入 monitor snapshot、基础日志清理调度、alert/stats 初始切片和诊断页资源图，但还没有完成这些目标的生产闭环。

本清单只锁定后续确认后的执行范围，不修改业务代码。Task 7-10 不应早于 Task 5-6 执行，因为监控、告警和统计依赖两个前提：

1. Workspace 写文件必须先变成 pending diff/确认/拒绝，否则监控只能记录高风险落盘行为，不能拦截风险。
2. Realtime 长连接容量释放、背压和关键事件语义必须先明确，否则在线人数、队列压力、错误率和容量统计都不可信。

## 当前源码证据

| 能力 | 当前证据 | 判断 |
| --- | --- | --- |
| 诊断快照 API | `internal/handler/debug_handler.go:31` 返回 `/api/debug/status`；`internal/handler/debug_handler.go:65` 返回 Go runtime；`internal/handler/debug_handler.go:88` 返回内存；`internal/handler/debug_handler.go:122` 返回容量；`internal/handler/debug_handler.go:155` 返回 Redis | 已有基础，只是按请求返回，不是后台监控服务 |
| 按天日志文件 | `internal/logger/logger.go` 按模型和日期缓存 logger 并生成 `{model}-{date}.log` | 已有基础，monitor snapshot 已有统一事件字段，其他审计事件仍需补齐 |
| 日志清理 | `internal/logger/logger.go` 定义 `StartCleanupScheduler`、`CleanExpiredLogs` 和 `WriteLogCleanupAudit`；`cmd/server/main.go` 按配置启动并在关闭时等待 | 已有基础调度和 `log_cleanup` 摘要，仍缺归档压缩和全局脱敏策略 |
| token daily billing | `internal/service/billing/billing.go:220` 写 `billing:daily:{model}:{date}`；`internal/service/billing/billing.go:223` 写 `billing:daily_detail:{model}:{date}` | 只有 daily，没有 week/month 统一 stats |
| Web 请求指标 | `internal/handler/web_metrics_handler.go:61` 追加内存记录；`internal/handler/web_metrics_handler.go:126` 返回最近记录和图表 | 只保留进程内最近 500 条，不是长期统计 |
| Redis 连接池命中 | `internal/handler/debug_handler.go:193` 获取 pool stats；`internal/handler/debug_handler.go:195` 返回 hits；`internal/handler/debug_handler.go:196` 返回 misses | 这是连接池命中，不是业务缓存命中 |
| monitor/alert/stats 服务 | `internal/service/monitor`、`internal/service/alert`、`internal/service/stats` 已存在 | 已有初始实现，仍缺跨实例长期化和完整恢复事件 |
| 关键审计事件 | `monitor_snapshot`、`alert_firing`、`stats_rollup`、`workspace_write_*`、`log_cleanup` 已有初始落点 | 仍缺 `alert_recovered` 状态机和更多统一 schema 覆盖 |

## 确认后会修改的范围

### Task 7：monitor snapshot 与按天日志落点

会新增或修改：

- `internal/service/monitor/snapshot.go`
- `internal/service/monitor/ipgeo.go`
- `internal/service/monitor/logger.go`
- `internal/service/monitor/process_windows.go`
- `internal/handler/debug_handler.go`
- `internal/service/session/manager.go`
- `internal/service/metrics/metrics.go`
- `internal/logger/logger.go`
- `cmd/server/main.go`
- `conf/config.go`
- `conf/config.yaml`
- `conf/config_dev.yaml`
- `conf/config_prod.yaml`
- `web/diagnostics.html`

核心行为：

- 新增统一 monitor snapshot，字段覆盖 server、process、memory、capacity、redis、openai、azure、responses、metrics、errors。
- 增加 `pid`、进程可观测字段、Windows handle/process 基础字段；FD/socket 在 Windows 下使用可获得的 handle/连接近似字段，不能伪造 Linux FD。
- 增加真实 IP 字段，明确 `trusted_proxies` 生效后才信任 `X-Forwarded-For`。
- 增加所在地字段，默认本地/离线 provider 可返回 unknown；后续可配置 GeoIP 数据源。
- 后台定时写 `monitor_snapshot`、`user_session_snapshot`、`cache_stats_snapshot`、`process_resource_snapshot` 到当天日志。
- 已调度 `CleanExpiredLogs`，按配置保留天数清理旧日志，并写 `log_cleanup` 摘要；下一步补归档/压缩策略。

### Task 8：钉钉过载告警

会新增或修改：

- `internal/service/alert/dingtalk.go`
- `internal/service/alert/manager.go`
- `internal/service/alert/rules.go`
- `internal/service/alert/state.go`
- `internal/service/alert/alert_test.go`
- `conf/config.go`
- `conf/config.yaml`
- `conf/config_dev.yaml`
- `conf/config_prod.yaml`
- `cmd/server/main.go`
- `internal/handler/debug_handler.go`
- `web/diagnostics.html`

核心行为：

- 新增 `alert.enabled`、`dingtalk_webhook`、`dingtalk_secret`、`cooldown_seconds`、容量/错误率/Redis/OpenAI/队列/内存阈值配置。
- 支持钉钉机器人加签。
- 同一告警在冷却期内不重复发送。
- 指标恢复后写 `alert_recovered`，可发送恢复通知。
- 容量拒绝、Redis 连续失败、OpenAI 连接/重连失败、队列压力、错误率和内存压力触发 `alert_firing`。
- webhook、secret、token、key 只写脱敏摘要。

### Task 9：day/week/month stats

会新增或修改：

- `internal/service/stats/period.go`
- `internal/service/stats/stats.go`
- `internal/service/stats/store.go`
- `internal/service/stats/stats_test.go`
- `internal/handler/stats_handler.go`
- `internal/service/billing/billing.go`
- `internal/handler/web_metrics_handler.go`
- `internal/provider/openai/client_ws.go`
- `internal/middleware/rate.go`
- `internal/service/metrics/metrics.go`
- `cmd/server/main.go`

核心行为：

- 新增 `stats:day:*`、`stats:week:*`、`stats:month:*` 或等价存储键。
- 统一 Realtime 和 Responses 的 token、cached tokens、reasoning tokens、费用和延迟。
- 统计音频输入/输出时长、连接数、会话数、错误数、限流拒绝、容量拒绝、告警触发/恢复、Workspace pending/confirm/reject。
- 区分 `business_cache_hits` 和 `redis_pool_hits`，避免把 Redis 连接池命中误写成业务缓存命中。
- 新增 `GET /api/stats/resources?period=day|week|month&model=openai`。
- 每次聚合或写入关键统计事件时写 `stats_rollup` 或对应资源事件到当天日志。

### Task 10：诊断面板图表

会新增或修改：

- `web/diagnostics.html`
- `web/style.css`
- `web/theme.js`
- `internal/handler/debug_handler.go`
- `internal/handler/stats_handler.go`

核心行为：

- 面板展示在线人数、在线用户、用户 ID/名称、真实 IP、所在地、内存、PID/进程字段、Go runtime、Redis、OpenAI、Azure、Responses、错误中心、告警状态。
- 增加 day/week/month 资源统计 tab。
- 增加 token、费用、错误、告警、容量拒绝、限流拒绝、缓存命中、音频时长图表。
- 空数据时稳定显示空状态，不用 Redis key 扫描结果伪装正式统计。
- 页面展示字段必须能在当天日志或 stats API 中追溯。

## 确认后不会修改的范围

Task 7-10 阶段不会完成：

- 百万并发压测工具和生产容量报告；这属于 Task 11。
- 全仓库中文详细注释一次性补齐；这属于 Task 12。
- 更换数据库、引入大型前端框架或重写全部页面。
- 承诺单实例百万并发；容量结论必须等 Task 11 压测报告。

## 第一批红灯测试

确认开始 Task 7-10 后，先写并验证以下红灯测试。

### Monitor 红灯

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/monitor ./internal/handler -run "MonitorSnapshot|RealIP|Geo|Process|DailyLog" -count=1
```

期望：

- `internal/service/monitor` 包不存在或 `Snapshot` 未定义。
- `monitor_snapshot` 日志事件未实现。
- process、real_ip、geo 字段未能从统一 snapshot 返回。

### Alert/DingTalk 红灯

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/alert ./internal/handler -run "DingTalk|Alert|Cooldown|Recovered|Overload" -count=1
```

期望：

- `internal/service/alert` 包不存在。
- 钉钉签名、冷却去重、恢复通知和 webhook 失败路径没有实现。
- debug status 中没有 alert 状态。

### Stats 红灯

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/stats ./internal/handler -run "Stats|Period|Resources|Cache|Week|Month" -count=1
```

期望：

- `internal/service/stats` 包不存在。
- day/week/month key 或查询模型未定义。
- `/api/stats/resources` 路由不存在。
- 业务缓存命中和 Redis pool 命中没有分开。

### Dashboard 红灯

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/handler -run "DebugStatus|StatsResources|AlertStatus" -count=1
```

期望：

- debug/status 缺 monitor snapshot、alert、stats 字段。
- stats resources handler 未注册或未返回稳定空状态。

## 完成 Task 7 的验收证据

- `go test ./internal/service/monitor ./internal/handler -run "MonitorSnapshot|RealIP|Geo|Process|DailyLog" -count=1` 通过。
- 启动服务后，当天日志能检索到 `monitor_snapshot`。
- `/api/debug/status` 返回 `server.pid`、process 字段、real_ip/geo 字段来源说明、monitor snapshot 时间。
- 日志中 key/token/webhook/Redis value/文件内容不明文输出。

## 完成 Task 8 的验收证据

- `go test ./internal/service/alert -run "DingTalk|Cooldown|Recovered|WebhookFailure" -count=1` 通过。
- 构造容量拒绝或阈值超限后，当天日志出现 `alert_firing`。
- 恢复后当天日志出现 `alert_recovered`。
- 钉钉 webhook 成功或失败都写 `dingtalk_sent` 或 `dingtalk_failed`。
- `/api/debug/status` 或诊断页能看到当前告警状态、最近发送结果和冷却状态。

## 完成 Task 9 的验收证据

- `go test ./internal/service/stats ./internal/handler -run "Stats|Period|Resources|Cache|Week|Month" -count=1` 通过。
- 写入一次 Realtime usage 和一次 Responses usage 后，day/week/month 都可查询。
- API 同时返回 `business_cache_hits` 和 `redis_pool_hits`。
- 当天日志能检索到 `stats_rollup` 或资源统计事件。

## 完成 Task 10 的验收证据

- `/web/diagnostics.html` 展示新增监控、告警和统计字段。
- day/week/month tab 在空数据和非空数据下都能稳定渲染。
- 图表不依赖 Redis key 扫描作为正式统计来源。
- 页面字段能通过 `/api/debug/status`、`/api/stats/resources` 和当天日志追溯。

## 需要用户确认的执行口径

建议确认语句：

```text
确认开始执行 Task 7-10，按计划实现 monitor snapshot、按天日志落点、钉钉过载告警、day/week/month stats 和诊断面板图表。
```

收到确认后，应先确认 Task 5-6 已完成并通过验证，再开始 Task 7-10。执行时继续按 TDD：先红灯测试，再实现，再全量验证。
