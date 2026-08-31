<?php
/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 19:00
 */

/**
 * MemberCommentCoverageTest
 *
 * 文件功能：
 * - docs/中文注释标准-v0.0.3.md §5.7 的 CI 门禁：断言范围内全部类属性与类常量都拥有含中文的
 *   PHPDoc 注释块（只写类型不算达标）。
 * - 复用 tools/audit-members.php 的机器可校验口径，避免同一规则在测试与工具中实现两次产生漂移。
 * - 工具输出 `--quiet` 统计并以退出码表达结果：0 表示无缺失，1 表示存在缺失。
 *
 * 适用场景：
 * - composer run audit-members 与本测试共用同一口径；新增属性忘写中文注释时，本地与 CI 立即失败。
 *
 * 返回值：
 * - 工具退出码为 0 时测试通过；非 0 时测试失败并附缺失摘要尾部，便于直接定位文件与成员。
 *
 * 异常或失败场景：
 * - 工具不可执行或 PHP 进程异常时按失败处理，不允许静默跳过。
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class MemberCommentCoverageTest extends TestCase
{
    /** 审计工具必须在限定秒数内返回，防止范围扩张后拖慢 CI 反馈。 */
    private const TOOL_TIMEOUT_SECONDS = 60;

    public function test_all_class_members_have_chinese_doc_blocks(): void
    {
        $root = dirname(__DIR__, 2);
        $tool = $root . '/tools/audit-members.php';
        $this->assertFileExists($tool, '注释审计工具缺失：' . $tool);

        $command = sprintf(
            'php %s --quiet 2>&1',
            escapeshellarg($tool)
        );
        $outputLines = [];
        $exitCode = 0;
        exec($command, $outputLines, $exitCode);

        $summary = implode(' | ', array_slice($outputLines, -3));
        $this->assertSame(
            0,
            $exitCode,
            '存在缺失中文注释的类属性/类常量（详见 composer run audit-members 输出）：' . $summary
        );

        // 统计行必须报告已审计成员总数，防止工具静默失败被误判为通过。
        $joined = implode("\n", $outputLines);
        $this->assertMatchesRegularExpression('/members total: [1-9]\d*/', $joined, '审计工具未报告成员总数，疑似未真正执行。');
    }
}
