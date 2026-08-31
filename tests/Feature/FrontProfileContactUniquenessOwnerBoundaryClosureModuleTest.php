<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台联系方式唯一性属主边界闭环测试。
 *
 * 文件功能：
 * - 验证现代联系方式唯一性检查（/api/front/profile/verification-checks）排除当前用户
 *   而非伪造的 user_id / userId。
 * - 验证遗留联系方式更新接口（/user/center/updateVerifyInfo）拒绝其他用户邮箱，
 *   不允许用伪造 userId 排除他人。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台个人资料联系方式（手机/邮箱）唯一性校验的越权回归测试。
 *
 * 入参例子：
 * - 现代参数：type=phone、userphoneNo=13922800100、伪造 user_id={otherId}&userId={otherId}。
 * - 遗留参数：type=email、useremail={otherEmail}。
 *
 * 返回值：
 * - 现代接口对本人手机号返回 msg 为 SUC；遗留接口对他人邮箱返回 msg 为 FAIL、_eml 为 useremail。
 *
 * 异常或失败场景：
 * - 遗留接口用他人邮箱做唯一性检查时被拒绝（FAIL/useremail）。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontProfileContactUniquenessOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证现代手机号唯一性检查排除当前用户而非伪造的 user_id。
    public function test_modern_phone_uniqueness_check_excludes_current_user_not_spoofed_user_id(): void
    {
        $viewerId = 412280100;
        $otherId = 412280101;
        $viewerEmail = 'front-contact-unique-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-contact-unique-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'contact-unique-boundary-viewer', $viewerEmail, '86-13922800100');
        $this->insertUserInfo($otherId, 'contact-unique-boundary-other', $otherEmail, '86-13922800101');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/verification-checks', [
                'type' => 'phone',
                'userphoneNo' => '13922800100',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');
    }

    // 验证遗留邮箱唯一性检查拒绝其他用户邮箱而非伪造排除。
    public function test_legacy_email_uniqueness_check_rejects_other_user_email_not_spoofed_exclusion(): void
    {
        $viewerId = 412280200;
        $otherId = 412280201;
        $viewerEmail = 'front-contact-unique-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-contact-unique-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'contact-unique-legacy-boundary-viewer', $viewerEmail, '86-13922800200');
        $this->insertUserInfo($otherId, 'contact-unique-legacy-boundary-other', $otherEmail, '86-13922800201');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updateVerifyInfo', [
                'type' => 'email',
                'useremail' => $otherEmail,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('_eml', 'useremail');
    }

    // 校验权限清单文档记录了联系方式唯一性属主边界闭环。
    public function test_final_checklist_records_contact_uniqueness_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 228.', $checklist);
        $this->assertStringContainsString('ProfileController::updateVerifyInfo', $checklist);
        $this->assertStringContainsString('/api/front/profile/verification-checks', $checklist);
        $this->assertStringContainsString('user/center/updateVerifyInfo', $checklist);
        $this->assertStringContainsString('FrontProfileContactUniquenessOwnerBoundaryClosureModuleTest', $checklist);
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
