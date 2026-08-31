# Realtime Production Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将当前 Go + OpenAI Realtime WebSocket 网关从本地调试/中小规模雏形推进到可生产管控、可观测、可告警、可统计、可压测验证的工程状态。

**Architecture:** 先收紧生产安全边界，再修复 Realtime 长连接生命周期、背压和重连韧性，然后补齐监控、日志、告警和统计数据源，最后建立容量压测、中文注释补齐和编码防回归流程。百万并发不作为单进程承诺，必须通过多实例、负载均衡、Redis/指标分片、OpenAI/中转配额和压测报告共同证明。

**Tech Stack:** Go 1.25、Gin、Gorilla WebSocket、go-redis/v9、zap、PowerShell、HTML/CSS/原生 JavaScript。

---

## Scope Check

本目标横跨安全、Realtime 长连接、Workspace 写文件、监控、日志、钉钉告警、统计图表、压测和注释清理。执行时按阶段拆分验收；每个阶段必须能独立通过测试和人工检查，不能一次性混改。

全局完成门槛见 `docs/reviews/2026-06-06-completion-acceptance-gates.md`。测试质量缺口见 `docs/reviews/2026-06-06-test-quality-gates-readiness-matrix.md`。当前源码行号快照见 `docs/reviews/2026-06-06-current-p0-evidence-snapshot.md`。修复确认前收口索引见 `docs/reviews/2026-06-06-pre-fix-confirmation-brief.md`。执行本计划时，单个 Task 通过只代表阶段推进；只有该矩阵中的生产安全、容量证明、监控日志、钉钉告警、统计图表、Workspace 安全、中文注释和测试验证全部有当前证据支撑，才可以声明主目标完成。

## File Structure Map

- `conf/config.go`：新增生产安全、可信代理、监控、告警、统计配置结构。
- `conf/config.yaml`、`conf/config_dev.yaml`、`conf/config_prod.yaml`：提供 dev/prod 默认值，生产默认关闭公开调试能力。
- `conf/loader.go`、`conf/loader_test.go`：加载并校验生产必填配置。
- `cmd/server/main.go`：拆分路由注册、配置 trusted proxies、启动后台监控/告警/统计任务。
- `internal/middleware/auth.go`：移除默认 JWT secret 兜底，支持用户名称 claim。
- `internal/handler/openai_handler.go`、`internal/handler/azureai_handler.go`：Origin 配置化、真实 IP 采集、上游 key 传递策略收紧。
- `internal/handler/redis_handler.go`、`internal/handler/debug_handler.go`、`internal/handler/web_metrics_handler.go`：公开调试接口加保护，扩展监控字段。
- `internal/service/workspace/`：增加写文件预览、确认、审计记录。
- `internal/provider/openai/client_ws.go`：修复 App 断开后的上游连接关闭、OpenAI Ping、队列背压和重连恢复边界。
- `internal/service/monitor/`：新增进程、FD/socket、IP 地理位置、系统运行信息采集。
- `internal/service/alert/`：新增过载检测和钉钉机器人通知。
- `internal/service/stats/`：新增天/周/月资源统计聚合。
- `internal/service/metrics/metrics.go`：降低全局锁压力，补充业务缓存命中和用户维度字段。
- `internal/service/billing/billing.go`：写入日/周/月 token、音频时长、费用统计。
- `web/diagnostics.html`、`web/redis.html`、`web/chat.html`、`web/index.html`：展示新增监控、告警、统计和 Workspace 写入确认。
- `tools/wsload/`：新增 WebSocket 压测工具和容量报告模板。
- `docs/production-capacity.md`：记录容量模型、压测命令、实测结果和无法由单机证明的边界。

## Task 1: 生产安全配置与启动校验

参考审查证据：`docs/reviews/2026-06-06-production-startup-security-readiness-matrix.md`。实现时必须覆盖 prod JWT secret、公开 token/debug 路由、Origin 白名单、Trusted Proxy、上游 query key、Redis 强依赖、日志保留和启动校验日志；不要只添加配置字段而不让错误配置启动失败。

