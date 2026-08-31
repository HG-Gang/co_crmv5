# 当前目标验收差距矩阵

审查日期：2026-06-08

## 结论

长期目标仍未完成，不能标记为已达成。当前仓库已经分批补上了生产安全开关、基础监控快照、AI 项目助手页面、主题同步、钉钉告警、按天日志、进程内 day/week/month 统计和压测工具雏形；但这些能力仍不足以证明百万并发、1 秒响应、跨实例长期统计、完整日志审计闭环和全项目中文详细注释。

本矩阵只做验收差距确认，不执行修复。确认进入修复后，应以本文件和 `docs/reviews/2026-06-08-realtime-current-architecture-audit.md` 作为第一批任务拆分依据。

## 目标 1：审查 Go + OpenAI WebSocket 架构是否正确，是否能顶住百万并发和 1 秒响应

状态：未满足。

当前证据：
- `conf/config.yaml` 中 `capacity.max_active_sessions: 100000`，这是单实例准入上限，不是百万并发压测结果。
- `internal/service/session/capacity.go` 使用进程内 atomic 计数，不是跨实例容量控制。
- `internal/provider/openai/client_ws.go` 每个 App 会话持有一条上游 Realtime WebSocket，并启动四个主 goroutine。
- `docs/production-capacity.md` 已明确当前未实测百万并发，也未实测 1 秒首包、首音频或完整响应的 P95/P99。

缺口：
- 没有真实百万连接压测报告。
- 没有多实例 + LB + Redis Cluster + 上游配额联合容量模型。
- 没有 Realtime 首包、首音频、完整响应的 p50/p95/p99 指标闭环。
- 没有 OS FD/socket、CPU、内存、GC、网络带宽的阶梯容量曲线。

验收所需证据：
- `tools/wsload` 或等价压测报告，覆盖单实例阶梯压测和多实例压测。
- 报告包含连接数、ramp、duration、错误率、close code、p50/p95/p99、资源曲线、Redis 和上游状态。
- 上游 OpenAI/第三方中转提供明确并发连接、RPM、TPM、音频 token 和 429 策略证据。

## 目标 2：不合理地方全部列出并支持问题所在

状态：部分满足。

当前证据：
- `docs/reviews/2026-06-08-realtime-current-architecture-audit.md` 已按 P0/P1/P2 列出当前主要问题、证据、影响和确认后修复方向。
- 旧审查文档中已有安全、容量、日志、统计、Workspace、注释和测试矩阵。

缺口：
- 旧文档中部分内容存在编码损坏或历史行号漂移。
- 问题清单需要在每次进入修复前重新用当前源码校准行号。

验收所需证据：
- 一份当前源码可读、UTF-8 正常、行号可复核的问题清单。
- 每个 P0/P1 项都有对应文件、函数或配置字段。

## 目标 3：待确认后统一修改和修复

状态：未进入统一修复阶段。

当前证据：
- 当前新增的是审查文档，没有修改生产业务逻辑。
- 工作树已有大量历史修改，不能盲目回滚。

缺口：
- 尚未收到“按某个修复顺序开始统一修复”的最终确认。
- 修复前需要确认第一批范围，例如安全/WS 限流/监控日志/统计持久化。

验收所需证据：
- 用户确认修复范围。
- 每个修复项有测试或可复核验证。
- 业务代码变更后通过目标测试、构建或 smoke test。

## 目标 4：所有项目代码必须中文详细注释逻辑、参数、功能作用

状态：未满足。

当前证据：
- 多个核心 Go 文件已有中文注释，例如 `internal/service/stats/stats.go`、`internal/service/monitor/snapshot.go`、`internal/service/metrics/metrics.go`。
- `web/theme.js` 已有中文说明，解释主题归一化、localStorage、唯一写入口和跨页面同步。
- `docs/commentary-cleanup.md` 和旧 commentary inventory 已开始记录注释治理。

缺口：
- 不能证明所有文件均有中文详细注释。
- 部分文档或注释存在 mojibake 乱码，PowerShell 默认编码读取也容易误判。
- 一次性机械注释全仓会增加噪音，应按模块分批治理。

验收所需证据：
- 编码扫描脚本能够识别并阻止源文件乱码。
- 每个核心模块的导出类型、配置字段、handler 入参、WebSocket 事件、状态机、边界条件和风险点有中文说明。
- 注释覆盖清单按模块关闭：安全、Realtime、Workspace、监控、告警、统计、Web、测试。

## 目标 5：更详细监控面板，展示服务所有模块状态并记录日志

状态：部分满足。

当前证据：
- `internal/handler/debug_handler.go` 的 `DebugStatusHandler` 返回统一 monitor snapshot。
- `internal/service/monitor/snapshot.go` 汇总 server、process、memory、capacity、redis、modules、metrics、alerts。
- `web/diagnostics.html` 展示协程数、活跃会话、内存、过载告警、Redis、OpenAI、Responses、Azure、业务 token、错误、最近会话。
- `internal/service/metrics/metrics.go` 的 session snapshot 已包含 `user_id`、`user_name`、`remote_addr`、`real_ip`、`ip_location`、token、队列、错误和响应信息。
- `web/diagnostics.html` 已展示真实 IP、所在地、用户 ID、用户名称和 token/cache/reasoning 字段。

缺口：
- 当前面板主要读单实例进程内快照，不是跨实例全局监控。
- `recent_sessions` 默认只保留最近 50 条，不可能展示百万在线全量明细。
- 真实所在地目前依赖请求头或本地 IP 分类，还没有内置可信 GeoIP 数据库。
- 缺少 p50/p95/p99、OpenAI 上游错误率、Redis pool 趋势、钉钉发送状态钻取、错误中心和跨实例汇总。
- “所有信息全部必须记录在日志”尚未闭环，仍有大量事件只存在内存统计中。

