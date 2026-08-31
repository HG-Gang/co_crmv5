# Task 5-6 Workspace And Runtime Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将 Workspace 写文件改为待确认预览，并修复 Realtime 长连接的容量释放、背压和 OpenAI Ping 语义，为后续监控、告警、统计和容量压测建立可靠前提。

**Architecture:** Task 5 先把所有写文件入口统一导入 `workspace.PreviewWrite/ConfirmPendingWrite/RejectPendingWrite`，让模型工具和 HTTP API 默认只产生 diff 和 pending id。Task 6 再处理 Realtime 热路径：任一 pump 退出后主动关闭上下游连接，关键事件队列满不静默丢弃，OpenAI Ping 配置必须有实现或被移除。两项都按 TDD 执行，每个风险点先写红灯测试。

**Tech Stack:** Go 1.x, Gin, Gorilla WebSocket, Zap, Redis optional, PowerShell test commands.

---

## Current Evidence

- Workspace 模型工具直接写文件：`internal/provider/openai/tool_execution.go:376`。
- Workspace HTTP 写接口直接写文件：`internal/handler/workspace_handler.go:44`。
- Workspace 底层写入调用 `os.WriteFile`：`internal/service/workspace/workspace.go:142` 到 `internal/service/workspace/workspace.go:160`。
- `workspace_write_confirm` 配置已经存在但未接入写链路：`conf/config.go:84`。
- 每个 Realtime 会话启动 4 条主 goroutine：`internal/provider/openai/client_ws.go:343` 到 `internal/provider/openai/client_ws.go:346`。
- 上游写队列满会返回错误：`internal/provider/openai/client_ws.go:793` 到 `internal/provider/openai/client_ws.go:803`。
- App 下行队列满会丢弃消息：`internal/provider/openai/client_ws.go:1158` 到 `internal/provider/openai/client_ws.go:1171`。
- `api_ping_interval` 有配置读取，但当前未看到向 OpenAI 发送 Ping 的 ticker。

## File Structure

### Workspace

- `internal/service/workspace/pending.go`
  - 负责 pending write 的内存存储、状态流转、diff/hash、确认和拒绝。
- `internal/service/workspace/audit.go`
  - 负责 Workspace 写入预览、确认、拒绝、失败的按天审计日志。
- `internal/service/workspace/workspace.go`
  - 保留底层 `Write()`，新增文本/敏感路径校验辅助函数，避免 handler 和工具重复判断。
- `internal/handler/workspace_handler.go`
  - 新增 preview/confirm/reject API，原 `/api/workspace/write` 在确认开关开启时返回 pending。
- `internal/provider/openai/tool_execution.go`
  - `workspace_write_file` 在确认开关开启时只创建 pending，不直接落盘。
- `web/chat.html`
  - 收到 pending 后显示 diff，提供应用和拒绝按钮。

### Realtime Runtime

- `internal/provider/openai/client_ws.go`
  - 增加集中关闭连接、关键事件发送、OpenAI Ping ticker、队列满语义。
- `internal/provider/openai/config.go`
  - 保持 `GetApiPingInterval()`，必要时补默认值/注释。
- `internal/service/metrics/metrics.go`
  - 增加关键事件投递失败和 OpenAI Ping 指标。

---

## Task 5: Workspace Pending Diff, Confirm, Reject, Audit

**Files:**
- Create: `internal/service/workspace/pending.go`
- Create: `internal/service/workspace/audit.go`
- Create: `internal/service/workspace/workspace_test.go`
- Create: `internal/handler/workspace_handler_test.go`
- Modify: `internal/service/workspace/workspace.go`
- Modify: `internal/provider/openai/tool_execution.go`
- Modify: `internal/provider/openai/tool_execution_test.go`
- Modify: `internal/handler/workspace_handler.go`
- Modify: `cmd/server/main.go`
- Modify: `web/chat.html`

- [ ] **Step 1: Write failing workspace service tests**

Add `internal/service/workspace/workspace_test.go`:

