<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:12
 */

/**
 * 支付相关测试的 AUTO_INCREMENT 恢复脚本（CLI）：按 baseline 还原自增计数。
 *
 * 文件功能：
 * - 读取测试开始前保存的 baseline JSON（各表期望的 AUTO_INCREMENT 值）。
 * - 对照 information_schema 实际值，必要时执行 ALTER TABLE 重置自增值。
 * - stdout 输出 JSON 结果（restored / verified / failures），有失败项时退出码为 2。
 *
 * 适用场景：
 * - 依赖真实 MySQL 自增主键的支付流程测试结束后回归自增值，
 *   避免残留数据推高自增计数影响后续测试。
 *
 * 入参例子：
 * - php payment_ai_restore.php <baseline.json>；第一个参数为 baseline 文件路径，
 *   缺失或文件不存在时抛 RuntimeException。
 *
 * 返回值：
 * - 退出码 0 表示全部恢复/校验成功；2 表示存在失败项（failures 非空）。
 *
 * 失败场景：
 * - 表中 max(id) 不低于期望自增值时无法安全重置，记入 failures 且不执行 ALTER，
 *   说明测试数据未清理干净，需先清理再恢复。
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = Illuminate\Support\Facades\DB::class;
$baselinePath = $argv[1] ?? '';
if ($baselinePath === '' || !is_file($baselinePath)) {
    throw new RuntimeException('Missing baseline state file.');
}
$baseline = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
$db::statement('SET SESSION information_schema_stats_expiry = 0');
$result = ['restored' => [], 'verified' => [], 'failures' => []];

foreach ($baseline['tables'] as $table => $expectedState) {
    if (preg_match('/^[A-Za-z0-9_]+$/', (string) $table) !== 1) {
        $result['failures'][] = 'Unsafe table ' . $table . '.';
        continue;
    }
    $readAuto = static function () use ($db, $table): ?int {
        $row = $db::table('information_schema.TABLES')
            ->useWritePdo()
            ->where('TABLE_SCHEMA', $db::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->first(['AUTO_INCREMENT']);
        if ($row === null) {
            throw new RuntimeException('Missing information_schema row for ' . $table . '.');
        }

        return $row->AUTO_INCREMENT === null ? null : (int) $row->AUTO_INCREMENT;
    };
    $expected = $expectedState['auto_increment'];
    $actual = $readAuto();
    if ($expected === null) {
        if ($actual !== null) {
            $result['failures'][] = $table . ': expected NULL AUTO_INCREMENT, got ' . $actual . '.';
        } else {
            $result['verified'][$table] = null;
        }
        continue;
    }

    $expected = (int) $expected;
    if ($actual !== $expected) {
        $maxId = $db::table($table)->useWritePdo()->max('id');
        if ($maxId !== null && (int) $maxId >= $expected) {
            $result['failures'][] = $table . ': max(id) ' . $maxId
                . ' is not below baseline AUTO_INCREMENT ' . $expected . '.';
            continue;
        }
        $db::statement('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . $expected);
        $result['restored'][$table] = ['from' => $actual, 'to' => $expected];
        $db::statement('SET SESSION information_schema_stats_expiry = 0');
        $actual = $readAuto();
    }
    if ($actual !== $expected) {
        $result['failures'][] = $table . ': expected AUTO_INCREMENT ' . $expected
            . ', got ' . var_export($actual, true) . '.';
    } else {
        $result['verified'][$table] = $expected;
    }
}

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
exit($result['failures'] === [] ? 0 : 2);
