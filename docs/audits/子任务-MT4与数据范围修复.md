# 子任务：修复 MT4 账号供应与后台数据范围测试族

> 执行者：fix_mt4_datascope 智能体。请完整阅读本文件并执行，全部通过前不要结束。

## 项目环境

- 项目：`D:\Software\PhpProject\Demo\co_crmv5`（Laravel 8，PHP 8.0.2）
- 数据库：MySQL `127.0.0.1:3307`，库 `co_crmv5`，用户 `root`，密码 `123456`

## 需要处理的测试文件（全部必须 OK）

1. `tests\Feature\UserMt4ProvisioningMigrationClosureModuleTest.php`（当前执行早期即中断，可能是挂起或崩溃，需诊断根因并修复）
2. `tests\Feature\UserMt4ProvisioningRuntimeClosureModuleTest.php`（当前单文件通过 37 tests，验证不回归）
3. `tests\Feature\AdminAgentStatsDataScopeTest.php`（当前单文件通过 4 tests，验证不回归）
4. `tests\Feature\AdminDataScopeServiceTest.php`（当前单文件通过 6 tests，验证不回归）
5. `tests\Feature\FrontRegisterVerificationLifecycleClosureModuleTest.php`（当前单文件通过 19 tests，验证不回归）

## 背景与根因方向

- 这些测试使用 `tests\Support\MySqlFixtureMutex`（GET_LOCK 超时已改为 20 秒，避免被执行环境看门狗误杀）、`MySqlTableFingerprint`、`MySqlAutoIncrementSnapshot` 做夹具闭环校验。
- 历史问题：测试进程被中断后遗留孤儿连接与残留行会导致后续测试指纹不匹配或自增序列无法恢复。若某文件单独运行通过但在前序残留时失败，优先保证其自身清理逻辑按业务键删净所有行（user_id、login_id、ticket、binding id 等），再恢复 AUTO_INCREMENT。
- 特别关注 `UserMt4ProvisioningMigrationClosureModuleTest`：它可能触发真实迁移或外部 MT4 服务调用导致挂起；若挂起源是外部网络/服务调用，应确认测试注入的是模拟网关（不发起真实 TCP 连接）；若确有真实调用，检查超时配置并收紧。

## 执行协议

- 每次只跑一个文件：`php vendor\bin\phpunit tests\Feature\XxxTest.php`（不要一次传多个文件）。
- 运行前先杀残留 php：`Get-Process php -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue }`
- 若库被污染：`php scripts\cleanup-test-fixture-residue.php`（备份并清理测试残留，保留 admin id=1、roles 51/52/53）。
- 验证以 phpunit 的 "OK (N tests, M assertions)" 为准。
- 文件注释按 `docs\中文注释标准.md`：新增/修改代码必须带中文注释说明功能、入参、返回、失败场景。

## 范围边界

- 可修改：上述 5 个测试文件、`tests\Support\MySql*`（如有必要）、app 下 MT4 供应/注册/后台数据范围相关 Service/Controller/Model（若测试证明业务代码有真实缺陷）。
- 不要修改佣金 Saga、支付网关等其他模块文件；如确需跨模块修改，在最终答复中说明原因与建议，不要动手。

## 完成标准与报告

- 5 个文件全部 phpunit OK。
- 报告每个文件修改了什么、根因、最终测试数/断言数。
- 同一失败重试 3 次仍无法解决时，报告卡点细节并停止。
