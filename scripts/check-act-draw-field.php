<?php
/**
 * 检查旧库 draw_record_log.act_draw 字段含义
 *
 * 目的：确认 act_draw 存储的是 USD 还是 RMB
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=hank_zl_data', 'root', '123456');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== 检查 act_draw 字段含义 ===\n\n";

$stmt = $pdo->query("
    SELECT
        record_id,
        apply_amount,
        act_apply_amount,
        act_draw,
        draw_rate,
        draw_poundage,
        act_pdg_rmb,
        act_draw / draw_rate as calculated_usd,
        act_apply_amount * draw_rate as calculated_rmb
    FROM draw_record_log
    WHERE apply_status = '2' AND draw_rate > 0
    LIMIT 10
");

echo "字段对比分析（前10条已完成出金）：\n";
echo str_repeat("-", 120) . "\n";
printf("%-6s %-10s %-10s %-10s %-8s %-10s %-10s\n",
    "ID", "申请USD", "实际USD?", "act_draw", "汇率", "act/汇率", "计算RMB");
echo str_repeat("-", 120) . "\n";

$pattern_usd = 0;  // act_draw 是 USD 的记录数
$pattern_rmb = 0;  // act_draw 是 RMB 的记录数

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("%-6d %-10.2f %-10.2f %-10.2f %-8.4f %-10.2f %-10.2f\n",
        $row['record_id'],
        $row['apply_amount'],
        $row['act_apply_amount'],
        $row['act_draw'],
        $row['draw_rate'],
        $row['calculated_usd'],
        $row['calculated_rmb']
    );

    // 判断：如果 act_draw ≈ act_apply_amount * draw_rate，说明是 RMB
    $diff_rmb = abs($row['act_draw'] - $row['calculated_rmb']);
    // 判断：如果 act_draw ≈ act_apply_amount，说明是 USD
    $diff_usd = abs($row['act_draw'] - $row['act_apply_amount']);

    if ($diff_rmb < 1) {
        $pattern_rmb++;
    } elseif ($diff_usd < 1) {
        $pattern_usd++;
    }
}

echo "\n=== 结论 ===\n";
echo "act_draw 接近 USD 的记录数: {$pattern_usd}\n";
echo "act_draw 接近 RMB 的记录数: {$pattern_rmb}\n";

if ($pattern_rmb > $pattern_usd) {
    echo "\n🔴 确认：act_draw 字段存储的是**人民币金额**（已乘汇率）\n";
    echo "   迁移逻辑错误：应该使用 act_apply_amount 而非 act_draw\n";
} else {
    echo "\n✅ act_draw 字段存储的是美元金额\n";
}
