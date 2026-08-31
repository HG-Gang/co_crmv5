# Realtime 架构与容量当前审查

审查日期：2026-06-07

## 结论

当前代码已经比早期版本更接近可运营的 Realtime 网关：具备单实例容量上限、四协程单写者模型、有界队列、上游重连、监控快照、钉钉过载告警、用户真实 IP/所在地展示、day/week/month 资源统计和 `tools/wsload` 压测工具。

但当前仍不能证明“百万并发 + 1 秒内稳定响应”。现有证据只能证明代码具备局部保护和可观测性，不能证明集群容量、上游配额、Redis 容量、OS socket/FD 参数、P95/P99 延迟和成本模型已经满足目标。

## 当前已具备的证据

| 能力 | 当前证据 | 判断 |
| --- | --- | --- |
| 单实例容量准入 | `conf/config.yaml` 配置 `capacity.max_active_sessions: 100000`；`internal/service/session/capacity.go` 使用 `atomic.Int64` 做进程内准入 | 有单实例保护，但不是百万并发证明 |
| Realtime 会话模型 | `internal/provider/openai/client_ws.go` 注释和 `HandleWS` 启动 `readPump`、`openAIWritePump`、`recvPump`、`writePump` 四个主协程 | 架构边界清晰，但百万连接时 goroutine 调度压力极大 |
| 有界队列 | `sendChanSize = 512`、`apiSendChanSize = 512`；队列满后按 `send_queue_timeout_ms` 等待并记录 metrics | 可防止无限堆积，但仍需压测证明丢弃率和关键事件语义 |
| 上游重连 | `replayState` 只缓存 `session.update` 和最近 `conversation.item.*`；重连由 `openAIWritePump` 串行执行 | 能处理部分短断，不能证明上游大面积故障下稳定 |
| 监控快照 | `internal/service/monitor` 采集 server/process/memory/capacity/modules/metrics；`DebugStatusHandler` 返回 `data.monitor`；`cmd/server/main.go` 启动 `monitor.StartPeriodicLogger` 和日志清理调度；2026-06-07 23:06 8098 烟测只访问 `/health` 即写入带 `event=monitor_snapshot`、`event_date`、`instance_id` 的 `monitor snapshot`；日志清理会写 `log_cleanup` 摘要；logger 跨零点轮换有单元测试覆盖 | 已形成统一快照、后台周期日志、monitor 审计字段、基础日志保留控制、清理摘要和跨零点轮换；仍缺长期持久化、跨实例统计、其他审计事件 schema 和归档压缩 |
| 用户身份/IP/所在地 | `metrics.Snapshot()` 输出 `user_id`、`user_name`、`remote_addr`、`real_ip`、`ip_location`；诊断页最近会话表格展示这些字段 | 已满足基础展示；真实地理位置依赖可信代理或未来 GeoIP 数据库 |
| 钉钉过载告警 | `internal/service/alert/dingtalk.go` 实现 webhook、签名、冷却；OpenAI/Azure 容量拒绝会触发告警 | 已覆盖容量拒绝，尚未覆盖队列满、错误率、Redis/OpenAI 异常等复合过载 |
| day/week/month 统计 | `internal/handler/web_metrics_handler.go` 返回 `charts.resources.day/week/month`；诊断页展示三组资源条形图 | 已覆盖 Web 请求最近窗口，不是跨实例长期统计 |
| 压测工具 | `tools/wsload` 支持连接数、ramp、duration、延迟百分位、错误分布、debug 采样和 JSON 报告 | 工具可用，但尚未提交真实百万并发报告 |

## 不能声明百万并发的原因

### 1. 单实例配置 100000 不等于已承载 100000

证据：

- `conf/config.yaml` 里 `capacity.max_active_sessions: 100000` 是配置值。
- `internal/service/session/capacity.go` 只做当前 Go 进程内的 atomic 计数。
- 当前没有单实例 100000 长连接的 `tools/wsload` 报告、CPU/内存/goroutine/FD/socket 曲线。

影响：

- 100000 是准入上限，不是压测结果。
- 如果 OS 文件句柄、内存、GC 或上游连接先达到瓶颈，配置值不会自动保证服务可用。

建议：

- 先做 1k、5k、10k、50k、100k 单实例阶梯压测。
- 每一档必须记录 P50/P95/P99、错误率、close code、goroutine、heap/RSS、FD/socket、Redis pool、日志写入。

### 2. 当前架构是每 App 会话一条上游 Realtime WebSocket

证据：

- `internal/provider/openai/client_ws.go` 的 `Connect` 为当前客户端建立 `apiConn`。
- `Client` 同时持有 `appConn` 和 `apiConn`，即 App 连接和上游连接一一对应。

影响：

- 百万 App 连接意味着百万上游 Realtime 连接。
- OpenAI 或第三方中转的并发连接、RPM、TPM、音频 token 和价格通常会先成为硬约束。

建议：

- 在容量报告里明确上游供应商最大并发、限流策略和商务配额。
- 压测时必须区分 `upstream-mode=mock` 和真实上游模式，不能用 mock 结果证明真实 OpenAI 容量。

### 3. 每会话四个主协程会放大百万连接的调度压力

证据：

- `HandleWS` 启动 `readPump`、`openAIWritePump`、`recvPump`、`writePump`。
- metrics 快照固定展示 `pipeline_workers: 4`。

影响：

