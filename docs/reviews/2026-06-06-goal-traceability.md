# Go + OpenAI WebSocket 目标追踪清单

审查日期：2026-06-06

用途：把用户提出的 8 条目标逐条映射到当前代码证据、已发现问题和后续统一修复任务。当前阶段仍是“先审查并列出问题”，未得到确认前不进入业务代码统一修改。

## 总体结论

当前项目还不能证明“百万并发 + 1 秒内稳定响应”。现有代码更接近本地调试、开发验证和中小规模 Realtime 网关雏形。要达到生产级目标，必须补齐生产安全边界、上游配额模型、集群容量设计、监控日志、钉钉告警、日/周/月统计、Workspace 写入确认、压测工具和压测报告。

官方依据：

- OpenAI Realtime WebSocket 文档明确其 WebSocket 方式适合后端服务到 OpenAI 的 server-to-server 连接，API Key 应只在安全后端可用；浏览器/移动端更推荐 WebRTC。
- OpenAI 生产最佳实践要求安全保存 API Key，避免写入代码或公开仓库，并用环境变量或密钥管理服务注入。
- OpenAI Rate limits 文档说明限流按组织、项目、模型等维度生效，百万并发必须纳入上游配额和用量等级评估。

参考链接：

- https://developers.openai.com/api/docs/guides/realtime-websocket
- https://developers.openai.com/api/docs/guides/production-best-practices
- https://developers.openai.com/api/docs/guides/rate-limits#usage-tiers

## 目标 1：审查 Go + OpenAI WebSocket 架构是否正确，是否能顶住百万并发和 1 秒内响应

当前状态：未满足，已完成第一轮审查并形成明确结论。

证据：

- `internal/provider/openai/client_ws.go:284` 每个 App 会话都会建立一条上游 OpenAI Realtime WebSocket。
- `internal/provider/openai/client_ws.go:342-346` 每个会话启动 4 个主 goroutine。
- `internal/provider/openai/client_ws.go:82-86` 每个会话有两个 512 缓冲队列。
- `conf/config.yaml:47` 单实例最大活跃会话配置为 `100000`，不是百万。
- `internal/service/session/capacity.go:11-32` 容量控制是单进程计数，不是集群级准入。
- `internal/service/metrics/metrics.go:34` 使用全局 `sync.Mutex`，高频指标更新和快照读取会争用同一把锁。
- 未发现容量压测工具、pprof 暴露、FD/socket 指标和容量报告。

问题判断：

- 当前结构不能承诺单机百万并发。
- 即使多实例扩展，也必须同时证明 LB、Redis、指标系统、OpenAI/第三方中转配额、OS 文件描述符、socket、内存、网络带宽和日志吞吐。
- “1 秒内响应”需要定义口径，例如连接建立时间、首包延迟、完整回答延迟、P95/P99、错误率和上游限流场景。

对应修复任务：

- `docs/superpowers/plans/2026-06-06-realtime-production-hardening.md` 的 Task 6 和 Task 11。
- 后续需要新增 `tools/wsload` 和 `docs/production-capacity.md`，以实测结果作为验收依据。

## 目标 2：不合理的地方先全部列出并支持问题所在

当前状态：基本满足第一轮审查，仍可继续补充更细的证据。

已归档问题：

- P0：单会话直连一条上游 WS，不能单机百万并发。
- P0：公开调试路由暴露敏感能力。
- P0：缺少生产启动安全 gate，prod 误配置不会统一失败；细化证据见 `docs/reviews/2026-06-06-production-startup-security-readiness-matrix.md`。
- P0：JWT 默认密钥兜底不安全。
- P0：上游 API Key 允许通过 URL query 传入。
- P0：全局 metrics mutex 是高并发热锁。
- P0：Workspace 工具允许模型直接写文件，细化证据见 `docs/reviews/2026-06-06-workspace-write-safety-matrix.md`。
- P0：长连接运行时韧性和背压处理不能证明 1 秒内稳定响应。
- P1：Origin 校验硬编码。
- P1：Realtime 协议兼容和第三方中转缺少端到端证明。
- P1：真实 IP 采集不可靠，未配置 trusted proxies。
- P1：Redis 限流降级会破坏集群限流。
- P1：Redis 启动失败直接 Fatal，缺少可配置降级策略。
- P1：缺少钉钉过载告警。
- P1：监控面板缺少用户、IP、所在地、进程、FD/socket、告警状态、按天日志落点等字段。
- P1：统计只有 daily 为主，缺少 day/week/month 完整聚合。
- P1：Web 请求看板只保留进程内最近 500 条。
- P2：旧事件处理器疑似死代码。
- P2：中文注释覆盖不均，需要补齐并建立编码防回归检查。
- P2：关键包测试覆盖不足。
- P2：没有容量证明体系。

主报告：