```go
package workspace

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"TozoAI-Chat-Api/conf"
)

func withTempProject(t *testing.T) string {
	t.Helper()
	tempDir := t.TempDir()
	oldWD, err := os.Getwd()
	if err != nil {
		t.Fatalf("Getwd: %v", err)
	}
	if err := os.Chdir(tempDir); err != nil {
		t.Fatalf("Chdir temp dir: %v", err)
	}
	t.Cleanup(func() { _ = os.Chdir(oldWD) })
	return tempDir
}

func TestPreviewWriteCreatesPendingWithoutWritingFile(t *testing.T) {
	root := withTempProject(t)
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Security.WorkspaceWriteConfirm = true
	ResetPendingWritesForTest()

	pending, err := PreviewWrite("current", "notes/a.txt", "after\n", WriteActor{UserID: "1001", UserName: "张三", Source: "test"})
	if err != nil {
		t.Fatalf("PreviewWrite error = %v", err)
	}
	if pending.ID == "" || pending.Status != PendingStatusPending {
		t.Fatalf("pending = %+v, want id and pending status", pending)
	}
	if pending.Path != "notes/a.txt" || pending.After != "after\n" {
		t.Fatalf("pending path/content = %+v", pending)
	}
	if pending.Diff == "" || !strings.Contains(pending.Diff, "+after") {
		t.Fatalf("diff = %q, want added content", pending.Diff)
	}
	if _, err := os.Stat(filepath.Join(root, "notes", "a.txt")); !os.IsNotExist(err) {
		t.Fatalf("file was written during preview, stat err = %v", err)
	}
}

func TestConfirmPendingWriteWritesFileAndMarksConfirmed(t *testing.T) {
	root := withTempProject(t)
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Security.WorkspaceWriteConfirm = true
	ResetPendingWritesForTest()

	pending, err := PreviewWrite("current", "notes/a.txt", "confirmed\n", WriteActor{UserID: "1001"})
	if err != nil {
		t.Fatalf("PreviewWrite error = %v", err)
	}
	file, confirmed, err := ConfirmPendingWrite(pending.ID, WriteActor{UserID: "1001", UserName: "张三"})
	if err != nil {
		t.Fatalf("ConfirmPendingWrite error = %v", err)
	}
	if confirmed.Status != PendingStatusConfirmed {
		t.Fatalf("confirmed status = %s, want confirmed", confirmed.Status)
	}
	if file.Content != "confirmed\n" {
		t.Fatalf("file content = %q", file.Content)
	}
	data, err := os.ReadFile(filepath.Join(root, "notes", "a.txt"))
	if err != nil {
		t.Fatalf("ReadFile: %v", err)
	}
	if string(data) != "confirmed\n" {
		t.Fatalf("disk content = %q", string(data))
	}
}

func TestRejectPendingWriteDoesNotWriteFile(t *testing.T) {
	root := withTempProject(t)
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Security.WorkspaceWriteConfirm = true
	ResetPendingWritesForTest()

	pending, err := PreviewWrite("current", "notes/a.txt", "rejected\n", WriteActor{UserID: "1001"})
	if err != nil {
		t.Fatalf("PreviewWrite error = %v", err)
	}
	rejected, err := RejectPendingWrite(pending.ID, WriteActor{UserID: "1001"}, "user rejected")
	if err != nil {
		t.Fatalf("RejectPendingWrite error = %v", err)
	}
	if rejected.Status != PendingStatusRejected {
		t.Fatalf("rejected status = %s, want rejected", rejected.Status)
	}
	if _, err := os.Stat(filepath.Join(root, "notes", "a.txt")); !os.IsNotExist(err) {
		t.Fatalf("file was written after reject, stat err = %v", err)
	}
}

func TestPreviewWriteRejectsSensitivePath(t *testing.T) {
	withTempProject(t)
	conf.Global = &conf.GlobalConfig{}
	conf.Global.Security.WorkspaceWriteConfirm = true
	ResetPendingWritesForTest()

	_, err := PreviewWrite("current", ".env", "OPENAI_API_KEY=secret", WriteActor{UserID: "1001"})
	if err == nil || !strings.Contains(err.Error(), "sensitive path") {
		t.Fatalf("PreviewWrite error = %v, want sensitive path error", err)
	}
}
```

- [ ] **Step 2: Run service tests to verify red**

Run:

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/workspace -run "PreviewWrite|ConfirmPendingWrite|RejectPendingWrite" -count=1
```

Expected: FAIL because `WriteActor`, `PreviewWrite`, `ConfirmPendingWrite`, `RejectPendingWrite`, `PendingStatus*`, and `ResetPendingWritesForTest` do not exist.

- [ ] **Step 3: Add pending write implementation**

Create `internal/service/workspace/pending.go` with this implementation shape:

```go
package workspace

import (
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"
	"unicode/utf8"

	"github.com/google/uuid"
)

const (
	PendingStatusPending   = "pending"
	PendingStatusConfirmed = "confirmed"
	PendingStatusRejected  = "rejected"
	PendingStatusExpired   = "expired"
)

type WriteActor struct {
	UserID    string
	UserName  string
	RequestID string
	Source    string
}

type PendingWrite struct {
	ID          string `json:"id"`
	ProjectID   string `json:"project_id"`
	Path        string `json:"path"`
	Before      string `json:"before,omitempty"`
	After       string `json:"after,omitempty"`
	Diff        string `json:"diff"`
	DiffHash    string `json:"diff_hash"`
	Status      string `json:"status"`
	UserID      string `json:"user_id"`
	UserName    string `json:"user_name"`
	RequestID   string `json:"request_id,omitempty"`
	Source      string `json:"source,omitempty"`
	Reason      string `json:"reason,omitempty"`
	CreatedAt   int64  `json:"created_at"`
	UpdatedAt   int64  `json:"updated_at"`
	RollbackRef string `json:"rollback_ref,omitempty"`
}

