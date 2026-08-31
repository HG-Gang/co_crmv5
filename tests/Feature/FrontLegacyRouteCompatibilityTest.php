<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 02:01
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Feature\Concerns\CreatesLegacySmokeUsers;

/**
 * 旧前台路由兼容性回归测试。
 *
 * 文件功能：
 * - 固定旧 URL、HTTP 方法和控制器动作的兼容契约。
 * - 将无副作用页面烟测与会产生资金写入的定时任务区分开，避免测试误触外部 MT4。
 */
class FrontLegacyRouteCompatibilityTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacySmokeUsers;

    public function test_front_resource_api_routes_are_registered_and_legacy_module_aliases_are_removed(): void
    {
        $resourceRoutes = [
            'front_api_flows_deposits' => 'App\\Http\\Controllers\\Front\\FlowController@depositFlowSearch',
            'front_api_flows_withdrawals' => 'App\\Http\\Controllers\\Front\\FlowController@withdrawalFlowSearch',
            'front_api_flows_withdrawal_applications' => 'App\\Http\\Controllers\\Front\\FlowController@withdrawApplyFlowSearch',
            'front_api_flows_direct_deposits' => 'App\\Http\\Controllers\\Front\\FlowController@directDepositFlowSearch',
            'front_api_flows_direct_withdrawals' => 'App\\Http\\Controllers\\Front\\FlowController@directWithdrawalFlowSearch',
            'front_api_flows_direct_agent_deposits' => 'App\\Http\\Controllers\\Front\\FlowController@directDepositFlowSearch',
            'front_api_flows_direct_agent_withdrawals' => 'App\\Http\\Controllers\\Front\\FlowController@directWithdrawalFlowSearch',
            'front_api_orders_open' => 'App\\Http\\Controllers\\Front\\OrderController@openOrders',
            'front_api_orders_closed' => 'App\\Http\\Controllers\\Front\\OrderController@closedOrders',
            'front_api_commissions_realtime' => 'App\\Http\\Controllers\\Front\\CommissionController@realTime',
            'front_api_commissions_history' => 'App\\Http\\Controllers\\Front\\CommissionController@history',
            'front_api_commissions_transfers' => 'App\\Http\\Controllers\\Front\\CommissionController@transfer',
            'front_api_agents_direct' => 'App\\Http\\Controllers\\Front\\AgentController@subList',
            'front_api_agents_direct_customers' => 'App\\Http\\Controllers\\Front\\AgentController@customerList',
            'front_api_customers_commission_transfers' => 'App\\Http\\Controllers\\Front\\AgentController@directUserCommTrans',
            'front_api_positions_summary' => 'App\\Http\\Controllers\\Front\\PositionController@positionSummary',
            'front_api_positions_direct_agent_summaries' => 'App\\Http\\Controllers\\Front\\PositionController@subPositionSummary',
            'front_api_positions_trades' => 'App\\Http\\Controllers\\Front\\PositionController@positionDetail',
            'front_api_news' => 'App\\Http\\Controllers\\Front\\NewsController@newsList',
            'front_api_account_vouchers' => 'App\\Http\\Controllers\\Front\\AccountController@voucherList',
        ];

        foreach ($resourceRoutes as $name => $action) {
            $this->assertTrue(Route::has($name), $name . ' route is missing.');
            $this->assertSame($action, Route::getRoutes()->getByName($name)->getActionName());

            [$controller, $method] = explode('@', $action);
            $this->assertTrue(method_exists($controller, $method), $action . ' method is missing.');
        }

        foreach ([
            'front_api_depositFlowSearch',
            'front_api_directDepositFlowSearch',
            'front_api_openOrderSearch',
            'front_api_closeOrderSearch',
            'front_api_commissionRealTime',
            'front_api_commissionHistory',
            'front_api_directCustListSearch',
            'front_api_directUserCommTrans',
            'front_api_newsListSearch',
            'front_api_voucher_voucherSearch',
            'front_api_positionSummary2Search',
            'front_api_positions_sub_summary',
            'front_api_positions_detail',
        ] as $legacyName) {
            $this->assertFalse(Route::has($legacyName), $legacyName . ' must not remain registered under /api/front.');
        }
    }

    public function test_front_legacy_user_web_routes_are_registered(): void
    {
        $legacyRoutes = [
            'legacy_user_login_page' => ['GET', 'user/login', 'App\\Http\\Controllers\\Front\\AuthController@showLogin'],
            'legacy_user_index_login_page' => ['GET', 'user/index/login', 'App\\Http\\Controllers\\Front\\AuthController@showLogin'],
            'legacy_user_register_page' => ['GET', 'user/register/{register_type?}/{user_id?}/{comm_type?}', 'App\\Http\\Controllers\\Front\\AuthController@legacyRegisterPage'],
            'legacy_user_index_register_page' => ['GET', 'user/index/register/{register_type?}/{user_id?}/{comm_type?}', 'App\\Http\\Controllers\\Front\\AuthController@legacyRegisterPage'],
            'legacy_en_user_register_page' => ['GET', 'en/user/register/{register_type?}/{user_id?}/{comm_type?}', 'App\\Http\\Controllers\\Front\\AuthController@legacyRegisterPage'],
            'legacy_user_forget_password_page' => ['GET', 'user/forget_password', 'App\\Http\\Controllers\\Front\\ForgotPasswordController@showForgotPassword'],
            'legacy_user_sign_in' => ['POST', 'user/signIn', 'App\\Http\\Controllers\\Front\\AuthController@legacySignIn'],
            'legacy_user_index_sign_in' => ['POST', 'user/index/signIn', 'App\\Http\\Controllers\\Front\\AuthController@legacySignIn'],
            'legacy_user_register_verify_info' => ['POST', 'user/register/registerVerifyInfo', 'App\\Http\\Controllers\\Front\\AuthController@registerVerifyInfo'],
            'legacy_user_register_send_code' => ['POST', 'user/register/registerSendCode', 'App\\Http\\Controllers\\Front\\AuthController@registerSendCode'],
            'legacy_user_register_into' => ['POST', 'user/register/registerinto', 'App\\Http\\Controllers\\Front\\AuthController@register'],
            'legacy_user_register_captcha' => ['GET', 'user/register/captcha', 'App\\Http\\Controllers\\Front\\AuthController@registerCaptcha'],
            'legacy_user_register_testemail' => ['GET', 'user/register/testemail', 'App\\Http\\Controllers\\Front\\AuthController@checkEmail'],
            'legacy_user_captcha' => ['GET', 'user/captcha', 'App\\Http\\Controllers\\Front\\AuthController@loginCaptcha'],
            'legacy_user_register_testmodel' => ['GET', 'user/register/testmodel', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testmodel'],
            'legacy_user_register_rebate_deposit' => ['GET', 'user/register/rebateDeposit', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@orderRebateDeposit'],
            'legacy_user_forget_check_info' => ['POST', 'user/check_user_info', 'App\\Http\\Controllers\\Front\\ForgotPasswordController@checkUserInfo'],
            'legacy_user_forget_send_code' => ['POST', 'user/forgetpswSendCode', 'App\\Http\\Controllers\\Front\\ForgotPasswordController@sendResetCode'],
            'legacy_user_forget_verify' => ['POST', 'user/forgetPasswordInfoVerification', 'App\\Http\\Controllers\\Front\\ForgotPasswordController@forgetPasswordInfoVerification'],
            'legacy_user_change_password' => ['POST', 'user/change_password', 'App\\Http\\Controllers\\Front\\ForgotPasswordController@saveChangePassword'],
            'legacy_user_upload_file' => ['POST', 'user/upload/file', 'App\\Http\\Controllers\\Front\\UploadController@singleFileUpload'],
            'legacy_user_multiple_file' => ['POST', 'user/multiple/file', 'App\\Http\\Controllers\\Front\\UploadController@multipleFileUpload'],
            'legacy_user_front_message' => ['GET', 'user/front/message', 'App\\Http\\Controllers\\Front\\DashboardController@frontMsg'],
            'legacy_user_hot_news' => ['POST', 'user/main/hot/news', 'App\\Http\\Controllers\\Front\\DashboardController@hotNews'],
            'legacy_user_hot_news_v2' => ['POST', 'user/main/hot/newsV2', 'App\\Http\\Controllers\\Front\\DashboardController@hotNewsV2'],
            'legacy_user_has_show_gift_tips' => ['POST', 'user/main/hasShowGiftTips', 'App\\Http\\Controllers\\Front\\DashboardController@hasShowGiftTips'],
            'legacy_user_change_account_save' => ['POST', 'user/change_account_save', 'App\\Http\\Controllers\\Front\\AccountController@changeAccountSave'],
            'legacy_user_voucher_save' => ['POST', 'user/user_voucher_save', 'App\\Http\\Controllers\\Front\\AccountController@userVoucherSave'],
            'legacy_user_deposit_export' => ['POST', 'user/flow/depositExport', 'App\\Http\\Controllers\\Front\\FlowController@depositExport'],
            'legacy_user_download_file' => ['GET', 'user/flow/downloadfile/{file}/{role}', 'App\\Http\\Controllers\\Front\\FlowController@downloadFile'],
            'legacy_user_detail' => ['GET', 'show/user_detail/{userId}/{role}', 'App\\Http\\Controllers\\Front\\AgentController@legacyUserDetailPage'],
            'legacy_user_customer_detail' => ['GET', 'user/cust/show_direct_cust_info/{role}/{uid}', 'App\\Http\\Controllers\\Front\\AgentController@legacyUserDetailPage'],
            'legacy_user_customer_login_history' => ['POST', 'user/cust/loginHistorySearch/{uid}', 'App\\Http\\Controllers\\Front\\AgentController@legacyLoginHistorySearch'],
            'legacy_user_news_detail' => ['GET', 'user/news/news_detail/{newsId}', 'App\\Http\\Controllers\\Front\\NewsController@newsDetail'],
            'legacy_user_open_order_detail' => ['GET', 'open/order_detail/{orderId}/{orderType}/{role}', 'App\\Http\\Controllers\\Front\\OrderController@openOrderDetail'],
            'legacy_user_close_order_detail' => ['GET', 'close/order_detail/{orderId}/{orderType}/{role}', 'App\\Http\\Controllers\\Front\\OrderController@closeOrderDetail'],
            'legacy_user_realtime_rebate_detail' => ['GET', 'user/realtime/rebate_detail/{orderNo}/{role}', 'App\\Http\\Controllers\\Front\\CommissionController@realtimeRebateDetail'],
        ];

        foreach ($legacyRoutes as $name => [$verb, $uri, $action]) {
            $this->assertTrue(Route::has($name), $name . ' route is missing.');

            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($action, $route->getActionName());
            $this->assertSame($uri, $route->uri());
            $this->assertContains($verb, $route->methods());

            [$controller, $method] = explode('@', $action);
            $this->assertTrue(method_exists($controller, $method), $action . ' method is missing.');
        }
    }

    /**
     * 验证旧前台业务模块路由仍指向正确的实现。
     *
     * @return void 路由名称、方法、URL 或控制器动作偏离旧契约时断言失败。
     */
    public function test_front_legacy_user_module_routes_are_registered(): void
    {
        $legacyRoutes = [
            'legacy_user_logout' => [['GET'], 'user/loginOut', 'App\\Http\\Controllers\\Front\\LegacyPageController@logout'],
            'legacy_user_index_page' => [['GET'], 'user/index', 'App\\Http\\Controllers\\Front\\LegacyPageController@dashboard'],
            'legacy_user_index_index_page' => [['GET'], 'user/index/index', 'App\\Http\\Controllers\\Front\\LegacyPageController@dashboard'],
            'legacy_user_indexreg_page' => [['POST'], 'user/indexreg', 'App\\Http\\Controllers\\Front\\LegacyPageController@dashboard'],
            'legacy_user_main_home_page' => [['GET'], 'user/main/home', 'App\\Http\\Controllers\\Front\\LegacyPageController@dashboard'],
            'legacy_user_register_hotnews' => [['GET'], 'user/register/hotnews', 'App\\Http\\Controllers\\Front\\DashboardController@registerHotNews'],
            'legacy_user_offweb_feedback' => [['POST'], 'user/offweb/feedback', 'App\\Http\\Controllers\\Front\\LegacyPageController@feedback'],
            'legacy_user_relationship' => [['POST'], 'user/relationShip', 'App\\Http\\Controllers\\Front\\ProfileController@relationShip'],
            'legacy_user_relationship_html' => [['POST'], 'user/relationShipHtml', 'App\\Http\\Controllers\\Front\\ProfileController@relationShipHtml'],
            'legacy_user_agents_relationship_html' => [['POST'], 'user/agents/relationShipHtml', 'App\\Http\\Controllers\\Front\\ProfileController@relationShipHtmlV2'],
            'legacy_user_center_page' => [['GET'], 'user/center', 'App\\Http\\Controllers\\Front\\LegacyPageController@profile'],
            'legacy_user_center_upload_id_page' => [['GET'], 'user/center/uploadIdCard', 'App\\Http\\Controllers\\Front\\LegacyPageController@profileIdentity'],
            'legacy_user_center_upload_bank_page' => [['GET'], 'user/center/uploadBank', 'App\\Http\\Controllers\\Front\\LegacyPageController@profileBank'],
            'legacy_user_center_change_bank_page' => [['GET'], 'user/center/uploadChangeBank/{type}', 'App\\Http\\Controllers\\Front\\LegacyPageController@profileBankChange'],
            'legacy_user_center_upload_head_page' => [['GET'], 'user/center/uploadHead_browse', 'App\\Http\\Controllers\\Front\\LegacyPageController@profileAvatar'],
            'legacy_user_center_phone_email_page' => [['GET'], 'user/center/updPhoneEmail/{type}', 'App\\Http\\Controllers\\Front\\LegacyPageController@profileContact'],
            'legacy_user_center_cancel_page' => [['GET'], 'user/center/cancelAccount', 'App\\Http\\Controllers\\Front\\LegacyPageController@cancelAccount'],
            'legacy_user_center_cancel_verify_info' => [['POST'], 'user/center/cancelVerifyInfo', 'App\\Http\\Controllers\\Front\\ProfileController@cancelVerifyInfo'],
            'legacy_user_center_cancel_verify_code' => [['POST'], 'user/center/cancelVerifyPassSendCode', 'App\\Http\\Controllers\\Front\\ProfileController@cancelVerifyPassSendCode'],
            'legacy_user_center_upload_id_card' => [['POST'], 'user/center/uploadIdCard', 'App\\Http\\Controllers\\Front\\ProfileController@uploadIdCard'],
            'legacy_user_center_upload_bank_card' => [['POST'], 'user/center/uploadBankCard', 'App\\Http\\Controllers\\Front\\ProfileController@uploadBankCard'],
            'legacy_user_center_upload_change_bank_card' => [['POST'], 'user/center/uploadChangeBankCard', 'App\\Http\\Controllers\\Front\\ProfileController@uploadChangeBankCard'],
            'legacy_user_center_update_verify_info' => [['POST'], 'user/center/updateVerifyInfo', 'App\\Http\\Controllers\\Front\\ProfileController@updateVerifyInfo'],
            'legacy_user_center_change_bank_verify_code' => [['POST'], 'user/center/changeBankCardVerifyCode', 'App\\Http\\Controllers\\Front\\ProfileController@changeBankCardVerifyCode'],
            'legacy_user_center_update_verify_code' => [['POST'], 'user/center/updVerifyPassSendCode', 'App\\Http\\Controllers\\Front\\ProfileController@updVerifyPassSendCode'],
            'legacy_user_center_change_bank_code' => [['POST'], 'user/center/changeBankCardSendCode', 'App\\Http\\Controllers\\Front\\ProfileController@changeBankCardSendCode'],
            'legacy_user_center_update_phone_email' => [['POST'], 'user/center/updatePhoneEmailInfo', 'App\\Http\\Controllers\\Front\\ProfileController@updatePhoneEmailInfo'],
            'legacy_user_center_ajax_cancel' => [['POST'], 'user/center/ajaxCancelAccount', 'App\\Http\\Controllers\\Front\\CancelController@ajaxCancelAccount'],
            'legacy_user_center_upload_head_img' => [['POST'], 'user/center/uploadHeadImg', 'App\\Http\\Controllers\\Front\\ProfileController@uploadHeadImg'],
            'legacy_user_edit_password_page' => [['GET'], 'user/editpsw', 'App\\Http\\Controllers\\Front\\LegacyPageController@profilePassword'],
            'legacy_user_agents_edit_password_page' => [['GET'], 'user/agents/editpsw', 'App\\Http\\Controllers\\Front\\BigNumberController@agents_editpsw_browse'],
            'legacy_user_edit_password_save' => [['POST'], 'user/editpsw_save', 'App\\Http\\Controllers\\Front\\ProfileController@user_editpsw_save'],
            'legacy_user_agents_edit_password_save' => [['POST'], 'user/agents/editpsw_save', 'App\\Http\\Controllers\\Front\\BigNumberController@agentsEditPasswordSave'],
            'legacy_user_voucher_page' => [['GET'], 'user/voucher', 'App\\Http\\Controllers\\Front\\LegacyPageController@voucher'],
            'legacy_user_account_page' => [['GET'], 'user/account', 'App\\Http\\Controllers\\Front\\LegacyPageController@account'],
            'legacy_user_deposit_page' => [['GET'], 'user/deposit', 'App\\Http\\Controllers\\Front\\LegacyPageController@deposit'],
            'legacy_user_deposit_request' => [['POST'], 'user/deposit_request', 'App\\Http\\Controllers\\Front\\DepositController@deposit_request'],
            'legacy_user_deposit_request_otc' => [['POST'], 'user/deposit_request_otc', 'App\\Http\\Controllers\\Front\\DepositController@deposit_request_otc'],
            'legacy_user_withdraw_page' => [['GET'], 'user/withdraw', 'App\\Http\\Controllers\\Front\\LegacyPageController@withdraw'],
            'legacy_user_withdraw_request' => [['POST'], 'user/withdraw_request', 'App\\Http\\Controllers\\Front\\WithdrawController@withdraw_request'],
            'legacy_user_withdraw_request_otc' => [['POST'], 'user/withdraw_request_OTC', 'App\\Http\\Controllers\\Front\\WithdrawController@withdraw_request_OTC'],
            'legacy_user_flow_page' => [['GET'], 'user/flow/main', 'App\\Http\\Controllers\\Front\\LegacyPageController@flow'],
            'legacy_user_flow_deposit_search' => [['POST'], 'user/flow/depositFlowSearch', 'App\\Http\\Controllers\\Front\\FlowController@depositFlowSearch'],
            'legacy_user_flow_withdrawal_search' => [['POST'], 'user/flow/withdrawalFlowSearch', 'App\\Http\\Controllers\\Front\\FlowController@withdrawalFlowSearch'],
            'legacy_user_flow_withdraw_apply_search' => [['POST'], 'user/flow/withdrawApplyFlowSearch', 'App\\Http\\Controllers\\Front\\FlowController@withdrawApplyFlowSearch'],
            'legacy_user_flow_direct_deposit_search' => [['POST'], 'user/flow/directDepositFlowSearch', 'App\\Http\\Controllers\\Front\\FlowController@directDepositFlowSearch'],
            'legacy_user_flow_direct_withdrawal_search' => [['POST'], 'user/flow/directWithdrawalFlowSearch', 'App\\Http\\Controllers\\Front\\FlowController@directWithdrawalFlowSearch'],
            'legacy_user_flow_direct_agents_deposit_search' => [['POST'], 'user/flow/directAgentsDepositFlowSearch', 'App\\Http\\Controllers\\Front\\FlowController@directDepositFlowSearch'],
            'legacy_user_flow_direct_agents_withdrawal_search' => [['POST'], 'user/flow/directAgentsWithdrawalFlowSearch', 'App\\Http\\Controllers\\Front\\FlowController@directWithdrawalFlowSearch'],
            'legacy_user_proxy_list_page' => [['GET'], 'user/proxy/list', 'App\\Http\\Controllers\\Front\\LegacyPageController@proxyList'],
            'legacy_user_proxy_confirm_page' => [['GET'], 'user/proxy/confirm', 'App\\Http\\Controllers\\Front\\LegacyPageController@proxyConfirm'],
            'legacy_user_proxy_direct_customer_page' => [['GET'], 'user/proxy/direct_cust_detail/{puid}', 'App\\Http\\Controllers\\Front\\LegacyPageController@proxyDirectCustomerDetail'],
            'legacy_user_proxy_list_search' => [['POST'], 'user/proxy/proxyListSearch', 'App\\Http\\Controllers\\Front\\AgentController@proxyListSearch'],
            'legacy_user_proxy_confirm_search' => [['POST'], 'user/proxy/proxyConfirmSearch', 'App\\Http\\Controllers\\Front\\AgentController@proxyConfirmSearch'],
            'legacy_user_proxy_confirm_change' => [['POST'], 'user/proxy/confirmLevelChange', 'App\\Http\\Controllers\\Front\\AgentController@confirmLevelChange'],
            'legacy_user_proxy_direct_customer_list' => [['POST'], 'user/proxy/direct_cust_detail_list', 'App\\Http\\Controllers\\Front\\AgentController@directCustDetailList'],
            'legacy_user_proxy_group_list' => [['POST'], 'user/proxy/getSubAgentsGrpIdList', 'App\\Http\\Controllers\\Front\\AgentController@getSubAgentsGrpIdList'],
            'legacy_user_proxy_parent_path' => [['POST'], 'user/proxy/parentPath', 'App\\Http\\Controllers\\Front\\AgentController@getParentPath'],
            'legacy_user_proxy_commission_transfer_page' => [['GET'], 'user/proxy/direct_user_commTrans_browse/{uid}', 'App\\Http\\Controllers\\Front\\LegacyPageController@commissionTransfer'],
            'legacy_user_proxy_commission_transfer' => [['POST'], 'user/proxy/directUserCommTrans', 'App\\Http\\Controllers\\Front\\AgentController@directUserCommTrans'],
            'legacy_user_position_summary_page' => [['GET'], 'user/position/summary', 'App\\Http\\Controllers\\Front\\LegacyPageController@positionSummary'],
            // 该旧路径是免登录的实时返佣任务入口，必须路由到结算控制器，不能误渲染持仓汇总页面。
            'legacy_user_position_comm_summary_page' => [['GET'], 'user/position/comm_summary', 'App\\Http\\Controllers\\Front\\LegacyCommissionSummaryController@commSummary'],
            // V2 是旧点差返佣任务入口，必须调用专用结算控制器，不能回退为持仓页面渲染。
            'legacy_user_position_comm_summary_v2_page' => [['GET'], 'user/position/comm_summaryv2', 'App\\Http\\Controllers\\Front\\LegacySpreadCommissionSummaryController@commSummaryV2'],
            'legacy_user_position_summary_detail_page' => [['GET'], 'user/position/summary/deatil/{id}', 'App\\Http\\Controllers\\Front\\LegacyPageController@positionSummary'],
            'legacy_user_position_summary_search' => [['POST'], 'user/position/positionSummarySearch', 'App\\Http\\Controllers\\Front\\PositionController@positionSummary'],
            'legacy_user_position_sub_agents_search' => [['POST'], 'user/position/v2/subAgentsListSearchV2', 'App\\Http\\Controllers\\Front\\PositionController@subPositionSummary'],
            'legacy_user_position_click_search' => [['POST'], 'user/position/v2/positionSummaryClickSearch', 'App\\Http\\Controllers\\Front\\PositionController@clickSearch'],
            // summary2 只展示当前登录用户的 MT4 汇总，不能指向具备代理树钻取能力的 positionSummary 页面。
            'legacy_user_position_summary2_page' => [['GET'], 'user/position/summary2', 'App\\Http\\Controllers\\Front\\LegacyPageController@positionSummary2'],
            'legacy_user_position_summary2_search' => [['POST'], 'user/position/positionSummary2Search', 'App\\Http\\Controllers\\Front\\PositionController@positionSummary2Search'],
            'legacy_user_close_order_page' => [['GET'], 'user/close/order', 'App\\Http\\Controllers\\Front\\LegacyPageController@orderClosed'],
            'legacy_user_close_order_search' => [['POST'], 'user/close/closeOrderSearch', 'App\\Http\\Controllers\\Front\\OrderController@closeOrderSearch'],
            'legacy_user_open_order_page' => [['GET'], 'user/open/order', 'App\\Http\\Controllers\\Front\\LegacyPageController@orderOpen'],
            'legacy_user_open_order_search' => [['POST'], 'user/open/openOrderSearch', 'App\\Http\\Controllers\\Front\\OrderController@openOrderSearch'],
            'legacy_user_close_order2_page' => [['GET'], 'user/close/order2', 'App\\Http\\Controllers\\Front\\LegacyPageController@orderClosed'],
            'legacy_user_close_order2_search' => [['POST'], 'user/close/closeOrder2Search', 'App\\Http\\Controllers\\Front\\OrderController@closeOrder2Search'],
            'legacy_user_open_order2_page' => [['GET'], 'user/open/order2', 'App\\Http\\Controllers\\Front\\LegacyPageController@orderOpen'],
            'legacy_user_open_order2_search' => [['POST'], 'user/open/openOrder2Search', 'App\\Http\\Controllers\\Front\\OrderController@openOrder2Search'],
            'legacy_user_realtime_rebate_page' => [['GET'], 'user/realtime/rebate', 'App\\Http\\Controllers\\Front\\LegacyPageController@realtimeRebate'],
            'legacy_user_realtime_rebate_search' => [['POST'], 'user/realtime/realtimeRebateSearch', 'App\\Http\\Controllers\\Front\\CommissionController@realtimeRebateSearch'],
            'legacy_user_customer_list_page' => [['GET'], 'user/cust/list', 'App\\Http\\Controllers\\Front\\LegacyPageController@customerList'],
            'legacy_user_customer_change_list_page' => [['GET'], 'user/change/list', 'App\\Http\\Controllers\\Front\\LegacyPageController@groupChange'],
            'legacy_user_customer_change_group_page' => [['GET'], 'user/cust/change/group/{uid}', 'App\\Http\\Controllers\\Front\\LegacyPageController@groupChange'],
            'legacy_user_customer_change_group_edit' => [['POST'], 'user/cust/change/group_edit', 'App\\Http\\Controllers\\Front\\AgentController@changeDirectCustGroupEdit'],
            'legacy_user_customer_direct_list_search' => [['POST'], 'user/cust/directCustListSearch', 'App\\Http\\Controllers\\Front\\AgentController@directCustListSearch'],
            'legacy_user_customer_direct_change_search' => [['POST'], 'user/cust/directCustChangeListSearch', 'App\\Http\\Controllers\\Front\\AgentController@directCustChangeListSearch'],
            'legacy_user_address_page' => [['GET'], 'user/address/list', 'App\\Http\\Controllers\\Front\\LegacyPageController@address'],
            'legacy_user_address_add_page' => [['GET'], 'user/address/add', 'App\\Http\\Controllers\\Front\\LegacyPageController@address'],
            'legacy_user_address_edit_page' => [['GET'], 'user/address/info/{recId}', 'App\\Http\\Controllers\\Front\\LegacyPageController@address'],
            'legacy_user_address_search' => [['POST'], 'user/address/search', 'App\\Http\\Controllers\\Front\\GiftController@addressSearch'],
            'legacy_user_address_update' => [['POST'], 'user/address/update', 'App\\Http\\Controllers\\Front\\GiftController@addressUpdate'],
            'legacy_user_gift_page' => [['GET'], 'user/gift/list', 'App\\Http\\Controllers\\Front\\LegacyPageController@gift'],
            'legacy_user_gift_search' => [['POST'], 'user/gift/search', 'App\\Http\\Controllers\\Front\\GiftController@giftSearch'],
            'legacy_user_news_page' => [['GET'], 'user/news_list_browse', 'App\\Http\\Controllers\\Front\\LegacyPageController@news'],
            'legacy_user_news_list_search' => [['POST'], 'user/newsListSearch', 'App\\Http\\Controllers\\Front\\NewsController@newsListSearch'],
            'legacy_user_voucher_browse_page' => [['GET'], 'user/voucher/voucher_browse', 'App\\Http\\Controllers\\Front\\LegacyPageController@voucher'],
            'legacy_user_voucher_search' => [['POST'], 'user/voucher/voucherSearch', 'App\\Http\\Controllers\\Front\\AccountController@voucherList'],
        ];

        foreach ($legacyRoutes as $name => [$verbs, $uri, $action]) {
            $this->assertTrue(Route::has($name), $name . ' route is missing.');

            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($action, $route->getActionName());
            $this->assertSame($uri, $route->uri());

            foreach ($verbs as $verb) {
                $this->assertContains($verb, $route->methods(), $name . ' does not support ' . $verb . '.');
            }

            [$controller, $method] = explode('@', $action);
            $this->assertTrue(method_exists($controller, $method), $action . ' method is missing.');
        }
    }

    public function test_front_legacy_user_maintenance_and_big_agent_routes_are_registered(): void
    {
        $legacyRoutes = [
            'legacy_import_user' => [['GET'], 'importUser', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@importUser'],
            'legacy_import_agents' => [['GET'], 'importAgents', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@importAgents'],
            'legacy_sync_to_t4_by_local_agents' => [['GET'], 'syncToT4ByLocalAgents', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@syncToT4ByLocalAgents'],
            'legacy_sync_to_t4_by_local_user' => [['POST'], 'syncToT4ByLocalUser', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@syncToT4ByLocalUser'],
            'legacy_local_register_notify_by_agents' => [['POST'], 'localRegisterNotifyByAgents', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@localRegisterNotifyByAgents'],
            'legacy_sync_agents' => [['POST'], 'syncAgents', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@syncAgents'],
            'legacy_sync_user' => [['POST'], 'syncUser', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@syncUser'],
            'legacy_sync_disable_user_to_t4' => [['POST'], 'syncDisableUserToT4', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@syncDisableUserToT4'],
            'legacy_import_lang' => [['GET'], 'importLang', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@importLang'],
            'legacy_test_register_page' => [['GET'], 'test', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testRegisterPage'],
            'legacy_test_hello_register' => [['POST'], 'test/helloRegister', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testHelloRegister'],
            'legacy_test_deposit' => [['POST'], 'test/deposit', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testDeposit'],
            'legacy_test_withdraw' => [['POST'], 'test/withdraw', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testWithdraw'],
            'legacy_test_account_info' => [['POST'], 'test/getAccountInfo', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testGetAccountInfo'],
            'legacy_test_rights_sum' => [['GET'], 'test_rights_sum', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testRightsSum'],
            'legacy_test_info' => [['GET'], 'test_info', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testInfo'],
            'legacy_test_sms' => [['GET'], 'test_sms', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testSms'],
            'legacy_test_search' => [['GET'], 'test_serach/{id}', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testSearch'],
            'legacy_test_export' => [['POST'], 'test_export', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testExport'],
            'legacy_test_order' => [['GET'], 'test_order', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testOrder'],
            'legacy_trades_exp_zero' => [['GET'], 'trades_exp_zero', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@tradesExpZero'],
            'legacy_whs_test' => [['GET'], 'whstest', 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@whsTest'],
            'legacy_agents_login_page' => [['GET'], 'agents/login', 'App\\Http\\Controllers\\Front\\BigNumberController@agentsLogin'],
            'legacy_user_agents_sign_in' => [['POST'], 'user/agents/signIn', 'App\\Http\\Controllers\\Front\\BigNumberController@agentsSignIn'],
            'legacy_user_agents_index' => [['GET'], 'user/agents/index', 'App\\Http\\Controllers\\Front\\BigNumberController@agentsIndex'],
            'legacy_user_agents_logout' => [['GET'], 'user/agents/loginOut', 'App\\Http\\Controllers\\Front\\BigNumberController@loginOut'],
            'legacy_user_agents_main_home' => [['GET'], 'user/agents/main/home', 'App\\Http\\Controllers\\Front\\BigNumberController@agentsMainHome'],
            'legacy_user_agents_proxy_list' => [['GET'], 'user/agents/proxy/list', 'App\\Http\\Controllers\\Front\\BigNumberController@proxy_agents_list_browse'],
            'legacy_user_agents_proxy_search' => [['POST'], 'user/agents/proxy/proxySearch', 'App\\Http\\Controllers\\Front\\BigNumberController@bigNumberListSearch'],
            'legacy_user_agents_proxy_search_by_sub' => [['POST'], 'user/agents/proxy/proxySearchBySub', 'App\\Http\\Controllers\\Front\\BigNumberController@bigNumberListSearchBySubAgents'],
            'legacy_user_agents_position_summary' => [['GET'], 'user/agents/position/summary', 'App\\Http\\Controllers\\Front\\BigNumberController@position_agents_summary_browse'],
            'legacy_user_agents_position_search' => [['POST'], 'user/agents/position/positionSummarySearch', 'App\\Http\\Controllers\\Front\\BigNumberController@bigPositionSummarySearch'],
            'legacy_user_agents_position_sub_search' => [['POST'], 'user/agents/position/subAgentsListSearch', 'App\\Http\\Controllers\\Front\\BigNumberController@bigSubPositionSummaryStats'],
            'legacy_user_agents_close_order' => [['GET'], 'user/agents/close/order', 'App\\Http\\Controllers\\Front\\BigNumberController@big_close_order_browse'],
            'legacy_user_agents_close_order_search' => [['POST'], 'user/agents/close/closeOrderSearch', 'App\\Http\\Controllers\\Front\\BigNumberController@bigCloseOrderSearch'],
            'legacy_user_agents_open_order' => [['GET'], 'user/agents/open/order', 'App\\Http\\Controllers\\Front\\BigNumberController@big_open_order_browse'],
            'legacy_user_agents_open_order_search' => [['POST'], 'user/agents/open/openOrderSearch', 'App\\Http\\Controllers\\Front\\BigNumberController@bigOpenOrderSearch'],
            'legacy_user_agents_change_password' => [['POST'], 'user/agents/changePassword', 'App\\Http\\Controllers\\Front\\BigNumberController@changePasswordSave'],
        ];

        foreach ($legacyRoutes as $name => [$verbs, $uri, $action]) {
            $this->assertTrue(Route::has($name), $name . ' route is missing.');

            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($action, $route->getActionName());
            $this->assertSame($uri, $route->uri());

            foreach ($verbs as $verb) {
                $this->assertContains($verb, $route->methods(), $name . ' does not support ' . $verb . '.');
            }

            [$controller, $method] = explode('@', $action);
            $this->assertTrue(method_exists($controller, $method), $action . ' method is missing.');
        }
    }

    public function test_front_legacy_payment_callback_routes_are_registered(): void
    {
        $legacyRoutes = [
            'legacy_user_deposit_notify' => ['user/deposit_notfiy', 'POST'],
            'legacy_user_deposit_notify2' => ['user/deposit_notfiy2', 'POST'],
            'legacy_user_deposit_tigerpay_notify' => ['user/deposit_tigerpay_notify', 'POST'],
            'legacy_user_deposit_wppay_notify' => ['user/deposit_wppay_notify', 'POST'],
            'legacy_user_deposit_wppay_return' => ['user/deposit_wppay_return', 'GET'],
            'legacy_user_deposit_exlink_bbnotify' => ['user/deposit_exlink_bbnotify', 'POST'],
            'legacy_user_deposit_exlink_bbreturn' => ['user/deposit_exlink_bbreturn', 'GET'],
            'legacy_user_deposit_exlink_fbnotify' => ['user/deposit_exlink_fbnotify', 'POST'],
            'legacy_user_deposit_exlink_fbreturn' => ['user/deposit_exlink_fbreturn', 'GET'],
            'legacy_user_deposit_btb_notify' => ['user/deposit_btb_notify', 'POST'],
            'legacy_user_deposit_btb_return' => ['user/deposit_btb_return', 'GET'],
            'legacy_user_deposit_passto_notify' => ['user/deposit_passto_notify', 'POST'],
            'legacy_user_deposit_switch_notify' => ['user/deposit_switch_notify', 'POST'],
            'legacy_user_deposit_notify_otc' => ['user/deposit_notfiy_otc', 'POST'],
            'legacy_user_withdraw_notify_otc' => ['user/withdraw_notfiy_otc', 'POST'],
            'legacy_user_withdraw_verify_otc' => ['user/withdraw_verify_otc', 'POST'],
            'legacy_user_deposit_return' => ['user/deposit_return', 'GET'],
            'legacy_user_deposit_return2' => ['user/deposit_return2', 'GET'],
        ];

        foreach ($legacyRoutes as $name => [$uri, $method]) {
            $this->assertTrue(Route::has($name), $name . ' route is missing.');

            $route = Route::getRoutes()->getByName($name);
            $this->assertSame('App\\Http\\Controllers\\Front\\PaymentNotifyController@legacyCallback', $route->getActionName());
            $this->assertSame($uri, $route->uri());
            $this->assertContains($method, $route->methods(), $name . ' does not support ' . $method . '.');
            $this->assertNotContains($method === 'GET' ? 'POST' : 'GET', $route->methods(), $name . ' exposes an unsafe extra method.');
        }
    }

    public function test_front_legacy_named_route_aliases_are_registered(): void
    {
        $legacyAliases = [
            'login' => 'user/login',
            'indexLogin' => 'user/index/login',
            'agentsLogin' => 'agents/login',
            'registerIntoUrl' => 'user/register/registerinto',
            'testemail' => 'user/register/testemail',
            'show.user.info.detail' => 'show/user_detail/{userId}/{role}',
            'user.agents.path' => 'user/agents/relationShipHtml',
            'user.login.captcha' => 'user/captcha',
            'user.loginUrl' => 'user/signIn',
            'userIndex' => 'user/index',
            'userIndexIndex' => 'user/index/index',
            'indexreg' => 'user/indexreg',
            'user.front.msg' => 'user/front/message',
            'login.main.hot.news' => 'user/main/hot/news',
            'front_main_hot_news' => 'user/main/hot/newsV2',
            'front_has_show_gift_tips' => 'user/main/hasShowGiftTips',
            'singleFileUpload' => 'user/upload/file',
            'multipleFileUpload' => 'user/multiple/file',
            'user.agents.signIn' => 'user/agents/signIn',
            'agentsIndex' => 'user/agents/index',
            'user.agents.loginOut' => 'user/agents/loginOut',
            'user.agents.main.home' => 'user/agents/main/home',
            'user.agents.proxy.search' => 'user/agents/proxy/proxySearch',
            'user.sub.agents.proxy.search' => 'user/agents/proxy/proxySearchBySub',
            'user.position.summary.search' => 'user/agents/position/positionSummarySearch',
            'user.subAgents.positionSummary.search' => 'user/agents/position/subAgentsListSearch',
            'user.big.close.order.search' => 'user/agents/close/closeOrderSearch',
            'user.big.open.order.search' => 'user/agents/open/openOrderSearch',
            'user.big.change.password' => 'user/agents/changePassword',
            'user_deposit_request' => 'user/deposit_request',
            'user_deposit_request_otc' => 'user/deposit_request_otc',
            'user_deposit_notfiy' => 'user/deposit_notfiy',
            'user_deposit_notify2' => 'user/deposit_notfiy2',
            'tigerpay_return_url' => 'user/deposit_tigerpay_notify',
            'wp_pay_notify_url' => 'user/deposit_wppay_notify',
            'wp_pay_return_url' => 'user/deposit_wppay_return',
            'exlink_pay_bbnotify_url' => 'user/deposit_exlink_bbnotify',
            'exlink_pay_bbreturn_url' => 'user/deposit_exlink_bbreturn',
            'exlink_pay_fbnotify_url' => 'user/deposit_exlink_fbnotify',
            'exlink_pay_fbreturn_url' => 'user/deposit_exlink_fbreturn',
            'btb_pay_notify_url' => 'user/deposit_btb_notify',
            'btb_pay_return_url' => 'user/deposit_btb_return',
            'passto_pay_notify_url' => 'user/deposit_passto_notify',
            'switch_pay_notify_url' => 'user/deposit_switch_notify',
            'user_deposit_notfiy_otc' => 'user/deposit_notfiy_otc',
            'user_withdraw_notfiy_otc' => 'user/withdraw_notfiy_otc',
            'user_withdraw_verify_otc' => 'user/withdraw_verify_otc',
            'user_deposit_return' => 'user/deposit_return',
            'user_deposit_return2' => 'user/deposit_return2',
            'front.deposit.flow.search' => 'user/flow/depositFlowSearch',
            'front.withdrawal_flow_search' => 'user/flow/withdrawalFlowSearch',
            'front.withdrawal_apply_flow_search' => 'user/flow/withdrawApplyFlowSearch',
            'front.direct.deposit.flow.search' => 'user/flow/directDepositFlowSearch',
            'download' => 'user/flow/downloadfile/{file}/{role}',
            'front.direct.withdrawal.flow.search' => 'user/flow/directWithdrawalFlowSearch',
            'front.direct.agents.deposit.flow.search' => 'user/flow/directAgentsDepositFlowSearch',
            'front.direct.agents.withdrawal.flow.search' => 'user/flow/directAgentsWithdrawalFlowSearch',
            'front.proxy_list.search' => 'user/proxy/proxyListSearch',
            'front.proxy_confirm.search' => 'user/proxy/proxyConfirmSearch',
            'front.proxy_confirm.change' => 'user/proxy/confirmLevelChange',
            'front.proxy_direct_cust_detail.list.search' => 'user/proxy/direct_cust_detail_list',
            'front.parent.path' => 'user/proxy/parentPath',
            'front.position_summary.main.search' => 'user/position/positionSummarySearch',
            'front.position_summary.sub.search.v2' => 'user/position/v2/subAgentsListSearchV2',
            'front.position_summary.click.search.v2' => 'user/position/v2/positionSummaryClickSearch',
            'front.close_order.search' => 'user/close/closeOrderSearch',
            'front.open_order.search' => 'user/open/openOrderSearch',
            'front.position_summary2.search' => 'user/position/positionSummary2Search',
            'front.close_order2.search' => 'user/close/closeOrder2Search',
            'front.open_order2.search' => 'user/open/openOrder2Search',
            'front.realtime_rebate.search' => 'user/realtime/realtimeRebateSearch',
            'front.direct.cust_list.search' => 'user/cust/directCustListSearch',
            'front.direct.cust_change_list.search' => 'user/cust/directCustChangeListSearch',
            'front_address_add' => 'user/address/add',
            'front_address_search' => 'user/address/search',
            'front_address_update' => 'user/address/update',
            'front_gift_search' => 'user/gift/search',
            'front.voucher.search' => 'user/voucher/voucherSearch',
        ];

        foreach ($legacyAliases as $name => $uri) {
            $this->assertTrue(Route::has($name), $name . ' legacy route alias is missing.');
            $this->assertSame($uri, Route::getRoutes()->getByName($name)->uri());
        }
    }

    public function test_front_legacy_public_smoke_routes_do_not_crash(): void
    {
        // 中间件要求会话用户在库中存在且启用；自建 smoke 用户避免依赖历史残留数据。
        $this->ensureLegacySmokeUser(990001);

        $pageUris = [
            '/user/login',
            '/user/index?frame=1',
            '/user/center?frame=1',
            '/user/proxy/list?frame=1',
            '/user/position/summary?frame=1',
            '/user/news_list_browse?frame=1',
            '/agents/login',
        ];

        foreach ($pageUris as $uri) {
            $this->withSession(['suser' => ['user_id' => 990001]])->get($uri)->assertOk();
        }

        $this->get('/importUser')
            ->assertStatus(423)
            ->assertJsonPath('data.legacy_action', 'importUser');

        $this->get('/test_rights_sum')
            ->assertStatus(423)
            ->assertJsonPath('data.legacy_action', 'testRightsSum');

        $this->post('/test/deposit')
            ->assertStatus(423)
            ->assertJsonPath('data.legacy_action', 'testDeposit');

        $this->get('/user/deposit_notfiy')->assertStatus(405);

        $this->get('/user/deposit_return')
            ->assertRedirect('/front/deposit?gateway=legacy_default&status=pending');
    }

    public function test_front_legacy_page_routes_render_without_crashing(): void
    {
        $bigAgentId = 990099996;
        $now = time();
        // 普通用户页面同样需要真实可用会话用户，与下方大代理数据一起构成身份边界。
        $this->ensureLegacySmokeUser(990001);
        DB::table('big_agents')->where('id', $bigAgentId)->delete();
        DB::table('big_agents')->insert([
            'id' => $bigAgentId,
            'email' => 'legacy-route-compatibility@example.test',
            'username' => 'legacy-route-compatibility',
            'password' => Hash::make('password'),
            'sub_agent_ids' => '',
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $pageUris = [
            '/user/index?frame=1',
            '/user/index/index?frame=1',
            '/user/main/home?frame=1',
            '/user/agents/index?frame=1',
            '/user/agents/main/home?frame=1',
            '/user/agents/proxy/list?frame=1',
            '/user/agents/position/summary?frame=1',
            '/user/agents/close/order?frame=1',
            '/user/agents/open/order?frame=1',
            '/user/agents/editpsw?frame=1',
            '/user/center?frame=1',
            '/user/center/uploadIdCard?frame=1',
            '/user/center/uploadBank?frame=1',
            '/user/center/uploadChangeBank/1?frame=1',
            '/user/center/uploadHead_browse?frame=1',
            '/user/center/updPhoneEmail/phone?frame=1',
            '/user/center/cancelAccount?frame=1',
            '/user/editpsw?frame=1',
            '/user/voucher?frame=1',
            '/user/account?frame=1',
            '/user/deposit?frame=1',
            '/user/withdraw?frame=1',
            '/user/flow/main?frame=1',
            '/user/proxy/list?frame=1',
            '/user/proxy/confirm?frame=1',
            '/user/proxy/direct_cust_detail/1001?frame=1',
            '/user/proxy/direct_user_commTrans_browse/1001?frame=1',
            '/user/position/summary?frame=1',
            // 实时返佣会写出账意图并调用 MT4；它不是无副作用页面，专用闭环测试会注入受控网关验证。
            '/user/position/comm_summaryv2?frame=1',
            '/user/position/summary/deatil/1?frame=1',
            '/user/position/summary2?frame=1',
            '/user/close/order?frame=1',
            '/user/open/order?frame=1',
            '/user/close/order2?frame=1',
            '/user/open/order2?frame=1',
            '/user/realtime/rebate?frame=1',
            '/user/cust/list?frame=1',
            '/user/change/list?frame=1',
            '/user/cust/change/group/1001?frame=1',
            '/user/address/list?frame=1',
            '/user/address/add?frame=1',
            '/user/address/info/1?frame=1',
            '/user/gift/list?frame=1',
            '/user/news_list_browse?frame=1',
            '/user/voucher/voucher_browse?frame=1',
        ];

        foreach ($pageUris as $uri) {
            // 大代理与普通用户使用不同旧 session，测试页面渲染时也不能绕过真实身份边界。
            $session = strpos($uri, '/user/agents/') === 0
                ? ['bigAgents' => ['id' => $bigAgentId]]
                : ['suser' => ['user_id' => 990001]];
            $response = $this->withSession($session)->get($uri);
            $this->assertSame(
                200,
                $response->getStatusCode(),
                $uri . ' did not render successfully; redirect=' . (string) $response->headers->get('Location')
            );
        }
    }

    public function test_front_legacy_ajax_routes_return_json_errors_instead_of_crashing_without_guard_login(): void
    {
        $withdrawCount = DB::table('withdraw_records')->count();
        $outboxCount = DB::table('withdraw_settlement_outbox')->count();
        $legacyAjaxUris = [
            '/user/flow/depositFlowSearch',
            '/user/flow/withdrawalFlowSearch',
            '/user/deposit_request',
            '/user/withdraw_request',
            '/user/withdraw_request_OTC',
        ];

        foreach ($legacyAjaxUris as $uri) {
            $this->postJson($uri)
                ->assertOk($uri . ' should return a JSON business error instead of a 500 response.')
                ->assertJsonPath('code', ResponseCode::AUTH_FAILED);
        }

        $this->assertSame($withdrawCount, DB::table('withdraw_records')->count());
        $this->assertSame($outboxCount, DB::table('withdraw_settlement_outbox')->count());
    }

    public function test_front_legacy_ajax_routes_return_json_errors_for_stale_legacy_session_user(): void
    {
        $withdrawCount = DB::table('withdraw_records')->count();
        $outboxCount = DB::table('withdraw_settlement_outbox')->count();
        $staleSession = [
            'suser' => [
                'user_id' => 987654321,
                'user_name' => 'stale-session-user',
            ],
        ];

        $legacyAjaxUris = [
            '/user/flow/depositFlowSearch',
            '/user/flow/withdrawalFlowSearch',
            '/user/deposit_request',
            '/user/withdraw_request',
            '/user/withdraw_request_OTC',
        ];

        foreach ($legacyAjaxUris as $uri) {
            $response = $this->withSession($staleSession)->postJson($uri);
            $response
                ->assertOk($uri . ' should return a JSON business error for stale legacy suser session.')
                ->assertJsonPath('code', ResponseCode::USER_NOT_FOUND)
                ->assertSessionMissing('suser');
        }

        $this->assertSame($withdrawCount, DB::table('withdraw_records')->count());
        $this->assertSame($outboxCount, DB::table('withdraw_settlement_outbox')->count());
    }

    public function test_front_legacy_main_ajax_routes_do_not_return_server_errors_without_guard_login(): void
    {
        $legacyAjaxUris = [
            '/user/main/hot/news',
            '/user/main/hot/newsV2',
            '/user/main/hasShowGiftTips',
            '/user/change_account_save',
            '/user/user_voucher_save',
            '/user/editpsw_save',
            '/user/agents/editpsw_save',
            '/user/deposit_request',
            '/user/deposit_request_otc',
            '/user/withdraw_request',
            '/user/withdraw_request_OTC',
            '/user/flow/depositFlowSearch',
            '/user/flow/withdrawalFlowSearch',
            '/user/flow/withdrawApplyFlowSearch',
            '/user/flow/directDepositFlowSearch',
            '/user/flow/directWithdrawalFlowSearch',
            '/user/flow/directAgentsDepositFlowSearch',
            '/user/flow/directAgentsWithdrawalFlowSearch',
            '/user/proxy/proxyListSearch',
            '/user/proxy/proxyConfirmSearch',
            '/user/proxy/confirmLevelChange',
            '/user/proxy/direct_cust_detail_list',
            '/user/proxy/getSubAgentsGrpIdList',
            '/user/proxy/parentPath',
            '/user/proxy/directUserCommTrans',
            '/user/position/positionSummarySearch',
            '/user/position/v2/subAgentsListSearchV2',
            '/user/position/v2/positionSummaryClickSearch',
            '/user/position/positionSummary2Search',
            '/user/close/closeOrderSearch',
            '/user/open/openOrderSearch',
            '/user/close/closeOrder2Search',
            '/user/open/openOrder2Search',
            '/user/realtime/realtimeRebateSearch',
            '/user/cust/change/group_edit',
            '/user/cust/directCustListSearch',
            '/user/cust/directCustChangeListSearch',
            '/user/address/search',
            '/user/address/update',
            '/user/gift/search',
            '/user/newsListSearch',
            '/user/voucher/voucherSearch',
            '/user/cust/loginHistorySearch/1001',
        ];

        foreach ($legacyAjaxUris as $uri) {
            $response = $this->postJson($uri);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                $uri . ' returned a server error: ' . $response->getContent()
            );
        }
    }

    public function test_front_legacy_profile_ajax_routes_do_not_return_server_errors_without_guard_login(): void
    {
        $legacyProfileUris = [
            '/user/center/cancelVerifyInfo',
            '/user/center/cancelVerifyPassSendCode',
            '/user/center/uploadIdCard',
            '/user/center/uploadBankCard',
            '/user/center/uploadChangeBankCard',
            '/user/center/updateVerifyInfo',
            '/user/center/changeBankCardVerifyCode',
            '/user/center/updVerifyPassSendCode',
            '/user/center/changeBankCardSendCode',
            '/user/center/updatePhoneEmailInfo',
            '/user/center/ajaxCancelAccount',
            '/user/center/uploadHeadImg',
            '/user/editpsw_save',
            '/user/agents/editpsw_save',
        ];

        foreach ($legacyProfileUris as $uri) {
            $response = $this->postJson($uri);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                $uri . ' returned a server error without a front user: ' . $response->getContent()
            );
        }
    }

    public function test_front_legacy_profile_ajax_routes_do_not_return_server_errors_for_stale_legacy_session_user(): void
    {
        $staleSession = [
            'suser' => [
                'user_id' => 987654321,
                'user_name' => 'stale-session-user',
            ],
        ];

        $legacyProfileUris = [
            '/user/center/cancelVerifyInfo',
            '/user/center/cancelVerifyPassSendCode',
            '/user/center/uploadIdCard',
            '/user/center/uploadBankCard',
            '/user/center/uploadChangeBankCard',
            '/user/center/updateVerifyInfo',
            '/user/center/changeBankCardVerifyCode',
            '/user/center/updVerifyPassSendCode',
            '/user/center/changeBankCardSendCode',
            '/user/center/updatePhoneEmailInfo',
            '/user/center/ajaxCancelAccount',
            '/user/center/uploadHeadImg',
            '/user/editpsw_save',
            '/user/agents/editpsw_save',
        ];

        foreach ($legacyProfileUris as $uri) {
            $response = $this->withSession($staleSession)->postJson($uri);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                $uri . ' returned a server error for stale legacy suser session: ' . $response->getContent()
            );
        }
    }

    public function test_front_legacy_profile_ajax_routes_resolve_real_legacy_session_user(): void
    {
        $this->ensureDemoRootAgent();

        $legacySession = [
            'suser' => [
                'user_id' => 1001,
                'user_name' => 'Demo Root Agent',
            ],
        ];

        $this->withSession($legacySession)
            ->postJson('/user/center/changeBankCardVerifyCode', [
                'useremail' => 'agent@test.com',
            ])
            ->assertOk()
            ->assertJsonPath('msg', 'SUC');
    }

    private function ensureDemoRootAgent(): void
    {
        $now = time();
        $userId = 1001;
        $email = 'agent@test.com';

        DB::table('user_logins')
            ->where('email', $email)
            ->where('user_id', '!=', $userId)
            ->update(['email' => 'agent-test-conflict-' . $now . '@example.invalid']);

        $login = DB::table('user_logins')->where('user_id', $userId)->orderBy('id')->first();
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('agent123'),
            'account_type' => 1,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'updated_at' => $now,
            'deleted_at' => null,
        ];

        if ($login) {
            DB::table('user_logins')->where('id', $login->id)->update($payload);
            $loginId = (int) $login->id;
        } else {
            $loginId = (int) DB::table('user_logins')->insertGetId($payload + ['created_at' => $now]);
        }

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $loginId,
                'user_name' => 'Demo Root Agent',
                'phone' => '',
                'gender' => 1,
                'avatar' => null,
                'level_id' => 0,
                'group_id' => 0,
                'parent_id' => 0,
                'account_type' => 1,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'used_margin' => 0,
                'avail_margin' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'risk_ratio' => 0,
                'margin_amount' => 0,
                'leverage' => 0,
                'cust_vol' => '0',
                'pay_provider_id' => 0,
                'equity_ratio' => 0,
                'comm_rate' => 0,
                'is_ecn' => 0,
                'follow_parent_ecn' => 0,
                'auth_status' => 1,
                'is_mt4_synced' => 1,
                'is_mt4_enabled' => 1,
                'is_mt4_readonly' => 0,
                'is_withdrawal_allowed' => 0,
                'is_deposit_allowed' => 0,
                'is_agent_confirmed' => 1,
                'original_group' => '',
                'mt4_group' => 'demo-agent',
                'mt4_code' => 0,
                'trading_mode' => 0,
                'settle_method' => 1,
                'settle_cycle' => 1,
                'country' => '',
                'city' => '',
                'state' => '',
                'address' => '',
                'is_gift_allowed' => 1,
                'data_source' => 0,
                'remark' => 'Front legacy compatibility test user',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
