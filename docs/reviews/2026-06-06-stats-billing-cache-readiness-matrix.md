# 统计、计费与缓存命中就绪矩阵

审查日期：2026-06-06

当前状态：仍处于“先审查、列问题、定位证据”阶段。本文只审查当前 token 计费、Responses 请求指标、Redis billing 展示、业务缓存命中和天/周/月统计能力，不修改业务代码。

## 总体判断

当前项目已经有基础的 daily token/audio 计费记录，也能在进程内展示一部分实时 token 指标；Responses 测试链路还能估算一次请求费用和 cached/reasoning token。但这些能力还不能满足用户要求的“天、周、月各种资源统计和多种图表展示”，也不能作为百万并发场景下的长期统计、审计和成本分析依据。

当前核心缺口是：统一 `stats` service、week/month 聚合、统计查询 API 和业务缓存 hit/miss 字段已有进程内实现，模型配置缓存也已通过 `conf.GetModel` 接入第一个真实生产者；但还没有 Redis/DB 长期数据源、跨实例聚合、GeoIP/Workspace/响应缓存等更多业务缓存生产者、完整恢复告警事件和可长期对账的成本模型。Web metrics 只保留最近 500 条内存记录，Redis daily billing TTL 只有 32 天，Redis 未启用时 billing 会静默跳过。

## 已有能力

| 能力 | 当前证据 | 当前价值 | 限制 |
| --- | --- | --- | --- |
| Realtime response token 明细 | `internal/provider/openai/client_ws.go:858` 在 `response.done` 写 `billing.RecordTokenUsageDetail`，`internal/provider/openai/client_ws.go:863` 写进程内 metrics | 能记录 Realtime 完成响应的 input/output token | 只在完成响应时记录；与 Responses HTTP 链路未统一 |
| Audio duration daily | `internal/service/billing/billing.go:52`、`internal/service/billing/billing.go:85`、`internal/service/billing/billing.go:87` | 能按模型写每日音频总时长 | 只有 daily；TTL 32 天；没有 week/month |
| Token daily/session/response billing | `internal/service/billing/billing.go:220`、`internal/service/billing/billing.go:223`、`internal/service/billing/billing.go:240` | 能按 session、daily、daily_detail、response 记录 token 明细 | 不是统一 stats 模型；没有错误、告警、限流、容量拒绝、Workspace 写入维度 |
| 进程内业务 token 指标 | `internal/service/metrics/metrics.go:122`、`internal/service/metrics/metrics.go:123`、`internal/service/metrics/metrics.go:124`、`internal/service/metrics/metrics.go:565` | 能在 `/api/debug/status` 中展示用户、模型、日期 token 汇总 | 进程内内存，重启丢失，多实例不可聚合，只有 `TokensByDay` |
| Responses 请求指标 | `internal/handler/web_metrics_handler.go:19`、`internal/handler/web_metrics_handler.go:61`、`internal/handler/web_metrics_handler.go:74` | 能展示最近 500 条 Responses/Web 请求 | 进程内窗口，不落长期统计，不适合审计和月报 |
| Responses cached/reasoning token | `internal/handler/web_metrics_handler.go:36`、`internal/handler/web_metrics_handler.go:243`、`internal/handler/web_metrics_handler.go:249` | 能从 usage details 中提取 cached/reasoning token | 只服务 Web metrics；没有写入 day/week/month stats；cached 和 reasoning 同时扫描 input/output details，需和官方字段语义对齐 |
| Responses 费用估算 | `internal/handler/web_metrics_handler.go:256`、`internal/handler/web_metrics_handler.go:258`、`internal/handler/web_metrics_handler.go:260`、`internal/handler/web_metrics_handler.go:267` | 可以基于模型配置中的单价估算费用 | 不是统一成本模型；没有 Realtime/audio/第三方中转成本口径 |
| Redis billing 页面 | `web/redis.html:340`、`web/redis.html:341`、`web/redis.html:342`、`web/redis.html:358`、`web/redis.html:404` | 能把 billing response/daily/session key 做页面汇总 | 页面聚合依赖扫描 Redis key，不是正式统计 API；没有 week/month tab |
| Redis key 解释 | `internal/handler/redis_handler.go:148`、`internal/handler/redis_handler.go:150`、`internal/handler/redis_handler.go:151`、`internal/handler/redis_handler.go:154` | 便于调试 billing key | Redis 明细接口仍是敏感调试接口，生产不应依赖它做统计展示 |
| Redis pool hits/misses | `internal/handler/debug_handler.go:193`、`internal/handler/debug_handler.go:195`、`internal/handler/debug_handler.go:196`，`web/diagnostics.html:410` | 能展示 Redis 连接池命中/未命中 | 这是连接池命中，不是业务缓存命中；不能满足“缓存命中总量”的业务口径 |

