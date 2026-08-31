# 监控字段与按天日志落点审计矩阵

审查日期：2026-06-06

当前阶段：审查并随当前源码校准证据。2026-06-07 已补充后台 monitor snapshot 运行时证据。

## 总体结论

当前项目已经具备按天日志文件基础能力、统一 monitor 快照、后台周期快照写日志和一部分实时诊断指标，但还没有满足“监控面板展示的所有信息都必须记录到日志并可长期对账”的目标。

现状可以分成四类：

| 类别 | 当前状态 | 典型证据 | 风险 |
| --- | --- | --- | --- |
| 按天日志基础能力 | 已有 | `internal/logger/logger.go` 按模型缓存 logger，并由 `dailyFileWriteSyncer` 写入 `{model}-{date}.log`；跨零点轮换已有 `TestModelLoggerRotatesFileWhenDateChangesWhileLoggerIsHeld` 覆盖 | 只是日志文件能力，不代表所有监控字段都已写入 |
| 日志清理调度 | 已有基础实现和审计摘要 | `internal/logger/logger.go` 提供 `StartCleanupScheduler`、`CleanExpiredLogs` 和 `WriteLogCleanupAudit`；`cmd/server/main.go` 按 `logs.retention_days` 和 `logs.cleanup_interval` 启动并在关闭时等待 | 解决基础磁盘保留，并写 `log_cleanup` 摘要；已有 `RedactField` 基础脱敏 helper，但仍缺归档压缩和全入口脱敏策略 |
| Realtime 生命周期日志 | 部分已有 | `internal/service/session/manager.go:98` 记录会话启动；`internal/service/session/manager.go:177` 记录会话关闭 | 缺少用户名称、所在地、可信 IP、FD/socket、告警状态 |
| 实时诊断快照 | API 返回，并已通过后台采样写入按天日志 | `internal/service/monitor/sampler.go` 启动后立即并周期采样；`internal/service/monitor/snapshot.go` 写 `monitor snapshot`；`cmd/server/main.go` 启动后台采样 | 已能按 `event=monitor_snapshot`、`event_date`、`instance_id` 追溯基础监控快照；仍缺跨实例聚合、其他审计事件统一 schema 和全入口字段脱敏 |
| 统计与面板数据 | Redis 或内存为主 | `internal/service/billing/billing.go:220` 写 daily token；`internal/handler/web_metrics_handler.go:19` 只保留最近 500 条内存记录 | 重启丢失、多实例不可聚合、日志审计不完整 |

## 已有按天日志能力

| 能力 | 证据 | 判断 |
| --- | --- | --- |
| 日志按日期命名 | `dailyFileWriteSyncer` 在写入时用当前日期生成 `{model}-{date}.log` | 已有 |
| 日志按模型分目录 | `dailyFileWriteSyncer` 使用 `logs/{model}` 目录 | 已有 |
| 日志输出到文件 | `GetModelLogger` 通过 `zapcore.NewCore` 接入 `dailyFileWriteSyncer` | 已有 |
| 开发环境同步 stdout/stderr | `buildLogWriter` / `buildErrorWriter` dev 下增加 stdout/stderr | 已有 |
| 日志清理调度 | `internal/logger/logger.go` 定义 `StartCleanupScheduler`、`CleanExpiredLogs` 和 `WriteLogCleanupAudit`；`cmd/server/main.go` 启动 `cleanupDone` 并在关闭时等待 | 已有基础调度和 `log_cleanup` 清理摘要 |
| 长连接跨零点轮换 | `internal/logger/logger_test.go` 的 `TestModelLoggerRotatesFileWhenDateChangesWhileLoggerIsHeld` | 已验证 |

结论：日志基础设施已经承载 monitor 快照，后台 monitor writer 已能周期性写入日志，并且 monitor snapshot 已带 `event/event_date/instance_id`；基础日志清理调度、`log_cleanup` 摘要、长生命周期 logger 跨零点轮换和基础敏感字段脱敏 helper 都已接入。当前剩余缺口是其他审计事件 schema、归档压缩、跨实例聚合和全入口敏感字段脱敏。

按天日志基础设施、跨零点轮换、清理调度、统一审计事件和敏感字段脱敏的细化证据见 `docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md`。

## 监控字段落点矩阵

