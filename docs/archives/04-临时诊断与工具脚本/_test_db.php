<?php
$ports = [3307, 3306, 3308, 33060];
foreach ($ports as $port) {
    try {
        $pdo = new PDO("mysql:host=127.0.0.1;port={$port}", 'root', '123456', [PDO::ATTR_TIMEOUT => 3]);
        echo "OK port {$port} pass=123456\n";
        $stmt = $pdo->query("SHOW DATABASES LIKE 'co_crmv5'");
        $db = $stmt->fetch();
        echo "  co_crmv5 exists: " . ($db ? 'YES' : 'NO') . "\n";
    } catch (PDOException $e) {
        echo "FAIL port {$port} pass=123456: " . $e->getMessage() . "\n";
    }
    try {
        $pdo = new PDO("mysql:host=127.0.0.1;port={$port}", 'root', '', [PDO::ATTR_TIMEOUT => 3]);
        echo "OK port {$port} pass=empty\n";
    } catch (PDOException $e) {
        echo "FAIL port {$port} pass=empty: " . $e->getMessage() . "\n";
    }
}
// Also check which PHP this is
echo "\nPHP: " . PHP_VERSION . "\n";
echo "CLI: " . php_sapi_name() . "\n";
