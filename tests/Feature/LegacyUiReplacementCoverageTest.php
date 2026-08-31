<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 12:53
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 旧项目页面入口迁移覆盖测试。
 *
 * 文件职责：
 * - 验证旧项目普通用户、代理商与后台模块都有命名的 Blade 页面入口。
 * - 验证 Blade 页面连接真实 API，并保留 CrmUI Blade 兼容页面的模块配置。
 * - 不再验证已移除的 Node/Naive 单页实现，防止废弃资源重新成为业务依赖。
 *
 * 业务方法、权限、状态机和数据库结果由对应模块的独立功能测试验证；本文件只守住
 * 路由到服务端渲染页面的入口契约。
 */
class LegacyUiReplacementCoverageTest extends TestCase
{
    /**
     * 后台模块入口清单。
     *
     * path 是 Blade 后台访问路径；route 是命名页面路由；crmui 是兼容 Blade 页面标识；
     * api 是页面表格读取的真实后端地址。
     *
     * @return array<string, array{path:string, route:string, crmui:string, api:string}>
     */
    public static function legacyAdminModuleProvider(): array
    {
        return [
            'online-users' => ['path' => 'online-users', 'route' => 'admin_page_online_users', 'crmui' => 'admin.online_users', 'api' => '/api/admin/onlineUserList'],
            'authentications' => ['path' => 'authentications', 'route' => 'admin_page_authentications', 'crmui' => 'admin.authentications', 'api' => '/api/admin/authPendingList'],
            'productions' => ['path' => 'productions', 'route' => 'admin_page_productions', 'crmui' => 'admin.productions', 'api' => '/api/admin/productionList'],
            'gifts' => ['path' => 'gifts', 'route' => 'admin_page_gifts', 'crmui' => 'admin.gifts', 'api' => '/api/admin/giftShipmentList'],
            'deposit-imports' => ['path' => 'deposit-imports', 'route' => 'admin_page_deposit_imports', 'crmui' => 'admin.deposit_imports', 'api' => '/api/admin/depositImportList'],
            'withdraw-imports' => ['path' => 'withdraw-imports', 'route' => 'admin_page_withdraw_imports', 'crmui' => 'admin.withdraw_imports', 'api' => '/api/admin/withdrawImportList'],
            'withdraw-flows' => ['path' => 'withdraw-flows', 'route' => 'admin_page_withdraw_flows', 'crmui' => 'admin.withdraw_flows', 'api' => '/api/admin/withdrawFlowList'],
            'undeposit-flows' => ['path' => 'undeposit-flows', 'route' => 'admin_page_undeposit_flows', 'crmui' => 'admin.undeposit_flows', 'api' => '/api/admin/undepositFlowList'],
            'rights-summary' => ['path' => 'rights-summary', 'route' => 'admin_page_rights_summary', 'crmui' => 'admin.rights_summary', 'api' => '/api/admin/rightsSummaryList'],
            'position-summary' => ['path' => 'position-summary', 'route' => 'admin_page_position_summary', 'crmui' => 'admin.position_summary', 'api' => '/api/admin/positionSummaryList'],
            'realtime-commissions' => ['path' => 'realtime-commissions', 'route' => 'admin_page_realtime_commissions', 'crmui' => 'admin.realtime_commissions', 'api' => '/api/admin/realtimeCommissionList'],
            'credit-imports' => ['path' => 'credit-imports', 'route' => 'admin_page_credit_imports', 'crmui' => 'admin.credit_imports', 'api' => '/api/admin/creditImportList'],
            'exchange-rates' => ['path' => 'exchange-rates', 'route' => 'admin_page_exchange_rates', 'crmui' => 'admin.exchange_rates', 'api' => '/api/admin/exchangeRateInfo'],
            'risk' => ['path' => 'risk', 'route' => 'admin_page_risk', 'crmui' => 'admin.risk', 'api' => '/api/admin/riskPositions'],
            'whs-exp-zero' => ['path' => 'whs-exp-zero', 'route' => 'admin_page_whs_exp_zero', 'crmui' => 'admin.whs_exp_zero', 'api' => '/api/admin/whsExpZeroList'],
            'blacklist' => ['path' => 'blacklist', 'route' => 'admin_page_blacklist', 'crmui' => 'admin.blacklist', 'api' => '/api/admin/blacklistList'],
            'cancel-applies' => ['path' => 'cancel-applies', 'route' => 'admin_page_cancel_applies', 'crmui' => 'admin.cancel_applies', 'api' => '/api/admin/cancelApplyList'],
            'trades' => ['path' => 'trades', 'route' => 'admin_page_trades', 'crmui' => 'admin.trades', 'api' => '/api/admin/tradeList'],
            'big-agents' => ['path' => 'big-agents', 'route' => 'admin_page_big_agents', 'crmui' => 'admin.big_agents', 'api' => '/api/admin/bigAgentList'],
        ];
    }

