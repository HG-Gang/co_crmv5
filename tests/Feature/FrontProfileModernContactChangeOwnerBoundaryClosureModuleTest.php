<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 22:57
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 前台现代联系方式修改安全边界测试。
 *
 * 文件功能：
 * - 验证现代手机号、邮箱接口只能修改当前登录用户，不能被请求体 user_id 覆盖。
 * - 验证手机号修改必须校验当前密码。
 * - 验证邮箱修改必须校验当前密码，以及绑定新邮箱的一次性验证码。
 *
 * 返回结果：
 * - UPDATED/SUCCESS 表示身份、密码和验证码校验通过后写入成功。
 * - OLD_PASSWORD_WRONG 表示当前密码缺失或错误。
 * - VALIDATION_FAILED + codeErr 表示邮箱验证码缺失、错误或已失效。
 */
class FrontProfileModernContactChangeOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 清理测试写入的资料验证码，避免固定测试用户之间发生缓存污染。
     */
    protected function tearDown(): void
    {
        foreach ([412300200, 412300400] as $userId) {
            Cache::forget('front_profile_updverify_code:' . $userId);
        }

        parent::tearDown();
    }

    /**
     * 验证现代手机号接口完成当前密码校验后，只更新当前登录用户。
     */
    public function test_modern_phone_change_ignores_spoofed_user_id_and_updates_current_profile_only(): void
    {
        $viewerId = 412300100;
        $otherId = 412300101;
        $viewerEmail = 'front-modern-contact-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-modern-contact-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'modern-contact-boundary-viewer', $viewerEmail, '13923000100');
        $this->insertUserInfo($otherId, 'modern-contact-boundary-other', $otherEmail, '13923000101');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/phone', [
                'verify_phone' => '13923000100',
                'verify_email' => $viewerEmail,
                'new_phone' => '13923000999',
                'password' => 'password',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $viewerId,
            'phone' => '13923000999',
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'phone' => '13923000101',
        ]);
        $this->assertDatabaseMissing('user_infos', [
            'user_id' => $otherId,
            'phone' => '13923000999',
        ]);
    }

    /**
     * 验证现代邮箱接口完成当前密码和目标邮箱验证码校验后，只更新当前登录用户。
     */
    public function test_modern_email_change_ignores_spoofed_user_id_and_updates_current_login_only(): void
    {
        $viewerId = 412300200;
        $otherId = 412300201;
        $viewerEmail = 'front-modern-contact-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-modern-contact-boundary-' . $otherId . '@example.test';
        $newEmail = 'front-modern-contact-boundary-new-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail, $newEmail]);
        $this->insertUserInfo($viewerId, 'modern-email-boundary-viewer', $viewerEmail, '13923000200');
        $this->insertUserInfo($otherId, 'modern-email-boundary-other', $otherEmail, '13923000201');
        $this->putEmailVerificationCode($viewerId, $newEmail, '230200');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/email', [
                'verify_phone' => '13923000200',
                'current_email' => $viewerEmail,
                'new_email' => $newEmail,
                'password' => 'password',
                'verification_code' => '230200',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('user_logins', [
            'user_id' => $viewerId,
            'email' => $newEmail,
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $otherId,
            'email' => $otherEmail,
        ]);
        $this->assertDatabaseMissing('user_logins', [
            'user_id' => $otherId,
            'email' => $newEmail,
        ]);
        $this->assertNull(Cache::get('front_profile_updverify_code:' . $viewerId));
    }

    /**
     * 验证现代手机号接口缺少当前密码时失败关闭，原手机号保持不变。
     */
    public function test_modern_phone_change_rejects_missing_password(): void
    {
        $viewerId = 412300300;
        $viewerEmail = 'front-modern-contact-boundary-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->insertUserInfo($viewerId, 'modern-phone-password-viewer', $viewerEmail, '13923000300');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/phone', [
                'verify_phone' => '13923000300',
                'verify_email' => $viewerEmail,
                'new_phone' => '13923000997',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OLD_PASSWORD_WRONG)
            ->assertJsonPath('data.error', 'pswErr')
            ->assertJsonPath('data.field', 'password');
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $viewerId,
            'phone' => '13923000300',
        ]);
    }

    /**
     * 验证现代邮箱接口即使密码正确，缺少目标邮箱验证码时也不能更新邮箱。
     */
    public function test_modern_email_change_rejects_missing_verification_code(): void
    {
        $viewerId = 412300400;
        $viewerEmail = 'front-modern-contact-boundary-' . $viewerId . '@example.test';
        $newEmail = 'front-modern-contact-boundary-new-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail, $newEmail]);
        $this->insertUserInfo($viewerId, 'modern-email-code-viewer', $viewerEmail, '13923000400');
        $this->putEmailVerificationCode($viewerId, $newEmail, '230400');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/email', [
                'verify_phone' => '13923000400',
                'current_email' => $viewerEmail,
                'new_email' => $newEmail,
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('data.error', 'codeErr')
            ->assertJsonPath('data.field', 'verification_code');
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $viewerId,
            'email' => $viewerEmail,
        ]);
    }

    /**
     * 验证最终清单记录现代联系方式修改的路由、控制器方法和归属边界测试。
     */
    public function test_final_checklist_records_modern_contact_change_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 230.', $checklist);
        $this->assertStringContainsString('ProfileController::changePhone', $checklist);
        $this->assertStringContainsString('ProfileController::changeEmail', $checklist);
        $this->assertStringContainsString('/api/front/profile/phone', $checklist);
        $this->assertStringContainsString('/api/front/profile/email', $checklist);
        $this->assertStringContainsString('FrontProfileModernContactChangeOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 创建本地密码为 password 的登录账号与业务资料。
     *
     * @param int $userId 业务用户 ID。
     * @param string $userName 用户姓名。
     * @param string $email 登录邮箱。
     * @param string $phone 联系手机号。
     * @return void
     */
    private function insertUserInfo(int $userId, string $userName, string $email, string $phone): void
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
            'phone' => $phone,
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
     * 写入绑定目标新邮箱的一次性验证码。
     *
     * @param int $userId 当前登录用户 ID。
     * @param string $email 验证码绑定的新邮箱。
     * @param string $code 六位验证码。
     * @return void
     */
    private function putEmailVerificationCode(int $userId, string $email, string $code): void
    {
        Cache::put('front_profile_updverify_code:' . $userId, [
            'code' => $code,
            'email' => $email,
            'phone' => '',
            'type' => 'email',
        ], now()->addMinutes(10));
    }

    /**
     * 清理固定测试账号，避免本地真实数据库中的历史数据造成唯一键冲突。
     *
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     * @return void
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
