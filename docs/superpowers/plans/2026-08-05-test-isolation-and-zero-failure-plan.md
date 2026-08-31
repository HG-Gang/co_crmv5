# 测试隔离与全量零失败实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 建立绝不会误写 `co_crmv5`/`hank_zl_data` 的专用测试入口，修复当前全量 PHPUnit 的 30 个 Failure 与 2 个 Error，并以当前运行结果而非历史报告证明零失败。

**Architecture:** 测试进程在 Laravel 完成配置加载后、任何测试方法运行前执行数据库身份和 MT4 双开关门禁；运行器只把 `co_crmv5_test` 作为可重建目标。失败修复按根因域逐项执行 TDD，先证明历史症状，再修改最小范围夹具或结构生命周期，不放宽业务断言。

**Tech Stack:** Laravel 8.83、PHP 8.0.2、PHPUnit 9.6、MySQL 8、PowerShell 5+。

**Git 约束:** 本计划禁止执行 `git add`、`git commit`、`git push`；`docs/` 仅本地保存且不得进入远端。

---

## 文件职责

- `tests/Support/TestDatabaseGuard.php`：纯值数据库身份门禁，不建立数据库连接。
- `tests/Unit/TestDatabaseGuardTest.php`：证明正式库、旧库、非 testing 环境和 MT4 开关开启时均失败关闭。
- `tests/CreatesApplication.php`：Laravel 启动完成后调用门禁，阻止任何 Feature 测试继续。
- `phpunit.xml`：固定 `co_crmv5_test`，并把两个 MT4 开关固定为 false。
- `scripts/prepare-test-database.php`：仅创建精确命名的测试库，不删除正式库。
- `scripts/run-full-serial.ps1`：准备、重建、执行完整套件并传递真实退出码。
- `scripts/run-tests-one-by-one.ps1`：只发现 `*Test.php`，保留 stderr，任一失败返回非零。
- `tests/Feature/FrontAuthRegistrationLoginClosureTest.php`：完整拥有并清理注册产生的 Outbox。
- `tests/Feature/FrontProfileClosureTest.php`：完整拥有并清理注册产生的 Outbox。
- `tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php`：移除 DDL 与事务混用，只验证已安装 schema。
- `tests/Feature/FrontDepositPaymentOrderIdempotencyClosureModuleTest.php`：恢复迁移测试触及的全部 schema 指纹。

### Task 1: 数据库身份与 MT4 失败关闭门禁

**Files:**
- Create: `tests/Support/TestDatabaseGuard.php`
- Create: `tests/Unit/TestDatabaseGuardTest.php`
- Modify: `tests/CreatesApplication.php`
- Modify: `phpunit.xml`

- [ ] **Step 1: 写 class-exists RED 测试**

```php
public function test_guard_contract_exists(): void
{
    $this->assertTrue(class_exists(\Tests\Support\TestDatabaseGuard::class));
}
```

- [ ] **Step 2: 运行并确认因类不存在而失败**

Run: `php vendor/bin/phpunit tests/Unit/TestDatabaseGuardTest.php --colors=never`

Expected: `FAIL`，失败原因为 `class_exists` 返回 false，不是 PHP 语法或自动加载错误。

- [ ] **Step 3: 创建最小类后补齐行为 RED 测试**

测试矩阵必须覆盖：

```php
['testing', 'mysql', 'co_crmv5_test', false, false] // 唯一允许
['local', 'mysql', 'co_crmv5_test', false, false]   // 拒绝非 testing
['testing', 'mysql', 'co_crmv5', false, false]      // 拒绝新项目正式库
['testing', 'mysql', 'hank_zl_data', false, false]  // 拒绝旧项目源库
['testing', 'mysql', 'co_crmv5_qa', false, false]   // 拒绝近似名称
['testing', 'sqlite', 'co_crmv5_test', false, false]// 拒绝驱动漂移
['testing', 'mysql', 'co_crmv5_test', true, false]  // 拒绝 MT4 总开关
['testing', 'mysql', 'co_crmv5_test', false, true]  // 拒绝用户同步开关
```