**Files:**
- Modify: `conf/config.go`
- Modify: `conf/config.yaml`
- Modify: `conf/config_dev.yaml`
- Modify: `conf/config_prod.yaml`
- Modify: `conf/loader.go`
- Test: `conf/loader_test.go`

- [ ] **Step 1: 写失败测试，生产空 JWT secret 必须报错**

在 `conf/loader_test.go` 增加测试函数：

```go
func TestValidateProductionRejectsEmptyJWTSecret(t *testing.T) {
	cfg := &GlobalConfig{}
	cfg.Env = "prod"
	cfg.JWT.Enabled = true
	cfg.JWT.Secret = ""

	err := validateProductionConfig(cfg)
	if err == nil || !strings.Contains(err.Error(), "jwt.secret") {
		t.Fatalf("validateProductionConfig error = %v, want jwt.secret error", err)
	}
}
```

- [ ] **Step 2: 运行失败测试**

Run: `go test ./conf -run TestValidateProductionRejectsEmptyJWTSecret -count=1`

Expected: 编译失败或测试失败，原因是 `validateProductionConfig` 尚未实现。

- [ ] **Step 3: 新增配置结构**

在 `GlobalConfig` 中增加：

```go
Security struct {
	PublicDebugEnabled      bool     `mapstructure:"public_debug_enabled"`
	PublicTokenEnabled      bool     `mapstructure:"public_token_enabled"`
	AllowedOrigins          []string `mapstructure:"allowed_origins"`
	TrustedProxies          []string `mapstructure:"trusted_proxies"`
	AllowUpstreamQueryKey   bool     `mapstructure:"allow_upstream_query_key"`
	WorkspaceWriteConfirm   bool     `mapstructure:"workspace_write_confirm"`
	WorkspaceAuditRetention int      `mapstructure:"workspace_audit_retention_days"`
} `mapstructure:"security"`
```

- [ ] **Step 4: 实现生产校验**

在 `conf/loader.go` 增加：

```go
func validateProductionConfig(cfg *GlobalConfig) error {
	if cfg == nil || cfg.Env != "prod" {
		return nil
	}
	if cfg.JWT.Enabled && strings.TrimSpace(cfg.JWT.Secret) == "" {
		return fmt.Errorf("prod requires jwt.secret")
	}
	if cfg.Security.PublicTokenEnabled {
		return fmt.Errorf("prod cannot enable security.public_token_enabled")
	}
	if cfg.Security.PublicDebugEnabled {
		return fmt.Errorf("prod cannot enable security.public_debug_enabled")
	}
	if len(cfg.Security.AllowedOrigins) == 0 {
		return fmt.Errorf("prod requires security.allowed_origins")
	}
	return nil
}
```

在 `Load()` 解码和环境变量替换后调用该函数。

- [ ] **Step 5: 配置 dev/prod 默认值**

`conf/config_dev.yaml`：

```yaml
security:
  public_debug_enabled: true
  public_token_enabled: true
  allowed_origins:
    - "http://127.0.0.1:8096"
    - "http://localhost:8096"
  trusted_proxies: []
  allow_upstream_query_key: true
  workspace_write_confirm: false
  workspace_audit_retention_days: 30
```

`conf/config_prod.yaml`：

```yaml
security:
  public_debug_enabled: false
  public_token_enabled: false
  allowed_origins: []
  trusted_proxies: []
  allow_upstream_query_key: false
  workspace_write_confirm: true
  workspace_audit_retention_days: 180
```

- [ ] **Step 6: 验证**

Run: `go test ./conf -count=1`

Expected: `ok TozoAI-Chat-Api/conf`。

## Task 2: 公开路由收紧与 Trusted Proxy

