<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 09:31
 */

/**
 * AdminDataScopeManagementTest
 *
 * 文件功能：
 * - 验证数据范围管理：路由注册、Blade 页面外壳、角色数据范围配置与管理员代理绑定写入 admin_agent_bindings 表。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\DataScopeController;
use App\Models\Admin;
use App\Models\AdminAgentBinding;
use App\Models\Role;
use App\Models\RoleDataScope;
use App\Models\UserInfo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台数据范围管理回归测试。
 *
 * 测试目标：
 * - 数据范围不只停留在数据库表和服务层，后台必须提供 Blade 配置页面。
 * - 角色数据范围必须能通过接口写入 role_data_scopes 表。
 * - 管理员代理绑定必须能通过接口写入 admin_agent_bindings 表。
 */
class AdminDataScopeManagementTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 后台必须存在数据范围管理页面和对应 API 路由。
     *
     * @return void
     */
    public function test_data_scope_management_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_data_scopes'));
        $this->assertTrue(Route::has('admin_api_roleDataScopeList'));
        $this->assertTrue(Route::has('admin_api_saveRoleDataScope'));
        $this->assertTrue(Route::has('admin_api_adminAgentBindingList'));
        $this->assertTrue(Route::has('admin_api_saveAdminAgentBinding'));
        $this->assertTrue(Route::has('admin_api_deleteAdminAgentBinding'));
    }

    /**
     * 数据范围管理页面必须由 Laravel Blade 直接渲染。
     *
     * @return void
     */
    public function test_data_scope_management_page_renders_blade_shell(): void
    {
        $response = $this->get('/admin/data-scopes');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('roleScopeTable', false);
        $response->assertSee('adminAgentBindingTable', false);
    }

    /**
     * 保存角色数据范围时必须写入 role_data_scopes 表。
     *
     * @return void
     */
    public function test_can_save_role_data_scope_configuration(): void
    {
        $role = Role::create([
            'name' => 'scope-role-' . uniqid(),
            'guard_type' => 'admin',
            'description' => '数据范围配置测试角色',
            'permissions' => [],
            'status' => 1,
        ]);

        $request = Request::create('/api/admin/saveRoleDataScope', 'POST', [
            'role_id' => $role->id,
            'scope_type' => 'custom_users',
            'user_ids' => '10001,10002',
            'agent_ids' => '',
            'status' => 1,
        ]);

        $response = (new DataScopeController())->saveRoleDataScope($request);
        $payload = $response->getData(true);

        $this->assertSame(ResponseCode::UPDATED, $payload['code']);
        $this->assertSame([10001, 10002], RoleDataScope::where('role_id', $role->id)->first()->user_ids);
    }

    /**
     * 保存管理员代理绑定时必须写入 admin_agent_bindings 表。
     *
     * @return void
     */
    public function test_can_save_admin_agent_binding_configuration(): void
    {
        $admin = Admin::create([
            'username' => 'binding-admin-' . uniqid(),
            'password' => 'secret',
            'role_id' => 0,
            'status' => 1,
        ]);
        $agentId = null;
        do {
            $agentId = random_int(980000, 989999);
        } while (UserInfo::whereKey($agentId)->exists());
        UserInfo::create([
            'user_id' => $agentId,
            'login_id' => $agentId,
            'user_name' => '测试代理',
            'account_type' => 1,
        ]);

        $request = Request::create('/api/admin/saveAdminAgentBinding', 'POST', [
            'admin_id' => $admin->id,
            'agent_id' => $agentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        $response = (new DataScopeController())->saveAdminAgentBinding($request);
        $payload = $response->getData(true);

        $this->assertSame(ResponseCode::CREATED, $payload['code']);
        $this->assertTrue(AdminAgentBinding::where('admin_id', $admin->id)->where('agent_id', $agentId)->exists());
    }
}