    /**
     * 旧后台出金状态别名清单。
     *
     * @return array<string, array{path:string, route:string, status:string}>
     */
    public static function legacyAdminWithdrawStatusAliasProvider(): array
    {
        return [
            'pending' => ['path' => 'withdraw/pending', 'route' => 'admin_page_withdraw_pending', 'status' => '0'],
            'processing' => ['path' => 'withdraw/processing', 'route' => 'admin_page_withdraw_processing', 'status' => '1'],
            'completed' => ['path' => 'withdraw/completed', 'route' => 'admin_page_withdraw_completed', 'status' => '2'],
            'failed' => ['path' => 'withdraw/failed', 'route' => 'admin_page_withdraw_failed', 'status' => '3'],
        ];
    }

    /**
     * 普通用户与代理商的主模块入口清单。
     *
     * @return array<string, array{legacyRoute:string, path:string, route:string, crmui:string, api:string, layuiPage:string}>
     */
    public static function legacyFrontModuleProvider(): array
    {
        return [
            'dashboard' => ['legacyRoute' => 'legacy_user_index_page', 'path' => 'dashboard', 'route' => 'front_page_dashboard', 'crmui' => 'front.dashboard', 'api' => '/api/front/dashboard', 'layuiPage' => 'dashboard/index'],
            'profile' => ['legacyRoute' => 'legacy_user_center_page', 'path' => 'profile', 'route' => 'front_page_profile', 'crmui' => 'front.profile', 'api' => '/api/front/profile', 'layuiPage' => 'profile/index'],
            'account-info' => ['legacyRoute' => 'legacy_user_account_page', 'path' => 'account/info', 'route' => 'front_page_account_info', 'crmui' => 'front.account_info', 'api' => '/api/front/account/profile', 'layuiPage' => ''],
            'voucher' => ['legacyRoute' => 'legacy_user_voucher_page', 'path' => 'account/voucher', 'route' => 'front_page_account_voucher', 'crmui' => 'front.account_voucher', 'api' => '/api/front/account/vouchers', 'layuiPage' => ''],
            'cancel-account' => ['legacyRoute' => 'legacy_user_center_cancel_page', 'path' => 'account/cancel', 'route' => 'front_page_account_cancel', 'crmui' => 'front.account_cancel', 'api' => '/api/front/account/cancellation', 'layuiPage' => ''],
            'deposit' => ['legacyRoute' => 'legacy_user_deposit_page', 'path' => 'deposit', 'route' => 'front_page_deposit', 'crmui' => 'front.deposit', 'api' => '/api/front/deposits/history', 'layuiPage' => 'deposit/index'],
            'withdraw' => ['legacyRoute' => 'legacy_user_withdraw_page', 'path' => 'withdraw', 'route' => 'front_page_withdraw', 'crmui' => 'front.withdraw', 'api' => '/api/front/withdrawals/history', 'layuiPage' => 'withdraw/index'],
            'flow' => ['legacyRoute' => 'legacy_user_flow_page', 'path' => 'flow', 'route' => 'front_page_flow', 'crmui' => 'front.flow', 'api' => '/api/front/flows/account', 'layuiPage' => 'flow/index'],
            'proxy-list' => ['legacyRoute' => 'legacy_user_proxy_list_page', 'path' => 'agent/sub', 'route' => 'front_page_agent_sub', 'crmui' => 'front.agent_sub', 'api' => '/api/front/agents/direct', 'layuiPage' => ''],
            'customer-list' => ['legacyRoute' => 'legacy_user_customer_list_page', 'path' => 'agent/customers', 'route' => 'front_page_agent_customers', 'crmui' => 'front.agent_customers', 'api' => '/api/front/agents/direct-customers', 'layuiPage' => ''],
            'proxy-confirm' => ['legacyRoute' => 'legacy_user_proxy_confirm_page', 'path' => 'agent/confirm-level', 'route' => 'front_page_agent_confirm_level', 'crmui' => 'front.agent_confirm_level', 'api' => '/api/front/agents/level-confirmation', 'layuiPage' => ''],
            'group-change' => ['legacyRoute' => 'legacy_user_customer_change_list_page', 'path' => 'agent/group-change', 'route' => 'front_page_agent_group_change', 'crmui' => 'front.agent_group_change', 'api' => '/api/front/agents/group-changes', 'layuiPage' => ''],
            'position-summary' => ['legacyRoute' => 'legacy_user_position_summary_page', 'path' => 'position/summary', 'route' => 'front_page_position_summary', 'crmui' => 'front.position_summary', 'api' => '/api/front/positions/summary', 'layuiPage' => ''],
            'open-orders' => ['legacyRoute' => 'legacy_user_open_order_page', 'path' => 'order/open', 'route' => 'front_page_order_open', 'crmui' => 'front.open_orders', 'api' => '/api/front/orders/open', 'layuiPage' => ''],
            'closed-orders' => ['legacyRoute' => 'legacy_user_close_order_page', 'path' => 'order/closed', 'route' => 'front_page_order_closed', 'crmui' => 'front.closed_orders', 'api' => '/api/front/orders/closed', 'layuiPage' => ''],
            'realtime-commission' => ['legacyRoute' => 'legacy_user_realtime_rebate_page', 'path' => 'commission/realtime', 'route' => 'front_page_commission_realtime', 'crmui' => 'front.commission_realtime', 'api' => '/api/front/commissions/realtime', 'layuiPage' => ''],
            'commission-transfer' => ['legacyRoute' => 'legacy_user_proxy_commission_transfer_page', 'path' => 'commission/transfer', 'route' => 'front_page_commission_transfer', 'crmui' => 'front.commission_transfer', 'api' => '/api/front/commissions/history', 'layuiPage' => ''],
            'gift-address' => ['legacyRoute' => 'legacy_user_address_page', 'path' => 'gift/address', 'route' => 'front_page_gift_address', 'crmui' => 'front.gift_address', 'api' => '/api/front/gift-addresses', 'layuiPage' => ''],
            'gift-list' => ['legacyRoute' => 'legacy_user_gift_page', 'path' => 'gift/list', 'route' => 'front_page_gift_list', 'crmui' => 'front.gift_list', 'api' => '/api/front/gifts', 'layuiPage' => ''],
            'news' => ['legacyRoute' => 'legacy_user_news_page', 'path' => 'news', 'route' => 'front_page_news', 'crmui' => 'front.news', 'api' => '/api/front/news', 'layuiPage' => ''],
        ];
    }

