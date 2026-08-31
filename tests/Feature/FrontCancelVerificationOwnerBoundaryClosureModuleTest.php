<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端注销身份校验-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证现代注销身份校验接口 /api/front/profile/verification-cancellation-checks 使用当前登录用户身份，忽略伪装 userId。
 * - 验证旧接口 /user/center/cancelVerifyInfo 使用他人手机号/邮箱/证件号时返回校验失败。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 注销流程身份校验接口的归属权边界回归测试，防止用他人身份通过校验。
 *
 * 入参例子：
 * - POST /api/front/profile/verification-cancellation-checks
 *   请求体：{ "userphoneNo": "13922100100", "useremail": "{当前用户邮箱}",
 *            "userIdcardNo": "ID412210100", "user_id": {他人ID}, "userId": {他人ID} }
 * - POST /user/center/cancelVerifyInfo
 *   请求体：{ "userphoneNo": "{他人手机号}", "useremail": "{他人邮箱}", "userIdcardNo": "{他人证件号}", ... }
 *
 * 返回值：
 * - 现代接口返回 msg=SUC、err=NOErr、col=NOCOL；
 *   旧接口返回 msg=FAIL、err=phoneErr、col=userphoneNo。
 *
 * 异常或失败场景：
 * - 若现代接口校验失败或旧接口用他人凭据校验通过，测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontCancelVerificationOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代注销身份校验使用当前用户而非伪装 userId。
     *
     * 携带伪装 user_id / userId 但使用当前用户凭据请求校验，断言校验通过。
     */
    public function test_modern_cancel_identity_check_uses_current_user_not_spoofed_user_id(): void
    {
        $viewerId = 412210100;
        $otherId = 412210101;
        $viewerEmail = 'front-cancel-verify-boundary-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'cancel-verify-boundary-viewer', $viewerEmail, '13922100100');
        $this->insertUserInfo($otherId, 'cancel-verify-boundary-other', 'front-cancel-verify-boundary-' . $otherId . '@example.test', '13922100101');
        $this->insertUserAuth($viewerId, 'ID412210100');
        $this->insertUserAuth($otherId, 'ID412210101');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/verification-cancellation-checks', [
                'userphoneNo' => '13922100100',
                'useremail' => $viewerEmail,
                'userIdcardNo' => 'ID412210100',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'NOErr')
            ->assertJsonPath('col', 'NOCOL');
    }

    /**
     * 验证旧注销身份校验拒绝他人的手机号/邮箱/证件号。
     *
     * 携带他人凭据请求 cancelVerifyInfo，断言返回 FAIL 与 phoneErr。
     */
    public function test_legacy_cancel_identity_check_rejects_spoofed_other_user_credentials(): void
    {
        $viewerId = 412210200;
        $otherId = 412210201;
        $otherEmail = 'front-cancel-verify-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'cancel-verify-legacy-boundary-viewer', 'front-cancel-verify-boundary-' . $viewerId . '@example.test', '13922100200');
        $this->insertUserInfo($otherId, 'cancel-verify-legacy-boundary-other', $otherEmail, '13922100201');
        $this->insertUserAuth($viewerId, 'ID412210200');
        $this->insertUserAuth($otherId, 'ID412210201');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/cancelVerifyInfo', [
                'userphoneNo' => '13922100201',
                'useremail' => $otherEmail,
                'userIdcardNo' => 'ID412210201',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'phoneErr')
            ->assertJsonPath('col', 'userphoneNo');
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 221 项、ProfileController::cancelVerifyInfo 及本测试类名。
     */
    public function test_final_checklist_records_cancel_verification_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 221.', $checklist);
        $this->assertStringContainsString('ProfileController::cancelVerifyInfo', $checklist);
        $this->assertStringContainsString('/api/front/profile/verification-cancellation-checks', $checklist);
        $this->assertStringContainsString('user/center/cancelVerifyInfo', $checklist);
        $this->assertStringContainsString('FrontCancelVerificationOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带指定邮箱与手机号的客户测试数据（account_type 固定为 2）。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param string $email 登录邮箱。
     * @param string $phone 手机号。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, string $email, string $phone): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();

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
     * 插入一条实名认证记录（证件号已审核通过）。
     *
     * @param int $userId 用户 ID。
     * @param string $idCardNo 身份证号。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserAuth(int $userId, string $idCardNo): void
    {
        $now = time();

        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'id_card_no' => $idCardNo,
            'id_card_status' => 2,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户的认证、用户信息及登录测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
