<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/31
 * Time: 22:13
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台持仓汇总到交易明细的 MT4 账号映射闭环测试。
 *
 * 文件功能：
 * - 验证持仓汇总与交易明细统一使用 `user_infos.mt4_code = mt4_trades.login`。
 * - 验证业务 `user_id` 与 MT4 登录号不同时，下钻仍返回该业务用户的真实订单。
 * - 验证后台指定用户数据范围先约束业务用户，再映射到 MT4 登录号，不能泄露其它账号订单。
 *
 * 返回结果：
 * - 测试通过表示“汇总行 -> 业务用户 -> MT4 登录号 -> 交易订单”链路和数据权限一致。
 * - 测试失败表示页面虽然存在下钻按钮，但实际订单或权限范围仍使用了错误的用户编号。
 */
class AdminPositionSummaryTradeAccountMappingClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 被观察的 CRM 业务用户 ID。持仓汇总以 user_trades.user_id 本地事实源筛选，直查/下钻断言围绕它展开。
     * @var int
     */
    private const USER_ID = 984601;
    /**
     * USER_ID 映射的真实 MT4 登录号（user_infos.mt4_code）。验证接口输出真实登录号而非 CRM ID。
     * @var int
     */
    private const MT4_LOGIN = 884601;
    /**
     * 另一代理树下的业务用户 ID，用于证明受限管理员无法越权查看。
     * @var int
     */
    private const OUTSIDE_USER_ID = 984602;
    /**
     * OUTSIDE_USER_ID 映射的 MT4 登录号，其订单不得出现在范围内结果。
     * @var int
     */
    private const OUTSIDE_MT4_LOGIN = 884602;
    /**
     * 挂在 MT4_LOGIN 名下的订单 ticket。断言汇总/下钻返回的是这张真实订单。
     * @var int
     */
    private const MAPPED_TICKET = 994601;
    /**
     * user_id 被写成错误业务用户 ID 的诱饵订单 ticket。验证筛选不能命中错误映射。
     * @var int
     */
    private const DECOY_TICKET = 994602;
    /**
     * 范围外用户的订单 ticket。断言其不出现在受限结果中。
     * @var int
     */
    private const OUTSIDE_TICKET = 994603;
    /**
     * 夹具创建的角色主键，绑定持仓汇总权限与数据范围后挂到 ADMIN_ID。
     * @var int
     */
    private const ROLE_ID = 984690;
    /**
     * 夹具创建的后台管理员主键，登录后以受限数据范围调用持仓汇总接口。
     * @var int
     */
    private const ADMIN_ID = 984690;
    /**
     * 夹具订单的统一合约代码标记。tearDown 按它清理 user_trades，避免误删他人订单。
     * @var string
     */
    private const SYMBOL = 'MAPPING-DRILL';

    /**
     * 持仓汇总和交易明细必须命中同一条 MT4 映射订单。
     *
     * 设计原因：
     * - 页面传递的是业务 user_id，交易表保存的是 MT4 login，二者不能直接比较。
     * - 额外写入 login=user_id 的诱饵订单；若控制器仍沿用历史直连逻辑，返回盈亏和 ticket 会立即错误。
     *
     * @return void
     */
    public function test_position_summary_and_trade_detail_resolve_the_same_mapped_mt4_account(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedMappedTradeFixture();

        $summaryResponse = $this->asAdmin($admin)->post('/api/admin/positionSummaryList', [
            'user_id' => self::USER_ID,
            'per_page' => 5,
        ]);

        $summaryResponse->assertOk()
            ->assertJsonPath('code', 1000)
            ->assertJsonPath('data.records.total', 1);
        $this->assertSame(self::MT4_LOGIN, (int) $summaryResponse->json('data.records.data.0.mt4_login'));
        $this->assertSame(1, (int) $summaryResponse->json('data.records.data.0.total_orders'));
        $this->assertSame(45.25, (float) $summaryResponse->json('data.records.data.0.total_profit'));

        $tradeResponse = $this->asAdmin($admin)->post('/api/admin/tradeList', [
            'user_id' => self::USER_ID,
            'symbol' => self::SYMBOL,
            'per_page' => 5,
        ]);

        $tradeResponse->assertOk()
            ->assertJsonPath('code', 1000)
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.ticket', self::MAPPED_TICKET)
            ->assertJsonPath('data.records.data.0.login', self::MT4_LOGIN);
        $this->assertSame(self::USER_ID, (int) $tradeResponse->json('data.records.data.0.user.user_id'));
        $this->assertSame(45.25, (float) $tradeResponse->json('data.summary.total_profit'));
        $this->assertStringNotContainsString((string) self::DECOY_TICKET, $tradeResponse->getContent());
    }

    /**
     * custom_users 数据范围必须把业务用户集合转换为对应 MT4 登录号集合。
     *
     * @return void
     */
    public function test_trade_data_scope_maps_business_user_ids_to_mt4_logins(): void
    {
        $admin = $this->createRestrictedAdmin();
        $this->seedMappedTradeFixture();

        $response = $this->asAdmin($admin)->post('/api/admin/tradeList', [
            'symbol' => self::SYMBOL,
            'per_page' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 1000)
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.ticket', self::MAPPED_TICKET)
            ->assertJsonPath('data.records.data.0.login', self::MT4_LOGIN);
        $this->assertStringNotContainsString((string) self::DECOY_TICKET, $response->getContent());
        $this->assertStringNotContainsString((string) self::OUTSIDE_TICKET, $response->getContent());
    }

    /**
     * 迁移文档必须记录交易账号映射根因、执行链和运行时测试证据。
     *
     * @return void
     */
    public function test_mapping_closure_is_recorded_in_audit_and_final_checklist(): void
    {
        $audit = (string) file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md'));
        $checklist = (string) file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        foreach ([$audit, $checklist] as $document) {
            $this->assertStringContainsString('user_infos.mt4_code = mt4_trades.login', $document);
            $this->assertStringContainsString('AdminPositionSummaryTradeAccountMappingClosureModuleTest', $document);
        }

        $this->assertStringContainsString('业务用户 ID', $checklist);
        $this->assertStringContainsString('## 379. 2026-07-29', $checklist);
        $this->assertStringContainsString('custom_users', $checklist);
    }

    /**
     * 构造关闭认证中间件但保留控制器 admin guard 身份的请求。
     *
     * @param Admin $admin 当前测试管理员。
     * @return self 返回可继续发送后台请求的测试实例。
     */
    private function asAdmin(Admin $admin): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }

    /**
     * 创建或更新超级管理员夹具，超级管理员用于隔离账号映射本身，不叠加数据范围影响。
     *
     * @return Admin 后台超级管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'position-trade-mapping-super',
                'email' => 'position-trade-mapping-super@example.test',
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
     * 创建仅允许查看当前业务用户的后台管理员。
     *
     * @return Admin custom_users 数据范围管理员模型。
     */
    private function createRestrictedAdmin(): Admin
    {
        $now = time();

        DB::table('roles')->updateOrInsert(
            ['id' => self::ROLE_ID],
            [
                'name' => 'position_trade_mapping_scope',
                'guard_type' => 'admin',
                'description' => '持仓交易映射数据范围测试角色',
                'permissions' => null,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('role_data_scopes')->updateOrInsert(
            ['role_id' => self::ROLE_ID],
            [
                'scope_type' => 'custom_users',
                'agent_ids' => null,
                'user_ids' => json_encode([self::USER_ID]),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('admins')->updateOrInsert(
            ['id' => self::ADMIN_ID],
            [
                'role_id' => (string) self::ROLE_ID,
                'username' => 'position_trade_mapping_scope_admin',
                'email' => 'position-trade-mapping-scope@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(self::ADMIN_ID);
    }

    /**
     * 写入业务用户、真实 MT4 订单、错误直连诱饵订单和范围外订单。
     *
     * @return void
     */
    private function seedMappedTradeFixture(): void
    {
        $now = time();

        $this->upsertUser(self::USER_ID, self::MT4_LOGIN, 'Mapped trade owner', $now);
        $this->upsertUser(self::OUTSIDE_USER_ID, self::OUTSIDE_MT4_LOGIN, 'Outside trade owner', $now);
        $this->upsertMt4Account(self::MT4_LOGIN, 'Mapped MT4 owner', $now);
        $this->upsertMt4Account(self::OUTSIDE_MT4_LOGIN, 'Outside MT4 owner', $now);
        $this->upsertTrade(self::MAPPED_TICKET, self::MT4_LOGIN, 45.25, $now);
        $this->upsertTrade(self::DECOY_TICKET, self::USER_ID, 999.99, $now);
        $this->upsertTrade(self::OUTSIDE_TICKET, self::OUTSIDE_MT4_LOGIN, 888.88, $now);
    }

    /**
     * 写入 MT4 账户快照，确保持仓汇总行能够同时返回账号信息和交易聚合。
     *
     * @param int $login MT4 登录号。
     * @param string $name MT4 侧账号姓名。
     * @param int $now 固定时间戳。
     * @return void
     */
    private function upsertMt4Account(int $login, string $name, int $now): void
    {
        DB::table('mt4_users')->updateOrInsert(
            ['login' => $login],
            [
                'name' => $name,
                'group' => 'mapping-live',
                'balance' => 1000,
                'equity' => 1045.25,
                'margin' => 100,
                'margin_free' => 945.25,
                'leverage' => 100,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 写入业务用户和真实 MT4 登录号映射。
     *
     * @param int $userId 业务用户 ID。
     * @param int $mt4Login MT4 登录号。
     * @param string $userName 业务用户名。
     * @param int $now 固定时间戳。
     * @return void
     */
    private function upsertUser(int $userId, int $mt4Login, string $userName, int $now): void
    {
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'mt4_code' => $mt4Login,
                'mt4_group' => 'mapping-live',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 写入单条 MT4 交易订单。
     *
     * @param int $ticket MT4 订单号。
     * @param int $login MT4 登录号。
     * @param float $profit 订单盈亏，用于区分真实映射与诱饵结果。
     * @param int $now 固定时间戳。
     * @return void
     */
    private function upsertTrade(int $ticket, int $login, float $profit, int $now): void
    {
        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $login,
                'symbol' => self::SYMBOL,
                'cmd' => 0,
                'volume' => 100,
                'open_price' => 100,
                'close_price' => 101,
                'commission' => -1,
                'swaps' => 0,
                'profit' => $profit,
                'open_time' => $now - 600,
                'close_time' => 0,
                'comment' => 'position trade mapping ' . $ticket,
                'modify_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
