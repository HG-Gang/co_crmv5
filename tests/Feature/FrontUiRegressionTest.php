<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:59
 */

namespace Tests\Feature;

use Tests\TestCase;
use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\ManagesSharedSystemConfigFixtures;

/**
 * Blade 单体前端与业务接口综合回归测试。
 *
 * 文件功能：
 * - 验证普通用户、代理商和后台管理员页面由 Blade 输出，并使用 public/css、public/js 下的项目自定义资源。
 * - 验证页面字段、筛选器、写入操作和真实 Laravel 路由保持闭环，不依赖 Node、Vite、Mix 或 SPA 构建产物。
 * - 对涉及数据库状态的资金、交易、新闻、礼品和代理关系接口执行真实查询与权限边界断言。
 *
 * 执行结果：
 * - 通过表示 Blade 页面契约、自定义脚本契约和后端数据链路一致。
 * - 失败表示页面字段、接口路由、权限边界或真实数据结果至少有一项发生回归。
 */
class FrontUiRegressionTest extends TestCase
{
    use ManagesSharedSystemConfigFixtures;

    /**
     * setUp 捕获的 payment_channels 全表快照。tearDown 在事务内先删后按快照回插，
     * 保证 UI 回归用例对渠道表的改写零残留。
     * @var array<int, array<string, mixed>>|null
     */
    private $paymentChannelSnapshot;

    /**
     * user_id=1001 的 deposit_records 行快照。该用户是 UI 用例的固定演示账户，
     * tearDown 按快照恢复其订单数据。
     * @var array<int, array<string, mixed>>|null
     */
    private $paymentDepositSnapshot;

    /**
     * 改写前的 front demo 相关 system_configs 行快照。tearDown 恢复并断言恢复后与快照一致。
     * @var array<int, array<string, mixed>>|null
     */
    private $frontDemoConfigSnapshot;

    /**
     * 进入探测阶段前再次捕获的配置快照。用例借它区分"夹具自己写入的行"与"探测并发写入的行"，
     * 恢复时只回滚前者。
     * @var array<int, array<string, mixed>>|null
     */
    private $frontDemoConfigProbeOriginalSnapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentChannelSnapshot = null;
        $this->paymentDepositSnapshot = null;
        $this->frontDemoConfigSnapshot = null;
        $this->frontDemoConfigProbeOriginalSnapshot = null;

        $testName = $this->getName();
        $usesFrontDemoSeeder = in_array($testName, $this->frontDemoSeederTests(), true);
        if ($usesFrontDemoSeeder) {
            $this->acquireSharedSystemConfigFixtureLock();
        }

        try {
            if ($usesFrontDemoSeeder) {
                $initialRows = $this->frontDemoSystemConfigRows();
                $this->captureSharedSystemConfigFixtureOwnedState(
                    $this->frontDemoSystemConfigKeys(),
                    $initialRows
                );
                if ($testName === $this->withdrawalConfigLeakProbeTest()) {
                    $this->frontDemoConfigProbeOriginalSnapshot = $initialRows;
                    $initialRows = $this->deleteFrontDemoInsertedWithdrawalConfigRows(
                        $initialRows
                    );
                }
                $this->frontDemoConfigSnapshot = $initialRows;
            }

            if (in_array($testName, $this->isolatedPaymentTests(), true)) {
                $this->paymentChannelSnapshot = DB::table('payment_channels')
                    ->get()
                    ->map(static function ($row): array {
                        return (array) $row;
                    })
                    ->all();
                $this->paymentDepositSnapshot = DB::table('deposit_records')
                    ->where('user_id', 1001)
                    ->get()
                    ->map(static function ($row): array {
                        return (array) $row;
                    })
                    ->all();
            }
        } catch (\Throwable $exception) {
            $this->runSharedSystemConfigFixtureLifecycleCleanup($exception, [
                'restore front demo system config snapshot' => function (): void {
                    $this->restoreFrontDemoSystemConfigRows($this->frontDemoConfigSnapshot);
                },
                'restore front demo probe original snapshot' => function (): void {
                    if ($this->frontDemoConfigProbeOriginalSnapshot !== null
                        && $this->frontDemoConfigSnapshot !== null) {
                        $this->captureSharedSystemConfigFixtureOwnedState(
                            $this->frontDemoSystemConfigKeys(),
                            $this->frontDemoConfigSnapshot
                        );
                    }
                    $this->restoreFrontDemoSystemConfigRows($this->frontDemoConfigProbeOriginalSnapshot);
                },
                'release shared system config fixture lock' => function (): void {
                    $this->releaseSharedSystemConfigFixtureLock();
                },
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
            'restore isolated payment fixtures' => function (): void {
                if ($this->paymentChannelSnapshot !== null) {
                    DB::transaction(function (): void {
                        DB::table('payment_channels')->delete();
                        if ($this->paymentChannelSnapshot !== []) {
                            DB::table('payment_channels')->insert($this->paymentChannelSnapshot);
                        }

                        DB::table('deposit_records')->where('user_id', 1001)->delete();
                        if ($this->paymentDepositSnapshot !== []) {
                            DB::table('deposit_records')->insert($this->paymentDepositSnapshot);
                        }
                    });
                }
            },
            'restore front demo system config snapshot' => function (): void {
                if ($this->frontDemoConfigSnapshot !== null) {
                    $this->restoreFrontDemoSystemConfigRows($this->frontDemoConfigSnapshot);
                    $this->assertSame(
                        $this->frontDemoConfigSnapshot,
                        $this->frontDemoSystemConfigRows(),
                        'FrontDemoDataSeeder system configs were not restored after an isolated UI test.'
                    );
                }
            },
            'restore front demo probe original snapshot' => function (): void {
                if ($this->frontDemoConfigProbeOriginalSnapshot !== null
                    && $this->frontDemoConfigSnapshot !== null) {
                    $this->captureSharedSystemConfigFixtureOwnedState(
                        $this->frontDemoSystemConfigKeys(),
                        $this->frontDemoConfigSnapshot
                    );
                }
                $this->restoreFrontDemoSystemConfigRows($this->frontDemoConfigProbeOriginalSnapshot);
            },
            'parent teardown' => function (): void {
                    parent::tearDown();
            },
            'release shared system config fixture lock' => function (): void {
                    $this->releaseSharedSystemConfigFixtureLock();
            },
        ]);
    }

    public function test_front_demo_seeder_fixture_lifecycle_restores_required_withdrawal_configs(): void
    {
        $this->assertSame(
            0,
            DB::table('system_configs')
                ->whereIn('key', $this->frontDemoInsertedWithdrawalConfigKeys())
                ->count()
        );

        $this->seedFrontDemoDataAndCaptureOwnedConfig();

        $this->assertSame(
            $this->frontDemoInsertedWithdrawalConfigKeys(),
            DB::table('system_configs')
                ->whereIn('key', $this->frontDemoInsertedWithdrawalConfigKeys())
                ->whereNull('deleted_at')
                ->orderBy('key')
                ->pluck('key')
                ->all()
        );
    }

    private function withdrawalConfigLeakProbeTest(): string
    {
        return 'test_front_demo_seeder_fixture_lifecycle_restores_required_withdrawal_configs';
    }

    /** @return array<int, string> */
    private function frontDemoSystemConfigKeys(): array
    {
        return [
            'deposit_enabled',
            'deposit_exchange_rate_cny',
            'deposit_max_amount',
            'deposit_min_amount',
            'download_mobile_url',
            'download_pc_url',
            'withdraw_check_open',
            'withdraw_exchange_rate_cny',
            'withdraw_max_amount',
            'withdraw_min_amount',
            'withdraw_risk_rate_limit',
            'withdrawal_enabled',
            'withdrawal_end_time',
            'withdrawal_fee_rate',
            'withdrawal_fixed_fee_usd',
            'withdrawal_start_time',
            'withdrawal_weekend_enabled',
        ];
    }

