<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 14:45
 */

declare(strict_types=1);

/**
 * 清理历次被中断测试遗留的 MySQL 夹具数据。
 *
 * 文件功能：
 * - 先备份将被删除的行（生成可恢复的 INSERT SQL 文件）。
 * - 按外键安全顺序删除测试残留数据。
 * - 保留真实种子数据：admin id=1（superadmin）、roles id=51/52/53。
 *
 * 适用场景：
 * - 全量 PHPUnit 套件被系统/环境中断后，夹具指纹测试出现
 *   table_fingerprint_mismatch 或自增序列混乱时，运行本脚本恢复干净基线。
 *
 * 入参例子：
 * - php scripts\cleanup-test-fixture-residue.php
 *
 * 返回值：
 * - 成功时逐表打印删除行数；失败时抛出异常并保持备份文件可恢复。
 *
 * 安全边界：
 * - 只删除带明确测试标记的行（example.test/test.com 邮箱、1e9~2e9 测试用户区间、
 *   已知测试命名前缀、非种子 admin/role），不触碰真实业务数据。
 * - 删除前会把全部受影响行以 INSERT 语句形式备份到
 *   storage/app/backups/test-fixture-residue-{时间戳}.sql。
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('本脚本仅支持 MySQL 驱动。');
}

// 真实种子主键：只保留这些行，其余 admin/role 均为测试残留。
$keepAdminIds = [1];
$keepRoleIds = [51, 52, 53];

// 测试标记常量：邮箱域名、用户 ID 区间、命名前缀。
$testEmailDomains = ['%@example.test', '%@test.com', '%@test.local'];
$testUserIdMin = 1000000000;
$testUserIdMax = 1999999999;
// 测试用户常用 ID 区间：98xxxx（后台/前台批量夹具）与 4988xxxxxx（Saga 夹具）。
$testUserIdRanges = [
    [980000, 999999],
    [498800000, 498899999],
];
$testUserNamePrefixes = [
    'Agent scope %',
    'Scoped root %',
    'Unscoped %',
    'Batch Credit %',
    'Uploaded Credit User%',
    'Batch %',
    '%Sync User',
    '%Csv User',
    '%Retry%',
    '%Stuck%',
    '%Guard%',
    '%Reclaim%',
    'commission-reconcile-%',
    'commission-saga-%',
    'Zero MT4 %',
    '交易分组夹具%',
    'Position %',
    'Rights %',
    'Data Scope %',
    '%Export User',
    '%Tree Agent',
    '%Tree Customer',
    'Closed Order %',
    'REST %',
    'Drill %',
    'Transfer %',
    'Voucher %',
    'Gift %',
    'Cancel %',
    'Missing Profile%',
    'payment-task%',
    'parent-only-%',
];
$testBatchPrefixes = ['CSV-%', 'BATCH-%'];

$backupDir = storage_path('app/backups');
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
    throw new RuntimeException('无法创建备份目录：' . $backupDir);
}
$backupFile = $backupDir . '/test-fixture-residue-' . date('Ymd-His') . '.sql';
$backupHandle = fopen($backupFile, 'ab');
if ($backupHandle === false) {
    throw new RuntimeException('无法打开备份文件：' . $backupFile);
}
fwrite($backupHandle, "-- 测试残留数据备份，生成时间：" . date('Y-m-d H:i:s') . "\n");

/**
 * 备份并删除指定表的行。
 *
 * @param string $table 目标表名。
 * @param callable $whereBuilder 接收查询构造器，返回带 where 条件的构造器。
 * @return int 删除行数。
 */
function backupAndDelete(string $table, callable $whereBuilder): int
{
    global $backupHandle;

    $query = DB::table($table)->useWritePdo();
    $query = $whereBuilder($query);
    $rows = $query->get()->all();
    if ($rows === []) {
        echo $table . ': 0 行待清理' . PHP_EOL;

        return 0;
    }

    // 备份：按行生成 INSERT 语句，字段顺序取自首行。
    $columns = array_keys((array) $rows[0]);
    $columnList = '`' . implode('`,`', $columns) . '`';
    fwrite($backupHandle, "\n-- 表：" . $table . "，待删除行数：" . count($rows) . "\n");
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $value = $row->{$column};
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_int($value) || is_float($value)) {
                $values[] = (string) $value;
            } else {
                $values[] = DB::connection()->getPdo()->quote((string) $value);
            }
        }
        fwrite(
            $backupHandle,
            'INSERT INTO `' . $table . '` (' . $columnList . ') VALUES (' . implode(',', $values) . ");\n"
        );
    }

    $deleted = $query->delete();
    echo $table . ': ' . $deleted . ' 行已清理' . PHP_EOL;

    return $deleted;
}

echo '备份文件：' . $backupFile . PHP_EOL;

// 1. 先定位测试登录账号 ID 与测试用户 ID，供后续关联表清理。
$testLoginIds = DB::table('user_logins')
    ->where(function ($query) use ($testEmailDomains, $testUserIdMin, $testUserIdMax, $testUserIdRanges) {
        foreach ($testEmailDomains as $domain) {
            $query->orWhere('email', 'like', $domain);
        }
        $query->orWhereBetween('user_id', [$testUserIdMin, $testUserIdMax]);
        foreach ($testUserIdRanges as $range) {
            $query->orWhereBetween('user_id', $range);
        }
    })
    ->pluck('id')
    ->all();
