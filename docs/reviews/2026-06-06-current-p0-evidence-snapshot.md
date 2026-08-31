# 当前态 P0/P1 证据快照

审查日期：2026-06-06

当前阶段：只校准源码证据，不修改业务逻辑。由于工作区已有多轮改动，旧审查报告中的部分行号可能漂移；后续修复前，P0/P1 问题定位优先以本快照中的当前行号为准。

## 证据采集方式

本轮使用 `rg -n` 直接扫描当前工作区源码，重点覆盖 Realtime 长连接、容量控制、公开路由、JWT、上游 key、Origin、真实 IP、Redis 明细、Workspace 写入、metrics 热路径、告警和统计缺口。

## P0 证据矩阵

| 编号 | 问题 | 当前源码证据 | 风险判断 | 修复入口 |
| --- | --- | --- | --- | --- |
| P0-EV-01 | 单个 App 会话对应一条上游 Realtime WebSocket | `internal/provider/openai/client_ws.go:284` 每次连接调用 `dialer.DialContext(ctx, fullURL, header)` | 百万 App 会话会放大为百万上游连接，受本机 FD/socket、goroutine、内存、上游配额和中转连接数共同限制 | Task 6、Task 11 |
| P0-EV-02 | 每会话存在固定缓冲和多协程资源模型 | `internal/provider/openai/client_ws.go:86` `apiSendChanSize = 512`；`internal/provider/openai/client_ws.go:210-212` 初始化 `sendChan`、`apiSendChan`、`apiReconnectChan` | 单实例容量不能只看连接数，队列、goroutine 和 reconnect channel 都会成为资源成本 | Task 6、Task 11 |
| P0-EV-03 | 单实例容量上限不是百万并发证明 | `conf/config.yaml:47` `max_active_sessions: 100000`；`internal/service/session/capacity.go:14` `TryAcquireCapacity()`；`internal/service/session/capacity.go:32` `ReleaseCapacity()` | 这是当前进程内准入计数，不是集群级容量控制，也没有压测数据支撑 1 秒响应 | Task 1、Task 2、Task 11 |
| P0-EV-04 | App 断开后容量释放依赖 `sess.Start()` 返回 | `internal/handler/openai_handler.go:89` 容量预占；`internal/handler/openai_handler.go:97` `defer session.ReleaseCapacity()`；`internal/handler/openai_handler.go:135` 调用 `sess.Start(c.Request.Context())` | 如果上游读协程阻塞导致 `Start()` 不返回，容量释放会延迟，影响长连接稳定性和准入准确性 | Task 6 |
| P0-EV-05 | 上游读阻塞没有被 `api_ping_interval` 主动打断的证据 | `conf/config.yaml:78` 配置 `api_ping_interval`；`internal/provider/openai/config.go:295` 读取该配置；`internal/provider/openai/client_ws.go:663` 上游 `ReadMessage()`；仅看到 `internal/provider/openai/client_ws.go:496` 对 App 发送 Ping | 配置语义与实现不一致，OpenAI 上游半开连接检测不能被证明；App 断开释放也可能被读阻塞拖慢 | Task 6 |
| P0-EV-06 | 下行慢消费者可能丢消息 | `internal/provider/openai/client_ws.go:1156` `safeSend` 处理 App 下行队列；`internal/provider/openai/client_ws.go:1169` 记录 `SlowConsumerDrop` | 关键控制事件、错误事件、工具结果和音频片段未区分优先级，无法证明高负载下语义完整 | Task 6 |
| P0-EV-07 | 上游发送队列满会让请求语义失败 | `internal/provider/openai/client_ws.go:797` 尝试写入 `apiSendChan`；`internal/provider/openai/client_ws.go:801` 记录 `APISendQueueTimeout`；`internal/provider/openai/client_ws.go:802` 返回 `openai outbound queue full` | 高频音频或文本输入时可能无法进入上游；需要明确返回给 App 的错误协议和队列水位告警 | Task 6、Task 8 |
| P0-EV-08 | 敏感公开路由仍注册在匿名 public group | `cmd/server/main.go:62` `public := r.Group("/")`；`cmd/server/main.go:75` `/test/generate-token`；`cmd/server/main.go:92-97` 注册 Redis/debug/Responses/models/metrics/Azure 状态接口；`cmd/server/main.go:108` 才开始 auth group | 生产外网暴露时可匿名生成 token、读取 Redis 明细和诊断配置，扩大攻击面 | Task 1、Task 2 |
| P0-EV-09 | Redis 明细支持完整 value 输出 | `internal/handler/redis_handler.go:31-34` 读取 `pattern`、`cursor`、`count`、`full=1` | 匿名公开时可能泄露 Redis value；大 key/full 输出还会拖慢 Redis 和 Go 实例 | Task 2、Task 7 |
| P0-EV-10 | JWT 空密钥仍回退固定默认值 | `internal/middleware/auth.go:37` 鉴权中间件回退 `default-jwt-secret-123456`；`internal/middleware/auth.go:140` `GenerateToken()` 同样回退 | 生产配置为空时仍可启动，token 可被伪造 | Task 1、Task 3 |
| P0-EV-11 | 生产配置默认 JWT secret 为空但缺启动失败证据 | `conf/config_prod.yaml:5` `env: prod`；`conf/config_prod.yaml:15-17` JWT enabled 且 `secret: ""` | 如果没有生产启动 gate，prod 会配合默认密钥回退形成高危组合 | Task 1 |
| P0-EV-12 | 上游 API key 允许通过 URL query 覆盖 | `internal/handler/openai_handler.go:152-153` 读取 `upstream_ws_url` 和 `upstream_api_key`/`api_key`；`web/ws-test.js:389-390`、`web/chat.html:715-716` 把上游地址和 key 拼进 WS query | API key 可能进入浏览器历史、代理日志、访问日志和错误日志；生产应禁用或改为服务端配置 | Task 4 |
| P0-EV-13 | 当前测试仍锁定 query key 覆盖行为 | `internal/handler/openai_handler_test.go:17`、`internal/handler/openai_handler_test.go:49` 使用 `upstream_api_key=relay-key` 断言覆盖逻辑 | 当前测试方向与生产安全目标冲突，修复阶段需要改成 prod 拒绝、dev 显式允许 | Task 4 |
| P0-EV-14 | Workspace 工具直接写入项目文件 | `internal/provider/openai/tool_execution.go:376` 处理 `workspace_write_file`；`internal/service/workspace/workspace.go:157-160` `MkdirAll` 后 `os.WriteFile` | 模型工具调用可直接改磁盘文件，缺 pending diff、确认、拒绝、审计、回滚 | Task 5 |
| P0-EV-15 | Workspace 只有基础大小和路径逃逸保护 | `internal/service/workspace/workspace.go:13-14` 512 KiB 读写上限；`internal/service/workspace/workspace.go:183-196` 拒绝绝对路径和逃逸根目录路径 | 基础防护必要但不等于安全写入，仍缺用户确认和敏感路径策略 | Task 5 |
| P0-EV-16 | 全局 metrics mutex 覆盖高频热路径 | `internal/service/metrics/metrics.go:34` 全局 `sync.Mutex`；`internal/service/metrics/metrics.go:233`、`301-304`、`393-395`、`549-565`、`657-659` 多个高频记录和快照持锁 | 高并发事件记录、错误记录、队列水位和面板轮询争同一把锁，指标系统可能反向拖慢业务链路 | Task 6、Task 7 |

