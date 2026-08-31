<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 22:27
 */

/**
 * 文件功能：验证 legacy 销户审核路由（cancel_apply_pass、update_cancel）与新版
 *           后台的兼容性：按业务 user_id 解析申请、拒绝语义与缺失参数保护。
 *
 * 适用场景：/index/admin/cancel/cancel_apply_pass、/index/admin/cancel/update_cancel
 *           路由的兼容性回归测试。
 *
 * 入参例子：
 * - POST /index/admin/cancel/cancel_apply_pass：{cancel_userid}
 * - POST /index/admin/cancel/update_cancel：{cancel_userid, accept_rejection, cancel_remark}
 *
 * 返回值：
 * - 通过：code=SUCCESS，cancel_applies.status=1；
 * - 拒绝（accept_rejection=2）：code=SUCCESS，status=-1 并映射取消备注为拒绝原因；
 * - 缺失 cancel_userid：code=VALIDATION_FAILED，数据不变。
 *
 * 异常或失败场景：
 * - 缺少业务用户 ID 时校验失败，不触碰任何申请记录。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLegacyCancelApplyCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, int> */
    private array $testUserIds = [];

    /** @var array<int, int> */
    private array $testApplyIds = [];

    protected function tearDown(): void
    {
        if ($this->testApplyIds !== []) {
            DB::table('operation_logs')
                ->whereIn('order_no', array_map(static fn (int $id): string => 'cancel_apply:' . $id, $this->testApplyIds))
                ->delete();
            DB::table('cancel_applies')->whereIn('id', $this->testApplyIds)->delete();
        }

        parent::tearDown();
    }

    // legacy 审核通过应按业务 user_id 解析申请而非申请主键。
    public function test_legacy_cancel_pass_resolves_by_business_user_id_not_apply_primary_key(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = $this->newUserId();
        $applyId = $this->insertApply($userId);
        $this->assertNotSame($userId, $applyId);
        $this->fakeSuccessfulMt4($userId);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/cancel_apply_pass', [
                'cancel_userid' => $userId,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame(1, (int) DB::table('cancel_applies')->where('id', $applyId)->value('status'));
    }

    // canonical 旧页面拒绝语义（accept_rejection=2）应映射为拒绝状态与备注。
    public function test_legacy_update_cancel_accept_rejection_two_rejects_and_maps_remark(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = $this->newUserId();
        $applyId = $this->insertApply($userId);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/update_cancel', [
                'cancel_userid' => $userId,
                'accept_rejection' => 2,
                'cancel_remark' => 'legacy rejection reason',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('cancel_applies', [
            'id' => $applyId,
            'status' => -1,
            'reject_reason' => 'legacy rejection reason',
        ]);
    }

    // legacy 审核缺少业务 user_id 时应校验失败且不触碰任何记录。
    public function test_legacy_cancel_missing_business_user_id_fails_without_touching_rows(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $applyId = $this->insertApply($this->newUserId());

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/cancel_apply_pass', [])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(0, (int) DB::table('cancel_applies')->where('id', $applyId)->value('status'));
    }

    private function insertApply(int $userId): int
    {
        $this->testUserIds[] = $userId;
        $now = time();

        $id = (int) DB::table('cancel_applies')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'legacy-cancel-' . $userId,
            'status' => 0,
            'cancel_remark' => '',
            'reject_reason' => '',
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $this->testApplyIds[] = $id;

        return $id;
    }

    private function newUserId(): int
    {
        return random_int(980000, 989999);
    }

    private function fakeSuccessfulMt4(int $expectedUserId): void
    {
        $this->app->instance(Mt4ManagerService::class, new class($expectedUserId) extends Mt4ManagerService {
            private int $expectedUserId;

            public function __construct(int $expectedUserId)
            {
                $this->expectedUserId = $expectedUserId;
                parent::__construct('127.0.0.1', 0, 'test', '1', 1);
            }

            public function lockUser($userId)
            {
                return (int) $userId === $this->expectedUserId
                    ? ['status' => 'ok']
                    : ['status' => 'error', 'error_code' => 'unexpected_user'];
            }
        });
    }
}