    /**
     * 旧详情和二级别名入口清单。
     *
     * 新闻详情不放入本清单：其控制器会先校验 news 表中的已发布记录，使用虚构 ID
     * 会把本文件的静态 Blade 架构测试错误地变成数据库集成测试；下方专用方法只验证
     * 路由与 Blade 数据契约，真实新闻记录的成功、未发布和软删除分支由新闻闭环测试负责。
     *
     * @return array<string, array{legacyRoute:string, path:string, route:string, api:string}>
     */
    public static function legacyFrontAliasProvider(): array
    {
        return [
            // summary2 是普通用户本人 MT4 汇总，不得复用支持代理树钻取的 position/summary 接口。
            'customer-position-summary' => ['legacyRoute' => 'legacy_user_position_summary2_page', 'path' => 'position/summary2', 'route' => 'front_page_position_summary2', 'api' => '/user/position/positionSummary2Search'],
            'customer-open-orders' => ['legacyRoute' => 'legacy_user_open_order2_page', 'path' => 'order/open2', 'route' => 'front_page_order_open', 'api' => '/api/front/orders/open'],
            'customer-closed-orders' => ['legacyRoute' => 'legacy_user_close_order2_page', 'path' => 'order/closed2', 'route' => 'front_page_order_closed', 'api' => '/api/front/orders/closed'],
            'position-commission-summary' => ['legacyRoute' => 'legacy_user_position_comm_summary_page', 'path' => 'position/comm-summary', 'route' => 'front_page_position_summary', 'api' => '/api/front/positions/summary'],
            'position-commission-summary-v2' => ['legacyRoute' => 'legacy_user_position_comm_summary_v2_page', 'path' => 'position/comm-summary-v2', 'route' => 'front_page_position_summary', 'api' => '/api/front/positions/summary'],
            'voucher-browse' => ['legacyRoute' => 'legacy_user_voucher_browse_page', 'path' => 'account/voucher/browse', 'route' => 'front_page_account_voucher', 'api' => '/api/front/account/vouchers'],
            'address-add' => ['legacyRoute' => 'legacy_user_address_add_page', 'path' => 'gift/address/add', 'route' => 'front_page_gift_address', 'api' => '/api/front/gift-addresses'],
            'address-edit' => ['legacyRoute' => 'legacy_user_address_edit_page', 'path' => 'gift/address/info/1001', 'route' => 'front_page_gift_address', 'api' => '/api/front/gift-addresses'],
            'proxy-direct-customer-detail' => ['legacyRoute' => 'legacy_user_proxy_direct_customer_page', 'path' => 'agent/customers/1001', 'route' => 'front_page_agent_customers', 'api' => '/api/front/agents/direct-customers'],
            'customer-change-group' => ['legacyRoute' => 'legacy_user_customer_change_group_page', 'path' => 'agent/group-change/1001', 'route' => 'front_page_agent_group_change', 'api' => '/api/front/agents/group-changes'],
            'commission-transfer-target' => ['legacyRoute' => 'legacy_user_proxy_commission_transfer_page', 'path' => 'commission/transfer/1001', 'route' => 'front_page_commission_transfer', 'api' => '/api/front/commissions/history'],
            'position-summary-detail' => ['legacyRoute' => 'legacy_user_position_summary_detail_page', 'path' => 'position/summary/detail/1001', 'route' => 'front_page_position_summary_detail', 'api' => '/api/front/positions/summary'],
            'open-order-detail' => ['legacyRoute' => 'legacy_user_open_order_detail', 'path' => 'order/open/detail/1001', 'route' => 'front_page_order_open_detail', 'api' => '/api/front/orders/open'],
            'closed-order-detail' => ['legacyRoute' => 'legacy_user_close_order_detail', 'path' => 'order/closed/detail/1001', 'route' => 'front_page_order_closed_detail', 'api' => '/api/front/orders/closed'],
            'realtime-commission-detail' => ['legacyRoute' => 'legacy_user_realtime_rebate_detail', 'path' => 'commission/realtime/detail/1001', 'route' => 'front_page_commission_realtime_detail', 'api' => '/api/front/commissions/realtime'],
            'agent-customer-detail' => ['legacyRoute' => 'legacy_user_customer_detail', 'path' => 'agent/customer-detail/agent/1001', 'route' => 'front_page_agent_customer_detail', 'api' => '/api/front/agents/direct-customers'],
        ];
    }

