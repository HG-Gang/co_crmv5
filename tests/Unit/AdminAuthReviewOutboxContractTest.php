<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 18:40
 */

/**
 * AdminAuthReviewOutboxContractTest
 *
 * 文件功能：
 * - 验证实名审核 outbox 契约：迁移定义持久化进行中审核与恢复索引、模型隐藏加密载荷与快照指纹、载荷往返不落明文且完整性哈希不匹配即拒绝、恢复调度携带 outbox id 且内核调度带防重叠保护。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdminAuthReviewPayload;
use RuntimeException;
use Tests\TestCase;

final class AdminAuthReviewOutboxContractTest extends TestCase
{
    public function test_migration_defines_durable_active_review_and_recovery_indexes(): void
    {
        $source = $this->source('database/migrations/2026_08_07_000002_create_admin_auth_review_outboxes.php');

        foreach ([
            "Schema::create('admin_auth_review_outboxes'",
            "unsignedBigInteger('user_id')",
            "unsignedBigInteger('active_user_id')->nullable()",
            "unsignedBigInteger('admin_id')",
            "string('admin_name', 100)",
            "string('request_ip', 45)",
            "string('status', 30)->default('pending')",
            "unsignedInteger('attempts')->default(0)",
            "text('payload_ciphertext')->nullable()",
            "char('payload_hash', 64)->nullable()",
            "char('auth_snapshot_hash', 64)->nullable()",
            "unique('active_user_id', 'admin_auth_review_outboxes_active_user_unique')",
            "index(['status', 'available_at'], 'admin_auth_review_outboxes_ready_index')",
            "index(['status', 'locked_at'], 'admin_auth_review_outboxes_stale_index')",
        ] as $contract) {
            $this->assertStringContainsString($contract, $source, $contract);
        }
        $this->assertStringNotContainsString(
            "if (Schema::hasTable('admin_auth_review_outboxes'))",
            $source,
            'A pre-existing malformed outbox table must fail migration instead of being accepted silently.'
        );
    }

    public function test_model_hides_encrypted_payload_and_snapshot_fingerprints(): void
    {
        $source = $this->source('app/Models/AdminAuthReviewOutbox.php');

        $this->assertStringContainsString(
            "protected \$table = 'admin_auth_review_outboxes';",
            $source
        );
        foreach (['payload_ciphertext', 'payload_hash', 'auth_snapshot_hash'] as $field) {
            $this->assertStringContainsString($field, $source);
        }
        $this->assertStringContainsString(
            "protected \$hidden = ['payload_ciphertext', 'payload_hash', 'auth_snapshot_hash'];",
            $source
        );
    }

    public function test_payload_round_trip_preserves_decisions_without_plaintext_storage(): void
    {
        $this->assertTrue(
            class_exists(AdminAuthReviewPayload::class),
            'AdminAuthReviewPayload must exist before encrypted review intents can be stored.'
        );

        $payload = [
            'user_id' => 901100,
            'decisions' => [
                'bank_decision' => 1,
                'bank_reason' => '',
            ],
            'status_label' => 'component',
        ];
        $secured = AdminAuthReviewPayload::encrypt($payload);

        $this->assertArrayHasKey('ciphertext', $secured);
        $this->assertArrayHasKey('hash', $secured);
        $this->assertSame(64, strlen($secured['hash']));
        $this->assertStringNotContainsString('901100', $secured['ciphertext']);
        $this->assertSame(
            $payload,
            AdminAuthReviewPayload::decrypt($secured['ciphertext'], $secured['hash'])
        );
    }

    public function test_payload_rejects_a_mismatched_integrity_hash(): void
    {
        $this->assertTrue(
            class_exists(AdminAuthReviewPayload::class),
            'AdminAuthReviewPayload must exist before encrypted review intents can be verified.'
        );

        $secured = AdminAuthReviewPayload::encrypt([
            'user_id' => 901101,
            'decisions' => ['id_card_decision' => 1],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payload hash mismatch');
        AdminAuthReviewPayload::decrypt($secured['ciphertext'], str_repeat('0', 64));
    }

    public function test_snapshot_hash_is_stable_and_changes_with_reviewed_bank_data(): void
    {
        $this->assertTrue(
            class_exists(AdminAuthReviewPayload::class),
            'AdminAuthReviewPayload must exist before authentication snapshots can be fingerprinted.'
        );

        $snapshot = [
            'id_card_status' => 2,
            'bank_status' => 3,
            'bank_no_tmp' => 'NEW-BANK-NO',
            'bank_name_tmp' => 'New Bank',
        ];

        $first = AdminAuthReviewPayload::snapshotHash($snapshot);
        $second = AdminAuthReviewPayload::snapshotHash($snapshot);
        $snapshot['bank_no_tmp'] = 'CHANGED-BANK-NO';

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
        $this->assertNotSame($first, AdminAuthReviewPayload::snapshotHash($snapshot));
    }

    public function test_recovery_job_carries_only_the_outbox_id_into_the_processor(): void
    {
        $source = $this->source('app/Jobs/ProcessAdminAuthReview.php');

        foreach ([
            'implements ShouldQueue',
            'private $outboxId;',
            'public function __construct(int $outboxId)',
            'public function handle(AdminAuthReviewProcessor $processor): void',
            '$processor->process((int) $this->outboxId);',
            'public $tries = 1;',
        ] as $contract) {
            $this->assertStringContainsString($contract, $source, $contract);
        }
    }

    public function test_dispatcher_recovers_due_safe_retries_and_stale_processing_claims(): void
    {
        $source = $this->source('app/Console/Commands/DispatchPendingAdminAuthReviews.php');

        foreach ([
            "protected \$signature = 'mt4:dispatch-admin-auth-reviews';",
            "Mt4SyncGate::remoteUserSyncEnabled()",
            "whereIn('status', ['pending', 'retryable'])",
            "where('status', 'processing')",
            "now()->subMinutes(5)->timestamp",
            'chunkById(100',
            'ProcessAdminAuthReview::dispatch((int) $outbox->id);',
        ] as $contract) {
            $this->assertStringContainsString($contract, $source, $contract);
        }
    }

    public function test_kernel_schedules_admin_auth_review_recovery_with_overlap_protection(): void
    {
        $source = $this->source('app/Console/Kernel.php');

        $this->assertStringContainsString(
            "command('mt4:dispatch-admin-auth-reviews')",
            $source
        );
        $position = strpos($source, "command('mt4:dispatch-admin-auth-reviews')");
        $this->assertIsInt($position);
        $schedule = substr($source, $position, 180);
        $this->assertStringContainsString('->everyMinute()', $schedule);
        $this->assertStringContainsString('->withoutOverlapping(5)', $schedule);
    }

    private function source(string $relativePath): string
    {
        $path = base_path($relativePath);
        $this->assertFileExists($path);

        $source = file_get_contents($path);
        $this->assertIsString($source);

        return $source;
    }
}
