<?php
/**
 * 验证审计文档中提到的3个数据口径问题
 *
 * 问题来源：docs/audits/2026-08-30-handoff-resume-here.md §5.3
 */

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=co_crmv5', 'root', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 数据口径问题验证 ===\n\n";

    // 问题1: 前台 actdraw 取 USD 未乘汇率
    echo "问题1: 检查前台 actdraw 字段口径\n";
    echo "---------------------------------------\n";

    $stmt = $pdo->query("
        SELECT
            id,
            user_id,
            apply_amount,
            actual_amount,
            exchange_rate,
            apply_amount * exchange_rate as calculated_rmb,
            actual_amount * exchange_rate as actual_rmb
        FROM withdraw_records
        WHERE status = 2
        LIMIT 5
    ");

    echo "出金记录示例（前5条已完成）：\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf(
            "  ID:%d 用户:%d 申请USD:%.2f 实际USD:%.2f 汇率:%.4f 计算RMB:%.2f 实际RMB:%.2f\n",
            $row['id'],
            $row['user_id'],
            $row['apply_amount'],
            $row['actual_amount'],
            $row['exchange_rate'],
            $row['calculated_rmb'],
            $row['actual_rmb']
        );
    }

    // 检查旧库对比
    $pdoOld = new PDO('mysql:host=127.0.0.1;port=3307;dbname=hank_zl_data', 'root', '123456');
    $pdoOld->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdoOld->query("
        SELECT
            record_id,
            user_id,
            apply_amount,
            act_apply_amount as actual_usd,
            act_draw as actual_rmb_stored,
            draw_rate,
            act_pdg_rmb as fee_rmb,
            draw_poundage as fee_usd,
            act_apply_amount * draw_rate as calculated_rmb
        FROM draw_record_log
        WHERE apply_status = '2'
        LIMIT 5
    ");

    echo "\n旧库对应记录（draw_record_log, apply_status=2，前5条）：\n";
    echo "  注意：act_draw存储RMB，act_apply_amount存储USD\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf(
            "  ID:%d 用户:%d 申请:%.2f 实际USD:%.2f 存储RMB:%.2f 汇率:%.4f 计算RMB:%.2f 手续费USD:%.2f\n",
            $row['record_id'],
            $row['user_id'],
            $row['apply_amount'],
            $row['actual_usd'],
            $row['actual_rmb_stored'],
            $row['draw_rate'],
            $row['calculated_rmb'],
            $row['fee_usd']
        );
    }

    // 问题2: 出金合计行 drawpoundage 口径
    echo "\n\n问题2: 检查出金合计行口径\n";
    echo "---------------------------------------\n";

    // 新库的聚合方式
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_records,
            SUM(fee) as total_fee_usd,
            SUM(rmb_fee) as total_fee_rmb,
            SUM(actual_amount) as total_actual_usd
        FROM withdraw_records
        WHERE status = 2
    ");
    $newAggregate = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "新库聚合（withdraw_records）：\n";
    echo "  记录数: {$newAggregate['total_records']}\n";
    echo "  手续费USD合计: " . number_format($newAggregate['total_fee_usd'], 2) . "\n";
    echo "  手续费RMB合计: " . number_format($newAggregate['total_fee_rmb'], 2) . "\n";
    echo "  实际金额USD合计: " . number_format($newAggregate['total_actual_usd'], 2) . "\n";

    // 旧库的聚合方式（使用正确字段）
    $stmt = $pdoOld->query("
        SELECT
            COUNT(*) as total_records,
            SUM(draw_poundage) as total_fee_usd,
            SUM(act_pdg_rmb) as total_fee_rmb,
            SUM(act_apply_amount) as total_actual_usd,
            SUM(act_draw) as total_actual_rmb_stored
        FROM draw_record_log
        WHERE apply_status = '2'
    ");
    $oldAggregate = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n旧库聚合（draw_record_log）：\n";
    echo "  记录数: {$oldAggregate['total_records']}\n";
    echo "  手续费USD合计: " . number_format($oldAggregate['total_fee_usd'], 2) . "\n";
    echo "  手续费RMB合计: " . number_format($oldAggregate['total_fee_rmb'], 2) . "\n";
    echo "  实际金额USD合计: " . number_format($oldAggregate['total_actual_usd'], 2) . "\n";
    echo "  (act_draw RMB存储: " . number_format($oldAggregate['total_actual_rmb_stored'], 2) . ")\n";

    // 对比差异
    $fee_diff_usd = abs($newAggregate['total_fee_usd'] - $oldAggregate['total_fee_usd']);
    $fee_diff_rmb = abs($newAggregate['total_fee_rmb'] - $oldAggregate['total_fee_rmb']);

    echo "\n口径差异分析：\n";
    echo "  手续费USD差异: " . number_format($fee_diff_usd, 2) . "\n";
    echo "  手续费RMB差异: " . number_format($fee_diff_rmb, 2) . "\n";

    if ($fee_diff_usd > 1 || $fee_diff_rmb > 1) {
        echo "  ⚠️  发现显著差异，需要检查口径是否一致\n";
    } else {
        echo "  ✅ 差异在可接受范围内\n";
    }

    // 问题3: 先乘后总 vs 逐行先舍的尾差
    echo "\n\n问题3: 检查舍入尾差\n";
    echo "---------------------------------------\n";

    $stmt = $pdo->query("
        SELECT
            id,
            actual_amount,
            exchange_rate,
            ROUND(actual_amount * exchange_rate, 2) as rounded_first,
            actual_amount * exchange_rate as multiply_first
        FROM withdraw_records
        WHERE status = 2
        LIMIT 10
    ");

    $total_rounded_first = 0;
    $total_multiply_first = 0;
    $differences = [];

    echo "逐行计算对比（前10条）：\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rounded = $row['rounded_first'];
        $multiply = $row['multiply_first'];
        $diff = abs($rounded - $multiply);

        $total_rounded_first += $rounded;
        $total_multiply_first += $multiply;

        if ($diff > 0.01) {
            $differences[] = [
                'id' => $row['id'],
                'diff' => $diff
            ];
        }

        echo sprintf(
            "  ID:%d 先舍:%.2f 先乘:%.6f 差值:%.6f\n",
            $row['id'],
            $rounded,
            $multiply,
            $diff
        );
    }

    echo "\n合计对比：\n";
    echo "  先舍后总: " . number_format($total_rounded_first, 2) . "\n";
    echo "  先乘后舍: " . number_format(round($total_multiply_first, 2), 2) . "\n";
    echo "  尾差: " . number_format(abs($total_rounded_first - round($total_multiply_first, 2)), 2) . "\n";

    if (!empty($differences)) {
        echo "  ⚠️  发现 " . count($differences) . " 条记录存在舍入差异\n";
    } else {
        echo "  ✅ 未发现显著舍入差异\n";
    }

    echo "\n=== 验证完成 ===\n";

} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
    exit(1);
}