var pendingStore = struct {
	sync.Mutex
	items map[string]*PendingWrite
}{items: make(map[string]*PendingWrite)}

func PreviewWrite(projectID, relPath, content string, actor WriteActor) (PendingWrite, error) {
	if err := validateWritableContent(relPath, content); err != nil {
		return PendingWrite{}, err
	}
	root, err := ProjectRoot(projectID)
	if err != nil {
		return PendingWrite{}, err
	}
	fullPath, displayPath, err := resolve(root, relPath)
	if err != nil {
		return PendingWrite{}, err
	}
	if strings.TrimSpace(displayPath) == "" {
		return PendingWrite{}, errors.New("path is required")
	}
	before, err := readExistingText(fullPath)
	if err != nil {
		return PendingWrite{}, err
	}
	diff := buildSimpleDiff(displayPath, before, content)
	now := time.Now().Unix()
	item := &PendingWrite{
		ID:        uuid.NewString(),
		ProjectID: defaultProjectID(projectID),
		Path:      displayPath,
		Before:    before,
		After:     content,
		Diff:      diff,
		DiffHash:  hashText(diff),
		Status:    PendingStatusPending,
		UserID:    actor.UserID,
		UserName:  actor.UserName,
		RequestID: actor.RequestID,
		Source:    actor.Source,
		CreatedAt: now,
		UpdatedAt: now,
	}
	pendingStore.Lock()
	pendingStore.items[item.ID] = item
	pendingStore.Unlock()
	AuditWorkspaceWrite("workspace_write_preview", *item, nil)
	return clonePendingWrite(item), nil
}

func ConfirmPendingWrite(id string, actor WriteActor) (FileContent, PendingWrite, error) {
	item, err := updatePendingStatus(id, PendingStatusConfirmed, actor, "")
	if err != nil {
		return FileContent{}, PendingWrite{}, err
	}
	file, err := Write(item.ProjectID, item.Path, item.After)
	if err != nil {
		item.Status = PendingStatusPending
		AuditWorkspaceWrite("workspace_write_failed", item, err)
		return FileContent{}, item, err
	}
	AuditWorkspaceWrite("workspace_write_confirmed", item, nil)
	return file, item, nil
}

func RejectPendingWrite(id string, actor WriteActor, reason string) (PendingWrite, error) {
	item, err := updatePendingStatus(id, PendingStatusRejected, actor, reason)
	if err != nil {
		return PendingWrite{}, err
	}
	AuditWorkspaceWrite("workspace_write_rejected", item, nil)
	return item, nil
}

func updatePendingStatus(id, status string, actor WriteActor, reason string) (PendingWrite, error) {
	pendingStore.Lock()
	defer pendingStore.Unlock()
	item := pendingStore.items[strings.TrimSpace(id)]
	if item == nil {
		return PendingWrite{}, fmt.Errorf("pending write not found: %s", id)
	}
	if item.Status != PendingStatusPending {
		return PendingWrite{}, fmt.Errorf("pending write is not pending: %s", item.Status)
	}
	item.Status = status
	item.UserID = firstNonEmpty(actor.UserID, item.UserID)
	item.UserName = firstNonEmpty(actor.UserName, item.UserName)
	item.RequestID = firstNonEmpty(actor.RequestID, item.RequestID)
	item.Source = firstNonEmpty(actor.Source, item.Source)
	item.Reason = reason
	item.UpdatedAt = time.Now().Unix()
	return clonePendingWrite(item), nil
}

func ResetPendingWritesForTest() {
	pendingStore.Lock()
	pendingStore.items = make(map[string]*PendingWrite)
	pendingStore.Unlock()
}

func validateWritableContent(path, content string) error {
	if len([]byte(content)) > maxWriteBytes {
		return fmt.Errorf("file too large to write through web workspace API: %d bytes", len([]byte(content)))
	}
	clean := strings.ToLower(filepath.ToSlash(strings.TrimSpace(path)))
	for _, forbidden := range []string{".env", ".env.", "id_rsa", "id_ed25519", "authorized_keys"} {
		if clean == forbidden || strings.HasSuffix(clean, "/"+forbidden) || strings.Contains(clean, "/"+forbidden+".") {
			return fmt.Errorf("sensitive path is not allowed: %s", path)
		}
	}
	if !utf8.ValidString(content) {
		return fmt.Errorf("binary content is not allowed: %s", path)
	}
	return nil
}

