<?php
// 查找旧库出金相关表名
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=hank_zl_data', 'root', '123456');
$stmt = $pdo->query("SHOW TABLES LIKE '%draw%'");
echo "旧库中包含 draw 的表：\n";
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "  - {$row[0]}\n";
}
