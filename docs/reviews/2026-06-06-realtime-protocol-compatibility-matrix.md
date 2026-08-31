# Realtime 协议兼容与第三方中转风险矩阵

审查日期：2026-06-06

当前状态：仍处于“先审查、列问题、定位证据”阶段。本文只固化 OpenAI Realtime WebSocket 协议兼容、Azure 兼容和第三方中转接入风险，不修改业务代码。

## 结论

当前 Go 主链路已经明显向 OpenAI Realtime GA 风格靠拢：后端会为每个 App 会话建立一条上游 Realtime WebSocket，握手使用 `Authorization: Bearer ...`，事件以 JSON 文本帧转发，二进制音频会包装成 `input_audio_buffer.append`，并且 `gateway_protocol.go` 会构造 `session.update`、`conversation.item.create`、`response.create`、`response.cancel` 等原生事件。

但仍不能判断“协议完全正确无误”，也不能把任意第三方 HTTP 中转地址直接视为可用 Realtime WebSocket 中转。当前最大的协议风险是：配置默认指向第三方中转站、前端允许通过 URL query 覆盖上游地址和 Key、`web/audio.html` 仍发送旧版 `session.update` 字段、默认模型仍是 `gpt-realtime`，而官方当前文档和示例已需要按最新 Realtime 模型与事件字段重新核对。

## 官方依据

- OpenAI Realtime WebSocket 官方文档：`https://developers.openai.com/api/docs/guides/realtime-websocket`
- OpenAI Realtime API 总览：`https://developers.openai.com/api/docs/guides/realtime`
- OpenAI 生产最佳实践：`https://developers.openai.com/api/docs/guides/production-best-practices`

说明：本轮在当前运行环境中尝试安装 OpenAI developer docs MCP 被系统拒绝，直接 `curl` 官方页面返回 403。因此本文不复制官方正文，只保留官方链接作为复核入口，并以当前仓库代码作为主要证据。

## 协议兼容矩阵

