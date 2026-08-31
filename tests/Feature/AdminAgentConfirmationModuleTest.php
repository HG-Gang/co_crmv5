<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 后台代理确认模块测试。
 *
 * 文件功能：
 * - 验证代理确认/拒绝接口（confirmAgent、rejectAgentConfirmation）注册了权限中间件。
 * - 验证确认通过更新 is_agent_confirmed 标志并写入 operation_logs 审计日志。
 * - 验证拒绝时重置标志、保存原因并写入审计日志。
 * - 验证前端按钮、权限迁移与 CrmUi 路由配置均已接线。
 *
 * 适用场景：
 * - 后台代理管理模块代理确认/拒绝流程的接口、审计与前端配置回归测试。
 *
 * 入参例子：
 * - POST /api/admin/confirmAgent
 *   {
 *     "agent_id": 985601
 *   }
 * - POST /api/admin/rejectAgentConfirmation
 *   {
 *     "agent_id": 985602,
 *     "reason": "Agent qualification documents are incomplete"
 *   }
 *
 * 方法功能：
 * - test_agent_confirmation_api_routes_have_permission_middleware：校验两个命名路由存在且挂载 check.permission:admin。
 * - test_confirm_agent_updates_real_confirmation_flag_and_writes_operation_log：确认代理并断言标志落库与审计日志内容。
 * - test_reject_agent_confirmation_resets_flag_saves_reason_and_writes_operation_log：拒绝代理并断言标志、原因与审计日志。
 * - test_agent_confirmation_frontend_configs_are_exposed：检查 blade、pages.js、CrmUi 暴露确认/拒绝按钮与路由。
 * - test_agent_confirmation_permission_migrations_declare_required_permissions：检查操作权限与跟进迁移声明所需权限。
 *
 * 返回值：
 * - 确认/拒绝成功返回 code=UPDATED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若接口未写审计日志、未更新标志或前端权限缺失，测试断言失败。
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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAgentConfirmationModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 校验确认/拒绝两个命名路由存在且挂载 check.permission:admin 权限中间件。
     *
     * @return void
     */
    public function test_agent_confirmation_api_routes_have_permission_middleware(): void
    {
        foreach ([
            'admin_api_confirmAgent',
            'admin_api_rejectAgentConfirmation',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API route is not registered.');
            $this->assertContains(
                'check.permission:admin',
                Route::getRoutes()->getByName($routeName)->gatherMiddleware()
            );
        }
    }

    /**
     * 确认代理通过：断言 is_agent_confirmed 落库为 1 且 operation_logs 写入审计记录。
     *
     * @return void
     */
    public function test_confirm_agent_updates_real_confirmation_flag_and_writes_operation_log(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();
        $agentUserId = 985601;

        $this->upsertAgentFixture($agentUserId, 0, 'Pending confirmation', $now);
        DB::table('operation_logs')->where('order_no', 'agent_confirmation:' . $agentUserId)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/confirmAgent', [
                'agent_id' => $agentUserId,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $agentUserId,
            'account_type' => 1,
            'is_agent_confirmed' => 1,
        ]);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'agent_confirmation:' . $agentUserId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'confirmAgent must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertSame($agentUserId, (int) $log->target_user_id);
        $this->assertSame(0, (int) $log->action_type);
        $this->assertNotSame('', (string) $log->ip);
        $this->assertStringContainsString('Confirm agent user_id:' . $agentUserId, $log->content);
        $this->assertStringContainsString('is_agent_confirmed:0->1', $log->content);
    }

    /**
     * 拒绝代理确认：断言标志重置为 0、拒绝原因写入 remark 且审计日志记录变更。
     *
     * @return void
     */
    public function test_reject_agent_confirmation_resets_flag_saves_reason_and_writes_operation_log(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();
        $agentUserId = 985602;
        $reason = 'Agent qualification documents are incomplete';

        $this->upsertAgentFixture($agentUserId, 1, '', $now);
        DB::table('operation_logs')->where('order_no', 'agent_confirmation:' . $agentUserId)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/rejectAgentConfirmation', [
                'agent_id' => $agentUserId,
                'reason' => $reason,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $agentUserId,
            'account_type' => 1,
            'is_agent_confirmed' => 0,
            'remark' => $reason,
        ]);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'agent_confirmation:' . $agentUserId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'rejectAgentConfirmation must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertSame($agentUserId, (int) $log->target_user_id);
        $this->assertSame(0, (int) $log->action_type);
        $this->assertNotSame('', (string) $log->ip);
        $this->assertStringContainsString('Reject agent confirmation user_id:' . $agentUserId, $log->content);
        $this->assertStringContainsString('is_agent_confirmed:1->0', $log->content);
        $this->assertStringContainsString('reason:' . $reason, $log->content);
    }

    /**
     * 检查 blade、pages.js、CrmUi PageController 暴露确认/拒绝按钮与对应路由。
     *
     * @return void
     */
    public function test_agent_confirmation_frontend_configs_are_exposed(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/agents/index.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        $this->assertStringContainsString('lay-event="confirmAgent"', $blade);
        $this->assertStringContainsString('data-permission="admin_agent_confirm"', $blade);
        $this->assertStringContainsString('lay-event="rejectAgentConfirmation"', $blade);
        $this->assertStringContainsString('data-permission="admin_agent_reject_confirmation"', $blade);
        $this->assertStringContainsString('/api/admin/confirmAgent', $layui);
        $this->assertStringContainsString('/api/admin/rejectAgentConfirmation', $layui);
        $this->assertStringContainsString("'route' => 'admin_api_confirmAgent'", $crmui);
        $this->assertStringContainsString("'route' => 'admin_api_rejectAgentConfirmation'", $crmui);
    }

    /**
     * 检查操作权限迁移与跟进迁移均声明确认/拒绝所需权限。
     *
     * @return void
     */
    public function test_agent_confirmation_permission_migrations_declare_required_permissions(): void
    {
        $operationMigration = file_get_contents(database_path('migrations/2026_06_07_000003_add_admin_agent_operation_permissions.php')) ?: '';
        $followUpPath = database_path('migrations/2026_07_07_000004_add_admin_agent_confirmation_permissions.php');

        foreach ([
            'admin_agent_confirm',
            'admin_api_confirmAgent',
            'admin_agent_reject_confirmation',
            'admin_api_rejectAgentConfirmation',
        ] as $expected) {
            $this->assertStringContainsString($expected, $operationMigration);
        }

        $this->assertFileExists($followUpPath, 'Existing databases need a follow-up migration for agent confirmation permissions.');

        $followUpMigration = file_get_contents($followUpPath) ?: '';
        foreach ([
            'admin_agent_confirm',
            'admin_api_confirmAgent',
            'admin_agent_reject_confirmation',
            'admin_api_rejectAgentConfirmation',
        ] as $expected) {
            $this->assertStringContainsString($expected, $followUpMigration);
        }
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'agent-confirmation-admin',
                'email' => 'agent-confirmation-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function upsertAgentFixture(int $userId, int $isAgentConfirmed, string $remark, int $now): void
    {
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'agent-confirmation-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 1,
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
            'user_name' => 'Confirmation Agent',
            'phone' => '1760000' . substr((string) $userId, -4),
            'account_type' => 1,
            'parent_id' => 0,
            'level_id' => 2,
            'comm_rate' => 0.2,
            'auth_status' => 1,
            'is_agent_confirmed' => $isAgentConfirmed,
            'remark' => $remark,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
