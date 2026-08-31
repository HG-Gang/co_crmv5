<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:08
 */

/**
 * AdminRiskPositionActionIdentityClosureModuleTest
 *
 * 文件功能：
 * - 验证持仓风险读模型与强平动作间的安全订单身份契约：含 2024 前持仓、身份解析到匹配的 MT4 持仓、无显式映射不可操作且不猜测 login、金额与风险值保留精确字符串。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
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
use Tests\TestCase;

/**
 * 锁定持仓风险读模型与强平动作之间的安全订单身份契约。
 */
class AdminRiskPositionActionIdentityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 被观察的 CRM 业务用户 ID。风险持仓与强平动作的身份判定以它的 user_trades 本地事实源为准。
     * @var int
     */
    private const USER_ID = 998261;
    /**
     * USER_ID 映射的真实 MT4 登录号（user_infos.mt4_code）。断言强平网关收到的是它而不是 CRM ID。
     * @var int
     */
    private const MT4_LOGIN = 898261;
    /**
     * 2024 年后的开仓订单 ticket。用于验证新持仓必须完成 MT4 映射后才可强平。
     * @var int
     */
    private const CURRENT_TICKET = 9926101;
    /**
     * 2023 年底的历史订单 ticket。验证不带日期参数的持仓查询仍包含 2024 年前订单。
     * @var int
     */
    private const OLD_TICKET = 9926102;
    /**
     * mt4_code=0（无 MT4 映射）的业务用户 ID。验证其持仓不可强平且绝不猜测 login。
     * @var int
     */
    private const UNMAPPED_USER_ID = 998262;
    /**
     * UNMAPPED_USER_ID 名下且 mt4 侧 login=0 的订单 ticket，构成"无映射"场景。
     * @var int
     */
    private const UNMAPPED_TICKET = 9926103;
    /**
     * 夹具订单的统一合约代码标记。tearDown 按它清理 user_trades，避免误删他人订单。
     * @var string
     */
    private const SYMBOL = 'RISK-ID';

    public function test_modern_positions_without_dates_include_pre_2024_open_positions(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedUser(self::USER_ID, self::MT4_LOGIN);
        $this->seedUserTrade(self::CURRENT_TICKET, self::USER_ID, '2026-08-18 10:00:00');
        $this->seedUserTrade(self::OLD_TICKET, self::USER_ID, '2023-12-31 23:59:59');

        $response = $this->asAdmin($admin)->postJson('/api/admin/riskPositions', [
            'user_id' => self::USER_ID,
            'symbol' => self::SYMBOL,
            'per_page' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 2);

        $tickets = array_map('intval', array_column($response->json('data.records.data'), 'ticket'));
        sort($tickets);
        $this->assertSame([self::CURRENT_TICKET, self::OLD_TICKET], $tickets);
    }

    public function test_position_action_identity_resolves_the_matching_open_mt4_trade(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedUser(self::USER_ID, self::MT4_LOGIN);
        $this->seedUserTrade(self::CURRENT_TICKET, self::USER_ID, '2026-08-18 10:00:00');
        $mappedTradeId = $this->seedMt4Trade(self::CURRENT_TICKET, self::MT4_LOGIN);
        $gateway = new class implements RiskForceCloseGateway {
            /**
             * close() 收到的 [login, ticket, comment] 调用记录。断言强平网关只收到真实映射订单且参数正确。
             * @var array<int, array{login:int,ticket:int,comment:string}>
             */
            public $calls = [];

            public function close(int $login, int $ticket, string $comment): RiskForceCloseResult
            {
                $this->calls[] = compact('login', 'ticket', 'comment');

                return RiskForceCloseResult::closed('risk-position-action-id');
            }
        };
        $this->app->instance(RiskForceCloseGateway::class, $gateway);

        $list = $this->asAdmin($admin)->postJson('/api/admin/riskPositions', [
            'ticket' => self::CURRENT_TICKET,
            'symbol' => self::SYMBOL,
        ]);

        $list->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.force_close_id', $mappedTradeId);

        $actionId = $list->json('data.records.data.0.force_close_id');
        $this->asAdmin($admin)
            ->postJson('/api/admin/riskForceClose/' . $actionId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.login', self::MT4_LOGIN)
            ->assertJsonPath('data.ticket', self::CURRENT_TICKET);

        $this->assertCount(1, $gateway->calls);
        $this->assertSame(self::MT4_LOGIN, $gateway->calls[0]['login']);
        $this->assertSame(self::CURRENT_TICKET, $gateway->calls[0]['ticket']);
    }

    public function test_position_without_explicit_mt4_mapping_is_not_actionable_and_never_guesses_login(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedUser(self::UNMAPPED_USER_ID, 0);
        $this->seedUserTrade(self::UNMAPPED_TICKET, self::UNMAPPED_USER_ID, '2026-08-18 11:00:00');
        $this->seedMt4Trade(self::UNMAPPED_TICKET, 0);

        $response = $this->asAdmin($admin)->postJson('/api/admin/riskPositions', [
            'user_id' => self::UNMAPPED_USER_ID,
            'symbol' => self::SYMBOL,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.login', null)
            ->assertJsonPath('data.records.data.0.force_close_id', null);
    }

    public function test_position_money_and_risk_values_remain_exact_decimal_strings(): void
    {
        $admin = $this->ensureSuperAdmin();
        $ticket = 9926104;
        $this->seedUser(self::USER_ID, self::MT4_LOGIN);
        $this->seedUserTrade(
            $ticket,
            self::USER_ID,
            '2026-08-18 12:00:00',
            '9007199254740.25',
            '-0.25'
        );

        $response = $this->asAdmin($admin)->postJson('/api/admin/riskPositions', [
            'ticket' => $ticket,
            'symbol' => self::SYMBOL,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.profit', '9007199254740.25')
            ->assertJsonPath('data.records.data.0.commission', '-0.25')
            ->assertJsonPath('data.records.data.0.risk_value', '9007199254740.00')
            ->assertJsonPath('data.records.data.0.abs_comm', '0.25')
            ->assertJsonPath('data.records.data.0.feng_xian_positionval', '3602879701896000.00')
            ->assertJsonPath('data.summary.total_profit', '9007199254740.25')
            ->assertJsonPath('data.summary.total_risk_value', '9007199254740.00');
    }

    private function asAdmin(Admin $admin): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'risk-position-action-super',
                'email' => 'risk-position-action-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function seedUser(int $userId, int $mt4Login): void
    {
        $now = time();
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Risk position action ' . $userId,
                'phone' => '',
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => ',' . $userId . ',',
                'mt4_code' => $mt4Login,
                'mt4_group' => 'RISK-LIVE',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function seedUserTrade(
        int $ticket,
        int $userId,
        string $openTime,
        string $profit = '125',
        string $commission = '-2'
    ): void
    {
        $now = time();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => self::SYMBOL,
            'digits' => 2,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => $openTime,
            'open_price' => 100,
            'stop_loss' => 90,
            'take_profit' => 130,
            'close_time' => '1970-01-01 00:00:00',
            'commission' => $commission,
            'swaps' => 0,
            'close_price' => 0,
            'profit' => $profit,
            'margin_rate' => 1,
            'comment' => 'risk action identity',
            'modify_time' => $openTime,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedMt4Trade(int $ticket, int $login): int
    {
        $now = time();

        return (int) DB::table('mt4_trades')->insertGetId([
            'ticket' => $ticket,
            'login' => $login,
            'symbol' => self::SYMBOL,
            'cmd' => 0,
            'volume' => 100,
            'open_price' => 100,
            'close_price' => null,
            'commission' => -2,
            'swaps' => 0,
            'profit' => 125,
            'open_time' => $now - 300,
            'close_time' => 0,
            'comment' => 'risk action identity',
            'modify_time' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
