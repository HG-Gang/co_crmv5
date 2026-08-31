# 前台出金资金闭环设计

## 背景

旧项目 `UserWithdrawController` 在用户提交时校验风险、余额和密码，然后调用 MT4 `USER_WITHDRAW` 扣款，只有 MT4 明确成功才写本地出金记录。新项目当前只写 `withdraw_records` pending，既没有真实扣款，也没有并发 reservation、幂等键或不确定结果处理。

## 目标

- 保留旧项目“真实 MT4 扣款后才进入后台审核”的资金语义。
- 让普通用户现代/Legacy/OTC 入口共用一套精确金额、密码、风险和所有权规则。
- 解决并发超额申请、重复 MT4 扣款、外部调用与本地提交之间的崩溃窗口。
- 后台 `process/complete/reject` 只能推进合法状态；已扣款的拒绝必须通过 MT4 反向入金退款。
- MT4 连接前失败可重试；写入后失败、读超时、进程崩溃窗口进入 `unknown`，禁止自动重复资金操作。

## 方案比较

### 方案 A：同步 HTTP 内直接 MT4 扣款

与旧代码最接近，但网络调用和本地事务难以原子化；响应断开或本地写失败会导致无法判断 MT4 是否已扣款，不能满足不确定结果禁止重复的要求。

### 方案 B：本地 reservation + outbox + MT4 worker（采用）

申请事务只创建唯一订单和 reservation outbox；worker 在事务外调用 MT4，明确成功后在事务内写 ticket、扣减本地镜像余额并进入后台审核。明确拒绝、可重试和 unknown 分别落不同状态。后台拒绝通过独立 refund outbox 调用 MT4 `USER_DEPOSIT`。该方案可恢复、可审计且每个外部调用只有一个幂等业务事件。

### 方案 C：后台审批后才调用 MT4

会让“申请成功”阶段没有资金冻结，不能防止用户在多个 pending 申请中超额申请，也不符合旧项目扣款时机。

## 状态与数据

`withdraw_records.status` 保留后台状态：`0=pending`、`1=processing`、`2=completed`、`3=rejected/failed`。

新增 `funding_status`：

- `pending`：outbox 待扣款。
- `processing`：MT4 withdrawal 已被 claim，外部调用进行中。
- `debited`：明确扣款成功，有数字 `mt4_ticket`，等待后台处理。
- `retryable`：明确未发送，可安全重试。
- `unknown`：写入/读取超时或本地提交失败，禁止自动重试。
- `refund_pending` / `refund_processing` / `refunded` / `refund_unknown`：拒绝后的反向入金状态。
- `rejected`：MT4 明确拒绝且没有资金被扣。
- `cancelled`：扣款前后台取消申请。

新增字段：`idempotency_key`、`funding_status`、`funding_payload_hash`、`refund_mt4_ticket`、`refund_time`、`funding_error_code`。金额字段改为 `DECIMAL(18,2)`，汇率改为 `DECIMAL(18,8)`；`local_order_no` 唯一，`idempotency_key + user_id` 唯一。

新增 `withdraw_settlement_outbox`：`event_type` (`withdraw_debit`/`withdraw_refund`) 与 `withdraw_record_id` 唯一，状态为 pending/processing/retryable/unknown/rejected/processed/blocked/cancelled。

## 创建链路

1. `withdrawPage` 只展示当前用户数据，不能写资金。
2. `submitWithdraw`/Legacy aliases 解析认证用户、密码和幂等 key。
3. `Money::fromDecimalString` 拒绝浮点、科学计数、三位小数、零、负数和越界。
4. 在数据库事务外通过只读 MT4 account snapshot 获取实时 balance/free margin；快照失败时 fail-closed，不创建订单。
5. 事务锁 `user_infos`，以实时 `min(balance, free_margin)` 减去当前用户 pending/processing/unknown reservation；余额不足即拒绝。并发请求即使读到同一远端快照，也会被事务内 reservation 串行化。
6. 校验实名/银行卡审核状态、出金开关、风险率、未平仓订单、限额和费用。
7. 创建唯一 withdraw record 与 debit outbox；事务内不调用外部服务。
8. dispatch `ProcessWithdrawFunding` afterCommit；同一幂等 key 重放只返回原订单。

## MT4 worker 链路

1. 事务锁 outbox/order，只有 pending/retryable 且资金状态合法时 claim 为 processing。
2. 事务外调用 `WithdrawalFundingGateway::withdraw()`。
3. 明确成功：事务内写数字 ticket、`funding_status=debited`、outbox processed；不直接加减 `user_infos` MT4 镜像。
4. connection_failed：`retryable`，设置退避。
5. write_failed/read_timeout/transport exception/缺少 ticket：`unknown`，保留 reservation，不自动再次调用。
6. provider rejected：`rejected`，withdraw status=3，记录错误原因，不需要退款。

## 后台链路

- `process`：只允许 `status=0` 且 `funding_status=debited`，原子改为 1。
- `complete`：只允许 `status=1` 且 `funding_status=debited`，原子改为 2；不再伪造 MT4 成功。
- `reject`：
  - funding pending/retryable：原子取消 debit outbox，status=3。
  - funding debited：创建唯一 refund outbox，状态进入 refund_pending；不得立即返回已拒绝完成。
  - funding processing：创建 blocked refund，待 debit 终态后协调。
  - funding unknown：拒绝自动处理，返回人工对账错误。

## 退款链路

`RefundWithdrawFunding` 事务 claim -> 事务外 MT4 `deposit()` -> 明确成功写 refund ticket/time、status=3/funding_status=refunded；不直接加减 `user_infos` MT4 镜像。明确未发送可重试；不确定结果为 refund_unknown，禁止自动重复。

## 失败与幂等不变量

- 任何外部调用都不在数据库事务内。
- 同一 outbox 只能被一个 worker claim；同一事件不会再次产生第二笔 MT4 操作。
- `unknown` 状态不会被 scanner 自动重新派发。
- unknown reservation 持续占用本地可申请额度，直到人工对账明确资金结果；这可能保守拒绝新申请，但不会放大可用余额。
- status=2 必须有 funding_status=debited；status=3 且原先 debited 必须有 refunded 或人工对账证据。
- 用户只能读自己的订单；管理员操作必须经过 AdminDataScopeService。

## 测试策略

先写 RED 测试，再实现：金额和密码边界、幂等重放、并发 reservation、MT4 结果分类、崩溃窗口、后台合法状态、拒绝退款、unknown 禁止重试、用户/管理员数据范围、MySQL DECIMAL/InnoDB/唯一索引和 Legacy aliases。
