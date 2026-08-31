# 百万并发与 1 秒延迟容量就绪矩阵

审查日期：2026-06-06

范围：Go Realtime WebSocket 主链路、单实例容量控制、每会话资源模型、上游 OpenAI/中转依赖、压测工具、pprof/FD/socket 指标和容量报告。

## 总体结论

当前项目不能证明“百万并发 + 1 秒内稳定响应”。代码已经具备一些中小规模网关所需的保护机制：单实例活跃会话上限、每会话四协程模型、App/OpenAI 双端心跳、有界队列、慢消费者丢弃、OpenAI 重连、Redis session 和 daily billing。

但这些机制只能说明“单连接逻辑比早期版本更可控”，不能证明百万并发。百万并发必须由集群容量模型和压测报告证明，至少包含多实例数量、每实例连接上限、LB 长连接策略、Redis Cluster 容量、OpenAI/第三方中转连接配额、OS FD/socket 参数、P50/P95/P99 延迟、错误率和成本模型。

## 当前每会话资源模型

| 资源 | 当前实现 | 证据 | 容量影响 |
|---|---|---|---|
| App WebSocket | 每个 App 会话 1 条 App→Go WS | `internal/service/session/manager.go:47`、`internal/provider/openai/client_ws.go:182` | 百万会话即百万 App socket |
| 上游 WebSocket | 每个 App 会话 1 条 Go→OpenAI Realtime WS | `internal/provider/openai/client_ws.go:284` `DialContext` | 百万会话即百万上游 socket，通常先受上游配额限制 |
| 主 goroutine | 每会话 4 个主协程 | `internal/provider/openai/client_ws.go:341-346` | 百万会话至少 400 万主协程，不含 runtime/Redis/logger 额外协程 |
| 下行队列 | `sendChan` 512 缓冲 | `internal/provider/openai/client_ws.go:82-83`、`210` | 慢客户端会占用内存；满时丢弃消息 |
| 上行队列 | `apiSendChan` 512 缓冲 | `internal/provider/openai/client_ws.go:84-86`、`211` | App 快于上游时占用内存；满时超时并报错 |
| 重连队列 | `apiReconnectChan` 1 缓冲 | `internal/provider/openai/client_ws.go:184`、`212` | 避免重复上游重连，但不能缓解上游大面积故障 |
| App 入站帧限制 | 64 KiB | `internal/provider/openai/client_ws.go:80`、`398` | 控制 App 单消息内存风险 |
| OpenAI 入站帧限制 | 16 MiB | `internal/provider/openai/client_ws.go:87-89`、`291` | 音频大帧可用，但并发下内存峰值高 |
| Replay 状态 | session.update + 最近 conversation.item.*，默认 32 条 | `internal/provider/openai/client_ws.go:120-160` | 每会话保留历史事件，百万会话时需要估算内存 |
| Metrics 会话保留 | 最近 50 个 session，活跃不裁剪 | `internal/service/metrics/metrics.go:14-26`、`810-840` | 活跃百万会话时内存指标 map 仍会增长 |

## 当前已有保护

### 1. 单实例活跃会话上限

证据：

- `conf/config.yaml:47` 配置 `capacity.max_active_sessions: 100000`。
- `internal/service/session/capacity.go:11-32` 使用 `atomic.Int64` 做当前进程内准入控制。
- `internal/handler/openai_handler.go:90-95` 容量满时返回 503。
- `internal/handler/azureai_handler.go:65-69` Azure 容量满时返回 503。

限制：

- 这是单进程计数，不是集群总容量。
- 没有跨实例全局准入。
- 没有告警、自动摘除、负载均衡反馈。
- `100000` 是配置目标，不是压测证明。

### 2. 四协程单写者模型

证据：

- `internal/provider/openai/client_ws.go:1-48` 注释说明 readPump/openAIWritePump/recvPump/writePump。
- `internal/provider/openai/client_ws.go:170-174` 明确 App 和 OpenAI 写操作都由唯一 writer 串行化。
- `internal/provider/openai/client_ws.go:341-346` 启动四个主协程。

收益：

- 避免 gorilla/websocket 并发写 panic。
- 读写职责清晰，便于定位心跳和重连问题。

限制：

- 四协程模型放大了超高连接数下的调度压力。
- 百万连接时 goroutine、栈内存、调度延迟都必须压测证明。

### 3. 有界队列和慢消费者保护

证据：

- `internal/provider/openai/client_ws.go:82-86` 队列大小固定为 512。
- `internal/provider/openai/client_ws.go:795-801` OpenAI 上行队列满时等待 `send_queue_timeout_ms` 后记录 timeout。
- `internal/provider/openai/client_ws.go:1156-1169` App 下行队列满时短暂等待后丢弃消息并记录 slow consumer。
- `conf/config.yaml:83` `send_queue_timeout_ms: 250`。

收益：

- 避免慢客户端或上游阻塞导致内存无限增长。

限制：

- 丢弃音频/text delta 会造成用户体验劣化。
- 没有连续慢消费者断开策略。
- 没有优先级队列或 backpressure 协议。
- 队列满和丢弃只在进程内 metrics 中，未做告警和日/周/月统计。

### 4. OpenAI 重连和最小状态恢复

证据：

- `internal/provider/openai/client_ws.go:120-160` replay 只保存可安全恢复的事件。
- `internal/provider/openai/client_ws.go:590-687` recvPump 读异常后请求上游重连。
- `internal/provider/openai/client_ws.go:1226-1288` 重连后恢复 session.update 和 history。

收益：

- 可处理部分上游短断。

限制：

