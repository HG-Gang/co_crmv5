# WebSocket 运行时韧性与背压风险矩阵

审查日期：2026-06-06

当前状态：仍处于“先审查、列问题、定位证据”阶段。本文只审查当前 WebSocket 长连接运行时链路的会话生命周期、重连恢复、队列背压、慢消费者处理和容量释放风险，不修改业务代码。

## 结论

当前实现已经有一些正确的工程边界：App 连接和 OpenAI 连接分别使用单读/单写协程，App 下行和 OpenAI 上行都使用有界队列，上游写失败会尝试重连，重连成功后会按配置恢复 `session.update` 和最近上下文。

但这些还不能证明“百万并发 + 1 秒内稳定响应”。关键风险是：App 断开后 OpenAI 读协程可能继续阻塞到上游读超时，导致会话和容量释放延迟；慢消费者会触发下行消息丢弃；上游队列满会让 App 消息失败；配置中存在 `api_ping_interval`，但当前代码没有看到向 OpenAI 周期发送 Ping 的实现；重连恢复只重放有限上下文，无法证明长对话和工具调用状态完整恢复。

## 运行时链路矩阵

| 编号 | 运行时点 | 当前实现证据 | 判断 | 风险 | 确认后建议 |
| --- | --- | --- | --- | --- | --- |
| P0-RUN-01 | App 断开后的会话释放 | `internal/provider/openai/client_ws.go:388-455` 中 `readPump` 发现 App 断开后调用 `cancel()`；`internal/provider/openai/client_ws.go:625-694` 中 `recvPump` 一旦进入 `conn.ReadMessage()`，不会被 `ctx.Done()` 直接打断；`internal/provider/openai/client_ws.go:341-348` 需要等待 4 个协程全部退出 | 存在释放延迟风险 | App 已断开时，OpenAI 读协程可能继续阻塞直到上游返回消息或 `api_read_timeout/api_pong_timeout` 到期；`sess.Start()` 不返回，handler 的 `defer session.ReleaseCapacity()` 也随之延迟 | App 断开或 context cancel 时主动关闭上游 `apiConn`，让 `recvPump` 立即退出；补“App 断开后容量在短时间内释放”的测试 |
| P0-RUN-02 | 单实例容量计数释放口径 | `internal/handler/openai_handler.go:89-97` 先 `TryAcquireCapacity()`，handler 返回时才 `ReleaseCapacity()`；`internal/service/session/capacity.go:11-46` 是进程内 atomic 计数 | 单实例保护存在，但不是集群容量证明 | 如果会话退出被上游读阻塞拖住，活跃计数会虚高；多实例之间仍无全局容量视图 | 缩短异常释放路径，增加 per-instance 与 cluster-level 两层容量指标 |
| P0-RUN-03 | 下行慢消费者处理 | `internal/provider/openai/client_ws.go:1156-1171` `sendChan` 满时等待 `send_queue_timeout_ms`，超时直接丢弃；`internal/service/metrics/metrics.go:378-389` 只记录 `SlowConsumerDrop` | 背压不会拖死全局，但语义不完整 | 文本 delta、音频 delta、工具结果或错误事件被丢弃后，客户端看到的回答可能断裂；当前没有确认/重传/降级关闭策略 | 区分可丢弃流式音频片段和不可丢弃控制事件；关键事件队列满时应关闭会话或返回明确错误 |
| P0-RUN-04 | 上游写队列满 | `internal/provider/openai/client_ws.go:793-803` `apiSendChan` 满时等待配置超时并返回 `openai outbound queue full`；`internal/provider/openai/client_ws.go:436-448` handler 只记录错误，不一定立即断开 | 有界队列保护内存，但请求语义会失败 | App 快速发送音频/文本时，事件可能没有进入上游；用户侧只会看到局部失败或无响应，无法证明 1 秒内稳定处理 | 为上游队列满定义明确协议响应和关闭策略；补高频输入压测和队列水位告警 |
| P0-RUN-05 | OpenAI 主动 Ping 配置未落地 | `conf/config.yaml:78`、`conf/models/openai.yaml:41` 配置 `api_ping_interval`；`internal/provider/openai/config.go:295-302` 有 `GetApiPingInterval()`；仓库只在 `internal/provider/openai/client_ws.go:496` 看到向 App 发 Ping，没有看到向 OpenAI 发 `PingMessage` | 配置和实现不一致 | 空闲 Realtime 连接主要依赖读超时和上游事件；如果上游不主动发消息/Ping，健康连接也可能被误判为超时并重连 | 要么实现 OpenAI Ping ticker，要么删除/重命名该配置并明确只依赖读超时 |
| P1-RUN-06 | 重连时锁粒度 | `internal/provider/openai/client_ws.go:235-284` `Connect()` 持有 `connMu` 完成旧连接关闭、URL 构造和网络拨号 | 简化了连接替换，但锁覆盖网络 I/O | 拨号期间 `Close()`、`writeToOpenAI()`、`recvPump` 的连接状态检查可能等待同一把锁；高并发异常时关闭路径会变慢 | 把网络拨号移到锁外，只在替换连接指针时持锁；补关闭期间重连的并发测试 |
| P1-RUN-07 | 写失败重试策略 | `internal/provider/openai/client_ws.go:560-583` 写 OpenAI 失败后重连并重试一次；重试失败由 `openAIWritePump` 返回并结束会话 | 行为清晰，但恢复能力有限 | 短时网络抖动、代理异常、上游 101 后断开可能导致会话直接结束；客户端需要自行重连，但没有端到端自动恢复证明 | 区分临时错误、鉴权错误、限流错误；增加指数退避、熔断和客户端重连指引 |
| P1-RUN-08 | 读失败重连策略 | `internal/provider/openai/client_ws.go:663-689` 读失败请求 `openAIWritePump` 执行重连；失败后发送 `reconnect_required` 给 App | 避免并发写上游，方向正确 | `apiReconnectChan` 只有 1 个缓冲；读协程等待写协程处理，写协程如果卡在重连拨号会放大读侧停顿 | 给重连请求增加状态指标和超时；压测断网/代理卡死场景 |
| P1-RUN-09 | 重连恢复内容 | `internal/provider/openai/client_ws.go:118-159` 只缓存最近 `session.update` 和 `conversation.item.*`；`internal/provider/openai/client_ws.go:1211-1249` 重连后重放 session 和历史 | 最小恢复有价值 | 不恢复音频 buffer、正在进行的 response、工具调用中间态和服务端上下文；长对话恢复不是完整语义恢复 | 明确“最小恢复”边界；把正在响应中的会话标记为 interrupted，客户端重新发起 response |
| P1-RUN-10 | 配置超时与 1 秒目标冲突 | `conf/config.yaml:71-83` 默认重连 3 次、每次延迟 1s、上游读超时 120s、上游 Pong 超时 90s、队列等待 250ms | 适合开发稳定性，不是 1 秒 SLA 证明 | 任何一次上游读超时或重连路径都远超 1 秒；“1 秒响应”必须定义首包、完整响应、错误返回和重连场景口径 | 新增 SLO 文档，分别定义正常请求、上游慢、上游断线、队列满、慢消费者的目标 |
| P1-RUN-11 | 会话元数据写 Redis 忽略错误 | `internal/service/session/manager.go:109-123` 写 `session:{id}` 到 Redis 时忽略错误；`internal/service/session/manager.go:169-172` 关闭状态也忽略错误 | 避免 Redis 影响主链路 | 在线人数、用户明细和会话状态可能与真实连接不一致；无法满足“所有信息必须记录日志/可监控”的目标 | Redis 失败进入指标、日志和告警；在线状态以进程内实时状态和持久化状态双源展示 |
| P2-RUN-12 | 运行时测试覆盖不足 | 当前 `rg` 未发现针对 `safeSend`、`reconnect()`、`requestOpenAIReconnect`、App 断开释放容量、OpenAI Ping 的专门测试 | 缺少回归保护 | 后续调整长连接生命周期容易引入 goroutine 泄漏、重复写、连接关闭竞态 | 修复阶段先补单元测试和集成测试，再改生命周期实现 |

## 对“百万并发 + 1 秒响应”的影响

这些运行时问题不代表当前代码不可用，但足以说明它还不能被证明达到生产级目标：

1. 容量释放可能被上游读阻塞拖到 90 秒级，百万连接下会放大为大量僵持资源。
2. 队列满时当前策略是丢弃或报错，不是可证明的端到端稳定响应。
3. 重连路径默认 1 秒延迟且最多 3 次，已经超出“1 秒内响应”的严格口径。
4. 当前缺少长时间断网、慢客户端、上游半开、代理卡死、高频音频输入的压测证据。

## 当前不进入修复的原因

用户要求“待确认后统一修改和修复”。当前只把运行时韧性、背压和释放路径风险固化为证据。确认进入修复后，应先把生产安全 Task 1-4 完成，再进入生命周期和背压修复；否则监控和压测能力会继续放大当前匿名调试接口和 query key 暴露风险。
