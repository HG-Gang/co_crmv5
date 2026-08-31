# Go + OpenAI Realtime WebSocket 当前架构审查

审查日期：2026-06-08

## 总结论

当前服务已经具备 Realtime WebSocket 网关的基础形态：JWT 保护路由、Origin 校验、单实例活跃会话准入、有界队列、App/OpenAI 双端心跳、上游重连、基础监控快照、按天日志、钉钉过载通知、进程内 day/week/month 统计和 AI 项目助手页面。

但是，当前实现不能证明可以稳定支撑“百万并发 + 1 秒内响应”。主要原因不是某一个函数写错，而是容量模型、上游连接模型、消息级限流、跨实例统计、长期日志审计、SLA 指标和真实压测报告还没有闭环。

在用户确认前，本文件只列问题和证据，不进入统一修复。

## 当前已经具备的能力

| 能力 | 当前证据 | 审查判断 |
| --- | --- | --- |
| 生产安全开关 | `conf/config_prod.yaml` 关闭公开 token/debug/query key；`conf/loader.go` 校验生产配置 | 方向正确，但生产默认配置仍需要部署侧补齐 Redis、Origin、JWT 等必填项 |
| JWT 保护路由 | `cmd/server/main.go` 对受保护分组加载 `middleware.Auth()`；`registerProtectedRoutes` 注册 Realtime、Responses、Workspace、Azure、Stats 等路由 | 能保护主要 API，但 WebSocket token 仍支持 query 方式，生产有泄漏风险 |
| Origin 校验 | `internal/handler/openai_handler.go` 的 `checkRealtimeOrigin` 使用 `security.allowed_origins` | 生产白名单必须显式配置，否则无法安全对公网开放 |
| 单实例容量准入 | `conf/config.yaml` 配置 `capacity.max_active_sessions: 100000`；`internal/service/session/capacity.go` 使用进程内 atomic 计数 | 这是单实例保护，不是百万并发证明，也不是集群级容量控制 |
| Realtime 主链路 | `internal/provider/openai/client_ws.go` 每连接启动 `readPump`、`openAIWritePump`、`recvPump`、`writePump` | 读写职责清晰，但百万连接会放大为数百万 goroutine |
| 有界队列 | `sendChanSize = 512`、`apiSendChanSize = 512` | 能防止无限堆积，但队列满时的用户语义和丢弃率需要压测证明 |
| 监控快照 | `internal/service/monitor` 汇总 server/process/memory/capacity/modules/metrics；`/api/debug/status` 返回快照 | 适合诊断面板，不等于跨实例生产监控系统 |
| 按天日志 | `internal/logger/logger.go` 的 daily writer；`monitor.LogSnapshotThrottled` 写监控快照 | 已有按天落盘基础，但不是所有资源事件都完整持久化 |
| 钉钉过载通知 | `internal/service/alert/dingtalk.go` 和 `internal/service/monitor/overload_alert.go` | 已有容量、内存、协程、队列等基础过载通知，但阈值仍硬编码 |
| day/week/month 统计 | `internal/service/stats/stats.go` 和 `/api/stats/resources` | 当前是进程内滚动窗口，不是跨实例长期统计 |

## P0：百万并发目标不能成立的原因

### 1. 当前容量上限是单实例 100000，不是百万证明

证据：
- `conf/config.yaml` 中 `capacity.max_active_sessions: 100000`。
- `internal/service/session/capacity.go` 的 `TryAcquireCapacity()` 只用当前 Go 进程内的 `atomic.Int64` 计数。

影响：
- 100000 是准入配置，不是压测结果。
- 多实例部署时没有统一的集群级活跃会话总量控制。
- 不能用单进程计数证明集群百万并发。

确认后修复方向：
- 明确单实例目标和集群目标。
- 建立容量报告，至少包含实例数、每实例连接数、LB 策略、Redis/指标分片、OS FD/socket 参数、上游配额、P50/P95/P99 和错误率。

### 2. 每个 App 会话都会建立一个上游 Realtime WebSocket

证据：
- `internal/provider/openai/client_ws.go` 的 `HandleWS()` 中每个会话都会调用 `c.Connect(ctx)`。
- `Client` 同时持有 `appConn` 和 `apiConn`。