验收所需证据：
- `/api/debug/status` 或新的 monitor API 返回目标字段，并明确字段来源。
- 面板展示在线人数、Go runtime、内存、协程、token、缓存命中、用户真实 IP/所在地、OpenAI/Redis/错误/告警。
- 同一批字段能在按天日志或长期审计系统中查询。
- 多实例情况下支持 instance_id 维度和聚合维度。

## 目标 6：日志沿用按天记录逻辑

状态：部分满足。

当前证据：
- `internal/logger/logger.go` 使用 daily file writer。
- `internal/service/monitor/snapshot.go` 的 `LogSnapshotThrottled` 会把监控快照写入按天日志。
- `internal/service/alert/dingtalk.go` 会写钉钉发送、失败、冷却、恢复审计日志。
- `internal/service/workspace/audit.go` 会写 Workspace 写入审计日志。

缺口：
- 并非所有资源事件、token 事件、缓存命中、限流、队列、错误和上游事件都有统一按天 schema。
- 日志归档、压缩、跨实例收集和查询还没有形成完整方案。

验收所需证据：
- 统一 audit event schema。
- monitor_snapshot、stats_rollup、alert_firing、alert_recovered、dingtalk_sent、workspace_write、rate_limit、openai_error、token_usage 等事件均按天落盘。
- 敏感字段脱敏测试覆盖 token、api_key、webhook、diff、content。

## 目标 7：增加系统过载预警，使用钉钉机器人通知

状态：部分满足。

当前证据：
- `internal/service/alert/dingtalk.go` 实现钉钉 webhook、签名、冷却、发送失败审计和恢复状态。
- `internal/service/monitor/overload_alert.go` 通过 `AlertSnapshotOverload` 评估容量、协程、内存、GC、队列等过载信号。
- `internal/handler/alert_helper.go` 在容量拒绝时触发钉钉过载告警。
- 钉钉测试覆盖发送、禁用、冷却、失败、恢复、IP/所在地内容。

缺口：
- 阈值仍主要是硬编码常量，未完全配置化。
- 缺少 p99 延迟、OpenAI 连接失败率、Redis 异常、错误率突增、限流突增等复合告警。
- 告警需要明确 warning、critical、recovered 分级。

验收所需证据：
- 配置文件可调整过载阈值。
- 钉钉消息包含 instance_id、provider、reason、active/max、queue、memory、goroutine、p99、user/IP/所在地和最近错误摘要。
- 告警、恢复、冷却、发送失败均写按天审计日志。

## 目标 8：增加统计信息，统计天、周、月各种资源的多种展示图

状态：部分满足。

当前证据：
- `internal/service/stats/stats.go` 提供 `RecordUsage`、`RecordResourceEvent`、`ResourcePeriodsWithFilter`。
- `internal/handler/stats_handler.go` 提供 `/api/stats/resources`。
- `web/diagnostics.html` 展示 day/week/month 请求、失败、token、缓存、费用、平均延迟和资源条形图。
- `web/chat.html` 提供当前会话 token 当前/累加统计，并支持 bar、line、area、stacked、grouped 图表形状。

缺口：
- `stats` 仍是进程内存储，`maxUsageRecords = 10000`，重启丢失，多实例不可聚合。
- 诊断页 day/week/month 主要是最近窗口，不是长期生产报表。
- 图表形状支持在 AI 项目助手 token 图上更完整，诊断资源统计图仍较基础。

验收所需证据：
- 长期 stats 数据写入 Redis/DB/日志聚合。
- API 能按 day/week/month 返回跨实例聚合结果。
- 前端支持至少条形、折线、面积、堆叠/分组等多种资源图展示。
- 资源维度覆盖 token、缓存命中/未命中、错误、限流、容量拒绝、告警、Workspace 写入、费用、延迟。

## 额外目标：颜色模式跨页面统一

状态：基本满足，但仍需运行时 smoke 验证。

当前证据：
- 多个页面引入 `/web/theme.js?v=20260607-theme`。
- `web/theme.js` 使用统一 `localStorage` key：`tozo-ws-theme`。
- `web/theme.js` 通过 `applyTheme` 更新 body class，并广播 `tozo-theme-change`。
- `internal/handler/web_static_handler.go` 能向静态 HTML 注入或归一化 theme.js 标签，防止新增页面遗漏。
- `internal/handler/web_static_handler_test.go` 覆盖 theme.js 注入和去重。

缺口：
- 本轮未启动服务和浏览器 smoke test。
- `web/ws-test.js` 仍有局部 `applyTheme` fallback，需要后续确认是否保留兼容逻辑或统一到 `window.TozoTheme`。

验收所需证据：
- 打开测试面板修改颜色后，语音对话测试、聊天看板、Redis 监控、诊断看板、Responses 测试、Azure 监控均同步生效。
- 新增页面不手写主题逻辑也能继承 `theme.js`。

## 建议的下一步确认范围

建议不要一次性修完整个长期目标。合理顺序是：

1. 先修生产 WS 鉴权和 query token/key 策略。
2. 再修 WS 消息级限流、背压、慢消费者断开和 SLA 指标。
3. 然后修 metrics 热路径和监控日志统一落盘。
4. 接着把 day/week/month 统计迁移到可跨实例聚合的数据源。
5. 再扩展诊断面板和多图表。
6. 最后分模块修中文注释和乱码。

在真实压测、上游配额和跨实例统计闭环完成前，不能声明当前服务已经支持百万并发或 1 秒内稳定响应。
