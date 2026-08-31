<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:54
 */

/**
 * 后台在线用户列表 user_id 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字（如带字母后缀）时接口返回校验失败。
 * - 验证校验失败时不返回任何在线用户记录。
 *
 * 适用场景：
 * - 后台在线用户管理列表的 user_id 精确筛选，防止脏数据注入查询。
 *
 * 入参例子：
 * - POST /api/admin/onlineUserList，body：{"user_id": "983701abc", "limit": 5}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - user_id 非严格整数时接口拒绝查询并返回校验失败响应。
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

class AdminOnlineUserListUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 在线用户列表校验用例的夹具业务用户 ID。验证按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983701;

    protected function tearDown(): void
    {
        DB::table('user_onlines')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 验证 user_id 带非数字后缀时列表接口返回校验失败且不返回测试在线用户。
    public function test_online_user_list_rejects_non_strict_user_id_filter_without_returning_online_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createOnlineUser();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/onlineUserList', [
                'user_id' => self::TEST_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('Online User ID Validation User', $response->getContent());
    }

    // 校验最终检查清单文档记录了在线用户列表 user_id 校验边界。
    public function test_final_checklist_records_online_user_list_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 315.', $checklist);
        $this->assertStringContainsString('OnlineUserController::onlineUserList', $checklist);
        $this->assertStringContainsString('/api/admin/onlineUserList', $checklist);
        $this->assertStringContainsString('user_onlines.user_id', $checklist);
        $this->assertStringContainsString('AdminOnlineUserListUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-online-user-id-super',
                'email' => 'admin-online-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createOnlineUser(): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => self::TEST_USER_ID],
            [
                'login_id' => 0,
                'user_name' => 'Online User ID Validation User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) self::TEST_USER_ID,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now - 3600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('user_onlines')->insert([
            'user_id' => self::TEST_USER_ID,
            'last_activity' => $now,
            'ip_address' => '203.0.113.171',
            'user_agent' => 'Online User ID Validation Browser',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
