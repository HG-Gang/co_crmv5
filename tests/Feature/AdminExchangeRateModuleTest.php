<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 02:27
 */

/**
 * AdminExchangeRateModuleTest
 *
 * 文件功能：
 * - 验证后台汇率配置模块：页面注册、Blade 控件、API 权限中间件、system_configs 键口径、操作日志记录前后值与权限迁移。
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
 * 后台汇率配置模块覆盖测试。
 *
 * 功能逻辑说明：
 * - 旧项目汇率配置用于维护入金汇率与出金汇率，新项目第一阶段统一落到 system_configs 表。
 * - 本测试不访问真实数据库，只验证 Blade 页面、路由、接口鉴权、控制器源码和权限迁移契约。
 * - 这样在 MySQL 3307 暂不可用时，仍然可以先锁定模块结构，等数据库恢复后再执行迁移写入复核。
 */
class AdminExchangeRateModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 汇率配置页面必须注册为独立 Blade 路由。
     *
     * @return void
     */
    public function test_exchange_rate_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_exchange_rates'), 'admin_page_exchange_rates 页面路由未注册。');
    }

    /**
     * 汇率配置页面必须包含入金汇率、出金汇率表单控件和页面脚本。
     *
     * @return void
     */
    public function test_exchange_rate_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/exchange-rates');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="exchangeRateForm"', false);
        $response->assertSee('name="sys_deposit_rate"', false);
        $response->assertSee('name="sys_draw_rate"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"exchange-rates/index\"", false);
    }

    /**
     * 汇率配置 API 必须注册并挂载后台权限中间件。
     *
     * @return void
     */
    public function test_exchange_rate_api_routes_have_permission_middleware(): void
    {
        foreach ([
            'admin_api_exchangeRateInfo',
            'admin_api_updateExchangeRate',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API 路由未注册。');
            $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    /**
     * 控制器必须使用 system_configs key/value 模式保存两个汇率配置。
     *
     * @return void
     */
    public function test_exchange_rate_controller_uses_system_config_keys(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/ExchangeRateController.php');

        $this->assertFileExists($controllerPath, 'ExchangeRateController 控制器不存在。');
        $source = file_get_contents($controllerPath);

        $this->assertStringContainsString('SystemConfig::updateOrCreate', $source);
        $this->assertStringContainsString('sys_deposit_rate', $source);
        $this->assertStringContainsString('sys_draw_rate', $source);
        $this->assertStringContainsString('exchange_rate', $source);
    }

    /**
     * 权限迁移必须声明页面权限和两个 API 权限，保证权限数据来自 permissions 表。
     *
     * @return void
     */
    public function test_exchange_rate_update_writes_operation_log_with_before_and_after_values(): void
    {
        $admin = Admin::query()->first() ?: Admin::query()->create([
            'username' => 'exchange-rate-audit-admin',
            'email' => 'exchange-rate-audit-admin@example.test',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);

        DB::table('system_configs')->updateOrInsert(
            ['key' => 'sys_deposit_rate'],
            [
                'value' => '6.8',
                'group' => 'exchange_rate',
                'description' => 'Deposit exchange rate',
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]
        );
        DB::table('system_configs')->updateOrInsert(
            ['key' => 'sys_draw_rate'],
            [
                'value' => '6.7',
                'group' => 'exchange_rate',
                'description' => 'Withdraw exchange rate',
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]
        );

        DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'exchange_rate')
            ->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateExchangeRate', [
                'sys_deposit_rate' => '7.1234567800',
                'sys_draw_rate' => '7.01000000',
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::UPDATED);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'exchange_rate')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'updateExchangeRate must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertSame(0, (int) $log->action_type);
        $this->assertNotSame('', (string) $log->ip);
        $this->assertStringContainsString('sys_deposit_rate:6.8->7.12345678', $log->content);
        $this->assertStringContainsString('sys_draw_rate:6.7->7.01', $log->content);
    }

    public function test_exchange_rate_permission_migration_declares_required_permissions(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000008_add_admin_exchange_rate_permissions.php');

        $this->assertFileExists($migrationPath, '汇率配置权限迁移文件不存在。');
        $source = file_get_contents($migrationPath);

        foreach ([
            'admin_exchange_rates',
            'admin_exchange_rate_info',
            'admin_exchange_rate_update',
            'admin_api_exchangeRateInfo',
            'admin_api_updateExchangeRate',
            '/admin/exchange-rates',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }
}