影响：
- 百万 App 连接会变成百万上游 Realtime 连接。
- OpenAI 或第三方中转的连接数、RPM、TPM、音频 token、价格和 429 策略通常会先成为硬限制。
- 如果只压测本地 mock 上游，不能证明真实 OpenAI/中转容量。

确认后修复方向：
- 压测报告必须区分 mock upstream 和真实 upstream。
- 必须拿到第三方中转或 OpenAI 的并发连接配额证明。

### 3. 每会话四个主 goroutine 会放大调度压力

证据：
- `internal/provider/openai/client_ws.go` 中 `wg.Add(4)` 后启动 `readPump`、`openAIWritePump`、`recvPump`、`writePump`。

影响：
- 百万连接至少对应 400 万个主业务 goroutine，不含 runtime、Redis、logger、monitor、HTTP 和临时 goroutine。
- goroutine 栈、调度延迟、GC pause、内存碎片和 CPU 抢占都必须用真实曲线证明。

确认后修复方向：
- 阶梯压测 1k、5k、10k、50k、100k，再进入多实例压测。
- 每档记录 goroutine、heap/RSS、GC、FD/socket、CPU、网络、错误率和 p95/p99。

### 4. 双 512 队列保护内存，但也带来峰值和语义风险

证据：
- `internal/provider/openai/client_ws.go` 中 `sendChanSize = 512`。
- `internal/provider/openai/client_ws.go` 中 `apiSendChanSize = 512`。
- OpenAI 入站帧限制为 `apiMaxMessageSize = 16 * 1024 * 1024`。

影响：
- 队列是有界的，但慢消费者或上游卡顿时会保留大量消息引用。
- `safeSend` 对非关键事件会 best-effort，队列满时可能丢弃。
- 关键事件和非关键事件的投递保障还需要更明确的协议和告警语义。

确认后修复方向：
- 区分必须送达事件、可降级事件、可丢弃事件。
- 慢消费者达到阈值时主动断开，避免无限占用会话资源。

## P0：1 秒响应目标不能成立的原因

### 1. 1 秒响应口径未被完整定义和采集

当前缺少以下核心指标：
- `connect_latency_ms`：App 到 Go WebSocket 握手耗时。
- `upstream_connect_latency_ms`：Go 到 OpenAI/中转握手耗时。
- `first_event_latency_ms`：用户事件到第一个有效响应事件耗时。
- `first_audio_latency_ms`：用户事件到第一段可播放音频耗时。
- `complete_response_latency_ms`：一次 response 从创建到完成耗时。

影响：
- 没有 p50/p95/p99，就不能证明 1 秒内稳定响应。
- 只看平均值会掩盖慢上游、队列满、重连、GC 和网络抖动。

确认后修复方向：
- 在 Realtime 主链路记录首包、首音频、完整响应和上游连接耗时。
- 诊断面板展示 p50/p95/p99，钉钉告警接入 p99 超阈值。

### 2. 当前超时和重连策略不是 1 秒 SLA

证据：
- 配置里存在秒级到分钟级的上游读超时、Pong 超时、重连延迟和队列等待。
- `internal/provider/openai/client_ws.go` 中队列投递使用 `time.After(c.cfg.GetSendQueueTimeout())`。
- 上游重连路径中存在 `time.After(delay)`。

影响：
- 上游慢、半开连接、重连和队列满时，用户体验可能超过 1 秒。
- 网关可以尽量快速失败，但不能保证上游模型和中转 1 秒内生成有效内容。

确认后修复方向：
- 定义正常路径、上游慢、上游断线、队列满、慢消费者五类场景的 SLA。
- 对不满足 SLA 的路径返回明确错误事件，而不是让用户等待。

## P1：生产安全和公开路由问题

### 1. 生产配置默认不能直接安全运行

证据：
- `conf/config_prod.yaml` 中 `redis.addr: ""`。
- `conf/config_prod.yaml` 中 `security.allowed_origins: []`。
- `conf/loader.go` 中生产配置会拒绝空 `allowed_origins`。

影响：
- 安全校验是正确方向，但生产部署模板不完整。
- 部署人员必须知道哪些字段必须通过环境变量或私有配置注入。

确认后修复方向：
- 提供生产配置模板和启动前检查说明。
- 明确 Redis 强依赖/弱依赖策略。

### 2. WebSocket JWT query token 存在泄漏风险

证据：
- `web/chat.html` 中 `query.set('token', state.token)`。
- `internal/middleware/auth.go` 支持从 query token 读取凭证。

