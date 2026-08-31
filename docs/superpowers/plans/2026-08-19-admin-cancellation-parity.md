# Admin Cancellation Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Complete the six legacy `CancellationController` routes with a real-DB read model, exact V1/V2 adapters, safe review state transitions, and equivalent Layui/CrmUI workflows.

**Architecture:** Keep `CancelApplyController` as the only review state machine and add one focused query service for list filtering, data scope, balance, and open-position counts. `LegacyAdminController` only normalizes legacy fields and envelopes; both UI families consume the modern API and require an audit remark before either decision.

**Tech Stack:** Laravel 8.83, PHP 7.4, Eloquent/Query Builder, Blade, Layui, CrmUI jQuery runtime, PHPUnit 9.5.

---

### Task 1: Lock the six-route contract with failing tests

**Files:**
- Create: `tests/Feature/AdminCancellationLegacyParityClosureModuleTest.php`
- Modify: `tests/Feature/AdminLegacyCancelApplyCompatibilityTest.php`

- [x] Add fixtures for `cancel_applies`, `user_infos`, and `user_trades` using `DatabaseTransactions` so only `co_crmv5_test` is written.
- [x] Assert V1 returns `rows/total`, including empty-string empty results.
- [x] Assert V2 returns `code=200`, `msg`, `count`, `data`, and `totalRow`.
- [x] Assert user/status/date filters, default `2024-01-01` through today, balance formatting, and old open-position count (`cmd` 0-5, `close_time=1970-01-01`, nonzero `margin_rate`).
- [x] Assert `update_cancel` accepts only `1=approve` and `2=reject`, requires nonblank `cancel_remark`, and returns legacy `msg/col` alongside the modern code.
- [x] Assert created-scope list and mutation use the same `cancel_applies.created_by` owner.
- [x] Run `vendor\bin\phpunit --colors=never tests\Feature\AdminCancellationLegacyParityClosureModuleTest.php tests\Feature\AdminLegacyCancelApplyCompatibilityTest.php` and confirm failures are caused by missing adapters and the obsolete `0/1` decision contract.

### Task 2: Add the shared real-DB cancellation read model

**Files:**
- Create: `app/Services/AdminCancelApplyQueryService.php`
- Modify: `app/Http/Controllers/Admin/CancelApplyController.php`

- [x] Build one scoped `cancel_applies` query with exact user ID, exact status, inclusive timestamp date bounds, deterministic `created_at DESC, id DESC` ordering, and page size `1..100`.
- [x] Batch-read `user_infos.total_funds` (including soft-deleted reviewed users) and aggregate `user_trades` open-position counts; do not use mock/default datasets or per-row queries.
- [x] Return modern fields `id`, `user_id`, `user_name`, `balance`, `open_positions`, `status`, `cancel_remark`, `reject_reason`, `created_at`, and `updated_at` in the existing paginator envelope.
- [x] Validate modern list parameters before querying and preserve blank optional filters.
- [x] Run the Task 1 tests plus all `AdminCancelApply*` tests and confirm list assertions pass.

### Task 3: Repair legacy list and mutation adapters

**Files:**
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `tests/Feature/AdminLegacyCancelApplyCompatibilityTest.php`

- [x] Add a dedicated list adapter for `userlistSearch` and `userlistSearchV2`; normalize `userId`, `cancel_status`, `startdate`, `enddate`, `rows/limit`, and legacy default dates before forwarding to `admin_api_cancelApplyList`.
- [x] Map modern rows to `cancel_userid`, `cancel_username`, `bal`, `vol`, `cancel_status`, `cancel_remark`, and `rec_crt_date` without duplicating SQL.
- [x] Validate `update_cancel.accept_rejection` as `in:1,2` and require a trimmed `cancel_remark` for both decisions.
- [x] Scope the pending-application lookup before exposing its primary key, forward to the modern state machine, and adapt success/failure to legacy `msg/col` while retaining the modern business code.
- [x] Pass optional approval remarks into operation logs; require a nonblank reason for rejection; use `canAccessRecord()` so created-scope list and review agree.
- [x] Run Task 1 tests and all cancellation lifecycle/data-scope tests to green.

### Task 4: Complete Layui and CrmUI review workflows

**Files:**
- Modify: `resources/admin/layui/cancel-applies/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`
- Modify: `resources/admin/crmui/partials/module-page.blade.php`
- Modify: `public/js/apps/crmui/admin.js`
- Modify: `resources/lang/zh-CN/crmui.php`
- Modify: `resources/lang/en/crmui.php`
- Modify: `tests/Feature/FrontUiRegressionTest.php`

- [x] Add user ID, status, start/end date filters and show username, balance, open positions, applicant reason, review reason, state, and application time in both UI families.
- [x] Default both pages to pending applications and reset back to that state.
- [x] Open the same required 500-character review-remark modal for approve and reject, prevent duplicate submissions, and reload the current page after success.
- [x] Hide review actions when `status != 0`; implement reusable declarative CrmUI required-field and visibility metadata rather than cancellation-only DOM branches.
- [x] Add status badges, negative-balance emphasis, stable action widths, accessible labels, and responsive filter layout without nested cards.
- [x] Run `node --check` for both changed bundles, targeted UI tests, Blade cache/clear, and PHP 7.4 lint.

### Task 5: Review, regression, and evidence closure

**Files:**
- Create: `tests/Feature/AdminCancellationMatrixClosureTest.php`
- Modify: `docs/audits/旧项目路由核验证据.json`
- Regenerate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Regenerate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Modify: `docs/项目整体进度梳理-2026-08-17.md`

- [x] Run an independent specification review, close every missing/extra behavior, then run an independent code-quality review and close all Critical/Important findings.
- [x] Run cancellation Feature tests first, then legacy route inventory/semantics, data-scope, UI, and matrix gates serially.
- [x] Add seven-dimensional evidence for all six HTTP-method routes and assert the evidence group from a dedicated matrix test.
- [x] Regenerate the authoritative matrix and verify `475 total / 440 verified / 35 remaining / 0 unresolved / 0 unmatched`.
- [x] Update the progress document with fresh commands/counts and identify `PositionSummaryController` as the next batch; keep browser status `BLOCKED_BY_BROWSER_POLICY` unless a real four-viewport run succeeds.

### Self-Review

- [x] All six legacy routes are assigned to a backend, adapter, UI, permission, validation, test, and evidence step.
- [x] The plan has no production/legacy database writes, Seeder execution, migration execution, tinker, reset SQL, or browser-policy bypass.
- [x] The review state machine remains single-sourced in `CancelApplyController`; legacy code only adapts fields and envelopes.
- [x] PHP 7.4 syntax, old empty envelopes, `1/2` decisions, both decision remarks, and created-scope parity are explicit.
