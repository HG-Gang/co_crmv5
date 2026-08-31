<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3007', 'root', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS co_crmv5 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "Database co_crmv5 created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
