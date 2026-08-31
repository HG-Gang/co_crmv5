# Admin Gift Legacy Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close all six legacy Admin Gift routes with real DB data, exact legacy protocols, correct data scope, and coherent Layui/CrmUI workflows.

**Architecture:** Keep all business queries and writes in `Admin\GiftController`; add narrow adapters in `LegacyAdminController`. Mark forwarded legacy requests with a server-only attribute and use an administrator-owned one-time CSV download path for the old two-stage export contract.

**Tech Stack:** Laravel 8.83, PHP 7.4+, Blade, Layui, CrmUI JavaScript, MySQL test database, PHPUnit 9.6.

---

### Task 1: Lock The Six Legacy Contracts With Failing Tests

**Files:**
- Create: `tests/Feature/AdminGiftLegacyParityClosureModuleTest.php`
- Modify: `tests/Feature/AdminLegacyMiscOperationsClosureModuleTest.php`

- [ ] Add fixtures for eligible/default, non-default, gift-disabled, out-of-range-date, created-scope, and formula-prefixed Gift records.
- [ ] Assert both old browse URLs render distinct `giftPageMode` states and only their intended workflow controls.
- [ ] Assert `addressList` returns `code=0`, `rec_id`, only eligible default addresses, and old pagination fields.
- [ ] Assert `shipment_list` applies old default dates and explicit filters, maps `id` to `rec_id`, and returns the old envelope.
- [ ] Assert nested `giftInfo` plus `recipients[*].rec_id` writes authoritative DB address fields, string `0` tracking, status `1`, and rolls back a mixed invalid batch.
- [ ] Assert export returns JSON path, empty results fail, the protected GET downloads once, and CSV formula prefixes are escaped.
- [ ] Assert `created` scope works for address/list/send/update/export and excludes records owned by another administrator.
- [ ] Run `vendor\bin\phpunit --colors=never tests\Feature\AdminGiftLegacyParityClosureModuleTest.php`; expected result before implementation: contract assertions fail for missing adapters and incorrect envelopes.

### Task 2: Correct Gift Query, Scope, Send, And CSV Business Logic

**Files:**
- Modify: `app/Services/AdminDataScopeService.php`
- Modify: `app/Http/Controllers/Admin/GiftController.php`
- Modify: `tests/Feature/AdminGiftDataScopeClosureModuleTest.php`
- Modify: `tests/Feature/AdminGiftModuleTest.php`
- Modify: `tests/Feature/GiftStockDeductionBoundaryClosureModuleTest.php`

- [ ] Add an optional explicit created-owner column to `AdminDataScopeService::apply()` while preserving every existing call's default behavior.
- [ ] Apply `gift_shipments.admin_id` to shipment list/export and `user_infos.created_by` to address list.
- [ ] Add scalar/date validation shared by shipment list/export and keep the existing 5000-row export ceiling.
- [ ] Validate every recipient against real `user_infos` and default `user_addresses` rows before opening the transaction; write DB snapshots, not request snapshots.
- [ ] Use `canAccessRecord()` with the user creator for sends and shipment creator for updates.
- [ ] Use the server-only legacy attribute for empty tracking `0` and status `1`; modern empty tracking remains empty string and status `0`.
- [ ] Add BOM, cache headers, and formula sanitization to modern CSV export.
- [ ] Run the targeted parity, data-scope, stock-boundary, and modern Gift tests; expected result: all pass with no partial writes.

### Task 3: Add Narrow Legacy Adapters And Protected Export Download

**Files:**
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `app/Http/Controllers/Admin/GiftController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminGiftLegacyParityClosureModuleTest.php`

- [ ] Mark forwarded subrequests with `legacy_admin_uri` in the request attribute bag.
- [ ] Add dedicated adapters for address list, shipment list, send, and export before generic named-route forwarding.
- [ ] Normalize old pagination and filters, force legacy default address behavior, and add default shipment dates.
- [ ] Convert modern paginator data into exact old row fields and `code=0` envelopes.
- [ ] Convert modern send success/failure into old `0/5000` mutation envelopes.
- [ ] Prepare administrator-owned CSV files from the same scoped shipment query and return `data.path`.
- [ ] Register a `legacy.admin.auth` protected one-time download route whose permission override is `admin_api_exportGiftShipments`.
- [ ] Run `vendor\bin\phpunit --colors=never tests\Feature\AdminGiftLegacyParityClosureModuleTest.php`; expected result: all parity tests pass.

### Task 4: Close Layui And CrmUI Workflow Semantics

**Files:**
- Modify: `resources/admin/layui/gifts/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`
- Modify: `tests/Feature/AdminGiftModuleTest.php`
- Modify: `tests/Feature/FrontUiRegressionTest.php`

- [ ] Render Layui sections conditionally for `all`, `send`, and `shipments`, with stable responsive table containers and accessible section labels.
- [ ] Load Layui `jquery`, guard absent mode-specific elements, disable the send button while the request is pending, and reload only existing tables.
- [ ] Remove the manual recipient form from CrmUI shipments and keep sends exclusively on the address picker page.
- [ ] Default CrmUI gift addresses to `is_default=1` and preserve selected DB-row payload generation.
- [ ] Run Gift UI contracts plus `node --check public/js/apps/admin/layui/pages.js`; expected result: tests and syntax pass.

### Task 5: Review, Evidence, Matrix, And Progress Closure

**Files:**
- Create: `tests/Unit/AdminGiftBusinessMatrixClosureTest.php`
- Modify: `docs/audits/旧项目路由核验证据.json`
- Modify: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Modify: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Modify: `docs/项目整体进度梳理-2026-08-17.md`
- Modify: existing matrix total assertions that intentionally pin the global verified count.

- [ ] Run an independent specification review and close every missing requirement.
- [ ] Run an independent code-quality review and close every Critical or Important finding.
- [ ] Write seven-dimensional evidence for exactly six Gift routes and regenerate the matrix.
- [ ] Assert matrix totals `475 total / 434 verified / 41 remaining / 0 unresolved / 0 unmatched`.
- [ ] Run Gift feature regression, all matrix closure tests, legacy route compatibility, relevant permission/UI suites, PHP lint, JS syntax, and Blade cache/clear.
- [ ] Record exact fresh counts and retain browser status as `BLOCKED_BY_BROWSER_POLICY` unless real navigation becomes allowed.
- [ ] Continue immediately with the next remaining controller, `CancellationController`.

No Git commit steps are included because the current workspace is not a Git repository.
