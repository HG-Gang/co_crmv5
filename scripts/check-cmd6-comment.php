<?php
/**
 * 检查 cmd=6 的 comment 数据完整性
 *
 * 用途：验证审计文档中报告的数据迁移缺陷
 */

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=co_crmv5', 'root', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 检查新库 co_crmv5 ===\n";
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_cmd6,
            SUM(CASE WHEN comment IS NULL OR comment = '' THEN 1 ELSE 0 END) as empty_comment,
            SUM(CASE WHEN comment LIKE '%DBCN%' THEN 1 ELSE 0 END) as has_dbcn,
            SUM(CASE WHEN comment LIKE '%-FY%' THEN 1 ELSE 0 END) as has_fy
        FROM mt4_trades
        WHERE cmd = 6
    ");
    $newDb = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "新库 cmd=6 总行数: {$newDb['total_cmd6']}\n";
    echo "空 comment:        {$newDb['empty_comment']}\n";
    echo "含 DBCN:           {$newDb['has_dbcn']}\n";
    echo "含 -FY:            {$newDb['has_fy']}\n\n";

    // 检查旧库
    $pdo2 = new PDO('mysql:host=127.0.0.1;port=3307;dbname=hank_zl_data', 'root', '123456');
    $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 检查旧库 hank_zl_data ===\n";
    $stmt2 = $pdo2->query("
        SELECT
            COUNT(*) as total_cmd6,
            SUM(CASE WHEN comment IS NULL OR comment = '' THEN 1 ELSE 0 END) as empty_comment,
            SUM(CASE WHEN comment LIKE '%DBCN%' THEN 1 ELSE 0 END) as has_dbcn,
            SUM(CASE WHEN comment LIKE '%-FY%' THEN 1 ELSE 0 END) as has_fy
        FROM mt4_trades
        WHERE cmd = 6
    ");
    $oldDb = $stmt2->fetch(PDO::FETCH_ASSOC);

    echo "旧库 cmd=6 总行数: {$oldDb['total_cmd6']}\n";
    echo "空 comment:        {$oldDb['empty_comment']}\n";
    echo "含 DBCN:           {$oldDb['has_dbcn']}\n";
    echo "含 -FY:            {$oldDb['has_fy']}\n\n";

    // 结论
    echo "=== 结论 ===\n";
    if ($newDb['empty_comment'] > 0 && $oldDb['has_dbcn'] > 0) {
        echo "🔴 确认数据缺陷：新库丢失了 {$oldDb['has_dbcn']} 条 DBCN 标记的返佣记录\n";
        echo "   影响范围：实时返佣、入金流水、出金流水查询将返回空\n";
    } else {
        echo "✅ 数据完整，无缺失\n";
    }

} catch (PDOException $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}
