<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:55
 */

declare(strict_types=1);

/**
 * 返佣转移人工对账策略单元测试。
 *
 * 文件功能：
 * - 校验 CommissionTransferReconciliationPolicy::evaluate 只接受与原始阶段一致、证据完整的人工对账证据。
 * - 校验缺失、矛盾、格式非法或阶段不匹配的证据一律拒绝并返回明确错误码。
 *
 * 适用场景：
 * - 改动人工对账证据校验规则、余额格式校验或阶段决策矩阵后回归。
 *
 * 入参例子：
 * - evaluate('deposit', ['decision' => 'confirmed_completed', 'withdraw_reference' => 'W-1001', ...]) => 允许，终态 completed。
 * - evaluate('unknown', ['decision' => 'confirmed_rejected']) => 拒绝，错误码 unknown_manual_origin_step。
 *
 * 返回值：断言通过表示策略接受/拒绝结果与错误码完全一致。
 *
 * 异常或失败场景：
 * - 缺失凭证引用、余额为负数/科学计数/超精度、未知原始阶段、已处理命令重叠拒绝等任一情况被误放行时失败。
 */
namespace Tests\Unit;

use App\Services\CommissionTransfer\CommissionTransferReconciliationPolicy;
use PHPUnit\Framework\TestCase;

final class CommissionTransferReconciliationPolicyTest extends TestCase
{
    /**
     * @dataProvider acceptedEvidenceProvider
     * 校验仅与原始阶段一致的完整资金证据会被接受，并返回预期终态。
     *
     * @param string $originStep 人工对账原始阶段。
     * @param array $evidence 人工对账证据字段。
     * @param string $terminalStatus 期望终态。
     * @return void 断言通过不返回值。
     */
    public function test_accepts_only_stage_consistent_funding_evidence(
        string $originStep,
        array $evidence,
        string $terminalStatus
    ): void {
        $result = (new CommissionTransferReconciliationPolicy())->evaluate($originStep, $evidence);

        $this->assertTrue($result->isAllowed());
        $this->assertSame($terminalStatus, $result->terminalStatus());
        $this->assertNull($result->errorCode());
    }

