<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 10:32
 */

/**
 * 文件功能：
 * - 承载前台 API 路由域：前缀 api/front，控制器命名空间 App\Http\Controllers\Front。
 * - 公开接口只保留登录、注册、找回密码、验证码等无需 token 的入口；业务接口统一经 jwt.auth:user 与 sso:user 中间件保护；大数登录验证码路由带 web 中间件以复用会话。
 * - 输入：HTTP 请求与中间件栈分发；输出：对应控制器动作的响应。
 * - 明确不负责：不承载控制器业务逻辑、permissions 权限字典与迁移定义（分别由控制器与迁移负责）。
 */

/**
 * 前台 API 路由 | Front API Routes
 *
 * 功能逻辑说明：
 * - 路由前缀：api/front。
 * - 控制器命名空间：App\Http\Controllers\Front。
 * - 公开接口只负责登录、注册、找回密码等无需 token 的入口。
 * - JWT 与 SSO 保护接口必须携带前台用户 token，并通过 SingleSignOn 校验当前 token 是否仍有效。
 * - 前台菜单接口：返回当前登录用户可见的 Layui/Blade 菜单树，菜单来源为 roles、role_permissions 与 permissions 表。
 *
 * 因此此文件内的控制器只需写类名即可，例如 'AuthController@login'
 * 跨命名空间需用 '\' 前缀，例如 '\App\Http\Controllers\Common\UploadController@upload'
 */
use Illuminate\Support\Facades\Route;

Route::pattern('user', '[0-9]+');

// ========== 公开接口(无需JWT) | Public APIs ==========
Route::prefix('auth')->group(function () {
    Route::post('/login', 'AuthController@login')->name('front_api_auth_login');
    Route::post('/register', 'AuthController@register')->name('front_api_auth_register');
    Route::get('/register/captcha', 'AuthController@registerCaptcha')->name('front_api_auth_register_captcha');
    Route::post('/register/email-code', 'AuthController@registerSendCode')->name('front_api_auth_register_email_code');
    Route::post('/register/verify', 'AuthController@registerVerifyInfo')->name('front_api_auth_register_verify');
    Route::get('/email/check', 'AuthController@checkEmail')->name('front_api_auth_email_check');
    Route::get('/inviter', 'AuthController@validateInviter')->name('front_api_auth_inviter');
    Route::post('/password/email-code', 'ForgotPasswordController@sendResetCode')->name('front_api_auth_password_email_code');
    Route::post('/password/reset', 'ForgotPasswordController@resetPassword')->name('front_api_auth_password_reset');
    // 验证码路由保留浏览器会话，供未显式传 captcha_key 的兼容登录流程读取 key。
    Route::get('/big-number/captcha', 'BigNumberController@captcha')->middleware('web')->name('front_api_auth_big_number_captcha');
    Route::post('/big-number/login', 'BigNumberController@login')->name('front_api_auth_big_number_login');
});
Route::post('/payment/notify/{gateway}', 'PaymentNotifyController@notify')->name('front_api_payment_notify');
Route::get('/payment/return/{gateway}', 'PaymentNotifyController@returnPage')->name('front_api_payment_return');

