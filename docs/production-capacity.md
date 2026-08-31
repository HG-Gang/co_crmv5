# 生产容量报告

日期：2026-06-07

## 结论

当前项目还没有实测百万并发，也不能声明“百万并发 + 1 秒稳定响应”已经达到。

本阶段新增了 `tools/wsload` 压测工具，并用本地 WebSocket echo 测试覆盖了真实连接、发送消息、接收响应、close code 和报告聚合路径。这个验证只能证明工具行为可复现，不能替代生产集群压测。

## 已验证内容

本阶段已运行的工具级验证：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build')
go run ./tools/wsload -h
```

结果：参数帮助可正常输出，不会发起连接。

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build')
go test ./tools/wsload -run "Config|Latency|Report|CloseCode|Percentile|Echo" -count=1 -v
```

结果：目标测试通过，覆盖配置解析、百分位计算、报告 JSON、容量边界声明，以及本地 echo WebSocket 的成功连接和消息往返。

## 当前未验证内容

以下内容尚未实测，因此不能作为生产容量结论：

- 未实测单实例 10 万连接。
- 未实测集群百万连接。
- 未实测 1 秒首包、首音频或完整响应的 P95/P99。
- 未实测 Redis Cluster 在百万会话下的吞吐和内存。
- 未实测 OpenAI 或第三方中转的 Realtime 最大并发、RPM/TPM、音频 token 限额。
- 未实测 LB 长连接分布、实例摘除、半开连接和重连风暴。
- 未实测 FD/socket、带宽、GC pause、goroutine 调度和日志写入峰值。

## 1 秒响应口径

“1 秒响应”必须拆成可测指标，不能只看平均值：

- `connect_latency_ms`：App 到 Go WebSocket 握手完成时间。
- `upstream_connect_latency_ms`：Go 到 OpenAI/第三方中转 WebSocket 握手完成时间。
- `first_event_latency_ms`：App 发送用户事件到收到第一条上游有效响应事件。
- `first_audio_latency_ms`：App 发送音频或文本到收到第一段可播放音频。
- `complete_response_latency_ms`：一次 response 从创建到完成的总耗时。

生产验收建议至少要求：

- P95 `first_event_latency_ms` <= 1000ms。
- P99 `first_event_latency_ms` <= 2000ms。
- 错误率 <= 0.5%。
- close code 和上游限流原因有完整分布。
- 容量拒绝、队列满、Redis 异常和上游异常均有告警记录。

## 百万并发验收材料

要声明“百万并发可用”，至少需要提交以下证据：

| 类别 | 必须提供 |
| --- | --- |
| 集群拓扑 | 实例数、实例规格、区域、LB 类型、连接分配策略、故障摘除策略 |
| 每实例容量 | 最大连接数、CPU、RSS/heap、goroutine、FD/socket、GC pause、网络带宽 |
| OS 参数 | 文件句柄、端口范围、TCP keepalive、backlog、TIME_WAIT、内核 socket 参数 |
| Redis 容量 | Cluster 节点数、连接池、QPS、慢查询、内存、故障恢复 |
| 上游配额 | OpenAI/第三方中转并发连接、RPM/TPM、音频 token、429 策略和配额凭证 |
| 压测命令 | `tools/wsload` 版本、参数、消息类型、ramp、duration、token 策略 |
| 延迟结果 | P50/P95/P99 的连接、首包、首音频、完整响应 |
| 错误分布 | HTTP 状态、WebSocket close code、读写超时、限流、容量拒绝、断链 |
| 资源曲线 | CPU、内存、goroutine、FD/socket、Redis pool、日志写入、磁盘和网络 |
| 成本估算 | token、音频时长、带宽、Redis、日志、监控、告警和上游费用 |

## 建议压测阶梯

1. 本地工具验证：1-10 连接，mock 或 echo server，确认报告字段正确。
2. 开发环境冒烟：10-100 连接，真实服务，短时 `1m-5m`。
3. 单实例基线：1k、5k、10k、50k 逐级爬坡，记录资源曲线。
4. 单实例上限：逼近 `capacity.max_active_sessions`，观察拒绝、队列、错误率和告警。
5. 小集群验证：多实例 + LB + Redis，验证连接分布和实例摘除。
6. 生产规模预演：按目标比例放大，必须包含上游配额和真实消息类型。
7. 百万并发验收：只有当集群、上游、Redis、OS 和成本全部有实测报告时才能声明达成。

## 当前容量结论文本

当前应使用以下对外口径：

> 当前已具备 WebSocket 压测工具和小规模可复现验证路径；尚未实测百万并发，不能声明已达到百万并发或 1 秒稳定响应。百万并发必须由多实例拓扑、LB 长连接策略、OS FD/socket 参数、Redis 容量、OpenAI/第三方中转配额和真实压测报告共同证明。

## 后续工作

- 用 `tools/wsload` 在真实开发服务上生成 `.tmp/wsload-report.json`。
- 将 debug/metrics 采样纳入压测报告，补充 goroutine、内存、容量、Redis 和错误率曲线。
- 在生产前建立固定压测环境，避免本机测试结果被误用为生产结论。
- 将容量压测报告与告警、按天/周/月统计和日志审计一起归档。
