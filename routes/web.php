<?php
/**
 * Web页面路由 | Web Page Routes
 *
 * 使用view命名空间加载blade模板:
 * front_layui:: => resources/front/layui/
 * admin_layui:: => resources/admin/layui/
 * (命名空间在AppServiceProvider中注册)
 */
use Illuminate\Support\Facades\Route;

function crm_naive_front_page($path)
{
    $map = [
        'dashboard' => 'dashboard',
        'profile' => 'profile',
        'profile/edit' => 'profile',
        'profile/change-password' => 'profile',
        'profile/change-email' => 'profile',
        'account/info' => 'account',
        'account/balance' => 'account',
        'account/voucher' => 'vouchers',
        'account/cancel' => 'cancel-account',
        'deposit' => 'deposits',
        'withdraw' => 'withdrawals',
        'flow' => 'flow',
        'position/summary' => 'position-summary',
        'order/open' => 'open-orders',
        'order/closed' => 'closed-orders',
        'agent/sub' => 'agent-sub',
        'agent/customers' => 'agent-customers',
        'agent/confirm-level' => 'agent-confirm',
        'agent/group-change' => 'group-change',
        'commission/realtime' => 'commission-realtime',
        'commission/history' => 'commission-history',
        'commission/transfer' => 'commission-transfer',
        'gift/address' => 'gift-address',
        'gift/list' => 'gift-list',
        'news' => 'news',
    ];

    return $map[trim($path ?: 'dashboard', '/')] ?? 'dashboard';
}

function crm_naive_admin_page($path)
{
    $map = [
        'dashboard' => 'dashboard',
        'users' => 'users',
        'roles' => 'roles',
        'permissions' => 'permissions',
        'menus' => 'menus',
        'profile/edit' => 'admins',
        'profile/change-password' => 'admins',
    ];

    $path = trim($path ?: 'dashboard', '/');
    if (preg_match('#^users/\d+$#', $path)) {
        return 'users';
    }

    return $map[$path] ?? 'dashboard';
}

// 根路径重定向 | Root redirect
Route::get('/', function () {
    return redirect('/front/login');
});

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
        return view('front_layui::profile.index');
    })->name('profile_edit');

    Route::get('/profile/change-password', function () {
        return view('front_layui::profile.index');
    })->name('profile_change_password');

    Route::get('/profile/change-email', function () {
        return view('front_layui::profile.index');
    })->name('profile_change_email');

    // 账户管理 | Account
    Route::get('/account/info', function () {
        return view('front_layui::account.info');
    })->name('account_info');

    Route::get('/account/balance', function () {
        return view('front_layui::account.info');
    })->name('account_balance');

    Route::get('/account/voucher', function () {
        return view('front_layui::account.voucher');
    })->name('account_voucher');

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

    Route::get('/order/open', function () {
        return view('front_layui::order.open');
    })->name('order_open');

    Route::get('/order/closed', function () {
        return view('front_layui::order.closed');
    })->name('order_closed');

    // 代理管理 | Agent
    Route::get('/agent/sub', function () {
        return view('front_layui::agent.sub');
    })->name('agent_sub');

    Route::get('/agent/customers', function () {
        return view('front_layui::agent.customers');
    })->name('agent_customers');

    Route::get('/agent/confirm-level', function () {
        return view('front_layui::agent.confirm-level');
    })->name('agent_confirm_level');

    Route::get('/agent/group-change', function () {
        return view('front_layui::agent.group-change');
    })->name('agent_group_change');

    // 返佣 | Commission
    Route::get('/commission/realtime', function () {
        return view('front_layui::commission.realtime');
    })->name('commission_realtime');

    Route::get('/commission/history', function () {
        return view('front_layui::commission.history');
    })->name('commission_history');

    Route::get('/commission/transfer', function () {
        return view('front_layui::commission.transfer');
    })->name('commission_transfer');

    // 礼品 | Gift
    Route::get('/gift/address', function () {
        return view('front_layui::gift.address');
    })->name('gift_address');

    Route::get('/gift/list', function () {
        return view('front_layui::gift.list');
    })->name('gift_list');

    Route::get('/news', function () {
        return view('front_layui::news.index');
    })->name('news');

    // 兜底到新版页面；必须放在具体 Layui 路由之后。
    Route::get('/{path?}', function ($path = 'dashboard') {
        return view('naive.app', ['guard' => 'front', 'page' => crm_naive_front_page($path)]);
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

    Route::get('/profile/edit', function () {
        return view('admin_layui::profile.edit');
    })->name('profile_edit');

    Route::get('/profile/change-password', function () {
        return view('admin_layui::profile.change-password');
    })->name('profile_change_password');

    // 兜底到新版页面；必须放在具体 Layui 路由之后。
    Route::get('/{path?}', function ($path = 'dashboard') {
        return view('naive.app', ['guard' => 'admin', 'page' => crm_naive_admin_page($path)]);
    })->where('path', '.*')->name('modern_app');
});

// ========== 独立 Naive UI 页面 | Independent Naive UI Pages ==========
// 保留原 /front 与 /admin Layui 页面，新 UI 使用独立路径承载。
Route::prefix('front-naive')->name('front_naive_')->group(function () {
    Route::get('/', function () {
        return redirect('/front-naive/dashboard');
    })->name('index');

    Route::get('/login', function () {
        return view('naive.app', ['guard' => 'front', 'page' => 'login']);
    })->name('login');

    Route::get('/{path?}', function ($path = 'dashboard') {
        return view('naive.app', ['guard' => 'front', 'page' => crm_naive_front_page($path)]);
    })->where('path', '.*')->name('app');
});

Route::prefix('admin-naive')->name('admin_naive_')->group(function () {
    Route::get('/', function () {
        return redirect('/admin-naive/dashboard');
    })->name('index');

    Route::get('/login', function () {
        return view('naive.app', ['guard' => 'admin', 'page' => 'login']);
    })->name('login');

    Route::get('/{path?}', function ($path = 'dashboard') {
        return view('naive.app', ['guard' => 'admin', 'page' => crm_naive_admin_page($path)]);
    })->where('path', '.*')->name('app');
});
