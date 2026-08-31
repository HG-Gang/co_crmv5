# 待确认统一修复清单

审查日期：2026-06-06

当前状态：仍处于“先审查、列问题、定位证据”阶段。未收到明确确认前，不进入业务代码统一修改和修复。

## 总体判断

当前 Go + OpenAI Realtime WebSocket 服务不能证明可以稳定支撑“百万并发 + 1 秒内响应”。现有实现适合作为本地调试、开发验证和中小规模 Realtime 网关雏形；要进入生产级目标，需要先补齐安全边界、容量证明、监控日志、告警统计和 Workspace 写入确认机制。

## 修复优先级总览

| 优先级 | 问题 | 证据位置 | 风险 | 确认后修复方向 |
| --- | --- | --- | --- | --- |
| P0 | 单会话直连一条上游 WebSocket，不能证明百万并发 | `internal/provider/openai/client_ws.go:284`、`internal/provider/openai/client_ws.go:343`、`conf/config.yaml:16`、`internal/service/session/capacity.go:9` | 每个 App 会话都会消耗上游连接、goroutine、队列和 FD/socket；单实例计数不能代表集群容量 | 定义容量口径，新增压测工具和容量报告，按多实例/LB/Redis/上游配额验证 |
| P0 | 公开调试路由匿名暴露 | `cmd/server/main.go:75`、`cmd/server/main.go:92`、`cmd/server/main.go:93`、`cmd/server/main.go:96` | 可匿名签发 token、读取 Redis、查看运行配置和请求明细 | prod 禁用测试 token；敏感 API 放入受保护组；静态测试页和敏感 API 分离 |
| P0 | 缺少生产启动安全 gate | `conf/loader.go:28`、`cmd/server/main.go:41`、`docs/reviews/2026-06-06-production-startup-security-readiness-matrix.md` | prod JWT、公开调试、Origin、Trusted Proxy、上游 query key、Redis 地址、日志保留等误配置不会在启动前统一失败 | 新增 `ValidateProductionConfig()`、安全配置结构、路由注册测试和启动校验日志 |
| P0 | JWT 空密钥回退固定默认值 | `internal/middleware/auth.go:36`、`internal/middleware/auth.go:139` | 生产误配置时仍会启动，token 可被伪造 | 移除默认密钥；prod 空 secret 启动失败；补测试 |
| P0 | 上游 API Key 可通过 query 覆盖 | `internal/handler/openai_handler.go:153`、`web/ws-test.js:386`、`web/ws-test.js:387` | Key 可能进入浏览器历史、代理日志、访问日志、错误日志 | prod 禁止 query key；dev 保留调试开关；生产改用服务端配置或一次性凭证 |
| P0 | 全局 metrics mutex 是高并发热锁 | `internal/service/metrics/metrics.go:34`、`internal/service/metrics/metrics.go:233`、`internal/service/metrics/metrics.go:655` | 高频事件和监控快照争同一把锁，指标系统可能拖慢业务链路 | 高频计数改 atomic/分片；最近记录和聚合计数分离；长周期统计落 Redis/DB |
| P0 | Workspace 工具可直接写文件 | `internal/provider/openai/gateway_protocol.go:617`、`internal/provider/openai/tool_execution.go:376`、`internal/handler/workspace_handler.go:44`、`docs/reviews/2026-06-06-workspace-write-safety-matrix.md` | 模型输出错误、prompt 注入或中转篡改工具调用可能直接改项目文件 | 改为 pending diff、用户确认、审计日志、回滚快照和敏感路径策略 |
| P0 | 长连接运行时韧性和背压处理不能证明 1 秒内稳定响应 | `internal/provider/openai/client_ws.go:388`、`internal/provider/openai/client_ws.go:625`、`internal/provider/openai/client_ws.go:793`、`internal/provider/openai/client_ws.go:1156`、`conf/config.yaml:78` | App 断开后容量释放可能被上游读阻塞拖延；慢消费者会丢消息；OpenAI Ping 配置未看到发送实现；队列满和重连路径不能证明 1 秒内响应 | App 断开时主动关闭上游连接；区分可丢弃/不可丢弃事件；明确实现或移除 `api_ping_interval`；补断网、慢客户端、半开连接和高频音频压测 |
| P1 | Origin 校验硬编码 | `internal/handler/openai_handler.go:36` | prod 只允许占位域名，真实部署不可控 | 配置 allowed origins；prod 必填；dev 允许 localhost |
| P1 | Realtime 协议兼容和第三方中转缺少端到端证明 | `conf/config.yaml:57`、`conf/config.yaml:70`、`internal/provider/openai/config.go:133`、`internal/handler/openai_handler.go:152`、`web/audio.html:708` | 第三方中转可能只是普通 HTTP 中转或不完整支持 Realtime WS；语音页旧字段可能导致页面测试失败 | 区分 Realtime WS 中转和普通 HTTP 中转；收紧 query key；统一 GA 事件字段；补握手、文本、音频、工具调用冒烟测试 |
| P1 | 真实 IP 采集不可靠 | `internal/handler/openai_handler.go:72`，仓库未发现 `SetTrustedProxies` | 反向代理/LB 后不能可靠得到用户真实 IP，也无法准确做所在地统计 | 配置 trusted proxies；统一 IP 解析；接入 GeoIP；记录用户 IP 和所在地 |
| P1 | Redis 限流降级破坏集群限流 | `internal/middleware/rate.go:136`、`internal/middleware/rate.go:158` | Redis 异常时多实例各自本地限流，全局保护失效 | prod 配置 fail-closed/fail-open；异常告警；熔断状态进入监控 |
| P1 | Redis 启动失败直接 Fatal | `internal/service/redis/redis.go:60` | Redis 短时不可用会导致服务不可启动 | 按环境配置强/弱依赖；弱依赖启动 degraded 并告警 |
| P1 | 缺少钉钉过载告警 | 仓库未发现可用 `DingTalk`/`dingtalk`/`alert` 服务；容量不足当前只返回 503；细化证据见 `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md` | 过载、Redis 异常、上游重连失败、错误率升高和队列压力无法主动通知 | 新增告警配置、签名、冷却、恢复通知、面板状态和日志落点 |
| P1 | 监控面板字段不足 | `internal/handler/debug_handler.go`、`web/diagnostics.html`、`internal/service/metrics/metrics.go` | 用户目标中的在线明细、PID、FD/socket、所在地、告警状态、日志落点等仍缺失 | 新增 monitor snapshot、用户明细、进程资源、OpenAI 状态、错误中心、告警状态 |
| P1 | 按天日志与审计持久化未闭环 | `internal/logger/logger.go:42`、`internal/logger/logger.go:155`、`cmd/server/main.go:52`、`docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md` | 有按天日志文件基础设施，但缺跨零点轮换证明、清理调度、统一 event schema、敏感字段脱敏和面板字段持久化 | 新增 monitor/audit writer、日志保留配置、清理后台任务、统一审计事件和 redact helper |
| P1 | 缺少 day/week/month 统计聚合 | `internal/service/billing/billing.go:220`、`internal/service/metrics/metrics.go:124`、`docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md` | 只有 daily 维度或进程内指标，不能满足天/周/月图表、业务缓存命中和统一成本统计 | 新增 stats service、统计 API、Redis/DB key 设计、成本模型和图表周期切换 |
| P1 | Web 请求指标只保留进程内最近 500 条 | `internal/handler/web_metrics_handler.go:19`、`internal/handler/web_metrics_handler.go:21` | 重启丢失，多实例不可聚合，不适合作长期审计 | 内存保留调试窗口；长期统计写 Redis/DB/日志平台 |
| P2 | 旧事件处理器疑似死代码 | `internal/provider/openai/events_client.go`、`internal/provider/openai/events_server.go`，未发现主链路引用 | 容易误导维护者，以为事件处理走旧文件 | 删除或合并到当前 `client_ws.go` / `gateway_protocol.go` 主链路 |
| P2 | 中文注释覆盖不均 | `docs/commentary-cleanup.md`、`docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md` 已列优先级和具体文件 | 不能满足“所有项目代码中文详细注释”的目标；Workspace、Web 静态处理、Web metrics、聊天看板、主题脚本和测试文件缺口明显 | 按安全、Realtime、Workspace、监控、告警、统计、Web、测试分阶段补齐，避免无效注释 |
| P2 | 文件级注释覆盖清单未关闭 | `docs/reviews/2026-06-06-source-commentary-coverage-inventory.md` | 当前仍有多个 P1 文件 0 中文注释，不能声明“所有文件已中文详细注释” | Task 12 先关闭 P1 文件，再处理测试和占位文件 |
| P2 | 测试覆盖不足 | `docs/reviews/2026-06-06-test-quality-gates-readiness-matrix.md` 已记录 `go test ./... -cover` 结果，多个关键包 0.0% 覆盖 | 安全、告警、统计、Workspace 写入、限流策略缺少回归保护 | 修复阶段按 TDD 补目标测试，再改实现 |
| P2 | 缺少容量证明体系 | 未发现 `tools/wsload`、`docs/production-capacity.md`、pprof、FD/socket 指标 | 不能用证据证明百万并发和 1 秒延迟 | 新增压测工具、容量报告、pprof、资源指标和验收门槛 |