影响：
- token 可能进入浏览器历史、代理日志、网关 access log 或错误日志。

确认后修复方向：
- 生产使用短期 WebSocket 握手票据，或 `Sec-WebSocket-Protocol`。
- query token 仅允许开发环境显式开启。

### 3. 上游 API Key query override 只适合开发调试

证据：
- `internal/handler/openai_handler.go` 读取 `upstream_api_key` / `api_key`。
- `conf/config_prod.yaml` 中 `allow_upstream_query_key: false`。
- `web/index.html` 测试面板仍保留上游 Key 调试输入。

影响：
- 生产如果误开 query key，会导致上游 Key 进入 URL、代理日志和错误追踪系统。

确认后修复方向：
- 保留测试面板开发能力，但生产彻底禁用。
- AI 项目助手页面不暴露上游 Key 输入。

## P1：消息级限流和背压问题

### 1. 当前限流只覆盖 HTTP 建连，不覆盖 WS 内消息

证据：
- `internal/middleware/rate.go` 是 Gin middleware。
- WebSocket 建立后，音频帧、文本事件、tool 事件不会再经过 HTTP middleware。

影响：
- 已建立连接可以持续发送高频音频或大批 JSON 事件。
- 百万并发下，WS 内消息才是真正的热路径。

确认后修复方向：
- 增加每连接、每用户、每模型的 WS 消息级限流。
- 将音频帧速率、文本事件速率、工具调用速率分别配置。

### 2. 当前 Redis 限流不是百万级方案

证据：
- `internal/middleware/rate.go` 每请求调用 Redis `INCR`。
- `conf/config.yaml` 中 Redis pool 默认 `128`。

影响：
- 高 QPS 下 Redis 可能成为瓶颈。
- Redis 失败时降级本地限流，跨实例全局限流失效。

确认后修复方向：
- 明确 Redis fail-open/fail-closed/degraded 策略。
- 对百万入口流量使用更低成本的分布式限流或边缘限流。

## P1：监控、日志和统计问题

### 1. metrics 全局锁仍是高并发风险

证据：
- `internal/service/metrics/metrics.go` 的 collector 使用单个 `sync.Mutex`。
- 会话、心跳、队列水位、OpenAI 事件、错误和 `Snapshot()` 都访问同一 collector。

影响：
- 监控系统可能和业务热路径争锁。
- 诊断面板轮询可能放大锁竞争。

确认后修复方向：
- 高频计数改 atomic 或分片计数。
- 最近会话明细和全局总量分离。
- 长期指标交给 Redis/Prometheus/日志聚合系统。

### 2. 最近会话只适合诊断，不适合百万在线明细

证据：
- `internal/service/metrics/metrics.go` 中 `maxRecentSessions = 50`。
- `/api/debug/status` 展示的是最近会话快照。

影响：
- 面板不能展示百万在线明细。
- 活跃会话 map 在大规模连接下仍需要内存模型和裁剪策略。

确认后修复方向：
- 面板展示总量、抽样、Top N、错误会话和搜索，不展示全量百万列表。
- 会话明细落 Redis/日志/指标平台，前端分页查询。

### 3. day/week/month 统计当前是进程内窗口

证据：
- `internal/service/stats/stats.go` 中 `maxUsageRecords = 10000`。
- `ResourcePeriodsWithFilter()` 在当前进程内记录上聚合 day/week/month。

影响：
- 服务重启会丢失统计。
- 多实例无法聚合。
- 不适合生产长期天/周/月报表。

确认后修复方向：
- 将资源事件写入 Redis/DB/日志聚合系统。
- 诊断页保留进程内实时窗口，统计页使用长期聚合来源。

### 4. 按天日志已有基础，但审计闭环不完整

证据：
- `internal/service/monitor/snapshot.go` 的 `LogSnapshotThrottled()` 写监控快照。
- `internal/service/alert/dingtalk.go` 写钉钉审计日志。
- `internal/service/workspace/audit.go` 写 workspace 审计日志。

影响：
- 不是所有 token、缓存、限流、错误、队列和上游事件都有统一 schema 按天落盘。
- 后续排障和对账会依赖内存状态，重启后不可追溯。

确认后修复方向：
- 定义统一 audit event schema。
- 资源事件、错误事件、告警事件和 token 用量全部结构化落当天日志。

