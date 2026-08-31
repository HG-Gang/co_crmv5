<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员列表密码隐藏闭包测试。
 *
 * 文件功能：
 * - 验证管理员列表接口（/api/admin/adminList、/api/admin/admins）不返回 password 字段，且响应内容不含密码哈希。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 256 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块列表接口的敏感字段泄露回归测试。
 *
 * 入参例子：
 * - POST /api/admin/adminList
 *   {
 *     "per_page": 10000
 *   }
 *
 * 方法功能：
 * - test_admin_list_endpoints_do_not_expose_password_hashes：遍历两个列表接口，断言返回行无 password 键且响应体不含密码哈希。
 * - test_final_checklist_records_admin_list_password_hidden_boundary：校验最终清单文档包含第 256 项边界记录。
 *
 * 返回值：
 * - 列表成功返回 code=SUCCESS；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若列表返回 password 字段或泄露密码哈希，测试断言失败。
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

class AdminAccountListPasswordHiddenClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 遍历管理员列表接口：断言返回行无 password 键且响应体不含密码哈希。
     *
     * @return void
     */
    public function test_admin_list_endpoints_do_not_expose_password_hashes(): void
    {
        $actor = $this->ensureSuperAdmin();
        $adminId = $this->createManagedAdmin();
        $passwordHash = (string) DB::table('admins')->where('id', $adminId)->value('password');

        foreach (['/api/admin/adminList', '/api/admin/admins'] as $endpoint) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post($endpoint, [
                    'per_page' => 10000,
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $payload = $response->json();
            $rows = collect(data_get($payload, 'data.data', []));
            $row = $rows->firstWhere('id', $adminId);

            $this->assertNotNull($row, $endpoint . ' 必须返回测试管理员以证明断言覆盖真实列表数据。');
            $this->assertArrayNotHasKey('password', $row, $endpoint . ' 不能返回 password 字段。');
            $this->assertStringNotContainsString($passwordHash, $response->getContent(), $endpoint . ' 不能泄露管理员密码哈希。');
        }
    }

    /**
     * 校验最终清单文档第 256 项记录了列表密码隐藏边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_list_password_hidden_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 256.', $checklist);
        $this->assertStringContainsString('AdminController::index', $checklist);
        $this->assertStringContainsString('/api/admin/adminList', $checklist);
        $this->assertStringContainsString('/api/admin/admins', $checklist);
        $this->assertStringContainsString('admins.password', $checklist);
        $this->assertStringContainsString('AdminAccountListPasswordHiddenClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-list-hidden-super',
                'email' => 'admin-list-hidden-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedAdmin(): int
    {
        $now = time();
        $username = 'admin-list-hidden-password';
        $email = 'admin-list-hidden-password@example.test';

        DB::table('admins')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();

        return (int) DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('list-hidden-secret'),
            'mobile' => '13925600000',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