## 关键缺口矩阵

| 编号 | 问题 | 证据 | 影响 | 确认后修复方向 |
| --- | --- | --- | --- | --- |
| P1-STATS-01 | 统一 `internal/service/stats` 仍是进程内初始实现 | `internal/service/stats/stats.go` 已存在，Realtime、Responses、容量拒绝、限流、运行错误、告警触发和 Workspace 写入已写入同一进程口径，但还没有 Redis/DB 持久化和跨实例聚合 | 进程重启和多实例仍会丢统计；业务缓存命中/未命中、告警恢复和长期月报仍缺稳定模型 | 扩展 stats service 的持久化存储、缓存统计和跨实例聚合 |
| P1-STATS-02 | week/month 只有进程内窗口，没有长期聚合 | `internal/service/stats/stats.go` 已返回 day/week/month；`internal/service/billing/billing.go:220`、`internal/service/billing/billing.go:223` 仍只写 daily billing | 重启或多实例后周报、月报不可追溯 | 写 `stats:day:*`、`stats:week:*`、`stats:month:*` 或等价数据库表 |
| P1-STATS-03 | Redis billing TTL 只有 32 天 | `internal/service/billing/billing.go:87`、`internal/service/billing/billing.go:222`、`internal/service/billing/billing.go:237`、`internal/service/billing/billing.go:258` | 月度统计边界不稳，跨月审计和长期对账会丢数据 | day/week/month stats 使用更长 TTL 或持久数据库；保留原 billing 短期调试 key |
| P1-STATS-04 | Redis 未启用时 billing 静默跳过 | `internal/service/billing/billing.go:64`、`internal/service/billing/billing.go:191` | 统计数据可能无告警丢失，面板看起来只是“没有数据” | 记录 degraded 指标和日志；生产模式按配置 fail-closed 或告警 |
| P1-STATS-05 | Realtime 和 Responses 已进入同一进程口径，但未长期化 | `internal/provider/openai/client_ws.go` 的 `response.done` 和 `internal/handler/web_metrics_handler.go` 的 Responses 记录都写入 `stats.RecordUsage` | 当前可在单实例内统一查看，但重启和多实例仍不能对账 | 将 `stats.RecordUsage` 写入 Redis/DB 长期模型 |
| P1-STATS-06 | 业务缓存命中已有 stats/API 字段和模型配置缓存生产者，仍缺更多业务来源 | `internal/service/stats/stats.go` 定义 `business_cache_hits` / `business_cache_misses`；`conf.GetModel` 通过 `model_config_cache` 写入 hit/miss；`/api/stats/resources` 和 `web/diagnostics.html` 已展示；`internal/handler/debug_handler.go` 仍只有 Redis pool hits/misses | API 口径已能区分业务缓存和 Redis 连接池，但目前真实生产者只覆盖模型配置缓存，还缺 GeoIP 缓存、Workspace 文件缓存或响应缓存等业务调用点 | 接入更多业务 cache hit/miss 生产者，并补 Redis/DB 持久化 |
| P1-STATS-07 | Web metrics 只保留最近 500 条内存记录 | `internal/handler/web_metrics_handler.go:19`、`internal/handler/web_metrics_handler.go:74` | 重启丢失，多实例不可聚合，不适合审计、报表和告警 | 内存记录只做调试窗口，长期数据写 stats/log/DB |
| P1-STATS-08 | 成本模型不完整 | `internal/handler/web_metrics_handler.go:256` 到 `internal/handler/web_metrics_handler.go:267` 只按 Responses usage 和配置单价估算 | 无法评估 Realtime/audio/第三方中转/缓存命中成本，百万并发商业成本无法证明 | 统一价格配置、计费模式和成本字段；按模型、供应商、模态、缓存拆分 |
| P1-STATS-09 | 统计查询 API 已有进程内实现，仍缺长期数据源 | `internal/handler/stats_handler.go` 提供 `/api/stats/resources`；`web/diagnostics.html` 已读取该接口 | API 可查询 day/week/month，但数据仍来自进程内 collector | 将 API 数据源切到 Redis/DB 聚合，并保留内存窗口作调试 |
| P1-STATS-10 | 按天统计日志事件已有初始落点，仍缺长期化 | `web_request_metric` 和 `stats_rollup` 已由 `internal/handler/web_metrics_handler.go` 发出；`internal/handler/stats_handler.go` 查询 `/api/stats/resources` 时也写 `stats_rollup` | Web/Responses 与 stats resources rollup 可追溯，业务缓存字段已进入日志事件；恢复告警、跨实例 stats 和 Redis/DB 持久化还不完整 | 扩展统一 event schema、恢复事件和持久化 stats 日志 |