    public static function acceptedEvidenceProvider(): array
    {
        return [
            'completed after deposit' => [
                'deposit',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-1001',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-1001',
                    'source_balance_after' => '975.00',
                    'target_balance_after' => '225.00',
                ]),
                'completed',
            ],
            'completed after an unknown withdrawal is externally verified' => [
                'withdraw',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-1001B',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-1001B',
                    'source_balance_after' => '0.00',
                    'target_balance_after' => '350.00',
                ]),
                'completed',
            ],
            'compensated after deposit rejection' => [
                'deposit',
                self::evidence([
                    'decision' => 'confirmed_compensated',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-1002',
                    'deposit_status' => 'confirmed_rejected',
                    'deposit_reference' => 'D-REJECT-1002',
                    'compensation_status' => 'confirmed_processed',
                    'compensation_reference' => 'C-1002',
                ]),
                'compensated',
            ],
            'compensated after an unknown withdrawal is verified and reversed' => [
                'withdraw',
                self::evidence([
                    'decision' => 'confirmed_compensated',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-1002B',
                    'deposit_status' => 'confirmed_not_processed',
                    'compensation_status' => 'confirmed_processed',
                    'compensation_reference' => 'C-1002B',
                ]),
                'compensated',
            ],
            'rejected before any funding command succeeded' => [
                'withdraw',
                self::evidence([
                    'decision' => 'confirmed_rejected',
                    'withdraw_status' => 'confirmed_rejected',
                    'withdraw_reference' => 'W-REJECT-1003',
                ]),
                'rejected',
            ],
        ];
    }

    /**
     * @dataProvider rejectedEvidenceProvider
     * 校验不完整或与阶段不一致的证据会被拒绝并返回指定错误码。
     *
     * @param string $originStep 人工对账原始阶段。
     * @param array $evidence 人工对账证据字段。
     * @param string $errorCode 期望错误码。
     * @return void 断言通过不返回值。
     */
    public function test_rejects_incomplete_or_stage_inconsistent_evidence(
        string $originStep,
        array $evidence,
        string $errorCode
    ): void {
        $result = (new CommissionTransferReconciliationPolicy())->evaluate($originStep, $evidence);

        $this->assertFalse($result->isAllowed());
        $this->assertNull($result->terminalStatus());
        $this->assertSame($errorCode, $result->errorCode());
    }

    public static function rejectedEvidenceProvider(): array
    {
        return [
            'completed lacks a deposit ticket' => [
                'accountinfo',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-2001',
                    'deposit_status' => 'confirmed_processed',
                    'source_balance_after' => '975.00',
                    'target_balance_after' => '225.00',
                ]),
                'deposit_reference_required',
            ],
            'completed lacks trusted target balance' => [
                'finalize',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-2002',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-2002',
                    'source_balance_after' => '975.00',
                ]),
                'target_balance_after_required',
            ],
            'completed rejects a negative balance' => [
                'accountinfo',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-NEGATIVE',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-NEGATIVE',
                    'source_balance_after' => '-0.01',
                    'target_balance_after' => '0.00',
                ]),
                'invalid_source_balance_after',
            ],
            'completed rejects scientific notation' => [
                'accountinfo',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-SCIENCE',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-SCIENCE',
                    'source_balance_after' => '1e3',
                    'target_balance_after' => '0.00',
                ]),
                'invalid_source_balance_after',
            ],
            'completed rejects too many decimal places' => [
                'accountinfo',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-SCALE',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-SCALE',
                    'source_balance_after' => '1.001',
                    'target_balance_after' => '0.00',
                ]),
                'invalid_source_balance_after',
            ],
            'completed rejects balance outside decimal range' => [
                'accountinfo',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-RANGE',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-RANGE',
                    'source_balance_after' => '10000000000000000.00',
                    'target_balance_after' => '0.00',
                ]),
                'invalid_source_balance_after',
            ],
            'completed is impossible before withdrawal' => [
                'limit',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-2003',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-2003',
                    'source_balance_after' => '975.00',
                    'target_balance_after' => '225.00',
                ]),
                'decision_not_allowed_for_origin_step',
            ],
            'compensated requires confirmed compensation' => [
                'compensate',
                self::evidence([
                    'decision' => 'confirmed_compensated',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-2004',
                    'deposit_status' => 'confirmed_rejected',
                    'compensation_status' => 'confirmed_not_processed',
                ]),
                'compensation_must_be_processed',
            ],
            'compensated rejected deposit requires its reference' => [
                'deposit',
                self::evidence([
                    'decision' => 'confirmed_compensated',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-REJECTED',
                    'deposit_status' => 'confirmed_rejected',
                    'compensation_status' => 'confirmed_processed',
                    'compensation_reference' => 'C-REJECTED',
                ]),
                'deposit_reference_required',
            ],
            'rejected cannot overlap a completed compensation' => [
                'compensate',
                self::evidence([
                    'decision' => 'confirmed_rejected',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-2005',
                    'deposit_status' => 'confirmed_rejected',
                    'compensation_status' => 'confirmed_processed',
                    'compensation_reference' => 'C-2005',
                ]),
                'decision_not_allowed_for_origin_step',
            ],
            'rejected fails when any funding command processed' => [
                'withdraw',
                self::evidence([
                    'decision' => 'confirmed_rejected',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => 'W-2006',
                ]),
                'withdraw_must_not_be_processed',
            ],
            'unknown origin always fails closed' => [
                'unknown',
                self::evidence(['decision' => 'confirmed_rejected']),
                'unknown_manual_origin_step',
            ],
            'unknown funding status always fails closed' => [
                'withdraw',
                self::evidence([
                    'decision' => 'confirmed_rejected',
                    'withdraw_status' => 'unknown',
                ]),
                'invalid_withdraw_status',
            ],
            'blank padded reference is rejected' => [
                'accountinfo',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => '   ',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-BLANK',
                    'source_balance_after' => '0.00',
                    'target_balance_after' => '0.00',
                ]),
                'withdraw_reference_required',
            ],
            'overlong reference is rejected' => [
                'accountinfo',
                self::evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => str_repeat('W', 101),
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => 'D-LONG',
                    'source_balance_after' => '0.00',
                    'target_balance_after' => '0.00',
                ]),
                'invalid_withdraw_reference',
            ],
        ];
    }

    private static function evidence(array $overrides): array
    {
        return array_merge([
            'decision' => '',
            'withdraw_status' => 'confirmed_not_processed',
            'withdraw_reference' => null,
            'deposit_status' => 'confirmed_not_processed',
            'deposit_reference' => null,
            'compensation_status' => 'confirmed_not_processed',
            'compensation_reference' => null,
            'source_balance_after' => null,
            'target_balance_after' => null,
        ], $overrides);
    }
}
