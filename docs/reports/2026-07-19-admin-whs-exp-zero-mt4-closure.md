# 后台仓位清零 MT4 闭环

- 日期：2026-07-19
- 问题：`AdminWhsExpZeroController@oneKeyZero` 仅创建 `status=1` 待处理记录，不调用 MT4，与旧项目“入金补平负余额”语义不一致。

## 执行链路

```text
POST /api/admin/whsExpZero
  -> jwt.auth:admin + sso:admin + check.permission:admin
  -> AdminWhsExpZeroController@oneKeyZero
  -> 校验 user_id / 负余额 / 无持仓 / 无待处理记录 / 数据范围
  -> 计算 depositAmount:
       credit >= abs(balance) ? abs(balance) : abs(balance)-credit
  -> 事务写 whs_exp_zeros(status=1)
  -> DepositSettlementGateway::deposit(userId, amount, WHS_ZERO:{uid})
  -> 成功:
       status=2
       user_infos.total_funds = 0
       operation_logs(whs_exp_zero)
       返回 code=1000 + provider_reference
  -> 失败:
       status=3
       不改余额
       code=2025 MT4_SYNC_FAILED
```

## 变更

| 文件 | 说明 |
|---|---|
| `app/Http/Controllers/Admin/AdminWhsExpZeroController.php` | 注入网关、真实清零状态机 |
| `resources/lang/en|zh-CN/admin.php` | 清零文案 |
| `tests/Feature/AdminWhsExpZeroMt4ClosureModuleTest.php` | 成功/信用覆盖/失败 |
| `tests/Feature/AdminWhsExpZeroModuleTest.php` | 适配成功语义 |

## 测试

```text
AdminWhsExpZeroMt4ClosureModuleTest  OK (3, 19)
AdminWhsExpZeroModuleTest             OK (3, 24)
AdminWhsExpZeroUserIdValidation...    OK (4, 19)
AdminWhsExpZeroStatusFilter...       OK (2, 6)
```