func readExistingText(fullPath string) (string, error) {
	info, err := os.Stat(fullPath)
	if os.IsNotExist(err) {
		return "", nil
	}
	if err != nil {
		return "", err
	}
	if info.IsDir() {
		return "", fmt.Errorf("cannot write directory: %s", fullPath)
	}
	if info.Size() > maxReadBytes {
		return "", fmt.Errorf("existing file too large to preview: %d bytes", info.Size())
	}
	data, err := os.ReadFile(fullPath)
	if err != nil {
		return "", err
	}
	if !utf8.Valid(data) {
		return "", fmt.Errorf("existing binary file is not allowed: %s", fullPath)
	}
	return string(data), nil
}

func buildSimpleDiff(path, before, after string) string {
	if before == after {
		return fmt.Sprintf("--- %s\n+++ %s\n", path, path)
	}
	var b strings.Builder
	b.WriteString("--- " + path + "\n")
	b.WriteString("+++ " + path + "\n")
	for _, line := range strings.Split(strings.TrimSuffix(before, "\n"), "\n") {
		if line != "" {
			b.WriteString("-" + line + "\n")
		}
	}
	for _, line := range strings.Split(strings.TrimSuffix(after, "\n"), "\n") {
		if line != "" {
			b.WriteString("+" + line + "\n")
		}
	}
	return b.String()
}

func hashText(value string) string {
	sum := sha256.Sum256([]byte(value))
	return hex.EncodeToString(sum[:])
}

func clonePendingWrite(item *PendingWrite) PendingWrite {
	if item == nil {
		return PendingWrite{}
	}
	return *item
}

func defaultProjectID(projectID string) string {
	if strings.TrimSpace(projectID) == "" {
		return "current"
	}
	return projectID
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return value
		}
	}
	return ""
}
```

- [ ] **Step 4: Add audit writer**

Create `internal/service/workspace/audit.go`:

```go
package workspace

import (
	"go.uber.org/zap"

	"TozoAI-Chat-Api/internal/logger"
)

func AuditWorkspaceWrite(event string, item PendingWrite, err error) {
	fields := []zap.Field{
		zap.String("event", event),
		zap.String("pending_write_id", item.ID),
		zap.String("project_id", item.ProjectID),
		zap.String("path", item.Path),
		zap.String("diff_hash", item.DiffHash),
		zap.String("status", item.Status),
		zap.String("user_id", item.UserID),
		zap.String("user_name", item.UserName),
		zap.String("request_id", item.RequestID),
		zap.String("source", item.Source),
	}
	if err != nil {
		fields = append(fields, zap.Error(err))
	}
	logger.GetModelLogger("global").Info("workspace write audit", fields...)
}
```

- [ ] **Step 5: Run service tests to verify green**

Run:

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/workspace -run "PreviewWrite|ConfirmPendingWrite|RejectPendingWrite" -count=1
```

Expected: PASS.

- [ ] **Step 6: Write failing model tool test**

Modify `internal/provider/openai/tool_execution_test.go`. Replace the direct-write expectation with a confirm-enabled preview expectation:

```go
func TestExecuteWorkspaceFunctionToolCreatesPendingWriteWhenConfirmEnabled(t *testing.T) {
	tempDir := t.TempDir()
	oldWD, err := os.Getwd()
	if err != nil {
		t.Fatalf("Getwd: %v", err)
	}
	if err := os.Chdir(tempDir); err != nil {
		t.Fatalf("Chdir temp dir: %v", err)
	}
	t.Cleanup(func() { _ = os.Chdir(oldWD) })

	conf.Global = &conf.GlobalConfig{}
	conf.Global.Security.WorkspaceWriteConfirm = true
	workspace.ResetPendingWritesForTest()

	client := &Client{cfg: NewOpenAIConfig(&conf.ModelConfig{}), gateway: newGatewayAdapter(), userID: "1001"}
	writeEvt := &protocol.ResponseFunctionCallArgumentsDoneEvent{ResponseID: "resp_1", Name: "workspace_write_file"}
	result := client.executeWorkspaceFunctionTool(context.Background(), writeEvt, map[string]any{
		"project_id": "current",
		"path":       "notes/todo.txt",
		"content":    "hello workspace",
	})

	if result.output["ok"] != true {
		t.Fatalf("write output ok = %v, want true: %+v", result.output["ok"], result.output)
	}
	if result.output["pending_write_id"] == "" {
		t.Fatalf("output = %+v, want pending_write_id", result.output)
	}
	if result.output["status"] != workspace.PendingStatusPending {
		t.Fatalf("status = %v, want pending", result.output["status"])
	}
	if _, err := os.Stat(filepath.Join(tempDir, "notes", "todo.txt")); !os.IsNotExist(err) {
		t.Fatalf("file was written by tool before confirmation, stat err = %v", err)
	}
}
```

