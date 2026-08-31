# 按天日志与审计持久化就绪矩阵

审查日期：2026-06-06

当前阶段：随当前源码校准证据，并记录已验证的阶段性修复。

## 总体结论

当前项目已经有“按模型、按日期写日志文件”的基础能力，长生命周期 logger 跨零点后也会写入新日期文件；后台 monitor snapshot 已经能周期性写入当天日志，并带有 `event/event_date/instance_id` 审计字段；日志保留天数和清理间隔也已配置化并在服务启动时调度，清理结果会写 `log_cleanup` 审计摘要；敏感日志字段已有统一 `RedactField` helper，Web metrics 的 API Key 审计日志和 Workspace diff/content 审计不落明文已有测试保护；钉钉告警成功/失败发送结果已写按天日志且 webhook 脱敏。但还不能证明已经满足“监控面板所有信息写日志、日志按天记录、可长期审计”的生产目标。

核心问题不是没有日志文件，也不再是完全缺少后台定时快照、monitor 实例标识、基础清理调度、清理摘要、跨零点轮换保障或基础敏感字段脱敏 helper，而是其他审计事件模型仍不统一、脱敏策略尚未覆盖所有日志入口，面板字段到日志事件的一一映射仍不完整。现有日志更偏运行排障，尚未形成可对账、可聚合、可追溯的审计闭环。

## 已有能力

| 能力 | 证据 | 当前判断 |
| --- | --- | --- |
| 按日期生成日志文件 | `internal/logger/logger.go` 的 `dailyFileWriteSyncer` 在写入时用 `nowFunc().Format("2006-01-02")` 定位 `{model}-{date}.log` | 已有基础能力 |
| 按模型分目录 | `internal/logger/logger.go` 使用 `logs/{model}` 目录 | 已有基础能力 |
| 写入 zap 文件日志 | `GetModelLogger` 通过 `zapcore.NewCore` 接入 `dailyFileWriteSyncer`；dev 环境用 `NewMultiWriteSyncer` 同步 stdout/stderr | 已有基础能力 |
| 开发环境同步控制台 | `buildLogWriter` / `buildErrorWriter` 在 dev 环境增加 stdout/stderr | 已有 |
| logger 缓存 | `GetModelLogger` 按模型缓存 logger，日期切换由 writer 负责 | 已有，跨零点轮换已测试覆盖 |
| 跨零点轮换 | `internal/logger/logger_test.go` 的 `TestModelLoggerRotatesFileWhenDateChangesWhileLoggerIsHeld` 覆盖同一个 logger 从 2026-06-07 写到 2026-06-08 | 已验证 |
| 敏感字段脱敏 helper | `internal/logger/logger.go` 提供 `RedactField`，`internal/logger/logger_test.go` 覆盖 api_key、token、webhook、secret、redis_value、content、diff | 已有基础能力 |
| Web metrics API Key 审计脱敏 | `internal/handler/web_metrics_handler.go` 写 `api_key` 时调用 `logger.RedactField`，`TestWriteWebMetricsDailyLogRedactsSensitiveAPIKey` 验证日志不含明文 key | 已验证 |
| Workspace diff/content 不落审计日志 | `internal/service/workspace/audit.go` 只写 `diff_hash`，`TestWorkspaceWriteAuditLogsPreviewAndConfirm` 覆盖含 `OPENAI_API_KEY=secret` 的内容不会进入日志 | 已验证 |
| 钉钉告警发送结果审计 | `internal/service/alert/dingtalk.go` 成功写 `alert_firing`、`dingtalk_sent`，失败写 `dingtalk_failed`；测试覆盖 webhook 脱敏 | 已验证触发和发送结果，恢复事件未完成 |
| 后台 monitor 快照日志 | `cmd/server/main.go` 启动 `monitor.StartPeriodicLogger`；`internal/service/monitor/sampler.go` 立即并周期写快照；2026-06-07 23:06 8098 烟测只访问 `/health` 即写入带 `event=monitor_snapshot`、`event_date=2026-06-07`、`instance_id=monitor-smoke-8098` 的 `monitor snapshot` | 已有初始能力 |
| 日志清理调度 | `internal/logger/logger.go` 定义 `StartCleanupScheduler`、`CleanExpiredLogs` 和 `WriteLogCleanupAudit`；`cmd/server/main.go` 用 `conf.Global.Logs.RetentionDays` 与 `conf.Global.Logs.CleanupInterval` 启动并在关闭时等待；`internal/logger/logger_test.go` 覆盖过期日志删除、`log_cleanup` 摘要、相对路径和 context 取消退出 | 已有基础调度和清理摘要，仍缺压缩/归档策略 |
| 服务启动/关闭日志 | `cmd/server/main.go:157`、`cmd/server/main.go:170`、`cmd/server/main.go:181` | 已有 |
| Realtime 会话生命周期日志 | `internal/handler/openai_handler.go:87`、`internal/handler/openai_handler.go:137`、`internal/service/session/manager.go:97`、`internal/service/session/manager.go:177` | 部分已有 |
| Token 和音频计费日志 | `internal/service/billing/billing.go:91`、`internal/service/billing/billing.go:98`、`internal/service/billing/billing.go:263`、`internal/service/billing/billing.go:270` | 部分已有 |
| Responses 请求日志 | `internal/handler/openai_responses_handler.go:60`、`internal/handler/openai_responses_handler.go:75` | 部分已有 |
| Azure 请求日志 | `internal/handler/azureai_handler.go:145`、`internal/handler/azureai_handler.go:166`、`internal/handler/azureai_handler.go:189` | 部分已有 |

