<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 03:49
 */

/**
 * AdminRightsSummaryModuleTest
 *
 * 文件功能：
 * - 验证后台权益汇总模块：页面注册、Blade 控件、API 权限中间件、当前范围的在线结算金额汇总与导出 CSV。
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
 * 后台权益汇总模块覆盖测试。
 *
 * 重点验证 MT4 权益快照、业务用户映射、权限路由和当前筛选 CSV 导出闭环。
 */
class AdminRightsSummaryModuleTest extends TestCase
{
    public function test_rights_summary_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_rights_summary'), 'admin_page_rights_summary page route is missing.');
    }

    public function test_rights_summary_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/rights-summary');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="rightsSummaryCards"', false);
        $response->assertSee('data-summary-field="online_settlement_deposit_amount"', false);
        $response->assertSee('data-summary-field="online_settlement_withdraw_amount"', false);
        $response->assertSee('data-summary-field="online_settlement_commission_amount"', false);
        $response->assertSee('data-summary-field="online_settlement_net_amount"', false);
        $response->assertSee('id="rightsSummarySearchForm"', false);
        $response->assertSee('id="rightsSummaryTable"', false);
        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="login"', false);
        $response->assertSee('id="exportRightsSummary"', false);
        $response->assertSee('data-permission="admin_rights_summary_export"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"rights-summary/index\"", false);
    }

    public function test_rights_summary_api_routes_have_permission_middleware(): void
    {
        foreach (['admin_api_rightsSummaryList', 'admin_api_exportRightsSummary'] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API route is missing.');
            $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_rights_summary_summary_includes_online_settlement_amounts_for_current_scope(): void
    {
        $includedUserId = 982713;
        $excludedUserId = 982714;
        $includedLogin = 882713;
        $excludedLogin = 882714;
        $userIds = [$includedUserId, $excludedUserId];
        $logins = [$includedLogin, $excludedLogin];
        $now = time();

        $this->cleanupRightsSummaryOnlineFixtures($userIds, $logins);

        try {
            $this->insertRightsSummaryUser($includedUserId, $includedLogin, 'Rights Online Included', 'online-scope', $now);
            $this->insertRightsSummaryUser($excludedUserId, $excludedLogin, 'Rights Online Excluded', 'offline-scope', $now);

            // 入金/出金/返佣均只统计已完成记录；未完成记录用于证明接口不会把待处理金额计入在线结算汇总。
            DB::table('deposit_records')->insert([
                [
                    'user_id' => $includedUserId,
                    'user_name' => 'Rights Online Included',
                    'mt4_ticket' => 0,
                    'amount' => 120.00,
                    'actual_amount' => 115.50,
                    'exchange_rate' => 1,
                    'channel_name' => 'online settlement',
                    'channel_order_no' => 'rights-online-dp-included',
                    'local_order_no' => 'rights-online-dp-included',
                    'status' => '02',
                    'remarks' => 'included paid deposit',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_id' => $includedUserId,
                    'user_name' => 'Rights Online Included',
                    'mt4_ticket' => 0,
                    'amount' => 999.00,
                    'actual_amount' => 999.00,
                    'exchange_rate' => 1,
                    'channel_name' => 'online settlement',
                    'channel_order_no' => 'rights-online-dp-pending',
                    'local_order_no' => 'rights-online-dp-pending',
                    'status' => '01',
                    'remarks' => 'pending deposit excluded',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_id' => $excludedUserId,
                    'user_name' => 'Rights Online Excluded',
                    'mt4_ticket' => 0,
                    'amount' => 888.00,
                    'actual_amount' => 888.00,
                    'exchange_rate' => 1,
                    'channel_name' => 'online settlement',
                    'channel_order_no' => 'rights-online-dp-excluded',
                    'local_order_no' => 'rights-online-dp-excluded',
                    'status' => '02',
                    'remarks' => 'outside filtered scope',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('withdraw_records')->insert([
                [
                    'user_id' => $includedUserId,
                    'user_name' => 'Rights Online Included',
                    'mt4_ticket' => '',
                    'apply_amount' => 45.00,
                    'actual_amount' => 40.25,
                    'status' => 2,
                    'local_order_no' => 'rights-online-wd-included',
                    'third_order_no' => 'rights-online-wd-included',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_id' => $includedUserId,
                    'user_name' => 'Rights Online Included',
                    'mt4_ticket' => '',
                    'apply_amount' => 333.00,
                    'actual_amount' => 333.00,
                    'status' => 0,
                    'local_order_no' => 'rights-online-wd-pending',
                    'third_order_no' => 'rights-online-wd-pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_id' => $excludedUserId,
                    'user_name' => 'Rights Online Excluded',
                    'mt4_ticket' => '',
                    'apply_amount' => 777.00,
                    'actual_amount' => 777.00,
                    'status' => 2,
                    'local_order_no' => 'rights-online-wd-excluded',
                    'third_order_no' => 'rights-online-wd-excluded',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('commission_records')->insert([
                [
                    'unique_id' => 'rights-online-cr-included',
                    'agent_id' => $includedUserId,
                    'parent_id' => 0,
                    'settle_status' => 2,
                    'commission_amount' => 18.00,
                    'real_amount' => 15.75,
                    'remarks' => 'included settled commission',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'unique_id' => 'rights-online-cr-pending',
                    'agent_id' => $includedUserId,
                    'parent_id' => 0,
                    'settle_status' => 1,
                    'commission_amount' => 444.00,
                    'real_amount' => 444.00,
                    'remarks' => 'pending commission excluded',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'unique_id' => 'rights-online-cr-excluded',
                    'agent_id' => $excludedUserId,
                    'parent_id' => 0,
                    'settle_status' => 2,
                    'commission_amount' => 666.00,
                    'real_amount' => 666.00,
                    'remarks' => 'outside filtered scope',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->postJson('/api/admin/rightsSummaryList', ['mt4_group' => 'online-scope', 'limit' => 10]);

            $response->assertOk();
            $response->assertJsonPath('data.summary.total_accounts', 1);
            $this->assertSame(115.50, (float) $response->json('data.summary.online_settlement_deposit_amount'));
            $this->assertSame(40.25, (float) $response->json('data.summary.online_settlement_withdraw_amount'));
            $this->assertSame(15.75, (float) $response->json('data.summary.online_settlement_commission_amount'));
            $this->assertSame(91.00, (float) $response->json('data.summary.online_settlement_net_amount'));
        } finally {
            $this->cleanupRightsSummaryOnlineFixtures($userIds, $logins);
        }
    }

    public function test_rights_summary_export_endpoint_returns_current_filter_csv(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $userId = 982612;
        $login = 882612;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Rights Export User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'mt4_code' => $login,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_users')->updateOrInsert(
            ['login' => $login],
            [
                'name' => 'Rights MT4 Name',
                'group' => 'demo-rights',
                'balance' => 1234.56,
                'equity' => 1200.25,
                'margin' => 88.8,
                'margin_free' => 1111.45,
                'leverage' => 200,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('rights_settlements')->updateOrInsert(
            ['user_id' => $userId],
            [
                'amount' => 77.88,
                'status' => 0,
                'remark' => 'rights csv export',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportRightsSummary', ['user_id' => $userId]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('rights_summary_export.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Rights Export User', $content);
        $this->assertStringContainsString('Rights MT4 Name', $content);
        $this->assertStringContainsString('1234.56', $content);
        $this->assertStringContainsString('77.88', $content);
    }

    /**
     * 写入权益汇总在线结算测试用户。
     *
     * @param int $userId 业务用户 ID，对应 user_infos.user_id。
     * @param int $login MT4 登录账号，对应 mt4_users.login 与 user_infos.mt4_code。
     * @param string $userName 用户名，用于响应内容可读性校验。
     * @param string $mt4Group MT4 分组，用于测试当前筛选范围只汇总命中的账号。
     * @param int $now 10 位时间戳，保持测试数据 created_at/updated_at 一致。
     * @return void
     */
    private function insertRightsSummaryUser(int $userId, int $login, string $userName, string $mt4Group, int $now): void
    {
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => $userName,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'mt4_code' => $login,
            'total_funds' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('mt4_users')->insert([
            'login' => $login,
            'name' => $userName . ' MT4',
            'group' => $mt4Group,
            'balance' => 1000.00,
            'equity' => 900.00,
            'margin' => 50.00,
            'margin_free' => 850.00,
            'leverage' => 200,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理权益汇总在线结算测试夹具。
     *
     * @param array<int, int> $userIds 本测试写入的业务用户 ID 列表。
     * @param array<int, int> $logins 本测试写入的 MT4 登录账号列表。
     * @return void
     */
    private function cleanupRightsSummaryOnlineFixtures(array $userIds, array $logins): void
    {
        DB::table('deposit_records')->whereIn('user_id', $userIds)->delete();
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('commission_records')->whereIn('agent_id', $userIds)->delete();
        DB::table('rights_settlements')->whereIn('user_id', $userIds)->delete();
        DB::table('mt4_users')->whereIn('login', $logins)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
    }
}
