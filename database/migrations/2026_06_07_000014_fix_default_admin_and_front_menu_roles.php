<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:33
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * 修复默认后台账号与前台 Layui 菜单角色授权。
 *
 * 文件功能：
 * - 当前后台登录控制器读取 App\Models\Admin，也就是 admins 表；历史 DatabaseSeeder 写入 admin_logins 会导致默认账号不可登录。
 * - 当前前台菜单接口读取 user_logins.role_id，再通过 roles 与 role_permissions 过滤 permissions 表中的 front 菜单。
 * - 本迁移把“账号、角色、菜单、授权、演示用户角色绑定”统一写回数据表配置，确保前后台菜单权限都来自 DB。
 */
class FixDefaultAdminAndFrontMenuRoles extends Migration
{
    /**
     * 执行迁移：补字段、补超级管理员、补前台菜单权限与角色授权。
     *
     * @return void
     */
    public function up()
    {
        $this->ensureFrontUserRoleColumn();
        $superRoleId = $this->ensureRole('super_admin', 'admin', '超级管理员，拥有后台全部菜单、按钮和接口权限。');
        $agentRoleId = $this->ensureRole('agent_role', 'front', '前台代理商角色，显示代理、返佣、客户和通用账户菜单。');
        $customerRoleId = $this->ensureRole('customer_role', 'front', '前台普通客户角色，只显示客户可访问的账户、资金、交易和礼品菜单。');

        $this->ensureDefaultAdmin($superRoleId);
        $this->ensureFrontMenus();
        $this->grantRolePermissions($agentRoleId, $this->agentMenuSlugs());
        $this->grantRolePermissions($customerRoleId, $this->customerMenuSlugs());
        $this->bindDemoFrontUsers($agentRoleId, $customerRoleId);
    }

