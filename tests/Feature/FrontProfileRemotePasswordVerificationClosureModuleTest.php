<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:20
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\TradePasswordGateway;
use App\Facades\Mt4ManagerApi;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 前台资料敏感操作远端密码校验闭环测试。
 *
 * 文件功能：
 * - 验证 MT4 密码网关无法给出确定结果时，联系方式、银行卡换绑和密码修改都必须失败关闭。
 * - 验证网络故障不会被误报为密码错误，也不会继续写库、消费验证码或调用密码变更接口。
 *
 * 返回结果：
 * - NETWORKFAIL/FATALCANOTCONNECT 表示远端密码校验结果未知，用户可稍后重试。
 * - THIRD_PARTY_ERROR 表示现代 API 已明确停止敏感操作，数据库保持原值。
 */
class FrontProfileRemotePasswordVerificationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, int> 当前测试创建的业务用户 ID。 */
    private array $fixtureUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtureUserIds as $userId) {
            Cache::forget('front_profile_updverify_code:' . $userId);
            Cache::forget('front_profile_change_code:' . $userId);
        }

        parent::tearDown();
    }

    /**
     * 验证旧联系方式更新在密码网关结果未知时返回旧网络错误码，并保留邮箱与验证码。
     */
    public function test_legacy_contact_update_stops_on_remote_password_network_failure(): void
    {
        $userId = 419020100;
        $oldEmail = 'profile-remote-contact-old-419020100@example.test';
        $newEmail = 'profile-remote-contact-new-419020100@example.test';
        $login = $this->insertUser($userId, $oldEmail, '86-13920100100');
        $this->bindPasswordResult(TradePasswordVerificationResult::unknown('read_timeout'));
        $this->putCode('updverify', $userId, '201100', $newEmail);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updatePhoneEmailInfo', [
                'type' => 'email',
                'oldemail' => $oldEmail,
                'useremail' => $newEmail,
                'updVerifyCode' => '201100',
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'NETWORKFAIL')
            ->assertJsonPath('col', 'FATALCANOTCONNECT');
        $this->assertDatabaseHas('user_logins', ['user_id' => $userId, 'email' => $oldEmail]);
        $this->assertNotNull(Cache::get('front_profile_updverify_code:' . $userId));
    }

    /**
     * 验证现代银行卡换绑在密码网关结果未知时返回第三方错误，且不上传待审核银行卡。
     */
    public function test_modern_bank_change_stops_on_remote_password_network_failure(): void
    {
        $userId = 419020200;
        $email = 'profile-remote-bank-419020200@example.test';
        $login = $this->insertUser($userId, $email, '86-13920200200');
        $this->insertApprovedBank($userId);
        $this->bindPasswordResult(TradePasswordVerificationResult::unknown('connect_timeout'));
        $this->putCode('change', $userId, '202200', $email);
        Storage::fake('public');

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/api/front/profile/bank-card-change', [
                'verify_phone' => '13920200200',
                'verify_email' => $email,
                'verification_code' => '202200',
                'password' => 'password',
                'bank_name' => 'Remote Guard Bank',
                'bank_no' => '6222000000020200',
                'bank_addr' => 'Remote Guard Branch',
                'bank_card_img' => UploadedFile::fake()->image('front.jpg', 32, 32),
                'bank_card_back_img' => UploadedFile::fake()->image('back.jpg', 32, 32),
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR)
            ->assertJsonPath('data.error', 'NETWORKFAIL')
            ->assertJsonPath('data.field', 'FATALCANOTCONNECT');
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $userId,
            'bank_no_tmp' => '6222000000020200',
        ]);
        $this->assertNotNull(Cache::get('front_profile_change_code:' . $userId));
    }

    /**
     * 验证现代密码修改在旧密码远端校验未知时停止，原本地哈希保持不变。
     */
    public function test_modern_password_change_stops_before_remote_reset_when_verification_is_unknown(): void
    {
        $userId = 419020300;
        $login = $this->insertUser(
            $userId,
            'profile-remote-password-modern-419020300@example.test',
            '86-13920300300'
        );
        $this->bindPasswordResult(TradePasswordVerificationResult::unknown('read_timeout'));
        Mt4ManagerApi::shouldReceive('changePassword')->never();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/password', [
                'old_password' => 'password',
                'password' => 'new-password-419020300',
                'password_confirmation' => 'new-password-419020300',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR)
            ->assertJsonPath('data.error', 'NETWORKFAIL')
            ->assertJsonPath('data.field', 'FATALCANOTCONNECT');
        $this->assertTrue(Hash::check('password', $this->passwordHashFor($userId)));
    }

    /**
     * 验证旧密码修改在远端明确拒绝时返回 API 密码错误，在网络未知时返回连接错误。
     */
    public function test_legacy_password_change_preserves_rejected_and_network_failure_meanings(): void
    {
        $userId = 419020400;
        $login = $this->insertUser(
            $userId,
            'profile-remote-password-legacy-419020400@example.test',
            '86-13920400400'
        );

        $this->bindPasswordResults([
            TradePasswordVerificationResult::rejected('bad_password'),
            TradePasswordVerificationResult::unknown('connect_timeout'),
        ]);
        Mt4ManagerApi::shouldReceive('changePassword')->never();

        $payload = [
            'olduserpsw' => 'password',
            'newuserpsw' => 'new-password-419020400',
            'confirmuserpsw' => 'new-password-419020400',
        ];
        $rejected = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/editpsw_save', $payload);
        $networkFailure = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login->fresh(), 'user')
            ->postJson('/user/editpsw_save', $payload);

        $rejected->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'apipswerr')
            ->assertJsonPath('col', 'olduserpsw');
        $networkFailure->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'FATALCANOTCONNECT')
            ->assertJsonPath('col', 'nocol');
        $this->assertTrue(Hash::check('password', $this->passwordHashFor($userId)));
    }

    /**
     * 验证资料页把远端网络未知和手机号校验失败翻译为可操作提示。
     *
     * 返回结果：
     * - NETWORKFAIL 必须在银行卡换绑和联系方式修改中统一提示稍后重试。
     * - phoneErr 必须使用手机号专用词条，不能引用不存在或语义错误的邮箱词条。
     */
    public function test_profile_assets_translate_remote_and_phone_errors_explicitly(): void
    {
        $pages = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $translations = file_get_contents(public_path('js/shared/i18n.js')) ?: '';

        $this->assertSame(2, substr_count(
            $pages,
            "'NETWORKFAIL': 'profile.networkUnavailable'"
        ));
        $this->assertStringContainsString(
            "phoneErr: 'profile.phoneVerifyFailed'",
            $pages
        );
        $this->assertStringNotContainsString('profile.emailVerifyFailed', $pages);
        $this->assertStringContainsString(
            "submitJson('/api/front/profile/phone', data.field, function()",
            $pages
        );
        $this->assertStringContainsString(
            '}, contactChangeErrorMessage);',
            $pages
        );

        $this->assertStringContainsString(
            "networkUnavailable: 'MT4 服务暂时无法确认当前密码，请稍后重试'",
            $translations
        );
        $this->assertStringContainsString(
            "phoneVerifyFailed: '当前手机号与当前账号不匹配'",
            $translations
        );
        $this->assertStringContainsString(
            "networkUnavailable: 'MT4 could not verify the current password. Please try again later.'",
            $translations
        );
        $this->assertStringContainsString(
            "phoneVerifyFailed: 'The current phone number does not match this account.'",
            $translations
        );
    }

    /**
     * 绑定单个密码网关结果，并启用 MT4 模式。
     */
    private function bindPasswordResult(TradePasswordVerificationResult $result): void
    {
        $this->bindPasswordResults([$result]);
    }

    /**
     * 按请求顺序绑定多个密码网关结果。
     *
     * @param array<int, TradePasswordVerificationResult> $results 预设的远端校验结果。
     */
    private function bindPasswordResults(array $results): void
    {
        config(['mt4.enabled' => true]);
        $this->app->instance(TradePasswordGateway::class, new class($results) implements TradePasswordGateway {
            /** @var array<int, TradePasswordVerificationResult> */
            private array $results;

            /** @param array<int, TradePasswordVerificationResult> $results 预设的远端校验结果。 */
            public function __construct(array $results)
            {
                $this->results = $results;
            }

            /**
             * 返回下一项预设结果，测试可据此验证每次敏感操作都重新校验密码。
             */
            public function verify(int $userId, string $password): TradePasswordVerificationResult
            {
                return array_shift($this->results);
            }
        });
    }

    /**
     * 创建登录账号与业务资料，密码固定为 password，便于隔离远端结果语义。
     */
    private function insertUser(int $userId, string $email, string $phone): UserLogin
    {
        $this->fixtureUserIds[] = $userId;
        $now = time();
        DB::table('withdraw_records')->where('user_id', $userId)->delete();
        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
            'account_type' => 2,
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
            'user_name' => 'profile-remote-password-' . $userId,
            'phone' => $phone,
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return UserLogin::findOrFail($loginId);
    }

    /**
     * 创建已审核通过的原银行卡，只有该状态才允许进入换绑密码校验。
     */
    private function insertApprovedBank(int $userId): void
    {
        $now = time();
        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_name' => 'Current Bank',
            'bank_no' => '622299999999' . substr((string) $userId, -4),
            'bank_addr' => 'Current Branch',
            'bank_status' => 2,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入真实发码接口使用的验证码缓存结构。
     */
    private function putCode(string $purpose, int $userId, string $code, string $email): void
    {
        Cache::put('front_profile_' . $purpose . '_code:' . $userId, [
            'code' => $code,
            'email' => $email,
            'phone' => '',
            'type' => $purpose === 'change' ? 'bank-change' : 'email',
        ], now()->addMinutes(10));
    }

    /**
     * 读取指定用户当前密码哈希，用于确认失败分支没有写库。
     */
    private function passwordHashFor(int $userId): string
    {
        return (string) DB::table('user_logins')
            ->where('user_id', $userId)
            ->value('password');
    }
}
