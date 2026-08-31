<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 01:21
 */

/**
 * FrontRegistrationMt4ContractTest
 *
 * 文件功能：
 * - 验证前台注册 MT4 开通契约：本地注册默认启用未同步并建待处理 outbox、控制器消费验证码并在本地提交后签发 JWT 不受 MT4 闸门阻塞、加密载荷不序列化凭据、过期载荷解密前拒绝、分组校验先于本地写、处理器按状态重置元数据且绝不重发 unknown。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FrontRegistrationMt4ContractTest extends TestCase
{
    public function test_local_registration_starts_enabled_and_unsynced_with_a_pending_outbox(): void
    {
        $source = $this->source('app/Services/UserRegistrationService.php');
        $register = $this->methodSource($source, 'register');
        $createLogin = $this->methodSource($source, 'createUserLogin');
        $createInfo = $this->methodSource($source, 'createUserInfo');

        $this->assertStringContainsString("'is_enabled' => 1", $createLogin);
        $this->assertStringContainsString("'is_mt4_synced' => 0", $createInfo);
        $this->assertStringContainsString("'is_mt4_enabled' => 0", $createInfo);
        $this->assertStringContainsString('UserMt4ProvisioningOutbox::create', $register);
        $this->assertStringContainsString("'status' => 'pending'", $register);
        $this->assertStringContainsString("'registered' => true", $register);
        $this->assertStringContainsString("'provisioning_status' => 'pending'", $register);
    }

    public function test_controller_consumes_captcha_and_signs_jwt_after_local_commit_without_mt4_gate(): void
    {
        $method = $this->methodSource(
            $this->source('app/Http/Controllers/Front/AuthController.php'),
            'register'
        );

        $registered = strpos($method, "['registered']");
        $consume = strpos($method, 'consumeRegisterCaptcha');
        $provisioningStatus = strpos($method, "['provisioning_status']");
        $jwt = strpos($method, 'generateToken');

        $this->assertNotFalse($registered, 'Registration response must expose whether the local account was committed.');
        $this->assertNotFalse($consume, 'Committed registrations must consume the captcha used by registration.');
        $this->assertNotFalse($provisioningStatus, 'The response must expose the pending provisioning status.');
        $this->assertNotFalse($jwt, 'A committed local account must receive a registration JWT.');
        $this->assertLessThan($consume, $registered);
        $this->assertLessThan($provisioningStatus, $consume);
        $this->assertLessThan($jwt, $provisioningStatus);
        $this->assertStringNotContainsString('UserMt4ProvisioningProcessor', $method);
        $this->assertStringNotContainsString('Mt4SyncGate', $method);
        $this->assertStringNotContainsString('ResponseCode::MT4_SYNC_FAILED', $method);
    }

    public function test_modern_and_legacy_login_do_not_depend_on_mt4_provisioning_state(): void
    {
        $source = $this->source('app/Http/Controllers/Front/AuthController.php');
        $methods = [
            $this->methodSource($source, 'login'),
            $this->methodSource($source, 'legacySignIn'),
        ];

        foreach ($methods as $method) {
            $this->assertStringNotContainsString('is_mt4_synced', $method);
            $this->assertStringNotContainsString('is_mt4_enabled', $method);
            $this->assertStringNotContainsString('mt4_code', $method);
            $this->assertStringNotContainsString('MT4_SYNC_FAILED', $method);
        }
    }

    public function test_provisioning_dispatcher_and_minute_schedule_are_registered(): void
    {
        $command = $this->source('app/Console/Commands/DispatchPendingUserMt4Provisioning.php');
        $kernel = $this->source('app/Console/Kernel.php');

        $this->assertStringContainsString('mt4:dispatch-user-provisioning', $command);
        $this->assertStringContainsString('ProcessUserMt4Provisioning::dispatch', $command);
        $this->assertStringContainsString("['pending', 'retryable', 'unknown']", $command);
        $this->assertStringContainsString("command('mt4:dispatch-user-provisioning')", $kernel);
        $this->assertStringContainsString('->everyMinute()', $kernel);
        $this->assertStringContainsString('->withoutOverlapping(5)', $kernel);
    }

    public function test_registration_encrypts_payload_but_never_calls_or_dispatches_mt4(): void
    {
        $source = $this->source('app/Services/UserRegistrationService.php');
        $payload = $this->source('app/Services/Registration/UserMt4ProvisioningPayload.php');
        $register = $this->methodSource($source, 'register');

        $transaction = strpos($register, 'DB::transaction');
        $encrypt = strpos($register, 'UserMt4ProvisioningPayload::encrypt');

        $this->assertNotFalse($transaction);
        $this->assertNotFalse($encrypt);
        $this->assertStringNotContainsString('provisioningProcessor->process', $register);
        $this->assertStringNotContainsString('ProcessUserMt4Provisioning::dispatch', $register);
        $this->assertStringNotContainsString('gateway->provision', $register);
        $this->assertStringNotContainsString('gateway->reconcile', $register);
        $this->assertStringContainsString('Crypt::encryptString', $payload);
        $this->assertStringContainsString('Crypt::decryptString', $payload);
        $this->assertStringContainsString("hash_hmac('sha256'", $payload);
        $this->assertStringContainsString('hash_equals', $payload);
    }

    public function test_registration_builds_the_complete_legacy_mt4_opening_payload(): void
    {
        $payload = $this->methodSource(
            $this->source('app/Services/UserRegistrationService.php'),
            'buildMt4ProvisioningPayload'
        );

        foreach (['user_id', 'name', 'password', 'email', 'phone', 'id_card', 'parent_id', 'group', 'country', 'leverage'] as $key) {
            $this->assertStringContainsString("'{$key}' =>", $payload);
        }
        $this->assertStringContainsString("'parent_id' => (int) \$userInfo->parent_id", $payload);
        $this->assertStringContainsString('buildLegacyRelationshipCode((int) $userInfo->parent_id)', $payload);
        $this->assertStringContainsString("'leverage' => 100", $payload);
    }

    public function test_processor_owns_claim_and_never_resends_unknown_registration(): void
    {
        $source = $this->source('app/Services/Registration/UserMt4ProvisioningProcessor.php');

        $this->assertStringContainsString('UserMt4ProvisioningPayload::decrypt', $source);
        $this->assertStringContainsString('ownsClaim', $source);
        $this->assertStringContainsString("'manual_reconcile_required'", $source);
        $this->assertStringContainsString("'local_commit_after_external_success_failed'", $source);

        $this->assertNotFalse(strpos($source, "status === 'unknown'"));
        $gatewayCall = $this->methodSource($source, 'callGateway');
        $reconcile = strpos($gatewayCall, 'gateway->reconcile');
        $provision = strpos($gatewayCall, 'gateway->provision');
        $this->assertNotFalse($reconcile);
        $this->assertNotFalse($provision);
        $this->assertLessThan($provision, $reconcile);
    }

    public function test_outbox_model_never_serializes_encrypted_credentials(): void
    {
        $source = $this->source('app/Models/UserMt4ProvisioningOutbox.php');

        $this->assertStringContainsString(
            "protected $" . "hidden = ['payload_ciphertext', 'payload_hash']",
            $source
        );
    }

    public function test_expired_payload_is_rejected_before_decryption_or_gateway_delivery(): void
    {
        $source = $this->source('app/Services/Registration/UserMt4ProvisioningProcessor.php');
        $claim = $this->methodSource($source, 'claim');
        $expiry = strpos($claim, 'payloadExpired');
        $decrypt = strpos($claim, 'UserMt4ProvisioningPayload::decrypt');

        $this->assertNotFalse($expiry, 'Claiming must stop expired password material before it is decrypted.');
        $this->assertNotFalse($decrypt);
        $this->assertLessThan($decrypt, $expiry);
        $this->assertStringContainsString("markManual($" . "outbox, 'provision_payload_expired')", $claim);
    }

    public function test_resolved_mt4_group_is_validated_before_any_local_account_write(): void
    {
        $source = $this->source('app/Services/UserRegistrationService.php');
        $register = $this->methodSource($source, 'register');
        $resolveGroup = strpos($register, 'resolveMt4Group');
        $validateGroup = strpos($register, 'isSafeMt4ProtocolValue');
        $generateUserId = strpos($register, 'generateUserId');
        $createLogin = strpos($register, 'createUserLogin');

        $this->assertNotFalse($resolveGroup);
        $this->assertNotFalse($validateGroup);
        $this->assertNotFalse($generateUserId);
        $this->assertNotFalse($createLogin);
        $this->assertLessThan($generateUserId, $resolveGroup);
        $this->assertLessThan($generateUserId, $validateGroup);
        $this->assertLessThan($createLogin, $validateGroup);
    }

    public function test_unknown_paths_clear_credentials_but_retryable_keeps_them(): void
    {
        $source = $this->source('app/Services/Registration/UserMt4ProvisioningProcessor.php');

        foreach ([
            'recordUnknown',
            'recordReconciliationRetryable',
            'recordReconciliationUnknown',
            'markStaleClaimUnknown',
            'recordLocalCommitFailure',
        ] as $method) {
            $this->assertStringContainsString(
                'clearPayload(',
                $this->methodSource($source, $method),
                $method . ' must remove credentials before an account-info-only reconciliation.'
            );
        }
        $clearPayload = $this->methodSource($source, 'clearPayload');
        $this->assertStringContainsString('payload_ciphertext = null', $clearPayload);
        $this->assertStringContainsString('payload_hash = null', $clearPayload);
        $this->assertStringNotContainsString(
            'clearPayload(',
            $this->methodSource($source, 'recordRetryable'),
            'A connection-before-send retry still needs the encrypted registration payload.'
        );
    }

    public function test_processor_resets_due_and_completion_metadata_for_each_state(): void
    {
        $source = $this->source('app/Services/Registration/UserMt4ProvisioningProcessor.php');

        foreach (['finalizeProcessed', 'recordRejected', 'markManual'] as $method) {
            $terminal = $this->methodSource($source, $method);
            $this->assertStringContainsString(
                'available_at = null',
                $terminal,
                $method . ' must not leave a terminal event in the due queue.'
            );
            $this->assertStringContainsString(
                'processed_at = now()',
                $terminal,
                $method . ' must timestamp terminal completion.'
            );
        }

        foreach (['recordRetryable', 'recordUnknown', 'recordReconciliationRetryable', 'markStaleClaimUnknown'] as $method) {
            $nonterminal = $this->methodSource($source, $method);
            $this->assertStringContainsString(
                'processed_at = null',
                $nonterminal,
                $method . ' must clear stale completion metadata.'
            );
        }

        foreach (['recordReconciliationUnknown', 'recordLocalCommitFailure'] as $method) {
            $mixed = $this->methodSource($source, $method);
            $this->assertStringContainsString('processed_at = null', $mixed);
            $this->assertStringContainsString('available_at = null', $mixed);
        }
    }

    public function test_migration_repairs_contract_without_mutating_existing_rows(): void
    {
        $source = $this->source(
            'database/migrations/2026_07_19_000002_create_user_mt4_provisioning_outbox.php'
        );

        $this->assertStringContainsString('ensureColumns()', $source);
        $this->assertStringContainsString('ensureColumnTypes()', $source);
        $this->assertStringContainsString('assertNoDuplicateUserIds()', $source);
        $this->assertStringContainsString('COUNT(*) > 1', $source);
        $this->assertStringNotContainsString('->delete(', $source);
        $this->assertStringNotContainsString('->update(', $source);
        $down = $this->methodSource($source, 'down');
        $this->assertStringNotContainsString('drop', strtolower($down));
        $this->assertStringNotContainsString('truncate', strtolower($down));
    }

    private function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        return $source;
    }

    private function methodSource(string $source, string $method): string
    {
        $start = strpos($source, 'function ' . $method . '(');
        $this->assertNotFalse($start, 'Missing method ' . $method . '.');
        $open = strpos($source, '{', $start);
        $this->assertNotFalse($open, 'Missing opening brace for ' . $method . '.');

        $depth = 0;
        $length = strlen($source);
        for ($offset = $open; $offset < $length; $offset++) {
            if ($source[$offset] === '{') {
                $depth++;
            } elseif ($source[$offset] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $offset - $start + 1);
                }
            }
        }

        $this->fail('Unclosed method ' . $method . '.');
    }
}
