<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证 legacy 凭证审核保存路由（voucherReviewSave）的审核状态语义
 *           与新版后台一致：reviewstatus=2 拒绝并持久化审核意见，1 通过。
 *
 * 适用场景：/index/admin/auth/voucherReviewSave 路由的兼容性回归测试。
 *
 * 入参例子：
 * - POST /index/admin/auth/voucherReviewSave：{recId, userId, reviewstatus, reviewmsg}
 *
 * 返回值：
 * - reviewstatus=2：code=SUCCESS，voucher_infos.review_status=2 且记录 review_message；
 * - reviewstatus=1：code=SUCCESS，review_status=1 且 review_message 清空。
 *
 * 异常或失败场景：
 * - 审核状态或审核意见未按语义落库时断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLegacyVoucherReviewStatusCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, int> */
    private array $testUserIds = [];

    /** @var array<int, int> */
    private array $testVoucherIds = [];

    protected function tearDown(): void
    {
        if ($this->testVoucherIds !== []) {
            DB::table('voucher_infos')->whereIn('id', $this->testVoucherIds)->delete();
        }

        parent::tearDown();
    }

    // 审核状态 2 应拒绝凭证并持久化审核意见。
    public function test_legacy_voucher_review_status_two_rejects_and_persists_review_message(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = $this->newUserId();
        $voucherId = $this->insertVoucher($userId);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/voucherReviewSave', [
                'recId' => $voucherId,
                'userId' => $userId,
                'reviewstatus' => 2,
                'reviewmsg' => 'legacy reject reason',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('voucher_infos', [
            'id' => $voucherId,
            'review_status' => 2,
            'review_message' => 'legacy reject reason',
        ]);
    }

    // 审核状态 1 应通过凭证并清空审核意见。
    public function test_legacy_voucher_review_status_one_approves(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = $this->newUserId();
        $voucherId = $this->insertVoucher($userId);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/voucherReviewSave', [
                'recId' => $voucherId,
                'userId' => $userId,
                'reviewstatus' => 1,
                'reviewmsg' => 'legacy approval note',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('voucher_infos', [
            'id' => $voucherId,
            'review_status' => 1,
            'review_message' => '',
        ]);
    }

    private function insertVoucher(int $userId): int
    {
        $now = time();

        $this->testUserIds[] = $userId;
        $id = (int) DB::table('voucher_infos')->insertGetId([
            'user_id' => $userId,
            'images' => '[]',
            'remarks' => 'legacy review test',
            'review_status' => 0,
            'review_message' => '',
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $this->testVoucherIds[] = $id;

        return $id;
    }

    private function newUserId(): int
    {
        return random_int(990000, 999999);
    }
}