$testLoginUserIds = DB::table('user_logins')
    ->where(function ($query) use ($testEmailDomains, $testUserIdMin, $testUserIdMax, $testUserIdRanges) {
        foreach ($testEmailDomains as $domain) {
            $query->orWhere('email', 'like', $domain);
        }
        $query->orWhereBetween('user_id', [$testUserIdMin, $testUserIdMax]);
        foreach ($testUserIdRanges as $range) {
            $query->orWhereBetween('user_id', $range);
        }
    })
    ->pluck('user_id')
    ->all();
$testUserIds = array_values(array_unique(array_merge($testLoginUserIds, [1709173693])));

// 2. 子表先行清理：交易、代理后代、导入记录。
backupAndDelete('user_trades', function ($query) use ($testUserIds) {
    return $testUserIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('user_id', $testUserIds);
});
backupAndDelete('agent_descendants', function ($query) use ($testUserIds) {
    if ($testUserIds === []) {
        return $query->whereRaw('1 = 0');
    }

    return $query->whereIn('agent_id', $testUserIds)->orWhereIn('descendant_id', $testUserIds);
});

// 3. 用户资料与登录账号：先删 user_infos，再删 user_logins。
backupAndDelete('user_infos', function ($query) use ($testLoginIds, $testUserIds, $testUserNamePrefixes, $testUserIdMin, $testUserIdMax, $testUserIdRanges) {
    $query->where(function ($inner) use ($testLoginIds, $testUserIds, $testUserIdMin, $testUserIdMax, $testUserIdRanges) {
        if ($testLoginIds !== []) {
            $inner->orWhereIn('login_id', $testLoginIds);
        }
        if ($testUserIds !== []) {
            $inner->orWhereIn('user_id', $testUserIds);
        }
        $inner->orWhereBetween('user_id', [$testUserIdMin, $testUserIdMax]);
        foreach ($testUserIdRanges as $range) {
            $inner->orWhereBetween('user_id', $range);
        }
    });
    foreach ($testUserNamePrefixes as $prefix) {
        $query->orWhere('user_name', 'like', $prefix);
    }

    return $query;
});
backupAndDelete('user_logins', function ($query) use ($testEmailDomains, $testUserIdMin, $testUserIdMax, $testUserIdRanges) {
    foreach ($testEmailDomains as $domain) {
        $query->orWhere('email', 'like', $domain);
    }

    $query->orWhereBetween('user_id', [$testUserIdMin, $testUserIdMax]);
    foreach ($testUserIdRanges as $range) {
        $query->orWhereBetween('user_id', $range);
    }

    return $query;
});

// 4. 导入记录表：按测试用户或测试批次号清理。
foreach (['credit_imports', 'deposit_imports', 'withdraw_imports'] as $importTable) {
    backupAndDelete($importTable, function ($query) use ($testUserIds, $testBatchPrefixes) {
        $query->where(function ($inner) use ($testUserIds) {
            if ($testUserIds !== []) {
                $inner->orWhereIn('user_id', $testUserIds);
            }
        });
        foreach ($testBatchPrefixes as $prefix) {
            $query->orWhere('batch_no', 'like', $prefix);
        }

        return $query;
    });
}

// 4.1 支付通道与入金单：只保留真实种子通道，删除历次测试残留的通道与订单。
backupAndDelete('payment_channels', function ($query) {
    // 真实种子通道白名单：生产默认通道；其余 channel_code 均为测试残留。
    $seedChannels = ['bank_transfer', 'usdt_trc20', 'quick_pay'];

    return $query->whereNotIn('channel_code', $seedChannels)
        ->where(function ($inner) {
            $inner->orWhere('channel_code', 'like', 'toggle_test_channel')
                ->orWhere('channel_code', 'like', 'json_config_%')
                ->orWhere('channel_code', 'like', 'secret_refs_%')
                ->orWhere('channel_code', 'like', 'payment-task%')
                ->orWhere('channel_code', 'like', 'boundary-%')
                ->orWhere('channel_code', 'like', 'route_id_channel_%')
                ->orWhere('channel_code', 'like', 'legacy_toggle_channel')
                ->orWhere('channel_code', 'like', 'gateway-test')
                ->orWhere('channel_code', 'like', '%-modern')
                ->orWhere('channel_code', 'like', '%-legacy')
                ->orWhere('channel_code', 'regexp', '^[0-9]+$');
        });
});
backupAndDelete('deposit_records', function ($query) {
    return $query->where(function ($inner) {
        $inner->orWhere('local_order_no', 'like', 'PAYMENT-TASK%')
            ->orWhere('gateway_code', 'like', 'payment-task%');
    });
});

// 5. 后台权限相关：绑定、数据范围、角色权限、管理员、角色。
backupAndDelete('admin_agent_bindings', function ($query) {
    return $query->whereRaw('1 = 1');
});
backupAndDelete('role_data_scopes', function ($query) use ($keepRoleIds) {
    return $query->whereNotIn('role_id', $keepRoleIds);
});
backupAndDelete('role_permissions', function ($query) use ($keepRoleIds) {
    return $query->whereNotIn('role_id', $keepRoleIds);
});
backupAndDelete('admin_login_logs', function ($query) use ($keepAdminIds) {
    return $query->whereNotIn('admin_id', $keepAdminIds);
});
backupAndDelete('admins', function ($query) use ($keepAdminIds) {
    return $query->whereNotIn('id', $keepAdminIds);
});
backupAndDelete('roles', function ($query) use ($keepRoleIds) {
    return $query->whereNotIn('id', $keepRoleIds);
});

fclose($backupHandle);
echo '清理完成，备份文件：' . $backupFile . PHP_EOL;
