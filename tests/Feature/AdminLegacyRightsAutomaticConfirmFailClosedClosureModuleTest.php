<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:33
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧后台权益自动确认入口失败关闭闭环测试。
 *
 * 文件功能：
 * - 验证旧 `confirm_options` 不会在项目2伪造 MT4 自动出入金成功。
 * - 验证完整旧字段请求会返回旧页面可识别的失败 JSON，而不是执行本地权益结算写入。
 * - 验证缺少关键旧字段时返回参数失败，便于前端明确展示错误字段。
 */
class AdminLegacyRightsAutomaticConfirmFailClosedClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 自动权益确认依赖未接入 MT4 写接口时，必须失败关闭且不写权益结算记录。
     *
     * @return void
     */
    public function test_legacy_confirm_options_returns_legacy_failure_without_writing_settlement(): void
    {
        $admin = $this->ensureSuperAdmin();
        $settlementId = $this->createPendingSettlement(412355426, 'auto confirm untouched');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/confirm_options', [
                'uid' => 412355426,
                'real_amt' => '100.00',
                'other_amt' => '',
                'amount' => '100.00',
                'sumdata' => '2026-07-29',
                'status' => 1,
                'type' => 'deposit',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'erroptions')
            ->assertJsonPath('col', 'NOCOL')
            ->assertJsonPath('data.reason', 'MT4 automatic rights confirmation is unsupported.');

        $this->assertDatabaseHas('rights_settlements', [
            'id' => $settlementId,
            'user_id' => 412355426,
            'status' => 0,
            'remark' => 'auto confirm untouched',
        ]);
    }

    /**
     * 缺少 type 时不应进入自动确认失败分支，应先返回参数校验失败。
     *
     * @return void
     */
    public function test_legacy_confirm_options_rejects_missing_type_without_writing_settlement(): void
    {
        $admin = $this->ensureSuperAdmin();
        $settlementId = $this->createPendingSettlement(412355427, 'auto confirm missing type');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/confirm_options', [
                'uid' => 412355427,
                'real_amt' => '100.00',
                'amount' => '100.00',
                'sumdata' => '2026-07-29',
                'status' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'errparams')
            ->assertJsonPath('col', 'type');

        $this->assertDatabaseHas('rights_settlements', [
            'id' => $settlementId,
            'user_id' => 412355427,
            'status' => 0,
            'remark' => 'auto confirm missing type',
        ]);
    }

    /**
     * 创建测试后台管理员。
     *
     * @return Admin 可绑定 admin guard 的超级管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-legacy-auto-rights-super',
                'email' => 'admin-legacy-auto-rights-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建待处理权益结算记录。
     *
     * @param int $userId 结算记录归属用户 ID。
     * @param string $remark 初始备注，用于断言旧自动确认入口没有改写记录。
     * @return int 新增 `rights_settlements.id`。
     */
    private function createPendingSettlement(int $userId, string $remark): int
    {
        $now = time();

        return (int) DB::table('rights_settlements')->insertGetId([
            'user_id' => $userId,
            'amount' => 100.0000,
            'status' => 0,
            'remark' => $remark,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
