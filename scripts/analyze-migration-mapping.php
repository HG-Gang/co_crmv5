<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "旧库→新库 表映射分析\n";
echo "====================================\n\n";

$oldDb = 'old_crm';
$newDb = 'mysql';

// 定义表映射关系：旧表 => [新表, 记录数, 状态]
$mappings = [
    // 已完成迁移
    'admin' => ['admins', '✅ 已完成', 14],
    'agents' => ['user_logins + user_infos', '✅ 已完成', 4730],
    'user' => ['user_logins + user_infos', '✅ 已完成', 13222],
    'mt4_trades' => ['mt4_trades', '✅ 已完成', 872140],

    // 需要迁移的核心业务表
    'deposit_record_log' => ['deposit_records', '⚠️  待迁移', 17678],
    'draw_record_log' => ['withdraw_records', '⚠️  待迁移', 1413],
    'voucher_info' => ['voucher_infos', '⚠️  待迁移', 138],
    'cancel_apply' => ['cancel_applies', '⚠️  待迁移', 29],
    'operation_log' => ['operation_logs', '⚠️  待迁移', 1162],
    'system_login_log' => ['admin_login_logs', '⚠️  待迁移', 30537],
    'user_img' => ['user_images', '⚠️  待迁移', 867],
    'user_addresses' => ['user_addresses', '⚠️  待迁移', 12],
    'mt4_users' => ['mt4_users', '⚠️  待迁移', 17948],
    'symbol_prices' => ['symbol_prices', '⚠️  待迁移', 316],
    'user_trades' => ['commission_records', '⚠️  待迁移（需确认）', 286253],
    'agent_relations' => ['agent_descendants', '⚠️  待迁移（需确认）', 52906],
    'user_online' => ['user_onlines', '⚠️  待迁移', 11146],
    'trans_apply_log' => ['trans_apply_logs', '⚠️  待迁移', 25],

    // 导入/备份表（可选）
    'deposit_import' => ['deposit_imports', '○ 可选', 81],
    'withdraw_import' => ['withdraw_imports', '○ 可选', 43],
    'credit_import' => ['credit_imports', '○ 可选', 13],
    'import_agents' => ['N/A', '○ 历史导入记录，可忽略', 4249],
    'import_user' => ['N/A', '○ 历史导入记录，可忽略', 8781],

    // 配置表
    'mt4_config' => ['mt4_configs', '○ 已有配置', 7],
    'system_config' => ['system_configs', '○ 已有配置', 1],
    'system_param' => ['system_configs', '○ 可合并到system_configs', 19],
    'group_config' => ['group_configs', '○ 已有配置', 27],
    'agent_level' => ['agent_levels', '○ 已有配置', 5],
    'role' => ['roles', '○ 已有配置', 5],

    // 反馈/礼品等边缘功能
    'offweb_feedback' => ['offweb_feedbacks', '○ 可选', 2],
    'gift_shipments' => ['gift_shipments', '○ 可选', 2],

    // 层级关系表
    'closure_table' => ['agent_descendants', '⚠️  闭包表（需转换）', 52906],
    'hierarchy' => ['agent_descendants', '⚠️  层级表（需转换）', 52906],

    // 备份表（忽略）
    'agents_copy1' => ['N/A', '✗ 备份表，忽略', 78],
    'user_copy2' => ['N/A', '✗ 备份表，忽略', 476],
    'mt4_trades_b' => ['N/A', '✗ 备份表，忽略', 8364],
    'mt4_trades_copy1' => ['N/A', '✗ 备份表，忽略', 7441],
    'mt4_users_b' => ['N/A', '✗ 备份表，忽略', 169],
    'mt4_users_copy1' => ['N/A', '✗ 备份表，忽略', 551],
    'mt4_config_b' => ['N/A', '✗ 备份表，忽略', 7],
    'mt4_config_copy1' => ['N/A', '✗ 备份表，忽略', 7],
    'mt4_prices_b' => ['N/A', '✗ 备份表，忽略', 141],
    'mt4_prices_copy1' => ['N/A', '✗ 备份表，忽略', 188],
    'user_trades_copy1' => ['N/A', '✗ 备份表，忽略', 9],

    // 非CRM业务（忽略）
    't_infowear_exercise_distance_report' => ['N/A', '✗ 智能穿戴业务，非CRM', 400801],
    't_infowear_exercise_ranking_report' => ['N/A', '✗ 智能穿戴业务，非CRM', 278086],
    't_infowear_exercise_type_report' => ['N/A', '✗ 智能穿戴业务，非CRM', 401245],
    't_infowear_exercise_week_report' => ['N/A', '✗ 智能穿戴业务，非CRM', 400651],
    't_exercise_medals' => ['N/A', '✗ 智能穿戴业务，非CRM', 42],
    't_user_24h_heart_rate' => ['N/A', '✗ 智能穿戴业务，非CRM', 151],
    'ps_countries' => ['N/A', '✗ PrestaShop数据，非CRM', 244],
    'ps_country_langs' => ['N/A', '✗ PrestaShop数据，非CRM', 1711],
    'ps_country_tmp' => ['N/A', '✗ PrestaShop数据，非CRM', 245],
    'ps_woer_app_versionv2s' => ['N/A', '✗ PrestaShop数据，非CRM', 168],
    't_sys_dict' => ['N/A', '✗ 其他系统字典，非CRM', 104],
    't_sys_langs' => ['N/A', '✗ 其他系统语言，非CRM', 14],
    't_sys_notes' => ['N/A', '✗ 其他系统通知，非CRM', 14],
    't_sys_note_langs' => ['N/A', '✗ 其他系统通知翻译，非CRM', 23],
    't_sys_note_users' => ['N/A', '✗ 其他系统通知用户，非CRM', 28],
    't_sys_notices' => ['N/A', '✗ 其他系统公告，非CRM', 10],
    't_sys_notice_langs' => ['N/A', '✗ 其他系统公告翻译，非CRM', 9],
    't_sys_notice_sends' => ['N/A', '✗ 其他系统公告发送，非CRM', 32],
    't_activity_edms' => ['N/A', '✗ 其他系统活动，非CRM', 1],
    't_activity_edm_langs' => ['N/A', '✗ 其他系统活动翻译，非CRM', 3],
    't_device_language' => ['N/A', '✗ 设备语言配置，非CRM', 156],
    't_device_support_language_list' => ['N/A', '✗ 设备支持语言，非CRM', 156],
    't_ffit_country_dict' => ['N/A', '✗ 健身国家字典，非CRM', 178],

    // 测试/临时表（忽略）
    'numbers' => ['N/A', '✗ 测试数据，忽略', 1000],
    'newslist' => ['N/A', '✗ 测试数据，忽略', 1],
    'data_list' => ['N/A', '✗ 不明用途，需确认', 17952],
    'data_operation_log' => ['data_operation_logs', '○ 可选', 1],
    'login_user' => ['N/A', '✗ 不明用途，需确认', 112],
    'member_user' => ['N/A', '✗ 不明用途，需确认', 330],
    'big_agents' => ['big_agents', '○ 可选', 10],
    'big_agents_login_log' => ['big_agent_login_logs', '○ 可选', 300],
    'agents_group' => ['N/A', '✗ 已整合到group_configs', 5],
    'user_group' => ['N/A', '✗ 已整合到group_configs', 35],
    'mail_setting' => ['mail_settings', '○ 可选', 1],
    'mt4_prices' => ['mt4_prices', '○ 可选（价格历史）', 107],
    'symbol_spread' => ['spread_configs', '○ 可选', 35],
];

