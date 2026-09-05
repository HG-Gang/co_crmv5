<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "检查MT4交易记录的close_time字段...\n\n";

// 检查close_time为NULL的记录
$nullCount = DB::connection('old_crm')
    ->table('mt4_trades')
    ->whereNull('CLOSE_TIME')
    ->count();

echo "CLOSE_TIME为NULL的记录：{$nullCount}条\n";

// 检查close_time为空字符串的记录
$emptyCount = DB::connection('old_crm')
    ->table('mt4_trades')
    ->where('CLOSE_TIME', '')
    ->count();

echo "CLOSE_TIME为空字符串的记录：{$emptyCount}条\n";

// 检查close_time为'0000-00-00 00:00:00'的记录
$zeroCount = DB::connection('old_crm')
    ->table('mt4_trades')
    ->where('CLOSE_TIME', '0000-00-00 00:00:00')
    ->count();

echo "CLOSE_TIME为'0000-00-00 00:00:00'的记录：{$zeroCount}条\n";

// 采样几条
$samples = DB::connection('old_crm')
    ->table('mt4_trades')
    ->select('TICKET', 'CLOSE_TIME', 'CMD')
    ->limit(10)
    ->get();

echo "\n前10条记录的CLOSE_TIME样本：\n";
foreach ($samples as $sample) {
    $closeTime = $sample->CLOSE_TIME ?? 'NULL';
    echo "  TICKET: {$sample->TICKET}, CMD: {$sample->CMD}, CLOSE_TIME: '{$closeTime}'\n";
}
