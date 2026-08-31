<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:05
 */

namespace Tests\Feature;

use App\Models\UserLogin;
use App\Models\UserInfo;
use App\Models\UserAuth;
use App\Models\AgentDescendant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RegisteredUserFixtureCleaner;
use Tests\TestCase;

/**
 * 前台核心业务模块全链路闭环保真测试。
 *
 * 文件功能：
 * - 验证用户登录后的核心业务 API：仪表盘、入金选项、提现选项、代理列表、资金流水、持仓、订单、佣金。
 * - 每条路由覆盖中间件链路（JWT → SSO）→ 控制器 → 数据库查询 → 响应格式。
 *
 * 涉及表：user_mt4_provisioning_outbox、user_login_logs、user_logins、user_infos、agent_descendants。
 * 只读业务表：deposit_records、withdraw_records、mt4_trades、commission_records。
 */
class FrontBusinessModulesClosureTest extends TestCase
{
    private string $testEmail;
    private string $testPassword = 'Test@123456';
    private ?string $token = null;
    private ?int $testUserId = null;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $staleUserIds = UserLogin::where('email', 'like', 'e2e_biz_%@test.local')->pluck('user_id');
        RegisteredUserFixtureCleaner::forceDelete($staleUserIds);

        $this->testEmail = 'e2e_biz_' . md5(uniqid('', true)) . '@test.local';
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testUserId) {
                RegisteredUserFixtureCleaner::forceDelete([$this->testUserId]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function registerAndLogin(): void
    {
        $ck = 'biz_' . uniqid();
        Cache::put('front_register_captcha_' . sha1($ck), 'XYZ99', 300);
        Cache::put('front_register_email_code_' . sha1(strtolower($this->testEmail)), [
            'email' => strtolower($this->testEmail), 'code' => '888888',
        ], 600);

        $s = mt_rand(100000, 999999);
        $reg = $this->postJson('/api/front/auth/register', [
            'email' => $this->testEmail, 'password' => $this->testPassword,
            'password_confirmation' => $this->testPassword, 'user_name' => '业务测试',
            'phone_code' => '+86', 'phone_number' => "13800{$s}", 'phone' => "13800{$s}",
            'id_card_no' => '440101' . date('Ymd') . sprintf('%04d', mt_rand(0, 9999)),
            'gender' => 1, 'account_type' => 2, 'inviter_id' => 10,
            'captcha_key' => $ck, 'captcha_code' => 'XYZ99', 'email_code' => '888888', 'agree_terms' => 1,
        ])->json();

        $this->testUserId = (int) ($reg['data']['user_id'] ?? 0);

        $this->token = $this->postJson('/api/front/auth/login', [
            'email' => $this->testEmail, 'password' => $this->testPassword,
        ])->json('data.access_token');

        $this->assertNotEmpty($this->token);
    }

    private function isSuccess(int $code): bool
    {
        return in_array($code, [0, 1000, 1002], true);
    }

    private function authGet(string $uri): array
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson($uri)->json();
    }

    private function authPost(string $uri, array $data = []): array
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson($uri, $data)->json();
    }

    /**
     * 1. 仪表盘数据（GET /api/front/dashboard）
     *
     * 执行链路：JWT → SSO → DashboardController@dashboardData
     * 返回：用户统计数据、今日摘要、最新新闻。
     */
    public function test_dashboard_data(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/dashboard');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '仪表盘应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 2. 导航菜单（GET /api/front/menus）
     *
     * 执行链路：JWT → SSO → MenuController@userMenus
     * 返回：按角色过滤的菜单树结构。
     */
    public function test_navigation_menus(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/menus');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '菜单应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 3. 入金选项（GET /api/front/deposits/form-options）
     *
     * 执行链路：JWT → SSO → DepositController@depositPage
     * 返回：支付通道列表、汇率、限额等。
     */
    public function test_deposit_form_options(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/deposits/form-options');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '入金选项应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 4. 入金历史（GET /api/front/deposits/history）
     */
    public function test_deposit_history(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/deposits/history');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '入金历史应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 5. 提现选项（GET /api/front/withdrawals/form-options）
     */
    public function test_withdraw_form_options(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/withdrawals/form-options');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '提现选项应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 6. 提现历史（GET /api/front/withdrawals/history）
     */
    public function test_withdraw_history(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/withdrawals/history');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '提现历史应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 7. 代理列表（GET /api/front/agents/direct）
     * 注意：普通客户无代理权限，code=4006 是预期行为（权限拒绝）。
     */
    public function test_agent_sub_list(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/agents/direct');
        $code = $json['code'] ?? 999;
        // 客户访问代理路由 → 4006 权限拒绝是正确行为；代理访问 → 0/1000 成功
        $this->assertTrue($this->isSuccess($code) || $code === 4006,
            '代理路由应返回成功或权限拒绝 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 8. 代理客户列表 */
    public function test_agent_customers(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/agents/direct-customers');
        $code = $json['code'] ?? 999;
        $this->assertTrue($this->isSuccess($code) || $code === 4006,
            '代理客户路由应返回成功或权限拒绝 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 9. 代理统计 */
    public function test_agent_statistics(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/agents/statistics');
        $code = $json['code'] ?? 999;
        $this->assertTrue($this->isSuccess($code) || $code === 4006,
            '代理统计路由应返回成功或权限拒绝 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 10. 资金流水（GET /api/front/flows/account）
     */
    public function test_fund_flow(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/flows/account');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '资金流水应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 11. 持仓汇总（代理专属路由） */
    public function test_position_summary(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/positions/summary');
        $code = $json['code'] ?? 999;
        $this->assertTrue($this->isSuccess($code) || $code === 4006,
            '持仓汇总路由应返回成功或权限拒绝 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 12. 未平仓订单（GET /api/front/orders/open）
     */
    public function test_open_orders(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/orders/open');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '未平仓订单应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 13. 已平仓订单（GET /api/front/orders/closed）
     */
    public function test_closed_orders(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/orders/closed');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '已平仓订单应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 14. 实时返佣（代理专属路由） */
    public function test_realtime_commission(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/commissions/realtime');
        $code = $json['code'] ?? 999;
        $this->assertTrue($this->isSuccess($code) || $code === 4006,
            '实时返佣路由应返回成功或权限拒绝 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 15. 返佣历史（代理专属路由） */
    public function test_commission_history(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/commissions/history');
        $code = $json['code'] ?? 999;
        $this->assertTrue($this->isSuccess($code) || $code === 4006,
            '返佣历史路由应返回成功或权限拒绝 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 16. 账户余额（GET /api/front/account/balance）
     */
    public function test_account_balance(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/account/balance');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '账户余额应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 17. 账户信息（GET /api/front/account/profile）
     */
    public function test_account_profile(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/account/profile');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '账户信息应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 18. 新闻列表（GET /api/front/news）
     */
    public function test_news_list(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/news');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '新闻列表应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 19. 赠品地址（GET /api/front/gift-addresses）
     */
    public function test_gift_addresses(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/gift-addresses');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '赠品地址应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 20. 赠品列表（GET /api/front/gifts）
     */
    public function test_gift_list(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/gifts');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '赠品列表应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 21. 交易品种（GET /api/front/trade-symbols）
     */
    public function test_trade_symbols(): void
    {
        $this->registerAndLogin();
        $json = $this->authGet('/api/front/trade-symbols');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '交易品种应返回成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }
}
