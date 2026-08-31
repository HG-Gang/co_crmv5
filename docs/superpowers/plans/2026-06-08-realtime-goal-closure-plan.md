# Realtime Goal Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将当前 Go + OpenAI Realtime WebSocket 服务从“已有基础能力但未闭环”推进到可按证据验收的生产安全、可观测、可告警、可统计、可压测状态。

**Architecture:** 先关闭生产安全和 WebSocket 运行时风险，再补齐 SLA 指标、按天审计日志和跨实例统计入口，最后扩展面板、容量报告和中文注释治理。百万并发不由单进程承诺，必须由多实例拓扑、上游配额、Redis/日志聚合和真实压测报告共同证明。

**Tech Stack:** Go 1.25、Gin、Gorilla WebSocket、go-redis/v9、zap、PowerShell、HTML/CSS/原生 JavaScript。

---

## Scope Check

本计划基于 2026-06-08 当前状态，只覆盖剩余缺口，不重复实现已经存在的主题同步、基础 monitor snapshot、基础 stats service、基础钉钉通知和 AI 项目助手页面。执行前必须先读取：

- `docs/reviews/2026-06-08-realtime-current-architecture-audit.md`
- `docs/reviews/2026-06-08-active-goal-gap-matrix.md`
- `docs/production-capacity.md`

如果当前源码和本文行号不一致，以源码为准重新定位。每个任务必须先写或补测试，再改实现，再运行目标验证。

## File Structure Map

- `conf/config.go`：补充 WebSocket 鉴权策略、WS 消息级限流、SLA、过载阈值、stats 持久化配置。
- `conf/config.yaml`、`conf/config_dev.yaml`、`conf/config_prod.yaml`：提供开发和生产默认值；生产默认禁用 query token/key。
- `conf/loader.go`、`conf/loader_test.go`：生产配置校验继续作为启动安全 gate。
- `internal/middleware/auth.go`：限制生产 query token，支持 WebSocket 安全握手凭据。
- `internal/handler/openai_handler.go`、`internal/handler/azureai_handler.go`：接入 WS 鉴权策略、消息级限流上下文、SLA trace 初始化。
- `internal/provider/openai/client_ws.go`：补 WS 内消息限流、慢消费者断开、关键事件投递策略、SLA 指标。
- `internal/service/metrics/metrics.go`：拆分热路径计数，补延迟分位、错误率、队列指标。
- `internal/service/monitor/`：配置化过载阈值，补 Redis/OpenAI/错误率/p99 告警信号。
- `internal/service/alert/dingtalk.go`：补告警等级和更完整消息字段。
- `internal/service/stats/`：增加可替换的持久化接口，保留进程内实现作为开发默认。
- `internal/logger/`：统一 audit event schema 和敏感字段脱敏。
- `web/diagnostics.html`：扩展错误中心、延迟百分位、钉钉状态、多图表切换。
- `web/chat.html`、`web/ws-test.js`：移除生产 query token 依赖，保留开发调试路径。
- `tools/wsload/`：补容量报告采样字段和示例命令。
- `docs/production-capacity.md`：记录真实压测结果；没有真实压测时必须继续声明未达成百万并发。
- `docs/commentary-cleanup.md`、`scripts/check-commentary.ps1`：继续治理中文注释和编码损坏。

## Task 1: 生产 WebSocket 鉴权策略收紧

**Files:**
- Modify: `conf/config.go`
- Modify: `conf/config.yaml`
- Modify: `conf/config_dev.yaml`
- Modify: `conf/config_prod.yaml`
- Modify: `conf/loader.go`
- Modify: `internal/middleware/auth.go`
- Modify: `web/chat.html`
- Modify: `web/ws-test.js`
- Test: `internal/middleware/auth_test.go`
- Test: `conf/loader_test.go`
- Test: `internal/quality/chat_page_test.go`

- [ ] **Step 1: 写失败测试，生产禁止 query token**

在 `internal/middleware/auth_test.go` 增加测试：