// 按状态分类
$completed = [];
$pending = [];
$optional = [];
$ignored = [];

foreach ($mappings as $oldTable => [$newTable, $status, $count]) {
    $item = [
        'old' => $oldTable,
        'new' => $newTable,
        'count' => $count,
        'status' => $status,
    ];

    if (strpos($status, '✅') !== false) {
        $completed[] = $item;
    } elseif (strpos($status, '⚠️') !== false) {
        $pending[] = $item;
    } elseif (strpos($status, '○') !== false) {
        $optional[] = $item;
    } else {
        $ignored[] = $item;
    }
}

echo "【已完成迁移】(" . count($completed) . " 个表)\n";
echo str_repeat("=", 60) . "\n";
$completedCount = 0;
foreach ($completed as $item) {
    echo sprintf("✅ %-25s => %-30s %10s条\n", $item['old'], $item['new'], number_format($item['count']));
    $completedCount += $item['count'];
}
echo "小计: " . number_format($completedCount) . " 条\n\n";

echo "【待迁移核心表】(" . count($pending) . " 个表)\n";
echo str_repeat("=", 60) . "\n";
$pendingCount = 0;
foreach ($pending as $item) {
    echo sprintf("⚠️  %-25s => %-30s %10s条\n", $item['old'], $item['new'], number_format($item['count']));
    $pendingCount += $item['count'];
}
echo "小计: " . number_format($pendingCount) . " 条\n\n";

echo "【可选表】(" . count($optional) . " 个表)\n";
echo str_repeat("=", 60) . "\n";
$optionalCount = 0;
foreach ($optional as $item) {
    echo sprintf("○  %-25s => %-30s %10s条\n", $item['old'], $item['new'], number_format($item['count']));
    $optionalCount += $item['count'];
}
echo "小计: " . number_format($optionalCount) . " 条\n\n";

echo "【忽略表】(" . count($ignored) . " 个表)\n";
echo str_repeat("=", 60) . "\n";
$ignoredCount = 0;
foreach ($ignored as $item) {
    echo sprintf("✗  %-25s => %-30s %10s条\n", $item['old'], $item['new'], number_format($item['count']));
    $ignoredCount += $item['count'];
}
echo "小计: " . number_format($ignoredCount) . " 条\n\n";

echo "====================================\n";
echo "统计汇总\n";
echo "====================================\n\n";
echo "已完成: " . count($completed) . " 个表, " . number_format($completedCount) . " 条记录\n";
echo "待迁移: " . count($pending) . " 个表, " . number_format($pendingCount) . " 条记录\n";
echo "可选: " . count($optional) . " 个表, " . number_format($optionalCount) . " 条记录\n";
echo "忽略: " . count($ignored) . " 个表, " . number_format($ignoredCount) . " 条记录\n";
echo "\n";
echo "总计: " . (count($completed) + count($pending) + count($optional) + count($ignored)) . " 个表, ";
echo number_format($completedCount + $pendingCount + $optionalCount + $ignoredCount) . " 条记录\n";

echo "\n====================================\n";
echo "下一步行动\n";
echo "====================================\n\n";
echo "1. 优先迁移核心业务表（" . count($pending) . "个表，" . number_format($pendingCount) . "条记录）\n";
echo "2. 根据业务需要决定是否迁移可选表\n";
echo "3. 忽略备份表和非CRM业务表\n";
