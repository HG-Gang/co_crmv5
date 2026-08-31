<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 21:40
 */

/**
 * 文件功能：
 * - 承载 Web 页面路由域：view 命名空间（front_layui:: / admin_layui::）渲染 Blade 外壳页，CrmUI/Naive 独立 SPA 壳、旧前台/后台页面路由与维护调试入口。
 * - 同时提供废弃 Naive 路径到真实 Blade 命名路由的解析函数（crm_blade_front_route / crm_blade_admin_route）、根路径 langId 跳转与 svg 转图接口。
 * - 输入：HTTP 请求与中间件栈分发；输出：对应控制器动作的响应。
 * - 明确不负责：不承载控制器业务逻辑、permissions 权限字典与迁移定义（分别由控制器与迁移负责）。
 */

/**
 * Web页面路由 | Web Page Routes
 *
 * 使用view命名空间加载blade模板:
 * front_layui:: => resources/front/layui/
 * admin_layui:: => resources/admin/layui/
 * (命名空间在AppServiceProvider中注册)
 */
use App\Http\Controllers\Admin\LegacyAdminController;
use Illuminate\Support\Facades\Route;

if (! function_exists('crm_blade_front_route')) {
    /**
     * 将已废弃的 Naive 页面路径解析为真实的前台 Blade 命名路由。
     *
     * @return array{0:string,1:array<string,int|string>} 路由名称和被路径携带的参数；未知路径固定回到 Blade 仪表盘。
     */
    function crm_blade_front_route($path): array
    {
        $map = [
            'dashboard' => 'front_page_dashboard',
            'register' => 'front_page_register',
            'forgot-password' => 'front_page_forgot_password',
            'big-number/login' => 'front_page_big_number_login',
            'profile' => 'front_page_profile',
            'profile/edit' => 'front_page_profile_edit',
            'profile/change-password' => 'front_page_profile_change_password',
            'profile/change-email' => 'front_page_profile_change_email',
            'account' => 'front_page_account_info',
            'account/info' => 'front_page_account_info',
            'account/balance' => 'front_page_account_balance',
            'vouchers' => 'front_page_account_voucher',
            'account/voucher' => 'front_page_account_voucher',
            'account/voucher/browse' => 'front_page_account_voucher_browse',
            'account/cancel' => 'front_page_account_cancel',
            'cancel-account' => 'front_page_account_cancel',
            'deposits' => 'front_page_deposit',
            'deposit' => 'front_page_deposit',
            'withdrawals' => 'front_page_withdraw',
            'withdraw' => 'front_page_withdraw',
            'flow' => 'front_page_flow',
            'position-summary' => 'front_page_position_summary',
            'position/summary' => 'front_page_position_summary',
            'position/summary2' => 'front_page_position_summary2',
            'position/comm-summary' => 'front_page_position_comm_summary',
            'position/comm-summary-v2' => 'front_page_position_comm_summary_v2',
            'open-orders' => 'front_page_order_open',
            'order/open' => 'front_page_order_open',
            'order/open2' => 'front_page_order_open2',
            'closed-orders' => 'front_page_order_closed',
            'order/closed' => 'front_page_order_closed',
            'order/closed2' => 'front_page_order_closed2',
            'agent-sub' => 'front_page_agent_sub',
            'agent/sub' => 'front_page_agent_sub',
            'agent-customers' => 'front_page_agent_customers',
            'agent/customers' => 'front_page_agent_customers',
            'agent-confirm' => 'front_page_agent_confirm_level',
            'agent/confirm-level' => 'front_page_agent_confirm_level',
            'group-change' => 'front_page_agent_group_change',
            'agent/group-change' => 'front_page_agent_group_change',
            'commission-realtime' => 'front_page_commission_realtime',
            'commission/realtime' => 'front_page_commission_realtime',
            'commission-history' => 'front_page_commission_history',
            'commission/history' => 'front_page_commission_history',
            'commission-transfer' => 'front_page_commission_transfer',
            'commission/transfer' => 'front_page_commission_transfer',
            'gift-address' => 'front_page_gift_address',
            'gift/address' => 'front_page_gift_address',
            'gift/address/add' => 'front_page_gift_address_add',
            'gift-list' => 'front_page_gift_list',
            'gift/list' => 'front_page_gift_list',
            'news' => 'front_page_news',
        ];
        $path = trim((string) ($path ?: 'dashboard'), '/');

        if (preg_match('#^gift/address/info/(\d+)$#', $path, $matches)) {
            return ['front_page_gift_address_edit', ['recId' => (int) $matches[1]]];
        }
        if (preg_match('#^agent/customers/(\d+)$#', $path, $matches)) {
            return ['front_page_agent_customers_detail', ['puid' => (int) $matches[1]]];
        }
        if (preg_match('#^agent/group-change/(\d+)$#', $path, $matches)) {
            return ['front_page_agent_group_change_detail', ['uid' => (int) $matches[1]]];
        }
        if (preg_match('#^commission/transfer/(\d+)$#', $path, $matches)) {
            return ['front_page_commission_transfer_target', ['uid' => (int) $matches[1]]];
        }
        if (preg_match('#^position/summary/(?:detail|deatil)/(\d+)$#', $path, $matches)) {
            return ['front_page_position_summary_detail', ['id' => (int) $matches[1]]];
        }
        if (preg_match('#^order/open/detail/([^/]+)$#', $path, $matches)) {
            return ['front_page_order_open_detail', ['orderId' => $matches[1]]];
        }
        if (preg_match('#^order/closed/detail/([^/]+)$#', $path, $matches)) {
            return ['front_page_order_closed_detail', ['orderId' => $matches[1]]];
        }
        if (preg_match('#^commission/realtime/detail/([^/]+)$#', $path, $matches)) {
            return ['front_page_commission_realtime_detail', ['orderNo' => $matches[1]]];
        }
        if (preg_match('#^agent/customer-detail/([^/]+)/(\d+)$#', $path, $matches)) {
            return ['front_page_agent_customer_detail', ['role' => $matches[1], 'uid' => (int) $matches[2]]];
        }
        if (preg_match('#^news/detail/(\d+)$#', $path, $matches)) {
            return ['front_page_news_detail', ['newsId' => (int) $matches[1]]];
        }

        return [$map[$path] ?? 'front_page_dashboard', []];
    }
}

if (! function_exists('crm_blade_admin_route')) {
    /**
     * 将已废弃的 Naive 后台路径解析为真实的后台 Blade 命名路由。
     *
     * @return array{0:string,1:array<string,int>} 路由名称和路径参数；未知路径固定回到 Blade 仪表盘。
     */
    function crm_blade_admin_route($path): array
    {
        $map = [
            'dashboard' => 'admin_page_dashboard',
            'users' => 'admin_page_users',
            'roles' => 'admin_page_roles',
            'permissions' => 'admin_page_permissions',
            'menus' => 'admin_page_menus',
            'data-scopes' => 'admin_page_data_scopes',
            'profile/edit' => 'admin_page_profile_edit',
            'profile/change-password' => 'admin_page_profile_change_password',
            'agents' => 'admin_page_agents',
            'online-users' => 'admin_page_online_users',
            'authentications' => 'admin_page_authentications',
            'productions' => 'admin_page_productions',
            'gifts' => 'admin_page_gifts',
            'deposits' => 'admin_page_deposits',
            'deposit-imports' => 'admin_page_deposit_imports',
            'withdrawals' => 'admin_page_withdrawals',
            'withdraw/pending' => 'admin_page_withdraw_pending',
            'withdraw/processing' => 'admin_page_withdraw_processing',
            'withdraw/completed' => 'admin_page_withdraw_completed',
            'withdraw/failed' => 'admin_page_withdraw_failed',
            'withdraw-imports' => 'admin_page_withdraw_imports',
            'withdraw-flows' => 'admin_page_withdraw_flows',
            'undeposit-flows' => 'admin_page_undeposit_flows',
            'rights-summary' => 'admin_page_rights_summary',
            'position-summary' => 'admin_page_position_summary',
            'commissions' => 'admin_page_commissions',
            'realtime-commissions' => 'admin_page_realtime_commissions',
            'credit-imports' => 'admin_page_credit_imports',
            'vouchers' => 'admin_page_vouchers',
            'agent-levels' => 'admin_page_agent_levels',
            'group-configs' => 'admin_page_group_configs',
            'system-configs' => 'admin_page_system_configs',
            'exchange-rates' => 'admin_page_exchange_rates',
            'channels' => 'admin_page_channels',
            'admins' => 'admin_page_admins',
            'news' => 'admin_page_news',
            'risk' => 'admin_page_risk',
            'whs-exp-zero' => 'admin_page_whs_exp_zero',
            'blacklist' => 'admin_page_blacklist',
            'cancel-applies' => 'admin_page_cancel_applies',
            'trades' => 'admin_page_trades',
            'big-agents' => 'admin_page_big_agents',
        ];
        $path = trim((string) ($path ?: 'dashboard'), '/');

        if (preg_match('#^users/(\d+)$#', $path, $matches)) {
            return ['admin_page_users_detail', ['id' => (int) $matches[1]]];
        }

        return [$map[$path] ?? 'admin_page_dashboard', []];
    }
}

