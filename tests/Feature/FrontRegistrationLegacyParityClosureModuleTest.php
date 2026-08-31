<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:05
 */

/**
 * FrontRegistrationLegacyParityClosureModuleTest
 *
 * 文件功能：
 * - 验证前台注册旧口径等价：仅持久化待处理 MT4 载荷不调用网关、免邮箱验证码与默认邀请人、受邀代理佣金上限与零佣金分组、层级由 parent_id 派生、闭包写入失败整体回滚、密码旧模式校验。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\UserMt4ProvisioningGateway;
use App\Models\AgentDescendant;
use App\Models\AgentLevel;
use App\Models\GroupConfig;
use App\Models\UserInfo;
use App\Models\UserMt4ProvisioningOutbox;
use App\Services\FamilyTreeService;
use App\Services\Registration\UserMt4ProvisioningPayload;
use App\Services\Registration\UserMt4ProvisioningProcessor;
use App\Services\UserRegistrationService;
use App\Support\FrontLegacyData;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Tests\Support\RegisteredUserFixtureCleaner;
use Tests\TestCase;

class FrontRegistrationLegacyParityClosureModuleTest extends TestCase
{
    /** @var array<int, int> */
    private array $createdUserIds = [];

    protected function tearDown(): void
    {
        try {
            RegisteredUserFixtureCleaner::forceDelete($this->createdUserIds);
        } finally {
            parent::tearDown();
        }
    }

    public function test_registration_only_persists_pending_legacy_mt4_payload_without_calling_gateway(): void
    {
        Config::set('mt4.enabled', true);
        Config::set('mt4.user_sync_enabled', true);

        $gateway = Mockery::mock(UserMt4ProvisioningGateway::class);
        $gateway->shouldNotReceive('provision');
        $gateway->shouldNotReceive('reconcile');
        $service = new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway));

        $suffix = str_replace('.', '', uniqid('', true));
        $email = 'legacy-parity-' . $suffix . '@example.test';
        $password = 'RegisterA123';
        $result = $service->register([
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'user_name' => 'Legacy Parity User',
            'phone' => '86-139' . substr($suffix, -8),
            'id_card_no' => 'LEGACY-PARITY-' . $suffix,
            'gender' => 2,
            'account_type' => 2,
            'leverage' => 500,
        ], null, 2);

        $this->assertTrue($result['success'] ?? false, json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertTrue($result['registered'] ?? false);
        $this->assertSame('pending', $result['provisioning_status'] ?? null);

        $userLogin = $result['user_login'];
        $userInfo = $result['user_info'];
        $this->createdUserIds[] = (int) $userLogin->user_id;

        $this->assertSame(1, (int) $userLogin->is_enabled);
        $this->assertSame(10, (int) $userInfo->parent_id);
        $this->assertSame(1, (int) $userInfo->is_agent_confirmed);
        $this->assertSame(100, (int) $userInfo->leverage);

        $outbox = UserMt4ProvisioningOutbox::where('user_id', $userLogin->user_id)->firstOrFail();
        $this->assertSame('pending', $outbox->status);
        $this->assertSame(0, (int) $outbox->attempts);
        $this->assertNotSame('', (string) $outbox->getRawOriginal('payload_ciphertext'));
        $this->assertNotSame('', (string) $outbox->getRawOriginal('payload_hash'));

        $payload = UserMt4ProvisioningPayload::decrypt(
            (string) $outbox->getRawOriginal('payload_ciphertext'),
            (string) $outbox->getRawOriginal('payload_hash')
        );
        $this->assertSame([
            'user_id' => (int) $userLogin->user_id,
            'name' => 'Legacy Parity User',
            'password' => $password,
            'email' => $email,
            'phone' => (string) $userInfo->phone,
            'id_card' => 'LEGACY-PARITY-' . $suffix,
            'parent_id' => 10,
            'group' => (string) $userInfo->mt4_group,
            'country' => '100000000000000000',
            'leverage' => 100,
        ], array_intersect_key($payload, array_flip([
            'user_id', 'name', 'password', 'email', 'phone', 'id_card',
            'parent_id', 'group', 'country', 'leverage',
        ])));
    }

    public function test_legacy_register_submission_does_not_require_email_code_and_uses_default_inviter(): void
    {
        $captchaResponse = $this->get('/user/register/captcha')->assertOk();
        preg_match('/<text[^>]*>([^<]+)<\/text>/', $captchaResponse->getContent(), $matches);
        $captchaCode = trim((string) ($matches[1] ?? ''));
        $this->assertNotSame('', $captchaCode);

        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')
            ->once()
            ->withArgs(function (array $data, $parentId, int $accountType, string $commissionMode): bool {
                return $parentId === 10
                    && $accountType === 2
                    && $commissionMode === 'A'
                    && ($data['inviter_id'] ?? null) === 10;
            })
            ->andReturn(['legacy-validation-sentinel']);
        $registrationService->shouldNotReceive('register');
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/user/register/registerinto', [
            'register_type' => 'user',
            'comm_type' => 'A',
            'username' => 'Legacy Register User',
            'sex' => 2,
            'userIdcardNo' => 'LEGACY-NO-EMAIL-CODE',
            'modules' => '86',
            'userphoneNo' => '13999112233',
            'useremail' => 'legacy-no-email-code@example.test',
            'reguserverfcode' => $captchaCode,
            'password' => 'LegacyA123',
            'agreeRule' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_ERROR)
            ->assertJsonPath('message', 'legacy-validation-sentinel');
    }

    public function test_invited_agent_uses_next_level_commission_cap_and_zero_commission_group(): void
    {
        Config::set('mt4.enabled', true);
        Config::set('mt4.user_sync_enabled', true);

        $gateway = Mockery::mock(UserMt4ProvisioningGateway::class);
        $gateway->shouldNotReceive('provision');
        $gateway->shouldNotReceive('reconcile');
        $service = new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway));

        $parent = \App\Models\UserInfo::where('user_id', 10)->firstOrFail();
        $parentLevelCode = (int) AgentLevel::whereKey($parent->level_id)->value('level_code');
        $nextLevelCode = $parentLevelCode === 4 ? 5 : $parentLevelCode + 1;
        $nextLevel = AgentLevel::where('level_code', $nextLevelCode)->firstOrFail();
        $zeroCommissionGroup = GroupConfig::query()
            ->where('category', 1)
            ->where('has_commission', 0)
            ->where('is_enabled', 1)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->firstOrFail();

        $suffix = str_replace('.', '', uniqid('', true));
        $password = 'AgentA123';
        $result = $service->register([
            'email' => 'legacy-agent-' . $suffix . '@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'user_name' => 'Legacy Invited Agent',
            'phone' => '86-138' . substr($suffix, -8),
            'id_card_no' => 'LEGACY-AGENT-' . $suffix,
            'gender' => 1,
            'account_type' => 1,
            'commission_mode' => 'A',
        ], 10, 1);

        $this->assertTrue($result['success'] ?? false, json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->createdUserIds[] = (int) $result['user_info']->user_id;

        $userInfo = $result['user_info'];
        $this->assertSame((int) $nextLevel->id, (int) $userInfo->level_id);
        $this->assertSame(min((int) $parent->comm_rate, (int) $nextLevel->max_commission), (int) $userInfo->comm_rate);
        $this->assertSame((int) $zeroCommissionGroup->id, (int) $userInfo->group_id);
        $this->assertSame((string) $zeroCommissionGroup->name, (string) $userInfo->mt4_group);
        $this->assertSame(0, (int) $userInfo->is_agent_confirmed);
        $this->assertSame(1, (int) $userInfo->is_mt4_readonly);

        $outbox = UserMt4ProvisioningOutbox::where('user_id', $userInfo->user_id)->firstOrFail();
        $payload = UserMt4ProvisioningPayload::decrypt(
            (string) $outbox->getRawOriginal('payload_ciphertext'),
            (string) $outbox->getRawOriginal('payload_hash')
        );
        $this->assertSame((string) $zeroCommissionGroup->name, $payload['group'] ?? null);
        $this->assertSame('100000000000000000', $payload['country'] ?? null);
    }

    public function test_agent_registration_without_inviter_is_rejected(): void
    {
        $gateway = Mockery::mock(UserMt4ProvisioningGateway::class);
        $gateway->shouldNotReceive('provision');
        $gateway->shouldNotReceive('reconcile');
        $service = new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway));
        $suffix = str_replace('.', '', uniqid('', true));
        $password = 'AgentA123';

        $result = $service->register([
            'email' => 'agent-without-inviter-' . $suffix . '@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'user_name' => 'Agent Without Inviter',
            'phone' => '86-137' . substr($suffix, -8),
            'id_card_no' => 'AGENT-NO-INVITER-' . $suffix,
            'account_type' => 1,
        ], null, 1);

        if (($result['success'] ?? false) === true) {
            $this->createdUserIds[] = (int) $result['user_info']->user_id;
        }
        $this->assertFalse($result['success'] ?? true);
    }

    public function test_multilevel_registration_and_agent_move_keep_the_complete_descendant_closure(): void
    {
        Config::set('mt4.enabled', true);
        Config::set('mt4.user_sync_enabled', true);

        $gateway = Mockery::mock(UserMt4ProvisioningGateway::class);
        $gateway->shouldNotReceive('provision');
        $gateway->shouldNotReceive('reconcile');
        $service = new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway));

        $customerOne = $this->registerTreeFixtureUser($service, 10, 2, 'C1');
        $agentB = $this->registerTreeFixtureUser($service, 10, 1, 'B');
        $agentB->update(['is_agent_confirmed' => 1]);
        $customerTwo = $this->registerTreeFixtureUser($service, (int) $agentB->user_id, 2, 'C2');
        $agentD = $this->registerTreeFixtureUser($service, (int) $agentB->user_id, 1, 'D');
        $agentD->update(['is_agent_confirmed' => 1]);
        $customerThree = $this->registerTreeFixtureUser($service, (int) $agentD->user_id, 2, 'C3');

        $this->assertDescendantRelation(10, (int) $agentB->user_id, 1, 1, 1);
        $this->assertDescendantRelation(10, (int) $agentD->user_id, 1, 0, 2);
        $this->assertDescendantRelation(10, (int) $customerOne->user_id, 2, 1, 1);
        $this->assertDescendantRelation(10, (int) $customerTwo->user_id, 2, 0, 2);
        $this->assertDescendantRelation(10, (int) $customerThree->user_id, 2, 0, 3);

        $this->assertDescendantRelation((int) $agentB->user_id, (int) $agentD->user_id, 1, 1, 1);
        $this->assertDescendantRelation((int) $agentB->user_id, (int) $customerTwo->user_id, 2, 1, 1);
        $this->assertDescendantRelation((int) $agentB->user_id, (int) $customerThree->user_id, 2, 0, 2);
        $this->assertFalse(AgentDescendant::where('agent_id', $agentB->user_id)
            ->where('descendant_id', $customerOne->user_id)->exists());
        $this->assertDescendantRelation((int) $agentD->user_id, (int) $customerThree->user_id, 2, 1, 1);

        $scopeA = FrontLegacyData::userScopeIds(10, false);
        sort($scopeA);
        $expectedScopeA = array_map('intval', [
            $agentB->user_id,
            $agentD->user_id,
            $customerOne->user_id,
            $customerTwo->user_id,
            $customerThree->user_id,
        ]);
        sort($expectedScopeA);
        $this->assertSame($expectedScopeA, $scopeA);

        $scopeB = FrontLegacyData::userScopeIds((int) $agentB->user_id, false);
        sort($scopeB);
        $expectedScopeB = array_map('intval', [
            $agentD->user_id,
            $customerTwo->user_id,
            $customerThree->user_id,
        ]);
        sort($expectedScopeB);
        $this->assertSame($expectedScopeB, $scopeB);

        (new FamilyTreeService())->reassignParent((int) $agentD->user_id, 10);

        $this->assertFalse(AgentDescendant::where('agent_id', $agentB->user_id)
            ->whereIn('descendant_id', [$agentD->user_id, $customerThree->user_id])
            ->exists());
        $this->assertDescendantRelation(10, (int) $agentD->user_id, 1, 1, 1);
        $this->assertDescendantRelation(10, (int) $customerThree->user_id, 2, 0, 2);
        $this->assertDescendantRelation((int) $agentD->user_id, (int) $customerThree->user_id, 2, 1, 1);
        $this->assertSame(
            '10,' . $agentD->user_id . ',' . $customerThree->user_id,
            (string) $customerThree->fresh()->family_tree
        );
    }

    public function test_registration_derives_hierarchy_from_parent_id_instead_of_stale_inviter_family_tree(): void
    {
        $gateway = Mockery::mock(UserMt4ProvisioningGateway::class);
        $gateway->shouldNotReceive('provision');
        $gateway->shouldNotReceive('reconcile');
        $service = new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway));

        $parentAgent = $this->registerTreeFixtureUser($service, 10, 1, 'AuthoritativeParent');
        $siblingAgent = $this->registerTreeFixtureUser($service, 10, 1, 'UnrelatedSibling');
        $parentAgent->update([
            'is_agent_confirmed' => 1,
            'family_tree' => '10,' . $siblingAgent->user_id . ',' . $parentAgent->user_id,
        ]);
        $siblingAgent->update(['is_agent_confirmed' => 1]);

        $customer = $this->registerTreeFixtureUser(
            $service,
            (int) $parentAgent->user_id,
            2,
            'ParentTopologyCustomer'
        );

        $this->assertSame(
            '10,' . $parentAgent->user_id . ',' . $customer->user_id,
            (string) $customer->family_tree
        );
        $this->assertDescendantRelation(10, (int) $customer->user_id, 2, 0, 2);
        $this->assertDescendantRelation((int) $parentAgent->user_id, (int) $customer->user_id, 2, 1, 1);
        $this->assertDatabaseMissing('agent_descendants', [
            'agent_id' => (int) $siblingAgent->user_id,
            'descendant_id' => (int) $customer->user_id,
            'deleted_at' => null,
        ]);
    }

    public function test_registration_failure_after_closure_write_rolls_back_the_entire_local_account(): void
    {
        $gateway = Mockery::mock(UserMt4ProvisioningGateway::class);
        $gateway->shouldNotReceive('provision');
        $gateway->shouldNotReceive('reconcile');
        $service = new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway));
        $failedUserId = null;
        $eventName = 'eloquent.creating: ' . UserMt4ProvisioningOutbox::class;

        Event::listen($eventName, function (UserMt4ProvisioningOutbox $outbox) use (&$failedUserId): void {
            $failedUserId = (int) $outbox->user_id;
            throw new RuntimeException('forced outbox persistence failure');
        });

        $suffix = str_replace('.', '', uniqid('', true));
        try {
            $service->register([
                'email' => 'tree-rollback-' . $suffix . '@example.test',
                'password' => 'RollbackA123',
                'password_confirmation' => 'RollbackA123',
                'user_name' => 'Tree Rollback',
                'phone' => '86-15' . substr($suffix, -9),
                'id_card_no' => 'TREE-ROLLBACK-' . $suffix,
                'gender' => 1,
                'account_type' => 2,
            ], 10, 2);
            $this->fail('The forced outbox failure must escape the registration transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced outbox persistence failure', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertNotNull($failedUserId);
        $this->assertDatabaseMissing('user_logins', ['user_id' => $failedUserId]);
        $this->assertDatabaseMissing('user_infos', ['user_id' => $failedUserId]);
        $this->assertDatabaseMissing('user_auths', ['user_id' => $failedUserId]);
        $this->assertDatabaseMissing('agent_descendants', ['descendant_id' => $failedUserId]);
        $this->assertDatabaseMissing('user_mt4_provisioning_outbox', ['user_id' => $failedUserId]);
    }

    /** @dataProvider invalidLegacyPasswordProvider */
    public function test_registration_rejects_passwords_that_do_not_match_legacy_pattern(string $password): void
    {
        $gateway = Mockery::mock(UserMt4ProvisioningGateway::class);
        $gateway->shouldNotReceive('provision');
        $gateway->shouldNotReceive('reconcile');
        $service = new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway));
        $suffix = str_replace('.', '', uniqid('', true));

        $result = $service->register([
            'email' => 'invalid-password-' . md5($password . $suffix) . '@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'user_name' => 'Invalid Password User',
            'phone' => '86-136' . substr($suffix, -8),
            'id_card_no' => 'INVALID-PASSWORD-' . md5($password . $suffix),
            'account_type' => 2,
        ], 10, 2);

        if (($result['success'] ?? false) === true) {
            $this->createdUserIds[] = (int) $result['user_info']->user_id;
        }
        $this->assertFalse($result['success'] ?? true);
    }

    public function invalidLegacyPasswordProvider(): array
    {
        return [
            'must start with a letter' => ['123456'],
            'must end with a digit' => ['Abcdef'],
        ];
    }

    public function test_modern_registration_does_not_require_email_code(): void
    {
        $captchaKey = 'modern-no-email-code-' . uniqid();
        Cache::put('front_register_captcha_' . sha1($captchaKey), 'AB12', now()->addMinutes(10));

        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')
            ->once()
            ->withArgs(function (array $data, $parentId, int $accountType, string $commissionMode): bool {
                return $parentId === 10
                    && $accountType === 2
                    && $commissionMode === ''
                    && ($data['inviter_id'] ?? null) === 10;
            })
            ->andReturn(['modern-validation-sentinel']);
        $registrationService->shouldNotReceive('register');
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', [
            'email' => 'modern-no-email-code@example.test',
            'password' => 'ModernA123',
            'password_confirmation' => 'ModernA123',
            'user_name' => 'Modern Register User',
            'phone_code' => '86',
            'phone_number' => '13999112234',
            'phone' => '86-13999112234',
            'id_card_no' => 'MODERN-NO-EMAIL-CODE',
            'gender' => 2,
            'account_type' => 2,
            'captcha_key' => $captchaKey,
            'captcha_code' => 'AB12',
            'agree_terms' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_ERROR)
            ->assertJsonPath('message', 'modern-validation-sentinel');
    }

    public function test_layui_legacy_invitation_page_embeds_locked_type_and_commission_mode(): void
    {
        $response = $this->get('/user/register/agents/10/A')->assertOk();

        $response->assertSee('id="legacyAccountType" value="1"', false)
            ->assertSee('name="commission_mode" value="A"', false)
            ->assertSee('name="inviter_id" id="inviterId"', false)
            ->assertSee('value="10"', false)
            ->assertDontSee('name="email_code"', false)
            ->assertDontSee('id="sendEmailCode"', false);
    }

    public function test_crmui_registration_page_supports_agent_and_commission_invitation_context(): void
    {
        $response = $this->get('/front-crmui/register/10?account_type=1&commission_mode=A')->assertOk();

        $response->assertSee('name="account_type" value="1"', false)
            ->assertSee('name="account_type" value="2"', false)
            ->assertSee('name="commission_mode" value="A"', false)
            ->assertSee('name="inviter_id" value="10"', false)
            ->assertDontSee('name="email_code"', false)
            ->assertDontSee('data-crmui-secondary-action="send-email-code"', false);
    }

    public function test_all_registration_views_and_layui_script_exclude_email_code_workflow(): void
    {
        foreach ([
            'resources/front/layui/auth/register.blade.php',
            'resources/front/layui/auth/register_v2.blade.php',
            'resources/front/crmui/auth/register.blade.php',
            'resources/front/crmui/auth/register_v2.blade.php',
        ] as $relativePath) {
            $source = file_get_contents(base_path($relativePath)) ?: '';
            $this->assertStringNotContainsString('name="email_code"', $source, $relativePath);
            $this->assertStringNotContainsString('sendEmailCode', $source, $relativePath);
            $this->assertStringNotContainsString('send-email-code', $source, $relativePath);
        }

        $script = file_get_contents(base_path('public/js/apps/front/layui/pages.js')) ?: '';
        $this->assertStringNotContainsString('/api/front/auth/register/email-code', $script);
        $this->assertStringNotContainsString('sendRegisterEmailCode', $script);
        $this->assertStringNotContainsString('registerEmailCodeTimer', $script);
    }

    private function registerTreeFixtureUser(
        UserRegistrationService $service,
        int $parentId,
        int $accountType,
        string $label
    ): UserInfo {
        $suffix = str_replace('.', '', uniqid('', true));
        $password = 'TreeA123';
        $result = $service->register([
            'email' => 'tree-' . strtolower($label) . '-' . $suffix . '@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'user_name' => 'Tree ' . $label,
            'phone' => '86-13' . substr($suffix, -9),
            'id_card_no' => 'TREE-' . $label . '-' . $suffix,
            'gender' => 1,
            'account_type' => $accountType,
        ], $parentId, $accountType);

        $this->assertTrue($result['success'] ?? false, json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertSame('pending', $result['provisioning_status'] ?? null);
        $this->assertInstanceOf(UserInfo::class, $result['user_info'] ?? null);

        $userInfo = $result['user_info'];
        $this->createdUserIds[] = (int) $userInfo->user_id;

        return $userInfo;
    }

    private function assertDescendantRelation(
        int $agentId,
        int $descendantId,
        int $descendantType,
        int $isDirect,
        int $depth
    ): void {
        $this->assertDatabaseHas('agent_descendants', [
            'agent_id' => $agentId,
            'descendant_id' => $descendantId,
            'descendant_type' => $descendantType,
            'is_direct' => $isDirect,
            'depth' => $depth,
            'deleted_at' => null,
        ]);
    }
}
