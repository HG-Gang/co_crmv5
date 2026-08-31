# 源码中文注释覆盖文件级清单

审查日期：2026-06-06

当前阶段：只审查并固化证据，不修改业务代码。

## 总体结论

当前项目还不能满足“当前所有文件的项目代码必须全部使用中文详细注释其逻辑含义和参数的含义及功能作用”。本轮按当前工作区重新扫描 `cmd`、`conf`、`internal`、`pkg`、`web` 下的 Go、HTML、JS、CSS、YAML/YML 文件，得到：

| 指标 | 数值 |
| --- | ---: |
| 扫描源码文件数 | 68 |
| 扫描总行数 | 18479 |
| 注释行数 | 1386 |
| 中文注释行数 | 1228 |

说明：这些数字只用于定位覆盖缺口，不代表注释质量。HTML 页面里很多中文是可见文案，不是代码注释，因此 `ChineseAnyLines` 高但 `ChineseCommentLines` 可能为 0。

## 扫描命令

本轮使用 PowerShell 读取 UTF-8 文件，按扩展名粗略统计注释行：

```powershell
$roots = @('cmd','conf','internal','pkg','web')
$exts = @('.go','.html','.js','.css','.yaml','.yml')
$files = Get-ChildItem -Path $roots -Recurse -File | Where-Object { $exts -contains $_.Extension.ToLowerInvariant() }
```

乱码扫描排除 `docs`，因为审查文档会记录 mojibake 模式本身，直接扫描 `docs` 会自引用命中：

```powershell
rg -n "锛|鍙|鏃|閰|鈥|乣|乄|丱|丷|丟|丠|乬|亃|ï¿½|Ã|Â|â€" cmd conf internal pkg web README.md
```

## 零中文注释文件

这些文件中文注释行数为 0，应优先进入后续补齐计划。

### 2026-06-07 P1 关闭记录

以下 P1 项已补中文说明或测试目标说明，不再按“零中文注释文件”处理：

| 文件 | 本轮处理 |
| --- | --- |
| `web/chat.html` | 已补页面状态、Workspace 工具契约、上游中转 query 参数、pending 写入确认/拒绝链路说明 |
| `web/diagnostics.html` | 已补 debug/health/Redis/Web metrics 聚合、资源统计、轮询和敏感边界说明 |
| `web/theme.js` | 已补 localStorage key、主题白名单、样式注入、选择器绑定和跨标签页同步说明 |
| `internal/service/workspace/workspace.go` | 已补项目根、路径逃逸、读写大小限制、目录过滤和直接写入兼容路径说明 |
| `internal/handler/web_static_handler.go` | 已补共享主题脚本注入、旧 script 归一化和静态路径穿越防护说明 |
| `internal/handler/openai_handler_test.go` | 已补上游 query key、URL 转换、Origin 校验和容量告警测试目标说明 |
| `internal/handler/workspace_handler.go` | 已补 Workspace HTTP 入口、pending diff、确认/拒绝和审计 actor 说明 |
| `internal/handler/web_static_handler_test.go` | 已补主题脚本注入和路径穿越测试目标说明 |
| `conf/loader_test.go` | 已补模型覆盖、环境变量 key 优先级和生产配置校验测试目标说明 |
| `internal/provider/openai/client_ws_test.go` | 已补恢复缓存、关键事件背压、OpenAI Ping 和幂等关闭测试目标说明 |

下表保留为 2026-06-06 的原始审查快照，便于追踪本轮关闭前的缺口来源。

| 文件 | 类型 | 行数 | 风险判断 | 优先级 |
| --- | --- | ---: | --- | --- |
| `web/chat.html` | HTML | 954 | 聊天看板、项目选择、Workspace 工具、文件修改链路都在这里，当前只有页面文案，没有代码注释解释状态流转 | P1 |
| `web/diagnostics.html` | HTML | 626 | 诊断看板轮询 debug/health/redis，展示监控字段，但脚本无中文注释说明字段来源和敏感边界 | P1 |
| `web/theme.js` | JS | 258 | 统一颜色模式、localStorage、样式注入和跨页面同步逻辑缺少中文说明 | P1 |
| `internal/service/workspace/workspace.go` | Go | 202 | 项目根目录解析、读写文件、路径逃逸保护和大小限制缺少中文说明 | P1 |
| `web/responses.html` | HTML | 189 | Responses 测试页脚本无注释，无法说明请求字段、费用估算和错误展示边界 | P2 |
| `internal/provider/openai/tool_execution_test.go` | Go test | 173 | 测试覆盖天气、地图、Workspace 写入，但没有说明防回归目标 | P2 |
| `web/azure.html` | HTML | 171 | Azure 监控页脚本无注释，无法说明状态字段来源和密钥脱敏边界 | P2 |
| `internal/handler/web_static_handler.go` | Go | 89 | 主题脚本注入和路径穿越防护缺少中文说明 | P1 |
| `internal/handler/openai_handler_test.go` | Go test | 72 | 当前测试锁定 query 上游 key 覆盖行为，缺少风险说明 | P1 |
| `internal/handler/workspace_handler.go` | Go | 63 | Workspace HTTP 写接口无中文注释，属于高风险文件修改入口 | P1 |
| `pkg/protocol/openai/server_events_test.go` | Go test | 53 | 协议事件解析测试无目标说明 | P2 |
| `internal/handler/web_static_handler_test.go` | Go test | 51 | 主题注入和路径穿越测试无中文目标说明 | P2 |
| `conf/loader_test.go` | Go test | 51 | 配置加载优先级和环境变量覆盖测试缺少中文说明 | P2 |
| `internal/provider/openai/config_test.go` | Go test | 43 | 代理配置读取测试缺少中文说明 | P2 |
| `internal/provider/openai/client_ws_test.go` | Go test | 30 | 会话恢复快照测试缺少中文说明 | P2 |
| `internal/provider/azureai/config.go` | Go | 1 | 仅 package 行，需判断是否保留占位文件 | P2 |
| `internal/provider/azureai/response.go` | Go | 1 | 仅 package 行，需判断是否保留占位文件 | P2 |
| `pkg/errors/azureai_error.go` | Go | 1 | 仅 package 行，需判断是否保留占位文件 | P2 |
| `pkg/errors/openai_errors.go` | Go | 1 | 仅 package 行，需判断是否保留占位文件 | P2 |

