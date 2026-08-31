<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 01:06
 */

/**
 * AdminLegacyPageRenderClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台页面渲染闭环：目标视图正确渲染、页面要求登录、出金申请页保持默认全部状态与 layui 契约、未知页面保持 404。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台遗留 GET 页面入口渲染闭环测试。
 *
 * 文件目的：
 * - 旧后台全部 `index/admin/*` 页面型路由在新项目统一经
 *   `LegacyAdminController@handle` -> `renderLegacyPage` 渲染现代
 *   `admin_layui::<模块>.index`（或 profile/dashboard 等专用模板）。
 * - 逐条锁定旧页面路由的渲染目标视图，防止迁移后页面被 410/404 或错页覆盖。
 * - 页面均受 `legacy.admin.auth` 保护：匿名请求必须 401，登录管理员必须 200。
 */
class AdminLegacyPageRenderClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 旧后台页面路由 -> 期望渲染的现代视图。
     *
     * 说明：pageModuleFor 的模块名与 admin_layui 目录一一对应；
     * userinfo/userpwd/withdraw/* 与 login/captcha 走专用模板分支。
     */
    public static function legacyPageDataProvider(): array
    {
        $pages = [
            'index/admin/agent' => 'admin_layui::agents.index',
            'index/admin/agent/edit/{agentId}' => 'admin_layui::agents.index',
            'index/admin/amount/batch_operation' => 'admin_layui::deposit-imports.index',
            'index/admin/amount/batch_operation_withdraw' => 'admin_layui::withdraw-imports.index',
            'index/admin/amount/deposit_flow' => 'admin_layui::deposits.index',
            'index/admin/amount/deposit_import_index' => 'admin_layui::deposit-imports.index',
            'index/admin/amount/orderId_detail/123' => 'admin_layui::withdrawals.detail',
            'index/admin/amount/rights_summary' => 'admin_layui::rights-summary.index',
            'index/admin/amount/rightsSummarySearchDetail/123/1/1' => 'admin_layui::rights-summary.index',
            'index/admin/amount/show_channel_browse' => 'admin_layui::channels.index',
            'index/admin/amount/undeposit_flow' => 'admin_layui::undeposit-flows.index',
            'index/admin/amount/whpj_rate' => 'admin_layui::exchange-rates.index',
            'index/admin/amount/withdraw_apply' => 'admin_layui::withdrawals.index',
            'index/admin/amount/withdraw_flow' => 'admin_layui::withdraw-flows.index',
            'index/admin/amount/withdraw_import_index' => 'admin_layui::withdraw-imports.index',
            'index/admin/auth/user_certified' => 'admin_layui::authentications.index',
            'index/admin/auth/user_certified_detail/123' => 'admin_layui::authentications.detail',
            'index/admin/auth/user_examine' => 'admin_layui::authentications.index',
            'index/admin/auth/user_examine/detail/show/123' => 'admin_layui::authentications.detail',
            'index/admin/auth/user_voucher/detail/5/123' => 'admin_layui::vouchers.detail',
            'index/admin/auth/voucher_info_browse' => 'admin_layui::vouchers.index',
            'index/admin/bigAgents/add' => 'admin_layui::big-agents.index',
            'index/admin/bigAgents/show' => 'admin_layui::big-agents.index',
            'index/admin/big_agents_list' => 'admin_layui::big-agents.index',
            'index/admin/cancel/user_list' => 'admin_layui::cancel-applies.index',
            'index/admin/credit/credit_import_index' => 'admin_layui::credit-imports.index',
            'index/admin/cust/add' => 'admin_layui::users.customer-add',
            'index/admin/cust/change_list' => 'admin_layui::users.index',
            'index/admin/cust/cust_detail/{customerId}' => 'admin_layui::users.customer-detail',
            'index/admin/cust/list' => 'admin_layui::users.index',
            'index/admin/customer/{agentId}' => 'admin_layui::users.direct-customers',
            'index/admin/fengXian/Ipaddress_list' => 'admin_layui::risk.index',
            'index/admin/fengXian/position_list' => 'admin_layui::risk.index',
            'index/admin/fengXian/profit_list' => 'admin_layui::risk.index',
            'index/admin/gift/send_gift_browse' => 'admin_layui::gifts.index',
            'index/admin/gift/shipment_list_browse' => 'admin_layui::gifts.index',
            'index/admin/group/add' => 'admin_layui::group-configs.index',
            'index/admin/group/user_group_add' => 'admin_layui::group-configs.index',
            'index/admin/group/user_group_browse' => 'admin_layui::group-configs.index',
            'index/admin/group/user_group_edit/{userGroupId}' => 'admin_layui::group-configs.index',
            'index/admin/index' => 'admin_layui::dashboard.index',
            'index/admin/news/news_add_browse' => 'admin_layui::news.index',
            'index/admin/news/news_edit/5' => 'admin_layui::news.index',
            'index/admin/news/news_list_browse' => 'admin_layui::news.index',
            'index/admin/online' => 'admin_layui::online-users.index',
            'index/admin/order/close_list' => 'admin_layui::trades.index',
            'index/admin/order/open_list' => 'admin_layui::trades.index',
            'index/admin/order/position_summary_list' => 'admin_layui::position-summary.index',
            'index/admin/order/production_list' => 'admin_layui::productions.index',
            'index/admin/order/real_commission_list' => 'admin_layui::realtime-commissions.index',
            'index/admin/order/whs_exp_zero_list' => 'admin_layui::whs-exp-zero.index',
            'index/admin/role/add' => 'admin_layui::roles.index',
            'index/admin/role/edit/1' => 'admin_layui::roles.index',
            'index/admin/userinfo' => 'admin_layui::profile.edit',
            'index/admin/userpwd' => 'admin_layui::profile.change-password',
            'index/admin/welcome' => 'admin_layui::dashboard.index',
        ];

        $cases = [];
        foreach ($pages as $uri => $view) {
            $cases[str_replace('/', '_', $uri)] = [$uri, $view];
        }

        return $cases;
    }

    /**
     * 登录管理员请求旧后台页面必须返回 200 且渲染目标现代视图。
     *
     * @dataProvider legacyPageDataProvider
     */
    public function test_legacy_admin_page_renders_target_view(string $uri, string $expectedView): void
    {
        $admin = Admin::query()->findOrFail(1);
        if (strpos($uri, '{agentId}') !== false) {
            $uri = str_replace('{agentId}', (string) $this->createAgentFixture(), $uri);
        }
        if (strpos($uri, '{customerId}') !== false) {
            $uri = str_replace('{customerId}', (string) $this->createCustomerFixture(), $uri);
        }
        if (strpos($uri, '{userGroupId}') !== false) {
            $uri = str_replace('{userGroupId}', (string) $this->createUserGroupFixture(), $uri);
        }
        if (str_contains($uri, 'index/admin/auth/user_certified_detail/')) {
            $uid = 984205;
            $this->createAuthenticationFixture($uid);
            $uri = 'index/admin/auth/user_certified_detail/' . $uid;
        }
        if (str_contains($uri, 'index/admin/auth/user_examine/detail/')) {
            $uid = 984206;
            $this->createAuthenticationFixture($uid);
            $uri = 'index/admin/auth/user_examine/detail/show/' . $uid;
        }
        if (str_contains($uri, 'index/admin/auth/user_voucher/detail/')) {
            [$voucherId, $uid] = $this->createVoucherFixture();
            $uri = 'index/admin/auth/user_voucher/detail/' . $voucherId . '/' . $uid;
        }
        if (str_contains($uri, 'index/admin/amount/orderId_detail/')) {
            $uri = 'index/admin/amount/orderId_detail/' . $this->createWithdrawFixture();
        }
        if (strpos($uri, 'index/admin/news/news_edit/') === 0) {
            $uri = 'index/admin/news/news_edit/' . $this->createNewsFixture();
        }

        $this->actingAs($admin, 'admin')
            ->get('/' . $uri)
            ->assertOk()
            ->assertViewIs($expectedView);
    }

    /**
     * 未登录管理员访问旧后台页面必须被 legacy.admin.auth 拦截（401 + 业务码 AUTH_FAILED）。
     */
    public function test_legacy_admin_pages_require_authentication(): void
    {
        foreach ([
            'index/admin/amount/deposit_flow',
            'index/admin/amount/withdraw_apply',
            'index/admin/news/news_list_browse',
            'index/admin/order/close_list',
        ] as $uri) {
            $this->getJson('/' . $uri)
                ->assertStatus(401)
                ->assertJsonPath('code', ResponseCode::AUTH_FAILED);
        }
    }

    public function test_legacy_withdraw_apply_page_keeps_the_default_all_status_and_layui_contract(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $response = $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/withdraw_apply')
            ->assertOk()
            ->assertViewIs('admin_layui::withdrawals.index')
            ->assertSee('id="withdrawSearchForm"', false)
            ->assertSee('name="local_order_no"', false)
            ->assertSee('name="mt4_ticket"', false)
            ->assertSee('name="user_id"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="start_date"', false)
            ->assertSee('name="end_date"', false)
            ->assertSee('data-layui-page="withdrawals/index"', false);

        foreach (['0', '1', '2', '3'] as $status) {
            $this->assertStringNotContainsString(
                'option value="' . $status . '" selected',
                $response->getContent(),
                '旧 withdraw_apply 页面应默认展示全部状态。'
            );
        }
    }

    /**
     * 登录管理员访问不存在的旧后台页面仍保持路由 404（不吞错）。
     */
    public function test_legacy_admin_unknown_page_stays_404(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/definitely/not/exist')
            ->assertNotFound();
    }

    private function createAgentFixture(): int
    {
        do {
            $userId = random_int(1200000000, 1900000000);
        } while (DB::table('user_logins')->where('user_id', $userId)->exists()
            || DB::table('user_infos')->where('user_id', $userId)->exists());

        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-page-agent-' . $userId . '@example.test',
            'password' => Hash::make('LegacyPageA123'),
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
            'user_name' => 'Legacy page render agent',
            'account_type' => 1,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $userId;
    }

    private function createNewsFixture(): int
    {
        $now = time();

        return (int) DB::table('news')->insertGetId([
            'title' => 'Legacy page render news ' . $now,
            'content' => 'Legacy page render news content',
            'image' => '',
            'author_id' => 1,
            'author_name' => 'Page Render Admin',
            'is_published' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function createCustomerFixture(): int
    {
        do {
            $userId = random_int(1200000000, 1900000000);
        } while (DB::table('user_logins')->where('user_id', $userId)->exists()
            || DB::table('user_infos')->where('user_id', $userId)->exists());

        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-page-customer-' . $userId . '@example.test',
            'password' => Hash::make('LegacyPageA123'),
            'account_type' => 2,
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
            'user_name' => 'Legacy page render customer',
            'phone' => '86-139' . substr((string) $userId, -8),
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'level_id' => 0,
            'group_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $userId;
    }

    private function createUserGroupFixture(): int
    {
        $now = time();

        return (int) DB::table('group_configs')->insertGetId([
            'name' => 'legacy-page-user-group-' . uniqid(),
            'radix' => 50,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function createWithdrawFixture(): int
    {
        $now = time();
        $localOrderNo = 'legacy-page-withdraw-' . uniqid();

        return (int) DB::table('withdraw_records')->insertGetId([
            'user_id' => 984208,
            'user_name' => 'Legacy page withdraw user',
            'mt4_ticket' => 'MT4-PAGE-984208',
            'apply_amount' => '20.00',
            'actual_amount' => '19.00',
            'fee' => '1.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '7.00',
            'bank_no' => '62220000984208',
            'bank_name' => 'Legacy Page Bank',
            'bank_addr' => 'Shanghai',
            'status' => 0,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => $localOrderNo,
            'funding_status' => 'debited',
            'funding_payload_hash' => hash('sha256', $localOrderNo),
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function createAuthenticationFixture(int $userId): void
    {
        $now = time();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-page-auth-' . $userId . '@example.test',
            'password' => Hash::make('LegacyPageA123'),
            'account_type' => 2,
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
            'user_name' => 'Legacy page auth user',
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_auths')->updateOrInsert(['user_id' => $userId], [
            'id_card_status' => 1,
            'bank_status' => 1,
            'id_card_remarks' => '',
            'bank_remarks' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /** @return array{int, int} */
    private function createVoucherFixture(): array
    {
        $userId = 984207;
        $this->createAuthenticationFixture($userId);
        $voucherId = (int) DB::table('voucher_infos')->insertGetId([
            'user_id' => $userId,
            'images' => json_encode(['legacy-page-voucher.jpg']),
            'remarks' => 'Legacy page voucher',
            'review_status' => 0,
            'review_message' => '',
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        return [$voucherId, $userId];
    }
}