**Files:**
- Modify: `cmd/server/main.go`
- Modify: `internal/handler/debug_handler.go`
- Test: `internal/handler/openai_handler_test.go`

- [ ] **Step 1: 写路由保护测试**

新增测试：生产配置下 `/test/generate-token` 不应注册或返回 404/403；`/api/debug/status` 需要鉴权或明确关闭。

```go
func TestProductionDisablesPublicTokenRoute(t *testing.T) {
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Env = "prod"
	conf.Global.Security.PublicTokenEnabled = false

	router := buildRouter()
	req := httptest.NewRequest(http.MethodGet, "/test/generate-token?userId=1001", nil)
	w := httptest.NewRecorder()
	router.ServeHTTP(w, req)

	if w.Code != http.StatusNotFound && w.Code != http.StatusForbidden {
		t.Fatalf("status = %d, want 404 or 403", w.Code)
	}
}
```

- [ ] **Step 2: 抽出 `buildRouter()`**

把 `main()` 中 Gin 路由构建逻辑抽为：

```go
func buildRouter() *gin.Engine {
	r := gin.Default()
	registerRoutes(r)
	return r
}
```

`main()` 只负责配置加载、组件初始化、调用 `buildRouter()` 和启动 server。

- [ ] **Step 3: 按配置注册调试路由**

`/test/generate-token` 仅当 `conf.Global.Security.PublicTokenEnabled` 为 true 时注册。

`/api/redis/keys`、`/api/debug/status`、`/api/web/models`、`/api/web/metrics` 仅当 `PublicDebugEnabled` 为 true 时公开；生产需要放入受保护路由组。

- [ ] **Step 4: 配置 Trusted Proxy**

在 router 创建后：

```go
if len(conf.Global.Security.TrustedProxies) > 0 {
	if err := r.SetTrustedProxies(conf.Global.Security.TrustedProxies); err != nil {
		panic(fmt.Sprintf("trusted proxies 配置无效: %v", err))
	}
} else {
	_ = r.SetTrustedProxies(nil)
}
```

- [ ] **Step 5: 验证**

Run: `go test ./cmd/server ./internal/handler -count=1`

Expected: 所有测试通过。

## Task 3: JWT 默认密钥移除与用户名称采集

**Files:**
- Modify: `internal/middleware/auth.go`
- Modify: `internal/service/session/manager.go`
- Modify: `internal/service/metrics/metrics.go`
- Test: `internal/middleware/auth_test.go`

- [ ] **Step 1: 写失败测试**

```go
func TestGenerateTokenRejectsEmptySecret(t *testing.T) {
	conf.Global = &conf.GlobalConfig{}
	conf.Global.JWT.Enabled = true
	conf.Global.JWT.Secret = ""

	_, err := GenerateToken("1001")
	if err == nil || !strings.Contains(err.Error(), "jwt secret") {
		t.Fatalf("GenerateToken error = %v, want jwt secret error", err)
	}
}
```

- [ ] **Step 2: 移除默认密钥兜底**

把 `default-jwt-secret-123456` 两处替换为显式错误返回。`Auth()` 解析时 secret 为空直接返回 500，`GenerateToken()` secret 为空直接返回错误。

- [ ] **Step 3: 支持用户名 claim**

扩展 Claims：

```go
type Claims struct {
	UserID   string `json:"user_id"`
	UserName string `json:"user_name,omitempty"`
	jwt.RegisteredClaims
}
```

`Auth()` 设置 `user_name` 到 Gin context；Session 和 metrics 增加 `UserName` 字段。

- [ ] **Step 4: 验证**

Run: `go test ./internal/middleware ./internal/service/session ./internal/service/metrics -count=1`

Expected: 新增测试通过。

## Task 4: Realtime Origin 与上游 Key 策略

**Files:**
- Modify: `internal/handler/openai_handler.go`
- Modify: `internal/handler/azureai_handler.go`
- Modify: `web/index.html`
- Modify: `web/ws-test.js`
- Test: `internal/handler/openai_handler_test.go`