| 编号 | 协议点 | 当前实现证据 | 判断 | 风险 | 确认后建议 |
| --- | --- | --- | --- | --- | --- |
| P0-RT-01 | 上游连接形态 | `internal/provider/openai/client_ws.go:252-284` 每次会话调用 `BuildRealtimeURL()`、`BuildRealtimeHeader()` 并 `DialContext` 建立上游 WS | 形态正确但资源模型高风险 | 每个 App 会话对应一条上游连接，第三方中转和 OpenAI 配额必须能承载同等连接数 | 容量设计中明确上游连接配额、连接复用边界、实例数和压测口径 |
| P0-RT-02 | WebSocket URL 拼接 | `internal/provider/openai/config.go:133-149` 对非 Azure URL 自动补 `?model=...` | 基本符合 OpenAI Realtime URL 形态 | 第三方中转如果不支持 `?model=` 或路径不是 `/v1/realtime`，会握手失败或路由到错误接口 | 给第三方中转增加独立兼容测试：握手、`session.update`、文本、音频、工具调用、错误事件 |
| P0-RT-03 | 第三方中转默认地址 | `conf/config.yaml:57-70` 和 `conf/models/openai.yaml:8-33` 默认 `default_model: "gpt-realtime"`、`ws_url: "wss://dxb.huifei.net/v1/realtime"` | 当前默认已不是 OpenAI 官方域名 | 只能证明“配置指向中转”，不能证明中转完整兼容 OpenAI Realtime WebSocket；普通 Chat/Completions HTTP 中转不可直接当 Realtime WS 用 | 在文档和启动校验中区分“OpenAI 官方”“OpenAI 兼容 Realtime WS 中转”“普通 HTTP 中转” |
| P0-RT-04 | 鉴权 Header | `internal/provider/openai/config.go:153-167` OpenAI 使用 `Authorization: Bearer ...`，Azure 使用 `api-key` | OpenAI/Azure 分支清晰 | 第三方中转可能要求非标准 Header 或额外租户 Header；当前无 provider 级 header 配置 | 增加显式中转配置项，而不是让浏览器通过 query 注入 Key |
| P0-RT-05 | Query 传上游 Key | `internal/handler/openai_handler.go:152-170` 允许 `upstream_api_key` / `api_key` 覆盖；`web/ws-test.js:389-391`、`web/chat.html:713-717` 会拼 query | 调试方便，但生产不可接受 | Key 可能进入浏览器历史、代理日志、访问日志、错误日志；也扩大匿名调试面 | prod 禁止 query key；dev 使用显式开关；生产只读服务端配置或短期凭证 |
| P0-RT-06 | `session.update` GA 风格 | `internal/provider/openai/gateway_protocol.go:424-447` 构造 `type=realtime`、`model`、`output_modalities`、`audio.input.format`、`audio.output.format` | Go 主链路已按 GA 风格组织 | 仍需用真实 OpenAI/中转端到端验证字段名和模型是否被接受 | 建立协议冒烟测试，记录 `session.updated` 成功和失败错误体 |
| P1-RT-07 | 原生事件自动注入 session | `internal/provider/openai/gateway_protocol.go:450-479` 对首次非 `session.update` 原生事件自动 prepend `session.update` | 有助于基础配置生效 | 如果客户端本来想完全自定义 session，首次非 session 事件会被后端注入默认配置 | 文档说明行为；如进入生产，增加可配置开关或明确客户端接入约束 |
| P1-RT-08 | 旧版语音页字段 | `web/audio.html:708-721` 仍发送 `modalities`、`input_audio_format`、`output_audio_format`、顶层 `turn_detection` | 与当前 Go 主链路 GA 风格不一致 | 语音对话测试页可能在最新 OpenAI Realtime 或严格中转上失败，造成“后端不可用”的误判 | 修复阶段把 `web/audio.html` 改为与 `gateway_protocol.go` / `web/ws-test.js` 一致的 GA 结构 |
| P1-RT-09 | 聊天页文本模式 | `web/chat.html:746-790` 发送 `session.update`、`conversation.item.create`、`response.create`，并设置 `output_modalities: ["text"]` | 结构接近当前 Realtime 原生事件 | 仍允许 query 上游 Key 和模型；Workspace 写文件工具风险更高 | 先做生产安全和 Workspace pending diff，再验证聊天页协议 |
| P1-RT-10 | 二进制音频包装 | `internal/provider/openai/client_ws.go:747-759` 把二进制帧 base64 成 `input_audio_buffer.append`；`internal/provider/openai/gateway_protocol.go:222-235` 追加 `append` / `commit` | 方向正确 | 缺少端到端音频格式校验、采样率校验、空音频/超大音频保护和中转兼容证明 | 增加音频协议测试和真实中转冒烟脚本，验证 24k PCM、commit、转写和音频输出 |
| P1-RT-11 | `response.create/cancel` 状态机 | `internal/provider/openai/gateway_protocol.go:754-934` 串行化 create/cancel，处理 pending create、cancel after created、常见错误同步 | 这是必要保护 | 状态机只能覆盖已知错误码；未知中转错误、乱序事件、断线恢复仍需真实压测验证 | 加入长聊、中断、连续响应、上游重连场景的回归测试和压测指标 |
| P1-RT-12 | Server 事件兼容 | `pkg/protocol/openai/server_events.go:65-67` 同时支持 `response.output_audio.delta` 和旧版 `response.audio.delta` | 兼容性较好 | 如果第三方中转返回非官方字段或包装层，解析和 metrics 可能不完整 | 增加未知事件落日志和协议样本采集，避免静默丢字段 |
| P1-RT-13 | 默认模型 | `internal/provider/openai/config.go:40`、`conf/config.yaml:57`、`conf/models/openai.yaml:8` 默认 `gpt-realtime` | 当前可运行性取决于账号和中转支持 | 官方模型命名和默认推荐会更新，第三方中转也可能只支持特定模型别名 | 不贸然改默认模型；确认后从官方文档和中转商能力表统一升级 |
| P1-RT-14 | Azure Realtime URL | `internal/provider/openai/config.go:170-235` 同时兼容 Azure GA `/openai/v1/realtime?model=` 和 preview `/openai/realtime?api-version=&deployment=` | 兼容分支较完整 | Azure 部署名、api-version、GA/preview 风格混用时容易接错路径 | 为 Azure 分支补 URL 生成和真实握手冒烟测试，避免只靠静态拼接 |
| P2-RT-15 | 旧协议类型文件 | `pkg/protocol/openai/client_events.go:29-36`、`pkg/protocol/openai/types.go:277-286` 仍保留旧顶层 `input_audio_format` / `output_audio_format` 类型 | 有历史兼容价值 | 维护者可能误以为新链路仍应使用旧字段 | 修复阶段清理或标注旧类型用途，避免新代码继续扩散旧字段 |

## 第三方中转必须确认的问题

第三方中转地址和 Key 可以作为上游使用，但前提是它不是普通 HTTP Chat/Completions 中转，而是完整支持 OpenAI Realtime WebSocket 的中转。至少要逐项确认：

1. 是否支持 `wss://.../v1/realtime?model=...` 长连接握手。
2. 是否接受 `Authorization: Bearer <key>`，或者是否需要自定义 Header。
3. 是否完整透传 `session.update`、`conversation.item.create`、`input_audio_buffer.append`、`input_audio_buffer.commit`、`response.create`、`response.cancel`。
4. 是否完整返回 `session.created/session.updated`、`response.created`、`response.done`、`error`、`rate_limits.updated`、音频 delta 和文本 delta。
5. 是否支持当前默认模型 `gpt-realtime`，以及是否支持官方当前推荐模型或别名。
6. 是否支持函数工具调用和 Workspace 工具 schema，是否会改写工具调用事件。
7. 是否公开连接数、并发数、token、音频、限流、超时、重连和错误码配额。
8. 是否允许生产使用、是否有 SLA、日志脱敏和数据合规说明。

## 当前不进入修复的原因

用户要求“待确认后统一修改和修复”。当前阶段只应继续把协议风险和证据固化。真正修复时，应先完成生产安全 Task 1-4：JWT、公开路由、Origin、上游 Key 策略；否则新增协议测试、监控页面或中转输入框会继续扩大现有匿名调试面的风险。
