# 2026-08-29 表指纹契约单测同步审计

## 背景

`tests/Support/MySqlTableFingerprint.php` 刚修改：指纹由五字段（auto_increment/row_count/content_digest/engine/structure_hash）缩减为四字段（row_count/content_digest/engine/structure_hash），删除 `auto_increment` 字段。原因：MySQL 8.0.12 在夹具快照恢复后，同一会话内两次读 `information_schema.TABLES.AUTO_INCREMENT` 出现 ±1 陈旧视图差异（2026-08-28 全量串行与 2026-08-29 复现：行数、内容摘要、结构哈希完全一致，仅该字段漂移），属服务器元数据间歇滞后而非业务残留；自增门禁由 `MySqlAutoIncrementSnapshot::restore()` 的捕获-预检-复核独立承担。类 docblock 已同步改写。

该修改导致 `tests/Unit/PaymentFixtureContractTest::test_external_payment_probe_uses_the_deterministic_table_fingerprint_contract` 失败：其 foreach 断言源码含带引号的 `'auto_increment'` 键，而新实现已无该键（实现内仅存大写 `AUTO_INCREMENT` 出现在元数据查询列与 structure_hash 归一化正则中，`assertStringContainsString` 大小写敏感故不匹配）。

## 改动文件与改动点

### tests/Unit/PaymentFixtureContractTest.php（唯一改动）

`test_external_payment_probe_uses_the_deterministic_table_fingerprint_contract`：

1. 原断言 `foreach (['auto_increment', 'row_count', 'content_digest', 'engine', 'structure_hash'] as $field)` 改为四字段 `['row_count', 'content_digest', 'engine', 'structure_hash']`，逐个断言源码含带引号字段键——锁定新指纹结构。
2. 新增 `assertStringNotContainsString("'auto_increment'", ...)`：禁止 `auto_increment` 指纹键回归（键级精确断言，不干扰实现 docblock/正则中大写 `AUTO_INCREMENT` 的合法出现）。
3. 新增两条 docblock 契约断言：源码含 `自增值（AUTO_INCREMENT）不纳入指纹` 与 `MySqlAutoIncrementSnapshot::restore`，锁定"自增值不纳入指纹、由自增快照门禁独立保障"的理由说明不被删除。
4. 方法 docblock 补充四字段与自增排除的契约说明；探针断言（`use Tests\Support\MySqlTableFingerprint;`、`MySqlTableFingerprint::capture($tables)`）原样保留，测试意图（锁定指纹确定性）不变。无任何断言被放宽或删除。

## 排查过的其他位置（均无需改动）

- `tests/Unit/TableRowsSnapshotTest.php`：不引用 MySqlTableFingerprint，无字段结构断言。
- `tests/Unit/MySqlAutoIncrementSnapshotTest.php`：不引用 MySqlTableFingerprint。
- `tests/Unit/MySqlTableFingerprintTest.php`：仅测 `digestRows` 稳定性/敏感性，不涉及指纹字段结构。
- `tests/Feature/CommissionTransferAtomicStorageMigrationTest.php:98`：断言的是迁移脚本源码含 `content_digest`，与指纹类无关且新实现仍满足。
- `payment_state_probe.php`：仅调用 `MySqlTableFingerprint::capture($tables)`，自身不引用 `auto_increment` 指纹键。
- Feature 测试中其他 `auto_increment` 引用（如 UserMt4ProvisioningMigrationClosureModuleTest、FrontRegisterVerificationLifecycleClosureModuleTest、payment_ai_restore.php）均为各套件自有快照/自增恢复结构，与 MySqlTableFingerprint 无关。

## 与新契约一致性说明

新断言与实现逐字对齐：实现 `$fingerprint[$table]` 恰为四键（row_count/content_digest/engine/structure_hash，见 MySqlTableFingerprint.php 第 55-63 行）；docblock 第 16-20 行含上述两个理由关键词；源码无小写带引号 `'auto_increment'`（grep 计数 0）。观察项（未改动）：实现第 43 行元数据查询 `->first(['AUTO_INCREMENT', 'ENGINE'])` 仍查询 AUTO_INCREMENT 列但结果未被使用，属可后续清理的死查询列，不影响指纹契约。

## 验证

- `php -l tests/Unit/PaymentFixtureContractTest.php`：No syntax errors detected。
- `php -l tests/Support/MySqlTableFingerprint.php`：No syntax errors detected。
- 按约束未运行 phpunit。
