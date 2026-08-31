# 测试质量门槛就绪矩阵

审查日期：2026-06-06

当前阶段：继续审查并固化证据，不修改业务逻辑。

## 结论

当前项目有一批有价值的单元测试，主要覆盖配置加载、OpenAI Realtime 协议适配、工具调用、调试脱敏、Web 静态主题注入和部分 query 上游配置覆盖行为。但这些测试还不能证明系统满足生产安全、百万并发容量、1 秒内响应、监控日志、钉钉告警、天/周/月统计、Workspace 安全写入和全仓库中文注释目标。

本轮实际执行 `go test ./... -cover` 通过，但覆盖率显示多个生产关键包仍为 0.0%。因此，“测试通过”只能证明当前已有测试未失败，不能作为主目标完成证据。

## 当前已有测试覆盖

| 测试文件 | 当前覆盖行为 | 仍不能证明的目标 |
| --- | --- | --- |
| `conf/loader_test.go:9` | 根级模型覆盖、环境变量覆盖、配置合并 | 生产启动安全 gate、JWT 空密钥拒绝、Origin/Trusted Proxy/日志保留配置校验 |
| `internal/handler/debug_handler_test.go:10` | proxy 解析优先级、API key 脱敏格式 | debug/status 生产鉴权、敏感路由匿名访问关闭、监控快照落日志 |
| `internal/handler/web_static_handler_test.go:10` | `/web/*.html` 共享主题脚本注入、相对路径归一、路径穿越拒绝 | 所有页面浏览器烟测、主题跨页面实时同步、未来新增页面继承策略 |
| `internal/handler/openai_handler_test.go:13` | query 覆盖上游 Realtime URL/API key/model、HTTP URL 转 WS URL、非法 URL 拒绝 | prod 禁止 query key、Origin 配置化、真实 IP 可信代理、日志不泄露 key |
| `internal/provider/openai/config_test.go:9` | proxy URL 读取和空值处理 | Realtime/Azure/第三方中转完整握手和真实协议冒烟 |
| `internal/provider/openai/client_ws_test.go:9` | 会话恢复快照只缓存可恢复事件 | App 断开快速释放容量、OpenAI 读阻塞打断、Ping ticker、重连竞态、队列背压 |
| `internal/provider/openai/gateway_protocol_test.go:13` | legacy 文本计划、GA `session.update`、工具注入、Azure URL、ping/pong、response gate、中断标记、response id 兼容 | 真实 OpenAI/中转端到端文本、音频、工具调用、错误事件和限流语义 |
| `internal/provider/openai/tool_execution_test.go:16` | 天气、地图工具调用，以及当前 Workspace 工具直接写读文件行为 | pending diff、确认/拒绝/回滚、审计日志、敏感路径策略；当前测试反而锁定了直接写文件的高风险行为 |
| `pkg/protocol/openai/server_events_test.go:5` | 当前和 legacy server event name 解析 | 完整 Realtime 事件矩阵、未知事件兼容策略、协议变更告警 |

## 覆盖率证据

本轮命令：

```powershell
go test ./... -cover
```

命令退出码为 0，但覆盖率暴露以下关键缺口：

| 包 | 当前覆盖率 | 风险说明 |
| --- | ---: | --- |
| `TozoAI-Chat-Api/cmd/server` | 0.0% | 路由注册、公开接口收紧、Trusted Proxy 和启动流程无回归保护 |
| `internal/logger` | 0.0% | 按天日志、跨零点轮换、日志清理和敏感字段脱敏未被测试证明 |
| `internal/middleware` | 0.0% | JWT 默认密钥、鉴权、限流、生产安全边界缺少测试 |
| `internal/provider/azureai` | 0.0% | Azure Realtime/状态接口和生产监控字段缺少测试 |
| `internal/provider/openairesponses` | 0.0% | Responses 费用、错误、缓存 token 和状态链路缺少测试 |
| `internal/rate` | 0.0% | 限流策略、Redis 异常降级和生产 fail-open/fail-closed 语义缺少测试 |
| `internal/service/billing` | 0.0% | token/audio 计费、daily detail、后续 day/week/month 聚合缺少测试 |
| `internal/service/metrics` | 0.0% | 全局 metrics 热路径、在线用户、错误中心、缓存命中、队列水位缺少测试 |
| `internal/service/redis` | 0.0% | Redis 初始化、Ping 失败策略、连接池状态和明细读取缺少测试 |
| `internal/service/session` | 0.0% | 单进程容量控制、会话释放、用户 ID/名称维度缺少测试 |
| `internal/service/workspace` | 0.0% | Workspace 根目录逃逸、大小限制、写入确认、审计和回滚缺少测试 |
| `pkg/response` | 0.0% | 统一 HTTP 响应结构缺少回归保护 |
| `pkg/utils` | 0.0% | 工具函数边界缺少测试 |

