<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "数据迁移缺口检查\n";
echo "========================================\n\n";

// 检查关键配置表
$configTables = [
    'exchange_rates' => '汇率配置',
    'mt4_groups' => 'MT4组配置',
    'mt4_symbols' => 'MT4交易品种',
    'commission_rules' => '佣金规则',
    'group_configs' => '组配置',
];

echo "【配置类表检查】\n";
foreach ($configTables as $table => $name) {
    try {
        $count = DB::table($table)->count();
        $status = $count > 0 ? '✅' : '❌';
        echo "{$status} {$name} ({$table}): {$count} 条\n";
    } catch (Exception $e) {
        echo "❌ {$name} ({$table}): 表不存在\n";
    }
}

echo "\n【权限系统检查】\n";
$permissionTables = [
    'permissions' => '权限定义',
    'roles' => '角色定义',
    'role_permissions' => '角色权限关联',
    'admin_role' => '管理员角色绑定',
];

foreach ($permissionTables as $table => $name) {
    try {
        $count = DB::table($table)->count();
        $status = $count > 0 ? '✅' : '❌';
        echo "{$status} {$name} ({$table}): {$count} 条\n";
    } catch (Exception $e) {
        echo "❌ {$name} ({$table}): 表不存在\n";
    }
}

echo "\n【业务扩展表检查】\n";
$businessTables = [
    'news' => '新闻公告',
    'gifts' => '礼品管理',
    'productions' => '产品配置',
    'channels' => '渠道配置',
    'credit_imports' => '信用导入',
    'deposit_imports' => '入金导入',
    'withdraw_imports' => '出金导入',
];

foreach ($businessTables as $table => $name) {
    try {
        $count = DB::table($table)->count();
        $status = $count > 0 ? '✅' : '⚠️';
        echo "{$status} {$name} ({$table}): {$count} 条\n";
    } catch (Exception $e) {
        echo "❌ {$name} ({$table}): 表不存在\n";
    }
}

echo "\n【已迁移表验证】\n";
$migratedTables = [
    'deposit_records' => '入金记录',
    'withdraw_records' => '出金记录',
    'mt4_users' => 'MT4用户',
    'mt4_trades' => 'MT4交易',
    'agent_descendants' => '代理层级',
    'admin_login_logs' => '管理员登录日志',
];

$totalRecords = 0;
foreach ($migratedTables as $table => $name) {
    try {
        $count = DB::table($table)->count();
        $totalRecords += $count;
        echo "✅ {$name} ({$table}): " . number_format($count) . " 条\n";
    } catch (Exception $e) {
        echo "❌ {$name} ({$table}): 查询失败\n";
    }
}

echo "\n========================================\n";
echo "迁移统计\n";
echo "========================================\n";
echo "已迁移核心记录数：" . number_format($totalRecords) . " 条\n";

// 统计旧库表数量
try {
    $oldTables = DB::connection('old_crm')->select('SHOW TABLES');
    echo "旧库表总数：" . count($oldTables) . " 张\n";
} catch (Exception $e) {
    echo "旧库连接失败\n";
}

// 统计新库表数量
$newTables = DB::select('SHOW TABLES');
echo "新库表总数：" . count($newTables) . " 张\n";

echo "\n========================================\n";
echo "建议\n";
echo "========================================\n";
echo "1. 优先迁移：权限系统数据（permissions, role_permissions）\n";
echo "2. 紧急迁移：配置类数据（exchange_rates, mt4_groups）\n";
echo "3. 可选迁移：业务扩展表（news, gifts, productions）\n";
echo "4. 完成后运行：php artisan test 验证功能完整性\n";
