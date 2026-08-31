<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 03:15
 */

/**
 * AdminLegacyIpAddressDetailClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台异常 IP 登录明细入口闭环：点号下划线 IP 还原为点分 login_ip 并转发现代接口，畸形/空 IP 直接失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台遗留"异常 IP 登录明细"入口 fengXian/IpaddressDeatail/{idaddr} 闭环测试。
 *
 * 文件目的：
 * - 锁定旧后台 FengXianManageController@fengXian_Ipaddress_detail 的迁移行为：
 *   旧 URL 以 {idaddr} 传递 IP，点号替换为下划线（如 192_168_1_1），
 *   迁移后还原为点分 login_ip 并转发到现代 admin_api_riskIpDetail。
 * - 畸形 IP 直接按现代校验失败关闭，避免把脏参数送进明细查询。
 */
class AdminLegacyIpAddressDetailClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_ipaddress_detail_restores_underscore_ip_and_returns_log_rows(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987301;
        $this->seedUserWithLoginLog($userId, 'Legacy Ip Detail User', '192.168.1.1');
        $this->seedLoginLog(987302, '192.168.1.1');
        $this->seedLoginLog(987303, '10.0.0.9');

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/fengXian/IpaddressDeatail/192_168_1_1')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.login_ip', '192.168.1.1');

        $records = $response->json('data.records');
        $userIds = collect($records['data'] ?? [])->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        $this->assertContains($userId, $userIds);
        $this->assertContains(987302, $userIds);
        $this->assertNotContains(987303, $userIds);
    }

    public function test_legacy_ipaddress_detail_invalid_ip_fails_closed(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/fengXian/IpaddressDeatail/not_a_real_ip')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_ipaddress_detail_empty_idaddr_fails_closed(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/fengXian/IpaddressDeatail/')
            ->assertNotFound();
    }

    private function seedUserWithLoginLog(int $userId, string $userName, string $ip): void
    {
        $this->seedLoginLog($userId, $ip);
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-ip-detail-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '178000' . substr((string) $userId, -4),
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedLoginLog(int $userId, string $ip): void
    {
        $now = time();

        DB::table('user_login_logs')->insert([
            'login_id' => $userId,
            'user_id' => $userId,
            'login_ip' => $ip,
            'ip_location' => 'Test Location',
            'user_agent' => 'Test UA',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
