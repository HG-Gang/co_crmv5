# 钉钉告警与系统过载预警就绪矩阵

审查日期：2026-06-06

当前状态：随当前源码校准证据，并记录已验证的阶段性修复。本文审查系统过载预警、钉钉机器人通知、冷却/恢复通知、告警状态面板和按天日志落点。

## 总体判断

当前项目已经有 `internal/service/alert`，能在容量拒绝或 monitor 复合过载信号触发时发送钉钉机器人通知；支持 webhook、secret 加签、冷却去重，发送成功会写统一 stats，成功/失败发送结果也会写入当天日志，webhook 使用 `logger.RedactField` 脱敏。已有能力还包括：容量满时返回 503，限流时返回 429，OpenAI 连接/重连/写入失败会进入进程内 metrics，Redis ping 和连接池状态可以通过诊断接口查看，诊断页面能显示活跃会话比例和部分错误计数。

这些能力已经覆盖“容量拒绝/部分 monitor 过载信号 -> 钉钉通知 -> stats -> 按天日志”的初始链路，但仍不能完整满足用户要求。剩余缺口包括：恢复通知状态机不完整，诊断面板缺少告警状态和最近发送结果，Redis/OpenAI/队列/错误率等复合过载规则仍需继续扩展，跨实例告警状态和长期持久化还未闭环。

## 已有能力

| 能力 | 当前证据 | 当前价值 | 限制 |
| --- | --- | --- | --- |
| 单实例容量拒绝 | `internal/handler/openai_handler.go` 和 `internal/handler/azureai_handler.go` 在容量不足时返回 503；`internal/handler/alert_helper.go` 调用 `alert.NotifyOverload` | 活跃会话达到 `capacity.max_active_sessions` 时快速拒绝新连接，并触发钉钉告警 | 仍缺恢复通知和跨实例聚合 |
| 钉钉发送器 | `internal/service/alert/dingtalk.go` 实现 `NotifyOverload`、签名、冷却和发送结果审计 | 已有主动通知链路 | 仍缺恢复通知、告警状态查询 API 和更多规则配置 |
| 钉钉发送结果按天日志 | `TestNotifyOverloadWritesDailyAuditLogAndRedactsWebhook`、`TestNotifyOverloadWritesFailedAuditLogAndRedactsWebhook` | 成功写 `alert_firing` / `dingtalk_sent`，失败写 `dingtalk_failed`，webhook 不落明文 | 仍缺 `alert_recovered` 实际状态机 |
| 容量拒绝指标 | `internal/service/metrics/metrics.go:67`、`internal/service/metrics/metrics.go:300` 到 `internal/service/metrics/metrics.go:303` | 诊断页能看到 `capacity_rejected` | 只是当前进程内计数，重启丢失，多实例不可聚合 |
| 限流拒绝指标 | `internal/middleware/rate.go:121` 到 `internal/middleware/rate.go:127`、`internal/middleware/rate.go:159` 到 `internal/middleware/rate.go:167` | 本地/全局限流时返回 429，并进入 metrics | 限流激增不会触发告警；Redis 限流失败只降级本地 |
| Redis 异常可诊断 | `internal/handler/debug_handler.go:186` 到 `internal/handler/debug_handler.go:197` | `/api/debug/status` 能返回 Redis ping error 和 pool stats | 只在页面/API 被动展示，没有连续失败计数、告警和按天日志事件 |
| OpenAI 连接/重连错误指标 | `internal/service/metrics/metrics.go:428` 到 `internal/service/metrics/metrics.go:433`、`internal/service/metrics/metrics.go:610` 到 `internal/service/metrics/metrics.go:615` | OpenAI 连接失败、重连失败会进入进程内错误摘要 | 无连续失败阈值、错误率窗口、告警冷却和恢复通知 |
| 错误摘要 | `internal/service/metrics/metrics.go:785` 到 `internal/service/metrics/metrics.go:803` | `metrics.errors` 能保留最近错误和按 reason/code 汇总 | 没有最近 1 分钟错误率窗口，也没有主动通知 |
| 诊断页面容量展示 | `web/diagnostics.html:375` 到 `web/diagnostics.html:381`、`web/diagnostics.html:465` | 页面能看到活跃会话比例和容量拒绝数量 | 没有告警状态、最近钉钉发送结果、恢复时间和冷却剩余时间 |

