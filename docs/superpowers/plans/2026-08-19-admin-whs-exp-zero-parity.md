# Admin WHS Exp Zero Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the six remaining WHS zeroing review items with DB-backed behavior, safe mutations, exact legacy envelopes, permissions, and both admin UIs.

**Architecture:** `AdminWhsExpZeroController` remains the single modern business source. `LegacyAdminController` only normalizes old fields and adapts responses; Layui and CrmUI consume the modern APIs. Existing tables and permissions are reused without schema work.

**Tech Stack:** Laravel 8, PHP 7.4, Eloquent/query builder, PHPUnit 9, Layui, CrmUI/Vue runtime.

---

### Task 1: Freeze Route, Permission, And Maintenance Contracts

**Files:**
- Create: `tests/Feature/AdminLegacyWhsExpZeroPermissionClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`

- [x] Write failing tests for the five admin route methods/actions, page/list/action permissions, anonymous rejection, and `whstest` HTTP 423.
- [x] Run the test and confirm failures identify the WHS page fallback and incorrect search/mutation permission mappings.
- [x] Add explicit WHS page and target-route permission mappings.
- [x] Re-run the focused test to green.

### Task 2: Restore Scan And Record Search Parity

**Files:**
- Create: `tests/Feature/AdminLegacyWhsExpZeroParityClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/AdminWhsExpZeroController.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`

- [x] Write failing tests proving `oneKeySearch` creates pending rows, does not duplicate active rows, and returns `msg/err/col`.
- [x] Write failing tests proving V1/V2 searches read `whs_exp_zeros`, honor `wez_*` plus dates, apply scope, and return their exact envelopes.
- [x] Run the focused tests and confirm they fail against the current candidate-list forwarding.
- [x] Add reusable scoped candidate and record queries plus strict validation.
- [x] Add legacy scan/list adapters and rerun focused tests to green.

### Task 3: Make Single-User Clearing Atomic

**Files:**
- Modify: `tests/Feature/AdminWhsExpZeroMt4ClosureModuleTest.php`
- Modify: `tests/Feature/AdminLegacyWhsExpZeroParityClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/AdminWhsExpZeroController.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`

- [x] Write failing tests for claiming a pending scan row, rejecting an existing processing row before gateway invocation, settled completion, and failed-gateway balance preservation.
- [x] Run focused tests and confirm the pending-row and processing-state assertions fail.
- [x] Implement the transactional `0 -> 2|3` state machine and legacy mutation envelope.
- [x] Re-run focused and existing WHS tests to green.

### Task 4: Align Layui And CrmUI Contracts

**Files:**
- Create: `tests/Feature/AdminWhsExpZeroDualUiClosureModuleTest.php`
- Modify: `resources/admin/layui/whs-exp-zero/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`
- Verify: `app/Http/Controllers/CrmUi/Admin/PageController.php`

- [x] Write failing static/UI tests for candidate and record tabs, real API URLs, record filters, action permissions, and CrmUI parity.
- [x] Run the focused test and confirm the Layui record view is missing.
- [x] Add the Layui tabs, date/status filters, record table, and tab-aware reload behavior.
- [x] Run UI tests, `node --check`, and Blade cache checks.

### Task 5: Evidence, Review, And Matrix Closure

**Files:**
- Modify: `docs/audits/旧项目路由核验证据.json`
- Modify: `docs/项目整体进度梳理-2026-08-17.md`
- Generate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Generate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Create: `tests/Unit/AdminWhsExpZeroBusinessMatrixClosureTest.php`

- [x] Run the complete WHS keyword Feature suite and record fresh test/assertion totals.
- [x] Complete specification review, then code-quality review, fixing all Critical/Important findings.
- [x] Add five admin-route evidence entries and extend maintenance evidence for `whstest`.
- [x] Regenerate the matrix and assert `428/475` verified with `47` remaining.
- [x] Run all existing matrix gates, PHP lint, JS syntax, Blade cache, and the WHS matrix gate.
- [x] Update the progress document and set the next controller batch from the remaining table.
