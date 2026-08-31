# Workspace 文件修改安全就绪矩阵

审查日期：2026-06-06

当前状态：仍处于“先审查、列问题、定位证据”阶段。本文只审查聊天看板、Workspace HTTP API、OpenAI Realtime 工具调用和本地项目文件写入边界，不修改业务代码。

## 总体判断

当前 Workspace 能力已经可以列项目、列目录、读文件、写文件，也已经通过路径归一化阻止绝对路径和根目录逃逸，单文件读写大小限制为 512 KiB。但它还不能安全支撑“类似 Codex，可以选择项目，然后通过 WebSocket 询问咨询，并更改当前项目相关文件”的目标。

核心风险是：模型工具 `workspace_write_file` 会直接调用 `workspace.Write()` 写磁盘；`/api/workspace/write` 也会直接写磁盘；前端 `web/chat.html` 的保存按钮和工具回调没有 pending diff、二次确认、审计日志、回滚快照或拒绝路径。模型输出错误、提示注入、上游中转篡改工具调用、用户误操作，都可能直接改项目文件。

## 已有能力

| 能力 | 当前证据 | 当前价值 | 限制 |
| --- | --- | --- | --- |
| Workspace 路由受 auth 组保护 | `cmd/server/main.go:120` 到 `cmd/server/main.go:127` | 在 JWT/限流开启时可受中间件保护 | 如果生产配置未开启 JWT/限流，auth 组仍可能无实际保护 |
| 项目选择 | `internal/service/workspace/workspace.go:43` 到 `internal/service/workspace/workspace.go:52`；`web/chat.html:632` 到 `web/chat.html:651` | 当前能暴露一个 `current` 项目并在页面选择 | 只支持当前进程工作目录；没有多项目白名单、只读/可写权限 |
| 路径逃逸防护 | `internal/service/workspace/workspace.go:178` 到 `internal/service/workspace/workspace.go:196` | 禁止绝对路径和 `..` 逃出项目根目录 | 未限制敏感文件类型，例如 `.env`、密钥、日志、二进制、生成目录 |
| 文件大小限制 | `internal/service/workspace/workspace.go:13` 到 `internal/service/workspace/workspace.go:15`、`internal/service/workspace/workspace.go:132`、`internal/service/workspace/workspace.go:143` | 避免通过 Web API 读写超大文件 | 只限制大小，不区分文本/二进制，不做 diff 粒度限制 |
| 目录列表跳过部分目录 | `internal/service/workspace/workspace.go:17` 到 `internal/service/workspace/workspace.go:20` | 跳过 `.git`、`node_modules`、`vendor` 等高噪声目录 | 只影响列表，不阻止直接按路径读取/写入跳过目录内文件 |
| 前端文件编辑器 | `web/chat.html:678` 到 `web/chat.html:703` | 用户可手动打开和保存文件 | 保存直接 POST `/api/workspace/write`，没有变更预览和确认 |
| Realtime 工具调用 | `internal/provider/openai/gateway_protocol.go:568` 到 `internal/provider/openai/gateway_protocol.go:638` | 模型知道 `workspace_list_files/read_file/write_file` 工具 schema | 工具描述允许写完整文件内容，没有确认机制或安全策略字段 |

## 关键缺口矩阵

