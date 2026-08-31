<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 00:14
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧后台管理员账号管理闭环测试。
 *
 * 文件功能：
 * - 验证项目1 `AdministratorsController` 的新增、编辑、启用、停用、删除入口在项目2可执行。
 * - 验证旧字段 `password2`、`mobile`、`role_id`、`statue` 与项目2 `admins` 表和统一响应共存。
 * - 验证旧 GET 写入口只对白名单管理员账号动作放开，不影响其他旧 GET 资金写入口的失败关闭策略。
 */
class AdminLegacyAdministratorsClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 本夹具管理员用户名前缀（legacy-admins-closure-）。断言与清理都按前缀圈定。
     * @var string
     */
    private const PREFIX = 'legacy-admins-closure-';

    /**
     * 旧管理员列表、新增页和编辑页应渲染当前管理员账号页面。
     *
     * @return void
     */
    public function test_legacy_administrators_pages_render_current_admin_account_page(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createAdminRole(self::PREFIX . 'page-role');
        $adminId = $this->createManagedAdmin($roleId);

        foreach ([
            '/index/admin/Administrators',
            '/index/admin/Administrators/add',
            '/index/admin/Administrators/edit/' . $adminId,
        ] as $uri) {
            $response = $this->legacyRequest($actor)->get($uri);

            $response->assertOk()
                ->assertSee('data-layui-page="admins/index"', false)
                ->assertSee('js/apps/admin/layui/pages.js', false)
                ->assertSee('js/vendor/lucide/lucide.min.js', false);
        }
    }

    /**
     * 旧 addsave 应创建启用状态的后台管理员并返回旧 statue 字段。
     *
     * @return void
     */
    public function test_legacy_administrators_addsave_creates_admin_with_legacy_response(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createAdminRole(self::PREFIX . 'create-role');
        $username = self::PREFIX . 'create-' . uniqid();
        $email = $username . '@example.test';

        $response = $this->legacyRequest($actor)
            ->postJson('/index/admin/Administrators/addsave', [
                'username' => $username,
                'email' => $email,
                'password' => 'secret123',
                'mobile' => '13900001234',
                'role_id' => $roleId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('statue', '1')
            ->assertJsonPath('msg', '添加成功');

        $admin = DB::table('admins')->where('email', $email)->first();
        $this->assertNotNull($admin);
        $this->assertSame($username, (string) $admin->username);
        $this->assertTrue(Hash::check('secret123', (string) $admin->password));
        $this->assertSame('13900001234', (string) $admin->mobile);
        $this->assertSame((string) $roleId, (string) $admin->role_id);
        $this->assertSame(1, (int) $admin->status);
    }

    /**
     * 旧 editsave 应使用 password2 作为新密码并保留旧响应字段。
     *
     * @return void
     */
    public function test_legacy_administrators_editsave_uses_password2_and_updates_admin_fields(): void
    {
        $actor = $this->ensureSuperAdmin();
        $oldRoleId = $this->createAdminRole(self::PREFIX . 'old-role');
        $newRoleId = $this->createAdminRole(self::PREFIX . 'new-role');
        $adminId = $this->createManagedAdmin($oldRoleId);
        $username = self::PREFIX . 'edited-' . $adminId;
        $email = $username . '@example.test';

        $response = $this->legacyRequest($actor)
            ->postJson('/index/admin/Administrators/editsave', [
                'id' => $adminId,
                'username' => $username,
                'email' => $email,
                'password' => 'old-secret',
                'password2' => 'new-secret',
                'mobile' => '13900004321',
                'role_id' => $newRoleId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED)
            ->assertJsonPath('statue', '1')
            ->assertJsonPath('msg', '编辑成功');

        $admin = DB::table('admins')->where('id', $adminId)->first();
        $this->assertSame($username, (string) $admin->username);
        $this->assertSame($email, (string) $admin->email);
        $this->assertTrue(Hash::check('new-secret', (string) $admin->password));
        $this->assertSame('13900004321', (string) $admin->mobile);
        $this->assertSame((string) $newRoleId, (string) $admin->role_id);
    }

    /**
     * 旧 start/stop GET 写入口必须被方法边界拦截，不能切换 admins.status。
     *
     * @return void
     */
    public function test_legacy_administrators_start_and_stop_get_entries_are_rejected(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createAdminRole(self::PREFIX . 'toggle-role');
        $adminId = $this->createManagedAdmin($roleId);

        foreach ([
            'stop' => '/index/admin/Administrators/stop',
            'start' => '/index/admin/Administrators/start',
        ] as $label => $uri) {
            $response = $this->legacyRequest($actor)->getJson($uri . '?id=' . $adminId);

            $response->assertStatus(405)
                ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
                ->assertJsonPath('data.legacy_uri', 'index/admin/Administrators/' . $label);
        }

        $this->assertSame(1, (int) DB::table('admins')->where('id', $adminId)->value('status'));
    }

    /**
     * 旧 del GET 写入口必须被方法边界拦截，不能软删除后台管理员。
     *
     * @return void
     */
    public function test_legacy_administrators_del_get_entry_is_rejected_without_soft_delete(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createAdminRole(self::PREFIX . 'delete-role');
        $adminId = $this->createManagedAdmin($roleId);

        $response = $this->legacyRequest($actor)
            ->getJson('/index/admin/Administrators/del?id=' . $adminId);

        $response->assertStatus(405)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('data.legacy_uri', 'index/admin/Administrators/del');

        $deletedAt = DB::table('admins')->where('id', $adminId)->value('deleted_at');
        $this->assertNull($deletedAt);
    }

    /**
     * 缺少 id 时旧 stop 也不能绕过方法边界进入校验链。
     *
     * @return void
     */
    public function test_legacy_administrators_stop_rejects_missing_id_via_method_boundary(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createAdminRole(self::PREFIX . 'missing-id-role');
        $adminId = $this->createManagedAdmin($roleId);

        $response = $this->legacyRequest($actor)
            ->getJson('/index/admin/Administrators/stop');

        $response->assertStatus(405)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('data.legacy_uri', 'index/admin/Administrators/stop');

        $this->assertSame(1, (int) DB::table('admins')->where('id', $adminId)->value('status'));
    }

    /**
     * 非管理员账号模块的旧 GET 写入口仍保持失败关闭。
     *
     * @return void
     */
    public function test_non_administrator_legacy_get_mutation_still_fails_closed(): void
    {
        $actor = $this->ensureSuperAdmin();

        $response = $this->legacyRequest($actor)
            ->getJson('/index/admin/role/del?id=1');

        $response->assertStatus(405)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('data.allowed_method', 'POST');
    }

    /**
     * 创建绕过旧后台中间件后的测试请求对象。
     *
     * @param Admin $actor 当前登录后台管理员。
     * @return self 当前测试实例，已绑定 admin guard 登录态。
     */
    private function legacyRequest(Admin $actor): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin');
    }

    /**
     * 创建超级管理员。
     *
     * @return Admin admin guard 可识别的后台管理员。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => self::PREFIX . 'super',
                'email' => self::PREFIX . 'super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建后台角色。
     *
     * @param string $name 角色名称，测试内保持唯一。
     * @return int roles.id。
     */
    private function createAdminRole(string $name): int
    {
        $now = time();

        DB::table('roles')->where('name', $name)->delete();

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_type' => 'admin',
            'description' => '旧后台管理员兼容测试角色',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 创建可被旧入口维护的后台管理员。
     *
     * @param int $roleId roles.id，用于验证编辑角色更新。
     * @return int 新建 admins.id。
     */
    private function createManagedAdmin(int $roleId): int
    {
        $now = time();
        $username = self::PREFIX . 'managed-' . uniqid();
        $email = $username . '@example.test';

        return (int) DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('old-secret'),
            'mobile' => '13900000000',
            'role_id' => $roleId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