允许项无返回值；拒绝项抛 `RuntimeException`，错误文本包含实际环境、驱动、数据库名或开启的开关，不包含密码。

- [ ] **Step 4: 运行行为测试并确认当前实现失败**

Run: `php vendor/bin/phpunit tests/Unit/TestDatabaseGuardTest.php --colors=never`

Expected: 至少一个拒绝场景 `FAIL`，证明测试能识别不安全配置。

- [ ] **Step 5: 实现最小门禁并写中文边界注释**

公开接口固定为：

```php
public static function assertSafe(
    string $environment,
    string $driver,
    string $database,
    bool $mt4Enabled,
    bool $mt4UserSyncEnabled
): void
```

实现必须使用精确全等判断，只允许 `testing/mysql/co_crmv5_test/false/false`；不得使用 `_test` 后缀匹配、静默默认值或捕获后继续。

- [ ] **Step 6: 接入 Laravel 启动链并固定 phpunit 配置**

`CreatesApplication::createApplication()` 在 `$app->make(Kernel::class)->bootstrap()` 后、`return $app` 前读取：

```php
$environment = (string) $app->environment();
$connection = (string) config('database.default');
$driver = (string) config("database.connections.{$connection}.driver");
$database = (string) config("database.connections.{$connection}.database");
$mt4Enabled = (bool) config('mt4.enabled', false);
$mt4UserSyncEnabled = (bool) config('mt4.user_sync_enabled', false);
```

`phpunit.xml` 明确设置：

```xml
<server name="DB_CONNECTION" value="mysql" force="true"/>
<server name="DB_DATABASE" value="co_crmv5_test" force="true"/>
<server name="MT4_ENABLED" value="false" force="true"/>
<server name="MT4_USER_SYNC_ENABLED" value="false" force="true"/>
```

- [ ] **Step 7: 验证 GREEN 与反向保护**

Run: `php vendor/bin/phpunit tests/Unit/TestDatabaseGuardTest.php --colors=never`

Expected: 全部通过。

Run: `$env:DB_DATABASE='co_crmv5'; php vendor/bin/phpunit tests/Feature/ExampleTest.php --colors=never`

Expected: 即使外部环境试图覆盖，也由 `force=true` 落到 `co_crmv5_test`；门禁不得连接或修改 `co_crmv5`。

### Task 2: 可重复准备测试库与可信运行器

**Files:**
- Create: `scripts/prepare-test-database.php`
- Modify: `scripts/run-full-serial.ps1`
- Modify: `scripts/run-tests-one-by-one.ps1`
- Create: `tests/Unit/TestRunnerContractTest.php`

- [ ] **Step 1: 写运行器契约 RED 测试**

测试读取三个脚本源码并断言：只出现 `co_crmv5_test` 作为可重建库；禁止 `co_crmv5_qa`；逐文件发现规则为 `*Test.php`；汇总后存在显式非零退出；MT4 两个开关均为 false；PHP 路径不能硬编码到个人目录。

- [ ] **Step 2: 运行并确认历史脚本违反契约**

Run: `php vendor/bin/phpunit tests/Unit/TestRunnerContractTest.php --colors=never`

Expected: 因 `co_crmv5_qa`、`*.php`、硬编码 PHP 路径或缺失非零退出而 `FAIL`。

- [ ] **Step 3: 实现测试库准备脚本**

准备脚本必须：加载项目 `.env` 的主机、端口、账号和密码；目标库名只接受 `co_crmv5_test`；PDO DSN 不预选数据库；执行参数化前已完成标识符白名单；只执行 `CREATE DATABASE IF NOT EXISTS co_crmv5_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`。连接或建库失败原样抛出并返回非零，不输出密码。

- [ ] **Step 4: 修正全量运行器**

运行顺序固定为：定位项目根和当前 `php`；设置测试环境及 MT4 false；执行准备脚本；执行 `artisan migrate:fresh --seed --force`；只有重建退出 0 才执行 PHPUnit；保存 stdout、stderr、退出码；最终 `exit $testExit`。

