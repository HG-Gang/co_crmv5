<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证大代理（big_agents）创建与更新时 sub_agent_ids 子代理分配的
 *           完整性校验，包括启用状态、归属范围与原子性。
 *
 * 适用场景：后台 /api/admin/createBigAgent、/api/admin/updateBigAgent/{id}
 *           接口的子代理归属校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/createBigAgent：{username, email, password, sub_agent_ids}
 * - POST /api/admin/updateBigAgent/{id}：{username, email, sub_agent_ids, is_enabled}
 *
 * 返回值：
 * - 合法子代理分配返回 code=CREATED/UPDATED 并落库；
 * - 含非法子代理（客户、非顶层代理、禁用代理、越权代理、不存在 ID）时返回
 *   code=VALIDATION_FAILED 且整体不落库。
 *
 * 异常或失败场景：
 * - 子代理不是已启用的顶层代理、超出当前管理员数据范围或 ID 不存在时校验失败。
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

class AdminBigAgentSubAgentIntegrityClosureTest extends TestCase
{
    use DatabaseTransactions;

    // 创建大代理时仅接受已启用的顶层代理作为子代理，混合非法子代理时整体不落库。
    public function test_create_accepts_only_enabled_root_agents_and_rejects_mixed_invalid_assignments_atomically(): void
    {
        $actor = Admin::query()->findOrFail(1);
        $validRootId = $this->createBusinessUser(981001001, 1, 0, 1, 1);
        $customerId = $this->createBusinessUser(981001002, 2, 0, 1, 1);
        $childAgentId = $this->createBusinessUser(981001003, 1, $validRootId, 1, 1);
        $disabledAgentId = $this->createBusinessUser(981001004, 1, 0, 1, 0);
        $invalidId = 981001099;

        $client = $this->adminClient($actor);
        $validUsername = 'big-agent-integrity-valid-' . bin2hex(random_bytes(4));
        $validResponse = $client->postJson('/api/admin/createBigAgent', [
            'username' => $validUsername,
            'email' => $validUsername . '@example.test',
            'password' => 'big-agent-secret',
            'sub_agent_ids' => (string) $validRootId,
        ]);

        $validResponse->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertArrayNotHasKey('password', $validResponse->json('data'));
        $this->assertArrayNotHasKey('jwt_token_id', $validResponse->json('data'));
        $this->assertDatabaseHas('big_agents', [
            'username' => $validUsername,
            'sub_agent_ids' => (string) $validRootId,
        ]);

        foreach ([$customerId, $childAgentId, $disabledAgentId, $invalidId] as $invalidSubAgentId) {
            $username = 'big-agent-integrity-invalid-' . $invalidSubAgentId;
            DB::table('big_agents')->where('username', $username)->delete();
            $response = $client->postJson('/api/admin/createBigAgent', [
                'username' => $username,
                'email' => $username . '@example.test',
                'password' => 'big-agent-secret',
                'sub_agent_ids' => $validRootId . ',' . $invalidSubAgentId,
            ]);

            $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
            $this->assertDatabaseMissing('big_agents', ['username' => $username]);
        }
    }

    // 更新大代理时分配超出当前管理员数据范围的子代理应校验失败且不部分变更账号。
    public function test_update_rejects_out_of_scope_assignment_without_partially_changing_account(): void
    {
        $visibleRootId = $this->createBusinessUser(981002001, 1, 0, 1, 1);
        $hiddenRootId = $this->createBusinessUser(981002002, 1, 0, 1, 1);
        $actor = $this->createScopedAdmin($visibleRootId);
        $targetId = DB::table('big_agents')->insertGetId([
            'username' => 'big-agent-integrity-scope-target',
            'email' => 'big-agent-integrity-scope-target@example.test',
            'password' => Hash::make('big-agent-secret'),
            'sub_agent_ids' => (string) $visibleRootId,
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $response = $this->adminClient($actor)->postJson('/api/admin/updateBigAgent/' . $targetId, [
            'username' => 'big-agent-integrity-scope-changed',
            'email' => 'big-agent-integrity-scope-changed@example.test',
            'sub_agent_ids' => $visibleRootId . ',' . $hiddenRootId,
            'is_enabled' => 0,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertDatabaseHas('big_agents', [
            'id' => $targetId,
            'username' => 'big-agent-integrity-scope-target',
            'email' => 'big-agent-integrity-scope-target@example.test',
            'sub_agent_ids' => (string) $visibleRootId,
            'is_enabled' => 1,
        ]);
    }

    private function adminClient(Admin $admin)
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }

    private function createBusinessUser(
        int $userId,
        int $accountType,
        int $parentId,
        int $authStatus,
        int $isEnabled
    ): int {
        $now = time();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'big-agent-integrity-' . $userId . '@example.test',
            'password' => Hash::make('user-secret'),
            'account_type' => $accountType,
            'role_id' => 0,
            'is_enabled' => $isEnabled,
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
            'user_name' => 'integrity-user-' . $userId,
            'parent_id' => $parentId,
            'account_type' => $accountType,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : (string) $userId,
            'auth_status' => $authStatus,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $userId;
    }

    private function createScopedAdmin(int $visibleRootId): Admin
    {
        $now = time();
        DB::table('big_agents')->where('username', 'big-agent-integrity-scope-target')->delete();
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'big_agent_integrity_' . bin2hex(random_bytes(4)),
            'guard_type' => 'admin',
            'description' => '',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->insert([
            'role_id' => $roleId,
            'scope_type' => 'custom_agents',
            'agent_ids' => json_encode([$visibleRootId]),
            'user_ids' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $adminId = DB::table('admins')->insertGetId([
            'username' => 'big-agent-integrity-admin-' . bin2hex(random_bytes(4)),
            'email' => 'big-agent-integrity-admin-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => Hash::make('admin-secret'),
            'role_id' => (string) $roleId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }
}