## 建议确认后的执行顺序

1. 生产安全配置与启动校验：prod 下 JWT secret、allowed origins、敏感调试开关必须显式配置。
2. 公开路由收紧与 Trusted Proxy：把 token、debug、redis、metrics、模型状态等敏感 API 从匿名公开组移出。
3. JWT 默认密钥移除与用户名称采集：删除固定默认密钥，扩展 claim/context/session/metrics 用户字段。
4. Realtime Origin 与上游 Key 策略：prod 禁止 URL query 传上游 key；dev 保留显式调试路径。
5. Workspace 写文件预览、确认与审计：模型只能产出待确认 diff，用户确认后才写入。
6. 长连接生命周期、背压和重连韧性修复：确保 App 断开快速释放容量，队列满有明确语义，上游 Ping 配置与实现一致。
7. 监控数据采集与按天日志落点：补在线用户、真实 IP、所在地、进程资源、OpenAI 状态、错误中心。
8. 钉钉过载告警：接入容量、内存、错误率、Redis、上游重连等告警规则。
9. 天/周/月统计聚合：统一资源统计模型，覆盖 token、费用、音频、错误、告警、限流、Workspace 写入。
10. 监控面板与图表：在诊断看板展示实时状态、周期图表、告警状态和日志落点。
11. 压测工具与容量报告：新增 WebSocket 压测工具，输出 P95/P99、错误率、资源曲线和集群拓扑。
12. 中文注释补齐与编码防回归：按模块补齐关键逻辑、参数、边界条件说明，并保留 mojibake 扫描。