    /**
     * 验证后台模块有命名入口、CrmUI Blade 配置和真实 API 地址。
     *
     * @dataProvider legacyAdminModuleProvider
     * @param string $path 后台页面相对路径。
     * @param string $route 页面命名路由。
     * @param string $crmui CrmUI Blade 页面标识。
     * @param string $api 后台列表 API 地址。
     * @return void
     */
    public function test_legacy_admin_modules_keep_blade_routes_and_real_api_contracts(string $path, string $route, string $crmui, string $api): void
    {
        $html = $this->get('/admin-crmui/' . $path)->assertOk()->getContent();

        $this->assertTrue(Route::has($route), '缺少后台 Blade 页面路由：' . $route);
        $this->assertApiUriIsRegistered($api);
        $this->assertStringContainsString('data-crmui-page="' . $crmui . '"', $html);
        $this->assertStringContainsString($api, $html, '后台 Blade 页面未输出模块 API：' . $api);
    }

    /**
     * 验证旧后台出金状态别名仍然进入同一个服务端 Blade 页面并锁定状态筛选。
     *
     * @dataProvider legacyAdminWithdrawStatusAliasProvider
     * @param string $path 旧状态页面路径。
     * @param string $route 页面命名路由。
     * @param string $status 出金状态值。
     * @return void
     */
    public function test_legacy_admin_withdraw_status_aliases_keep_blade_filter_contract(string $path, string $route, string $status): void
    {
        $html = $this->get('/admin/' . $path)->assertOk()->getContent();

        $this->assertTrue(Route::has($route), '缺少旧后台出金状态路由：' . $route);
        $this->assertStringContainsString('data-layui-page="withdrawals/index"', $html);
        $this->assertSame(1, preg_match_all(
            '/<input\b(?=[^>]*\btype="hidden")(?=[^>]*\bname="status")(?=[^>]*\bvalue="'
                . preg_quote($status, '/') . '")[^>]*>/i',
            $html
        ));
        $this->assertDoesNotMatchRegularExpression('/<select\b[^>]*\bname="status"[^>]*>/i', $html);
    }

