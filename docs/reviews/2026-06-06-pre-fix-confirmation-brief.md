# 统一修复确认前收口索引

审查日期：2026-06-06

当前阶段：问题、证据、顺序和验收标准已经固化到多份审查文档；按用户要求，未获得明确确认前不进入业务代码统一修复。

## 当前结论

当前 Go + OpenAI Realtime WebSocket 服务不能证明“百万并发 + 1 秒内稳定响应”。主要原因不是某一个函数写错，而是生产安全、容量模型、长连接生命周期、监控日志、Workspace 写入安全、钉钉告警、天/周/月统计、中文注释和测试质量还没有形成闭环。

现在已经具备进入统一修复阶段的审查条件：P0/P1/P2 问题已列出，当前源码行号已重新校准，实施计划已按 Task 1-12 拆分，验收门槛已定义。还缺的不是更多审查，而是用户明确确认开始修复。

## 用户 8 条目标收口状态

| 用户目标 | 当前状态 | 已固化证据 | 修复任务 |
| --- | --- | --- | --- |
| 1. 审查架构是否正确、是否能顶住百万并发和 1 秒响应 | 已审查，结论为未满足 | `docs/reviews/2026-06-06-capacity-readiness-matrix.md`、`docs/reviews/2026-06-06-runtime-resilience-backpressure-matrix.md`、`docs/reviews/2026-06-06-current-p0-evidence-snapshot.md` | Task 6、Task 11 |
| 2. 不合理地方全部列出并支持问题所在 | 第一轮已满足，后续随代码变化维护 | `docs/reviews/2026-06-06-pending-fix-backlog.md`、`docs/reviews/2026-06-06-current-p0-evidence-snapshot.md` | 全部 Task |
| 3. 待确认后统一修改和修复 | 正在遵守 | 本文件和 `docs/superpowers/plans/2026-06-06-realtime-production-hardening.md` | 先 Task 1-4 |
| 4. 所有项目代码中文详细注释逻辑、参数和功能作用 | 未满足 | `docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md`、`docs/reviews/2026-06-06-source-commentary-coverage-inventory.md` | Task 12 |
| 5. 更详细监控面板并全部写日志 | 未满足 | `docs/reviews/2026-06-06-observability-gap-matrix.md`、`docs/reviews/2026-06-06-monitoring-log-audit-matrix.md` | Task 7、Task 10 |
| 6. 日志沿用按天记录逻辑 | 基础能力已有，审计闭环未满足 | `docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md` | Task 7、Task 8、Task 9 |
| 7. 系统过载钉钉机器人通知 | 未满足 | `docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md` | Task 8 |
| 8. 天/周/月统计和多资源图表 | 未满足 | `docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md` | Task 9、Task 10 |

## 建议确认的第一阶段范围

建议用户确认后先执行 Task 1-4，不先做面板、告警、统计或压测。

原因：当前匿名公开接口、默认 JWT 密钥、query 传上游 key、Origin 硬编码和 Trusted Proxy 缺失会扩大外网暴露面。如果先扩展监控或压测，会把更多敏感数据和操作面建立在未收紧的安全边界上。

第一阶段建议范围：

1. Task 1：生产安全配置与启动校验。
2. Task 2：公开路由收紧与 Trusted Proxy。
3. Task 3：JWT 默认密钥移除与用户名称采集。
4. Task 4：Realtime Origin 与上游 Key 策略。

第一阶段不包含：

1. Workspace pending diff 与确认写入。
2. 长连接生命周期和背压重构。
3. 监控面板扩展。
4. 钉钉告警。
5. 天/周/月统计图表。
6. 压测工具和容量报告。
7. 全仓库中文注释补齐。

这些内容会在 Task 1-4 验证通过后按顺序推进，避免一次性混改。

## 第一阶段必须关闭的风险

| 风险 | 当前证据 | 第一阶段关闭条件 |
| --- | --- | --- |
| prod 空 JWT secret 启动不失败 | `internal/middleware/auth.go:37`、`internal/middleware/auth.go:140`、`conf/config_prod.yaml:17` | prod 配置校验拒绝空 secret；默认密钥回退移除；相关测试通过 |
| 匿名公开敏感调试接口 | `cmd/server/main.go:62`、`cmd/server/main.go:75`、`cmd/server/main.go:92-97` | prod 不注册或不匿名暴露 token/debug/redis/models/metrics/status 接口；路由测试通过 |
| 上游 key 通过 URL query | `internal/handler/openai_handler.go:152-153`、`web/ws-test.js:389-390`、`web/chat.html:715-716` | prod 禁止 query key；dev 显式配置才允许；日志和 API 不输出明文 key |
| Origin 白名单硬编码 | `internal/handler/openai_handler.go:36-37` | allowed origins 配置化；prod 必填；测试覆盖拒绝非法 Origin |
| 真实 IP 不可靠 | `internal/handler/openai_handler.go:72`、`internal/handler/azureai_handler.go:47`，当前未发现 `SetTrustedProxies` | 配置 trusted proxies；启动时设置 Gin trusted proxies；测试覆盖代理来源 |

## 第一阶段验收命令

确认进入修复后，每个 Task 需要先写目标测试，再改实现。第一阶段至少需要执行：

```powershell
go test ./conf -count=1
go test ./cmd/server ./internal/handler -count=1
go test ./internal/middleware ./internal/service/session ./internal/service/metrics -count=1
go test ./internal/handler -run "Origin|RealtimeConfig" -count=1
go test ./... -count=1
```

如果第一阶段改动了 Web 测试页面对上游 key 的展示或提交方式，还需要启动服务做最小浏览器烟测，确认主题同步、连接表单和错误提示仍能正常显示。

## 确认语句

进入统一修复阶段时，建议确认语句为：

```text
确认开始统一修复，先执行 Task 1-4，按测试驱动方式收紧生产安全、公开路由、JWT、Origin 和上游 Key 策略。
```

收到该类明确确认后，才能开始修改业务代码。确认前继续新增监控、告警、统计或压测代码都不符合“待确认后统一修改和修复”的约束。