// 根路径保留旧项目 langId 查询参数；登录 Blade 可据此恢复访问者选择的语言。
Route::get('/', function () {
    return redirect()->route('front_page_login', [
        'langId' => request()->input('langId', '1'),
    ]);
});

// SVG 转图片外部调用接口：?svg=<源svg路径>&format=png|jpg|jpeg
Route::get('svg-convert', 'Controller@svgToImage')->name('svg_convert');

// ========== 旧前台 User 路由兼容 | Legacy Front User Routes ==========
// ========== Independent CrmUI Pages ==========
Route::prefix('front-crmui/big-agent')->name('front_crmui_big_agent_')->group(function () {
    Route::get('/login', 'CrmUi\\Front\\BigAgentPageController@login')->name('login');

    Route::middleware('legacy.front.auth')->group(function () {
        Route::get('/logout', 'Front\\BigNumberController@loginOut')->name('logout');
        Route::get('/dashboard', 'CrmUi\\Front\\BigAgentPageController@dashboard')->name('dashboard');
        Route::get('/{path?}', 'CrmUi\\Front\\BigAgentPageController@show')->where('path', '.*')->name('app');
    });
});

Route::prefix('front-naive/big-agent')->name('front_naive_big_agent_')->group(function () {
    Route::get('/login', 'CrmUi\Front\BigAgentPageController@login')->name('login');

    Route::middleware('legacy.front.auth')->group(function () {
        Route::get('/logout', 'Front\BigNumberController@loginOut')->name('logout');
        Route::get('/dashboard', 'CrmUi\Front\BigAgentPageController@dashboard')->name('dashboard');
        Route::get('/{path?}', 'CrmUi\Front\BigAgentPageController@show')->where('path', '.*')->name('app');
    });
});

Route::prefix('front-crmui')->name('front_crmui_')->group(function () {
    Route::get('/', 'CrmUi\Front\PageController@index')->name('index');
    Route::get('/login', 'CrmUi\Front\PageController@login')->name('login');
    Route::get('/register/{inviter_id?}', 'CrmUi\Front\PageController@register')->name('register');
    Route::get('/forgot-password', 'CrmUi\Front\PageController@forgotPassword')->name('forgot_password');
    Route::get('/big-number/login', 'CrmUi\Front\PageController@bigNumberLogin')->name('big_number_login');
    Route::get('/{path?}', 'CrmUi\Front\PageController@show')->where('path', '.*')->name('app');
});

Route::prefix('front-naive')->name('front_naive_')->group(function () {
    Route::get('/', function () {
        return redirect()->route('front_naive_app', ['path' => 'dashboard']);
    })->name('index');
    Route::get('/{path?}', 'CrmUi\Front\PageController@show')->where('path', '.*')->name('app');
});

Route::prefix('admin-crmui')->name('admin_crmui_')->group(function () {
    Route::get('/', 'CrmUi\Admin\PageController@index')->name('index');
    Route::get('/login', 'CrmUi\Admin\PageController@login')->name('login');
    Route::get('/{path?}', 'CrmUi\Admin\PageController@show')->where('path', '.*')->name('app');
});

// 旧礼品导出使用管理员隔离的一次性下载地址；读取成功后先删除文件再返回内容。
Route::get('index/admin/gift/shipment_list_download/{token}', function (string $token) {
    $admin = request()->user('admin');
    if (!$admin || (int) $admin->id <= 0) {
        abort(404);
    }

    $path = storage_path(
        'app/legacy-admin-exports/admin/' . (int) $admin->id . '/' . $token . '.csv'
    );
    if (!is_file($path)) {
        abort(404);
    }

    clearstatcache(true, $path);
    $modifiedAt = @filemtime($path);
    $expiresBefore = now()->timestamp - LegacyAdminController::LEGACY_GIFT_EXPORT_TTL_SECONDS;
    if ($modifiedAt === false) {
        abort(404);
    }
    if ($modifiedAt < $expiresBefore) {
        if (!@unlink($path)) {
            abort(500);
        }
        abort(404);
    }

    $contents = file_get_contents($path);
    if ($contents === false || !@unlink($path)) {
        abort(500);
    }

    return response($contents, 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="shipment_list.csv"',
        'Cache-Control' => 'no-store, private',
    ]);
})
    ->where('token', '[a-f0-9]{32}')
    ->middleware('legacy.admin.auth')
    ->defaults('legacy_permission_route', 'admin_api_exportGiftShipments')
    ->name('legacy_admin_gift_shipment_download');

require __DIR__ . '/legacy_admin.php';

Route::get('importUser', 'Front\LegacyMaintenanceController@importUser')->name('legacy_import_user');
Route::get('importAgents', 'Front\LegacyMaintenanceController@importAgents')->name('legacy_import_agents');
Route::get('syncToT4ByLocalAgents', 'Front\LegacyMaintenanceController@syncToT4ByLocalAgents')->name('legacy_sync_to_t4_by_local_agents');
Route::post('syncToT4ByLocalUser', 'Front\LegacyMaintenanceController@syncToT4ByLocalUser')->name('legacy_sync_to_t4_by_local_user');
Route::post('localRegisterNotifyByAgents', 'Front\LegacyMaintenanceController@localRegisterNotifyByAgents')->name('legacy_local_register_notify_by_agents');
Route::post('syncAgents', 'Front\LegacyMaintenanceController@syncAgents')->name('legacy_sync_agents');
Route::post('syncUser', 'Front\LegacyMaintenanceController@syncUser')->name('legacy_sync_user');
Route::post('syncDisableUserToT4', 'Front\LegacyMaintenanceController@syncDisableUserToT4')->name('legacy_sync_disable_user_to_t4');
Route::get('importLang', 'Front\LegacyMaintenanceController@importLang')->name('legacy_import_lang');
Route::get('test', 'Front\LegacyMaintenanceController@testRegisterPage')->name('legacy_test_register_page');
Route::post('test/helloRegister', 'Front\LegacyMaintenanceController@testHelloRegister')->name('legacy_test_hello_register');
Route::post('test/deposit', 'Front\LegacyMaintenanceController@testDeposit')->name('legacy_test_deposit');
Route::post('test/withdraw', 'Front\LegacyMaintenanceController@testWithdraw')->name('legacy_test_withdraw');
Route::post('test/getAccountInfo', 'Front\LegacyMaintenanceController@testGetAccountInfo')->name('legacy_test_account_info');
Route::get('test_rights_sum', 'Front\LegacyMaintenanceController@testRightsSum')->name('legacy_test_rights_sum');
Route::get('test_info', 'Front\LegacyMaintenanceController@testInfo')->name('legacy_test_info');
Route::get('test_sms', 'Front\LegacyMaintenanceController@testSms')->name('legacy_test_sms');
Route::get('test_serach/{id}', 'Front\LegacyMaintenanceController@testSearch')->name('legacy_test_search');
Route::post('test_export', 'Front\LegacyMaintenanceController@testExport')->name('legacy_test_export');
Route::get('test_order', 'Front\LegacyMaintenanceController@testOrder')->name('legacy_test_order');
Route::get('trades_exp_zero', 'Front\LegacyMaintenanceController@tradesExpZero')->name('legacy_trades_exp_zero');
Route::get('whstest', 'Front\LegacyMaintenanceController@whsTest')->name('legacy_whs_test');
Route::get('agents/login', 'Front\BigNumberController@agentsLogin')->name('legacy_agents_login_page');
Route::get('user/login', 'Front\AuthController@showLogin')->name('legacy_user_login_page');
Route::get('user/index/login', 'Front\AuthController@showLogin')->name('legacy_user_index_login_page');
Route::post('user/signIn', 'Front\AuthController@legacySignIn')->name('legacy_user_sign_in');
Route::post('user/index/signIn', 'Front\AuthController@legacySignIn')->name('legacy_user_index_sign_in');
Route::get('user/captcha', 'Front\AuthController@loginCaptcha')->name('legacy_user_captcha');
Route::get('user/loginOut', 'Front\LegacyPageController@logout')->name('legacy_user_logout');
Route::get('user/index', 'Front\LegacyPageController@dashboard')->name('legacy_user_index_page');
Route::get('user/index/index', 'Front\LegacyPageController@dashboard')->name('legacy_user_index_index_page');
Route::post('user/indexreg', 'Front\LegacyPageController@dashboard')->name('legacy_user_indexreg_page');
Route::get('user/main/home', 'Front\LegacyPageController@dashboard')->name('legacy_user_main_home_page');

Route::post('user/register/registerVerifyInfo', 'Front\AuthController@registerVerifyInfo')->name('legacy_user_register_verify_info');
Route::post('user/register/registerSendCode', 'Front\AuthController@registerSendCode')->name('legacy_user_register_send_code');
Route::post('user/register/registerinto', 'Front\AuthController@register')->name('legacy_user_register_into');
Route::get('user/register/captcha', 'Front\AuthController@registerCaptcha')->name('legacy_user_register_captcha');
Route::get('user/register/testemail', 'Front\AuthController@checkEmail')->name('legacy_user_register_testemail');
Route::get('user/register/testmodel', 'Front\LegacyMaintenanceController@testmodel')->name('legacy_user_register_testmodel');
Route::get('user/register/rebateDeposit', 'Front\LegacyMaintenanceController@orderRebateDeposit')->name('legacy_user_register_rebate_deposit');
Route::get('user/register/hotnews', 'Front\DashboardController@registerHotNews')->name('legacy_user_register_hotnews');
Route::post('user/offweb/feedback', 'Front\LegacyPageController@feedback')->name('legacy_user_offweb_feedback');
Route::get('user/register/{register_type?}/{user_id?}/{comm_type?}', 'Front\AuthController@legacyRegisterPage')->name('legacy_user_register_page');
Route::get('user/index/register/{register_type?}/{user_id?}/{comm_type?}', 'Front\AuthController@legacyRegisterPage')->name('legacy_user_index_register_page');
Route::get('en/user/register/{register_type?}/{user_id?}/{comm_type?}', 'Front\AuthController@legacyRegisterPage')->name('legacy_en_user_register_page');

