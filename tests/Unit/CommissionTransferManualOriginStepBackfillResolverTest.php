<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:50
 */

declare(strict_types=1);

/**
 * 返佣转移人工对账原始步骤回填解析器单元测试。
 *
 * 文件功能：
 * - 校验 CommissionTransferManualOriginStepBackfillResolver::resolve 对“当前步骤 + 错误码”组合只能回填确定性的原始阶段。
 * - 校验无法判定时回退到 unknown，缺省错误码时 fail-closed。
 *
 * 适用场景：
 * - 改动人工对账原始阶段判定逻辑或错误码映射后回归。
 *
 * 入参例子：
 * - resolve('deposit', 'read_timeout') => 'deposit'（保留当前步骤）。
 * - resolve('manual_reconcile', 'payload_decrypt_failed') => 'verify'（仅验签）。
 * - resolve('manual_reconcile', 'read_timeout') => 'unknown'（多阶段通用传输错误）。
 *
 * 返回值：断言通过表示回填结果与预期阶段完全一致。
 *
 * 异常或失败场景：
 * - 错误码被误判（如子串匹配）、缺少错误码时未 fail-closed、或通用错误码被错误归因到单一阶段时失败。
 */
namespace Tests\Unit;

use App\Services\CommissionTransfer\CommissionTransferManualOriginStepBackfillResolver;
use PHPUnit\Framework\TestCase;

final class CommissionTransferManualOriginStepBackfillResolverTest extends TestCase
{
    /**
     * @dataProvider deterministicOriginProvider
     * 校验 resolve 仅对“当前步骤 + 错误码”组合给出确定性回填结果。
     *
     * @param string $currentStep 当前人工对账步骤。
     * @param string $errorCode 原始命令的错误码，可为 null。
     * @param string $expected 期望回填的原始阶段。
     * @return void 断言通过不返回值。
     */
    public function test_resolve_returns_deterministic_origin(
        string $currentStep,
        string $errorCode = null,
        string $expected
    ): void {
        $resolver = new CommissionTransferManualOriginStepBackfillResolver();

        $this->assertSame($expected, $resolver->resolve($currentStep, $errorCode));
    }

    /**
     * 提供“当前步骤 + 错误码 => 期望阶段”的确定性用例。
     *
     * @return array<string, array<int, string|null>> dataProvider 用例集合。
     */
    public static function deterministicOriginProvider(): array
    {
        return [
            'preserved current step wins' => ['deposit', 'read_timeout', 'deposit'],
            'payload decryption is verify only' => ['manual_reconcile', 'payload_decrypt_failed', 'verify'],
            'canonical withdrawal unknown' => ['manual_reconcile', 'withdraw_result_unknown', 'withdraw'],
            'canonical deposit unknown' => ['manual_reconcile', 'deposit_result_unknown', 'deposit'],
            'canonical compensation unknown' => ['manual_reconcile', 'compensation_result_uncertain', 'compensate'],
            'canonical account snapshot rejection' => ['manual_reconcile', 'accountinfo_rejected', 'accountinfo'],
            'transport code can occur on multiple stages' => ['manual_reconcile', 'read_timeout', 'unknown'],
            'stale financial claim can occur on three stages' => ['manual_reconcile', 'stale_financial_command_claim', 'unknown'],
            'substring is not evidence' => ['manual_reconcile', 'custom_deposit_provider_failure', 'unknown'],
            'missing error code fails closed' => ['manual_reconcile', null, 'unknown'],
        ];
    }
}
