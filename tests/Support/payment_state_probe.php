<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:12
 */

/**
 * 支付相关测试的状态采集脚本（CLI）：输出环境状态 JSON，供测试前后对比。
 *
 * 文件功能：
 * - 用 MySqlTableFingerprint::capture 采集指定表的表结构指纹。
 * - 对 deposit_records 计算 SHOW CREATE TABLE / SHOW INDEX 的 sha256 哈希与索引明细。
 * - 采集 system_configs 中支付开关、时段、金额等关键配置项。
 * - 统计 payment-task2/3/4 等前缀的残留测试数据（deposit_records、payment_channels、
 *   user_logins、user_infos、system_configs）。
 *
 * 适用场景：
 * - 支付相关回归测试开始前保存基线状态，结束后比对残留数据与结构变化。
 *
 * 入参例子：
 * - 无入参；结果输出到 stdout（JSON），异常时抛 RuntimeException。
 *
 * 返回值：
 * - stdout 输出包含 tables / schema / system_configs / residue 四部分的 JSON。
 *
 * 失败场景：
 * - SHOW CREATE TABLE 拿不到 deposit_records 定义时抛 RuntimeException，
 *   表示环境表结构异常，测试不应继续。
 */

declare(strict_types=1);

use Tests\Support\MySqlTableFingerprint;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = Illuminate\Support\Facades\DB::class;
$db::statement('SET SESSION information_schema_stats_expiry = 0');
$tables = ['deposit_records', 'user_logins', 'user_infos', 'payment_channels', 'system_configs'];
$configKeys = [
    'deposit_enabled',
    'deposit_weekend_enabled',
    'deposit_start_time',
    'deposit_end_time',
    'deposit_min_amount',
    'deposit_max_amount',
];
$state = [
    'tables' => MySqlTableFingerprint::capture($tables),
    'schema' => [],
    'system_configs' => [],
    'residue' => [],
];

$createRow = $db::selectOne('SHOW CREATE TABLE deposit_records', [], false);
$createSql = '';
foreach ((array) $createRow as $column => $value) {
    if (strcasecmp(trim((string) $column), 'Create Table') === 0) {
        $createSql = (string) $value;
        break;
    }
}
if ($createSql === '') {
    throw new RuntimeException('SHOW CREATE TABLE returned no deposit_records definition.');
}
$normalizedCreate = preg_replace('/AUTO_INCREMENT=\d+/i', 'AUTO_INCREMENT=<AI>', $createSql);
$state['schema']['show_create_sha256'] = hash('sha256', (string) $normalizedCreate);

$indexRows = [];
foreach ($db::select('SHOW INDEX FROM deposit_records', [], false) as $row) {
    $raw = (array) $row;
    $name = (string) ($raw['Key_name'] ?? '');
    $column = (string) ($raw['Column_name'] ?? '');
    if (!in_array($name, [
        'deposit_records_idempotency_user_unique',
        'deposit_records_idempotency_user_gateway_unique',
        'deposit_records_local_order_no_unique',
    ], true) && !in_array($column, ['idempotency_key', 'local_order_no'], true)) {
        continue;
    }
    $indexRows[] = [
        'Key_name' => $name,
        'Non_unique' => (int) ($raw['Non_unique'] ?? -1),
        'Seq_in_index' => (int) ($raw['Seq_in_index'] ?? -1),
        'Column_name' => $raw['Column_name'] ?? null,
        'Sub_part' => $raw['Sub_part'] ?? null,
        'Index_type' => $raw['Index_type'] ?? null,
        'Collation' => $raw['Collation'] ?? null,
        'Visible' => $raw['Visible'] ?? null,
        'Expression' => $raw['Expression'] ?? null,
    ];
}
usort($indexRows, static function (array $left, array $right): int {
    return [$left['Key_name'], $left['Seq_in_index']] <=> [$right['Key_name'], $right['Seq_in_index']];
});
$state['schema']['show_index_sha256'] = hash(
    'sha256',
    json_encode($indexRows, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);
$state['schema']['show_index_rows'] = $indexRows;

$state['system_configs'] = $db::table('system_configs')
    ->useWritePdo()
    ->whereIn('key', $configKeys)
    ->orderBy('key')
    ->get()
    ->map(static function (object $row): array {
        return (array) $row;
    })
    ->all();

$countPatterns = static function (string $table, string $column, array $patterns) use ($db): int {
    $query = $db::table($table)->useWritePdo();
    $query->where(function ($nested) use ($column, $patterns): void {
        foreach ($patterns as $offset => $pattern) {
            if ($offset === 0) {
                $nested->where($column, 'like', $pattern);
            } else {
                $nested->orWhere($column, 'like', $pattern);
            }
        }
    });

    return (int) $query->count();
};

$state['residue']['deposit_keys'] = $countPatterns(
    'deposit_records',
    'idempotency_key',
    ['payment-task2-%', 'payment-task3-%', 'payment-task4-%']
);
$state['residue']['deposit_orders'] = $countPatterns(
    'deposit_records',
    'local_order_no',
    ['PAYMENT-TASK2-%', 'PAYMENT-TASK3-%', 'PAYMENT-TASK4-%', 'MIGRATION-DUPLICATE-%']
);
$state['residue']['payment_channels'] = $countPatterns(
    'payment_channels',
    'channel_code',
    ['payment-task2-%', 'payment-task3-%', 'payment-task4-%']
);
$state['residue']['user_logins'] = $countPatterns(
    'user_logins',
    'email',
    ['payment-task2-%@example.test', 'payment-task3-%@example.test']
);
$state['residue']['user_infos'] = (int) $db::table('user_infos')
    ->useWritePdo()
    ->whereIn('user_name', ['payment-task2-user', 'payment-task3-user'])
    ->count();
$state['residue']['system_configs'] = (int) $db::table('system_configs')
    ->useWritePdo()
    ->where('key', 'like', 'payment-snapshot-%')
    ->count();

echo json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