- [ ] **Step 1: 写 Origin 测试**

```go
func TestRealtimeOriginUsesConfiguredAllowedOrigins(t *testing.T) {
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Env = "prod"
	conf.Global.Security.AllowedOrigins = []string{"https://app.example.com"}

	req := httptest.NewRequest(http.MethodGet, "/ws/realtime/openai", nil)
	req.Header.Set("Origin", "https://evil.example.com")
	if checkRealtimeOrigin(req) {
		t.Fatalf("unexpected origin allowed")
	}
}
```

- [ ] **Step 2: 抽出 `checkRealtimeOrigin`**

`websocket.Upgrader.CheckOrigin` 调用 `checkRealtimeOrigin(r)`。dev 允许 localhost；prod 只允许配置中的 origin。

- [ ] **Step 3: 收紧上游 key query**

`realtimeConfigFromQuery()` 中，如果 `api_key`/`upstream_api_key` 存在且 `AllowUpstreamQueryKey=false`，返回错误：

```go
if apiKey != "" && !conf.Global.Security.AllowUpstreamQueryKey {
	return cfg, fmt.Errorf("upstream api key query override is disabled")
}
```

- [ ] **Step 4: 前端提示**

`web/index.html` 中标记第三方中转 key 输入框为“仅开发调试使用”；生产建议服务端配置。

- [ ] **Step 5: 验证**

Run: `go test ./internal/handler -run "Origin|RealtimeConfig" -count=1`

Expected: 所有测试通过。

## Task 5: Workspace 写文件预览、确认与审计

**Files:**
- Create: `internal/service/workspace/pending.go`
- Create: `internal/service/workspace/audit.go`
- Modify: `internal/service/workspace/workspace.go`
- Modify: `internal/provider/openai/tool_execution.go`
- Modify: `internal/handler/workspace_handler.go`
- Modify: `web/chat.html`
- Test: `internal/service/workspace/workspace_test.go`
- Test: `internal/provider/openai/tool_execution_test.go`

- [ ] **Step 1: 写失败测试**

```go
func TestWorkspaceWriteRequiresConfirmWhenEnabled(t *testing.T) {
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Security.WorkspaceWriteConfirm = true

	req, err := PreviewWrite("current", "notes/a.txt", "hello", WriteActor{UserID: "1001"})
	if err != nil {
		t.Fatalf("PreviewWrite error = %v", err)
	}
	if req.ID == "" || req.Path != "notes/a.txt" || req.Status != "pending" {
		t.Fatalf("preview = %+v", req)
	}
}
```

- [ ] **Step 2: 新增 PendingWrite 结构**

```go
type PendingWrite struct {
	ID        string `json:"id"`
	ProjectID string `json:"project_id"`
	Path      string `json:"path"`
	Before    string `json:"before"`
	After     string `json:"after"`
	Diff      string `json:"diff"`
	Status    string `json:"status"`
	UserID    string `json:"user_id"`
	UserName  string `json:"user_name"`
	CreatedAt int64  `json:"created_at"`
}
```

- [ ] **Step 3: 工具调用改为预览**

`workspace_write_file` 在 `WorkspaceWriteConfirm=true` 时只创建 pending write，返回 `pending_write_id` 和 diff，不直接写磁盘。

参考审查证据：`docs/reviews/2026-06-06-workspace-write-safety-matrix.md`。实现时需要同时覆盖模型工具写入、HTTP 写接口、前端手动保存、敏感路径策略、文本/二进制判断、审计日志、回滚快照和 stats 聚合字段。

- [ ] **Step 4: 新增确认 API**

`POST /api/workspace/write/confirm` 接收 `pending_write_id`，确认后调用原 `Write()`，并写 audit log。

- [ ] **Step 5: 前端确认**

`web/chat.html` 收到 `pending_write_id` 后显示 diff，并提供“应用修改”和“拒绝修改”按钮。