有覆盖但仍不足的包：

| 包 | 当前覆盖率 | 说明 |
| --- | ---: | --- |
| `conf` | 71.5% | 基础配置加载覆盖较好，但缺生产安全校验 |
| `internal/handler` | 10.8% | 只覆盖少量 handler helper；公开路由、监控、Redis、Responses、Azure 等大量路径未覆盖 |
| `internal/provider/openai` | 29.0% | 协议适配已有基础测试，但长连接生命周期、背压、真实上游兼容仍不足 |
| `pkg/protocol/openai` | 13.3% | event name 解析有覆盖，完整事件 schema 覆盖不足 |

## 缺口矩阵

| 优先级 | 缺口 | 当前证据 | 影响 | 修复阶段门槛 |
| --- | --- | --- | --- | --- |
| P0 | 生产启动安全测试不存在 | `conf/loader_test.go` 未覆盖 prod JWT secret、公开调试开关、allowed origins、trusted proxies、Redis/logs gate | 生产误配置仍可能启动并暴露敏感能力 | Task 1 先写失败测试，再实现 `ValidateProductionConfig()` |
| P0 | 敏感公开路由测试不存在 | `cmd/server` 覆盖率 0.0%，路由注册未抽出可测试函数 | `/test/generate-token`、debug、Redis、metrics 等匿名暴露风险无法被测试关闭 | Task 2 增加 prod 路由注册/鉴权测试 |
| P0 | JWT 和限流中间件测试不存在 | `internal/middleware` 覆盖率 0.0% | 默认密钥、鉴权 claim、Redis 限流降级策略容易回归 | Task 3 补 JWT 空密钥、用户名称 claim、限流异常策略测试 |
| P0 | Realtime query key 策略测试方向需要调整 | `internal/handler/openai_handler_test.go:13` 当前证明 query key 可以覆盖 | 这与生产 API key 不进 URL 的安全目标冲突 | Task 4 改为 prod 拒绝、dev 显式允许，并测试日志脱敏 |
| P0 | Workspace 安全写入测试缺失 | `internal/service/workspace` 覆盖率 0.0%；`tool_execution_test.go:130` 锁定直接写文件 | 模型工具可直接改项目文件，缺 pending diff 和确认 | Task 5 先写 pending/confirm/reject/rollback 测试 |
| P0 | 长连接生命周期和背压测试缺失 | `client_ws_test.go` 仅覆盖 replay cache | App 断开释放延迟、OpenAI 读阻塞、Ping 配置、慢客户端丢事件无法被证明 | Task 6 补 App disconnect、Ping、Queue、Reconnect 测试 |
| P1 | 监控、日志、告警、统计服务测试不存在 | `internal/service/metrics`、`billing`、`logger` 均为 0.0%，`monitor`、`alert`、`stats` 包尚不存在 | 用户要求的面板字段、按天日志、钉钉告警、天/周/月统计无法验收 | Task 7-9 按服务先写测试，再接 handler 和页面 |
| P1 | 前端页面缺浏览器烟测 | 当前没有 Playwright 或等价浏览器检查 | 主题同步、图表、面板字段、按钮文字和 JS 运行时错误无法由 Go 测试发现 | 涉及页面变更时启动服务并做浏览器烟测或截图证据 |
| P1 | 压测和容量证明缺失 | 未发现 `tools/wsload` 和 `docs/production-capacity.md` | 无法证明百万并发和 1 秒延迟 | Task 11 新增可复现压测工具、容量报告和 P50/P95/P99 数据 |
| P2 | 测试文件缺中文测试目标说明 | 多个 `*_test.go` 0 或极低中文注释，见源码注释覆盖清单 | 后续维护者难以理解测试防止哪类生产回归 | Task 12 给关键测试增加中文目标说明 |

## 质量门槛

后续每个修复阶段至少满足：

1. 先用测试描述当前缺口；能做红绿验证的场景要先看到失败，再实现修复。
2. 阶段内目标测试通过，且 `go test ./... -count=1` 通过。
3. 涉及安全边界的变更必须有 prod/dev 两类配置测试。
4. 涉及日志、告警、统计的变更必须能在测试或可复现运行中看到实际事件字段。
5. 涉及前端页面、图表或主题同步的变更必须做浏览器烟测，不能只看 Go 测试。
6. 涉及容量目标的变更必须提供压测命令、P50/P95/P99、错误率、资源曲线和上游配额说明。

## 与总目标的关系

本矩阵补充 `docs/reviews/2026-06-06-completion-acceptance-gates.md` 中“测试验证”门槛的细化证据。当前结论是：测试体系尚未满足主目标验收要求，应继续保留在待修复清单中。用户确认进入修复阶段后，建议按实施计划 Task 1-4 先关闭生产安全测试缺口，再推进 Workspace、长连接、监控、告警、统计、前端和压测测试。
