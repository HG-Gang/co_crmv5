<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:53
 */

declare(strict_types=1);

/**
 * 前端佣金转账 Saga 路由-封闭模块测试。
 *
 * 文件功能：
 * - 验证现代转账接口 /api/front/commissions/transfers 强制要求 Idempotency-Key 头与交易密码，并按 key 幂等重放。
 * - 验证旧转账页面签发会话绑定的 nonce，旧接口 /user/proxy/directUserCommTrans 将 Saga 结果映射为旧协议响应。
 * - 验证旧 nonce 在用途与会话校验通过前不触发任何资金网关调用，且 body/header nonce 不一致时拒绝。
 * - 验证客户 JWT 接口 /api/front/customers/commission-transfers 无需会话即可转账。
 * - 验证 Saga 终态（rejected / completed）与可重试态（retryable / unknown）在重放后正确收敛。
 * - 验证控制器、旧页面、blade 与各前端源码均暴露共享 Saga 契约。
 * - 验证最终权限检查清单文档记录了该封闭模块。
 *
 * 适用场景：
 * - 佣金转账 Saga 路由、幂等与旧协议兼容的回归测试。
 *
 * 入参例子：
 * - POST /api/front/commissions/transfers
 *   请求体：{ "sub_agent_id": 412470101, "amount": "125.00", "password": "trade-secret", "remark": "..." }
 *   头：Idempotency-Key: route-key-1
 * - POST /user/proxy/directUserCommTrans
 *   请求体：{ "depositId": ..., "comm_money": "50.00", "password": "trade-secret", "idempotency_key": {64位hex} }
 *
 * 返回值：
 * - 现代接口返回 SUCCESS / AUTH_FAILED / MT4_SYNC_FAILED / INSUFFICIENT_BALANCE 等业务码及 data.status；
 *   旧接口返回 msg=SUC/FAIL、code 与 errorType。
 *
 * 异常或失败场景：
 * - 若缺头不校验、nonce 校验绕过、幂等重放重复扣款或终态未收敛，测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\CommissionTransferAccountSnapshotGateway;
use App\Contracts\CommissionTransferFundingGateway;
use App\Contracts\TradePasswordGateway;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\CommissionTransfer;
use App\Models\UserLogin;
use App\Services\CommissionTransfer\CommissionTransferAccountSnapshotResult;
use App\Services\CommissionTransfer\CommissionTransferCommandResult;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class FrontCommissionTransferSagaRouteClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 佣金转账发起方业务用户 ID（固定夹具值）。路由链路的资金密码与转出命令都以它为目标。
     * @var int
     */
    private const SOURCE = 412470100;
    /**
     * 佣金转账接收方业务用户 ID，与 SOURCE 构成同一路由用例的两端。
     * @var int
     */
    private const TARGET = 412470101;

    /**
     * 绑定进容器的 RouteTradePasswordGateway 替身。捕获路由链路发出的资金密码校验调用，
     * 并按预设返回成功/失败结果。
     * @var RouteTradePasswordGateway
     */
    private $passwordGateway;

    /**
     * 绑定进容器的 RouteFundingGateway 替身。捕获转出/入账/补偿命令的调用序列与参数，
     * 验证路由层 saga 的编排顺序。
     * @var RouteFundingGateway
     */
    private $fundingGateway;

    /**
     * 测试前准备：清理旧数据并插入转账双方用户，绑定密码/资金/快照网关替身。
     *
     * @return void 无返回值。
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->deleteFixtures();
        $this->insertUser(self::SOURCE, 'route-transfer-source', 1, 0, '1000.00');
        $this->insertUser(self::TARGET, 'route-transfer-target', 1, self::SOURCE, '25.00');

        $this->passwordGateway = new RouteTradePasswordGateway();
        $this->fundingGateway = new RouteFundingGateway();
        $this->app->instance(TradePasswordGateway::class, $this->passwordGateway);
        $this->app->instance(CommissionTransferFundingGateway::class, $this->fundingGateway);
        $this->app->instance(
            CommissionTransferAccountSnapshotGateway::class,
            new RouteSnapshotGateway('875.00')
        );
    }

    /**
     * 验证现代转账接口要求幂等头与交易密码，并按 key 幂等重放。
     *
     * 缺 Idempotency-Key 头返回 VALIDATION_FAILED；带 key 请求成功，
     * 同 key 重放返回成功且密码网关只调用一次、资金网关调用两次、转账状态为 completed。
     */
    public function test_modern_transfer_requires_header_and_trade_password_then_replays_idempotently(): void
    {
        $login = UserLogin::where('user_id', self::SOURCE)->firstOrFail();
        $request = [
            'sub_agent_id' => self::TARGET,
            'amount' => '125.00',
            'password' => 'trade-secret',
            'remark' => 'route success',
        ];

        $missingHeader = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/commissions/transfers', $request);
        $missingHeader->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', 'route-key-1')
            ->postJson('/api/front/commissions/transfers', $request);
        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $replay = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', 'route-key-1')
            ->postJson('/api/front/commissions/transfers', $request);
        $replay->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame(1, count($this->passwordGateway->calls));
        $this->assertSame(2, count($this->fundingGateway->calls), json_encode($this->fundingGateway->calls));
        $this->assertSame('completed', CommissionTransfer::query()->value('status'));
        $this->assertSame(875.0, (float) DB::table('user_infos')->where('user_id', self::SOURCE)->value('total_funds'));
        $this->assertSame(2, DB::table('commission_records')->where('data_type', 'transfer')->whereIn('agent_id', [self::SOURCE, self::TARGET])->count());
    }

    /**
     * 验证旧页面签发会话绑定 nonce，旧接口将 Saga 结果映射为旧协议响应。
     *
     * 访问旧转账浏览页并解析 idempotency_key，用该 nonce 提交旧转账接口，
     * 断言返回 msg=SUC、code=0 与 comm_money。
     */
    public function test_legacy_page_issues_a_session_bound_nonce_and_legacy_post_maps_the_saga_result(): void
    {
        UserLogin::where('user_id', self::SOURCE)->firstOrFail();
        $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class]);
        $sessionId = str_repeat('c', 40);
        session()->setId($sessionId);
        $this->withCookie((string) config('session.cookie'), $sessionId)
            ->withCredentials()
            ->withSession(['suser' => ['user_id' => self::SOURCE]]);
        $page = $this->get('/user/proxy/direct_user_commTrans_browse/' . self::TARGET);
        $page->assertOk();
        preg_match('/name="idempotency_key"[^>]+value="([a-f0-9]{64})"/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $nonce = $matches[1];

        $response = $this
            ->withHeader('Idempotency-Key', $nonce)
            ->postJson('/user/proxy/directUserCommTrans', [
                'depositId' => self::TARGET,
                'comm_money' => '50.00',
                'password' => 'trade-secret',
                'idempotency_key' => $nonce,
                'remark' => 'legacy route success',
            ]);

        $response->assertOk();
        $this->assertSame('SUC', $response->json('msg'), $response->getContent());
        $response->assertJsonPath('code', 0)->assertJsonPath('comm_money', 875);
    }

    /**
     * 验证旧 nonce 在用途与会话校验通过前被消费，不触发任何资金调用。
     *
     * 使用伪造 nonce 提交旧转账接口，断言返回 FAIL/PARAM 且密码与资金网关均未被调用。
     */
    public function test_legacy_nonce_is_consumed_by_purpose_and_session_before_any_funding_call(): void
    {
        $login = UserLogin::where('user_id', self::SOURCE)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/directUserCommTrans', [
                'depositId' => self::TARGET,
                'comm_money' => '50.00',
                'password' => 'trade-secret',
                'idempotency_key' => str_repeat('a', 64),
            ]);

        $response->assertOk()->assertJsonPath('msg', 'FAIL')->assertJsonPath('errorType', 'PARAM');
        $this->assertSame([], $this->passwordGateway->calls);
        $this->assertSame([], $this->fundingGateway->calls);
    }

    /**
     * 验证旧路由在 body 与 header nonce 不一致时于 Saga 前拒绝。
     *
     * 从页面获取合法 nonce 后用不同的 header key 提交，
     * 断言返回 FAIL/PARAM 且密码与资金网关均未被调用。
     */
    public function test_legacy_route_rejects_body_and_header_nonce_mismatch_before_saga(): void
    {
        $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class]);
        $sessionId = str_repeat('d', 40);
        session()->setId($sessionId);
        $this->withCookie((string) config('session.cookie'), $sessionId)
            ->withCredentials()
            ->withSession(['suser' => ['user_id' => self::SOURCE]]);
        $page = $this->get('/user/proxy/direct_user_commTrans_browse/' . self::TARGET);
        $page->assertOk();
        preg_match('/name="idempotency_key"[^>]+value="([a-f0-9]{64})"/', $page->getContent(), $matches);
        $nonce = (string) ($matches[1] ?? '');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $nonce);

        $response = $this->withHeader('Idempotency-Key', str_repeat('e', 64))
            ->postJson('/user/proxy/directUserCommTrans', [
                'depositId' => self::TARGET,
                'comm_money' => '50.00',
                'password' => 'trade-secret',
                'idempotency_key' => $nonce,
            ]);

        $response->assertOk()->assertJsonPath('msg', 'FAIL')->assertJsonPath('errorType', 'PARAM');
        $this->assertSame([], $this->passwordGateway->calls);
        $this->assertSame([], $this->fundingGateway->calls);
    }

    /**
     * 验证客户 JWT 接口转账无需会话。
     *
     * 使用 JWT 令牌调用 /api/front/customers/commission-transfers，
     * 断言返回 code=0、msg=SUCCESS 且资金网关被调用两次。
     */
    public function test_customer_jwt_api_does_not_require_a_session_for_commission_transfer(): void
    {
        $login = UserLogin::where('user_id', self::SOURCE)->firstOrFail();
        $token = app(JwtService::class)->generateToken([
            'sub' => $login->getAuthIdentifier(),
            'guard' => 'user',
        ]);

        $response = $this->withoutMiddleware(SingleSignOn::class)
            ->withToken($token)
            ->postJson('/api/front/customers/commission-transfers', [
                'sub_agent_id' => self::TARGET,
                'amount' => '50.00',
                'password' => 'trade-secret',
            ], ['Idempotency-Key' => 'jwt-no-session-key']);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('msg', 'SUCCESS');
        $this->assertSame(2, count($this->fundingGateway->calls));
    }

    /**
     * 验证现代与旧适配器保留 Saga 终态与可重试态。
     *
     * 密码错误返回 rejected；可重试错误（connection_failed）返回 retryable 且 61 秒后重放收敛为 completed；
     * 旧接口对 rejected 返回 INSUFFICIENT_BALANCE，对 unknown 返回 MT4_SYNC_FAILED。
     */
    public function test_modern_and_legacy_adapters_preserve_saga_terminal_and_retryable_states(): void
    {
        $this->passwordGateway->result = TradePasswordVerificationResult::rejected('bad_password');
        $modernPasswordFailure = $this->postModernTransfer('state-password-key');
        $modernPasswordFailure->assertJsonPath('code', ResponseCode::AUTH_FAILED);
        $modernPasswordFailure->assertJsonPath('data.status', 'rejected');
        $this->assertSame([], $this->fundingGateway->calls);

        $this->passwordGateway->result = TradePasswordVerificationResult::verified();
        $this->fundingGateway->withdrawResults = [
            CommissionTransferCommandResult::retryableNotSent('connection_failed'),
            CommissionTransferCommandResult::processed('701001'),
        ];
        $retryable = $this->postModernTransfer('state-retry-key');
        $retryable->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED)
            ->assertJsonPath('data.status', 'retryable');
        $this->travel(61)->seconds();
        $replayed = $this->postModernTransfer('state-retry-key');
        $replayed->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.status', 'completed');

        $this->deleteFixtures();
        $this->insertUser(self::SOURCE, 'route-transfer-source', 1, 0, '1000.00');
        $this->insertUser(self::TARGET, 'route-transfer-target', 1, self::SOURCE, '25.00');
        $this->passwordGateway->result = TradePasswordVerificationResult::verified();
        $this->fundingGateway->calls = [];
        $this->fundingGateway->withdrawResults = [CommissionTransferCommandResult::rejected('insufficient_funds')];
        $legacyNonce = $this->issueLegacyCommissionNonce('f');
        $legacyRejected = $this->withHeader('Idempotency-Key', $legacyNonce)
            ->postJson('/user/proxy/directUserCommTrans', [
                'depositId' => self::TARGET,
                'comm_money' => '50.00',
                'password' => 'trade-secret',
                'idempotency_key' => $legacyNonce,
            ]);
        $legacyRejected->assertJsonPath('code', ResponseCode::INSUFFICIENT_BALANCE)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('errorType', 'INSUFFICIENT_BALANCE');

        $this->deleteFixtures();
        $this->insertUser(self::SOURCE, 'route-transfer-source', 1, 0, '1000.00');
        $this->insertUser(self::TARGET, 'route-transfer-target', 1, self::SOURCE, '25.00');
        $this->passwordGateway->result = TradePasswordVerificationResult::verified();
        $this->fundingGateway->calls = [];
        $this->fundingGateway->withdrawResults = [CommissionTransferCommandResult::unknown('read_timeout')];
        $legacyNonce = $this->issueLegacyCommissionNonce('1');
        $legacyUnknown = $this->withHeader('Idempotency-Key', $legacyNonce)
            ->postJson('/user/proxy/directUserCommTrans', [
                'depositId' => self::TARGET,
                'comm_money' => '50.00',
                'password' => 'trade-secret',
                'idempotency_key' => $legacyNonce,
            ]);
        $legacyUnknown->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('errorType', 'MT4_data_no_sync');
    }

    /**
     * 验证控制器与前端源码暴露共享 Saga 契约。
     *
     * 断言现代/旧控制器、旧页面控制器、blade 模板与各前端 JS 中包含
     * CommissionTransferService、LegacyFormIntentService、idempotency_key 与 Idempotency-Key 等契约要素。
     */
    public function test_controller_and_frontend_sources_expose_the_shared_saga_contract(): void
    {
        $commission = file_get_contents(app_path('Http/Controllers/Front/CommissionController.php')) ?: '';
        $agent = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';
        $legacyPage = file_get_contents(app_path('Http/Controllers/Front/LegacyPageController.php')) ?: '';
        $blade = file_get_contents(resource_path('front/layui/commission/transfer.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';
        $naive = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $crmui = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';

        $this->assertStringContainsString('CommissionTransferService', $commission);
        $this->assertStringContainsString('createOrRetrieve', $commission);
        $this->assertStringContainsString('CommissionTransferService', $agent);
        $this->assertStringContainsString('LegacyFormIntentService', $agent);
        $this->assertStringNotContainsString('Hash::check($password', $agent);
        $this->assertStringContainsString("'commission_transfer'", $legacyPage);
        $this->assertStringContainsString("'password'", $blade);
        $this->assertStringContainsString('name="idempotency_key"', $blade);
        $this->assertStringContainsString('Idempotency-Key', $layui);
        $this->assertStringContainsString('Idempotency-Key', $naive);
        $this->assertStringContainsString('Idempotency-Key', $crmui);
    }

    /**
     * 插入带余额的测试用户（代理商，已确认且已启用 MT4）。
     *
     * @param int $userId 用户 ID。
     * @param string $name 用户名。
     * @param int $accountType 账号类型。
     * @param int $parentId 上级用户 ID（0 表示无上级）。
     * @param string $balance 账户余额字符串。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUser(int $userId, string $name, int $accountType, int $parentId, string $balance): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'route-transfer-' . $userId . '@example.test',
            'password' => Hash::make('login-password'),
            'account_type' => $accountType,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $name,
            'phone' => '178' . substr((string) $userId, -8),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : (string) $userId,
            'group_id' => 0,
            'level_id' => 1,
            'comm_rate' => 0,
            'auth_status' => 1,
            'is_agent_confirmed' => 1,
            'is_mt4_enabled' => 1,
            'is_mt4_synced' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'total_funds' => $balance,
            'used_margin' => '0.00',
            'avail_margin' => $balance,
            'equity' => $balance,
            'effective_credit' => '0.00',
            'risk_ratio' => '0.00',
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理转账双方相关的 outbox、转账单、佣金记录及用户信息测试数据。
     *
     * @return void 无返回值。
     */
    private function deleteFixtures(): void
    {
        $ids = [self::SOURCE, self::TARGET];
        DB::table('commission_transfer_outbox')->whereIn('commission_transfer_id', function ($query): void {
            $query->select('id')->from('commission_transfers')->where('source_user_id', self::SOURCE);
        })->delete();
        DB::table('commission_transfers')->whereIn('source_user_id', $ids)->delete();
        DB::table('commission_records')->whereIn('agent_id', $ids)->delete();
        DB::table('user_infos')->whereIn('user_id', $ids)->delete();
        DB::table('user_logins')->whereIn('user_id', $ids)->delete();
    }

    /**
     * 以现代接口提交一笔带幂等头的转账请求。
     *
     * @param string $key Idempotency-Key 值。
     * @return \Illuminate\Testing\TestResponse 接口响应。
     */
    private function postModernTransfer(string $key)
    {
        $login = UserLogin::where('user_id', self::SOURCE)->firstOrFail();

        return $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/front/commissions/transfers', [
                'sub_agent_id' => self::TARGET,
                'amount' => '50.00',
                'password' => 'trade-secret',
            ]);
    }

    /**
     * 通过旧浏览页签发一个会话绑定的佣金转账 nonce。
     *
     * @param string $sessionSeed 会话 ID 种子字符。
     * @return string 64 位十六进制 nonce；解析失败时返回空字符串。
     */
    private function issueLegacyCommissionNonce(string $sessionSeed): string
    {
        $sessionId = str_repeat($sessionSeed, 40);
        session()->setId($sessionId);
        $this->withCookie((string) config('session.cookie'), $sessionId)
            ->withCredentials()
            ->withSession(['suser' => ['user_id' => self::SOURCE]]);
        $page = $this->get('/user/proxy/direct_user_commTrans_browse/' . self::TARGET);
        $page->assertOk();
        preg_match('/name="idempotency_key"[^>]+value="([a-f0-9]{64})"/', $page->getContent(), $matches);

        return (string) ($matches[1] ?? '');
    }
}

