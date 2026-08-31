<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:45
 */

/**
 * 文件功能：
 * - 承载后台 API 路由域：前缀 api/admin，控制器命名空间 App\Http\Controllers\Admin。
 * - 登录接口为公开入口；其余后台接口统一经 jwt.auth:admin、sso:admin 与 check.permission:admin 三层中间件保护，按 permissions.api_route 鉴权。
 * - 输入：HTTP 请求与中间件栈分发；输出：对应控制器动作的响应。
 * - 明确不负责：不承载控制器业务逻辑、permissions 权限字典与迁移定义（分别由控制器与迁移负责）。
 */

/**
 * 后台 API 路由 | Admin API Routes
 *
 * 功能逻辑说明：
 * - 路由前缀：api/admin。
 * - 控制器命名空间：App\Http\Controllers\Admin。
 * - 登录接口为公开入口；其他后台业务接口必须通过 JWT、SSO 与后台权限保护接口分组。
 * - JWT 用于识别当前管理员，SSO 用于校验 token 是否仍有效，check.permission:admin 用于按 permissions.api_route 强制鉴权。
 * - 后台菜单接口返回当前管理员可见菜单树和按钮权限 slug，菜单管理接口维护 permissions 表中的菜单权限字典。
 *
 * 因此此文件内的控制器只需写类名即可
 */
use Illuminate\Support\Facades\Route;

// ========== 公开接口 | Public ==========
Route::post('/login', 'AuthController@login')->name('admin_api_login');

