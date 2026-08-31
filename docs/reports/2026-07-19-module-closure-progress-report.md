# 普通用户 / 代理商 / 后台管理员模块闭环进度报告

- 日期：2026-07-19
- 新项目：`D:\Software\PhpProject\Demo\co_crmv5`
- 旧项目：`D:\Php-project\Php\new_co_gmtk_crmV3`
- DB：`127.0.0.1:3307 / co_crmv5 / root`

## 1. 权威证据（非旧“未完成清单”）

| 检查 | 结果 |
|---|---|
| 旧路由审计 `legacy-routes:audit --scope=all` | **395 routes：375 matched + 20 intentional method restrictions + 0 gaps** |
| 运行时全量路由 | **791 routes / 1135 method declarations** |
| 全量路由链路报告 | `docs/reports/2026-07-19-full-route-execution-chain-report.md` |
| 出金 Task5 链路报告 | `docs/reports/2026-07-19-front-withdrawal-route-execution-chain.md` |
| 旧审计清单文件 | `docs/audits/2026-07-19-all-legacy-route-inventory.md` |

说明：`未完成功能清单.md`（2026-06-13）中多项 Controller 名称已过时；当前代码以 Risk/RealtimeCommission/CancelApply/OnlineUser/News 等新命名落地，并由 `LegacyAdminController` 兼容旧 URI。

## 2. 三端模块矩阵

| 端 | 路由映射 | 业务闭环测试样本 | 状态 |
|---|---|---|---|
| 普通用户 Front | 已注册现代 API + Legacy 兼容 | FrontAuth/Deposit/Profile/Withdraw/Agent/Legacy 等 100+ 文件抽样通过 | **主链路闭环** |
| 代理商 | Front Agent + BigNumber + Admin Agent | FrontAgent* / AdminAgent* / BigAgent 相关通过 | **主链路闭环** |
| 后台管理员 | Admin API + Blade 页 + Legacy admin | AdminUser/Auth/Deposit/Withdraw/Agent 等通过 | **主链路闭环** |
| 出金资金闭环 | 申请→outbox→Job→审核/退款 | Schema/Settlement/Admin state machine/Job/scanner 通过 | **本轮补齐 Task5 展示与 Legacy 统一规则** |

## 3. 本轮实际完成项

1. **Task5 出金 Legacy/UI 闭环**
   - 新增测试：`tests/Feature/FrontWithdrawalLegacyRouteAndUiClosureModuleTest.php`
   - 历史接口增加 `funding_status` / `funding_status_text`
   - Layui 历史表增加资金状态列
   - 文案：`FrontLegacyData::withdrawFundingStatusText()` + en/zh-CN
   - 验证：`OK (3 tests, 46 assertions)`

2. **Settlement 测试共享库竞态修复**
   - 文件：`tests/Feature/FrontWithdrawSettlementClosureModuleTest.php`
   - AUTO_INCREMENT 恢复改为 `max(snapshot, max(id)+1)` 钳制
   - 验证：`OK (128 tests, 958 assertions)`

3. **路由审计与全量链路报告刷新**
   - 395 旧路由 0 gaps
   - 791 新路由执行链路报告已生成

4. **后台风险强平真实逻辑**
   - 消除 `RiskController@forceClose` 伪成功
   - 新增 `RiskForceCloseGateway` + `Mt4RiskForceCloseGateway` + `ORDER_CLOSE`
   - 失败 fail-closed；成功写 `operation_logs`
   - 报告：`docs/reports/2026-07-19-admin-risk-force-close-closure.md`
   - 测试：`AdminRiskForceCloseGatewayClosureModuleTest` OK (3/12)

5. **后台仓位清零 MT4 闭环（自主审查新增）**
   - `oneKeyZero` 从“只建待处理记录”改为：计算补入金额 → MT4 deposit → status=2/3 → 本地余额镜像
   - 报告：`docs/reports/2026-07-19-admin-whs-exp-zero-mt4-closure.md`
   - 测试：`AdminWhsExpZeroMt4ClosureModuleTest` + 相关回归全部 OK

6. **入金审核 Settlement 对齐 + 批量导入卡死恢复**
   - `depositApprove` 改为 payment_success + pending outbox + SettleDepositPayment（不再直接伪 status=02）
   - 金额/信用导入：`is_synced=3` 超时后可 retry/sync reclaim
   - 报告：`docs/reports/2026-07-19-deposit-approve-and-import-recovery-closure.md`