## 建议统一统计字段

后续 `stats` service 至少覆盖以下字段，避免每个页面自行解释 Redis key：

| 字段 | 含义 |
| --- | --- |
| `period` | `day`、`week`、`month` |
| `model` | openai、azureai、openairesponses 或具体模型配置 |
| `user_id` / `user_name` | 用户维度；聚合总览可为空 |
| `input_tokens` / `output_tokens` / `total_tokens` | token 用量 |
| `cached_tokens` / `reasoning_tokens` | 缓存和推理 token |
| `input_audio_ms` / `output_audio_ms` | 音频时长 |
| `responses` / `sessions` / `connections` | 请求、会话和连接数量 |
| `errors` / `rate_limit_rejected` / `capacity_rejected` | 错误、限流、容量拒绝 |
| `business_cache_hits` / `business_cache_misses` | 明确业务缓存口径后的命中/未命中 |
| `redis_pool_hits` / `redis_pool_misses` | Redis 连接池指标，和业务缓存分开 |
| `alert_firing` / `alert_recovered` | 告警触发和恢复次数 |
| `workspace_write_pending` / `workspace_write_confirmed` / `workspace_write_rejected` | Workspace 文件修改审计 |
| `estimated_cost` | 统一费用估算 |

## 与实施计划的关系

确认进入修复后，本矩阵对应实施计划 Task 9 和 Task 10，但不应先于生产安全和长连接韧性修复执行。

推荐顺序仍是：

1. Task 1-4：先收紧公开路由、JWT、Origin、上游 key。
2. Task 6：修复长连接生命周期、背压和重连韧性，确保在线人数和容量释放指标可信。
3. Task 7：新增 monitor snapshot、真实 IP/所在地、按天日志落点。
4. Task 8：新增 DingTalk 告警。
5. Task 9：新增 day/week/month stats 和统一成本/缓存统计。
6. Task 10：前端展示统计图、缓存命中、费用趋势和日志落点。

## 验收口径

| 目标 | 必须提供的证据 |
| --- | --- |
| day/week/month 聚合可用 | 单元测试证明 day/week/month key 或表名稳定；写入一次 usage 后三个周期都有数据 |
| Realtime 与 Responses 统一 | 同一用户同一模型分别走 WS 和 Responses 后，stats API 能返回合并后的 token/费用 |
| 缓存命中口径清晰 | API 同时返回 `business_cache_*` 和 `redis_pool_*`，页面文案不混用 |
| 统计可审计 | 当天日志中能查到 `stats_rollup`、`web_request_metric`、`cache_stats_snapshot` |
| 前端图表可用 | `/web/diagnostics.html` 或统计页可切换 day/week/month，空状态和非空状态都稳定展示 |
| 多实例可聚合 | 至少通过 Redis/DB 聚合证明两实例写入同一周期不会互相覆盖 |

## 当前结论

当前代码只能证明“有一部分 daily billing、进程内实时指标、进程内 day/week/month 统计 API、业务缓存 hit/miss 字段和模型配置缓存生产者”，不能证明用户要求的完整业务缓存命中总量、跨实例统计、统一费用模型和长期日志审计已经完成。该问题应保留在待修复清单中，继续按实施计划 Task 9/10 推进。

## 2026-06-07 补充：进程内 Web 统计已增加日志审计事件

本轮补齐了进程内 Responses/Web 看板统计的两个日志落点：

