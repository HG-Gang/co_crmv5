<?php
/**
 * 前台API路由 | Front API Routes
 *
 * RouteServiceProvider 已设定:
 *   - 前缀: api/front
 *   - 命名空间: App\Http\Controllers\Front
 *
 * 因此此文件内的控制器只需写类名即可，例如 'AuthController@login'
 * 跨命名空间需用 '\' 前缀，例如 '\App\Http\Controllers\Common\UploadController@upload'
 */
use Illuminate\Support\Facades\Route;

// ========== 公开接口(无需JWT) | Public APIs ==========
Route::post('/login', 'AuthController@login')->name('front_api_login');
Route::post('/register', 'AuthController@register')->name('front_api_register');
Route::get('/registerCaptcha', 'AuthController@registerCaptcha')->name('front_api_registerCaptcha');
Route::post('/registerVerifyInfo', 'AuthController@registerVerifyInfo')->name('front_api_registerVerifyInfo');
Route::post('/registerSendCode', 'AuthController@registerSendCode')->name('front_api_registerSendCode');
Route::post('/checkEmail', 'AuthController@checkEmail')->name('front_api_checkEmail');
Route::post('/validateInviter', 'AuthController@validateInviter')->name('front_api_validateInviter');
Route::post('/forgotPasswordSendCode', 'ForgotPasswordController@sendResetCode')->name('front_api_forgotPasswordSendCode');
Route::post('/forgotPasswordReset', 'ForgotPasswordController@resetPassword')->name('front_api_forgotPasswordReset');
Route::post('/payment/notify/{gateway}', 'PaymentNotifyController@notify')->name('front_api_payment_notify');
Route::get('/payment/return/{gateway}', 'PaymentNotifyController@returnPage')->name('front_api_payment_return');
Route::post('/bigNumber/login', 'BigNumberController@login')->name('front_api_big_number_login');

