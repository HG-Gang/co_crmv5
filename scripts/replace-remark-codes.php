<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:38
 */

/**
 * 将散落硬编码 MT4 备注码替换为 Mt4RemarkCodes 常量的历史一次性脚本。
 *
 * 文件功能：
 * - 把 Job 中 'DBUN-'、'WDUN-' 等硬编码备注码替换为常量引用，对齐旧项目全缀体系。
 *
 * 适用场景：
 * - 仅历史修复用，当前代码无需再执行。
 */

// 替换散落硬编码备注码为 Mt4RemarkCodes 常量（对齐旧项目全缀体系）。
$jobs = [
    ['app/Jobs/SettleDepositPayment.php', "'comment' => 'DBUN-'", "'comment' => \\App\\Constants\\Mt4RemarkCodes::DBUN"],
    ['app/Jobs/ProcessWithdrawFunding.php', "'comment' => 'WDUN-'", "'comment' => \\App\\Constants\\Mt4RemarkCodes::WDUN"],
    ['app/Jobs/RefundDepositPayment.php', "'comment' => 'DBRF-'", "'comment' => \\App\\Constants\\Mt4RemarkCodes::DBRF"],
    ['app/Jobs/RefundWithdrawFunding.php', "'comment' => 'WDRF-'", "'comment' => \\App\\Constants\\Mt4RemarkCodes::WDRF"],
    ['app/Services/Legacy/LegacyCommissionSummaryService.php', "'comment' => 'DBCN'", "'comment' => \\App\\Constants\\Mt4RemarkCodes::DBCN"],
    ['app/Services/Legacy/LegacySpreadCommissionSummaryService.php', "'comment' => 'DBCN'", "'comment' => \\App\\Constants\\Mt4RemarkCodes::DBCN"],
    ['app/Http/Controllers/Admin/AdminWhsExpZeroController.php', "\$comment = 'WHS_ZERO:'", "\$comment = \\App\\Constants\\Mt4RemarkCodes::WHS_ZERO"],
    ['app/Http/Controllers/Admin/RiskController.php', "\$comment = 'CRM risk force close #'", "\$comment = \\App\\Constants\\Mt4RemarkCodes::RISK_FORCE_CLOSE"],
    ['app/Http/Controllers/Admin/RealtimeCommissionController.php', "stripos(\$comment, 'DBCN')", "stripos(\$comment, \\App\\Constants\\Mt4RemarkCodes::DBCN)"],
];

foreach ($jobs as $job) {
    [$file, $old, $new] = $job;
    if (! is_file($file)) {
        echo "MISS: $file\n";
        continue;
    }
    $c = file_get_contents($file);
    if (strpos($c, $new) !== false) {
        echo "already: $file\n";
        continue;
    }
    if (strpos($c, $old) === false) {
        echo "OLD NOT FOUND: $file :: $old\n";
        continue;
    }
    $c = str_replace($old, $new, $c);
    file_put_contents($file, $c);
    echo "replaced: $file\n";
}
