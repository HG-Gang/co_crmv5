# 中文注释补齐与编码防回归清单

创建日期：2026-06-06

用途：落实“当前所有文件的项目代码必须全部使用中文详细注释其逻辑含义、参数含义及功能作用”的目标，同时避免把终端编码展示问题误判为源文件乱码。

## 当前扫描结论

本次以 UTF-8 读取并扫描 `cmd`、`conf`、`internal`、`web`、`README.md`，未发现以下典型 mojibake 片段：

```powershell
rg -n "锛|鍙|鏃|閰|鈥|乣|乄|丱|丷|丟|丠|乬|亃|ï¿½|Ã|Â|â€" cmd conf internal web README.md
```

当前主要问题不是源文件已经损坏，而是中文注释覆盖不均。部分核心文件已有较完整说明，部分配置、Handler 参数、WebSocket 事件、监控字段、错误边界和前端状态流转仍需补齐。

补充审查矩阵：`docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md` 已按文件级扫描列出零中文注释、低注释覆盖和编码防回归缺口。

当前文件级统计清单：`docs/reviews/2026-06-06-source-commentary-coverage-inventory.md`。后续执行 Task 12 时应先关闭该清单中的 P1 文件，再处理 P2 测试和占位文件。

## 注释规则

必须注释的内容：

- 包级职责：这个包负责什么，不负责什么，主要被谁调用。
- 入口函数：请求入口、WebSocket 入口、后台任务入口、CLI 入口。
- 配置结构：每个字段的含义、单位、默认值、生产风险。
- 协议事件：OpenAI Realtime、App WebSocket、Workspace tool 的事件含义和方向。
- 状态流转：连接、重连、恢复、限流、过载、告警、统计聚合。
- 错误边界：什么错误会返回给用户，什么错误只记日志，什么错误必须告警。
- 安全边界：API Key、JWT、Origin、真实 IP、Workspace 写文件、Redis 明细接口。

不建议注释的内容：

- 不要逐行复述代码。
- 不要给明显变量写空泛注释。
- 不要写会很快过期的实现细节，除非它解释的是边界或取舍。
- 不要在未确认阶段顺手大范围改业务代码。

## 优先级

### P0：生产安全和入口边界

这些文件影响生产暴露面，确认进入修复阶段后优先补齐注释：

- `cmd/server/main.go`：服务启动、路由分组、公开接口和受保护接口边界。
- `conf/config.go`：全局配置结构、模型配置结构、容量、JWT、限流、Fallback 字段。
- `conf/loader.go`：配置加载顺序、环境变量覆盖、模型配置合并规则。
- `conf/config.yaml`、`conf/config_dev.yaml`、`conf/config_prod.yaml`：生产和开发默认值含义。
- `internal/middleware/auth.go`：JWT token 来源、claim 字段、WebSocket query token 风险。
- `internal/middleware/rate.go`：本地限流、Redis 全局限流、降级策略。
- `internal/handler/openai_handler.go`：OpenAI Realtime 入口、Origin、真实 IP、上游覆盖参数。
- `internal/handler/azureai_handler.go`：Azure Realtime/HTTP 入口和上游配置边界。
- `internal/handler/redis_handler.go`：Redis key 明细接口、`full=1` 风险。
- `internal/handler/debug_handler.go`：诊断接口输出字段和脱敏边界。

### P0：Realtime 主链路

这些文件决定长连接正确性和性能：

- `internal/provider/openai/client_ws.go`：四协程模型、队列、心跳、重连、恢复、慢消费者处理。
- `internal/provider/openai/gateway_protocol.go`：App 消息到 OpenAI Realtime 事件的转换规则。
- `internal/provider/openai/tool_execution.go`：工具调用、Workspace 文件读写、错误返回和继续响应策略。
- `internal/provider/openai/config.go`：Realtime 超时、代理、endpoint、模型参数解析。
- `internal/provider/provider.go`：Provider 工厂和单连接临时配置覆盖。
- `internal/service/session/manager.go`：会话生命周期、Redis 元数据、计费结束点。
- `internal/service/session/capacity.go`：单实例容量计数和集群容量边界。
- `internal/service/metrics/metrics.go`：指标热路径、快照结构、裁剪策略。

### P1：监控、日志、告警、统计

这些文件或未来新增文件必须在实现时同步写中文注释：