- [ ] **Step 6: 验证**

Run: `go test ./internal/service/workspace ./internal/provider/openai -run Workspace -count=1`

Expected: pending、confirm、audit 全部通过。

## Task 6: 长连接生命周期、背压和重连韧性修复

**Files:**
- Modify: `internal/provider/openai/client_ws.go`
- Modify: `internal/provider/openai/config.go`
- Modify: `internal/service/metrics/metrics.go`
- Modify: `conf/config.go`
- Modify: `conf/config.yaml`
- Test: `internal/provider/openai/client_ws_test.go`
- Test: `internal/provider/openai/gateway_protocol_test.go`

- [ ] **Step 1: 写 App 断开后快速释放测试**

构造 App 侧断开、OpenAI mock 连接阻塞读的场景，断言 `HandleWS` 在短时间内退出，不能等待 `api_read_timeout/api_pong_timeout`。

```go
func TestHandleWSClosesUpstreamWhenAppDisconnects(t *testing.T) {
	// 目标：App readPump 退出后必须主动关闭 OpenAI apiConn，
	// 让 recvPump 的 ReadMessage 立即返回，避免容量释放被拖到上游读超时。
}
```

- [ ] **Step 2: 主动关闭上游连接**

在 App 断开、`ctx.Done()` 或任一 pump 退出触发 cancel 后，集中关闭 `apiConn` 和 `appConn`，确保阻塞中的 reader 被唤醒。关闭逻辑必须保持单写约束，不能引入多个 goroutine 并发写同一个 WebSocket。

- [ ] **Step 3: 明确队列满语义**

将 `safeSend` 的丢弃策略按事件类型拆分：

- 流式音频 delta 可按策略丢弃并计数。
- `session.updated`、`response.done`、`error`、`reconnect_required`、工具调用结果等控制事件不可静默丢弃；队列满时应关闭会话或返回明确错误。
- `apiSendChan` 满时向 App 返回结构化错误，并记录队列水位。

- [ ] **Step 4: OpenAI Ping 配置与实现一致**

二选一：

1. 实现 Go→OpenAI 的 Ping ticker，使用 `GetApiPingInterval()` 定期发送 `websocket.PingMessage`，由 `PongHandler` 更新读 deadline。
2. 移除或重命名 `api_ping_interval` 配置，并在文档中明确当前只依赖读超时和上游事件检测半开连接。

生产建议优先实现 Ping ticker，并补测试覆盖 ticker 启停、写失败和 context cancel。

- [ ] **Step 5: 重连恢复边界测试**

补测试覆盖：

- 写失败后只重试一次的行为可观察。
- 重连成功后恢复 `session.update` 和最近 `conversation.item.*`。
- 正在进行的 response 被中断时，客户端收到明确 `session_restored` 或 `reconnect_required` 事件。

- [ ] **Step 6: 验证**

Run:

```powershell
go test ./internal/provider/openai -run "HandleWS|Reconnect|Queue|Ping|Restore" -count=1
go test ./internal/service/metrics -run "Queue|Reconnect|SlowConsumer" -count=1
```

Expected: App 断开释放、关键事件不静默丢弃、OpenAI Ping/重连恢复测试通过。

## Task 7: 监控数据采集与日志落点

参考审查证据：`docs/reviews/2026-06-06-monitoring-log-audit-matrix.md` 和 `docs/reviews/2026-06-06-daily-log-audit-persistence-matrix.md`。实现时不仅要写 monitor snapshot，还要覆盖跨零点日志轮换、日志清理调度、统一 `event` 字段、敏感字段脱敏、`instance_id` 和面板字段到日志字段的一一映射。

**Files:**
- Create: `internal/service/monitor/process.go`
- Create: `internal/service/monitor/ipgeo.go`
- Create: `internal/service/monitor/snapshot.go`
- Modify: `internal/handler/debug_handler.go`
- Modify: `internal/service/session/manager.go`
- Modify: `cmd/server/main.go`
- Test: `internal/service/monitor/monitor_test.go`