| 用户目标字段 | 当前展示/采集状态 | 当前日志状态 | 证据 | 缺口 |
| --- | --- | --- | --- | --- |
| 在线人数/活跃会话 | 已有活跃会话计数 | monitor 快照已周期写入，包含 `capacity.active_sessions` 和 `event=monitor_snapshot` | `internal/service/monitor/snapshot.go`、`internal/service/session/capacity.go`、运行时日志 `monitor snapshot` | 缺跨实例活跃会话聚合 |
| 在线用户明细 | 进程内 recent sessions 部分保留 | 会话日志有 `user_id`，但无完整在线列表快照 | `internal/service/metrics/metrics.go:229`、`internal/service/metrics/metrics.go:730` | 缺用户名称、所在地、设备、IP 可信来源标识 |
| 用户 ID | 部分已有 | Realtime 日志携带 `user_id` | `internal/service/session/manager.go:75-77` | 需要所有 HTTP/WS/告警/统计日志统一携带 |
| 用户名称 | 未满足 | 未发现统一 `user_name` 字段 | `docs/reviews/2026-06-06-observability-gap-matrix.md:115` | JWT claim、session、metrics、日志都缺字段 |
| 用户真实 IP | 部分已有但不可靠 | Realtime 连接日志携带 `remote_addr` | `internal/handler/openai_handler.go:72`、`internal/handler/openai_handler.go:85`、`internal/service/session/manager.go:118`、`internal/service/metrics/metrics.go:733` | 未配置 trusted proxies，代理后不可信 |
| 用户所在地 | 未满足 | 无日志字段 | 仓库未发现独立 GeoIP 服务 | 缺 GeoIP provider、缓存、失败降级、日志字段 |
| 内存实时使用 | API 已返回 MemStats，monitor 快照包含内存和 GC 字段 | 已随 `monitor snapshot` 周期落日志 | `internal/service/monitor/snapshot.go`、运行时日志 `memory.alloc_mb/sys_mb` | 缺长期趋势存储和跨实例聚合 |
| Go 服务信息 | 已采集 Go/runtime/process 字段 | 已随 `monitor snapshot` 周期落日志 | `internal/service/monitor/snapshot.go`、`cmd/server/main.go` | 缺构建版本、启动参数摘要和配置校验状态 |
| 进程数/PID | 当前进程 PID/PPID、goroutines、FD/handle/socket 已采集 | 已随 `monitor snapshot` 周期落日志，并包含 `instance_id` | `internal/service/monitor/snapshot.go`、运行时日志 `pid/goroutines/fd_count/handle_count/socket_count/instance_id` | 缺系统进程数和平台不支持字段的生产替代指标 |
| Redis 状态 | API 已返回连接池和 ping，monitor 快照包含模块配置状态 | Redis 初始化/关闭有日志；monitor 周期快照记录 redis module 状态 | `internal/service/monitor/snapshot.go`、`internal/service/redis/redis.go` | 缺周期性 Redis ping/pool stats、错误率和 degraded 状态 |
| 缓存命中总量 | Redis pool hits/misses 已返回，业务缓存 hit/miss 已进入 stats 口径 | 业务缓存 hit/miss 可进入 stats_rollup；monitor 快照记录 metrics business 字段 | `internal/service/stats/stats.go`、`conf/config.go`、运行时 `stats_rollup` | 仍缺更多业务缓存生产者和长期持久化 |
| token 总览 | 进程内 metrics 和 Redis daily 部分已有 | billing 成功时写少量 Debug 日志 | `internal/service/metrics/metrics.go:549`、`internal/service/billing/billing.go:220`、`internal/service/billing/billing.go:270` | 缺 day/week/month 统一日志事件 |
| OpenAI 连接信息 | metrics 中有连接、重连、事件计数 | 具体事件日志分散在 Provider；快照未统一落日志 | `internal/service/metrics/metrics.go:417`、`internal/service/metrics/metrics.go:598` | 缺上游连接数、限流头、配额状态、第三方中转状态 |
| OpenAI 错误信息 | metrics 中有错误摘要 | 部分错误有日志，缺统一错误中心日志事件 | `internal/service/metrics/metrics.go:591`、`internal/service/metrics/metrics.go:788` | 缺错误等级、模块、用户、IP、所在地、告警关联 |
| Responses 请求明细 | Web metrics 内存记录 | 成功/失败只写少量日志 | `internal/handler/web_metrics_handler.go:61`、`internal/handler/openai_responses_handler.go:57` | `WebRequestRecord` 未写入按天审计日志 |
| Azure 模块状态 | API 已返回，monitor modules 包含 azure 状态 | 代理请求有部分日志，模块状态随 monitor 快照周期落日志 | `internal/service/monitor/snapshot.go`、`internal/handler/azureai_handler.go` | 缺按模块成功率、错误率、延迟、token 的长期周期统计 |
| 系统过载状态 | 过载时返回 503 并记录 metrics | Realtime 过载有 Warn 日志；无钉钉告警日志 | `internal/handler/openai_handler.go:89`、`internal/handler/azureai_handler.go:64` | 缺 alert_firing、alert_recovered、dingtalk_sent 等事件 |
| 天/周/月统计 | daily 部分已有 | 成功时有少量 token 日志 | `internal/service/billing/billing.go:220`、`internal/service/billing/billing.go:223` | 缺 weekly/monthly、统计 API、图表、日志事件 |

## 当前日志已覆盖的事件