Run:

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/provider/openai -run Workspace -count=1
```

Expected: FAIL because tool still writes directly.

- [ ] **Step 7: Modify model tool write path**

In `internal/provider/openai/tool_execution.go`, change `workspace_write_file` branch:

```go
case "workspace_write_file":
	content := stringFromAny(args["content"])
	if conf.Global != nil && conf.Global.Security.WorkspaceWriteConfirm {
		pending, err := workspace.PreviewWrite(projectID, relPath, content, workspace.WriteActor{
			UserID:  c.userID,
			Source:  "openai_tool",
			RequestID: c.sessionID,
		})
		if err != nil {
			return workspaceToolResult(evt, output, "workspace_write_preview_failed", err)
		}
		output["ok"] = true
		output["status"] = pending.Status
		output["pending_write_id"] = pending.ID
		output["diff"] = pending.Diff
		output["diff_hash"] = pending.DiffHash
		output["path"] = pending.Path
		return workspaceToolResult(evt, output, "workspace_write_pending", nil)
	}
	file, err := workspace.Write(projectID, relPath, content)
	if err != nil {
		return workspaceToolResult(evt, output, "workspace_write_failed", err)
	}
	output["ok"] = true
	output["file"] = workspaceFileOutput(file, false)
	return workspaceToolResult(evt, output, "workspace_write_ok", nil)
```

- [ ] **Step 8: Add handler tests and APIs**

Create `internal/handler/workspace_handler_test.go`:

```go
package handler

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"github.com/gin-gonic/gin"

	"TozoAI-Chat-Api/conf"
	"TozoAI-Chat-Api/internal/service/workspace"
)

func TestWorkspaceWriteHandlerReturnsPendingWhenConfirmEnabled(t *testing.T) {
	tempDir := t.TempDir()
	oldWD, _ := os.Getwd()
	_ = os.Chdir(tempDir)
	t.Cleanup(func() { _ = os.Chdir(oldWD) })

	conf.Global = &conf.GlobalConfig{}
	conf.Global.Security.WorkspaceWriteConfirm = true
	workspace.ResetPendingWritesForTest()
	gin.SetMode(gin.TestMode)

	router := gin.New()
	router.POST("/api/workspace/write", WorkspaceWriteHandler)
	router.POST("/api/workspace/write/confirm", WorkspaceWriteConfirmHandler)
	router.POST("/api/workspace/write/reject", WorkspaceWriteRejectHandler)

	body := bytes.NewBufferString(`{"project_id":"current","path":"notes/a.txt","content":"hello"}`)
	w := httptest.NewRecorder()
	router.ServeHTTP(w, httptest.NewRequest(http.MethodPost, "/api/workspace/write", body))
	if w.Code != http.StatusOK {
		t.Fatalf("write status = %d body = %s", w.Code, w.Body.String())
	}
	var payload struct {
		Code int `json:"code"`
		Data struct {
			ID string `json:"id"`
			Status string `json:"status"`
		} `json:"data"`
	}
	if err := json.Unmarshal(w.Body.Bytes(), &payload); err != nil {
		t.Fatalf("Unmarshal: %v", err)
	}
	if payload.Data.ID == "" || payload.Data.Status != workspace.PendingStatusPending {
		t.Fatalf("payload = %+v, want pending id", payload)
	}
	if _, err := os.Stat(filepath.Join(tempDir, "notes", "a.txt")); !os.IsNotExist(err) {
		t.Fatalf("file was written before confirm, err = %v", err)
	}

	confirmBody := bytes.NewBufferString(`{"pending_write_id":"` + payload.Data.ID + `"}`)
	confirmRecorder := httptest.NewRecorder()
	router.ServeHTTP(confirmRecorder, httptest.NewRequest(http.MethodPost, "/api/workspace/write/confirm", confirmBody))
	if confirmRecorder.Code != http.StatusOK {
		t.Fatalf("confirm status = %d body = %s", confirmRecorder.Code, confirmRecorder.Body.String())
	}
	data, err := os.ReadFile(filepath.Join(tempDir, "notes", "a.txt"))
	if err != nil {
		t.Fatalf("ReadFile: %v", err)
	}
	if string(data) != "hello" {
		t.Fatalf("disk content = %q", string(data))
	}
}
```

Modify `internal/handler/workspace_handler.go`:

```go
type workspaceConfirmRequest struct {
	PendingWriteID string `json:"pending_write_id"`
	Reason         string `json:"reason"`
}