- [ ] **Step 1: 写进程快照测试**

```go
func TestProcessSnapshotIncludesPIDAndRuntime(t *testing.T) {
	snap := ProcessSnapshot()
	if snap.PID <= 0 {
		t.Fatalf("PID = %d, want > 0", snap.PID)
	}
	if snap.GoVersion == "" || snap.Goroutines <= 0 {
		t.Fatalf("snapshot missing runtime fields: %+v", snap)
	}
}
```

- [ ] **Step 2: 实现 ProcessSnapshot**

字段包含：PID、Go version、OS、Arch、CPU、goroutines、内存、GC、启动时间、运行时长、FD/handle 数量（不支持的平台返回 `-1` 并带 `unsupported`）。

- [ ] **Step 3: 实现 IP 地理位置接口**

`ResolveIP(ip string)` 先处理内网/本机，再读取可配置 HTTP provider；provider 未配置时返回 `{country:"未配置", region:"未配置", city:"未配置"}` 并在监控面板提示。

- [ ] **Step 4: 日志落点**

新增后台任务每 30 秒记录一次 monitor snapshot 到 `global-YYYY-MM-DD.log`，字段包括在线人数、内存、goroutines、Redis、OpenAI、错误总数、告警状态。

- [ ] **Step 5: 验证**

Run: `go test ./internal/service/monitor ./internal/handler -count=1`

Expected: 监控快照和 debug handler 测试通过。

## Task 8: 钉钉过载告警

**Files:**
- Create: `internal/service/alert/dingtalk.go`
- Create: `internal/service/alert/manager.go`
- Modify: `conf/config.go`
- Modify: `conf/config.yaml`
- Modify: `cmd/server/main.go`
- Modify: `internal/handler/openai_handler.go`
- Test: `internal/service/alert/alert_test.go`

- [ ] **Step 1: 写签名测试**

```go
func TestDingTalkSign(t *testing.T) {
	got := signDingTalk(1700000000000, "secret")
	if got == "" {
		t.Fatalf("sign is empty")
	}
}
```

- [ ] **Step 2: 新增配置**

```go
Alert struct {
	Enabled           bool   `mapstructure:"enabled"`
	DingTalkWebhook   string `mapstructure:"dingtalk_webhook"`
	DingTalkSecret    string `mapstructure:"dingtalk_secret"`
	CooldownSeconds   int    `mapstructure:"cooldown_seconds"`
	CapacityThreshold int    `mapstructure:"capacity_threshold_percent"`
	MemoryThresholdMB uint64 `mapstructure:"memory_threshold_mb"`
	ErrorThreshold     uint64 `mapstructure:"error_threshold_per_minute"`
} `mapstructure:"alert"`
```

- [ ] **Step 3: 实现告警管理器**

告警状态包含 `normal`、`firing`、`recovering`。同一告警在 cooldown 内不重复发；指标恢复后发送恢复通知。

参考审查证据：`docs/reviews/2026-06-06-alert-dingtalk-readiness-matrix.md`。实现时需要覆盖容量拒绝、限流激增、Redis 连续失败、OpenAI 连接/重连失败、错误率、队列压力、冷却去重、恢复通知、按天日志和诊断面板状态。

- [ ] **Step 4: 接入过载点**

在 `TryAcquireCapacity()` 失败处、Redis ping 失败、OpenAI 重连连续失败、错误率超过阈值时触发 `alert.Manager.Evaluate(snapshot)`。

- [ ] **Step 5: 验证**

Run: `go test ./internal/service/alert -count=1`

Expected: 签名、冷却、恢复通知测试通过。

## Task 9: 天/周/月统计聚合

