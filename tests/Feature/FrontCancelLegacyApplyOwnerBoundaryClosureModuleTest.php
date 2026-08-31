<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端旧注销申请-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证旧注销接口 /user/center/ajaxCancelAccount 忽略请求中的 user_id / userId 伪装参数。
 * - 验证注销申请始终为当前登录用户创建，而不是伪装的目标用户。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 旧注销申请接口的归属权边界回归测试，防止通过参数伪装为他人提交注销。
 *
 * 入参例子：
 * - POST /user/center/ajaxCancelAccount
 *   请求体：{ "userIdcardNo": "ID412200100", "userphoneNo": "13922000100",
 *            "useremail": "...", "password": "password", "userverfcode": "246810",
 *            "cancelRemark": "...", "user_id": {他人ID}, "userId": {他人ID} }
 * - 会话：withSession(['cancelCode' => '246810'])
 *
 * 返回值：
 * - HTTP 200，msg 为 SUC、err 为 NOErr、col 为 NOCOL；
 *   cancel_applies 表中只有当前用户的新申请记录。
 *
 * 异常或失败场景：
 * - 若申请记录落在伪装的目标用户上，或返回非成功标志，测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧注销接口忽略伪装 userId 并为当前用户创建申请。
     *
     * 携带伪装 user_id / userId 提交注销申请，
     * 断言响应成功且 cancel_applies 只存在当前用户的记录。
     */
    public function test_legacy_cancel_apply_ignores_spoofed_user_id_and_creates_apply_for_current_user(): void
    {
        $viewerId = 412200100;
        $otherId = 412200101;
        $viewerEmail = 'front-cancel-legacy-boundary-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'cancel-legacy-boundary-viewer', $viewerEmail, '13922000100');
        $this->insertUserInfo($otherId, 'cancel-legacy-boundary-other', 'front-cancel-legacy-boundary-' . $otherId . '@example.test', '13922000101');
        $this->insertUserAuth($viewerId, 'ID412200100');
        $this->insertUserAuth($otherId, 'ID412200101');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withSession(['cancelCode' => '246810'])
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/ajaxCancelAccount', [
                'userIdcardNo' => 'ID412200100',
                'userphoneNo' => '13922000100',
                'useremail' => $viewerEmail,
                'password' => 'password',
                'userverfcode' => '246810',
                'cancelRemark' => 'Legacy spoofed id should stay on current user',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'NOErr')
            ->assertJsonPath('col', 'NOCOL');

        $this->assertDatabaseHas('cancel_applies', [
            'user_id' => $viewerId,
            'cancel_remark' => 'Legacy spoofed id should stay on current user',
            'status' => 0,
        ]);
        $this->assertDatabaseMissing('cancel_applies', [
            'user_id' => $otherId,
            'cancel_remark' => 'Legacy spoofed id should stay on current user',
        ]);
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 220 项、CancelController::ajaxCancelAccount 及本测试类名。
     */
    public function test_final_checklist_records_legacy_cancel_apply_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 220.', $checklist);
        $this->assertStringContainsString('CancelController::ajaxCancelAccount', $checklist);
        $this->assertStringContainsString('user/center/ajaxCancelAccount', $checklist);
        $this->assertStringContainsString('cancelRemark', $checklist);
        $this->assertStringContainsString('FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest', $checklist);
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
     * 清理指定用户的注销申请、交易、认证、用户信息及登录测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('cancel_applies')->whereIn('user_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