- 百万连接至少对应 400 万主业务 goroutine，不含 runtime、logger、Redis、监控和临时 goroutine。
- goroutine 栈、调度延迟、GC pause 和内存碎片必须实测。

建议：

- 容量报告必须包含 goroutine 数、调度延迟迹象、GC pause 和每连接内存估算。
- 单实例上限要以实测曲线收敛，而不是直接采用配置上限。

### 4. 每会话两个 512 缓冲队列有内存峰值风险

证据：

- `sendChanSize = 512`。
- `apiSendChanSize = 512`。
- OpenAI 入站帧上限为 16 MiB，App 入站帧上限为 64 KiB。

影响：

- 队列本身防止无限增长，但在慢消费者或上游卡顿时会保留大量消息引用。
- 如果音频、文本 delta、tool result 和错误事件混合堆积，内存峰值和关键事件延迟都会上升。

建议：

- 继续保留有界队列，但补充连续慢消费者断开策略。
- 对 `response.done`、`error`、`reconnect_required`、workspace tool result 这类关键事件设更明确的投递保障和观测。

### 5. metrics 全局锁仍是高并发热路径风险

证据：

- `internal/service/metrics/metrics.go` 的 `collector` 使用单个 `sync.Mutex`。
- 会话开始、心跳、队列水位、OpenAI 事件、错误记录、业务 token 和 `Snapshot()` 都会访问同一 collector。

影响：

- 高并发下监控系统可能和业务热路径争锁。
- 诊断页面轮询 `Snapshot()` 时可能放大锁竞争。

建议：

- 高频总量计数改为 atomic 或分片计数。
- 最近会话明细和全局总量分离。
- 跨实例长期指标交给 Redis/Prometheus/日志平台，而不是全部塞在进程内 map。

### 6. Redis 配置仍是单地址，不是百万会话容量模型

证据：

- `conf/config.yaml` 中 Redis 是单个 `addr: "127.0.0.1:6379"`。
- `pool_size: 128` 是单实例连接池配置。

影响：

- 百万会话下 session、billing、rate limit、workspace audit 和监控统计都会对 Redis 形成持续压力。
- 单地址 Redis 不等同于 Redis Cluster 容量。

建议：

- 生产容量报告必须给出 Redis Cluster 节点数、QPS、内存、慢查询、连接池和故障恢复数据。
- 明确 Redis 不可用时生产策略是 fail-open、fail-closed 还是 degraded。

### 7. day/week/month 统计目前是最近窗口，不是全量长期统计

证据：

- `internal/handler/web_metrics_handler.go` 使用进程内 `webRequestStats.Records`，最大 500 条。
- `charts.resources.day/week/month` 基于最近窗口聚合。

影响：

- 服务重启会丢失 Web 请求统计。
- 多实例之间无法聚合。
- 不能代表生产全量天/周/月资源统计。

建议：

- 新增长期 stats service，把 token、费用、音频、错误、限流、容量拒绝、告警、workspace 写入等统一写入 Redis 或日志聚合系统。
- 诊断页保留最近窗口，统计页展示长期聚合。

### 8. 过载告警仍主要覆盖容量拒绝

证据：

- `notifyCapacityOverloadAlert` 只在 `TryAcquireCapacity()` 失败时触发。
- `alert.NotifyOverload` 已有钉钉发送、签名和冷却。

影响：

- 内存过高、队列持续满、Redis 异常、OpenAI 重连失败、错误率升高、P99 超阈值等过载形态不会主动通知。

建议：

- 增加过载判定器：容量拒绝、队列满、慢消费者、Redis ping 失败、OpenAI connect failure、错误率、内存/GC 超阈值。
- 告警内容带上 provider、user、real_ip、ip_location、active/max、队列水位、错误率和最近快照 ID。

## 当前应对外使用的容量口径

当前可以说：

> 当前服务已经具备 Realtime WebSocket 网关的基础保护、监控、告警、统计和压测工具，适合继续做分阶段容量验证。

当前不能说：

> 当前服务已经能顶住百万并发，并且 1 秒内稳定响应。

原因：

- 没有真实百万连接压测报告。
- 没有上游 OpenAI/第三方中转百万 Realtime 连接配额证明。
- 没有 Redis Cluster 和 LB 长连接分布证明。
- 没有 P95/P99 首包、首音频、完整响应延迟数据。
- 没有成本和故障恢复数据。

## 下一步修复和验证顺序

1. 生成真实小规模 `tools/wsload` 报告：10、100、1000 连接，记录 debug snapshot。
2. 增加 mock upstream 模式或本地 echo upstream，让压测能隔离 Go 网关容量和真实上游容量。
3. 优化 metrics 热路径：高频计数 atomic/分片，最近会话明细限流采样。
4. 在已有后台 monitor 周期采样、`event/event_date/instance_id`、基础日志清理调度、`log_cleanup` 摘要和跨零点轮换测试基础上，补归档压缩、长期聚合和其他审计事件 schema。
5. 增加复合过载告警：队列满、错误率、OpenAI 重连失败、Redis 异常、内存/GC 超阈值。
6. 建立长期 stats service，补齐跨实例 day/week/month 聚合。
7. 做单实例阶梯压测，再做多实例 + LB + Redis Cluster 压测。
8. 只有压测报告、资源曲线、上游配额和成本模型齐全后，才能重新审查百万并发目标。
