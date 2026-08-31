# 长任务：账号生命周期 MT4 闭环

- 日期：2026-07-19
- 模式：连续闭环实现（不以“下一批建议”结束）

## 路由基线
- 旧路由审计：`395 routes / 0 gaps`（375 matched + 20 intentional method restrictions）

## 本轮实现

### 1. 用户启停 × MT4 lock/unlock
`AdminUserController@changeUserStatus`
```
is_enabled=0 → lockUser → 成功后 is_enabled=0, is_mt4_enabled=0, is_mt4_readonly=1
is_enabled=1 → unlockUser → 成功后 is_enabled=1, is_mt4_enabled=1, is_mt4_readonly=0
MT4 失败 → MT4_SYNC_FAILED，本地不变
```

### 2. 实名审核通过 × MT4 comment 同步
`AdminUserController@reviewAuth`
```
status=1 → updateComment(bank_no|bank_name|审核通过) → 成功后本地通过
status=2 拒绝 → 不调 MT4
MT4 失败 → 本地不改
```

### 3. 销户通过 × MT4 lock（前轮已合入）
`CancelApplyController@approve` 先 lockUser 再本地销户。

### 4. 出金 complete/reject 派发（前轮已合入）
complete → funding_status=completed  
reject debited → refund outbox + 立即 dispatch

## 测试（全部 OK）
- AdminUserStatusMt4SyncClosureModuleTest
- AdminAuthReviewMt4SyncClosureModuleTest
- AdminUserStatusRouteTargetBoundary / Validation
- AdminReviewAuthApproveState
- AdminAuthenticationModuleTest
- AdminCancelApply* MT4/Review
- AdminWithdrawalRejectRefundDispatch / FundingStateMachine

## 变更主文件
- `app/Http/Controllers/Admin/AdminUserController.php`
- `app/Http/Controllers/Admin/CancelApplyController.php`
- `app/Http/Controllers/Admin/WithdrawController.php`
- 对应 Feature 测试与 docs/reports

## 仍受数据模型限制（非空实现）
- RealtimeCommission 无 COMMENT 字段：无法伪造旧正则
- PositionSummary/FundFlow 无旧 COMMENT 分类：只输出真实字段
- 后台返佣 settle：旧项目亦多为状态位；前台 transfer 已有余额事务闭环