    /** @return array<int, string> */
    private function frontDemoInsertedWithdrawalConfigKeys(): array
    {
        return [
            'withdraw_check_open',
            'withdrawal_end_time',
            'withdrawal_fee_rate',
            'withdrawal_fixed_fee_usd',
            'withdrawal_start_time',
            'withdrawal_weekend_enabled',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function frontDemoSystemConfigRows(): array
    {
        return DB::table('system_configs')
            ->useWritePdo()
            ->whereIn('key', $this->frontDemoSystemConfigKeys())
            ->orderBy('key')
            ->get()
            ->map(static function ($row): array {
                $normalized = (array) $row;
                foreach (['id', 'created_at', 'updated_at', 'deleted_at'] as $column) {
                    if ($normalized[$column] !== null) {
                        $normalized[$column] = (string) $normalized[$column];
                    }
                }
                ksort($normalized);

                return $normalized;
            })
            ->all();
    }

    private function seedFrontDemoDataAndCaptureOwnedConfig(): void
    {
        if ($this->frontDemoConfigSnapshot === null) {
            throw new \LogicException('The front demo config snapshot is unavailable.');
        }

        $startedAt = time();
        $this->seed(\Database\Seeders\FrontDemoDataSeeder::class);
        $this->captureSharedSystemConfigFixtureOwnedStateAfterFrontDemoSeeder(
            $this->frontDemoSystemConfigKeys(),
            $this->frontDemoConfigSnapshot,
            $this->frontDemoSystemConfigDefinitions(),
            $startedAt,
            time()
        );
    }

    public function test_crmui_and_naive_trade_symbol_filters_use_dynamic_database_options(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Front/PageController.php')) ?: '';
        $partial = file_get_contents(resource_path('front/crmui/partials/module-page.blade.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';

        foreach (['position/summary', 'order/open', 'order/closed'] as $path) {
            $definition = $this->sourceBetween(
                $controller,
                "'" . $path . "' => [",
                $path === 'position/summary'
                    ? "'order/open' => ["
                    : ($path === 'order/open' ? "'order/closed' => [" : "'agent/sub' => [")
            );

            $this->assertStringContainsString("'name' => 'symbol'", $definition, 'CrmUI page definition must declare a symbol filter: ' . $path);
            $this->assertStringContainsString("'type' => 'select'", $definition, 'CrmUI symbol filter must render as a select: ' . $path);
            $this->assertStringContainsString("'dynamicOptions' => 'symbols'", $definition, 'CrmUI symbol filter must use the shared database-backed symbol option source: ' . $path);

            foreach (['/front-crmui/' . $path, '/front-naive/' . $path] as $url) {
                $html = $this->get($url)->assertOk()->getContent();

                $this->assertStringContainsString('name="symbol"', $html, $url . ' must render the symbol filter.');
                $this->assertStringContainsString('data-dynamic-options="symbols"', $html, $url . ' must mark the symbol filter as dynamic.');
            }
        }

        $this->assertStringContainsString('@if(!empty($field[\'dynamicOptions\'])) data-dynamic-options="{{ $field[\'dynamicOptions\'] }}" @endif', $partial);
        $this->assertStringContainsString("symbols: '/api/front/trade-symbols'", $script);
        $this->assertStringContainsString("symbols: 'GET'", $script);
        $this->assertStringContainsString('loadDynamicFilterOptions($page)', $script);
        $this->assertStringContainsString('[data-dynamic-options]', $script);
    }

    public function test_crmui_and_naive_news_use_timeline_detail_contract(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Front/PageController.php')) ?: '';
        $partial = file_get_contents(resource_path('front/crmui/partials/module-page.blade.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';

        $this->assertStringContainsString("'timeline' => 'news'", $controller);
        $this->assertStringContainsString('data-timeline="{{ $page[\'timeline\'] ?? \'\' }}"', $partial);
        $this->assertStringContainsString('data-crmui-news-timeline', $partial);
        $this->assertStringContainsString('id="crmuiNewsTimeline"', $partial);
        $this->assertStringContainsString('function renderNewsTimeline', $script);
        $this->assertStringContainsString('<div class="crmui-news-card"', $script);
        $this->assertStringNotContainsString('data-crmui-news-row', $script);
        $this->assertStringNotContainsString('function openNewsDetailModal', $script);
        $this->assertStringNotContainsString("$(document).on('click', '[data-crmui-news-row]'", $script);

        foreach (['/front-crmui/news', '/front-naive/news'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-crmui-page="front.news"', $html);
            $this->assertStringContainsString('data-timeline="news"', $html);
            $this->assertStringContainsString('data-crmui-news-timeline', $html);
            $this->assertStringContainsString('id="crmuiNewsTimeline"', $html);
            $this->assertStringNotContainsString('data-crmui-row-action="detail"', $html, $url . ' must not use the generic table detail row action for news.');
            $this->assertStringNotContainsString('<table class="crmui-table">', $html, $url . ' must render the news timeline instead of a generic table.');
        }
    }

    public function test_crmui_and_naive_commission_pages_render_chart_and_dynamic_agent_contract(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Front/PageController.php')) ?: '';
        $partial = file_get_contents(resource_path('front/crmui/partials/module-page.blade.php')) ?: '';
        $layout = file_get_contents(resource_path('front/crmui/layouts/app.blade.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';

        foreach ([
            'commissionTrendChart',
            'commissionGenderChart',
            'commissionGenderAmountChart',
            'commissionTransferTrendChart',
            'commissionTransferGenderChart',
            'commissionTransferGenderAmountChart',
        ] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }

        $this->assertStringContainsString("'name' => 'sub_agent_id', 'label' => 'sub_agent_id', 'type' => 'select', 'dynamicOptions' => 'direct_agents'", $controller);
        $this->assertStringContainsString("direct_agents: '/api/front/commissions/transfer-agent-options'", $script);
        $this->assertStringContainsString("direct_agents: 'GET'", $script);
        $this->assertStringContainsString("data-chart-groups='@json(\$page['chartGroups'] ?? [])'", $partial);
        $this->assertStringContainsString('data-crmui-chart-grid', $partial);
        $this->assertStringContainsString('data-crmui-chart-target', $partial);
        $this->assertStringContainsString('function renderCharts', $script);
        $this->assertStringContainsString('chartOption(title, values, type)', $script);
        $this->assertStringContainsString('/js/vendor/echarts/echarts.common.min.js', $layout);

        foreach ([
            '/front-crmui/commission/history' => ['commissionTrendChart', 'commissionGenderChart', 'commissionGenderAmountChart'],
            '/front-naive/commission/history' => ['commissionTrendChart', 'commissionGenderChart', 'commissionGenderAmountChart'],
            '/front-crmui/commission/transfer' => ['commissionTransferTrendChart', 'commissionTransferGenderChart', 'commissionTransferGenderAmountChart', 'data-dynamic-options="direct_agents"'],
            '/front-naive/commission/transfer' => ['commissionTransferTrendChart', 'commissionTransferGenderChart', 'commissionTransferGenderAmountChart', 'data-dynamic-options="direct_agents"'],
        ] as $url => $needles) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-chart-groups=', $html, $url . ' must expose chart groups to the CrmUI renderer.');
            $this->assertStringContainsString('/js/vendor/echarts/echarts.common.min.js', $html, $url . ' must load ECharts before chart rendering.');
            $this->assertLessThan(
                strpos($html, '/js/apps/crmui/front.js'),
                strpos($html, '/js/vendor/echarts/echarts.common.min.js'),
                $url . ' must load ECharts before the CrmUI page script.'
            );

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $html, $url . ' missing chart or dynamic option marker: ' . $needle);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function deleteFrontDemoInsertedWithdrawalConfigRows(array $rows): array
    {
        return $this->deleteSharedSystemConfigFixtureOwnedRows(
            $this->frontDemoSystemConfigKeys(),
            $rows,
            $this->frontDemoInsertedWithdrawalConfigKeys()
        );
    }

    /** @return array<string, array{value: string, group: string, description: string, required: bool}> */
    private function frontDemoSystemConfigDefinitions(): array
    {
        return [
            'deposit_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo deposit switch', 'required' => false],
            'deposit_exchange_rate_cny' => ['value' => '7.12', 'group' => 'finance', 'description' => 'Demo CNY deposit rate', 'required' => false],
            'deposit_min_amount' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo min deposit amount', 'required' => false],
            'deposit_max_amount' => ['value' => '500000', 'group' => 'finance', 'description' => 'Demo max deposit amount', 'required' => false],
            'withdrawal_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo withdrawal switch', 'required' => true],
            'withdrawal_weekend_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo weekend withdrawal switch', 'required' => true],
            'withdrawal_start_time' => ['value' => '', 'group' => 'finance', 'description' => 'Demo withdrawal start time', 'required' => true],
            'withdrawal_end_time' => ['value' => '', 'group' => 'finance', 'description' => 'Demo withdrawal end time', 'required' => true],
            'withdraw_exchange_rate_cny' => ['value' => '7.05', 'group' => 'finance', 'description' => 'Demo CNY withdrawal rate', 'required' => true],
            'withdraw_min_amount' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo min withdrawal amount', 'required' => true],
            'withdraw_max_amount' => ['value' => '50000', 'group' => 'finance', 'description' => 'Demo max withdrawal amount', 'required' => true],
            'withdraw_risk_rate_limit' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo withdrawal risk limit', 'required' => true],
            'withdraw_check_open' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo open-position withdrawal check', 'required' => true],
            'withdrawal_fee_rate' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo withdrawal fee rate', 'required' => true],
            'withdrawal_fixed_fee_usd' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo fixed withdrawal fee', 'required' => true],
            'download_pc_url' => ['value' => '#', 'group' => 'front', 'description' => 'Demo PC download URL', 'required' => false],
            'download_mobile_url' => ['value' => '#', 'group' => 'front', 'description' => 'Demo mobile download URL', 'required' => false],
        ];
    }

    /** @param array<int, array<string, mixed>>|null $rows */
    private function restoreFrontDemoSystemConfigRows($rows): void
    {
        if ($rows === null) {
            return;
        }

        $this->restoreSharedSystemConfigSnapshot($this->frontDemoSystemConfigKeys(), $rows);
    }

    /** @return array<int, string> */
    private function frontDemoSeederTests(): array
    {
        return [
            $this->withdrawalConfigLeakProbeTest(),
            'test_front_account_profile_api_returns_runtime_overview_metrics',
            'test_front_deposit_without_configured_channels_fails_closed_in_blade',
            'test_front_demo_payment_channel_remarks_are_seeded_in_database_config',
            'test_front_deposit_database_channels_normalize_legacy_remark_fields',
            'test_front_deposit_channel_remarks_do_not_fall_back_to_controller_literals',
            'test_crmui_deposit_keeps_submission_aliases_but_rejects_unconfigured_channels',
            'test_legacy_deposit_submission_rejects_incomplete_gateway_config',
            'test_front_realtime_commission_api_returns_current_agent_rebate_rows',
        ];
    }

    private function isolatedPaymentTests(): array
    {
        return [
            'test_front_deposit_without_configured_channels_fails_closed_in_blade',
            'test_front_demo_payment_channel_remarks_are_seeded_in_database_config',
            'test_front_deposit_database_channels_normalize_legacy_remark_fields',
            'test_front_deposit_channel_remarks_do_not_fall_back_to_controller_literals',
            'test_crmui_deposit_keeps_submission_aliases_but_rejects_unconfigured_channels',
            'test_legacy_deposit_submission_rejects_incomplete_gateway_config',
        ];
    }

    public function test_front_login_missing_profile_returns_user_not_found(): void
    {
        $now = time();
        $userId = 990099;

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $userId],
            [
                'email' => 'missing-profile-login@example.test',
                'password' => Hash::make('123456'),
                'account_type' => 2,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 1,
                'jwt_token_id' => '',
                'last_login_ip' => '',
                'last_login_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $response = $this->postJson('/api/front/auth/login', [
            'account' => (string) $userId,
            'password' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::USER_NOT_FOUND)
            ->assertJsonPath('message', __('auth.user_info_not_found'));

        $this->assertArrayNotHasKey('access_token', $response->json('data') ?: []);
    }

    public function test_front_dashboard_returns_legacy_profile_contract(): void
    {
        $now = time();
        $userId = 990001;

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $userId],
            [
                'email' => 'legacy-dashboard-profile@example.test',
                'password' => password_hash('123456', PASSWORD_BCRYPT),
                'account_type' => 2,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 1,
                'jwt_token_id' => '',
                'last_login_ip' => '',
                'last_login_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Legacy Dashboard User',
                'phone' => '86-13900000001',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 123.45,
                'equity' => 123.45,
                'effective_credit' => 0,
                'comm_rate' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/dashboard');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.user.user_id', (string) $userId)
            ->assertJsonPath('data.user.user_name', 'Legacy Dashboard User');
    }

    public function test_front_dashboard_news_timeline_does_not_open_detail_modal(): void
    {
        $blade = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $dashboard = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';

        $this->assertStringContainsString('id="dashboardNews"', $blade);
        $this->assertStringContainsString('layui-timeline', $blade);
        $this->assertStringNotContainsString('dashboard-news-link', $blade);
        $this->assertStringNotContainsString('J_dashboardNews', $dashboard);
        $this->assertStringNotContainsString('openNewsDetailModal', $dashboard);
    }

    public function test_blade_templates_have_no_inline_executable_scripts(): void
    {
        $violations = [];

        foreach ($this->filesUnder(resource_path(), '.blade.php') as $file) {
            $content = file_get_contents($file) ?: '';

            if (preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $content, $scripts, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($scripts as $script) {
                    $attributes = $script[1][0] ?? '';
                    $body = trim($script[2][0] ?? '');

                    if ($body === '' || preg_match('/\bsrc\s*=/i', $attributes)) {
                        continue;
                    }

                    if (preg_match('/\btype\s*=\s*["\']?(?:text\/html|application\/json|application\/ld\+json)["\']?/i', $attributes)) {
                        continue;
                    }

                    $line = substr_count(substr($content, 0, $script[0][1]), "\n") + 1;
                    $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file) . ':' . $line;
                }
            }

            if (preg_match_all('/\s(on[a-z]+)\s*=/i', $content, $events, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($events as $event) {
                    $line = substr_count(substr($content, 0, $event[0][1]), "\n") + 1;
                    $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file) . ':' . $line . ' ' . strtolower($event[1][0]);
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Blade templates must load executable JS through public/js apps/shared modules; inline script logic belongs in JS files.');
    }

    public function test_search_forms_use_module_placeholder_contract(): void
    {
        $violations = [];
        $roots = [
            resource_path('front/layui'),
            resource_path('admin/layui'),
        ];

        foreach ($roots as $root) {
            foreach ($this->filesUnder($root, '.blade.php') as $file) {
                $content = file_get_contents($file) ?: '';

                if (! preg_match_all(
                    '/<form\b(?=[^>]*(?:id="[^"]*SearchForm"|lay-filter="moduleSearchForm"|class="[^"]*(?:module-toolbar|J_flowForm)[^"]*"))[^>]*>.*?<\/form>/is',
                    $content,
                    $forms
                )) {
                    continue;
                }

                foreach ($forms[0] as $form) {
                    $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);

                    if (preg_match('/<label\b[^>]*class="[^"]*\blayui-form-label\b[^"]*"/i', $form)) {
                        $violations[] = $relative . ' search form still renders layui-form-label.';
                    }

                    if (! preg_match_all('/<input\b[^>]*class="[^"]*\blayui-input\b[^"]*"[^>]*>/i', $form, $inputs)) {
                        continue;
                    }

                    foreach ($inputs[0] as $input) {
                        if (preg_match('/\bplaceholder\s*=/i', $input)) {
                            continue;
                        }

                        $name = preg_match('/\bname="([^"]+)"/i', $input, $matches) ? $matches[1] : 'unknown';
                        $violations[] = $relative . ' search input [' . $name . '] is missing placeholder.';
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_legacy_dom_markers_removed_from_front_assets(): void
    {
        $violations = $this->filesContaining([
            'crm-data-head',
            'crm-row-detail',
        ], [
            public_path('js/apps/front/layui'),
            public_path('css/front'),
            resource_path('front/layui'),
            resource_path('admin/layui'),
        ]);

        $this->assertSame([], $violations, '前端不应再出现 crm-data-head 或 crm-row-detail 这类 DOM 残留。');
    }

    public function test_module_page_script_exports_image_helpers(): void
    {
        $layui = $this->publicScript('front/layui/module-page.js');

        $this->assertStringContainsString('function parseImages', $layui);
        $this->assertStringContainsString('function imageIconsHtml', $layui);
        $this->assertStringContainsString('crm-image-icons', $layui);
    }

    public function test_voucher_page_image_helpers_contract(): void
    {
        $voucherBlade = file_get_contents(resource_path('front/layui/account/voucher.blade.php')) ?: '';
        $layui = $this->publicScript('front/layui/module-page.js');
        $frontCss = file_get_contents(public_path('css/front/style.css')) ?: '';

        $this->assertStringContainsString("['key' => 'images', 'label' => 'front.voucher_images']", $voucherBlade);
        $this->assertStringNotContainsString("'chartGroups' =>", $voucherBlade, 'Voucher page must not initialize ECharts when it has no chart container.');

        $this->assertStringContainsString('function openImagePreview', $layui);
        $this->assertStringContainsString('data-image-preview', $layui);
        $this->assertStringContainsString("'/storage/' + url.replace", $layui);

        $this->assertStringContainsString("$(document).on('click', '[data-image-preview]'", $layui);
        $this->assertStringNotContainsString('閳?', $layui);
        $this->assertStringContainsString(">\u{67E5}\u{770B}</a>", $layui);
        $this->assertStringContainsString('data-lucide="file-text"', $layui);
        $this->assertStringContainsString('width: 132px;', $frontCss);
        $this->assertStringContainsString('max-width: 132px;', $frontCss);
        $this->assertStringContainsString('aspect-ratio: 4 / 3;', $frontCss);
        $this->assertStringContainsString('object-fit: contain;', $frontCss);
    }

    public function test_admin_voucher_review_script_and_blade_contract(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/vouchers/index.blade.php')) ?: '';
        $script = $this->publicScript('admin/layui/vouchers/index.js');
        $adminCss = file_get_contents(public_path('css/admin/style.css')) ?: '';

        $this->assertStringContainsString('id="voucherTable"', $blade);
        $this->assertStringContainsString("{field: 'images'", $script);
        $this->assertStringContainsString("{field: 'images', title: CrmLang.t('front.voucher_images'), width: 220, templet: voucherImagesHtml}", $script);
        $this->assertStringContainsString('function parseVoucherImages', $script);
        $this->assertStringContainsString('function voucherImagesHtml', $script);
        $this->assertStringContainsString('function openVoucherImagePreview', $script);
        $this->assertStringContainsString('data-admin-voucher-preview', $script);
        $this->assertStringContainsString('role="button" tabindex="0"', $script);
        $this->assertStringContainsString("$(document).on('click', '[data-admin-voucher-preview]'", $script);
        $this->assertStringContainsString("$(document).on('keydown', '[data-admin-voucher-preview]'", $script);
        $this->assertStringContainsString("event.key !== 'Enter' && event.key !== ' '", $script);
        $this->assertStringContainsString('event.stopPropagation();', $script);
        $this->assertStringContainsString("'/storage/' + url.replace", $script);
        $this->assertStringContainsString('admin-voucher-preview-layer', $script);
        $this->assertStringContainsString('admin-voucher-preview-image', $script);
        $this->assertStringNotContainsString('style="box-sizing:border-box', $script, 'Voucher preview layout must be controlled by CSS classes, not inline styles.');
        foreach ([
            '.admin-voucher-image-links',
            'max-width: 220px;',
            'flex-wrap: wrap;',
            'overflow-wrap: anywhere;',
            '.admin-voucher-preview-layer',
            '.admin-voucher-preview-image',
            'object-fit: contain;',
            'max-height: calc(88vh - 90px);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $adminCss, 'Admin voucher image links must stay compact and readable: ' . $needle);
        }
        $this->assertStringNotContainsString('echarts.', $script, 'Admin voucher review must not initialize ECharts when it has no chart container.');
    }

    public function test_crmui_vouchers_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/vouchers')->assertOk()->getContent();

        foreach (['images', 'user_id', 'review_status', 'review_message', 'created_at'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI voucher column: ' . $field);
        }

        $this->assertStringContainsString('data-crmui-row-action="approve"', $crmuiHtml);
        $this->assertStringContainsString('/api/admin/voucherApprove/__ID__', $crmuiHtml);
        $this->assertStringContainsString('data-crmui-row-action="reject"', $crmuiHtml);
        $this->assertStringContainsString('/api/admin/voucherReject/__ID__', $crmuiHtml);
        $this->assertStringContainsString('name:reason:textarea', $crmuiHtml);
    }

    public function test_crmui_cancel_applies_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/cancel-applies')->assertOk()->getContent();
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';
        $css = file_get_contents(public_path('css/crmui/admin.css')) ?: '';

        foreach (['user_id', 'user_name', 'balance', 'open_positions', 'cancel_remark', 'reject_reason', 'status', 'created_at'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI cancel apply column: ' . $field);
        }
        foreach (['user_id', 'status', 'start_date', 'end_date'] as $filter) {
            $this->assertStringContainsString('name="' . $filter . '"', $crmuiHtml, 'Missing CrmUI cancel apply filter: ' . $filter);
        }
        $this->assertMatchesRegularExpression('/<option value="0"\s+selected>/', $crmuiHtml, 'CrmUI cancellation list must default to pending status.');

        $this->assertStringContainsString('data-crmui-row-action="approve"', $crmuiHtml);
        $this->assertStringContainsString('/api/admin/cancelApplyApprove/__ID__', $crmuiHtml);
        $this->assertStringContainsString('data-crmui-row-action="reject"', $crmuiHtml);
        $this->assertStringContainsString('/api/admin/cancelApplyReject/__ID__', $crmuiHtml);
        $this->assertStringContainsString('name:reason:textarea', $crmuiHtml);
        $this->assertSame(2, substr_count($crmuiHtml, 'data-visible-when='), 'Both CrmUI review actions must be visible only for pending rows.');
        $this->assertSame(2, substr_count($crmuiHtml, 'data-field-rules='), 'Both CrmUI review actions must declare required remark metadata.');
        $this->assertStringContainsString('function actionMatchesRow', $script);
        $this->assertStringContainsString('function validateRequiredActionFields', $script);
        $this->assertStringContainsString('$actionModal.data(\'requestPending\', true)', $script);
        $this->assertStringContainsString('crmui-status-badge', $script);
        $this->assertStringContainsString('crmui-money.is-negative', $css);
    }

    public function test_layui_cancel_applies_page_contract(): void
    {
        $layuiHtml = $this->get('/admin/cancel-applies')->assertOk()->getContent();
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $css = file_get_contents(public_path('css/admin/style.css')) ?: '';

        foreach (['user_id', 'status', 'start_date', 'end_date'] as $filter) {
            $this->assertStringContainsString('name="' . $filter . '"', $layuiHtml, 'Missing Layui cancel apply filter: ' . $filter);
        }
        $this->assertMatchesRegularExpression('/<option value="0"\s+selected>/', $layuiHtml, 'Layui cancellation list must default to pending status.');
        $this->assertStringContainsString('id="resetCancelApplySearch"', $layuiHtml);
        $this->assertStringContainsString('Number(d.status) === 0', $layuiHtml, 'Processed cancellation rows must not render review actions.');

        foreach (['user_name', 'balance', 'open_positions', 'cancel_remark', 'reject_reason'] as $column) {
            $this->assertStringContainsString("field: '" . $column . "'", $script, 'Missing Layui cancel apply column: ' . $column);
        }
        $this->assertStringContainsString('function openCancelApplyReview', $script);
        $this->assertStringContainsString('formType: 2', $script);
        $this->assertStringContainsString("attr('maxlength', 500)", $script);
        $this->assertStringContainsString('reviewRequestPending', $script);
        $this->assertStringContainsString('data: {reason: reviewRemark}', $script);
        $this->assertStringContainsString('cancel-apply-status-badge', $script);
        $this->assertStringContainsString('.cancel-apply-balance.is-negative', $css);
    }

    public function test_crmui_commissions_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/commissions')->assertOk()->getContent();

        foreach (['id', 'agent_id', 'user_id', 'commission_amount', 'settle_status', 'created_at'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI commission column: ' . $field);
        }

        $this->assertStringContainsString('/api/admin/commissions', $crmuiHtml);
        $this->assertStringContainsString('/api/admin/commissionSettle', $crmuiHtml);
        $this->assertStringContainsString('data-crmui-row-action="settle"', $crmuiHtml);
        $this->assertStringContainsString('data-record-key="id"', $crmuiHtml);
        $this->assertStringContainsString('data-payload-name="id"', $crmuiHtml);
    }

    public function test_crmui_agents_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/agents')->assertOk()->getContent();

        foreach (['user_id', 'user_name', 'level_id', 'comm_rate', 'auth_status'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI agent column: ' . $field);
        }

        foreach (['/api/admin/agentDescendants', '/api/admin/updateAgentLevel', '/api/admin/updateAgentCommission'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml, 'Missing CrmUI agent endpoint: ' . $endpoint);
        }

        foreach (['descendants', 'update_level', 'update_commission'] as $action) {
            $this->assertStringContainsString('data-crmui-row-action="' . $action . '"', $crmuiHtml, 'Missing CrmUI agent action: ' . $action);
        }

        $this->assertStringContainsString('data-record-key="user_id"', $crmuiHtml);
        $this->assertStringContainsString('data-payload-name="agent_id"', $crmuiHtml);
    }

    public function test_crmui_authentications_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/authentications')->assertOk()->getContent();
        $crmuiJs = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        foreach (['user_id', 'user_name', 'id_card_no', 'id_card_status', 'bank_status', 'created_at'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI auth column: ' . $field);
        }

        foreach (['/api/admin/authPendingList', '/api/admin/authCertifiedList', '/api/admin/reviewAuth'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml . $crmuiJs, 'Missing CrmUI auth endpoint: ' . $endpoint);
        }

        foreach (['data-crmui-view="pending"', 'data-crmui-view="certified"', 'data-crmui-row-action="review"', 'name:id_card_decision:select', 'name:id_card_reason:textarea', 'name:bank_decision:select', 'name:bank_reason:textarea'] as $needle) {
            $this->assertStringContainsString($needle, $crmuiHtml . $crmuiJs, 'Missing CrmUI authentication review support: ' . $needle);
        }
        $this->assertStringNotContainsString('name:status:select', $crmuiHtml);
        $this->assertStringNotContainsString('name:reason:textarea', $crmuiHtml);
    }

    public function test_crmui_deposits_and_withdrawals_page_contract(): void
    {
        $depositHtml = $this->get('/admin-crmui/deposits')->assertOk()->getContent();
        $withdrawHtml = $this->get('/admin-crmui/withdrawals')->assertOk()->getContent();

        foreach (['/api/admin/depositApprove', '/api/admin/depositReject'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $depositHtml, 'Missing CrmUI deposit endpoint: ' . $endpoint);
        }

        foreach (['approve', 'reject'] as $action) {
            $this->assertStringContainsString('data-crmui-row-action="' . $action . '"', $depositHtml, 'Missing CrmUI deposit action: ' . $action);
        }

        foreach (['/api/admin/withdrawProcess', '/api/admin/withdrawComplete', '/api/admin/withdrawReject'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $withdrawHtml, 'Missing CrmUI withdraw endpoint: ' . $endpoint);
        }

        foreach (['process', 'complete', 'reject'] as $action) {
            $this->assertStringContainsString('data-crmui-row-action="' . $action . '"', $withdrawHtml, 'Missing CrmUI withdraw action: ' . $action);
        }

        $this->assertStringContainsString('name:reason:textarea', $depositHtml);
        $this->assertStringContainsString('name:reason:textarea', $withdrawHtml);
    }

    public function test_crmui_rights_summary_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/rights-summary')->assertOk()->getContent();

        foreach (['user_id', 'balance', 'equity', 'margin', 'margin_free', 'leverage', 'settlement_amount', 'settlement_status', 'updated_at'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI rights summary column: ' . $field);
        }

        $this->assertStringContainsString('/api/admin/rightsSummaryList', $crmuiHtml);
        $this->assertStringContainsString('/api/admin/manualConfirmRightsSettlement/__ID__', $crmuiHtml);
        $this->assertStringContainsString('data-crmui-row-action="manual_confirm"', $crmuiHtml);
        $this->assertStringContainsString('data-record-key="settlement_id"', $crmuiHtml);
        $this->assertStringContainsString('name:manual_confirm_reason:textarea', $crmuiHtml);
    }

    /**
     * 后台持仓汇总前端必须展示后端真实返回的代理树汇总字段。
     *
     * 业务边界：
     * - 服务端 Blade 页面读取 /api/admin/positionSummaryList。
     * - 字段必须对应 PositionSummaryController 返回的 user_infos 与代理树交易聚合结果，不能继续使用 symbol/volume/profit 这种明细订单列。
     */
    public function test_crmui_position_summary_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/position-summary')->assertOk()->getContent();

        $fields = [
            'user_id',
            'user_name',
            'parent_id',
            'account_type',
            'mt4_group',
            'total_orders',
            'total_volume',
            'total_profit',
            'total_comm',
            'total_swaps',
            'total_noble_metal',
            'total_for_exca',
            'total_crud_oil',
            'total_index',
            'total_currency',
            'total_stock',
        ];

        foreach ($fields as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI position summary column: ' . $field);
        }

        foreach (['/api/admin/positionSummaryList', '/api/admin/exportPositionSummary'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml, 'Missing CrmUI position summary endpoint: ' . $endpoint);
        }
    }

    public function test_crmui_import_modules_page_contract(): void
    {
        $modules = [
            'deposit-imports' => [
                'retry' => '/api/admin/retryDepositImport/__ID__',
                'sync' => '/api/admin/syncDepositImport/__ID__',
                'endpoint' => '/api/admin/depositImportList',
                'fields' => ['id', 'user_id', 'user_name', 'amount', 'batch_no', 'mt4_order_id', 'is_synced', 'fail_reason', 'created_at'],
            ],
            'withdraw-imports' => [
                'retry' => '/api/admin/retryWithdrawImport/__ID__',
                'sync' => '/api/admin/syncWithdrawImport/__ID__',
                'endpoint' => '/api/admin/withdrawImportList',
                'fields' => ['id', 'user_id', 'user_name', 'amount', 'batch_no', 'mt4_order_id', 'is_synced', 'fail_reason', 'created_at'],
            ],
            'credit-imports' => [
                'retry' => '/api/admin/retryCreditImport/__ID__',
                'sync' => '/api/admin/syncCreditImport/__ID__',
                'endpoint' => '/api/admin/creditImportList',
                'fields' => ['id', 'user_id', 'user_name', 'credit_type', 'amount', 'batch_no', 'mt4_order_id', 'is_synced', 'fail_reason', 'created_at'],
            ],
        ];

        foreach ($modules as $path => $module) {
            $crmuiHtml = $this->get('/admin-crmui/' . $path)->assertOk()->getContent();

            foreach ($module['fields'] as $field) {
                $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI import column for ' . $path . ': ' . $field);
            }

            $this->assertStringContainsString($module['endpoint'], $crmuiHtml, 'Missing CrmUI import list endpoint for ' . $path);
            $this->assertStringContainsString($module['retry'], $crmuiHtml, 'Missing CrmUI import retry endpoint for ' . $path);
            $this->assertStringContainsString('data-crmui-row-action="retry_import"', $crmuiHtml, 'Missing CrmUI import retry row action for ' . $path);
            $this->assertStringContainsString('data-record-key="id"', $crmuiHtml, 'Missing CrmUI import retry record id for ' . $path);

            if ($module['sync']) {
                $this->assertStringContainsString($module['sync'], $crmuiHtml, 'Missing CrmUI import sync endpoint for ' . $path);
                $this->assertStringContainsString('data-crmui-row-action="sync_import"', $crmuiHtml, 'Missing CrmUI import sync row action for ' . $path);
            }
        }
    }

    public function test_crmui_data_scopes_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/data-scopes')->assertOk()->getContent();
        $crmuiJs = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        foreach (['/api/admin/roleDataScopeList', '/api/admin/saveRoleDataScope', '/api/admin/adminAgentBindingList', '/api/admin/saveAdminAgentBinding', '/api/admin/deleteAdminAgentBinding'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml . $crmuiJs, 'Missing CrmUI data-scope endpoint: ' . $endpoint);
        }

        foreach (['id', 'name', 'guard_type', 'data_scope.scope_type', 'data_scope.agent_ids', 'data_scope.user_ids', 'admin_id', 'agent_id', 'binding_type', 'status'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI data-scope column: ' . $field);
        }

        foreach (['data-crmui-view="role_scopes"', 'data-crmui-view="admin_agent_bindings"', 'data-crmui-row-action="save_scope"', 'data-crmui-row-action="save_binding"', 'data-crmui-row-action="delete_binding"', 'data-action-view="role_scopes"', 'data-action-view="admin_agent_bindings"', 'data-crmui-active-view'] as $needle) {
            $this->assertStringContainsString($needle, $crmuiHtml . $crmuiJs, 'Missing CrmUI data-scope view/action support: ' . $needle);
        }
    }

    public function test_crmui_blacklist_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/blacklist')->assertOk()->getContent();

        foreach (['name', 'id_card', 'email', 'phone'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI blacklist column: ' . $field);
        }

        foreach (['name', 'id_card', 'email', 'phone', 'remark'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $crmuiHtml, 'Missing CrmUI blacklist form field: ' . $field);
        }

        $this->assertStringContainsString('/api/admin/createBlacklist', $crmuiHtml);
        $this->assertStringContainsString('/api/admin/updateBlacklist/__ID__', $crmuiHtml);
        $this->assertStringContainsString('/api/admin/deleteBlacklist/__ID__', $crmuiHtml);
        $this->assertStringContainsString('data-crmui-row-action="update"', $crmuiHtml);
        $this->assertStringContainsString('data-crmui-row-action="delete"', $crmuiHtml);
    }

    public function test_crmui_roles_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/roles')->assertOk()->getContent();
        $crmuiJs = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        foreach (['id', 'name', 'guard_type', 'description'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI role column: ' . $field);
        }

        foreach (['/api/admin/roles', '/api/admin/createRole', '/api/admin/updateRole', '/api/admin/deleteRole', '/api/admin/permissions/tree', '/api/admin/assignPermissions'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml . $crmuiJs, 'Missing CrmUI role endpoint: ' . $endpoint);
        }

        foreach (['update', 'delete', 'assign_permissions'] as $action) {
            $this->assertStringContainsString('data-crmui-row-action="' . $action . '"', $crmuiHtml, 'Missing CrmUI role action: ' . $action);
        }

        foreach (['data-permission-tree-url', 'data-permission-tree', 'collectCrmUiPermissionIds', 'loadCrmUiPermissionTree'] as $needle) {
            $this->assertStringContainsString($needle, $crmuiHtml . $crmuiJs, 'Missing CrmUI role permission-tree support: ' . $needle);
        }
    }

    public function test_crmui_admins_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/admins')->assertOk()->getContent();

        foreach (['id', 'username', 'email', 'status', 'created_at'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI admin column: ' . $field);
        }

        foreach (['/api/admin/admins', '/api/admin/createAdmin', '/api/admin/updateAdmin/__ID__', '/api/admin/deleteAdmin/__ID__'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml, 'Missing CrmUI admin endpoint: ' . $endpoint);
        }

        foreach (['update', 'delete'] as $action) {
            $this->assertStringContainsString('data-crmui-row-action="' . $action . '"', $crmuiHtml, 'Missing CrmUI admin row action: ' . $action);
        }
    }

    public function test_crmui_big_agents_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/big-agents')->assertOk()->getContent();

        foreach (['id', 'username', 'is_enabled', 'created_at'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI big-agent column: ' . $field);
        }

        foreach (['/api/admin/bigAgentList', '/api/admin/createBigAgent', '/api/admin/updateBigAgent/__ID__', '/api/admin/deleteBigAgent/__ID__'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml, 'Missing CrmUI big-agent endpoint: ' . $endpoint);
        }

        foreach (['update', 'delete'] as $action) {
            $this->assertStringContainsString('data-crmui-row-action="' . $action . '"', $crmuiHtml, 'Missing CrmUI big-agent row action: ' . $action);
        }
    }

    public function test_crmui_config_modules_page_contract(): void
    {
        $modules = [
            'agent-levels' => [
                'list' => '/api/admin/agent-levels',
                'create' => '/api/admin/createAgentLevel',
                'update' => '/api/admin/updateAgentLevel2/__ID__',
                'delete' => '/api/admin/deleteAgentLevel/__ID__',
                'fields' => ['id', 'level_code', 'name', 'max_commission', 'min_commission', 'user_commission'],
            ],
            'group-configs' => [
                'list' => '/api/admin/group-configs',
                'create' => '/api/admin/createGroupConfig',
                'update' => '/api/admin/updateGroupConfig/__ID__',
                'delete' => '/api/admin/deleteGroupConfig/__ID__',
                'fields' => ['id', 'name', 'radix', 'category', 'is_enabled', 'updated_at'],
            ],
            'channels' => [
                'list' => '/api/admin/channels',
                'create' => '/api/admin/createChannel',
                'update' => '/api/admin/updateChannel/__ID__',
                'delete' => '/api/admin/deleteChannel/__ID__',
                'toggle' => '/api/admin/toggleChannel/__ID__',
                'fields' => ['id', 'name', 'channel_code', 'exchange_rate', 'is_enabled', 'sort'],
            ],
            'news' => [
                'list' => '/api/admin/news',
                'create' => '/api/admin/createNews',
                'update' => '/api/admin/updateNews/__ID__',
                'delete' => '/api/admin/deleteNews/__ID__',
                'toggle' => '/api/admin/toggleNews/__ID__',
                'fields' => ['id', 'title', 'is_published', 'created_at'],
            ],
            'productions' => [
                'list' => '/api/admin/productionList',
                'create' => '/api/admin/createProduction',
                'update' => '/api/admin/updateProduction/__ID__',
                'delete' => '/api/admin/deleteProduction/__ID__',
                'fields' => ['id', 'symbol', 'bid', 'ask', 'spread', 'group_id', 'status', 'modify_time'],
            ],
        ];

        foreach ($modules as $path => $module) {
            $crmuiHtml = $this->get('/admin-crmui/' . $path)->assertOk()->getContent();

            foreach ([$module['list'], $module['create'], $module['update'], $module['delete']] as $endpoint) {
                $this->assertStringContainsString($endpoint, $crmuiHtml, 'Missing CrmUI ' . $path . ' endpoint: ' . $endpoint);
            }

            foreach ($module['fields'] as $field) {
                $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI ' . $path . ' column: ' . $field);
            }

            foreach (['update', 'delete'] as $action) {
                $this->assertStringContainsString('data-crmui-row-action="' . $action . '"', $crmuiHtml, 'Missing CrmUI ' . $path . ' row action: ' . $action);
            }
        }
    }

    /**
     * 验证后台礼品发货记录页与地址选择页共同形成服务端 Blade 发放闭环。
     *
     * @return void 发货页提供记录与状态更新，地址页提供真实收件人选择并提交 sendGift。
     */
    public function test_crmui_gifts_page_contract(): void
    {
        $shipmentHtml = $this->get('/admin-crmui/gifts')->assertOk()->getContent();
        $addressHtml = $this->get('/admin-crmui/gift-addresses')->assertOk()->getContent();
        $crmuiJs = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';
        $combined = $shipmentHtml . $addressHtml . $crmuiJs;

        foreach (['/api/admin/giftShipmentList', '/api/admin/giftAddressList', '/api/admin/sendGift'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $combined, 'Missing gift endpoint: ' . $endpoint);
        }

        foreach (['sender_name', 'gift_name', 'gift_quantity', 'tracking_number', 'remark'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $addressHtml, 'Missing CrmUI send gift field: ' . $field);
        }

        $this->assertStringContainsString('data-crmui-gift-recipient-picker="1"', $addressHtml);
        $this->assertStringContainsString('data-crmui-row-action="select_gift_recipient"', $addressHtml);
        $this->assertStringContainsString('crmUiGiftSendPayload', $crmuiJs);
    }

    public function test_crmui_trades_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/trades')->assertOk()->getContent();

        foreach (['login', 'ticket', 'symbol', 'cmd', 'volume', 'commission', 'swaps', 'profit', 'open_time', 'close_time'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI trade column: ' . $field);
        }

        foreach (['/api/admin/tradeList', '/api/admin/openPositions', '/api/admin/closedPositions'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml, 'Missing CrmUI trade endpoint: ' . $endpoint);
        }

        foreach (['total_orders', 'total_volume', 'total_profit', 'total_swaps', 'total_commission'] as $metric) {
            $this->assertStringContainsString('data-crmui-metric="' . $metric . '"', $crmuiHtml, 'Missing CrmUI trade metric: ' . $metric);
        }
    }

    public function test_crmui_risk_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/risk')->assertOk()->getContent();

        foreach (['login', 'user_name', 'ticket', 'symbol', 'volume', 'commission', 'profit', 'risk_value', 'open_time'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI risk position column: ' . $field);
        }

        foreach (['/api/admin/riskPositions', '/api/admin/riskMarginCalls', '/api/admin/riskIpList', '/api/admin/riskIpDetail', '/api/admin/riskForceClose/__ID__'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml, 'Missing CrmUI risk endpoint: ' . $endpoint);
        }

        foreach (['total_records', 'total_profit', 'total_volume', 'total_risk_value', 'total_margin'] as $metric) {
            $this->assertStringContainsString('data-crmui-metric="' . $metric . '"', $crmuiHtml, 'Missing CrmUI risk metric: ' . $metric);
        }
    }

    public function test_crmui_whs_exp_zero_page_contract(): void
    {
        $crmuiHtml = $this->get('/admin-crmui/whs-exp-zero')->assertOk()->getContent();
        $crmuiJs = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        foreach (['/api/admin/whsExpZeroList', '/api/admin/whsExpZeroRecords', '/api/admin/whsExpZero'] as $endpoint) {
            $this->assertStringContainsString($endpoint, $crmuiHtml . $crmuiJs, 'Missing CrmUI whs-exp-zero endpoint: ' . $endpoint);
        }

        foreach (['userId', 'userName', 'userBalance', 'userCredit', 'needZeroAmount', 'id', 'user_id', 'user_name', 'balance_before', 'credit_amount', 'zero_amount', 'status_name', 'fail_reason', 'created_at', 'processed_at'] as $field) {
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'Missing CrmUI whs-exp-zero column: ' . $field);
        }

        foreach (['data-crmui-view="zero_candidates"', 'data-crmui-view="zero_records"', 'data-crmui-row-action="one_key_zero"', 'data-action-view="zero_candidates"'] as $needle) {
            $this->assertStringContainsString($needle, $crmuiHtml . $crmuiJs, 'Missing CrmUI whs-exp-zero view/action support: ' . $needle);
        }
    }

    /**
     * 验证后台只暴露两套服务端 Blade 视觉外观，不再出现已删除的 Naive 入口。
     *
     * @return void 成功时 Layui 与 CrmUI 均可切换，Naive 选项和客户端应用路由均不存在。
     */
    public function test_admin_ui_style_switch_contract(): void
    {
        $header = file_get_contents(resource_path('admin/layui/layouts/header.blade.php')) ?: '';
        $layout = $this->publicScript('admin/layui/layout.js');

        foreach (['layui', 'crmui'] as $style) {
            $this->assertStringContainsString('data-style="' . $style . '"', $header, 'Admin header missing UI style option: ' . $style);
            $this->assertStringContainsString("'.crm-style-switch[data-style=\"' + activeStyle + '\"]'", $layout);
        }

        $this->assertStringNotContainsString('data-style="naive"', $header);
        $this->assertStringNotContainsString("routeUrl('admin_naive_app'", $layout);
        $this->assertStringContainsString("routeUrl('admin_crmui_app'", $layout);
        $this->assertStringContainsString('admin_ui_style', $layout);
    }

    public function test_front_responsive_layer_css_contract(): void
    {
        $frontCss = file_get_contents(public_path('css/front/style.css')) ?: '';

        $this->assertStringContainsString('.layui-layer.crm-responsive-layer', $frontCss);
        $this->assertStringContainsString('max-height: calc(100dvh - 24px)', $frontCss);
        $this->assertStringContainsString('.crm-responsive-layer-body', $frontCss);
    }

    public function test_module_page_summary_renders_above_table(): void
    {
        $moduleBlade = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';
        $layuiSummaryPosition = strpos($moduleBlade, 'id="moduleAutoSummary"');
        $layuiTablePosition = strpos($moduleBlade, '<div class="module-table-wrap">');

        $this->assertNotFalse($layuiSummaryPosition, 'Layui 妯″潡椤靛繀椤诲寘鍚嚜鍔ㄦ眹鎬诲鍣ㄣ€?');
        $this->assertNotFalse($layuiTablePosition, 'Layui 妯″潡椤靛繀椤诲寘鍚〃鏍煎鍣ㄣ€?');

        $this->assertLessThan(
            $layuiTablePosition,
            $layuiSummaryPosition,
            'Layui module summary must render above the table.'
        );
    }

    public function test_account_info_exposes_runtime_group_and_gender_profile(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Front/AccountController.php')) ?: '';
        $blade = file_get_contents(resource_path('front/layui/account/info.blade.php')) ?: '';
        $layui = $this->publicScript('front/layui/module-page.js');
        $sharedI18n = file_get_contents(public_path('js/shared/i18n.js')) ?: '';
        $commonZh = file_get_contents(public_path('js/shared/lang/common/zh-CN.js')) ?: '';
        $commonEn = file_get_contents(public_path('js/shared/lang/common/en.js')) ?: '';

        foreach (['groupConfig', 'group_name', 'commission_rate', 'customer_gender_profile', 'funds_comparison'] as $needle) {
            $this->assertStringContainsString($needle, $controller, 'accountInfo must expose ' . $needle . '.');
        }
        $accountOverviewMethod = $this->sourceBetween($controller, 'private function accountOverviewData', 'private function customerGenderProfile');
        $this->assertStringContainsString('->closed()', $accountOverviewMethod, 'Account overview order statistics must reuse the UserTrade closed scope.');
        $this->assertStringContainsString('->open()', $accountOverviewMethod, 'Account overview order statistics must reuse the UserTrade open scope.');
        $this->assertStringNotContainsString('1971-01-01', $accountOverviewMethod, 'Account overview must not use a different close_time sentinel from the order list.');
        foreach (['male', 'female', 'unknown', 'ratio'] as $needle) {
            $this->assertStringContainsString("'" . $needle . "'", $controller, 'customer gender profile must include ' . $needle . '.');
        }

        $this->assertStringContainsString("'key' => 'group_name'", $blade);
        $this->assertStringContainsString("'key' => 'commission_rate'", $blade);
        $this->assertStringContainsString("'comparisonTable' => 'funds_comparison'", $blade);
        $summaryFieldConfig = $this->sourceBetween($blade, "'summaryFields' => [", "    'comparisonTable' => 'funds_comparison'");
        foreach (['total_funds', 'equity'] as $fundMetricKey) {
            $this->assertStringNotContainsString(
                "'key' => '" . $fundMetricKey . "'",
                $summaryFieldConfig,
                'Account overview must keep comparison-first fund metrics out of one-by-one summary cards: ' . $fundMetricKey
            );
        }
        foreach ([
            "'target' => 'accountGenderChart'",
            "'title' => 'front.client_gender_profile'",
            "'key' => 'customer_gender_profile.male.ratio'",
            "'key' => 'customer_gender_profile.female.ratio'",
            "'key' => 'customer_gender_profile.unknown.ratio'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $blade, 'Account client profile must expose gender ratio chart config: ' . $needle);
        }

        $this->assertStringContainsString('function accountComparisonTableHtml', $layui);
        $this->assertStringContainsString('funds_comparison', $layui);
        $this->assertStringContainsString('front.account_comparison_table', $layui);
        foreach ([$sharedI18n, $commonZh, $commonEn] as $dictionary) {
            $this->assertStringContainsString('client_gender_profile', $dictionary, 'Runtime JS i18n dictionaries must translate the account gender chart title.');
        }
    }

    /**
     * 验证账户综合接口返回当前数据库中的组名和运行时统计指标。
     *
     * @return void
     */
    public function test_front_account_profile_api_returns_runtime_overview_metrics(): void
    {
        $this->seedFrontDemoDataAndCaptureOwnedConfig();

        $login = UserLogin::where('user_id', 1001)->firstOrFail();
        $expectedGroupName = (string) DB::table('user_infos')
            ->join('group_configs', 'group_configs.id', '=', 'user_infos.group_id')
            ->where('user_infos.user_id', 1001)
            ->value('group_configs.name');
        $this->assertNotSame('', $expectedGroupName, 'Demo 账号必须关联可解析的当前交易组。');

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/profile');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.group_name', $expectedGroupName)
            ->assertJsonPath('data.commission_rate', 85);

        $comparisonRows = $response->json('data.funds_comparison');
        $this->assertCount(5, $comparisonRows);
        $this->assertSame(
            ['total_deposit', 'total_rebate', 'total_withdraw', 'total_funds', 'equity'],
            array_column($comparisonRows, 'key')
        );

        $genderProfile = $response->json('data.customer_gender_profile');
        foreach (['male', 'female', 'unknown'] as $genderKey) {
            $this->assertArrayHasKey($genderKey, $genderProfile);
            $this->assertArrayHasKey('count', $genderProfile[$genderKey]);
            $this->assertArrayHasKey('ratio', $genderProfile[$genderKey]);
        }
        $this->assertGreaterThan(0, array_sum(array_column($genderProfile, 'count')));
    }

    public function test_front_account_profile_api_returns_exact_comparison_table_and_gender_ratios(): void
    {
        $now = time();
        $agentId = 982001;
        $customerIds = [982002, 982003, 982004];
        $userIds = array_merge([$agentId], $customerIds);

        DB::table('deposit_records')->whereIn('user_id', [$agentId])->delete();
        DB::table('withdraw_records')->whereIn('user_id', [$agentId])->delete();
        DB::table('commission_records')->whereIn('agent_id', [$agentId])->delete();
        DB::table('agent_descendants')
            ->where('agent_id', $agentId)
            ->orWhereIn('descendant_id', $customerIds)
            ->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();

        $groupId = (int) DB::table('group_configs')->where('name', 'Regression Metrics Group')->value('id');
        if ($groupId <= 0) {
            $groupId = (int) DB::table('group_configs')->insertGetId([
                'pair_id' => null,
                'name' => 'Regression Metrics Group',
                'radix' => 50,
                'category' => 2,
                'has_commission' => 1,
                'is_enabled' => 1,
                'is_ecn' => 0,
                'is_default' => 0,
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $agentId],
            [
                'email' => 'front-account-metrics-agent@example.test',
                'password' => Hash::make('123456'),
                'account_type' => 1,
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
            ]
        );
        $loginId = (int) DB::table('user_logins')->where('user_id', $agentId)->value('id');

        $upsertUser = static function (int $userId, array $overrides) use ($now, $groupId): void {
            DB::table('user_infos')->updateOrInsert(
                ['user_id' => $userId],
                array_merge([
                    'login_id' => 0,
                    'user_name' => 'Regression User ' . $userId,
                    'phone' => '',
                    'gender' => 0,
                    'avatar' => null,
                    'level_id' => 0,
                    'group_id' => $groupId,
                    'parent_id' => 0,
                    'account_type' => 2,
                    'family_tree' => (string) $userId,
                    'total_funds' => 0,
                    'used_margin' => 0,
                    'avail_margin' => 0,
                    'equity' => 0,
                    'effective_credit' => 0,
                    'risk_ratio' => 0,
                    'margin_amount' => 0,
                    'leverage' => 0,
                    'cust_vol' => '0',
                    'pay_provider_id' => 0,
                    'equity_ratio' => 0,
                    'comm_rate' => 0,
                    'is_ecn' => 0,
                    'follow_parent_ecn' => 0,
                    'auth_status' => 1,
                    'is_mt4_synced' => 1,
                    'is_mt4_enabled' => 1,
                    'is_mt4_readonly' => 0,
                    'is_withdrawal_allowed' => 0,
                    'is_deposit_allowed' => 0,
                    'is_agent_confirmed' => 0,
                    'original_group' => '',
                    'mt4_group' => '',
                    'mt4_code' => 0,
                    'trading_mode' => 0,
                    'settle_method' => 1,
                    'settle_cycle' => 1,
                    'country' => '',
                    'city' => '',
                    'state' => '',
                    'address' => '',
                    'is_gift_allowed' => 0,
                    'data_source' => 0,
                    'remark' => 'front account metrics regression test',
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ], $overrides)
            );
        };

        $upsertUser($agentId, [
            'login_id' => $loginId,
            'user_name' => 'Account Metrics Agent',
            'gender' => 1,
            'level_id' => 1,
            'account_type' => 1,
            'family_tree' => (string) $agentId,
            'total_funds' => 4567.89,
            'equity' => 4321.09,
            'comm_rate' => 73,
            'is_agent_confirmed' => 1,
        ]);
        $upsertUser($customerIds[0], [
            'user_name' => 'Metrics Male Customer',
            'gender' => 1,
            'parent_id' => $agentId,
            'family_tree' => $agentId . ',' . $customerIds[0],
        ]);
        $upsertUser($customerIds[1], [
            'user_name' => 'Metrics Female Customer',
            'gender' => 2,
            'parent_id' => $agentId,
            'family_tree' => $agentId . ',' . $customerIds[1],
        ]);
        $upsertUser($customerIds[2], [
            'user_name' => 'Metrics Unknown Customer',
            'gender' => 0,
            'parent_id' => $agentId,
            'family_tree' => $agentId . ',' . $customerIds[2],
        ]);

        foreach ($customerIds as $customerId) {
            DB::table('agent_descendants')->updateOrInsert(
                ['agent_id' => $agentId, 'descendant_id' => $customerId],
                [
                    'descendant_type' => 2,
                    'is_direct' => 1,
                    'depth' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        foreach ([['ACC-METRIC-D-1', 1200.25], ['ACC-METRIC-D-2', 300.75]] as [$orderNo, $amount]) {
            DB::table('deposit_records')->updateOrInsert(
                ['local_order_no' => $orderNo],
                [
                    'user_id' => $agentId,
                    'user_name' => 'Account Metrics Agent',
                    'mt4_ticket' => 0,
                    'amount' => $amount,
                    'actual_amount' => $amount,
                    'exchange_rate' => 1,
                    'channel_name' => 'Regression',
                    'channel_order_no' => $orderNo,
                    'status' => '02',
                    'payment_time' => '2026-06-01 09:00:00',
                    'remarks' => 'front account metrics regression test',
                    'created_by' => 'test',
                    'updated_by' => 'test',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        foreach ([['ACC-METRIC-W-1', 200.50], ['ACC-METRIC-W-2', 49.50]] as [$orderNo, $amount]) {
            DB::table('withdraw_records')->updateOrInsert(
                ['local_order_no' => $orderNo],
                [
                    'user_id' => $agentId,
                    'user_name' => 'Account Metrics Agent',
                    'mt4_ticket' => '',
                    'apply_amount' => $amount,
                    'actual_amount' => $amount,
                    'fee' => 0,
                    'exchange_rate' => 1,
                    'rmb_fee' => 0,
                    'bank_no' => '',
                    'bank_name' => '',
                    'bank_addr' => '',
                    'status' => 2,
                    'third_order_no' => $orderNo,
                    'reject_reason' => null,
                    'mt4_return_status' => '',
                    'created_by' => 'test',
                    'updated_by' => 'test',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        foreach ([['ACC-METRIC-C-1', 75.25], ['ACC-METRIC-C-2', 24.75]] as [$uniqueId, $amount]) {
            DB::table('commission_records')->updateOrInsert(
                ['unique_id' => $uniqueId],
                [
                    'agent_id' => $agentId,
                    'parent_id' => 0,
                    'agent_profit' => 0,
                    'agent_volume' => 0,
                    'equity_value' => 0,
                    'equity_diff' => 0,
                    'settle_cycle' => 1,
                    'mt4_order_id' => 0,
                    'date_range' => '2026-06-01 - 2026-06-01',
                    'settle_status' => 2,
                    'fee' => 0,
                    'swap' => 0,
                    'commission_amount' => $amount,
                    'returned_amount' => $amount,
                    'deposit' => 0,
                    'real_amount' => $amount,
                    'data_type' => 'mainData',
                    'manual_reason' => '',
                    'remarks' => 'front account metrics regression test',
                    'created_by' => 'test',
                    'updated_by' => 'test',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/profile');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.group_name', 'Regression Metrics Group')
            ->assertJsonPath('data.commission_rate', 73);

        $comparisonRows = $response->json('data.funds_comparison');
        $this->assertSame(
            ['total_deposit', 'total_rebate', 'total_withdraw', 'total_funds', 'equity'],
            array_column($comparisonRows, 'key')
        );
        $comparisonByKey = collect($comparisonRows)->keyBy('key');
        $this->assertSame('front.total_deposit', $comparisonByKey['total_deposit']['label']);
        $this->assertEquals(1501.00, (float) $comparisonByKey['total_deposit']['value']);
        $this->assertEquals(100.00, (float) $comparisonByKey['total_rebate']['value']);
        $this->assertEquals(250.00, (float) $comparisonByKey['total_withdraw']['value']);
        $this->assertEquals(4567.89, (float) $comparisonByKey['total_funds']['value']);
        $this->assertEquals(4321.09, (float) $comparisonByKey['equity']['value']);

        $genderProfile = $response->json('data.customer_gender_profile');
        $this->assertSame(1, $genderProfile['male']['count']);
        $this->assertSame(1, $genderProfile['female']['count']);
        $this->assertSame(1, $genderProfile['unknown']['count']);
        $this->assertEquals(33.33, (float) $genderProfile['male']['ratio']);
        $this->assertEquals(33.33, (float) $genderProfile['female']['ratio']);
        $this->assertEquals(33.33, (float) $genderProfile['unknown']['ratio']);
    }

    public function test_layui_dashboard_route_url_function_not_commented_out(): void
    {
        $source = $this->publicScript('front/layui/dashboard/index.js');

        $this->assertDoesNotMatchRegularExpression(
            '/\/\/[^\r\n]*function\s+routeUrl/',
            $source,
            'Layui dashboard routeUrl function must not be swallowed by a line comment.'
        );
        $this->assertMatchesRegularExpression(
            '/(^|\r?\n)\s*function\s+routeUrl\s*\(/',
            $source,
            'Layui dashboard must keep an executable routeUrl function.'
        );
    }

    public function test_dashboard_switch_control_accessibility_and_sound_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/dashboard/index_v2.blade.php')) ?: '';
        $dashboard = $this->publicScript('front/layui/dashboard/index.js');

        $this->assertStringContainsString('.dashboard-switch-control.is-open .dashboard-option-menu', $blade);
        $this->assertStringContainsString('aria-haspopup="menu"', $blade);
        $this->assertStringContainsString('aria-expanded="false"', $blade);
        foreach (['style', 'locale', 'sound'] as $switch) {
            $this->assertStringContainsString('data-dashboard-switch="' . $switch . '"', $blade, 'Dashboard switch control is missing stable switch key: ' . $switch);
            $this->assertStringContainsString('data-dashboard-switch="' . $switch . '"', $v2Blade, 'Dashboard v2 switch control is missing stable switch key: ' . $switch);
        }
        $this->assertGreaterThanOrEqual(3, substr_count($blade, 'role="button"'), 'Dashboard switch controls must expose button semantics.');
        $this->assertGreaterThanOrEqual(3, substr_count($v2Blade, 'role="button"'), 'Dashboard v2 switch controls must expose button semantics.');
        $this->assertGreaterThanOrEqual(7, substr_count($blade, 'role="menuitemradio"'), 'Dashboard switch options must expose radio menu semantics.');
        $this->assertGreaterThanOrEqual(7, substr_count($v2Blade, 'role="menuitemradio"'), 'Dashboard v2 switch options must expose radio menu semantics.');
        $this->assertStringContainsString('data-dashboard-sound-option="on"', $blade);
        $this->assertStringContainsString('data-dashboard-sound-option="off"', $blade);
        $this->assertStringContainsString('data-dashboard-style-current', $blade);
        foreach ([$blade, $v2Blade] as $dashboardBlade) {
            $this->assertStringContainsString("@section('frame-theme-picker-provided', '1')", $dashboardBlade);
            $this->assertStringContainsString("@include('partials.theme-picker', ['themePickerCompact' => true])", $dashboardBlade);
            $this->assertStringNotContainsString("'themePickerId' =>", $dashboardBlade);
            $this->assertStringNotContainsString('data-dashboard-theme-option', $dashboardBlade);
            $this->assertStringNotContainsString('data-dashboard-theme-current', $dashboardBlade);
            $this->assertStringNotContainsString('data-dashboard-switch="theme"', $dashboardBlade);
            $this->assertStringNotContainsString('dashboard-theme-swatch', $dashboardBlade);
            $this->assertStringNotContainsString('data-lucide="palette"', $dashboardBlade);
        }
        $this->assertStringContainsString('data-dashboard-locale-current', $blade);
        $this->assertStringContainsString('data-dashboard-sound-current', $blade);
        $this->assertStringContainsString('.dashboard-switch-current', $blade);
        $this->assertMatchesRegularExpression('/\.dashboard-control-panel\s*\{[^}]*overflow:\s*visible/s', $blade);
        $this->assertMatchesRegularExpression('/\.dashboard-actions\s*\{[^}]*position:\s*relative;[^}]*overflow:\s*visible/s', $blade);
        $this->assertMatchesRegularExpression('/\.dashboard-control-panel\s+\.crm-theme-picker\s*\{[^}]*height:\s*38px/s', $blade);
        $this->assertStringNotContainsString('.dashboard-control-panel .crm-theme-picker select', $blade);
        $this->assertStringContainsString('.crm-preference-trigger {', file_get_contents(public_path('css/common/crm-themes.css')) ?: '');
        $this->assertMatchesRegularExpression('/\.dashboard-switch-control\.is-open\s*\{[^}]*z-index:\s*1200/s', $blade);
        $this->assertMatchesRegularExpression('/\.dashboard-option-menu\s*\{[^}]*z-index:\s*1201/s', $blade);

        $this->assertStringContainsString("function (event)", $dashboard);
        $this->assertStringContainsString("event.preventDefault();", $dashboard);
        $this->assertStringContainsString("event.stopPropagation();", $dashboard);
        $this->assertStringContainsString("toggleDashboardSwitchMenu", $dashboard);
        $this->assertStringContainsString("closeDashboardSwitchMenus", $dashboard);
        $this->assertStringContainsString("event.key === 'Escape'", $dashboard);
        $this->assertStringContainsString("keydown.dashboardSwitchMenu", $dashboard);
        $this->assertStringContainsString("event.key === 'Enter' || event.key === ' '", $dashboard);
        $this->assertStringContainsString("attr('aria-expanded', 'true')", $dashboard);
        $this->assertStringContainsString("attr('aria-expanded', 'false')", $dashboard);
        $this->assertStringContainsString("renderDashboardSwitchLabels();", $dashboard);
        $this->assertStringContainsString("toggleClass('is-active'", $dashboard);
        $this->assertStringContainsString("attr('aria-checked'", $dashboard);
        $this->assertStringContainsString("var sound = localStorage.getItem('crm_sound_enabled') || localStorage.getItem('front_sound_enabled') || 'on';", $dashboard);
        $this->assertStringContainsString("function applyDashboardSound(nextSound)", $dashboard);
        $this->assertStringContainsString("localStorage.setItem('crm_sound_enabled', nextSound);", $dashboard);
        $this->assertStringContainsString("localStorage.setItem('front_sound_enabled', nextSound);", $dashboard);
        $this->assertStringContainsString("[data-dashboard-sound-option], [data-dashboard-sound]", $dashboard);
        $this->assertStringContainsString("var style = localStorage.getItem('crm_ui_style') || localStorage.getItem('front_ui_style') || 'layui';", $dashboard);
        $this->assertStringNotContainsString("var style = 'layui';", $dashboard);
        $this->assertStringNotContainsString("localStorage.setItem('crm_ui_style', style);\n        localStorage.setItem('front_ui_style', style);", $this->sourceBetween($dashboard, 'function bindDashboardSwitches', 'function toggleDashboardSwitchMenu'));
        $this->assertStringContainsString("isEn ? 'Layui Classic' : 'Layui \u{7ECF}\u{5178}'", $dashboard);
        $this->assertStringContainsString("isEn ? 'CrmUI Focus' : 'CrmUI \u{4E13}\u{6CE8}'", $dashboard);
        $this->assertStringContainsString("isEn ? 'Naive Clean' : 'Naive \u{6E05}\u{723D}'", $dashboard);
        $this->assertStringContainsString('data-dashboard-style-option="naive"', $blade);
        $this->assertStringContainsString("$('[data-dashboard-style-current]').text", $dashboard);
        foreach ([
            'data-dashboard-theme-option',
            'data-dashboard-theme-current',
            'applyDashboardTheme',
            'currentDashboardTheme',
            'themeText',
            "localStorage.setItem('front_theme'",
            "localStorage.setItem('crm_theme'",
            'window.parent.CrmTheme',
        ] as $privateThemeState) {
            $this->assertStringNotContainsString($privateThemeState, $dashboard);
        }
        $this->assertStringContainsString("window.addEventListener('crm:theme-change'", $dashboard);
        $this->assertStringContainsString("$('[data-dashboard-locale-current]').text", $dashboard);
        $this->assertStringContainsString("$('[data-dashboard-sound-current]').text", $dashboard);
        $this->assertStringNotContainsString('缂佸繐鍚€鐢啫鐪?', $dashboard);
        $this->assertStringNotContainsString('濞撳懐鍩ョ敮鍐ㄧ湰', $dashboard);
        $this->assertStringNotContainsString('閳?/span', $blade);
        $this->assertStringNotContainsString('娑?/span', $blade);
    }

    public function test_dashboard_visible_text_uses_language_keys_not_garbled_fallbacks(): void
    {
        $combined = implode("\n", [
            file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '',
            file_get_contents(resource_path('front/layui/dashboard/index_v2.blade.php')) ?: '',
        ]);

        foreach ([
            '缁忓吀',
            '涓撴敞',
            '娓呯埥',
            '涓枃',
            '澹伴煶',
            '寮€鍚',
            '鍏抽棴',
            '璧勯噾',
            '瀹㈡埛',
            '璁㈠崟',
            '杩斾剑',
        ] as $garbledText) {
            $this->assertStringNotContainsString($garbledText, $combined, 'Dashboard visible text must not contain mojibake: ' . $garbledText);
        }

        foreach ([
            "{{ __('front.layout_classic') }}",
            "{{ __('front.layout_crmui') }}",
            "{{ __('front.layout_naive') }}",
            "{{ __('front.sound_mode') }}",
            "{{ __('front.sound_on') }}",
            "{{ __('front.sound_off') }}",
            "{{ __('front.funds_chart') }}",
            "{{ __('front.network_chart') }}",
            "{{ __('front.order_chart') }}",
            "{{ __('front.commission_chart') }}",
        ] as $languageKey) {
            $this->assertStringContainsString($languageKey, $combined, 'Dashboard must render visible text through language key: ' . $languageKey);
        }

        $this->assertSame(2, substr_count($combined, "{{ app()->getLocale() === 'en' ? 'EN' : 'ZH' }}"));
        $this->assertSame(2, substr_count($combined, 'data-dashboard-locale-option="zh-CN"><span>ZH</span>'));
    }

    public function test_dashboard_crmui_route_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $dashboard = $this->publicScript('front/layui/dashboard/index.js');

        $this->assertStringContainsString('id="crm-dashboard-routes"', $blade);
        $this->assertStringContainsString("route('front_crmui_app', ['path' => 'dashboard'])", $blade);
        $this->assertStringContainsString("route('front_naive_app', ['path' => 'dashboard'])", $blade);
        $this->assertStringContainsString("data-layui-page=\"dashboard/index\"", $blade);
        $this->assertStringContainsString("var dashboardRoutes = readJsonConfig('crm-dashboard-routes') || window.CrmDashboardRoutes || {};", $dashboard);
        $this->assertStringContainsString("routeUrl('front_crmui_app', {path: 'dashboard'}, dashboardRoutes.crmuiDashboard || '/front-crmui/dashboard')", $dashboard);
        $this->assertStringContainsString("routeUrl('front_naive_app', {path: 'dashboard'}, dashboardRoutes.naiveDashboard || '/front-naive/dashboard')", $dashboard);
    }

    public function test_dashboard_echarts_polished_visual_options(): void
    {
        $blade = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $dashboard = $this->publicScript('front/layui/dashboard/index.js');
        $themes = file_get_contents(public_path('css/common/crm-themes.css')) ?: '';

        foreach ([
            'itemStyle: {borderRadius: [8, 8, 2, 2]}',
            'label: {show: true',
            'tooltip: {trigger: \'item\', confine: true',
            'roseType: \'radius\'',
            'labelLine: {smooth: true',
            'axisLabel: {color:',
            'splitLine: {lineStyle:',
            'animationDuration: 450',
        ] as $needle) {
            $this->assertStringContainsString($needle, $dashboard, 'Dashboard ECharts option missing polished visual setting: ' . $needle);
        }

        // 2026-08-28 紧凑化：图表高度由 250/260px 收紧为 212/220px。
        $this->assertStringContainsString('.dashboard-chart { width: 100%; height: 212px;', $blade);
        $this->assertStringContainsString('.dashboard-chart-card.is-funds .dashboard-chart { height: 220px;', $blade);
        $this->assertStringContainsString('.dashboard-chart-head span { font-weight: 600;', $blade);
        $semanticFillRules = $this->sourceBetween($themes, '/* Semantic states:', 'html[data-crm-theme] .crm-dashboard-page .dashboard-value {');
        foreach (['.dashboard-value.red', '.dashboard-value.orange', '.dashboard-value.green', '.dashboard-value.blue', '.dashboard-value.cyan'] as $selector) {
            $this->assertStringNotContainsString($selector, $semanticFillRules, 'Dashboard KPI values must not be styled as filled badge/button semantic states: ' . $selector);
        }
        $this->assertStringContainsString('.crm-dashboard-page .dashboard-value.green', $themes);
        $this->assertStringContainsString('background: transparent !important;', $themes);
    }

    public function test_front_theme_skins_expose_flat_visual_tokens(): void
    {
        $css = file_get_contents(public_path('css/front/style.css')) ?: '';
        $blade = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $themeBlocks = [
            'root' => $this->sourceBetween($css, ':root {', 'html[data-front-theme="dark"]'),
            'dark' => $this->sourceBetween($css, 'html[data-front-theme="dark"] {', 'html[data-front-theme="sea"]'),
            'sea' => $this->sourceBetween($css, 'html[data-front-theme="sea"] {', 'html[data-front-theme="warm"]'),
            'warm' => $this->sourceBetween($css, 'html[data-front-theme="warm"] {', 'html[data-front-theme="contrast"]'),
            'contrast' => $this->sourceBetween($css, 'html[data-front-theme="contrast"] {', 'html[data-front-theme] body'),
        ];

        foreach ($themeBlocks as $theme => $block) {
            foreach ([
                '--front-panel',
                '--front-line',
                '--front-side',
                '--front-soft-accent',
                '--front-chip-bg',
                '--front-focus-ring',
            ] as $token) {
                $this->assertStringContainsString($token, $block, $theme . ' skin is missing polished visual token: ' . $token);
            }
        }

        foreach ([
            'background: var(--front-side);',
            'background: var(--front-panel);',
            'background: var(--front-soft-accent);',
            'box-shadow: 0 0 0 3px var(--front-focus-ring',
            'background: var(--front-chip-bg',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blade, 'Dashboard control panel must consume skin visual token: ' . $needle);
        }

        foreach (['--front-hero-gradient', '--front-control-gradient', '--front-panel-glass'] as $obsoleteToken) {
            $this->assertStringNotContainsString($obsoleteToken, $css);
            $this->assertStringNotContainsString($obsoleteToken, $blade);
        }
    }

    public function test_dashboards_do_not_render_legacy_theme_icons(): void
    {
        $blade = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/dashboard/index_v2.blade.php')) ?: '';

        foreach ([$blade, $v2Blade] as $dashboardBlade) {
            $this->assertStringContainsString("@include('partials.theme-picker', ['themePickerCompact' => true])", $dashboardBlade);
            $this->assertStringNotContainsString('data-dashboard-theme-option', $dashboardBlade);
            $this->assertStringNotContainsString('dashboard-theme-swatch', $dashboardBlade);
            $this->assertStringNotContainsString('data-lucide="palette"', $dashboardBlade);
        }
    }

    public function test_dashboard_style_options_use_lucide_and_include_naive(): void
    {
        $blade = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/dashboard/index_v2.blade.php')) ?: '';
        $dashboard = $this->publicScript('front/layui/dashboard/index.js');

        $this->assertStringContainsString('data-dashboard-style-option="layui"><i data-lucide="wallet-cards"></i><span>{{ __(\'front.layout_classic\') }}</span>', $blade);
        $this->assertStringContainsString('data-dashboard-style-option="crmui"><i data-lucide="gauge"></i><span>{{ __(\'front.layout_crmui\') }}</span>', $blade);
        $this->assertStringContainsString('data-dashboard-style-option="naive"><i data-lucide="sparkles"></i><span>{{ __(\'front.layout_naive\') }}</span>', $blade);
        $this->assertStringContainsString('data-dashboard-style-option="crmui"', $v2Blade);
        $this->assertStringContainsString('data-dashboard-style-option="naive"', $v2Blade);
        $this->assertStringContainsString('crmuiDashboard', $v2Blade);
        $this->assertStringContainsString('naiveDashboard', $v2Blade);
        $this->assertStringContainsString("isEn ? 'Layui Classic' : 'Layui \u{7ECF}\u{5178}'", $dashboard);
        $this->assertStringContainsString("isEn ? 'CrmUI Focus' : 'CrmUI \u{4E13}\u{6CE8}'", $dashboard);
        $this->assertStringContainsString("isEn ? 'Naive Clean' : 'Naive \u{6E05}\u{723D}'", $dashboard);
        $this->assertStringContainsString("styleShortLabel(currentStyle)", $dashboard);
    }

    public function test_front_dashboard_shell_and_frame_expose_style_options(): void
    {
        $shellHtml = $this->get('/front/dashboard')->assertOk()->getContent();
        $frameHtml = $this->get('/front/dashboard?frame=1')->assertOk()->getContent();

        foreach (['layui', 'crmui', 'naive'] as $style) {
            $this->assertStringContainsString('data-style="' . $style . '"', $shellHtml);
            $this->assertStringContainsString('data-dashboard-style-option="' . $style . '"', $frameHtml);
        }

        $this->assertStringContainsString('id="crm-dashboard-routes"', $frameHtml);
        $this->assertStringContainsString('front-crmui\/dashboard', $frameHtml);
        $this->assertStringContainsString('front-naive\/dashboard', $frameHtml);
    }

    public function test_front_layui_core_pages_render_frame_smoke_contract(): void
    {
        $pages = [
            '/front/dashboard?frame=1' => ['root' => 'dashboard-page', 'needles' => ['dashboard-control-panel', 'data-dashboard-style-option="naive"', '/js/apps/front/layui/pages.js']],
            '/front/profile?frame=1' => ['root' => 'crm-profile-shell', 'needles' => ['data-upload-field="avatar"', 'data-layui-page="profile/index"']],
            '/front/account/info?frame=1' => ['root' => 'id="frontModulePage"', 'needles' => ['data-api="/api/front/account/profile"', 'data-comparison-table="funds_comparison"', 'accountFundsChart']],
            '/front/deposit?frame=1' => ['root' => 'deposit-page', 'needles' => ['id="depositChannelList"', 'id="depositHistorySummary"', 'data-layui-page="deposit/index"']],
            '/front/flow?frame=1' => ['root' => 'flow-page', 'needles' => ['lay-filter="frontFlowTabs"', 'name="withdraw_source"', 'flow-table-wrap']],
            '/front/position/summary?frame=1' => ['root' => 'id="frontModulePage"', 'needles' => ['data-api="/api/front/positions/summary"', 'data-dynamic-options="symbols"', 'id="moduleChain"']],
            '/front/order/open?frame=1' => ['root' => 'id="frontModulePage"', 'needles' => ['data-api="/api/front/orders/open"', 'data-dynamic-options="symbols"', 'module-link-order']],
            '/front/order/closed?frame=1' => ['root' => 'id="frontModulePage"', 'needles' => ['data-api="/api/front/orders/closed"', 'data-dynamic-options="symbols"', 'front.force_close']],
            '/front/agent/customers?frame=1' => ['root' => 'id="frontModulePage"', 'needles' => ['data-api="/api/front/agents/direct-customers"', 'front.commission_transfer', 'front.group_change']],
            '/front/commission/realtime?frame=1' => ['root' => 'id="frontModulePage"', 'needles' => ['data-api="/api/front/commissions/realtime"', 'current_commission_amount', 'rebate_ratio']],
            '/front/news?frame=1' => ['root' => 'id="frontModulePage"', 'needles' => ['data-api="/api/front/news"', 'data-timeline="news"', 'id="moduleNewsTimeline"']],
        ];

        foreach ($pages as $url => $expectation) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString($expectation['root'], $html, 'Front frame page did not render the expected page root: ' . $url);
            $this->assertStringContainsString('/js/shared/ajax.js', $html, 'Front frame page did not load shared Ajax wrapper: ' . $url);
            $this->assertStringNotContainsString('front-plain.js', $html, 'Front frame page must not load legacy front-plain script: ' . $url);
            $this->assertStringNotContainsString('2026060502', $html, 'Front frame page must not load the broken front-plain cache version: ' . $url);

            foreach ($expectation['needles'] as $needle) {
                $this->assertStringContainsString($needle, $html, 'Front frame page missing required rendered marker ' . $needle . ': ' . $url);
            }
        }
    }

    public function test_front_naive_compatibility_route_renders_dashboard_shell(): void
    {
        $html = $this->get('/front-naive/dashboard')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<body\b[^>]*>/i', $html, $bodyMatches));
        $bodyTag = $bodyMatches[0];

        $this->assertStringContainsString('data-crmui-page="front.dashboard"', $html);
        $this->assertStringContainsString('data-ui-family="naive"', $bodyTag);
        $this->assertStringNotContainsString('data-ui-family="crmui"', $bodyTag);
        $this->assertStringContainsString('data-crmui-render-family="naive"', $bodyTag);
        $this->assertStringContainsString('/css/crmui/naive.css', $html);
        $this->assertStringNotContainsString('front-plain.js', $html);
        $this->assertStringNotContainsString('2026060502', $html);
    }

    public function test_front_crmui_and_naive_core_pages_render_shared_data_shells(): void
    {
        $pages = [
            'dashboard' => ['key' => 'front.dashboard', 'needles' => ['data-api-url="http://localhost/api/front/dashboard"', '/js/shared/ajax.js']],
            'deposit' => ['key' => 'front.deposit', 'needles' => ['data-options-url="http://localhost/api/front/deposits/form-options"', 'data-crmui-channel-remarks']],
            'commission/realtime' => ['key' => 'front.commission_realtime', 'needles' => ['data-api-url="http://localhost/api/front/commissions/realtime"', 'current_commission_amount']],
            'news' => ['key' => 'front.news', 'needles' => ['data-api-url="http://localhost/api/front/news"', 'data-timeline="news"', 'data-crmui-news-timeline', 'id="crmuiNewsTimeline"']],
        ];

        foreach (['crmui' => '/front-crmui/', 'naive' => '/front-naive/'] as $family => $prefix) {
            foreach ($pages as $path => $expectation) {
                $html = $this->get($prefix . $path)->assertOk()->getContent();

                $this->assertStringContainsString('data-crmui-page="' . $expectation['key'] . '"', $html, $family . ' page shell missing page key: ' . $path);
                $this->assertStringContainsString('data-ui-family="' . $family . '"', $html, $family . ' page shell missing active UI family: ' . $path);
                $this->assertStringContainsString('data-crmui-render-family="' . $family . '"', $html, $family . ' page shell missing render variant: ' . $path);
                $this->assertStringContainsString('/js/shared/ajax.js', $html, $family . ' page shell missing shared Ajax wrapper: ' . $path);
                $this->assertStringNotContainsString('front-plain.js', $html, $family . ' page shell must not load legacy front-plain script: ' . $path);
                $this->assertStringNotContainsString('2026060502', $html, $family . ' page shell must not load broken front-plain version: ' . $path);

                if ($family === 'naive') {
                    $this->assertStringContainsString('/css/crmui/naive.css', $html, 'Naive page shell missing visual override CSS: ' . $path);
                }

                foreach ($expectation['needles'] as $needle) {
                    $this->assertStringContainsString($needle, $html, $family . ' page shell missing marker ' . $needle . ': ' . $path);
                }
            }
        }
    }

    public function test_front_crmui_and_naive_shells_expose_ui_family_switcher_and_preserve_paths(): void
    {
        $frontScript = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $frontCss = file_get_contents(public_path('css/crmui/front.css')) ?: '';

        foreach ([
            'crmui' => '/front-crmui/commission/history',
            'naive' => '/front-naive/commission/history',
        ] as $family => $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-crmui-ui-switch', $html, $family . ' shell must expose a UI family switcher.');
            $this->assertStringContainsString('data-ui-current-family="' . $family . '"', $html, $family . ' switcher must expose the current family.');
            $this->assertStringContainsString('data-crmui-page-path="commission/history"', $html, $family . ' shell must expose the current canonical page path.');

            foreach (['layui', 'crmui', 'naive'] as $target) {
                $this->assertStringContainsString('data-ui-target="' . $target . '"', $html, $family . ' shell missing UI target: ' . $target);
                $this->assertStringContainsString('data-crmui-ui-target="' . $target . '"', $html, $family . ' shell missing CrmUI target: ' . $target);
            }

            $this->assertStringContainsString('href="http://localhost/front-' . $family . '/deposit"', $html, $family . ' sidebar navigation must stay inside the active UI family.');
        }

        foreach ([
            'function switchUiFamily(targetFamily)',
            "localStorage.setItem('crm_ui_style', targetFamily);",
            "localStorage.setItem('front_ui_style', targetFamily);",
            "return '/front/' + pagePath;",
            "return '/front-naive/' + pagePath;",
            "return '/front-crmui/' + pagePath;",
            "[data-crmui-ui-target]",
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontScript, 'CrmUI front shell script missing UI switch behavior: ' . $needle);
        }

        foreach ([
            '.crmui-ui-switch',
            '.crmui-ui-switch-button',
            '.crmui-ui-switch-button.is-active',
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontCss, 'CrmUI front stylesheet missing switcher styling: ' . $needle);
        }
    }

    public function test_front_crmui_and_naive_direct_customer_rows_use_modal_actions(): void
    {
        $frontScript = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';

        foreach ([
            'crmui' => '/front-crmui/agent/customers',
            'naive' => '/front-naive/agent/customers',
        ] as $family => $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-api-url="http://localhost/api/front/agents/direct-customers"', $html, $family . ' customer page must use the direct customers API.');
            $this->assertStringContainsString('data-crmui-row-action="commission_transfer"', $html, $family . ' customer page missing commission transfer row modal.');
            $this->assertStringContainsString('data-crmui-row-action="group_change"', $html, $family . ' customer page missing group change row modal.');
            $this->assertStringContainsString('data-action-url="http://localhost/api/front/commissions/transfers"', $html, $family . ' commission transfer must post to the REST transfer API.');
            $this->assertStringContainsString('data-action-url="http://localhost/api/front/agents/group-change-applications"', $html, $family . ' group change must post to the REST group-change API.');
            $this->assertStringContainsString('data-record-key="user_id"', $html, $family . ' modal actions must use the selected customer user_id.');
            $this->assertStringContainsString('data-payload-name="sub_agent_id"', $html, $family . ' transfer modal must keep the legacy sub_agent_id payload contract.');
            $this->assertStringContainsString('data-payload-name="target_user_id"', $html, $family . ' group-change modal must keep the target_user_id payload contract.');
            $this->assertStringContainsString('"name":"sub_agent_id","label":"Sub-agent ID","type":"hidden"', $html, $family . ' transfer modal must carry sub_agent_id as a hidden field.');
            $this->assertStringContainsString('"name":"target_user_preview","label":"Target user ID","type":"readonly"', $html, $family . ' transfer modal must show the selected customer id.');
            $this->assertStringContainsString('"source":"user_id"', $html, $family . ' readonly target preview must read from user_id.');
            $this->assertStringContainsString('"name":"amount","label":"Amount","type":"number"', $html, $family . ' transfer modal must request an amount.');
            $this->assertStringContainsString('"name":"password","label":"Password","type":"password"', $html, $family . ' transfer modal must request the fund password.');
            $this->assertStringContainsString('"name":"target_user_id","label":"Target user ID","type":"hidden"', $html, $family . ' group-change modal must carry target_user_id as a hidden field.');
            $this->assertStringContainsString('"name":"target_user_preview","label":"Target user ID","type":"readonly"', $html, $family . ' group-change modal must show the selected customer id.');
            $this->assertStringContainsString('"name":"new_group_id","label":"New group ID","type":"select"', $html, $family . ' group-change modal must request a new group.');
            $this->assertStringContainsString('"dynamicOptions":"available_groups"', $html, $family . ' group-change modal must load real group options from the direct customer response.');
        }

        foreach ([
            'function pageDynamicOptions($page, key)',
            "field.dynamicOptions",
            "data-field-config",
            "field.source || field.name",
            "crmui-readonly-field",
            "return '<input name=\"' + escapeHtml(field.name) + '\" type=\"hidden\" value=\"' + escapeHtml(value) + '\">';",
            "payload[payloadName] === ''",
            "if (/\\/commissions\\/transfers(?:\\?|$)/i.test(url))",
            "headers: actionHeaders",
            "'Idempotency-Key': key",
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontScript, 'CrmUI row modal script missing behavior: ' . $needle);
        }
    }

    public function test_group_change_lists_label_target_group_as_name_not_id(): void
    {
        $layuiBlade = file_get_contents(resource_path('front/layui/agent/group-change.blade.php')) ?: '';
        $controller = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';
        $crmuiController = file_get_contents(app_path('Http/Controllers/CrmUi/Front/PageController.php')) ?: '';
        $groupChangeList = $this->sourceBetween($controller, 'public function groupChangeList(Request $request): JsonResponse', 'public function groupChange(Request $request): JsonResponse');

        $this->assertStringContainsString("'trans_type_gid' => trim((string) (\$log->group_name ?? ''))", $groupChangeList, 'Group-change API must expose the real group name and never fall back to a numeric ID.');
        $this->assertStringContainsString("['key' => 'trans_type_gid', 'label' => 'front.group_name']", $layuiBlade, 'Layui group-change list must label the target group as a name, not an ID.');
        $this->assertStringContainsString("['key' => 'trans_type_gid', 'label' => 'group_name']", $crmuiController, 'CrmUI/Naive group-change list must label the target group as a name, not an ID.');

        foreach ([
            '/front/agent/group-change?frame=1' => 'Group Name',
            '/front-crmui/agent/group-change' => 'Group name',
            '/front-naive/agent/group-change' => 'Group name',
        ] as $url => $expectedLabel) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString($expectedLabel, $html, $url . ' must render the target group-name label.');
            $this->assertStringNotContainsString('Group ID</th>', $html, $url . ' must not expose the target group column as an ID label.');
        }
    }

    public function test_front_group_name_fields_do_not_fall_back_to_group_ids(): void
    {
        $accountController = file_get_contents(app_path('Http/Controllers/Front/AccountController.php')) ?: '';
        $agentController = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';

        $this->assertStringNotContainsString("group_name' => \$userInfo->groupConfig->name ?? (string) \$userInfo->group_id", $accountController);
        $this->assertStringNotContainsString("\$html .= \$this->legacyDetailItem('交易组', \$user->groupConfig->name ?? \$user->group_id);", $agentController);
        $this->assertStringNotContainsString("\$groupName = (string) (\$user->groupConfig->name ?? \$user->group_id);", $agentController);
        $this->assertStringNotContainsString("'trans_type_gid' => \$log->group_name ?: \$log->group_id", $agentController);
    }

    public function test_theme_catalog_applies_to_naive_front_shell(): void
    {
        $partial = file_get_contents(resource_path('views/partials/theme-assets.blade.php')) ?: '';

        $this->assertStringContainsString('body[data-ui-family="layui"][data-visual-direction="c"]', $partial);
        $this->assertStringContainsString('body[data-ui-family="crmui"][data-visual-direction="c"]', $partial);
        $this->assertStringContainsString('body[data-ui-family="naive"][data-visual-direction="c"]', $partial);

        $html = $this->get('/front-naive/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('data-ui-family="naive"', $html);
        $this->assertStringContainsString('body[data-ui-family="naive"][data-visual-direction="c"]', $html);
    }

    public function test_front_shells_scope_theme_assets_by_ui_family(): void
    {
        $contracts = [
            resource_path('views/front/layouts/app.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            resource_path('front/layui/layouts/app.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            resource_path('front/layui/auth/login.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            resource_path('front/layui/auth/login_v2.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            resource_path('front/layui/auth/register.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            resource_path('front/layui/auth/register_v2.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            resource_path('front/layui/auth/forgot-password.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            resource_path('front/layui/auth/big-number-login.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            resource_path('front/layui/legacy-big-agent/layout.blade.php') => ['data-ui-family="layui"', 'data-ui-surface="big-agent"', 'data-visual-direction="c"'],
            resource_path('front/crmui/layouts/app.blade.php') => ['data-ui-family="{{ $renderFamily }}"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            // auth 布局由 crmui 与 naive 两个家族共享，主题作用域必须跟随服务端 renderFamily 动态输出。
            resource_path('front/crmui/layouts/auth.blade.php') => ['data-ui-family="{{ $renderFamily }}"', 'data-ui-surface="front"', 'data-visual-direction="c"'],
            // big-agent 布局同样由 crmui 与 naive 共享（front-naive/big-agent 路由），主题作用域动态输出。
            resource_path('front/crmui/big-agent/layout.blade.php') => ['data-ui-family="{{ $renderFamily }}"', 'data-ui-surface="big-agent"', 'data-visual-direction="c"'],
        ];

        foreach ($contracts as $file => $needles) {
            $content = file_get_contents($file) ?: '';
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $content, basename($file) . ' missing theme scope marker: ' . $needle);
            }
        }
    }

    public function test_naive_front_css_consumes_theme_tokens(): void
    {
        $css = file_get_contents(public_path('css/crmui/naive.css')) ?: '';

        foreach ([
            'background: var(--crmui-surface);',
            'background: var(--crmui-primary);',
            'color: var(--crmui-primary);',
            'background: color-mix(in srgb, var(--crmui-primary) 12%, var(--crmui-surface));',
            // 2026-08-28 皮肤清爽化：顶栏由「92% 半透明 + backdrop blur」改为纯净不透明 surface，
            // 去掉毛玻璃后半透明会在页面底色上显脏；此处仍是主题 token，不是固定色。
            'background: var(--crmui-surface-2);',
            'color: var(--crmui-muted);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, 'Naive CSS must use theme tokens instead of fixed colors: ' . $needle);
        }
    }

    public function test_module_page_echarts_polished_options(): void
    {
        $layui = $this->publicScript('front/layui/module-page.js');

        foreach ([
            'tooltip: {trigger: \'item\', confine: true',
            'label: {show: true',
            'labelLine: {smooth: true',
            'itemStyle: {borderRadius: [8, 8, 2, 2]}',
            'axisLabel: {color:',
            'splitLine: {lineStyle:',
            'animationDuration: 450',
        ] as $needle) {
            $this->assertStringContainsString($needle, $layui, 'Layui module ECharts option missing polished setting: ' . $needle);
        }
    }

    public function test_front_assets_never_use_radar_series(): void
    {
        $sources = [
            'public/js/apps/front/layui/module-page.js' => $this->publicScript('front/layui/module-page.js'),
            'resources/front/layui/commission/history.blade.php' => file_get_contents(resource_path('front/layui/commission/history.blade.php')) ?: '',
        ];

        foreach ($sources as $file => $source) {
            $this->assertStringNotContainsString("'radar'", $source, $file . ' must not use radar because echarts.common.min.js does not include series.radar.');
            $this->assertStringNotContainsString('"radar"', $source, $file . ' must not use radar because echarts.common.min.js does not include series.radar.');
            $this->assertStringNotContainsString('chart_radar', $source, $file . ' must not expose an unsupported radar chart option.');
            $this->assertStringNotContainsString('type: \'radar\'', $source, $file . ' must not render unsupported radar series.');
        }
    }

    public function test_route_url_and_page_prefix_functions_executable(): void
    {
        $dashboard = $this->publicScript('front/layui/dashboard/index.js');
        $layout = $this->publicScript('front/layui/layout.js');

        $this->assertFunctionDeclarationIsExecutable($dashboard, 'routeUrl', 'dashboard routeUrl');
        $this->assertFunctionDeclarationIsExecutable($layout, 'routeUrl', 'layout routeUrl');
        $this->assertFunctionDeclarationIsExecutable($layout, 'pagePrefixFromUrl', 'layout pagePrefixFromUrl');
    }

    public function test_blade_first_screen_translation_keys_exist(): void
    {
        $usedKeys = [];
        $paths = [
            resource_path('front'),
            resource_path('admin'),
            resource_path('views'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $content = file_get_contents($file->getPathname()) ?: '';
                preg_match_all('/__\(\s*[\'"]([a-z]+\\.[A-Za-z0-9_]+)[\'"]/', $content, $matches);
                foreach ($matches[1] as $key) {
                    $usedKeys[$key][] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        foreach (['zh-CN', 'en'] as $locale) {
            $definedKeys = $this->languageKeys($locale);
            $missing = array_values(array_diff(array_keys($usedKeys), $definedKeys));
            sort($missing);

            $this->assertSame([], $missing, $locale . ' 语言包缺少 Blade 首屏翻译 key，页面会直接显示 key。');
        }
    }

    public function test_legacy_direct_customer_translation_values_preserve_column_semantics(): void
    {
        $expected = [
            'zh-CN' => [
                'systemlanguage.total' => '总计',
                'systemlanguageadmin.Registration_time' => '开户时间',
                'systemlanguageadmin.account_deposit_moneny' => '入金',
                'systemlanguageadmin.account_withdrawal_moneny' => '出金',
                'systemlanguageadmin.position_summary_net_deposit' => '净入金',
                'systemlanguageadmin.position_summary_total_comm' => '手续费',
                'systemlanguageadmin.position_summary_total_money' => '盈亏',
            ],
            'en' => [
                'systemlanguage.total' => 'Total',
                'systemlanguageadmin.Registration_time' => 'Registration Time',
                'systemlanguageadmin.account_deposit_moneny' => 'Deposit',
                'systemlanguageadmin.account_withdrawal_moneny' => 'Withdrawal',
                'systemlanguageadmin.position_summary_net_deposit' => 'Net Deposit',
                'systemlanguageadmin.position_summary_total_comm' => 'Commission',
                'systemlanguageadmin.position_summary_total_money' => 'Profit/Loss',
            ],
        ];
        $actual = [];

        foreach ($expected as $locale => $translations) {
            $groups = [];
            foreach ($translations as $translationKey => $value) {
                [$group, $key] = explode('.', $translationKey, 2);
                $groups[$group] ??= include resource_path('lang/' . $locale . '/' . $group . '.php');
                $actual[$locale][$translationKey] = $groups[$group][$key] ?? null;
            }
        }

        $this->assertSame($expected, $actual, 'Legacy direct-customer financial columns must preserve their original business labels.');
    }

    public function test_front_js_translation_keys_exist_in_language_packs(): void
    {
        $usedKeys = [];
        $paths = [
            public_path('js/apps/front/layui'),
            public_path('js/apps/admin/layui'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.js')) {
                    continue;
                }

                $content = file_get_contents($file->getPathname()) ?: '';
                preg_match_all(
                    '/\b(?:tr|t|CrmLang\.t|CRM\.t)\(\s*[\'"]((?:front|common|menu|auth|response|user|register|profile|validation)\.[A-Za-z0-9_]+)[\'"]/',
                    $content,
                    $matches
                );

                foreach ($matches[1] as $key) {
                    $usedKeys[$key][] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        foreach (['zh-CN', 'en'] as $locale) {
            $definedKeys = $this->languageKeys($locale);
            $missing = array_values(array_diff(array_keys($usedKeys), $definedKeys));
            sort($missing);

            $this->assertSame([], $missing, $locale . ' 语言包缺少前台 JS 静态翻译 key，页面会显示英文兜底或 key。');
        }

        $zhStatic = $this->publicScript('shared/lang/common/zh-CN.js');
        $enStatic = $this->publicScript('shared/lang/common/en.js');
        $commonI18n = $this->publicScript('common/i18n.js');

        foreach ([$zhStatic, $commonI18n] as $source) {
            $this->assertStringNotContainsString('naive_admin_desc', $source);
            $this->assertStringNotContainsString('naive_front_desc', $source);
            $this->assertStringContainsString("chart_render_failed: '\u{56FE}\u{8868}\u{6E32}\u{67D3}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{5237}\u{65B0}\u{540E}\u{91CD}\u{8BD5}'", $source);
        }

        foreach ([$enStatic, $commonI18n] as $source) {
            $this->assertStringNotContainsString('naive_admin_desc', $source);
            $this->assertStringNotContainsString('naive_front_desc', $source);
            $this->assertStringContainsString("chart_render_failed: 'Chart rendering failed. Please refresh and try again.'", $source);
        }
    }

    public function test_register_page_phone_input_validation(): void
    {
        $blade = file_get_contents(resource_path('front/layui/auth/register.blade.php')) ?: '';
        $script = $this->publicScript('front/layui/auth/register.js');

        $this->assertStringContainsString('name="phone_number"', $blade);
        $this->assertStringContainsString('minlength="11"', $blade);
        $this->assertStringContainsString('maxlength="20"', $blade);
        $this->assertStringContainsString('register-phone-input', $blade);
        $this->assertStringContainsString('/^[0-9]{11,20}$/', $script);
    }

    public function test_front_auth_js_uses_resource_endpoints_not_legacy(): void
    {
        $scripts = [
            'layui register' => $this->publicScript('front/layui/auth/register.js'),
            'layui login' => $this->publicScript('front/layui/auth/login.js'),
            'layui forgot password' => $this->publicScript('front/layui/auth/forgot-password.js'),
            'layui big number login' => $this->publicScript('front/layui/auth/big-number-login.js'),
        ];
        $combined = implode("\n", $scripts);

        foreach ([
            'front_api_login',
            'front_api_register',
            'front_api_registerSendCode',
            'front_api_registerCaptcha',
            'front_api_validateInviter',
            'front_api_forgotPasswordSendCode',
            'front_api_forgotPasswordReset',
            'front_api_big_number_login',
            "api('/registerSendCode'",
            "api('/forgotPasswordSendCode'",
            "api('/forgotPasswordReset'",
            "api('/bigNumber/login'",
        ] as $legacyEndpoint) {
            $this->assertStringNotContainsString($legacyEndpoint, $combined, $legacyEndpoint . ' must not be used by public front auth JS.');
        }

        foreach ([
            '/api/front/auth/login',
            '/api/front/auth/register',
            '/api/front/auth/register/captcha',
            '/api/front/auth/inviter',
            '/api/front/auth/password/email-code',
            '/api/front/auth/password/reset',
            '/api/front/auth/big-number/login',
        ] as $resourceEndpoint) {
            $this->assertStringContainsString($resourceEndpoint, $combined, $resourceEndpoint . ' must stay visible as a hardcoded frontend URL.');
        }
    }

    public function test_front_auth_resource_routes_registered(): void
    {
        $frontRoutes = file_get_contents(base_path('routes/front.php')) ?: '';
        $webRoutes = file_get_contents(base_path('routes/web.php')) ?: '';

        foreach ([
            'front_api_auth_login',
            'front_api_auth_register',
            'front_api_auth_register_captcha',
            'front_api_auth_register_email_code',
            'front_api_auth_register_verify',
            'front_api_auth_email_check',
            'front_api_auth_inviter',
            'front_api_auth_password_email_code',
            'front_api_auth_password_reset',
            'front_api_auth_big_number_login',
        ] as $routeName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($routeName), $routeName . ' resource auth route is missing.');
        }

        foreach ([
            "Route::get('/email/check', 'AuthController@checkEmail')",
            "Route::get('/inviter', 'AuthController@validateInviter')",
            "Route::post('/login', 'AuthController@login')",
            "Route::post('/register', 'AuthController@register')",
            "Route::post('/register/email-code', 'AuthController@registerSendCode')",
            "Route::post('/password/email-code', 'ForgotPasswordController@sendResetCode')",
            "Route::post('/password/reset', 'ForgotPasswordController@resetPassword')",
        ] as $route) {
            $this->assertStringContainsString($route, $frontRoutes, $route . ' route is missing or uses the wrong HTTP method.');
        }

        $registerScript = $this->publicScript('front/layui/auth/register.js');
        $this->assertStringContainsString("url: '/api/front/auth/inviter'", $registerScript);
        $this->assertStringContainsString("type: 'GET'", $registerScript);

        foreach ([
            'front_api_login',
            'front_api_register',
            'front_api_registerinto',
            'front_api_registerCaptcha',
            'front_api_registerVerifyInfo',
            'front_api_registerSendCode',
            'front_api_checkEmail',
            'front_api_testemail',
            'front_api_validateInviter',
            'front_api_forgotPasswordSendCode',
            'front_api_forgetpswSendCode',
            'front_api_forgotPasswordReset',
            'front_api_big_number_login',
        ] as $legacyRouteName) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($legacyRouteName), $legacyRouteName . ' must not stay registered.');
            $this->assertStringNotContainsString("'" . $legacyRouteName . "'", $webRoutes, $legacyRouteName . ' must not be used by Blade route aliases.');
        }

        foreach ([
            "'front.login.post' => 'front_api_auth_login'",
            "'front.register.post' => 'front_api_auth_register'",
            "'user.forget.password.post' => 'front_api_auth_password_email_code'",
        ] as $alias) {
            $this->assertStringContainsString($alias, $webRoutes, $alias . ' alias is missing.');
        }

        foreach ([
            '/login',
            '/register',
            '/registerinto',
            '/registerVerifyInfo',
            '/registerSendCode',
            '/checkEmail',
            '/testemail',
            '/validateInviter',
            '/forgotPasswordSendCode',
            '/forgetpswSendCode',
            '/forgotPasswordReset',
            '/bigNumber/login',
        ] as $endpoint) {
            try {
                \Illuminate\Support\Facades\Route::getRoutes()->match(
                    \Illuminate\Http\Request::create('/api/front' . $endpoint, 'POST')
                );
                $this->fail('/api/front' . $endpoint . ' must be removed in favor of /api/front/auth/... paths.');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
                $this->assertTrue(true);
            }
        }

        try {
            \Illuminate\Support\Facades\Route::getRoutes()->match(
                \Illuminate\Http\Request::create('/api/front/registerCaptcha', 'GET')
            );
            $this->fail('/api/front/registerCaptcha must be removed in favor of /api/front/auth/register/captcha.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
            $this->assertTrue(true);
        }
    }

    public function test_password_recovery_routes_registered_without_legacy(): void
    {
        foreach ([
            'front_api_auth_password_email_code',
            'front_api_auth_password_reset',
        ] as $routeName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($routeName), $routeName . ' resource password recovery route is missing.');
        }

        foreach ([
            'front_api_saveChangePassword',
            'front_api_checkUserInfo',
            'front_api_check_user_info',
            'front_api_forgetPasswordInfoVerification',
        ] as $legacyRouteName) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($legacyRouteName), $legacyRouteName . ' must not stay registered as a public front API.');
        }

        foreach ([
            '/saveChangePassword',
            '/checkUserInfo',
            '/check_user_info',
            '/forgetPasswordInfoVerification',
        ] as $endpoint) {
            try {
                \Illuminate\Support\Facades\Route::getRoutes()->match(
                    \Illuminate\Http\Request::create('/api/front' . $endpoint, 'POST')
                );
                $this->fail('/api/front' . $endpoint . ' must be removed in favor of /api/front/auth/password/... paths.');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
                $this->assertTrue(true);
            }
        }

        foreach ([
            'legacy_user_forget_check_info',
            'legacy_user_forget_verify',
            'legacy_user_change_password',
        ] as $legacyWebRouteName) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($legacyWebRouteName),
                $legacyWebRouteName . ' legacy web compatibility route must stay registered.'
            );
        }
    }

    public function test_logout_and_token_refresh_resource_routes_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php')) ?: '';
        $middleware = file_get_contents(app_path('Http/Middleware/CheckPermission.php')) ?: '';

        foreach ([
            'front_api_auth_logout',
            'front_api_auth_token_refresh',
        ] as $routeName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($routeName), $routeName . ' resource auth route is missing.');
        }

        foreach ([
            'front_api_logout',
            'front_api_refreshToken',
        ] as $legacyRouteName) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($legacyRouteName), $legacyRouteName . ' must not stay registered.');
            $this->assertStringNotContainsString("'" . $legacyRouteName . "'", $webRoutes, $legacyRouteName . ' must not be used by Blade route aliases.');
            $this->assertStringNotContainsString("'" . $legacyRouteName . "'", $middleware, $legacyRouteName . ' must not stay in permission bypass rules.');
        }

        $this->assertStringContainsString("'front.logout' => 'front_api_auth_logout'", $webRoutes);
        $this->assertStringContainsString("'front_api_auth_logout'", $middleware);
        $this->assertStringContainsString("'front_api_auth_token_refresh'", $middleware);

        foreach ([
            '/logout',
            '/refreshToken',
        ] as $endpoint) {
            try {
                \Illuminate\Support\Facades\Route::getRoutes()->match(
                    \Illuminate\Http\Request::create('/api/front' . $endpoint, 'POST')
                );
                $this->fail('/api/front' . $endpoint . ' must be removed in favor of /api/front/auth/... paths.');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_layui_scripts_use_explicit_api_urls(): void
    {
        $violations = [];
        $layout = $this->publicScript('front/layui/layout.js');
        $dashboard = $this->publicScript('front/layui/dashboard/index.js');

        foreach ($this->filesUnder(public_path('js/apps/front/layui'), '.js') as $file) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . 'common.js')) {
                continue;
            }

            $content = file_get_contents($file) ?: '';
            if (strpos($content, 'front_api_') !== false) {
                $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $violations, 'Layui front scripts must call backend APIs with explicit /api/front/... URLs instead of front_api_* route names.');
        $expectedComment = "\u{9875}\u{9762}\u{8DF3}\u{8F6C}\u{4F7F}\u{7528} PHP \u{6CE8}\u{5165}\u{7684} Laravel \u{8DEF}\u{7531}\u{6E05}\u{5355}\u{FF0C}\u{540E}\u{7AEF} API \u{4FDD}\u{6301}\u{663E}\u{5F0F} /api/front/... URL";
        foreach ([$layout, $dashboard] as $source) {
            $this->assertDoesNotMatchRegularExpression('/(?:\x{95B3}|\x{95B8}|\x{93C9}|\x{9420}|\x{9225})\?/u', $source);
            $this->assertStringContainsString($expectedComment, $source);
        }
    }

    public function test_admin_layui_scripts_keep_readable_api_urls(): void
    {
        $violations = [];

        foreach ($this->filesUnder(public_path('js/apps/admin/layui'), '.js') as $file) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . 'common.js')) {
                continue;
            }

            $content = file_get_contents($file) ?: '';
            if (preg_match('/\broute\s*:\s*[\'"]admin_api_/', $content)) {
                $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $violations, 'Admin Layui scripts must keep backend API URLs visible as hardcoded /api/admin/... strings instead of hiding them behind admin_api_* route names.');
    }

    public function test_business_js_uses_readable_api_urls_not_route_names(): void
    {
        $violations = [];

        foreach ([
            public_path('js/apps/front'),
            public_path('js/apps/admin'),
        ] as $path) {
            foreach ($this->filesUnder($path, '.js') as $file) {
                $content = file_get_contents($file) ?: '';
                if (preg_match_all('/\b(?:front|admin)_api_[A-Za-z0-9_]+/', $content, $matches)) {
                    $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file)
                        . ' => ' . implode(', ', array_values(array_unique($matches[0])));
                }
            }
        }

        $this->assertSame([], $violations, 'Business JS must document and call backend APIs as readable /api/... URLs, not front_api_* or admin_api_* route names.');
    }

    public function test_crm_ajax_and_table_use_readable_urls(): void
    {
        $ajax = $this->publicScript('common/ajax.js');
        $table = $this->publicScript('common/table-common.js');

        foreach (['opts.route', 'opts.routeParams', 'crmRoute(opts.route', 'crmRouteFromUrl'] as $needle) {
            $this->assertStringNotContainsString($needle, $ajax, 'CrmAjax backend requests must use readable url strings only: ' . $needle);
        }
        $this->assertDoesNotMatchRegularExpression('/(?:\x{95B3}|\x{95B8}|\x{93C9}|\x{9420}|\x{9225})\?/u', $ajax);
        $this->assertStringContainsString("\u{540E}\u{7AEF} API \u{5730}\u{5740}\u{5FC5}\u{987B}\u{7531}\u{4E1A}\u{52A1} JS \u{76F4}\u{63A5}\u{4F20}\u{5165}\u{6E05}\u{6670}\u{7684} /api/... URL", $ajax);

        foreach (['merged.route', 'merged.routeParams', 'crmRoute(merged.route', 'crmRouteFromUrl'] as $needle) {
            $this->assertStringNotContainsString($needle, $table, 'CrmTable backend requests must use readable url strings only: ' . $needle);
        }

        $this->assertStringContainsString("guard === 'admin' ? '/admin/login' : '/front/login'", $ajax);
    }

    public function test_layui_common_wrappers_use_readable_urls(): void
    {
        $frontLayui = $this->publicScript('front/layui/common.js');
        $adminLayui = $this->publicScript('admin/layui/common.js');

        foreach ([
            'opts.url = routeUrl(opts.route',
            'opts.routeParams',
            'settings.url = CRM.route(settings.route',
            'settings.routeParams',
            'settings.url = CRM.routeFromUrl(settings.url',
            'CRM.routeFromUrl = function',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $frontLayui . "\n" . $adminLayui, 'Legacy AJAX wrappers must send the readable url configured by business scripts: ' . $needle);
        }
    }

    public function test_front_account_balance_menu_and_route_contract(): void
    {
        $routes = file_get_contents(base_path('routes/web.php')) ?: '';
        $zhMenus = file_get_contents(resource_path('lang/zh-CN/menus.php')) ?: '';
        $enMenus = file_get_contents(resource_path('lang/en/menus.php')) ?: '';
        $zhBreadcrumb = file_get_contents(resource_path('lang/zh-CN/breadcrumb.php')) ?: '';
        $enBreadcrumb = file_get_contents(resource_path('lang/en/breadcrumb.php')) ?: '';
        $frontSeeder = file_get_contents(database_path('seeders/FrontDemoDataSeeder.php')) ?: '';
        $frontAccountMenuMigration = file_get_contents(database_path('migrations/2026_05_16_000001_merge_front_profile_account_menus.php')) ?: '';

        $layuiResponse = $this->get('/front/account/balance?frame=1');

        $layuiResponse->assertOk();

        $this->assertStringContainsString("view('front_layui::account.balance')", $routes);
        $this->assertStringContainsString('data-api="/api/front/account/balance"', $layuiResponse->getContent());
        $this->assertStringContainsString('data-method="GET"', $layuiResponse->getContent());
        $this->assertStringContainsString('data-translate="front.account_balance"', $layuiResponse->getContent());
        $this->assertStringContainsString("'front_account_balance' => '\u{8D26}\u{6237}\u{4F59}\u{989D}'", $zhMenus);
        $this->assertStringContainsString("'front_account_balance' => 'Account Balance'", $enMenus);
        $this->assertStringContainsString("'front_account_balance' => '\u{8D26}\u{6237}\u{7BA1}\u{7406} / \u{8D26}\u{6237}\u{4F59}\u{989D}'", $zhBreadcrumb);
        $this->assertStringContainsString("'front_account_balance' => 'Account / Balance'", $enBreadcrumb);
        foreach ([$frontSeeder, $frontAccountMenuMigration] as $source) {
            $this->assertMatchesRegularExpression(
                "/where\\('slug', 'front_account_balance'\\)->update\\(\\[[\\s\\S]*'route' => '\\/front\\/account\\/balance'[\\s\\S]*'api_route' => 'front_api_account_balance'[\\s\\S]*'type' => 2[\\s\\S]*'status' => 1/s",
                $source,
                'Account balance menu permission must be enabled and point to the standalone balance page/API route.'
            );
        }
    }

    public function test_front_seeder_legacy_trade_classification_helpers(): void
    {
        $frontSeeder = file_get_contents(database_path('seeders/FrontDemoDataSeeder.php')) ?: '';

        $this->assertStringContainsString('$hasClosedLegacyTrades = $this->hasClosedLegacyTrades($legacyTrades);', $frontSeeder);
        $this->assertStringContainsString('$hasLegacyCloseTime = array_key_exists', $frontSeeder);
        $this->assertStringContainsString('$isOpen = $hasClosedLegacyTrades && $hasLegacyCloseTime ? $this->isLegacyOpenTrade($legacyCloseTime) : ($n % 3 === 0);', $frontSeeder);
        $this->assertStringContainsString('private function isLegacyOpenTrade($legacyCloseTime): bool', $frontSeeder);
        $this->assertStringContainsString('private function hasClosedLegacyTrades(array $legacyTrades): bool', $frontSeeder);
    }

    public function test_front_menu_permissions_use_resource_route_names(): void
    {
        $frontRoutes = file_get_contents(base_path('routes/front.php')) ?: '';
        $sources = [
            'database/seeders/FrontDemoDataSeeder.php' => file_get_contents(database_path('seeders/FrontDemoDataSeeder.php')) ?: '',
            'database/migrations/2026_05_13_000003_add_front_news_permission.php' => file_get_contents(database_path('migrations/2026_05_13_000003_add_front_news_permission.php')) ?: '',
            'database/migrations/2026_05_16_000001_merge_front_profile_account_menus.php' => file_get_contents(database_path('migrations/2026_05_16_000001_merge_front_profile_account_menus.php')) ?: '',
            'database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php' => file_get_contents(database_path('migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php')) ?: '',
            '_seed.php' => file_get_contents(base_path('_seed.php')) ?: '',
            '_seed_menus.php' => file_get_contents(base_path('_seed_menus.php')) ?: '',
        ];
        $legacyRouteNames = [
            'front_api_dashboardData',
            'front_api_profileInfo',
            'front_api_changePassword',
            'front_api_changeEmail',
            'front_api_accountInfo',
            'front_api_accountBalance',
            'front_api_accountFlow',
            'front_api_submitVoucher',
            'front_api_cancelApply',
            'front_api_submitDeposit',
            'front_api_submitWithdraw',
            'front_api_positionSummary',
            'front_api_openOrders',
            'front_api_closedOrders',
            'front_api_agentSubList',
            'front_api_agentCustomerList',
            'front_api_agentConfirmLevel',
            'front_api_agentGroupChangeList',
            'front_api_agentGroupChange',
            'front_api_commissionRealTime',
            'front_api_commissionHistory',
            'front_api_commissionTransfer',
            'front_api_giftAddressList',
            'front_api_giftList',
            'front_api_newsList',
        ];
        $violations = [];

        foreach ($sources as $path => $source) {
            foreach ($legacyRouteNames as $routeName) {
                if (str_contains($source, $routeName)) {
                    $violations[] = $path . ' uses ' . $routeName;
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Front menu permission api_route values must use RESTful resource-style route names.');
        $this->assertStringContainsString("Route::get('/navigation/menus', 'MenuController@userMenus')", $frontRoutes);
        $this->assertStringContainsString("Route::get('/menus', 'MenuController@userMenus')", $frontRoutes);
        foreach ([
            'front_api_dashboard',
            'front_api_profile',
            'front_api_account_profile',
            'front_api_account_balance',
            'front_api_account_vouchers',
            'front_api_account_cancellation',
            'front_api_flows_account',
            'front_api_positions_summary',
            'front_api_orders_open',
            'front_api_orders_closed',
            'front_api_agents_direct',
            'front_api_agents_direct_customers',
            'front_api_agents_level_confirmation',
            'front_api_agents_group_changes',
            'front_api_commissions_realtime',
            'front_api_commissions_history',
            'front_api_gift_addresses_index',
            'front_api_gifts',
            'front_api_news',
        ] as $routeName) {
            $this->assertStringContainsString($routeName, implode("\n", $sources), $routeName . ' must be present in menu permission data.');
        }

        $this->assertStringContainsString("Route::post('/commissions/transfers', 'CommissionController@transfer')", $frontRoutes);
    }

    public function test_front_seed_menu_api_routes_exist(): void
    {
        $sources = [
            '_seed.php' => file_get_contents(base_path('_seed.php')) ?: '',
            '_seed_menus.php' => file_get_contents(base_path('_seed_menus.php')) ?: '',
        ];
        $missing = [];

        foreach ([
            database_path('seeders'),
            database_path('migrations'),
        ] as $directory) {
            foreach ($this->filesUnder($directory, '.php') as $file) {
                $sources[str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file)] = file_get_contents($file) ?: '';
            }
        }

        foreach ($sources as $path => $source) {
            preg_match_all("/'api_route'\\s*=>\\s*'(front_api_[^']+)'|,'(front_api_[^']+)'\\]/", $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $routeName = $match[1] ?: $match[2];

                if ($routeName !== '' && !\Illuminate\Support\Facades\Route::has($routeName)) {
                    $missing[] = $path . ' uses missing route ' . $routeName;
                }
            }
        }

        sort($missing);

        $this->assertSame([], $missing, 'Front seed menu api_route values must point to registered Laravel route names.');
    }

    public function test_front_menu_primary_read_api_routes(): void
    {
        $sources = [
            '_seed_menus.php' => file_get_contents(base_path('_seed_menus.php')) ?: '',
            'database/seeders/FrontDemoDataSeeder.php' => file_get_contents(database_path('seeders/FrontDemoDataSeeder.php')) ?: '',
            'database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php' => file_get_contents(database_path('migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php')) ?: '',
        ];

        foreach ([
            'front_voucher' => 'front_api_account_vouchers',
            'front_cancel' => 'front_api_account_cancellation',
            'front_deposit' => 'front_api_deposits_history',
            'front_withdraw' => 'front_api_withdrawals_history',
            'front_commission_transfer' => 'front_api_commissions_history',
        ] as $slug => $routeName) {
            foreach ($sources as $path => $source) {
                $this->assertMatchesRegularExpression(
                    "/" . preg_quote("'slug' => '" . $slug . "'", '/') . "[\\s\\S]*?" . preg_quote("'api_route' => '" . $routeName . "'", '/') . "|" .
                    preg_quote("where('slug', '" . $slug . "')", '/') . "[\\s\\S]*?" . preg_quote("'api_route' => '" . $routeName . "'", '/') . "|" .
                    "\\['[^']+','" . preg_quote($slug, '/') . "','front',[^\\n]+,'" . preg_quote($routeName, '/') . "'\\]/",
                    $source,
                    $path . ' ' . $slug . ' menu api_route must point to the page primary read API instead of a write-only submission route.'
                );
            }
        }
    }

    public function test_seed_keeps_current_front_menu_slugs(): void
    {
        $seed = file_get_contents(base_path('_seed.php')) ?: '';

        foreach ([
            'front_account',
            'front_account_info',
            'front_account_balance',
            'front_voucher',
            'front_cancel',
            'front_deposit_withdraw',
            'front_deposit',
            'front_withdraw',
            'front_flow',
            'front_trading',
            'front_position_summary',
            'front_open_orders',
            'front_closed_orders',
            'front_agent_confirm',
            'front_group_change',
            'front_commission_transfer',
            'front_gift',
            'front_gift_address',
            'front_gift_list',
            'front_news',
        ] as $slug) {
            $this->assertStringContainsString("'slug'=>'" . $slug . "'", $seed, '_seed.php must not drop current front menu slug: ' . $slug);
        }
    }

    public function test_deposit_page_channel_manager_visual_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/deposit/index.blade.php')) ?: '';
        $script = $this->publicScript('front/layui/deposit/index.js');
        $manager = $this->publicScript('common/pay-channel-manager.js');
        $css = file_get_contents(public_path('css/front/style.css')) ?: '';
        $v3Css = file_get_contents(public_path('css/front/front-v3.css')) ?: '';
        $legacyChannelStyles = $this->sourceBetween($css, '.payment-channel-layui-tabs {', '.crm-upload-card {');
        $v3DepositStyles = $this->sourceBetween($v3Css, '.crm-v3-deposit {', '@media (max-width: 768px) {');

        $this->assertStringContainsString('payment-channel-layui-tabs', $blade);
        $this->assertStringContainsString('lay-filter="depositPaymentChannelTabs"', $blade);
        $this->assertStringContainsString('PayChannelManager.create', $script);
        $this->assertStringContainsString("container: '#depositChannelList'", $script);
        $this->assertStringContainsString('payment-channel-head', $manager);
        $this->assertStringContainsString('data-lucide="badge-dollar-sign"', $manager);
        $this->assertStringContainsString('channel-meta-grid', $manager);
        $this->assertStringContainsString('channel-meta-item', $manager);
        $this->assertStringContainsString('.payment-channel-panel { padding: 16px;', $css);
        $this->assertStringContainsString('.deposit-page .payment-channel-layui-tabs { max-width: 920px;', $css);
        $this->assertStringContainsString('.payment-channel-layui-tabs .layui-tab-title {', $css);
        $this->assertStringContainsString('background: var(--front-table-head);', $css);
        $this->assertStringContainsString('.payment-channel-head { display: flex;', $css);
        $this->assertStringContainsString('.channel-meta-grid { display: grid;', $css);
        $this->assertStringContainsString('.channel-meta-item strong {', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $css);
        $this->assertStringContainsString('.payment-channel-panel .channel-remark-list li::marker', $css);
        $this->assertStringContainsString('@media (max-width: 768px) {', $css);
        $this->assertStringContainsString('.payment-channel-layui-tabs .layui-tab-title li { flex: 1 1 calc(50% - 8px);', $css);

        $this->assertNotSame('', $legacyChannelStyles, 'Legacy shared payment channel styles must stay grouped for reuse.');
        $this->assertStringNotContainsString('color: #fff;', $legacyChannelStyles, 'Payment channel styles must use theme tokens instead of hardcoded white text.');
        $this->assertStringNotContainsString('rgba(15, 23, 42', $legacyChannelStyles, 'Payment channel shadows must use theme tokens.');
        $this->assertStringContainsString('color: var(--front-on-accent);', $legacyChannelStyles);
        $this->assertStringContainsString('box-shadow: 0 10px 24px var(--front-shadow);', $legacyChannelStyles);

        $this->assertNotSame('', $v3DepositStyles, 'Deposit v3 visual layer must expose dedicated channel styles.');
        $this->assertStringContainsString('.crm-v3 .payment-channel-layui-tabs', $v3DepositStyles);
        $this->assertStringContainsString('.crm-v3 .payment-channel-panel {', $v3DepositStyles);
        $this->assertStringContainsString('box-shadow: var(--v3-shadow-sm);', $v3DepositStyles);
        $this->assertStringContainsString('.crm-v3 .payment-channel-head > .crm-lucide-icon', $v3DepositStyles);
        $this->assertStringContainsString('color: var(--v3-primary-ink);', $v3DepositStyles);
        $this->assertStringContainsString('.crm-v3 #depositHistorySummary {', $v3DepositStyles);
        $this->assertStringContainsString('justify-content: flex-start;', $v3DepositStyles);
    }

    public function test_front_deposit_without_configured_channels_fails_closed_in_blade(): void
    {
        $this->seedFrontDemoDataAndCaptureOwnedConfig();
        DB::table('payment_channels')->delete();

        $login = UserLogin::where('user_id', 1001)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/deposits/form-options');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame([], $response->json('data.channels'), 'Unconfigured legacy channels must not be exposed as usable payment paths.');

        $manager = $this->publicScript('common/pay-channel-manager.js');

        $this->assertStringContainsString('channels[i].remark_items', $manager);
        $this->assertStringContainsString('channels[i].description', $manager);

        $crmui = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $crmuiPartial = file_get_contents(resource_path('front/crmui/partials/module-page.blade.php')) ?: '';
        $this->assertStringContainsString('data-crmui-channel-remarks', $crmuiPartial);
        $this->assertStringContainsString('function renderChannelRemarks', $crmui);
        $this->assertStringContainsString('remark_items', $crmui);
        $this->assertStringContainsString('description', $crmui);
    }

    public function test_front_demo_payment_channel_remarks_are_seeded_in_database_config(): void
    {
        $this->seedFrontDemoDataAndCaptureOwnedConfig();

        $rows = DB::table('payment_channels')
            ->whereIn('channel_code', ['bank_transfer', 'quick_pay', 'usdt_trc20'])
            ->orderBy('channel_code')
            ->get();

        $this->assertCount(3, $rows);

        foreach ($rows as $row) {
            $config = json_decode((string) $row->config, true);
            $this->assertIsArray($config, 'payment_channels.config must decode for ' . $row->channel_code);
            $this->assertArrayHasKey('remark_items', $config, 'payment_channels.config must carry remark_items for ' . $row->channel_code);
            $this->assertIsArray($config['remark_items']);
            $this->assertNotSame([], $config['remark_items'], 'remark_items must not be an empty local fallback for ' . $row->channel_code);

            foreach ($config['remark_items'] as $item) {
                $this->assertIsString($item);
                $this->assertNotSame('', trim($item));
            }
        }
    }

    public function test_front_deposit_database_channels_normalize_legacy_remark_fields(): void
    {
        $this->seedFrontDemoDataAndCaptureOwnedConfig();
        DB::table('payment_channels')->delete();

        $now = time();
        DB::table('payment_channels')->insert([
            'name' => 'Manual Bank Transfer',
            'channel_code' => 'manual-bank',
            'exchange_rate' => 7.18,
            'is_enabled' => 1,
            'sort' => 10,
            'config' => json_encode([
                'remarks' => "Minimum deposit: 500 USD\nUpload bank voucher after transfer\n到账前请勿重复提交",
                'adapter' => 'wp',
                'currency' => 'USD',
                'app_id' => 'front-ui-regression-app',
                'gateway_url' => 'https://pay.example.test/checkout',
                'secret_reference' => 'env:FRONT_UI_REGRESSION_PAYMENT_SECRET',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
                'min_amount' => 500,
                'max_amount' => 6800,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $login = UserLogin::where('user_id', 1001)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/deposits/form-options');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $channel = $response->json('data.channels.0');
        $this->assertSame('manual-bank', $channel['code']);
        $this->assertSame([
            'Minimum deposit: 500 USD',
            'Upload bank voucher after transfer',
            '到账前请勿重复提交',
        ], $channel['remark_items']);
        $this->assertStringContainsString('Upload bank voucher after transfer', $channel['description']);
    }

    public function test_front_deposit_channel_remarks_do_not_fall_back_to_controller_literals(): void
    {
        $this->seedFrontDemoDataAndCaptureOwnedConfig();
        DB::table('payment_channels')->delete();

        $now = time();
        DB::table('payment_channels')->insert([
            'name' => 'Legacy Numeric Gateway',
            'channel_code' => '1',
            'exchange_rate' => 7.18,
            'is_enabled' => 1,
            'sort' => 10,
            'config' => json_encode([
                'adapter' => 'wp',
                'currency' => 'USD',
                'app_id' => 'front-ui-regression-app',
                'gateway_url' => 'https://pay.example.test/checkout',
                'secret_reference' => 'env:FRONT_UI_REGRESSION_PAYMENT_SECRET',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
                'min_amount' => 500,
                'max_amount' => 6800,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $login = UserLogin::where('user_id', 1001)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/deposits/form-options');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $channel = $response->json('data.channels.0');
        $this->assertSame('1', $channel['code']);
        $this->assertSame([], $channel['remark_items'], 'Missing DB remark_items must not synthesize legacy controller text.');
        $this->assertSame('', $channel['description']);
        $this->assertStringNotContainsString('Minimum transaction limit per trade', json_encode($channel, JSON_UNESCAPED_UNICODE));
    }

    public function test_crmui_deposit_keeps_submission_aliases_but_rejects_unconfigured_channels(): void
    {
        $this->seedFrontDemoDataAndCaptureOwnedConfig();
        DB::table('payment_channels')->delete();
        DB::table('deposit_records')->where('user_id', 1001)->delete();

        $login = UserLogin::where('user_id', 1001)->firstOrFail();
        $optionsResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/deposits/form-options');

        $optionsResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame([], $optionsResponse->json('data.channels'), 'CrmUI must not render synthetic payment channels when the database has no usable configuration.');

        $crmuiHtml = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/front-crmui/deposit')
            ->assertOk()
            ->getContent();

        foreach ([
            'data-options-url="http://localhost/api/front/deposits/form-options"',
            'data-crmui-channel-remarks',
            'name="deposit_amt_usd"',
            'name="deposit_pay_amt_rmb"',
            'name="pay_channel"',
            'name="passageway"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $crmuiHtml, 'CrmUI deposit form is missing legacy-compatible markup: ' . $needle);
        }

        $submitResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', 'front-ui-empty-channel')
            ->postJson('/api/front/deposits/submissions', [
                'deposit_amt_usd' => '650.00',
                'pay_channel' => '4',
            ]);

        $submitResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseMissing('deposit_records', [
            'user_id' => 1001,
            'amount' => '650.00',
        ]);
    }

    public function test_legacy_deposit_submission_rejects_incomplete_gateway_config(): void
    {
        $this->seedFrontDemoDataAndCaptureOwnedConfig();
        DB::table('payment_channels')->delete();
        DB::table('deposit_records')->where('user_id', 1001)->delete();

        $now = time();
        DB::table('payment_channels')->insert([
            'name' => 'Gateway Test Channel',
            'channel_code' => 'gateway-test',
            'exchange_rate' => 7.25,
            'is_enabled' => 1,
            'sort' => 10,
            'config' => json_encode([
                'gateway_url' => 'https://pay.example.test/checkout',
                'remark_items' => ['Legacy gateway remark'],
                'min_amount' => 10,
                'max_amount' => 5000,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $login = UserLogin::where('user_id', 1001)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', 'front-ui-incomplete-gateway')
            ->postJson('/user/deposit_request', [
                'deposit_amt' => '120.00',
                'passageway' => 'gateway-test',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseMissing('deposit_records', [
            'user_id' => 1001,
            'amount' => '120.00',
        ]);
    }

    public function test_front_visual_surface_system_contract(): void
    {
        $dashboard = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $deposit = file_get_contents(resource_path('front/layui/deposit/index.blade.php')) ?: '';
        $profile = file_get_contents(resource_path('front/layui/profile/index.blade.php')) ?: '';
        $account = file_get_contents(resource_path('front/layui/account/info.blade.php')) ?: '';
        $css = file_get_contents(public_path('css/front/style.css')) ?: '';

        foreach ([
            'dashboard' => $dashboard,
            'deposit' => $deposit,
            'profile' => $profile,
            'account' => $account,
        ] as $page => $source) {
            $this->assertStringContainsString('crm-visual-page', $source, $page . ' page must opt in to the shared visual surface system.');
        }

        $this->assertStringContainsString('crm-dashboard-page', $dashboard);
        $this->assertStringContainsString('crm-deposit-form-card', $deposit);
        $this->assertStringContainsString('crm-deposit-history-card', $deposit);
        $this->assertStringContainsString('crm-account-overview-page', $account);

        foreach ([
            '.crm-visual-page',
            '.crm-visual-page .layui-card',
            '.crm-visual-page .layui-card-header',
            '.crm-visual-page .module-chart-card',
            '.crm-dashboard-page .dashboard-chart-card',
            '.crm-deposit-page .payment-channel-panel',
            '.crm-profile-shell .layui-card',
            '.crm-account-overview-page .module-comparison-table',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, 'Shared front stylesheet is missing visual surface rule: ' . $needle);
        }
    }

    public function test_deposit_withdraw_pages_no_mock_data(): void
    {
        $sources = [
            file_get_contents(resource_path('front/layui/deposit/index.blade.php')) ?: '',
            $this->publicScript('front/layui/deposit/index.js'),
            file_get_contents(resource_path('front/layui/withdraw/index.blade.php')) ?: '',
            $this->publicScript('front/layui/withdraw/index.js'),
        ];

        $combined = implode("\n", $sources);

        foreach (['depositMockSummary', 'withdrawMockSummary', 'mockDepositPage', 'renderMockSummary', 'mock data', "\u{6A21}\u{62DF}\u{6C47}\u{603B}", "\u{6D4B}\u{8BD5}\u{6C47}\u{603B}"] as $needle) {
            $this->assertStringNotContainsString($needle, $combined, 'Deposit/withdraw pages must not render local mock data: ' . $needle);
        }

        $this->assertStringContainsString("summaryElem: '#depositHistorySummary'", $combined);
        $this->assertStringContainsString("summaryElem: '#withdrawHistorySummary'", $combined);
    }

    public function test_deposit_withdraw_pages_use_readable_api_urls(): void
    {
        $deposit = $this->publicScript('front/layui/deposit/index.js');
        $withdraw = $this->publicScript('front/layui/withdraw/index.js');
        $depositCore = $this->publicScript('common/deposit-page-core.js');
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $combined = $deposit . "\n" . $withdraw;

        foreach ([
            'front_api_depositPage',
            'front_api_submitDeposit',
            'front_api_depositHistory',
            'front_api_withdrawPage',
            'front_api_submitWithdraw',
            'front_api_withdrawHistory',
        ] as $routeName) {
            $this->assertStringNotContainsString($routeName, $combined, 'Layui deposit/withdraw pages must not hide API URLs behind route names: ' . $routeName);
        }

        foreach ([
            "pageApi: '/api/front/deposits/form-options'",
            "pageMethod: 'GET'",
            "submitApi: '/api/front/deposits/submissions'",
            "historyApi: '/api/front/deposits/history'",
            "historyMethod: 'GET'",
            "url: '/api/front/withdrawals/form-options'",
            "method: 'GET'",
            "url: '/api/front/withdrawals/submissions'",
            "url: '/api/front/withdrawals/history'",
        ] as $snippet) {
            $this->assertStringContainsString($snippet, $combined);
        }

        $this->assertStringContainsString("method: opts.pageMethod || 'POST'", $depositCore);
        $this->assertStringContainsString("method: opts.historyMethod || 'POST'", $depositCore);

        foreach ([
            '/api/front/deposits/page',
            '/api/front/withdrawals/page',
        ] as $legacyEndpoint) {
            $this->assertStringNotContainsString($legacyEndpoint, $combined, $legacyEndpoint . ' must be replaced by readable form-options API URLs.');
        }

        foreach (['pageRoute', 'submitRoute', 'historyRoute', 'route: opts.'] as $routeFallback) {
            $this->assertStringNotContainsString($routeFallback, $depositCore, 'Deposit page core must require readable hardcoded API URLs instead of accepting route-name fallbacks: ' . $routeFallback);
        }

        foreach ([
            "Route::get('/deposits/form-options', 'DepositController@depositPage')",
            "Route::post('/deposits/submissions', 'DepositController@submitDeposit')",
            "Route::get('/deposits/history', 'DepositController@depositHistory')",
            "Route::get('/withdrawals/form-options', 'WithdrawController@withdrawPage')",
            "Route::post('/withdrawals/submissions', 'WithdrawController@submitWithdraw')",
            "Route::get('/withdrawals/history', 'WithdrawController@withdrawHistory')",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, $route . ' route alias is missing.');
        }

        foreach ([
            "Route::post('/deposits/page'",
            "Route::post('/withdrawals/page'",
        ] as $legacyRoute) {
            $this->assertStringNotContainsString($legacyRoute, $routes, $legacyRoute . ' legacy route must be removed.');
        }
    }

    public function test_deposit_history_summary_renders_above_table(): void
    {
        $blade = file_get_contents(resource_path('front/layui/deposit/index.blade.php')) ?: '';
        $v3Css = file_get_contents(public_path('css/front/front-v3.css')) ?: '';

        $summaryPosition = strpos($blade, 'id="depositHistorySummary"');
        $tablePosition = strpos($blade, 'id="depositHistoryTable"');
        $summaryStylePosition = strpos($v3Css, '.crm-v3 #depositHistorySummary {');
        $tableWrapStylePosition = strpos($v3Css, '.crm-v3 .deposit-history-table-wrap {');

        $this->assertNotFalse($summaryPosition, 'Deposit summary template is missing.');
        $this->assertNotFalse($tablePosition, 'Deposit history table template is missing.');
        $this->assertNotFalse($summaryStylePosition, 'Deposit summary alignment style must live in front-v3.css.');
        $this->assertNotFalse($tableWrapStylePosition, 'Deposit history table wrap style must live in front-v3.css.');
        $this->assertLessThan($tablePosition, $summaryPosition, 'Deposit summary must stay above the table.');
        $this->assertStringContainsString('class="deposit-history-table-wrap"', $blade);
        $this->assertStringNotContainsString('.deposit-page #depositHistorySummary', $blade, 'Deposit summary alignment must not be patched with Blade inline styles.');
        $this->assertStringContainsString('justify-content: flex-start;', $v3Css);
        $this->assertStringContainsString('padding: 12px;', $v3Css);
        $this->assertStringContainsString('.crm-v3 #depositHistorySummary .crm-table-summary-item { margin-left: 0;', $v3Css);
    }

    public function test_flow_tabs_use_backend_endpoints_and_no_mock(): void
    {
        $blade = file_get_contents(resource_path('front/layui/flow/index.blade.php')) ?: '';
        $script = $this->publicScript('front/layui/flow/index.js');
        $tabs = [
            'deposit' => '/api/front/flows/deposits',
            'withdraw' => '/api/front/flows/withdrawals',
            'withdraw_apply' => '/api/front/flows/withdrawal-applications',
            'direct_deposit' => '/api/front/flows/direct-deposits',
            'direct_withdraw' => '/api/front/flows/direct-withdrawals',
            'direct_agents_deposit' => '/api/front/flows/direct-agent-deposits',
            'direct_agents_withdraw' => '/api/front/flows/direct-agent-withdrawals',
        ];

        foreach ($tabs as $type => $endpoint) {
            $this->assertStringContainsString("'type' => '" . $type . "'", $blade, $type . ' tab config is missing.');
            $this->assertStringContainsString("'" . $type . "': '" . $endpoint . "'", $script, $type . ' tab must use its backend endpoint.');
        }
        $this->assertStringContainsString("method: 'GET'", $script, 'Flow tab tables must request read-only flow APIs with GET.');

        $this->assertStringNotContainsString('MOCK-', $script);
        $this->assertStringNotContainsString('mockRows', $script);
        $this->assertStringNotContainsString('filterMockRows', $script);
        $this->assertStringNotContainsString('data: rows', $script, 'Layui flow table must request backend rows instead of injecting local data.');

        $summaryPosition = strpos($blade, 'id="flowSummary_{{ $tab[\'type\'] }}"');
        $tablePosition = strpos($blade, 'id="flowTable_{{ $tab[\'type\'] }}"');

        $this->assertNotFalse($summaryPosition, 'Flow summary template is missing.');
        $this->assertNotFalse($tablePosition, 'Flow table template is missing.');
        $this->assertLessThan($tablePosition, $summaryPosition, 'Flow summary must render above each table.');
        $this->assertStringContainsString("summaryElem: '#flowSummary_' + type", $script);
        $this->assertStringContainsString('.flow-page .layui-tab-content { min-height: 560px;', $blade);
        $this->assertStringContainsString('.flow-page .flow-table-wrap { width: 100%; min-height: 430px;', $blade);
        $this->assertStringContainsString('height: 420,', $script);
        $this->assertStringContainsString('preRenderAllTables();', $script);
        $this->assertStringNotContainsString("preRenderAllTables();\n                renderTable(activeType);", $script, 'Flow boot must not reload the active tab immediately after pre-rendering it.');
        $this->assertStringContainsString('if (type === activeType) {', $script);
        $this->assertStringContainsString('config.url = endpoint;', $script);
        $this->assertStringContainsString('config.data = [];', $script);
        $this->assertStringNotContainsString('autoLoad: type === activeType', $script, 'Hidden flow tabs should render an empty shell and load real backend data on first activation.');

        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        foreach ([
            "Route::get('/flows/account', 'FlowController@accountFlow')",
            "Route::get('/flows/deposits', 'FlowController@depositFlowSearch')",
            "Route::get('/flows/withdrawals', 'FlowController@withdrawalFlowSearch')",
            "Route::get('/flows/withdrawal-applications', 'FlowController@withdrawApplyFlowSearch')",
            "Route::get('/flows/direct-deposits', 'FlowController@directDepositFlowSearch')",
            "Route::get('/flows/direct-withdrawals', 'FlowController@directWithdrawalFlowSearch')",
            "Route::get('/flows/direct-agent-deposits', 'FlowController@directDepositFlowSearch')",
            "Route::get('/flows/direct-agent-withdrawals', 'FlowController@directWithdrawalFlowSearch')",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, $route . ' route alias is missing.');
        }
    }

    public function test_flow_withdraw_source_select_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/flow/index.blade.php')) ?: '';
        $script = $this->publicScript('front/layui/flow/index.js');

        $this->assertMatchesRegularExpression(
            '/<select\s+name="withdraw_source"[\s\S]*<\/select>/',
            $blade,
            'Withdrawal source must be a Layui select, not a free text input.'
        );
        $this->assertStringContainsString('.flow-page .J_withdrawSource.is-hidden { visibility: hidden;', $blade);
        $this->assertStringContainsString("toggleClass('is-hidden', !show)", $script);
        $this->assertStringNotContainsString('.toggle(show)', $script, 'Flow tabs must reserve withdraw source space to avoid first-open layout shaking.');
        $this->assertStringNotContainsString('name="remark"', $blade);
        $this->assertStringNotContainsString('name="remarks"', $blade);
        $this->assertStringNotContainsString('name="comment"', $blade);
    }

    public function test_flow_withdraw_rows_use_display_order_and_source_text(): void
    {
        $withdrawController = file_get_contents(app_path('Http/Controllers/Front/WithdrawController.php')) ?: '';
        $withdrawService = file_get_contents(app_path('Services/Withdrawal/WithdrawalOrderService.php')) ?: '';
        $flowController = file_get_contents(app_path('Http/Controllers/Front/FlowController.php')) ?: '';
        $flowScript = $this->publicScript('front/layui/flow/index.js');

        $this->assertStringContainsString('private function withdrawDisplayOrderNo(WithdrawRecord $record): string', $withdrawController);
        $this->assertStringContainsString('$row[\'order_no\'] = $this->withdrawDisplayOrderNo($record);', $withdrawController);
        $this->assertStringContainsString('$row[\'withdrawalType\'] = $this->withdrawSourceText($record);', $withdrawController);
        $this->assertStringContainsString('$row[\'withdrawalType2\'] = FrontLegacyData::withdrawStatusText($record->status);', $withdrawController);
        $this->assertStringNotContainsString("'reject_reason'  => 'WBIN-'", $withdrawController);
        $this->assertStringContainsString("'reject_reason' => ''", $withdrawService);

        $this->assertStringContainsString('private function withdrawDisplayOrderNo($row): string', $flowController);
        $this->assertStringContainsString('private function withdrawSourceText($row): string', $flowController);
        $this->assertStringContainsString('private function applyWithdrawSourceFilter($query, string $withdrawSource): void', $flowController);
        $accountFlowMethod = $this->sourceBetween($flowController, 'public function accountFlow(Request $request): JsonResponse', 'private function typedFlow');
        $typedFlowMethod = $this->sourceBetween($flowController, 'private function typedFlow(Request $request, int $agentId, string $flowType)', 'public function depositExport');
        $withdrawSourceFilterMethod = $this->sourceBetween($flowController, 'private function applyWithdrawSourceFilter($query, string $withdrawSource): void', 'public function depositExport');
        $this->assertStringContainsString("'local_order_no', 'third_order_no'", $accountFlowMethod, 'Account-flow withdraw rows must keep both local and third-party order numbers.');
        $this->assertStringNotContainsString("'local_order_no as order_no'", $accountFlowMethod, 'Account-flow all rows must normalize display order numbers after pagination.');
        $this->assertStringContainsString("->through(function (\$row) {", $flowController, 'Account-flow union rows must be normalized after pagination.');
        $this->assertStringContainsString("\$row->order_no = \$this->withdrawDisplayOrderNo(\$row);", $flowController, 'Account-flow withdraw rows must use the same display order number as withdrawal history.');
        $this->assertStringContainsString("\$row->flow_type_text = \$this->withdrawSourceText(\$row);", $flowController, 'Account-flow withdraw rows must expose the real withdrawal source text.');
        $this->assertStringContainsString("'order_no' => \$this->withdrawDisplayOrderNo(\$row)", $flowController);
        $this->assertStringContainsString("'withdrawalType' => \$this->withdrawSourceText(\$row)", $flowController);
        $this->assertStringContainsString("'withdrawalType2' => FrontLegacyData::withdrawStatusText(\$row->status)", $flowController);
        $this->assertStringContainsString("'directdrawalComment' => \$this->withdrawSourceText(\$row)", $flowController);
        $this->assertStringContainsString("\$this->applyWithdrawSourceFilter(\$query, (string) \$request->input('withdraw_source'));", $typedFlowMethod);
        $this->assertStringNotContainsString("\$query->where('bank_name', 'like', '%' . \$request->input('withdraw_source') . '%');", $typedFlowMethod);
        $this->assertStringContainsString("__('front.bank_transfer')", $withdrawSourceFilterMethod);
        $this->assertStringContainsString("__('front.crypto_currency')", $withdrawSourceFilterMethod);
        $this->assertStringContainsString("whereNotNull('bank_name')", $withdrawSourceFilterMethod);
        $this->assertStringContainsString("where('bank_name', '<>', '')", $withdrawSourceFilterMethod);
        $this->assertStringContainsString("orWhereNull('bank_name')", $withdrawSourceFilterMethod);
        $this->assertStringContainsString("orWhere('bank_name', '')", $withdrawSourceFilterMethod);

        $this->assertStringContainsString("column('withdrawalType', 'front.withdraw_type'", $flowScript);
        $this->assertStringContainsString("column('withdrawalType2', 'front.apply_status'", $flowScript);
        $this->assertStringContainsString("column('directdrawalComment', 'front.withdraw_type'", $flowScript);
    }

    public function test_remark_comment_fields_not_query_filters(): void
    {
        $restrictedNames = ['remark', 'remarks', 'comment'];
        $violations = [];

        foreach ($this->filesUnder(resource_path('front/layui'), 'blade.php') as $file) {
            $content = file_get_contents($file) ?: '';
            foreach ($this->extractPhpArrayBlocks($content, "'filters' => [") as $block) {
                foreach ($restrictedNames as $name) {
                    if (preg_match("/'name'\\s*=>\\s*'" . preg_quote($name, '/') . "'/", $block)) {
                        $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file) . ' uses ' . $name;
                    }
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Remark/comment fields may be submitted or displayed, but must not be query filters.');
    }

    public function test_crmui_front_page_definitions_do_not_use_remark_comment_query_filters(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Front/PageController.php')) ?: '';
        $restrictedNames = ['remark', 'remarks', 'comment'];
        $violations = [];

        foreach ($this->extractPhpArrayBlocks($controller, "'filters' => [") as $block) {
            foreach ($restrictedNames as $name) {
                if (preg_match("/(?:'name'\\s*=>\\s*'" . preg_quote($name, '/') . "'|'" . preg_quote($name, '/') . "')/", $block)) {
                    $violations[] = $name . ' in ' . trim(substr($block, 0, 90));
                }
            }
        }

        $this->assertSame([], $violations, 'CrmUI/Naive front pages must not expose remark/comment fields as query filters.');
    }

    public function test_news_timeline_module_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/news/index.blade.php')) ?: '';
        $layui = $this->publicScript('front/layui/module-page.js');
        $controller = file_get_contents(app_path('Http/Controllers/Front/NewsController.php')) ?: '';
        $partial = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';

        $this->assertStringContainsString("'pageClass' => 'news-timeline-module'", $blade);
        $this->assertStringContainsString("'timeline' => 'news'", $blade);
        $this->assertStringContainsString("'api' => '/api/front/news'", $blade);
        $this->assertStringContainsString("'method' => 'GET'", $blade);
        $this->assertStringContainsString('data-timeline="{{ $timeline }}"', $partial);
        $this->assertStringContainsString('function renderNewsTimeline', $layui);
        $this->assertStringContainsString('layui-timeline', $layui);
        $this->assertStringContainsString('<h3 class="module-news-title">', $layui);
        $this->assertStringContainsString('function openNewsDetailModal', $layui);
        $this->assertStringContainsString('data-initial-news-id=', $partial);
        $this->assertStringNotContainsString('data-news-detail-row', $layui);
        $this->assertStringNotContainsString("$('#moduleNewsTimeline').on('click', '[data-news-detail-row]'", $layui);

        $this->assertStringContainsString("'content' =>", $controller);
    }

    public function test_module_pages_no_mock_data(): void
    {
        $partial = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';
        $module = $this->publicScript('front/layui/module-page.js');
        $combined = $partial . "\n" . $module;

        foreach (['mockWhenEmpty', 'data-mock-when-empty', 'mockValue', 'mockRows', 'mockSummary', 'renderMockData', 'MOCK-', 'demo_user_', 'uploads/voucher/demo_'] as $needle) {
            $this->assertStringNotContainsString($needle, $combined, 'Layui module pages must not generate local mock data: ' . $needle);
        }
    }

    public function test_trade_symbol_dynamic_filter_options(): void
    {
        $position = file_get_contents(resource_path('front/layui/position/summary.blade.php')) ?: '';
        $openOrders = file_get_contents(resource_path('front/layui/order/open.blade.php')) ?: '';
        $closedOrders = file_get_contents(resource_path('front/layui/order/closed.blade.php')) ?: '';
        $partial = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';
        $layuiModule = $this->publicScript('front/layui/module-page.js');
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $controllerPath = app_path('Http/Controllers/Front/TradeSymbolController.php');
        $frontLegacyData = file_get_contents(app_path('Support/FrontLegacyData.php')) ?: '';
        $positionController = file_get_contents(app_path('Http/Controllers/Front/PositionController.php')) ?: '';
        $orderController = file_get_contents(app_path('Http/Controllers/Front/OrderController.php')) ?: '';

        $this->assertFileExists($controllerPath, 'Trade symbol options must have a dedicated controller.');
        $controller = file_get_contents($controllerPath) ?: '';

        foreach ([$position, $openOrders, $closedOrders] as $source) {
            $this->assertStringContainsString("'name' => 'symbol', 'label' => 'front.symbol', 'type' => 'select', 'dynamicOptions' => 'symbols'", $source);
        }

        $this->assertStringContainsString('@if(!empty($filter[\'dynamicOptions\'])) data-dynamic-options="{{ $filter[\'dynamicOptions\'] }}" @endif', $partial);
        $this->assertStringContainsString("symbols: '/api/front/trade-symbols'", $layuiModule);
        $this->assertStringContainsString("symbols: 'GET'", $layuiModule);
        $this->assertStringContainsString("method: dynamicOptionMethods[key] || 'POST'", $layuiModule);
        $this->assertStringContainsString('loadDynamicFilterOptions()', $layuiModule);

        $this->assertStringContainsString("Route::get('/trade-symbols', 'TradeSymbolController@index')->name('front_api_trade_symbols');", $routes);
        $this->assertStringContainsString("DB::table('symbol_prices')", $controller);
        $this->assertStringContainsString('distinct()', $controller);
        $this->assertStringNotContainsString('mock', strtolower($controller));

        $this->assertStringContainsString('public static function applySymbolFilter($query, Request $request, string $column = \'symbol\'): void', $frontLegacyData);
        $this->assertStringContainsString('$query->where($column, $request->input(\'symbol\'));', $frontLegacyData, 'Symbol select values must use exact backend filtering, not fuzzy mock-style matching.');
        $this->assertStringContainsString('self::applySymbolFilter($tradeQuery, $request);', $frontLegacyData, 'Account/position financial summaries must respect selected trade symbol.');
        $this->assertGreaterThanOrEqual(3, substr_count($positionController, 'FrontLegacyData::applySymbolFilter($query, $request);'), 'Position summary, search and detail queries must all apply the same real symbol filter.');
        $this->assertStringContainsString('$this->openCountForUser($agentId, $request)', $positionController);
        $this->assertStringContainsString('$this->floatingProfitForUser($agentId, $request)', $positionController);
        $openCountForScopeMethod = $this->sourceBetween($positionController, 'private function openCountForScope(array $userIds, Request $request): int', 'private function floatingProfitForUser');
        $floatingProfitForUserMethod = $this->sourceBetween($positionController, 'private function floatingProfitForUser(int $userId, Request $request): string', 'private function agentLevelPayload');
        $this->assertStringContainsString('->open();', $openCountForScopeMethod, 'Position summary open count must reuse the UserTrade open scope.');
        $this->assertStringContainsString('->open();', $floatingProfitForUserMethod, 'Position summary floating profit must reuse the UserTrade open scope.');
        $this->assertStringNotContainsString("where('close_time', '1970-01-01 00:00:00')", $openCountForScopeMethod . $floatingProfitForUserMethod, 'Position summary open metrics must not duplicate the MT4 open sentinel.');
        $positionDetailMethod = $this->sourceBetween($positionController, 'public function positionDetail(Request $request): JsonResponse', 'public function clickSearch');
        $this->assertStringContainsString('$query->closed();', $positionDetailMethod, 'Position detail closed status filter must reuse the UserTrade closed scope.');
        $this->assertStringContainsString('$query->open();', $positionDetailMethod, 'Position detail open status filter must reuse the UserTrade open scope.');
        $this->assertStringNotContainsString("where('close_time', '>', '1970-01-01 00:00:00')", $positionDetailMethod, 'Position detail must not duplicate the closed order sentinel.');
        $this->assertStringNotContainsString("where('close_time', '1970-01-01 00:00:00')", $positionDetailMethod, 'Position detail must not duplicate the open order sentinel.');
        $this->assertGreaterThanOrEqual(2, substr_count($orderController, 'FrontLegacyData::applySymbolFilter($query, $request);'), 'Open and closed order lists must filter by selected trade symbol.');
        $this->assertStringNotContainsString("where('symbol', 'like'", $orderController, 'Trade symbol is a select option and should not use fuzzy matching.');
    }

    /**
     * 验证本人汇总与旧代理树汇总分别使用各自权威返佣来源。
     *
     * @return void 本人汇总读取 commission_records；旧代理树汇总按 MT4 DBCN 余额记录逐行聚合。
     */
    public function test_position_summary_rebate_sources(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Front/PositionController.php')) ?: '';

        $this->assertStringContainsString('use App\Models\CommissionRecord;', $controller);
        $this->assertStringContainsString('private function totalRebateForScope(array $userIds, Request $request): string', $controller);
        $this->assertStringNotContainsString("\$row['total_rebate'] = 0;", $controller, 'Position summary must not hard-code total rebate to zero.');
        $this->assertStringContainsString("\$sumData['total_rebate'] = \$this->totalRebateForScope([\$agentId], \$request);", $controller);
        $this->assertStringNotContainsString("\$row['total_rebate'] = \$this->totalRebateForScope([\$agentId], \$request);", $controller, 'Self summary row, totalRow and summary should share the same real total_rebate value from sumData.');
        $this->assertStringContainsString('if ($cmd === 6 && $this->isRebateComment($comment))', $controller);
        $this->assertStringContainsString("\$sum['total_rebate'] += \$profit;", $controller);
        $this->assertStringContainsString('$totalRow = $this->sumLegacyAgentSummaryRows($summaryRows);', $controller);
        $this->assertStringNotContainsString("\$totalRow['total_rebate'] = \$this->totalRebateForScope(\$userIds, \$request);", $controller, 'Legacy agent tree total rebate must keep the old MT4 DBCN balance contract.');

        $method = $this->sourceBetween($controller, 'private function totalRebateForScope(array $userIds, Request $request): string', 'private function selfLoginIdSumData');
        $this->assertStringContainsString("CommissionRecord::whereIn('agent_id', \$ids)", $method);
        $this->assertStringContainsString('FrontLegacyData::applyCreatedAtFilter($query, $request);', $method);
        $this->assertStringContainsString("return FrontLegacyData::money(\$query->sum('commission_amount'));", $method);
    }

    public function test_order_pages_reuse_scoped_order_queries(): void
    {
        $openBlade = file_get_contents(resource_path('front/layui/order/open.blade.php')) ?: '';
        $closedBlade = file_get_contents(resource_path('front/layui/order/closed.blade.php')) ?: '';
        $controller = file_get_contents(app_path('Http/Controllers/Front/OrderController.php')) ?: '';

        foreach ([$openBlade, $closedBlade] as $blade) {
            $this->assertStringContainsString("'action' => 'showOrderInfo'", $blade, 'Order ticket must remain the order detail entry.');
            $this->assertStringContainsString("'action' => 'showUserInfo'", $blade, 'User ID must remain the user detail entry.');
            $this->assertStringNotContainsString("'rowActions' => [\n        ['type' => 'showOrderInfo', 'label' => 'common.detail'", $blade, 'Order pages must not render a duplicate detail button.');
        }

        $this->assertStringContainsString('if ((int) $user->account_type !== 1)', $controller, 'Order user detail must not attach agent level fields to normal customers.');
        $this->assertStringContainsString("'order_chain'", $controller, 'Order list rows must expose the scoped order user chain for the detail popup.');
        $this->assertStringContainsString('private function orderChain(UserInfo $user = null, int $viewerAgentId): array', $controller);
        $openOrdersMethod = $this->sourceBetween($controller, 'public function openOrders(Request $request): JsonResponse', 'public function openOrderSearch');
        $openOrderDetailMethod = $this->sourceBetween($controller, 'public function openOrderDetail', 'public function closeOrderDetail');
        $closedOrdersMethod = $this->sourceBetween($controller, 'public function closedOrders(Request $request): JsonResponse', 'public function closeOrderSearch');
        $closeOrderDetailMethod = $this->sourceBetween($controller, 'public function closeOrderDetail', 'private function userDetail');
        $legacyOrderDetailMethod = $this->sourceBetween($controller, 'private function legacyOrderDetailHtml', 'private function legacyDetailItem');
        $this->assertStringContainsString('->open()', $openOrdersMethod, 'Open order list must use the UserTrade open scope instead of a local hardcoded close_time rule.');
        $this->assertStringContainsString('->open()', $openOrderDetailMethod, 'Open order detail must use the UserTrade open scope instead of a local hardcoded close_time rule.');
        $this->assertStringContainsString('->closed()', $closedOrdersMethod, 'Closed order list must use the UserTrade closed scope instead of a local hardcoded close_time rule.');
        $this->assertStringContainsString('->closed()', $closeOrderDetailMethod, 'Closed order detail must use the UserTrade closed scope instead of a local hardcoded close_time rule.');
        foreach ([$openOrderDetailMethod, $closeOrderDetailMethod] as $detailMethod) {
            $this->assertStringContainsString('Request $request', $detailMethod, 'Legacy order detail routes must know the current logged-in front account.');
            $this->assertStringContainsString('$userInfo = $this->legacyFrontUserInfo($request);', $detailMethod, 'Legacy order detail routes must read the current front user before loading an order.');
            $this->assertStringContainsString('FrontLegacyData::applyAllowedUserFilter($query, $request, (int) $userInfo->user_id);', $detailMethod, 'Legacy order detail routes must not expose orders outside the current agent/customer scope.');
            $this->assertStringContainsString('$this->legacyOrderDetailHtml($trade, $userInfo,', $detailMethod, 'Legacy order detail HTML must receive the current viewer for scoped chain and rebate details.');
        }
        $this->assertStringContainsString('legacyOrderChainHtml($this->orderChain($trade->user, (int) $viewer->user_id))', $legacyOrderDetailMethod);
        $this->assertStringContainsString('legacyCommissionDetailsHtml($this->commissionService->orderCommissionDetails($trade, (int) $viewer->user_id))', $legacyOrderDetailMethod);
        $this->assertStringContainsString("\u{5F53}\u{524D}\u{94FE}\u{8DEF}", $controller);
        $this->assertStringContainsString("\u{8FD4}\u{4F63}\u{660E}\u{7EC6}", $controller);
        $this->assertStringContainsString("<th>\u{4EE3}\u{7406}\u{7EA7}\u{522B}</th>", $controller);
        $this->assertStringContainsString("<th>\u{8FD4}\u{4F63}\u{6BD4}\u{4F8B}</th>", $controller);
        $this->assertStringNotContainsString("->where('close_time', '1970-01-01 00:00:00')", $openOrdersMethod . $openOrderDetailMethod, 'Open order queries must not duplicate the open order sentinel.');
        $this->assertStringNotContainsString("->where('close_time', '<=', '1970-01-01 00:00:00')", $openOrdersMethod . $openOrderDetailMethod, 'Open order detail must not broaden the open order sentinel.');
        $this->assertStringNotContainsString("->where('close_time', '>', '1970-01-01 00:00:00')", $closedOrdersMethod . $closeOrderDetailMethod, 'Closed order queries must not narrow real table data with a duplicated close_time sentinel.');

        $layui = $this->publicScript('front/layui/module-page.js');
        $this->assertStringContainsString('function (row)', $layui);
        $this->assertStringContainsString('renderOrderChainDetails(row)', $layui);
    }

    public function test_crmui_order_pages_use_ticket_link_without_duplicate_detail_button(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Front/PageController.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';

        foreach (['/front-crmui/order/open', '/front-crmui/order/closed', '/front-naive/order/open', '/front-naive/order/closed'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-key="ticket"', $html, 'Order ticket column must stay visible: ' . $url);
            $this->assertStringContainsString('data-action="showOrderInfo"', $html, 'Order ticket column must be the detail entry: ' . $url);
            $this->assertStringNotContainsString('data-crmui-action-column', $html, 'Order pages must not render a duplicate operations column: ' . $url);
            $this->assertStringNotContainsString('data-crmui-row-action="detail"', $html, 'Order pages must not render a duplicate detail button: ' . $url);
        }

        $this->assertStringContainsString("['key' => 'ticket', 'action' => 'showOrderInfo'", $controller);
        $this->assertStringNotContainsString("'rowActions' => [['key' => 'detail', 'local' => true]]", $this->sourceBetween($controller, "'order/open' => [", "'agent/sub' => ["));
        $this->assertStringContainsString('function crmUiActionCellHtml', $script);
        $this->assertStringContainsString(".data('crmuiRow', rows[rowIndex] || {})", $script);
        $this->assertStringContainsString("$(document).on('click', '[data-crmui-cell-action=\"showOrderInfo\"]'", $script);
    }

    public function test_closed_order_detail_legacy_json_contract(): void
    {
        $now = time();
        $agentId = 991001;
        $customerId = 991002;
        $ticket = 991777001;

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $agentId],
            [
                'email' => 'front-closed-orders-agent@example.test',
                'password' => 'front-closed-orders-test',
                'account_type' => 1,
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
            ]
        );
        $loginId = (int) DB::table('user_logins')->where('user_id', $agentId)->value('id');

        foreach ([
            [$agentId, 'Closed Order Agent', 1, 0, (string) $agentId],
            [$customerId, 'Closed Order Customer', 2, $agentId, $agentId . ',' . $customerId],
        ] as [$userId, $userName, $accountType, $parentId, $familyTree]) {
            DB::table('user_infos')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'login_id' => $userId === $agentId ? $loginId : 0,
                    'user_name' => $userName,
                    'phone' => '',
                    'gender' => 1,
                    'avatar' => null,
                    'level_id' => $accountType === 1 ? 1 : 0,
                    'group_id' => 0,
                    'parent_id' => $parentId,
                    'account_type' => $accountType,
                    'family_tree' => $familyTree,
                    'total_funds' => 0,
                    'used_margin' => 0,
                    'avail_margin' => 0,
                    'equity' => 0,
                    'effective_credit' => 0,
                    'risk_ratio' => 0,
                    'margin_amount' => 0,
                    'leverage' => 0,
                    'cust_vol' => '0',
                    'pay_provider_id' => 0,
                    'equity_ratio' => 0,
                    'comm_rate' => 0,
                    'is_ecn' => 0,
                    'follow_parent_ecn' => 0,
                    'auth_status' => 1,
                    'is_mt4_synced' => 1,
                    'is_mt4_enabled' => 1,
                    'is_mt4_readonly' => 0,
                    'is_withdrawal_allowed' => 0,
                    'is_deposit_allowed' => 0,
                    'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
                    'original_group' => '',
                    'mt4_group' => '',
                    'mt4_code' => 0,
                    'trading_mode' => 0,
                    'settle_method' => 1,
                    'settle_cycle' => 1,
                    'country' => '',
                    'city' => '',
                    'state' => '',
                    'address' => '',
                    'is_gift_allowed' => 0,
                    'data_source' => 0,
                    'remark' => 'front closed orders regression test',
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('agent_descendants')->updateOrInsert(
            ['agent_id' => $agentId, 'descendant_id' => $customerId],
            [
                'descendant_type' => 2,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('user_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'user_id' => $customerId,
                'symbol' => 'XAUUSD',
                'digits' => 2,
                'cmd' => 0,
                'volume' => 10,
                'open_time' => '2026-06-01 09:00:00',
                'open_price' => 2300.12,
                'stop_loss' => 0,
                'take_profit' => 0,
                'close_time' => '2026-06-01 10:00:00',
                'expiration' => null,
                'reason' => 0,
                'conv_rate1' => 1,
                'conv_rate2' => 1,
                'commission' => -3.5,
                'commission_agent' => 12.25,
                'swaps' => 0,
                'close_price' => 2310.12,
                'profit' => 100,
                'taxes' => 0,
                'comment' => 'real closed order regression',
                'internal_id' => 0,
                'margin_rate' => 1,
                'timestamp_val' => $now,
                'magic' => 0,
                'gw_volume' => 0,
                'gw_open_price' => 0,
                'gw_close_price' => 0,
                'modify_time' => '2026-06-01 10:00:00',
                'settlement_status' => 1,
                'settled_at' => '2026-06-01 10:05:00',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/orders/closed?orderId=' . $ticket . '&per_page=5');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            // 旧兼容 JSON 契约中 ticket（MT4 订单号）为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.ticket', (string) $ticket)
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.user_id', (string) $customerId)
            ->assertJsonPath('data.list.data.0.symbol', 'XAUUSD');
    }

    public function test_closed_order_list_scopes_chain_and_commission_details_to_current_agent(): void
    {
        $now = time();
        $rootAgentId = 983001;
        $viewerAgentId = 983002;
        $customerId = 983003;
        $ticket = 983777001;
        $userIds = [$rootAgentId, $viewerAgentId, $customerId];

        DB::table('commission_records')->where('mt4_order_id', $ticket)->delete();
        DB::table('user_trades')->where('ticket', $ticket)->delete();
        DB::table('agent_descendants')
            ->whereIn('agent_id', [$rootAgentId, $viewerAgentId])
            ->orWhereIn('descendant_id', [$viewerAgentId, $customerId])
            ->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();

        DB::table('agent_levels')->updateOrInsert(
            ['level_code' => 1],
            [
                'name' => 'Regression Root Level',
                'max_commission' => 100,
                'min_commission' => 80,
                'user_commission' => 20,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('agent_levels')->updateOrInsert(
            ['level_code' => 2],
            [
                'name' => 'Regression Viewer Level',
                'max_commission' => 79,
                'min_commission' => 50,
                'user_commission' => 20,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        $rootLevelId = (int) DB::table('agent_levels')->where('level_code', 1)->value('id');
        $viewerLevelId = (int) DB::table('agent_levels')->where('level_code', 2)->value('id');

        $groupId = (int) DB::table('group_configs')->where('name', 'Regression Order Chain Group')->value('id');
        if ($groupId <= 0) {
            $groupId = (int) DB::table('group_configs')->insertGetId([
                'pair_id' => null,
                'name' => 'Regression Order Chain Group',
                'radix' => 50,
                'category' => 1,
                'has_commission' => 1,
                'is_enabled' => 1,
                'is_ecn' => 0,
                'is_default' => 0,
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }

        DB::table('spread_configs')->updateOrInsert(
            ['agent_group_id' => $groupId],
            [
                'spread' => 12,
                'spread_ratio' => 1,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $viewerAgentId],
            [
                'email' => 'front-order-chain-viewer@example.test',
                'password' => Hash::make('123456'),
                'account_type' => 1,
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
            ]
        );
        $loginId = (int) DB::table('user_logins')->where('user_id', $viewerAgentId)->value('id');

        $upsertUser = static function (int $userId, array $overrides) use ($now, $groupId): void {
            DB::table('user_infos')->updateOrInsert(
                ['user_id' => $userId],
                array_merge([
                    'login_id' => 0,
                    'user_name' => 'Regression Chain User ' . $userId,
                    'phone' => '',
                    'gender' => 1,
                    'avatar' => null,
                    'level_id' => 0,
                    'group_id' => $groupId,
                    'parent_id' => 0,
                    'account_type' => 2,
                    'family_tree' => (string) $userId,
                    'total_funds' => 0,
                    'used_margin' => 0,
                    'avail_margin' => 0,
                    'equity' => 0,
                    'effective_credit' => 0,
                    'risk_ratio' => 0,
                    'margin_amount' => 0,
                    'leverage' => 0,
                    'cust_vol' => '0',
                    'pay_provider_id' => 0,
                    'equity_ratio' => 0,
                    'comm_rate' => 0,
                    'is_ecn' => 0,
                    'follow_parent_ecn' => 0,
                    'auth_status' => 1,
                    'is_mt4_synced' => 1,
                    'is_mt4_enabled' => 1,
                    'is_mt4_readonly' => 0,
                    'is_withdrawal_allowed' => 0,
                    'is_deposit_allowed' => 0,
                    'is_agent_confirmed' => 0,
                    'original_group' => '',
                    'mt4_group' => '',
                    'mt4_code' => 0,
                    'trading_mode' => 0,
                    'settle_method' => 1,
                    'settle_cycle' => 1,
                    'country' => '',
                    'city' => '',
                    'state' => '',
                    'address' => '',
                    'is_gift_allowed' => 0,
                    'data_source' => 0,
                    'remark' => 'front order chain regression test',
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ], $overrides)
            );
        };

        $upsertUser($rootAgentId, [
            'user_name' => 'Root Hidden Agent',
            'level_id' => $rootLevelId,
            'account_type' => 1,
            'family_tree' => (string) $rootAgentId,
            'comm_rate' => 90,
            'is_agent_confirmed' => 1,
        ]);
        $upsertUser($viewerAgentId, [
            'login_id' => $loginId,
            'user_name' => 'Current Viewer Agent',
            'level_id' => $viewerLevelId,
            'parent_id' => $rootAgentId,
            'account_type' => 1,
            'family_tree' => $rootAgentId . ',' . $viewerAgentId,
            'comm_rate' => 60,
            'is_agent_confirmed' => 1,
        ]);
        $upsertUser($customerId, [
            'user_name' => 'Scoped Order Customer',
            'gender' => 2,
            'parent_id' => $viewerAgentId,
            'account_type' => 2,
            'family_tree' => $rootAgentId . ',' . $viewerAgentId . ',' . $customerId,
            'comm_rate' => 20,
        ]);

        foreach ([
            [$rootAgentId, $viewerAgentId, 1, 1, 1],
            [$rootAgentId, $customerId, 2, 0, 2],
            [$viewerAgentId, $customerId, 2, 1, 1],
        ] as [$agentId, $descendantId, $type, $direct, $depth]) {
            DB::table('agent_descendants')->updateOrInsert(
                ['agent_id' => $agentId, 'descendant_id' => $descendantId],
                [
                    'descendant_type' => $type,
                    'is_direct' => $direct,
                    'depth' => $depth,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('user_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'user_id' => $customerId,
                'symbol' => 'EURUSD',
                'digits' => 5,
                'cmd' => 0,
                'volume' => 100,
                'open_time' => '2026-06-02 09:00:00',
                'open_price' => 1.12001,
                'stop_loss' => 0,
                'take_profit' => 0,
                'close_time' => '2026-06-02 10:00:00',
                'expiration' => null,
                'reason' => 0,
                'conv_rate1' => 1,
                'conv_rate2' => 1,
                'commission' => -2.5,
                'commission_agent' => 43.21,
                'swaps' => 0,
                'close_price' => 1.12101,
                'profit' => 100,
                'taxes' => 0,
                'comment' => 'scoped chain regression',
                'internal_id' => 0,
                'margin_rate' => 1,
                'timestamp_val' => $now,
                'magic' => 0,
                'gw_volume' => 0,
                'gw_open_price' => 0,
                'gw_close_price' => 0,
                'modify_time' => '2026-06-02 10:00:00',
                'settlement_status' => 1,
                'settled_at' => '2026-06-02 10:05:00',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        foreach ([[$rootAgentId, 98.76, 'CHAIN-COMM-ROOT'], [$viewerAgentId, 43.21, 'CHAIN-COMM-VIEWER']] as [$agentId, $amount, $uniqueId]) {
            DB::table('commission_records')->updateOrInsert(
                ['unique_id' => $uniqueId],
                [
                    'agent_id' => $agentId,
                    'parent_id' => $agentId === $viewerAgentId ? $rootAgentId : 0,
                    'agent_profit' => 0,
                    'agent_volume' => 1,
                    'equity_value' => 0,
                    'equity_diff' => 0,
                    'settle_cycle' => 1,
                    'mt4_order_id' => $ticket,
                    'date_range' => '2026-06-02 - 2026-06-02',
                    'settle_status' => 2,
                    'fee' => 0,
                    'swap' => 0,
                    'commission_amount' => $amount,
                    'returned_amount' => $amount,
                    'deposit' => 0,
                    'real_amount' => $amount,
                    'data_type' => 'mainData',
                    'manual_reason' => '',
                    'remarks' => 'front order chain regression test',
                    'created_by' => 'test',
                    'updated_by' => 'test',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/orders/closed?orderId=' . $ticket . '&per_page=5');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.ticket', (string) $ticket)
            ->assertJsonPath('data.list.data.0.user_id', (string) $customerId);

        $chain = $response->json('data.list.data.0.order_chain');
        $this->assertSame(
            [$viewerAgentId, $customerId],
            array_map('intval', array_column($chain, 'user_id'))
        );
        $this->assertSame('Regression Viewer Level', $chain[0]['agent_level_name']);
        $this->assertSame('', $chain[1]['agent_level_name']);

        $details = $response->json('data.list.data.0.commission_details');
        $this->assertSame(
            [$viewerAgentId],
            array_map('intval', array_column($details, 'agent_id'))
        );
        $this->assertSame('Current Viewer Agent', $details[0]['agent_name']);
        $this->assertSame('Regression Viewer Level', $details[0]['agent_level']);
        $this->assertEquals(43.21, (float) $details[0]['commission_amount']);
        $this->assertSame('40%', $details[0]['rebate_ratio']);
    }

    public function test_withdraw_cancel_dashboard_share_open_closed_scopes(): void
    {
        $withdrawController = file_get_contents(app_path('Http/Controllers/Front/WithdrawController.php')) ?: '';
        $withdrawService = file_get_contents(app_path('Services/Withdrawal/WithdrawalOrderService.php')) ?: '';
        $cancelController = file_get_contents(app_path('Http/Controllers/Front/CancelController.php')) ?: '';
        $accountController = file_get_contents(app_path('Http/Controllers/Front/AccountController.php')) ?: '';
        $dashboardController = file_get_contents(app_path('Http/Controllers/Front/DashboardController.php')) ?: '';
        $bigNumberController = file_get_contents(app_path('Http/Controllers/Front/BigNumberController.php')) ?: '';
        $positionSummaryController = file_get_contents(app_path('Http/Controllers/Front/PositionSummaryController.php')) ?: '';
        $commissionService = file_get_contents(app_path('Services/CommissionService.php')) ?: '';

        $submitWithdrawMethod = $this->sourceBetween($withdrawController, 'public function submitWithdraw(Request $request): JsonResponse', 'public function withdraw_request');
        $withdrawRiskMethod = $this->sourceBetween($withdrawService, 'private function ', 'private function assertBankRules(');
        $cancelApplyMethod = $this->sourceBetween($cancelController, 'public function apply(Request $request): JsonResponse', 'public function ajaxCancelAccount');
        $cancelRiskMethod = $this->sourceBetween($cancelController, 'private function cancellationBusinessFailure(UserInfo $userInfo): ?array', 'private function hasPendingCancellation');
        $changeAccountMethod = $this->sourceBetween($accountController, 'public function changeAccountSave(Request $request): JsonResponse', 'public function voucherList');
        $dashboardDataMethod = $this->sourceBetween($dashboardController, 'public function dashboardData(Request $request): JsonResponse', 'public function frontMsg');
        $bigNumberOrderListMethod = $bigNumberController;
        $legacyPositionSummaryIndexMethod = $this->sourceBetween($positionSummaryController, 'public function index(Request $request): JsonResponse', 'public function search');
        $legacyPositionSummaryClickMethod = $this->sourceBetween($positionSummaryController, 'public function index(Request $request): JsonResponse', "\n}");

        $this->assertStringContainsString('WithdrawalOrderService::class', $submitWithdrawMethod);
        $this->assertStringContainsString('$this->cancellationBusinessFailure(', $cancelApplyMethod, 'Cancel apply must call the shared cancellation risk method.');
        foreach ([$withdrawRiskMethod, $cancelRiskMethod, $changeAccountMethod] as $method) {
            $this->assertStringContainsString('->open()', $method, 'Open order risk checks must reuse the UserTrade open scope.');
            $this->assertStringNotContainsString("where('close_time', '1970-01-01 00:00:00')", $method, 'Open order risk checks must not duplicate the MT4 open sentinel.');
        }

        $this->assertGreaterThanOrEqual(2, substr_count($dashboardDataMethod, '->open()'), 'Dashboard open order metrics must reuse the UserTrade open scope.');
        $this->assertStringContainsString('->closed()', $dashboardDataMethod, 'Dashboard closed order metrics must reuse the UserTrade closed scope.');
        $this->assertStringNotContainsString("where('close_time', '1970-01-01 00:00:00')", $dashboardDataMethod, 'Dashboard open metrics must not duplicate the MT4 open sentinel.');
        $this->assertStringNotContainsString("where('close_time', '>', '1970-01-01 00:00:00')", $dashboardDataMethod, 'Dashboard closed metrics must not duplicate the MT4 closed sentinel.');

        $this->assertStringContainsString('$query->open();', $bigNumberOrderListMethod, 'Big-number open order list must reuse the UserTrade open scope.');
        $this->assertStringContainsString('$query->closed();', $bigNumberOrderListMethod, 'Big-number closed order list must reuse the UserTrade closed scope.');
        $this->assertStringNotContainsString("where('close_time', '1970-01-01 00:00:00')", $bigNumberOrderListMethod, 'Big-number open order list must not duplicate the MT4 open sentinel.');
        $this->assertStringNotContainsString("where('close_time', '>', '1970-01-01 00:00:00')", $bigNumberOrderListMethod, 'Big-number closed order list must not duplicate the MT4 closed sentinel.');

        $this->assertStringContainsString('->open()', $legacyPositionSummaryIndexMethod, 'Legacy position summary overview must reuse the UserTrade open scope.');
        $this->assertStringContainsString('$query->closed();', $legacyPositionSummaryClickMethod, 'Legacy position detail closed status filter must reuse the UserTrade closed scope.');
        $this->assertStringContainsString('$query->open();', $legacyPositionSummaryClickMethod, 'Legacy position detail open status filter must reuse the UserTrade open scope.');
        $this->assertStringNotContainsString("where('close_time', '1970-01-01 00:00:00')", $legacyPositionSummaryIndexMethod . $legacyPositionSummaryClickMethod, 'Legacy position summary must not duplicate the MT4 open sentinel.');
        $this->assertStringNotContainsString("where('close_time', '>', '1970-01-01 00:00:00')", $legacyPositionSummaryClickMethod, 'Legacy position summary must not duplicate the MT4 closed sentinel.');

        $realtimeCommissionMethod = $this->sourceBetween($commissionService, 'public function calculateRealTimeCommission', 'public function calculateSettlement');
        $settlementMethod = $this->sourceBetween($commissionService, 'public function calculateSettlement', 'public function settleCommission');
        $this->assertStringContainsString('->open()', $realtimeCommissionMethod, 'Realtime commission service must reuse the UserTrade open scope.');
        $this->assertStringContainsString('->closed()', $settlementMethod, 'Commission settlement service must reuse the UserTrade closed scope.');
        $this->assertStringNotContainsString("where('close_time', '1970-01-01 00:00:00')", $realtimeCommissionMethod . $settlementMethod, 'CommissionService must not duplicate MT4 order state sentinels.');
        $this->assertStringNotContainsString("where('close_time', '>', '1970-01-01 00:00:00')", $realtimeCommissionMethod . $settlementMethod, 'CommissionService must not duplicate closed-order sentinels.');
    }

    public function test_realtime_commission_order_detail_action(): void
    {
        $realtimeBlade = file_get_contents(resource_path('front/layui/commission/realtime.blade.php')) ?: '';
        $layui = $this->publicScript('front/layui/module-page.js');

        $this->assertStringContainsString("'key' => 'ticket', 'label' => 'front.ticket', 'action' => 'showOrderInfo'", $realtimeBlade);
        $this->assertStringNotContainsString("'rowActions' => [\n        ['type' => 'showOrderInfo'", $realtimeBlade, 'Realtime commission order detail must only be opened from the ticket cell.');
        $this->assertStringContainsString("if (column.action === 'showOrderInfo')", $layui);
        $this->assertStringContainsString("$('#moduleTableBody').on('click', '.J_moduleCellAction', function (event)", $layui);
        $this->assertStringContainsString('event.preventDefault();', $layui);
        $this->assertStringContainsString('event.stopPropagation();', $layui);
        $this->assertStringContainsString('openOrderDetail(column.title || \'front.order_detail\'', $layui);
        $controller = file_get_contents(app_path('Http/Controllers/Front/CommissionController.php')) ?: '';
        $this->assertStringContainsString("'order_chain'", $controller, 'Realtime commission rows must expose the scoped order user chain for the detail popup.');
    }

    public function test_realtime_commission_order_detail_request_is_deduplicated(): void
    {
        $layui = $this->publicScript('front/layui/module-page.js');
        $openOrderDetail = $this->sourceBetween($layui, 'function openOrderDetail(titleKey, fields, row)', 'function renderUserDetailCharts(row)');

        $this->assertStringContainsString('var pendingOrderDetailRequests = {};', $layui, 'Realtime commission detail requests need an in-flight map keyed by order number.');
        $this->assertStringContainsString('var shouldFetchRealtimeDetail = isRealtimeCommissionPage() && orderId && !rowHasCommissionDetails(row);', $openOrderDetail);
        $this->assertStringContainsString('var detailRequestKey = String(orderId || \'\');', $openOrderDetail);
        $this->assertStringContainsString('if (shouldFetchRealtimeDetail && pendingOrderDetailRequests[detailRequestKey])', $openOrderDetail, 'Repeated clicks on the same realtime order must not send duplicate detail requests while the first request is still pending.');
        $this->assertStringContainsString('pendingOrderDetailRequests[detailRequestKey] = true;', $openOrderDetail);
        $this->assertStringContainsString('delete pendingOrderDetailRequests[detailRequestKey];', $openOrderDetail, 'The in-flight lock must be released after the detail request returns.');
        $this->assertMatchesRegularExpression(
            "/if \\(shouldFetchRealtimeDetail\\) \\{\\s*pendingOrderDetailRequests\\[detailRequestKey\\] = true;\\s*\\}\\s*layerIndex = openDetailModal\\(titleKey \\|\\| 'front\\.order_detail'/s",
            $openOrderDetail,
            'The duplicate-request guard must be set before the detail modal opens, otherwise a fast second click can enter the same fetch path twice.'
        );
    }

    public function test_agent_level_confirmation_get_route_contract(): void
    {
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $blade = file_get_contents(resource_path('front/layui/agent/confirm-level.blade.php')) ?: '';
        $layui = $this->publicScript('front/layui/module-page.js');
        $ajax = $this->publicScript('common/ajax.js');
        $controller = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';

        $this->assertStringContainsString("Route::get('/agents/level-confirmation', 'AgentController@confirmLevel')->name('front_api_agents_level_confirmation');", $routes);
        $this->assertStringContainsString("Route::post('/agents/level-confirmation/changes', 'AgentController@confirmLevelChange')->name('front_api_agents_level_confirmation_changes');", $routes);
        $matchedRoute = \Illuminate\Support\Facades\Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/api/front/agents/level-confirmation', 'GET')
        );
        $this->assertSame('front_api_agents_level_confirmation', $matchedRoute->getName());
        $this->assertContains('GET', $matchedRoute->methods());
        try {
            \Illuminate\Support\Facades\Route::getRoutes()->match(
                \Illuminate\Http\Request::create('/api/front/agents/level-confirmation', 'POST')
            );
            $this->fail('Agent level confirmation query route must use GET; POST is reserved for changes.');
        } catch (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $exception) {
            $this->assertTrue(true);
        }

        $this->assertStringContainsString("'method' => 'GET'", $blade);
        $this->assertStringContainsString('data-method="{{ $method }}"', file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '');
        $this->assertStringContainsString('var requestMethod = $page.attr(\'data-method\') || \'POST\';', $layui);
        $this->assertStringContainsString('method: requestMethod,', $layui);
        $this->assertStringContainsString("if ((opts.method || 'POST').toUpperCase() === 'GET')", $ajax);

        foreach (['data-comm-prop', 'data-choice-gid', 'data-def-gid', 'data-extra-val'] as $attribute) {
            $this->assertStringContainsString($attribute, $layui, 'Layui agent level option missing ' . $attribute . '.');
        }

        $this->assertStringContainsString('payload.agent_gId = $option.data(\'choice-gid\') || $option.val()', $layui);
        $this->assertStringContainsString('payload.comm_prop = $option.data(\'comm-prop\')', $layui);
        $this->assertStringContainsString('payload.extra_val = $option.data(\'extra-val\') || 0', $layui);

        $confirmLevelChange = $this->sourceBetween($controller, 'public function confirmLevelChange', 'public function groupChangeList');
        $this->assertStringContainsString('(float) $level->user_commission', $confirmLevelChange, 'Confirmed commission rate must come from the selected agent_levels row.');
        $this->assertStringNotContainsString("'comm_rate' => (float) \$request->input('comm_prop')", $confirmLevelChange, 'Do not trust front-end submitted commission rate when confirming a real agent level.');
    }

    public function test_profile_upload_fields_visual_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/profile/index.blade.php')) ?: '';
        $script = $this->publicScript('front/layui/profile/index.js');
        $css = file_get_contents(public_path('css/front/style.css')) ?: '';
        $fields = [
            'avatar',
            'id_card_front',
            'id_card_back',
            'bank_card_img',
            'bank_card_img_back',
            'bank_change_card_img',
            'bank_change_card_img_back',
        ];

        foreach ($fields as $field) {
            $this->assertStringContainsString('data-upload-field="' . $field . '"', $blade, $field . ' upload field wrapper is missing.');
            $this->assertStringContainsString('data-upload-status="' . $field . '"', $blade, $field . ' upload status is missing.');
            $this->assertStringContainsString('data-upload-clear="' . $field . '"', $blade, $field . ' upload clear button is missing.');
            $this->assertStringContainsString('data-upload-name="' . $field . '"', $blade, $field . ' upload file name is missing.');
            $this->assertStringContainsString('data-upload-size="' . $field . '"', $blade, $field . ' upload file size is missing.');
        }

        $this->assertStringContainsString('crm-profile-avatar-upload-card', $blade);
        $this->assertStringContainsString('crm-profile-upload-card', $blade);
        $this->assertStringNotContainsString('.profile-upload-field.is-card-upload { display: grid;', $blade);
        $this->assertStringContainsString('.crm-profile-upload-field.is-card-upload', $css);
        $this->assertStringContainsString('.crm-profile-upload-preview', $css);
        $this->assertStringContainsString('.crm-profile-upload-meta', $css);
        $profileUploadCss = $this->sourceBetween($css, '.crm-profile-shell {', '.crm-profile-upload-status.has-file');
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(/i', $profileUploadCss, 'Profile upload styles must consume theme tokens instead of hard-coded colors.');

        $this->assertStringNotContainsString('id="submitAvatar"', $blade, 'Avatar upload must not render a separate submit button.');
        $this->assertStringContainsString("bindPreviewUpload('#selectAvatar', '#avatarPreview', 'avatar')", $script);
        $this->assertStringContainsString("if (fieldName === 'avatar')", $script);
        $this->assertStringContainsString('uploadAvatarFile(file);', $script);
        $this->assertStringContainsString("bindPreviewUpload('#idCardFrontBtn', '#idCardFrontPreview', 'id_card_front')", $script);
        $this->assertStringContainsString("bindPreviewUpload('#idCardBackBtn', '#idCardBackPreview', 'id_card_back')", $script);
        $this->assertStringContainsString("bindPreviewUpload('#bankCardImgBtn', '#bankCardImgPreview', 'bank_card_img')", $script);
        $this->assertStringContainsString("bindPreviewUpload('#bankCardBackImgBtn', '#bankCardBackImgPreview', 'bank_card_img_back')", $script);
        $this->assertStringContainsString("bindPreviewUpload('#bankChangeCardBackImgBtn', '#bankChangeCardBackImgPreview', 'bank_change_card_img_back')", $script);
        $this->assertStringContainsString('function updateUploadVisual', $script);
        $this->assertStringContainsString('function resetUploadVisual', $script);
    }

    public function test_profile_upload_preview_accessibility_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/profile/index.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/profile/index_v2.blade.php')) ?: '';
        $script = $this->publicScript('front/layui/profile/index.js');
        $crmuiPartial = file_get_contents(resource_path('front/crmui/partials/module-page.blade.php')) ?: '';
        $crmuiScript = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $crmuiCss = file_get_contents(public_path('css/crmui/front.css')) ?: '';

        foreach ([
            'id_card_front',
            'id_card_back',
            'bank_card_img',
        ] as $field) {
            $this->assertStringContainsString('data-upload-preview="' . $field . '"', $blade, $field . ' upload thumbnail is missing.');
            $this->assertStringContainsString('data-image-preview', $blade, $field . ' upload thumbnail must expose a full-image preview target.');
            $this->assertMatchesRegularExpression(
                '/<img[^>]*data-upload-preview="' . preg_quote($field, '/') . '"[^>]*role="button"[^>]*tabindex="0"[^>]*>/',
                $blade,
                $field . ' upload thumbnail must be keyboard-focusable and exposed as a button.'
            );
            $this->assertStringContainsString('data-upload-preview="' . $field . '"', $v2Blade, $field . ' v2 upload thumbnail is missing.');
            $this->assertMatchesRegularExpression(
                '/class="[^"]*crm-profile-upload-preview[^"]*"[^>]*data-upload-preview="' . preg_quote($field, '/') . '"|data-upload-preview="' . preg_quote($field, '/') . '"[^>]*class="[^"]*crm-profile-upload-preview[^"]*"/',
                $v2Blade,
                $field . ' v2 upload thumbnail must use the shared clickable preview class.'
            );
            $this->assertStringContainsString('data-image-preview', $v2Blade, $field . ' v2 upload thumbnail must expose a full-image preview target.');
            $this->assertMatchesRegularExpression(
                '/<img[^>]*data-upload-preview="' . preg_quote($field, '/') . '"[^>]*role="button"[^>]*tabindex="0"[^>]*>/',
                $v2Blade,
                $field . ' v2 upload thumbnail must be keyboard-focusable and exposed as a button.'
            );
        }

        foreach ([
            'bank_card_img_back',
            'bank_change_card_img',
            'bank_change_card_img_back',
        ] as $field) {
            $this->assertStringContainsString('data-upload-preview="' . $field . '"', $blade, $field . ' upload thumbnail is missing.');
            $this->assertStringContainsString('data-image-preview', $blade, $field . ' upload thumbnail must expose a full-image preview target.');
            $this->assertMatchesRegularExpression(
                '/<img[^>]*data-upload-preview="' . preg_quote($field, '/') . '"[^>]*role="button"[^>]*tabindex="0"[^>]*>/',
                $blade,
                $field . ' upload thumbnail must be keyboard-focusable and exposed as a button.'
            );
        }

        $this->assertStringContainsString('function openProfileUploadPreview', $script);
        $this->assertStringContainsString("$(document).on('click', '.crm-profile-upload-preview[data-image-preview]'", $script);
        $this->assertStringContainsString("$(document).on('keydown', '.crm-profile-upload-preview[data-image-preview]'", $script);
        $this->assertStringContainsString("event.key !== 'Enter' && event.key !== ' '", $script);
        $this->assertStringContainsString(".attr('data-image-preview', result || uploadPreviewDefaults[fieldName] || '')", $script);
        $this->assertStringContainsString("removeAttr('data-image-preview')", $script);

        $this->assertStringContainsString('data-crmui-upload-preview', $crmuiPartial);
        $this->assertStringContainsString('function openCrmUiImagePreview', $crmuiScript);
        $this->assertStringContainsString('FileReader', $crmuiScript);
        $this->assertStringContainsString("$(document).on('click', '[data-crmui-upload-preview]'", $crmuiScript);
        $this->assertStringContainsString('.crmui-upload-preview', $crmuiCss);
        $this->assertStringContainsString('cursor: zoom-in;', $crmuiCss);
    }

    public function test_module_page_api_map_uses_readable_urls(): void
    {
        $script = $this->publicScript('front/layui/module-page.js');
        $customerBlade = file_get_contents(resource_path('front/layui/agent/customers.blade.php')) ?: '';
        $positionBlade = file_get_contents(resource_path('front/layui/position/summary.blade.php')) ?: '';

        $this->assertStringContainsString('var API = {', $script);
        $this->assertStringContainsString("usersShow: '/api/front/users/{user}'", $script);
        $this->assertStringContainsString("agentSubList: '/api/front/agents/direct'", $script);
        $this->assertStringContainsString("commissionTransferAgents: '/api/front/commissions/transfer-agent-options'", $script);
        $this->assertStringNotContainsString("commissionTransferAgents: '/api/front/commission-transfer-agents'", $script);
        $this->assertStringNotContainsString('function apiRouteName', $script);
        $this->assertStringNotContainsString('routeRequestOptions', $script);
        $this->assertStringContainsString("'api' => '/api/front/users/{user}'", $customerBlade . $positionBlade);
        $this->assertStringNotContainsString("'api' => 'front_api_users_show'", $customerBlade . $positionBlade);

        $this->assertStringNotContainsString('url: column.api,', $script, '命名接口如 front_api_userDetail 不能作为相对 URL 发送，否则会请求到 /front/agent/front_api_userDetail。');
    }

    public function test_front_legacy_api_urls_removed_from_module_assets(): void
    {
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $sources = [
            'public/js/apps/front/layui/module-page.js' => $this->publicScript('front/layui/module-page.js'),
        ];

        foreach ($this->filesUnder(resource_path('front/layui'), 'blade.php') as $file) {
            $sources[str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file)] = file_get_contents($file) ?: '';
        }

        $legacyUrls = [
            '/api/front/accountBalance',
            '/api/front/accountInfo',
            '/api/front/voucherList',
            '/api/front/submitVoucher',
            '/api/front/cancelStatus',
            '/api/front/cancelApply',
            '/api/front/agentSubList',
            '/api/front/agentCustomerList',
            '/api/front/agentConfirmLevel',
            '/api/front/agentConfirmLevelChange',
            '/api/front/agentGroupChangeList',
            '/api/front/agentGroupChange',
            '/api/front/directUserCommTrans',
            '/api/front/commissionRealTime',
            '/api/front/commissionHistory',
            '/api/front/commissionTransfer',
            '/api/front/depositHistory',
            '/api/front/openOrders',
            '/api/front/closedOrders',
            '/api/front/positionSummary',
            '/api/front/newsList',
            '/api/front/giftList',
            '/api/front/giftAddressList',
            '/api/front/giftAddAddress',
            '/api/front/giftUpdateAddress',
            '/api/front/giftDeleteAddress',
            '/api/front/profile/update',
        ];
        $violations = [];

        foreach ($sources as $path => $source) {
            foreach ($legacyUrls as $url) {
                if (str_contains($source, $url)) {
                    $violations[] = $path . ' uses ' . $url;
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Front module JS/Blade files must use hardcoded resource-style URLs instead of camelCase legacy API paths.');

        foreach ([
            "Route::get('/account/balance', 'AccountController@accountBalance')",
            "Route::get('/account/profile', 'AccountController@accountInfo')",
            "Route::get('/account/vouchers', 'AccountController@voucherList')",
            "Route::get('/agents/direct', 'AgentController@subList')",
            "Route::get('/agents/direct-customers', 'AgentController@customerList')",
            "Route::get('/agents/group-changes', 'AgentController@groupChangeList')",
            "Route::get('/users/login-history', 'AgentController@userLoginHistory')",
            "Route::get('/agents/direct-level-options', 'AgentController@getSubAgentsGrpIdList')",
            "Route::get('/agents/hierarchy-path', 'AgentController@getParentPath')",
            "Route::get('/customers/group-change-requests', 'AgentController@directCustChangeListSearch')",
            "Route::get('/commissions/realtime', 'CommissionController@realTime')",
            "Route::get('/commissions/history', 'CommissionController@history')",
            "Route::get('/positions/summary', 'PositionController@positionSummary')",
            "Route::get('/positions/direct-agent-summaries', 'PositionController@subPositionSummary')",
            "Route::get('/positions/trades', 'PositionController@positionDetail')",
            "Route::get('/orders/open', 'OrderController@openOrders')",
            "Route::get('/orders/closed', 'OrderController@closedOrders')",
            "Route::get('/news', 'NewsController@newsList')",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, $route . ' route alias is missing.');
        }

        foreach ([
            "Route::post('/agents/group-change-applications', 'AgentController@groupChange')",
            "Route::post('/customers/commission-transfers', 'AgentController@directUserCommTrans')",
            "Route::post('/commissions/transfers', 'CommissionController@transfer')",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, $route . ' write action must stay POST.');
        }

        foreach ([
            resource_path('front/layui/agent/sub.blade.php') => "'api' => '/api/front/agents/direct'",
            resource_path('front/layui/agent/customers.blade.php') => "'api' => '/api/front/agents/direct-customers'",
            resource_path('front/layui/agent/group-change.blade.php') => "'api' => '/api/front/agents/group-changes'",
            resource_path('front/layui/commission/realtime.blade.php') => "'api' => '/api/front/commissions/realtime'",
            resource_path('front/layui/commission/history.blade.php') => "'api' => '/api/front/commissions/history'",
            resource_path('front/layui/commission/transfer.blade.php') => "'api' => '/api/front/commissions/history'",
            resource_path('front/layui/position/summary.blade.php') => "'api' => '/api/front/positions/summary'",
            resource_path('front/layui/order/open.blade.php') => "'api' => '/api/front/orders/open'",
            resource_path('front/layui/order/closed.blade.php') => "'api' => '/api/front/orders/closed'",
        ] as $path => $apiNeedle) {
            $source = file_get_contents($path) ?: '';
            $this->assertStringContainsString($apiNeedle, $source);
            $this->assertStringContainsString("'method' => 'GET'", $source, $path . ' must make read-only module requests with GET.');
        }

        $layui = $this->publicScript('front/layui/module-page.js');
        foreach ([
            "method: 'GET'",
            "url: endpoint",
        ] as $needle) {
            $this->assertStringContainsString($needle, $layui, $needle . ' is missing from Layui module shell.');
        }
        $this->assertStringNotContainsString('API.userLoginHistory', $layui, 'Layui module pages must not keep a generic login-history action endpoint.');
        $this->assertStringNotContainsString("column.action === 'showLoginHistory'", $layui, 'Layui module pages must not reopen the removed user-name/login-history click behavior.');
        $this->assertStringNotContainsString('loginHistoryColumns', $layui, 'Layui module pages must not keep unused login-history table rendering.');

        foreach ([
            "Route::post('/positions/sub-summary'",
            "Route::post('/positions/detail'",
        ] as $legacyRoute) {
            $this->assertStringNotContainsString($legacyRoute, $routes, $legacyRoute . ' legacy route must be replaced by readable resource-style paths.');
        }
    }

    public function test_front_plain_legacy_script_is_not_loaded_by_front_pages(): void
    {
        $violations = [];
        $roots = [
            resource_path('front'),
            resource_path('views/naive'),
            public_path('js/apps/front'),
            public_path('js/apps/crmui'),
            public_path('css/front'),
        ];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            foreach (array_merge(
                $this->filesUnder($root, '.blade.php'),
                $this->filesUnder($root, '.js'),
                $this->filesUnder($root, '.css')
            ) as $file) {
                $content = file_get_contents($file) ?: '';
                if (str_contains($content, 'front-plain.js') || str_contains($content, '2026060502')) {
                    $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Front pages must not load the removed front-plain.js?v=2026060502 script.');
        $this->assertFileDoesNotExist(public_path('js/apps/naive-admin/front-plain.js'));
    }

    public function test_legacy_front_api_endpoints_unregistered(): void
    {
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $legacyEndpoints = [
            '/dashboardData',
            '/profileInfo',
            '/updateProfile',
            '/changePassword',
            '/changeEmail',
            '/changePhone',
            '/uploadAvatar',
            '/submitIdentity',
            '/submitBankCard',
            '/submitBankChange',
            '/accountInfo',
            '/accountBalance',
            '/submitVoucher',
            '/voucherList',
            '/accountFlow',
            '/agentSubList',
            '/agentCustomerList',
            '/agentConfirmLevel',
            '/agentConfirmLevelChange',
            '/agentGroupChangeList',
            '/agentGroupChange',
            '/directCustListSearch',
            '/directUserCommTrans',
            '/commissionRealTime',
            '/commissionHistory',
            '/commissionTransfer',
            '/depositFlowSearch',
            '/directDepositFlowSearch',
            '/positionSummary2Search',
            '/position/positionSummary2Search',
            '/openOrderSearch',
            '/closeOrderSearch',
            '/giftAddressList',
            '/giftAddAddress',
            '/giftUpdateAddress',
            '/giftDeleteAddress',
            '/giftList',
            '/newsList',
            '/newsListSearch',
            '/voucher/voucherSearch',
            '/cancelApply',
            '/cancelStatus',
            '/depositPage',
            '/depositApply',
            '/depositRecords',
            '/deposit_request',
            '/deposit_request_otc',
            '/submitDeposit',
            '/depositHistory',
            '/withdrawPage',
            '/withdrawApply',
            '/withdrawRecords',
            '/withdraw_request',
            '/withdraw_request_OTC',
            '/submitWithdraw',
            '/withdrawHistory',
            '/voucherSubmit',
            '/voucherRecords',
            '/withdrawalFlowSearch',
            '/withdrawApplyFlowSearch',
            '/directWithdrawalFlowSearch',
            '/positionSummary',
            '/subPositionSummary',
            '/positionDetail',
            '/openOrders',
            '/closedOrders',
            '/agentStatistics',
            '/userLoginHistory',
            '/realtimeRebateSearch',
            '/realtime/realtimeRebateSearch',
            '/subPositionSummarySearch',
            '/positionSummaryClickSearch',
            '/position/v2/positionSummaryClickSearch',
            '/position/v2/subAgentsListSearchV2',
            '/openOrder2Search',
            '/open/openOrder2Search',
            '/closeOrderSearchV2',
            '/closeOrder2Search',
            '/close/closeOrder2Search',
            '/uploadIdCard',
            '/uploadBankCard',
            '/uploadChangeBankCard',
            '/uploadHeadImg',
            '/updatePhoneEmailInfo',
            '/changeBankCardVerifyCode',
            '/updateVerifyInfo',
            '/cancelVerifyInfo',
            '/updVerifyPassSendCode',
            '/changeBankCardSendCode',
            '/cancelVerifyPassSendCode',
            '/relationShip',
            '/relationShipHtml',
            '/relationShipHtmlV2',
            '/getSubAgentsGrpIdList',
            '/getParentPath',
            '/proxyConfirmSearch',
            '/directCustChangeListSearch',
            '/changeDirectCustGroupInfo',
            '/addressSearch',
            '/addressUpdate',
            '/giftSearch',
            '/ajaxCancelAccount',
            '/flow/withdrawalFlowSearch',
            '/flow/withdrawApplyFlowSearch',
            '/flow/directDepositFlowSearch',
            '/flow/directWithdrawalFlowSearch',
            '/flow/directAgentsDepositFlowSearch',
            '/flow/directAgentsWithdrawalFlowSearch',
            '/address/search',
            '/address/update',
            '/gift/search',
            '/bigNumber/agentSubList',
        ];

        foreach ($legacyEndpoints as $endpoint) {
            $this->assertStringNotContainsString("Route::post('" . $endpoint . "'", $routes, $endpoint . ' must not stay registered as a front API route.');

            try {
                \Illuminate\Support\Facades\Route::getRoutes()->match(
                    \Illuminate\Http\Request::create('/api/front' . $endpoint, 'POST')
                );
                $this->fail('/api/front' . $endpoint . ' must be removed in favor of RESTful resource-style API paths.');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_profile_resource_routes_registered_without_legacy(): void
    {
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $profileIndex = $this->publicScript('front/layui/profile/index.js');
        $profileEdit = $this->publicScript('front/layui/profile/edit.js');
        $seed = file_get_contents(base_path('_seed.php')) ?: '';
        $seedMenus = file_get_contents(base_path('_seed_menus.php')) ?: '';

        foreach ([
            "Route::patch('/profile', 'ProfileController@updateProfile')",
            "Route::post('/profile/identity-card-uploads', 'ProfileController@uploadIdCard')",
            "Route::post('/profile/bank-card-uploads', 'ProfileController@uploadBankCard')",
            "Route::post('/profile/bank-card-change-uploads', 'ProfileController@uploadChangeBankCard')",
            "Route::post('/profile/head-image', 'ProfileController@uploadHeadImg')",
            "Route::post('/profile/contact-info', 'ProfileController@updatePhoneEmailInfo')",
            "Route::post('/profile/bank-card-change/verification-checks', 'ProfileController@changeBankCardVerifyCode')",
            "Route::post('/profile/verification-checks', 'ProfileController@updateVerifyInfo')",
            "Route::post('/profile/verification-cancellation-checks', 'ProfileController@cancelVerifyInfo')",
            "Route::post('/profile/verification-password/verification-codes', 'ProfileController@updVerifyPassSendCode')",
            "Route::post('/profile/bank-card-change/verification-codes', 'ProfileController@changeBankCardSendCode')",
            "Route::post('/profile/verification-cancellation/verification-codes', 'ProfileController@cancelVerifyPassSendCode')",
            "Route::get('/profile/relationship-path', 'ProfileController@relationShip')",
            "Route::get('/profile/relationship-path/html', 'ProfileController@relationShipHtml')",
            "Route::get('/profile/relationship-tree/html', 'ProfileController@relationShipHtmlV2')",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, $route . ' route is missing.');
        }

        foreach ([
            'front_api_profile_update',
            'front_api_profile_identity_card_uploads',
            'front_api_profile_bank_card_uploads',
            'front_api_profile_bank_card_change_uploads',
            'front_api_profile_head_image',
            'front_api_profile_contact_info',
            'front_api_profile_bank_card_change_verification_checks',
            'front_api_profile_verification_checks',
            'front_api_profile_verification_cancellation_checks',
            'front_api_profile_verification_password_verification_codes',
            'front_api_profile_bank_card_change_verification_codes',
            'front_api_profile_verification_cancellation_verification_codes',
            'front_api_profile_relationship_path',
            'front_api_profile_relationship_path_html',
            'front_api_profile_relationship_tree_html',
        ] as $routeName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($routeName), $routeName . ' route name is missing.');
        }

        foreach ([
            'front_api_user_editpsw_save',
            'front_api_center_uploadIdCard',
            'front_api_center_uploadBankCard',
            'front_api_center_uploadChangeBankCard',
            'front_api_center_uploadHeadImg',
            'front_api_center_updatePhoneEmailInfo',
            'front_api_center_changeBankCardVerifyCode',
            'front_api_center_updateVerifyInfo',
            'front_api_center_cancelVerifyInfo',
            'front_api_center_updVerifyPassSendCode',
            'front_api_center_changeBankCardSendCode',
            'front_api_center_cancelVerifyPassSendCode',
            'front_api_profile_bank_card_change_verification_code',
            'front_api_profile_verification_info',
            'front_api_profile_verification_cancellation',
            'front_api_profile_verification_password_code',
            'front_api_profile_bank_card_change_code',
            'front_api_profile_cancel_verification_password_code',
            'front_api_profile_relationships',
            'front_api_profile_relationships_html',
            'front_api_profile_relationships_tree_html',
        ] as $legacyRouteName) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($legacyRouteName), $legacyRouteName . ' must not stay registered as a front API route.');
        }

        foreach ([
            '/profile/update',
            '/user_editpsw_save',
            '/center/uploadIdCard',
            '/center/uploadBankCard',
            '/center/uploadChangeBankCard',
            '/center/uploadHeadImg',
            '/center/updatePhoneEmailInfo',
            '/center/changeBankCardVerifyCode',
            '/center/updateVerifyInfo',
            '/center/cancelVerifyInfo',
            '/center/updVerifyPassSendCode',
            '/center/changeBankCardSendCode',
            '/center/cancelVerifyPassSendCode',
            '/profile/bank-card-change/verification-code',
            '/profile/verification-info',
            '/profile/verification-cancellation',
            '/profile/verification-password-code',
            '/profile/bank-card-change-code',
            '/profile/cancel-verification-password-code',
            '/profile/relationships',
            '/profile/relationships/html',
            '/profile/relationships/tree-html',
        ] as $endpoint) {
            try {
                \Illuminate\Support\Facades\Route::getRoutes()->match(
                    \Illuminate\Http\Request::create('/api/front' . $endpoint, 'POST')
                );
                $this->fail('/api/front' . $endpoint . ' must be removed in favor of /api/front/profile... resource paths.');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
                $this->assertTrue(true);
            }
        }

        foreach ([
            'legacy_user_center_upload_id_card',
            'legacy_user_center_upload_bank_card',
            'legacy_user_center_upload_change_bank_card',
            'legacy_user_center_update_verify_info',
            'legacy_user_center_change_bank_verify_code',
            'legacy_user_center_update_verify_code',
            'legacy_user_center_change_bank_code',
            'legacy_user_center_update_phone_email',
            'legacy_user_center_upload_head_img',
            'legacy_user_edit_password_save',
            'legacy_user_agents_edit_password_save',
        ] as $legacyWebRouteName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($legacyWebRouteName), $legacyWebRouteName . ' legacy web route must stay registered.');
        }

        foreach ([$profileIndex, $profileEdit] as $source) {
            $this->assertStringNotContainsString('/api/front/profile/update', $source, 'Profile update frontend calls must use PATCH /api/front/profile.');
        }

        $this->assertStringContainsString("url: '/api/front/profile'", $profileIndex);
        $this->assertStringContainsString("method: 'PATCH'", $profileIndex);
        $this->assertStringContainsString("url: '/api/front/profile'", $profileEdit);
        $this->assertStringContainsString("method: 'PATCH'", $profileEdit);
        $this->assertStringNotContainsString('front_api_updateProfile', $seed . $seedMenus);
        $this->assertStringContainsString("'front_profile_edit','guard_type'=>'front','parent_id'=>11,'type'=>2,'icon'=>'fas fa-user-edit','sort'=>2,'route'=>'/front/profile/edit','api_route'=>'front_api_profile_update'", $seed);
        $this->assertStringContainsString("'front_profile_edit','front',\$fp,2,'fas fa-user-edit',2,'/front/profile/edit','front_api_profile_update'", $seedMenus);
    }

    public function test_upload_resource_routes_registered_without_legacy(): void
    {
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';

        foreach ([
            "Route::post('/uploads', '\App\Http\Controllers\Common\UploadController@upload')",
            "Route::post('/uploads/single', 'UploadController@singleFileUpload')",
            "Route::post('/uploads/multiple', 'UploadController@multipleFileUpload')",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, $route . ' route is missing.');
        }

        foreach ([
            'front_api_uploads_store',
            'front_api_uploads_single',
            'front_api_uploads_multiple',
        ] as $routeName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($routeName), $routeName . ' route name is missing.');
        }

        foreach ([
            'front_api_uploadFile',
            'front_api_singleFileUpload',
            'front_api_multipleFileUpload',
        ] as $legacyRouteName) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($legacyRouteName), $legacyRouteName . ' must not stay registered as a front API route.');
        }

        foreach ([
            '/uploadFile',
            '/singleFileUpload',
            '/multipleFileUpload',
        ] as $endpoint) {
            try {
                \Illuminate\Support\Facades\Route::getRoutes()->match(
                    \Illuminate\Http\Request::create('/api/front' . $endpoint, 'POST')
                );
                $this->fail('/api/front' . $endpoint . ' must be removed in favor of /api/front/uploads... paths.');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
                $this->assertTrue(true);
            }
        }

        foreach ([
            'legacy_user_upload_file',
            'legacy_user_multiple_file',
        ] as $legacyWebRouteName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($legacyWebRouteName), $legacyWebRouteName . ' legacy web route must stay registered.');
        }
    }

    public function test_gift_and_agent_level_resource_routes(): void
    {
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $giftAddressBlade = file_get_contents(resource_path('front/layui/gift/address.blade.php')) ?: '';
        $giftListBlade = file_get_contents(resource_path('front/layui/gift/list.blade.php')) ?: '';

        foreach ([
            "Route::get('/agents/direct-level-options', 'AgentController@getSubAgentsGrpIdList')",
            "Route::get('/agents/hierarchy-path', 'AgentController@getParentPath')",
            "Route::get('/customers/group-change-requests', 'AgentController@directCustChangeListSearch')",
            "Route::get('/gift-addresses', 'GiftController@addressSearch')",
            "Route::post('/gift-addresses', 'GiftController@addAddress')",
            "Route::patch('/gift-addresses/{address}', 'GiftController@updateAddress')",
            "Route::delete('/gift-addresses/{address}', 'GiftController@deleteAddress')",
            "Route::get('/gifts', 'GiftController@giftList')",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, $route . ' route is missing.');
        }

        foreach ([
            'front_api_agents_direct_level_options',
            'front_api_agents_hierarchy_path',
            'front_api_customers_group_change_requests',
            'front_api_gift_addresses_index',
            'front_api_gift_addresses_store',
            'front_api_gift_addresses_update',
            'front_api_gift_addresses_destroy',
            'front_api_gifts',
        ] as $routeName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($routeName), $routeName . ' route name is missing.');
        }

        foreach ([
            'front_api_proxy_getSubAgentsGrpIdList',
            'front_api_proxy_parentPath',
            'front_api_agents_level_options',
            'front_api_agents_parent_path',
            'front_api_agents_level_confirmation_search',
            'front_api_proxy_proxyConfirmSearch',
            'front_api_proxy_confirmLevelChange',
            'front_api_proxy_direct_cust_detail_list',
            'front_api_cust_directCustChangeListSearch',
            'front_api_cust_change_group_edit',
            'front_api_customers_group_change_application_adapters',
            'front_api_gift_addresses',
            'front_api_gift_addresses_search',
            'front_api_gift_addresses_save',
            'front_api_gifts_search',
            'front_api_account_cancellation_application_adapters',
        ] as $legacyRouteName) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($legacyRouteName), $legacyRouteName . ' must not stay registered as a front API route.');
        }

        foreach ([
            '/proxy/getSubAgentsGrpIdList',
            '/proxy/parentPath',
            '/agents/level-options',
            '/agents/parent-path',
            '/agents/level-confirmation/search',
            '/proxy/proxyConfirmSearch',
            '/proxy/confirmLevelChange',
            '/proxy/direct_cust_detail_list',
            '/cust/directCustChangeListSearch',
            '/cust/change/group_edit',
            '/gift-addresses/create',
            '/gift-addresses/update',
            '/gift-addresses/delete',
            '/gift-addresses/upsert',
            '/gift-addresses/save',
            '/gift-addresses/search',
            '/gifts/search',
            '/customers/group-change-applications/legacy',
            '/customers/group-change-application-adapters',
            '/account/cancellation-legacy-applications',
            '/account/cancellation-application-adapters',
        ] as $endpoint) {
            try {
                \Illuminate\Support\Facades\Route::getRoutes()->match(
                    \Illuminate\Http\Request::create('/api/front' . $endpoint, 'POST')
                );
                $this->fail('/api/front' . $endpoint . ' must be removed in favor of resource-style paths.');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
                $this->assertTrue(true);
            }
        }

        foreach ([
            'legacy_user_proxy_confirm_search',
            'legacy_user_proxy_confirm_change',
            'legacy_user_proxy_direct_customer_list',
            'legacy_user_proxy_group_list',
            'legacy_user_proxy_parent_path',
            'legacy_user_customer_change_group_edit',
            'legacy_user_customer_direct_change_search',
        ] as $legacyWebRouteName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($legacyWebRouteName), $legacyWebRouteName . ' legacy web route must stay registered.');
        }

        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('front_api_center_ajaxCancelAccount'),
            'front_api_center_ajaxCancelAccount must not stay registered as a front API route.'
        );

        try {
            \Illuminate\Support\Facades\Route::getRoutes()->match(
                \Illuminate\Http\Request::create('/api/front/center/ajaxCancelAccount', 'POST')
            );
            $this->fail('/api/front/center/ajaxCancelAccount must be removed from /api/front; legacy web compatibility stays under /user/center/ajaxCancelAccount.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
            $this->assertTrue(true);
        }

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('legacy_user_center_ajax_cancel'),
            'legacy_user_center_ajax_cancel legacy web route must stay registered.'
        );

        $this->assertStringContainsString("'api' => '/api/front/gift-addresses'", $giftAddressBlade);
        $this->assertStringContainsString("'method' => 'GET'", $giftAddressBlade);
        $this->assertStringNotContainsString("'/api/front/gift-addresses/search'", $giftAddressBlade);
        $this->assertStringContainsString("'api' => '/api/front/gifts'", $giftListBlade);
        $this->assertStringContainsString("'method' => 'GET'", $giftListBlade);
    }

    public function test_gift_list_filters_match_legacy_contract(): void
    {
        $now = time();
        $userId = 992701;

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $userId],
            [
                'email' => 'gift-list-filter@example.test',
                'password' => Hash::make('123456'),
                'account_type' => 2,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 1,
                'jwt_token_id' => '',
                'last_login_ip' => '',
                'last_login_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => (int) DB::table('user_logins')->where('user_id', $userId)->value('id'),
                'user_name' => 'Gift List Filter User',
                'phone' => '86-13900002701',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'comm_rate' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('gift_shipments')->where('user_id', $userId)->delete();
        DB::table('gift_shipments')->insert([
            [
                'user_id' => $userId,
                'address_id' => 0,
                'recipient_name' => 'Alice Legacy',
                'recipient_phone' => '13900002701',
                'recipient_address' => 'Shenzhen Nanshan',
                'sender_name' => 'Ops',
                'tracking_number' => 'GT-992701-A',
                'gift_name' => 'Legacy Thermos',
                'gift_quantity' => 2,
                'status' => 1,
                'remark' => 'matched row',
                'admin_id' => 0,
                'shipped_at' => '2026-05-12 10:30:00',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'user_id' => $userId,
                'address_id' => 0,
                'recipient_name' => 'Bob Legacy',
                'recipient_phone' => '13900002702',
                'recipient_address' => 'Shenzhen Futian',
                'sender_name' => 'Ops',
                'tracking_number' => 'GT-992701-B',
                'gift_name' => 'Other Gift',
                'gift_quantity' => 1,
                'status' => 1,
                'remark' => 'filtered row',
                'admin_id' => 0,
                'shipped_at' => '2026-06-01 10:30:00',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);

        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/gifts?recipient_name=Alice&gift_name=Thermos&startdate=2026-05-01&enddate=2026-05-31');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.shipped_gifts.data')
            ->assertJsonPath('data.shipped_gifts.data.0.gift_name', 'Legacy Thermos')
            ->assertJsonPath('data.shipped_gifts.data.0.recipient_name', 'Alice Legacy')
            ->assertJsonPath('data.shipped_gifts.data.0.gift_quantity', 2)
            ->assertJsonPath('data.shipped_gifts.data.0.shipped_at', '2026-05-12 10:30:00');
    }

    public function test_gift_catalog_filter_excludes_inactive_and_out_of_stock(): void
    {
        $createdGiftItemsTable = $this->ensureGiftItemsTestTable();
        $now = time();
        $userId = 992711;

        try {
            DB::table('user_logins')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'email' => 'gift-catalog-filter@example.test',
                    'password' => Hash::make('123456'),
                    'account_type' => 2,
                    'is_enabled' => 1,
                    'is_cancelled' => 0,
                    'source_type' => 1,
                    'jwt_token_id' => '',
                    'last_login_ip' => '',
                    'last_login_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );

            DB::table('user_infos')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'login_id' => (int) DB::table('user_logins')->where('user_id', $userId)->value('id'),
                    'user_name' => 'Gift Catalog Filter User',
                    'phone' => '86-13900002711',
                    'gender' => 1,
                    'account_type' => 2,
                    'parent_id' => 0,
                    'family_tree' => (string) $userId,
                    'total_funds' => 0,
                    'equity' => 0,
                    'effective_credit' => 0,
                    'comm_rate' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );

            DB::table('gift_items')->whereIn('name', [
                'Catalog Thermos',
                'Catalog Keyboard',
                'Inactive Catalog Thermos',
                'Zero Stock Catalog Thermos',
            ])->delete();
            DB::table('gift_items')->insert([
                [
                    'name' => 'Catalog Thermos',
                    'description' => 'Real catalog thermos',
                    'points_cost' => 320,
                    'stock_quantity' => 8,
                    'status' => 1,
                    'image_url' => '/images/gifts/catalog-thermos.png',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
                [
                    'name' => 'Catalog Keyboard',
                    'description' => 'Different point cost',
                    'points_cost' => 500,
                    'stock_quantity' => 4,
                    'status' => 1,
                    'image_url' => '/images/gifts/catalog-keyboard.png',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
                [
                    'name' => 'Inactive Catalog Thermos',
                    'description' => 'Inactive gift',
                    'points_cost' => 320,
                    'stock_quantity' => 10,
                    'status' => 0,
                    'image_url' => '/images/gifts/inactive.png',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
                [
                    'name' => 'Zero Stock Catalog Thermos',
                    'description' => 'Out of stock gift',
                    'points_cost' => 320,
                    'stock_quantity' => 0,
                    'status' => 1,
                    'image_url' => '/images/gifts/zero-stock.png',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
            ]);

            $login = UserLogin::where('user_id', $userId)->firstOrFail();

            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->getJson('/api/front/gifts?name=Catalog&points_cost=320');

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS)
                ->assertJsonCount(1, 'data.available_gifts')
                ->assertJsonPath('data.available_gifts.0.name', 'Catalog Thermos')
                ->assertJsonPath('data.available_gifts.0.points_cost', 320)
                ->assertJsonPath('data.available_gifts.0.stock_quantity', 8);

            $this->assertStringNotContainsString('VIP Gift Box', $response->getContent());
            $this->assertStringNotContainsString('Inactive Catalog Thermos', $response->getContent());
            $this->assertStringNotContainsString('Zero Stock Catalog Thermos', $response->getContent());
        } finally {
            if ($createdGiftItemsTable) {
                Schema::dropIfExists('gift_items');
            }
        }
    }

    public function test_voucher_list_filters_match_legacy_fields(): void
    {
        $userId = 992820;
        $otherUserId = 992821;
        $now = time();
        $email = 'voucher-legacy-fields@example.test';

        DB::table('voucher_infos')->whereIn('user_id', [$userId, $otherUserId])->delete();
        DB::table('user_infos')->whereIn('user_id', [$userId, $otherUserId])->delete();
        DB::table('user_logins')->whereIn('user_id', [$userId, $otherUserId])->delete();
        DB::table('user_logins')->where('email', $email)->delete();

        DB::table('user_logins')->insert([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('123456'),
            'account_type' => 2,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 1,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $login->id,
            'user_name' => 'Voucher Legacy User',
            'phone' => '86-13900002820',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('voucher_infos')->insert([
            [
                'user_id' => $userId,
                'images' => 'vouchers/992820/matched.png',
                'remarks' => 'Matched legacy voucher',
                'review_status' => 2,
                'review_message' => 'Need clearer image',
                'created_by' => 'Voucher Legacy User',
                'updated_by' => 'Admin',
                'created_at' => strtotime('2026-05-10 09:00:00'),
                'updated_at' => strtotime('2026-05-11 10:30:00'),
                'deleted_at' => null,
            ],
            [
                'user_id' => $userId,
                'images' => 'vouchers/992820/outside.png',
                'remarks' => 'Outside range voucher',
                'review_status' => 0,
                'review_message' => '',
                'created_by' => 'Voucher Legacy User',
                'updated_by' => 'Admin',
                'created_at' => strtotime('2026-06-10 09:00:00'),
                'updated_at' => strtotime('2026-06-10 10:30:00'),
                'deleted_at' => null,
            ],
            [
                'user_id' => $otherUserId,
                'images' => 'vouchers/992821/other.png',
                'remarks' => 'Other user voucher',
                'review_status' => 1,
                'review_message' => 'Approved',
                'created_by' => 'Other User',
                'updated_by' => 'Admin',
                'created_at' => strtotime('2026-05-10 09:00:00'),
                'updated_at' => strtotime('2026-05-11 10:30:00'),
                'deleted_at' => null,
            ],
        ]);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/vouchers?review_status=2&startdate=2026-05-01&enddate=2026-05-31');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.data')
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.data.0.user_id', (string) $userId)
            ->assertJsonPath('data.data.0.remarks', 'Matched legacy voucher')
            ->assertJsonPath('data.data.0.review_msg', 'Need clearer image')
            // 旧兼容 JSON 契约中枚举状态字段为字符串（INT + EMULATE_PREPARES）。
            ->assertJsonPath('data.data.0.review_status', (string) 2)
            ->assertJsonPath('data.data.0.rec_crt_date', '2026-05-10 09:00:00')
            ->assertJsonPath('data.data.0.rec_upd_date', '2026-05-11 10:30:00');
    }

    public function test_cancel_application_rejects_wrong_code_and_password(): void
    {
        $userId = 992850;
        $now = time();
        $email = 'cancel-legacy-fields@example.test';

        DB::table('cancel_applies')->where('user_id', $userId)->delete();
        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();

        DB::table('user_logins')->insert([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('correct-password'),
            'account_type' => 2,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 1,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $login->id,
            'user_name' => 'Cancel Legacy User',
            'phone' => '86-13912345678',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'id_card_no' => 'ID992850',
            'id_card_status' => 2,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $payload = [
            'userIdcardNo' => 'ID992850',
            'userphoneNo' => '13912345678',
            'useremail' => $email,
            'password' => 'correct-password',
            'userverfcode' => '000000',
        ];

        $wrongCode = $this->withSession(['cancelCode' => '654321'])
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/account/cancellation-applications', $payload);

        $wrongCode->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('data.err', 'codeErr')
            ->assertJsonPath('data.col', 'userverfcode');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);

        $wrongPassword = $this->withSession(['cancelCode' => '654321'])
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/account/cancellation-applications', array_merge($payload, [
                'password' => 'wrong-password',
                'userverfcode' => '654321',
            ]));

        $wrongPassword->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('data.err', 'passwordErr')
            ->assertJsonPath('data.col', 'password');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);
    }

    public function test_news_list_filters_match_legacy_fields(): void
    {
        $oldCreated = strtotime('2026-03-08 09:00:00');
        $matchedUpdated = strtotime('2026-03-10 11:30:00');
        $outsideUpdated = strtotime('2026-04-10 11:30:00');

        DB::table('news_langs')->whereIn('news_id', [992801, 992802])->delete();
        DB::table('news')->whereIn('id', [992801, 992802])->delete();
        DB::table('news')->insert([
            [
                'id' => 992801,
                'title' => 'Legacy March Notice',
                'content' => '<p>Matched legacy news</p>',
                'image' => '',
                'author_id' => 0,
                'author_name' => 'Legacy Admin',
                'is_published' => 1,
                'created_at' => $oldCreated,
                'updated_at' => $matchedUpdated,
                'deleted_at' => null,
            ],
            [
                'id' => 992802,
                'title' => 'Legacy April Notice',
                'content' => '<p>Filtered legacy news</p>',
                'image' => '',
                'author_id' => 0,
                'author_name' => 'Legacy Admin',
                'is_published' => 1,
                'created_at' => $oldCreated,
                'updated_at' => $outsideUpdated,
                'deleted_at' => null,
            ],
        ]);
        DB::table('news_langs')->insert([
            [
                'news_id' => 992801,
                'lang_code' => 'zh-CN',
                'title' => '旧项目三月公告',
                'content' => '<p>中文公告内容</p>',
                'created_at' => $oldCreated,
                'updated_at' => $matchedUpdated,
                'deleted_at' => null,
            ],
        ]);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withHeader('X-Locale', 'zh-CN')
            ->getJson('/api/front/news?startdate=2026-03-01&enddate=2026-03-31');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.news.data')
            ->assertJsonPath('data.news.data.0.news_id', 992801)
            ->assertJsonPath('data.news.data.0.news_title', '旧项目三月公告')
            ->assertJsonPath('data.news.data.0.title', '旧项目三月公告')
            ->assertJsonPath('data.news.data.0.news_content', '<p>中文公告内容</p>')
            ->assertJsonPath('data.news.data.0.rec_crt_date', '2026-03-08 09:00:00')
            ->assertJsonPath('data.news.data.0.rec_upd_date', '2026-03-10 11:30:00');
    }

    public function test_front_js_comments_have_no_replacement_characters(): void
    {
        foreach ([
            'public/js/apps/front/layui/module-page.js' => $this->publicScript('front/layui/module-page.js'),
            'public/js/apps/front/layui/deposit/index.js' => $this->publicScript('front/layui/deposit/index.js'),
            'public/js/apps/front/layui/withdraw/index.js' => $this->publicScript('front/layui/withdraw/index.js'),
        ] as $file => $content) {
            preg_match_all('/^\s*\/\/.*$|\/\*[\s\S]*?\*\//m', $content, $matches);
            $comments = implode("\n", $matches[0]);

            $this->assertStringNotContainsString("\xEF\xBF\xBD", $comments, $file . ' contains replacement characters in comments.');
            $this->assertDoesNotMatchRegularExpression('/(?:\x{95B3}|\x{95B8}|\x{93C9}|\x{9420}|\x{9225})\?/u', $comments, $file . ' contains unreadable front-end comment fragments.');
        }

    }

    public function test_front_user_detail_uses_restful_route(): void
    {
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $controller = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';
        $module = $this->publicScript('front/layui/module-page.js');
        $customerBlade = file_get_contents(resource_path('front/layui/agent/customers.blade.php')) ?: '';
        $positionBlade = file_get_contents(resource_path('front/layui/position/summary.blade.php')) ?: '';
        $openOrderBlade = file_get_contents(resource_path('front/layui/order/open.blade.php')) ?: '';
        $closedOrderBlade = file_get_contents(resource_path('front/layui/order/closed.blade.php')) ?: '';
        $realtimeCommissionBlade = file_get_contents(resource_path('front/layui/commission/realtime.blade.php')) ?: '';

        $this->assertStringContainsString("Route::get('/users/{user}', 'AgentController@showUser')->name('front_api_users_show');", $routes);
        $this->assertStringNotContainsString("Route::post('/userDetail', 'AgentController@userDetail')->name('front_api_userDetail');", $routes);
        $this->assertStringNotContainsString("Route::post('/users/detail', 'AgentController@userDetail')->name('front_api_users_detail');", $routes);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('front_api_userDetail'), 'Front user detail must not keep the non-RESTful front_api_userDetail route.');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('front_api_users_detail'), 'Front user detail must not keep a POST /users/detail compatibility route.');
        $matchedRoute = \Illuminate\Support\Facades\Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/api/front/users/10001', 'GET')
        );
        $this->assertSame('front_api_users_show', $matchedRoute->getName());
        $this->assertContains('GET', $matchedRoute->methods());
        try {
            \Illuminate\Support\Facades\Route::getRoutes()->match(
                \Illuminate\Http\Request::create('/api/front/userDetail', 'POST')
            );
            $this->fail('Legacy /api/front/userDetail POST route must not be registered.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
            $this->assertTrue(true);
        }
        $this->assertStringContainsString('public function showUser(Request $request, int $user): JsonResponse', $controller);
        $this->assertStringContainsString('private function agentLevelDetailPayload(UserInfo $user): array', $controller);
        $agentLevelPayloadMethod = $this->sourceBetween($controller, 'private function agentLevelDetailPayload(UserInfo $user): array', 'public function showUser');
        $this->assertStringContainsString('if ((int) $user->account_type !== 1)', $agentLevelPayloadMethod, 'Customer detail payload must not expose agent level fields.');
        $this->assertStringContainsString('return [];', $agentLevelPayloadMethod, 'Only agents have level hierarchy; normal customers must return no level payload.');
        $legacyUserDetailMethod = $this->sourceBetween($controller, 'public function legacyUserDetailPage', 'public function legacyLoginHistorySearch');
        $this->assertStringContainsString('if ((int) $user->account_type === 1)', $legacyUserDetailMethod, 'Legacy detail page must only render agent level for agents.');

        $this->assertStringContainsString("'api' => '/api/front/users/{user}'", $customerBlade);
        $this->assertStringContainsString("'method' => 'GET'", $customerBlade);
        $this->assertStringContainsString("'routeParams' => ['user' => '{user_id}']", $customerBlade);
        foreach ([$openOrderBlade, $closedOrderBlade, $realtimeCommissionBlade] as $showUserInfoBlade) {
            $this->assertStringContainsString("'api' => '/api/front/users/{user}'", $showUserInfoBlade);
            $this->assertStringContainsString("'method' => 'GET'", $showUserInfoBlade);
            $this->assertStringContainsString("'routeParams' => ['user' => '{login}']", $showUserInfoBlade);
            $this->assertStringContainsString("'idField' => 'login'", $showUserInfoBlade);
        }
        $this->assertStringNotContainsString("['key' => 'user_name', 'label' => 'front.user_name', 'action' => 'showUserInfo'", $positionBlade);
        $this->assertStringNotContainsString("'api' => 'front_api_userDetail'", $customerBlade . $positionBlade . $openOrderBlade . $closedOrderBlade . $realtimeCommissionBlade);

        $this->assertStringContainsString('url: apiTemplate(userDetailApi, buildActionPayload(userDetailRouteParams, row))', $module);
        $this->assertStringContainsString("var userDetailMethod = column.method || 'GET';", $module);
        $this->assertStringContainsString("userDetailMethod === 'GET' ? null : buildActionPayload", $module);
        $this->assertStringContainsString("column.api || API.usersShow", $module);

    }

    public function test_front_restful_user_detail_scope_boundary(): void
    {
        $now = time();
        $agentId = 990001;
        $customerId = 990002;
        $outsideCustomerId = 990003;

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $agentId],
            [
                'email' => 'front-restful-detail-agent@example.test',
                'password' => 'front-restful-detail-test',
                'account_type' => 1,
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
            ]
        );
        $loginId = (int) DB::table('user_logins')->where('user_id', $agentId)->value('id');

        foreach ([
            [$agentId, 'REST Test Agent', 1, 0, 1],
            [$customerId, 'REST Test Customer', 2, $agentId, 0],
            [$outsideCustomerId, 'REST Outside Customer', 2, 0, 0],
        ] as [$userId, $userName, $accountType, $parentId, $levelId]) {
            DB::table('user_infos')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'login_id' => $userId === $agentId ? $loginId : 0,
                    'user_name' => $userName,
                    'phone' => '',
                    'gender' => 1,
                    'avatar' => null,
                    'level_id' => $levelId,
                    'group_id' => 0,
                    'parent_id' => $parentId,
                    'account_type' => $accountType,
                    'family_tree' => $parentId > 0 ? $agentId . ',' . $userId : (string) $userId,
                    'total_funds' => 0,
                    'used_margin' => 0,
                    'avail_margin' => 0,
                    'equity' => 0,
                    'effective_credit' => 0,
                    'risk_ratio' => 0,
                    'margin_amount' => 0,
                    'leverage' => 0,
                    'cust_vol' => '0',
                    'pay_provider_id' => 0,
                    'equity_ratio' => 0,
                    'comm_rate' => 0,
                    'is_ecn' => 0,
                    'follow_parent_ecn' => 0,
                    'auth_status' => 1,
                    'is_mt4_synced' => 1,
                    'is_mt4_enabled' => 1,
                    'is_mt4_readonly' => 0,
                    'is_withdrawal_allowed' => 0,
                    'is_deposit_allowed' => 0,
                    'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
                    'original_group' => '',
                    'mt4_group' => '',
                    'mt4_code' => 0,
                    'trading_mode' => 0,
                    'settle_method' => 1,
                    'settle_cycle' => 1,
                    'country' => '',
                    'city' => '',
                    'state' => '',
                    'address' => '',
                    'is_gift_allowed' => 0,
                    'data_source' => 0,
                    'remark' => 'front restful user detail regression test',
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('agent_descendants')->updateOrInsert(
            ['agent_id' => $agentId, 'descendant_id' => $customerId],
            [
                'descendant_type' => 2,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();

        $visible = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/' . $customerId);

        $visible->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.user_id', (string) $customerId)
            ->assertJsonPath('data.user_name', 'REST Test Customer');

        $visibleData = $visible->json('data');
        $this->assertArrayNotHasKey('agent_level_rank', $visibleData);
        $this->assertArrayNotHasKey('agent_level_name', $visibleData);

        $outside = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/' . $outsideCustomerId);

        $outside->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 验证前台样式切换只在两套服务端 Blade 外观之间保留当前业务模块。
     *
     * @return void 通过表示 Layui 可切换到对应 CrmUI 页面，且语言包不再暴露已删除的 Naive 入口。
     */
    public function test_front_style_switch_exposes_crmui_and_naive_shells(): void
    {
        $layuiLayout = $this->publicScript('front/layui/layout.js');
        $zhFront = file_get_contents(resource_path('lang/zh-CN/front.php')) ?: '';
        $enFront = file_get_contents(resource_path('lang/en/front.php')) ?: '';
        $zhStatic = $this->publicScript('shared/lang/common/zh-CN.js');
        $enStatic = $this->publicScript('shared/lang/common/en.js');

        $this->assertStringContainsString('function frontCrmUiPageUrl(page)', $layuiLayout);
        $this->assertStringContainsString('function frontNaivePageUrl(page)', $layuiLayout);
        $this->assertStringContainsString('function ()', $layuiLayout);
        $this->assertStringContainsString('frontCrmUiPageUrl(currentLayuiPagePath())', $layuiLayout);
        $this->assertStringContainsString('frontNaivePageUrl(currentLayuiPagePath())', $layuiLayout);
        $this->assertStringContainsString("account: 'account/info'", $layuiLayout);
        $this->assertStringContainsString("deposits: 'deposit'", $layuiLayout);
        $this->assertStringNotContainsString("deposit: 'deposits'", $layuiLayout);
        $this->assertStringContainsString('window.location.href = currentCrmUiPageUrl();', $layuiLayout);
        $this->assertStringContainsString('window.location.href = currentNaivePageUrl();', $layuiLayout);
        $this->assertStringContainsString("CrmLang.t('front.layout_classic')", $layuiLayout);
        $this->assertStringContainsString("CrmLang.t('front.layout_crmui')", $layuiLayout);
        $this->assertStringContainsString("CrmLang.t('front.layout_naive')", $layuiLayout);
        $this->assertStringNotContainsString("CrmLang.t('front.layout_clean')", $layuiLayout);

        foreach ([$zhFront, $enFront] as $language) {
            $this->assertStringContainsString("'layout_classic' =>", $language);
            $this->assertStringContainsString("'layout_crmui' =>", $language);
            $this->assertStringContainsString("'layout_naive' =>", $language);
        }

        foreach ([$zhFront, $enFront, $zhStatic, $enStatic] as $language) {
            $this->assertStringNotContainsString('layout_clean', $language);
            $this->assertStringNotContainsString('naive_admin_desc', $language);
            $this->assertStringNotContainsString('naive_front_desc', $language);
        }
    }

    public function test_profile_avatar_upload_uses_file_api_without_submit_button(): void
    {
        $blade = file_get_contents(resource_path('front/layui/profile/index.blade.php')) ?: '';
        $editBlade = file_get_contents(resource_path('front/layui/profile/edit.blade.php')) ?: '';
        $script = $this->publicScript('front/layui/profile/index.js');
        $editScript = $this->publicScript('front/layui/profile/edit.js');

        $this->assertStringNotContainsString('id="submitAvatar"', $blade);
        $this->assertStringContainsString('function uploadAvatarFile', $script);
        $this->assertStringContainsString("uploadAvatarFile(file);", $script);
        $this->assertStringNotContainsString("$('#submitAvatar').on('click'", $script);

        $this->assertStringNotContainsString('id="submitAvatar"', $editBlade);
        $this->assertStringContainsString('function uploadAvatarFile', $editScript);
        $this->assertStringContainsString("uploadAvatarFile(file || selectedAvatar);", $editScript);
        $this->assertStringContainsString('function notifyParentAvatar', $editScript, 'Profile edit avatar upload must refresh the shell header avatar like the main profile page.');
        $this->assertStringContainsString('notifyParentAvatar(avatarDefault);', $editScript, 'Profile edit avatar upload must notify the parent layout after the backend accepts the avatar.');
        $this->assertStringNotContainsString("$('#submitAvatar').on('click'", $editScript);
        $this->assertStringNotContainsString('legacySubmitAvatarUpload', $editScript);
        $this->assertStringNotContainsString('auto: false', $editScript);
    }

    public function test_profile_upload_shared_component_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/profile/index.blade.php')) ?: '';
        $editBlade = file_get_contents(resource_path('front/layui/profile/edit.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/profile/index_v2.blade.php')) ?: '';
        $editScript = $this->publicScript('front/layui/profile/edit.js');
        $css = file_get_contents(public_path('css/front/style.css')) ?: '';
        $v2Css = file_get_contents(public_path('css/front/v2.css')) ?: '';

        foreach ([
            'crm-profile-shell',
            'crm-profile-hero',
            'crm-profile-avatar-block',
            'crm-profile-avatar-wrap',
            'crm-profile-avatar-action',
            'crm-profile-upload-field',
            'crm-profile-upload-card',
            'crm-profile-upload-preview',
            'crm-profile-upload-meta',
            'layui-upload-drag',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blade, 'Profile page upload UI must use the shared polished component class: ' . $needle);
        }

        foreach ([
            'crm-profile-shell',
            'crm-profile-hero',
            'crm-profile-avatar-block',
            'crm-profile-avatar-wrap',
            'crm-profile-avatar-action',
            'crm-profile-upload-field',
            'crm-profile-upload-card',
            'crm-profile-upload-meta',
            'layui-upload-drag',
        ] as $needle) {
            $this->assertStringContainsString($needle, $editBlade, 'Profile edit page upload UI must use the shared polished component class: ' . $needle);
        }

        foreach ([
            'crm-profile-shell',
            'crm-profile-hero',
            'crm-profile-avatar-block',
            'crm-profile-avatar-wrap',
            'crm-profile-avatar-action',
            'crm-profile-upload-field',
            'crm-profile-upload-card',
            'crm-profile-upload-preview',
            'crm-profile-upload-meta',
            'layui-upload-drag',
        ] as $needle) {
            $this->assertStringContainsString($needle, $v2Blade, 'Profile v2 upload UI must reuse the same Layui upload component class: ' . $needle);
        }

        foreach (['avatar', 'id_card_front', 'id_card_back', 'bank_card_img'] as $field) {
            $this->assertStringContainsString('data-upload-field="' . $field . '"', $v2Blade, $field . ' v2 upload wrapper is missing.');
            $this->assertStringContainsString('data-upload-status="' . $field . '"', $v2Blade, $field . ' v2 upload status is missing.');
            $this->assertStringContainsString('data-upload-clear="' . $field . '"', $v2Blade, $field . ' v2 upload clear button is missing.');
            $this->assertStringContainsString('data-upload-name="' . $field . '"', $v2Blade, $field . ' v2 upload file name is missing.');
            $this->assertStringContainsString('data-upload-size="' . $field . '"', $v2Blade, $field . ' v2 upload file size is missing.');
        }

        $this->assertStringNotContainsString('style="display:flex;gap:12px;align-items:center;"', $v2Blade);
        $this->assertStringNotContainsString('style="font-size:12px;color:var(--v2-muted);"', $v2Blade);
        $this->assertStringNotContainsString('max-width:100%;max-height:120px;margin-top:8px;display:none;border-radius:8px;', $v2Blade);
        $this->assertStringNotContainsString('<i data-lucide="upload"></i> Upload', $v2Blade);
        $this->assertStringContainsString('.front-v2-profile .crm-profile-upload-card', $v2Css);
        $this->assertStringContainsString('.front-v2-profile .crm-profile-upload-preview', $v2Css);
        $this->assertStringContainsString('.front-v2-profile .crm-profile-upload-meta', $v2Css);

        $this->assertStringNotContainsString('.profile-upload-card {', $blade, 'Profile upload component CSS must live in the shared front stylesheet, not inline Blade styles.');
        $this->assertStringNotContainsString('.profile-upload-card {', $editBlade, 'Profile edit upload component CSS must live in the shared front stylesheet, not inline Blade styles.');
        $this->assertStringNotContainsString('.profile-upload-card {', $v2Blade, 'Profile v2 upload component CSS must live in stylesheets, not inline Blade styles.');
        $this->assertStringContainsString('class="layui-form layui-form-pane"', $editBlade, 'Profile edit form must use the same pane layout as the main profile page.');
        $this->assertStringContainsString('crm-profile-sensitive', $editBlade, 'Profile edit header must expose the same compact identity strip as the main profile page.');
        foreach (['profileName', 'profileUserId', 'profilePhoneMasked', 'profileEmailMasked', 'profileIdCardMasked'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $editBlade, 'Profile edit page is missing polished header field: ' . $id);
            $this->assertStringContainsString('#' . $id, $editScript, 'Profile edit script must populate polished header field: ' . $id);
        }

        foreach ([
            '.crm-profile-shell',
            '.crm-profile-hero',
            '.crm-profile-avatar-block',
            '.crm-profile-avatar-wrap',
            '.crm-profile-avatar-action',
            '.crm-profile-avatar-wrap:hover .crm-profile-avatar-action',
            '.crm-profile-upload-field',
            '.crm-profile-upload-card',
            '.crm-profile-upload-preview',
            '.crm-profile-upload-meta',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, 'Shared front stylesheet is missing profile upload polish: ' . $needle);
        }

        foreach ([
            'grid-template-columns: 76px minmax(0, 1fr);',
            '.crm-profile-shell .profile-actions',
            'clear: both;',
            '.crm-profile-shell .layui-form-pane .layui-form-label',
            'width: 88px;',
            '.crm-profile-shell .layui-form-pane > .layui-row',
            'width: 100%;',
            '.crm-profile-avatar-block .crm-profile-upload-meta',
            'display: none;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, 'Mobile profile layout must stay compact and Layui-like: ' . $needle);
        }
    }

    public function test_crm_ajax_global_mask_contract(): void
    {
        $script = $this->publicScript('common/ajax.js');
        $layuiCommon = $this->publicScript('front/layui/common.js');
        $sharedUtils = file_get_contents(public_path('js/shared/utils.js')) ?: '';
        $css = file_get_contents(public_path('css/front/style.css')) ?: '';
        $adminCss = file_get_contents(public_path('css/admin/style.css')) ?: '';
        $legacyFrontBlade = file_get_contents(resource_path('views/front/layouts/app.blade.php')) ?: '';
        $adminLoginBlade = file_get_contents(resource_path('admin/layui/auth/login.blade.php')) ?: '';
        $adminLoginScript = $this->publicScript('admin/layui/auth/login.js');

        $this->assertStringContainsString('function showGlobalMask', $script);
        $this->assertStringContainsString('function hideGlobalMask', $script);
        $this->assertStringContainsString('function configureMask', $script);
        $this->assertStringContainsString('function ()', $script);
        $this->assertStringContainsString("$(document).ajaxSend(function (_event, _xhr, settings)", $script);
        $this->assertStringContainsString("$(document).ajaxComplete(function (_event, _xhr, settings)", $script);
        $this->assertStringContainsString("settings.__crmMaskManaged", $script);
        $this->assertStringContainsString("settings.__crmMaskEnabled = showGlobalMask(settings);", $script);
        $this->assertStringContainsString("hideGlobalMask(settings.__crmMaskEnabled);", $script);
        $this->assertStringContainsString('function ()', $script);
        $this->assertStringContainsString('function translate(key, fallback)', $script);
        $this->assertStringContainsString("window.CrmLang && CrmLang.getLocale ? CrmLang.getLocale() : 'zh-CN'", $script);
        $this->assertStringNotContainsString("CrmLang.getLocale()", str_replace("window.CrmLang && CrmLang.getLocale ? CrmLang.getLocale() : 'zh-CN'", '', $script));
        $this->assertStringNotContainsString('alert(CrmLang.t(', $script);
        $this->assertStringContainsString('configureMask: configureMask', $script);
        $this->assertStringContainsString('window.CrmAjax = CrmAjax;', $script);
        $this->assertStringContainsString('CrmAjax.installJqueryGlobalMask();', $script);
        $this->assertStringContainsString('var defaultMaskConfig', $script);
        $this->assertStringContainsString("var allowedMaskStyles = ['spinner', 'minimal', 'dots', 'bar', 'card', 'gif'];", $script);
        $this->assertStringContainsString('function configureMask(config)', $script);
        $this->assertStringContainsString("allowedMaskStyles.indexOf(normalized.style) === -1", $script);
        $this->assertStringContainsString('config.gif || config.image', $script);
        $this->assertStringContainsString('crm-ajax-mask-style', $script);
        $this->assertStringContainsString(
            'var visible = maskNode && maskNode.classList.contains(\'is-visible\');',
            $script,
            'Changing the global Ajax mask style while a request is active must not hide the mask.'
        );
        $this->assertStringContainsString(
            'if (visible) { maskNode.classList.add(\'is-visible\'); }',
            $script,
            'ensureGlobalMask must preserve is-visible after replacing the mask className.'
        );
        $this->assertStringContainsString('.crm-ajax-mask', $css);
        $this->assertStringContainsString('.crm-ajax-mask-style-gif.has-gif', $css);
        $this->assertStringContainsString('.crm-ajax-mask-style-dots', $css);
        $this->assertStringContainsString('.crm-ajax-mask-style-bar', $css);
        $this->assertStringContainsString('.crm-ajax-mask-style-card', $css);
        $this->assertStringContainsString('CrmAjax.showGlobalMask({})', $sharedUtils);
        $this->assertStringContainsString('CrmAjax.hideGlobalMask(index)', $sharedUtils);
        $this->assertStringNotContainsString('layui.layer.load(', $sharedUtils, 'Shared loading helper must use the same global Ajax page mask instead of a second Layui loading layer.');

        foreach ([$legacyFrontBlade] as $legacyEntryBlade) {
            $this->assertStringContainsString('js/shared/ajax.js', $legacyEntryBlade);
            $this->assertStringContainsString('v=2026060702', $legacyEntryBlade);
        }
        foreach ([
            resource_path('front/layui/layouts/app.blade.php'),
            resource_path('admin/layui/layouts/app.blade.php'),
            resource_path('front/layui/auth/login.blade.php'),
            resource_path('front/layui/auth/register.blade.php'),
            resource_path('front/layui/auth/big-number-login.blade.php'),
            resource_path('front/layui/auth/forgot-password.blade.php'),
            resource_path('admin/layui/auth/login.blade.php'),
        ] as $entryBlade) {
            $this->assertStringContainsString("asset('/js/shared/ajax.js') }}?v=2026060702", file_get_contents($entryBlade) ?: '');
        }
        $this->assertStringContainsString('var maskEnabled = window.CrmAjax && CrmAjax.showGlobalMask ? CrmAjax.showGlobalMask(settings) : false;', $layuiCommon);
        $this->assertStringContainsString('settings.__crmMaskManaged = true;', $layuiCommon);
        $this->assertStringContainsString('CrmAjax.hideGlobalMask(maskEnabled);', $layuiCommon);
        $this->assertStringNotContainsString('var loadIdx = layer.load(1);', $this->publicScript('front/layui/auth/login.js'));
        foreach ([
            'public/js/apps/admin/layui/auth/login.js' => $this->publicScript('admin/layui/auth/login.js'),
            'public/js/apps/front/layui/module-page.js' => $this->publicScript('front/layui/module-page.js'),
            'public/js/apps/front/layui/profile/index.js' => $this->publicScript('front/layui/profile/index.js'),
            'public/js/apps/front/layui/profile/edit.js' => $this->publicScript('front/layui/profile/edit.js'),
            'public/js/apps/front/layui/profile/change-email.js' => $this->publicScript('front/layui/profile/change-email.js'),
            'public/js/apps/front/layui/profile/change-password.js' => $this->publicScript('front/layui/profile/change-password.js'),
            'public/js/apps/front/layui/auth/forgot-password.js' => $this->publicScript('front/layui/auth/forgot-password.js'),
            'public/js/apps/front/layui/auth/big-number-login.js' => $this->publicScript('front/layui/auth/big-number-login.js'),
        ] as $frontAjaxScript => $content) {
            $this->assertStringNotContainsString('layer.load(', $content, $frontAjaxScript . ' must rely on the global CrmAjax page mask instead of opening a second Layui loading layer.');
            $this->assertStringNotContainsString("layer.closeAll('loading')", $content, $frontAjaxScript . ' must not close shared loading layers directly.');
        }
        $this->assertStringContainsString("url: '/api/admin/login'", $adminLoginScript);
        $this->assertStringContainsString("guard: 'admin'", $adminLoginScript);
        $this->assertStringContainsString('CrmAjax.request({', $adminLoginScript);
        $this->assertStringContainsString("asset('/js/shared/ajax.js') }}?v=2026060702", $adminLoginBlade);
        foreach (['.crm-ajax-mask', '.crm-ajax-mask-style-gif.has-gif', '.crm-ajax-mask-style-dots', '.crm-ajax-mask-style-bar', '.crm-ajax-mask-style-card'] as $selector) {
            $this->assertStringContainsString($selector, $adminCss);
        }
    }

    public function test_admin_login_accepts_email_as_username(): void
    {
        $script = $this->publicScript('admin/layui/auth/login.js');
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AuthController.php')) ?: '';

        $this->assertStringContainsString('var email = fields.email', $script);
        $this->assertStringContainsString('username: email', $script, 'Blade email field must be submitted as the admin login account.');
        $this->assertStringContainsString("url: '/api/admin/login'", $script);

        $loginMethod = $this->sourceBetween($controller, 'public function login(Request $request)', 'public function isLegacyLoginRequest(Request $request)');
        $this->assertStringContainsString('$account = (string) $request->username;', $loginMethod);
        $this->assertStringContainsString("Admin::where('username', \$account)", $loginMethod);
        $this->assertStringContainsString("->orWhere('email', \$account)", $loginMethod, 'Admin API login must accept the Blade email field through the username parameter.');
    }

    public function test_bank_card_back_images_contract(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_05_31_000001_add_bank_card_back_images_to_user_auths.php')) ?: '';
        $model = file_get_contents(app_path('Models/UserAuth.php')) ?: '';
        $controller = file_get_contents(app_path('Http/Controllers/Front/ProfileController.php')) ?: '';
        $layui = $this->publicScript('front/layui/profile/index.js');

        $this->assertStringContainsString("'bank_card_back_img'", $migration);
        $this->assertStringContainsString("'bank_card_back_img_tmp'", $migration);
        $this->assertStringContainsString("'bank_card_back_img'", $model);
        $this->assertStringContainsString("'bank_card_back_img_tmp'", $model);
        $this->assertStringContainsString("'bank_card_back_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096'", $controller);
        $this->assertStringContainsString("'bank_card_back_img' => \$backPath", $controller);
        $this->assertStringContainsString("'bank_card_back_img_tmp' => \$backPath", $controller);
        $this->assertStringContainsString("bank_card_back_img: 'bank_card_img_back'", $layui);
        $this->assertStringContainsString("bank_card_back_img: 'bank_change_card_img_back'", $layui);
    }

    public function test_agent_detail_chain_and_order_stats_contract(): void
    {
        $module = $this->publicScript('front/layui/module-page.js');
        $subBlade = file_get_contents(resource_path('front/layui/agent/sub.blade.php')) ?: '';
        $customerBlade = file_get_contents(resource_path('front/layui/agent/customers.blade.php')) ?: '';
        $realtimeBlade = file_get_contents(resource_path('front/layui/commission/realtime.blade.php')) ?: '';

        $this->assertStringContainsString("'showChain' => true", $subBlade);
        $this->assertStringContainsString("'action' => 'updateUserChain'", $subBlade);
        $this->assertStringContainsString("'action' => 'showUserInfo'", $customerBlade);
        $this->assertStringNotContainsString("['key' => 'user_name', 'label' => 'front.user_name', 'action' => 'showUserInfo'", $subBlade . $customerBlade);
        $this->assertStringNotContainsString('userLoginHistory', $subBlade . $customerBlade);
        $this->assertStringNotContainsString("column.action === 'showLoginHistory'", $module);
        $this->assertStringNotContainsString('API.userLoginHistory', $module);
        $this->assertStringNotContainsString('loginHistoryColumns', $module);

        $this->assertStringContainsString('function renderChain', $module);
        $this->assertStringContainsString('if (!clickedChain.length)', $module);
        $this->assertStringContainsString('$chain.hide().empty()', $module);
        $this->assertStringContainsString('ids.slice(0, sourceIndex + 1)', $module);
        $this->assertStringContainsString('if (!ids.length && clickedChain.length)', $module);
        $this->assertStringContainsString('ids = clickedChain.slice()', $module);
        $this->assertStringContainsString('ids.slice(0, currentIndex + 1)', $module);
        $this->assertStringContainsString('front.open_order_count', $module);
        $this->assertStringContainsString('front.closed_order_count', $module);
        $this->assertStringContainsString('front.profit_7d', $module);
        $this->assertStringContainsString('front.profit_15d', $module);
        $this->assertStringContainsString('front.profit_30d', $module);
        $this->assertStringContainsString('crm-detail-bars', $module);

        $agentController = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';
        $userDetailMethod = $this->sourceBetween($agentController, 'public function userDetail(Request $request): JsonResponse', 'public function showUser');
        $legacyUserDetailMethod = $this->sourceBetween($agentController, 'public function legacyUserDetailPage', 'public function legacyLoginHistorySearch');
        foreach ([$userDetailMethod, $legacyUserDetailMethod] as $source) {
            $this->assertStringContainsString('->closed()', $source, 'Agent user detail order statistics must reuse the UserTrade closed scope.');
            $this->assertStringContainsString('->open()', $source, 'Agent user detail order statistics must reuse the UserTrade open scope.');
            $this->assertStringNotContainsString('1971-01-01', $source, 'Agent user detail must not use a different close_time sentinel from the order list.');
        }

        $this->assertStringContainsString('commission-realtime-module', $realtimeBlade);
        $this->assertStringContainsString('moduleSummaryToggle', $module);
        $this->assertStringContainsString('realtimeVisibleRows', $module);
        $this->assertStringContainsString('rows.slice(0, Math.max(1, pageState.perPage))', $module);
        $this->assertStringContainsString('detail_commission: 1', $module);
        $this->assertStringContainsString('per_page: 1', $module);
    }

    public function test_customer_action_modal_uses_legacy_field_names(): void
    {
        $customerBlade = file_get_contents(resource_path('front/layui/agent/customers.blade.php')) ?: '';
        $layui = $this->publicScript('front/layui/module-page.js');

        $this->assertStringContainsString("'action' => 'openCommissionTransfer'", $customerBlade);
        $this->assertStringContainsString("'action' => 'openGroupChange'", $customerBlade);
        $this->assertStringContainsString('function openCustomerActionModal', $layui);
        $this->assertStringContainsString('data-customer-action-form', $layui);
        $this->assertStringNotContainsString("navigateFrontPage(frontPageRouteUrl('front_page_commission_transfer'", $layui);
        $this->assertStringNotContainsString("navigateFrontPage(frontPageRouteUrl('front_page_agent_group_change'", $layui);

        $layuiCustomerModal = $this->sourceBetween($layui, 'function customerActionModalHtml', 'function openCustomerActionModal');

        $this->assertStringContainsString('name="sub_agent_id"', $layuiCustomerModal, 'The legacy customer commission transfer API still expects sub_agent_id.');
        $this->assertStringContainsString("t('front.target_user_id')", $layuiCustomerModal, 'Direct customer transfer modal should display a target-user label.');
        $this->assertStringNotContainsString("t('front.sub_agent_id')", $layuiCustomerModal, 'Direct customer transfer modal must not call the selected customer a sub-agent.');
    }

    public function test_agent_direct_relation_drilldown_contract(): void
    {
        $subBlade = file_get_contents(resource_path('front/layui/agent/sub.blade.php')) ?: '';
        $layui = $this->publicScript('front/layui/module-page.js');
        $controller = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';
        $pageController = file_get_contents(app_path('Http/Controllers/CrmUi/Front/PageController.php')) ?: '';
        $agentSubDefinition = $this->sourceBetween($pageController, "'agent/sub' => [", "'agent/customers' => [");
        $subListMethod = $this->sourceBetween($controller, 'public function subList(Request $request): JsonResponse', 'public function proxyListSearch');
        $customerListMethod = $this->sourceBetween($controller, 'public function customerList(Request $request): JsonResponse', 'public function directCustListSearch');

        $this->assertStringContainsString("'key' => 'agentsTotal', 'label' => 'front.agent_count', 'action' => 'showDirectAgents'", $subBlade);
        $this->assertStringContainsString("'key' => 'accountTotal', 'label' => 'front.customer_count', 'action' => 'showDirectCustomers'", $subBlade);
        $this->assertStringNotContainsString("'key' => 'detail'", $agentSubDefinition, 'CrmUI front sub-agent rows must not render a duplicate detail action.');
        $this->assertStringNotContainsString("'route' => 'front_api_users_show'", $agentSubDefinition, 'CrmUI front sub-agent rows must not call the user detail endpoint from a generic detail button.');

        $this->assertStringContainsString("function openDirectRelationList", $layui);
        $this->assertStringContainsString("column.action === 'showDirectAgents' || column.action === 'showDirectCustomers'", $layui);
        $this->assertStringContainsString("var endpoint = type === 'agents' ? API.agentSubList : API.agentCustomerList;", $layui);
        $this->assertStringContainsString("parent_id: targetUserId", $layui);
        $this->assertStringContainsString("direct_only: 1", $layui);

        foreach ([$subListMethod, $customerListMethod] as $method) {
            $this->assertStringContainsString('[$queryAgentId, $directOnly] = $this->legacyAgentParentScope($request, $agentId);', $method);
            $this->assertStringContainsString("if (\$queryAgentId !== \$agentId) {", $method);
            $this->assertStringContainsString('$this->canViewUser($agentId, $queryAgentId)', $method);
            $this->assertStringContainsString('$directOnly = $request->has(\'direct_only\') && $request->direct_only == 1;', $method);
            $this->assertStringContainsString("UserInfo::with(['login', 'level'])", $method);
            $this->assertStringContainsString("->whereIn('user_id', \$descendantIds)", $method);
            $this->assertStringContainsString('$this->scopeDepth(', $method);
            $this->assertStringNotContainsString('AgentDescendant::', $method);
        }

        $this->assertStringContainsString('FrontLegacyData::userScopeIds($queryAgentId, false, 1, $directOnly ? true : null)', $subListMethod);
        $this->assertStringContainsString("->where('account_type', 1)", $subListMethod);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($queryAgentId, false, 2, $directOnly ? true : null)', $customerListMethod);
        $this->assertStringContainsString("->where('account_type', 2)", $customerListMethod);
        $this->assertStringContainsString("if (\$request->filled('parent_id'))", $controller);
        $this->assertStringContainsString("foreach (['userPId', 'user_pid'] as \$legacyKey)", $controller);
        $this->assertStringContainsString("return [(int) \$request->input(\$legacyKey), true];", $controller);
        $this->assertStringContainsString('private function scopeDepth(UserInfo $user, int $ancestorId, int $isDirect): int', $controller);
    }

    public function test_agent_count_drilldown_scope_boundary(): void
    {
        $now = time();
        $rootAgentId = 990201;
        $clickedAgentId = 990202;
        $directAgentId = 990203;
        $indirectAgentId = 990204;
        $directCustomerId = 990205;
        $indirectCustomerId = 990206;
        $outsideAgentId = 990207;

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $rootAgentId],
            [
                'email' => 'front-agent-count-drilldown@example.test',
                'password' => 'front-agent-count-drilldown-test',
                'account_type' => 1,
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
            ]
        );
        $loginId = (int) DB::table('user_logins')->where('user_id', $rootAgentId)->value('id');

        foreach ([
            [$rootAgentId, 'Drill Root Agent', 1, 0],
            [$clickedAgentId, 'Drill Clicked Agent', 1, $rootAgentId],
            [$directAgentId, 'Drill Direct Agent', 1, $clickedAgentId],
            [$indirectAgentId, 'Drill Indirect Agent', 1, $directAgentId],
            [$directCustomerId, 'Drill Direct Customer', 2, $clickedAgentId],
            [$indirectCustomerId, 'Drill Indirect Customer', 2, $directAgentId],
            [$outsideAgentId, 'Drill Outside Agent', 1, 0],
        ] as [$userId, $userName, $accountType, $parentId]) {
            DB::table('user_infos')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'login_id' => $userId === $rootAgentId ? $loginId : 0,
                    'user_name' => $userName,
                    'phone' => '',
                    'gender' => 1,
                    'avatar' => null,
                    'level_id' => $accountType === 1 ? 1 : 0,
                    'group_id' => 0,
                    'parent_id' => $parentId,
                    'account_type' => $accountType,
                    'family_tree' => $parentId > 0 ? $rootAgentId . ',' . $userId : (string) $userId,
                    'total_funds' => 0,
                    'used_margin' => 0,
                    'avail_margin' => 0,
                    'equity' => 0,
                    'effective_credit' => 0,
                    'risk_ratio' => 0,
                    'margin_amount' => 0,
                    'leverage' => 0,
                    'cust_vol' => '0',
                    'pay_provider_id' => 0,
                    'equity_ratio' => 0,
                    'comm_rate' => 0,
                    'is_ecn' => 0,
                    'follow_parent_ecn' => 0,
                    'auth_status' => 1,
                    'is_mt4_synced' => 1,
                    'is_mt4_enabled' => 1,
                    'is_mt4_readonly' => 0,
                    'is_withdrawal_allowed' => 0,
                    'is_deposit_allowed' => 0,
                    'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
                    'original_group' => '',
                    'mt4_group' => '',
                    'mt4_code' => 0,
                    'trading_mode' => 0,
                    'settle_method' => 1,
                    'settle_cycle' => 1,
                    'country' => '',
                    'city' => '',
                    'state' => '',
                    'address' => '',
                    'is_gift_allowed' => 0,
                    'data_source' => 0,
                    'remark' => 'front agent count drilldown regression test',
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        foreach ([
            [$rootAgentId, $clickedAgentId, 1, 1, 1],
            [$rootAgentId, $directAgentId, 1, 0, 2],
            [$rootAgentId, $indirectAgentId, 1, 0, 3],
            [$rootAgentId, $directCustomerId, 2, 0, 2],
            [$rootAgentId, $indirectCustomerId, 2, 0, 3],
            [$clickedAgentId, $directAgentId, 1, 1, 1],
            [$clickedAgentId, $indirectAgentId, 1, 0, 2],
            [$clickedAgentId, $directCustomerId, 2, 1, 1],
            [$clickedAgentId, $indirectCustomerId, 2, 0, 2],
        ] as [$agentId, $descendantId, $descendantType, $isDirect, $depth]) {
            DB::table('agent_descendants')->updateOrInsert(
                ['agent_id' => $agentId, 'descendant_id' => $descendantId],
                [
                    'descendant_type' => $descendantType,
                    'is_direct' => $isDirect,
                    'depth' => $depth,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $acting = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user');

        $agents = $acting->getJson('/api/front/agents/direct?parent_id=' . $clickedAgentId . '&direct_only=1&per_page=50');
        $agents->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.user_id', (string) $directAgentId)
            ->assertJsonPath('data.list.data.0.user_name', 'Drill Direct Agent');

        $customers = $acting->getJson('/api/front/agents/direct-customers?parent_id=' . $clickedAgentId . '&direct_only=1&per_page=50');
        $customers->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.user_id', (string) $directCustomerId)
            ->assertJsonPath('data.list.data.0.user_name', 'Drill Direct Customer');

        $legacyAgents = $acting->getJson('/api/front/agents/direct?userPId=' . $clickedAgentId . '&per_page=50');
        $legacyAgents->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.user_id', (string) $directAgentId);

        $legacyCustomers = $acting->getJson('/api/front/agents/direct-customers?user_pid=' . $clickedAgentId . '&per_page=50');
        $legacyCustomers->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.user_id', (string) $directCustomerId);
    }

    public function test_commission_transfer_agent_options_contract(): void
    {
        $transferBlade = file_get_contents(resource_path('front/layui/commission/transfer.blade.php')) ?: '';
        $partial = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';
        $layui = $this->publicScript('front/layui/module-page.js');
        $routes = file_get_contents(base_path('routes/front.php')) ?: '';
        $controller = file_get_contents(app_path('Http/Controllers/Front/CommissionController.php')) ?: '';

        $this->assertStringContainsString("'name' => 'sub_agent_id', 'label' => 'front.sub_agent_id', 'type' => 'select', 'verify' => 'required', 'dynamicOptions' => 'direct_agents'", $transferBlade);
        $this->assertStringContainsString('@if(!empty($field[\'dynamicOptions\'])) data-dynamic-options="{{ $field[\'dynamicOptions\'] }}" @endif', $partial);
        $this->assertStringContainsString("[data-dynamic-options]", $layui);
        $this->assertStringContainsString("direct_agents: '/api/front/commissions/transfer-agent-options'", $layui);
        $this->assertStringContainsString("direct_agents: 'GET'", $layui);
        $this->assertStringNotContainsString("direct_agents: '/api/front/commission-transfer-agents'", $layui);

        $this->assertStringContainsString("'chartGroups' =>", $transferBlade);
        $this->assertStringContainsString('commissionTransferTrendChart', $transferBlade);
        $this->assertStringContainsString('commissionTransferGenderChart', $transferBlade);
        $this->assertStringContainsString('commissionTransferGenderAmountChart', $transferBlade);
        $this->assertStringContainsString('analytics.ranges.3.commission_amount', $transferBlade);
        $this->assertStringContainsString('analytics.gender.male.count_percentage', $transferBlade);
        $this->assertStringContainsString('analytics.gender.male.commission_amount', $transferBlade);
        $this->assertStringContainsString("Route::get('/commissions/transfer-agent-options', 'CommissionController@transferAgentOptions')->name('front_api_commissions_transfer_agent_options');", $routes);
        $this->assertStringNotContainsString("Route::post('/commission-transfer-agents', 'CommissionController@transferAgentOptions')->name('front_api_commission_transfer_agents');", $routes);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('front_api_commission_transfer_agents'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('front_api_commissions_transfer_agent_options'));
        $this->assertStringContainsString('public function transferAgentOptions(Request $request): JsonResponse', $controller);
        $this->assertStringContainsString('$directAgentIds = FrontLegacyData::userScopeIds($agentId, false, 1, true);', $controller);
        $this->assertStringContainsString("UserInfo::with('level')", $controller);
        $this->assertStringContainsString("->whereIn('user_id', \$directAgentIds)", $controller);
        $this->assertStringContainsString("->where('account_type', 1)", $controller);
        $this->assertStringContainsString("->orderBy('user_id')", $controller);
        $this->assertStringContainsString('$isSubAgent = in_array((int) $subAgentId, $directAgentIds, true);', $controller);
        $this->assertStringNotContainsString("AgentDescendant::where('agent_id', \$agentId)", $controller);
        $this->assertStringContainsString("'agent_level_name'", $controller);
        $this->assertStringContainsString("'label'", $controller);
        $this->assertStringContainsString('(string) $agent->user_id', $controller);
        $this->assertStringContainsString('$name', $controller);
        $this->assertStringContainsString('$levelName', $controller);
        foreach (['commission_transfer_trend', 'commission_transfer_gender_profile', 'commission_transfer_amount_profile'] as $key) {
            $this->assertStringContainsString("'" . $key . "' =>", file_get_contents(resource_path('lang/zh-CN/front.php')) ?: '', 'zh-CN missing front.' . $key);
            $this->assertStringContainsString("'" . $key . "' =>", file_get_contents(resource_path('lang/en/front.php')) ?: '', 'en missing front.' . $key);
        }
    }

    public function test_commission_transfer_agent_options_runtime(): void
    {
        $now = time();
        $agentId = 990101;
        $directAgentId = 990102;
        $indirectAgentId = 990103;
        $directCustomerId = 990104;

        $levelId = (int) (DB::table('agent_levels')->value('id') ?: 0);
        $levelName = $levelId > 0
            ? (string) DB::table('agent_levels')->where('id', $levelId)->value('name')
            : '';

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $agentId],
            [
                'email' => 'front-transfer-agent-options@example.test',
                'password' => 'front-transfer-agent-options-test',
                'account_type' => 1,
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
            ]
        );
        $loginId = (int) DB::table('user_logins')->where('user_id', $agentId)->value('id');

        foreach ([
            [$agentId, 'Transfer Root Agent', 1, 0],
            [$directAgentId, 'Transfer Direct Agent', 1, $agentId],
            [$indirectAgentId, 'Transfer Indirect Agent', 1, $directAgentId],
            [$directCustomerId, 'Transfer Direct Customer', 2, $agentId],
        ] as [$userId, $userName, $accountType, $parentId]) {
            DB::table('user_infos')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'login_id' => $userId === $agentId ? $loginId : 0,
                    'user_name' => $userName,
                    'phone' => '',
                    'gender' => 1,
                    'avatar' => null,
                    'level_id' => $accountType === 1 ? $levelId : 0,
                    'group_id' => 0,
                    'parent_id' => $parentId,
                    'account_type' => $accountType,
                    'family_tree' => $parentId > 0 ? $agentId . ',' . $userId : (string) $userId,
                    'total_funds' => 0,
                    'used_margin' => 0,
                    'avail_margin' => 0,
                    'equity' => 0,
                    'effective_credit' => 0,
                    'risk_ratio' => 0,
                    'margin_amount' => 0,
                    'leverage' => 0,
                    'cust_vol' => '0',
                    'pay_provider_id' => 0,
                    'equity_ratio' => 0,
                    'comm_rate' => 0,
                    'is_ecn' => 0,
                    'follow_parent_ecn' => 0,
                    'auth_status' => 1,
                    'is_mt4_synced' => 1,
                    'is_mt4_enabled' => 1,
                    'is_mt4_readonly' => 0,
                    'is_withdrawal_allowed' => 0,
                    'is_deposit_allowed' => 0,
                    'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
                    'original_group' => '',
                    'mt4_group' => '',
                    'mt4_code' => 0,
                    'trading_mode' => 0,
                    'settle_method' => 1,
                    'settle_cycle' => 1,
                    'country' => '',
                    'city' => '',
                    'state' => '',
                    'address' => '',
                    'is_gift_allowed' => 0,
                    'data_source' => 0,
                    'remark' => 'front commission transfer options regression test',
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        foreach ([
            [$agentId, $directAgentId, 1, 1],
            [$agentId, $indirectAgentId, 1, 0],
            [$agentId, $directCustomerId, 2, 1],
        ] as [$ownerId, $descendantId, $descendantType, $isDirect]) {
            DB::table('agent_descendants')->updateOrInsert(
                ['agent_id' => $ownerId, 'descendant_id' => $descendantId],
                [
                    'descendant_type' => $descendantType,
                    'is_direct' => $isDirect,
                    'depth' => $isDirect ? 1 : 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/transfer-agent-options');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', $directAgentId)
            ->assertJsonPath('data.0.user_id', $directAgentId)
            ->assertJsonPath('data.0.user_name', 'Transfer Direct Agent');

        $data = $response->json('data.0');
        $this->assertStringContainsString((string) $directAgentId, $data['label']);
        $this->assertStringContainsString('Transfer Direct Agent', $data['label']);
        if ($levelName !== '') {
            $this->assertSame($levelName, $data['agent_level_name']);
            $this->assertStringContainsString($levelName, $data['label']);
        }
    }

    public function test_front_realtime_commission_api_returns_current_agent_rebate_rows(): void
    {
        $this->seedFrontDemoDataAndCaptureOwnedConfig();

        $login = UserLogin::where('user_id', 1001)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/realtime?orderId=900101&per_page=5');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = data_get($response->json(), 'data.list.data', []);
        $this->assertNotEmpty($rows);
        $row = $rows[0];

        foreach ([
            'ticket',
            'current_commission_amount',
            'current_commission_status',
            'current_commission_status_text',
            'rebate_ratio',
            'commission_updated_at',
            'order_created_at',
            'order_closed_at',
        ] as $field) {
            $this->assertArrayHasKey($field, $row);
        }

        $this->assertSame('900101', (string) $row['ticket']);
        $this->assertSame(__('front.status_settled'), $row['current_commission_status_text']);
        $this->assertNotSame('', (string) $row['commission_updated_at']);
        $this->assertSame(2, (int) data_get($row, 'user_info.account_type'));
        $this->assertArrayNotHasKey('agent_level_name', data_get($row, 'user_info', []));
        $this->assertArrayNotHasKey('agent_level_rank', data_get($row, 'user_info', []));
    }

    public function test_commission_pages_and_api_contract(): void
    {
        $realtimeBlade = file_get_contents(resource_path('front/layui/commission/realtime.blade.php')) ?: '';
        $historyBlade = file_get_contents(resource_path('front/layui/commission/history.blade.php')) ?: '';
        $transferBlade = file_get_contents(resource_path('front/layui/commission/transfer.blade.php')) ?: '';
        $controller = file_get_contents(app_path('Http/Controllers/Front/CommissionController.php')) ?: '';
        $zhFront = file_get_contents(resource_path('lang/zh-CN/front.php')) ?: '';
        $enFront = file_get_contents(resource_path('lang/en/front.php')) ?: '';
        $zhStatic = $this->publicScript('shared/lang/common/zh-CN.js');
        $enStatic = $this->publicScript('shared/lang/common/en.js');

        foreach ([
            'current_commission_amount',
            'current_commission_status_text',
            'rebate_ratio',
            'commission_updated_at',
            'order_created_at',
            'order_closed_at',
        ] as $field) {
            $this->assertStringContainsString($field, $realtimeBlade, 'Layui realtime commission table is missing field: ' . $field);
            $this->assertStringContainsString("'" . $field . "'", $controller, 'Realtime commission API is missing response field: ' . $field);
        }

        foreach ([
            'current_commission_amount',
            'current_commission_status_text',
            'commission_updated_at',
            'order_created_at',
            'order_closed_at',
        ] as $field) {
            $this->assertStringContainsString($field . ':', $zhStatic);
            $this->assertStringContainsString($field . ':', $enStatic);
        }

        foreach ([
            'commission_trend',
            'commission_gender_profile',
            'commission_gender_count_profile',
            'commission_gender_amount_profile',
            'commission_transfer_trend',
            'commission_transfer_gender_profile',
            'commission_transfer_amount_profile',
            'last_3_days',
            'last_7_days',
            'last_15_days',
            'last_30_days',
        ] as $field) {
            $this->assertStringContainsString($field . ':', $zhStatic);
            $this->assertStringContainsString($field . ':', $enStatic);
        }

        $this->assertStringContainsString('currentAgentOrderCommission', $controller);
        $this->assertStringContainsString('CommissionRecord::where(\'agent_id\', $agentId)', $controller);
        $this->assertStringContainsString('->where(\'mt4_order_id\', (int) $trade->ticket)', $controller);
        $this->assertStringContainsString("'current_commission_status'", $controller);
        $this->assertStringContainsString("'current_commission_status_text'", $controller);
        $realtimeMethod = $this->sourceBetween($controller, 'public function realTime(Request $request): JsonResponse', 'public function realtimeRebateSearch');
        $legacyRealtimeDetailMethod = $this->sourceBetween($controller, 'public function realtimeRebateDetail', 'private function userDetail');
        $this->assertStringContainsString('->closed();', $realtimeMethod, 'Realtime commission list must reuse the UserTrade closed scope.');
        $this->assertStringNotContainsString("->where('close_time', '>', '1970-01-01 00:00:00')", $realtimeMethod, 'Realtime commission list must not duplicate the closed order sentinel.');
        $this->assertStringNotContainsString("->where('close_time', '1970-01-01 00:00:00')", $realtimeMethod, 'Realtime commission list must not only query open orders.');
        $this->assertStringContainsString("->orderBy('close_time', 'desc')", $realtimeMethod, 'Realtime commission list should prioritize order close time.');
        $this->assertStringContainsString('$agentId = $this->legacyFrontUserId($request);', $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail must identify the current front agent.');
        $this->assertStringContainsString('$currentCommission = $this->currentAgentOrderCommission($trade, (int) $agentId);', $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail must show the current account commission amount, not the order-wide agent commission.');
        $this->assertStringContainsString('$this->commissionService->orderCommissionDetails($trade, $agentId)', $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail must stop rebate visibility at the current agent.');
        $this->assertStringContainsString("'agent_level' => \$rebate['agent_level'] ?? ''", $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail must label scoped rebate rows by agent level instead of pretending the level is a parent ID.');
        $this->assertStringNotContainsString("'parent_id' => \$rebate['agent_level'] ?? ''", $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail must not put agent level into parent_id.');
        $this->assertStringNotContainsString('CommissionRecord::where(function', $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail must not expose every rebate record for the order.');
        $this->assertStringContainsString("legacyDetailItem('\u{5F53}\u{524D}\u{8D26}\u{6237}\u{8FD4}\u{4F63}'", $legacyRealtimeDetailMethod);
        $this->assertStringContainsString("legacyDetailItem('\u{5F53}\u{524D}\u{8D26}\u{6237}\u{8FD4}\u{4F63}\u{6BD4}\u{4F8B}'", $legacyRealtimeDetailMethod);
        $this->assertStringContainsString("legacyDetailItem('\u{5F53}\u{524D}\u{8D26}\u{6237}\u{8FD4}\u{4F63}\u{72B6}\u{6001}'", $legacyRealtimeDetailMethod);
        $this->assertStringContainsString("legacyDetailItem('\u{8FD4}\u{4F63}\u{66F4}\u{65B0}\u{65F6}\u{95F4}'", $legacyRealtimeDetailMethod);
        $this->assertStringContainsString("<th>\u{4EE3}\u{7406}\u{7EA7}\u{522B}</th>", $legacyRealtimeDetailMethod);
        $this->assertStringContainsString("<th>\u{8FD4}\u{4F63}\u{6BD4}\u{4F8B}</th>", $legacyRealtimeDetailMethod);
        $this->assertStringNotContainsString("<th>\u{4E0A}\u{7EA7}ID</th>", $legacyRealtimeDetailMethod);
        $this->assertStringNotContainsString("<th>\u{5B9E}\u{9645}\u{91D1}\u{989D}</th>", $legacyRealtimeDetailMethod);
        $this->assertStringContainsString('$rebate->rebate_ratio', $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail should display rebate ratio from the scoped detail row.');
        $this->assertStringContainsString('$rebate->settle_status_text', $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail should display scoped settlement text directly.');
        $this->assertStringNotContainsString('FrontLegacyData::money($rebate->real_amount)', $legacyRealtimeDetailMethod, 'Legacy realtime rebate detail must not format the rebate ratio column as money.');
        $this->assertStringContainsString('commissionHistoryAnalytics', $controller);
        $this->assertStringContainsString('UserInfo::whereIn(\'user_id\', $userIds)', $controller);
        $this->assertStringContainsString("'analytics'", $controller);
        $this->assertStringContainsString("'ranges'", $controller);
        $this->assertStringContainsString('$totalGenderCount', $controller);
        $this->assertStringContainsString('$totalGenderCommission', $controller);
        $this->assertStringContainsString("'count_percentage'", $controller);
        $this->assertStringContainsString("'commission_percentage'", $controller);
        foreach ([3, 7, 15, 30] as $days) {
            $this->assertStringContainsString("'days' => " . $days, $controller);
        }

        $this->assertStringContainsString("'chartGroups' =>", $historyBlade);
        $this->assertStringContainsString('/js/vendor/echarts/echarts.common.min.js', $historyBlade);
        $this->assertStringContainsString('/js/vendor/echarts/echarts.common.min.js', $transferBlade);
        $this->assertStringContainsString('commissionTrendChart', $historyBlade);
        $this->assertStringContainsString('commissionGenderChart', $historyBlade);
        $this->assertStringContainsString('commissionGenderAmountChart', $historyBlade);
        $this->assertStringContainsString('analytics.ranges.3.commission_amount', $historyBlade);
        $this->assertStringContainsString('analytics.gender.male.count_percentage', $historyBlade);
        $this->assertStringContainsString('analytics.gender.male.commission_amount', $historyBlade);
        $this->assertStringContainsString('analytics.gender.male.commission_percentage', $historyBlade);
        foreach (['current_commission_amount', 'current_commission_status_text', 'commission_updated_at', 'order_created_at', 'order_closed_at', 'commission_trend', 'commission_gender_profile', 'commission_gender_count_profile', 'commission_gender_amount_profile'] as $key) {
            $this->assertStringContainsString("'" . $key . "' =>", $zhFront, 'zh-CN 缂哄皯 front.' . $key);
            $this->assertStringContainsString("'" . $key . "' =>", $enFront, 'en 缂哄皯 front.' . $key);
        }
    }

    public function test_chain_reset_logic(): void
    {
        $layui = $this->publicScript('front/layui/module-page.js');

        $this->assertStringContainsString('if (!ids.length && clickedChain.length)', $layui);
    }

    public function test_frontend_scripts_have_no_debug_console_output(): void
    {
        $violations = $this->filesContaining(['console.log(', 'console.warn(', 'console.error(', 'debugger'], [
            public_path('js/apps/front'),
            public_path('js/apps/admin'),
            resource_path('front'),
            resource_path('admin'),
        ]);

        $this->assertSame([], $violations, 'Custom frontend entry scripts and templates must not keep debug console output.');
    }

    public function test_front_layui_pages_do_not_ship_dead_batch_fix_patches(): void
    {
        $script = $this->publicScript('front/layui/pages.js');

        foreach ([
            "registry['batch-fixes']",
            'loadAccountFlowData(tabIndex)',
            'window.FrontLayuiFixes',
            'window.CrmAjax.request = function(options)',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $script, 'Layui pages.js must not ship stale global patch code: ' . $needle);
        }
    }

    private function filesContaining(array $needles, array $paths): array
    {
        $violations = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! preg_match('/\.(js|css|blade\.php)$/', $file->getFilename())) {
                    continue;
                }

                $content = file_get_contents($file->getPathname()) ?: '';
                foreach ($needles as $needle) {
                    if (strpos($content, $needle) !== false) {
                        $violations[] = $needle . ' @ ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    }
                }
            }
        }

        sort($violations);

        return $violations;
    }

    private function ensureGiftItemsTestTable(): bool
    {
        if (Schema::hasTable('gift_items')) {
            return false;
        }

        Schema::create('gift_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200)->default('');
            $table->string('description', 1000)->default('');
            $table->integer('points_cost')->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->string('image_url', 500)->default('');
            $table->unsignedInteger('created_at')->default(0);
            $table->unsignedInteger('updated_at')->default(0);
            $table->unsignedInteger('deleted_at')->nullable();
        });

        return true;
    }

    private function filesUnder(string $path, string $suffix): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function extractPhpArrayBlocks(string $content, string $startToken): array
    {
        $blocks = [];
        $offset = 0;

        while (($start = strpos($content, $startToken, $offset)) !== false) {
            $open = strpos($content, '[', $start);
            if ($open === false) {
                break;
            }

            $depth = 0;
            $quote = null;
            $escaped = false;
            $length = strlen($content);

            for ($i = $open; $i < $length; $i++) {
                $char = $content[$i];

                if ($quote !== null) {
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }
                    if ($char === '\\') {
                        $escaped = true;
                        continue;
                    }
                    if ($char === $quote) {
                        $quote = null;
                    }
                    continue;
                }

                if ($char === "'" || $char === '"') {
                    $quote = $char;
                    continue;
                }

                if ($char === '[') {
                    $depth++;
                    continue;
                }

                if ($char === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $blocks[] = substr($content, $open, $i - $open + 1);
                        $offset = $i + 1;
                        break;
                    }
                }
            }

            if ($offset <= $start) {
                $offset = $start + strlen($startToken);
            }
        }

        return $blocks;
    }

    private function sourceBetween(string $content, string $startToken, string $endToken): string
    {
        $start = strpos($content, $startToken);
        $end = $start === false ? false : strpos($content, $endToken, $start + strlen($startToken));

        if ($start === false || $end === false) {
            return '';
        }

        return substr($content, $start, $end - $start);
    }

    private function publicScript(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $path = public_path('js/' . $this->publicScriptPath($normalized));

        if (is_file($path)) {
            return file_get_contents($path) ?: '';
        }

        if (preg_match('#^(front|admin)/layui/(.+)\.js$#', $normalized, $matches)) {
            return $this->aggregatedLayuiPageScript($matches[1], $matches[2]);
        }

        return '';
    }

    private function publicScriptPath(string $normalized): string
    {
        if (str_starts_with($normalized, 'common/')) {
            return 'shared/' . substr($normalized, strlen('common/'));
        }

        foreach (['front/', 'admin/'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return 'apps/' . $normalized;
            }
        }

        return $normalized;
    }

    private function aggregatedLayuiPageScript(string $area, string $page): string
    {
        $aggregatePath = public_path('js/apps/' . $area . '/layui/pages.js');
        $source = is_file($aggregatePath) ? (file_get_contents($aggregatePath) ?: '') : '';
        $needle = "registry['" . str_replace("'", "\\'", $page) . "'] = once(function () {";
        $start = strpos($source, $needle);

        if ($start === false) {
            return '';
        }

        $next = strpos($source, "\n    registry['", $start + strlen($needle));
        $exports = strpos($source, "\n    exports(", $start + strlen($needle));
        $end = $next === false ? $exports : $next;

        if ($end === false) {
            return substr($source, $start);
        }

        return substr($source, $start, $end - $start);
    }

    private function assertFunctionDeclarationIsExecutable(string $source, string $functionName, string $label): void
    {
        $needle = 'function ' . $functionName;
        $found = false;

        foreach (preg_split('/\R/', $source) ?: [] as $lineNumber => $line) {
            $position = strpos($line, $needle);
            if ($position === false) {
                continue;
            }

            $found = true;
            $commentPosition = strpos($line, '//');
            $this->assertTrue(
                $commentPosition === false || $commentPosition > $position,
                $label . ' must not be hidden behind a line comment at line ' . ($lineNumber + 1) . '.'
            );
            $this->assertMatchesRegularExpression(
                '/^\s*function\s+' . preg_quote($functionName, '/') . '\s*\(/',
                $line,
                $label . ' must be declared on its own executable line.'
            );
        }

        $this->assertTrue($found, $label . ' declaration is missing.');
    }

    private function languageKeys(string $locale): array
    {
        $keys = [];
        $path = resource_path('lang/' . $locale);

        if (! is_dir($path)) {
            return $keys;
        }

        foreach (glob($path . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $group = basename($file, '.php');
            $data = include $file;
            if (! is_array($data)) {
                continue;
            }

            foreach ($this->flattenLanguageArray($data, $group) as $key) {
                $keys[] = $key;
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    private function flattenLanguageArray(array $data, string $prefix): array
    {
        $keys = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix . '.' . $key;
            if (is_array($value)) {
                array_push($keys, ...$this->flattenLanguageArray($value, $fullKey));
                continue;
            }
            $keys[] = $fullKey;
        }

        return $keys;
    }
}