## 关键缺口矩阵

| 编号 | 问题 | 证据 | 风险 | 修复方向 |
| --- | --- | --- | --- | --- |
| P1-LOG-01 | 长生命周期对象持有旧 logger 的跨零点轮换风险已关闭 | `dailyFileWriteSyncer` 在写入时按当前日期轮换文件；`TestModelLoggerRotatesFileWhenDateChangesWhileLoggerIsHeld` 证明同一个 logger 跨天写入新文件 | 按天归档风险已降低；仍需审计事件统一携带 `event_date` 便于检索 | 保持回归测试，并继续把关键审计事件写入统一 schema |
| P1-LOG-02 | 日志清理已有审计摘要，但仍缺归档压缩 | `StartCleanupScheduler` 已按配置调用 `CleanExpiredLogs`；`WriteLogCleanupAudit` 使用短句柄写 `logs/audit/audit-YYYY-MM-DD.log`，事件名为 `log_cleanup`，只记录相对路径、扫描/删除/失败数量 | 磁盘增长风险已降低，也能审计清理摘要；但旧日志只删除不归档，且清理摘要还未接入集中日志平台 | 增加压缩/归档策略，并把 `log_cleanup` 纳入统一日志检索和告警平台 |
| P1-LOG-03 | `logs` 配置已扩展基础保留策略，仍缺审计/脱敏/压缩开关 | `conf.Global.Logs` 已包含 `root_dir`、`retention_days`、`cleanup_interval`；`RedactField` 已提供基础脱敏能力但没有配置开关；`conf/config*.yaml` 已配置默认保留值 | 仍无法配置压缩归档、统一审计开关和全局脱敏开关 | 继续扩展 `audit_enabled`、`redact_sensitive_fields`、归档/压缩配置 |
| P1-LOG-04 | global 日志归一到默认启用模型目录 | `internal/logger/logger.go:114-125` 对空模型名或 `global` 调用默认模型名 | 全局事件、OpenAI 事件、Azure 事件可能混在同一模型目录，影响检索和多模型归属判断 | 保留独立 `logs/global/global-YYYY-MM-DD.log` 或写明确 `scope`/`event_source` 字段 |
| P1-LOG-05 | 多数审计事件缺少统一 `event` 字段和 schema | monitor snapshot 已补 `event/event_date/instance_id`，但其他日志仍大量使用自然语言 message | 后续日志检索、统计、告警对账困难 | 所有审计日志统一带 `event`、`request_id`、`user_id`、`instance_id`、`module`、`status` |
| P1-LOG-06 | 监控快照已有后台定时写日志，且已有基础审计字段；长期审计仍不完整 | `cmd/server/main.go` 启动 `monitor.StartPeriodicLogger` 和日志清理调度；`internal/service/monitor/sampler.go` 周期调用 `LogSnapshotThrottled`；运行时日志已出现带 `event=monitor_snapshot` 的 `monitor snapshot`；日志清理写 `log_cleanup`；基础敏感字段脱敏 helper 已有 | 基础面板信息可以按天追溯并具备基础磁盘保留控制，但长期聚合、跨实例对账和全入口脱敏仍未完成 | 增加全入口字段脱敏、归档压缩和长期聚合 |
| P1-LOG-07 | Web 请求指标只保存在内存最近 500 条 | `internal/handler/web_metrics_handler.go:19`、`internal/handler/web_metrics_handler.go:21` | 重启丢失，多实例不可聚合，不是长期审计 | 请求结束时写 `web_request_metric`，内存窗口只做调试视图 |
| P1-LOG-08 | Redis 诊断、Redis key 扫描和 debug status 没有统一访问审计 | `internal/handler/debug_handler.go`、`internal/handler/redis_handler.go` 未发现审计事件写入 | 敏感调试接口被访问后缺少可追溯记录 | 敏感接口统一写 `admin_api_access`，包含用户、IP、路径、参数摘要、结果 |
| P1-LOG-09 | Workspace 文件写入没有审计闭环 | 细化证据见 `docs/reviews/2026-06-06-workspace-write-safety-matrix.md` | 无法追踪谁触发、谁确认、改了什么、如何回滚 | 写 `workspace_write_preview`、`workspace_write_confirmed`、`workspace_write_failed`、`workspace_write_reverted` |
| P1-LOG-10 | 告警触发和钉钉发送结果已有日志，恢复事件仍缺 | `alert.NotifyOverload` 成功写 `alert_firing`、`dingtalk_sent`，失败写 `dingtalk_failed`；细化证据见 `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md` | 可以审计告警触发和钉钉发送成功/失败，但无法审计恢复闭环 | 继续实现 `alert_recovered`、恢复通知和诊断面板告警状态 |
| P1-LOG-11 | day/week/month 统计没有统一日志事件 | 细化证据见 `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md` | 统计 API、Redis/DB 和日志不能对账 | 聚合完成后写 `stats_rollup` |
| P1-LOG-12 | 敏感字段已有统一 helper 和部分入口测试，仍未全入口覆盖 | `logger.RedactField` 覆盖 key/token/webhook/secret/value/content/diff；Web metrics `api_key` 写日志已脱敏；Workspace 审计只写 `diff_hash`；Redis 明细调试、所有 handler/provider 自然语言日志仍未逐项审计 | 未接入 helper 的日志入口仍可能泄露 JWT、Redis value、webhook、query key 或错误文本中的敏感片段 | 继续把 `RedactField` 接入 admin/debug/Redis/alert/provider 日志入口，并补敏感字段扫描测试 |

