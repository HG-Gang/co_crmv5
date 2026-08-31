<?php
/**
 * Execute SQL schema against MySQL
 * Try multiple connection configs
 */
$configs = [
    ['host' => '127.0.0.1', 'port' => 3307, 'pass' => '123456'],
    ['host' => '127.0.0.1', 'port' => 3307, 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3307, 'pass' => 'root'],
    ['host' => 'localhost', 'port' => 3307, 'pass' => '123456'],
    ['host' => 'localhost', 'port' => 3307, 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3306, 'pass' => '123456'],
    ['host' => '127.0.0.1', 'port' => 3306, 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3306, 'pass' => 'root'],
];

$sqlFile = __DIR__ . '/database/co_crmv5_schema.sql';
if (!file_exists($sqlFile)) {
    echo "ERROR: SQL file not found at {$sqlFile}\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
echo "SQL file loaded: " . strlen($sql) . " bytes\n";

foreach ($configs as $c) {
    try {
        $dsn = "mysql:host={$c['host']};port={$c['port']};charset=utf8mb4";
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ];
        $pdo = new PDO($dsn, 'root', $c['pass'], $opts);
        echo "CONNECTED: {$c['host']}:{$c['port']} pass='{$c['pass']}'\n";

        // Execute the SQL
        $pdo->exec($sql);
        echo "Schema executed!\n";

        // Verify
        $pdo->exec('USE co_crmv5');
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables: " . count($tables) . "\n";
        foreach ($tables as $t) echo "  - {$t}\n";

        // Check seeds
        $admin = $pdo->query("SELECT username FROM admins LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        echo "Admin: " . ($admin ? $admin['username'] : 'NONE') . "\n";

        $menus = $pdo->query("SELECT COUNT(*) as c FROM menus")->fetch(PDO::FETCH_ASSOC);
        echo "Menus: " . $menus['c'] . "\n";

        echo "\n=== SUCCESS ===\n";
        exit(0);
    } catch (PDOException $e) {
        echo "FAIL {$c['host']}:{$c['port']} pass='{$c['pass']}': " . $e->getMessage() . "\n";
    }
}

echo "\nAll connection attempts failed.\n";
echo "Please ensure MySQL is running and update .env accordingly.\n";
echo "Then run: php _exec_sql.php\n";
