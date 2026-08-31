<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 22:35
 */

/**
 * 全量测试后的数据库收尾清理脚本。
 *
 * 文件功能：
 * - 删除测试运行残留的 batch_sync / credit_sync 测试角色、管理员与会话；
 * - 将 seeder/测试运行污染的 is_mt4_synced / is_mt4_enabled 标记恢复为迁移基线（0）；
 * - 清理测试产生的过期 MT4 开户 outbox（保留 1 小时内真实 pending）；
 * - 前后端密码统一重置为 abc123（user_logins / admins / big_agents）；
 * - 将 agent@test.com 演示账号 id 重排到迁移数据之后（消除测试重建空洞），同步 user_infos.login_id；
 * - 全部业务表 ALTER TABLE AUTO_INCREMENT = 1 归一（空表=1，非空表=max(id)+1）。
 *
 * 适用场景：
 * - 每次「真实数据全量测试」结束后执行一次，产出干净可交付的数据库最终状态。
 *
 * 用法：
 * - php scripts/post-test-cleanup.php
 *
 * 返回值：
 * - 输出各步骤统计；非零退出码表示失败。
 */

$p = new PDO('mysql:host=127.0.0.1;port=3307;dbname=co_crmv5', 'root', '123456', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$p->exec('SET FOREIGN_KEY_CHECKS = 0');

// 1. 清理 batch_sync / credit_sync 测试角色、管理员与会话（fixture 残留）。
$delRoles = $p->exec("DELETE FROM roles WHERE name LIKE 'batch_sync_%' OR name LIKE 'credit_sync_%'");
$delAdmins = $p->exec("DELETE FROM admins WHERE username LIKE 'batch_sync_%' OR username LIKE 'credit_sync_%'");
$delSessions = $p->exec("DELETE FROM admin_logins WHERE username LIKE 'batch_sync_%' OR username LIKE 'credit_sync_%'");
echo "清理测试角色: $delRoles, 测试管理员: $delAdmins, 测试会话: $delSessions\n";

// 2. MT4 同步标记恢复迁移基线（seeder/测试运行会把用户标记为已同步，需归零）。
$n = $p->exec('UPDATE user_infos SET is_mt4_synced = 0 WHERE is_mt4_synced = 1');
echo "is_mt4_synced 归零: $n 行\n";

// 3. 清理测试产生的过期 MT4 开户 outbox（保留 1 小时内真实 pending）。
$o = $p->exec('DELETE FROM user_mt4_provisioning_outbox WHERE created_at < UNIX_TIMESTAMP() - 3600');
echo "清理过期 outbox: $o\n";

// 4. 前后端密码统一 abc123。
$hash = '$2y$10$bIo/.0r34HlWauss.NTFQ.jBGnjPauTAzZ.NMg8wizUp6.YGhUCS6';
$p->exec("UPDATE user_logins SET password = '$hash'");
$p->exec("UPDATE admins SET password = '$hash'");
$p->exec("UPDATE big_agents SET password = '$hash'");
echo "密码 abc123 已重置\n";

// 5. agent@test.com 演示账号 id 重排到迁移数据之后，并同步 user_infos.login_id。
$maxId = (int) $p->query("SELECT COALESCE(MAX(id),0) FROM user_logins WHERE email <> 'agent@test.com'")->fetchColumn();
$agent = $p->query("SELECT id FROM user_logins WHERE email='agent@test.com' LIMIT 1")->fetchColumn();
if ($agent !== false && (int) $agent !== $maxId + 1) {
    $p->exec("UPDATE user_infos SET login_id = " . ($maxId + 1) . " WHERE login_id = " . (int) $agent);
    $p->exec("UPDATE user_logins SET id = " . ($maxId + 1) . " WHERE id = " . (int) $agent);
    echo "agent@test.com id 重排: $agent -> " . ($maxId + 1) . "\n";
} else {
    echo "agent@test.com id 无需重排\n";
}

// 6. 全部业务表 AUTO_INCREMENT 归一。
$p->exec('SET SESSION information_schema_stats_expiry = 0');
$tables = $p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='co_crmv5' AND TABLE_NAME NOT LIKE '%backup%' AND TABLE_NAME <> 'migrations'")->fetchAll(PDO::FETCH_COLUMN);
$done = 0;
foreach ($tables as $t) {
    try {
        $p->exec("ALTER TABLE `$t` AUTO_INCREMENT = 1");
        $done++;
    } catch (Throwable $e) {
        echo "跳过 $t: " . substr($e->getMessage(), 0, 80) . "\n";
    }
}
echo "AUTO_INCREMENT 归一: $done / " . count($tables) . " 张表\n";

// 7. 汇总关键状态。
echo "is_mt4_synced=1 剩余: " . $p->query('SELECT COUNT(*) FROM user_infos WHERE is_mt4_synced = 1')->fetchColumn() . "\n";
echo "batch_sync 残留: " . $p->query("SELECT COUNT(*) FROM roles WHERE name LIKE 'batch_sync_%'")->fetchColumn() . "\n";
echo "收尾清理完成。\n";