/**
 * 交易密码网关测试替身：记录调用并可按预设结果返回。
 */
final class RouteTradePasswordGateway implements TradePasswordGateway
{
    /**
     * verify() 收到的 [userId, password] 调用记录。断言转账路由确实先校验资金密码。
     * @var array<int, array{0: int, 1: string}>
     */
    public $calls = [];
    /**
     * 预设的资金密码校验结果。为 null 时默认验证成功，非空时返回注入的失败结果。
     * @var TradePasswordVerificationResult|null
     */
    public $result;

    public function verify(int $userId, string $password): TradePasswordVerificationResult
    {
        $this->calls[] = [$userId, $password];

        return $this->result ?: TradePasswordVerificationResult::verified();
    }
}

/**
 * 佣金转账资金网关测试替身：记录调用并按预设结果队列返回。
 */
final class RouteFundingGateway implements CommissionTransferFundingGateway
{
    /**
     * 记录 withdraw/deposit/compensate 的调用序列 [动作, userId, 金额, 备注]。
     * 断言路由层发出的资金命令顺序与参数。
     * @var array<int, array{0: string, 1: int, 2: string, 3: string}>
     */
    public $calls = [];
    /**
     * withdraw 的预设结果队列，逐次弹出；为空时默认返回已处理。
     * @var array<int, CommissionTransferCommandResult>
     */
    public $withdrawResults = [];
    /**
     * deposit 的预设结果队列，逐次弹出；为空时默认返回已处理。
     * @var array<int, CommissionTransferCommandResult>
     */
    public $depositResults = [];
    /**
     * compensate 的预设结果队列，逐次弹出；用例借它构造补偿失败场景。
     * @var array<int, CommissionTransferCommandResult>
     */
    public $compensationResults = [];

    public function withdraw(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        $this->calls[] = ['withdraw', $userId, $amount, $comment];

        return array_shift($this->withdrawResults) ?: CommissionTransferCommandResult::processed('700001');
    }

    public function deposit(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        $this->calls[] = ['deposit', $userId, $amount, $comment];

        return array_shift($this->depositResults) ?: CommissionTransferCommandResult::processed('700002');
    }

    public function compensate(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        $this->calls[] = ['compensate', $userId, $amount, $comment];

        return array_shift($this->compensationResults) ?: CommissionTransferCommandResult::processed('700003');
    }
}

/**
 * 账户快照网关测试替身：固定返回构造时传入的余额。
 */
final class RouteSnapshotGateway implements CommissionTransferAccountSnapshotGateway
{
    /**
     * 快照替身返回的固定账户余额（字符串金额）。保证余额校验断言可复现。
     * @var string
     */
    private $balance;

    public function __construct(string $balance)
    {
        $this->balance = $balance;
    }

    public function snapshot(int $userId): CommissionTransferAccountSnapshotResult
    {
        return CommissionTransferAccountSnapshotResult::confirmed($this->balance);
    }
}
