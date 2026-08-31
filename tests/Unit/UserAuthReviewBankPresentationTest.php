<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 14:50
 */

/**
 * UserAuthReviewBankPresentationTest
 *
 * 文件功能：
 * - 验证用户认证审核银行卡展示口径：首次审核用正式银行卡字段、换卡审核用非空临时字段并逐字段回退、非换卡状态忽略过期临时字段。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\UserAuth;
use PHPUnit\Framework\TestCase;

class UserAuthReviewBankPresentationTest extends TestCase
{
    public function test_initial_bank_review_uses_formal_bank_fields(): void
    {
        $auth = new UserAuth([
            'bank_status' => 1,
            'bank_no' => 'FORMAL-NO',
            'bank_no_tmp' => '',
            'bank_name' => 'Formal Bank',
            'bank_name_tmp' => '',
            'bank_addr' => 'Formal Branch',
            'bank_addr_tmp' => '',
        ]);

        $this->assertSame('FORMAL-NO', $auth->review_bank_no);
        $this->assertSame('Formal Bank', $auth->review_bank_name);
        $this->assertSame('Formal Branch', $auth->review_bank_addr);
        $this->assertSame('FORMAL-NO', $auth->toArray()['review_bank_no']);
    }

    public function test_changed_bank_review_uses_non_empty_temporary_bank_fields(): void
    {
        $auth = new UserAuth([
            'bank_status' => 3,
            'bank_no' => 'OLD-NO',
            'bank_no_tmp' => 'NEW-NO',
            'bank_name' => 'Old Bank',
            'bank_name_tmp' => 'New Bank',
            'bank_addr' => 'Old Branch',
            'bank_addr_tmp' => 'New Branch',
        ]);

        $this->assertSame('NEW-NO', $auth->review_bank_no);
        $this->assertSame('New Bank', $auth->review_bank_name);
        $this->assertSame('New Branch', $auth->review_bank_addr);
    }

    public function test_changed_bank_review_falls_back_per_field_when_temporary_value_is_blank(): void
    {
        $auth = new UserAuth([
            'bank_status' => 3,
            'bank_no' => 'OLD-NO',
            'bank_no_tmp' => '   ',
            'bank_name' => 'Old Bank',
            'bank_name_tmp' => 'New Bank',
            'bank_addr' => 'Old Branch',
            'bank_addr_tmp' => '',
        ]);

        $this->assertSame('OLD-NO', $auth->review_bank_no);
        $this->assertSame('New Bank', $auth->review_bank_name);
        $this->assertSame('Old Branch', $auth->review_bank_addr);
    }

    public function test_non_change_status_ignores_stale_temporary_bank_fields(): void
    {
        $auth = new UserAuth([
            'bank_status' => 2,
            'bank_no' => 'APPROVED-NO',
            'bank_no_tmp' => 'STALE-NO',
            'bank_name' => 'Approved Bank',
            'bank_name_tmp' => 'Stale Bank',
            'bank_addr' => 'Approved Branch',
            'bank_addr_tmp' => 'Stale Branch',
        ]);

        $this->assertSame('APPROVED-NO', $auth->review_bank_no);
        $this->assertSame('Approved Bank', $auth->review_bank_name);
        $this->assertSame('Approved Branch', $auth->review_bank_addr);
    }
}
