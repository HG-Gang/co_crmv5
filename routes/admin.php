<?php
/**
 * 后台API路由 | Admin API Routes
 *
 * RouteServiceProvider 已设定:
 *   - 前缀: api/admin
 *   - 命名空间: App\Http\Controllers\Admin
 *
 * 因此此文件内的控制器只需写类名即可
 */
use Illuminate\Support\Facades\Route;

// ========== 公开接口 | Public ==========
Route::post('/login', 'AuthController@login')->name('admin_api_login');

// ========== JWT保护 | JWT Protected ==========
Route::middleware(['jwt.auth:admin', 'sso:admin'])->group(function () {
    Route::post('/logout', 'AuthController@logout')->name('admin_api_logout');
    Route::post('/refreshToken', 'AuthController@refreshToken')->name('admin_api_refreshToken');
    Route::post('/menus', 'MenuController@adminMenus')->name('admin_api_menus');

    // 仪表盘 | Dashboard
    Route::post('/dashboardData', 'AdminDashboardController@dashboardData')->name('admin_api_dashboardData');

    // 管理员资料 | Admin Profile
    Route::post('/profileInfo', 'AuthController@profileInfo')->name('admin_api_profileInfo');
    Route::post('/updateProfile', 'AuthController@updateProfile')->name('admin_api_updateProfile');
    Route::post('/changePassword', 'AuthController@changePassword')->name('admin_api_changePassword');
    Route::post('/uploadAvatar', 'AuthController@uploadAvatar')->name('admin_api_uploadAvatar');

    // 用户管理 | User Management
    Route::post('/userList', 'AdminUserController@userList')->name('admin_api_userList');
    Route::post('/userDetail', 'AdminUserController@userDetail')->name('admin_api_userDetail');
    Route::post('/updateUser', 'AdminUserController@updateUser')->name('admin_api_updateUser');
    Route::post('/changeUserStatus', 'AdminUserController@changeUserStatus')->name('admin_api_changeUserStatus');
    Route::post('/reviewAuth', 'AdminUserController@reviewAuth')->name('admin_api_reviewAuth');

    // 角色管理 | Role Management
    Route::post('/roleList', 'RoleController@roleList')->name('admin_api_roleList');
    Route::post('/createRole', 'RoleController@createRole')->name('admin_api_createRole');
    Route::post('/updateRole', 'RoleController@updateRole')->name('admin_api_updateRole');
    Route::post('/deleteRole', 'RoleController@deleteRole')->name('admin_api_deleteRole');
    Route::post('/assignPermissions', 'RoleController@assignPermissions')->name('admin_api_assignPermissions');

    // 权限管理 | Permission Management
    Route::post('/permissionTree', 'PermissionController@permissionTree')->name('admin_api_permissionTree');
    Route::post('/createPermission', 'PermissionController@createPermission')->name('admin_api_createPermission');
    Route::post('/updatePermission', 'PermissionController@updatePermission')->name('admin_api_updatePermission');
    Route::post('/deletePermission', 'PermissionController@deletePermission')->name('admin_api_deletePermission');

    // 菜单管理 | Menu Management
    Route::post('/menuTree', 'MenuController@menuTree')->name('admin_api_menuTree');
    Route::post('/createMenu', 'MenuController@createMenu')->name('admin_api_createMenu');
    Route::post('/updateMenu', 'MenuController@updateMenu')->name('admin_api_updateMenu');
    Route::post('/deleteMenu', 'MenuController@deleteMenu')->name('admin_api_deleteMenu');

    // 代理管理 | Agent Management
    Route::post('/agentList', 'AgentController@index')->name('admin_api_agentList');
    Route::post('/agentDetail', 'AgentController@show')->name('admin_api_agentDetail');
    Route::post('/agentDescendants', 'AgentController@descendants')->name('admin_api_agentDescendants');
    Route::post('/updateAgentLevel', 'AgentController@updateLevel')->name('admin_api_updateAgentLevel');
    Route::post('/updateAgentCommission', 'AgentController@updateCommission')->name('admin_api_updateAgentCommission');

    // 代理级别 | Agent Level
    Route::post('/agentLevelList', 'AgentLevelController@index')->name('admin_api_agentLevelList');
    Route::post('/createAgentLevel', 'AgentLevelController@store')->name('admin_api_createAgentLevel');
    Route::post('/updateAgentLevel2', 'AgentLevelController@update')->name('admin_api_updateAgentLevel2');

    // 组别配置 | Group Config
    Route::post('/groupConfigList', 'GroupConfigController@index')->name('admin_api_groupConfigList');
    Route::post('/createGroupConfig', 'GroupConfigController@store')->name('admin_api_createGroupConfig');
    Route::post('/updateGroupConfig', 'GroupConfigController@update')->name('admin_api_updateGroupConfig');

    // 入金管理 | Deposit
    Route::post('/depositList', 'DepositController@index')->name('admin_api_depositList');
    Route::post('/depositDetail', 'DepositController@show')->name('admin_api_depositDetail');
    Route::post('/depositApprove', 'DepositController@approve')->name('admin_api_depositApprove');
    Route::post('/depositReject', 'DepositController@reject')->name('admin_api_depositReject');

    // 出金管理 | Withdraw
    Route::post('/withdrawList', 'WithdrawController@index')->name('admin_api_withdrawList');
    Route::post('/withdrawProcess', 'WithdrawController@process')->name('admin_api_withdrawProcess');
    Route::post('/withdrawComplete', 'WithdrawController@complete')->name('admin_api_withdrawComplete');
    Route::post('/withdrawReject', 'WithdrawController@reject')->name('admin_api_withdrawReject');

    // 返佣管理 | Commission
    Route::post('/commissionList', 'CommissionController@index')->name('admin_api_commissionList');
    Route::post('/commissionSettle', 'CommissionController@settle')->name('admin_api_commissionSettle');

    // 系统配置 | System Config
    Route::post('/systemConfigList', 'SystemConfigController@index')->name('admin_api_systemConfigList');
    Route::post('/updateSystemConfig', 'SystemConfigController@update')->name('admin_api_updateSystemConfig');
    Route::post('/operationLogs', 'SystemConfigController@logs')->name('admin_api_operationLogs');

    // 新闻公告 | News
    Route::post('/newsList', 'NewsController@index')->name('admin_api_newsList');
    Route::post('/createNews', 'NewsController@store')->name('admin_api_createNews');
    Route::post('/updateNews', 'NewsController@update')->name('admin_api_updateNews');
    Route::post('/deleteNews', 'NewsController@destroy')->name('admin_api_deleteNews');

    // 支付通道 | Payment Channels
    Route::post('/channelList', 'PaymentChannelController@index')->name('admin_api_channelList');
    Route::post('/updateChannel', 'PaymentChannelController@update')->name('admin_api_updateChannel');

    // 管理员管理 | Admin Users
    Route::post('/adminList', 'AdminController@index')->name('admin_api_adminList');
    Route::post('/createAdmin', 'AdminController@store')->name('admin_api_createAdmin');
    Route::post('/updateAdmin', 'AdminController@update')->name('admin_api_updateAdmin');
    Route::post('/deleteAdmin', 'AdminController@destroy')->name('admin_api_deleteAdmin');

    // 文件上传 | Upload (跨命名空间)
    Route::post('/uploadFile', '\App\Http\Controllers\Common\UploadController@upload')->name('admin_api_uploadFile');
});
