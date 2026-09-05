<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "symbol_spread 表数据检查\n";
echo "========================================\n\n";

try {
    $total = DB::connection('old_crm')->table('symbol_spread')->count();
    echo "总记录数：{$total}\n\n";

    $active = DB::connection('old_crm')->table('symbol_spread')->where('voided', 0)->count();
    $voided = DB::connection('old_crm')->table('symbol_spread')->where('voided', 1)->count();

    echo "有效记录 (voided=0)：{$active}\n";
    echo "作废记录 (voided=1)：{$voided}\n\n";

    if ($total > 0) {
        echo "前5条数据示例：\n";
        $samples = DB::connection('old_crm')->table('symbol_spread')->limit(5)->get();
        foreach ($samples as $row) {
            echo "  ID:{$row->id} spread:{$row->spread} agent_group_id:{$row->agent_group_id} ratio:{$row->spread_ratio} voided:{$row->voided}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ 查询失败：" . $e->getMessage() . "\n";
}
