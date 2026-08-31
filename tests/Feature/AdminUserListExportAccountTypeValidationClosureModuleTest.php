<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户列表与导出接口 account_type 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 account_type 传入非严格数字时用户列表与导出接口均返回校验失败。
 * - 验证校验失败时不返回测试用户、导出响应保持 JSON 而非流式 CSV。
 *
 * 适用场景：
 * - 后台用户管理页面的账户类型筛选与导出，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/userList，body：{"account_type": "2abc", "limit": 5}。
 * - POST /api/admin/exportUsers，body：{"account_type": "2abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED，导出不产生 StreamedResponse。
 *
 * 异常或失败场景：
 * - account_type 非严格整数时接口拒绝查询并返回校验失败响应。
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
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AdminUserListExportAccountTypeValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 用户列表导出账户类型校验用例的夹具业务用户 ID。验证导出按 account_type 过滤拒绝非法值。
     * @var int
     */
    private const TEST_USER_ID = 983811;
    /**
     * 夹具用户的 user_name 标记。断言合法过滤命中该用户、非法过滤结果为空。
     * @var string
     */
    private const TEST_USER_NAME = 'Admin User Account Type Validation User';

    protected function tearDown(): void
    {
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_logins')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 验证用户列表对非严格 account_type 筛选返回校验失败且不返回测试用户。
    public function test_user_list_rejects_non_strict_account_type_filter_without_returning_user_row(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->createManagedUser();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/userList', [
                'account_type' => '2abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
    }

    // 验证用户导出对非严格 account_type 筛选返回校验失败且不流式输出用户数据。
    public function test_user_export_rejects_non_strict_account_type_filter_without_streaming_user_row(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->createManagedUser();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportUsers', [
                'account_type' => '2abc',
            ]);

        $this->assertNotInstanceOf(StreamedResponse::class, $response->baseResponse);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
    }

    // 校验最终检查清单文档记录了用户列表导出 account_type 校验边界。
    public function test_final_checklist_records_admin_user_list_export_account_type_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 332.', $checklist);
        $this->assertStringContainsString('AdminUserController::userList', $checklist);
        $this->assertStringContainsString('AdminUserController::exportUsers', $checklist);
        $this->assertStringContainsString('AdminUserController::filteredUserQuery', $checklist);
        $this->assertStringContainsString('/api/admin/userList', $checklist);
        $this->assertStringContainsString('/api/admin/exportUsers', $checklist);
        $this->assertStringContainsString('user_infos.account_type', $checklist);
        $this->assertStringContainsString('AdminUserListExportAccountTypeValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-account-type-super',
                'email' => 'admin-user-account-type-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedUser(): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_logins')->where('user_id', self::TEST_USER_ID)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => self::TEST_USER_ID,
            'email' => 'admin-user-account-type-' . self::TEST_USER_ID . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => self::TEST_USER_ID,
            'login_id' => $loginId,
            'user_name' => self::TEST_USER_NAME,
            'phone' => '188331983811',
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) self::TEST_USER_ID,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
