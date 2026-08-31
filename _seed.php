<?php
/**
 * 前端 UI 回归测试数据种子脚本。
 *
 * 文件功能：
 * - 引导 Laravel 容器后写入 FrontUiRegressionTest 依赖的基础演示数据（
 *   测试账号、配置、菜单等），供前端页面回归使用。
 *
 * 适用场景：
 * - 由 FrontUiRegressionTest 测试基础设施调用，普通业务无需执行。
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
$now = time();

// 1. 创建或重置前端回归使用的管理员账号，统一密码避免测试入口出现多套凭据。
$a = DB::table('admins')->where('username','admin')->first();
if (!$a) {
    DB::table('admins')->insert(['role_id'=>'1','mobile'=>'','email'=>'admin@cocrmv5.com','username'=>'admin','password'=>Hash::make('abc123'),'login_count'=>0,'last_login_ip'=>'','status'=>1,'jwt_token_id'=>'','created_by'=>'system','created_at'=>$now,'updated_at'=>$now]);
    echo "Admin created\n";
} else {
    DB::table('admins')->where('username','admin')->update(['password'=>Hash::make('abc123')]);
    echo "Admin password reset\n";
}

// 2. Permissions
DB::table('permissions')->truncate();
$perms = [
    ['name'=>'仪表盘','slug'=>'admin_dashboard','guard_type'=>'admin','parent_id'=>0,'type'=>2,'icon'=>'fas fa-tachometer-alt','sort'=>1,'route'=>'/admin/dashboard','api_route'=>'admin_api_dashboardData','status'=>1],
    ['name'=>'用户管理','slug'=>'admin_user_mgmt','guard_type'=>'admin','parent_id'=>0,'type'=>1,'icon'=>'fas fa-users','sort'=>2,'route'=>'','api_route'=>'','status'=>1],
    ['name'=>'用户列表','slug'=>'admin_user_list','guard_type'=>'admin','parent_id'=>2,'type'=>2,'icon'=>'fas fa-list','sort'=>1,'route'=>'/admin/users','api_route'=>'admin_api_userList','status'=>1],
    ['name'=>'编辑用户','slug'=>'admin_user_update','guard_type'=>'admin','parent_id'=>3,'type'=>3,'icon'=>'','sort'=>1,'route'=>'','api_route'=>'admin_api_updateUser','status'=>1],
    ['name'=>'系统管理','slug'=>'admin_system','guard_type'=>'admin','parent_id'=>0,'type'=>1,'icon'=>'fas fa-cog','sort'=>3,'route'=>'','api_route'=>'','status'=>1],
    ['name'=>'角色管理','slug'=>'admin_roles','guard_type'=>'admin','parent_id'=>5,'type'=>2,'icon'=>'fas fa-user-tag','sort'=>1,'route'=>'/admin/roles','api_route'=>'admin_api_roleList','status'=>1],
    ['name'=>'权限管理','slug'=>'admin_permissions','guard_type'=>'admin','parent_id'=>5,'type'=>2,'icon'=>'fas fa-shield-alt','sort'=>2,'route'=>'/admin/permissions','api_route'=>'admin_api_permissionTree','status'=>1],
    ['name'=>'代理级别','slug'=>'admin_agent_levels','guard_type'=>'admin','parent_id'=>5,'type'=>2,'icon'=>'fas fa-layer-group','sort'=>3,'route'=>'/admin/agent-levels','api_route'=>'admin_api_agentLevelList','status'=>1],
    ['name'=>'组别配置','slug'=>'admin_group_configs','guard_type'=>'admin','parent_id'=>5,'type'=>2,'icon'=>'fas fa-object-group','sort'=>4,'route'=>'/admin/group-configs','api_route'=>'admin_api_groupConfigList','status'=>1],
    ['name'=>'仪表盘','slug'=>'front_dashboard','guard_type'=>'front','parent_id'=>0,'type'=>2,'icon'=>'fas fa-tachometer-alt','sort'=>1,'route'=>'/front/dashboard','api_route'=>'front_api_dashboard','status'=>1],
    ['name'=>'个人中心','slug'=>'front_profile','guard_type'=>'front','parent_id'=>0,'type'=>2,'icon'=>'fas fa-user','sort'=>2,'route'=>'/front/profile','api_route'=>'front_api_profile','status'=>1],
    ['name'=>'个人资料','slug'=>'front_profile_info','guard_type'=>'front','parent_id'=>11,'type'=>2,'icon'=>'fas fa-id-card','sort'=>1,'route'=>'/front/profile','api_route'=>'front_api_profile','status'=>0],
    ['name'=>'编辑资料','slug'=>'front_profile_edit','guard_type'=>'front','parent_id'=>11,'type'=>2,'icon'=>'fas fa-user-edit','sort'=>2,'route'=>'/front/profile/edit','api_route'=>'front_api_profile_update','status'=>0],
    ['name'=>'修改密码','slug'=>'front_change_pwd','guard_type'=>'front','parent_id'=>11,'type'=>2,'icon'=>'fas fa-key','sort'=>3,'route'=>'/front/profile/change-password','api_route'=>'front_api_profile_password','status'=>0],
    ['name'=>'账户管理','slug'=>'front_account','guard_type'=>'front','parent_id'=>0,'type'=>1,'icon'=>'fas fa-wallet','sort'=>3,'route'=>'','api_route'=>'','status'=>1],
    ['name'=>'账户综合','slug'=>'front_account_info','guard_type'=>'front','parent_id'=>15,'type'=>2,'icon'=>'fas fa-info-circle','sort'=>1,'route'=>'/front/account/info','api_route'=>'front_api_account_profile','status'=>1],
    ['name'=>'账户余额','slug'=>'front_account_balance','guard_type'=>'front','parent_id'=>15,'type'=>2,'icon'=>'fas fa-coins','sort'=>2,'route'=>'/front/account/balance','api_route'=>'front_api_account_balance','status'=>1],
    ['name'=>'提交凭证','slug'=>'front_voucher','guard_type'=>'front','parent_id'=>15,'type'=>2,'icon'=>'fas fa-receipt','sort'=>3,'route'=>'/front/account/voucher','api_route'=>'front_api_account_vouchers','status'=>1],
    ['name'=>'注销账户','slug'=>'front_cancel','guard_type'=>'front','parent_id'=>15,'type'=>2,'icon'=>'fas fa-user-times','sort'=>4,'route'=>'/front/account/cancel','api_route'=>'front_api_account_cancellation','status'=>1],
    ['name'=>'入出金','slug'=>'front_deposit_withdraw','guard_type'=>'front','parent_id'=>0,'type'=>1,'icon'=>'fas fa-dollar-sign','sort'=>4,'route'=>'','api_route'=>'','status'=>1],
    ['name'=>'入金','slug'=>'front_deposit','guard_type'=>'front','parent_id'=>20,'type'=>2,'icon'=>'fas fa-plus-circle','sort'=>1,'route'=>'/front/deposit','api_route'=>'front_api_deposits_history','status'=>1],
    ['name'=>'出金','slug'=>'front_withdraw','guard_type'=>'front','parent_id'=>20,'type'=>2,'icon'=>'fas fa-minus-circle','sort'=>2,'route'=>'/front/withdraw','api_route'=>'front_api_withdrawals_history','status'=>1],
    ['name'=>'账户流水','slug'=>'front_flow','guard_type'=>'front','parent_id'=>20,'type'=>2,'icon'=>'fas fa-stream','sort'=>3,'route'=>'/front/flow','api_route'=>'front_api_flows_account','status'=>1],
    ['name'=>'交易记录','slug'=>'front_trading','guard_type'=>'front','parent_id'=>0,'type'=>1,'icon'=>'fas fa-chart-bar','sort'=>5,'route'=>'','api_route'=>'','status'=>1],
    ['name'=>'仓位总结','slug'=>'front_position_summary','guard_type'=>'front','parent_id'=>24,'type'=>2,'icon'=>'fas fa-chart-pie','sort'=>1,'route'=>'/front/position/summary','api_route'=>'front_api_positions_summary','status'=>1],
    ['name'=>'持仓订单','slug'=>'front_open_orders','guard_type'=>'front','parent_id'=>24,'type'=>2,'icon'=>'fas fa-play-circle','sort'=>2,'route'=>'/front/order/open','api_route'=>'front_api_orders_open','status'=>1],
    ['name'=>'历史订单','slug'=>'front_closed_orders','guard_type'=>'front','parent_id'=>24,'type'=>2,'icon'=>'fas fa-history','sort'=>3,'route'=>'/front/order/closed','api_route'=>'front_api_orders_closed','status'=>1],
    ['name'=>'代理管理','slug'=>'front_agent','guard_type'=>'front','parent_id'=>0,'type'=>1,'icon'=>'fas fa-sitemap','sort'=>6,'route'=>'','api_route'=>'','status'=>1],
    ['name'=>'下级代理','slug'=>'front_agent_sub','guard_type'=>'front','parent_id'=>28,'type'=>2,'icon'=>'fas fa-user-friends','sort'=>1,'route'=>'/front/agent/sub','api_route'=>'front_api_agents_direct','status'=>1],
    ['name'=>'直属客户','slug'=>'front_agent_customers','guard_type'=>'front','parent_id'=>28,'type'=>2,'icon'=>'fas fa-users','sort'=>2,'route'=>'/front/agent/customers','api_route'=>'front_api_agents_direct_customers','status'=>1],
    ['name'=>'代理级别确认','slug'=>'front_agent_confirm','guard_type'=>'front','parent_id'=>28,'type'=>2,'icon'=>'fas fa-check-circle','sort'=>3,'route'=>'/front/agent/confirm-level','api_route'=>'front_api_agents_level_confirmation','status'=>1],
    ['name'=>'组别变更','slug'=>'front_group_change','guard_type'=>'front','parent_id'=>28,'type'=>2,'icon'=>'fas fa-exchange-alt','sort'=>4,'route'=>'/front/agent/group-change','api_route'=>'front_api_agents_group_changes','status'=>1],
    ['name'=>'返佣管理','slug'=>'front_commission','guard_type'=>'front','parent_id'=>0,'type'=>1,'icon'=>'fas fa-money-bill-wave','sort'=>7,'route'=>'','api_route'=>'','status'=>1],
    ['name'=>'实时返佣','slug'=>'front_commission_rt','guard_type'=>'front','parent_id'=>33,'type'=>2,'icon'=>'fas fa-bolt','sort'=>1,'route'=>'/front/commission/realtime','api_route'=>'front_api_commissions_realtime','status'=>1],
    ['name'=>'返佣历史','slug'=>'front_commission_hist','guard_type'=>'front','parent_id'=>33,'type'=>2,'icon'=>'fas fa-history','sort'=>2,'route'=>'/front/commission/history','api_route'=>'front_api_commissions_history','status'=>1],
    ['name'=>'佣金转账','slug'=>'front_commission_transfer','guard_type'=>'front','parent_id'=>33,'type'=>2,'icon'=>'fas fa-paper-plane','sort'=>3,'route'=>'/front/commission/transfer','api_route'=>'front_api_commissions_history','status'=>1],
    ['name'=>'礼品中心','slug'=>'front_gift','guard_type'=>'front','parent_id'=>0,'type'=>1,'icon'=>'fas fa-gift','sort'=>8,'route'=>'','api_route'=>'','status'=>1],
    ['name'=>'地址管理','slug'=>'front_gift_address','guard_type'=>'front','parent_id'=>37,'type'=>2,'icon'=>'fas fa-map-marker-alt','sort'=>1,'route'=>'/front/gift/address','api_route'=>'front_api_gift_addresses_index','status'=>1],
    ['name'=>'礼品列表','slug'=>'front_gift_list','guard_type'=>'front','parent_id'=>37,'type'=>2,'icon'=>'fas fa-box','sort'=>2,'route'=>'/front/gift/list','api_route'=>'front_api_gifts','status'=>1],
    ['name'=>'新闻公告','slug'=>'front_news','guard_type'=>'front','parent_id'=>0,'type'=>2,'icon'=>'fas fa-bullhorn','sort'=>9,'route'=>'/front/news','api_route'=>'front_api_news','status'=>1],
];
foreach ($perms as $p) { DB::table('permissions')->insert($p); }
echo count($perms)." permissions inserted\n";

// 3. Role-permission assignments
DB::table('role_permissions')->truncate();
$adminIds = DB::table('permissions')->where('guard_type','admin')->pluck('id');
foreach ($adminIds as $pid) {
    DB::table('role_permissions')->insert(['role_id'=>1,'permission_id'=>$pid,'created_at'=>$now,'updated_at'=>$now]);
}
echo count($adminIds)." admin perms assigned to super_admin\n";

$frontIds = DB::table('permissions')->where('guard_type','front')->pluck('id');
foreach ($frontIds as $pid) {
    DB::table('role_permissions')->insert(['role_id'=>2,'permission_id'=>$pid,'created_at'=>$now,'updated_at'=>$now]);
}
$customerIds = DB::table('permissions')->where('guard_type','front')->whereNotIn('slug', ['front_agent','front_agent_sub','front_agent_customers','front_agent_confirm','front_group_change','front_commission_transfer'])->pluck('id');
foreach ($customerIds as $pid) {
    DB::table('role_permissions')->insert(['role_id'=>3,'permission_id'=>$pid,'created_at'=>$now,'updated_at'=>$now]);
}
echo count($frontIds)." front perms assigned to agent role\n";
echo count($customerIds)." front perms assigned to customer role\n";

// 4. 创建或重置前端回归使用的代理账号，并与管理员共享统一初始密码。
$ta = DB::table('user_logins')->where('email','agent@test.com')->first();
if (!$ta) {
    $seq = DB::table('id_sequences')->where('type','agent')->first();
    $uid = $seq ? $seq->current_value : 1001;
    DB::table('id_sequences')->where('type','agent')->update(['current_value'=>$uid+1,'updated_at'=>$now]);
    $lid = DB::table('user_logins')->insertGetId(['user_id'=>$uid,'email'=>'agent@test.com','password'=>Hash::make('abc123'),'account_type'=>2,'is_enabled'=>1,'is_cancelled'=>0,'source_type'=>0,'jwt_token_id'=>'','last_login_ip'=>'','created_at'=>$now,'updated_at'=>$now]);
    DB::table('user_infos')->insert(['user_id'=>$uid,'login_id'=>$lid,'user_name'=>'TestAgent','phone'=>'13800138000','gender'=>1,'level_id'=>0,'group_id'=>0,'parent_id'=>0,'account_type'=>2,'family_tree'=>(string)$uid,'total_funds'=>0,'used_margin'=>0,'avail_margin'=>0,'equity'=>0,'effective_credit'=>0,'risk_ratio'=>0,'margin_amount'=>0,'leverage'=>0,'cust_vol'=>'0','pay_provider_id'=>0,'equity_ratio'=>0,'comm_rate'=>50,'is_ecn'=>0,'follow_parent_ecn'=>0,'auth_status'=>1,'is_mt4_synced'=>0,'is_mt4_enabled'=>1,'is_mt4_readonly'=>0,'is_withdrawal_allowed'=>0,'is_deposit_allowed'=>0,'is_agent_confirmed'=>1,'original_group'=>'','mt4_group'=>'','mt4_code'=>0,'trading_mode'=>0,'settle_method'=>1,'settle_cycle'=>1,'country'=>'China','city'=>'','state'=>'','is_gift_allowed'=>0,'data_source'=>0,'remark'=>'','created_by'=>0,'updated_by'=>0,'created_at'=>$now,'updated_at'=>$now]);
    echo "Agent created: agent@test.com/abc123, user_id=$uid\n";
} else {
    DB::table('user_logins')->where('email','agent@test.com')->update(['password'=>Hash::make('abc123')]);
    echo "Agent password reset\n";
}

echo "\n=== Test Accounts ===\n";
echo "Admin: admin / abc123\n";
echo "Agent: agent@test.com / abc123\n";