func WorkspaceWriteHandler(c *gin.Context) {
	var req workspaceFileRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"code": 400, "error": err.Error()})
		return
	}
	if conf.Global != nil && conf.Global.Security.WorkspaceWriteConfirm {
		pending, err := workspace.PreviewWrite(defaultProjectID(req.ProjectID), req.Path, req.Content, workspace.WriteActor{
			UserID: c.GetString("user_id"), UserName: c.GetString("user_name"), Source: "http_api",
		})
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"code": 400, "error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"code": 200, "data": pending})
		return
	}
	file, err := workspace.Write(defaultProjectID(req.ProjectID), req.Path, req.Content)
	if err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"code": 400, "error": err.Error()})
		return
	}
	c.JSON(http.StatusOK, gin.H{"code": 200, "data": file})
}

func WorkspaceWriteConfirmHandler(c *gin.Context) {
	var req workspaceConfirmRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"code": 400, "error": err.Error()})
		return
	}
	file, pending, err := workspace.ConfirmPendingWrite(req.PendingWriteID, workspace.WriteActor{
		UserID: c.GetString("user_id"), UserName: c.GetString("user_name"), Source: "http_api",
	})
	if err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"code": 400, "error": err.Error()})
		return
	}
	c.JSON(http.StatusOK, gin.H{"code": 200, "data": gin.H{"file": file, "pending": pending}})
}

func WorkspaceWriteRejectHandler(c *gin.Context) {
	var req workspaceConfirmRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"code": 400, "error": err.Error()})
		return
	}
	pending, err := workspace.RejectPendingWrite(req.PendingWriteID, workspace.WriteActor{
		UserID: c.GetString("user_id"), UserName: c.GetString("user_name"), Source: "http_api",
	}, req.Reason)
	if err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"code": 400, "error": err.Error()})
		return
	}
	c.JSON(http.StatusOK, gin.H{"code": 200, "data": pending})
}
```

Modify `cmd/server/main.go` protected routes:

```go
auth.POST("/api/workspace/write/confirm", handler.WorkspaceWriteConfirmHandler)
auth.POST("/api/workspace/write/reject", handler.WorkspaceWriteRejectHandler)
```

- [ ] **Step 9: Update chat page pending UI**

In `web/chat.html`, when a workspace tool event contains `pending_write_id`, render a diff block with two buttons:

```javascript
function renderPendingWrite(toolOutput) {
    const pendingId = toolOutput.pending_write_id;
    if (!pendingId) return;
    const wrap = document.createElement('div');
    wrap.className = 'pending-write';
    wrap.innerHTML = `
        <div class="pending-title">文件修改待确认：${escapeHtml(toolOutput.path || '')}</div>
        <pre class="pending-diff">${escapeHtml(toolOutput.diff || '')}</pre>
        <div class="pending-actions">
            <button type="button" data-action="confirm" data-id="${escapeHtml(pendingId)}">应用修改</button>
            <button type="button" data-action="reject" data-id="${escapeHtml(pendingId)}">拒绝修改</button>
        </div>`;
    wrap.querySelector('[data-action="confirm"]').addEventListener('click', () => confirmPendingWrite(pendingId));
    wrap.querySelector('[data-action="reject"]').addEventListener('click', () => rejectPendingWrite(pendingId));
    document.getElementById('messages').appendChild(wrap);
}

async function confirmPendingWrite(pendingId) {
    await apiJSON('/api/workspace/write/confirm', {
        method: 'POST',
        body: JSON.stringify({ pending_write_id: pendingId })
    });
    appendMessage('system', '文件修改已应用。');
    await loadFiles(state.currentPath || '');
}

async function rejectPendingWrite(pendingId) {
    await apiJSON('/api/workspace/write/reject', {
        method: 'POST',
        body: JSON.stringify({ pending_write_id: pendingId, reason: 'user rejected in chat UI' })
    });
    appendMessage('system', '文件修改已拒绝。');
}
```

Expected page behavior: 工具调用后不直接刷新为“已完成写入”，而是显示 diff 并等待用户点击。

- [ ] **Step 10: Run Task 5 verification**

Run:

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/workspace ./internal/provider/openai ./internal/handler -run "Workspace|PendingWrite|ConfirmPendingWrite|RejectPendingWrite" -count=1
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./... -count=1
```

Expected: 所有测试通过。检查当天日志中能看到 `workspace_write_preview` 和 `workspace_write_confirmed` 事件。

---

## Task 6: Realtime Lifecycle, Backpressure, Ping

**Files:**
- Create: `internal/provider/openai/client_ws_test.go`
- Modify: `internal/provider/openai/client_ws.go`
- Modify: `internal/service/metrics/metrics.go`
- Modify: `internal/provider/openai/config.go`

- [ ] **Step 1: Write failing queue classification tests**

Create `internal/provider/openai/client_ws_test.go`:

```go
package openai

import (
	"strings"
	"testing"
	"time"

	"TozoAI-Chat-Api/conf"
	"TozoAI-Chat-Api/pkg/response"
)

func testClientForQueue(size int) *Client {
	cfg := &conf.ModelConfig{}
	cfg.Realtime.SendQueueTimeoutMs = 1
	c := NewClient(cfg)
	c.sendChan = make(chan []byte, size)
	c.apiSendChan = make(chan openAIOutbound, size)
	return c
}

func TestSendAppEventRejectsCriticalEventWhenQueueFull(t *testing.T) {
	c := testClientForQueue(1)
	c.sendChan <- []byte("blocked")
	err := c.sendAppEvent([]byte(`{"event":"end"}`), appEventPolicy{Critical: true, Name: "response.done"})
	if err == nil || !strings.Contains(err.Error(), "critical app event queue full") {
		t.Fatalf("sendAppEvent error = %v, want critical queue full error", err)
	}
}

func TestSendAppEventAllowsDroppableEventWhenQueueFull(t *testing.T) {
	c := testClientForQueue(1)
	c.sendChan <- []byte("blocked")
	err := c.sendAppEvent([]byte(`{"event":"audio.delta"}`), appEventPolicy{Critical: false, Name: "audio_delta"})
	if err != nil {
		t.Fatalf("sendAppEvent error = %v, want nil for droppable event", err)
	}
}

func TestForwardCriticalToAppReturnsError(t *testing.T) {
	c := testClientForQueue(1)
	c.sendChan <- []byte("blocked")
	resp := response.NewResponse(500, response.EventError, "must deliver")
	err := c.forwardCriticalToApp(resp, "test_error")
	if err == nil {
		t.Fatalf("forwardCriticalToApp error = nil, want queue full error")
	}
}

func TestSendAppEventFastPath(t *testing.T) {
	c := testClientForQueue(1)
	err := c.sendAppEvent([]byte("ok"), appEventPolicy{Critical: true, Name: "response.done"})
	if err != nil {
		t.Fatalf("sendAppEvent error = %v", err)
	}
	select {
	case got := <-c.sendChan:
		if string(got) != "ok" {
			t.Fatalf("got = %q", string(got))
		}
	case <-time.After(time.Second):
		t.Fatalf("sendChan did not receive message")
	}
}
```

Run:

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/provider/openai -run "SendAppEvent|ForwardCritical" -count=1
```

Expected: FAIL because `appEventPolicy`, `sendAppEvent`, and `forwardCriticalToApp` do not exist.

- [ ] **Step 2: Implement app event queue policy**

In `internal/provider/openai/client_ws.go`, add:

```go
type appEventPolicy struct {
	Name     string
	Critical bool
}

func (c *Client) sendAppEvent(data []byte, policy appEventPolicy) error {
	metrics.QueueDepth(c.sessionID, len(c.sendChan), cap(c.sendChan), len(c.apiSendChan), cap(c.apiSendChan))
	select {
	case c.sendChan <- append([]byte(nil), data...):
		metrics.QueueDepth(c.sessionID, len(c.sendChan), cap(c.sendChan), len(c.apiSendChan), cap(c.apiSendChan))
		return nil
	case <-time.After(c.cfg.GetSendQueueTimeout()):
		if policy.Critical {
			metrics.AppCriticalQueueTimeout(c.sessionID, policy.Name, len(data))
			return fmt.Errorf("critical app event queue full: %s", policy.Name)
		}
		c.log.Warn("sendChan 已满，丢弃非关键消息",
			zap.String("event", policy.Name),
			zap.Int("data_len", len(data)),
			zap.Int("chan_cap", cap(c.sendChan)),
			zap.Duration("waited", c.cfg.GetSendQueueTimeout()))
		metrics.SlowConsumerDrop(c.sessionID, len(data))
		return nil
	}
}

func (c *Client) forwardCriticalToApp(resp *response.StandardResponse, name string) error {
	data, err := resp.ToJSON()
	if err != nil {
		return err
	}
	return c.sendAppEvent(data, appEventPolicy{Name: name, Critical: true})
}
```

Modify call sites:

- Audio delta/text delta can use non-critical policy only if the event is safe to lose.
- `response.done`、`error`、`reconnect_required`、`session_restored`、workspace tool result must use critical policy.

- [ ] **Step 3: Add metric for critical queue timeout**

In `internal/service/metrics/metrics.go`, add a counter to `goMetrics`:

```go
CriticalQueueTimeouts uint64 `json:"critical_queue_timeouts"`
```

Add function:

```go
func AppCriticalQueueTimeout(sessionID, eventName string, bytes int) {
	global.mu.Lock()
	defer global.mu.Unlock()
	global.goStats.CriticalQueueTimeouts++
	if s := global.sessions[sessionID]; s != nil {
		addSessionEventLocked(s, "critical_queue_timeout", eventName, "", bytes, "queue_full")
	}
}
```

- [ ] **Step 4: Write failing connection close test seam**

Add test in `internal/provider/openai/client_ws_test.go` for explicit close helper:

```go
func TestCloseRealtimeConnectionsIsIdempotent(t *testing.T) {
	c := NewClient(&conf.ModelConfig{})
	c.closeRealtimeConnections()
	c.closeRealtimeConnections()
}
```

Run:

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/provider/openai -run CloseRealtimeConnections -count=1
```

