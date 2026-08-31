# Task 5-6 执行确认清单

日期：2026-06-07

## 当前状态

Task 1-4 已经完成生产安全收紧：生产配置校验、公开路由、JWT 默认密钥、Realtime Origin 和上游 query key 策略已经有测试覆盖。

主目标仍未完成。当前最需要确认的下一批修复是 Task 5-6：

1. Task 5：Workspace 写文件必须改成待确认预览，不能让模型工具或 HTTP 接口直接落盘。
2. Task 6：Realtime 长连接必须修复容量释放、背压、关键事件投递和 OpenAI Ping 语义。

这两项是后续监控、钉钉告警、统计图表和容量压测的前置条件。原因很直接：如果模型仍能直接改文件，监控再完善也挡不住高风险写入；如果长连接退出和背压语义不清，容量压测数据也不可信。

## 确认后会修改的范围

### Task 5 修改范围

会新增或修改：

- `internal/service/workspace/pending.go`
- `internal/service/workspace/audit.go`
- `internal/service/workspace/workspace.go`
- `internal/handler/workspace_handler.go`
- `internal/provider/openai/tool_execution.go`
- `cmd/server/main.go`
- `web/chat.html`
- `internal/service/workspace/workspace_test.go`
- `internal/handler/workspace_handler_test.go`
- `internal/provider/openai/tool_execution_test.go`

核心行为：

- `workspace_write_file` 在 `security.workspace_write_confirm=true` 时只创建 pending write。
- `/api/workspace/write` 在确认开关开启时只返回 pending id 和 diff。
- 新增 `/api/workspace/write/confirm`，用户确认后才调用底层 `Write()`。
- 新增 `/api/workspace/write/reject`，用户拒绝后文件不变。
- 审计日志记录 `workspace_write_preview`、`workspace_write_confirmed`、`workspace_write_rejected`、`workspace_write_failed`。
- 拒绝 `.env`、私钥、授权文件等敏感路径。
- 拒绝二进制内容，避免把图片、数据库、压缩包当文本覆盖。

### Task 6 修改范围

会新增或修改：

- `internal/provider/openai/client_ws.go`
- `internal/provider/openai/client_ws_test.go`
- `internal/provider/openai/config.go`
- `internal/service/metrics/metrics.go`

核心行为：

- 任一 pump 退出后集中关闭 App WS 和 OpenAI WS，让阻塞读立即返回。
- App 下行队列满时区分关键事件和可丢弃事件。
- `response.done`、`error`、`reconnect_required`、`session_restored`、workspace tool result 等关键事件不能静默丢弃。
- 上游写队列满时向 App 返回结构化错误，并记录指标。
- `api_ping_interval` 要么真正驱动 OpenAI Ping ticker，要么从配置和文档中移除；建议实现 Ping ticker。
- 队列水位、关键事件投递失败、OpenAI Ping 写失败进入 metrics。

## 确认后不会修改的范围

Task 5-6 阶段不会处理：

- 钉钉机器人通知。
- day/week/month 统计 API。
- 监控大屏图表重做。
- 容量压测工具和生产容量报告。
- 全仓库中文注释大规模补齐。
- Redis/DB 长期统计落库重构。

这些放到 Task 7-12。这样可以避免一次性混改，保证每个阶段能独立验证。

## 第一批红灯测试

确认开始后，先写并验证这些红灯测试。

### Workspace service 红灯

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/workspace -run "PreviewWrite|ConfirmPendingWrite|RejectPendingWrite" -count=1
```

期望：

- `PreviewWrite` 未定义，测试失败。
- `ConfirmPendingWrite` 未定义，测试失败。
- `RejectPendingWrite` 未定义，测试失败。
- 失败原因必须是缺失待实现能力，而不是测试拼写错误。

### Workspace tool 红灯

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/provider/openai -run Workspace -count=1
```

期望：

- 当前工具仍直接写磁盘。
- 新测试应证明开启 `WorkspaceWriteConfirm` 后，工具调用不应写文件，只应返回 `pending_write_id` 和 `diff`。

### Workspace handler 红灯

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/handler -run "WorkspaceWrite" -count=1
```

期望：

- 当前 handler 没有 confirm/reject API。
- `/api/workspace/write` 仍直接写文件，不满足待确认策略。

### Realtime queue 红灯

命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/provider/openai -run "SendAppEvent|ForwardCritical|CloseRealtimeConnections|OpenAIPing" -count=1
```

期望：

- `sendAppEvent`、`forwardCriticalToApp`、`closeRealtimeConnections` 等测试 seam 尚不存在。
- 当前 `safeSend` 对所有下行事件采用统一队列满丢弃策略，无法区分关键事件。

## 完成 Task 5 的验收证据

Task 5 完成前必须提供：

- 单元测试证明 preview 不写文件。
- 单元测试证明 confirm 后文件才变化。
- 单元测试证明 reject 后文件不变。
- 单元测试证明 `.env`、私钥等敏感路径被拒绝。
- 模型工具测试证明 `workspace_write_file` 在确认开关开启时只返回 pending。
- handler 测试证明 HTTP 写入口同样进入 pending。
- 当天日志可检索到 `workspace_write_preview` 和 `workspace_write_confirmed`。

验收命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/workspace ./internal/provider/openai ./internal/handler -run "Workspace|PendingWrite|ConfirmPendingWrite|RejectPendingWrite" -count=1
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./... -count=1
```

## 完成 Task 6 的验收证据

Task 6 完成前必须提供：

- 测试证明关键事件队列满返回错误，不静默丢弃。
- 测试证明可丢弃事件队列满只计数，不拖死会话。
- 测试证明集中关闭连接函数幂等。
- 测试证明 `api_ping_interval` 的配置解析有效。
- 代码证明 OpenAI Ping ticker 使用该配置，且写失败会退出或记录明确错误。
- metrics 快照包含关键事件队列超时和 OpenAI Ping 指标。

验收命令：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/provider/openai -run "SendAppEvent|ForwardCritical|CloseRealtimeConnections|OpenAIPing|Queue|Reconnect" -count=1
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/metrics -run "Queue|Critical|Ping|SlowConsumer" -count=1
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./... -count=1
```

## 需要用户确认的执行口径

建议确认语句：

```text
确认开始执行 Task 5-6，按计划先修 Workspace pending diff/确认/审计，再修 Realtime 长连接背压和 OpenAI Ping。
```

收到确认后，应按 `docs/superpowers/plans/2026-06-06-task5-6-workspace-runtime-hardening.md` 逐步执行，并保持 TDD 顺序：先红灯、再实现、再验证。
