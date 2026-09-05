<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "新库 co_crmv5 数据变化对比\n";
echo "====================================\n\n";

// 迁移涉及的 13 张表
$tables = [
    'deposit_records' => '入金记录',
    'withdraw_records' => '出金记录',
    'voucher_infos' => '凭证信息',
    'cancel_applies' => '销户申请',
    'operation_logs' => '操作日志',
    'admin_login_logs' => '管理员登录日志',
    'user_images' => '用户图片',
    'mt4_users' => 'MT4用户',
    'symbol_prices' => '符号价格',
    'user_addresses' => '用户地址',
    'user_onlines' => '在线用户',
    'trans_apply_logs' => '转账申请日志',
    'agent_descendants' => '代理层级关系',
];

echo "迁移命令涉及的 13 张表:\n";
echo str_repeat("=", 70) . "\n";
printf("%-30s %-20s %s\n", "表名", "中文名", "当前记录数");
echo str_repeat("-", 70) . "\n";

$total = 0;
foreach ($tables as $table => $name) {
    try {
        $count = DB::connection('mysql')->table($table)->count();
        $total += $count;
        printf("%-30s %-20s %s\n", $table, $name, number_format($count));
    } catch (Exception $e) {
        printf("%-30s %-20s %s\n", $table, $name, "表不存在");
    }
}

echo str_repeat("-", 70) . "\n";
echo "总计: " . number_format($total) . " 条记录\n\n";

// 检查是否有其他表也有数据
echo "新库所有表的数据情况:\n";
echo str_repeat("=", 70) . "\n";

$allTables = DB::connection('mysql')->select('SHOW TABLES');
$dbName = config('database.connections.mysql.database');
$tableKey = "Tables_in_{$dbName}";

$migratedTables = array_keys($tables);
$otherTablesWithData = [];

foreach ($allTables as $tableObj) {
    $tableName = $tableObj->$tableKey;

    // 跳过迁移和系统表
    if (in_array($tableName, $migratedTables) || $tableName === 'migrations') {
        continue;
    }

    $count = DB::connection('mysql')->table($tableName)->count();
    if ($count > 0) {
        $otherTablesWithData[] = [
            'name' => $tableName,
            'count' => $count,
        ];
    }
}

if (!empty($otherTablesWithData)) {
    echo "其他有数据的表:\n";
    foreach ($otherTablesWithData as $table) {
        printf("  %-40s %s 条\n", $table['name'], number_format($table['count']));
    }
} else {
    echo "✅ 只有迁移的 13 张表有数据，其他表都是空的。\n";
}

echo "\n====================================\n";
echo "检查完成！\n";
echo "====================================\n";
