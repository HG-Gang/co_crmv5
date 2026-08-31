<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:51
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧后台代理 update 调试入口兼容闭环测试。
 *
 * 文件功能：
 * - 验证项目1 `AgentControllerV3@AgentUpdate` 只回显 `parent_id` 提交体并立即结束。
 * - 验证项目2不能把该半成品旧入口误转发为代理等级或代理层级写入。
 * - 验证旧入口即使接收到上级代理字段，也不会改写 user_infos 的 parent_id、level_id。
 *
 * 入参例子：
 * - POST /index/admin/agent/update
 * - parent_id[parent_id]=930101
 * - parent_id[agent_id]=930102
 *
 * 返回值：
 * - 成功时返回项目1 `print_r($request->parent_id)` 的纯文本兼容内容。
 * - 不返回伪造成功 JSON，避免旧 Blade 把未完成入口误判为真实保存成功。
 */
class AdminLegacyAgentUpdateDebugClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 本夹具代理用户名/标识前缀（legacy-agent-update-debug-）。断言与清理都按前缀圈定。
     * @var string
     */
    private const PREFIX = 'legacy-agent-update-debug-';

    /**
     * 旧 agent/update 只能回显 parent_id 数组，不能写入代理层级或等级。
     *
     * @return void
     */
    public function test_legacy_agent_update_echoes_parent_payload_without_mutating_agent(): void
    {
        $actor = $this->ensureSuperAdmin();
        $oldLevelId = $this->ensureAgentLevel(9301, self::PREFIX . 'old-level');
        $newLevelId = $this->ensureAgentLevel(9302, self::PREFIX . 'new-level');
        $oldParentId = 930001;
        $newParentId = 930002;
        $agentId = 930003;

        $this->createAgent($oldParentId, 0, $oldLevelId);
        $this->createAgent($newParentId, 0, $newLevelId);
        $this->createAgent($agentId, $oldParentId, $oldLevelId);

        $response = $this->legacyRequest($actor)
            ->post('/index/admin/agent/update', [
                'parent_id' => [
                    'parent_id' => $newParentId,
                    'agent_id' => $agentId,
                    'level_id' => $newLevelId,
                ],
            ]);

        $response->assertOk();
        $this->assertStringContainsString('[parent_id] => ' . $newParentId, $response->getContent());
        $this->assertStringContainsString('[agent_id] => ' . $agentId, $response->getContent());
        $this->assertStringContainsString('[level_id] => ' . $newLevelId, $response->getContent());

        $agent = DB::table('user_infos')->where('user_id', $agentId)->first();
        $this->assertSame($oldParentId, (int) $agent->parent_id);
        $this->assertSame($oldLevelId, (int) $agent->level_id);
    }

    /**
     * 创建绕过旧后台中间件后的测试请求对象。
     *
     * @param Admin $actor 当前登录后台管理员。
     * @return self 已绑定 admin guard 登录态的测试实例。
     */
    private function legacyRequest(Admin $actor): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin');
    }

    /**
     * 创建可通过 admin guard 识别的超级管理员。
     *
     * @return Admin 后台管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => self::PREFIX . 'super',
                'email' => self::PREFIX . 'super@example.test',
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
     * 准备代理等级夹具。
     *
     * @param int $levelCode 旧项目等级编码，用于保持测试数据唯一。
     * @param string $name 等级名称，用于定位测试夹具。
     * @return int agent_levels.id。
     */
    private function ensureAgentLevel(int $levelCode, string $name): int
    {
        $now = time();

        DB::table('agent_levels')->updateOrInsert(
            ['level_code' => $levelCode],
            [
                'name' => $name,
                'max_commission' => 90,
                'min_commission' => 10,
                'user_commission' => 5,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('agent_levels')->where('level_code', $levelCode)->value('id');
    }

    /**
     * 创建代理用户夹具。
     *
     * @param int $userId 业务用户 ID，对应 user_infos.user_id。
     * @param int $parentId 上级代理业务用户 ID，0 表示一级代理。
     * @param int $levelId agent_levels.id，用于验证该旧入口不会误改等级。
     * @return void
     */
    private function createAgent(int $userId, int $parentId, int $levelId): void
    {
        $now = time();

        DB::table('agent_descendants')
            ->where('agent_id', $userId)
            ->orWhere('descendant_id', $userId)
            ->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = (int) DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => self::PREFIX . $userId . '@example.test',
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
            'user_name' => self::PREFIX . $userId,
            'phone' => '13993' . substr((string) $userId, -6),
            'account_type' => 1,
            'parent_id' => $parentId,
            'level_id' => $levelId,
            'family_tree' => $parentId > 0 ? (string) $parentId : '',
            'comm_rate' => 1,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
