<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证未入金用户列表（neverDepositUserList）对 user_id 筛选值的
 *           严格校验，非法值不得返回用户，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/neverDepositUserList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/neverDepositUserList：{user_id, per_page}
 *
 * 返回值：
 * - user_id 带非数字后缀时返回 code=VALIDATION_FAILED，响应不含用户信息。
 *
 * 异常或失败场景：
 * - 非严格数字 user_id（如 '983601abc'）时校验失败，不返回任何用户。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminNeverDepositUserListUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 未入金用户列表校验用例的夹具业务用户 ID。验证按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983601;

    protected function tearDown(): void
    {
        DB::table('deposit_records')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_logins')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 未入金用户列表应拒绝非严格 user_id 筛选值且不返回用户。
    public function test_never_deposit_user_list_rejects_non_strict_user_id_filter_without_returning_user(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createNeverDepositUser();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/neverDepositUserList', [
                'user_id' => self::TEST_USER_ID . 'abc',
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('Never Deposit User ID Validation User', $response->getContent());
    }

    // 核对最终检查清单文档记录了未入金 user_id 校验边界。
    public function test_final_checklist_records_never_deposit_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 313.', $checklist);
        $this->assertStringContainsString('FundFlowController::neverDepositUserList', $checklist);
        $this->assertStringContainsString('/api/admin/neverDepositUserList', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('AdminNeverDepositUserListUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-never-deposit-user-id-super',
                'email' => 'admin-never-deposit-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createNeverDepositUser(): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => self::TEST_USER_ID],
            [
                'login_id' => 0,
                'user_name' => 'Never Deposit User ID Validation User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) self::TEST_USER_ID,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now - 86400,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => self::TEST_USER_ID],
            [
                'email' => 'never-deposit-user-id-validation@example.test',
                'password' => Hash::make('password'),
                'account_type' => 2,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
