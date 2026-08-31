# 总目标完成验收门槛矩阵

审查日期：2026-06-06

当前阶段：只审查并固化证据，不修改业务逻辑。

## 总体结论

当前不能把主目标标记为完成。原因很明确：已有工作完成了主题同步、第一轮架构审查、实施计划和多份专项矩阵，但用户提出的 8 条目标中，大多数仍处于“已定位缺口，等待确认后修复”的状态。

本文件定义后续真正完成目标时必须提供的证据。任何阶段只要缺少对应证据，就只能说明“有进展”，不能说明“已完成”。

## 官方边界

以下官方边界会影响验收口径：

- OpenAI Realtime WebSocket 是服务端到服务端连接形态；浏览器和移动端通常更推荐 WebRTC。见 [Realtime API with WebSocket](https://platform.openai.com/docs/guides/realtime-websocket)。
- API key 应只保存在安全后端，不能暴露在浏览器、移动端、URL query、代理日志或客户端代码中。见 [Production best practices](https://platform.openai.com/docs/guides/production-best-practices) 和 [Best Practices for API Key Safety](https://help.openai.com/en/articles/5112595)。
- OpenAI API rate limits 按组织、项目、模型等维度生效，并包含 RPM、RPD、TPM、TPD、IPM 等限制。百万并发必须把上游配额纳入容量模型。见 [Rate limits](https://platform.openai.com/docs/guides/rate-limits/usage-tiers)。

## 目标级验收矩阵

| 用户目标 | 当前状态 | 完成必须提供的证据 | 当前缺口来源 |
| --- | --- | --- | --- |
| 1. 审查 Go + OpenAI WebSocket 架构是否正确，是否能顶住百万并发和 1 秒响应 | 已审查，未满足 | `docs/production-capacity.md` 存在；`tools/wsload` 可运行；报告包含实例数、每实例连接数、LB、Redis、上游配额、FD/socket、CPU、内存、带宽、P50/P95/P99、错误率、成本；1 秒延迟口径已定义并有压测数据 | `docs/reviews/2026-06-06-capacity-readiness-matrix.md`、`docs/reviews/2026-06-06-runtime-resilience-backpressure-matrix.md` |
| 2. 不合理地方全部列出并支持问题所在 | 第一轮已满足，后续需随代码变化维护 | 所有 P0/P1/P2 问题都有文件位置、风险、修复方向和验收证据；修复前用当前源码行号快照校准证据；修复后每个问题都有测试或运行证据关闭 | `docs/reviews/2026-06-06-pending-fix-backlog.md`、`docs/reviews/2026-06-06-current-p0-evidence-snapshot.md` |
| 3. 待确认后统一修改和修复 | 正在遵守 | 用户明确确认开始修复后，按实施计划 Task 1-12 顺序执行；第一阶段优先 Task 1-4；每个 Task 独立测试通过；不绕过生产安全优先级 | `docs/superpowers/plans/2026-06-06-realtime-production-hardening.md`、`docs/reviews/2026-06-06-pre-fix-confirmation-brief.md` |
| 4. 所有项目代码中文详细注释逻辑、参数和功能作用 | 未满足 | 修改范围内关键函数、配置字段、handler 入参、WebSocket 事件、状态机、监控字段都有中文说明；全仓库注释覆盖清单关闭；UTF-8/mojibake 扫描通过 | `docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md`、`docs/reviews/2026-06-06-source-commentary-coverage-inventory.md`、`docs/commentary-cleanup.md` |
| 5. 更详细监控面板展示所有模块状态且全部写日志 | 未满足 | 面板展示在线人数、在线用户、真实 IP/所在地、用户 ID/名称、PID、FD/socket、内存、goroutines、Redis、缓存命中、token、OpenAI/Azure/Responses、错误中心、告警状态；同字段可在当天日志 `monitor_snapshot` 或审计事件中追溯 | `docs/reviews/2026-06-06-monitoring-log-audit-matrix.md`、`docs/reviews/2026-06-06-observability-gap-matrix.md` |
| 6. 日志沿用按天记录逻辑 | 基础能力已有，审计闭环未满足 | 跨零点或模拟日期后新事件进入当天日志；长连接事件有明确 `event_date`；日志清理调度可验证；敏感字段脱敏；`event` schema 可检索 | `docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md` |
| 7. 增加系统过载预警，钉钉机器人通知 | 未满足 | `internal/service/alert` 存在；容量、内存、Redis、OpenAI 重连、错误率、队列压力有规则；签名、冷却、恢复通知、发送失败都有测试；面板和日志有 `alert_firing`、`alert_recovered`、`dingtalk_sent`/`dingtalk_failed` | `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md` |
| 8. 增加天、周、月统计和多资源图表 | 未满足 | `internal/service/stats` 存在；day/week/month 资源 key 或表存在；API 可按周期查询 token、费用、音频、错误、告警、限流、容量拒绝、Workspace 写入、缓存命中；前端图表可切换周期；日志有 `stats_rollup` | `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md` |

## 全局完成门槛

只有同时满足以下条件，才可以声明主目标完成：

| 门槛 | 必须通过的证据 |
| --- | --- |
| 生产安全 | prod 空 JWT secret 启动失败；公开敏感路由不可匿名访问；Origin 配置化；query key 在 prod 被拒绝；Trusted Proxy 配置有测试 |
| Realtime 协议 | 官方 OpenAI 和第三方 Realtime WS 中转分别完成握手、`session.update`、文本、音频、工具调用、错误事件冒烟测试 |
| 长连接韧性 | App 断开后容量快速释放；上游读阻塞被主动打断；OpenAI Ping 配置与实现一致；队列满对关键事件不静默丢弃 |
| Workspace 写入安全 | 模型工具和 HTTP 写接口只生成 pending diff；用户确认后才写磁盘；写入、拒绝、失败、回滚都有审计日志 |
| 监控日志 | 新 monitor API 或 debug API 返回目标字段；当天日志能检索 `monitor_snapshot`、`web_request_metric`、`admin_api_access` 和错误事件 |
| 钉钉告警 | 过载、恢复、冷却、签名失败、webhook 失败均有单元测试或可复现运行日志 |
| 统计图表 | Redis/DB 和日志可对账；前端可按天/周/月切换；统计覆盖 token、费用、错误、限流、告警、缓存、Workspace 写入 |
| 中文注释 | 修改范围和核心未注释模块完成中文说明；`docs/reviews/2026-06-06-source-commentary-coverage-inventory.md` 中 P1 清单关闭；编码扫描无源文件乱码；注释解释意图、参数和边界，不堆重复说明 |
| 容量证明 | `tools/wsload` 压测输出和 `docs/production-capacity.md` 能复现；报告明确不能由单实例承诺百万并发，必须给出集群拓扑和上游配额 |
| 测试验证 | `go test ./... -count=1` 通过；`docs/reviews/2026-06-06-test-quality-gates-readiness-matrix.md` 中的关键 0 覆盖包已按阶段关闭；新增关键包测试通过；如果有前端图表或页面变更，还需提供浏览器烟测或截图证据 |

## 阶段关闭规则

后续开始修复后，每个阶段关闭前必须同时满足：

1. 对应测试先失败再通过，或至少新增覆盖当前缺口的回归测试。
2. `go test ./... -count=1` 通过。
3. 相关文档中的缺口状态已更新为“已修复”，并补充实际证据。
4. 如果阶段涉及前端页面，必须做页面烟测，确认文字不重叠、图表有数据、交互能完成。
5. 如果阶段涉及日志、告警或统计，必须提供实际日志事件或可复现的测试日志。

## 当前不能完成的原因

当前仍处于“审查和固化证据”阶段。生产安全、监控日志、钉钉告警、天/周/月统计、容量压测、Workspace 写入确认和全仓库中文注释都没有完成实现和验证。因此主目标必须保持 active，不能标记完成。

生产安全门槛的细化启动 gate 见 `docs/reviews/2026-06-06-production-startup-security-readiness-matrix.md`。该文件中的 prod 配置校验、路由注册测试和 JWT/Origin/Redis/logs 规则未满足前，不能进入“生产可用”结论。
