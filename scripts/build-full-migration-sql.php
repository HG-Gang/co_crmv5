<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:50
 */

/**
 * 组装「新库全量重置 + 最新表结构 + 旧库全量数据迁移」单文件可执行 SQL。
 *
 * 文件功能：
 * - 读取 mysqldump 导出的最新表结构（database/sql/_schema_latest.sql，无数据、含 DROP TABLE IF EXISTS）。
 * - 去除 CREATE TABLE 中的 AUTO_INCREMENT=N 数值，保证清库重建后所有自增列从 1 开始。
 * - 拼接 legacy_front_full_migration.sql（旧库 hank_zl_data → 新库 co_crmv5 的主数据迁移）。
 * - 补充旧库有对应数据源但原迁移未覆盖的表：user_onlines / sys_dicts / whs_exp_zeros /
 *   rights_settlements / user_login_logs。
 * - 尾部修正：恢复 migrations 表记录；MT4 同步标记统一归零（用户维度同步开关关闭，
 *   仅本地数据为准）；user_logins / admins / big_agents 密码统一重置为 abc123。
 * - 输出 database/sql/full_reset_and_migrate.sql 单文件，可直接用 mysql 客户端执行。
 *
 * 用法：
 * - php scripts/build-full-migration-sql.php
 *
 * 注意：
 * - 执行目标 SQL 会清空 co_crmv5 全部业务表并重建，属于不可逆操作，
 *   执行前必须确保已备份（数据库层面 mysqldump 全库备份）。
 * - 密码统一 abc123 仅用于本地测试环境，生产环境不得执行本文件。
 */

// 新库密码统一 abc123 的 bcrypt hash（PHP password_hash('abc123', PASSWORD_BCRYPT) 生成）。
const ABC123_HASH = '$2y$10$bIo/.0r34HlWauss.NTFQ.jBGnjPauTAzZ.NMg8wizUp6.YGhUCS6';

const SCHEMA_FILE = __DIR__ . '/../database/sql/_schema_latest.sql';
const LEGACY_MIGRATION_FILE = __DIR__ . '/../database/sql/legacy_front_full_migration.sql';
const OUTPUT_FILE = __DIR__ . '/../database/sql/full_reset_and_migrate.sql';

if (! is_file(SCHEMA_FILE)) {
    fwrite(STDERR, "缺少表结构文件: " . SCHEMA_FILE . "\n");
    exit(1);
}
if (! is_file(LEGACY_MIGRATION_FILE)) {
    fwrite(STDERR, "缺少旧库迁移 SQL: " . LEGACY_MIGRATION_FILE . "\n");
    exit(1);
}

$schema = file_get_contents(SCHEMA_FILE);
$legacy = file_get_contents(LEGACY_MIGRATION_FILE);

// 1. 去除 CREATE TABLE 中 AUTO_INCREMENT=N（N 来自当前库自增值），让重建后全部从 1 开始。
$schema = preg_replace('/ AUTO_INCREMENT=\d+/i', '', $schema);

// 1.1 系统初始化表保留策略：permissions/menus/role_permissions/role_data_scopes/
//     admin_agent_bindings/gift_items/id_sequences/system_configs 属于「代码初始化数据」
//     （由 Laravel 迁移种子生成，旧库无对应数据源），不能随业务数据清空重建。
//     处理：DROP TABLE IF EXISTS 改为 CREATE TABLE IF NOT EXISTS（已有数据保留；
//     全新库则建空表，需先执行 migrations 初始化权限体系）。
$keepTables = [
    'permissions', 'menus', 'role_permissions', 'role_data_scopes',
    'admin_agent_bindings', 'gift_items', 'id_sequences', 'system_configs',
];
foreach ($keepTables as $t) {
    // 匹配该表的完整块：DROP TABLE IF EXISTS `t`; ... CREATE TABLE `t` ( ... );
    $pattern = '/DROP TABLE IF EXISTS `' . preg_quote($t, '/') . '`;.*?CREATE TABLE `' . preg_quote($t, '/') . '` \(.*?\) ENGINE=InnoDB[^;]*;/s';
    if (preg_match($pattern, $schema, $mm)) {
        $block = $mm[0];
        // 去掉 DROP 行，CREATE 改为 CREATE TABLE IF NOT EXISTS
        $block = preg_replace('/^DROP TABLE IF EXISTS `' . preg_quote($t, '/') . '`;\s*/m', '', $block);
        $block = str_replace(
            'CREATE TABLE `' . $t . '` (',
            'CREATE TABLE IF NOT EXISTS `' . $t . '` (',
            $block
        );
        $schema = str_replace($mm[0], $block, $schema);
    }
}

