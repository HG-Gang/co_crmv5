# Task 11-12 执行确认清单

日期：2026-06-07

## 当前状态

Task 11-12 对应长期目标中的“百万并发 + 1 秒响应证明”和“全项目中文详细注释”。截至 2026-06-07，本轮已经补上压测工具、容量边界文档、编码防回归脚本和第一批 P1 中文注释；当前仍不能声明“已实测百万并发”或“全项目注释全部完成”。

本清单只锁定后续确认后的执行范围，不修改业务代码。Task 11-12 必须排在 Task 5-10 之后，因为容量压测和注释收口依赖前面阶段的真实实现：

1. Task 5 关闭 Workspace 直接落盘风险。
2. Task 6 修复 Realtime 生命周期、背压、关键事件和 OpenAI Ping。
3. Task 7-10 补齐 monitor、alert、stats 和 dashboard 数据源。
4. Task 11 用压测工具和报告证明容量边界。
5. Task 12 对实际最终代码补齐中文注释和编码门禁。

## 当前源码证据

| 能力 | 当前证据 | 判断 |
| --- | --- | --- |
| 压测工具目录 | `tools/wsload/main.go`、`tools/wsload/main_test.go`、`tools/wsload/README.md` | 已实现轻量压测工具 |
| WebSocket 压测工具 | `go test ./tools/wsload -run "Config\|Latency\|Report\|CloseCode\|Percentile\|Echo" -count=1 -v` 通过 | 已覆盖配置、报告和本地 echo WS 路径 |
| 生产容量报告 | `docs/production-capacity.md` | 已创建，明确未实测百万并发 |
| pprof/FD/socket 指标 | `rg -n "net/http/pprof|runtime/pprof|fd_count|handle_count|socket_count" cmd internal pkg web` 未发现业务实现 | 未实现 |
| 容量目标验收门槛 | `docs/reviews/2026-06-06-completion-acceptance-gates.md:25` 要求 `tools/wsload` 和 `docs/production-capacity.md` | 只有门槛，没有实现 |
| 中文注释缺口清单 | `docs/reviews/2026-06-06-source-commentary-coverage-inventory.md` 已追加 2026-06-07 P1 关闭记录 | P1 入口部分已关闭，P2 仍待补 |
| 编码防回归 | `scripts/check-commentary.ps1`、`internal/quality/commentary_test.go` | 已形成脚本和测试门禁 |

## 2026-06-07 本轮执行结果

Task 11 已完成可复现工具和容量边界文档：

- 新增 `tools/wsload`，支持 `-url`、`-users`、`-ramp`、`-duration`、`-token`、`-message`、`-debug-url`、`-report`。
- 新增本地 echo WebSocket 测试，覆盖真实连接、消息往返、close code 和报告聚合。
- 新增 `tools/wsload/README.md`，说明参数、报告字段、验证命令和生产容量边界。
- 新增 `docs/production-capacity.md`，明确当前未实测百万并发，不能声明已达到百万并发或 1 秒稳定响应。

Task 12 已完成第一批门禁和 P1 注释收口：

- 新增 `scripts/check-commentary.ps1`，以 ASCII 源码 + Unicode code point 扫描典型中文乱码，避免 PowerShell 5.1 误读脚本自身中文。
- 新增 `internal/quality/commentary_test.go`，把编码扫描纳入 `go test`。
- 补齐 Workspace、Web 静态主题注入、Web metrics、聊天看板、诊断看板、统一主题脚本和关键测试文件的中文职责/参数/安全边界说明。
- 浏览器烟测确认 `chat.html` 和 `diagnostics.html` 加载共享主题脚本，诊断页资源统计存在，页面无 console error。

## Task 11：压测工具与生产容量报告

### 确认后会修改的范围

会新增或修改：

- `tools/wsload/main.go`
- `tools/wsload/README.md`
- `docs/production-capacity.md`
- `internal/handler/debug_handler.go`
- `internal/service/monitor/snapshot.go`
- `internal/service/metrics/metrics.go`
- `conf/config.go`
- `conf/config.yaml`
- `cmd/server/main.go`

核心行为：

- 新增 `tools/wsload`，支持连接数、ramp、duration、token、文本消息、可选音频文件、真实上游或 mock 上游模式。
- 输出连接成功/失败、每秒连接数、每秒消息数、P50/P95/P99 握手延迟、首包延迟、完整响应延迟、close code、错误分布。
- 支持采样 Go debug/monitor API，记录 goroutine、内存、capacity、Redis pool、队列水位和错误数。
- `docs/production-capacity.md` 必须写明测试环境、集群拓扑、实例规格、LB 长连接策略、Redis 容量、OpenAI/第三方中转配额、OS 参数、压测命令、延迟结果、错误率、资源曲线和成本估算。
- 报告必须明确：不能由单实例代码直接承诺百万并发；百万并发只能由集群拓扑、上游配额和压测数据共同证明。

### 确认后不会修改的范围

- 不会伪造百万并发结果。
- 不会把单机开发压测写成生产容量证明。
- 不会绕过 OpenAI/第三方中转真实配额限制。
- 不会在报告中隐藏未测边界。

