<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 16:05
 */

/**
 * AdminPermissionPlanTest
 *
 * 文件功能：
 * - 验证权限计划契约：角色授权走 role_permissions 表、后台受保护路由带权限中间件、菜单响应包含权限 slug，且前台仅授权子菜单时菜单树自动保留父级容器。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Controllers\Admin\MenuController;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台权限方案回归测试。
 *
 * 这些测试只创建鉴权所需的最小数据表，避免依赖真实 MySQL 业务库。
 * 覆盖目标：
 * - 角色权限必须以 role_permissions 中间表作为唯一生效来源。
 * - 后台 API 受保护路由必须统一挂载 check.permission:admin。
 * - 菜单接口必须返回按钮权限 slug 数组，供 Layui 与 Blade/JS 后台共用。
 */
class AdminPermissionPlanTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTemporaryPermissions();
    }

    /**
     * 测试结束后清理可能写入真实 DB 的临时权限数据。
     *
     * 逻辑说明：
     * - 当前测试环境连接真实 MySQL，临时权限必须使用 test_* 前缀并在结束后清理。
     * - 避免测试生成的菜单 route 污染后台真实权限字典，影响后续菜单覆盖审计。
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->deleteTemporaryPermissions();

        parent::tearDown();
    }

    private function deleteTemporaryPermissions(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('slug', 'like', 'test_admin_users_%')
            ->orWhere('slug', 'like', 'test_admin_deposit_approve_%')
            ->orWhere('slug', 'like', 'test_admin_user_review_auth_%')
            ->orWhere('slug', 'like', 'front_agent_parent_%')
            ->orWhere('slug', 'like', 'front_agent_child_%')
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    /**
     * 角色权限判断必须读取 role_permissions 中间表。
     *
     * 业务含义：
     * - permissions.slug 是前后端共用的权限标识。
     * - role_permissions 是角色实际拥有权限的唯一生效来源。
     * - roles.permissions JSON 即使为空，也不能影响中间表授权结果。
     *
     * @return void
     */
    public function test_role_permission_check_uses_role_permissions_table(): void
    {
        $role = Role::create([
            'name' => '财务审核-' . uniqid(),
            'guard_type' => 'admin',
            'description' => '只能审核财务相关动作',
            'permissions' => [],
            'status' => 1,
        ]);

        $permission = Permission::create([
            'name' => '入金审核',
            'slug' => 'test_admin_deposit_approve_' . uniqid(),
            'guard_type' => 'admin',
            'parent_id' => 0,
            'type' => 3,
            'route' => '',
            'api_route' => 'admin_api_depositApprove',
            'status' => 1,
            'sort' => 10,
        ]);

        $role->permissionsRelation()->sync([$permission->id]);

        $this->assertTrue($role->fresh()->hasPermission($permission->slug));
    }

    /**
     * 后台 API 受保护路由组必须挂载接口权限校验中间件。
     *
     * 业务含义：
     * - jwt.auth:admin 只证明“是谁”。
     * - sso:admin 只证明“当前 token 是否有效”。
     * - check.permission:admin 才能证明“当前管理员能不能调用该接口”。
     *
     * @return void
     */
    public function test_admin_protected_routes_include_permission_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin_api_userList');

        $this->assertNotNull($route, 'admin_api_userList 路由必须存在。');
        $this->assertContains('check.permission:admin', $route->gatherMiddleware());
    }

    /**
     * 菜单接口必须同时返回菜单树和按钮权限 slug 数组。
     *
     * 业务含义：
     * - menus 用于渲染左侧导航。
     * - permissions 用于 Blade + JS 判断按钮是否显示。
     * - 前端隐藏按钮只是体验优化，后端接口仍必须再次鉴权。
     *
     * @return void
     */
    public function test_admin_menus_response_contains_permission_slugs(): void
    {
        $role = Role::create([
            'name' => '客服-' . uniqid(),
            'guard_type' => 'admin',
            'description' => '查看用户并审核实名',
            'permissions' => [],
            'status' => 1,
        ]);

        $admin = Admin::create([
            'username' => 'service-admin-' . uniqid(),
            'password' => 'secret',
            'role_id' => $role->id,
            'status' => 1,
        ]);

        $menu = Permission::create([
            'name' => '用户管理',
            'slug' => 'test_admin_users_' . uniqid(),
            'guard_type' => 'admin',
            'parent_id' => 0,
            'type' => 1,
            'route' => '/admin/__test-users',
            'api_route' => 'admin_api_userList',
            'status' => 1,
            'sort' => 1,
        ]);

        $button = Permission::create([
            'name' => '实名审核',
            'slug' => 'test_admin_user_review_auth_' . uniqid(),
            'guard_type' => 'admin',
            'parent_id' => $menu->id,
            'type' => 3,
            'route' => '',
            'api_route' => 'admin_api_reviewAuth',
            'status' => 1,
            'sort' => 2,
        ]);

        $role->permissionsRelation()->sync([$menu->id, $button->id]);

        $request = Request::create('/api/admin/menus', 'POST');
        $request->setUserResolver(function () use ($admin) {
            return $admin->fresh('role');
        });

        $response = (new MenuController(new MenuService()))->adminMenus($request);
        $payload = $response->getData(true);

        $this->assertSame(1000, $payload['code']);
        $this->assertContains($menu->slug, $payload['data']['permissions']);
        $this->assertContains($button->slug, $payload['data']['permissions']);
    }

    /**
     * 前台角色只授权子菜单时，菜单树必须自动保留父级菜单容器。
     *
     * 业务含义：
     * - 前台代理商和普通客户菜单都来自 permissions 表与 role_permissions 授权配置。
     * - 运维在后台分配权限时可能只勾选具体页面节点，例如 front_agent_sub。
     * - 如果父级 front_agent 被过滤掉，Layui 左侧菜单会没有承载子菜单的容器，表现为 agent 登录后菜单丢失。
     *
     * @return void
     */
    public function test_front_menu_tree_keeps_parent_when_only_child_permission_is_granted(): void
    {
        $menuService = new MenuService();

        // $parentMenu：前台代理商菜单父级节点，只用于承载下级页面，不一定被角色直接授权。
        $parentMenu = Permission::create([
            'name' => '代理中心',
            'slug' => 'front_agent_parent_' . uniqid(),
            'guard_type' => 'front',
            'parent_id' => 0,
            'type' => 1,
            'route' => '',
            'api_route' => '',
            'status' => 1,
            'sort' => 10,
        ]);

        // $childMenu：代理商实际可访问的子页面，模拟角色只授权具体页面节点的真实配置。
        $childMenu = Permission::create([
            'name' => '直属代理',
            'slug' => 'front_agent_child_' . uniqid(),
            'guard_type' => 'front',
            'parent_id' => $parentMenu->id,
            'type' => 2,
            'route' => '/front/agent/sub',
            'api_route' => 'front_api_agents_direct',
            'status' => 1,
            'sort' => 11,
        ]);

        $menus = $menuService->getUserMenus('front', [$childMenu->id]);
        $tree = $menuService->buildTree($menus, 'zh-CN');

        $this->assertCount(1, $tree);
        $this->assertSame($parentMenu->slug, $tree[0]['slug']);
        $this->assertSame($childMenu->slug, $tree[0]['children'][0]['slug']);
    }
}
