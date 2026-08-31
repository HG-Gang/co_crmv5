# Front Withdrawal Funding Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将普通用户出金申请改造成精确金额、幂等 reservation、MT4 扣款 outbox、后台合法审批和拒绝退款的可恢复资金闭环。

**Architecture:** `WithdrawController` 只负责认证、规则校验和调用 `WithdrawalOrderService`；服务在本地事务内创建唯一订单与 outbox。`ProcessWithdrawFunding`/`RefundWithdrawFunding` 在事务外调用 MT4 gateway，按明确未发送、成功、拒绝和不确定结果更新资金状态。后台 Controller 只能推进合法状态，已扣款拒绝必须等待反向 MT4 入金成功。

**Tech Stack:** Laravel 8、Eloquent/Query Builder、MySQL InnoDB/DECIMAL/唯一索引、Queue Job/Artisan scanner、MT4 Manager Socket、PHPUnit 9。

---

### Task 1: 精确 schema、唯一订单与 outbox

**Files:**
- Create: `database/migrations/2026_07_12_000001_harden_withdrawal_funding.php`
- Modify: `app/Models/WithdrawRecord.php`
- Create: `app/Models/WithdrawSettlementOutbox.php`
- Create: `tests/Feature/FrontWithdrawalFundingSchemaClosureModuleTest.php`

- [x] 写 RED：断言 `withdraw_records` 为 InnoDB，金额为 `DECIMAL(18,2)`、汇率为 `DECIMAL(18,8)`、时间为 DATETIME；`local_order_no` 和 `(idempotency_key,user_id)` 唯一。
- [x] 写 RED：断言 `withdraw_settlement_outbox` 为 InnoDB，`BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`，且 `(event_type,withdraw_record_id)` 唯一。
- [x] 逐项验证迁移重入：缺列补列、错误索引恢复、NULL 回填；非空错误主键 fail-fast。
- [x] 实现迁移和模型 casts/fillable，使新建库与现有库得到相同契约。
- [x] 运行：`php -d memory_limit=1G vendor/phpunit/phpunit/phpunit tests/Feature/FrontWithdrawalFundingSchemaClosureModuleTest.php --colors=never`。

验证证据：fresh `63 tests / 572 assertions`；规格审查与质量审查均通过，Critical/Important/Minor 为 0；测试后真实表仍为 `withdraw_records=29`、`withdraw_settlement_outbox=0`，无临时表、临时索引、测试行或残留 advisory lock。

### Task 2: 幂等申请与并发 reservation

**Files:**
- Create: `app/Services/Withdrawal/WithdrawalOrderService.php`
- Create: `app/Contracts/WithdrawalAccountSnapshotGateway.php`
- Create: `app/Services/Withdrawal/WithdrawalAccountSnapshot.php`
- Modify: `app/Http/Controllers/Front/WithdrawController.php`
- Modify: `public/js/apps/front/layui/pages.js`
- Create: `tests/Feature/FrontWithdrawSettlementClosureModuleTest.php`

- [ ] 写 RED：amount 必须是普通十进制字符串；拒绝数字 JSON、科学计数、三位小数、零、负数、范围外和 DECIMAL 溢出。
- [ ] 写 RED：`Idempotency-Key` 缺失/非法失败；相同 key+用户+金额返回原订单，不同金额冲突。
- [ ] 写 RED：先读取真实 MT4 balance/free margin；读取失败时不创建订单。事务锁用户并减去 pending/processing/unknown reservations；两个请求争用同一余额只能一个创建。
- [ ] 写 RED：密码、实名/银行卡、出金开关、风险率、持仓、限额、手续费和银行快照均在创建前验证。
- [ ] 实现 `WithdrawalOrderService::createOrRetrieve()`：事务内锁 `user_infos`，生成唯一 `WDR...`，写 `funding_status=pending` 和唯一 debit outbox，外部调用为零。
- [ ] Controller 返回“资金处理中”的订单状态，不得把 pending 伪装为 MT4 已扣款。
- [ ] 更新 Layui 提交为每次用户动作生成并复用同一 idempotency key；Legacy aliases 归一化后走同一服务。
- [ ] 运行目标测试和 `FrontLegacyRouteCompatibilityTest`。

### Task 3: MT4 debit gateway、Job 与恢复扫描

**Files:**
- Create: `app/Contracts/WithdrawalFundingGateway.php`
- Create: `app/Services/Withdrawal/WithdrawalFundingResult.php`
- Create: `app/Services/Withdrawal/Mt4WithdrawalFundingGateway.php`
- Create: `app/Services/Withdrawal/Mt4WithdrawalAccountSnapshotGateway.php`
- Create: `app/Jobs/ProcessWithdrawFunding.php`
- Create: `app/Console/Commands/DispatchPendingWithdrawSettlements.php`
- Modify: `app/Providers/Mt4ServiceProvider.php`
- Modify: `app/Console/Kernel.php`
- Create: `tests/Feature/Mt4WithdrawalFundingGatewayClosureModuleTest.php`
- Create: `tests/Feature/ProcessWithdrawFundingJobClosureModuleTest.php`
- Create: `tests/Feature/DispatchPendingWithdrawSettlementsCommandClosureModuleTest.php`