### 第一批红灯测试

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./tools/wsload -run "Config|Latency|Report|CloseCode|Percentile" -count=1
```

期望：

- `tools/wsload` 包不存在，测试失败。
- 配置解析、百分位计算、报告结构和 close code 聚合都未定义。

命令：

```powershell
Test-Path docs\production-capacity.md
```

期望：

- 返回 `False`，证明容量报告尚未创建。

### 完成 Task 11 的验收证据

- `go run ./tools/wsload -h` 能输出参数说明。
- `go test ./tools/wsload -run "Config|Latency|Report|CloseCode|Percentile|Echo" -count=1` 通过。
- 使用本地 echo WebSocket 完成一次小规模可复现压测路径测试。
- `docs/production-capacity.md` 引用实际压测命令和输出摘要。
- 报告包含 P50/P95/P99、错误率、资源曲线、上游配额和不能证明的边界。
- 如果没有真实百万并发环境，报告必须明确写“当前未实测百万并发，不能声明已达到”。

## Task 12：中文注释补齐与编码防回归

### 确认后会修改的范围

会新增或修改：

- `docs/commentary-cleanup.md`
- `docs/reviews/2026-06-06-source-commentary-coverage-inventory.md`
- `cmd/server/main.go`
- `conf/config.go`
- `conf/loader.go`
- `conf/*.yaml`
- `internal/handler/*.go`
- `internal/middleware/*.go`
- `internal/provider/openai/*.go`
- `internal/provider/azureai/*.go`
- `internal/provider/openairesponses/*.go`
- `internal/service/*/*.go`
- `pkg/**/*.go`
- `web/*.html`
- `web/*.js`
- `web/*.css`
- 关键 `*_test.go`
- 可选新增：`scripts/check-commentary.ps1` 或等价 Go/PowerShell 检查脚本

核心行为：

- 先关闭 P1 注释缺口：Workspace、Web 静态主题注入、Web metrics、聊天看板、主题脚本、诊断看板。
- 再补 Realtime、Gateway Protocol、工具执行、session、metrics、monitor、alert、stats、dashboard。
- 对配置字段补单位、默认值、生产风险和环境变量覆盖说明。
- 对 handler 入参说明来源、鉴权、脱敏、错误返回和生产暴露边界。
- 对 WebSocket 事件说明消息方向、事件类型、关键状态和失败处理。
- 对测试文件补中文测试目标，说明防止哪类生产回归。
- 清理或注释只有 `package` 行的预留文件，避免维护者误判。
- 建立 mojibake 扫描和注释覆盖检查命令，作为每阶段验收输入。

### 注释质量边界

注释必须解释：

- 逻辑职责：这个函数/结构/页面脚本负责什么。
- 参数含义：字段从哪里来，单位是什么，是否可信。
- 功能作用：对用户、上游、Redis、日志或监控有什么影响。
- 安全边界：是否涉及密钥、JWT、真实 IP、Redis value、项目文件、Workspace diff。
- 失败语义：什么错误会返回客户端，什么错误只写日志，什么错误必须告警。

注释不应机械复述代码，例如“给变量赋值”“调用函数”“返回结果”。

### 第一批红灯检查

命令：

```powershell
rg -n "^[[:space:]]*package " internal\handler\workspace_handler.go internal\service\workspace\workspace.go web\theme.js
```

期望：

- 能定位到 P1 文件，但不能证明中文注释已达标。

命令：

```powershell
rg -n "锛|鍙|鏃|閰|鈥|乣|乄|丱|丷|丟|丠|乬|亃|ï¿½|Ã|Â|â€" cmd conf internal pkg web README.md
```

期望：

- 无未解释命中。

命令：

```powershell
Test-Path scripts\check-commentary.ps1
```

期望：

- 当前返回 `False`，说明还没有自动化注释/乱码检查脚本。

### 完成 Task 12 的验收证据

- `docs/reviews/2026-06-06-source-commentary-coverage-inventory.md` 中第一批 P1 文件已追加关闭记录。
- Workspace、Realtime、monitor、alert、stats、dashboard 的关键函数和字段都有中文职责、参数、边界说明。
- mojibake 扫描无未解释命中。
- `scripts/check-commentary.ps1` 和 `go test ./internal/quality -run TestCommentaryScriptScansSourceForMojibake -count=1` 可复现。
- `go test ./... -count=1` 通过。
- 涉及 Web 页面时完成页面烟测，确认主题同步、按钮事件、图表和文本布局正常。

## 总目标最终关闭顺序

即使 Task 11-12 完成，也只能在以下证据全部存在后，才能声明长期目标完成：

1. Task 1-4：生产安全、公开路由、JWT、Origin、上游 Key 策略已通过测试。
2. Task 5：Workspace pending diff/确认/拒绝/审计已通过测试。
3. Task 6：Realtime 生命周期、背压、关键事件、OpenAI Ping 已通过测试。
4. Task 7：monitor snapshot 和按天日志落点可验证。
5. Task 8：钉钉告警、冷却、恢复、发送失败可验证。
6. Task 9-10：day/week/month stats 和图表可验证。
7. Task 11：`tools/wsload` 和 `docs/production-capacity.md` 有实测证据。
8. Task 12：中文注释和编码防回归清单关闭。
9. 全量 `go test ./... -count=1` 通过。
10. 前端涉及页面完成烟测。

## 需要用户确认的执行口径

建议确认语句：

```text
确认开始执行 Task 11-12，按计划新增 WebSocket 压测工具、生产容量报告，并补齐中文注释和编码防回归。
```

收到确认后，应先确认 Task 5-10 已完成并通过验证，再执行 Task 11-12。容量目标不能用静态审查替代实测。
