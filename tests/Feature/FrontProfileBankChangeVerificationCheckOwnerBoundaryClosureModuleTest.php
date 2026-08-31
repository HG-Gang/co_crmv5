<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台改银行卡验证检查属主边界闭环测试。
 *
 * 文件功能：
 * - 验证现代改卡验证检查（/api/front/profile/bank-card-change/verification-checks）
 *   使用当前登录用户而非伪造的 user_id / userId。
 * - 验证遗留改卡验证码接口（/user/center/changeBankCardVerifyCode）拒绝其他用户邮箱。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台“修改银行卡”验证环节的越权回归测试，防止用他人邮箱发起验证。
 *
 * 入参例子：
 * - 现代参数：useremail={viewerEmail}、伪造 user_id={otherId}&userId={otherId}。
 * - 遗留参数：useremail={otherEmail}（他人邮箱）。
 *
 * 返回值：
 * - 现代接口返回 msg 为 SUC；遗留接口对他人邮箱返回 msg 为 FAIL、err/col 为 useremail。
 *
 * 异常或失败场景：
 * - 遗留接口提交他人邮箱时被拒绝（FAIL/useremail）。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontProfileBankChangeVerificationCheckOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证现代改卡邮箱检查使用当前用户而非伪造的 user_id / userId。
    public function test_modern_bank_change_email_check_uses_current_user_not_spoofed_user_id(): void
    {
        $viewerId = 412270100;
        $otherId = 412270101;
        $viewerEmail = 'front-bank-change-check-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-bank-change-check-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'bank-change-check-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'bank-change-check-boundary-other', $otherEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/bank-card-change/verification-checks', [
                'useremail' => $viewerEmail,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');
    }

    // 验证遗留改卡邮箱检查拒绝伪造的其他用户邮箱。
    public function test_legacy_bank_change_email_check_rejects_spoofed_other_user_email(): void
    {
        $viewerId = 412270200;
        $otherId = 412270201;
        $viewerEmail = 'front-bank-change-check-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-bank-change-check-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'bank-change-check-legacy-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'bank-change-check-legacy-boundary-other', $otherEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/changeBankCardVerifyCode', [
                'useremail' => $otherEmail,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'useremail')
            ->assertJsonPath('col', 'useremail');
    }

    // 校验权限清单文档记录了改卡验证检查属主边界闭环。
    public function test_final_checklist_records_bank_change_verification_check_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 227.', $checklist);
        $this->assertStringContainsString('ProfileController::changeBankCardVerifyCode', $checklist);
        $this->assertStringContainsString('/api/front/profile/bank-card-change/verification-checks', $checklist);
        $this->assertStringContainsString('user/center/changeBankCardVerifyCode', $checklist);
        $this->assertStringContainsString('FrontProfileBankChangeVerificationCheckOwnerBoundaryClosureModuleTest', $checklist);
    }

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
            'phone' => '1392270' . substr((string) $userId, -4),
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