## 当前已覆盖的日志事件

| 事件类别 | 已有证据 | 不足 |
| --- | --- | --- |
| 服务生命周期 | `cmd/server/main.go:157`、`cmd/server/main.go:170`、`cmd/server/main.go:181` | 缺 build version、instance_id、配置校验结果 |
| Redis 初始化/关闭 | `internal/service/redis/redis.go:60`、`internal/service/redis/redis.go:63`、`internal/service/redis/redis.go:171` | 缺 degraded 状态、连续失败计数和周期快照 |
| WS 连接请求/结束 | `internal/handler/openai_handler.go:87`、`internal/handler/openai_handler.go:137` | 缺可信真实 IP、所在地、user_name、审计事件名 |
| 会话启动/关闭/token | `internal/service/session/manager.go:97`、`internal/service/session/manager.go:177`、`internal/service/session/manager.go:184` | 缺完整在线用户快照和跨实例汇总 |
| OpenAI 上游连接/错误 | `internal/provider/openai/client_ws.go:280`、`internal/provider/openai/client_ws.go:286`、`internal/provider/openai/client_ws.go:901` | 缺上游配额、限流头、第三方中转健康状态快照 |
| Billing 记录 | `internal/service/billing/billing.go:98`、`internal/service/billing/billing.go:270` | 主要是 Debug 级别，缺统计事件和失败告警闭环 |
| Responses 请求 | `internal/handler/openai_responses_handler.go:60`、`internal/handler/openai_responses_handler.go:75` | 与 `WebRequestRecord` 没有统一审计字段 |
| Azure 代理请求 | `internal/handler/azureai_handler.go:166`、`internal/handler/azureai_handler.go:189` | 缺周期状态、错误率、token、延迟聚合 |

## 应新增的审计事件