    /**
     * 验证普通用户和代理商模块从旧路由进入服务端 Blade 页面，并连接相同的业务 API。
     *
     * @dataProvider legacyFrontModuleProvider
     * @param string $legacyRoute 旧项目命名路由。
     * @param string $path Blade 页面相对路径。
     * @param string $route 新页面命名路由。
     * @param string $crmui CrmUI Blade 页面标识。
     * @param string $api 页面读取 API 地址。
     * @param string $layuiPage 聚合 Layui 页面标识，通用模块页传空字符串。
     * @return void
     */
    public function test_legacy_front_modules_keep_blade_pages_and_api_contracts(string $legacyRoute, string $path, string $route, string $crmui, string $api, string $layuiPage): void
    {
        $this->assertTrue(Route::has($legacyRoute), '缺少旧前台路由：' . $legacyRoute);
        $this->assertTrue(Route::has($route), '缺少新 Blade 页面路由：' . $route);
        $this->assertApiUriIsRegistered($api);
        $this->assertLayuiFrontEndpoint($path, $api, $layuiPage);

        $crmuiHtml = $this->get('/front-crmui/' . $path)->assertOk()->getContent();
        $this->assertStringContainsString('data-crmui-page="' . $crmui . '"', $crmuiHtml);
        $this->assertStringContainsString($api, $crmuiHtml);
    }

    /**
     * 验证旧详情和二级别名最终复用已存在的 Blade 页面与 API 契约。
     *
     * @dataProvider legacyFrontAliasProvider
     * @param string $legacyRoute 旧项目命名路由。
     * @param string $path 别名访问路径。
     * @param string $route 最终复用的 Blade 页面路由。
     * @param string $api 复用模块的真实 API 地址。
     * @return void
     */
    public function test_legacy_front_aliases_reuse_blade_pages_and_api_contracts(string $legacyRoute, string $path, string $route, string $api): void
    {
        $this->assertTrue(Route::has($legacyRoute), '缺少旧别名路由：' . $legacyRoute);
        $this->assertTrue(Route::has($route), '缺少别名目标 Blade 路由：' . $route);
        $this->assertApiUriIsRegistered($api);
        $this->assertLayuiFrontEndpoint($path, $api);
    }

