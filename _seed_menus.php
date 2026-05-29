<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$now = time();

// Check permissions table columns
$cols = array_map(function($c){ return $c->Field; }, DB::select('SHOW COLUMNS FROM permissions'));
echo "Columns: " . implode(', ', $cols) . "\n";

// Clear existing
DB::table('role_permissions')->truncate();
DB::table('permissions')->truncate();
echo "Cleared permissions + role_permissions\n";

$id = 0;
$insert = function($data) use (&$id, $cols) {
    $id++;
    $row = ['name'=>$data[0],'slug'=>$data[1],'guard_type'=>$data[2],'parent_id'=>$data[3],'type'=>$data[4],'icon'=>$data[5],'sort'=>$data[6],'route'=>$data[7],'api_route'=>$data[8],'status'=>1];
    // Only insert columns that exist
    $filtered = array_intersect_key($row, array_flip($cols));
    DB::table('permissions')->insert($filtered);
    return $id;
};

// ===== ADMIN GUARD =====
$d = $insert(['仪表盘','admin_dashboard','admin',0,2,'fas fa-tachometer-alt',1,'/admin/dashboard','admin_api_dashboardData']); // 1

$um = $insert(['用户管理','admin_user_mgmt','admin',0,1,'fas fa-users',2,'','']); // 2
$insert(['所有用户','admin_user_list','admin',$um,2,'fas fa-list',1,'/admin/users','admin_api_userList']); // 3
$insert(['查看详情','admin_user_detail','admin',3,3,'',1,'','admin_api_userDetail']); // 4
$insert(['编辑用户','admin_user_update','admin',3,3,'',2,'','admin_api_updateUser']); // 5
$insert(['变更状态','admin_user_status','admin',3,3,'',3,'','admin_api_changeUserStatus']); // 6
$insert(['代理商列表','admin_agent_list','admin',$um,2,'fas fa-sitemap',2,'/admin/agents','admin_api_agentList']); // 7
$insert(['普通客户','admin_customer_list','admin',$um,2,'fas fa-user',3,'/admin/customers','admin_api_customerList']); // 8
$insert(['大代理管理','admin_big_agents','admin',$um,2,'fas fa-crown',4,'/admin/big-agents','admin_api_bigAgentList']); // 9
$insert(['黑名单','admin_blacklist','admin',$um,2,'fas fa-ban',5,'/admin/blacklist','admin_api_blacklist']); // 10
$insert(['注销申请','admin_cancel_applies','admin',$um,2,'fas fa-user-times',6,'/admin/cancel-applies','admin_api_cancelApplyList']); // 11

$fm = $insert(['财务管理','admin_finance','admin',0,1,'fas fa-dollar-sign',3,'','']); // 12
$insert(['入金审核','admin_deposit_review','admin',$fm,2,'fas fa-plus-circle',1,'/admin/deposit/review','admin_api_depositReview']); // 13
$insert(['出金审核','admin_withdraw_review','admin',$fm,2,'fas fa-minus-circle',2,'/admin/withdraw/review','admin_api_withdrawReview']); // 14
$insert(['批量入金','admin_deposit_batch','admin',$fm,2,'fas fa-file-import',3,'/admin/deposit/batch','admin_api_depositBatch']); // 15
$insert(['批量出金','admin_withdraw_batch','admin',$fm,2,'fas fa-file-export',4,'/admin/withdraw/batch','admin_api_withdrawBatch']); // 16
$insert(['信用额度','admin_credit','admin',$fm,2,'fas fa-credit-card',5,'/admin/credit','admin_api_creditList']); // 17
$insert(['凭证审核','admin_voucher_review','admin',$fm,2,'fas fa-receipt',6,'/admin/voucher/review','admin_api_voucherReview']); // 18

$cm = $insert(['返佣管理','admin_commission','admin',0,1,'fas fa-money-bill-wave',4,'','']); // 19
$insert(['返佣配置','admin_commission_config','admin',$cm,2,'fas fa-cog',1,'/admin/commission/config','admin_api_commConfig']); // 20
$insert(['返佣记录','admin_commission_records','admin',$cm,2,'fas fa-list-alt',2,'/admin/commission/records','admin_api_commRecords']); // 21
$insert(['权益结算','admin_settlement','admin',$cm,2,'fas fa-calculator',3,'/admin/settlement','admin_api_settlement']); // 22
$insert(['点差配置','admin_spread','admin',$cm,2,'fas fa-chart-line',4,'/admin/spread','admin_api_spreadConfig']); // 23