## 低中文注释大文件

这些文件行数较多，但中文注释行数少于 5，后续应按模块补齐关键函数、参数和边界说明。

| 文件 | 行数 | 中文注释行数 | 主要缺口 |
| --- | ---: | ---: | --- |
| `internal/provider/openai/gateway_protocol_test.go` | 407 | 2 | 测试覆盖 GA session、工具注入、响应门控，但缺少测试目标说明 |
| `internal/handler/web_metrics_handler.go` | 373 | 2 | Web metrics 内存窗口、费用估算、cached/reasoning token 和非审计边界说明不足 |
| `internal/handler/redis_handler.go` | 169 | 3 | Redis key/value/full=1 的生产风险和类型展示边界说明不足 |
| `internal/handler/openai_responses_handler.go` | 128 | 4 | Responses 网关默认参数、错误返回和 Web metrics 记录边界说明不足 |
| `internal/handler/debug_handler_test.go` | 99 | 3 | API key/proxy 脱敏测试缺少防泄露目标说明 |
| `internal/handler/web_models_handler.go` | 93 | 2 | 模型列表字段来源、endpoint/key 状态暴露边界说明不足 |

## 低比例关键文件

这些文件不是 0 注释，但中文注释占比低，且属于主目标关键链路。

| 文件 | 行数 | 中文注释行数 | 原因 |
| --- | ---: | ---: | --- |
| `internal/provider/openai/gateway_protocol.go` | 1099 | 16 | App 消息到 OpenAI Realtime 事件的转换规则复杂，工具参数和状态门控需要更详细中文说明 |
| `internal/provider/openai/tool_execution.go` | 696 | 8 | 天气、知识库、地图、Workspace 写入等工具执行边界不足，尤其是文件写入安全 |
| `web/audio.html` | 1227 | 6 | 音频采集、编码、播放、旧 Realtime 字段和连接状态流转需要中文说明 |
| `web/redis.html` | 534 | 6 | Redis key、billing daily、full=1、敏感信息展示边界需要中文说明 |
| `web/style.css` | 508 | 10 | 公共样式、主题变量、响应式布局缺少模块化说明 |
| `web/index.html` | 446 | 10 | WebSocket 测试面板表单、日志、统计和中转配置说明不足 |
| `internal/provider/azureai/client.go` | 289 | 9 | Azure HTTP/Realtime 状态、deployment、API version、错误边界说明不足 |
| `internal/provider/openairesponses/client.go` | 247 | 9 | Responses 请求构造、默认字段、usage 解析和费用估算说明不足 |
| `internal/handler/debug_handler.go` | 456 | 23 | debug 状态字段很多，需逐类说明字段来源、脱敏和生产暴露风险 |

## 建议分批补齐顺序

1. Workspace 与文件修改安全：`internal/handler/workspace_handler.go`、`internal/service/workspace/workspace.go`、`internal/provider/openai/tool_execution.go`、`web/chat.html`。
2. 生产安全和公开诊断：`cmd/server/main.go`、`conf/*.go`、`conf/*.yaml`、`internal/middleware/*.go`、`internal/handler/openai_handler.go`、`internal/handler/redis_handler.go`、`internal/handler/debug_handler.go`。
3. Realtime 主链路和协议转换：`internal/provider/openai/client_ws.go`、`internal/provider/openai/gateway_protocol.go`、`internal/provider/openai/config.go`、`internal/service/session/*`、`internal/service/metrics/metrics.go`。
4. 监控、告警、统计：现有 `web_metrics_handler.go`、`billing.go`、`redis.go`，以及后续新增 `monitor`、`alert`、`stats` 包。
5. 前端页面：`web/theme.js`、`web/diagnostics.html`、`web/redis.html`、`web/audio.html`、`web/responses.html`、`web/azure.html`、`web/index.html`。
6. 测试文件：每个关键 `*_test.go` 增加测试目标说明，明确防止哪类生产回归。

## 验收口径

| 验收项 | 证明方式 |
| --- | --- |
| 覆盖清单关闭 | 本文件中的 P1 文件全部补齐中文注释，P2 文件完成注释或删除无用占位文件 |
| 注释质量达标 | 抽查关键函数，注释解释职责、参数、状态流转、错误边界和生产风险，不逐行复述代码 |
| 编码无回归 | mojibake 扫描无未解释命中，必要时用 `Get-Content -Encoding UTF8` 确认 |
| 行为无回归 | `go test ./... -count=1` 通过 |
| 前端无回归 | 涉及 Web 页面时完成浏览器烟测，确认页面加载、主题同步、按钮事件和文本布局正常 |

## 当前判断

当前中文注释目标仍未完全完成。2026-06-07 已关闭第一批 P1 高风险入口和主题/诊断/Workspace 链路注释缺口，并新增编码防回归脚本与测试；P2 页面、占位文件和大型协议/Realtime 文件仍需后续按模块补齐。
