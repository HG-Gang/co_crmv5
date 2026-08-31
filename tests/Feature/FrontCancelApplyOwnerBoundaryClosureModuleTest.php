<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:38
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
 * 前台现代注销申请归属边界测试。
 *
 * 文件功能：
 * - 验证状态查询和注销提交只能使用当前认证用户，不能由 user_id 或 userId 覆盖。
 * - 验证现代注销成功仍需真实身份、一次性验证码和密码，并在成功后消费验证码。
 */
class FrontCancelApplyOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证状态接口忽略伪造用户 ID，只返回当前用户自己的最新申请。
     */
    public function test_cancel_status_ignores_spoofed_user_id_and_returns_current_users_apply(): void
    {
        $viewerId = 412190100;
        $otherId = 412190101;

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'cancel-boundary-viewer');
        $this->insertUserInfo($otherId, 'cancel-boundary-other');
        $this->insertCancelApply($viewerId, 'Own visible cancellation');
        $this->insertCancelApply($otherId, 'Other hidden cancellation');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/cancellation?user_id=' . $otherId . '&userId=' . $otherId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.cancel_remark', 'Own visible cancellation');

        $this->assertStringNotContainsString('Other hidden cancellation', $response->getContent());
    }

    /**
     * 验证完整敏感校验通过后，伪造用户 ID 仍不能改变注销申请归属。
     */
    public function test_cancel_apply_ignores_spoofed_user_id_and_creates_apply_for_current_user(): void
    {
        $viewerId = 412190200;
        $otherId = 412190201;

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'cancel-apply-boundary-viewer');
        $this->insertUserInfo($otherId, 'cancel-apply-boundary-other');
        $this->insertUserAuth($viewerId, 'ID' . $viewerId);
        $this->insertUserAuth($otherId, 'ID' . $otherId);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $viewerPhone = '1782190' . substr((string) $viewerId, -4);
        $code = '219200';
        Cache::put('front_profile_cancel_code:' . $viewerId, [
            'code' => $code,
            'email' => strtolower((string) $login->email),
            'phone' => $viewerPhone,
            'type' => 'cancel',
        ], now()->addMinutes(10));
        config(['mt4.enabled' => false]);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/account/cancellation-applications', [
                'reason' => 'Spoofed id should stay on current user',
                'userIdcardNo' => 'ID' . $viewerId,
                'userphoneNo' => $viewerPhone,
                'useremail' => (string) $login->email,
                'password' => 'password',
                'userverfcode' => $code,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('cancel_applies', [
            'user_id' => $viewerId,
            'cancel_remark' => 'Spoofed id should stay on current user',
            'status' => 0,
        ]);
        $this->assertDatabaseMissing('cancel_applies', [
            'user_id' => $otherId,
            'cancel_remark' => 'Spoofed id should stay on current user',
        ]);
        $this->assertNull(Cache::get('front_profile_cancel_code:' . $viewerId));
    }

    /**
     * 验证最终闭环清单记录现代注销查询和提交的归属测试证据。
     */
    public function test_final_checklist_records_cancel_apply_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 219.', $checklist);
        $this->assertStringContainsString('CancelController::status', $checklist);
        $this->assertStringContainsString('CancelController::apply', $checklist);
        $this->assertStringContainsString('/api/front/account/cancellation', $checklist);
        $this->assertStringContainsString('/api/front/account/cancellation-applications', $checklist);
        $this->assertStringContainsString('FrontCancelApplyOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 创建注销测试所需的登录账号和业务资料。
     *
     * @param int $userId 业务用户 ID。
     * @param string $userName 用户名称。
     * @return void
     */
    private function insertUserInfo(int $userId, string $userName): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-cancel-boundary-' . $userId . '@example.test',
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
            'phone' => '1782190' . substr((string) $userId, -4),
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
     * 创建当前用户实名认证证件资料，用于注销身份闭环校验。
     *
     * @param int $userId 业务用户 ID。
     * @param string $idCardNo 当前用户身份证号。
     * @return void
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
     * 创建指定用户的注销申请，供状态归属查询测试使用。
     *
     * @param int $userId 申请所属业务用户 ID。
     * @param string $remark 用户提交的注销原因。
     * @return int 新增申请主键。
     */
    private function insertCancelApply(int $userId, string $remark): int
    {
        $now = time();

        return (int) DB::table('cancel_applies')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'cancel-user-' . $userId,
            'status' => 0,
            'cancel_remark' => $remark,
            'reject_reason' => '',
            'created_by' => (string) $userId,
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定业务用户的注销、交易、身份、资料、登录和验证码测试数据。
     *
     * @param array<int, int> $userIds 需要清理的业务用户 ID 列表。
     * @return void
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('cancel_applies')->whereIn('user_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();

        foreach ($userIds as $userId) {
            Cache::forget('front_profile_cancel_code:' . $userId);
        }
    }
}