    /**
     * 回滚迁移：只撤销本迁移的演示账号绑定和前台角色授权，不删除菜单字典和超级管理员账号。
     *
     * 逻辑边界：
     * - permissions 是可被业务继续维护的权限字典，回滚不删除，避免误删后续手工配置。
     * - admins.superadmin 可能已经在真实环境使用，回滚不删除账号，避免破坏登录入口。
     *
     * @return void
     */
    public function down()
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['agent_role', 'customer_role'])
            ->where('guard_type', 'front')
            ->pluck('id')
            ->toArray();

        DB::table('role_permissions')->whereIn('role_id', $roleIds)->delete();

        if (Schema::hasColumn('user_logins', 'role_id')) {
            DB::table('user_logins')
                ->whereIn('email', ['agent@test.com', 'subagent1@test.com', 'subagent2@test.com'])
                ->orWhere('account_type', 2)
                ->update(['role_id' => 0]);
        }
    }

    /**
     * 补齐前台登录表角色字段。
     *
     * 字段含义：
     * - role_id：当前前台登录账号绑定的 roles.id，用于菜单接口按 role_permissions 过滤前台菜单。
     *
     * @return void
     */
    private function ensureFrontUserRoleColumn()
    {
        if (!Schema::hasColumn('user_logins', 'role_id')) {
            Schema::table('user_logins', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id')->default(0)->after('account_type')->comment('前台角色ID，对应 roles.id，用于菜单和按钮权限过滤');
                $table->index('role_id');
            });
        }
    }

    /**
     * 创建或更新角色，并返回角色主键。
     *
     * @param string $name 角色稳定名称，例如 super_admin、agent_role、customer_role。
     * @param string $guardType 守卫类型，admin=后台管理员，front=前台用户。
     * @param string $description 角色用途中文说明，供后台角色管理页面识别。
     * @return int roles.id。
     */
    private function ensureRole($name, $guardType, $description)
    {
        $now = time();

        DB::table('roles')->updateOrInsert(
            ['name' => $name, 'guard_type' => $guardType],
            [
                'description' => $description,
                'permissions' => json_encode([], JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('roles')
            ->where('name', $name)
            ->where('guard_type', $guardType)
            ->value('id');
    }

    /**
     * 写入当前后台登录链路可用的默认超级管理员。
     *
     * 参数含义：
     * - $roleId：super_admin 角色 ID，管理员登录后通过该角色识别超级权限。
     *
     * @param int $roleId roles.id。
     * @return void
     */
    private function ensureDefaultAdmin($roleId)
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['username' => 'superadmin'],
            [
                'role_id' => (string) $roleId,
                'mobile' => '13800138000',
                'email' => 'admin@crmv5.com',
                'password' => Hash::make('abc123'),
                'login_count' => 0,
                'last_login_ip' => '',
                'last_login_at' => null,
                'last_login_address' => '',
                'status' => 1,
                'jwt_token_id' => '',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 补齐前台 Layui 菜单权限字典。
     *
     * 逻辑说明：
     * - 父级菜单 type=1，route 通常为空，只用于承载子菜单。
     * - 可点击页面 type=2，route 指向 Blade 页面路径，api_route 指向页面主要读取接口。
     * - 所有菜单都写入 permissions.guard_type=front，菜单接口只读取这一类权限。
     *
     * @return void
     */
    private function ensureFrontMenus()
    {
        foreach ($this->frontMenuTree() as $index => $menu) {
            $parentId = $this->upsertFrontPermission(array_merge($menu, [
                'parent_id' => 0,
                'sort' => ($index + 1) * 10,
            ]));

            foreach ($menu['children'] as $childIndex => $child) {
                $this->upsertFrontPermission(array_merge($child, [
                    'parent_id' => $parentId,
                    'sort' => (($index + 1) * 10) + $childIndex + 1,
                ]));
            }
        }
    }

    /**
     * 写入或更新单条前台权限，并返回 permissions.id。
     *
     * @param array<string, mixed> $permission 权限配置；slug 是稳定权限标识，route 是页面路径，api_route 是主要接口路由名。
     * @return int permissions.id。
     */
    private function upsertFrontPermission(array $permission)
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => $permission['slug']],
            [
                'name' => $permission['name'],
                'guard_type' => 'front',
                'parent_id' => (int) $permission['parent_id'],
                'type' => (int) $permission['type'],
                'icon' => $permission['icon'],
                'sort' => (int) $permission['sort'],
                'route' => $permission['route'],
                'api_route' => $permission['api_route'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('permissions')->where('slug', $permission['slug'])->value('id');
    }

    /**
     * 给角色绑定菜单权限。
     *
     * 参数含义：
     * - $roleId：roles.id，表示被授权的前台角色。
     * - $slugs：permissions.slug 列表，表示该角色可见的菜单节点。
     *
     * @param int $roleId 角色 ID。
     * @param array<int, string> $slugs 菜单权限 slug 列表。
     * @return void
     */
    private function grantRolePermissions($roleId, array $slugs)
    {
        $now = time();
        $permissionIds = DB::table('permissions')
            ->where('guard_type', 'front')
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->toArray();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * 绑定演示前台账号到对应角色。
     *
     * 参数含义：
     * - $agentRoleId：代理商角色 ID，用于 account_type=1 的演示代理账号。
     * - $customerRoleId：普通客户角色 ID，用于 account_type=2 的演示客户账号。
     *
     * @param int $agentRoleId 代理角色 ID。
     * @param int $customerRoleId 客户角色 ID。
     * @return void
     */
    private function bindDemoFrontUsers($agentRoleId, $customerRoleId)
    {
        if (!Schema::hasColumn('user_logins', 'role_id')) {
            return;
        }

        DB::table('user_logins')
            ->where('account_type', 1)
            ->where(function ($query) {
                $query->whereIn('email', ['agent@test.com', 'subagent1@test.com', 'subagent2@test.com'])
                    ->orWhereIn('user_id', [1001, 1101, 1102]);
            })
            ->update(['role_id' => $agentRoleId]);

        DB::table('user_logins')
            ->where('account_type', 2)
            ->where(function ($query) {
                $query->where('email', 'like', 'customer%@test.com')
                    ->orWhereBetween('user_id', [600101, 600199]);
            })
            ->update(['role_id' => $customerRoleId]);
    }

    /**
     * 前台菜单树配置。
     *
     * @return array<int, array<string, mixed>> 父子菜单配置。
     */
    private function frontMenuTree()
    {
        return [
            [
                'name' => '控制台',
                'slug' => 'front_dashboard',
                'type' => 2,
                'icon' => 'layui-icon layui-icon-console',
                'route' => '/front/dashboard',
                'api_route' => 'front_api_dashboard',
                'children' => [],
            ],
            [
                'name' => '个人中心',
                'slug' => 'front_profile',
                'type' => 2,
                'icon' => 'layui-icon layui-icon-username',
                'route' => '/front/profile',
                'api_route' => 'front_api_profile',
                'children' => [],
            ],
            [
                'name' => '账户管理',
                'slug' => 'front_account',
                'type' => 1,
                'icon' => 'layui-icon layui-icon-template-1',
                'route' => '',
                'api_route' => '',
                'children' => [
                    ['name' => '账户综合', 'slug' => 'front_account_info', 'type' => 2, 'icon' => 'layui-icon layui-icon-about', 'route' => '/front/account/info', 'api_route' => 'front_api_account_profile'],
                    ['name' => '账户余额', 'slug' => 'front_account_balance', 'type' => 2, 'icon' => 'layui-icon layui-icon-rmb', 'route' => '/front/account/balance', 'api_route' => 'front_api_account_balance'],
                    ['name' => '提交凭证', 'slug' => 'front_voucher', 'type' => 2, 'icon' => 'layui-icon layui-icon-note', 'route' => '/front/account/voucher', 'api_route' => 'front_api_account_vouchers'],
                    ['name' => '注销账户', 'slug' => 'front_cancel', 'type' => 2, 'icon' => 'layui-icon layui-icon-close-fill', 'route' => '/front/account/cancel', 'api_route' => 'front_api_account_cancellation'],
                ],
            ],
            [
                'name' => '入出金',
                'slug' => 'front_deposit_withdraw',
                'type' => 1,
                'icon' => 'layui-icon layui-icon-dollar',
                'route' => '',
                'api_route' => '',
                'children' => [
                    ['name' => '入金', 'slug' => 'front_deposit', 'type' => 2, 'icon' => 'layui-icon layui-icon-add-circle', 'route' => '/front/deposit', 'api_route' => 'front_api_deposits_history'],
                    ['name' => '出金', 'slug' => 'front_withdraw', 'type' => 2, 'icon' => 'layui-icon layui-icon-reduce-circle', 'route' => '/front/withdraw', 'api_route' => 'front_api_withdrawals_history'],
                    ['name' => '资金流水', 'slug' => 'front_flow', 'type' => 2, 'icon' => 'layui-icon layui-icon-list', 'route' => '/front/flow', 'api_route' => 'front_api_flows_account'],
                ],
            ],
            [
                'name' => '交易管理',
                'slug' => 'front_trading',
                'type' => 1,
                'icon' => 'layui-icon layui-icon-chart',
                'route' => '',
                'api_route' => '',
                'children' => [
                    ['name' => '持仓汇总', 'slug' => 'front_position_summary', 'type' => 2, 'icon' => 'layui-icon layui-icon-table', 'route' => '/front/position/summary', 'api_route' => 'front_api_positions_summary'],
                    ['name' => '当前持仓', 'slug' => 'front_open_orders', 'type' => 2, 'icon' => 'layui-icon layui-icon-play', 'route' => '/front/order/open', 'api_route' => 'front_api_orders_open'],
                    ['name' => '历史平仓', 'slug' => 'front_closed_orders', 'type' => 2, 'icon' => 'layui-icon layui-icon-log', 'route' => '/front/order/closed', 'api_route' => 'front_api_orders_closed'],
                ],
            ],
            [
                'name' => '代理管理',
                'slug' => 'front_agent',
                'type' => 1,
                'icon' => 'layui-icon layui-icon-group',
                'route' => '',
                'api_route' => '',
                'children' => [
                    ['name' => '下级代理', 'slug' => 'front_agent_sub', 'type' => 2, 'icon' => 'layui-icon layui-icon-friends', 'route' => '/front/agent/sub', 'api_route' => 'front_api_agents_direct'],
                    ['name' => '直属客户', 'slug' => 'front_agent_customers', 'type' => 2, 'icon' => 'layui-icon layui-icon-user', 'route' => '/front/agent/customers', 'api_route' => 'front_api_agents_direct_customers'],
                    ['name' => '代理级别确认', 'slug' => 'front_agent_confirm', 'type' => 2, 'icon' => 'layui-icon layui-icon-ok-circle', 'route' => '/front/agent/confirm-level', 'api_route' => 'front_api_agents_level_confirmation'],
                    ['name' => '客户组别变更', 'slug' => 'front_group_change', 'type' => 2, 'icon' => 'layui-icon layui-icon-transfer', 'route' => '/front/agent/group-change', 'api_route' => 'front_api_agents_group_changes'],
                ],
            ],
            [
                'name' => '返佣管理',
                'slug' => 'front_commission',
                'type' => 1,
                'icon' => 'layui-icon layui-icon-diamond',
                'route' => '',
                'api_route' => '',
                'children' => [
                    ['name' => '实时返佣', 'slug' => 'front_commission_rt', 'type' => 2, 'icon' => 'layui-icon layui-icon-light', 'route' => '/front/commission/realtime', 'api_route' => 'front_api_commissions_realtime'],
                    ['name' => '返佣历史', 'slug' => 'front_commission_hist', 'type' => 2, 'icon' => 'layui-icon layui-icon-date', 'route' => '/front/commission/history', 'api_route' => 'front_api_commissions_history'],
                    ['name' => '佣金转账', 'slug' => 'front_commission_transfer', 'type' => 2, 'icon' => 'layui-icon layui-icon-release', 'route' => '/front/commission/transfer', 'api_route' => 'front_api_commissions_history'],
                ],
            ],
            [
                'name' => '礼品中心',
                'slug' => 'front_gift',
                'type' => 1,
                'icon' => 'layui-icon layui-icon-gift',
                'route' => '',
                'api_route' => '',
                'children' => [
                    ['name' => '地址管理', 'slug' => 'front_gift_address', 'type' => 2, 'icon' => 'layui-icon layui-icon-location', 'route' => '/front/gift/address', 'api_route' => 'front_api_gift_addresses_index'],
                    ['name' => '礼品列表', 'slug' => 'front_gift_list', 'type' => 2, 'icon' => 'layui-icon layui-icon-cart', 'route' => '/front/gift/list', 'api_route' => 'front_api_gifts'],
                ],
            ],
            [
                'name' => '新闻公告',
                'slug' => 'front_news',
                'type' => 2,
                'icon' => 'layui-icon layui-icon-notice',
                'route' => '/front/news',
                'api_route' => 'front_api_news',
                'children' => [],
            ],
        ];
    }

    /**
     * 代理商角色应拥有的前台菜单权限。
     *
     * @return array<int, string> permissions.slug 列表。
     */
    private function agentMenuSlugs()
    {
        return [
            'front_dashboard',
            'front_profile',
            'front_account',
            'front_account_info',
            'front_account_balance',
            'front_voucher',
            'front_cancel',
            'front_deposit_withdraw',
            'front_deposit',
            'front_withdraw',
            'front_flow',
            'front_trading',
            'front_position_summary',
            'front_open_orders',
            'front_closed_orders',
            'front_agent',
            'front_agent_sub',
            'front_agent_customers',
            'front_agent_confirm',
            'front_group_change',
            'front_commission',
            'front_commission_rt',
            'front_commission_hist',
            'front_commission_transfer',
            'front_gift',
            'front_gift_address',
            'front_gift_list',
            'front_news',
        ];
    }

    /**
     * 普通客户角色应拥有的前台菜单权限。
     *
     * @return array<int, string> permissions.slug 列表。
     */
    private function customerMenuSlugs()
    {
        return [
            'front_dashboard',
            'front_profile',
            'front_account',
            'front_account_info',
            'front_account_balance',
            'front_voucher',
            'front_cancel',
            'front_deposit_withdraw',
            'front_deposit',
            'front_withdraw',
            'front_flow',
            'front_trading',
            'front_position_summary',
            'front_open_orders',
            'front_closed_orders',
            'front_gift',
            'front_gift_address',
            'front_gift_list',
            'front_news',
        ];
    }
}
