<?php
/**
 * 临时诊断：查看 MySQL 进程与锁状态（只读，不修改任何数据）。
 */
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3307;dbname=co_crmv5',
    'root',
    '123456',
    [PDO::ATTR_TIMEOUT => 5]
);
echo "== PROCESSLIST ==\n";
foreach ($pdo->query('SHOW FULL PROCESSLIST') as $row) {
    printf(
        "%s\t%s\t%s\t%s\t%s\t%s\t%s\n",
        $row['Id'],
        $row['User'],
        $row['Host'],
        $row['db'] ?? '',
        $row['Command'],
        $row['Time'],
        mb_substr((string) ($row['Info'] ?? ''), 0, 100)
    );
}
echo "== LOCKED TABLES ==\n";
try {
    foreach ($pdo->query('SHOW OPEN TABLES WHERE In_use > 0') as $row) {
        printf("%s\t%s\t%s\n", $row['Database'], $row['Table'], $row['In_use']);
    }
} catch (Throwable $e) {
    echo $e->getMessage(), "\n";
}