// ========== JWT 与 SSO 保护接口 | JWT & SSO Protected ==========
Route::middleware(['jwt.auth:user', 'sso:user'])->group(function () {
    Route::post('/auth/logout', 'AuthController@logout')->name('front_api_auth_logout');
    Route::post('/auth/token/refresh', 'AuthController@refreshToken')->name('front_api_auth_token_refresh');
    // 前台菜单接口：返回当前登录用户可见的 Layui/Blade 菜单树，用于 agent/customer 两套前台菜单配置。
    Route::get('/navigation/menus', 'MenuController@userMenus')->name('front_api_navigation_menus');
    // 前台菜单兼容接口：保留旧调用路径，仍复用同一个 userMenus 控制器方法和同一套 DB 权限配置。
    Route::get('/menus', 'MenuController@userMenus')->name('front_api_menus');

    // 仪表盘 | Dashboard
    Route::get('/dashboard', 'DashboardController@dashboardData')->name('front_api_dashboard');

    // 用户资料 | Profile
    Route::get('/profile', 'ProfileController@profileInfo')->name('front_api_profile');
    Route::patch('/profile', 'ProfileController@updateProfile')->name('front_api_profile_update');
    Route::post('/profile/password', 'ProfileController@changePassword')->name('front_api_profile_password');
    Route::post('/profile/email', 'ProfileController@changeEmail')->name('front_api_profile_email');
    Route::post('/profile/phone', 'ProfileController@changePhone')->name('front_api_profile_phone');
    Route::post('/profile/avatar', 'ProfileController@uploadAvatar')->name('front_api_profile_avatar');
    Route::post('/profile/identity', 'ProfileController@submitIdentity')->name('front_api_profile_identity');
    Route::post('/profile/bank-card', 'ProfileController@submitBankCard')->name('front_api_profile_bank_card');
    Route::post('/profile/bank-card-change', 'ProfileController@submitBankChange')->name('front_api_profile_bank_card_change');
    Route::post('/profile/identity-card-uploads', 'ProfileController@uploadIdCard')->name('front_api_profile_identity_card_uploads');
    Route::post('/profile/bank-card-uploads', 'ProfileController@uploadBankCard')->name('front_api_profile_bank_card_uploads');
    Route::post('/profile/bank-card-change-uploads', 'ProfileController@uploadChangeBankCard')->name('front_api_profile_bank_card_change_uploads');
    Route::post('/profile/head-image', 'ProfileController@uploadHeadImg')->name('front_api_profile_head_image');
    Route::post('/profile/contact-info', 'ProfileController@updatePhoneEmailInfo')->name('front_api_profile_contact_info');
    Route::post('/profile/bank-card-change/verification-checks', 'ProfileController@changeBankCardVerifyCode')->name('front_api_profile_bank_card_change_verification_checks');
    Route::post('/profile/verification-checks', 'ProfileController@updateVerifyInfo')->name('front_api_profile_verification_checks');
    Route::post('/profile/verification-cancellation-checks', 'ProfileController@cancelVerifyInfo')->name('front_api_profile_verification_cancellation_checks');
    Route::post('/profile/verification-password/verification-codes', 'ProfileController@updVerifyPassSendCode')->name('front_api_profile_verification_password_verification_codes');
    Route::post('/profile/bank-card-change/verification-codes', 'ProfileController@changeBankCardSendCode')->name('front_api_profile_bank_card_change_verification_codes');
    Route::post('/profile/verification-cancellation/verification-codes', 'ProfileController@cancelVerifyPassSendCode')->name('front_api_profile_verification_cancellation_verification_codes');
    Route::get('/profile/relationship-path', 'ProfileController@relationShip')->name('front_api_profile_relationship_path');
    Route::get('/profile/relationship-path/html', 'ProfileController@relationShipHtml')->name('front_api_profile_relationship_path_html');
    Route::get('/profile/relationship-tree/html', 'ProfileController@relationShipHtmlV2')->name('front_api_profile_relationship_tree_html');
    // 文件上传 | Upload (跨命名空间 | Cross namespace)
    Route::post('/uploads', '\App\Http\Controllers\Common\UploadController@upload')->name('front_api_uploads_store');
    Route::post('/uploads/single', 'UploadController@singleFileUpload')->name('front_api_uploads_single');
    Route::post('/uploads/multiple', 'UploadController@multipleFileUpload')->name('front_api_uploads_multiple');

    // 账户管理 | Account
    Route::get('/account/profile', 'AccountController@accountInfo')->name('front_api_account_profile');
    Route::patch('/account/trading-profile', 'AccountController@updateTradingProfile')->name('front_api_account_trading_profile_update');
    Route::get('/account/balance', 'AccountController@accountBalance')->name('front_api_account_balance');
    Route::post('/account/voucher-submissions', 'AccountController@submitVoucher')->name('front_api_account_voucher_submissions');
    Route::get('/account/vouchers', 'AccountController@voucherList')->name('front_api_account_vouchers');

    // 代理商 | Agent
    Route::get('/agents/direct', 'AgentController@subList')->name('front_api_agents_direct');
    Route::get('/agents/direct-customers', 'AgentController@customerList')->name('front_api_agents_direct_customers');
    Route::get('/agents/statistics', 'AgentController@statistics')->name('front_api_agents_statistics');
    Route::get('/agents/level-confirmation', 'AgentController@confirmLevel')->name('front_api_agents_level_confirmation');
    Route::post('/agents/level-confirmation/changes', 'AgentController@confirmLevelChange')->name('front_api_agents_level_confirmation_changes');
    Route::get('/agents/group-changes', 'AgentController@groupChangeList')->name('front_api_agents_group_changes');
    Route::post('/agents/group-change-applications', 'AgentController@groupChange')->name('front_api_agents_group_change_applications');
    Route::get('/users/login-history', 'AgentController@userLoginHistory')->name('front_api_users_login_history');
    Route::post('/customers/commission-transfers', 'AgentController@directUserCommTrans')->name('front_api_customers_commission_transfers');
    Route::get('/users/{user}', 'AgentController@showUser')->name('front_api_users_show');
    Route::get('/agents/direct-level-options', 'AgentController@getSubAgentsGrpIdList')->name('front_api_agents_direct_level_options');
    Route::get('/agents/hierarchy-path', 'AgentController@getParentPath')->name('front_api_agents_hierarchy_path');
    Route::get('/customers/group-change-requests', 'AgentController@directCustChangeListSearch')->name('front_api_customers_group_change_requests');
    // 返佣 | Commission
    Route::get('/commissions/realtime', 'CommissionController@realTime')->name('front_api_commissions_realtime');
    Route::get('/commissions/history', 'CommissionController@history')->name('front_api_commissions_history');
    Route::post('/commissions/transfers', 'CommissionController@transfer')->name('front_api_commissions_transfers');
    Route::get('/commissions/transfer-agent-options', 'CommissionController@transferAgentOptions')->name('front_api_commissions_transfer_agent_options');

    // 入出金 | Deposit & Withdraw
    Route::get('/deposits/form-options', 'DepositController@depositPage')->name('front_api_deposits_form_options');
    Route::post('/deposits/submissions', 'DepositController@submitDeposit')->name('front_api_deposits_submissions');
    Route::get('/deposits/history', 'DepositController@depositHistory')->name('front_api_deposits_history');
    Route::get('/withdrawals/form-options', 'WithdrawController@withdrawPage')->name('front_api_withdrawals_form_options');
    Route::post('/withdrawals/submissions', 'WithdrawController@submitWithdraw')->name('front_api_withdrawals_submissions');
    Route::get('/withdrawals/history', 'WithdrawController@withdrawHistory')->name('front_api_withdrawals_history');

    // 流水 | Flow
    Route::get('/flows/account', 'FlowController@accountFlow')->name('front_api_flows_account');
    Route::get('/flows/deposits', 'FlowController@depositFlowSearch')->name('front_api_flows_deposits');
    Route::get('/flows/withdrawals', 'FlowController@withdrawalFlowSearch')->name('front_api_flows_withdrawals');
    Route::get('/flows/withdrawal-applications', 'FlowController@withdrawApplyFlowSearch')->name('front_api_flows_withdrawal_applications');
    Route::get('/flows/direct-deposits', 'FlowController@directDepositFlowSearch')->name('front_api_flows_direct_deposits');
    Route::get('/flows/direct-withdrawals', 'FlowController@directWithdrawalFlowSearch')->name('front_api_flows_direct_withdrawals');
    Route::get('/flows/direct-agent-deposits', 'FlowController@directDepositFlowSearch')->name('front_api_flows_direct_agent_deposits');
    Route::get('/flows/direct-agent-withdrawals', 'FlowController@directWithdrawalFlowSearch')->name('front_api_flows_direct_agent_withdrawals');

    // 仓位总结 | Position
    Route::get('/trade-symbols', 'TradeSymbolController@index')->name('front_api_trade_symbols');
    Route::get('/positions/summary', 'PositionController@positionSummary')->name('front_api_positions_summary');
    Route::get('/positions/self-summary', 'PositionController@selfSummary')->name('front_api_positions_self_summary');
    Route::get('/positions/direct-agent-summaries', 'PositionController@subPositionSummary')->name('front_api_positions_direct_agent_summaries');
    Route::get('/positions/trades', 'PositionController@positionDetail')->name('front_api_positions_trades');

    // 订单 | Orders
    Route::get('/orders/open', 'OrderController@openOrders')->name('front_api_orders_open');
    Route::get('/orders/closed', 'OrderController@closedOrders')->name('front_api_orders_closed');

    // 礼品 | Gift
    Route::get('/gift-addresses', 'GiftController@addressSearch')->name('front_api_gift_addresses_index');
    Route::post('/gift-addresses', 'GiftController@addAddress')->name('front_api_gift_addresses_store');
    Route::patch('/gift-addresses/{address}', 'GiftController@updateAddress')->whereNumber('address')->name('front_api_gift_addresses_update');
    Route::delete('/gift-addresses/{address}', 'GiftController@deleteAddress')->whereNumber('address')->name('front_api_gift_addresses_destroy');
    Route::get('/gifts', 'GiftController@giftList')->name('front_api_gifts');
    Route::get('/news', 'NewsController@newsList')->name('front_api_news');

    // 注销申请 | Cancel
    Route::get('/account/cancellation', 'CancelController@status')->name('front_api_account_cancellation');
    Route::post('/account/cancellation-applications', 'CancelController@apply')->name('front_api_account_cancellation_applications');

    // ==================== 旧版/备用路由 | Legacy/Fallback ====================

});