    /**
     * 验证新闻详情保留旧入口、现代 Blade 路由和真实列表 API，但不伪造数据库记录。
     *
     * 执行链路说明：
     * - 旧入口仍由 legacy_user_news_detail 命名路由承接，保证历史链接可访问。
     * - 现代入口固定调用 NewsController@newsPage，并把 newsId 传给新闻 Blade。
     * - 新闻 Blade 使用 /api/front/news 按 news_id 精确加载正文；真实记录、未发布记录、
     *   软删除记录和不存在记录的 HTTP 结果由 FrontNewsDetailRouteClosureModuleTest 验证。
     *
     * @return void 成功表示新闻详情页面链路完整，且本架构测试不依赖外部数据库状态。
     */
    public function test_news_detail_keeps_server_rendered_blade_route_without_fabricated_record(): void
    {
        $this->assertTrue(Route::has('legacy_user_news_detail'), '缺少旧新闻详情路由：legacy_user_news_detail');
        $this->assertTrue(Route::has('front_page_news_detail'), '缺少现代新闻详情 Blade 路由：front_page_news_detail');
        $this->assertApiUriIsRegistered('/api/front/news');

        $detailRoute = Route::getRoutes()->getByName('front_page_news_detail');
        $this->assertNotNull($detailRoute, '无法读取现代新闻详情路由定义。');
        $this->assertSame('front/news/detail/{newsId}', $detailRoute->uri());
        $this->assertSame('App\\Http\\Controllers\\Front\\NewsController@newsPage', $detailRoute->getActionName());
        $this->assertSame('/front/news/detail/1001', route('front_page_news_detail', ['newsId' => 1001], false));

        $blade = file_get_contents(resource_path('front/layui/news/index.blade.php')) ?: '';
        $this->assertStringContainsString("'api' => '/api/front/news'", $blade);
        $this->assertStringContainsString('legacyNewsId', $blade);
    }

    /**
     * 为全量执行链报告保留可精确关联的入金 Blade 请求证据。
     *
     * 数据提供器中的动态路径只能证明整组页面入口；本方法使用固定 URI，让报告生成器能把
     * GET /front/deposit 的成功请求行与旧支付回跳产生的重定向断言严格区分。
     *
     * @return void 成功表示入金入口由服务端 Blade 渲染，并输出聚合 Layui 页面标识。
     */
    public function test_front_deposit_has_exact_server_rendered_blade_request_evidence(): void
    {
        $html = $this->get('/front/deposit?frame=1')->assertOk()->getContent();

        $this->assertStringContainsString('data-layui-page="deposit/index"', $html);
        $this->assertStringContainsString('/js/apps/front/layui/pages.js', $html);
    }

    /**
     * 验证代表性写入表单仍由 Blade 渲染，且保留旧字段名与真实 REST API。
     *
     * @return void
     */
    public function test_blade_forms_preserve_legacy_fields_for_gifts_cancellation_and_agent_requests(): void
    {
        $addressHtml = $this->get('/front/gift/address?frame=1')->assertOk()->getContent();
        $cancelHtml = $this->get('/front/account/cancel?frame=1')->assertOk()->getContent();
        $groupHtml = $this->get('/front/agent/group-change?frame=1')->assertOk()->getContent();
        $transferHtml = $this->get('/front/commission/transfer?frame=1')->assertOk()->getContent();

        foreach (['recipient_name', 'recipient_phone', 'recipient_address', 'is_default'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $addressHtml);
        }
        $this->assertStringContainsString('/api/front/gift-addresses', $addressHtml);

        foreach (['userIdcardNo', 'userphoneNo', 'useremail', 'userverfcode', 'password'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $cancelHtml);
        }
        $this->assertStringContainsString('/api/front/account/cancellation-applications', $cancelHtml);

        foreach (['target_user_id', 'new_group_id', 'reason'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $groupHtml);
        }
        $this->assertStringContainsString('/api/front/agents/group-change-applications', $groupHtml);

        foreach (['sub_agent_id', 'amount', 'remark'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $transferHtml);
        }
        $this->assertStringContainsString('/api/front/commissions/transfers', $transferHtml);
    }

