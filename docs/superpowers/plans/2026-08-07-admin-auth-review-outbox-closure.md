# Admin Authentication Review Outbox Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the cache-lease based authentication review flow with a database-backed Outbox/Saga that preserves legacy review behavior, prevents cross-node duplicate review, and never reports local success when MT4 or the local commit is uncertain.

**Architecture:** `AdminAuthReviewProcessor` owns both the single-transaction local review path and the MT4-backed bank approval path. Every transaction that touches both records locks `user_auths` before the Outbox; claim and finalize first read only the Outbox identity, then lock and revalidate both records in that order. MT4-backed reviews create one encrypted, uniquely active intent, call MT4 outside the transaction, and finalize local state only if the claim and authentication snapshot still match. A queued job and scheduled dispatcher recover `pending`/`retryable` records and turn stale `processing` claims into `unknown` without blindly resending an uncertain MT4 request.

**Tech Stack:** PHP 7.4/8.x, Laravel 8.83, Eloquent/MySQL InnoDB, Laravel Crypt, Laravel queue/scheduler, PHPUnit 9 and Mockery.

---

## File Structure

- Create `database/migrations/2026_08_07_000002_create_admin_auth_review_outboxes.php`: durable status, claim, encrypted payload, unique active-user key, and recovery indexes.
- Create `app/Models/AdminAuthReviewOutbox.php`: fillable/casts contract and hidden sensitive payload fields.
- Create `app/Services/AdminAuthReviewPayload.php`: authenticated encryption/decryption and deterministic authentication snapshot hashing.
- Create `app/Services/AdminAuthReviewProcessor.php`: submit, database claim, MT4 classification, finalization, retry, rejection, and unknown-state transitions.
- Create `app/Jobs/ProcessAdminAuthReview.php`: queue adapter carrying only the outbox ID.
- Create `app/Console/Commands/DispatchPendingAdminAuthReviews.php`: dispatch due and stale outbox rows in bounded chunks.
- Modify `app/Console/Kernel.php`: schedule the dispatcher every minute with overlap protection.
- Modify `app/Http/Controllers/Admin/AdminUserController.php`: retain HTTP validation/access checks, delegate state changes, remove cache locking and direct MT4 orchestration.
- Modify `tests/Unit/AdminAuthReviewAtomicControllerTest.php`: preserve component and aggregate behavior while asserting processor delegation instead of cache locking.
- Create `tests/Unit/AdminAuthReviewOutboxContractTest.php`: migration/model/security/claim/recovery/controller contract tests that do not touch a database.
- Create `tests/Unit/AdminAuthReviewProcessorTest.php`: red-green tests for result classification, conflict handling, fresh-snapshot finalization, and failure-state transitions using Mockery/fakes only.

### Task 1: Lock Down the Durable Contract

**Files:**
- Test: `tests/Unit/AdminAuthReviewOutboxContractTest.php`
- Create: `database/migrations/2026_08_07_000002_create_admin_auth_review_outboxes.php`
- Create: `app/Models/AdminAuthReviewOutbox.php`
- Create: `app/Services/AdminAuthReviewPayload.php`

- [x] **Step 1: Write failing source-contract and payload tests**

Assert that the migration contains the following exact durable fields and indexes:

```php
$required = [
    "unsignedBigInteger('user_id')",
    "unsignedBigInteger('active_user_id')->nullable()",
    "unsignedBigInteger('admin_id')",
    "string('admin_name', 100)",
    "string('request_ip', 45)",
    "string('status', 30)->default('pending')",
    "unsignedInteger('attempts')->default(0)",
    "text('payload_ciphertext')",
    "char('payload_hash', 64)",
    "char('auth_snapshot_hash', 64)",
    "unique('active_user_id', 'admin_auth_review_outboxes_active_user_unique')",
    "index(['status', 'available_at'], 'admin_auth_review_outboxes_ready_index')",
    "index(['status', 'locked_at'], 'admin_auth_review_outboxes_stale_index')",
];
```