Route::post('user/relationShip', 'Front\ProfileController@relationShip')->name('legacy_user_relationship');
Route::post('user/relationShipHtml', 'Front\ProfileController@relationShipHtml')->name('legacy_user_relationship_html');
Route::post('user/agents/relationShipHtml', 'Front\ProfileController@relationShipHtmlV2')->name('legacy_user_agents_relationship_html');

Route::get('user/forget_password', 'Front\ForgotPasswordController@showForgotPassword')->name('legacy_user_forget_password_page');
Route::post('user/check_user_info', 'Front\ForgotPasswordController@checkUserInfo')->name('legacy_user_forget_check_info');
Route::post('user/forgetpswSendCode', 'Front\ForgotPasswordController@sendResetCode')->name('legacy_user_forget_send_code');
Route::post('user/forgetPasswordInfoVerification', 'Front\ForgotPasswordController@forgetPasswordInfoVerification')->name('legacy_user_forget_verify');
Route::post('user/change_password', 'Front\ForgotPasswordController@saveChangePassword')->name('legacy_user_change_password');

Route::post('user/upload/file', 'Front\UploadController@singleFileUpload')->name('legacy_user_upload_file');
Route::post('user/multiple/file', 'Front\UploadController@multipleFileUpload')->name('legacy_user_multiple_file');

Route::post('user/agents/signIn', 'Front\BigNumberController@agentsSignIn')->name('legacy_user_agents_sign_in');
Route::get('user/agents/captcha', 'Front\BigNumberController@captcha')->name('legacy_user_agents_captcha');
Route::get('user/agents/login/captcha', 'Front\BigNumberController@captcha')->name('legacy_user_agents_login_captcha');
Route::get('user/agents/index', 'Front\BigNumberController@agentsIndex')->name('legacy_user_agents_index');
Route::get('user/agents/loginOut', 'Front\BigNumberController@loginOut')->name('legacy_user_agents_logout');
Route::get('user/agents/main/home', 'Front\BigNumberController@agentsMainHome')->name('legacy_user_agents_main_home');
Route::get('user/agents/proxy/list', 'Front\BigNumberController@proxy_agents_list_browse')->name('legacy_user_agents_proxy_list');
Route::post('user/agents/proxy/proxySearch', 'Front\BigNumberController@bigNumberListSearch')->name('legacy_user_agents_proxy_search');
Route::post('user/agents/proxy/proxySearchBySub', 'Front\BigNumberController@bigNumberListSearchBySubAgents')->name('legacy_user_agents_proxy_search_by_sub');
Route::get('user/agents/position/summary', 'Front\BigNumberController@position_agents_summary_browse')->name('legacy_user_agents_position_summary');
Route::post('user/agents/position/positionSummarySearch', 'Front\BigNumberController@bigPositionSummarySearch')->name('legacy_user_agents_position_search');
Route::post('user/agents/position/subAgentsListSearch', 'Front\BigNumberController@bigSubPositionSummaryStats')->name('legacy_user_agents_position_sub_search');
Route::get('user/agents/trade-symbols', 'Front\TradeSymbolController@index')->name('legacy_user_agents_trade_symbols');
Route::get('user/agents/close/order', 'Front\BigNumberController@big_close_order_browse')->name('legacy_user_agents_close_order');
Route::post('user/agents/close/closeOrderSearch', 'Front\BigNumberController@bigCloseOrderSearch')->name('legacy_user_agents_close_order_search');
Route::get('user/agents/open/order', 'Front\BigNumberController@big_open_order_browse')->name('legacy_user_agents_open_order');
Route::post('user/agents/open/openOrderSearch', 'Front\BigNumberController@bigOpenOrderSearch')->name('legacy_user_agents_open_order_search');
Route::post('user/agents/changePassword', 'Front\BigNumberController@changePasswordSave')->name('legacy_user_agents_change_password');

Route::get('user/front/message', 'Front\DashboardController@frontMsg')->middleware('legacy.front.auth')->name('legacy_user_front_message');
Route::post('user/main/hot/news', 'Front\DashboardController@hotNews')->name('legacy_user_hot_news');
Route::post('user/main/hot/newsV2', 'Front\DashboardController@hotNewsV2')->middleware('legacy.front.auth')->name('legacy_user_hot_news_v2');
Route::post('user/main/hasShowGiftTips', 'Front\DashboardController@hasShowGiftTips')->middleware('legacy.front.auth')->name('legacy_user_has_show_gift_tips');

Route::post('user/change_account_save', 'Front\AccountController@changeAccountSave')->name('legacy_user_change_account_save');
Route::post('user/user_voucher_save', 'Front\AccountController@userVoucherSave')->name('legacy_user_voucher_save');

Route::get('user/center', 'Front\LegacyPageController@profile')->name('legacy_user_center_page');
// 旧资料弹层 GET 入口分别渲染独立 Session 表单，避免错误回退到需要 JWT API 的整张资料中心。
Route::get('user/center/uploadIdCard', 'Front\LegacyPageController@profileIdentity')->name('legacy_user_center_upload_id_page');
Route::get('user/center/uploadBank', 'Front\LegacyPageController@profileBank')->name('legacy_user_center_upload_bank_page');
Route::get('user/center/uploadChangeBank/{type}', 'Front\LegacyPageController@profileBankChange')->name('legacy_user_center_change_bank_page');
Route::get('user/center/uploadHead_browse', 'Front\LegacyPageController@profileAvatar')->name('legacy_user_center_upload_head_page');
Route::get('user/center/updPhoneEmail/{type}', 'Front\LegacyPageController@profileContact')
    ->where('type', 'phone|email')
    ->name('legacy_user_center_phone_email_page');
Route::get('user/center/cancelAccount', 'Front\LegacyPageController@cancelAccount')->name('legacy_user_center_cancel_page');
Route::post('user/center/cancelVerifyInfo', 'Front\ProfileController@cancelVerifyInfo')->name('legacy_user_center_cancel_verify_info');
Route::post('user/center/cancelVerifyPassSendCode', 'Front\ProfileController@cancelVerifyPassSendCode')->name('legacy_user_center_cancel_verify_code');
Route::post('user/center/uploadIdCard', 'Front\ProfileController@uploadIdCard')->name('legacy_user_center_upload_id_card');
Route::post('user/center/uploadBankCard', 'Front\ProfileController@uploadBankCard')->name('legacy_user_center_upload_bank_card');
Route::post('user/center/uploadChangeBankCard', 'Front\ProfileController@uploadChangeBankCard')->name('legacy_user_center_upload_change_bank_card');
Route::post('user/center/updateVerifyInfo', 'Front\ProfileController@updateVerifyInfo')->name('legacy_user_center_update_verify_info');
Route::post('user/center/changeBankCardVerifyCode', 'Front\ProfileController@changeBankCardVerifyCode')->name('legacy_user_center_change_bank_verify_code');
Route::post('user/center/updVerifyPassSendCode', 'Front\ProfileController@updVerifyPassSendCode')->name('legacy_user_center_update_verify_code');
Route::post('user/center/changeBankCardSendCode', 'Front\ProfileController@changeBankCardSendCode')->name('legacy_user_center_change_bank_code');
Route::post('user/center/updatePhoneEmailInfo', 'Front\ProfileController@updatePhoneEmailInfo')->name('legacy_user_center_update_phone_email');
Route::post('user/center/ajaxCancelAccount', 'Front\CancelController@ajaxCancelAccount')->name('legacy_user_center_ajax_cancel');
Route::post('user/center/uploadHeadImg', 'Front\ProfileController@uploadHeadImg')->name('legacy_user_center_upload_head_img');

