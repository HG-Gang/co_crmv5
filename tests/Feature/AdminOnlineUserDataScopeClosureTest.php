<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:54
 */

/**
 * 后台在线用户列表与强制下线接口的数据范围（数据权限）控制测试。
 *
 * 文件功能：
 * - 验证受限管理员无法列出或强制下线其数据范围之外的用户。
 * - 验证越权操作不会产生 operation_logs 操作日志。
 *
 * 适用场景：
 * - 多层级后台管理员的数据范围隔离，防止越权查看或操作在线用户。
 *
 * 入参例子：
 * - POST /api/admin/onlineUserList（数据范围查询）。
 * - POST /api/admin/forceOfflineUser/{onlineId}（强制下线）。
 *
 * 返回值：
 * - 列表返回 code=200、data.total=0（空结果）。
 * - 越权强制下线返回 ResponseCode::PERMISSION_DENIED。
 *
 * 异常或失败场景：
 * - AdminDataScopeService 返回空范围（whereRaw('1 = 0')）或 canAccessUser=false 时拒绝访问。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AdminOnlineUserDataScopeClosureTest extends TestCase
{
    use DatabaseTransactions;

    // 模拟受限管理员的数据范围为空，验证在线用户列表为空且强制下线被拒绝、无操作日志。
    public function test_restricted_admin_cannot_list_or_force_offline_out_of_scope_user(): void
    {
        $admin = new Admin();
        $admin->id = 880203;
        $admin->username = 'restricted-online-admin';
        $admin->status = 1;

        $now = time();
        $onlineId = (int) DB::table('user_onlines')->insertGetId([
            'user_id' => 88020301,
            'last_activity' => $now,
            'ip_address' => '203.0.113.203',
            'user_agent' => 'scope-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('apply')
            ->once()
            ->andReturnUsing(static function ($query) {
                return $query->whereRaw('1 = 0');
            });
        $scope->shouldReceive('canAccessUser')->once()->andReturnFalse();
        $this->app->instance(AdminDataScopeService::class, $scope);

        $client = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');

        $client->postJson('/api/admin/onlineUserList')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
        $client->postJson('/api/admin/forceOfflineUser/' . $onlineId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertDatabaseHas('user_onlines', ['id' => $onlineId]);
        $this->assertDatabaseMissing('operation_logs', ['order_no' => 'online_user:' . $onlineId]);
    }
}
