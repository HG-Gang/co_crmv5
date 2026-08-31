<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证大代理（big_agents）创建与更新接口能正确持久化 email 与
 *           sub_agent_ids 字段，兼容 legacy agents 数组入参，并核对前端字段暴露。
 *
 * 适用场景：后台 /api/admin/createBigAgent、/api/admin/updateBigAgent/{id}
 *           接口的写入与兼容性回归测试，以及 CrmUI/naive/layui 前端字段对齐检查。
 *
 * 入参例子：
 * - POST /api/admin/createBigAgent：{username, email, password, sub_agent_ids, is_enabled}
 * - POST /api/admin/createBigAgent：{username, email, password, agents: [id1, id2]}
 *
 * 返回值：
 * - 写入成功返回 code=CREATED/UPDATED，数据表字段与入参一致；
 * - agents 数组会被归一化为逗号分隔的 sub_agent_ids；
 * - 含非法 agents 元素时返回 code=VALIDATION_FAILED 且不落库。
 *
 * 异常或失败场景：
 * - agents 中出现非用户 ID 字符串（如 'not-a-user-id'）时校验失败。
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

class AdminBigAgentWriteClosureTest extends TestCase
{
    use DatabaseTransactions;

    // 创建与更新大代理应正确持久化 email、sub_agent_ids，更新后旧值被覆盖。
    public function test_create_and_update_big_agent_persist_email_and_sub_agent_ids(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $rootIds = [
            $this->createRootAgent(),
            $this->createRootAgent(),
            $this->createRootAgent(),
        ];
        $username = 'scope-big-agent-' . substr(sha1((string) microtime(true)), 0, 12);
        $email = $username . '@example.test';

        $client = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');

        $created = $client->postJson('/api/admin/createBigAgent', [
            'username' => $username,
            'email' => $email,
            'password' => 'BigAgent-write-password',
            'sub_agent_ids' => $rootIds[0] . ',' . $rootIds[1],
            'is_enabled' => 1,
        ]);

        $created->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $id = (int) $created->json('data.id');
        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('big_agents', [
            'id' => $id,
            'email' => $email,
            'sub_agent_ids' => $rootIds[0] . ',' . $rootIds[1],
        ]);

        $updatedEmail = 'updated-' . $email;
        $client->postJson('/api/admin/updateBigAgent/' . $id, [
            'username' => $username,
            'email' => $updatedEmail,
            'sub_agent_ids' => (string) $rootIds[2],
            'is_enabled' => 0,
        ])->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('big_agents', [
            'id' => $id,
            'email' => $updatedEmail,
            'sub_agent_ids' => (string) $rootIds[2],
            'is_enabled' => 0,
        ]);
    }

    // legacy agents 数组入参应归一化为 sub_agent_ids，非法元素整体拒绝。
    public function test_legacy_agents_array_is_normalized_and_invalid_ids_are_rejected(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $rootIds = [$this->createRootAgent(), $this->createRootAgent()];
        $username = 'legacy-big-agent-' . substr(sha1((string) microtime(true)), 0, 12);
        $email = $username . '@example.test';

        $client = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');

        $client->postJson('/api/admin/createBigAgent', [
            'username' => $username,
            'email' => $email,
            'password' => 'BigAgent-write-password',
            'agents' => [(string) $rootIds[0], (string) $rootIds[1]],
        ])->assertOk()->assertJsonPath('code', ResponseCode::CREATED);

        $this->assertDatabaseHas('big_agents', [
            'username' => $username,
            'sub_agent_ids' => $rootIds[0] . ',' . $rootIds[1],
        ]);

        $invalidUsername = $username . '-invalid';
        $client->postJson('/api/admin/createBigAgent', [
            'username' => $invalidUsername,
            'email' => 'invalid-' . $email,
            'password' => 'BigAgent-write-password',
            'agents' => [(string) $rootIds[0], 'not-a-user-id'],
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('big_agents', ['username' => $invalidUsername]);
    }

    // 所有后台前端（PageController/pages.js/big-agents blade）必须暴露 email 与 sub_agent_ids 字段。
    public function test_all_admin_frontends_expose_required_big_agent_fields(): void
    {
        $pageController = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $naive = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $layui = file_get_contents(resource_path('admin/layui/big-agents/index.blade.php')) ?: '';

        foreach (['email', 'sub_agent_ids'] as $field) {
            $this->assertStringContainsString("'" . $field . "'", $pageController);
            $this->assertStringContainsString("'" . $field . "'", $naive);
            $this->assertStringContainsString('name="' . $field . '"', $layui);
        }
    }

    private function createRootAgent(): int
    {
        do {
            $userId = random_int(980000000, 989999999);
        } while (DB::table('user_infos')->where('user_id', $userId)->exists());

        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'big-agent-write-' . $userId . '@example.test',
            'password' => Hash::make('user-secret'),
            'account_type' => 1,
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
            'user_name' => 'big-agent-write-' . $userId,
            'parent_id' => 0,
            'account_type' => 1,
            'family_tree' => (string) $userId,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $userId;
    }
}