## 每阶段验收证据

| 阶段 | 必须提供的证据 |
| --- | --- |
| 安全收紧 | 路由测试证明 prod 不注册匿名敏感接口；JWT 空 secret 测试失败；Origin 配置测试通过 |
| Realtime Key 策略 | handler 测试证明 prod query key 被拒绝，dev 显式允许；日志不输出明文 key |
| Workspace 写入确认 | 测试证明工具调用只生成 pending diff，未确认不会写文件；确认后有审计日志 |
| 长连接韧性 | App 断开后容量快速释放；慢消费者关键事件不静默丢失；OpenAI Ping 配置与实现一致；断网/半开/高频输入测试有结果 |
| 监控日志 | `/api/debug/status` 或新 monitor API 返回目标字段；按天日志中能查到监控快照和错误事件；字段清单见 `docs/reviews/2026-06-06-monitoring-log-audit-matrix.md` |
| 钉钉告警 | 过载、恢复、冷却、签名失败、webhook 失败均有测试或可复现日志 |
| 天/周/月统计 | Redis/DB 中有 day/week/month key 或表；API 能按周期返回统计；前端能切换周期 |
| 容量证明 | `tools/wsload` 可复现实测；`docs/production-capacity.md` 记录实例数、LB、Redis、上游配额、P95/P99、错误率、资源曲线 |
| 中文注释 | 修改范围内关键函数、配置字段、handler 入参、WebSocket 事件和状态机均有中文说明；编码扫描无源文件乱码 |

更完整的目标级完成门槛见 `docs/reviews/2026-06-06-completion-acceptance-gates.md`。主目标只有在该矩阵中的所有全局门槛都有当前证据支撑时，才可以标记完成。

当前源码行号可能随多轮改动漂移。修复前应以 `docs/reviews/2026-06-06-current-p0-evidence-snapshot.md` 重新校准 P0/P1 证据位置，再关闭清单项。

进入统一修复前的确认范围、第一阶段边界和验收命令见 `docs/reviews/2026-06-06-pre-fix-confirmation-brief.md`。

## 当前不应立即修改的原因

用户要求“待确认后统一修改和修复”。当前最安全的推进方式是先把问题、位置、顺序和验收标准固化，等待确认后再按测试驱动方式进入实现。直接增强监控面板或压测能力会扩大当前匿名公开接口的暴露面，因此生产安全 Task 1-4 必须排在监控、告警、统计和容量压测之前。

## 相关审查文档

- `docs/reviews/2026-06-06-realtime-architecture-review.md`
- `docs/reviews/2026-06-06-production-exposure-matrix.md`
- `docs/reviews/2026-06-06-production-startup-security-readiness-matrix.md`
- `docs/reviews/2026-06-06-observability-gap-matrix.md`
- `docs/reviews/2026-06-06-monitoring-log-audit-matrix.md`
- `docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md`
- `docs/reviews/2026-06-06-capacity-readiness-matrix.md`
- `docs/reviews/2026-06-06-realtime-protocol-compatibility-matrix.md`
- `docs/reviews/2026-06-06-runtime-resilience-backpressure-matrix.md`
- `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md`
- `docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md`
- `docs/reviews/2026-06-06-source-commentary-coverage-inventory.md`
- `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md`
- `docs/reviews/2026-06-06-workspace-write-safety-matrix.md`
- `docs/reviews/2026-06-06-goal-traceability.md`
- `docs/reviews/2026-06-06-completion-acceptance-gates.md`
- `docs/reviews/2026-06-06-test-quality-gates-readiness-matrix.md`
- `docs/reviews/2026-06-06-current-p0-evidence-snapshot.md`
- `docs/reviews/2026-06-06-pre-fix-confirmation-brief.md`
- `docs/superpowers/plans/2026-06-06-realtime-production-hardening.md`
- `docs/commentary-cleanup.md`