// ========== JWT、SSO 与后台权限保护接口 | JWT, SSO & Permission Protected ==========
Route::middleware(['jwt.auth:admin', 'sso:admin', 'check.permission:admin'])->group(function () {
    Route::post('/logout', 'AuthController@logout')->name('admin_api_logout');
    Route::post('/refreshToken', 'AuthController@refreshToken')->name('admin_api_refreshToken');
    // 后台当前管理员菜单接口：返回 data.menus 和 data.permissions，供 Blade/Layui 渲染菜单与按钮。
    Route::post('/menus', 'MenuController@adminMenus')->name('admin_api_menus');

    // 资源化后台接口别名：保留原命名路由，确保 permissions.api_route 与 check.permission:admin 继续按现有权限表鉴权。
    Route::post('/dashboard', 'AdminDashboardController@dashboardData')->name('admin_api_dashboardData');
    Route::post('/users', 'AdminUserController@userList')->name('admin_api_userList');
    Route::post('/users/{user}', 'AdminUserController@userDetail')->whereNumber('user')->name('admin_api_userDetail');
    Route::patch('/users/{user}', 'AdminUserController@updateUser')->whereNumber('user')->name('admin_api_updateUser');
    Route::patch('/users/{user}/status', 'AdminUserController@changeUserStatus')->whereNumber('user')->name('admin_api_changeUserStatus');
    Route::post('/agents', 'AgentController@index')->name('admin_api_agentList');
    Route::post('/deposits', 'DepositController@index')->name('admin_api_depositList');
    Route::post('/withdrawals', 'WithdrawController@index')->name('admin_api_withdrawList');
    Route::post('/commissions', 'CommissionController@index')->name('admin_api_commissionList');
    Route::post('/commission-transfers/reconciliation-cases', 'CommissionController@reconciliationCases')
        ->name('admin_api_commissionTransferReconciliationList');
    Route::get('/commission-transfers/reconciliation-cases/{transfer}', 'CommissionController@reconciliationCase')
        ->whereNumber('transfer')
        ->name('admin_api_commissionTransferReconciliationDetail');
    Route::post('/commission-transfers/reconciliation-cases/{transfer}/decisions', 'CommissionController@reconcileTransfer')
        ->whereNumber('transfer')
        ->name('admin_api_commissionTransferReconcile');
    Route::post('/vouchers', 'VoucherController@index')->name('admin_api_voucherList');
    Route::post('/roles', 'RoleController@roleList')->name('admin_api_roleList');
    Route::post('/menus/tree', 'MenuController@menuTree')->name('admin_api_menuTree');
    Route::post('/agent-levels', 'AgentLevelController@index')->name('admin_api_agentLevelList');
    Route::post('/group-configs', 'GroupConfigController@index')->name('admin_api_groupConfigList');
    Route::post('/system-configs', 'SystemConfigController@index')->name('admin_api_systemConfigList');
    Route::post('/operation-logs', 'SystemConfigController@logs')->name('admin_api_operationLogs');
    Route::post('/channels', 'PaymentChannelController@index')->name('admin_api_channelList');
    Route::post('/admins', 'AdminController@index')->name('admin_api_adminList');
    Route::post('/changeAdminStatus', 'LegacyAdminActionController@changeAdminStatus')->name('admin_api_changeAdminStatus');
    Route::post('/news', 'NewsController@index')->name('admin_api_newsList');

    // 仪表盘 | Dashboard
    Route::post('/dashboardData', 'AdminDashboardController@dashboardData')->name('admin_api_dashboardData');
    Route::post('/bigNumberDashboard', 'BigNumberController@dashboard')->name('admin_api_bigNumberDashboard');
    Route::post('/bigNumberTrend', 'BigNumberController@trend')->name('admin_api_bigNumberTrend');

    // 管理员资料 | Admin Profile
    Route::post('/profileInfo', 'AuthController@profileInfo')->name('admin_api_profileInfo');
    Route::post('/updateProfile', 'AuthController@updateProfile')->name('admin_api_updateProfile');
    Route::post('/changePassword', 'AuthController@changePassword')->name('admin_api_changePassword');
    Route::post('/uploadAvatar', 'AuthController@uploadAvatar')->name('admin_api_uploadAvatar');

    // 用户管理 | User Management
    Route::post('/userList', 'AdminUserController@userList')->name('admin_api_userList');
    Route::post('/createUser', 'LegacyAdminActionController@createUser')->name('admin_api_createUser');
    Route::post('/resetUserPassword', 'LegacyAdminActionController@resetUserPassword')->name('admin_api_resetUserPassword');
    Route::post('/exportUsers', 'AdminUserController@exportUsers')->name('admin_api_exportUsers');
    Route::post('/userDetail', 'AdminUserController@userDetail')->name('admin_api_userDetail');
    // 客户资料统计：出入金、返佣、开关订单数与近 7/15/30 天盈亏，全部来自真实业务表并套用数据范围。
    Route::post('/customerStatistics', 'CustomerStatisticsController@customerStatistics')->name('admin_api_customerStatistics');
    Route::post('/updateUser', 'AdminUserController@updateUser')->name('admin_api_updateUser');
    Route::post('/changeUserStatus', 'AdminUserController@changeUserStatus')->name('admin_api_changeUserStatus');
    Route::post('/reviewAuth', 'AdminUserController@reviewAuth')->name('admin_api_reviewAuth');
    // 实名认证审核：待审/已审列表读取 user_auths 与 user_infos，并继续由 permissions.api_route 控制接口访问。
    Route::post('/authPendingList', 'AuthenticationController@pendingList')->name('admin_api_authPendingList');
    Route::post('/authCertifiedList', 'AuthenticationController@certifiedList')->name('admin_api_authCertifiedList');
    Route::post('/authDetail', 'AuthenticationController@detail')->name('admin_api_authDetail');

    // 角色管理 | Role Management
    Route::post('/roleList', 'RoleController@roleList')->name('admin_api_roleList');
    Route::post('/createRole', 'RoleController@createRole')->name('admin_api_createRole');
    Route::post('/updateRole', 'RoleController@updateRole')->name('admin_api_updateRole');
    Route::post('/deleteRole', 'RoleController@deleteRole')->name('admin_api_deleteRole');
    Route::post('/assignPermissions', 'RoleController@assignPermissions')->name('admin_api_assignPermissions');

    // 数据范围管理：配置角色可见数据集合，以及管理员可管理的代理节点。
    Route::post('/roleDataScopeList', 'DataScopeController@roleDataScopeList')->name('admin_api_roleDataScopeList');
    Route::post('/saveRoleDataScope', 'DataScopeController@saveRoleDataScope')->name('admin_api_saveRoleDataScope');
    Route::post('/adminAgentBindingList', 'DataScopeController@adminAgentBindingList')->name('admin_api_adminAgentBindingList');
    Route::post('/saveAdminAgentBinding', 'DataScopeController@saveAdminAgentBinding')->name('admin_api_saveAdminAgentBinding');
    Route::post('/deleteAdminAgentBinding', 'DataScopeController@deleteAdminAgentBinding')->name('admin_api_deleteAdminAgentBinding');

    // 权限管理 | Permission Management
    Route::post('/permissionTree', 'PermissionController@permissionTree')->name('admin_api_permissionTree');
    Route::post('/permissions/tree', 'PermissionController@permissionTree')->name('admin_api_permissionTree');
    Route::post('/createPermission', 'PermissionController@createPermission')->name('admin_api_createPermission');
    Route::post('/updatePermission', 'PermissionController@updatePermission')->name('admin_api_updatePermission');
    Route::post('/deletePermission', 'PermissionController@deletePermission')->name('admin_api_deletePermission');

    // 后台菜单管理接口：维护 permissions 表中的菜单权限字典，供前后台菜单配置共用。
    Route::post('/menuTree', 'MenuController@menuTree')->name('admin_api_menuTree');
    Route::post('/createMenu', 'MenuController@createMenu')->name('admin_api_createMenu');
    Route::post('/updateMenu', 'MenuController@updateMenu')->name('admin_api_updateMenu');
    Route::post('/deleteMenu', 'MenuController@deleteMenu')->name('admin_api_deleteMenu');

    // 代理管理 | Agent Management
    Route::post('/agentList', 'AgentController@index')->name('admin_api_agentList');
    Route::post('/createAgent', 'LegacyAdminActionController@createAgent')->name('admin_api_createAgent');
    Route::post('/exportAgents', 'AgentController@exportAgents')->name('admin_api_exportAgents');
    Route::post('/agentStatsList', 'AgentController@listWithStats')->name('admin_api_agentStatsList');
    Route::post('/confirmAgent', 'AgentController@confirmAgent')->name('admin_api_confirmAgent');
    Route::post('/rejectAgentConfirmation', 'AgentController@rejectAgentConfirmation')->name('admin_api_rejectAgentConfirmation');
    Route::post('/agentDetail', 'AgentController@show')->name('admin_api_agentDetail');
    Route::post('/agentParentPath', 'AgentController@parentPath')->name('admin_api_agentParentPath');
    Route::post('/agentDescendants', 'AgentController@descendants')->name('admin_api_agentDescendants');
    Route::post('/updateAgentLevel', 'AgentController@updateLevel')->name('admin_api_updateAgentLevel');
    Route::post('/updateAgentCommission', 'AgentController@updateCommission')->name('admin_api_updateAgentCommission');

    // 代理级别 | Agent Level
    Route::post('/agentLevelList', 'AgentLevelController@index')->name('admin_api_agentLevelList');
    Route::post('/createAgentLevel', 'AgentLevelController@store')->name('admin_api_createAgentLevel');
    Route::post('/updateAgentLevel2/{id}', 'AgentLevelController@update')->name('admin_api_updateAgentLevel2');
    Route::post('/deleteAgentLevel/{id}', 'AgentLevelController@destroy')->name('admin_api_deleteAgentLevel');

    // 组别配置 | Group Config
    Route::post('/groupConfigList', 'GroupConfigController@index')->name('admin_api_groupConfigList');
    Route::post('/createGroupConfig', 'GroupConfigController@store')->name('admin_api_createGroupConfig');
    Route::post('/updateGroupConfig/{id}', 'GroupConfigController@update')->name('admin_api_updateGroupConfig');
    Route::post('/deleteGroupConfig/{id}', 'GroupConfigController@destroy')->name('admin_api_deleteGroupConfig');

    // 旧 UserGroupController 已合并到 group_configs；以下接口保留旧字段语义并使用独立权限字符串，
    // 避免控制器已实现但没有 HTTP 入口造成前后端断链。
    Route::post('/userGroupList', 'UserGroupController@index')->name('admin_api_userGroupList');
    Route::post('/createUserGroup', 'UserGroupController@store')->name('admin_api_createUserGroup');
    Route::post('/updateUserGroup/{id}', 'UserGroupController@update')->whereNumber('id')->name('admin_api_updateUserGroup');
    Route::post('/deleteUserGroup/{id}', 'UserGroupController@destroy')->whereNumber('id')->name('admin_api_deleteUserGroup');

    // 入金管理 | Deposit
    Route::post('/depositList', 'DepositController@index')->name('admin_api_depositList');
    Route::post('/exportDeposits', 'LegacyAdminExportController@exportDeposits')->name('admin_api_exportDeposits');
    Route::post('/depositDetail', 'DepositController@show')->name('admin_api_depositDetail');
    Route::post('/depositApprove', 'DepositController@approve')->name('admin_api_depositApprove');
    Route::post('/depositReject', 'DepositController@reject')->name('admin_api_depositReject');
    // 批量入金导入：列表和新增接口都继续走后台 JWT、SSO、permissions.api_route 鉴权。
    Route::post('/depositImportList', 'BatchAmountImportController@depositImportList')->name('admin_api_depositImportList');
    Route::post('/createDepositImport', 'BatchAmountImportController@createDepositImport')->name('admin_api_createDepositImport');
    Route::post('/depositImportTemplate', 'BatchAmountImportController@depositImportTemplate')->name('admin_api_depositImportTemplate');
    Route::post('/exportDepositImports', 'BatchAmountImportController@exportDepositImports')->name('admin_api_exportDepositImports');
    Route::post('/retryDepositImport/{id}', 'BatchAmountImportController@retryDepositImport')->name('admin_api_retryDepositImport');
    Route::post('/syncDepositImport/{id}', 'BatchAmountImportController@syncDepositImport')->name('admin_api_syncDepositImport');

    // 出金管理 | Withdraw
    Route::post('/withdrawList', 'WithdrawController@index')->name('admin_api_withdrawList');
    Route::post('/exportWithdrawals', 'LegacyAdminExportController@exportWithdrawals')->name('admin_api_exportWithdrawals');
    Route::post('/withdrawProcess', 'WithdrawController@process')->name('admin_api_withdrawProcess');
    Route::post('/withdrawComplete', 'WithdrawController@complete')->name('admin_api_withdrawComplete');
    Route::post('/withdrawReject', 'WithdrawController@reject')->name('admin_api_withdrawReject');
    // 批量出金导入：列表、CSV 导入/导出、模板下载和失败重试均复用 BatchAmountImportController 闭环。
    Route::post('/withdrawImportList', 'BatchAmountImportController@withdrawImportList')->name('admin_api_withdrawImportList');
    Route::post('/createWithdrawImport', 'BatchAmountImportController@createWithdrawImport')->name('admin_api_createWithdrawImport');
    Route::post('/withdrawImportTemplate', 'BatchAmountImportController@withdrawImportTemplate')->name('admin_api_withdrawImportTemplate');
    Route::post('/exportWithdrawImports', 'BatchAmountImportController@exportWithdrawImports')->name('admin_api_exportWithdrawImports');
    Route::post('/retryWithdrawImport/{id}', 'BatchAmountImportController@retryWithdrawImport')->name('admin_api_retryWithdrawImport');
    Route::post('/syncWithdrawImport/{id}', 'BatchAmountImportController@syncWithdrawImport')->name('admin_api_syncWithdrawImport');
    // 资金流水：入金/出金流水来自 mt4_trades，未入金流水来自 deposit_records，接口继续由 permissions.api_route 鉴权。
    Route::post('/depositFlowList', 'FundFlowController@depositFlowList')->name('admin_api_depositFlowList');
    Route::post('/exportDepositFlows', 'FundFlowController@exportDepositFlows')->name('admin_api_exportDepositFlows');
    Route::post('/withdrawFlowList', 'FundFlowController@withdrawFlowList')->name('admin_api_withdrawFlowList');
    Route::post('/exportWithdrawFlows', 'FundFlowController@exportWithdrawFlows')->name('admin_api_exportWithdrawFlows');
    Route::post('/undepositFlowList', 'FundFlowController@undepositFlowList')->name('admin_api_undepositFlowList');
    Route::post('/exportUndepositFlows', 'FundFlowController@exportUndepositFlows')->name('admin_api_exportUndepositFlows');
    Route::post('/neverDepositUserList', 'FundFlowController@neverDepositUserList')->name('admin_api_neverDepositUserList');

    // 返佣管理 | Commission
    Route::post('/commissionList', 'CommissionController@index')->name('admin_api_commissionList');
    Route::post('/commissionSettle', 'CommissionController@settle')->name('admin_api_commissionSettle');
    // 实时返佣：读取 mt4_trades 中 COMMENT 命中旧返佣关键词的正向余额记录，接口继续由 permissions.api_route 与数据范围服务双重控制。
    Route::post('/realtimeCommissionList', 'RealtimeCommissionController@realtimeCommissionList')->name('admin_api_realtimeCommissionList');
    Route::post('/exportRealtimeCommissions', 'RealtimeCommissionController@exportRealtimeCommissions')->name('admin_api_exportRealtimeCommissions');
    // 实时返佣统计模块：只返回按天聚合的图表序列与来源分布，不返回明细行，响应体不随返佣总量增长。
    Route::post('/realtimeCommissionStatistics', 'RealtimeCommissionController@realtimeCommissionStatistics')->name('admin_api_realtimeCommissionStatistics');
    // 批量信用导入：列表和新增接口都必须通过 permissions.api_route 与 check.permission:admin 鉴权。
    Route::post('/creditImportList', 'BatchCreditImportController@creditImportList')->name('admin_api_creditImportList');
    Route::post('/createCreditImport', 'BatchCreditImportController@createCreditImport')->name('admin_api_createCreditImport');
    Route::post('/creditImportTemplate', 'BatchCreditImportController@creditImportTemplate')->name('admin_api_creditImportTemplate');
    Route::post('/exportCreditImports', 'BatchCreditImportController@exportCreditImports')->name('admin_api_exportCreditImports');
    Route::post('/retryCreditImport/{id}', 'BatchCreditImportController@retryCreditImport')->name('admin_api_retryCreditImport');
    Route::post('/syncCreditImport/{id}', 'BatchCreditImportController@syncCreditImport')->name('admin_api_syncCreditImport');

    // 系统配置 | System Config
    Route::post('/systemConfigList', 'SystemConfigController@index')->name('admin_api_systemConfigList');
    Route::post('/updateSystemConfig', 'SystemConfigController@update')->name('admin_api_updateSystemConfig');
    Route::post('/operationLogs', 'SystemConfigController@logs')->name('admin_api_operationLogs');
    // 汇率配置：入金汇率和出金汇率写入 system_configs，接口权限由 permissions.api_route 控制。
    Route::post('/exchangeRateInfo', 'ExchangeRateController@info')->name('admin_api_exchangeRateInfo');
    Route::post('/updateExchangeRate', 'ExchangeRateController@update')->name('admin_api_updateExchangeRate');
    // 在线用户：只读查看 user_onlines 最近活跃记录，接口权限由 permissions.api_route 控制。
    Route::post('/onlineUserList', 'OnlineUserController@onlineUserList')->name('admin_api_onlineUserList');
    Route::post('/forceOfflineUser/{id}', 'OnlineUserController@forceOffline')->whereNumber('id')->name('admin_api_forceOfflineUser');
    // 产品/交易品种：读取 symbol_prices 并汇总 mt4_trades 当前持仓，接口权限由 permissions.api_route 控制。
    Route::post('/productionList', 'ProductionController@productionList')->name('admin_api_productionList');
    Route::post('/exportProductions', 'ProductionController@exportProductions')->name('admin_api_exportProductions');
    Route::post('/createProduction', 'ProductionController@createProduction')->name('admin_api_createProduction');
    Route::post('/updateProduction/{id}', 'ProductionController@updateProduction')->whereNumber('id')->name('admin_api_updateProduction');
    Route::post('/deleteProduction/{id}', 'ProductionController@deleteProduction')->whereNumber('id')->name('admin_api_deleteProduction');
    // 礼品发放/发货：读取 gift_shipments 与 user_addresses，发放动作通过事务写入发货记录。
    Route::post('/giftShipmentList', 'GiftController@shipmentList')->name('admin_api_giftShipmentList');
    Route::post('/exportGiftShipments', 'GiftController@exportGiftShipments')->name('admin_api_exportGiftShipments');
    Route::post('/giftAddressList', 'GiftController@addressList')->name('admin_api_giftAddressList');
    Route::post('/sendGift', 'GiftController@sendGift')->name('admin_api_sendGift');
    Route::post('/updateGiftShipment/{id}', 'GiftController@updateShipment')->whereNumber('id')->name('admin_api_updateGiftShipment');
    Route::post('/giftItemList', 'GiftController@giftItemList')->name('admin_api_giftItemList');
    Route::post('/createGiftItem', 'GiftController@createGiftItem')->name('admin_api_createGiftItem');
    Route::post('/updateGiftItem/{id}', 'GiftController@updateGiftItem')->whereNumber('id')->name('admin_api_updateGiftItem');
    Route::post('/deleteGiftItem/{id}', 'GiftController@deleteGiftItem')->whereNumber('id')->name('admin_api_deleteGiftItem');

    // 新闻公告 | News
    Route::post('/newsList', 'NewsController@index')->name('admin_api_newsList');
    Route::post('/createNews', 'NewsController@store')->name('admin_api_createNews');
    Route::post('/updateNews/{id}', 'NewsController@update')->name('admin_api_updateNews');
    Route::post('/deleteNews/{id}', 'NewsController@destroy')->name('admin_api_deleteNews');
    Route::post('/toggleNews/{id}', 'NewsController@togglePublish')->name('admin_api_toggleNews');

    // 支付通道 | Payment Channels
    Route::post('/channelList', 'PaymentChannelController@index')->name('admin_api_channelList');
    Route::post('/createChannel', 'PaymentChannelController@store')->name('admin_api_createChannel');
    Route::post('/updateChannel/{id}', 'PaymentChannelController@update')->name('admin_api_updateChannel');
    Route::post('/deleteChannel/{id}', 'PaymentChannelController@destroy')->name('admin_api_deleteChannel');
    Route::post('/toggleChannel/{id}', 'PaymentChannelController@toggleEnable')->name('admin_api_toggleChannel');

    // 管理员管理 | Admin Users
    Route::post('/adminList', 'AdminController@index')->name('admin_api_adminList');
    Route::post('/createAdmin', 'AdminController@store')->name('admin_api_createAdmin');
    Route::post('/updateAdmin/{id}', 'AdminController@update')->name('admin_api_updateAdmin');
    Route::post('/resetAdminPassword/{id}', 'AdminController@resetPassword')->name('admin_api_resetAdminPassword');
    Route::post('/deleteAdmin/{id}', 'AdminController@destroy')->name('admin_api_deleteAdmin');

    // 凭证审核 | Vouchers
    Route::post('/voucherList', 'VoucherController@index')->name('admin_api_voucherList');
    Route::post('/voucherApprove/{id}', 'VoucherController@approve')->name('admin_api_voucherApprove');
    Route::post('/voucherReject/{id}', 'VoucherController@reject')->name('admin_api_voucherReject');

    // 风控管理 | Risk
    // 盈利风险读模型在 Task 3 接入；当前路由必须真实存在并通过权限中间件失败关闭，不能回退到交易汇总。
    Route::post('/riskProfitUsers', 'RiskController@profitableUsers')->name('admin_api_riskProfitUsers');
    Route::post('/riskPositions', 'RiskController@positions')->name('admin_api_riskPositions');
    Route::post('/riskMarginCalls', 'RiskController@marginCalls')->name('admin_api_riskMarginCalls');
    // 异常 IP 风控：读取 user_login_logs 中同一 IP 登录多个业务账号的风险聚合结果。
    Route::post('/riskIpList', 'RiskController@riskIpList')->name('admin_api_riskIpList');
    // 异常 IP 详情：按 login_ip 展开用户登录明细、交易统计和资金统计，仍由 permissions.api_route 鉴权。
    Route::post('/riskIpDetail', 'RiskController@riskIpDetail')->name('admin_api_riskIpDetail');
    Route::post('/riskForceClose/{id}', 'RiskController@forceClose')->name('admin_api_riskForceClose');

    // 黑名单 | Blacklist
    Route::post('/blacklistList', 'BlacklistController@index')->name('admin_api_blacklistList');
    Route::post('/createBlacklist', 'BlacklistController@store')->name('admin_api_createBlacklist');
    Route::post('/updateBlacklist/{id}', 'BlacklistController@update')->name('admin_api_updateBlacklist');
    Route::post('/deleteBlacklist/{id}', 'BlacklistController@destroy')->name('admin_api_deleteBlacklist');

    // 注销申请 | Cancel Applies
    Route::post('/cancelApplyList', 'CancelApplyController@index')->name('admin_api_cancelApplyList');
    Route::post('/cancelApplyApprove/{id}', 'CancelApplyController@approve')->name('admin_api_cancelApplyApprove');
    Route::post('/cancelApplyReject/{id}', 'CancelApplyController@reject')->name('admin_api_cancelApplyReject');

    // 交易订单 | Trades
    Route::post('/tradeList', 'TradeController@index')->name('admin_api_tradeList');
    Route::post('/openPositions', 'TradeController@openPositions')->name('admin_api_openPositions');
    Route::post('/closedPositions', 'TradeController@closedPositions')->name('admin_api_closedPositions');
    Route::post('/exportClosedPositions', 'TradeController@exportClosedPositions')->name('admin_api_exportClosedPositions');
    Route::post('/tradeSummary', 'TradeController@summary')->name('admin_api_tradeSummary');
    // 权益汇总：第一阶段读取 mt4_users 资金快照，并通过 user_infos.mt4_code 映射业务用户后应用数据范围。
    Route::post('/rightsSummaryList', 'RightsSummaryController@rightsSummaryList')->name('admin_api_rightsSummaryList');
    Route::post('/exportRightsSummary', 'RightsSummaryController@exportRightsSummary')->name('admin_api_exportRightsSummary');
    // 权益结算手动确认：只确认 rights_settlements 待处理记录，不调用 MT4 自动入出金，接口权限由 permissions.api_route 控制。
    Route::post('/manualConfirmRightsSettlement/{id}', 'RightsSummaryController@manualConfirmRightsSettlement')->name('admin_api_manualConfirmRightsSettlement');
    // 持仓汇总：按 user_infos 用户维度聚合 mt4_trades 交易手数、盈亏、手续费和品种分类手数，并继续通过 permissions.api_route 与 AdminDataScopeService 控制访问范围。
    Route::post('/positionSummaryList', 'PositionSummaryController@positionSummaryList')->name('admin_api_positionSummaryList');
    Route::post('/exportPositionSummary', 'PositionSummaryController@exportPositionSummary')->name('admin_api_exportPositionSummary');
    Route::post('/whsExpZeroList', 'AdminWhsExpZeroController@zeroList')->name('admin_api_whsExpZeroList');
    Route::post('/whsExpZeroRecords', 'AdminWhsExpZeroController@recordList')->name('admin_api_whsExpZeroRecords');
    Route::post('/whsExpZero', 'AdminWhsExpZeroController@oneKeyZero')->name('admin_api_whsExpZero');

    // 大代理 | Big Agents
    Route::post('/bigAgentList', 'BigAgentController@index')->name('admin_api_bigAgentList');
    Route::post('/changeBigAgentStatus', 'LegacyAdminActionController@changeBigAgentStatus')->name('admin_api_changeBigAgentStatus');
    Route::post('/createBigAgent', 'BigAgentController@store')->name('admin_api_createBigAgent');
    Route::post('/updateBigAgent/{id}', 'BigAgentController@update')->name('admin_api_updateBigAgent');
    Route::post('/deleteBigAgent/{id}', 'BigAgentController@destroy')->name('admin_api_deleteBigAgent');

    // 文件上传 | Upload (跨命名空间)
    Route::post('/uploadFile', '\App\Http\Controllers\Common\UploadController@upload')->name('admin_api_uploadFile');
});
