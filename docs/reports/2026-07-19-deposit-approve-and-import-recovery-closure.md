# 后台入金审核 Settlement 对齐 + 批量导入卡死恢复闭环

- 日期：2026-07-19
- 原则：以新项目 settlement/outbox 架构为准，补齐旧项目“审核后入账 / 失败可恢复”语义，禁止伪成功。

## 1. 批量导入失败恢复（金额 + 信用）

### 缺口
`is_synced=3`（processing）在请求中断后无法 retry/sync，记录永久卡死。

### 闭环
| 状态 | 行为 |
|---|---|
| `0` pending | 可 sync |
| `1` success | 不可 retry/sync |
| `2` failed | 可 retry → 0 |
| `3` processing 且 `updated_at` > 5 分钟 | 可 retry→0 或 sync 直接 reclaim |
| `3` processing 且未过期 | 拒绝（防并发双写） |

### 变更文件
- `app/Http/Controllers/Admin/BatchAmountImportController.php`
- `app/Http/Controllers/Admin/BatchCreditImportController.php`
- `tests/Feature/AdminBatchAmountImportStuckProcessingRecoveryClosureModuleTest.php`

### 测试
```text
AdminBatchAmountImportStuckProcessingRecoveryClosureModuleTest  OK (3, 14)
AdminBatchAmountImportRetryModuleTest                            OK (7, 49)
AdminBatchAmountImportMt4SyncClosureModuleTest                   OK (7, 53)
```

## 2. 后台入金审核与 Settlement 状态机对齐

### 缺口
`DepositController@approve` 直接把 `status=02`，**不入队 MT4 结算**，与前台支付回调路径（payment_success → outbox → SettleDepositPayment → status=02）不一致。

### 新口径（以新项目为准）
```text
POST /api/admin/depositApprove
  -> 校验 id / 数据范围
  -> 已 settled 或 status=02 → 拒绝重复
  -> settlement processing/unknown → 拒绝
  -> 事务:
       payment_status = success
       settlement_status = pending
       payment_time = now
       status 保持 01（未伪造已入账）
       payment_settlement_outbox(deposit_settlement) pending
  -> dispatch SettleDepositPayment
  -> Job 成功后才把 status=02 + mt4_ticket
```

### 变更文件
- `app/Http/Controllers/Admin/DepositController.php`
- `resources/lang/en|zh-CN/admin.php`（`deposit_settlement_in_progress`）
- `tests/Feature/AdminDepositApproveSettlementClosureModuleTest.php`

### 测试
```text
AdminDepositApproveSettlementClosureModuleTest   OK (3, 13)
AdminDepositRequestIdValidationClosureModuleTest OK (4, 18)
SettleDepositPaymentJobClosureModuleTest         OK (27, 126)
```

## 3. 说明

- 前台在线支付仍走回调 → outbox；后台线下/人工审核现已走同一 settlement 机。
- 批量导入仍用同步 API（非 outbox），但补齐了 processing 卡死恢复，避免运维死锁。