- [ ] **Step 5: 修正逐文件运行器**

发现规则固定为 `Get-ChildItem ... -Filter '*Test.php'`；每个文件同时记录 stdout/stderr 与 `$LASTEXITCODE`；退出 0 且存在成功摘要才记 `OK`；非零且有 PHPUnit 失败摘要记 `FAIL`；无可识别摘要记 `CRASH`；`FAIL + CRASH > 0` 时脚本 `exit 1`。

- [ ] **Step 6: 验证运行器契约 GREEN**

Run: `php vendor/bin/phpunit tests/Unit/TestRunnerContractTest.php --colors=never`

Expected: 全部通过。

### Task 3: 注册与个人中心夹具拥有 Outbox 全生命周期

**Files:**
- Modify: `tests/Feature/FrontAuthRegistrationLoginClosureTest.php`
- Modify: `tests/Feature/FrontProfileClosureTest.php`

- [ ] **Step 1: 在干净测试库复现唯一键冲突**

先运行同一测试文件两次；第二次必须能证明历史实现会在复用 `id_sequences` 产生的业务 ID 时命中 `user_mt4_provisioning_user_unique`，日志仅记录异常类型和业务 ID，不输出加密 payload。

- [ ] **Step 2: 写夹具完整性 RED 断言**

每次注册完成后记录 `$testUserId`；清理完成后断言以下表均不存在该用户：`user_mt4_provisioning_outbox`、`agent_descendants`、`user_auths`、`user_infos`、`user_logins`。Outbox 必须先于父表删除。

- [ ] **Step 3: 实现最小清理修复**

两个测试的 stale 清理和 `tearDown()` 都先执行：

```php
UserMt4ProvisioningOutbox::where('user_id', $userId)->forceDelete();
```

然后清理后代关系、认证、资料和登录记录。不得改注册服务的唯一约束，不得吞掉清理异常。

- [ ] **Step 4: 单文件连续两次验证 GREEN**

Run twice:

`php vendor/bin/phpunit tests/Feature/FrontAuthRegistrationLoginClosureTest.php --colors=never`

`php vendor/bin/phpunit tests/Feature/FrontProfileClosureTest.php --colors=never`

Expected: 四次命令全部退出 0，且测试库中无 `e2e_%@test.local`、`e2e_prof_%@test.local` 对应 Outbox 残留。

### Task 4: 新闻翻译测试移除事务与 DDL 冲突

**Files:**
- Modify: `tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php`

- [ ] **Step 1: 在迁移后的测试库复现历史 Error**

Run: `php vendor/bin/phpunit tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php --colors=never`

Expected before fix: teardown 报 `PDOException: There is no active transaction`；若未复现，必须先检查当前 schema，而不能猜测修改。

- [ ] **Step 2: 写 schema 前置条件 RED/诊断断言**

测试启动时只读 `information_schema`，明确断言 `active_translation_key` 为 generated column 且 `news_langs_active_translation_unique` 存在；缺失时输出“测试库 migrations 未完整安装”并失败，禁止测试自愈 DDL。

- [ ] **Step 3: 删除冲突机制**

移除 `DatabaseTransactions` trait 和 `setUp()` 中三个 `ALTER TABLE`。保留测试自己创建行的显式删除；通过 `MySqlFixtureMutex` 串行保护共享新闻夹具，`finally` 中先清行再释放锁。

- [ ] **Step 4: 连续两次验证 GREEN**

Run twice: `php vendor/bin/phpunit tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php --colors=never`

Expected: 两次均退出 0，不出现事务错误，执行前后 `SHOW CREATE TABLE news_langs` 归一化 hash 相同。

### Task 5: 入金幂等迁移测试恢复完整表指纹

**Files:**
- Modify: `tests/Feature/FrontDepositPaymentOrderIdempotencyClosureModuleTest.php`
- Modify if root cause requires: `tests/Support/MySqlTableFingerprint.php`
- Test: `tests/Feature/FrontDepositPaymentOrderIdempotencyClosureModuleTest.php`