```go
func TestAuthRejectsQueryTokenInProductionWhenDisabled(t *testing.T) {
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Env = "prod"
	conf.Global.JWT.Enabled = true
	conf.Global.JWT.Secret = "test-secret"
	conf.Global.Security.AllowWebSocketQueryToken = false

	token, err := GenerateTokenWithUserName("1001", "张三")
	if err != nil {
		t.Fatalf("GenerateTokenWithUserName error = %v", err)
	}

	router := gin.New()
	router.Use(Auth())
	router.GET("/ws/realtime/openai", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"ok": true})
	})

	req := httptest.NewRequest(http.MethodGet, "/ws/realtime/openai?token="+url.QueryEscape(token), nil)
	w := httptest.NewRecorder()
	router.ServeHTTP(w, req)

	if w.Code != http.StatusUnauthorized {
		t.Fatalf("status = %d, want 401", w.Code)
	}
}
```

- [ ] **Step 2: 写失败测试，开发允许 query token**

在同一文件增加：

```go
func TestAuthAllowsQueryTokenInDevWhenEnabled(t *testing.T) {
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Env = "dev"
	conf.Global.JWT.Enabled = true
	conf.Global.JWT.Secret = "test-secret"
	conf.Global.Security.AllowWebSocketQueryToken = true

	token, err := GenerateTokenWithUserName("1001", "张三")
	if err != nil {
		t.Fatalf("GenerateTokenWithUserName error = %v", err)
	}

	router := gin.New()
	router.Use(Auth())
	router.GET("/ws/realtime/openai", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"user_id": c.GetString("user_id")})
	})

	req := httptest.NewRequest(http.MethodGet, "/ws/realtime/openai?token="+url.QueryEscape(token), nil)
	w := httptest.NewRecorder()
	router.ServeHTTP(w, req)

	if w.Code != http.StatusOK {
		t.Fatalf("status = %d body=%s, want 200", w.Code, w.Body.String())
	}
}
```

- [ ] **Step 3: 运行失败测试**

Run: `go test ./internal/middleware -run "QueryToken" -count=1`

Expected: FAIL，原因是配置字段或策略尚未存在。

- [ ] **Step 4: 增加配置字段**

在 `conf/config.go` 的 `Security` 结构中增加：

```go
AllowWebSocketQueryToken bool `mapstructure:"allow_websocket_query_token"` // 是否允许 WebSocket 通过 URL query 传 JWT；生产默认 false。
```

`conf/config_dev.yaml`：

```yaml
security:
  allow_websocket_query_token: true
```

`conf/config_prod.yaml`：

```yaml
security:
  allow_websocket_query_token: false
```

- [ ] **Step 5: 生产校验禁止 query token**

在 `validateProductionConfig` 中增加：

```go
if cfg.Security.AllowWebSocketQueryToken {
	return fmt.Errorf("prod cannot enable security.allow_websocket_query_token")
}
```

- [ ] **Step 6: 修改认证逻辑**

在 `internal/middleware/auth.go` 中，只有满足以下条件才读取 `token` query：

```go
func allowQueryToken(c *gin.Context) bool {
	if conf.Global == nil {
		return false
	}
	if !conf.Global.Security.AllowWebSocketQueryToken {
		return false
	}
	path := c.Request.URL.Path
	return strings.HasPrefix(path, "/ws/")
}
```

`extractToken` 的逻辑改为优先 Authorization header；header 缺失且 `allowQueryToken(c)` 为 true 时才读取 query token。

- [ ] **Step 7: 前端保留开发路径并标注生产禁用**

`web/chat.html` 和 `web/ws-test.js` 继续允许开发连接 query token，但 UI 文案必须明确“仅开发调试”。生产页面后续接短期握手票据。

- [ ] **Step 8: 验证**

Run: `go test ./conf ./internal/middleware ./internal/quality -run "QueryToken|Production|ChatPage" -count=1`

Expected: PASS。

## Task 2: WebSocket 消息级限流和慢消费者断开

**Files:**
- Modify: `conf/config.go`
- Modify: `conf/config.yaml`
- Modify: `internal/provider/openai/client_ws.go`
- Modify: `internal/service/metrics/metrics.go`
- Test: `internal/provider/openai/client_ws_test.go`
- Test: `internal/service/metrics/metrics_test.go`

- [ ] **Step 1: 写失败测试，文本事件超过速率应返回限流错误**

在 `internal/provider/openai/client_ws_test.go` 增加针对消息级限流的单元测试。测试构造启用 `ws_message_rate_limit` 的 Client，连续发送超过阈值的 `conversation.item.create`，期望收到 `rate_limited` 或等价错误事件，并记录 metrics。