## P1 证据矩阵

| 编号 | 问题 | 当前源码证据 | 风险判断 | 修复入口 |
| --- | --- | --- | --- | --- |
| P1-EV-01 | Origin 白名单硬编码 | `internal/handler/openai_handler.go:36-37` dev 放行，非 dev 只允许 `https://your-app-domain.com` | 真实生产域名不可配置；部署时要么不可用，要么开发者临时放宽安全边界 | Task 4 |
| P1-EV-02 | 真实 IP 依赖 `ClientIP()`，但未配置 trusted proxies | `internal/handler/openai_handler.go:72`、`internal/handler/azureai_handler.go:47` 使用 `c.ClientIP()`；当前源码未发现 `SetTrustedProxies` | 负载均衡或反向代理后真实 IP 不可信，所在地统计和安全审计不可靠 | Task 2、Task 7 |
| P1-EV-03 | 过载只返回 503，没有主动告警闭环 | `internal/handler/openai_handler.go:89-94`、`internal/handler/azureai_handler.go:64-69` 容量不足时返回 `server overloaded` | 用户侧失败但运维侧没有钉钉通知、冷却、恢复和日志事件 | Task 8 |
| P1-EV-04 | 告警服务和钉钉配置不存在 | `rg -n "dingtalk|DingTalk|webhook|alert|overload" cmd conf internal web` 只命中文档和两个 handler 的 overloaded 文案，没有可用 `internal/service/alert` | 无法满足系统过载钉钉机器人预警目标 | Task 8 |
| P1-EV-05 | 统计仍以 daily 为主，缺 week/month 聚合 | `internal/service/billing/billing.go:220` 写 `billing:daily`；`internal/service/billing/billing.go:223` 写 `billing:daily_detail`；`internal/service/metrics/metrics.go:124` 只有 `TokensByDay` | 无法满足天/周/月多资源图表、成本趋势和多实例聚合目标 | Task 9、Task 10 |
| P1-EV-06 | 监控字段仍缺完整生产审计闭环 | `internal/service/monitor` 已生成统一快照；`cmd/server/main.go` 已启动 `monitor.StartPeriodicLogger` 和日志清理调度；2026-06-07 23:06 8098 烟测只访问 `/health` 即在当天日志写入带 `event=monitor_snapshot`、`event_date`、`instance_id` 的 `monitor snapshot`；`internal/logger/logger_test.go` 覆盖过期日志清理、`log_cleanup` 摘要、相对路径和取消退出 | 基础面板信息已经能按天追溯，且有基础日志保留控制和清理摘要，但仍缺跨零点验证、敏感字段脱敏、归档压缩、长期持久化和跨实例聚合 | Task 7 |

## 修复前使用规则

1. 修复阶段开始前，先用本快照复核 P0/P1 行号是否仍有效。
2. 每关闭一个问题，必须把对应文档中的“当前证据”更新为“修复证据”，并补测试命令。
3. 旧报告中的历史行号如果与本快照冲突，以当前源码重新扫描结果为准。
4. 任何涉及公开路由、query key、JWT、Redis full value、Workspace 写入的改动，必须先补失败测试再改实现。
