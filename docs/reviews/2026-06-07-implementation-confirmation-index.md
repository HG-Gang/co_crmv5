# Task 5-12 实施确认索引

日期：2026-06-07

## 目的

本文件把当前长期目标剩余修复范围收拢成一个入口索引。它不是实现代码，也不是完成声明；它只说明确认后应按什么顺序执行、每个阶段的输入文档在哪里、需要哪些验收证据，以及哪些结论当前不能下。

## 当前状态

Task 1-4 已经围绕生产安全边界推进：生产启动校验、公开路由、JWT 默认密钥、Origin 和上游 query key 策略已有代码与测试覆盖。长期目标仍未完成，原因是 Task 5-12 仍处于确认前状态。

当前不能声明：

- 不能声明单实例或当前代码已经支持百万并发。
- 不能声明 1 秒响应已被压测证明。
- 不能声明模型工具改文件已进入用户确认模式。
- 不能声明监控面板字段已全部写入按天日志。
- 不能声明钉钉过载告警已实现。
- 不能声明 day/week/month 统计图已实现。
- 不能声明全项目中文详细注释已完成。

## 确认文档索引

| 阶段 | 文档 | 作用 |
| --- | --- | --- |
| 当前总审计 | `docs/reviews/2026-06-07-active-goal-current-state-audit.md` | 把用户 8 条目标映射到当前源码证据和未完成门槛 |
| Task 5-6 | `docs/reviews/2026-06-07-task5-6-execution-confirmation.md` | Workspace pending diff/确认/拒绝/审计，Realtime 背压、关键事件和 OpenAI Ping |
| Task 7-10 | `docs/reviews/2026-06-07-task7-10-execution-confirmation.md` | monitor snapshot、按天日志、钉钉告警、day/week/month stats、诊断面板图表 |
| Task 11-12 | `docs/reviews/2026-06-07-task11-12-execution-confirmation.md` | WebSocket 压测工具、生产容量报告、中文注释和编码防回归 |
| 总实施计划 | `docs/superpowers/plans/2026-06-06-realtime-production-hardening.md` | Task 1-12 的完整阶段计划 |
| Task 5-6 细计划 | `docs/superpowers/plans/2026-06-06-task5-6-workspace-runtime-hardening.md` | Task 5-6 的 TDD 步骤、测试命令和实现边界 |

## 必须执行顺序

后续如果用户确认开始统一修复，应按以下顺序执行：

1. Task 5：Workspace 写文件改为 pending diff、确认、拒绝、审计。
2. Task 6：Realtime 生命周期、集中关闭、背压分级、关键事件投递、OpenAI Ping。
3. Task 7：monitor snapshot、真实 IP/所在地、进程资源、按天日志。
4. Task 8：钉钉过载告警、冷却、恢复通知、发送失败日志。
5. Task 9：day/week/month stats，统一 Realtime 和 Responses 用量。
6. Task 10：诊断面板图表和监控字段展示。
7. Task 11：WebSocket 压测工具和生产容量报告。
8. Task 12：中文注释补齐和编码防回归。

不能跳过 Task 5-6 直接做监控、告警或容量压测。监控和告警需要可信的连接生命周期和容量释放语义；容量压测需要长连接背压和关键事件语义先稳定。

## 可接受的用户确认语句

如果用户想一次性开始剩余修复，可以使用：

```text
确认开始执行剩余 Task 5-12，按顺序先 Task 5-6，再 Task 7-10，最后 Task 11-12，全程按 TDD 和验收清单执行。
```

如果用户只想先做第一批，可以使用：

```text
确认开始执行 Task 5-6，按计划先修 Workspace pending diff/确认/审计，再修 Realtime 长连接背压和 OpenAI Ping。
```

如果 Task 5-6 已完成并通过验证，再继续：

```text
确认开始执行 Task 7-10，按计划实现 monitor snapshot、按天日志落点、钉钉过载告警、day/week/month stats 和诊断面板图表。
```

如果 Task 5-10 已完成并通过验证，再继续：

```text
确认开始执行 Task 11-12，按计划新增 WebSocket 压测工具、生产容量报告，并补齐中文注释和编码防回归。
```

## 阶段验收门槛

每个阶段完成前至少需要：

- 先写红灯测试，并确认失败原因对应缺失能力。
- 实现最小可维护方案。
- 目标包测试通过。
- `go test ./... -count=1` 通过。
- 相关文档更新为当前证据。
- 涉及前端页面时完成页面烟测。
- 涉及日志、告警、统计时提供可检索日志事件或测试日志。

## 总目标完成门槛

长期目标只有在以下证据全部存在后才能关闭：

| 门槛 | 必须证据 |
| --- | --- |
| 生产安全 | prod 空 JWT secret 启动失败，公开敏感路由不可匿名访问，Origin 和 query key 策略有测试 |
| Workspace 安全 | 模型工具和 HTTP 写入口只产生 pending diff，确认后才写磁盘，拒绝和失败都有审计 |
| Realtime 韧性 | 连接退出释放容量，关键事件不静默丢弃，OpenAI Ping 配置有真实实现 |
| 监控日志 | monitor snapshot、用户会话、进程资源、缓存、OpenAI、错误和告警状态能写当天日志 |
| 钉钉告警 | 过载触发、恢复、冷却、签名、webhook 失败都有测试或可复现日志 |
| 统计图表 | day/week/month API 和页面图表覆盖 token、费用、错误、缓存、限流、容量、告警、Workspace |
| 容量证明 | `tools/wsload` 可运行，`docs/production-capacity.md` 包含真实压测数据、集群假设和上游配额 |
| 中文注释 | P1 注释缺口关闭，关键函数/参数/边界有中文说明，乱码扫描无未解释命中 |

## 当前下一步

当前最小可执行下一步仍是 Task 5-6。收到确认后，应从 `docs/superpowers/plans/2026-06-06-task5-6-workspace-runtime-hardening.md` 开始，按 TDD 执行第一批红灯测试，再进入实现。