Expected: FAIL because `closeRealtimeConnections` does not exist.

- [ ] **Step 5: Implement centralized close helper**

Add to `internal/provider/openai/client_ws.go`:

```go
func (c *Client) closeRealtimeConnections() {
	if c.appConn != nil {
		_ = c.appConn.Close()
	}
	c.connMu.Lock()
	if c.apiConn != nil {
		_ = c.apiConn.Close()
		c.apiConn = nil
	}
	c.connMu.Unlock()
}
```

Modify `HandleWS`:

```go
ctx, cancel := context.WithCancel(ctx)
defer func() {
	cancel()
	c.closeRealtimeConnections()
}()
```

Also call `c.closeRealtimeConnections()` after `wg.Wait()` if needed. The helper must be idempotent.

- [ ] **Step 6: Write OpenAI Ping test seam**

Add test in `internal/provider/openai/client_ws_test.go`:

```go
func TestOpenAIPingIntervalConfigured(t *testing.T) {
	cfg := &conf.ModelConfig{}
	cfg.Realtime.ApiPingInterval = "10ms"
	c := NewClient(cfg)
	if c.cfg.GetApiPingInterval() != 10*time.Millisecond {
		t.Fatalf("api ping interval = %v, want 10ms", c.cfg.GetApiPingInterval())
	}
}
```

This only verifies config parsing. For runtime behavior, add a follow-up integration test once a mock websocket writer seam is available.

- [ ] **Step 7: Implement OpenAI Ping ticker**

In `openAIWritePump`, add ticker from `c.cfg.GetApiPingInterval()`:

```go
pingTicker := time.NewTicker(c.cfg.GetApiPingInterval())
defer pingTicker.Stop()
```

Add select branch:

```go
case <-pingTicker.C:
	c.connMu.Lock()
	conn := c.apiConn
	if conn != nil {
		_ = conn.SetWriteDeadline(time.Now().Add(c.cfg.GetApiWriteTimeout()))
		err := conn.WriteMessage(websocket.PingMessage, nil)
		c.connMu.Unlock()
		if err != nil {
			c.log.Warn("发送 OpenAI Ping 失败", zap.Error(err))
			metrics.OpenAIWriteError(c.sessionID, "ping", "api_ping_interval", err)
			return
		}
		metrics.OpenAIPingSent(c.sessionID)
	} else {
		c.connMu.Unlock()
	}
```

In `metrics.go`, add `OpenAIPingSent(sessionID string)` and expose it in snapshot.

- [ ] **Step 8: Define upstream queue full protocol response**

When `enqueueOpenAIOutbound` returns `openai outbound queue full`, `handleAppTextMessage`/`handleAppBinaryMessage` must return an error that triggers a structured App response and session close. Minimal behavior:

```go
if err := c.forwardClientEvent(eventData, "binary_audio"); err != nil {
	_ = c.forwardCriticalToApp(response.NewResponse(503, response.EventError, "OpenAI 上游写队列已满，请稍后重试"), "api_queue_full")
	return err
}
```

Expected: API queue full is visible to client and metrics, not only server log.

- [ ] **Step 9: Run Task 6 verification**

Run:

```powershell
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/provider/openai -run "SendAppEvent|ForwardCritical|CloseRealtimeConnections|OpenAIPing|Queue|Reconnect" -count=1
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./internal/service/metrics -run "Queue|Critical|Ping|SlowConsumer" -count=1
$env:GOCACHE = (Join-Path (Get-Location) '.tmp\go-build'); go test ./... -count=1
```

Expected: queue policy、集中关闭、Ping 配置和全仓库测试通过。

---

## Self-Review

- Spec coverage: Task 5 覆盖模型工具写文件、HTTP 写文件、前端确认、审计日志；Task 6 覆盖容量释放前提、背压、关键事件、OpenAI Ping 和队列水位。
- Placeholder scan: 本计划未留下临时空步骤。
- Type consistency: `WriteActor`、`PendingWrite`、`PendingStatus*`、`PreviewWrite`、`ConfirmPendingWrite`、`RejectPendingWrite`、`appEventPolicy`、`sendAppEvent`、`forwardCriticalToApp` 在测试和实现步骤中使用一致。

## Execution Choice

确认开始后建议执行顺序：

1. 先执行 Task 5，关闭“模型可直接改文件”的 P0 风险。
2. 再执行 Task 6，修复长连接背压和容量释放语义。
3. Task 5-6 全部通过后，才能进入 monitor/alert/stats/dashboard/capacity。
