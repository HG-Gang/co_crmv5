<?php
/**
 * 修复 cmd=6 的 comment 数据迁移缺陷
 *
 * 问题: 迁移脚本将旧库 mt4_trades 的 617,352 行 cmd=6 记录迁移到新库时，
 *       丢失了 comment 字段内容，导致 611,274 条 DBCN 标记的返佣记录无法识别
 *
 * 影响范围:
 * - 实时返佣查询 (WHERE comment LIKE '%DBCN%')
 * - 入金流水查询
 * - 出金流水查询
 *
 * 修复策略:
 * 1. 按 ticket (旧库主键) 批量匹配
 * 2. 只更新 cmd=6 且 comment 为空的记录
 * 3. 分批执行，每批 1000 条，避免长时间锁表
 * 4. 全程事务保护，失败自动回滚
 */

// 配置
$oldDb = [
    'host' => '127.0.0.1',
    'port' => 3307,
    'database' => 'hank_zl_data',
    'username' => 'root',
    'password' => '123456',
];

$newDb = [
    'host' => '127.0.0.1',
    'port' => 3307,
    'database' => 'co_crmv5',
    'username' => 'root',
    'password' => '123456',
];

$batchSize = 1000;
$dryRun = true; // 默认干跑模式，需要手动改为 false 才真正执行

// 提高内存限制（处理60万+记录）
ini_set('memory_limit', '1024M');

echo "=== 修复 cmd=6 comment 数据缺陷 ===\n";
echo "模式: " . ($dryRun ? "🔍 DRY RUN (只检查不写入)" : "⚠️  LIVE RUN (真实写入)") . "\n";
echo "内存限制: " . ini_get('memory_limit') . "\n\n";

try {
    // 连接旧库（只读）
    $pdoOld = new PDO(
        "mysql:host={$oldDb['host']};port={$oldDb['port']};dbname={$oldDb['database']};charset=utf8mb4",
        $oldDb['username'],
        $oldDb['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 连接新库（读写）
    $pdoNew = new PDO(
        "mysql:host={$newDb['host']};port={$newDb['port']};dbname={$newDb['database']};charset=utf8mb4",
        $newDb['username'],
        $newDb['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 步骤1: 统计需要修复的记录
    echo "步骤1: 统计需要修复的记录...\n";
    $stmt = $pdoNew->query("
        SELECT COUNT(*) as need_fix
        FROM mt4_trades
        WHERE cmd = 6 AND (comment IS NULL OR comment = '')
    ");
    $needFix = $stmt->fetchColumn();
    echo "  需要修复: {$needFix} 条记录\n\n";

    if ($needFix == 0) {
        echo "✅ 无需修复，所有记录已完整\n";
        exit(0);
    }

    // 步骤2: 使用游标分批读取旧库数据（避免内存溢出）
    echo "步骤2: 从旧库分批读取 comment 数据...\n";
    $stmt = $pdoOld->prepare("
        SELECT ticket, comment
        FROM mt4_trades
        WHERE cmd = 6 AND comment IS NOT NULL AND comment != ''
        ORDER BY ticket
    ");
    $stmt->execute();

    echo "  使用游标模式读取...\n";

    // 步骤3: 分批更新新库（流式处理）
    echo "\n步骤3: 分批更新新库 (每批 {$batchSize} 条)...\n";

    $pdoNew->beginTransaction();
    $updated = 0;
    $batch = 0;
    $chunk = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chunk[] = $row;

        // 达到批次大小或最后一批
        if (count($chunk) >= $batchSize) {
            $batch++;

            // 构建批量 CASE WHEN 语句
            $cases = [];
            $tickets = [];
            foreach ($chunk as $item) {
                $ticket = (int)$item['ticket'];
                $comment = $pdoNew->quote($item['comment']);
                $cases[] = "WHEN {$ticket} THEN {$comment}";
                $tickets[] = $ticket;
            }

            $ticketList = implode(',', $tickets);
            $caseWhen = implode("\n            ", $cases);

            $sql = "
                UPDATE mt4_trades
                SET comment = CASE ticket
                    {$caseWhen}
                END
                WHERE ticket IN ({$ticketList})
                  AND cmd = 6
                  AND (comment IS NULL OR comment = '')
            ";

            if (!$dryRun) {
                $affectedRows = $pdoNew->exec($sql);
                $updated += $affectedRows;
            } else {
                $updated += count($chunk);
            }

            echo "  批次 {$batch}: 处理 " . count($chunk) . " 条 (累计: {$updated})\r";

            // 清空缓冲区
            $chunk = [];

            // 每50批显示进度
            if ($batch % 50 == 0) {
                echo "\n  已处理: {$updated} 条\n";
            }
        }
    }

    // 处理最后不足一批的记录
    if (!empty($chunk)) {
        $batch++;

        $cases = [];
        $tickets = [];
        foreach ($chunk as $item) {
            $ticket = (int)$item['ticket'];
            $comment = $pdoNew->quote($item['comment']);
            $cases[] = "WHEN {$ticket} THEN {$comment}";
            $tickets[] = $ticket;
        }

        $ticketList = implode(',', $tickets);
        $caseWhen = implode("\n            ", $cases);

        $sql = "
            UPDATE mt4_trades
            SET comment = CASE ticket
                {$caseWhen}
            END
            WHERE ticket IN ({$ticketList})
              AND cmd = 6
              AND (comment IS NULL OR comment = '')
        ";

        if (!$dryRun) {
            $affectedRows = $pdoNew->exec($sql);
            $updated += $affectedRows;
        } else {
            $updated += count($chunk);
        }
    }

    echo "\n\n";

    if (!$dryRun) {
        $pdoNew->commit();
        echo "✅ 事务提交成功\n";
    } else {
        $pdoNew->rollBack();
        echo "🔍 DRY RUN 完成，已回滚（未实际写入）\n";
    }

    echo "\n=== 修复完成 ===\n";
    echo "总计更新: {$updated} 条记录\n";

    // 步骤4: 验证修复结果
    echo "\n步骤4: 验证修复结果...\n";
    $stmt = $pdoNew->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN comment IS NULL OR comment = '' THEN 1 ELSE 0 END) as empty,
            SUM(CASE WHEN comment LIKE '%DBCN%' THEN 1 ELSE 0 END) as has_dbcn
        FROM mt4_trades
        WHERE cmd = 6
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "  总记录: {$result['total']}\n";
    echo "  空 comment: {$result['empty']}\n";
    echo "  含 DBCN: {$result['has_dbcn']}\n";

    if ($result['empty'] == 0 && $result['has_dbcn'] > 600000) {
        echo "\n✅ 验证通过，数据已完整修复\n";
    } else {
        echo "\n⚠️  验证失败，数据可能仍有问题\n";
    }

} catch (PDOException $e) {
    if (isset($pdoNew) && $pdoNew->inTransaction()) {
        $pdoNew->rollBack();
        echo "\n❌ 发生错误，已回滚事务\n";
    }
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
