# 第一批统一修复确认单

日期：2026-06-08

## 结论

当前已经完成审查、差距矩阵和后续闭环计划。下一步如果确认进入修复，建议先执行第一批：生产 WebSocket 鉴权收紧、WebSocket 消息级限流与慢消费者断开、Realtime SLA 指标与延迟分位。

第一批不承诺百万并发，也不改完整监控统计体系。它的目标是先把生产安全和 WS 热路径风险压住，为后续监控日志、统计持久化、钉钉复合告警和容量压测打底。

## 第一批修复范围

### 1. 生产 WebSocket 鉴权收紧

目标：
- 生产默认禁止通过 URL query 传 JWT token。
- 开发环境可通过显式配置继续使用 query token，便于测试面板调试。
- 前端保留开发提示，但生产不能依赖 query token。

主要文件：
- `conf/config.go`
- `conf/config.yaml`
- `conf/config_dev.yaml`
- `conf/config_prod.yaml`
- `conf/loader.go`
- `internal/middleware/auth.go`
- `web/chat.html`
- `web/ws-test.js`

验收：
- `go test ./conf ./internal/middleware ./internal/quality -run "QueryToken|Production|ChatPage" -count=1`
- 生产配置启用 query token 时启动校验失败。
- 开发配置显式允许时，WebSocket query token 仍可用于本地调试。

### 2. WebSocket 消息级限流与慢消费者断开

目标：
- 已建立 WS 后，文本、音频、工具事件仍要被限流。
- 慢消费者连续丢弃或队列超时后主动断开，避免占住会话容量。
- 队列满、限流、慢消费者事件写入 metrics/stats，为后续告警提供数据。

主要文件：
- `conf/config.go`
- `conf/config.yaml`
- `internal/provider/openai/client_ws.go`
- `internal/service/metrics/metrics.go`
- `internal/service/stats/stats.go`

验收：
- `go test ./internal/provider/openai ./internal/service/metrics ./internal/service/stats -run "Rate|SlowConsumer|Queue" -count=1`
- 高频文本事件超过阈值时返回结构化限流错误。
- 高频音频帧超过阈值时不继续推给上游。
- 慢消费者超过阈值时关闭 App WS 和上游 WS，并释放容量。

### 3. Realtime SLA 指标与延迟分位

目标：
- 记录 App 握手、上游连接、首事件、首音频、完整响应耗时。
- metrics snapshot 输出 p50/p95/p99。
- 诊断面板展示核心延迟指标。

主要文件：
- `internal/provider/openai/client_ws.go`
- `internal/service/metrics/metrics.go`
- `internal/service/monitor/snapshot.go`
- `web/diagnostics.html`
- `internal/quality/diagnostics_page_test.go`

验收：
- `go test ./internal/service/metrics ./internal/quality -run "Latency|Diagnostics" -count=1`
- `/api/debug/status` 或 metrics snapshot 能看到首事件、首音频、完整响应的 p95/p99。
- 诊断页出现延迟指标卡片。

## 第一批不包含的内容

以下内容放到后续批次：

- 统一 audit event schema。
- day/week/month 跨实例持久化统计。
- 钉钉 warning/critical/recovered 阈值配置化。
- 诊断面板完整错误中心和多图表资源展示。
- `tools/wsload` 容量报告采样闭环。
- 全仓中文注释和 mojibake 清理。
- 真实百万并发压测。

## 第一批完成后的仍然不能声明

即使第一批完成，也不能声明：

> 当前服务已经能顶住百万并发，并且 1 秒内稳定响应。

原因：
- 第一批只解决安全和 WS 热路径基础风险。
- 仍缺真实压测、上游配额、跨实例统计、Redis/LB/OS 资源曲线和完整容量报告。

第一批完成后可以声明：

> 当前服务的生产 WebSocket 鉴权、WS 内消息级限流、慢消费者处理和 Realtime 延迟观测能力已经比当前状态更可控，可进入监控日志和统计持久化阶段。

## 建议执行方式

建议按 `docs/superpowers/plans/2026-06-08-realtime-goal-closure-plan.md` 的 Task 1 到 Task 3 执行。

执行时必须：
- 先写测试。
- 确认测试失败。
- 再改实现。
- 跑目标测试。
- 只改与第一批相关的文件。
- 不回滚用户或历史已有改动。

确认开始后，第一条执行指令可以是：

> 确认开始第一批修复，执行 Task 1-3：生产 WebSocket 鉴权、WS 消息级限流、Realtime SLA 指标。