    /**
     * 验证列表型模块保留旧筛选字段、行字段和新闻时间线渲染契约，避免 Blade 重写遗漏历史查询能力。
     *
     * @return void
     */
    public function test_blade_list_pages_keep_legacy_filters_columns_and_news_timeline_contract(): void
    {
        $giftHtml = $this->get('/front/gift/list?frame=1')->assertOk()->getContent();
        $newsHtml = $this->get('/front/news?frame=1')->assertOk()->getContent();
        $moduleScript = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';

        foreach (['recipient_name', 'gift_name', 'startdate', 'enddate'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $giftHtml);
        }
        foreach (['gift_name', 'recipient_name', 'recipient_phone', 'recipient_address', 'sender_name', 'gift_quantity', 'remark', 'shipped_at'] as $field) {
            $this->assertStringContainsString('"key":"' . $field . '"', $giftHtml);
        }

        foreach (['startdate', 'enddate'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $newsHtml);
        }
        foreach (['news_id', 'news_title', 'rec_crt_date'] as $field) {
            $this->assertStringContainsString('"key":"' . $field . '"', $newsHtml);
        }
        $this->assertStringContainsString('function renderNewsTimeline', $moduleScript);
        $this->assertStringContainsString('layui-timeline', $moduleScript);
        $this->assertStringContainsString('<h3 class="module-news-title">', $moduleScript);
        $this->assertStringNotContainsString('data-news-detail-row', $moduleScript);
    }

    /**
     * 验证 CrmUI 兼容入口也由 Blade 输出，并保留地址更新、删除和默认地址动作。
     *
     * @return void
     */
    public function test_crmui_compatibility_pages_are_server_rendered_blade_forms(): void
    {
        $html = $this->get('/front-crmui/gift/address')->assertOk()->getContent();

        $this->assertStringContainsString('data-crmui-page="front.gift_address"', $html);
        $this->assertStringContainsString('/api/front/gift-addresses/__ID__', $html);
        $this->assertStringContainsString('data-action-method="PATCH"', $html);
        $this->assertStringContainsString('data-action-method="DELETE"', $html);
        $this->assertStringContainsString('data-static-payload="{&quot;is_default&quot;:1}"', $html);
    }

    /**
     * 断言前台页面由 Blade 输出并正确声明 API 获取方式。
     *
     * 通用模块页把 API 写在 data-api；聚合 Layui 页把模块标记写在 data-layui-page，
     * 对应 API 保留在聚合脚本中。两种写法都由服务端 Blade 模板选择和输出。
     *
     * @param string $path 页面相对路径。
     * @param string $api 该页面的真实业务 API 地址。
     * @param string $layuiPage 聚合 Layui 模块标识。
     * @return void
     */
    private function assertLayuiFrontEndpoint(string $path, string $api, string $layuiPage = ''): void
    {
        $html = $this->get('/front/' . $path . '?frame=1')->assertOk()->getContent();

        if (str_contains($html, 'id="frontModulePage"')) {
            $this->assertStringContainsString('data-api="' . $api . '"', $html, '缺少前台模块 API：' . $path);
            $this->assertStringContainsString('/js/apps/front/layui/module-page.js', $html, '缺少前台模块脚本：' . $path);
            return;
        }

        if ($layuiPage !== '') {
            $this->assertStringContainsString('data-layui-page="' . $layuiPage . '"', $html, '缺少 Layui 页面标识：' . $path);
        }

        $source = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $this->assertStringContainsString($api, $source, '缺少聚合 Layui API：' . $api);
    }

    /**
     * 验证 API URI 已经注册到 Laravel 路由表。
     *
     * 使用 URI 而不是控制器内的硬编码字符串断言，是因为 CrmUI 会在 Blade 渲染时通过
     * route() 生成最终地址；这样既验证真实接口存在，也允许控制器保持命名路由实现。
     *
     * @param string $api 预期的相对 API URI，例如 /api/front/dashboard。
     * @return void
     */
    private function assertApiUriIsRegistered(string $api): void
    {
        $uri = ltrim($api, '/');
        $registered = collect(Route::getRoutes()->getRoutes())
            ->contains(static fn ($route): bool => $route->uri() === $uri);

        $this->assertTrue($registered, '缺少已注册 API 路由：' . $api);
    }
}
