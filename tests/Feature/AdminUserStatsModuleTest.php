<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:10
 */

/**
 * AdminUserStatsModuleTest
 *
 * 文件功能：
 * - 验证后台用户列表旧交易统计与 total row：cmd=6 且 Deposit/Withdrawal 备注计入入出金、cmd=0 用于手数/手续费/盈亏/库存费统计，前端配置已暴露。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
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
use Tests\TestCase;

/**
 * 后台用户统计列迁移闭环测试。
 *
 * 文件作用：
 * - 验证旧项目 CustomerController 用户列表里的交易统计列已迁移到新项目用户列表接口。
 * - 验证当前保留的 Layui 与 CrmUI 后台前端配置都能展示这些字段。
 */
class AdminUserStatsModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 用户列表接口必须返回旧项目统计字段和当前筛选范围的汇总行。
     *
     * 执行链路：
     * - 构造普通客户、入金、出金和平仓交易。
     * - 请求 /api/admin/users。
     * - 断言 data.list.0 返回单个用户统计，data.totalRow 返回同一筛选范围汇总。
     */
    public function test_user_list_endpoint_exposes_legacy_trade_statistics_and_total_row(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 986101;
        $now = strtotime('2026-06-15 10:00:00');

        $this->upsertUserStatsFixture($userId, 'Stats Customer', $now);
        $this->insertUserTrade($userId, 98610101, 6, 0, 0, 100, 0, 'Deposit approved', '2026-06-16 10:00:00');
        $this->insertUserTrade($userId, 98610102, 6, 0, 0, -40, 0, 'Withdrawal approved', '2026-06-17 10:00:00');
        $this->insertUserTrade($userId, 98610103, 0, 300, -2.3, 12.5, -0.7, 'EURUSD closed trade', '2026-06-18 10:00:00');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/users', [
                'account_type' => 2,
                'user_id' => $userId,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'limit' => 10,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);
        $response->assertJsonPath('data.total', 1);
        $this->assertSame($userId, (int) $response->json('data.list.0.user_id'));
        $this->assertSame('100.00', $response->json('data.list.0.total_yuerj'));
        $this->assertSame('40.00', $response->json('data.list.0.total_yuecj'));
        $this->assertSame('60.00', $response->json('data.list.0.total_net_worth'));
        $this->assertSame('2.30', $response->json('data.list.0.total_comm'));
        $this->assertSame('12.50', $response->json('data.list.0.total_profit'));
        $this->assertSame(3, (int) $response->json('data.list.0.total_volume'));
        $this->assertSame('0.70', $response->json('data.list.0.total_swaps'));

        $this->assertSame('100.00', $response->json('data.totalRow.total_yuerj'));
        $this->assertSame('40.00', $response->json('data.totalRow.total_yuecj'));
        $this->assertSame('60.00', $response->json('data.totalRow.total_net_worth'));
        $this->assertSame('2.30', $response->json('data.totalRow.total_comm'));
        $this->assertSame('12.50', $response->json('data.totalRow.total_profit'));
        $this->assertSame(3, (int) $response->json('data.totalRow.total_volume'));
        $this->assertSame('0.70', $response->json('data.totalRow.total_swaps'));
    }

    /**
     * 当前后台前端必须暴露用户统计字段。
     *
     * 解决的问题：
     * - 后端字段已返回但前端未配置列时，页面仍无法形成旧项目列表闭环。
     * - Layui 需要 totalRow: true 才能展示接口返回的 totalRow 汇总行。
     * - 历史 Naive 入口已被 BladeOnlyFrontendArchitectureTest 删除约束，本测试不重新制造旧入口。
     */
    public function test_user_stats_frontend_configs_are_exposed(): void
    {
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        foreach (['total_yuerj', 'total_yuecj', 'total_net_worth', 'total_comm', 'total_profit', 'total_volume', 'total_swaps'] as $field) {
            $this->assertStringContainsString("{field: '" . $field . "'", $layui);
        }

        $this->assertStringContainsString('totalRow: true', $layui);
        $this->assertStringContainsString(
            "'columns' => ['user_id', 'user_name', 'email', 'phone', 'total_yuerj', 'total_yuecj', 'total_net_worth', 'total_comm', 'total_profit', 'total_volume', 'total_swaps', 'auth_status', 'created_at']",
            $crmui
        );
        $this->assertDirectoryDoesNotExist(public_path('js/apps/naive-admin'));
    }

    /**
     * 创建后台超级管理员夹具。
     *
     * 返回值表示 admin guard 登录用户，用于绕过真实登录流程后仍保留管理员身份上下文。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'user-stats-admin',
                'email' => 'user-stats-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 写入普通客户夹具。
     *
     * 参数含义：
     * - $userId 表示业务用户 ID，对应 user_infos.user_id 和 user_logins.user_id。
     * - $now 表示注册时间戳，用于命中用户列表创建日期筛选。
     */
    private function upsertUserStatsFixture(int $userId, string $userName, int $now): void
    {
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_trades')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'user-stats-' . $userId . '@example.test',
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
            'phone' => '1881000' . substr((string) $userId, -4),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 1,
            'total_funds' => 60,
            'equity' => 72.5,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入 MT4 交易夹具。
     *
     * 执行结果：
     * - cmd=6 且 comment 命中 Deposit/Withdrawal 时用于入金、出金统计。
     * - cmd=0 且 margin_rate 不为 0 时用于手数、手续费、盈亏和库存费统计。
     */
    private function insertUserTrade(
        int $userId,
        int $ticket,
        int $cmd,
        int $volume,
        float $commission,
        float $profit,
        float $swaps,
        string $comment,
        string $closeTime
    ): void {
        $now = time();

        DB::table('user_trades')->where('ticket', $ticket)->delete();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'EURUSD',
            'digits' => 2,
            'cmd' => $cmd,
            'volume' => $volume,
            'open_time' => $closeTime,
            'open_price' => 1,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => $closeTime,
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 0,
            'conv_rate2' => 0,
            'commission' => $commission,
            'commission_agent' => 0,
            'swaps' => $swaps,
            'close_price' => 1,
            'profit' => $profit,
            'taxes' => 0,
            'comment' => $comment,
            'internal_id' => 0,
            'margin_rate' => $cmd === 6 ? 0 : 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => $closeTime,
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