- [ ] **Step 1: 精确定位差异，不先改代码**

在 `test_harden_migration_is_idempotent` 前后分别采集五张表的 `SHOW CREATE TABLE`、行摘要和 AUTO_INCREMENT；只输出不同的字段名与 hash，不输出业务行内容。

- [ ] **Step 2: 提出并验证单一根因假设**

优先检查迁移重复执行是否改变 `deposit_records` 的索引顺序、列定义或 AUTO_INCREMENT。每次只测试一个差异；若三次假设均失败，停止补丁并重新评估迁移测试架构。

- [ ] **Step 3: 写 RED 回归断言**

断言同一迁移重复执行前后：业务行摘要不变、相关索引集合等价、列定义等价、AUTO_INCREMENT 回到捕获值。测试必须在历史实现上稳定失败于已确认差异。

- [ ] **Step 4: 实现最小恢复或幂等修复**

若问题属于夹具，补齐 `finally` 恢复顺序；若属于 migration，修正 migration 的幂等判断。不得通过从 `FINGERPRINT_TABLES` 移除表、忽略 hash 或吞掉 restore 异常变绿。

- [ ] **Step 5: 连续两次验证 GREEN**

Run twice: `php vendor/bin/phpunit tests/Feature/FrontDepositPaymentOrderIdempotencyClosureModuleTest.php --colors=never`

Expected: 两次均退出 0，前后表指纹完全一致。

### Task 6: 全量收敛门禁

**Files:**
- Modify only files implicated by fresh failures
- Evidence: `storage/logs/full-serial-*.out`, `storage/logs/full-serial-*.err`, `storage/logs/full-serial-*.exit`

- [ ] **Step 1: 运行三个根因域相关回归**

Run:

```powershell
php vendor/bin/phpunit tests/Feature/FrontAuthRegistrationLoginClosureTest.php tests/Feature/FrontProfileClosureTest.php --colors=never
php vendor/bin/phpunit tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php --colors=never
php vendor/bin/phpunit tests/Feature/FrontDepositPaymentOrderIdempotencyClosureModuleTest.php --colors=never
```

Expected: 全部退出 0。

- [ ] **Step 2: 运行 Unit 与 Feature 单进程**

Run: `php vendor/bin/phpunit --testsuite Unit --colors=never`

Run: `php vendor/bin/phpunit --testsuite Feature --colors=never`

Expected: 两套均零 Failure、零 Error、退出 0。

- [ ] **Step 3: 运行逐文件隔离套件**

Run: `powershell -ExecutionPolicy Bypass -File scripts/run-tests-one-by-one.ps1 -Filter '*tests\\Feature\\*Test.php' -Log 'storage\\logs\\feature-per-file-current.log'`

Expected: `FAIL=0 CRASH=0` 且进程退出 0。

- [ ] **Step 4: 运行正式全量入口**

Run: `powershell -ExecutionPolicy Bypass -File scripts/run-full-serial.ps1`

Expected: 重建 `co_crmv5_test` 成功，完整 PHPUnit 零 Failure、零 Error，exit 文件为 `0`。

- [ ] **Step 5: 只读确认真实库未变化**

对 `co_crmv5` 与 `hank_zl_data` 比较执行前后表数量、关键表行数及只读 schema 指纹。任何差异都视为测试隔离失败，停止后续正式迁移阶段。

## 阶段完成条件

只有下列证据同时存在时才进入旧项目模块补齐阶段：

1. 门禁单元测试证明正式库、旧库和 MT4 开启配置全部失败关闭。
2. 当前 Unit、Feature、逐文件和完整 PHPUnit 均退出 0。
3. 不存在历史报告抵消当前失败、单独重跑抵消全量失败或被忽略 flake。
4. `co_crmv5` 与 `hank_zl_data` 在测试阶段保持只读不变。
5. 暂存区仍为空，未执行任何提交或推送。