7. **后台出金 complete/reject 与 funding 机闭环**
   - complete：`status=2` + `funding_status=completed`
   - reject debited：创建 refund outbox 并 `RefundWithdrawFunding::dispatch`（不再只等 scanner）
   - 修复 outbox 时间戳写 int（避免 Carbon/now 导致 5000）
   - 报告：`docs/reports/2026-07-19-admin-withdraw-funding-dispatch-closure.md`

8. **销户通过 MT4 锁定闭环**
   - approve 前 `lockUser`；失败不改本地
   - 成功：禁用登录 + MT4 只读禁用 + 软删 + 审计
   - 报告：`docs/reports/2026-07-19-cancel-apply-mt4-and-withdraw-dispatch-closure.md`

9. **用户启停 / 实名审核 MT4 同步（长任务）**
   - changeUserStatus：lock/unlock + is_mt4_enabled/readonly
   - reviewAuth 通过：updateComment 同步银行卡摘要
   - 报告：`docs/reports/2026-07-19-long-task-mt4-account-lifecycle-closure.md`

10. **MT4 协议根因修复（关键）**
   - 根因：新客户端使用错误的 `USER_DEPOSIT:k=v|` 协议，旧服务只认 `Eact=&ver=&key=\r\nQUIT`
   - 已按旧 Abstract_Service_Controller / Mt4ManagerService 重写 wire protocol
   - 报告：`docs/reports/2026-07-19-mt4-legacy-protocol-root-cause-fix.md`
   - 相关 PHPUnit 全绿

## 4. 关键执行链路（出金示例，完整见专项报告）

```text
POST /api/front/withdrawals/submissions
  -> jwt.auth:user / sso:user
  -> WithdrawController@submitWithdraw
  -> WithdrawalOrderService::createOrRetrieve
  -> withdraw_records(funding_status=pending)
  -> withdraw_settlement_outbox(event_type=withdraw_debit)
  -> 1001 withdrawal_funding_pending

GET /api/front/withdrawals/history
  -> WithdrawController@withdrawHistory
  -> 仅当前用户
  -> status_text + funding_status_text 分离展示

POST /user/withdraw_request[|_OTC]
  -> 字段别名映射
  -> 同一 submitWithdraw 规则
```

## 5. 验证命令

```bash
php artisan legacy-routes:audit storage/app/audits/legacy-routes.json --scope=all --policy=docs/audits/legacy-route-method-policy.json
php scripts/generate-full-route-execution-chain-report.php docs/reports/2026-07-19-full-route-execution-chain-report.md
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FrontWithdrawalLegacyRouteAndUiClosureModuleTest.php
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FrontWithdrawSettlementClosureModuleTest.php
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/AdminWithdrawalFundingStateMachineClosureModuleTest.php
```

抽样模块回归（FrontAgent/Auth/Deposit/Profile + AdminAgent/User/Auth/Withdraw/Deposit + Legacy）：**105 OK / 1 瞬时失败后重跑通过**。

## 6. 仍未达到“百分百声明完成”的项

| 项 | 说明 |
|---|---|
| 全量 2103 PHPUnit 单进程绿通 | 本机会话超时/共享库与 Node 进程清理偶发噪声；未在单次 run 中拿到最终 `OK (2103 tests)` |
| `FrontWithdrawIdempotencyJavascriptClosureModuleTest` | Windows Node 进程树终止偶发超时；业务用例单独可过 |
| 真实 MT4/支付通道生产配置 | 以 fake gateway 测通；生产密钥/端点属部署项 |
| 浏览器端到端冒烟 | 未在本轮执行真实浏览器登录全路径 |

因此：**业务主链路与旧路由映射可认为闭环，但不能诚实声明“零风险全量 100% 完成”。**

## 7. 建议下一跳

1. 独占测试库或 CI 串行 worker，跑完整 `phpunit` 并固化 JUnit。
2. 给 JS runner 提高 Windows 进程树超时或改用 job-object 清理，消掉 flaky。
3. 关键页最小冒烟：登录→出金→历史→后台审核→拒绝退款。
4. 再生成最终报告时填入真实 wall-clock 秒数与 token 账单。