```go
func TestClientLimitsHighFrequencyTextEvents(t *testing.T) {
	cfg := testRealtimeConfig()
	cfg.Realtime.MessageRateLimitEnabled = true
	cfg.Realtime.TextEventsPerSecond = 1

	c := newTestClientWithConfig(t, cfg)
	first := []byte(`{"type":"conversation.item.create","item":{"type":"message","role":"user","content":[{"type":"input_text","text":"hello"}]}}`)
	second := []byte(`{"type":"conversation.item.create","item":{"type":"message","role":"user","content":[{"type":"input_text","text":"again"}]}}`)

	if err := c.handleAppTextMessage(context.Background(), first); err != nil {
		t.Fatalf("first message error = %v", err)
	}
	err := c.handleAppTextMessage(context.Background(), second)
	if err == nil || !strings.Contains(err.Error(), "rate") {
		t.Fatalf("second message error = %v, want rate limited", err)
	}
}
```

- [ ] **Step 2: 写失败测试，慢消费者连续超阈值应关闭会话**

新增测试：模拟 `sendChan` 满且连续触发慢消费者计数，期望 client 标记 close reason，metrics 记录 `slow_consumer_disconnects`。

- [ ] **Step 3: 运行失败测试**

Run: `go test ./internal/provider/openai ./internal/service/metrics -run "Rate|SlowConsumer" -count=1`

Expected: FAIL，原因是消息级限流和断开策略尚未完成。

- [ ] **Step 4: 增加配置**

在 Realtime 配置中增加：

```go
MessageRateLimitEnabled bool `mapstructure:"message_rate_limit_enabled"`
TextEventsPerSecond     int  `mapstructure:"text_events_per_second"`
AudioFramesPerSecond    int  `mapstructure:"audio_frames_per_second"`
ToolEventsPerSecond     int  `mapstructure:"tool_events_per_second"`
SlowConsumerMaxDrops    int  `mapstructure:"slow_consumer_max_drops"`
```

- [ ] **Step 5: 实现每连接限流器**

在 `Client` 中增加轻量 per-session limiter，不依赖 Redis 热路径。文本、音频、工具事件分别计数，超限时：

- 记录 metrics。
- 向 App 返回结构化错误事件。
- 不转发到上游。

- [ ] **Step 6: 实现慢消费者断开**

`safeSend` 或关键事件投递失败时累计慢消费者次数。超过 `SlowConsumerMaxDrops`：

- 发送最后一个 close/error 事件。
- 主动关闭 App WS 和上游 WS。
- 释放会话容量。
- 写 metrics 和 stats 资源事件。

- [ ] **Step 7: 验证**

Run: `go test ./internal/provider/openai ./internal/service/metrics -run "Rate|SlowConsumer|Queue" -count=1`

Expected: PASS。

## Task 3: Realtime SLA 指标和延迟分位

**Files:**
- Modify: `internal/service/metrics/metrics.go`
- Modify: `internal/provider/openai/client_ws.go`
- Modify: `internal/service/monitor/snapshot.go`
- Modify: `web/diagnostics.html`
- Test: `internal/service/metrics/metrics_test.go`
- Test: `internal/quality/diagnostics_page_test.go`

- [ ] **Step 1: 写失败测试，response 生命周期记录首包和完整耗时**

在 `metrics_test.go` 增加：

```go
func TestResponseLatencySnapshotIncludesFirstEventAndCompleteDuration(t *testing.T) {
	ResetForTest()
	SessionStarted("session-1", "request-1", "user-1", "张三", "device-1", "openai", "127.0.0.1", "agent")
	OpenAIResponseCreated("session-1", "resp-1")
	time.Sleep(time.Millisecond)
	OpenAITextDelta("session-1", "resp-1", "hello")
	time.Sleep(time.Millisecond)
	OpenAIResponseDoneUsage("session-1", "resp-1", "completed", ResponseTokenUsage{InputTokens: 1, OutputTokens: 1, TotalTokens: 2})

	snapshot := Snapshot()
	business := snapshot["business"].(BusinessMetrics)
	if business.FirstEventLatencyP95Ms <= 0 {
		t.Fatalf("FirstEventLatencyP95Ms = %v, want > 0", business.FirstEventLatencyP95Ms)
	}
	if business.CompleteResponseLatencyP95Ms <= 0 {
		t.Fatalf("CompleteResponseLatencyP95Ms = %v, want > 0", business.CompleteResponseLatencyP95Ms)
	}
}
```