- 请求结束写 `web_request_metric`：由 `addWebRequestRecord` 统一发出，记录模型、状态、token、cached/reasoning、费用、延迟、endpoint、脱敏 key 和错误文本。
- 资源聚合写 `stats_rollup`：由 `WebMetricsHandler` 在构建 day/week/month 最近窗口资源统计后发出，记录 `resources.day/week/month` 汇总和时间线。
- 新增测试：`TestAddWebRequestRecordWritesAuditLogEvent`、`TestWebMetricsHandlerWritesStatsRollupAuditEvent`。

这只关闭了“进程内 Web 看板统计无日志审计事件”的一部分缺口。`P1-STATS-01`、`P1-STATS-02`、`P1-STATS-05`、`P1-STATS-07` 仍然成立，但表述需要更新：统一 `internal/service/stats` 已有进程内初始实现，Realtime 与 Responses 已合并到同一进程口径；业务缓存 hit/miss 字段已进入 stats/API/log 口径，`conf.GetModel` 已作为模型配置缓存生产者写入 hit/miss，但仍缺长期 Redis/DB 模型、跨实例聚合和更多业务缓存来源。

## 2026-06-07 补充：`internal/service/stats` 初始实现

本轮新增了统一 stats service 的第一块实现：

- `internal/service/stats/stats.go` 定义 `UsageRecord`、`ResourceSummary`、`ResourcePeriodStats` 和 `RecordUsage` / `ResourcePeriods`。
- Realtime `response.done` 通过 `stats.SourceRealtime` 写入统一统计。
- Responses/Web 调用通过 `stats.SourceResponses` 写入统一统计。
- `/api/web/metrics` 的 `charts.resources` 改为读取 `stats.ResourcePeriods`，不再只依赖 Web 最近请求窗口。
- 单元测试证明 Realtime 与 Responses 可以进入同一 day/week/month 聚合，并能按 source/model 分组。

因此，`P1-STATS-01` 的“完全没有 stats service”已经不再准确，应改为“stats service 仍是进程内初始实现，尚未持久化和跨实例聚合”。`P1-STATS-02`、`P1-STATS-05`、`P1-STATS-07` 仍未完全关闭：当前 day/week/month 是内存窗口，重启会丢失；Realtime 与 Responses 已进入同一进程口径，但还没有 Redis/DB 长期模型；多实例仍不能汇总。

## 2026-06-07 补充：`stats.RecordResourceEvent` 多资源事件

本轮继续补齐非 token 资源事件：

- `internal/service/stats/stats.go` 新增 `ResourceEvent`、`RecordResourceEvent`、资源 kind 常量和 `by_kind` 分组。
- `ResourceSummary` / `ResourceTimelinePoint` 已包含容量拒绝、限流拒绝、错误、告警触发/恢复、Workspace 写入 pending/confirmed/rejected/failed。
- `internal/service/metrics/metrics.go` 的容量拒绝、限流、OpenAI 错误、计费错误、OpenAI 上行队列超时和 App 关键事件下行队列超时已写入 stats。
- `internal/service/alert/dingtalk.go` 在钉钉过载告警成功发送后记录 `alert_firing`。
- `internal/service/workspace/audit.go` 在 Workspace 写入审计事件产生时同步写入 stats，且不依赖日志目录是否启用。

这进一步缩小了 `P1-STATS-01` 的资源覆盖缺口，但没有关闭持久化和跨实例问题；`alert_recovered` 目前只是预留统计字段，监控侧还没有真实恢复事件可写入。

## 2026-06-07 补充：独立 `/api/stats/resources`

本轮新增独立统计查询入口：

- `internal/handler/stats_handler.go` 提供 `GET /api/stats/resources?period=day|week|month&model=...&source=...&kind=...`。
- `internal/service/stats/stats.go` 新增 `ResourcePeriodsWithFilter`，支持按 source/model/kind 过滤当前进程内统计。
- `cmd/server/main.go` 注册该路由；生产环境沿用 debug route 策略，不匿名公开。
- `web/diagnostics.html` 的天/周/月资源统计图已切换到 `/api/stats/resources`，并显示容量拒绝、限流、错误、告警和 Workspace 写入。

`P1-STATS-09` 因此从“缺少 API”变为“API 只有进程内数据源”；持久化、跨实例和更多业务缓存生产者仍未关闭。
