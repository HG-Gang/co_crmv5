<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端注销验证码发送-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证现代验证码发送接口 /api/front/profile/verification-cancellation/verification-codes 将验证码写入当前用户的缓存键，而非伪装 userId。
 * - 验证旧接口 /user/center/cancelVerifyPassSendCode 使用他人邮箱/手机号时拒绝发送验证码。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 注销流程验证码发送接口的归属权边界回归测试，防止验证码被发送给他人。
 *
 * 入参例子：
 * - POST /api/front/profile/verification-cancellation/verification-codes
 *   请求体：{ "useremail": "{当前用户邮箱}", "userphoneNo": "13922200100",
 *            "user_id": {他人ID}, "userId": {他人ID} }
 * - POST /user/center/cancelVerifyPassSendCode
 *   请求体：{ "useremail": "{他人邮箱}", "userphoneNo": "13922200201", ... }
 *
 * 返回值：
 * - 现代接口返回 HTTP 200 且 status=true，缓存键 front_profile_cancel_code:{当前用户ID} 存在；
 *   旧接口返回 status=false，双方缓存均为空。
 *
 * 异常或失败场景：
 * - 若验证码写入他人缓存键、或旧接口对他人凭据仍然发送成功，测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FrontCancelVerificationCodeOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代验证码发送接口为当前用户写入缓存而非伪装 userId。
     *
     * 携带伪装 user_id / userId 请求发送验证码，
     * 断言当前用户缓存键存在、他人缓存键为空。
     */
    public function test_modern_cancel_code_send_writes_cache_for_current_user_not_spoofed_user_id(): void
    {
        $viewerId = 412220100;
        $otherId = 412220101;
        $viewerEmail = 'front-cancel-code-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-cancel-code-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'cancel-code-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'cancel-code-boundary-other', $otherEmail);
        Cache::forget('front_profile_cancel_code:' . $viewerId);
        Cache::forget('front_profile_cancel_code:' . $otherId);
        Mail::fake();

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/verification-cancellation/verification-codes', [
                'useremail' => $viewerEmail,
                'userphoneNo' => '13922200100',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $cached = Cache::get('front_profile_cancel_code:' . $viewerId);
        $this->assertIsArray($cached);
        $this->assertSame($viewerEmail, $cached['email'] ?? '');
        $this->assertArrayHasKey('code', $cached);
        $this->assertNull(Cache::get('front_profile_cancel_code:' . $otherId));
    }

    /**
     * 验证旧验证码发送接口拒绝使用他人邮箱的伪装请求。
     *
     * 携带他人邮箱、手机号及伪装 userId 请求发送验证码，
     * 断言返回 status=false 且双方缓存键均为空。
     */
    public function test_legacy_cancel_code_send_rejects_spoofed_other_user_email(): void
    {
        $viewerId = 412220200;
        $otherId = 412220201;
        $viewerEmail = 'front-cancel-code-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-cancel-code-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'cancel-code-legacy-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'cancel-code-legacy-boundary-other', $otherEmail);
        Cache::forget('front_profile_cancel_code:' . $viewerId);
        Cache::forget('front_profile_cancel_code:' . $otherId);
        Mail::fake();

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/cancelVerifyPassSendCode', [
                'useremail' => $otherEmail,
                'userphoneNo' => '13922200201',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', false);

        $this->assertNull(Cache::get('front_profile_cancel_code:' . $viewerId));
        $this->assertNull(Cache::get('front_profile_cancel_code:' . $otherId));
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 222 项、cancelVerifyPassSendCode、front_profile_cancel_code 及本测试类名。
     */
    public function test_final_checklist_records_cancel_verification_code_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 222.', $checklist);
        $this->assertStringContainsString('ProfileController::cancelVerifyPassSendCode', $checklist);
        $this->assertStringContainsString('/api/front/profile/verification-cancellation/verification-codes', $checklist);
        $this->assertStringContainsString('user/center/cancelVerifyPassSendCode', $checklist);
        $this->assertStringContainsString('front_profile_cancel_code', $checklist);
        $this->assertStringContainsString('FrontCancelVerificationCodeOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带指定邮箱的客户测试数据（account_type 固定为 2）。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param string $email 登录邮箱。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, string $email): void
    {
        $now = time();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1392220' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户 ID 与邮箱相关的用户信息及登录测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param array<int, string> $emails 待清理的邮箱列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
