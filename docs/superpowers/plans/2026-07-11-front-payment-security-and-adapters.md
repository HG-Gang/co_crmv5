# Front Payment Security and Adapters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将当前可伪造的通用入金回调改造成严格路由、精确金额、幂等下单、逐通道验签、合法状态机和可恢复资金入账链；缺少有效通道配置时明确禁用。

**Architecture:** `DepositController` 只负责认证、请求校验和调用 `PaymentOrderService`；`PaymentGatewayRegistry` 根据启用通道的 `adapter` 配置解析具体实现；每个 adapter 负责创建订单、验签、解析回调和 ACK。回调事务只锁本地订单、验证状态并写 outbox，外部 MT4/资金调用由幂等 worker 执行，支付状态与资金结算状态分离。

**Tech Stack:** Laravel 8、Eloquent/Query Builder、MySQL InnoDB/DECIMAL/唯一索引、Cache/数据库幂等、HTTP Client、PHPUnit 9。

---

### Task 1: Freeze unsafe routes and generic callbacks

**Files:**
- Modify: `routes/web.php`
- Modify: `routes/front.php`
- Modify: `app/Http/Middleware/VerifyCsrfToken.php`
- Modify: `app/Http/Controllers/Front/PaymentNotifyController.php`
- Create: `tests/Feature/FrontPaymentRouteSafetyClosureModuleTest.php`

- [x] Write failing route tests proving legacy deposit creation accepts POST only, notify accepts POST only, return accepts GET only, unknown gateways are rejected, and unsigned generic notifications cannot change `deposit_records.status`.
- [x] Run `php -d memory_limit=1G vendor/phpunit/phpunit/phpunit tests/Feature/FrontPaymentRouteSafetyClosureModuleTest.php --colors=never` and require failures for the current GET/POST `match` routes and mutable generic callback.
- [x] Replace legacy `match` routes with explicit POST submit/notify and GET return routes; add only the exact third-party notify URIs to CSRF exclusions.
- [x] Change `PaymentNotifyController` so unregistered or unconfigured gateways return 404/422 and never update orders; return pages remain display-only.
- [x] Run the target test and `FrontendRouteManifestTest` independently until both pass.

### Task 2: Exact money schema and idempotent local orders

**Files:**
- Create: `database/migrations/2026_07_11_000003_harden_deposit_payment_orders.php`
- Create: `app/Support/Money.php`
- Create: `app/Services/Payment/PaymentOrderService.php`
- Modify: `app/Http/Controllers/Front/DepositController.php`
- Modify: `app/Models/DepositRecord.php`
- Create: `tests/Feature/FrontDepositPaymentOrderIdempotencyClosureModuleTest.php`

- [x] Write failing tests rejecting scientific notation, three decimal places, negative/zero values and values outside configured bounds; accept only decimal strings with at most two fractional digits.
- [x] Write failing tests proving the same `Idempotency-Key + user_id + gateway` returns the original order, while the same key with a different amount returns conflict.
- [x] Migrate `amount/actual_amount` to `DECIMAL(18,2)`, `exchange_rate` to `DECIMAL(18,8)`, add unique `local_order_no`, add `idempotency_key`, `gateway_code`, `currency`, `payment_status`, `settlement_status`, `provider_payload_hash` and the composite idempotency unique index; convert `deposit_records` to InnoDB.
- [x] Implement `Money::fromDecimalString()` without float arithmetic and make `PaymentOrderService` create/retrieve orders inside a transaction.
- [x] Remove fallback channel reopening and static return-URL payment success paths; no enabled fully configured adapter means `OPERATION_NOT_ALLOWED` and no order row.
- [x] Run the target test, deposit owner-boundary test and strict-mode schema checks.

### Task 3: Gateway registry and configuration contract

**Files:**
- Create: `app/Contracts/PaymentGatewayAdapter.php`
- Create: `app/Services/Payment/PaymentGatewayRegistry.php`
- Create: `app/Services/Payment/PaymentCallback.php`
- Create: `app/Services/Payment/PaymentOrderResult.php`
- Modify: `app/Models/PaymentChannel.php`
- Create: `tests/Feature/PaymentGatewayRegistryTest.php`

- [x] Write failing tests for missing adapter, disabled channel, missing merchant/secret references, gateway mismatch and unsupported currency.
- [x] Define adapter methods `createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult`, `verifyCallback(Request $request, array $channelConfig): bool`, `parseCallback(Request $request, array $channelConfig): PaymentCallback`, and `acknowledge(PaymentCallback $callback): Response`.
- [x] Require channel config keys `adapter`, `merchant_id/app_id`, endpoint, secret/key reference, currency, amount unit, notify route and return route; never expose secret values through `frontChannels()`.
- [x] Implement registry whitelist aliases for Tiger, WP, Exlink FB/BB, BTB, PassTo, Switch and OTC; passageways 6/7 and 9/10/11 use the same adapter with explicit pay-type profile.
- [x] Run registry tests and payment channel API regressions.

### Task 4: Fixture-driven legacy gateway adapters