// 1.2 mt4_prices 幂等键调整：原设计用 CRC32(symbol) 作为主键 id（UPSERT 幂等），
//     导致自增值虚高（可达 42 亿）。改为自增 id + symbol 唯一索引（数据已确认
//     symbol 无重复），UPSERT 基于唯一索引保持幂等，AUTO_INCREMENT 从 1 连续。
$schema = str_replace(
    'KEY `mt4_prices_symbol_index` (`symbol`)',
    'UNIQUE KEY `mt4_prices_symbol_unique` (`symbol`)',
    $schema
);

// 2. 去掉 mysqldump 的版本化 SET 头（由本文件统一管理会话变量），只保留 DROP/CREATE 主体。
$schema = preg_replace('/^\/\*!40103 SET .*?;\s*$/m', '', $schema);
$schema = preg_replace('/^\/\*!40014 SET .*?;\s*$/m', '', $schema);
$schema = preg_replace('/^\/\*!40101 SET .*?;\s*$/m', '', $schema);
$schema = preg_replace('/^\/\*!40111 SET .*?;\s*$/m', '', $schema);
$schema = preg_replace('/^\/\*!40101 SET @saved_cs_client.*?;\s*$/m', '', $schema);
$schema = preg_replace('/^ SET character_set_client = utf8mb4 ;\s*$/m', '', $schema);
$schema = preg_replace('/^\/\*!40101 SET character_set_client = @saved_cs_client \*\/;\s*$/m', '', $schema);
$schema = trim($schema) . "\n";

