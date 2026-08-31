# 当前源码证据索引

日期：2026-06-08

## 用途

本索引用于后续统一修复前快速复核“问题所在”。它只记录当前源码中能直接定位的证据，不替代完整审查结论。完整判断见：

- `docs/reviews/2026-06-08-realtime-current-architecture-audit.md`
- `docs/reviews/2026-06-08-active-goal-gap-matrix.md`
- `docs/superpowers/plans/2026-06-08-realtime-goal-closure-plan.md`

## P0 证据：百万并发不能由当前单实例证明

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| 单实例活跃会话上限是 100000 | `conf/config.yaml:62` | 配置值不是压测结果，也不是集群百万容量证明 |
| 容量计数是当前进程内 atomic | `internal/service/session/capacity.go:14` | 不能跨实例控制总在线人数 |
| 容量释放也是当前进程内 atomic | `internal/service/session/capacity.go:32` | 释放语义依赖当前进程生命周期 |
| OpenAI handler 在接入时调用容量准入 | `internal/handler/openai_handler.go:118` | 只保护新建连接，不保护已建 WS 内消息流 |
| Azure handler 也调用同一容量准入 | `internal/handler/azureai_handler.go:68` | OpenAI/Azure 共用单进程容量池 |

## P0 证据：每会话资源模型会放大百万连接压力

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| App 下行队列固定 512 | `internal/provider/openai/client_ws.go:85` | 百万连接下队列引用会形成巨大内存峰值 |
| OpenAI 上行队列固定 512 | `internal/provider/openai/client_ws.go:88` | 上游卡顿时会放大内存与延迟 |
| 每个 Client 创建两个主要队列 | `internal/provider/openai/client_ws.go:213`、`internal/provider/openai/client_ws.go:214` | 每会话至少有 App 和上游两个缓冲队列 |
| 每会话启动四个主协程 | `internal/provider/openai/client_ws.go:349` | 百万连接至少放大到数百万 goroutine |

## P0 证据：1 秒响应缺少完整 SLA 闭环

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| 当前配置只说明单实例容量，不定义 1 秒首包或 p99 | `conf/config.yaml:62` | 不能验收“1 秒响应” |
| 诊断面板读取容量上限 | `web/diagnostics.html:450` | 面板展示容量，但不是延迟 SLA |
| 当前审查文档已列出缺少 connect/first_event/first_audio/complete_response 指标 | `docs/reviews/2026-06-08-realtime-current-architecture-audit.md` | 第一批修复需补 SLA 指标 |

## P1 证据：WebSocket query token 仍存在泄漏风险

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| 测试面板连接时把 JWT 放入 URL query | `web/ws-test.js:384` | URL 可能进入历史、代理日志、网关日志 |
| AI 项目助手连接时把 JWT 放入 URL query | `web/chat.html:965` | 生产不应依赖这种方式 |
| 当前配置尚未出现 `allow_websocket_query_token` | `conf/config.go` | 需要新增独立开关，不能复用上游 key 开关 |

## P1 证据：上游 Key 策略已有基础但仍需严格区分开发/生产

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| 默认配置允许上游 query key | `conf/config.yaml:29` | 适合开发调试，不适合生产 |
| 开发配置允许上游 query key | `conf/config_dev.yaml:23` | 开发路径可保留 |
| 生产配置禁用上游 query key | `conf/config_prod.yaml:24` | 生产方向正确 |
| 生产校验禁止开启 query key | `conf/loader.go:161` | 已有启动安全 gate |

## P1 证据：HTTP 限流不覆盖 WebSocket 内消息流

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| RateLimit 是 Gin middleware | `internal/middleware/rate.go:44` | 只在 HTTP 请求或 WS 握手阶段生效 |
| 已建立 WS 后的文本、音频、工具事件不经过 Gin middleware | `internal/provider/openai/client_ws.go` | 需要在 `Client` 热路径增加消息级限流 |

## P1 证据：metrics 和 recent sessions 不适合百万在线全量明细

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| 最近会话保留上限是 50 | `internal/service/metrics/metrics.go:19` | 面板不能展示百万在线全量明细 |
| Snapshot 超过 50 会截断 sessions | `internal/service/metrics/metrics.go:897`、`internal/service/metrics/metrics.go:898` | 这是诊断快照，不是生产全量监控 |
| 已结束会话裁剪基于 maxRecentSessions | `internal/service/metrics/metrics.go:1076`、`internal/service/metrics/metrics.go:1099` | 适合内存保护，但不能当长期审计 |

## P1 证据：day/week/month 统计仍是进程内窗口

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| 用量记录上限是 10000 | `internal/service/stats/stats.go:50` | 重启丢失，多实例不可聚合 |
| records 初始化为内存 slice | `internal/service/stats/stats.go:166` | 当前默认不是 Redis/DB |
| 超过上限后截断内存记录 | `internal/service/stats/stats.go:187`、`internal/service/stats/stats.go:188` | 不适合长期天/周/月报表 |
| Reset 后仍回到内存 slice | `internal/service/stats/stats.go:514` | 说明当前统计层还未抽象持久化 store |

## P1 证据：按天日志已有基础但事件 schema 未统一

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| DebugStatusHandler 会节流写 monitor snapshot | `internal/handler/debug_handler.go:34` | 诊断轮询不会每次刷日志 |
| monitor snapshot 写入函数存在 | `internal/service/monitor/snapshot.go:392` | 已有按天监控快照基础 |
| monitor sampler 周期写快照 | `internal/service/monitor/sampler.go:44` | 后台采样已接入 |
| sampler 同时评估过载告警 | `internal/service/monitor/sampler.go:45` | 监控和告警已有基础链路 |

## P1 证据：钉钉过载阈值仍需配置化

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| goroutine 过载阈值是常量 100000 | `internal/service/monitor/overload_alert.go:17` | 不同环境无法灵活调整 |
| AlertSnapshotOverload 是过载评估入口 | `internal/service/monitor/overload_alert.go:38` | 后续配置化应从这里接入 |
| goroutine 超阈值触发告警 | `internal/service/monitor/overload_alert.go:60` | 当前可告警，但缺 warning/critical 分级 |

## P2 证据：监控面板已有基础但仍需扩展

| 证据 | 位置 | 判断 |
| --- | --- | --- |
| 诊断页展示容量上限 | `web/diagnostics.html:450` | 已能看容量，但缺完整 SLA 指标 |
| 诊断页展示用户、真实 IP、所在地 | `web/diagnostics.html:654` | 已覆盖用户定位基础字段 |
| 诊断页展示 token/cache/reasoning | `web/diagnostics.html:553`、`web/diagnostics.html:584` | 已有 token 观测基础 |
| 聊天页有 token 图表 | `web/chat.html:718` | AI 项目助手已有会话级 token 图 |

## 下一步修复入口

建议第一批直接按以下文档执行：

- `docs/reviews/2026-06-08-first-repair-batch-confirmation.md`
- `docs/superpowers/plans/2026-06-08-realtime-goal-closure-plan.md`

第一批只处理：

1. 生产 WebSocket 鉴权策略收紧。
2. WebSocket 消息级限流和慢消费者断开。
3. Realtime SLA 指标与延迟分位。

第一批完成后仍不能声明百万并发或 1 秒稳定响应；必须继续完成跨实例统计、统一审计日志、钉钉复合告警、监控面板扩展和真实容量压测。