- `internal/logger/logger.go`：按天日志文件、日志清理、模块 logger。
- `internal/service/billing/billing.go`：token、音频时长、费用、daily key。
- `internal/service/redis/redis.go`：Redis 连接池、key 前缀、弱依赖和强依赖调用。
- `internal/handler/web_metrics_handler.go`：Web 请求看板内存记录边界。
- 未来 `internal/service/monitor/`：进程、内存、FD/socket、IP 所在地快照。
- 未来 `internal/service/alert/`：钉钉签名、冷却、过载和恢复通知。
- 未来 `internal/service/stats/`：day/week/month key、聚合维度、查询 API。

### P1：Web 页面和前端状态

这些文件需要解释页面用途、主要状态、关键 API、错误显示和主题继承：

- `web/index.html`、`web/ws-test.js`、`web/style.css`：WebSocket 测试面板。
- `web/chat.html`：项目选择、Workspace 工具、聊天看板、文件修改链路。
- `web/audio.html`：语音采集、音频编码、心跳和断线处理。
- `web/diagnostics.html`：诊断看板字段和轮询逻辑。
- `web/redis.html`：Redis key、billing daily 展示、敏感信息边界。
- `web/responses.html`：Responses HTTP 测试，不占 Realtime 长连接。
- `web/azure.html`：Azure 监控和 HTTP 能力状态。
- `web/theme.js`：统一颜色模式、localStorage 同步和新增页面继承规则。

### P2：测试和文档

测试文件需要说明测试目标，而不是重复断言：

- `conf/loader_test.go`
- `internal/handler/*_test.go`
- `internal/provider/openai/*_test.go`
- 后续新增 `internal/service/*/*_test.go`
- `README.md`
- `docs/reviews/*.md`
- `docs/superpowers/plans/*.md`

## 编码防回归

每次大批量修改源码后执行。这里刻意不扫描 `docs`，因为本文和审查矩阵会记录 mojibake 模式本身，直接扫描 `docs` 会产生自引用命中：

```powershell
rg -n "锛|鍙|鏃|閰|鈥|乣|乄|丱|丷|丟|丠|乬|亃|ï¿½|Ã|Â|â€" cmd conf internal pkg web README.md
```

本轮已新增自动化门禁：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\check-commentary.ps1
```

脚本使用 ASCII 源码和 Unicode code point 构造扫描模式，避免 Windows PowerShell 5.1 把脚本自身中文字符串按本地代码页误读。对应 Go 质量测试：

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build')
go test ./internal/quality -run TestCommentaryScriptScansSourceForMojibake -count=1
```

如果命中：

1. 用 `Get-Content -Encoding UTF8` 读取文件确认源文件是否真实乱码。
2. 如果只有终端显示异常，不修改源文件，只记录验证结果。
3. 如果源文件真实乱码，按模块修复，并保留最小 diff。
4. 修复后重新运行扫描命令和相关测试。

## 验收口径

本目标不能只靠“扫描无乱码”判定完成。完成条件是：

- 本次新增和修改文件均为 UTF-8。
- 关键生产安全、Realtime 主链路、监控、告警、统计、Workspace 写入相关代码都有中文说明。
- 配置字段说明包含单位、默认值和生产风险。
- Web 页面脚本说明主要状态和 API 调用。
- 编码扫描无未解释命中。
- `go test ./... -count=1` 通过。

## 2026-06-07 执行记录

已补齐第一批 P1 高风险注释和编码门禁：

- `internal/handler/workspace_handler.go`：补 Workspace HTTP 入口、pending diff、确认/拒绝和审计身份说明。
- `internal/service/workspace/workspace.go`：补项目根目录、路径逃逸、大小限制、目录过滤和直接写入兼容边界。
- `internal/handler/web_static_handler.go`：补 `/web` 静态页面主题脚本注入和路径穿越防护说明。
- `internal/handler/web_metrics_handler.go`：补 Web 请求窗口、费用估算、day/week/month 资源统计和非长期审计边界。
- `web/theme.js`：补颜色模式 localStorage、样式注入、选择器绑定和跨页面 storage 同步说明。
- `web/chat.html`：补项目状态、Workspace 工具契约、WebSocket 上游调试参数、pending 写入确认链路说明。
- `web/diagnostics.html`：补 debug/health/Redis/Web metrics 聚合、资源统计口径和轮询边界说明。
- 关键测试文件补中文防回归目标：`conf/loader_test.go`、`internal/handler/openai_handler_test.go`、`internal/handler/web_static_handler_test.go`、`internal/handler/workspace_handler_test.go`、`internal/provider/openai/client_ws_test.go`。

仍未声明“全项目注释全部完成”：P2 页面、占位文件和部分大型 Realtime/协议转换文件仍需后续按模块补齐。
