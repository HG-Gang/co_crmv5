<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:31
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
 * 旧后台代理编辑保存兼容闭环测试。
 *
 * 文件功能：
 * - 验证 index/admin/agents/agents_edit_save 不再 410 丢弃旧 Blade 表单。
 * - 验证不触发 MT4 真实同步的本地资料字段可原子写入新项目 user_infos、user_logins 和 user_auths。
 * - 验证重复邮箱和 MT4 敏感字段会按旧响应格式失败关闭，并且不会留下半更新数据。
 */
class AdminLegacyAgentEditSaveClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_agent_edit_save_updates_local_agent_profile_with_legacy_response(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentId = 98727901;
        $oldLevelId = $this->ensureAgentLevel(91, '旧代理编辑原等级');
        $newLevelId = $this->ensureAgentLevel(92, '旧代理编辑新等级');
        $this->seedAgent($agentId, [
            'user_name' => '编辑前代理',
            'level_id' => $oldLevelId,
            'comm_rate' => 10,
            'phone' => '86-13900000001',
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'trading_mode' => 0,
            'settle_cycle' => 1,
            'equity_ratio' => 0,
            'is_gift_allowed' => 0,
            'remark' => '编辑前备注',
        ], [
            'email' => 'legacy-agent-before@example.test',
        ], [
            'id_card_no' => 'OLD-ID-98727901',
        ]);

        $response = $this->withoutAdminMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/agents/agents_edit_save', $this->legacyPayload($agentId, [
                'username' => '编辑后代理',
                'useremail' => 'Legacy-Agent-After@Example.Test',
                'userphoneNo' => '13999990001',
                'userIdcardNo' => 'NEW-ID-98727901',
                'useragtId' => $newLevelId,
                'userrebate' => 35,
                'usertype' => 1,
                'userrights' => 2500,
                'gift_allowed' => 1,
                'userremark' => '旧后台代理备注已迁移',
            ], [
                'isoutmoney' => 1,
                'isallowmoney' => 1,
                'datausercycle' => 2,
                'sex' => 2,
            ]));

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED)
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'NOERR')
            ->assertJsonPath('col', 'NOCOL');

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $agentId,
            'account_type' => 1,
            'user_name' => '编辑后代理',
            'phone' => '86-13999990001',
            'level_id' => $newLevelId,
            'comm_rate' => 35,
            'is_withdrawal_allowed' => 1,
            'is_deposit_allowed' => 1,
            'trading_mode' => 1,
            'settle_cycle' => 2,
            'equity_ratio' => 2500,
            'gender' => 2,
            'is_gift_allowed' => 1,
            'remark' => '旧后台代理备注已迁移',
            'updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $agentId,
            'email' => 'legacy-agent-after@example.test',
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $agentId,
            'id_card_no' => 'NEW-ID-98727901',
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'legacy_agent_edit_save:' . $agentId)
            ->first();
        $this->assertNotNull($log, '旧代理编辑成功后必须写入 operation_logs 审计记录。');
        $this->assertStringContainsString('user_name:编辑前代理->编辑后代理', (string) $log->content);
        $this->assertStringContainsString('login.email:legacy-agent-before@example.test->legacy-agent-after@example.test', (string) $log->content);
        $this->assertStringContainsString('auth.id_card_no:changed', (string) $log->content);
    }

    public function test_legacy_agent_edit_save_rejects_duplicate_email_without_partial_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentId = 98727902;
        $duplicateId = 98727903;
        $levelId = $this->ensureAgentLevel(93, '旧代理编辑重复邮箱等级');
        $this->seedAgent($agentId, ['user_name' => '重复邮箱目标', 'level_id' => $levelId], ['email' => 'target-agent-email@example.test']);
        $this->seedAgent($duplicateId, ['user_name' => '重复邮箱占用者', 'level_id' => $levelId], ['email' => 'duplicate-agent-email@example.test']);

        $response = $this->withoutAdminMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/agents/agents_edit_save', $this->legacyPayload($agentId, [
                'username' => '不应写入的代理名',
                'useremail' => 'duplicate-agent-email@example.test',
            ]));

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'Existemail')
            ->assertJsonPath('col', 'useremail');

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $agentId,
            'user_name' => '重复邮箱目标',
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $agentId,
            'email' => 'target-agent-email@example.test',
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'legacy_agent_edit_save:' . $agentId)->count());
    }

    public function test_legacy_agent_edit_save_fails_closed_for_mt4_sensitive_change_without_writing(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentId = 98727904;
        $levelId = $this->ensureAgentLevel(94, '旧代理编辑敏感字段等级');
        $this->seedAgent($agentId, [
            'user_name' => '敏感变更前代理',
            'level_id' => $levelId,
            'mt4_group' => 'LEGACY-GROUP-A',
            'leverage' => 100,
        ]);

        $response = $this->withoutAdminMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/agents/agents_edit_save', $this->legacyPayload($agentId, [
                'username' => '不应写入敏感变更代理名',
                'cust_lvg' => 200,
            ], [
                'usergrpName' => 'LEGACY-GROUP-B',
            ]));

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'MT4SYNCUNSUPPORTED')
            ->assertJsonPath('col', 'mt4_group');

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $agentId,
            'user_name' => '敏感变更前代理',
            'mt4_group' => 'LEGACY-GROUP-A',
            'leverage' => 100,
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'legacy_agent_edit_save:' . $agentId)->count());
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
                'username' => 'legacy-agent-edit-super',
                'email' => 'legacy-agent-edit-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function ensureAgentLevel(int $levelCode, string $name): int
    {
        $now = time();
        DB::table('agent_levels')->updateOrInsert(
            ['level_code' => $levelCode],
            [
                'name' => $name,
                'max_commission' => 90,
                'min_commission' => 0,
                'user_commission' => 10,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('agent_levels')->where('level_code', $levelCode)->value('id');
    }

    /**
     * 创建旧代理编辑测试账号。
     *
     * @param int $userId 业务用户 ID。
     * @param array<string, mixed> $infoOverrides user_infos 覆盖字段。
     * @param array<string, mixed> $loginOverrides user_logins 覆盖字段。
     * @param array<string, mixed> $authOverrides user_auths 覆盖字段。
     * @return void
     */
    private function seedAgent(
        int $userId,
        array $infoOverrides = [],
        array $loginOverrides = [],
        array $authOverrides = []
    ): void {
        $now = time();
        DB::table('operation_logs')->where('target_user_id', $userId)->delete();
        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId(array_replace([
            'user_id' => $userId,
            'email' => 'legacy-agent-edit-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 1,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $loginOverrides));

        DB::table('user_infos')->insert(array_replace([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => '旧代理编辑测试用户',
            'phone' => '86-13900000000',
            'gender' => 1,
            'level_id' => 0,
            'group_id' => 0,
            'parent_id' => 0,
            'account_type' => 1,
            'family_tree' => (string) $userId,
            'leverage' => 100,
            'comm_rate' => 10,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'mt4_group' => 'LEGACY-GROUP-A',
            'trading_mode' => 0,
            'settle_method' => 1,
            'settle_cycle' => 1,
            'equity_ratio' => 0,
            'is_gift_allowed' => 0,
            'remark' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $infoOverrides));

        DB::table('user_auths')->insert(array_replace([
            'user_id' => $userId,
            'id_card_no' => 'OLD-ID-' . $userId,
            'bank_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $authOverrides));
    }

    /**
     * 构造旧 Blade 代理编辑表单。
     *
     * @param int $userId 被编辑代理业务用户 ID。
     * @param array<string, mixed> $dataOverrides data 嵌套字段覆盖值。
     * @param array<string, mixed> $rootOverrides 根级旧字段覆盖值。
     * @return array<string, mixed> 可直接提交给旧兼容路由的请求体。
     */
    private function legacyPayload(int $userId, array $dataOverrides = [], array $rootOverrides = []): array
    {
        return array_replace([
            'usergrpName' => 'LEGACY-GROUP-A',
            'useragtName' => '旧代理等级',
            'is_enc' => 0,
            'enc_look' => 0,
            'enable' => 1,
            'enablereadonly' => 0,
            'isoutmoney' => 0,
            'isallowmoney' => 0,
            'settlementmodel' => 1,
            'datausercycle' => 1,
            'sex' => 1,
            'data' => array_replace([
                'userId' => $userId,
                'username' => '旧代理编辑测试用户',
                'password' => '********',
                'userIdcardNo' => 'OLD-ID-' . $userId,
                'userphoneNo' => '13900000000',
                'useremail' => 'legacy-agent-edit-' . $userId . '@example.test',
                'usergrpId' => 0,
                'usertype' => 0,
                'userrights' => 0,
                'usercycle' => 1,
                'cust_lvg' => 100,
                'userparentId' => 0,
                'useragtId' => 0,
                'userrebate' => 10,
                'userremark' => '',
                'gift_allowed' => 0,
            ], $dataOverrides),
        ], $rootOverrides);
    }
}
