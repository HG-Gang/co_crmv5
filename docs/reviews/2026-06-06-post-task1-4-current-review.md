# Task 1-4 后当前审查清单

审查日期：2026-06-06

## 当前结论

Task 1-4 已完成并通过 `go test ./... -count=1`：生产安全配置、公开路由、JWT 默认密钥、Realtime Origin 和上游 query key 策略已经收紧。

主目标仍未完成。当前代码仍不能证明可以承载“百万并发 + 1 秒内稳定响应”，也还没有完成 Workspace 写入确认、监控日志闭环、钉钉告警、天/周/月统计、容量压测报告和全仓库中文详细注释。

## 已关闭的第一批问题

| 项 | 当前证据 |
| --- | --- |
| 生产安全配置结构 | `conf/config.go:78` 新增 `Security` 配置结构。 |
| 生产启动校验 | `conf/loader.go:78` 调用 `validateProductionConfig`，`conf/loader.go:143` 实现 prod 校验。 |
| 公开 token/debug 路由收紧 | `cmd/server/main.go:127` 仅在 `PublicTokenEnabled` 时注册 `/test/generate-token`；`cmd/server/main.go:146` 和 `cmd/server/main.go:175` 区分公开/鉴权 debug 路由。 |
| JWT 默认密钥移除 | `internal/middleware/auth.go:135` 生成 token 前必须解析真实 secret，空 secret 返回错误。 |
| Origin 与上游 key 策略 | `internal/handler/openai_handler.go:39` 使用配置化 Origin 检查，`internal/handler/openai_handler.go:184` 在未允许时拒绝 query key。 |

## 仍需确认修复的 P0/P1 问题

### P0-01：当前架构不能证明百万并发和 1 秒响应

证据：
- `internal/provider/openai/client_ws.go:210` 和 `internal/provider/openai/client_ws.go:211` 每个会话各分配一个 App 下行队列和上游写队列。
- `internal/provider/openai/client_ws.go:315` 每个会话独占一个 OpenAI Realtime WebSocket。
- `internal/provider/openai/client_ws.go:343` 到 `internal/provider/openai/client_ws.go:346` 每个会话启动 4 条主 goroutine。
- `internal/service/session/capacity.go:9` 的容量计数是单进程内 atomic 计数，不是集群容量控制。

影响：
百万 App 会话会对应百万上游连接和数百万 goroutine/队列/FD/socket 压力。没有 `tools/wsload`、pprof、FD/socket 指标和 `docs/production-capacity.md` 的实测报告前，不能声明达成百万并发或 1 秒时延。

建议下一步：
先修长连接背压和容量释放语义，再新增压测工具和容量报告。百万并发应按多实例、负载均衡、Redis/指标分片、上游配额共同证明，不能由单进程承诺。

### P0-02：Workspace 工具和 HTTP 接口仍可直接写项目文件

证据：
- `internal/provider/openai/tool_execution.go:376` 的 `workspace_write_file` 直接调用 `workspace.Write()`。
- `internal/handler/workspace_handler.go:44` 的 HTTP 写接口也直接写入。
- `internal/service/workspace/workspace.go:142` 到 `internal/service/workspace/workspace.go:160` 最终执行 `os.WriteFile`。
- `conf/config.go:84` 已有 `workspace_write_confirm` 配置字段，但当前写链路未使用。

影响：
模型误判、prompt 注入、上游中转篡改工具调用或已鉴权前端误操作，都可能直接改工作区文件。当前没有 pending diff、用户确认、拒绝路径、审计日志、回滚快照或敏感路径策略。

建议下一步：
把 `workspace_write_file` 和 `/api/workspace/write` 改为 preview/pending 模式；确认后才写磁盘；写入、拒绝、失败和回滚都写按天审计日志。

### P0-03：metrics 全局锁在高并发热路径上

证据：
- `internal/service/metrics/metrics.go:234`、`internal/service/metrics/metrics.go:304`、`internal/service/metrics/metrics.go:396`、`internal/service/metrics/metrics.go:552`、`internal/service/metrics/metrics.go:643` 等高频路径都持有同一个 `global.mu`。
- `internal/service/metrics/metrics.go:659` 的 `Snapshot()` 也持有同一把锁复制数据。

影响：
在高频音频帧、文本 delta、OpenAI event、队列水位和面板轮询叠加时，监控系统本身会争用业务热路径，放大尾延迟。

建议下一步：
高频计数拆成 atomic 或分片计数；最近会话/事件保留与总量统计分离；长期统计写 stats/Redis/DB，不让 debug snapshot 阻塞热路径。

### P0-04：背压语义仍不完整

证据：
- `internal/provider/openai/client_ws.go:793` 上游写队列满后等待 `send_queue_timeout_ms`，超时返回错误。
- `internal/provider/openai/client_ws.go:1158` App 下行队列满后等待超时并丢弃消息。

影响：
有界队列能保护内存，但关键事件和非关键事件没有分级。慢客户端或上游卡顿时，用户可能只看到局部缺失或无响应，无法证明 1 秒内稳定响应。

