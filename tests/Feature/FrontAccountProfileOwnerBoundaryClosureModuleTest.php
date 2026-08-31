<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 11:32
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 前台账户资料所有权边界测试。
 *
 * 文件功能：
 * - 验证账户概览、余额和账户类型切换始终使用当前登录用户。
 * - 请求体或查询串伪造 user_id/userId 时，不得读取或更新其他用户。
 * - 账户类型切换夹具包含真实配对组和明确成功的 MT4 测试响应。
 */
class FrontAccountProfileOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_account_profile_ignores_spoofed_user_id_and_returns_current_user_only(): void
    {
        $viewerId = 412350100;
        $otherId = 412350101;
        $viewerEmail = 'front-account-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-account-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'account-boundary-viewer', $viewerEmail, 120.50, 98.75, 0, 100);
        $this->insertUserInfo($otherId, 'account-boundary-other', $otherEmail, 9999.99, 8888.88, 1, 200);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/profile?user_id=' . $otherId . '&userId=' . $otherId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user_name', 'account-boundary-viewer');
        // user_id 与资金字段由 MySQL decimal/bigint 直接透传，可能以字符串返回，使用宽松比较。
        $this->assertEquals($viewerId, $response->json('data.user_id'));
        $this->assertEquals(120.5, $response->json('data.total_funds'));
        $this->assertEquals(98.75, $response->json('data.equity'));
        $this->assertStringNotContainsString('account-boundary-other', $response->getContent());
        $this->assertStringNotContainsString('9999.99', $response->getContent());
    }

    public function test_account_balance_ignores_spoofed_user_id_and_returns_current_user_only(): void
    {
        $viewerId = 412350200;
        $otherId = 412350201;
        $viewerEmail = 'front-account-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-account-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'account-balance-viewer', $viewerEmail, 220.25, 188.50, 0, 100);
        $this->insertUserInfo($otherId, 'account-balance-other', $otherEmail, 7777.77, 6666.66, 1, 200);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/balance?user_id=' . $otherId . '&userId=' . $otherId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user_name', 'account-balance-viewer');
        $this->assertEquals($viewerId, $response->json('data.user_id'));
        $this->assertEquals(220.25, $response->json('data.total_funds'));
        $this->assertEquals(188.5, $response->json('data.equity'));
        $this->assertStringNotContainsString('account-balance-other', $response->getContent());
        $this->assertStringNotContainsString('7777.77', $response->getContent());
    }

    /**
     * 验证旧账户切换入口忽略伪造用户编号，并只同步当前登录用户。
     *
     * @return void
     */
    public function test_legacy_account_type_change_ignores_spoofed_user_id_and_updates_current_user_only(): void
    {
        $viewerId = 412350300;
        $otherId = 412350301;
        $viewerEmail = 'front-account-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-account-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $groups = $this->insertAccountTypeGroups();
        $this->insertUserInfo(
            $viewerId,
            'account-change-viewer',
            $viewerEmail,
            3000,
            3000,
            0,
            100,
            $groups['stp_id'],
            'OWNER-STP'
        );
        $this->insertUserInfo(
            $otherId,
            'account-change-other',
            $otherEmail,
            0,
            0,
            0,
            100,
            $groups['stp_id'],
            'OWNER-STP'
        );

        $manager = new class extends Mt4ManagerService {
            /** @var array<int, array<string, mixed>> $calls 已收到的账户同步参数。 */
            public $calls = [];

            /** 构造不访问真实 Socket 的 MT4 测试替身。 */
            public function __construct()
            {
                parent::__construct('127.0.0.1', 1, 'test-key', 'test-version', 1);
            }

            /**
             * 记录当前用户同步参数并返回 MT4 明确成功。
             *
             * @param int $userId 当前登录用户编号。
             * @param string $group 目标 MT4 组名。
             * @param int $leverage 目标杠杆。
             * @return array<string, string> MT4 成功响应。
             */
            public function updateUserTradingProfile($userId, $group, $leverage)
            {
                $this->calls[] = compact('userId', 'group', 'leverage');

                return ['status' => 'ok', 'err' => '0', 'message' => 'OK'];
            }
        };
        $this->app->instance(Mt4ManagerService::class, $manager);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/change_account_save', [
                'is_enc' => 1,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS');

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $viewerId,
            'group_id' => $groups['ecn_id'],
            'mt4_group' => 'OWNER-ECN',
            'is_ecn' => 1,
            'leverage' => 200,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'is_ecn' => 0,
            'leverage' => 100,
        ]);
        $this->assertSame([
            ['userId' => $viewerId, 'group' => 'OWNER-ECN', 'leverage' => 200],
        ], $manager->calls);
    }

    public function test_legacy_account_type_change_rejects_non_strict_and_unsupported_ecn_values(): void
    {
        $viewerId = 412350400;
        $viewerEmail = 'front-account-boundary-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->insertUserInfo($viewerId, 'account-change-validation-viewer', $viewerEmail, 0, 0, 0, 100);
        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();

        foreach (['1abc', 2, -1, ''] as $invalidIsEcn) {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->postJson('/user/change_account_save', ['is_enc' => $invalidIsEcn]);

            $response->assertOk()
                ->assertJsonPath('msg', 'FAIL')
                ->assertJsonPath('err', 'UPDATEFAIL')
                ->assertJsonPath('col', 'is_enc');
            $this->assertDatabaseHas('user_infos', [
                'user_id' => $viewerId,
                'is_ecn' => 0,
                'leverage' => 100,
            ]);
        }
    }

    public function test_final_checklist_records_account_profile_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 235.', $checklist);
        $this->assertStringContainsString('AccountController::accountInfo', $checklist);
        $this->assertStringContainsString('AccountController::accountBalance', $checklist);
        $this->assertStringContainsString('AccountController::changeAccountSave', $checklist);
        $this->assertStringContainsString('/api/front/account/profile', $checklist);
        $this->assertStringContainsString('/api/front/account/balance', $checklist);
        $this->assertStringContainsString('user/change_account_save', $checklist);
        $this->assertStringContainsString('FrontAccountProfileOwnerBoundaryClosureModuleTest', $checklist);
        $this->assertStringContainsString('## 342.', $checklist);
        $this->assertStringContainsString('is_enc/is_ecn', $checklist);
    }

    private function insertUserInfo(
        int $userId,
        string $userName,
        string $email,
        float $totalFunds,
        float $equity,
        int $isEcn,
        int $leverage,
        int $groupId = 0,
        string $mt4Group = ''
    ): void {
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
            'phone' => '1392350' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => $groupId,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => $totalFunds,
            'used_margin' => 0,
            'avail_margin' => $totalFunds,
            'equity' => $equity,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => $leverage,
            'is_ecn' => $isEcn,
            'mt4_group' => $mt4Group,
            'original_group' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 创建所有权测试使用的双向 STP/ECN 配对组。
     *
     * @return array{stp_id: int, ecn_id: int} 返回两个当前交易组主键。
     */
    private function insertAccountTypeGroups(): array
    {
        $now = time();
        $base = [
            'legacy_group_id' => null,
            'pair_id' => null,
            'radix' => 50,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
        $stpId = (int) DB::table('group_configs')->insertGetId(array_merge($base, [
            'name' => 'OWNER-STP',
            'is_ecn' => 0,
        ]));
        $ecnId = (int) DB::table('group_configs')->insertGetId(array_merge($base, [
            'name' => 'OWNER-ECN',
            'is_ecn' => 1,
            'pair_id' => $stpId,
        ]));
        DB::table('group_configs')->where('id', $stpId)->update(['pair_id' => $ecnId]);

        return ['stp_id' => $stpId, 'ecn_id' => $ecnId];
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
