<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 22:45
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\RiskForceCloseGateway;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Risk\RiskForceCloseResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 后台风控业务用户与 MT4 交易账号映射闭环测试。
 *
 * 文件功能：
 * - 验证风险持仓读取 `user_trades.user_id` 本地事实源，并用 `user_infos.mt4_code` 输出真实 MT4 登录号。
 * - 验证 `custom_users` 直接限制业务用户，不能命中错误用户 ID 诱饵订单。
 * - 验证异常 IP 详情沿业务用户映射统计开仓、平仓数量。
 * - 验证强平网关收到真实订单的 MT4 登录号和 ticket，同时拒绝范围外订单。
 *
 * 返回结果：
 * - 测试通过表示风险列表、异常 IP 交易统计、数据范围和强平动作共用同一账号映射。
 * - 测试失败表示至少一条风险链仍把 CRM 业务用户 ID 当成 MT4 登录号使用。
 */
class AdminRiskTradeAccountMappingClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 被观察的 CRM 业务用户 ID。风险持仓以 user_trades.user_id 本地事实源筛选用例数据。
     * @var int
     */
    private const USER_ID = 984701;
    /**
     * USER_ID 映射的真实 MT4 登录号（user_infos.mt4_code）。验证接口输出与强平网关收到的是它而不是 CRM ID。
     * @var int
     */
    private const MT4_LOGIN = 884701;
    /**
     * 数据范围外另一代理树下的业务用户 ID。用于证明受限管理员看不到范围外持仓。
     * @var int
     */
    private const OUTSIDE_USER_ID = 984702;
    /**
     * OUTSIDE_USER_ID 映射的 MT4 登录号，其订单不得出现在范围内结果中。
     * @var int
     */
    private const OUTSIDE_MT4_LOGIN = 884702;
    /**
     * 挂在 MT4_LOGIN 名下的开仓订单 ticket。断言风险列表与强平网关收到的是这张真实订单。
     * @var int
     */
    private const MAPPED_OPEN_TICKET = 994701;
    /**
     * user_id 被故意写成错误业务用户 ID 的诱饵开仓订单。证明筛选不能命中错误映射。
     * @var int
     */
    private const DECOY_OPEN_TICKET = 994702;
    /**
     * 范围外用户（OUTSIDE_MT4_LOGIN）的开仓订单 ticket。断言其不出现在受限结果与强平参数中。
     * @var int
     */
    private const OUTSIDE_OPEN_TICKET = 994703;
    /**
     * MT4_LOGIN 名下的已平仓订单 ticket。用于异常 IP 详情的开仓/平仓数量统计断言。
     * @var int
     */
    private const MAPPED_CLOSED_TICKET = 994704;
    /**
     * 第二张诱饵开仓订单 ticket，进一步验证按 user_id 直查不会误命中。
     * @var int
     */
    private const SECOND_DECOY_OPEN_TICKET = 994705;
    /**
     * 夹具创建的角色主键，绑定风险模块权限与数据范围后挂到 ADMIN_ID。
     * @var int
     */
    private const ROLE_ID = 984790;
    /**
     * 夹具创建的后台管理员主键，登录后携带受限数据范围调用风险接口。
     * @var int
     */
    private const ADMIN_ID = 984790;
    /**
     * 夹具订单的统一合约代码标记。tearDown 按它清理 user_trades，避免误删他人订单。
     * @var string
     */
    private const SYMBOL = 'RISK-MAPPING';
    /**
     * 夹具登录行写入的 IP（TEST-NET-3 段，不会命中真实用户）。异常 IP 详情用例据此统计。
     * @var string
     */
    private const LOGIN_IP = '203.0.113.247';

    /**
     * 风险持仓的业务用户筛选必须返回该用户映射 MT4 账号下的真实订单。
     *
     * @return void
     */
    public function test_risk_positions_resolve_business_user_to_mapped_mt4_login(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedRiskMappingFixture();

        $response = $this->asAdmin($admin)->post('/api/admin/riskPositions', [
            'user_id' => self::USER_ID,
            'symbol' => self::SYMBOL,
            'per_page' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 1);

        $this->assertSame(self::MAPPED_OPEN_TICKET, (int) $response->json('data.records.data.0.ticket'));
        $this->assertSame(self::MT4_LOGIN, (int) $response->json('data.records.data.0.login'));
        $this->assertSame(self::USER_ID, (int) $response->json('data.records.data.0.user_id'));
        $this->assertSame(125.0, (float) $response->json('data.summary.total_profit'));
        $this->assertSame(123.0, (float) $response->json('data.summary.total_risk_value'));
        $this->assertSame(0.0, (float) $response->json('data.summary.total_margin'));
        $this->assertTrue(Schema::hasColumn('user_trades', 'margin_rate'));
        $this->assertSame('6150.00', $response->json('data.records.data.0.feng_xian_positionval'));
        $this->assertStringNotContainsString((string) self::DECOY_OPEN_TICKET, $response->getContent());
    }

    /**
     * custom_users 风险数据范围必须转换为 MT4 登录号，并排除诱饵订单及范围外订单。
     *
     * @return void
     */
    public function test_risk_positions_custom_user_scope_maps_to_mt4_login(): void
    {
        $admin = $this->createRestrictedAdmin();
        $this->seedRiskMappingFixture();

        $response = $this->asAdmin($admin)->post('/api/admin/riskPositions', [
            'symbol' => self::SYMBOL,
            'per_page' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 1);

        $this->assertSame(self::MAPPED_OPEN_TICKET, (int) $response->json('data.records.data.0.ticket'));
        $this->assertSame(self::MT4_LOGIN, (int) $response->json('data.records.data.0.login'));
        $this->assertSame(self::USER_ID, (int) $response->json('data.records.data.0.user_id'));
        $content = $response->getContent();
        $this->assertStringNotContainsString((string) self::DECOY_OPEN_TICKET, $content);
        $this->assertStringNotContainsString((string) self::SECOND_DECOY_OPEN_TICKET, $content);
        $this->assertStringNotContainsString((string) self::OUTSIDE_OPEN_TICKET, $content);
    }

    /**
     * 异常 IP 详情必须通过 mt4_code 统计当前业务用户的真实开仓和平仓订单。
     *
     * @return void
     */
    public function test_risk_ip_detail_counts_trades_through_mt4_account_mapping(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedRiskMappingFixture();

        $response = $this->asAdmin($admin)->post('/api/admin/riskIpDetail', [
            'login_ip' => self::LOGIN_IP,
            'user_id' => self::USER_ID,
            'per_page' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 1);

        $this->assertSame(self::USER_ID, (int) $response->json('data.records.data.0.user_id'));
        $this->assertSame(1, (int) $response->json('data.records.data.0.open_order_count'));
        $this->assertSame(1, (int) $response->json('data.records.data.0.closed_order_count'));
    }

    /**
     * 强平必须把映射订单的真实 login 与 ticket 发给网关，并阻止受限管理员处理范围外订单。
     *
     * @return void
     */
    public function test_force_close_uses_real_mt4_target_after_business_scope_check(): void
    {
        $admin = $this->createRestrictedAdmin();
        $this->seedRiskMappingFixture();
        $gateway = new class implements RiskForceCloseGateway {
            /**
             * 已收到的强平调用，用于验证控制器传递的真实 MT4 目标。
             *
             * @var array<int, array{login:int,ticket:int,comment:string}>
             */
            public $calls = [];

            /**
             * 记录强平参数并返回明确成功结果。
             *
             * @param int $login 真实 MT4 登录号。
             * @param int $ticket 真实 MT4 订单号。
             * @param string $comment CRM 风控审计备注。
             * @return RiskForceCloseResult 模拟 MT4 已确认平仓的结果。
             */
            public function close(int $login, int $ticket, string $comment): RiskForceCloseResult
            {
                $this->calls[] = compact('login', 'ticket', 'comment');

                return RiskForceCloseResult::closed('risk-mapping-reference');
            }
        };
        $this->app->instance(RiskForceCloseGateway::class, $gateway);

        $mappedTradeId = (int) DB::table('mt4_trades')
            ->where('ticket', self::MAPPED_OPEN_TICKET)
            ->value('id');
        $outsideTradeId = (int) DB::table('mt4_trades')
            ->where('ticket', self::OUTSIDE_OPEN_TICKET)
            ->value('id');

        $this->asAdmin($admin)
            ->postJson('/api/admin/riskForceClose/' . $mappedTradeId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.login', self::MT4_LOGIN)
            ->assertJsonPath('data.ticket', self::MAPPED_OPEN_TICKET)
            ->assertJsonPath('data.provider_reference', 'risk-mapping-reference');

        $this->assertCount(1, $gateway->calls);
        $this->assertSame(self::MT4_LOGIN, $gateway->calls[0]['login']);
        $this->assertSame(self::MAPPED_OPEN_TICKET, $gateway->calls[0]['ticket']);

        $this->asAdmin($admin)
            ->postJson('/api/admin/riskForceClose/' . $outsideTradeId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
        $this->assertCount(1, $gateway->calls);
    }

    /**
     * 迁移文档必须记录风险账号映射、权限链、强平边界和真实缺失字段。
     *
     * @return void
     */
    public function test_risk_mapping_closure_is_recorded_in_audit_and_checklist(): void
    {
        $audit = (string) file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md'));
        $checklist = (string) file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        foreach ([$audit, $checklist] as $document) {
            $this->assertStringContainsString('user_infos.mt4_code = mt4_trades.login', $document);
            $this->assertStringContainsString('AdminRiskTradeAccountMappingClosureModuleTest', $document);
            $this->assertStringContainsString('MARGIN_RATE', $document);
        }

        $this->assertStringContainsString('RiskController::positions', $checklist);
        $this->assertStringContainsString('RiskController::riskIpDetail', $checklist);
        $this->assertStringContainsString('RiskForceCloseGateway', $checklist);
        $this->assertStringContainsString('custom_users', $checklist);
        $this->assertStringContainsString('## 317. 2026-07-09', $checklist);
    }

    /**
     * 构造关闭鉴权中间件但保留 admin guard 身份的请求。
     *
     * @param Admin $admin 当前测试管理员。
     * @return self 可继续发送后台请求的测试实例。
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
     * 创建超级管理员，隔离账号映射本身而不叠加数据范围限制。
     *
     * @return Admin 后台超级管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'risk-mapping-super',
                'email' => 'risk-mapping-super@example.test',
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
     * 创建只允许访问当前业务用户的 custom_users 管理员。
     *
     * @return Admin 受限后台管理员模型。
     */
    private function createRestrictedAdmin(): Admin
    {
        $now = time();

        DB::table('roles')->updateOrInsert(
            ['id' => self::ROLE_ID],
            [
                'name' => 'risk_trade_mapping_scope',
                'guard_type' => 'admin',
                'description' => '风险交易账号映射数据范围测试角色',
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
                'username' => 'risk_trade_mapping_scope_admin',
                'email' => 'risk-trade-mapping-scope@example.test',
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
     * 写入业务用户、真实映射订单、错误直连诱饵订单、范围外订单和异常 IP 日志。
     *
     * @return void
     */
    private function seedRiskMappingFixture(): void
    {
        $now = time();

        $this->upsertUser(self::USER_ID, self::MT4_LOGIN, 'Mapped risk owner', $now);
        $this->upsertUser(self::OUTSIDE_USER_ID, self::OUTSIDE_MT4_LOGIN, 'Outside risk owner', $now);
        $this->upsertTrade(self::MAPPED_OPEN_TICKET, self::MT4_LOGIN, 0, -25.5, -2.0, $now);
        $this->upsertTrade(self::MAPPED_CLOSED_TICKET, self::MT4_LOGIN, $now - 60, 8.5, -1.0, $now);
        $this->upsertTrade(self::DECOY_OPEN_TICKET, self::USER_ID, 0, 999.99, -1.0, $now);
        $this->upsertTrade(self::SECOND_DECOY_OPEN_TICKET, self::USER_ID, 0, 888.88, -1.0, $now);
        $this->upsertTrade(self::OUTSIDE_OPEN_TICKET, self::OUTSIDE_MT4_LOGIN, 0, 777.77, -1.0, $now);
        $this->upsertLocalRiskTrade(self::MAPPED_OPEN_TICKET, self::USER_ID, 125, -2, $now);
        $this->upsertLocalRiskTrade(self::DECOY_OPEN_TICKET, self::MT4_LOGIN, 999.99, -1, $now);
        $this->upsertLocalRiskTrade(self::SECOND_DECOY_OPEN_TICKET, self::MT4_LOGIN, 888.88, -1, $now);
        $this->upsertLocalRiskTrade(self::OUTSIDE_OPEN_TICKET, self::OUTSIDE_USER_ID, 777.77, -1, $now);

        DB::table('user_login_logs')->insert([
            'login_id' => 0,
            'user_id' => self::USER_ID,
            'login_ip' => self::LOGIN_IP,
            'ip_location' => 'Risk mapping test network',
            'user_agent' => 'Risk mapping test browser',
            'created_at' => $now - 30,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入业务用户与 MT4 登录号的显式映射。
     *
     * @param int $userId CRM 业务用户 ID。
     * @param int $mt4Login MT4 登录号。
     * @param string $userName 风险列表展示用户名。
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
                'mt4_group' => 'risk-mapping-live',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now - 3600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 写入单条 MT4 订单，close_time=0 表示未平仓，大于 0 表示已平仓。
     *
     * @param int $ticket MT4 订单号。
     * @param int $login MT4 登录号或专用于捕获错误直连的诱饵值。
     * @param int $closeTime 平仓时间戳。
     * @param float $profit 订单盈亏。
     * @param float $commission 订单手续费。
     * @param int $now 固定时间戳。
     * @return void
     */
    private function upsertTrade(
        int $ticket,
        int $login,
        int $closeTime,
        float $profit,
        float $commission,
        int $now
    ): void {
        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $login,
                'symbol' => self::SYMBOL,
                'cmd' => 0,
                'volume' => 100,
                'open_price' => 100,
                'close_price' => $closeTime > 0 ? 101 : null,
                'commission' => $commission,
                'swaps' => 0,
                'profit' => $profit,
                'open_time' => $now - 600,
                'close_time' => $closeTime,
                'comment' => 'risk account mapping ' . $ticket,
                'modify_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * 写入持仓风险使用的本地 user_trades 事实行。
     */
    private function upsertLocalRiskTrade(
        int $ticket,
        int $userId,
        float $profit,
        float $commission,
        int $now
    ): void {
        DB::table('user_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'user_id' => $userId,
                'symbol' => self::SYMBOL,
                'digits' => 2,
                'cmd' => 0,
                'volume' => 100,
                'open_time' => date('Y-m-d H:i:s', $now - 600),
                'open_price' => 100,
                'stop_loss' => 0,
                'take_profit' => 0,
                'close_time' => '1970-01-01 00:00:00',
                'commission' => $commission,
                'swaps' => 0,
                'close_price' => 0,
                'profit' => $profit,
                'margin_rate' => 1,
                'comment' => 'risk local account mapping ' . $ticket,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