Assert that model serialization hides `payload_ciphertext`, `payload_hash`, and `auth_snapshot_hash`. Test `AdminAuthReviewPayload::encrypt()`/`decrypt()` round trip and hash mismatch rejection.

- [x] **Step 2: Run the contract test and confirm RED**

Run: `vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewOutboxContractTest.php`

Expected: FAIL because the migration, model, and payload class do not exist.

- [x] **Step 3: Implement the migration, model, and encrypted payload helper**

The migration creates an InnoDB table with nullable Unix timestamp columns (`available_at`, `locked_at`, `processed_at`, `created_at`, `updated_at`, `deleted_at`). `active_user_id` equals `user_id` in `pending`, `processing`, `retryable`, and `unknown`; terminal `processed`/`rejected` rows clear it to `NULL`, allowing a later review while retaining audit history.

The payload helper uses:

```php
$json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ciphertext = Crypt::encryptString($json);
$hash = hash_hmac('sha256', $json, (string) config('app.key'));
```

`decrypt()` must verify the HMAC with `hash_equals()` before returning the decoded array. `snapshotHash()` hashes the JSON-encoded current authentication snapshot with the same application key and refuses an empty key.

- [x] **Step 4: Run the contract test and confirm GREEN**

Run: `vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewOutboxContractTest.php`

Expected: PASS.

### Task 2: Implement the Processor State Machine

**Files:**
- Create: `app/Services/AdminAuthReviewProcessor.php`
- Test: `tests/Unit/AdminAuthReviewProcessorTest.php`

- [x] **Step 1: Write failing tests for submit and result classification**

Cover these exact outcomes:

```php
// no MT4 required: one transaction, fresh locked UserAuth, local updates + OperationLog
['status' => 'processed']

// another active outbox exists for the user
['status' => 'conflict']

// bank approval: pending outbox is created before MT4 is invoked
['status' => 'processed', 'outbox_id' => 123]

// connect failed before send
['status' => 'retryable', 'error_code' => 'connection_failed']

// write/read/malformed/transport uncertainty
['status' => 'unknown', 'error_code' => 'read_timeout']

// provider returned a definite rejection
['status' => 'rejected', 'error_code' => 'provider_rejected']
```

Also assert that MT4 confirmed success followed by a finalization exception invokes `recordLocalCommitFailure()` and returns `unknown` with `local_commit_after_external_success_failed`.

- [x] **Step 2: Run the processor test and confirm RED**

Run: `vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewProcessorTest.php`

Expected: FAIL because `AdminAuthReviewProcessor` does not exist.

- [x] **Step 3: Implement `submit()` with one database lock order**

Every transaction that touches both records follows this order to avoid deadlocks:

```text
user_auths row -> active/specific outbox row -> user_infos update -> operation_logs insert
```

`submit()` first locks `user_auths`, then checks the unique active Outbox so a review that waited for the user lock observes any intent committed while it was waiting. It re-runs `AuthReviewTransition::assertReviewableComponents()` and `resolve()`, then either applies a non-MT4 transition immediately or writes one encrypted `pending` Outbox. Duplicate-key races remain the final database defense and map to `conflict`; they are not reported as success.

- [x] **Step 4: Implement `claim()` and MT4 call outside the transaction**

`claim()` first reads the Outbox without a lock to obtain `user_id`, then in one transaction locks `user_auths` followed by the Outbox and revalidates ownership and state. It accepts only due `pending`/`retryable` rows, decrypts and validates the payload, verifies `auth_snapshot_hash`, re-runs the transition, sets `processing`, increments `attempts`, and returns the immutable comment/claim attempt. A fresh `processing`, `unknown`, or terminal row is skipped; a stale `processing` row is changed to `unknown` with `stale_processing_claim` and is never resent.

- [x] **Step 5: Implement final and failure transitions**

Use these exact classifications:

```php
$definitelyNotSent = ['connection_failed', 'mt4_sync_disabled'];
$uncertain = ['write_failed', 'read_timeout', 'malformed_response', 'transport', 'transport_exception', 'unexpected_response'];
```

