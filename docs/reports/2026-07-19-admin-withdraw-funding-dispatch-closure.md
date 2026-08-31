# 后台出金状态机与 Funding 退款派发闭环

- 日期：2026-07-19
- 原则：以新项目 withdraw funding/outbox 架构为准，补齐旧项目审核动作后的资金闭环。

## 1. complete 终态

**变更**：`WithdrawController@complete` 在 `status=1 + funding_status=debited` 时：
- `status = 2`
- `funding_status = completed`
- 清除 `funding_error_code`

避免后台已完成但 funding 仍显示 debited 的状态分裂。

## 2. reject 后立即派发退款 Job

**缺口**：reject debited 仅创建 `withdraw_refund` pending outbox，依赖分钟级 scanner，审核后资金退回延迟。

**闭环**：
```text
POST withdrawReject (funding=debited)
  -> lock withdraw_records
  -> create/pending withdraw_refund outbox
  -> funding_status=refund_pending
  -> RefundWithdrawFunding::dispatch(outboxId)->afterCommit()
  -> Job 执行 MT4 反向入金
```

pending/retryable 拒绝：取消 debit outbox，不派发 refund。  
processing 拒绝：创建 blocked refund，等待 debit job 解阻（原有语义保留）。

## 3. 时间戳修正

`withdraw_settlement_outbox` 的 `available_at/processed_at/locked_at` 为 **int unix**。  
reject 写 outbox 时改为 `time()`，避免 `now()` Carbon 写入导致 SQL 截断 5000。

## 4. 测试

```text
AdminWithdrawalRejectRefundDispatchClosureModuleTest  OK (3, 13)
AdminWithdrawalFundingStateMachineClosureModuleTest  OK (10, 34)
ProcessWithdrawFundingJobClosureModuleTest           OK (6, 18)
RefundWithdrawFundingJobClosureModuleTest            OK (23, 99)
```

## 5. 变更文件

- `app/Http/Controllers/Admin/WithdrawController.php`
- `tests/Feature/AdminWithdrawalRejectRefundDispatchClosureModuleTest.php`
