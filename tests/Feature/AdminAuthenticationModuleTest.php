<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 03:36
 */

/**
 * AdminAuthenticationModuleTest
 *
 * 文件功能：
 * - 验证后台实名认证审核模块契约：页面注册、Blade 控件、API 权限中间件、真实审核字段与操作日志写入。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
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

/**
 * 后台实名认证审核模块契约测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `AuthenticationController` 同时提供待审核列表、已审核列表、认证详情和身份证/银行卡审核动作。
 * - 新项目当前先补齐后台 Blade 页面、待审列表 API、已审列表 API 和复用审核 API 的权限字典。
 * - 测试不依赖 MySQL 3307 真实连接，只约束页面、路由、中间件、控制器源码和权限迁移文件，避免数据库不可用时阻塞迁移开发。
 */
class AdminAuthenticationModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 认证审核后台页面必须注册为独立 Blade 路由。
     *
     * @return void
     */
    public function test_authentication_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_authentications'), 'admin_page_authentications 页面路由未注册。');
    }

    /**
     * 认证审核页面必须包含待审表格、已审表格、审核弹窗和页面脚本。
     *
     * @return void
     */
    public function test_authentication_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/authentications');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="authPendingSearchForm"', false);
        $response->assertSee('id="authPendingTable"', false);
        $response->assertSee('id="authCertifiedTable"', false);
        $response->assertSee('id="authReviewForm"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"authentications/index\"", false);
    }

    /**
     * 认证审核模块 API 必须挂载后台 JWT、SSO 和权限中间件。
     *
     * @return void
     */
    public function test_authentication_api_routes_have_permission_middleware(): void
    {
        foreach ([
            'admin_api_authPendingList',
            'admin_api_authCertifiedList',
            'admin_api_reviewAuth',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API 路由未注册。');
            $this->assertContains(
                'check.permission:admin',
                Route::getRoutes()->getByName($routeName)->gatherMiddleware()
            );
        }
    }

    /**
     * 认证审核控制器必须读取真实用户资料和认证资料表，并套用后台数据范围。
     *
     * @return void
     */
    public function test_authentication_controller_uses_real_tables_and_data_scope(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/AuthenticationController.php');

        $this->assertFileExists($controllerPath, 'AuthenticationController 控制器不存在。');
        $source = file_get_contents($controllerPath);

        $this->assertStringContainsString('UserAuth::query()', $source);
        $this->assertStringContainsString('user_infos', $source);
        $this->assertStringContainsString('id_card_status', $source);
        $this->assertStringContainsString('bank_status', $source);
        $this->assertStringContainsString('AdminDataScopeService', $source);
        $this->assertStringContainsString('applyDataScope', $source);
    }

    /**
     * 权限迁移必须声明页面、待审列表、已审列表和审核动作权限。
     *
     * @return void
     */
    /**
     * reviewAuth must update real authentication fields and write an operation log.
     *
     * @return void
     */
    public function test_review_auth_updates_real_auth_fields_and_writes_operation_log(): void
    {
        $now = time();
        $userId = 984101;
        $reason = 'ID card image is unclear';

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'auth-audit-super-admin',
                'email' => 'auth-audit-super-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $admin = Admin::query()->findOrFail(1);

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $userId,
                'user_name' => 'Auth Audit User',
                'auth_status' => 0,
                'account_type' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('user_auths')->updateOrInsert(
            ['user_id' => $userId],
            [
                'bank_no' => '6222020202020202',
                'bank_name' => 'Feature Bank',
                'bank_addr' => 'Feature Branch',
                'bank_status' => 1,
                'bank_remarks' => '',
                'id_card_no' => '110101199003070019',
                'id_card_status' => 1,
                'id_card_front' => 'id-front.jpg',
                'id_card_back' => 'id-back.jpg',
                'id_card_remarks' => '',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('operation_logs')
            ->where('order_no', 'auth_review:' . $userId)
            ->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/reviewAuth', [
                'user_id' => $userId,
                'status' => 2,
                'reason' => $reason,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 4,
            'bank_status' => 4,
            'id_card_remarks' => $reason,
            'bank_remarks' => $reason,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'auth_status' => 2,
        ]);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'auth_review:' . $userId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'reviewAuth must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertSame($userId, (int) $log->target_user_id);
        $this->assertSame(0, (int) $log->action_type);
        $this->assertNotSame('', (string) $log->ip);
        $this->assertStringContainsString('Review auth user_id:' . $userId, $log->content);
        $this->assertStringContainsString('status:2', $log->content);
        $this->assertStringContainsString('id_card_status:1->4', $log->content);
        $this->assertStringContainsString('bank_status:1->4', $log->content);
        $this->assertStringContainsString('reason:' . $reason, $log->content);
    }

    public function test_authentication_permission_migration_declares_required_permissions(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000012_add_admin_authentication_permissions.php');

        $this->assertFileExists($migrationPath, '认证审核权限迁移文件不存在。');
        $source = file_get_contents($migrationPath);

        foreach ([
            'admin_authentications',
            'admin_auth_pending_list',
            'admin_auth_certified_list',
            'admin_user_review_auth',
            'admin_api_authPendingList',
            'admin_api_authCertifiedList',
            'admin_api_reviewAuth',
            '/admin/authentications',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }
}