On confirmed MT4 success, finalize first reads the Outbox identity, then a new transaction locks `user_auths` followed by the Outbox, verifies user and claim ownership plus snapshot equality, recalculates the transition from the locked row, writes `user_auths`, recalculates `user_infos.auth_status`, inserts `operation_logs`, and marks the Outbox `processed` while clearing `active_user_id` and encrypted payload. Any exception after confirmed external success is followed by a separate transaction that marks the Outbox `unknown`; if that second write also fails, the exception remains visible and stale-claim recovery later marks it unknown.

`transport` and other delivery-uncertain errors map to `unknown`. An `InvalidArgumentException` raised while building the MT4 comment occurs before connect/write and maps to terminal `rejected` with `invalid_mt4_comment`, so it cannot create a permanently blocked unknown intent.

- [x] **Step 6: Run processor tests and confirm GREEN**

Run: `vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewProcessorTest.php`

Expected: PASS.

### Task 3: Add Recovery Dispatch

**Files:**
- Create: `app/Jobs/ProcessAdminAuthReview.php`
- Create: `app/Console/Commands/DispatchPendingAdminAuthReviews.php`
- Modify: `app/Console/Kernel.php`
- Test: `tests/Unit/AdminAuthReviewOutboxContractTest.php`

- [x] **Step 1: Add failing job/command/scheduler contract assertions**

Assert that the job passes only an integer outbox ID to `AdminAuthReviewProcessor::process()`. Assert that the command scans due `pending`/`retryable` rows and `processing` rows older than five minutes, dispatches in `chunkById(100)`, and that Kernel schedules `mt4:dispatch-admin-auth-reviews` every minute with `withoutOverlapping(5)`.

- [x] **Step 2: Run the contract test and confirm RED**

Run: `vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewOutboxContractTest.php`

Expected: FAIL because recovery files and schedule entries do not exist.

- [x] **Step 3: Implement recovery job, command, and schedule**

When MT4 remote user sync is disabled, the command leaves `pending`/`retryable` rows untouched but still dispatches stale `processing` rows so they can become `unknown`. The job uses one queue attempt; durable outbox state, not queue retry timing, controls recovery.

- [x] **Step 4: Run the contract test and confirm GREEN**

Run: `vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewOutboxContractTest.php`

Expected: PASS.

### Task 4: Switch the HTTP Controller

**Files:**
- Modify: `app/Http/Controllers/Admin/AdminUserController.php`
- Modify: `tests/Unit/AdminAuthReviewAtomicControllerTest.php`

- [x] **Step 1: Rewrite controller tests first**

Remove cache-lock expectations. Assert validation, admin/data-scope checks, response mapping, and that the controller maps processor states as follows:

```php
'processed' => ResponseCode::SUCCESS,
'missing' => ResponseCode::DATA_NOT_FOUND,
'conflict' => ResponseCode::OPERATION_NOT_ALLOWED,
'retryable' => ResponseCode::MT4_SYNC_FAILED,
'rejected' => ResponseCode::MT4_SYNC_FAILED,
'unknown' => ResponseCode::MT4_SYNC_FAILED,
```

- [x] **Step 2: Run the controller test and confirm RED**

Run: `vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewAtomicControllerTest.php`

Expected: FAIL while the controller still uses `Cache::lock()` and calls MT4 directly.

- [x] **Step 3: Delegate from the controller**

Inject `AdminAuthReviewProcessor` as the fourth constructor dependency. Keep request validation, normalized decisions, data-scope authorization, and admin authentication in the controller. Delete `AUTH_REVIEW_LOCK_SECONDS`, the `Cache` import, `reviewAuthWhileLocked()`, and direct `Mt4ManagerService::updateComment()` logic. Return the existing response codes/messages so both Layui and CrmUI clients retain their contract.

- [x] **Step 4: Run controller and processor tests and confirm GREEN**

Run: `vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewAtomicControllerTest.php tests\Unit\AdminAuthReviewProcessorTest.php`

