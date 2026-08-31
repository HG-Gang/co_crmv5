<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 02:04
 */

/**
 * FrontLegacyLoginCaptchaAndParityClosureModuleTest
 *
 * 文件功能：
 * - 验证旧前台登录验证码与等价闭环：自定义验证码图片与包状态、缺错验证码先拒、有效验证码不可复用、MT4 开启时远程密码验证、未知结果失败关闭不发 token。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Contracts\TradePasswordGateway;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use App\Services\Mt4SyncDisabledException;
use App\Services\Mt4SyncGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesLegacyFrontUserFixture;
use Tests\TestCase;

/**
 * 旧前台登录验证码闭环测试。
 *
 * `/user/captcha` 与 `/user/signIn` 必须复用旧项目 custom_captcha 的
 * Session/Cache 契约；现代 `/api/front/auth/login` 不受该兼容协议影响。
 */
class FrontLegacyLoginCaptchaAndParityClosureModuleTest extends TestCase
{
    use CreatesLegacyFrontUserFixture;
    use DatabaseTransactions;

    /**
     * 登录用例的固定业务用户 ID（9869701）。CreatesLegacyFrontUserFixture 据此建号，验证码与登录链路共用该账号。
     * @var int
     */
    private const USER_ID = 9869701;

    /**
     * 夹具用户的登录账号（即其 email）。旧版登录接口以 loginUid 传参，多个用例复用同一账号。
     * @var string
     */
    private $account;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mt4.enabled' => false,
            'mt4.user_sync_enabled' => false,
            // 固定字符只用于从真实 Mews Captcha 链路取得可提交的验证码。
            'captcha.characters' => ['A'],
            'captcha.custom_captcha' => [
                'length' => 4,
                'width' => 150,
                'height' => 35,
                'quality' => 90,
                'expire' => 60,
                'sensitive' => false,
            ],
        ]);

        $this->account = $this->createLegacyFrontUserFixture(self::USER_ID)->email;
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupLegacyFrontUserFixtures();
        } finally {
            parent::tearDown();
        }
    }

    public function test_legacy_captcha_endpoint_creates_custom_captcha_image_and_package_state(): void
    {
        $response = $this->get('/user/captcha');

        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $session = $this->app['session.store'];
        $captchaKey = (string) $session->get('captcha.key');

        $this->assertNotSame('', $captchaKey);
        $this->assertTrue($session->has('captcha'));
        $this->assertTrue(Cache::has('captcha_' . md5($captchaKey)));
        $this->assertStringNotContainsString('AAAA', $response->getContent());
    }

    public function test_legacy_sign_in_rejects_missing_or_wrong_captcha_before_valid_credentials(): void
    {
        $this->postJson('/user/signIn', [
            'loginUid' => $this->account,
            'loginPassword' => 'abc123',
        ])->assertOk()
            ->assertJsonPath('errcptcode', '验证码错误!')
            ->assertJsonPath('loginStatus', 400);

        $this->get('/user/captcha')->assertOk();
        $session = $this->app['session.store'];

        $this->withSession($session->all())->postJson('/user/signIn', [
            'loginUid' => $this->account,
            'loginPassword' => 'abc123',
            'cptcode' => 'WRONG',
        ])->assertOk()
            ->assertJsonPath('errcptcode', '验证码错误!')
            ->assertJsonPath('loginStatus', 400);
    }

    public function test_valid_captcha_allows_local_login_and_cannot_be_reused(): void
    {
        $this->get('/user/captcha')->assertOk();
        $session = $this->app['session.store'];
        $captchaKey = (string) $session->get('captcha.key');

        $login = $this->withSession($session->all())->postJson('/user/signIn', [
            'loginUid' => $this->account,
            'loginPassword' => 'abc123',
            'cptcode' => 'aaaa',
        ]);

        $login->assertOk()
            ->assertJsonPath('msg', 'OK')
            ->assertJsonPath('loginStatus', 200)
            ->assertSessionHas('suser.user_id', self::USER_ID)
            ->assertSessionMissing('captcha');
        $this->assertFalse(Cache::has('captcha_' . md5($captchaKey)));

        $this->postJson('/user/signIn', [
            'loginUid' => $this->account,
            'loginPassword' => 'abc123',
            'cptcode' => 'aaaa',
        ])->assertOk()
            ->assertJsonPath('errcptcode', '验证码错误!')
            ->assertJsonPath('loginStatus', 400);
    }

    public function test_enabled_mt4_mode_uses_remote_password_verification_before_login_success(): void
    {
        config(['mt4.enabled' => true, 'mt4.user_sync_enabled' => true]);
        $gateway = new RecordingLegacyLoginPasswordGateway(TradePasswordVerificationResult::verified());
        $this->app->instance(TradePasswordGateway::class, $gateway);
        $session = $this->captchaSession();

        $response = $this->withSession($session)->postJson('/user/signIn', [
            'loginUid' => $this->account,
            // 本地哈希不是该值，verified 只能来自测试网关，证明控制器确实走远端服务。
            'loginPassword' => 'remote-password',
            'cptcode' => 'aaaa',
        ]);
        $response->assertOk()
            ->assertJsonPath('msg', 'OK')
            ->assertJsonPath('loginStatus', 200);

        $this->assertSame([self::USER_ID, 'remote-password'], $gateway->calls[0] ?? null);
    }

    public function test_remote_password_rejection_keeps_legacy_error_contract(): void
    {
        config(['mt4.enabled' => true, 'mt4.user_sync_enabled' => true]);
        $gateway = new RecordingLegacyLoginPasswordGateway(TradePasswordVerificationResult::rejected('bad_password'));
        $this->app->instance(TradePasswordGateway::class, $gateway);

        $this->withSession($this->captchaSession())->postJson('/user/signIn', [
            'loginUid' => $this->account,
            'loginPassword' => 'remote-password',
            'cptcode' => 'aaaa',
        ])->assertOk()
            ->assertJsonPath('errpsw', '密码错误!')
            ->assertJsonPath('loginStatus', 404);
    }

    public function test_remote_password_unknown_result_fails_closed_without_issuing_token(): void
    {
        config(['mt4.enabled' => true, 'mt4.user_sync_enabled' => true]);
        $gateway = new RecordingLegacyLoginPasswordGateway(TradePasswordVerificationResult::unknown('read_timeout'));
        $this->app->instance(TradePasswordGateway::class, $gateway);

        $response = $this->withSession($this->captchaSession())->postJson('/user/signIn', [
            'loginUid' => $this->account,
            'loginPassword' => 'remote-password',
            'cptcode' => 'aaaa',
        ]);

        $response->assertOk()
            ->assertJsonPath('mt4msg', '网络故障,暂时无法登陆')
            ->assertJsonPath('loginStatus', 500);
        $this->assertArrayNotHasKey('access_token', $response->json());
    }

    public function test_mt4_gate_exception_fails_closed_without_issuing_token(): void
    {
        config(['mt4.enabled' => true, 'mt4.user_sync_enabled' => true]);
        // Mt4SyncDisabledException 与门控类共文件，先加载门控类再构造测试异常。
        Mt4SyncGate::userSyncEnabled();
        $gateway = new RecordingLegacyLoginPasswordGateway(null, new Mt4SyncDisabledException('disabled'));
        $this->app->instance(TradePasswordGateway::class, $gateway);

        $response = $this->withSession($this->captchaSession())->postJson('/user/signIn', [
            'loginUid' => $this->account,
            'loginPassword' => 'remote-password',
            'cptcode' => 'aaaa',
        ]);

        $response->assertOk()
            ->assertJsonPath('mt4msg', '网络故障,暂时无法登陆')
            ->assertJsonPath('loginStatus', 500);
        $this->assertArrayNotHasKey('access_token', $response->json());
    }

    public function test_legacy_login_page_displays_refreshable_captcha_and_script_submits_it(): void
    {
        $this->get('/user/login')
            ->assertOk()
            ->assertSee('name="cptcode"', false)
            ->assertSee('id="legacyLoginCaptchaImg"', false)
            ->assertSee(route('legacy_user_captcha'), false);

        $script = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';

        $this->assertStringContainsString('fields.cptcode', $script);
        $this->assertStringContainsString('cptcode: captcha', $script);
        $this->assertStringContainsString('#legacyLoginCaptchaImg', $script);
    }

    /** @return array<string, mixed> */
    private function captchaSession(): array
    {
        $this->get('/user/captcha')->assertOk();

        return $this->app['session.store']->all();
    }
}

final class RecordingLegacyLoginPasswordGateway implements TradePasswordGateway
{
    /**
     * 预设的资金密码校验结果（null 表示返回验证成功）。驱动登录后资金密码校验的成功/失败分支。
     * @var TradePasswordVerificationResult|null
     */
    private $result;

    /**
     * 预设的替身抛出异常（null 表示不抛）。验证校验过程异常时登录链路的失败关闭行为。
     * @var \Throwable|null
     */
    private $exception;

    /**
     * verify() 收到的 [userId, password] 调用记录。断言资金密码校验的目标与次数。
     * @var array<int, array{0:int,1:string}>
     */
    public $calls = [];

    public function __construct(?TradePasswordVerificationResult $result, \Throwable $exception = null)
    {
        $this->result = $result;
        $this->exception = $exception;
    }

    public function verify(int $userId, string $password): TradePasswordVerificationResult
    {
        $this->calls[] = [$userId, $password];
        if ($this->exception) {
            throw $this->exception;
        }

        return $this->result;
    }
}
