<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 22:59
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * 前台资料验证码生命周期与银行卡换绑闭环测试。
 *
 * 文件功能：
 * - 验证邮箱修改验证码必须绑定目标邮箱、只能使用一次，并兼容旧 Session 键。
 * - 验证手机号修改不能绕过当前密码。
 * - 验证银行卡换绑必须满足原银行卡已审核、无处理中出金、密码和验证码均正确。
 * - 验证现代接口不能绕过旧项目已有的敏感操作安全边界。
 *
 * 返回结果：
 * - SUC 或 UPDATED 表示全部前置条件通过并写入成功。
 * - codeErr/emailErr/pswErr 表示联系方式验证失败。
 * - errbankpendingauth/errisapplying/errpassword/erruserverfcode 表示银行卡换绑被对应边界拒绝。
 */
class FrontLegacyProfileVerificationLifecycleClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, int> 需要清理公开镜像和验证码缓存的测试用户 ID。 */
    private array $fixtureUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtureUserIds as $userId) {
            Cache::forget('front_profile_updverify_code:' . $userId);
            Cache::forget('front_profile_change_code:' . $userId);
            File::deleteDirectory(public_path('storage/auth/' . $userId));
        }

        parent::tearDown();
    }

    /**
     * 验证“发送到新邮箱 -> 提交同一验证码 -> 成功后不可重放”的完整生命周期。
     */
    public function test_email_change_completes_one_time_code_lifecycle_for_new_email(): void
    {
        $userId = 419010100;
        $oldEmail = 'profile-lifecycle-old-419010100@example.test';
        $newEmail = 'profile-lifecycle-new-419010100@example.test';
        $login = $this->insertUser($userId, $oldEmail, '86-13910100100');
        Mail::fake();

        $send = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updVerifyPassSendCode', [
                'type' => 'email',
                'useremail' => $newEmail,
            ]);

        $send->assertOk()->assertJsonPath('status', true);
        $cached = Cache::get('front_profile_updverify_code:' . $userId);
        $this->assertIsArray($cached);
        $this->assertSame($newEmail, $cached['email'] ?? null);
        $this->assertNotSame('', (string) ($cached['code'] ?? ''));

        $payload = [
            'type' => 'email',
            'oldemail' => $oldEmail,
            'useremail' => $newEmail,
            'updVerifyCode' => (string) $cached['code'],
            'password' => 'password',
        ];
        $updated = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updatePhoneEmailInfo', $payload);

        $updated->assertOk()->assertJsonPath('msg', 'SUC');
        $this->assertDatabaseHas('user_logins', ['user_id' => $userId, 'email' => $newEmail]);
        $this->assertNull(Cache::get('front_profile_updverify_code:' . $userId));

        $replayed = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login->fresh(), 'user')
            ->postJson('/user/center/updatePhoneEmailInfo', $payload);
        $replayed->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'codeErr');
    }

    /**
     * 验证邮件发送异常时返回失败，且不会留下可被提交接口使用的验证码。
     */
    public function test_email_change_code_send_failure_does_not_leave_valid_cache(): void
    {
        $userId = 419010150;
        $oldEmail = 'profile-mail-failure-old-419010150@example.test';
        $newEmail = 'profile-mail-failure-new-419010150@example.test';
        $login = $this->insertUser($userId, $oldEmail, '86-13910100150');
        $this->putCode('updverify', $userId, '101150', $newEmail);
        Mail::shouldReceive('raw')
            ->once()
            ->andThrow(new RuntimeException('mail transport unavailable'));

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updVerifyPassSendCode', [
                'type' => 'email',
                'useremail' => $newEmail,
            ]);

        $response->assertOk()->assertJsonPath('status', false);
        $this->assertNull(Cache::get('front_profile_updverify_code:' . $userId));
    }

    /**
     * 验证验证码即使数值正确，也不能用于未绑定的另一个目标邮箱。
     */
    public function test_email_change_rejects_code_bound_to_another_target_email(): void
    {
        $userId = 419010200;
        $oldEmail = 'profile-target-old-419010200@example.test';
        $boundEmail = 'profile-target-bound-419010200@example.test';
        $submittedEmail = 'profile-target-other-419010200@example.test';
        $login = $this->insertUser($userId, $oldEmail, '86-13910200200');
        $this->putCode('updverify', $userId, '102200', $boundEmail);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updatePhoneEmailInfo', [
                'type' => 'email',
                'oldemail' => $oldEmail,
                'useremail' => $submittedEmail,
                'updVerifyCode' => '102200',
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'emailErr');
        $this->assertDatabaseHas('user_logins', ['user_id' => $userId, 'email' => $oldEmail]);
    }

    /**
     * 验证旧 Session 中的 updverifyCode/updverifyEmail 仍可完成邮箱修改。
     */
    public function test_email_change_accepts_legacy_session_verification_keys(): void
    {
        $userId = 419010300;
        $oldEmail = 'profile-session-old-419010300@example.test';
        $newEmail = 'profile-session-new-419010300@example.test';
        $this->insertUser($userId, $oldEmail, '86-13910300300');

        $response = $this->withSession([
            'suser' => ['user_id' => $userId],
            'updverifyCode' => '103300',
            'updverifyEmail' => $newEmail,
            'updverifyType' => 'email',
        ])->postJson('/user/center/updatePhoneEmailInfo', [
            'type' => 'email',
            'oldemail' => $oldEmail,
            'useremail' => $newEmail,
            'updVerifyCode' => '103300',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('msg', 'SUC');
        $this->assertDatabaseHas('user_logins', ['user_id' => $userId, 'email' => $newEmail]);
        $response->assertSessionMissing('updverifyCode');
        $response->assertSessionMissing('updverifyEmail');
    }

    /**
     * 验证手机号修改未提交密码时返回 pswErr，且数据库保持原手机号。
     */
    public function test_phone_change_rejects_missing_password(): void
    {
        $userId = 419010400;
        $email = 'profile-phone-password-419010400@example.test';
        $login = $this->insertUser($userId, $email, '86-13910400400');

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updatePhoneEmailInfo', [
                'type' => 'phone',
                'oldphonefill' => '13910400400',
                'userphoneNo' => '13910400999',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'pswErr');
        $this->assertDatabaseHas('user_infos', ['user_id' => $userId, 'phone' => '86-13910400400']);
    }

    /**
     * 验证原银行卡未审核通过时优先返回 errbankpendingauth，不写入换绑资料。
     */
    public function test_legacy_bank_change_rejects_unapproved_current_bank(): void
    {
        $userId = 419010500;
        $email = 'profile-bank-unapproved-419010500@example.test';
        $login = $this->insertUser($userId, $email, '86-13910500500');
        $this->insertBankAuth($userId, 1);
        $this->putCode('change', $userId, '105500', $email);
        Storage::fake('public');

        $response = $this->postLegacyBankChange($login, $email, '105500');

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'errbankpendingauth');
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $userId,
            'bank_no_tmp' => '6222000000010500',
        ]);
    }

    /**
     * 验证 status=0 的待处理出金会阻止银行卡换绑，避免收款账户在资金处理中变化。
     */
    public function test_legacy_bank_change_rejects_pending_withdrawal(): void
    {
        $userId = 419010600;
        $email = 'profile-bank-withdraw-419010600@example.test';
        $login = $this->insertUser($userId, $email, '86-13910600600');
        $this->insertBankAuth($userId, 2);
        $this->insertWithdraw($userId, 0);
        $this->putCode('change', $userId, '106600', $email);
        Storage::fake('public');

        $response = $this->postLegacyBankChange($login, $email, '106600');

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'errisapplying');
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $userId,
            'bank_no_tmp' => '6222000000010600',
        ]);
    }

    /**
     * 验证现代换绑接口缺少一次性验证码时不能成为旧安全流程的绕过入口。
     */
    public function test_modern_bank_change_rejects_missing_verification_code(): void
    {
        $userId = 419010700;
        $email = 'profile-bank-modern-code-419010700@example.test';
        $login = $this->insertUser($userId, $email, '86-13910700700');
        $this->insertBankAuth($userId, 2);
        Storage::fake('public');

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/api/front/profile/bank-card-change', [
                'verify_phone' => '13910700700',
                'verify_email' => $email,
                'password' => 'password',
                'bank_name' => 'Modern Secure Bank',
                'bank_no' => '6222000000010700',
                'bank_addr' => 'Modern Secure Branch',
                'bank_card_img' => UploadedFile::fake()->image('modern-front.jpg', 32, 32),
                'bank_card_back_img' => UploadedFile::fake()->image('modern-back.jpg', 32, 32),
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('data.error', 'erruserverfcode');
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $userId,
            'bank_no_tmp' => '6222000000010700',
        ]);
    }

    /**
     * 验证旧 changeCode/changeuseremail Session 会参与比较，错误验证码返回 erruserverfcode。
     */
    public function test_legacy_bank_change_compares_legacy_session_code(): void
    {
        $userId = 419010800;
        $email = 'profile-bank-session-code-419010800@example.test';
        $this->insertUser($userId, $email, '86-13910800800');
        $this->insertBankAuth($userId, 2);

        $response = $this->withSession([
            'suser' => ['user_id' => $userId],
            'changeCode' => '108800',
            'changeuseremail' => $email,
            'changePhoneNo' => '13910800800',
        ])->postJson('/user/center/uploadChangeBankCard', [
            'useremail' => $email,
            'userverfcode' => '000000',
            'password' => 'password',
            'bankclass' => 'Session Bank',
            'bankno' => '6222000000010800',
            'bankinfo' => 'Session Branch',
        ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'erruserverfcode');
    }

    /**
     * 验证现代 Blade 提供后端加固后必需的密码、验证码和 Lucide 发码控件。
     */
    public function test_profile_bank_change_form_exposes_secure_verification_controls(): void
    {
        $blade = file_get_contents(resource_path('front/layui/profile/index.blade.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';

        $this->assertStringContainsString('id="sendBankChangeCodeBtn"', $blade);
        $this->assertStringContainsString('name="verification_code"', $blade);
        $this->assertStringContainsString('name="password"', $blade);
        $this->assertStringContainsString('data-lucide="send"', $blade);
        $this->assertStringContainsString("'/api/front/profile/bank-card-change/verification-codes'", $script);
        $this->assertStringContainsString("'errbankpendingauth'", $script);
        $this->assertStringContainsString("'errisapplying'", $script);
        $this->assertStringContainsString("'erruserverfcode'", $script);
        $this->assertMatchesRegularExpression(
            '/lay-filter="emailForm".*name="password".*name="verification_code".*id="sendEmailChangeCodeBtn"/s',
            $blade
        );
        $this->assertMatchesRegularExpression(
            '/lay-filter="bankChangeForm".*name="password".*name="verification_code".*id="sendBankChangeCodeBtn"/s',
            $blade
        );

        preg_match('/lay-filter="phoneForm"(.*?)<\/form>/s', $blade, $phoneForm);
        $this->assertNotEmpty($phoneForm, '未找到修改手机号表单。');
        $this->assertStringContainsString('name="password"', $phoneForm[1]);
        $this->assertStringNotContainsString('sendBankChangeCodeBtn', $phoneForm[1]);
    }

    /**
     * 创建测试登录账号和业务资料，所有敏感操作统一使用明文 password 对应的本地哈希。
     */
    private function insertUser(int $userId, string $email, string $phone): UserLogin
    {
        $this->fixtureUserIds[] = $userId;
        $this->deleteFixtureRows([$userId], [$email]);
        $now = time();
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
            'user_name' => 'profile-lifecycle-' . $userId,
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
     * 创建银行卡认证状态；2 表示已审核通过，其余状态均不能申请换绑。
     */
    private function insertBankAuth(int $userId, int $status): void
    {
        $now = time();
        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_name' => 'Current Bank',
            'bank_no' => '622299999999' . substr((string) $userId, -4),
            'bank_addr' => 'Current Branch',
            'bank_status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 创建真实 withdraw_records 待处理或处理中记录，用于验证资金处理中禁止换绑。
     */
    private function insertWithdraw(int $userId, int $status): void
    {
        $now = time();
        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => 'profile-lifecycle-' . $userId,
            'apply_amount' => 100,
            'status' => $status,
            'local_order_no' => 'PROFILE-LIFECYCLE-' . $userId . '-' . $status,
            'funding_status' => $status === 0 ? 'pending' : 'processing',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入新架构验证码缓存，字段与真实发码接口保持一致。
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
     * 提交包含完整旧字段的银行卡换绑请求，返回响应供各业务边界断言。
     */
    private function postLegacyBankChange(UserLogin $login, string $email, string $code)
    {
        return $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/user/center/uploadChangeBankCard', [
                'useremail' => $email,
                'userverfcode' => $code,
                'password' => 'password',
                'bankclass' => 'Lifecycle New Bank',
                'bankno' => '622200000001' . substr((string) $login->user_id, -4),
                'bankinfo' => 'Lifecycle New Branch',
                'bankimg' => UploadedFile::fake()->image('legacy-front.jpg', 32, 32),
            ]);
    }

    /**
     * 清理固定测试 ID，避免本地真实数据库中的历史测试残留造成唯一键冲突。
     *
     * @param array<int, int> $userIds 测试业务用户 ID。
     * @param array<int, string> $emails 测试邮箱。
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
