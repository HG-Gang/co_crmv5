# 子任务：修复返佣转账 Saga/人工核对/旧代理统计适配器测试族

> 执行者：fix_saga_reconciliation 智能体。请完整阅读本文件并执行，全部通过前不要结束。

## 项目环境

- 项目：`D:\Software\PhpProject\Demo\co_crmv5`（Laravel 8，PHP 8.0.2）
- 数据库：MySQL `127.0.0.1:3307`，库 `co_crmv5`，用户 `root`，密码 `123456`

## 需要修复的测试文件（全部必须 OK）

1. `tests\Feature\CommissionTransferSagaServiceTest.php`（4 个错误：table_fingerprint_mismatch，user_infos row_count 15361->15362 多出 1 行未清理，AUTO_INCREMENT mismatch expected 71225 actual 71226 等）
2. `tests\Feature\CommissionTransferManualReconciliationServiceTest.php`（6 个错误：Refusing to lower user_infos AUTO_INCREMENT: MAX(id)=71222 is not below original=71220；说明测试创建的 user_infos 行未被 tearDown 删除）
3. `tests\Feature\AdminLegacyAgentStatisticsAdapterTest.php`（7 个错误：user_logins/user_infos AUTO_INCREMENT mismatch 与 fingerprint mismatch）
4. `tests\Feature\CommissionTransferAtomicStorageMigrationTest.php`（当前通过，验证不回归）
5. `tests\Feature\AdminCommissionTransferReconciliationClosureModuleTest.php`（当前通过，验证不回归）

## 根因方向（必须读代码确认后再修）

- 测试通过服务或子进程创建了 user_infos/user_logins/commission_transfers/commission_transfer_outbox/commission_records/operation_logs 等行，但 tearDown 只按父进程记录的自动 ID 清理，导致残留。
- 部分测试可能通过 proc_open/子进程（php artisan worker）写库，父进程无法追踪这些行。
- 修复原则：优先让 tearDown 按业务键（reserved user ids、order/ticket/batch 等）跨表删除所有夹具行，再恢复 AUTO_INCREMENT；如测试真的派生子进程写库，应改为注入同步/模拟网关使子进程不再写库，或确保子进程完成且按业务键清理。不要削弱指纹校验语义，校验仍必须通过。
- 相关支持类：`tests\Support\MySqlTableFingerprint.php`、`tests\Support\MySqlAutoIncrementSnapshot.php`（restore 在 max(id)>=期望值时会拒绝降低，必须先删干净再恢复）、`tests\Support\MySqlFixtureMutex.php`（GET_LOCK 超时已改为 20 秒）。

## 执行协议

- 每次只跑一个文件：`php vendor\bin\phpunit tests\Feature\XxxTest.php`（不要一次传多个文件）。
- 运行前先杀残留 php：`Get-Process php -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue }`
- 若库被污染：`php scripts\cleanup-test-fixture-residue.php`（备份并清理测试残留，保留 admin id=1、roles 51/52/53）。
- 验证以 phpunit 的 "OK (N tests, M assertions)" 为准。
- 文件注释按 `docs\中文注释标准.md`：新增/修改代码必须带中文注释说明功能、入参、返回、失败场景。

## 范围边界

- 可修改：上述 5 个测试文件、`tests\Support\MySql*`（如有必要）、app 下与佣金转账/人工核对相关的 Service/Controller/Model（若测试证明业务代码有真实缺陷）。
- 不要修改支付、MT4、数据范围等其他模块文件；如确需跨模块修改，在最终答复中说明原因与建议，不要动手。

## 完成标准与报告

- 5 个文件全部 phpunit OK。
- 报告每个文件修改了什么、根因、最终测试数/断言数。
- 同一失败重试 3 次仍无法解决时，报告卡点细节并停止。