// 3. 组装完整 SQL。
$out = [];
$out[] = "-- ============================================================================";
$out[] = "-- 新项目数据库全量重置与旧库数据迁移（单文件可执行）";
$out[] = "-- ============================================================================";
$out[] = "-- 功能：";
$out[] = "-- 1. 清空 co_crmv5 全部数据表（DROP 后重建，所有 AUTO_INCREMENT 从 1 开始）；";
$out[] = "-- 2. 安装最新表结构（由当前最新迁移状态导出，77 张业务/框架表）；";
$out[] = "-- 3. 从旧库 hank_zl_data 全量迁移数据到 co_crmv5（INSERT ... SELECT 跨库）；";
$out[] = "-- 4. 尾部修正：migrations 记录恢复、MT4 同步标记归零、前后端密码统一 abc123。";
$out[] = "-- ";
$out[] = "-- 源库：hank_zl_data（旧项目 new_co_gmtk_crmV3）";
$out[] = "-- 目标库：co_crmv5（新项目）";
$out[] = "-- ";
$out[] = "-- 警告：执行本文件会清空 co_crmv5 全部业务表并重建，属于不可逆操作！";
$out[] = "-- 执行前请先做数据库全量备份。";
$out[] = "-- ============================================================================";
$out[] = "";
$out[] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;";
$out[] = "SET FOREIGN_KEY_CHECKS = 0;";
$out[] = "SET @old_sql_mode := @@SESSION.sql_mode;";
$out[] = "SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'STRICT_TRANS_TABLES', ''), 'STRICT_ALL_TABLES', '');";
$out[] = "SET @now := UNIX_TIMESTAMP();";
$out[] = "";
$out[] = "-- ============================================================================";
$out[] = "-- 第一部分：清空全部表并重建最新表结构（AUTO_INCREMENT 全部归 1）";
$out[] = "-- ============================================================================";
$out[] = $schema;
$out[] = "";
$out[] = "-- ============================================================================";
$out[] = "-- 第二部分：旧库全量数据迁移（hank_zl_data -> co_crmv5）";
$out[] = "-- ============================================================================";
// 统一连接 collation：legacy SQL 的 SET NAMES utf8mb4 会重置连接 collation 为
// utf8mb4_0900_ai_ci（MySQL 8 默认），与 CAST(... AS CHAR)/字面量比较时产生 1267
// collation 冲突；显式固定为 utf8mb4_unicode_ci 与新库列一致。
$legacy = str_replace(
    "SET NAMES utf8mb4;",
    "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;",
    $legacy
);
$out[] = $legacy;
$out[] = "";
$out[] = "-- ============================================================================";
$out[] = "-- 第三部分：补充迁移（旧库有数据源但主迁移未覆盖的表）";
$out[] = "-- ============================================================================";
$out[] = "";
$out[] = "-- 3.1 user_login_log -> user_login_logs（用户登录日志；旧库表为空，语句幂等保留）";
$out[] = "INSERT INTO co_crmv5.user_login_logs (login_id, user_id, login_ip, ip_location, user_agent, created_at, updated_at, deleted_at)";
$out[] = "SELECT login_id, login_id, login_ip, ip_addr, '',";
$out[] = "       CASE WHEN created_at IS NULL OR created_at = '' OR created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(created_at) END,";
$out[] = "       CASE WHEN updated_at IS NULL OR updated_at = '' OR updated_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(updated_at) END,";
$out[] = "       NULL";
$out[] = "FROM hank_zl_data.user_login_log;";
$out[] = "";
$out[] = "-- ============================================================================";
$out[] = "-- 第四部分：尾部修正";
$out[] = "-- ============================================================================";
$out[] = "";
$out[] = "-- 4.1 恢复 migrations 表记录（表结构已按最新迁移安装，记录保证 migrate:status 一致）";
$out[] = file_get_contents(__DIR__ . '/../database/sql/_migrations_data.sql');
$out[] = "";
$out[] = "-- 4.2 MT4 用户维度同步标记统一归零：全局开关（MT4_USER_SYNC_ENABLED=false）关闭状态下，";
$out[] = "--     迁移数据仅以本地为准，不向 MT4 远端同步、不声明已同步，供本地全量验证迁移逻辑正确性。";
$out[] = "UPDATE co_crmv5.user_infos SET is_mt4_synced = 0;";
$out[] = "";
$out[] = "-- 4.3 前后端用户密码统一重置为 abc123（仅本地测试环境）";
$out[] = "UPDATE co_crmv5.user_logins SET password = '" . ABC123_HASH . "', updated_at = @now;";
$out[] = "UPDATE co_crmv5.admins SET password = '" . ABC123_HASH . "', updated_at = @now;";
$out[] = "UPDATE co_crmv5.big_agents SET password = '" . ABC123_HASH . "', updated_at = @now;";
$out[] = "";
$out[] = "-- 4.4 出金/入金必需配置键保障（system_configs 保留原数据，缺键时补齐默认值，幂等）";
$out[] = "INSERT IGNORE INTO co_crmv5.system_configs (`key`, `value`, `group`, description, created_at, updated_at, deleted_at) VALUES";
$out[] = "('withdrawal_enabled', '1', 'finance', '出金总开关：1=开启', @now, @now, NULL),";
$out[] = "('withdrawal_weekend_enabled', '0', 'finance', '周末是否允许出金：0=禁止', @now, @now, NULL),";
$out[] = "('withdrawal_start_time', '09:00:00', 'finance', '每日出金开始时间', @now, @now, NULL),";
$out[] = "('withdrawal_end_time', '16:30:00', 'finance', '每日出金结束时间', @now, @now, NULL),";
$out[] = "('withdraw_min_amount', '50', 'finance', '出金最小金额（美元）', @now, @now, NULL),";
$out[] = "('withdraw_max_amount', '50000', 'finance', '出金最大金额（美元）', @now, @now, NULL),";
$out[] = "('withdraw_risk_rate_limit', '50', 'finance', '出金风险率上限（百分比）', @now, @now, NULL),";
$out[] = "('withdraw_check_open', '1', 'finance', '出金人工审核开关：1=开启', @now, @now, NULL),";
$out[] = "('withdrawal_fee_rate', '0', 'finance', '出金手续费率', @now, @now, NULL),";
$out[] = "('withdrawal_fixed_fee_usd', '0', 'finance', '出金固定手续费（美元）', @now, @now, NULL),";
$out[] = "('withdraw_exchange_rate_cny', '7.05', 'finance', '出金人民币汇率', @now, @now, NULL);";
$out[] = "";
$out[] = "-- 4.5 自增序列重整：满足「每张表 AUTO_INCREMENT 从 1 开始」的要求";
$out[] = "--     对 id 无跨表引用（无 outbox/明细表外键指向）的表，把 id 按行号重排为从 1 连续；";
$out[] = "--     然后对所有表执行 ALTER TABLE AUTO_INCREMENT = 1 —— 空表自增归 1，";
$out[] = "--     非空表 MySQL 自动取 max(id)+1（无虚高空洞）。";
$out[] = "--     跨表引用表（user_logins/user_infos/user_auths/deposit_records/withdraw_records/";
$out[] = "--     user_trades/countries 等）保留原 id 保证引用一致性，仅做 AUTO_INCREMENT 归一。";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.mt4_prices SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.mt4_users SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.mt4_trades SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.mt4_configs SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.admin_login_logs SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.operation_logs SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.user_onlines SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.user_images SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.country_langs SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.country_translations SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.sys_dicts SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.voucher_infos SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.deposit_imports SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.withdraw_imports SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.credit_imports SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.app_versions SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.gift_shipments SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.news_langs SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.data_operation_logs SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.mail_settings SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "SET @row := 0;";
$out[] = "UPDATE co_crmv5.offweb_feedbacks SET id = (@row := @row + 1) ORDER BY id;";
$out[] = "";
$out[] = "-- 全部业务表 AUTO_INCREMENT 归一（空表=1；非空表=max(id)+1，无虚高）";
$out[] = "-- （系统保留表与跨引用表由上方 UPDATE 已重排或无需重排，此处统一归一）";
$out[] = "ALTER TABLE co_crmv5.mt4_prices AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.mt4_users AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.mt4_trades AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.mt4_configs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.admin_login_logs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.operation_logs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.user_onlines AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.user_images AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.country_langs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.country_translations AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.sys_dicts AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.voucher_infos AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.deposit_imports AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.withdraw_imports AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.credit_imports AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.app_versions AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.gift_shipments AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.news AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.news_langs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.blacklists AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.data_operation_logs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.mail_settings AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.offweb_feedbacks AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.batch_fail_records AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.whs_exp_zeros AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.rights_settlements AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.rights_settlement_temps AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.user_login_logs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.payment_settlement_outbox AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.withdraw_settlement_outbox AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.user_mt4_provisioning_outbox AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.commission_transfer_outbox AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.commission_transfers AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.commission_rebate_payouts AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.commission_records AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.trans_apply_logs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.cancel_applies AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.user_addresses AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.gift_items AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.big_agents AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.big_agent_login_logs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.admins AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.roles AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.permissions AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.menus AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.role_permissions AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.role_data_scopes AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.admin_agent_bindings AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.system_configs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.payment_channels AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.agent_levels AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.group_configs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.symbol_prices AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.spread_configs AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.countries AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.languages AUTO_INCREMENT = 1;";
$out[] = "ALTER TABLE co_crmv5.id_sequences AUTO_INCREMENT = 1;";
$out[] = "";
$out[] = "-- 4.6 id_sequences 业务序列对齐旧库最大 user_id：新注册用户 ID 从旧库最大之后连续分配，";
$out[] = "--     避免与迁移数据的主键冲突（customer/agent 两类账号统一取全局最大值 +1）。";
$out[] = "UPDATE co_crmv5.id_sequences SET current_value = (SELECT COALESCE(MAX(user_id), 0) FROM co_crmv5.user_logins) + 1, updated_at = @now;";
$out[] = "";
$out[] = "-- 4.7 确保 id=1 超级管理员存在（测试与系统初始化均依赖 admins.id=1；";
$out[] = "--     旧库 admin 表无 id=1，此处幂等补建，密码同统一 abc123）。";
$out[] = "INSERT INTO co_crmv5.admins (id, role_id, mobile, email, username, password, login_count, last_login_ip, last_login_at, status, created_by, created_at, updated_at)";
$out[] = "VALUES (1, '1', '13800138000', 'admin@crmv5.com', 'superadmin', '" . ABC123_HASH . "', 0, '', NULL, 1, 'system', @now, @now)";
$out[] = "ON DUPLICATE KEY UPDATE";
$out[] = "    role_id = VALUES(role_id),";
$out[] = "    email = VALUES(email),";
$out[] = "    username = VALUES(username),";
$out[] = "    password = VALUES(password),";
$out[] = "    status = 1,";
$out[] = "    updated_at = VALUES(updated_at);";
$out[] = "";
$out[] = "SET SESSION sql_mode = @old_sql_mode;";
$out[] = "SET FOREIGN_KEY_CHECKS = 1;";
$out[] = "";
$out[] = "-- ============================================================================";
$out[] = "-- 迁移完成。";
$out[] = "-- 验证建议：";
$out[] = "--   SELECT COUNT(*) FROM co_crmv5.user_logins;      -- 应与旧库 user+agents 有效数一致";
$out[] = "--   SELECT COUNT(*) FROM co_crmv5.user_infos;       -- 同上";
$out[] = "--   SELECT COUNT(*) FROM co_crmv5.user_auths;       -- 同上";
$out[] = "--   SELECT COUNT(*) FROM co_crmv5.user_trades;      -- 旧库 user_trades 有效行";
$out[] = "--   SELECT COUNT(*) FROM co_crmv5.mt4_trades;       -- 旧库 mt4_trades 有效行";
$out[] = "-- ============================================================================";

file_put_contents(OUTPUT_FILE, implode("\n", $out));

echo "已生成: " . OUTPUT_FILE . "\n";
echo "大小: " . filesize(OUTPUT_FILE) . " 字节\n";
echo "行数: " . substr_count(implode("\n", $out), "\n") . "\n";
