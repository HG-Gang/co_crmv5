<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "层级关系表分析\n";
echo "====================================\n\n";

$oldDb = 'old_crm';

// 1. agent_relations
echo "【agent_relations】\n";
$sample1 = DB::connection($oldDb)->table('agent_relations')->limit(5)->get();
echo "样本数据:\n";
foreach ($sample1 as $row) {
    echo "  parent_id={$row->parent_id}, child_id={$row->child_id}\n";
}
echo "\n";

// 2. closure_table
echo "【closure_table】\n";
$cols = DB::connection($oldDb)->select('SHOW COLUMNS FROM closure_table');
echo "字段:\n";
foreach ($cols as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}
$sample2 = DB::connection($oldDb)->table('closure_table')->limit(5)->get();
echo "样本数据:\n";
foreach ($sample2 as $row) {
    $data = (array)$row;
    echo "  " . json_encode($data) . "\n";
}
echo "\n";

// 3. hierarchy
echo "【hierarchy】\n";
$cols = DB::connection($oldDb)->select('SHOW COLUMNS FROM hierarchy');
echo "字段:\n";
foreach ($cols as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}
$sample3 = DB::connection($oldDb)->table('hierarchy')->limit(5)->get();
echo "样本数据:\n";
foreach ($sample3 as $row) {
    $data = (array)$row;
    echo "  " . json_encode($data) . "\n";
}
echo "\n";

// 检查记录数
echo "记录数对比:\n";
echo "  agent_relations: " . DB::connection($oldDb)->table('agent_relations')->count() . "\n";
echo "  closure_table: " . DB::connection($oldDb)->table('closure_table')->count() . "\n";
echo "  hierarchy: " . DB::connection($oldDb)->table('hierarchy')->count() . "\n";

// 检查 user_trades 的字段含义
echo "\n====================================\n";
echo "user_trades 字段分析\n";
echo "====================================\n\n";

$sample = DB::connection($oldDb)->table('user_trades')
    ->where('commission_agent', '>', 0)
    ->limit(3)
    ->get();

echo "有佣金的样本记录:\n";
foreach ($sample as $row) {
    echo "  user_id={$row->user_id}, ticket={$row->ticket}, ";
    echo "commission={$row->commission}, commission_agent={$row->commission_agent}, ";
    echo "profit={$row->profit}, comment={$row->comment}\n";
}
