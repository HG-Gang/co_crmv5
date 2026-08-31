# Legacy Route Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the remaining legacy-route parity for front users, agents, and administrators, with durable financial state machines, database-backed regression tests, route audit evidence, browser smoke evidence, and a full route execution-chain report.

**Architecture:** Keep modern and legacy HTTP adapters thin and route both through shared application services. Any MT4 command that may have moved money is represented by an outbox-backed state machine; an unknown command result is never retried automatically and can only be closed through an audited administrator reconciliation path. MySQL-writing tests run serially against the existing database and use rollback or fixture-specific cleanup; destructive database resets are forbidden.

**Tech Stack:** PHP 7.4+/Laravel 8, Eloquent/MySQL, PHPUnit 9, Blade, Layui/jQuery, vanilla JavaScript.

---

### Task 1: Commission transfer worker closure

**Files:**
- Create: `tests/Feature/CommissionTransferSagaDispatchClosureModuleTest.php`
- Create: `app/Jobs/ProcessCommissionTransferSaga.php`
- Create: `app/Console/Commands/DispatchPendingCommissionTransfers.php`
- Modify: `app/Providers/Mt4ServiceProvider.php`
- Modify: `app/Console/Kernel.php`

- [ ] Write a failing test that resolves all three MT4 gateways from the container, invokes the job with an outbox id, asserts the dispatcher only selects due `pending`/`retryable` and stale safe steps, and asserts the minute schedule contains `commission:dispatch-transfers`.
- [ ] Run `php vendor/bin/phpunit tests/Feature/CommissionTransferSagaDispatchClosureModuleTest.php --colors=never`; expected RED is a missing job/command/binding.
- [ ] Add a queue job whose entire handler is `CommissionTransferService::process($transferId)`, add a dispatcher that dispatches transfer ids rather than reissuing MT4 commands, bind the three gateway contracts to their MT4 adapters, and schedule the dispatcher with `withoutOverlapping(5)`.
- [ ] Run the dispatch test and `CommissionTransferSagaServiceTest`; both must exit 0.

### Task 2: Modern and legacy commission transfer adapters

**Files:**
- Create: `tests/Feature/FrontCommissionTransferSagaRouteClosureModuleTest.php`
- Modify: `app/Http/Controllers/Front/CommissionController.php`
- Modify: `app/Http/Controllers/Front/AgentController.php`
- Modify: `app/Http/Controllers/Front/LegacyPageController.php`
- Modify: `resources/front/layui/commission/transfer.blade.php`
- Modify: `public/js/apps/front/layui/module-page.js`
- Modify: `public/js/apps/naive-admin/front-plain.js`
- Modify: `public/js/apps/crmui/front.js`

- [ ] Write failing route/source tests for required trade `password`, safe `Idempotency-Key`, legacy `depositId/comm_money/password/idempotency_key`, session-bound `commission_transfer` intent validation, and legacy `msg/errorType/code/comm_money` responses.
- [ ] Run the new test; expected RED is the current local-balance transaction and login-password `Hash::check` path.
- [ ] Inject/use `CommissionTransferService` in both controllers. The modern adapter calls `createOrRetrieve($source, $target, $amount, $password, $remark, 'front_commission_transfer', $headerKey)`. The legacy adapter validates the body nonce with `LegacyFormIntentService`, then calls the service with purpose `legacy_commission_transfer` and maps terminal statuses to the legacy response contract.
- [ ] Issue the intent in `LegacyPageController::commissionTransfer(Request $request, $uid = null)`, render it as a hidden field, add password controls, and send the same nonce in both request body and `Idempotency-Key` header. Generate a fresh stable key per form attempt in the non-session modern shells.
- [ ] Run controller route tests, JavaScript source-contract tests, `node --check` for all changed scripts, and both existing commission transfer boundary suites.

### Task 3: Administrator manual reconciliation

**Files:**
- Create: `tests/Feature/AdminCommissionTransferReconciliationClosureModuleTest.php`
- Create: `app/Services/CommissionTransfer/CommissionTransferReconciliationService.php`
- Modify: `app/Http/Controllers/Admin/CommissionController.php`
- Modify: `routes/admin.php`
- Create: `database/migrations/2026_07_19_000004_add_commission_transfer_reconcile_permissions.php`
- Modify the established admin commission view/scripts discovered by the route test.