// ========== JWT保护接口 | JWT Protected ==========
Route::middleware(['jwt.auth:user', 'sso:user'])->group(function () {
    Route::post('/logout', 'AuthController@logout')->name('front_api_logout');
    Route::post('/refreshToken', 'AuthController@refreshToken')->name('front_api_refreshToken');
    Route::post('/menus', 'MenuController@userMenus')->name('front_api_menus');

    // 仪表盘 | Dashboard
    Route::post('/dashboardData', 'DashboardController@dashboardData')->name('front_api_dashboardData');

    // 用户资料 | Profile
    Route::post('/profileInfo', 'ProfileController@profileInfo')->name('front_api_profileInfo');
    Route::post('/updateProfile', 'ProfileController@updateProfile')->name('front_api_updateProfile');
    Route::post('/changePassword', 'ProfileController@changePassword')->name('front_api_changePassword');
    Route::post('/changeEmail', 'ProfileController@changeEmail')->name('front_api_changeEmail');
    Route::post('/changePhone', 'ProfileController@changePhone')->name('front_api_changePhone');
    Route::post('/uploadAvatar', 'ProfileController@uploadAvatar')->name('front_api_uploadAvatar');
    Route::post('/submitIdentity', 'ProfileController@submitIdentity')->name('front_api_submitIdentity');
    Route::post('/submitBankCard', 'ProfileController@submitBankCard')->name('front_api_submitBankCard');
    Route::post('/submitBankChange', 'ProfileController@submitBankChange')->name('front_api_submitBankChange');

    // 文件上传 | Upload (跨命名空间 | Cross namespace)
    Route::post('/uploadFile', '\App\Http\Controllers\Common\UploadController@upload')->name('front_api_uploadFile');

    // 账户管理 | Account
    Route::post('/accountInfo', 'AccountController@accountInfo')->name('front_api_accountInfo');
    Route::post('/accountBalance', 'AccountController@accountBalance')->name('front_api_accountBalance');
    Route::post('/submitVoucher', 'AccountController@submitVoucher')->name('front_api_submitVoucher');
    Route::post('/voucherList', 'AccountController@voucherList')->name('front_api_voucherList');

    // 代理商 | Agent
    Route::post('/agentSubList', 'AgentController@subList')->name('front_api_agentSubList');
    Route::post('/agentCustomerList', 'AgentController@customerList')->name('front_api_agentCustomerList');
    Route::post('/agentStatistics', 'AgentController@statistics')->name('front_api_agentStatistics');
    Route::post('/agentConfirmLevel', 'AgentController@confirmLevel')->name('front_api_agentConfirmLevel');
    Route::post('/agentConfirmLevelChange', 'AgentController@confirmLevelChange')->name('front_api_agentConfirmLevelChange');
    Route::post('/agentGroupChangeList', 'AgentController@groupChangeList')->name('front_api_agentGroupChangeList');
    Route::post('/agentGroupChange', 'AgentController@groupChange')->name('front_api_agentGroupChange');
    Route::post('/userDetail', 'AgentController@userDetail')->name('front_api_userDetail');
    Route::post('/userLoginHistory', 'AgentController@userLoginHistory')->name('front_api_userLoginHistory');

    // 返佣 | Commission
    Route::post('/commissionRealTime', 'CommissionController@realTime')->name('front_api_commissionRealTime');
    Route::post('/commissionHistory', 'CommissionController@history')->name('front_api_commissionHistory');
    Route::post('/commissionTransfer', 'CommissionController@transfer')->name('front_api_commissionTransfer');

    // 入出金 | Deposit & Withdraw
    Route::post('/depositPage', 'DepositController@depositPage')->name('front_api_depositPage');
    Route::post('/submitDeposit', 'DepositController@submitDeposit')->name('front_api_submitDeposit');
    Route::post('/depositHistory', 'DepositController@depositHistory')->name('front_api_depositHistory');
    Route::post('/withdrawPage', 'WithdrawController@withdrawPage')->name('front_api_withdrawPage');
    Route::post('/submitWithdraw', 'WithdrawController@submitWithdraw')->name('front_api_submitWithdraw');
    Route::post('/withdrawHistory', 'WithdrawController@withdrawHistory')->name('front_api_withdrawHistory');

    // 流水 | Flow
    Route::post('/accountFlow', 'FlowController@accountFlow')->name('front_api_accountFlow');

    // 仓位总结 | Position
    Route::post('/positionSummary', 'PositionController@positionSummary')->name('front_api_positionSummary');
    Route::post('/subPositionSummary', 'PositionController@subPositionSummary')->name('front_api_subPositionSummary');
    Route::post('/positionDetail', 'PositionController@positionDetail')->name('front_api_positionDetail');

    // 订单 | Orders
    Route::post('/openOrders', 'OrderController@openOrders')->name('front_api_openOrders');
    Route::post('/closedOrders', 'OrderController@closedOrders')->name('front_api_closedOrders');

    // 礼品 | Gift
    Route::post('/giftAddressList', 'GiftController@addressList')->name('front_api_giftAddressList');
    Route::post('/giftAddAddress', 'GiftController@addAddress')->name('front_api_giftAddAddress');
    Route::post('/giftUpdateAddress', 'GiftController@updateAddress')->name('front_api_giftUpdateAddress');
    Route::post('/giftDeleteAddress', 'GiftController@deleteAddress')->name('front_api_giftDeleteAddress');
    Route::post('/giftList', 'GiftController@giftList')->name('front_api_giftList');
    Route::post('/newsList', 'NewsController@newsList')->name('front_api_newsList');

    // 注销申请 | Cancel
    Route::post('/cancelApply', 'CancelController@apply')->name('front_api_cancelApply');
    Route::post('/cancelStatus', 'CancelController@status')->name('front_api_cancelStatus');

    // ==================== 旧版/备用路由 | Legacy/Fallback ====================
    Route::post('/depositApply', 'DepositController@store')->name('front_api_depositApply');
    Route::post('/depositRecords', 'DepositController@records')->name('front_api_depositRecords');
    Route::post('/withdrawApply', 'WithdrawController@store')->name('front_api_withdrawApply');
    Route::post('/withdrawRecords', 'WithdrawController@records')->name('front_api_withdrawRecords');
    Route::post('/voucherSubmit', 'VoucherController@store')->name('front_api_voucherSubmit');
    Route::post('/voucherRecords', 'VoucherController@records')->name('front_api_voucherRecords');

    Route::post('/bigNumber/agentSubList', 'BigNumberController@agentSubList')->name('front_api_big_number_agent_sub');
});