- `docs/reviews/2026-06-06-realtime-architecture-review.md`
- `docs/reviews/2026-06-06-production-exposure-matrix.md`
- `docs/reviews/2026-06-06-observability-gap-matrix.md`
- `docs/reviews/2026-06-06-monitoring-log-audit-matrix.md`
- `docs/reviews/2026-06-06-capacity-readiness-matrix.md`
- `docs/reviews/2026-06-06-realtime-protocol-compatibility-matrix.md`
- `docs/reviews/2026-06-06-runtime-resilience-backpressure-matrix.md`

## 目标 3：待确认后统一修改和修复

当前状态：遵守中。除前面已经完成的主题同步和审查文档外，本阶段不改业务逻辑。

已准备的统一修复计划：

- `docs/superpowers/plans/2026-06-06-realtime-production-hardening.md`

建议确认后的执行顺序：

1. 生产安全配置与启动校验。
2. 公开路由收紧与 Trusted Proxy。
3. JWT 默认密钥移除与用户名称采集。
4. Realtime Origin 与上游 Key 策略。
5. Workspace 写文件预览、确认与审计。
6. 长连接生命周期、背压和重连韧性修复。
7. 监控数据采集与按天日志落点。
8. 钉钉过载告警。
9. 天/周/月统计聚合。
10. 监控面板和图表。
11. 压测工具与容量报告。
12. 中文注释补齐与编码防回归。

## 目标 4：所有项目代码使用中文详细注释逻辑、参数和功能作用

当前状态：部分满足。

证据：

- 以 UTF-8 读取 `cmd`、`conf`、`internal`、`web`、`README.md` 后，未发现 `锛`、`鍙`、`鏃`、`閰`、`鈥` 等典型 mojibake 片段。
- `cmd/server/main.go`、`web/index.html`、`web/ws-test.js` 等核心文件已有中文说明，但详细程度不一致。
- 现有注释还没有覆盖所有关键参数、状态流转、错误边界、监控字段和 WebSocket 事件含义。
- `docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md` 已列出零中文注释和低覆盖文件，例如 Workspace handler/service、Web 静态处理器、Web metrics、聊天看板、主题脚本和测试文件。
- `docs/reviews/2026-06-06-source-commentary-coverage-inventory.md` 已记录当前文件级统计：68 个源码/页面/配置文件、18479 行、中文注释约 1228 行；`web/chat.html`、`web/diagnostics.html`、`web/theme.js`、Workspace 相关 Go 文件和多组测试文件仍为 0 中文注释。

问题判断：

- 一次性全仓库补齐详细中文注释风险很高，容易产生大量无效注释和过时注释。
- 应先约束后续所有新增/修改文件，再按模块分阶段补齐关键逻辑注释。
- 需要保留编码扫描命令作为防回归验证，避免终端显示编码问题再次被误判为源文件损坏。

对应修复任务：

- 实施计划 Task 12。
- `docs/commentary-cleanup.md` 已建立；后续需要按 `docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md` 分阶段补齐源码注释。

## 目标 5：更详细监控面板，展示服务所有模块状态并记录日志

当前状态：部分满足，缺口较大。

已有能力：

- `internal/handler/debug_handler.go:37-44` 已返回 server、memory、capacity、features、redis、network、openai、responses、azure、metrics。
- `web/diagnostics.html` 已展示部分 Redis、OpenAI、Azure、token、错误等运行信息。
- `internal/service/metrics/metrics.go` 已记录部分会话、OpenAI 事件、token、错误和响应数据。

缺失字段：

- 在线用户明细：用户 ID、名称、真实 IP、所在地、设备、User-Agent。
- 进程信息：PID、Go version、OS/Arch、CPU、goroutines、FD/socket/handle 数。
- 内存趋势：实时内存、GC、堆对象、趋势图。
- token 总览：实时、日/周/月、模型维度、用户维度。
- 缓存命中：业务缓存命中/未命中总量。
- OpenAI 信息：上游连接数、连接失败、重连、限流、剩余额度或响应头可用信息。
- 错误中心：错误级别、模块、最近错误、错误率。
- 告警状态：当前过载、最近钉钉通知、恢复通知。
- 日志落点：每次监控快照写入的日志文件、时间和结果。

对应修复任务：

- 实施计划 Task 7 和 Task 10。

## 目标 6：日志沿用按天记录逻辑

当前状态：基础能力已有，后台 monitor 快照、基础日志清理调度和 `log_cleanup` 清理摘要已接入；审计闭环仍未满足。

证据：

- `internal/logger/logger.go:44-60` 已按模型/模块创建带日期的日志文件。
- `internal/logger/logger.go` 已有 `StartCleanupScheduler` 和日志清理函数。
- `cmd/server/main.go` 已在服务启动时调用 `monitor.StartPeriodicLogger(...)`。
- `cmd/server/main.go` 已在服务启动时按 `logs.retention_days` 和 `logs.cleanup_interval` 调度日志清理，并在关闭时等待退出。
- `internal/logger/logger_test.go` 已覆盖清理摘要写入、相对路径、不暴露 `root_dir` 和审计文件短句柄释放。
- `internal/service/monitor/sampler.go` 会立即并周期性写入 monitor 快照。
- 2026-06-07 运行时烟测只访问 `/health`，当天日志中出现 `addr=:8098` 的 `monitor snapshot`。
- `docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md` 已补充跨零点轮换、清理调度、审计事件 schema、敏感字段脱敏和面板字段持久化证据。

