# 前台出金路由执行链路报告（Task5 闭环）

- 生成时间：2026-07-19
- 项目：`D:\Software\PhpProject\Demo\co_crmv5`
- 旧项目对照：`D:\Php-project\Php\new_co_gmtk_crmV3`
- 范围：普通用户出金申请/历史 + Legacy 兼容入口 + 资金状态展示

## 1. 闭环结论

| 项 | 状态 |
|---|---|
| 现代提交 `/api/front/withdrawals/submissions` | 已闭环 |
| 历史 `/api/front/withdrawals/history` | 已闭环（审核状态 + 资金状态分离） |
| Legacy `/user/withdraw_request` | 已闭环（映射到 submitWithdraw） |
| Legacy OTC `/user/withdraw_request_OTC` | 已闭环（复用 withdraw_request） |
| 幂等 `Idempotency-Key` | 已闭环 |
| 资金 outbox `withdraw_debit` | 已闭环 |
| Layui 历史列 `funding_status_text` | 已闭环 |
| 目标测试 `FrontWithdrawalLegacyRouteAndUiClosureModuleTest` | OK (3 tests, 46 assertions) |

## 2. 路由方法执行链路

### 2.1 POST `/api/front/withdrawals/submissions`

```
HTTP POST /api/front/withdrawals/submissions
  -> HTTP Kernel 全局中间件
  -> api/front 中间件组（jwt.auth:user, sso:user 等）
  -> Front\WithdrawController@submitWithdraw
  -> 校验 Idempotency-Key / 金额 / 密码 / agree
  -> WithdrawalOrderService::replayExisting 或 createOrRetrieve
  -> 事务内写 withdraw_records(funding_status=pending) + withdraw_settlement_outbox(event_type=withdraw_debit)
  -> JsonResponse code=1001, message=response.withdrawal_funding_pending
```

关键边界：

- 身份只来自登录上下文，请求体 `user_id` 不可覆盖。
- 金额必须为普通十进制字符串；非法金额不写库。
- 同 key+用户+金额回放原订单；同 key 不同金额冲突。
- 不把 pending/unknown 伪装为已完成。

### 2.2 GET `/api/front/withdrawals/history`

```
HTTP GET /api/front/withdrawals/history
  -> HTTP Kernel 全局中间件
  -> api/front 中间件组
  -> Front\WithdrawController@withdrawHistory
  -> WithdrawRecord::where(user_id = 当前用户)
  -> status / 时间筛选
  -> through() 组装展示字段
  -> FrontLegacyData::paginatedListResponse
  -> JsonResponse code=1000
```

新增/明确字段：

| 字段 | 含义 |
|---|---|
| `status` / `status_text` | 后台审核状态 |
| `funding_status` / `funding_status_text` | 资金处理状态 |
| `order_no` | local_order_no 优先 |
| `applyamount` / `actdraw` / `drawpoundage` | 金额展示 |

pending/unknown 的 `funding_status_text` 不得显示为“已完成/Completed”。

### 2.3 POST `/user/withdraw_request`

```
HTTP POST /user/withdraw_request
  -> web/legacy front 中间件
  -> Front\WithdrawController@withdraw_request
  -> 映射 amount/password/agree 别名
  -> submitWithdraw（同一服务与同一幂等规则）
```

### 2.4 POST `/user/withdraw_request_OTC`

```
HTTP POST /user/withdraw_request_OTC
  -> web/legacy front 中间件
  -> Front\WithdrawController@withdraw_request_OTC
  -> withdraw_request
  -> submitWithdraw
```

### 2.5 GET 前台出金页（Layui）

```
HTTP GET front withdraw page
  -> web
  -> Blade 页面
  -> public/js/apps/front/layui/pages.js
  -> submitWithdraw 使用 Idempotency-Key
  -> history 表渲染 status_text + funding_status_text
```

## 3. 后台资金状态机衔接（已存在，本轮回归）

| 动作 | 前置 | 结果 |
|---|---|---|
| Job `ProcessWithdrawFunding` | outbox withdraw_debit pending | pending/retryable -> processing -> debited/unknown/rejected |
| Admin process | status=0 + funding_status=debited | status=1 |
| Admin complete | status=1 + debited | 完成 |
| Admin reject | pending/retryable | cancelled；debited -> refund_pending |
| Job `RefundWithdrawFunding` | refund_pending | refunded / refund_unknown / refund_rejected |
| Command `payments:dispatch-withdraw-settlements` | pending/retryable outbox | 派发 Job |

## 4. 本轮变更文件

| 文件 | 变更 |
|---|---|
| `app/Support/FrontLegacyData.php` | 新增 `withdrawFundingStatusText()` |
| `app/Http/Controllers/Front/WithdrawController.php` | 历史响应增加 funding 字段 |
| `public/js/apps/front/layui/pages.js` | 历史表增加资金状态列 |
| `resources/lang/en/front.php` | funding 文案 |
| `resources/lang/zh-CN/front.php` | funding 文案 |
| `tests/Feature/FrontWithdrawalLegacyRouteAndUiClosureModuleTest.php` | Task5 闭环测试（新建） |

## 5. 验证命令与结果

```text
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FrontWithdrawalLegacyRouteAndUiClosureModuleTest.php
# OK (3 tests, 46 assertions)

php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FrontWithdrawSettlementClosureModuleTest.php
# OK (128 tests, 958 assertions)
# 已修复共享库 AUTO_INCREMENT 恢复：按 max(id)+1 钳制，避免并发写入导致 tearDown 误失败

# 出金相关逐文件回归（21 文件）
# 业务文件稳定通过；FrontWithdrawIdempotencyJavascriptClosureModuleTest
# 在 Windows 上偶发 Node 进程树终止超时（completed=yes 但 exit=3），单独重跑可通过
```

旧路由全量审计：

```text
php artisan legacy-routes:audit storage/app/audits/legacy-routes.json --scope=all --policy=docs/audits/legacy-route-method-policy.json
# Audited 395 legacy routes: 375 matched, 20 intentional method restrictions, 0 gaps.
```

全量路由链路报告：

```text
php scripts/generate-full-route-execution-chain-report.php docs/reports/2026-07-19-full-route-execution-chain-report.md
# routes=791 methods=1135
```

## 6. 未完成/风险

1. `FrontWithdrawSettlementClosureModuleTest` 在共享库上偶发 AUTO_INCREMENT 清理失败（tearDown 竞态），需独占库或降低 AI 强恢复策略后才能稳定 128/128。
2. 全量 2103 PHPUnit 需在无并发、长超时环境重跑；本会话未完成单进程全量绿通。
3. 真实 MT4 通道依赖外部服务，资金 Job 使用 fake gateway 测通，生产密钥/端点仍属部署项。
