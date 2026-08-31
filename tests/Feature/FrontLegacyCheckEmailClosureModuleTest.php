<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 02:54
 */

/**
 * FrontLegacyCheckEmailClosureModuleTest
 *
 * 文件功能：
 * - 验证注册页邮箱预检接口闭环：旧 testemail 与新 /email/check 同契约返回 exists、空邮箱视为不存在、免登录可调、大小写不敏感排序规则。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Support\CreatesLegacyFrontUserFixture;

/**
 * 闭环测试：注册页"邮箱是否已存在"预检接口。
 *
 * 覆盖矩阵项：
 * - GET user/register/testemail（旧 URI，web.php:247，公开白名单）
 * - GET /email/check（新 API 前缀，routes/front.php:26）
 * 两者均映射到 Front\AuthController@checkEmail：
 * 查询 user_logins.email 是否存在，返回 {code, message, data: {exists: bool}}。
 *
 * 旧行为：直接 SELECT count(*) 返回数字，且可被未登录用户调用。
 * 新行为：改用 exists() 布尔语义，返回 JSON 信封；无缺口，本测试固化行为契约。
 */
class FrontLegacyCheckEmailClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacyFrontUserFixture;

    /**
     * 夹具用户 ID。验证旧版邮箱占用检查接口对已注册/未注册邮箱的响应。
     * @var int
     */
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = random_int(350000000, 359999999);
        $this->createLegacyFrontUserFixture($this->userId, 2, 'Check Email Fixture');
    }

    private function registeredEmail(): string
    {
        $email = DB::table('user_logins')
            ->where('user_id', $this->userId)
            ->value('email');

        return (string) $email;
    }

    public function test_legacy_testemail_reports_registered_email_exists(): void
    {
        $this->get('/user/register/testemail?email=' . rawurlencode($this->registeredEmail()))
            ->assertOk()
            ->assertJsonPath('data.exists', true);
    }

    public function test_legacy_testemail_reports_unregistered_email_missing(): void
    {
        $this->get('/user/register/testemail?email=never-registered-' . $this->userId . '@example.test')
            ->assertOk()
            ->assertJsonPath('data.exists', false);
    }

    public function test_legacy_testemail_empty_email_treated_as_missing(): void
    {
        $this->get('/user/register/testemail?email=')
            ->assertOk()
            ->assertJsonPath('data.exists', false);
    }

    public function test_legacy_testemail_is_callable_without_login(): void
    {
        $this->get('/user/register/testemail?email=' . rawurlencode($this->registeredEmail()))
            ->assertOk()
            ->assertJsonPath('data.exists', true);
    }

    public function test_shared_api_email_check_serves_same_contract(): void
    {
        $this->get('/api/front/auth/email/check?email=' . rawurlencode($this->registeredEmail()))
            ->assertOk()
            ->assertJsonPath('data.exists', true);

        $this->get('/api/front/auth/email/check?email=never-registered-' . $this->userId . '@example.test')
            ->assertOk()
            ->assertJsonPath('data.exists', false);
    }

    public function test_email_search_respects_case_insensitive_database_collation(): void
    {
        $email = $this->registeredEmail();
        $upperCase = preg_replace('/@/', '@', strtoupper($email));

        if (strtoupper($email) === $email) {
            $this->assertTrue(true);
            return;
        }

        $this->get('/user/register/testemail?email=' . rawurlencode($upperCase))
            ->assertOk()
            ->assertJsonPath('data.exists', true);
    }
}