| 编号 | 问题 | 证据 | 影响 | 确认后修复方向 |
| --- | --- | --- | --- | --- |
| P0-WS-01 | 模型工具可直接写磁盘 | `internal/provider/openai/tool_execution.go:376` 到 `internal/provider/openai/tool_execution.go:383` 直接调用 `workspace.Write()` | 模型误判、提示注入或中转篡改工具调用可直接改项目文件 | `workspace_write_file` 改为只创建 pending write，返回 diff 和 `pending_write_id` |
| P0-WS-02 | HTTP 写接口也直接写磁盘 | `internal/handler/workspace_handler.go:44` 到 `internal/handler/workspace_handler.go:56` | 前端或任意已鉴权调用方可直接覆盖文件 | 增加 `/api/workspace/write/preview`、`/api/workspace/write/confirm`、`/api/workspace/write/reject` |
| P0-WS-03 | 没有用户确认 | `web/chat.html:853` 到 `web/chat.html:866` 只在写入后刷新目录和文件 | 用户看到的是“已完成”，不是“待确认” | 前端展示 diff，提供“应用修改”和“拒绝修改”按钮 |
| P0-WS-04 | 没有审计日志 | 仓库未发现 `workspace_write_audit` 实现；`docs/reviews/2026-06-06-monitoring-log-audit-matrix.md` 仅列出目标事件 | 无法追踪谁、何时、为什么改了哪个文件 | 写 `workspace_write_preview`、`workspace_write_confirmed`、`workspace_write_rejected`、`workspace_write_failed` 到按天日志 |
| P0-WS-05 | 没有回滚快照 | `workspace.Write()` 当前只 `os.WriteFile`，没有保存 before 内容 | 写错后无法从系统审计记录恢复 | 确认写入前保存 before、after、diff_hash、rollback_ref |
| P1-WS-06 | skippedDirs 只影响列表，不阻止直接访问 | `internal/service/workspace/workspace.go:17` 到 `internal/service/workspace/workspace.go:20` 只在 `List()` 中跳过目录 | 调用方知道路径时仍可读写 `.git`、`vendor`、`logs` 等目录 | 增加 path policy：禁止写 `.git`、日志、依赖、构建产物、密钥类文件 |
| P1-WS-07 | 没有文本/二进制判断 | `internal/service/workspace/workspace.go:135` 直接 `os.ReadFile`，`internal/service/workspace/workspace.go:160` 直接写字节 | 可能破坏二进制文件或非 UTF-8 文件 | 写前检测文本类型/编码；默认只允许 UTF-8 文本 |
| P1-WS-08 | 工具 schema 使用英文且缺少安全约束 | `internal/provider/openai/gateway_protocol.go:617` 到 `internal/provider/openai/gateway_protocol.go:632`、`web/chat.html:532` 到 `web/chat.html:539` | 模型无法从 schema 得到“只创建预览，不直接落盘”的契约 | 工具描述改为中文并明确 pending diff、用户确认和禁止敏感文件 |
| P1-WS-09 | 测试当前证明“会直接写入” | `internal/provider/openai/tool_execution_test.go:130` 到 `internal/provider/openai/tool_execution_test.go:173` | 现有测试锁定了高风险行为，后续修复需要重写测试 | 先写“确认开启时不会直接写文件”的红绿测试，再改实现 |
| P1-WS-10 | Workspace 写入未纳入 stats/monitor | `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md` 仅列目标字段 | 不能统计日/周/月文件写入次数、拒绝次数、失败次数 | stats 聚合 `workspace_write_pending/confirmed/rejected/failed` |

## 目标安全流程

确认进入修复阶段后，建议把文件修改链路改成：

1. 模型或前端提交写入意图：`project_id`、`path`、`after_content`、来源 `source=model|manual`。
2. 服务端读取当前文件作为 `before_content`，生成 diff、diff hash、pending id。
3. 服务端只保存 pending write，不写磁盘。
4. 前端展示 diff、文件路径、风险提示、调用来源和模型 response id。
5. 用户点击确认后，服务端再次校验路径、文件版本和 pending 状态。
6. 写入前保存 rollback snapshot，写入后记录 audit log。
7. 用户拒绝或 pending 过期时记录 reject/expired。

## 建议新增数据结构

| 字段 | 含义 |
| --- | --- |
| `pending_write_id` | 待确认写入唯一 ID |
| `project_id` | 项目 ID，当前阶段只有 `current` |
| `path` | 项目相对路径 |
| `before` / `after` | 写入前后内容，日志中只记录 hash 和摘要 |
| `diff` | 展示给用户确认的文本 diff |
| `source` | `model`、`manual_editor`、`api` |
| `response_id` | 触发工具调用的 OpenAI response ID |
| `user_id` / `user_name` | 操作用户 |
| `status` | `pending`、`confirmed`、`rejected`、`expired`、`failed` |
| `rollback_ref` | 回滚快照引用 |

## 验收口径

| 目标 | 必须提供的证据 |
| --- | --- |
| 模型不能直接写文件 | 开启 `workspace_write_confirm` 后，`workspace_write_file` 只返回 pending id 和 diff，磁盘文件不变 |
| 用户确认后才写 | 调用 confirm API 后文件才变化，reject API 后文件不变 |
| 路径安全 | 测试覆盖绝对路径、`..` 逃逸、跳过目录、敏感文件、超大文件、二进制文件 |
| 审计可追溯 | 当天日志包含 preview/confirm/reject/fail 事件，含 user_id、path、diff_hash、rollback_ref |
| 可回滚 | 写入后可以用 rollback_ref 恢复 before 内容 |
| 前端可用 | `web/chat.html` 能展示 diff、确认、拒绝、失败状态和刷新后的文件内容 |

## 当前结论

当前 Workspace 文件修改能力不满足生产安全边界。它具备基本路径逃逸保护和大小限制，但缺少用户确认、pending diff、审计日志、回滚快照、敏感路径策略、文本类型校验和统计聚合。该问题应保持 P0，等待用户确认后按实施计划 Task 5 修复。
