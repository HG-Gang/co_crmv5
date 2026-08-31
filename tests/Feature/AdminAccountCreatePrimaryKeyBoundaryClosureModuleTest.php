<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员创建接口主键边界闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/createAdmin 创建管理员时，请求体伪造的 id 主键被忽略，主键由数据库自增分配。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 257 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块的创建入口回归测试，防止调用方指定 admins.id 覆盖已有记录（如 id=1 超级管理员）。
 *
 * 入参例子：
 * - POST /api/admin/createAdmin
 *   {
 *     "id": 1,
 *     "username": "admin-create-spoofed-id",
 *     "email": "admin-create-spoofed-id@example.test",
 *     "password": "create-spoof-secret",
 *     "mobile": "13925700000",
 *     "status": 1
 *   }
 *
 * 方法功能：
 * - test_admin_create_ignores_spoofed_primary_key：伪造主键创建管理员，断言新记录 id 不为 1 且 id=1 超级管理员未被覆盖。
 * - test_final_checklist_records_admin_create_primary_key_boundary：校验最终清单文档包含第 257 项边界记录。
 *
 * 返回值：
 * - 成功时接口返回 code=CREATED，测试通过；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若控制器按表单 id 写入主键，会覆盖 id=1 超级管理员，测试断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountCreatePrimaryKeyBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 伪造主键创建管理员：断言新记录 id 不被伪造值占用，且 id=1 超级管理员未被覆盖。
     *
     * @return void
     */
    public function test_admin_create_ignores_spoofed_primary_key(): void
    {
        $actor = $this->ensureSuperAdmin();
        $username = 'admin-create-spoofed-id';
        $email = 'admin-create-spoofed-id@example.test';

        DB::table('admins')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createAdmin', [
                'id' => 1,
                'username' => $username,
                'email' => $email,
                'password' => 'create-spoof-secret',
                'mobile' => '13925700000',
                'status' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $created = DB::table('admins')->where('email', $email)->first();
        $superAdmin = DB::table('admins')->where('id', 1)->first();

        $this->assertNotNull($created);
        $this->assertNotSame(1, (int) $created->id);
        $this->assertSame($username, (string) $created->username);
        $this->assertTrue(Hash::check('create-spoof-secret', (string) $created->password));

        $this->assertSame('admin-create-boundary-super', (string) $superAdmin->username);
        $this->assertSame('admin-create-boundary-super@example.test', (string) $superAdmin->email);
        $this->assertFalse(Hash::check('create-spoof-secret', (string) $superAdmin->password));
    }

    /**
     * 校验最终清单文档第 257 项记录了创建接口主键边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_create_primary_key_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 257.', $checklist);
        $this->assertStringContainsString('AdminController::store', $checklist);
        $this->assertStringContainsString('/api/admin/createAdmin', $checklist);
        $this->assertStringContainsString('admins.id', $checklist);
        $this->assertStringContainsString('AdminAccountCreatePrimaryKeyBoundaryClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-create-boundary-super',
                'email' => 'admin-create-boundary-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }
}