## 关键缺口矩阵

| 编号 | 问题 | 证据 | 影响 | 确认后修复方向 |
| --- | --- | --- | --- | --- |
| P1-ALERT-01 | 告警服务已有基础实现，状态机仍不完整 | `internal/service/alert/dingtalk.go` 已存在；`internal/service/monitor/overload_alert.go` 会评估部分过载信号 | 已能主动通知容量/部分 monitor 过载，但恢复、状态查询和跨实例去重仍不足 | 扩展恢复状态机、告警状态 API 和跨实例 dedupe |
| P1-ALERT-02 | 钉钉配置已有基础字段，规则阈值仍不完整 | `conf.Global.Alerts.DingTalk` 已包含 enabled/webhook/secret/cooldown/timeout/@人配置 | 已能配置 webhook、secret、冷却和超时 | 继续增加容量、错误率、Redis/OpenAI、队列、内存阈值配置 |
| P1-ALERT-03 | 容量满已触发钉钉告警和日志 | `internal/handler/alert_helper.go` 在容量拒绝时调用 `alert.NotifyOverload`；`dingtalk.go` 写 stats 和当天日志 | 运维侧已有主动感知 | 仍需跨实例容量视图和恢复通知 |
| P1-ALERT-04 | Redis 异常只进入诊断 API 或本地降级 | `internal/middleware/rate.go:147` Redis 限流失败降级；`internal/handler/debug_handler.go:186` 到 `internal/handler/debug_handler.go:187` 返回 ping error | Redis 连续失败会破坏 session/billing/rate limit 可信度，但没有主动告警 | monitor 采样 Redis ping/pool stats，连续失败或 pool timeout 超阈值触发告警 |
| P1-ALERT-05 | OpenAI 上游异常没有告警阈值 | `internal/service/metrics/metrics.go:428` 到 `internal/service/metrics/metrics.go:433`、`internal/service/metrics/metrics.go:459` 到 `internal/service/metrics/metrics.go:462`、`internal/service/metrics/metrics.go:610` 到 `internal/service/metrics/metrics.go:615` | 上游连接失败、写失败、重连失败会影响大量会话，但只能在页面或日志里发现 | 对 connect failures、write errors、reconnect failures 建立连续失败和错误率阈值 |
| P1-ALERT-06 | 已有冷却去重，仍缺恢复通知 | `suppressedByCooldown` 已实现；`TestNotifyOverloadSuppressesDuringCooldown` 覆盖冷却；`alert_recovered` 仍没有真实状态机事件 | 同一异常不会刷屏，但恢复后无法确认故障闭环 | 告警状态包含 normal/firing/recovered，恢复时写 `alert_recovered` 并可发送恢复通知 |
| P1-ALERT-07 | 告警成功/失败已写按天日志，恢复事件仍缺 | `NotifyOverload` 成功写 `alert_firing`、`dingtalk_sent`；失败写 `dingtalk_failed`；测试覆盖 webhook 脱敏 | 可审计触发和发送结果，但无法审计恢复 | 增加 `alert_recovered` 与恢复通知发送结果 |
| P1-ALERT-08 | 诊断面板没有告警状态 | `web/diagnostics.html` 只展示容量比例、错误和链路统计，未发现 alert 状态区域 | 运维无法看到当前告警状态、冷却剩余时间和最近通知结果 | 诊断页新增 alert panel，展示 firing/recovered、规则、当前值、阈值、最近钉钉结果 |
| P2-ALERT-09 | 告警测试已有基础覆盖，恢复测试仍缺 | `internal/service/alert/dingtalk_test.go` 覆盖发送、stats、IP 所在地、禁用、冷却、成功/失败日志和 webhook 脱敏 | 发送链路回归风险已降低；恢复逻辑仍无测试 | 补恢复通知、更多规则和面板状态测试 |