- [ ] **Step 2: 运行失败测试**

Run: `go test ./internal/service/metrics -run Latency -count=1`

Expected: FAIL，原因是延迟分位字段尚未实现。

- [ ] **Step 3: 增加延迟聚合**

在 metrics 中保留有限窗口延迟样本：

- `connect_latency_ms`
- `upstream_connect_latency_ms`
- `first_event_latency_ms`
- `first_audio_latency_ms`
- `complete_response_latency_ms`

窗口只保留最近 N 条，避免无限增长。Snapshot 输出 p50/p95/p99。

- [ ] **Step 4: 在 Realtime 链路打点**

在 `client_ws.go`：

- App WS 接受后记录连接建立时间。
- 上游 `Connect` 成功后记录上游连接耗时。
- 第一次 text/audio/transcript delta 到达时记录首事件/首音频耗时。
- response.done 时记录完整响应耗时。

- [ ] **Step 5: 面板展示**

`web/diagnostics.html` 增加延迟卡片：

- 首事件 p50/p95/p99
- 首音频 p50/p95/p99
- 完整响应 p50/p95/p99
- 上游连接 p95

- [ ] **Step 6: 验证**

Run: `go test ./internal/service/metrics ./internal/quality -run "Latency|Diagnostics" -count=1`

Expected: PASS。

## Task 4: 统一按天审计事件 schema

**Files:**
- Create: `internal/service/audit/audit.go`
- Modify: `internal/service/monitor/snapshot.go`
- Modify: `internal/service/alert/dingtalk.go`
- Modify: `internal/service/stats/stats.go`
- Modify: `internal/service/metrics/metrics.go`
- Modify: `internal/logger/logger.go`
- Test: `internal/service/audit/audit_test.go`
- Test: `internal/logger/logger_test.go`

- [ ] **Step 1: 写失败测试，审计事件按天落盘且脱敏**

创建 `internal/service/audit/audit_test.go`：

```go
func TestWriteEventWritesDailyAuditLogAndRedactsSensitiveFields(t *testing.T) {
	root := t.TempDir()
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Logs.RootDir = root

	err := WriteEvent(context.Background(), Event{
		Name:       "token_usage",
		Provider:   "openai",
		InstanceID: "test-instance",
		Fields: map[string]any{
			"api_key": "sk-secret",
			"token":   "jwt-secret",
			"content": "OPENAI_API_KEY=secret",
		},
	})
	if err != nil {
		t.Fatalf("WriteEvent error = %v", err)
	}

	path := filepath.Join(root, "audit", "audit-"+time.Now().Format("2006-01-02")+".log")
	data, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("ReadFile error = %v", err)
	}
	text := string(data)
	for _, forbidden := range []string{"sk-secret", "jwt-secret", "OPENAI_API_KEY=secret"} {
		if strings.Contains(text, forbidden) {
			t.Fatalf("audit log leaked %q: %s", forbidden, text)
		}
	}
	if !strings.Contains(text, `"event":"token_usage"`) {
		t.Fatalf("audit log missing event: %s", text)
	}
}
```

- [ ] **Step 2: 运行失败测试**

Run: `go test ./internal/service/audit -count=1`

Expected: FAIL，原因是 audit 包尚未存在。

- [ ] **Step 3: 实现 audit 包**

`audit.Event` 字段：

- `event`
- `event_date`
- `timestamp`
- `instance_id`
- `provider`
- `source`
- `user_id`
- `user_name`
- `session_id`
- `request_id`
- `status`
- `reason`
- `fields`

`WriteEvent` 使用 `logger.WriteAuditJSON` 或等价方法写入按天 audit 日志，并统一调用脱敏 helper。

- [ ] **Step 4: 替换分散审计写法**

逐步接入：

- monitor snapshot
- dingtalk sent/failed/suppressed
- stats rollup
- token usage
- rate limit rejected
- queue timeout
- openai error
- workspace write

- [ ] **Step 5: 验证**