- 上游大面积 429/限流/断连时，重连会放大压力。
- 没有全局熔断和钉钉告警。
- 没有用压测覆盖上游 429、close、半开连接。

## 关键未满足项

| 验收项 | 当前状态 | 证据 | 风险 |
|---|---|---|---|
| WebSocket 压测工具 | 不存在 | `Test-Path tools` 为 false；`Test-Path tools/wsload` 为 false | 无法证明连接、首包、断链、错误率 |
| 容量报告 | 不存在 | `Test-Path docs/production-capacity.md` 为 false | 无法证明百万并发目标 |
| pprof | 不存在 | 未发现 `net/http/pprof`、`runtime/pprof` | 无法定位 CPU/heap/goroutine 热点 |
| FD/socket 指标 | 不存在 | 未发现 `fd_count`、`handle_count`、socket 指标实现 | 无法证明 OS 层连接容量 |
| PID/进程指标 | 部分缺失 | 当前诊断页只展示 Go version、OS/Arch、CPU、goroutines | 缺进程级排障字段 |
| 多实例容量模型 | 不存在 | 只有单实例 `capacity.max_active_sessions` | 无法证明集群总容量 |
| LB 长连接策略 | 不存在 | 仓库无 LB 配置和容量文档 | 无法证明连接分布和故障转移 |
| Redis Cluster 容量 | 不存在 | 当前 Redis 单地址配置，pool_size 128 | 无法证明 session/billing/rate limit 吞吐 |
| 上游配额证明 | 不存在 | 未记录 OpenAI/中转并发、RPM/TPM、音频 token 配额 | 上游会先成为瓶颈 |
| 1 秒延迟口径 | 未定义 | 文档仅笼统写“1 秒内响应” | 无法验收 |
| P95/P99 数据 | 不存在 | 未发现历史压测报告 | 无法证明稳定性 |
| 成本模型 | 不存在 | 只记录 token/billing，不含百万并发成本假设 | 无法评估商业可行性 |

## 1 秒延迟验收口径建议

必须先定义“1 秒内响应”是哪一种延迟，否则无法验收。

建议拆成 5 个指标：

1. `ws_connect_latency_ms`：App 到 Go WebSocket 握手完成时间。
2. `upstream_connect_latency_ms`：Go 到 OpenAI/中转 Realtime 握手完成时间。
3. `first_event_latency_ms`：App 发出用户事件到 Go 收到 OpenAI 第一条有效响应事件。
4. `first_audio_latency_ms`：App 发出音频/文本到收到第一段可播放音频。
5. `full_response_latency_ms`：一次 response.created 到 response.done 的完整耗时。

建议验收阈值：

- 单实例开发压测：P95 first_event <= 1000ms，错误率 <= 1%。
- 生产小流量：P95 first_audio <= 1000ms，P99 <= 2000ms，错误率 <= 0.5%。
- 百万并发目标：必须给出集群实例数、每实例连接数、上游配额和 P95/P99，而不是单一平均值。

## 百万并发容量证明最小材料

要声明“百万并发可用”，至少需要以下材料：

1. 集群拓扑：实例数、规格、区域、LB、网络带宽。
2. 每实例容量：连接数、goroutine、RSS/heap、FD/socket、CPU、GC。
3. 上游容量：OpenAI/中转 Realtime 最大连接数、限流策略、429 处理、配额凭证。
4. Redis 容量：Cluster 节点数、连接池、QPS、慢查询、内存。
5. 压测命令：工具版本、参数、并发、ramp、duration、消息类型、音频大小。
6. 延迟数据：P50/P95/P99 的 connect、first_event、first_audio、full_response。
7. 错误分布：HTTP/WS close code、上游错误、限流、超时、断链。
8. 资源曲线：CPU、内存、goroutine、FD/socket、GC pause、Redis pool。
9. 降级策略：容量满、Redis 异常、上游限流、慢消费者、实例摘除。
10. 成本估算：token、音频时长、上游连接、带宽、Redis、日志和监控成本。

## 建议新增压测工具能力

后续 `tools/wsload` 至少应支持：

```text
-url ws://127.0.0.1:8096/ws/realtime/openai
-users 1000
-ramp 30s
-duration 5m
-token <jwt>
-message "ping"
-audio-file sample.pcm
-upstream-mode real|mock
-report out.json
```

输出：

- 连接成功/失败数。
- 每秒连接建立数。
- 每秒消息数。
- P50/P95/P99 握手延迟。
- P50/P95/P99 首包延迟。
- P50/P95/P99 完整响应延迟。
- close code 分布。
- 错误文本聚合。
- goroutine/内存/FD/socket 采样。

## 建议新增容量文档结构

后续 `docs/production-capacity.md` 应包含：

```markdown
# 生产容量报告

## 结论

## 测试环境

## 集群拓扑

## 上游配额

## Redis 容量

## OS 参数

## 压测命令

## 延迟结果

## 错误率和断链原因

## 资源曲线

## 成本估算

## 当前不能证明的边界
```

## 对实施计划的影响

容量相关修复不能排在生产安全之前。当前公开诊断和 Redis 明细接口仍匿名暴露，直接做压测和面板增强会扩大攻击面。

推荐顺序：

1. Task 1-4：生产安全、公开路由、JWT、Origin、上游 key。
2. Task 6：长连接生命周期、背压和重连韧性修复，确保容量释放和队列语义可被压测验证。
3. Task 7：monitor 增加 PID、FD/socket、按天日志快照。
4. Task 8：过载告警接入容量拒绝、队列满、错误率、Redis/OpenAI 异常。
5. Task 9：stats 聚合容量拒绝、限流拒绝、错误、token、音频。
6. Task 11：压测工具与 `docs/production-capacity.md`。