建议下一步：
定义可丢弃事件和不可丢弃事件；队列水位纳入告警；关键事件失败要明确关闭会话或返回协议错误；补慢客户端、高频音频、半开连接和上游重连测试。

### P1-01：监控面板已有基础字段，但目标字段未闭环

已有证据：
- `internal/handler/debug_handler.go:65`、`internal/handler/debug_handler.go:88`、`internal/handler/debug_handler.go:122`、`internal/handler/debug_handler.go:155` 已返回 Go runtime、内存、容量、Redis。
- `internal/service/metrics/metrics.go:733` 已返回 `user_name`。
- `web/diagnostics.html:554` 到 `web/diagnostics.html:574` 只基于 Redis key 扫描做分类统计。

缺口：
未见真实 IP 可信解析、所在地 GeoIP、PID/FD/socket、系统进程数、业务缓存命中总量、钉钉告警状态、按天日志中的 monitor snapshot、day/week/month 统计 API。

建议下一步：
新增 monitor service，统一采集实例信息、在线用户、真实 IP/所在地、Redis、OpenAI、错误和告警状态；固定周期写 `monitor_snapshot` 到当天日志。

### P1-02：钉钉过载预警尚未实现

证据：
- 当前代码未发现 `internal/service/alert`、`dingtalk`、`DingTalk` 或 webhook 发送实现。
- `internal/handler/openai_handler.go:121` 和 `internal/handler/azureai_handler.go:71` 过载时仅返回 503。

影响：
容量拒绝、Redis 异常、上游重连失败、错误率升高、队列压力和内存压力无法主动通知。

建议下一步：
新增 alert service：阈值、冷却、恢复通知、钉钉签名、发送失败日志和面板状态；先用单元测试覆盖签名、冷却、恢复和失败路径。

### P1-03：天/周/月统计和业务缓存命中未完成

证据：
- `internal/service/billing/billing.go:220` 只有 daily token key。
- `internal/service/billing/billing.go:85` 和 `internal/service/billing/billing.go:222` TTL 仅 32 天。
- `internal/service/metrics/metrics.go:124` 只有进程内 `TokensByDay`。
- `internal/handler/web_metrics_handler.go:19` Web 请求指标只保留进程内最近 500 条。

影响：
不能生成稳定的日/周/月资源统计图，也不能跨实例、跨重启聚合。Redis pool hits/misses 不是业务缓存命中，不能作为“缓存命中总量”。

建议下一步：
新增 stats service 和 `/api/stats/resources?period=day|week|month&model=...`；统一写 token、费用、音频、错误、限流、容量拒绝、告警、Workspace 写入、业务缓存命中。

### P1-04：按天日志基础存在，但审计 schema 未闭环

证据：
- `internal/logger/logger.go:42` 根据日期创建 logger。
- `internal/logger/logger.go:59` 文件名按 `{model}-{date}.log` 生成。

缺口：
没有统一 `event` 字段、没有 monitor/audit writer、没有日志保留清理、没有敏感字段脱敏 helper，面板字段也未全部写入日志。

建议下一步：
定义统一审计事件字段：`event`、`event_date`、`instance_id`、`request_id`、`user_id`、`user_name`、`real_ip`、`module`、`status`；建立 redact helper，避免 key/token/webhook/Redis value/文件 diff 明文泄露。

### P1-05：中文详细注释目标仍未完成

证据：
- `internal/handler/workspace_handler.go:11` 到 `internal/handler/workspace_handler.go:63` 几乎无参数与安全边界说明。
- `internal/service/workspace/workspace.go:43`、`internal/service/workspace/workspace.go:62`、`internal/service/workspace/workspace.go:116`、`internal/service/workspace/workspace.go:142` 等关键 API 缺少中文职责、参数和边界说明。

影响：
仍不满足“当前所有文件的项目代码必须全部使用中文详细注释其逻辑含义和参数含义及功能作用”。

建议下一步：
不要做无差别堆注释；按安全边界和热路径优先补齐：Workspace、Realtime provider、monitor/alert/stats、handler 入参、配置字段、WebSocket 事件和状态机。

## 建议确认后的下一批 Task

1. Task 5：Workspace pending diff、确认、拒绝、审计、回滚。
2. Task 6：Realtime 长连接生命周期、背压、队列水位、断线释放和重连韧性。
3. Task 7：monitor snapshot、真实 IP/所在地、进程资源和按天日志落点。
4. Task 8：钉钉过载告警。
5. Task 9：day/week/month stats 和业务缓存命中统计。
6. Task 10：诊断面板图表和字段展示。
7. Task 11：WebSocket 压测工具和容量报告。
8. Task 12：中文注释补齐和编码防回归。

## 本轮未改动业务代码

本轮仅新增当前审查文档，未修改 Go/HTML/JS 业务逻辑。继续修复前应由用户确认下一批范围，建议优先从 Task 5-6 开始，因为它们直接影响“允许模型改文件”和“长连接是否能承受高并发压力”。
