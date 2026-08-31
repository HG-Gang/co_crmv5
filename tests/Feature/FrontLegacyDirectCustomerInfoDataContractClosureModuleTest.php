<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 11:25
 */

/**
 * FrontLegacyDirectCustomerInfoDataContractClosureModuleTest
 *
 * 文件功能：
 * - 验证旧前台直属客户详情数据契约：管理员角色渲染旧资料字段与登录历史入口、非管理员角色脱敏联系方式、角色值大小写敏感、认证文案要求身份证与银行卡均审核通过。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧前台直属客户详情页数据契约闭环测试。
 *
 * 适用场景：
 * - 代理从旧路由 user/cust/show_direct_cust_info/{role}/{uid} 查看代理树内客户。
 * - 页面必须沿用旧 Blade 的资料字段、敏感资料脱敏和管理员登录历史入口。
 *
 * 验证结果：
 * - role=admin 返回完整的旧字段语义和登录历史表格入口。
 * - 非管理员展示仍返回脱敏资料，但不返回登录历史表格。
 */
class FrontLegacyDirectCustomerInfoDataContractClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证管理员角色详情页完整返回旧 Blade 所需资料字段及登录历史入口。
     *
     * @return void 断言字段映射、状态文案、敏感信息脱敏和表格请求地址均符合旧协议。
     */
    public function test_admin_role_renders_legacy_customer_profile_fields_and_login_history_entry(): void
    {
        $viewerAgentId = 412530100;
        $customerId = 412530101;
        $createdAt = 1785000000;

        $this->insertUser($viewerAgentId, 'legacy-detail-viewer', 1, 0, '', 'viewer-' . $viewerAgentId . '@example.test');
        $this->insertUser(
            $customerId,
            'legacy-detail-customer',
            2,
            $viewerAgentId,
            (string) $viewerAgentId . ',' . $customerId,
            'abcdef@example.test',
            [
                'phone' => '86-13912345678',
                'gender' => 1,
                'total_funds' => '1234.50',
                'avail_margin' => '234.50',
                'effective_credit' => '345.60',
                'trading_mode' => 1,
                'auth_status' => 1,
                'is_withdrawal_allowed' => 1,
                'mt4_group' => 'legacy-vip',
                'remark' => '旧客户详情备注',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]
        );
        $this->insertUserAuth($customerId, 2, 2);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/user/cust/show_direct_cust_info/admin/' . $customerId);

        $response->assertOk();
        $response->assertSee('账户ID')
            ->assertSee('账户名称')
            ->assertSee('上级ID')
            ->assertSee('手机号')
            ->assertSee('E-mail')
            ->assertSee('性别')
            ->assertSee('账户余额')
            ->assertSee('可用保证金')
            ->assertSee('有效信用额')
            ->assertSee('账户模式')
            ->assertSee('账户状态')
            ->assertSee('出金状态')
            ->assertSee('客户组别')
            ->assertSee('开户时间')
            ->assertSee('备注')
            ->assertSee('legacy-detail-customer')
            ->assertSee((string) $customerId)
            ->assertSee((string) $viewerAgentId)
            ->assertSee('139*****678')
            ->assertSee('abc*****@example.test')
            ->assertSee('男')
            ->assertSee('1234.50')
            ->assertSee('234.50')
            ->assertSee('345.60')
            ->assertSee('权益模式')
            ->assertSee('已认证')
            ->assertSee('不允许出金')
            ->assertSee('legacy-vip')
            ->assertSee(date('Y-m-d H:i:s', $createdAt))
            ->assertSee('旧客户详情备注')
            ->assertSee('id="login_history"', false)
            ->assertSee('data-login-history-url="/user/cust/loginHistorySearch/' . $customerId . '"', false)
            ->assertSee('/js/apps/front/legacy/direct-customer-detail.js', false)
            ->assertDontSee('<script>', false);

        $response->assertDontSee('13912345678')
            ->assertDontSee('abcdef@example.test');
    }

    /**
     * 验证非管理员角色保留资料脱敏，但不可得到管理员专属登录历史入口。
     *
     * @return void 断言角色分支不会意外扩大登录历史的展示范围。
     */
    public function test_non_admin_role_masks_contact_data_and_hides_login_history_entry(): void
    {
        $viewerAgentId = 412530200;
        $customerId = 412530201;

        $this->insertUser($viewerAgentId, 'legacy-detail-agent-viewer', 1, 0, '', 'viewer-' . $viewerAgentId . '@example.test');
        $this->insertUser(
            $customerId,
            'legacy-detail-agent-customer',
            2,
            $viewerAgentId,
            (string) $viewerAgentId . ',' . $customerId,
            'ghijkl@example.test',
            ['phone' => '86-13912345678']
        );

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/user/cust/show_direct_cust_info/agent/' . $customerId);

        $response->assertOk()
            ->assertSee('139*****678')
            ->assertSee('ghi*****@example.test')
            ->assertDontSee('id="login_history"', false)
            ->assertDontSee('/user/cust/loginHistorySearch/' . $customerId, false)
            ->assertDontSee('13912345678')
            ->assertDontSee('ghijkl@example.test');
    }

    /**
     * 验证旧角色参数只接受精确 admin 值。
     *
     * @return void 防止大小写归一化把非旧协议角色误判为管理员，从而展示登录历史入口。
     */
    public function test_role_value_is_case_sensitive_for_legacy_login_history_entry(): void
    {
        $viewerAgentId = 412530300;
        $customerId = 412530301;

        $this->insertUser($viewerAgentId, 'legacy-detail-case-viewer', 1, 0, '', 'viewer-' . $viewerAgentId . '@example.test');
        $this->insertUser(
            $customerId,
            'legacy-detail-case-customer',
            2,
            $viewerAgentId,
            (string) $viewerAgentId . ',' . $customerId,
            'mnopqr@example.test'
        );

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/user/cust/show_direct_cust_info/Admin/' . $customerId);

        $response->assertOk()
            ->assertDontSee('id="login_history"', false)
            ->assertDontSee('/user/cust/loginHistorySearch/' . $customerId, false);
    }

    /**
     * 验证认证文案必须同时满足旧页面的账户、身份证和银行卡审核条件。
     *
     * @return void 即使 user_infos.auth_status 为已认证，缺少旧页面要求的双审核通过记录时仍显示“未认证”。
     */
    public function test_profile_authentication_text_requires_id_card_and_bank_approval(): void
    {
        $viewerAgentId = 412530400;
        $customerId = 412530401;

        $this->insertUser($viewerAgentId, 'legacy-detail-auth-viewer', 1, 0, '', 'viewer-' . $viewerAgentId . '@example.test');
        $this->insertUser(
            $customerId,
            'legacy-detail-auth-customer',
            2,
            $viewerAgentId,
            (string) $viewerAgentId . ',' . $customerId,
            'stuvwx@example.test',
            ['auth_status' => 1]
        );
        $this->insertUserAuth($customerId, 2, 1);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/user/cust/show_direct_cust_info/agent/' . $customerId);

        $response->assertOk()
            ->assertSee('未认证')
            ->assertDontSee('value="已认证"', false);
    }

    /**
     * 写入可登录的代理或普通客户测试夹具。
     *
     * @param int $userId 业务用户 ID。
     * @param string $userName 资料页展示名称。
     * @param int $accountType 账号类型，1=代理，2=普通客户。
     * @param int $parentId 上级代理业务用户 ID，根代理传 0。
     * @param string $familyTree 代理树链路，直接客户示例为“代理ID,客户ID”。
     * @param string $email 登录邮箱，用于验证旧页面的邮箱脱敏规则。
     * @param array<string, mixed> $overrides 要覆盖的业务资料字段。
     * @return void 夹具写入当前事务，测试结束后自动回滚。
     */
    private function insertUser(
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        string $familyTree,
        string $email,
        array $overrides = []
    ): void {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
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

        DB::table('user_infos')->insert(array_merge([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '',
            'gender' => 0,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $familyTree,
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 0,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'trading_mode' => 0,
            'mt4_group' => '',
            'remark' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $overrides));
    }

    /**
     * 写入旧详情页认证状态所依赖的身份证和银行卡审核夹具。
     *
     * @param int $userId 业务用户 ID，对应 user_infos.user_id。
     * @param int $idCardStatus 身份证审核状态，2 表示旧页面判定为通过。
     * @param int $bankStatus 银行卡审核状态，2 表示旧页面判定为通过。
     * @return void 每个用户只保留一条认证记录，避免历史夹具影响认证文案断言。
     */
    private function insertUserAuth(int $userId, int $idCardStatus, int $bankStatus): void
    {
        $now = time();

        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'id_card_status' => $idCardStatus,
            'bank_status' => $bankStatus,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