缺口：

- monitor snapshot 已有后台周期日志和统一 `event/event_date/instance_id` 字段，但仍缺敏感字段统一脱敏和跨实例对账。
- 过载告警、Workspace 写入审计、day/week/month 统计关键事件已有初始落点，但长期 Redis/DB 持久化和恢复事件仍不完整。
- 日志清理调度和清理结果审计已有基础实现，但缺压缩归档和跨零点验证。
- 长连接持有的 logger 跨零点是否自动写入新日期文件仍缺少实现或测试证明。

对应修复任务：

- 实施计划 Task 7、Task 8、Task 9、Task 12。

## 目标 7：增加系统过载预警，使用钉钉机器人通知

当前状态：未满足。

证据：

- 全仓库未发现可用的 `dingtalk`、`DingTalk`、`alert` 服务实现。
- `internal/handler/openai_handler.go:89-94` 容量不足只返回 503。
- `internal/handler/azureai_handler.go:64-69` Azure 容量不足也只返回 503。
- `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md` 已补充限流、Redis 异常、OpenAI 连接/重连失败、冷却恢复和日志落点证据。

缺口：

- 无钉钉 webhook、secret、签名、冷却时间、恢复通知配置。
- 无过载阈值策略，例如容量使用率、内存阈值、错误率、Redis 异常、OpenAI 重连失败。
- 无告警状态面板和按天日志记录。

对应修复任务：

- 实施计划 Task 8。

## 目标 8：增加天、周、月统计和多种资源展示图

当前状态：未满足。

证据：

- `internal/service/stats/stats.go` 已提供进程内 day/week/month 统一资源统计。
- `internal/handler/stats_handler.go` 已提供 `/api/stats/resources` 查询 API，支持 period/source/model/kind 过滤。
- `web/diagnostics.html` 已通过 `/api/stats/resources` 展示 day/week/month 资源统计。
- Realtime、Responses、容量拒绝、限流拒绝、错误、告警触发和 Workspace 写入已进入同一进程内 stats 口径。
- `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md` 已补充 Realtime、Responses、Redis billing、缓存命中和成本模型的逐项证据。

缺口：

- 缺少 day/week/month Redis/DB 持久化和跨实例聚合。
- 业务缓存命中/未命中已有 stats/API/log 字段口径，模型配置缓存已作为真实生产者接入；仍缺更多业务缓存来源和长期持久化。
- 缺少告警恢复事件状态机。
- 现有前端资源图仍是轻量条形图，缺少更多图表形态和长期历史数据源。

对应修复任务：

- 实施计划 Task 9 和 Task 10。

## 当前验收状态

已完成：

- 架构审查报告。
- 统一修复实施计划。
- 本追踪清单。
- 总目标完成验收门槛矩阵。
- 生产启动与配置安全就绪矩阵。
- 监控字段与按天日志落点审计矩阵。
- 按天日志与审计持久化就绪矩阵。
- 统计、计费与缓存命中就绪矩阵。
- 中文注释与编码质量就绪矩阵。
- 源码中文注释覆盖文件级清单。
- 钉钉告警与系统过载预警就绪矩阵。
- Workspace 文件修改安全就绪矩阵。
- 主题同步功能已独立验证，不属于本追踪清单的主线目标。

未完成：

- 未进入统一修复阶段。
- 未补齐生产安全。
- 未补齐监控日志。
- 未补齐钉钉告警。
- 未补齐天/周/月统计图。
- 未补齐容量压测工具和报告。
- 未完成全仓库中文注释补齐与编码防回归制度。
- 未达到 `docs/reviews/2026-06-06-completion-acceptance-gates.md` 定义的全局完成门槛。
- `docs/reviews/2026-06-06-test-quality-gates-readiness-matrix.md` 已细化测试验证缺口；当前 `go test ./... -cover` 虽通过，但 `cmd/server`、`internal/middleware`、`internal/service/metrics`、`internal/service/workspace` 等关键包仍为 0.0% 覆盖，不能作为生产目标完成证据。
- `docs/reviews/2026-06-06-current-p0-evidence-snapshot.md` 已用当前工作区重新定位 P0/P1 问题行号；后续修复前应以该快照校准旧报告中的历史行号。

## 下一步确认点

如果确认开始统一修复，建议先执行实施计划 Task 1-4，因为这些任务直接阻断生产安全和上游 key 泄露风险。Task 1-4 通过测试后，再进入 Workspace 写入确认、长连接韧性、监控、告警、统计、面板和压测。

确认前收口索引见 `docs/reviews/2026-06-06-pre-fix-confirmation-brief.md`。该文件把 8 条用户目标、现有证据、第一阶段 Task 1-4 范围和验收命令集中到一处，用于进入修复前的最终确认。