| 事件 | 触发时机 | 必要字段 |
| --- | --- | --- |
| `monitor_snapshot` | 固定周期，例如 30 秒 | `instance_id`、`event_date`、`server`、`memory`、`capacity`、`redis`、`openai`、`responses`、`azure`、`alerts` |
| `process_resource_snapshot` | 固定周期或资源阈值变化 | `pid`、`goroutines`、`memory`、`fd_count`/`handle_count`、`socket_count`、`gc` |
| `user_session_snapshot` | 固定周期或会话状态变化 | `session_id`、`user_id`、`user_name`、`real_ip`、`geo`、`device_id`、`model`、`connected_seconds` |
| `web_request_metric` | Web/Responses 请求结束 | `request_id`、`user_id`、`model`、`status`、`latency_ms`、`tokens`、`cost`、`error` |
| `admin_api_access` | debug、redis keys、metrics、model status 等敏感 API 被访问 | `request_id`、`user_id`、`real_ip`、`path`、`query_digest`、`status_code` |
| `alert_firing` | 阈值触发 | `alert_name`、`level`、`threshold`、`current`、`dedupe_key`、`cooldown_seconds` |
| `alert_recovered` | 告警恢复 | `alert_name`、`duration_seconds`、`current`、`dingtalk_result` |
| `dingtalk_sent` / `dingtalk_failed` | 钉钉发送完成 | `alert_name`、`webhook_digest`、`http_status`、`error` |
| `stats_rollup` | day/week/month 聚合完成 | `period`、`model`、`tokens`、`cost`、`errors`、`rate_limit_rejected`、`workspace_writes` |
| `workspace_write_audit` | 文件写入申请、确认、执行、失败、回滚 | `user_id`、`project_id`、`path`、`diff_hash`、`approved_by`、`status`、`rollback_ref` |
| `log_cleanup` | 日志清理任务每次运行 | `event_date`、`retention_days`、`model`、`scanned_count`、`deleted_count`、`failed_count`、`deleted_paths`、`failed_paths` |

## 配置建议

后续进入修复阶段时，建议在不破坏现有 `logs.root_dir` 的前提下扩展配置：

```yaml
logs:
  root_dir: "./logs"
  retention_days: 30
  cleanup_interval: "24h"
  monitor_snapshot_interval: "30s"
  audit_enabled: true
  redact_sensitive_fields: true
```

`retention_days` 和 `cleanup_interval` 用于磁盘控制；`monitor_snapshot_interval` 用于按天快照；`audit_enabled` 用于明确生产审计开关；`redact_sensitive_fields` 用于避免 key、token、Redis value、文件内容和 webhook 明文进入日志。

## 实施顺序约束

日志和审计增强不能早于生产安全收紧。当前 `/api/debug/status`、`/api/redis/keys`、`/api/web/metrics`、`/test/generate-token` 等接口仍存在匿名暴露风险，如果先增加更多日志和面板字段，会扩大敏感信息触达面。

建议顺序仍为：

1. 先完成生产安全 Task 1-4。
2. 再完成 Workspace 写入确认和长连接韧性。
3. 再实现 monitor snapshot 和日志审计事件。
4. 再接入钉钉告警和 day/week/month stats。
5. 最后升级前端看板和图表。

## 验收口径

| 验收项 | 证明方式 |
| --- | --- |
| 按天日志可轮换 | 跨零点或模拟日期后，新事件进入当天日志；当前已有 `TestModelLoggerRotatesFileWhenDateChangesWhileLoggerIsHeld` 证明同一个 logger 跨日期写入新文件 |
| 日志清理生效 | 配置短保留期后，过期日志被清理，`log_cleanup` 审计摘要只包含相对路径，调度器可随 context 取消退出 |
| 面板字段可追溯 | 任选诊断看板字段，可以在同一天日志中找到对应 `monitor_snapshot` 字段 |
| 敏感字段不泄露 | 已有 `RedactField`、Web metrics API key 和 Workspace content/diff 测试；最终仍需证明 API key、JWT、webhook、Redis value、Workspace content/diff 在所有日志入口均不以明文出现 |
| 审计事件可检索 | 当前已有 `monitor_snapshot`、`web_request_metric`、`alert_firing`、`dingtalk_sent`、`dingtalk_failed`、`stats_rollup`、`workspace_write_audit`、`log_cleanup` 的部分落点；最终还需 `alert_recovered` 和全入口统一 schema |
| 多实例可区分 | 每条审计事件包含 `instance_id` 或等价实例标识 |
| 统计可对账 | stats API、Redis/DB 和日志 `stats_rollup` 对同一周期结果一致 |

## 当前判断

当前代码只能证明“已经有按天日志文件基础设施、长生命周期 logger 跨零点轮换、基础敏感字段脱敏 helper、Web metrics API key 脱敏、Workspace content/diff 不落日志、钉钉告警触发和发送结果日志、部分运行日志、后台 monitor snapshot 初始落点、monitor 审计字段、基础日志清理调度和 `log_cleanup` 清理摘要”，不能证明“所有面板信息都按天写日志、可长期审计、可跨实例聚合、所有日志入口都已脱敏对账”。该问题应保留在待修复清单中，继续按实施计划 Task 7/8/9/10 逐步实现。
