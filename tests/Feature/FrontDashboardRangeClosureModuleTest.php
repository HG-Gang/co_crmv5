<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 22:16
 */

/**
 * FrontDashboardRangeClosureModuleTest
 *
 * 文件功能：
 * - 验证前台 Dashboard 7/15/30 天统计窗口：真实资金行过滤、非法 days 参数拒绝、默认 30 天。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesLegacyFrontUserFixture;
use Tests\TestCase;

/**
 * 锁定 Dashboard 7/15/30 天统计窗口的真实数据库聚合与输入边界。
 */
class FrontDashboardRangeClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacyFrontUserFixture;

    /**
     * 夹具登录用户 ID。区间统计用例以它构造流水样本。
     * @var int
     */
    private $userId;

    /**
     * 登录成功后缓存的 JWT。后续带鉴权的仪表盘请求都携带它。
     * @var string
     */
    private $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = random_int(380000000, 389999999);
        $login = $this->createLegacyFrontUserFixture($this->userId, 2, 'Dashboard Range Fixture');
        $this->token = app(JwtService::class)->generateToken([
            'sub' => $login->getAuthIdentifier(),
            'guard' => 'user',
        ]);
    }

    public function test_dashboard_days_filters_real_funding_rows(): void
    {
        $this->insertDeposit('DASH-RANGE-IN', '25.50', time() - 6 * 86400);
        $this->insertDeposit('DASH-RANGE-OUT', '90.00', time() - 8 * 86400);

        $this->withToken($this->token)
            ->getJson('/api/front/dashboard?days=7')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.period.days', 7)
            ->assertJsonPath('data.stats.monthly_deposit', 25.5);

        $this->withToken($this->token)
            ->getJson('/api/front/dashboard?days=15')
            ->assertOk()
            ->assertJsonPath('data.period.days', 15)
            ->assertJsonPath('data.stats.monthly_deposit', 115.5);
    }

    /** @dataProvider invalidDaysProvider */
    public function test_dashboard_rejects_invalid_days_parameter(string $query): void
    {
        $this->withToken($this->token)
            ->getJson('/api/front/dashboard?' . $query)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function invalidDaysProvider(): array
    {
        return [
            'unsupported integer' => ['days=8'],
            'decimal' => ['days=7.5'],
            'array' => ['days[]=7'],
        ];
    }

    public function test_dashboard_defaults_to_thirty_days(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/front/dashboard')
            ->assertOk()
            ->assertJsonPath('data.period.days', 30);
    }

    private function insertDeposit(string $orderNo, string $amount, int $createdAt): void
    {
        DB::table('deposit_records')->insert([
            'user_id' => $this->userId,
            'user_name' => 'dashboard-range-' . $this->userId,
            'mt4_ticket' => $this->userId,
            'amount' => $amount,
            'actual_amount' => $amount,
            'exchange_rate' => 1,
            'channel_name' => 'phpunit',
            'channel_order_no' => 'CH-' . $orderNo,
            'local_order_no' => $orderNo . '-' . $this->userId,
            'status' => '02',
            'payment_time' => date('Y-m-d H:i:s', $createdAt),
            'remarks' => 'dashboard range closure test',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ]);
    }
}