## P1：钉钉过载预警问题

证据：
- `internal/service/monitor/overload_alert.go` 中容量、goroutine、内存、GC、队列阈值是常量。
- `internal/service/alert/dingtalk.go` 支持 webhook、签名、冷却和审计。

影响：
- 阈值不可按环境调整。
- 还缺少错误率、OpenAI 连接失败率、Redis 异常、p99 延迟、限流突增等复合告警。

确认后修复方向：
- 把过载阈值放入配置。
- 增加 warning、critical、recovered 三类状态。
- 告警内容带 instance_id、provider、active/max、queue、memory、goroutine、p99、最近错误摘要。

## P2：AI 项目助手和监控面板问题

### 1. AI 项目助手功能方向正确，但生产安全还需收紧

证据：
- `web/chat.html` 已有项目选择、文件树、编辑区、聊天消息、原始事件筛选、token 当前/累加统计和 token 图表。
- `web/chat.html` 仍通过 query 传 JWT token。

影响：
- 页面功能接近目标，但 WebSocket 鉴权方式不适合生产。
- 文件写入必须继续保留 pending diff 和用户确认，不能让模型直接落盘。

确认后修复方向：
- 改生产 WS 鉴权。
- 文件编辑区和聊天区职责继续保持清晰：左侧项目文件，中间查看/编辑，底部提问，上方展示模型回复。

### 2. 诊断面板已有基础，但还不是最终监控面板

证据：
- `web/diagnostics.html` 展示 Go runtime、内存、活跃会话、告警、Redis、OpenAI/Azure/Responses、recent sessions、day/week/month 资源条形图。

仍缺：
- 跨实例聚合。
- p50/p95/p99 延迟。
- 真实 GeoIP 数据源。
- 错误中心和错误钻取。
- Redis/OpenAI/上游连接池趋势。
- 钉钉发送状态和最近失败原因。
- 多种图表形状统一切换。

确认后修复方向：
- 先补后端数据源和日志落盘，再扩展前端图表。

## P2：中文注释和编码问题

证据：
- 当前部分 review 文档和注释存在 mojibake 乱码。
- 项目已有大量中文注释，但还不能证明“所有文件项目代码全部中文详细注释”。

影响：
- 乱码文档会误导后续审查和执行。
- 机械给全仓加注释会制造噪音，反而降低可维护性。

确认后修复方向：
- 先修编码损坏文档和核心模块注释。
- 按安全、Realtime、Workspace、监控、告警、统计、Web、测试分批补齐。
- 注释只解释逻辑、参数、边界和风险，不重复代码本身。

## 确认后建议的统一修复顺序

1. 收紧生产 WS 鉴权、Origin、query token、query upstream key 策略。
2. 增加 WS 消息级限流、慢消费者断开、队列满协议响应和关键事件保障。
3. 拆分 metrics 热路径锁，保留轻量实时指标。
4. 定义 Realtime SLA 指标并记录首包、首音频、完整响应、上游连接 p50/p95/p99。
5. 完成统一 audit event schema，token、缓存、限流、错误、队列、告警全部按天落日志。
6. 将 day/week/month 统计迁移到可跨实例聚合的数据源。
7. 钉钉阈值配置化，补齐 warning/critical/recovered 和复合过载告警。
8. 扩展监控面板：跨实例、错误中心、延迟百分位、用户/IP/所在地、OpenAI/Redis/Go 服务状态、多图表形状。
9. 用 `tools/wsload` 做阶梯压测，生成容量报告。
10. 分模块修复乱码和中文注释覆盖。

## 当前不能对外声明的内容

当前不能声明：

> 当前服务已经能顶住百万并发，并且 1 秒内稳定响应。

原因：
- 没有真实百万连接压测报告。
- 没有上游 OpenAI/第三方中转百万 Realtime 连接配额证明。
- 没有 Redis Cluster、LB、OS FD/socket、网络带宽、CPU/内存/GC 曲线证明。
- 没有 Realtime 首包、首音频、完整响应的 p95/p99 数据。
- 没有跨实例长期统计和完整日志审计闭环。

当前可以声明：

> 当前服务已经具备 Realtime WebSocket 网关的基础保护、基础监控、基础告警和基础统计能力，但百万并发和 1 秒响应仍必须通过多实例架构、上游配额和真实压测报告共同证明。
