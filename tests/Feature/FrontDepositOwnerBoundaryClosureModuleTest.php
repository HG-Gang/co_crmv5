<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:53
 */

/**
 * 前端入金-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证现代入金提交接口 /api/front/deposits/submissions 忽略伪装 user_id / userId，且无适配器时 fail-closed 返回 OPERATION_NOT_ALLOWED。
 * - 验证旧入金接口 /user/deposit_request 同样忽略伪装 userId 并 fail-closed。
 * - 验证入金历史接口 /api/front/deposits/history 只返回当前用户记录并忽略伪装 userId。
 * - 验证入金历史拒绝非严格与不支持的状态过滤值。
 * - 验证数据库失败状态（09）映射为拒绝文案。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端入金提交与历史接口的归属权边界回归测试。
 *
 * 入参例子：
 * - POST /api/front/deposits/submissions
 *   请求体：{ "amount": "120.00", "channel": "boundary-236-modern", "user_id": {他人ID}, "userId": {他人ID} }
 * - POST /user/deposit_request（body: { "deposit_amt": "130.00", "passageway": ..., "user_id": ..., "userId": ... }）
 * - GET /api/front/deposits/history?user_id={他人ID}&userId={他人ID}&limit=20
 * - GET /api/front/deposits/history?status=01abc（或 03、-1、09）
 *
 * 返回值：
 * - 越权提交返回 OPERATION_NOT_ALLOWED 且不落库；历史接口返回 SUCCESS 且仅含当前用户记录；
 *   非法状态返回 VALIDATION_FAILED；状态 09 的 status_text 为拒绝文案。
 *
 * 异常或失败场景：
 * - 若伪装 userId 生效、他人记录被返回、非法状态未拒绝或失败状态文案错误，测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontDepositOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代入金提交忽略伪装 userId 且无适配器时 fail-closed。
     *
     * 携带伪装 user_id / userId 提交入金，断言返回 OPERATION_NOT_ALLOWED
     * 且双方 deposit_records 均无记录、响应不泄漏他人 ID。
     */
    public function test_modern_deposit_submission_ignores_spoofed_user_id_and_fails_closed_without_adapter(): void
    {
        $viewerId = 412360100;
        $otherId = 412360101;
        $channelCode = 'boundary-236-modern';
        $viewerEmail = 'front-deposit-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-deposit-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail], [$channelCode]);
        $this->allowDepositsForTest();
        $this->insertPaymentChannel($channelCode, 'Boundary Modern Channel', 2.5);
        $this->insertUserInfo($viewerId, 'deposit-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'deposit-boundary-other', $otherEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', 'owner-boundary-modern')
            ->postJson('/api/front/deposits/submissions', [
                'amount' => '120.00',
                'channel' => $channelCode,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertDatabaseMissing('deposit_records', ['user_id' => $viewerId]);
        $this->assertDatabaseMissing('deposit_records', [
            'user_id' => $otherId,
            'amount' => 120,
            'channel_name' => 'Boundary Modern Channel',
        ]);
        $this->assertStringNotContainsString((string) $otherId, $response->getContent());
    }

    /**
     * 验证旧入金请求忽略伪装 userId 且无适配器时 fail-closed。
     *
     * 携带伪装 user_id / userId 提交旧入金请求，断言返回 OPERATION_NOT_ALLOWED 且无入金记录落库。
     */
    public function test_legacy_deposit_request_ignores_spoofed_user_id_and_fails_closed_without_adapter(): void
    {
        $viewerId = 412360200;
        $otherId = 412360201;
        $channelCode = 'boundary-236-legacy';
        $viewerEmail = 'front-deposit-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-deposit-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail], [$channelCode]);
        $this->allowDepositsForTest();
        $this->insertPaymentChannel($channelCode, 'Boundary Legacy Channel', 1.75);
        $this->insertUserInfo($viewerId, 'deposit-legacy-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'deposit-legacy-other', $otherEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', 'owner-boundary-legacy')
            ->postJson('/user/deposit_request', [
                'deposit_amt' => '130.00',
                'passageway' => $channelCode,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertDatabaseMissing('deposit_records', ['user_id' => $viewerId]);
        $this->assertDatabaseMissing('deposit_records', [
            'user_id' => $otherId,
            'amount' => 130,
            'channel_name' => 'Boundary Legacy Channel',
        ]);
    }

    /**
     * 验证入金历史忽略伪装 userId 并只返回当前用户记录。
     *
     * 携带他人 user_id / userId 查询历史，断言只返回当前用户的入金记录且总额正确。
     */
    public function test_deposit_history_ignores_spoofed_user_id_and_returns_current_user_records_only(): void
    {
        $viewerId = 412360300;
        $otherId = 412360301;
        $viewerEmail = 'front-deposit-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-deposit-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail], []);
        $this->insertUserInfo($viewerId, 'deposit-history-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'deposit-history-other', $otherEmail);
        $this->insertDepositRecord($viewerId, 'deposit-history-viewer', 'DEP-BOUNDARY-VIEWER', 210, 210);
        $this->insertDepositRecord($otherId, 'deposit-history-other', 'DEP-BOUNDARY-OTHER', 999, 999);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/deposits/history?user_id=' . $otherId . '&userId=' . $otherId . '&limit=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = $response->json('data.list.data');
        $this->assertCount(1, $rows);
        $this->assertSame($viewerId, (int) $rows[0]['userId']);
        $this->assertSame('DEP-BOUNDARY-VIEWER', $rows[0]['order_no']);
        $this->assertStringNotContainsString('DEP-BOUNDARY-OTHER', $response->getContent());
        $this->assertStringNotContainsString('deposit-history-other', $response->getContent());
        $response->assertJsonPath('data.totalRow.amount', 210);
    }

    /**
     * 验证入金历史拒绝非严格与不支持的状态过滤值。
     *
     * 依次以 01abc、03、-1 作为 status 查询，断言均返回 VALIDATION_FAILED 且不泄漏记录。
     */
    public function test_deposit_history_rejects_non_strict_and_unsupported_status_filters(): void
    {
        $viewerId = 412360400;
        $viewerEmail = 'front-deposit-boundary-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail], []);
        $this->insertUserInfo($viewerId, 'deposit-status-viewer', $viewerEmail);
        $this->insertDepositRecord($viewerId, 'deposit-status-viewer', 'DEP-STATUS-HIDDEN', 310, 310, '01');
        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();

        foreach (['01abc', '03', '-1'] as $invalidStatus) {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->getJson('/api/front/deposits/history?status=' . urlencode($invalidStatus));

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
            $this->assertStringNotContainsString('DEP-STATUS-HIDDEN', $response->getContent());
        }
    }

    /**
     * 验证入金历史将数据库失败状态 09 映射为拒绝文案。
     *
     * 以 status=09 查询，断言返回 SUCCESS 且记录的 status_text 为拒绝文案。
     */
    public function test_deposit_history_maps_database_failure_status_to_rejected_text(): void
    {
        $viewerId = 412360500;
        $viewerEmail = 'front-deposit-boundary-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail], []);
        $this->insertUserInfo($viewerId, 'deposit-failed-viewer', $viewerEmail);
        $this->insertDepositRecord($viewerId, 'deposit-failed-viewer', 'DEP-FAILED-STATUS', 410, 410, '09');
        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/deposits/history?status=09');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.order_no', 'DEP-FAILED-STATUS')
            ->assertJsonPath('data.list.data.0.status_text', __('front.status_rejected'));
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 236 与 343 项、DepositController 相关方法及状态码白名单和本测试类名。
     */
    public function test_final_checklist_records_deposit_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 236.', $checklist);
        $this->assertStringContainsString('DepositController::submitDeposit', $checklist);
        $this->assertStringContainsString('DepositController::deposit_request', $checklist);
        $this->assertStringContainsString('DepositController::depositHistory', $checklist);
        $this->assertStringContainsString('/api/front/deposits/submissions', $checklist);
        $this->assertStringContainsString('/api/front/deposits/history', $checklist);
        $this->assertStringContainsString('user/deposit_request', $checklist);
        $this->assertStringContainsString('FrontDepositOwnerBoundaryClosureModuleTest', $checklist);
        $this->assertStringContainsString('## 343.', $checklist);
        $this->assertStringContainsString('01/02/05/09/10', $checklist);
    }

    /**
     * 写入入金相关的系统配置，允许测试环境执行入金。
     *
     * @return void 无返回值，仅写入 system_configs。
     */
    private function allowDepositsForTest(): void
    {
        foreach ([
            'deposit_enabled' => '1',
            'deposit_weekend_enabled' => '1',
            'deposit_start_time' => '',
            'deposit_end_time' => '',
            'deposit_min_amount' => '10',
            'deposit_max_amount' => '500000',
            'deposit_disabled_message' => 'Deposits are disabled',
        ] as $key => $value) {
            $now = time();
            DB::table('system_configs')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => 'deposit',
                    'description' => 'Front deposit owner boundary test fixture',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * 插入一条启用的支付通道配置。
     *
     * @param string $code 通道编码。
     * @param string $name 通道名称。
     * @param float $exchangeRate 汇率。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertPaymentChannel(string $code, string $name, float $exchangeRate): void
    {
        $now = time();

        DB::table('payment_channels')->insert([
            'name' => $name,
            'channel_code' => $code,
            'exchange_rate' => $exchangeRate,
            'is_enabled' => 1,
            'sort' => 236,
            'config' => json_encode([
                'gateway_url' => 'https://pay.example.test/' . $code,
                'min_amount' => 10,
                'max_amount' => 500000,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 插入一条入金记录。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param string $orderNo 本地订单号。
     * @param float $amount 入金金额。
     * @param float $actualAmount 实际到账金额。
     * @param string $status 入金状态码（默认 01）。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertDepositRecord(
        int $userId,
        string $userName,
        string $orderNo,
        float $amount,
        float $actualAmount,
        string $status = '01'
    ): void
    {
        $now = time();

        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'amount' => $amount,
            'actual_amount' => $actualAmount,
            'exchange_rate' => 1,
            'channel_name' => 'Boundary History Channel',
            'local_order_no' => $orderNo,
            'status' => $status,
            'remarks' => 'owner-boundary-history',
            'created_by' => $userName,
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
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
            'phone' => '1392360' . substr((string) $userId, -4),
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
            'is_ecn' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户的入金记录、用户信息、登录及支付通道测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param array<int, string> $emails 待清理的邮箱列表。
     * @param array<int, string> $channelCodes 待清理的通道编码列表（空数组表示不清理）。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds, array $emails, array $channelCodes): void
    {
        DB::table('deposit_records')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();

        if ($channelCodes !== []) {
            DB::table('payment_channels')->whereIn('channel_code', $channelCodes)->delete();
        }
    }
}
