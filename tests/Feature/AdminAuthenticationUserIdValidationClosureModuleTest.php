<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 后台实名认证列表 user_id 严格校验闭包测试。
 *
 * 文件功能：
 * - 验证待审核列表（/api/admin/authPendingList）与已认证列表（/api/admin/authCertifiedList）接口对非严格整数 user_id（如 {id}abc）返回校验失败，且不返回认证记录。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 321 项）。
 *
 * 适用场景：
 * - 后台实名认证管理模块列表筛选的参数严格校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/authPendingList
 *   {
 *     "user_id": "983751abc",
 *     "limit": 5
 *   }
 *
 * 方法功能：
 * - test_auth_pending_list_rejects_non_strict_user_id_filter_without_returning_auth_record：待审核列表非严格 user_id 被拒，断言响应不含认证用户姓名。
 * - test_auth_certified_list_rejects_non_strict_user_id_filter_without_returning_auth_record：已认证列表非严格 user_id 被拒，断言响应不含认证用户姓名。
 * - test_final_checklist_records_authentication_user_id_validation_boundary：校验最终清单文档包含第 321 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非严格 user_id 被接受并返回认证记录，测试断言失败。
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

class AdminAuthenticationUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 未认证（待审核）状态的夹具用户 ID。验证认证用户列表按 user_id 过滤时只接受数字且正确匹配状态。
     * @var int
     */
    private const PENDING_USER_ID = 983751;
    /**
     * 已认证状态的夹具用户 ID，与 PENDING_USER_ID 形成状态对照。
     * @var int
     */
    private const CERTIFIED_USER_ID = 983752;
    /**
     * 未认证用户的 user_name 标记。断言结果中出现的是正确用户，防止误配。
     * @var string
     */
    private const PENDING_USER_NAME = 'Authentication Pending User ID Validation User';
    /**
     * 已认证用户的 user_name 标记。断言结果中出现的是正确用户。
     * @var string
     */
    private const CERTIFIED_USER_NAME = 'Authentication Certified User ID Validation User';

    protected function tearDown(): void
    {
        DB::table('user_auths')->whereIn('user_id', [self::PENDING_USER_ID, self::CERTIFIED_USER_ID])->delete();
        DB::table('user_infos')->whereIn('user_id', [self::PENDING_USER_ID, self::CERTIFIED_USER_ID])->delete();

        parent::tearDown();
    }

    /**
     * 待审核列表非严格 user_id：断言校验失败且响应不含认证用户姓名。
     *
     * @return void
     */
    public function test_auth_pending_list_rejects_non_strict_user_id_filter_without_returning_auth_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createAuthUser(self::PENDING_USER_ID, self::PENDING_USER_NAME, 1, 0);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/authPendingList', [
                'user_id' => self::PENDING_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::PENDING_USER_NAME, $response->getContent());
    }

    /**
     * 已认证列表非严格 user_id：断言校验失败且响应不含认证用户姓名。
     *
     * @return void
     */
    public function test_auth_certified_list_rejects_non_strict_user_id_filter_without_returning_auth_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createAuthUser(self::CERTIFIED_USER_ID, self::CERTIFIED_USER_NAME, 2, 2);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/authCertifiedList', [
                'user_id' => self::CERTIFIED_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::CERTIFIED_USER_NAME, $response->getContent());
    }

    /**
     * 校验最终清单文档第 321 项记录了认证列表 user_id 校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_authentication_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 321.', $checklist);
        $this->assertStringContainsString('AuthenticationController::pendingList', $checklist);
        $this->assertStringContainsString('AuthenticationController::certifiedList', $checklist);
        $this->assertStringContainsString('/api/admin/authPendingList', $checklist);
        $this->assertStringContainsString('/api/admin/authCertifiedList', $checklist);
        $this->assertStringContainsString('user_auths.user_id', $checklist);
        $this->assertStringContainsString('AdminAuthenticationUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-authentication-user-id-super',
                'email' => 'admin-authentication-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createAuthUser(int $userId, string $userName, int $idCardStatus, int $bankStatus): void
    {
        $now = time();

        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => $userName,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => $idCardStatus === 2 && $bankStatus === 2 ? 1 : 0,
            'total_funds' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'created_at' => $now - 3600,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_no' => '622202' . $userId,
            'bank_name' => 'Authentication Validation Bank',
            'bank_addr' => 'Authentication Validation Branch',
            'bank_status' => $bankStatus,
            'bank_remarks' => '',
            'id_card_no' => '110101199003' . substr((string) $userId, -6),
            'id_card_status' => $idCardStatus,
            'id_card_front' => 'auth-front-' . $userId . '.jpg',
            'id_card_back' => 'auth-back-' . $userId . '.jpg',
            'id_card_remarks' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
