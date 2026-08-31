# 销户 MT4 锁定 + 出金退款派发闭环

- 日期：2026-07-19
- 原则：以新项目架构为准，补齐旧项目写路径语义，fail-closed。

## 1. 销户通过（CancelApply approve）

### 旧项目语义
通过销户前先 `_exte_sync_mt4_disable_user`，MT4 失败则审核失败。

### 新项目实现
```text
POST /api/admin/cancelApplyApprove/{id}
  -> 校验待处理申请
  -> Mt4ManagerService::lockUser(user_id)
  -> status!=ok → MT4_SYNC_FAILED，不改本地
  -> 事务:
       cancel_applies.status=1
       user_logins.is_cancelled=1, is_enabled=0
       user_infos.is_mt4_enabled=0, is_mt4_readonly=1
       soft-delete user_infos
       operation_logs
```

### 测试
```text
AdminCancelApplyApproveMt4ClosureModuleTest  OK (2, 13)
AdminCancelApplyReviewModuleTest             OK (2, 25)
AdminCancelApplyRouteIdValidation...         OK (3, 21)
```

## 2. 出金 reject/complete 与 funding 机

### complete
- `status=2` + `funding_status=completed`

### reject debited
- 创建 `withdraw_refund` pending outbox
- `RefundWithdrawFunding::dispatch(...)->afterCommit()`
- 时间戳使用 int unix（`time()`）

### 测试
```text
AdminWithdrawalRejectRefundDispatchClosureModuleTest OK (3, 13)
AdminWithdrawalFundingStateMachineClosureModuleTest  OK (10, 34)
DispatchPendingWithdrawSettlementsCommand...         OK (2, 20)
```

## 3. 变更文件

- `app/Http/Controllers/Admin/CancelApplyController.php`
- `app/Http/Controllers/Admin/WithdrawController.php`
- `tests/Feature/AdminCancelApplyApproveMt4ClosureModuleTest.php`
- `tests/Feature/AdminCancelApplyReviewModuleTest.php`
- `tests/Feature/AdminWithdrawalRejectRefundDispatchClosureModuleTest.php`
- `tests/Feature/DispatchPendingWithdrawSettlementsCommandClosureModuleTest.php`