Run: `go test ./internal/service/audit ./internal/logger ./internal/service/alert ./internal/service/monitor ./internal/service/stats -run "Audit|Log|DingTalk|Snapshot|Stats" -count=1`

Expected: PASS。

## Task 5: 统计持久化接口和跨实例聚合入口

**Files:**
- Modify: `internal/service/stats/stats.go`
- Create: `internal/service/stats/store.go`
- Create: `internal/service/stats/memory_store.go`
- Create: `internal/service/stats/redis_store.go`
- Modify: `internal/handler/stats_handler.go`
- Modify: `conf/config.go`
- Test: `internal/service/stats/stats_test.go`
- Test: `internal/handler/stats_handler_test.go`

- [ ] **Step 1: 写失败测试，stats store 可替换**

在 `stats_test.go` 增加：

```go
func TestResourcePeriodsUseConfiguredStore(t *testing.T) {
	store := NewMemoryStore()
	SetStoreForTest(store)
	defer ResetForTest()

	store.RecordUsage(context.Background(), UsageRecord{
		Source:      SourceRealtime,
		Provider:    "openai",
		Model:       "gpt-realtime",
		Status:      "completed",
		TotalTokens: 42,
		Timestamp:   time.Now().UnixMilli(),
	})

	periods := ResourcePeriods(time.Now())
	if periods["day"].Summary.TotalTokens != 42 {
		t.Fatalf("day tokens = %d, want 42", periods["day"].Summary.TotalTokens)
	}
}
```

- [ ] **Step 2: 运行失败测试**

Run: `go test ./internal/service/stats -run Store -count=1`

Expected: FAIL，原因是 store 接口尚未存在。

- [ ] **Step 3: 定义 Store 接口**

```go
type Store interface {
	RecordUsage(ctx context.Context, record UsageRecord) error
	RecordResourceEvent(ctx context.Context, event ResourceEvent) error
	ResourcePeriods(ctx context.Context, now time.Time, filter ResourceFilter) (map[string]ResourcePeriodStats, error)
}
```

- [ ] **Step 4: 保留 MemoryStore 默认实现**

将当前进程内逻辑迁入 `MemoryStore`，默认行为保持兼容。

- [ ] **Step 5: 增加 RedisStore 骨架**

RedisStore 先实现 day/week/month bucket 写入和读取，key 带日期、provider、source、kind。Redis 不可用时按配置 fail-open 或返回错误。

- [ ] **Step 6: handler 暴露数据来源**

`/api/stats/resources` 返回：

```json
{
  "source": "memory|redis",
  "instance_id": "...",
  "periods": {}
}
```

- [ ] **Step 7: 验证**

Run: `go test ./internal/service/stats ./internal/handler -run "Stats|ResourcePeriods" -count=1`

Expected: PASS。

## Task 6: 过载告警阈值配置化和复合信号

**Files:**
- Modify: `conf/config.go`
- Modify: `conf/config.yaml`
- Modify: `conf/config_prod.yaml`
- Modify: `internal/service/monitor/overload_alert.go`
- Modify: `internal/service/alert/dingtalk.go`
- Test: `internal/service/monitor/monitor_test.go`
- Test: `internal/service/alert/dingtalk_test.go`

- [ ] **Step 1: 写失败测试，配置阈值生效**

在 `monitor_test.go` 增加：

```go
func TestAlertSnapshotOverloadUsesConfiguredThresholds(t *testing.T) {
	resetOverloadAlertStateForTest()
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Alerts.Overload.GoroutinesWarning = 10
	conf.Global.Alerts.Overload.GoroutinesCritical = 20

	var events []alert.OverloadEvent
	restore := SetOverloadNotifierForTest(func(_ context.Context, event alert.OverloadEvent) error {
		events = append(events, event)
		return nil
	})
	defer restore()

	AlertSnapshotOverload(context.Background(), Snapshot{
		Process: ProcessSnapshot{Goroutines: 25},
	})

	if len(events) != 1 || events[0].Level != alert.LevelCritical {
		t.Fatalf("events = %+v, want one critical event", events)
	}
}
```

- [ ] **Step 2: 运行失败测试**

Run: `go test ./internal/service/monitor -run ConfiguredThresholds -count=1`

