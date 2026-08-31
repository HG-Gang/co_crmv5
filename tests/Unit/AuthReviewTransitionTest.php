<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 15:27
 */

/**
 * AuthReviewTransitionTest
 *
 * 文件功能：
 * - 验证实名审核状态转移：混合组件决定独立生效、单组件通过保留另一组件字段并正确派生 user_auth 状态、银行卡换卡提升待审快照、旧 flag 仅生成旧控制器会审核的组件、非法/非待审/歧义决定全部拒绝。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AuthReviewTransition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AuthReviewTransitionTest extends TestCase
{
    public function test_mixed_component_decisions_are_applied_independently(): void
    {
        $decisions = AuthReviewTransition::normalizeDecisions([
            'id_card_decision' => 1,
            'bank_decision' => 2,
            'id_card_reason' => 'must be cleared after approval',
            'bank_reason' => 'bank account name mismatch',
        ]);

        $transition = AuthReviewTransition::resolve([
            'id_card_status' => 1,
            'id_card_remarks' => 'old id card rejection',
            'bank_status' => 1,
            'bank_remarks' => '',
            'is_bank_synced' => 0,
        ], $decisions);

        $this->assertSame([
            'id_card_status' => 2,
            'id_card_remarks' => '',
            'bank_status' => 4,
            'bank_remarks' => 'bank account name mismatch',
        ], $transition['auth_updates']);
        $this->assertSame(2, $transition['final_id_card_status']);
        $this->assertSame(4, $transition['final_bank_status']);
        $this->assertSame(2, $transition['user_auth_status']);
        $this->assertFalse($transition['bank_sync_required']);
    }

    public function test_id_card_only_decision_preserves_bank_fields_and_can_complete_authentication(): void
    {
        $transition = AuthReviewTransition::resolve([
            'id_card_status' => 1,
            'id_card_remarks' => 'old id card rejection',
            'bank_status' => 2,
            'bank_remarks' => 'approved bank remark must remain',
            'is_bank_synced' => 1,
        ], AuthReviewTransition::normalizeDecisions([
            'id_card_decision' => 1,
        ]));

        $this->assertSame([
            'id_card_status' => 2,
            'id_card_remarks' => '',
        ], $transition['auth_updates']);
        $this->assertArrayNotHasKey('bank_status', $transition['auth_updates']);
        $this->assertArrayNotHasKey('bank_remarks', $transition['auth_updates']);
        $this->assertArrayNotHasKey('is_bank_synced', $transition['auth_updates']);
        $this->assertSame(1, $transition['user_auth_status']);
        $this->assertFalse($transition['bank_sync_required']);
    }

    public function test_bank_only_approval_preserves_id_card_fields_and_requires_mt4_sync(): void
    {
        $transition = AuthReviewTransition::resolve([
            'id_card_status' => 2,
            'id_card_remarks' => 'approved id card remark must remain',
            'bank_status' => 1,
            'bank_remarks' => 'old bank rejection',
            'is_bank_synced' => 0,
        ], AuthReviewTransition::normalizeDecisions([
            'bank_decision' => 1,
        ]));

        $this->assertSame([
            'bank_status' => 2,
            'bank_remarks' => '',
            'is_bank_synced' => 1,
        ], $transition['auth_updates']);
        $this->assertArrayNotHasKey('id_card_status', $transition['auth_updates']);
        $this->assertArrayNotHasKey('id_card_remarks', $transition['auth_updates']);
        $this->assertSame(1, $transition['user_auth_status']);
        $this->assertTrue($transition['bank_sync_required']);
    }

    public function test_bank_change_approval_promotes_pending_snapshot_and_clears_temporary_fields(): void
    {
        $transition = AuthReviewTransition::resolve([
            'id_card_status' => 2,
            'id_card_remarks' => '',
            'bank_no' => 'OLD-BANK-NO',
            'bank_no_tmp' => 'NEW-BANK-NO',
            'bank_name' => 'Old Bank',
            'bank_name_tmp' => 'New Bank',
            'bank_addr' => 'Old Branch',
            'bank_addr_tmp' => 'New Branch',
            'bank_card_img' => 'old-front.jpg',
            'bank_card_img_tmp' => 'new-front.jpg',
            'bank_card_back_img' => 'old-back.jpg',
            'bank_card_back_img_tmp' => 'new-back.jpg',
            'bank_status' => 3,
            'bank_remarks' => '',
            'is_bank_synced' => 0,
        ], AuthReviewTransition::normalizeDecisions([
            'bank_decision' => 1,
        ]));

        $this->assertSame('NEW-BANK-NO', $transition['auth_updates']['bank_no']);
        $this->assertSame('New Bank', $transition['auth_updates']['bank_name']);
        $this->assertSame('New Branch', $transition['auth_updates']['bank_addr']);
        $this->assertSame('new-front.jpg', $transition['auth_updates']['bank_card_img']);
        $this->assertSame('new-back.jpg', $transition['auth_updates']['bank_card_back_img']);
        $this->assertSame('', $transition['auth_updates']['bank_no_tmp']);
        $this->assertSame('', $transition['auth_updates']['bank_name_tmp']);
        $this->assertSame('', $transition['auth_updates']['bank_addr_tmp']);
        $this->assertSame('', $transition['auth_updates']['bank_card_img_tmp']);
        $this->assertSame('', $transition['auth_updates']['bank_card_back_img_tmp']);
        $this->assertSame('NEW-BANK-NO', $transition['bank_sync_no']);
        $this->assertSame('New Bank', $transition['bank_sync_name']);
        $this->assertSame(1, $transition['user_auth_status']);
    }

    public function test_incomplete_non_rejected_pair_derives_pending_user_auth_status(): void
    {
        $transition = AuthReviewTransition::resolve([
            'id_card_status' => 1,
            'id_card_remarks' => '',
            'bank_status' => 1,
            'bank_remarks' => '',
            'is_bank_synced' => 0,
        ], AuthReviewTransition::normalizeDecisions([
            'id_card_decision' => 1,
        ]));

        $this->assertSame(2, $transition['final_id_card_status']);
        $this->assertSame(1, $transition['final_bank_status']);
        $this->assertSame(0, $transition['user_auth_status']);
    }

    public function test_legacy_flags_emit_only_the_components_that_old_controller_would_review(): void
    {
        $payload = AuthReviewTransition::legacyDecisionPayload([
            'userIdcard_status' => '1',
            'userbank_status' => '0',
            'idcard_auth' => '0',
            'bank_auth' => '1',
            'idcard_reason' => 'unused after approval',
            'bank_reason' => 'must not be forwarded',
        ]);

        $this->assertSame([
            'id_card_decision' => 1,
            'id_card_reason' => 'unused after approval',
        ], $payload);
    }

    public function test_legacy_mixed_decisions_keep_component_specific_reasons(): void
    {
        $payload = AuthReviewTransition::legacyDecisionPayload([
            'userIdcard_status' => '1',
            'userbank_status' => '1',
            'idcard_auth' => '0',
            'bank_auth' => '2',
            'idcard_reason' => '',
            'bank_reason' => 'bank account name mismatch',
        ]);

        $this->assertSame([
            'id_card_decision' => 1,
            'id_card_reason' => '',
            'bank_decision' => 2,
            'bank_reason' => 'bank account name mismatch',
        ], $payload);
    }

    public function test_legacy_bank_change_status_is_treated_as_an_active_bank_review(): void
    {
        $payload = AuthReviewTransition::legacyDecisionPayload([
            'userIdcard_status' => '2',
            'userbank_status' => '3',
            'idcard_auth' => '2',
            'bank_auth' => '0',
            'bank_reason' => '',
        ]);

        $this->assertSame([
            'bank_decision' => 1,
            'bank_reason' => '',
        ], $payload);
    }

    public function test_status_compatibility_maps_one_decision_to_both_components(): void
    {
        $approved = AuthReviewTransition::normalizeDecisions([
            'status' => 1,
            'reason' => 'shared approval note',
        ]);
        $rejected = AuthReviewTransition::normalizeDecisions([
            'status' => 2,
            'reason' => 'shared rejection reason',
        ]);

        $this->assertSame(1, $approved['id_card_decision']);
        $this->assertSame(1, $approved['bank_decision']);
        $this->assertSame('shared approval note', $approved['id_card_reason']);
        $this->assertSame('shared approval note', $approved['bank_reason']);
        $this->assertSame(2, $rejected['id_card_decision']);
        $this->assertSame(2, $rejected['bank_decision']);
        $this->assertSame('shared rejection reason', $rejected['id_card_reason']);
        $this->assertSame('shared rejection reason', $rejected['bank_reason']);
    }

    public function test_component_decision_strings_are_trimmed_before_validation(): void
    {
        $decisions = AuthReviewTransition::normalizeDecisions([
            'id_card_decision' => ' 1 ',
        ]);

        $this->assertSame(1, $decisions['id_card_decision']);
    }

    public function test_review_queue_filter_preserves_pending_and_rejected_semantics(): void
    {
        $this->assertTrue(
            method_exists(AuthReviewTransition::class, 'reviewQueueStatuses'),
            '认证审核队列状态契约尚未实现。'
        );

        $this->assertSame([
            'id_card_statuses' => [1],
            'bank_statuses' => [1, 3],
        ], AuthReviewTransition::reviewQueueStatuses('1'));

        $this->assertSame([
            'id_card_statuses' => [4],
            'bank_statuses' => [4],
        ], AuthReviewTransition::reviewQueueStatuses('2'));

        $this->assertSame([
            'id_card_statuses' => [1, 4],
            'bank_statuses' => [1, 3, 4],
        ], AuthReviewTransition::reviewQueueStatuses(''));
    }

    public function test_review_queue_filter_rejects_unknown_status(): void
    {
        $this->assertTrue(
            method_exists(AuthReviewTransition::class, 'reviewQueueStatuses'),
            '认证审核队列状态契约尚未实现。'
        );
        $this->expectException(InvalidArgumentException::class);

        AuthReviewTransition::reviewQueueStatuses('3');
    }

    public function test_only_pending_components_are_reviewable(): void
    {
        $this->assertTrue(
            method_exists(AuthReviewTransition::class, 'assertReviewableComponents'),
            '认证组件待审状态校验尚未实现。'
        );

        AuthReviewTransition::assertReviewableComponents(
            ['id_card_status' => 1, 'bank_status' => 2],
            ['id_card_decision' => 1]
        );
        AuthReviewTransition::assertReviewableComponents(
            ['id_card_status' => 2, 'bank_status' => 1],
            ['bank_decision' => 1]
        );
        AuthReviewTransition::assertReviewableComponents(
            ['id_card_status' => 2, 'bank_status' => 3],
            ['bank_decision' => 1]
        );

        $this->addToAssertionCount(3);
    }

    /**
     * @dataProvider nonReviewableComponentProvider
     */
    public function test_non_pending_component_decisions_are_rejected(array $current, array $decisions): void
    {
        $this->assertTrue(
            method_exists(AuthReviewTransition::class, 'assertReviewableComponents'),
            '认证组件待审状态校验尚未实现。'
        );
        $this->expectException(InvalidArgumentException::class);

        AuthReviewTransition::assertReviewableComponents($current, $decisions);
    }

    public function nonReviewableComponentProvider(): array
    {
        return [
            'approved ID card' => [['id_card_status' => 2, 'bank_status' => 1], ['id_card_decision' => 1]],
            'rejected ID card' => [['id_card_status' => 4, 'bank_status' => 1], ['id_card_decision' => 1]],
            'approved bank card' => [['id_card_status' => 1, 'bank_status' => 2], ['bank_decision' => 1]],
            'rejected bank card' => [['id_card_status' => 1, 'bank_status' => 4], ['bank_decision' => 1]],
            'unsubmitted bank card' => [['id_card_status' => 1, 'bank_status' => 0], ['bank_decision' => 1]],
        ];
    }

    /**
     * @dataProvider invalidComponentStatusRepresentationProvider
     */
    public function test_non_canonical_component_status_representations_are_rejected(
        array $current,
        array $decisions
    ): void {
        $this->expectException(InvalidArgumentException::class);

        AuthReviewTransition::assertReviewableComponents($current, $decisions);
    }

    public function invalidComponentStatusRepresentationProvider(): array
    {
        return [
            'boolean ID card status' => [['id_card_status' => true, 'bank_status' => 1], ['id_card_decision' => 1]],
            'array ID card status' => [['id_card_status' => [1], 'bank_status' => 1], ['id_card_decision' => 1]],
            'zero-padded ID card status' => [['id_card_status' => '01', 'bank_status' => 1], ['id_card_decision' => 1]],
            'float bank status' => [['id_card_status' => 1, 'bank_status' => 1.9], ['bank_decision' => 1]],
            'object bank status' => [['id_card_status' => 1, 'bank_status' => (object) ['value' => 1]], ['bank_decision' => 1]],
            'whitespace-padded bank status' => [['id_card_status' => 1, 'bank_status' => ' 1 '], ['bank_decision' => 1]],
        ];
    }

    /**
     * @dataProvider invalidDecisionPayloadProvider
     */
    public function test_empty_invalid_or_ambiguous_decisions_are_rejected(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuthReviewTransition::normalizeDecisions($payload);
    }

    public function invalidDecisionPayloadProvider(): array
    {
        return [
            'empty payload' => [[]],
            'invalid status' => [['status' => 3]],
            'invalid component' => [['id_card_decision' => 0]],
            'status mixed with component' => [['status' => 1, 'bank_decision' => 2]],
            'shared rejection without reason' => [['status' => 2, 'reason' => '   ']],
            'ID card rejection without reason' => [['id_card_decision' => 2, 'id_card_reason' => '']],
            'bank rejection without reason' => [['bank_decision' => 2, 'bank_reason' => '   ']],
        ];
    }

    /**
     * @dataProvider invalidLegacyPayloadProvider
     */
    public function test_legacy_payload_without_a_complete_active_component_is_rejected(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuthReviewTransition::legacyDecisionPayload($payload);
    }

    public function invalidLegacyPayloadProvider(): array
    {
        return [
            'no pending component' => [[
                'userIdcard_status' => '0',
                'userbank_status' => '0',
            ]],
            'missing active id decision' => [[
                'userIdcard_status' => '1',
                'userbank_status' => '0',
            ]],
            'missing active bank decision' => [[
                'userIdcard_status' => '0',
                'userbank_status' => '1',
            ]],
        ];
    }
}
