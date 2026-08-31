<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:39
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧代理编辑 MT4 敏感字段失败关闭测试。
 *
 * 文件功能：
 * - agents_edit_save 已补齐本地资料安全闭环后，不再整体返回 410。
 * - 仍然验证交易组、杠杆、密码、启停等需要 MT4 确认的变更不能落入本地库。
 */
class AdminLegacyAgentEditFailClosedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_agent_edit_does_not_write_when_mt4_sensitive_fields_change(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentId = 982901;
        $this->seedAgent($agentId);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withoutAdminMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/agents/agents_edit_save', [
            'usergrpName' => 'MT4-GROUP-B',
            'data' => [
                'userId' => $agentId,
                'username' => 'legacy-agent-edit-should-not-write',
                'useremail' => 'legacy-agent-edit@example.test',
                'userphoneNo' => '13900000000',
                'userIdcardNo' => 'ID982901',
                'usergrpId' => 1,
                'userparentId' => 0,
                'userrebate' => 10,
                'cust_lvg' => 200,
                'gift_allowed' => 1,
            ],
        ]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'MT4SYNCUNSUPPORTED');
        $this->assertSame([], array_values(array_filter($queries, static function (array $query): bool {
            return preg_match('/^\s*(insert|update|delete|replace)\b/i', (string) ($query['query'] ?? '')) === 1;
        })));
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $agentId,
            'user_name' => 'legacy-agent-edit-before',
            'mt4_group' => 'MT4-GROUP-A',
            'leverage' => 100,
        ]);
    }

    private function withoutAdminMiddleware()
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ]);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'legacy-agent-edit-fail-closed-super',
                'email' => 'legacy-agent-edit-fail-closed-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建一个代理账号快照，用于验证敏感字段失败时所有本地字段保持原值。
     *
     * @param int $userId 代理业务用户 ID。
     * @return void
     */
    private function seedAgent(int $userId): void
    {
        $now = time();
        DB::table('operation_logs')->where('target_user_id', $userId)->delete();
        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-agent-edit@example.test',
            'password' => Hash::make('password'),
            'account_type' => 1,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'legacy-agent-edit-before',
            'phone' => '86-13900000000',
            'account_type' => 1,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'leverage' => 100,
            'comm_rate' => 10,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'mt4_group' => 'MT4-GROUP-A',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