Route::get('user/editpsw', 'Front\LegacyPageController@profilePassword')->name('legacy_user_edit_password_page');
Route::get('user/agents/editpsw', 'Front\BigNumberController@agents_editpsw_browse')->name('legacy_user_agents_edit_password_page');
Route::post('user/editpsw_save', 'Front\ProfileController@user_editpsw_save')->name('legacy_user_edit_password_save');
Route::post('user/agents/editpsw_save', 'Front\BigNumberController@agentsEditPasswordSave')->name('legacy_user_agents_edit_password_save');
Route::get('user/voucher', 'Front\LegacyPageController@voucher')->name('legacy_user_voucher_page');
Route::get('user/account', 'Front\LegacyPageController@account')->name('legacy_user_account_page');
Route::get('user/deposit', 'Front\LegacyPageController@deposit')->name('legacy_user_deposit_page');
Route::post('user/deposit_request', 'Front\DepositController@deposit_request')->name('legacy_user_deposit_request');
Route::post('user/deposit_request_otc', 'Front\DepositController@deposit_request_otc')->name('legacy_user_deposit_request_otc');
Route::post('user/deposit_notfiy', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_notify');
Route::post('user/deposit_notfiy2', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_notify2');
Route::post('user/deposit_tigerpay_notify', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_tigerpay_notify');
Route::post('user/deposit_wppay_notify', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_wppay_notify');
Route::get('user/deposit_wppay_return', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_wppay_return');
Route::post('user/deposit_exlink_bbnotify', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_exlink_bbnotify');
Route::get('user/deposit_exlink_bbreturn', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_exlink_bbreturn');
Route::post('user/deposit_exlink_fbnotify', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_exlink_fbnotify');
Route::get('user/deposit_exlink_fbreturn', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_exlink_fbreturn');
Route::post('user/deposit_btb_notify', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_btb_notify');
Route::get('user/deposit_btb_return', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_btb_return');
Route::post('user/deposit_passto_notify', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_passto_notify');
Route::post('user/deposit_switch_notify', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_switch_notify');
Route::post('user/deposit_notfiy_otc', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_notify_otc');
Route::post('user/withdraw_notfiy_otc', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_withdraw_notify_otc');
Route::post('user/withdraw_verify_otc', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_withdraw_verify_otc');
Route::get('user/deposit_return', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_return');
Route::get('user/deposit_return2', 'Front\PaymentNotifyController@legacyCallback')->name('legacy_user_deposit_return2');
Route::get('user/withdraw', 'Front\LegacyPageController@withdraw')->name('legacy_user_withdraw_page');
Route::post('user/withdraw_request', 'Front\WithdrawController@withdraw_request')->name('legacy_user_withdraw_request');
Route::post('user/withdraw_request_OTC', 'Front\WithdrawController@withdraw_request_OTC')->name('legacy_user_withdraw_request_otc');

Route::get('user/flow/main', 'Front\LegacyPageController@flow')->name('legacy_user_flow_page');
Route::post('user/flow/depositFlowSearch', 'Front\FlowController@depositFlowSearch')->name('legacy_user_flow_deposit_search');
Route::post('user/flow/withdrawalFlowSearch', 'Front\FlowController@withdrawalFlowSearch')->name('legacy_user_flow_withdrawal_search');
Route::post('user/flow/withdrawApplyFlowSearch', 'Front\FlowController@withdrawApplyFlowSearch')->name('legacy_user_flow_withdraw_apply_search');
Route::post('user/flow/directDepositFlowSearch', 'Front\FlowController@directDepositFlowSearch')->name('legacy_user_flow_direct_deposit_search');
Route::post('user/flow/depositExport', 'Front\FlowController@depositExport')->name('legacy_user_deposit_export');
Route::get('user/flow/downloadfile/{file}/{role}', 'Front\FlowController@downloadFile')->name('legacy_user_download_file');
Route::post('user/flow/directWithdrawalFlowSearch', 'Front\FlowController@directWithdrawalFlowSearch')->name('legacy_user_flow_direct_withdrawal_search');
Route::post('user/flow/directAgentsDepositFlowSearch', 'Front\FlowController@directDepositFlowSearch')->name('legacy_user_flow_direct_agents_deposit_search');
Route::post('user/flow/directAgentsWithdrawalFlowSearch', 'Front\FlowController@directWithdrawalFlowSearch')->name('legacy_user_flow_direct_agents_withdrawal_search');

Route::get('user/proxy/list', 'Front\LegacyPageController@proxyList')->name('legacy_user_proxy_list_page');
Route::get('user/proxy/confirm', 'Front\LegacyPageController@proxyConfirm')->name('legacy_user_proxy_confirm_page');
Route::get('user/proxy/direct_cust_detail/{puid}', 'Front\LegacyPageController@proxyDirectCustomerDetail')->name('legacy_user_proxy_direct_customer_page');
Route::post('user/proxy/proxyListSearch', 'Front\AgentController@proxyListSearch')->name('legacy_user_proxy_list_search');
Route::post('user/proxy/proxyConfirmSearch', 'Front\AgentController@proxyConfirmSearch')->name('legacy_user_proxy_confirm_search');
Route::post('user/proxy/confirmLevelChange', 'Front\AgentController@confirmLevelChange')->name('legacy_user_proxy_confirm_change');
Route::post('user/proxy/direct_cust_detail_list', 'Front\AgentController@directCustDetailList')->name('legacy_user_proxy_direct_customer_list');
Route::post('user/proxy/getSubAgentsGrpIdList', 'Front\AgentController@getSubAgentsGrpIdList')->name('legacy_user_proxy_group_list');
Route::post('user/proxy/parentPath', 'Front\AgentController@getParentPath')->name('legacy_user_proxy_parent_path');
Route::get('user/proxy/direct_user_commTrans_browse/{uid}', 'Front\LegacyPageController@commissionTransfer')->name('legacy_user_proxy_commission_transfer_page');
Route::post('user/proxy/directUserCommTrans', 'Front\AgentController@directUserCommTrans')->name('legacy_user_proxy_commission_transfer');

Route::get('user/position/summary', 'Front\LegacyPageController@positionSummary')->name('legacy_user_position_summary_page');
// 旧 comm_summary 与 comm_summaryv2 都是无参数定时返佣入口，不是持仓页面；两者分别按手续费和点差规则执行真实批处理并返回 JSON 汇总。
Route::get('user/position/comm_summary', 'Front\LegacyCommissionSummaryController@commSummary')->name('legacy_user_position_comm_summary_page');
Route::get('user/position/comm_summaryv2', 'Front\LegacySpreadCommissionSummaryController@commSummaryV2')->name('legacy_user_position_comm_summary_v2_page');
Route::get('user/position/summary/deatil/{id}', 'Front\LegacyPageController@positionSummary')->name('legacy_user_position_summary_detail_page');
Route::post('user/position/positionSummarySearch', 'Front\PositionController@positionSummary')->name('legacy_user_position_summary_search');
Route::post('user/position/v2/subAgentsListSearchV2', 'Front\PositionController@subPositionSummary')->name('legacy_user_position_sub_agents_search');
Route::post('user/position/v2/positionSummaryClickSearch', 'Front\PositionController@clickSearch')->name('legacy_user_position_click_search');
// 旧 summary2 是本人 MT4 汇总页面，必须使用专用 Blade，不能误复用代理树持仓汇总页。
Route::get('user/position/summary2', 'Front\LegacyPageController@positionSummary2')->name('legacy_user_position_summary2_page');
Route::post('user/position/positionSummary2Search', 'Front\PositionController@positionSummary2Search')->name('legacy_user_position_summary2_search');

Route::get('user/close/order', 'Front\LegacyPageController@orderClosed')->name('legacy_user_close_order_page');
Route::post('user/close/closeOrderSearch', 'Front\OrderController@closeOrderSearch')->name('legacy_user_close_order_search');
Route::get('user/open/order', 'Front\LegacyPageController@orderOpen')->name('legacy_user_open_order_page');
Route::post('user/open/openOrderSearch', 'Front\OrderController@openOrderSearch')->name('legacy_user_open_order_search');
Route::get('user/close/order2', 'Front\LegacyPageController@orderClosed')->name('legacy_user_close_order2_page');
Route::post('user/close/closeOrder2Search', 'Front\OrderController@closeOrder2Search')->name('legacy_user_close_order2_search');
Route::get('user/open/order2', 'Front\LegacyPageController@orderOpen')->name('legacy_user_open_order2_page');
Route::post('user/open/openOrder2Search', 'Front\OrderController@openOrder2Search')->name('legacy_user_open_order2_search');

Route::get('user/realtime/rebate', 'Front\LegacyPageController@realtimeRebate')->name('legacy_user_realtime_rebate_page');
Route::post('user/realtime/realtimeRebateSearch', 'Front\CommissionController@realtimeRebateSearch')->name('legacy_user_realtime_rebate_search');

Route::get('user/cust/list', 'Front\LegacyPageController@customerList')->name('legacy_user_customer_list_page');
Route::get('user/change/list', 'Front\LegacyPageController@groupChange')->name('legacy_user_customer_change_list_page');
Route::get('user/cust/change/group/{uid}', 'Front\LegacyPageController@groupChange')->name('legacy_user_customer_change_group_page');
Route::post('user/cust/change/group_edit', 'Front\AgentController@changeDirectCustGroupEdit')->name('legacy_user_customer_change_group_edit');
Route::post('user/cust/directCustListSearch', 'Front\AgentController@directCustListSearch')->name('legacy_user_customer_direct_list_search');
Route::post('user/cust/directCustChangeListSearch', 'Front\AgentController@directCustChangeListSearch')->name('legacy_user_customer_direct_change_search');

Route::get('user/address/list', 'Front\LegacyPageController@address')->name('legacy_user_address_page');
Route::get('user/address/add', 'Front\LegacyPageController@address')->name('legacy_user_address_add_page');
Route::get('user/address/info/{recId}', 'Front\LegacyPageController@address')->name('legacy_user_address_edit_page');
Route::post('user/address/search', 'Front\GiftController@addressSearch')->name('legacy_user_address_search');
Route::post('user/address/update', 'Front\GiftController@addressUpdate')->name('legacy_user_address_update');
Route::get('user/gift/list', 'Front\LegacyPageController@gift')->name('legacy_user_gift_page');
Route::post('user/gift/search', 'Front\GiftController@giftSearch')->name('legacy_user_gift_search');

Route::get('show/user_detail/{userId}/{role}', 'Front\AgentController@legacyUserDetailPage')->name('legacy_user_detail');
Route::get('user/cust/show_direct_cust_info/{role}/{uid}', 'Front\AgentController@legacyUserDetailPage')->name('legacy_user_customer_detail');
Route::post('user/cust/loginHistorySearch/{uid}', 'Front\AgentController@legacyLoginHistorySearch')->name('legacy_user_customer_login_history');
Route::get('user/news_list_browse', 'Front\LegacyPageController@news')->middleware('legacy.front.auth')->name('legacy_user_news_page');
Route::get('user/news/news_detail/{newsId}', 'Front\NewsController@newsDetail')->whereNumber('newsId')->middleware('legacy.front.auth')->name('legacy_user_news_detail');
Route::post('user/newsListSearch', 'Front\NewsController@newsListSearch')->name('legacy_user_news_list_search');
Route::get('user/voucher/voucher_browse', 'Front\LegacyPageController@voucher')->name('legacy_user_voucher_browse_page');
Route::post('user/voucher/voucherSearch', 'Front\AccountController@voucherList')->name('legacy_user_voucher_search');
Route::get('open/order_detail/{orderId}/{orderType}/{role}', 'Front\OrderController@openOrderDetail')->name('legacy_user_open_order_detail');
Route::get('close/order_detail/{orderId}/{orderType}/{role}', 'Front\OrderController@closeOrderDetail')->name('legacy_user_close_order_detail');
Route::get('user/realtime/rebate_detail/{orderNo}/{role}', 'Front\CommissionController@realtimeRebateDetail')->name('legacy_user_realtime_rebate_detail');

// Legacy /user routes are protected by default. These are the exact public
// exceptions retained from the old LoginMiddleware (login, registration,
// password recovery, relationship helpers, callbacks and public detail links).
$crmLegacyPublicUserUris = [
    'user/login',
    'user/index/login',
    'user/signIn',
    'user/index/signIn',
    'user/captcha',
    'user/loginOut',
    'user/register/registerVerifyInfo',
    'user/register/registerSendCode',
    'user/register/registerinto',
    'user/register/captcha',
    'user/register/testemail',
    'user/register/testmodel',
    'user/register/rebateDeposit',
    'user/register/hotnews',
    'user/offweb/feedback',
    'user/register/{register_type?}/{user_id?}/{comm_type?}',
    'user/index/register/{register_type?}/{user_id?}/{comm_type?}',
    'user/relationShip',
    'user/relationShipHtml',
    'user/agents/relationShipHtml',
    'user/forget_password',
    'user/check_user_info',
    'user/forgetpswSendCode',
    'user/forgetPasswordInfoVerification',
    'user/change_password',
    'user/agents/signIn',
    'user/agents/captcha',
    'user/agents/login/captcha',
    'user/main/hot/news',
    'user/deposit_notfiy',
    'user/deposit_notfiy2',
    'user/deposit_tigerpay_notify',
    'user/deposit_wppay_notify',
    'user/deposit_wppay_return',
    'user/deposit_exlink_bbnotify',
    'user/deposit_exlink_bbreturn',
    'user/deposit_exlink_fbnotify',
    'user/deposit_exlink_fbreturn',
    'user/deposit_btb_notify',
    'user/deposit_btb_return',
    'user/deposit_passto_notify',
    'user/deposit_switch_notify',
    'user/deposit_notfiy_otc',
    'user/withdraw_notfiy_otc',
    'user/withdraw_verify_otc',
    'user/deposit_return',
    'user/deposit_return2',
    'user/proxy/direct_cust_detail/{puid}',
    'user/proxy/direct_cust_detail_list',
    'user/position/comm_summary',
    'user/position/comm_summaryv2',
    'user/cust/show_direct_cust_info/{role}/{uid}',
    'user/cust/loginHistorySearch/{uid}',
    'user/realtime/rebate_detail/{orderNo}/{role}',
];

foreach (Route::getRoutes()->getRoutes() as $crmLegacyRoute) {
    $crmLegacyUri = $crmLegacyRoute->uri();
    if (strpos($crmLegacyUri, 'user/') !== 0
        || in_array($crmLegacyUri, $crmLegacyPublicUserUris, true)
        || in_array('legacy.front.auth', $crmLegacyRoute->middleware(), true)) {
        continue;
    }

    $crmLegacyRoute->middleware('legacy.front.auth');
}

if (! function_exists('crm_legacy_named_route_alias')) {
    function crm_legacy_named_route_alias($methods, string $uri, string $action, string $name): void
    {
        $routes = Route::getRoutes();
        $methods = array_map('strtoupper', (array) $methods);
        $action = strpos($action, 'App\\') === 0 ? $action : 'App\\Http\\Controllers\\' . $action;
        $matchedRoute = null;

        foreach ($routes->getRoutes() as $route) {
            if ($route->uri() !== $uri || $route->getActionName() !== $action) {
                continue;
            }

            if (empty(array_diff($methods, $route->methods()))) {
                $matchedRoute = $route;
                break;
            }
        }

        if (! $matchedRoute) {
            throw new RuntimeException("Legacy route alias target is missing: {$name}");
        }

        $reflection = new ReflectionProperty(get_class($routes), 'nameList');
        $reflection->setAccessible(true);
        $nameList = $reflection->getValue($routes);
        $nameList[$name] = $matchedRoute;
        $reflection->setValue($routes, $nameList);
    }
}

$crmLegacyNamedRouteAliases = [
    [['GET'], 'user/login', 'Front\AuthController@showLogin', 'login'],
    [['GET'], 'user/index/login', 'Front\AuthController@showLogin', 'indexLogin'],
    [['GET'], 'agents/login', 'Front\BigNumberController@agentsLogin', 'agentsLogin'],
    [['POST'], 'user/register/registerinto', 'Front\AuthController@register', 'registerIntoUrl'],
    [['GET'], 'user/register/testemail', 'Front\AuthController@checkEmail', 'testemail'],
    [['GET'], 'show/user_detail/{userId}/{role}', 'Front\AgentController@legacyUserDetailPage', 'show.user.info.detail'],
    [['POST'], 'user/agents/relationShipHtml', 'Front\ProfileController@relationShipHtmlV2', 'user.agents.path'],
    [['GET'], 'user/captcha', 'Front\AuthController@loginCaptcha', 'user.login.captcha'],
    [['POST'], 'user/signIn', 'Front\AuthController@legacySignIn', 'user.loginUrl'],
    [['GET'], 'user/index', 'Front\LegacyPageController@dashboard', 'userIndex'],
    [['GET'], 'user/index/index', 'Front\LegacyPageController@dashboard', 'userIndexIndex'],
    [['POST'], 'user/indexreg', 'Front\LegacyPageController@dashboard', 'indexreg'],
    [['GET'], 'user/front/message', 'Front\DashboardController@frontMsg', 'user.front.msg'],
    [['POST'], 'user/main/hot/news', 'Front\DashboardController@hotNews', 'login.main.hot.news'],
    [['POST'], 'user/main/hot/newsV2', 'Front\DashboardController@hotNewsV2', 'front_main_hot_news'],
    [['POST'], 'user/main/hasShowGiftTips', 'Front\DashboardController@hasShowGiftTips', 'front_has_show_gift_tips'],
    [['POST'], 'user/upload/file', 'Front\UploadController@singleFileUpload', 'singleFileUpload'],
    [['POST'], 'user/multiple/file', 'Front\UploadController@multipleFileUpload', 'multipleFileUpload'],
    [['POST'], 'user/agents/signIn', 'Front\BigNumberController@agentsSignIn', 'user.agents.signIn'],
    [['GET'], 'user/agents/index', 'Front\BigNumberController@agentsIndex', 'agentsIndex'],
    [['GET'], 'user/agents/loginOut', 'Front\BigNumberController@loginOut', 'user.agents.loginOut'],
    [['GET'], 'user/agents/main/home', 'Front\BigNumberController@agentsMainHome', 'user.agents.main.home'],
    [['POST'], 'user/agents/proxy/proxySearch', 'Front\BigNumberController@bigNumberListSearch', 'user.agents.proxy.search'],
    [['POST'], 'user/agents/proxy/proxySearchBySub', 'Front\BigNumberController@bigNumberListSearchBySubAgents', 'user.sub.agents.proxy.search'],
    [['POST'], 'user/agents/position/positionSummarySearch', 'Front\BigNumberController@bigPositionSummarySearch', 'user.position.summary.search'],
    [['POST'], 'user/agents/position/subAgentsListSearch', 'Front\BigNumberController@bigSubPositionSummaryStats', 'user.subAgents.positionSummary.search'],
    [['POST'], 'user/agents/close/closeOrderSearch', 'Front\BigNumberController@bigCloseOrderSearch', 'user.big.close.order.search'],
    [['POST'], 'user/agents/open/openOrderSearch', 'Front\BigNumberController@bigOpenOrderSearch', 'user.big.open.order.search'],
    [['POST'], 'user/agents/changePassword', 'Front\BigNumberController@changePasswordSave', 'user.big.change.password'],
    [['POST'], 'user/deposit_request', 'Front\DepositController@deposit_request', 'user_deposit_request'],
    [['POST'], 'user/deposit_request_otc', 'Front\DepositController@deposit_request_otc', 'user_deposit_request_otc'],
    [['POST'], 'user/deposit_notfiy', 'Front\PaymentNotifyController@legacyCallback', 'user_deposit_notfiy'],
    [['POST'], 'user/deposit_notfiy2', 'Front\PaymentNotifyController@legacyCallback', 'user_deposit_notify2'],
    [['POST'], 'user/deposit_tigerpay_notify', 'Front\PaymentNotifyController@legacyCallback', 'tigerpay_return_url'],
    [['POST'], 'user/deposit_wppay_notify', 'Front\PaymentNotifyController@legacyCallback', 'wp_pay_notify_url'],
    [['GET'], 'user/deposit_wppay_return', 'Front\PaymentNotifyController@legacyCallback', 'wp_pay_return_url'],
    [['POST'], 'user/deposit_exlink_bbnotify', 'Front\PaymentNotifyController@legacyCallback', 'exlink_pay_bbnotify_url'],
    [['GET'], 'user/deposit_exlink_bbreturn', 'Front\PaymentNotifyController@legacyCallback', 'exlink_pay_bbreturn_url'],
    [['POST'], 'user/deposit_exlink_fbnotify', 'Front\PaymentNotifyController@legacyCallback', 'exlink_pay_fbnotify_url'],
    [['GET'], 'user/deposit_exlink_fbreturn', 'Front\PaymentNotifyController@legacyCallback', 'exlink_pay_fbreturn_url'],
    [['POST'], 'user/deposit_btb_notify', 'Front\PaymentNotifyController@legacyCallback', 'btb_pay_notify_url'],
    [['GET'], 'user/deposit_btb_return', 'Front\PaymentNotifyController@legacyCallback', 'btb_pay_return_url'],
    [['POST'], 'user/deposit_passto_notify', 'Front\PaymentNotifyController@legacyCallback', 'passto_pay_notify_url'],
    [['POST'], 'user/deposit_switch_notify', 'Front\PaymentNotifyController@legacyCallback', 'switch_pay_notify_url'],
    [['POST'], 'user/deposit_notfiy_otc', 'Front\PaymentNotifyController@legacyCallback', 'user_deposit_notfiy_otc'],
    [['POST'], 'user/withdraw_notfiy_otc', 'Front\PaymentNotifyController@legacyCallback', 'user_withdraw_notfiy_otc'],
    [['POST'], 'user/withdraw_verify_otc', 'Front\PaymentNotifyController@legacyCallback', 'user_withdraw_verify_otc'],
    [['GET'], 'user/deposit_return', 'Front\PaymentNotifyController@legacyCallback', 'user_deposit_return'],
    [['GET'], 'user/deposit_return2', 'Front\PaymentNotifyController@legacyCallback', 'user_deposit_return2'],
    [['POST'], 'user/flow/depositFlowSearch', 'Front\FlowController@depositFlowSearch', 'front.deposit.flow.search'],
    [['POST'], 'user/flow/withdrawalFlowSearch', 'Front\FlowController@withdrawalFlowSearch', 'front.withdrawal_flow_search'],
    [['POST'], 'user/flow/withdrawApplyFlowSearch', 'Front\FlowController@withdrawApplyFlowSearch', 'front.withdrawal_apply_flow_search'],
    [['POST'], 'user/flow/directDepositFlowSearch', 'Front\FlowController@directDepositFlowSearch', 'front.direct.deposit.flow.search'],
    [['GET'], 'user/flow/downloadfile/{file}/{role}', 'Front\FlowController@downloadFile', 'download'],
    [['POST'], 'user/flow/directWithdrawalFlowSearch', 'Front\FlowController@directWithdrawalFlowSearch', 'front.direct.withdrawal.flow.search'],
    [['POST'], 'user/flow/directAgentsDepositFlowSearch', 'Front\FlowController@directDepositFlowSearch', 'front.direct.agents.deposit.flow.search'],
    [['POST'], 'user/flow/directAgentsWithdrawalFlowSearch', 'Front\FlowController@directWithdrawalFlowSearch', 'front.direct.agents.withdrawal.flow.search'],
    [['POST'], 'user/proxy/proxyListSearch', 'Front\AgentController@proxyListSearch', 'front.proxy_list.search'],
    [['POST'], 'user/proxy/proxyConfirmSearch', 'Front\AgentController@proxyConfirmSearch', 'front.proxy_confirm.search'],
    [['POST'], 'user/proxy/confirmLevelChange', 'Front\AgentController@confirmLevelChange', 'front.proxy_confirm.change'],
    [['POST'], 'user/proxy/direct_cust_detail_list', 'Front\AgentController@directCustDetailList', 'front.proxy_direct_cust_detail.list.search'],
    [['POST'], 'user/proxy/parentPath', 'Front\AgentController@getParentPath', 'front.parent.path'],
    [['POST'], 'user/position/positionSummarySearch', 'Front\PositionController@positionSummary', 'front.position_summary.main.search'],
    [['POST'], 'user/position/v2/subAgentsListSearchV2', 'Front\PositionController@subPositionSummary', 'front.position_summary.sub.search.v2'],
    [['POST'], 'user/position/v2/positionSummaryClickSearch', 'Front\PositionController@clickSearch', 'front.position_summary.click.search.v2'],
    [['POST'], 'user/close/closeOrderSearch', 'Front\OrderController@closeOrderSearch', 'front.close_order.search'],
    [['POST'], 'user/open/openOrderSearch', 'Front\OrderController@openOrderSearch', 'front.open_order.search'],
    [['POST'], 'user/position/positionSummary2Search', 'Front\PositionController@positionSummary2Search', 'front.position_summary2.search'],
    [['POST'], 'user/close/closeOrder2Search', 'Front\OrderController@closeOrder2Search', 'front.close_order2.search'],
    [['POST'], 'user/open/openOrder2Search', 'Front\OrderController@openOrder2Search', 'front.open_order2.search'],
    [['POST'], 'user/realtime/realtimeRebateSearch', 'Front\CommissionController@realtimeRebateSearch', 'front.realtime_rebate.search'],
    [['POST'], 'user/cust/directCustListSearch', 'Front\AgentController@directCustListSearch', 'front.direct.cust_list.search'],
    [['POST'], 'user/cust/directCustChangeListSearch', 'Front\AgentController@directCustChangeListSearch', 'front.direct.cust_change_list.search'],
    [['GET'], 'user/address/add', 'Front\LegacyPageController@address', 'front_address_add'],
    [['POST'], 'user/address/search', 'Front\GiftController@addressSearch', 'front_address_search'],
    [['POST'], 'user/address/update', 'Front\GiftController@addressUpdate', 'front_address_update'],
    [['POST'], 'user/gift/search', 'Front\GiftController@giftSearch', 'front_gift_search'],
    [['POST'], 'user/voucher/voucherSearch', 'Front\AccountController@voucherList', 'front.voucher.search'],
];

app()->instance('crm.legacy_named_route_aliases', $crmLegacyNamedRouteAliases);

if (! function_exists('crm_register_legacy_named_route_aliases')) {
    function crm_register_legacy_named_route_aliases(): void
    {
        $aliases = app()->bound('crm.legacy_named_route_aliases')
            ? app('crm.legacy_named_route_aliases')
            : [];

        foreach ($aliases as [$methods, $uri, $action, $name]) {
            crm_legacy_named_route_alias($methods, $uri, $action, $name);
        }
    }
}

// ========== 前台页面 | Front Pages ==========
Route::prefix('front')->name('front_page_')->group(function () {
    // 不需要登录的页面 | Public pages
    Route::get('/login', function () {
        return view('front_layui::auth.login');
    })->name('login');

    Route::get('/register/{inviter_id?}', function ($inviterId = null) {
        return view('front_layui::auth.register', ['inviterId' => $inviterId]);
    })->name('register');

    Route::get('/forgot-password', function () {
        return view('front_layui::auth.forgot-password');
    })->name('forgot_password');

    Route::get('/big-number/login', function () {
        return view('front_layui::auth.big-number-login');
    })->name('big_number_login');

    Route::get('/dashboard', function () {
        return view('front_layui::dashboard.index');
    })->name('dashboard');

    Route::get('/profile', function () {
        return view('front_layui::profile.index');
    })->name('profile');

    Route::get('/profile/edit', function () {
        return view('front_layui::profile.edit');
    })->name('profile_edit');

    Route::get('/profile/change-password', function () {
        return view('front_layui::profile.change-password');
    })->name('profile_change_password');

    Route::get('/profile/change-email', function () {
        return view('front_layui::profile.change-email');
    })->name('profile_change_email');

    // 账户管理 | Account
    Route::get('/account/info', function () {
        return view('front_layui::account.info');
    })->name('account_info');

    Route::get('/account/balance', function () {
        return view('front_layui::account.balance');
    })->name('account_balance');

    Route::get('/account/voucher', function () {
        return view('front_layui::account.voucher');
    })->name('account_voucher');

    Route::get('/account/voucher/browse', function () {
        return view('front_layui::account.voucher');
    })->name('account_voucher_browse');

    Route::get('/account/cancel', function () {
        return view('front_layui::account.cancel');
    })->name('account_cancel');

    // 入出金 | Deposit & Withdraw
    Route::get('/deposit', function () {
        return view('front_layui::deposit.index');
    })->name('deposit');

    Route::get('/withdraw', function () {
        return view('front_layui::withdraw.index');
    })->name('withdraw');

    Route::get('/flow', function () {
        return view('front_layui::flow.index');
    })->name('flow');

    // 交易 | Trading
    Route::get('/position/summary', function () {
        return view('front_layui::position.summary');
    })->name('position_summary');

    Route::get('/position/summary2', function () {
        // 现代前台入口与旧 URL 共用本人汇总页面，确保两个导航入口的统计口径一致。
        return view('front_layui::position.summary2');
    })->name('position_summary2');

    Route::get('/position/comm-summary', function () {
        return view('front_layui::position.summary');
    })->name('position_comm_summary');

    Route::get('/position/comm-summary-v2', function () {
        return view('front_layui::position.summary');
    })->name('position_comm_summary_v2');

    Route::get('/position/summary/detail/{id}', function ($id) {
        return view('front_layui::position.summary', ['legacyPositionId' => (int) $id]);
    })->whereNumber('id')->name('position_summary_detail');

    Route::get('/position/summary/deatil/{id}', function ($id) {
        return view('front_layui::position.summary', ['legacyPositionId' => (int) $id]);
    })->whereNumber('id')->name('position_summary_detail_legacy_typo');

    Route::get('/order/open', function () {
        return view('front_layui::order.open');
    })->name('order_open');

    Route::get('/order/open/detail/{orderId}', function ($orderId) {
        return view('front_layui::order.open', ['legacyOrderId' => $orderId]);
    })->name('order_open_detail');

    Route::get('/order/open2', function () {
        return view('front_layui::order.open');
    })->name('order_open2');

    Route::get('/order/closed', function () {
        return view('front_layui::order.closed');
    })->name('order_closed');

    Route::get('/order/closed/detail/{orderId}', function ($orderId) {
        return view('front_layui::order.closed', ['legacyOrderId' => $orderId]);
    })->name('order_closed_detail');

    Route::get('/order/closed2', function () {
        return view('front_layui::order.closed');
    })->name('order_closed2');

    // 代理管理 | Agent
    Route::get('/agent/sub', function () {
        return view('front_layui::agent.sub');
    })->name('agent_sub');

    Route::get('/agent/customers', function () {
        return view('front_layui::agent.customers');
    })->name('agent_customers');

    Route::get('/agent/customers/{puid}', function ($puid) {
        return view('front_layui::agent.customers', ['legacyParentUserId' => (int) $puid]);
    })->whereNumber('puid')->name('agent_customers_detail');

    Route::get('/agent/customer-detail/{role}/{uid}', function ($role, $uid) {
        return view('front_layui::agent.customers', ['legacyCustomerRole' => $role, 'legacyTargetUserId' => (int) $uid]);
    })->whereNumber('uid')->name('agent_customer_detail');

    Route::get('/agent/confirm-level', function () {
        return view('front_layui::agent.confirm-level');
    })->name('agent_confirm_level');

    Route::get('/agent/group-change', function () {
        return view('front_layui::agent.group-change');
    })->name('agent_group_change');

    Route::get('/agent/group-change/{uid}', function ($uid) {
        return view('front_layui::agent.group-change', ['legacyTargetUserId' => (int) $uid]);
    })->whereNumber('uid')->name('agent_group_change_detail');

    // 返佣 | Commission
    Route::get('/commission/realtime', function () {
        return view('front_layui::commission.realtime');
    })->name('commission_realtime');

    Route::get('/commission/realtime/detail/{orderNo}', function ($orderNo) {
        return view('front_layui::commission.realtime', ['legacyOrderNo' => $orderNo]);
    })->name('commission_realtime_detail');

    Route::get('/commission/history', function () {
        return view('front_layui::commission.history');
    })->name('commission_history');

    Route::get('/commission/transfer', function () {
        return view('front_layui::commission.transfer');
    })->name('commission_transfer');

    Route::get('/commission/transfer/{uid}', function ($uid) {
        return view('front_layui::commission.transfer', ['legacyTargetUserId' => (int) $uid]);
    })->whereNumber('uid')->name('commission_transfer_target');

    // 礼品 | Gift
    Route::get('/gift/address', function () {
        return view('front_layui::gift.address');
    })->name('gift_address');

    Route::get('/gift/address/add', function () {
        return view('front_layui::gift.address');
    })->name('gift_address_add');

    Route::get('/gift/address/info/{recId}', function ($recId) {
        return view('front_layui::gift.address', ['legacyAddressId' => (int) $recId]);
    })->whereNumber('recId')->name('gift_address_edit');

    Route::get('/gift/list', function () {
        return view('front_layui::gift.list');
    })->name('gift_list');

    Route::get('/news', function () {
        return view('front_layui::news.index');
    })->name('news');

    Route::get('/news/detail/{newsId}', 'Front\NewsController@newsPage')
        ->whereNumber('newsId')
        ->name('news_detail');

    // 非数字新闻详情路径必须稳定返回 404，不能继续落入下面的前台应用兜底路由。
    Route::get('/news/detail/{invalidNewsId}', function () {
        abort(404);
    })->where('invalidNewsId', '.*')->name('news_detail_invalid');

    // 未匹配的旧页面地址统一回到等价 Blade 路由，避免客户端脚本根据路径临时拼装页面。
    Route::get('/{path?}', function ($path = 'dashboard') {
        [$routeName, $parameters] = crm_blade_front_route($path);

        return redirect()->route($routeName, array_merge(request()->query(), $parameters));
    })->where('path', '.*')->name('modern_app');
});

// ========== 后台页面 | Admin Pages ==========
Route::prefix('admin')->name('admin_page_')->group(function () {
    Route::get('/login', function () {
        return view('admin_layui::auth.login');
    })->name('login');

    Route::get('/dashboard', function () {
        return view('admin_layui::dashboard.index');
    })->name('dashboard');

    Route::get('/users', function () {
        return view('admin_layui::users.index');
    })->name('users');

    Route::get('/users/{id}', function ($id) {
        return view('admin_layui::users.detail', ['userId' => $id]);
    })->name('users_detail');

    Route::get('/roles', function () {
        return view('admin_layui::roles.index');
    })->name('roles');

    Route::get('/permissions', function () {
        return view('admin_layui::permissions.index');
    })->name('permissions');

    Route::get('/menus', function () {
        return view('admin_layui::menus.index');
    })->name('menus');

    Route::get('/data-scopes', function () {
        return view('admin_layui::data-scopes.index');
    })->name('data_scopes');

    // 后台代理管理页面：Blade 只负责渲染页面骨架，列表数据由 admin_api_agentList 按权限和数据范围返回。
    Route::get('/agents', function () {
        return view('admin_layui::agents.index');
    })->name('agents');

    // 后台在线用户页面：只渲染 Layui 查询界面，真实列表数据由 admin_api_onlineUserList 按权限返回。
    Route::get('/online-users', function () {
        return view('admin_layui::online-users.index');
    })->name('online_users');

    // 后台实名认证审核页面：渲染待审列表、已审列表和审核弹窗，真实数据由后台权限 API 返回。
    Route::get('/authentications', function () {
        return view('admin_layui::authentications.index');
    })->name('authentications');

    // 现代详情页只渲染页面骨架，认证、权限和数据范围继续由详情与审核 API 校验。
    Route::get('/authentications/{user}/detail/{mode}', function ($user, $mode) {
        return view('admin_layui::authentications.detail', [
            'authUserId' => (int) $user,
            'authMode' => $mode,
        ]);
    })
        ->where('user', '[1-9]\d*')
        ->where('mode', 'auth|show')
        ->name('authentication_detail');

    // 详情路径必须严格匹配上方契约，避免非法参数落入后台页面兜底并被重定向。
    Route::get('/authentications/{invalidDetailPath}', function () {
        abort(404);
    })
        ->where('invalidDetailPath', '.+')
        ->name('authentication_detail_invalid');

    // 后台产品/交易品种页面：只渲染 Layui 查询界面，真实列表数据由 admin_api_productionList 按权限返回。
    Route::get('/productions', function () {
        return view('admin_layui::productions.index');
    })->name('productions');

    // 后台礼品发放/发货页面：渲染发货列表、可发放地址列表和发放弹窗，真实数据由后台权限 API 返回。
    Route::get('/gifts', function () {
        return view('admin_layui::gifts.index');
    })->name('gifts');

    // 后台入金管理页面：用于审核入金记录，接口层仍由权限表与数据范围服务二次校验。
    Route::get('/deposits', function () {
        return view('admin_layui::deposits.index');
    })->name('deposits');

    // 批量入金导入页面：用于维护 deposit_imports 导入记录，真实列表和新增写入由后台 API 鉴权处理。
    Route::get('/deposit-imports', function () {
        return view('admin_layui::deposit-imports.index');
    })->name('deposit_imports');

    // 后台出金管理页面：用于处理、完成、拒绝出金申请，页面按钮只做体验控制。
    Route::get('/withdrawals', function () {
        return view('admin_layui::withdrawals.index');
    })->name('withdrawals');
    foreach (['pending' => '0', 'processing' => '1', 'completed' => '2', 'failed' => '3'] as $statusPage => $defaultStatus) {
        Route::get('/withdraw/' . $statusPage, function () use ($defaultStatus) {
            return view('admin_layui::withdrawals.index', ['defaultStatus' => $defaultStatus]);
        })->name('withdraw_' . $statusPage);
    }

    // 批量出金导入页面：用于维护 withdraw_imports 导入记录，后续可继续扩展 Excel 上传和失败重试。
    Route::get('/withdraw-imports', function () {
        return view('admin_layui::withdraw-imports.index');
    })->name('withdraw_imports');

    // 后台出金流水页面：用于核对 MT4 余额类出金交易，真实列表数据由 admin_api_withdrawFlowList 按权限和数据范围返回。
    Route::get('/withdraw-flows', function () {
        return view('admin_layui::withdraw-flows.index');
    })->name('withdraw_flows');

    // 后台未入金流水页面：用于核对待支付入金记录，真实列表数据由 admin_api_undepositFlowList 按权限和数据范围返回。
    Route::get('/undeposit-flows', function () {
        return view('admin_layui::undeposit-flows.index');
    })->name('undeposit_flows');

    // 后台权益汇总页面：用于查看 MT4 账户余额、净值、保证金和可用保证金，接口继续由权限表和数据范围服务控制。
    Route::get('/rights-summary', function () {
        return view('admin_layui::rights-summary.index');
    })->name('rights_summary');

    // 后台持仓汇总页面：按用户/代理维度展示 mt4_trades 与 symbol_prices 聚合结果，真实列表由 admin_api_positionSummaryList 按权限和数据范围返回。
    Route::get('/position-summary', function () {
        return view('admin_layui::position-summary.index');
    })->name('position_summary');

    // 后台返佣管理页面：用于查看和结算代理返佣记录。
    Route::get('/commissions', function () {
        return view('admin_layui::commissions.index');
    })->name('commissions');

    // 后台实时返佣页面：读取 mt4_trades 中命中旧 COMMENT 返佣关键词的正向余额记录，真实列表由 admin_api_realtimeCommissionList 按权限和数据范围返回。
    Route::get('/realtime-commissions', function () {
        return view('admin_layui::realtime-commissions.index');
    })->name('realtime_commissions');

    // 批量信用导入页面：用于维护 credit_imports 导入记录，真实列表和新增写入由后台 API 鉴权处理。
    Route::get('/credit-imports', function () {
        return view('admin_layui::credit-imports.index');
    })->name('credit_imports');

    // 代理等级配置页面：维护代理等级、返佣比例等基础业务配置。
    Route::get('/agent-levels', function () {
        return view('admin_layui::agent-levels.index');
    })->name('agent_levels');

    // 组别配置页面：维护客户或代理可用的业务组别。
    Route::get('/group-configs', function () {
        return view('admin_layui::group-configs.index');
    })->name('group_configs');

    // 系统配置页面：维护后台可在线调整的系统参数。
    Route::get('/system-configs', function () {
        return view('admin_layui::system-configs.index');
    })->name('system_configs');

    // 汇率配置页面：只渲染入金汇率和出金汇率表单，读取和保存统一走后台权限接口。
    Route::get('/exchange-rates', function () {
        return view('admin_layui::exchange-rates.index');
    })->name('exchange_rates');

    // 支付通道页面：维护入金/出金相关支付通道状态。
    Route::get('/channels', function () {
        return view('admin_layui::channels.index');
    })->name('channels');

    // 管理员账号页面：维护后台管理员账号信息。
    Route::get('/admins', function () {
        return view('admin_layui::admins.index');
    })->name('admins');

    // 新闻公告页面：维护前后台展示的新闻公告内容。
    Route::get('/news', function () {
        return view('admin_layui::news.index');
    })->name('news');

    // 凭证审核页面：用于查看用户提交的凭证并执行审核动作。
    Route::get('/vouchers', function () {
        return view('admin_layui::vouchers.index');
    })->name('vouchers');

    // 风控页面：用于查看当前持仓风险、追保用户和强平入口。
    Route::get('/risk', function () {
        return view('admin_layui::risk.index');
    })->name('risk');

    Route::get('/whs-exp-zero', function () {
        return view('admin_layui::whs-exp-zero.index');
    })->name('whs_exp_zero');

    // 黑名单页面：用于维护姓名、证件、邮箱、手机号等黑名单记录。
    Route::get('/blacklist', function () {
        return view('admin_layui::blacklist.index');
    })->name('blacklist');

    // 注销申请页面：用于审核用户注销申请。
    Route::get('/cancel-applies', function () {
        return view('admin_layui::cancel-applies.index');
    })->name('cancel_applies');

    // 交易订单页面：用于查看全部订单、当前持仓和历史平仓记录。
    Route::get('/trades', function () {
        return view('admin_layui::trades.index');
    })->name('trades');

    // 大代理页面：用于维护大代理账号。
    Route::get('/big-agents', function () {
        return view('admin_layui::big-agents.index');
    })->name('big_agents');

    Route::get('/profile/edit', function () {
        return view('admin_layui::profile.edit');
    })->name('profile_edit');

    Route::get('/profile/change-password', function () {
        return view('admin_layui::profile.change-password');
    })->name('profile_change_password');

    // 未匹配的旧页面地址统一回到等价 Blade 路由，避免客户端脚本根据路径临时拼装页面。
    Route::get('/{path?}', function ($path = 'dashboard') {
        [$routeName, $parameters] = crm_blade_admin_route($path);

        return redirect()->route($routeName, array_merge(request()->query(), $parameters));
    })->where('path', '.*')->name('modern_app');
});

// ========== 已废弃 Naive URL 兼容层 ==========
// 保留历史 URL 和路由名称，但只重定向到服务端 Blade 页面，不再输出 Naive 单页应用。
if (! function_exists('crm_alias_named_route')) {
    /**
     * 注册旧 Blade 路由兼容别名。
     *
     * 参数逻辑说明：
     * - $alias：alias 表示旧模板 route() 使用的名称，例如 admin.dashboard、front.password.update。
     * - $targetName：targetName 表示当前真实 Laravel 命名路由，例如 admin_page_dashboard、front_api_profile_password。
     *
     * 业务边界：
     * - 别名目标必须先在当前路由表中存在，否则立即抛出异常，避免旧模板生成错误 URL。
     * - 这里只向 Laravel 路由名称索引追加别名，不新增真实 HTTP 路由，也不改变接口权限。
     */
    function crm_alias_named_route(string $alias, string $targetName): void
    {
        $routes = Route::getRoutes();
        $targetRoute = $routes->getByName($targetName);

        if (! $targetRoute) {
            foreach ($routes->getRoutes() as $route) {
                if ($route->getName() === $targetName) {
                    $targetRoute = $route;
                    break;
                }
            }
        }

        if (! $targetRoute) {
            throw new RuntimeException("Route alias target is missing: {$alias} -> {$targetName}");
        }

        $reflection = new ReflectionProperty(get_class($routes), 'nameList');
        $reflection->setAccessible(true);
        $nameList = $reflection->getValue($routes);
        $nameList[$alias] = $targetRoute;
        $reflection->setValue($routes, $nameList);
    }
}

// 旧 Blade 路由兼容别名：alias 表示旧模板 route() 使用的名称，targetName 表示当前真实 Laravel 命名路由。
// 这些别名用于兼容 resources/views 下的历史 Blade 页面；别名目标必须先在当前路由表中存在。
$crmBladeRouteAliases = [
    'admin.dashboard' => 'admin_page_dashboard',
    'admin.login' => 'admin_page_login',
    'admin.login.post' => 'admin_api_login',
    'admin.logout' => 'admin_api_logout',
    'admin.password.form' => 'admin_page_profile_change_password',
    'admin.password.update' => 'admin_api_changePassword',
    'admin.users.data' => 'admin_api_userList',
    'admin.users.index' => 'admin_page_users',
    'admin.users.show' => 'admin_page_users_detail',
    'front.dashboard' => 'front_page_dashboard',
    'front.login' => 'front_page_login',
    'front.login.post' => 'front_api_auth_login',
    'front.logout' => 'front_api_auth_logout',
    'front.password.form' => 'front_page_profile_change_password',
    'front.password.update' => 'front_api_profile_password',
    'front.profile' => 'front_page_profile',
    'front.profile.avatar' => 'front_api_profile_avatar',
    'front.profile.index' => 'front_page_profile',
    'front.profile.update' => 'front_api_profile_update',
    'front.register' => 'front_page_register',
    'front.register.post' => 'front_api_auth_register',
    'register' => 'front_page_register',
    'user.change.password' => 'front_page_profile_change_password',
    'user.change.password.post' => 'front_api_profile_password',
    'user.dashboard' => 'front_page_dashboard',
    'user.forget.password.post' => 'front_api_auth_password_email_code',
    'user.login' => 'front_page_login',
    'user.profile' => 'front_page_profile',
    'user.profile.update' => 'front_api_profile_update',
    'user.upload.avatar' => 'front_api_profile_avatar',
];

app()->instance('crm.blade_route_aliases', $crmBladeRouteAliases);

if (! function_exists('crm_register_blade_route_aliases')) {
    function crm_register_blade_route_aliases(): void
    {
        $aliases = app()->bound('crm.blade_route_aliases')
            ? app('crm.blade_route_aliases')
            : [];

        foreach ($aliases as $alias => $targetName) {
            crm_alias_named_route($alias, $targetName);
        }
    }
}