**Files:**
- Create: `app/Services/Payment/Gateways/TigerPayAdapter.php`
- Create: `app/Services/Payment/Gateways/WpPayAdapter.php`
- Create: `app/Services/Payment/Gateways/ExlinkFiatAdapter.php`
- Create: `app/Services/Payment/Gateways/ExlinkCryptoAdapter.php`
- Create: `app/Services/Payment/Gateways/BtbAdapter.php`
- Create: `app/Services/Payment/Gateways/PassToAdapter.php`
- Create: `app/Services/Payment/Gateways/SwitchAdapter.php`
- Create: `app/Services/Payment/Gateways/OtcAdapter.php`
- Create: `tests/Feature/PaymentGatewayAdapterFixtureTest.php`

- [x] Add sanitized request/callback fixtures for every adapter; fixtures contain no production credentials.
- [x] Implement exact field mapping, amount unit and signature calculation from the audited old protocols; use `hash_equals` for symmetric signatures and OpenSSL verification/decryption for Tiger.
- [x] Reject missing signatures, changed amount, changed merchant, changed gateway, changed currency, changed order number and invalid provider status.
- [x] Keep provider base URLs and all keys in runtime configuration/secret references; if any required current value is absent, adapter creation must fail closed.
- [x] Run fixture tests without external network access.

Task 4 final evidence: `PaymentGatewayAdapterFixtureTest` is `OK (91 tests, 732 assertions)`; `PaymentGatewayRegistryTest` is `OK (37 tests, 106 assertions)`; Task 2/controller, owner, route-safety, manifest and admin payment-channel regressions all pass. Final specification and code-quality reviews are APPROVED with zero Critical, Important or Minor findings.

### Task 5: Callback state machine and settlement outbox

**Files:**
- Create: `database/migrations/2026_07_11_000006_create_payment_settlement_outbox.php`
- Create: `app/Models/PaymentSettlementOutbox.php`
- Create: `app/Services/Payment/PaymentCallbackService.php`
- Create: `app/Jobs/SettleDepositPayment.php`
- Create: `app/Jobs/RefundDepositPayment.php`
- Create: `app/Contracts/DepositSettlementGateway.php`
- Create: `app/Contracts/DepositRefundGateway.php`
- Create: `app/Services/Payment/Mt4DepositSettlementGateway.php`
- Create: `app/Services/Payment/Mt4DepositRefundGateway.php`
- Modify: `app/Http/Controllers/Front/PaymentNotifyController.php`
- Create: `tests/Feature/FrontPaymentCallbackStateMachineClosureModuleTest.php`

- [x] Write failing tests for valid success, duplicate success, failure-after-success, amount/merchant/gateway mismatch, malformed payload, temporary internal failure and valid provider ACK.
- [x] Lock the order by unique local order number; require matching gateway, merchant snapshot, currency and exact decimal amount.
- [x] Allow only legal payment transitions; duplicate success is idempotent, later failure cannot regress success, and refund is a separate MT4 reversal state machine.
- [x] In the callback transaction update `payment_status/payment_time` with a real DATETIME and create one unique settlement outbox row; do not mark admin review or settlement complete.
- [x] Implement deposit and refund jobs with transactional claims; external MT4 calls run outside database transactions, success tickets complete the matching state, and uncertain results become non-retryable `unknown`.
- [x] Return provider-specific ACK only after valid processing; signature errors return 4xx, temporary failures return 5xx.

Task 5 final evidence: callback/deposit/refund/scanner/migration/gateway/route-safety suites are `OK (94 tests, 369 assertions)` when run independently. Migration `2026_07_11_000007_add_deposit_refund_settlement_fields` is applied, `payments:dispatch-deposit-settlements` is scheduled every minute, and final specification/code-quality reviews are APPROVED with zero Critical, Important or Minor findings.

### Task 6: Full payment regression, browser smoke and documentation

**Files:**
- Modify: `docs/admin-backend-blade-permission-final-checklist.md`
- Create: `docs/reports/2026-07-11-front-payment-route-execution-chain.md`
- Verify: all payment/deposit feature tests and browser flows

- [ ] Run every payment/deposit test file independently, then `FrontLegacyRouteCompatibilityTest`, `FrontendRouteManifestTest` and `FrontUiRegressionTest`.
- [ ] Run migrations against MySQL strict mode and verify InnoDB, DECIMAL, DATETIME and all unique indexes from `information_schema`.
- [ ] Use the local fake provider to smoke-test create → redirect → signed callback → duplicate callback → settlement outbox without real credentials.
- [ ] Document each legacy and modern route, adapter, signature fields, state transition, ownership/amount checks, ACK and external configuration boundary.
- [ ] Mark only configuration-backed adapters as available; list missing live merchant/key/endpoint inputs as deployment blockers, never as implemented success paths.

### Plan self-review

- [x] Every audited P0 has a task: route methods, CSRF, amount precision, idempotency, adapter/keys, callback verification, DATETIME, state machine, MT4 settlement and fallback removal.
- [x] No production secret or hard-coded old credential is copied into the plan.
- [x] Each implementation task starts with a failing test and has an exact verification command.
- [x] External provider availability is not required for deterministic fixture and fake-provider verification.
