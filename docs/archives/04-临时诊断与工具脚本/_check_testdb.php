<?php
/**
 * 临时诊断：检查 co_crmv5_test 是否存在及表数量。
 */
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3307',
    'root',
    '123456',
    [PDO::ATTR_TIMEOUT => 5]
);
foreach ($pdo->query('SHOW DATABASES') as $row) {
    echo $row[0], PHP_EOL;
}
echo "== co_crmv5_test tables ==\n";
$count = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'co_crmv5_test'"
)->fetchColumn();
echo 'table_count=', $count, PHP_EOL;

$pdo->exec('USE co_crmv5_test');
foreach (['admins', 'roles', 'menus', 'permissions', 'users', 'user_infos'] as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "$table=", $count, PHP_EOL;
    } catch (Throwable $e) {
        echo "$table=ERR ", $e->getMessage(), PHP_EOL;
    }
}
