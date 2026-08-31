<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 管理员凭证（Voucher）数据范围闭环测试。
 *
 * 文件功能：
 * - 通过 Mockery 替换 AdminDataScopeService，模拟受限管理员的数据范围。
 * - 验证受限管理员无法查看、审批或驳回数据范围之外的凭证。
 * - 验证越权操作不会改变凭证的审核状态。
 *
 * 适用场景：
 * - 管理员数据权限（DataScope）在凭证列表、审批、驳回接口上的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/voucherList（受限管理员）
 * - POST /api/admin/voucherApprove/{id}
 * - POST /api/admin/voucherReject/{id}，body 含 reason。
 *
 * 返回值：
 * - voucherList 返回 data.total = 0。
 * - voucherApprove / voucherReject 返回 code 为 PERMISSION_DENIED。
 * - 凭证 review_status 保持 0 不变。
 *
 * 异常或失败场景：
 * - 若越权操作被放行或凭证状态被修改，断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AdminVoucherDataScopeClosureTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证受限管理员无法查看、审批或驳回数据范围外的凭证，且状态保持不变。
     */
    public function test_restricted_admin_cannot_list_approve_or_reject_out_of_scope_voucher(): void
    {
        $admin = new Admin();
        $admin->id = 880202;
        $admin->username = 'restricted-voucher-admin';
        $admin->status = 1;

        $firstId = $this->insertVoucher(88020201);
        $secondId = $this->insertVoucher(88020202);

        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('apply')
            ->once()
            ->andReturnUsing(static function ($query) {
                return $query->whereRaw('1 = 0');
            });
        $scope->shouldReceive('canAccessUser')->twice()->andReturnFalse();
        $this->app->instance(AdminDataScopeService::class, $scope);

        $client = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');

        $client->postJson('/api/admin/voucherList')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
        $client->postJson('/api/admin/voucherApprove/' . $firstId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $client->postJson('/api/admin/voucherReject/' . $secondId, ['reason' => 'out of scope'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(0, (int) DB::table('voucher_infos')->where('id', $firstId)->value('review_status'));
        $this->assertSame(0, (int) DB::table('voucher_infos')->where('id', $secondId)->value('review_status'));
    }

    private function insertVoucher(int $userId): int
    {
        $now = time();

        return (int) DB::table('voucher_infos')->insertGetId([
            'user_id' => $userId,
            'images' => '[]',
            'remarks' => 'scope test',
            'review_status' => 0,
            'review_message' => '',
            'created_by' => (string) $userId,
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