$tm = $insert(['交易管理','admin_trade','admin',0,1,'fas fa-exchange-alt',5,'','']); // 24
$insert(['仓位总结','admin_position_summary','admin',$tm,2,'fas fa-chart-bar',1,'/admin/position/summary','admin_api_positionSummary']); // 25
$insert(['持仓订单','admin_open_orders','admin',$tm,2,'fas fa-play-circle',2,'/admin/order/open','admin_api_openOrders']); // 26
$insert(['历史订单','admin_closed_orders','admin',$tm,2,'fas fa-history',3,'/admin/order/closed','admin_api_closedOrders']); // 27
$insert(['爆仓清零','admin_margin_clear','admin',$tm,2,'fas fa-eraser',4,'/admin/margin-clear','admin_api_marginClear']); // 28

$sm = $insert(['系统管理','admin_system','admin',0,1,'fas fa-cog',6,'','']); // 29
$insert(['角色管理','admin_roles','admin',$sm,2,'fas fa-user-tag',1,'/admin/roles','admin_api_roleList']); // 30
$insert(['权限管理','admin_permissions','admin',$sm,2,'fas fa-shield-alt',2,'/admin/permissions','admin_api_permissionTree']); // 31
$insert(['代理级别','admin_agent_levels','admin',$sm,2,'fas fa-layer-group',3,'/admin/agent-levels','admin_api_agentLevelList']); // 32
$insert(['组别配置','admin_group_configs','admin',$sm,2,'fas fa-object-group',4,'/admin/group-configs','admin_api_groupConfigList']); // 33
$insert(['系统配置','admin_system_config','admin',$sm,2,'fas fa-sliders-h',5,'/admin/system-config','admin_api_systemConfig']); // 34
$insert(['支付通道','admin_payment','admin',$sm,2,'fas fa-university',6,'/admin/payment-channels','admin_api_paymentChannels']); // 35
$insert(['邮件配置','admin_mail','admin',$sm,2,'fas fa-envelope-open',7,'/admin/mail-settings','admin_api_mailSettings']); // 36
$insert(['APP版本','admin_app_versions','admin',$sm,2,'fas fa-mobile-alt',8,'/admin/app-versions','admin_api_appVersions']); // 37

$rm = $insert(['风险管控','admin_risk','admin',0,1,'fas fa-exclamation-triangle',7,'','']); // 38
$insert(['风险监控','admin_risk_monitor','admin',$rm,2,'fas fa-shield-alt',1,'/admin/risk/monitor','admin_api_riskMonitor']); // 39
$insert(['在线用户','admin_online_users','admin',$rm,2,'fas fa-wifi',2,'/admin/online-users','admin_api_onlineUsers']); // 40

$ctm = $insert(['内容管理','admin_content','admin',0,1,'fas fa-newspaper',8,'','']); // 41
$insert(['新闻公告','admin_news','admin',$ctm,2,'fas fa-bullhorn',1,'/admin/news','admin_api_newsList']); // 42
$insert(['消息推送','admin_messages','admin',$ctm,2,'fas fa-bell',2,'/admin/messages','admin_api_messageList']); // 43

$lm = $insert(['日志管理','admin_logs','admin',0,1,'fas fa-file-alt',9,'','']); // 44
$insert(['管理员日志','admin_admin_logs','admin',$lm,2,'fas fa-user-shield',1,'/admin/admin-logs','admin_api_adminLogs']); // 45
$insert(['操作日志','admin_operation_logs','admin',$lm,2,'fas fa-clipboard-list',2,'/admin/operation-logs','admin_api_operationLogs']); // 46
$insert(['登录日志','admin_login_logs','admin',$lm,2,'fas fa-sign-in-alt',3,'/admin/user-login-logs','admin_api_userLoginLogs']); // 47

// ===== FRONT GUARD =====
$insert(['仪表盘','front_dashboard','front',0,2,'fas fa-tachometer-alt',1,'/front/dashboard','front_api_dashboardData']); // 48

$fp = $insert(['个人中心','front_profile','front',0,1,'fas fa-user',2,'','']); // 49
$insert(['个人资料','front_profile_info','front',$fp,2,'fas fa-id-card',1,'/front/profile','front_api_profileInfo']); // 50
$insert(['编辑资料','front_profile_edit','front',$fp,2,'fas fa-user-edit',2,'/front/profile/edit','front_api_updateProfile']); // 51
$insert(['修改密码','front_change_pwd','front',$fp,2,'fas fa-key',3,'/front/profile/change-password','front_api_changePassword']); // 52
$insert(['修改邮箱','front_change_email','front',$fp,2,'fas fa-envelope',4,'/front/profile/change-email','front_api_changeEmail']); // 53

$fa = $insert(['账户管理','front_account','front',0,1,'fas fa-wallet',3,'','']); // 54
$insert(['账户信息','front_account_info','front',$fa,2,'fas fa-info-circle',1,'/front/account/info','front_api_accountInfo']); // 55
$insert(['账户余额','front_account_balance','front',$fa,2,'fas fa-coins',2,'/front/account/balance','front_api_accountBalance']); // 56
$insert(['提交凭证','front_voucher','front',$fa,2,'fas fa-receipt',3,'/front/account/voucher','front_api_submitVoucher']); // 57
$insert(['注销账户','front_cancel','front',$fa,2,'fas fa-user-times',4,'/front/account/cancel','front_api_cancelApply']); // 58

