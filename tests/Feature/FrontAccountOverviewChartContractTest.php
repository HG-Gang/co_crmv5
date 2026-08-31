<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:40
 */

/**
 * 前台账户综合图表契约测试。
 *
 * 文件功能（需求 6）：
 * - 验证账户综合页提供入金、返佣、出金、订单、代理、客户与间接客户画像及其相关金额图表。
 * - 验证每组图表都提供柱状图/折线图/面积图/饼图四种查看方式，且切换按钮满足多语言与 44px 触控目标。
 * - 验证图表数据来自 /api/front/account/profile 的真实数据库聚合，不使用任何前端假数据。
 *
 * 入参例子：
 * - 以真实用户身份请求 GET /api/front/account/profile。
 *
 * 返回值：
 * - 测试无返回值；断言通过表示图表口径与数据来源闭环。
 *
 * 异常或失败场景：
 * - 断言失败表示图表缺失某个业务维度，或金额不是来自数据库聚合。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontAccountOverviewChartContractTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证账户综合页声明了需求要求的全部图表维度。
     *
     * @return void 图表齐备时无返回值。
     */
    public function test_account_overview_declares_every_required_chart_dimension(): void
    {
        $blade = file_get_contents(resource_path('front/layui/account/info.blade.php')) ?: '';

        // 入金 / 返佣 / 出金 / 订单 / 代理 / 客户 / 间接客户 画像与相关金额。
        foreach ([
            "'front.funds_profile'",
            "'front.order_profile'",
            "'front.client_profile'",
            "'front.relation_deposit_profile'",
            "'front.relation_withdraw_profile'",
            "'front.relation_rebate_profile'",
            "'front.client_gender_profile'",
        ] as $title) {
            $this->assertStringContainsString($title, $blade, 'Account overview is missing chart group: ' . $title);
        }

        foreach ([
            'total_deposit',
            'total_rebate',
            'total_withdraw',
            'open_order_count',
            'closed_order_count',
            'direct_agents',
            'indirect_agents',
            'direct_customers',
            'indirect_customers',
            'direct_agents_deposit',
            'indirect_agents_deposit',
            'direct_customers_deposit',
            'indirect_customers_deposit',
            'direct_agents_withdraw',
            'indirect_customers_withdraw',
            'direct_agents_rebate',
            'indirect_agents_rebate',
            'relation_amount',
        ] as $field) {
            $this->assertStringContainsString(
                "'key' => '" . $field . "'",
                $blade,
                'Account overview chart is missing metric: ' . $field
            );
        }

        // 四种默认查看方式都被实际使用，说明多种视图确实生效。
        foreach (['bar', 'line', 'area', 'pie'] as $type) {
            $this->assertStringContainsString("'defaultType' => '" . $type . "'", $blade, 'No chart group defaults to ' . $type);
        }

        // 图表由已内置的 ECharts 渲染。
        $this->assertStringContainsString('/js/vendor/echarts/echarts.common.min.js', $blade);
    }

    /**
     * 验证图表查看方式为按钮组，并满足多语言与无障碍约定。
     *
     * @return void 契约完整时无返回值。
     */
    public function test_chart_view_mode_controls_follow_the_dashboard_conventions(): void
    {
        $partial = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';

        // 与控制台一致：按钮组 + role=group + aria-pressed + crm-sr-only + 44px 触控目标。
        $this->assertStringContainsString('class="module-chart-controls" role="group"', $partial);
        $this->assertStringContainsString('data-chart-type="{{ $chartViewMode[\'type\'] }}"', $partial);
        $this->assertStringContainsString('aria-pressed="{{ $chartViewMode[\'type\'] === $chartDefaultType ? \'true\' : \'false\' }}"', $partial);
        $this->assertStringContainsString('<span class="crm-sr-only">{{ $chartViewMode[\'label\'] }}</span>', $partial);
        $this->assertStringContainsString('width: 44px; height: 44px;', $partial);
        // 旧的下拉选择必须被按钮组替换。
        $this->assertStringNotContainsString('<select class="module-chart-type J_moduleChartType"', $partial);

        // 四种查看方式的文案全部来自语言包字面量，便于首屏翻译审计静态扫描。
        foreach (['front.chart_bar', 'front.chart_line', 'front.chart_area', 'front.chart_pie'] as $key) {
            $this->assertStringContainsString("__('" . $key . "')", $partial, 'Chart label must use language key: ' . $key);
        }

        // 切换只重绘当前快照，不重新请求接口。
        $this->assertStringContainsString("$('.J_moduleChartType').on('click', function () {", $script);
        $this->assertStringContainsString("['bar', 'line', 'area', 'pie'].indexOf(type) === -1", $script);
        $this->assertStringContainsString('moduleChartTypes[target] = type;', $script);
        $this->assertStringContainsString('renderChartSelectors();', $script);
        $this->assertStringContainsString('function defaultChartType(target)', $script);
        $this->assertStringNotContainsString("$('.J_moduleChartType').on('change', function () {", $script);
    }

    /**
     * 验证账户综合接口返回的关系网金额来自真实数据库聚合。
     *
     * @return void 金额与写入记录一致时无返回值。
     */
    public function test_account_profile_api_returns_database_backed_relation_amounts(): void
    {
        $agentId = 415900100;
        $directAgentId = $agentId + 1;
        $directCustomerId = $agentId + 2;
        $indirectCustomerId = $agentId + 3;

        $this->insertUserInfo($agentId, 1, 0);
        $this->insertUserInfo($directAgentId, 1, $agentId);
        $this->insertUserInfo($directCustomerId, 2, $agentId);
        $this->insertUserInfo($indirectCustomerId, 2, $directAgentId);

        $this->insertDeposit($directAgentId, 1000.00);
        $this->insertDeposit($directCustomerId, 250.50);
        $this->insertDeposit($indirectCustomerId, 130.25);
        $this->insertWithdraw($directAgentId, 400.00);
        $this->insertWithdraw($directCustomerId, 90.00);
        $this->insertWithdraw($indirectCustomerId, 45.75);
        $this->insertCommission($directAgentId, $agentId, 77.25);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/profile');

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $data = $response->json('data');

        $this->assertSame(1, (int) $data['direct_agents']);
        $this->assertSame(1, (int) $data['direct_customers']);
        $this->assertSame(1, (int) $data['indirect_customers']);
        $this->assertSame(1000.00, round((float) $data['direct_agents_deposit'], 2));
        $this->assertSame(250.50, round((float) $data['direct_customers_deposit'], 2));
        $this->assertSame(130.25, round((float) $data['indirect_customers_deposit'], 2));
        $this->assertSame(400.00, round((float) $data['direct_agents_withdraw'], 2));
        $this->assertSame(90.00, round((float) $data['direct_customers_withdraw'], 2));
        $this->assertSame(45.75, round((float) $data['indirect_customers_withdraw'], 2));
        $this->assertSame(77.25, round((float) $data['direct_agents_rebate'], 2));
    }

    /**
     * 验证关系网金额由 SQL 聚合得出，且空集合不会退化成全表统计。
     *
     * @return void 实现符合口径时无返回值。
     */
    public function test_relation_amount_helpers_aggregate_from_the_database(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Front/AccountController.php')) ?: '';

        $this->assertStringContainsString('private function scopeDepositTotal(array $userIds): float', $controller);
        $this->assertStringContainsString('private function scopeWithdrawTotal(array $userIds): float', $controller);
        $this->assertStringContainsString('private function scopeRebateTotal(array $agentIds): float', $controller);
        $this->assertStringContainsString("DepositRecord::whereIn('user_id', \$userIds)->sum('amount')", $controller);
        $this->assertStringContainsString("WithdrawRecord::whereIn('user_id', \$userIds)->sum('apply_amount')", $controller);
        // commission_records 只有 agent_id / parent_id，返佣必须按 agent_id 聚合。
        $this->assertStringContainsString("CommissionRecord::whereIn('agent_id', \$agentIds)->sum('commission_amount')", $controller);
        // 空集合直接返回 0，避免无条件全表聚合。
        $this->assertStringContainsString('return $userIds ? (float) DepositRecord', $controller);
        $this->assertStringContainsString('return $agentIds ? (float) CommissionRecord', $controller);
    }

    /**
     * 写入测试用户资料与登录记录。
     *
     * @param int $userId 业务用户编号。
     * @param int $accountType 账户类型，1=代理，2=客户。
     * @param int $parentId 上级业务用户编号。
     * @return void 写入完成后无返回值。
     */
    private function insertUserInfo(int $userId, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'account-overview-chart-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
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
            'user_name' => 'chart-user-' . $userId,
            'phone' => '1787000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入一条入金记录。
     *
     * @param int $userId 业务用户编号。
     * @param float $amount 入金金额。
     * @return void 写入完成后无返回值。
     */
    private function insertDeposit(int $userId, float $amount): void
    {
        $now = time();

        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => 'chart-user-' . $userId,
            'mt4_ticket' => $userId,
            'amount' => $amount,
            'actual_amount' => $amount,
            'exchange_rate' => 1,
            'channel_name' => 'manual-bank',
            'channel_order_no' => 'CH-CHART-' . $userId,
            'local_order_no' => 'CHART-DEP-' . $userId,
            'status' => '02',
            'payment_time' => date('Y-m-d H:i:s', $now),
            'remarks' => 'account overview chart contract test',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入一条出金记录。
     *
     * @param int $userId 业务用户编号。
     * @param float $amount 出金申请金额。
     * @return void 写入完成后无返回值。
     */
    private function insertWithdraw(int $userId, float $amount): void
    {
        $now = time();

        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => 'chart-user-' . $userId,
            'mt4_ticket' => (string) $userId,
            'apply_amount' => $amount,
            'actual_amount' => $amount,
            'fee' => 0,
            'exchange_rate' => 1,
            'rmb_fee' => 0,
            'bank_no' => '6222000000000000',
            'bank_name' => 'Chart Bank',
            'bank_addr' => 'Chart Branch',
            'status' => 2,
            'local_order_no' => 'CHART-WDR-' . $userId,
            'third_order_no' => 'CHART-OTC-' . $userId,
            'reject_reason' => '',
            'mt4_return_status' => '',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入一条返佣记录。
     *
     * @param int $agentId 获得返佣的代理业务用户编号。
     * @param int $parentId 上级代理业务用户编号。
     * @param float $amount 返佣金额。
     * @return void 写入完成后无返回值。
     */
    private function insertCommission(int $agentId, int $parentId, float $amount): void
    {
        $now = time();

        DB::table('commission_records')->insert([
            'unique_id' => md5('chart-commission-' . $agentId . '-' . $now),
            'agent_id' => $agentId,
            'parent_id' => $parentId,
            'agent_profit' => $amount * 2,
            'agent_volume' => 1.5,
            'equity_value' => 0,
            'equity_diff' => 0,
            'settle_cycle' => 1,
            'mt4_order_id' => $agentId,
            'date_range' => date('Y-m-d', $now),
            'settle_status' => 2,
            'fee' => 0,
            'swap' => 0,
            'commission_amount' => $amount,
            'returned_amount' => $amount,
            'deposit' => 0,
            'real_amount' => $amount,
            'data_type' => 'mainData',
            'manual_reason' => '',
            'remarks' => 'account overview chart contract test',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
