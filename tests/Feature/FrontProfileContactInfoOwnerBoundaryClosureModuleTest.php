<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 22:34
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
 * 前台联系方式修改归属边界测试。
 *
 * 文件功能：
 * - 验证现代 API 与旧 URI 都只能修改当前登录用户的邮箱或手机号。
 * - 合法邮箱修改必须携带绑定新邮箱的验证码和当前密码。
 * - 合法手机号修改必须携带当前密码，并继续兼容旧前台 Session 登录态。
 */
class FrontProfileContactInfoOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证伪造 user_id 不会把邮箱修改写入其他账号。
     *
     * 返回结果：code=UPDATED 表示当前账号完成验证码、密码和归属校验后更新成功。
     */
    public function test_modern_contact_email_update_ignores_spoofed_user_id_and_updates_current_login_only(): void
    {
        $viewerId = 412240100;
        $otherId = 412240101;
        $viewerEmail = 'front-profile-contact-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-profile-contact-boundary-' . $otherId . '@example.test';
        $newEmail = 'front-profile-contact-boundary-new-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail, $newEmail]);
        $this->insertUserInfo($viewerId, 'contact-boundary-viewer', $viewerEmail, '13922400100');
        $this->insertUserInfo($otherId, 'contact-boundary-other', $otherEmail, '13922400101');
        Cache::put('front_profile_updverify_code:' . $viewerId, [
            'code' => '224100',
            'email' => $newEmail,
            'phone' => '',
            'type' => 'email',
        ], now()->addMinutes(10));

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/contact-info', [
                'type' => 'email',
                'oldemail' => $viewerEmail,
                'useremail' => $newEmail,
                'updVerifyCode' => '224100',
                'password' => 'password',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED)
            ->assertJsonMissing(['msg' => 'SUC']);

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
    }

    /**
     * 验证旧手机号修改只写当前登录用户，并要求当前密码作为敏感操作确认。
     */
    public function test_legacy_contact_phone_update_ignores_spoofed_user_id_and_updates_current_profile_only(): void
    {
        $viewerId = 412240200;
        $otherId = 412240201;
        $viewerEmail = 'front-profile-contact-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-profile-contact-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'contact-legacy-boundary-viewer', $viewerEmail, '86-13922400200');
        $this->insertUserInfo($otherId, 'contact-legacy-boundary-other', $otherEmail, '86-13922400201');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updatePhoneEmailInfo', [
                'type' => 'phone',
                'oldphonefill' => '13922400200',
                'userphoneNo' => '13922400999',
                'password' => 'password',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $viewerId,
            'phone' => '86-13922400999',
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'phone' => '86-13922400201',
        ]);
        $this->assertDatabaseMissing('user_infos', [
            'user_id' => $otherId,
            'phone' => '86-13922400999',
        ]);
    }

    /**
     * 验证没有 user guard 时仍从旧 suser Session 确定操作人，且密码校验不能省略。
     */
    public function test_legacy_contact_phone_update_uses_session_owner_without_user_guard(): void
    {
        $viewerId = 412240210;
        $otherId = 412240211;
        $viewerEmail = 'front-profile-contact-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-profile-contact-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'contact-session-boundary-viewer', $viewerEmail, '86-13922400210');
        $this->insertUserInfo($otherId, 'contact-session-boundary-other', $otherEmail, '86-13922400211');

        $response = $this->withSession(['suser' => ['user_id' => $viewerId]])
            ->postJson('/user/center/updatePhoneEmailInfo', [
                'type' => 'phone',
                'oldphonefill' => '13922400210',
                'userphoneNo' => '13922400998',
                'password' => 'password',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $viewerId,
            'phone' => '86-13922400998',
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'phone' => '86-13922400211',
        ]);
        $this->assertDatabaseMissing('user_infos', [
            'user_id' => $otherId,
            'phone' => '86-13922400998',
        ]);
    }

    public function test_modern_contact_invalid_type_returns_standard_validation_code_without_writing(): void
    {
        $viewerId = 412240300;
        $viewerEmail = 'front-profile-contact-boundary-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->insertUserInfo($viewerId, 'contact-invalid-type-viewer', $viewerEmail, '86-13922400300');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/contact-info', [
                'type' => 'unsupported',
                'new_email' => 'must-not-be-written@example.test',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('data.error', 'typeErr')
            ->assertJsonPath('data.field', 'type');
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $viewerId,
            'email' => $viewerEmail,
        ]);
    }

    public function test_final_checklist_records_profile_contact_info_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 224.', $checklist);
        $this->assertStringContainsString('ProfileController::updatePhoneEmailInfo', $checklist);
        $this->assertStringContainsString('/api/front/profile/contact-info', $checklist);
        $this->assertStringContainsString('user/center/updatePhoneEmailInfo', $checklist);
        $this->assertStringContainsString('FrontProfileContactInfoOwnerBoundaryClosureModuleTest', $checklist);
    }

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
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