| 事件 | 日志证据 | 备注 |
| --- | --- | --- |
| 服务启动 | `cmd/server/main.go:156` | 含 addr/env/jwt/rate/fallback |
| 服务关闭 | `cmd/server/main.go:173`、`cmd/server/main.go:181` | 有优雅关闭日志 |
| Redis 初始化成功/失败 | `internal/service/redis/redis.go:60`、`internal/service/redis/redis.go:63` | 失败直接 Fatal，缺 degraded 策略 |
| WS 连接请求 | `internal/handler/openai_handler.go:86` | 携带 request_id/user_id/device_id/remote_addr |
| 容量拒绝 | `internal/handler/openai_handler.go:91` | 只记录本实例容量拒绝 |
| 会话启动 | `internal/service/session/manager.go:97` | 携带 model/start_time |
| 会话关闭 | `internal/service/session/manager.go:177` | 携带 duration |
| 会话 token 统计 | `internal/service/session/manager.go:184` | 只在 Close 时尝试读取 session usage |
| 音频时长记录 | `internal/service/billing/billing.go:98` | Debug 级别 |
| Token 消耗记录 | `internal/service/billing/billing.go:270` | Debug 级别，只含 session/response/input/output |
| Responses 成功/失败 | `internal/handler/openai_responses_handler.go:57`、`internal/handler/openai_responses_handler.go:75` | 缺用户/IP/费用/cached/reasoning 统一字段 |

## 当前没有闭环的关键日志事件

| 应新增日志事件 | 触发时机 | 必要字段 |
| --- | --- | --- |
| `monitor_snapshot` | 已由 `StartPeriodicLogger` 固定周期写入，且已带 `event/event_date/instance_id` | server、memory、capacity、redis、openai、responses、azure、metrics、alerts、stats、instance_id、event_date |
| `user_session_snapshot` | 固定周期或会话状态变化 | user_id、user_name、session_id、device_id、real_ip、geo、model、connected_seconds |
| `cache_stats_snapshot` | 固定周期 | redis_pool_hits、redis_pool_misses、business_cache_hits、business_cache_misses |
| `process_resource_snapshot` | 固定周期 | pid、go_version、goroutines、num_cpu、fd_count/handle_count、socket_count、memory |
| `openai_upstream_snapshot` | 固定周期 | connect_success、connect_failures、reconnects、rate_limits、latency、third_party_base_url |
| `web_request_metric` | 每次 Responses/Web 请求结束 | request_id、user_id、model、tokens、cached_tokens、reasoning_tokens、cost、latency、status、error |
| `alert_firing` | 过载或阈值触发 | alert_name、level、reason、threshold、current、cooldown_key、dingtalk_result |
| `alert_recovered` | 告警恢复 | alert_name、previous_duration、current、dingtalk_result |
| `stats_rollup` | day/week/month 聚合完成 | period、model、user_id 可选、tokens、cost、errors、rate_limit_rejected、workspace_writes |
| `workspace_write_audit` | 文件写入申请、确认、执行、失败 | user_id、project_id、path、diff_hash、approved_by、status、rollback_ref |

## 建议后续实现边界

确认进入修复阶段后，建议新增三个边界清晰的服务：

1. `internal/service/monitor`：负责采集 runtime、session、Redis、OpenAI、Azure、错误、告警状态，生成统一快照，并按天写日志。
2. `internal/service/stats`：负责把 token、费用、音频、错误、限流、告警、Workspace 写入聚合到 day/week/month。
3. `internal/service/alert`：负责过载规则、冷却、恢复检测、钉钉签名和发送结果日志。

不要让 `/api/debug/status` 自己承担所有采集和写日志职责。它应该只负责读取 monitor/stats 的当前快照并返回给前端，否则高频轮询会把业务接口、日志写入和诊断采集耦合在一起。

## 对实施顺序的影响

监控和日志增强不能排在生产安全之前。当前 `/api/debug/status`、`/api/redis/keys`、`/api/web/metrics` 等接口仍在公开路由组中，直接把更多监控字段加入这些匿名接口，会扩大生产暴露面。

建议仍按以下顺序推进：

1. 先完成生产安全 Task 1-4。
2. 完成 Task 6 长连接生命周期、背压和重连韧性修复，确保在线人数和容量释放指标可信。
3. 继续收紧 Task 7：monitor snapshot 已有后台落点和 `event/event_date/instance_id`，日志清理调度、`log_cleanup` 摘要、跨零点轮换和基础 `RedactField` helper 已有实现；下一步补全入口敏感字段脱敏、归档压缩和其他审计事件 schema。
4. 然后接入 Task 8 alert/DingTalk。
5. 最后实现 Task 9 stats day/week/month 和 Task 10 图表。

## 验收标准

修复完成后，至少需要提供以下证据：

| 验收项 | 证明方式 |
| --- | --- |
| 监控快照已写入按天日志 | 启动服务后等待一个采样周期，检查 `logs/{model}/{model}-{date}.log` 中存在 `monitor_snapshot` |
| 面板字段可追溯 | 任选一个诊断面板字段，可在同一天日志中找到对应字段和值 |
| 用户信息可追溯 | 同一会话的 `user_id`、`user_name`、real_ip、geo、session_id 可在日志中关联 |
| 过载告警可追溯 | 构造容量拒绝或内存阈值，日志中有 `alert_firing` 和钉钉发送结果 |
| 恢复通知可追溯 | 压力恢复后日志中有 `alert_recovered` |
| day/week/month 可查 | stats API、Redis/DB 和日志三处能对同一周期统计对账 |
| 多实例可聚合 | 多实例日志事件包含 `instance_id` 或可等价区分实例的字段 |