## 首批告警规则建议

| 规则 | 触发条件 | 恢复条件 | 日志字段 |
| --- | --- | --- | --- |
| `capacity_high` | 活跃会话 / `max_active_sessions` 超过 80% 和 90% | 低于恢复阈值，例如 70% | active、max、percent、threshold |
| `capacity_rejected` | 单位时间内 `capacity_rejected` 增量超过阈值 | 增量归零或低于阈值 | rejected_delta、window_seconds |
| `redis_unhealthy` | Redis ping 连续失败或 pool timeouts 增长 | 连续成功 N 次 | ping_error、timeouts_delta、pool_total、pool_idle |
| `openai_reconnect_failed` | OpenAI 重连失败连续超过阈值 | 重连成功或失败计数停止增长 | reason、failures_delta、session_id 可选 |
| `error_rate_high` | 最近 1 分钟错误总数或关键 reason 超阈值 | 错误率恢复 | errors_delta、top_reason、top_code |
| `queue_pressure` | `send_queue_timeout`、`api_send_queue_timeout`、slow consumer drop 持续增长 | 队列超时停止增长 | send_queue_len、api_send_queue_len、drops_delta |
| `memory_high` | `runtime.MemStats` 或 RSS/WorkingSet 超过配置阈值 | 低于恢复阈值 | alloc_mb、sys_mb、rss_mb |

## DingTalk 发送边界

确认进入修复阶段后，钉钉实现至少需要：

- 支持关键字或加签 secret 两种机器人安全策略。
- 签名使用 timestamp + secret 生成，测试固定时间戳输出非空且稳定。
- webhook、secret 不写日志明文，只记录是否配置和脱敏尾号。
- webhook 失败要记录 `dingtalk_failed`，不能吞掉。当前已有基础实现和测试。
- 同一告警 dedupe key 在冷却期内不重复发送。当前已有基础实现和测试。
- 恢复通知与触发通知都写按天日志；当前触发通知已有日志，恢复通知仍待补齐。
- 支持 dev 环境关闭发送但保留日志，避免本地测试误发。

## 与实施计划的关系

本矩阵对应实施计划 Task 8。执行顺序不能早于 Task 1-4 的生产安全收紧，也应在 Task 7 monitor snapshot 之后或同时进行，因为告警规则需要可靠的监控快照输入。

推荐顺序：

1. Task 1-4：先收紧公开路由、JWT、Origin、上游 key。
2. Task 6：修复长连接生命周期和容量释放语义。
3. Task 7：提供 monitor snapshot 和按天日志落点。
4. Task 8：实现 alert/DingTalk，接入容量、Redis、OpenAI、错误率和队列压力。
5. Task 9/10：把告警次数和状态纳入 day/week/month stats 与诊断面板。

## 验收口径

| 目标 | 必须提供的证据 |
| --- | --- |
| 签名正确 | `go test ./internal/service/alert -count=1` 通过 |
| 冷却去重 | 同一告警连续触发只发送一次，冷却后才再次发送 |
| 恢复通知 | 指标恢复后写 `alert_recovered` 并可发送恢复通知 |
| 日志可追溯 | 当前已能在测试日志中查 `alert_firing`、`dingtalk_sent`、`dingtalk_failed`；最终还需 `alert_recovered` |
| 面板可见 | 诊断页显示当前告警状态、规则、阈值、当前值、最近通知时间 |
| 敏感信息不泄露 | webhook/secret 在日志、API、页面中只脱敏显示 |

## 当前结论

当前项目已经部分满足钉钉过载预警目标：有独立 alert 服务、DingTalk 发送器、配置、签名、冷却、stats 事件，以及 `alert_firing`、`dingtalk_sent`、`dingtalk_failed` 按天日志测试。该目标仍未完全完成，因为恢复通知、告警状态面板、跨实例去重、更多复合过载规则和长期持久化仍需继续实现。