Expected: FAIL，原因是配置字段或 Level 尚未存在。

- [ ] **Step 3: 增加告警配置**

配置字段：

- capacity warning/critical percent
- goroutines warning/critical
- alloc MB warning/critical
- GC CPU warning/critical
- queue usage warning/critical
- p99 latency warning/critical
- error rate warning/critical
- redis failure enabled
- openai reconnect failure enabled

- [ ] **Step 4: 增加告警等级**

`alert.OverloadEvent` 增加 `Level`，取值：

- `warning`
- `critical`
- `recovered`

- [ ] **Step 5: 接入复合信号**

`AlertSnapshotOverload` 增加：

- Redis ping failure
- OpenAI connect/reconnect failure growth
- rate limit rejected growth
- p99 latency high
- error total growth

- [ ] **Step 6: 钉钉消息补字段**

消息包含：

- instance_id
- level
- provider
- reason
- active/max/percent
- queue usage
- memory/goroutine/gc
- p99 latency
- user_id/user_name/remote_addr/ip_location
- recent error summary

- [ ] **Step 7: 验证**

Run: `go test ./internal/service/monitor ./internal/service/alert -run "Overload|DingTalk|Threshold" -count=1`

Expected: PASS。

## Task 7: 诊断面板扩展和多图表资源展示

**Files:**
- Modify: `web/diagnostics.html`
- Modify: `web/style.css`
- Modify: `internal/quality/diagnostics_page_test.go`

- [ ] **Step 1: 写失败测试，诊断页包含延迟和告警钻取元素**

在 `diagnostics_page_test.go` 增加：

```go
func TestDiagnosticsPageShowsLatencyAndAlertDrilldown(t *testing.T) {
	html := readDiagnosticsHTML(t)
	for _, want := range []string{
		`id="lat-first-event-p95"`,
		`id="lat-first-audio-p95"`,
		`id="lat-complete-p95"`,
		`id="alert-drilldown"`,
		`id="resource-chart-shape"`,
		`renderResourceChart`,
	} {
		if !strings.Contains(html, want) {
			t.Fatalf("diagnostics.html missing %q", want)
		}
	}
}
```

- [ ] **Step 2: 运行失败测试**

Run: `go test ./internal/quality -run Diagnostics -count=1`

Expected: FAIL，原因是页面尚未包含这些元素。

- [ ] **Step 3: 增加延迟卡片**

新增卡片：

- 首事件 p95
- 首音频 p95
- 完整响应 p95
- 上游连接 p95

- [ ] **Step 4: 增加告警钻取**

展示：

- 活跃告警原因
- 最近恢复
- 最近钉钉发送/失败状态
- 告警等级
- 最近错误摘要

- [ ] **Step 5: 增加资源图形状切换**

`resource-chart-shape` 支持：

- bar
- line
- area
- stacked
- grouped

实现 `renderResourceChart`，复用 stats timeline。

- [ ] **Step 6: 验证**

Run: `go test ./internal/quality -run Diagnostics -count=1`

Expected: PASS。

## Task 8: 容量报告和 wsload 采样闭环

**Files:**
- Modify: `tools/wsload/main.go`
- Modify: `tools/wsload/README.md`
- Modify: `docs/production-capacity.md`
- Test: `tools/wsload/main_test.go`

- [ ] **Step 1: 写失败测试，报告包含 debug 采样和资源曲线**

在 `tools/wsload/main_test.go` 增加：

```go
func TestReportIncludesDebugSamplesAndResourceCurves(t *testing.T) {
	report := Report{
		TotalConnections: 10,
		DebugSamples: []DebugSample{
			{Timestamp: "2026-06-08T00:00:00Z", ActiveSessions: 10, Goroutines: 50, HeapAllocMB: 20},
		},
	}
	data, err := json.Marshal(report)
	if err != nil {
		t.Fatalf("Marshal error = %v", err)
	}
	text := string(data)
	for _, want := range []string{"debug_samples", "active_sessions", "goroutines", "heap_alloc_mb"} {
		if !strings.Contains(text, want) {
			t.Fatalf("report missing %q: %s", want, text)
		}
	}
}
```

- [ ] **Step 2: 运行失败测试**

Run: `go test ./tools/wsload -run Report -count=1`

Expected: FAIL，原因是报告结构缺少采样字段。

