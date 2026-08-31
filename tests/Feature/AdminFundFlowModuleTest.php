<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:48
 */

/**
 * AdminFundFlowModuleTest
 *
 * 文件功能：
 * - 验证后台资金流水模块：出金流水与未入金流水页面/路由/权限、CSV 导出，以及从未成功入金用户必须出现在未入金流水的跟进列表（Layui 与 CrmUI 双端）。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台资金流水模块覆盖测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `WithdrawFlowController` 提供出金流水查询与导出，核心来源是 MT4 出金类交易。
 * - 旧项目 `UnDepositAmountController` 提供未入金流水查询，核心来源是入金记录中待支付且未作废的数据。
 * - 新项目当前真实 DB 中 `mt4_trades`、`deposit_records`、`withdraw_records` 都是空表，本测试不伪造已有流水样本。
 * - 本轮先约束 Blade 页面、只读列表 API 和权限中间件，导出和 MT4 深层口径后续继续迁移。
 */
class AdminFundFlowModuleTest extends TestCase
{
    /**
     * 出金流水和未入金流水页面必须注册为独立 Blade 路由。
     *
     * @return void
     */
    public function test_fund_flow_pages_are_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_withdraw_flows'), 'admin_page_withdraw_flows 页面路由未注册');
        $this->assertTrue(Route::has('admin_page_undeposit_flows'), 'admin_page_undeposit_flows 页面路由未注册');
    }

    /**
     * 出金流水页面必须包含筛选表单、列表表格和页面脚本。
     *
     * @return void
     */
    public function test_withdraw_flow_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/withdraw-flows');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="withdrawFlowTable"', false);
        $response->assertSee('id="withdrawFlowSearchForm"', false);
        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="ticket"', false);
        $response->assertSee('id="exportWithdrawFlows"', false);
        $response->assertSee('data-permission="admin_withdraw_flow_export"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"withdraw-flows/index\"", false);
    }

    /**
     * 未入金流水页面必须包含筛选表单、列表表格和页面脚本。
     *
     * @return void
     */
    public function test_undeposit_flow_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/undeposit-flows');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="undepositFlowTable"', false);
        $response->assertSee('id="undepositFlowSearchForm"', false);
        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="local_order_no"', false);
        $response->assertSee('id="exportUndepositFlows"', false);
        $response->assertSee('data-permission="admin_undeposit_flow_export"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"undeposit-flows/index\"", false);
    }

    /**
     * 两个资金流水 API 必须注册并挂载后台接口鉴权中间件。
     *
     * @return void
     */
    public function test_fund_flow_api_routes_are_registered_with_permission_middleware(): void
    {
        foreach ([
            'admin_api_depositFlowList',
            'admin_api_exportDepositFlows',
            'admin_api_withdrawFlowList',
            'admin_api_exportWithdrawFlows',
            'admin_api_undepositFlowList',
            'admin_api_exportUndepositFlows',
            'admin_api_neverDepositUserList',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API 路由未注册');
            $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    /**
     * 出金流水和未入金流水导出接口必须返回当前筛选结果 CSV。
     *
     * @return void
     */
    public function test_fund_flow_export_endpoints_return_csv_downloads(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $userId = 982501;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Fund Flow Export User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990501],
            [
                'login' => $userId,
                'symbol' => '',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => -66.66,
                'open_time' => $now - 3600,
                'close_time' => $now,
                'comment' => 'WBIN-990501-fund-flow-export',
                'modify_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990502],
            [
                'login' => $userId,
                'symbol' => 'BALANCE',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 77.77,
                'open_time' => $now - 3600,
                'close_time' => $now,
                'comment' => 'DBUN-990502-fund-flow-export',
                'modify_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('deposit_records')->updateOrInsert(
            ['local_order_no' => 'UNDEPOSIT-EXPORT-ORDER'],
            [
                'user_id' => $userId,
                'user_name' => 'Fund Flow Export User',
                'mt4_ticket' => 0,
                'amount' => 88.88,
                'actual_amount' => 0,
                'exchange_rate' => 0,
                'channel_name' => 'CSV Channel',
                'channel_order_no' => 'CHANNEL-EXPORT-ORDER',
                'status' => '01',
                'payment_time' => null,
                'remarks' => 'undeposit csv export',
                'created_by' => '',
                'updated_by' => '',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        foreach ([
            '/api/admin/exportDepositFlows' => [['user_id' => $userId], '990502', 'deposit_flows_export.csv'],
            '/api/admin/exportWithdrawFlows' => [['user_id' => $userId], '990501', 'withdraw_flows_export.csv'],
            '/api/admin/exportUndepositFlows' => [['user_id' => $userId], 'UNDEPOSIT-EXPORT-ORDER', 'undeposit_flows_export.csv'],
        ] as $uri => [$payload, $needle, $fileName]) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($admin, 'admin')
                ->post($uri, $payload);

            $response->assertOk();
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
            $this->assertStringContainsString($fileName, (string) $response->headers->get('content-disposition'));
            $this->assertStringContainsString($needle, $response->streamedContent());
        }
    }

    /**
     * 从未成功入金用户必须接入未入金流水页面的第二个列表。
     *
     * @return void
     */
    public function test_never_deposit_users_are_exposed_on_undeposit_flow_page(): void
    {
        $response = $this->get('/admin/undeposit-flows');

        $response->assertOk();
        $response->assertSee('id="neverDepositUserTable"', false);
        $response->assertSee('id="neverDepositUserSearchForm"', false);
        $response->assertSee('id="reloadNeverDepositUsers"', false);

        $pages = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';

        $this->assertStringContainsString('/api/admin/neverDepositUserList', $pages);
        $this->assertStringContainsString('neverDepositUserTable', $pages);
        $this->assertStringContainsString('searchNeverDepositUsers', $pages);
    }

    public function test_layui_admin_exposes_never_deposit_users_as_follow_up_module(): void
    {
        $response = $this->get('/admin/undeposit-flows')->assertOk();
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';

        foreach (['user_id', 'user_name', 'start_date', 'end_date', 'min_days'] as $filterName) {
            $response->assertSee('name="' . $filterName . '"', false);
        }

        foreach (['/api/admin/neverDepositUserList', 'neverDepositUserTable', 'searchNeverDepositUsers'] as $needle) {
            $this->assertStringContainsString($needle, $script);
        }
    }

    public function test_crmui_admin_exposes_never_deposit_users_as_follow_up_module(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $zh = file_get_contents(resource_path('lang/zh-CN/crmui.php')) ?: '';
        $en = file_get_contents(resource_path('lang/en/crmui.php')) ?: '';

        $this->assertStringContainsString("'never-deposit-users' =>", $controller);
        $this->assertStringContainsString("'key' => 'never_deposit_users'", $controller);
        $this->assertStringContainsString("'api' => 'admin_api_neverDepositUserList'", $controller);
        $this->assertStringContainsString("'filters' => ['user_id', 'user_name', 'start_date', 'end_date', 'min_days']", $controller);
        $this->assertStringContainsString("'columns' => ['user_id', 'user_name', 'phone', 'email', 'parent_id', 'register_date', 'never_deposit_days']", $controller);
        $this->assertStringContainsString("'never_deposit_users' => ['title'", $zh);
        $this->assertStringContainsString("'never_deposit_users' => ['title'", $en);
    }
}