**Files:**
- Create: `internal/service/stats/stats.go`
- Create: `internal/service/stats/period.go`
- Modify: `internal/service/billing/billing.go`
- Modify: `internal/service/metrics/metrics.go`
- Create: `internal/handler/stats_handler.go`
- Modify: `cmd/server/main.go`
- Test: `internal/service/stats/stats_test.go`

- [ ] **Step 1: 写周期 key 测试**

```go
func TestPeriodKeys(t *testing.T) {
	at := time.Date(2026, 6, 6, 10, 0, 0, 0, time.UTC)
	keys := PeriodKeys("openai", at)
	if keys.Day != "stats:day:openai:2026-06-06" {
		t.Fatalf("day key = %s", keys.Day)
	}
	if keys.Month != "stats:month:openai:2026-06" {
		t.Fatalf("month key = %s", keys.Month)
	}
}
```

- [ ] **Step 2: 写入统计**

每次 response.done、session end、capacity reject、rate limit reject、workspace write、alert firing 都写入 day/week/month hash。

参考审查证据：`docs/reviews/2026-06-06-stats-billing-cache-readiness-matrix.md`。实现时需要同时统一 Realtime billing、Responses Web metrics、业务缓存命中、Redis pool 指标和成本模型，避免只补 token daily/week/month。

- [ ] **Step 3: 查询 API**

新增 `GET /api/stats/resources?period=day|week|month&model=openai`，返回 token、连接、错误、费用、音频时长、缓存命中、告警次数。

- [ ] **Step 4: 验证**

Run: `go test ./internal/service/stats ./internal/handler -run Stats -count=1`

Expected: 周期聚合和 handler 输出通过。

## Task 10: 监控面板与图表

**Files:**
- Modify: `web/diagnostics.html`
- Modify: `web/redis.html`
- Modify: `web/chat.html`
- Modify: `web/theme.js`

- [ ] **Step 1: 增加监控区块**

`diagnostics.html` 新增：真实 IP/所在地、用户 ID/名称、PID/FD/socket、内存趋势、token 总览、缓存命中、告警状态、天/周/月统计 tab。

- [ ] **Step 2: 原生 canvas 图表**

使用已有 canvas 风格实现折线/柱状图，不引入大型前端依赖。图表数据来自 `/api/stats/resources`。

- [ ] **Step 3: 日志说明**

页面展示“监控快照已按天写入 global 日志”，并显示最后一次日志写入时间。

- [ ] **Step 4: 浏览器烟测**

Run: 启动服务后访问：

```text
http://127.0.0.1:8096/web/diagnostics.html
http://127.0.0.1:8096/web/redis.html
http://127.0.0.1:8096/web/chat.html
```

Expected: 页面无 JS 报错，主题同步正常，新增图表有空状态和有数据状态。

## Task 11: 压测工具与容量报告

**Files:**
- Create: `tools/wsload/main.go`
- Create: `tools/wsload/README.md`
- Create: `docs/production-capacity.md`

- [ ] **Step 1: 新增压测工具**

`tools/wsload` 支持参数：

```text
-url ws://127.0.0.1:8096/ws/realtime/openai
-users 1000
-ramp 30s
-duration 5m
-token <jwt>
-message "ping"
```

- [ ] **Step 2: 输出指标**

压测输出连接成功数、失败数、P50/P95/P99 首包延迟、错误码分布、断连原因、每秒消息数。

- [ ] **Step 3: 容量文档**

`docs/production-capacity.md` 明确写入：

```markdown
单进程不能承诺百万并发。百万并发验收必须包含：
- 多实例数量和每实例连接上限
- LB 配置
- OS FD/socket 参数
- Redis Cluster 容量
- OpenAI/中转配额
- 压测报告
- P95/P99 延迟和错误率
```

- [ ] **Step 4: 验证**

Run: `go run ./tools/wsload -h`

Expected: 打印参数帮助，不连接服务。

## Task 12: 中文注释补齐与编码防回归