- [ ] Write failing tests that require admin authentication, route permission middleware, `AdminDataScopeService` filtering, and a mutation guard that only accepts `manual_reconcile_required` records.
- [ ] Add list/detail/reconcile endpoints. Reconcile stores an explicit administrator decision and external reference, never calls a funding gateway, performs a compare-and-set update inside a transaction, and writes `OperationLog` with before/after state.
- [ ] Add idempotent permission migration with duplicate preflight and a non-destructive `down()`.
- [ ] Run the new test, permission migration tests, `php artisan migrate --no-interaction`, and the admin commission regression suite.

### Task 4: Cross-gateway deposit idempotency

**Files:**
- Create: `tests/Feature/PaymentOrderCrossGatewayIdempotencyClosureModuleTest.php`
- Modify: `app/Services/Payment/PaymentOrderService.php`
- Create: `database/migrations/2026_07_19_000005_harden_deposit_idempotency_per_user.php`

- [ ] Write a serial MySQL RED test proving the same `(user_id, idempotency_key)` cannot create a second order after switching gateway, and that a changed amount or gateway returns a deterministic idempotency conflict without calling the provider order creator.
- [ ] Change `findExisting()` to query only user and key, include amount plus gateway in `existingResult()` payload comparison, and recognize the new unique constraint in MySQL/SQLite/PostgreSQL duplicate handling.
- [ ] In the migration, preflight duplicate user/key rows, drop only the known old user/key/gateway unique index, create a user/key unique index, verify its ordered columns, and keep financial rows in `down()`.
- [ ] Run the new test, existing deposit idempotency/payment tests, migration twice, and inspect the live index definition.

### Task 5: MT4 registration provisioning runtime

**Files:**
- Create: `tests/Feature/UserMt4ProvisioningRuntimeClosureModuleTest.php`
- Modify only proven defects in `app/Services/UserRegistrationService.php`, `app/Services/Registration/UserMt4ProvisioningProcessor.php`, the job/dispatcher, gateway, outbox model, or migration.

- [ ] Write serial database tests for processed, retryable-not-sent, rejected, unknown, stale safe/unsafe claims, competing claims, reconciliation-only retries, and local finalize failure.
- [ ] Verify each RED failure is behavioral rather than fixture/schema setup, then apply the smallest state-machine correction.
- [ ] Assert password ciphertext is retained only for a definitely-not-sent retry and is cleared for processed/rejected/unknown/stale outcomes; only processed provisioning enables login and permits JWT issuance.
- [ ] Run the runtime test, registration lifecycle tests, gateway tests, migration rerun test, and live schema/index inspection.

### Task 6: Full route parity and evidence

**Files:**
- Modify: `scripts/generate-full-route-execution-chain-report.php`
- Generate: `docs/reports/2026-07-19-full-route-execution-chain-report.md`

- [ ] Run all affected tests first, then `php vendor/bin/phpunit --colors=never` serially and require zero failures/errors.
- [ ] Run `php artisan migrate:status --no-interaction` and `php artisan legacy-routes:audit storage/app/audits/legacy-routes.json --scope=all --policy=docs/audits/legacy-route-method-policy.json`; require no unexplained gaps.
- [ ] Upgrade the report generator so every route row contains HTTP/URI, middleware, controller source, service/support/model/database chain, external dependency, success/failure branches, frontend consumer, and concrete test evidence. Remove unconditional completion claims.
- [ ] Start the local app, use the browser skill to smoke ordinary-user login/profile/deposit/withdraw, agent customer/commission-transfer and big-agent paths, and administrator login/user/funds/manual-reconcile paths.
- [ ] Regenerate the report, read it as UTF-8, verify every current route appears exactly once, then obtain fresh goal timing/token metrics and complete the goal only when all evidence is green.

**Environment note:** This workspace currently exposes no usable Git metadata, so verification checkpoints replace commit steps; no `git reset`, destructive migration, or `migrate:fresh` operation is permitted.