- [ ] RED gateway：MT4 `ok + numeric ticket` 为 debited；`connection_failed` 为 retryable_not_sent；`write_failed/read_timeout/transport exception/invalid ticket` 为 unknown；其他错误为 rejected。
- [ ] RED account snapshot：`USER_INFO_GET` 只有 `ok + 合法 balance/free_margin` 才返回快照；连接/写/读/畸形响应全部 fail-closed。
- [ ] RED Job：事务 claim 后才在事务外调用 gateway；成功写 ticket、funding_status=debited、outbox processed，不直接改 MT4 镜像字段。
- [ ] RED Job：明确未发送退避；unknown/过期 processing 终态化且不会二次调用；provider rejected 将申请设 status=3/funding_status=rejected。
- [ ] RED scanner：仅派发 pending、到期 retryable；unknown/rejected/processed 不派发；错误 event type 安全 no-op。
- [ ] 实现 gateway、Job、scanner、容器绑定和每分钟无重叠调度。
- [ ] 分别运行三个目标测试文件。

### Task 4: 后台 process/complete/reject 与 MT4 refund

**Files:**
- Create: `app/Contracts/WithdrawalRefundGateway.php`
- Create: `app/Services/Withdrawal/Mt4WithdrawalRefundGateway.php`
- Create: `app/Jobs/RefundWithdrawFunding.php`
- Modify: `app/Http/Controllers/Admin/WithdrawController.php`
- Modify: `app/Providers/Mt4ServiceProvider.php`
- Create: `tests/Feature/AdminWithdrawalFundingStateMachineClosureModuleTest.php`
- Create: `tests/Feature/RefundWithdrawFundingJobClosureModuleTest.php`

- [ ] RED：process 只允许 status=0 + funding_status=debited；complete 只允许 status=1 + debited。
- [ ] RED：pending/retryable 可在扣款前原子取消；processing 创建 blocked refund；debited 创建唯一 refund outbox；unknown 拒绝自动处理。
- [ ] RED refund Job：使用 MT4 deposit 反向入金；成功写 refund ticket/time 并将 status=3/funding_status=refunded，不直接改 MT4 镜像字段。
- [ ] RED：refund 连接前失败可重试；写入/读取超时与本地提交失败为 refund_unknown，禁止自动重复。
- [ ] 所有后台动作在事务中 `lockForUpdate()`，继续执行 AdminDataScopeService 所有权检查并记录 `updated_by/reject_reason`。
- [ ] 分别运行两个目标测试文件和现有后台出金回归。

### Task 5: 历史、Legacy/OTC 入口和显示语义

**Files:**
- Modify: `app/Http/Controllers/Front/WithdrawController.php`
- Modify: `app/Support/FrontLegacyData.php`
- Modify: `resources/front/layui/withdraw/index.blade.php`
- Modify: `public/js/apps/front/layui/pages.js`
- Create: `tests/Feature/FrontWithdrawalLegacyRouteAndUiClosureModuleTest.php`

- [ ] RED：现代、`/user/withdraw_request`、`/user/withdraw_request_OTC` 均要求同一金额/密码/幂等/资金状态规则。
- [ ] RED：历史只读本人记录，并分别显示后台状态与 funding/refund 状态，不将 pending/unknown 显示为已完成。
- [ ] RED：重复提交页面事件不会生成第二笔 MT4 outbox；旧字段 aliases 仍映射准确。
- [ ] 实现状态文本和 UI 禁止重复提交；不新增静态成功或 fallback 分支。
- [ ] 运行目标测试、`FrontendRouteManifestTest` 和 `FrontUiRegressionTest`。

### Task 6: 全量回归、MySQL 严格模式与逐路由报告

**Files:**
- Create: `docs/reports/2026-07-12-front-withdrawal-route-execution-chain.md`
- Modify: `docs/admin-backend-blade-permission-final-checklist.md`
- Modify: `docs/superpowers/plans/2026-07-12-front-withdrawal-funding-closure.md`

- [ ] 逐文件运行所有文件名含 Withdraw/Withdrawal 的测试；PHPUnit 每次只传一个文件。
- [ ] information_schema 验证 InnoDB、DECIMAL、DATETIME、主键和唯一索引。
- [ ] fake MT4：create reservation -> debit success -> admin process -> admin reject -> refund success；重复 Job 不产生第二次资金操作。
- [ ] 报告每个现代/Legacy/后台路由的中间件、验证、Controller、Service、数据库、MT4、状态迁移和响应。
- [ ] 规格审查后再做质量审查；所有 Critical/Important/Minor 清零才封板。

### Plan self-review

- [x] 覆盖旧项目提交时 MT4 扣款语义，同时修复事务外调用和崩溃窗口。
- [x] 前台申请、worker、后台审批、拒绝退款、scanner、UI 和报告均有独立任务。
- [x] 所有生产逻辑前都有 RED 测试；每个测试文件独立运行。
- [x] 没有把真实 MT4 凭据写入源码或 fixture。
- [x] 当前目录不是 Git 仓库，验证门槛替代 commit 步骤，不伪造版本控制证据。