- [ ] **Step 3: 补报告字段**

报告增加：

- debug_samples
- p50/p95/p99 connect latency
- p50/p95/p99 first event latency
- close code distribution
- error distribution
- resource curves

- [ ] **Step 4: README 增加压测阶梯**

明确：

- 10/100 开发冒烟
- 1k/5k/10k 单实例基线
- 50k/100k 单实例上限
- 多实例 + LB + Redis Cluster
- 百万并发只接受真实报告，不接受推断

- [ ] **Step 5: 更新容量报告模板**

`docs/production-capacity.md` 保留当前未达成结论，并加入待填表格：

- 环境拓扑
- 实例规格
- Redis 规格
- 上游配额
- OS 参数
- 压测命令
- 结果摘要
- 失败和瓶颈

- [ ] **Step 6: 验证**

Run: `go test ./tools/wsload -count=1`

Expected: PASS。

## Task 9: 中文注释和编码治理收口

**Files:**
- Modify: `scripts/check-commentary.ps1`
- Modify: `docs/commentary-cleanup.md`
- Modify: selected files from security/realtime/monitor/stats/alert modules
- Test: `internal/quality/commentary_test.go`

- [ ] **Step 1: 写失败测试，关键文档不得包含 mojibake 特征**

在 `commentary_test.go` 增加扫描：

```go
func TestReviewDocsDoNotContainMojibakeMarkers(t *testing.T) {
	root := projectRoot(t)
	files, err := filepath.Glob(filepath.Join(root, "docs", "reviews", "*.md"))
	if err != nil {
		t.Fatalf("Glob error = %v", err)
	}
	markers := []string{"锛", "銆", "鈥", "绋", "瑙"}
	for _, file := range files {
		data, err := os.ReadFile(file)
		if err != nil {
			t.Fatalf("ReadFile %s: %v", file, err)
		}
		text := string(data)
		for _, marker := range markers {
			if strings.Contains(text, marker) {
				t.Fatalf("%s contains mojibake marker %q", file, marker)
			}
		}
	}
}
```

- [ ] **Step 2: 运行失败测试**

Run: `go test ./internal/quality -run Mojibake -count=1`

Expected: FAIL，当前旧 review 文档可能存在乱码。

- [ ] **Step 3: 更新清理清单**

`docs/commentary-cleanup.md` 记录：

- 已修文件
- 暂缓文件
- 不适合机械注释的文件
- 每个模块中文注释验收标准

- [ ] **Step 4: 分批修核心模块注释**

优先：

- `internal/middleware/auth.go`
- `internal/handler/openai_handler.go`
- `internal/provider/openai/client_ws.go`
- `internal/service/metrics/metrics.go`
- `internal/service/monitor/overload_alert.go`
- `internal/service/stats/stats.go`
- `internal/service/alert/dingtalk.go`

注释只解释协议边界、参数含义、并发语义、错误策略和生产风险。

- [ ] **Step 5: 验证**

Run: `go test ./internal/quality -run "Commentary|Mojibake" -count=1`

Expected: PASS。

## Completion Audit

主目标只有在以下证据齐全后才可标记完成：

- 生产 query token/key 策略测试通过。
- WS 消息级限流、慢消费者断开、关键事件保障测试通过。
- Realtime SLA 指标在 metrics、monitor API 和诊断页可见。
- 统一 audit event schema 覆盖 token、缓存、错误、限流、队列、告警、workspace。
- day/week/month 统计支持跨实例数据源或明确生产聚合方式。
- 钉钉告警阈值配置化并覆盖 warning、critical、recovered。
- 诊断面板展示在线、内存、协程、token、缓存、用户/IP/所在地、OpenAI、Redis、错误、告警、统计图。
- `tools/wsload` 产出可复现报告；若没有真实百万压测，文档必须继续声明未达成。
- 中文注释和编码治理清单关闭到用户确认范围。

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-08-realtime-goal-closure-plan.md`. Two execution options:

1. Subagent-Driven (recommended) - dispatch a fresh subagent per task, review between tasks, fast iteration.
2. Inline Execution - execute tasks in this session using executing-plans, batch execution with checkpoints.

Because the user previously requested “待确认后统一修改和修复”, do not execute this plan until the user explicitly confirms the first repair batch.