$fd = $insert(['入出金','front_deposit_withdraw','front',0,1,'fas fa-dollar-sign',4,'','']); // 59
$insert(['入金','front_deposit','front',$fd,2,'fas fa-plus-circle',1,'/front/deposit','front_api_submitDeposit']); // 60
$insert(['出金','front_withdraw','front',$fd,2,'fas fa-minus-circle',2,'/front/withdraw','front_api_submitWithdraw']); // 61
$insert(['账户流水','front_flow','front',$fd,2,'fas fa-stream',3,'/front/flow','front_api_accountFlow']); // 62

$ft = $insert(['交易记录','front_trading','front',0,1,'fas fa-chart-bar',5,'','']); // 63
$insert(['仓位总结','front_position_summary','front',$ft,2,'fas fa-chart-pie',1,'/front/position/summary','front_api_positionSummary']); // 64
$insert(['持仓订单','front_open_orders','front',$ft,2,'fas fa-play-circle',2,'/front/order/open','front_api_openOrders']); // 65
$insert(['历史订单','front_closed_orders','front',$ft,2,'fas fa-history',3,'/front/order/closed','front_api_closedOrders']); // 66

$fag = $insert(['代理管理','front_agent','front',0,1,'fas fa-sitemap',6,'','']); // 67
$insert(['下级代理','front_agent_sub','front',$fag,2,'fas fa-user-friends',1,'/front/agent/sub','front_api_agentSubList']); // 68
$insert(['直属客户','front_agent_customers','front',$fag,2,'fas fa-users',2,'/front/agent/customers','front_api_agentCustomerList']); // 69
$insert(['代理级别确认','front_agent_confirm','front',$fag,2,'fas fa-check-circle',3,'/front/agent/confirm-level','front_api_agentConfirmLevel']); // 70
$insert(['组别变更','front_group_change','front',$fag,2,'fas fa-exchange-alt',4,'/front/agent/group-change','front_api_agentGroupChange']); // 71

$fc = $insert(['返佣管理','front_commission','front',0,1,'fas fa-money-bill-wave',7,'','']); // 72
$insert(['实时返佣','front_commission_rt','front',$fc,2,'fas fa-bolt',1,'/front/commission/realtime','front_api_commissionRealTime']); // 73
$insert(['返佣历史','front_commission_hist','front',$fc,2,'fas fa-history',2,'/front/commission/history','front_api_commissionHistory']); // 74
$insert(['佣金转账','front_commission_transfer','front',$fc,2,'fas fa-paper-plane',3,'/front/commission/transfer','front_api_commissionTransfer']); // 75

$fg = $insert(['礼品中心','front_gift','front',0,1,'fas fa-gift',8,'','']); // 76
$insert(['地址管理','front_gift_address','front',$fg,2,'fas fa-map-marker-alt',1,'/front/gift/address','front_api_giftAddressList']); // 77
$insert(['礼品列表','front_gift_list','front',$fg,2,'fas fa-box',2,'/front/gift/list','front_api_giftList']); // 78

echo "Inserted $id permissions\n";

// Assign admin permissions to super_admin (role_id=1)
$adminIds = DB::table('permissions')->where('guard_type','admin')->pluck('id');
foreach ($adminIds as $pid) {
    DB::table('role_permissions')->insert(['role_id'=>1,'permission_id'=>$pid,'created_at'=>$now,'updated_at'=>$now]);
}
echo count($adminIds)." admin perms -> super_admin\n";

// All front permissions to agent role (role_id=2)
$frontIds = DB::table('permissions')->where('guard_type','front')->pluck('id');
foreach ($frontIds as $pid) {
    DB::table('role_permissions')->insert(['role_id'=>2,'permission_id'=>$pid,'created_at'=>$now,'updated_at'=>$now]);
}
echo count($frontIds)." front perms -> agent_role\n";

// Customer role (role_id=3): exclude agent-only menus (代理管理 and its children, 佣金转账)
$agentOnlySlugs = ['front_agent','front_agent_sub','front_agent_customers','front_agent_confirm','front_group_change','front_commission_transfer'];
$customerIds = DB::table('permissions')->where('guard_type','front')->whereNotIn('slug',$agentOnlySlugs)->pluck('id');
foreach ($customerIds as $pid) {
    DB::table('role_permissions')->insert(['role_id'=>3,'permission_id'=>$pid,'created_at'=>$now,'updated_at'=>$now]);
}
echo count($customerIds)." front perms -> customer_role\n";

echo "\nDONE! Total permissions: $id\n";