Expected: PASS.

### Task 5: Close Review Findings

**Files:**
- Modify: `app/Services/AdminAuthReviewProcessor.php`
- Modify: `app/Http/Controllers/Admin/AdminUserController.php`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`
- Modify: `resources/admin/layui/authentications/index.blade.php`
- Modify: `resources/admin/layui/authentications/detail.blade.php`
- Modify: `public/js/apps/crmui/admin.js`
- Test: `tests/Unit/AdminAuthReviewProcessorTest.php`
- Test: `tests/Unit/AdminAuthReviewAtomicControllerTest.php`
- Test: `tests/Unit/AdminAuthenticationComponentReviewUiContractTest.php`
- Test: `tests/Unit/AdminAuthenticationDetailClosureTest.php`

- [x] **Step 1: Reproduce and close the review-wait race**

Simulate an active Outbox becoming visible only after `user_auths` is locked. Require `submit()` to return `conflict` without local writes, and require claim/finalize to reload and revalidate the Outbox only after locking `user_auths`.

- [x] **Step 2: Classify every reachable MT4 delivery result conservatively**

Map literal `transport` to `unknown`. Map a pre-connect protocol-delimiter `InvalidArgumentException` to terminal `rejected / invalid_mt4_comment`.

- [x] **Step 3: Enforce storage-aware reason and audit limits**

Reject `reason`, `id_card_reason`, and `bank_reason` over 500 characters before processor invocation. Preserve valid 500-character remarks, limit `operation_logs.content` to 1000 characters, and set both Layui and CrmUI review textareas to `maxlength=500`.

- [x] **Step 4: Fail closed when aggregate user state is missing**

Lock and require the matching `user_infos` row before updating aggregate `auth_status`. A missing row rolls back local changes; after confirmed MT4 success it is quarantined as `unknown` instead of falsely marking the Outbox `processed`.

### Task 6: Verification and Review

**Files:**
- Verify all files above.

- [x] **Step 1: Run the narrow regression suite**

```powershell
vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewOutboxContractTest.php
vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewProcessorTest.php
vendor\bin\phpunit --colors=never tests\Unit\AdminAuthReviewAtomicControllerTest.php
vendor\bin\phpunit --colors=never tests\Unit\AdminAuthenticationDetailClosureTest.php
```

Expected: zero failures and zero errors.

- [x] **Step 2: Run the affected module regression**

Run: `vendor\bin\phpunit --colors=never tests\Unit --filter Auth`

Expected: zero failures and zero errors.

- [x] **Step 3: Run static/runtime-safe checks**

Run `php -l` over every changed PHP file, `php artisan route:list --name=admin_api_reviewAuth`, and `php artisan schedule:list`. Do not run migrations or Feature tests because `co_crmv5_test` does not exist and both formal databases remain read-only.

- [x] **Step 4: Self-review the state machine**

Verify line by line that: no cache lease remains; all external calls occur outside transactions; all final local writes use fresh row locks; only definite not-sent failures are retried; uncertain states never resend automatically; active uniqueness is cleared only in definite terminal states; payload values never enter responses/logs; and operation logs are written atomically with the final auth state.

- [x] **Step 5: Record blocked validation honestly**

Document that migration replay, database-backed Feature tests, and browser visual checks remain blocked by the missing isolated database and in-app local-URL policy. Do not claim those checks passed.

## Plan Self-Review

- Spec coverage: covers cross-node uniqueness, lock freshness, encrypted sensitive data, MT4 outside transactions, retry/unknown/rejected semantics, local-commit failure, stale recovery, controller compatibility, and scheduler recovery.
- Placeholder scan: no TBD/TODO/"implement later" steps remain.
- Type consistency: the processor uses `submit(int, array, array): array` and `process(int): array`; all controller/job mappings use the same status strings and `outbox_id`/`error_code` fields.
- Environment constraint: Git commits/worktrees and database-writing verification are intentionally omitted because this directory has no Git metadata and no authorized isolated test database exists.