**Files:**
- Modify: `cmd/server/main.go`
- Modify: `conf/*.go`
- Modify: `conf/*.yaml`
- Modify: `internal/handler/*.go`
- Modify: `internal/middleware/*.go`
- Modify: `internal/provider/openai/*.go`
- Modify: `internal/service/**/*.go`
- Modify: `web/*.html`
- Modify: `web/*.js`
- Modify: `*_test.go`
- Reference: `docs/commentary-cleanup.md`
- Reference: `docs/reviews/2026-06-06-chinese-commentary-encoding-readiness-matrix.md`
- Reference: `docs/reviews/2026-06-06-source-commentary-coverage-inventory.md`

- [ ] **Step 1: 编码扫描**

```powershell
rg -n "锛|鍙|鏃|閰|鈥|乣|乄|丱|丷|丟|丠|乬|亃|ï¿½|Ã|Â|â€" cmd conf internal pkg web README.md
```

Expected: 无未解释命中；如命中，必须先用 UTF-8 读取确认是真乱码还是终端显示问题。

- [ ] **Step 2: 优先补 Workspace 与 Web 静态链路注释**

先补 `internal/handler/workspace_handler.go`、`internal/service/workspace/workspace.go`、`internal/handler/web_static_handler.go`、`web/theme.js`、`web/chat.html`、`web/diagnostics.html`。

Expected: project_id、path、content、路径逃逸拦截、文件大小限制、主题注入、跨页面主题同步、Workspace 工具参数都有中文说明。

- [ ] **Step 3: 补生产安全与 Realtime 主链路注释**

围绕 JWT、Origin、真实 IP、上游 key、四协程模型、容量释放、背压、重连、OpenAI Ping、错误边界补充注释。

Expected: 注释解释边界和取舍，不逐行复述代码。

- [ ] **Step 4: 补监控、告警、统计和前端页面注释**

覆盖 monitor snapshot、DingTalk alert、day/week/month stats、Web metrics、diagnostics/redis/responses/azure/audio 页面状态流转。

Expected: 面板字段来源、日志落点、缓存命中口径、成本口径都有中文说明。

- [ ] **Step 5: 补测试目标说明**

为关键 `*_test.go` 增加中文测试目标说明，重点说明防止哪类生产回归。

Expected: 测试文件能直接看出覆盖的风险点，例如 query key、主题注入、协议事件、Workspace 写入边界。

- [ ] **Step 6: 验证**

Run:

```powershell
rg -n "锛|鍙|鏃|閰|鈥|乣|乄|丱|丷|丟|丠|乬|亃|ï¿½|Ã|Â|â€" cmd conf internal pkg web README.md
go test ./... -count=1
```

Expected: 编码扫描无未解释命中；全项目测试通过。

## Final Verification

- [ ] Run: `gofmt -w` on all modified Go files.
- [ ] Run: `go test ./... -count=1`.
- [ ] Run: `go test ./... -cover`.
- [ ] Start service in dev mode and open `/web/diagnostics.html`.
- [ ] Verify production config rejects empty JWT secret.
- [ ] Verify production routes do not expose token/debug/redis endpoints publicly.
- [ ] Verify App disconnect releases capacity quickly and does not wait for OpenAI read timeout.
- [ ] Verify slow-consumer handling does not silently drop control events.
- [ ] Verify `api_ping_interval` either has a tested OpenAI Ping implementation or is removed from config/docs.
- [ ] Verify DingTalk test webhook can send one manual test message when configured.
- [ ] Verify day/week/month stats API returns stable empty state and non-empty state after a test request.
- [ ] Verify Workspace write requires confirmation when enabled.

## Execution Notes

Start with Tasks 1-4. Do not begin Workspace, long-connection runtime fixes, alerting, stats, dashboard, or load testing until production safety tests pass. After Task 5, complete Task 6 before capacity testing because capacity release and backpressure semantics are prerequisites for trustworthy load-test results. The capacity target is not complete until `docs/production-capacity.md` contains an actual test result and explains the cluster assumptions used for the result.
