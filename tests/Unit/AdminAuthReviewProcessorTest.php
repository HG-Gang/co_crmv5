<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 19:14
 */

/**
 * AdminAuthReviewProcessorTest
 *
 * 文件功能：
 * - 验证实名审核处理器：HTTP 边界可替换、仅对确定未发送的 MT4 结果重试、本地审核用锁定新鲜快照且不调 MT4、active outbox 唯一性冲突映射、加锁重载、加密意图保留、快照变化与缺 user_info 隔离为 unknown。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdminAuthReviewPayload;
use App\Services\AdminAuthReviewProcessor;
use App\Services\Mt4ManagerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use PDOException;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

final class AdminAuthReviewProcessorTest extends TestCase
{
    public function test_processor_can_be_substituted_at_the_http_boundary(): void
    {
        $source = file_get_contents(base_path('app/Services/AdminAuthReviewProcessor.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString('class AdminAuthReviewProcessor', $source);
        $this->assertStringNotContainsString('final class AdminAuthReviewProcessor', $source);
    }

    public function test_mt4_result_classification_retries_only_definitely_not_sent_responses(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $processor = new AdminAuthReviewProcessor(Mockery::mock(Mt4ManagerService::class));
        $method = new ReflectionMethod($processor, 'classifyMt4Result');
        $method->setAccessible(true);

        $cases = [
            [['status' => 'ok'], 'processed', null],
            [['status' => 'error', 'error_code' => 'connection_failed'], 'retryable', 'connection_failed'],
            [['status' => 'error', 'error_code' => 'mt4_sync_disabled'], 'retryable', 'mt4_sync_disabled'],
            [['status' => 'error', 'error_code' => 'write_failed'], 'unknown', 'write_failed'],
            [['status' => 'error', 'error_code' => 'read_timeout'], 'unknown', 'read_timeout'],
            [['status' => 'error', 'error_code' => 'malformed_response'], 'unknown', 'malformed_response'],
            [['status' => 'error', 'error_code' => 'transport'], 'unknown', 'transport'],
            [['status' => 'error', 'error_code' => 'transport_exception'], 'unknown', 'transport_exception'],
            [['status' => 'error', 'error_code' => 'unexpected_response'], 'unknown', 'unexpected_response'],
            [['status' => 'error', 'error_code' => 'invalid_bank'], 'rejected', 'invalid_bank'],
            [['status' => 'error'], 'rejected', 'provider_rejected'],
        ];

        foreach ($cases as [$response, $status, $errorCode]) {
            $classified = $method->invoke($processor, $response);
            $this->assertSame($status, $classified['status']);
            $this->assertSame($errorCode, $classified['error_code']);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_local_review_uses_locked_fresh_snapshot_and_never_calls_mt4(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $activeQuery = Mockery::mock();
        $activeQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $activeQuery->shouldReceive('first')->once()->andReturnNull();
        Mockery::mock('alias:App\Models\AdminAuthReviewOutbox')
            ->shouldReceive('where')->once()->with('active_user_id', 901200)->andReturn($activeQuery)
            ->shouldNotReceive('create');

        $auth = $this->authRow(1, 2);
        $auth->shouldReceive('update')->once()->with([
            'id_card_status' => 2,
            'id_card_remarks' => '',
        ])->andReturnTrue();
        $authQuery = Mockery::mock();
        $authQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $authQuery->shouldReceive('first')->once()->andReturn($auth);
        Mockery::mock('alias:App\Models\UserAuth')
            ->shouldReceive('where')->once()->with('user_id', 901200)->andReturn($authQuery);

        $userInfo = Mockery::mock();
        $userInfo->shouldReceive('update')->once()->with(['auth_status' => 1])->andReturnTrue();
        $userInfoQuery = Mockery::mock();
        $userInfoQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $userInfoQuery->shouldReceive('first')->once()->andReturn($userInfo);
        Mockery::mock('alias:App\Models\UserInfo')
            ->shouldReceive('where')->once()->with('user_id', 901200)->andReturn($userInfoQuery);
        Mockery::mock('alias:App\Models\OperationLog')
            ->shouldReceive('create')->once()->with(Mockery::on(function (array $log): bool {
                return $log['target_user_id'] === 901200
                    && strpos($log['content'], 'id_card_status:1->2') !== false;
            }))->andReturnTrue();

        DB::shouldReceive('transaction')->once()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldNotReceive('updateComment');

        $result = (new AdminAuthReviewProcessor($mt4))->submit(
            901200,
            ['id_card_decision' => 1, 'id_card_reason' => ''],
            $this->actorContext()
        );

        $this->assertSame(['status' => 'processed'], $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_existing_active_outbox_rejects_submit_after_auth_is_locked(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $auth = $this->authRow(1, 2);
        $auth->shouldNotReceive('update');
        $authQuery = Mockery::mock();
        $authQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $authQuery->shouldReceive('first')->once()->andReturn($auth);
        Mockery::mock('alias:App\Models\UserAuth')
            ->shouldReceive('where')->once()->with('user_id', 901201)->andReturn($authQuery);

        $active = Mockery::mock();
        $active->id = 55;
        $activeQuery = Mockery::mock();
        $activeQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $activeQuery->shouldReceive('first')->once()->andReturn($active);
        Mockery::mock('alias:App\Models\AdminAuthReviewOutbox')
            ->shouldReceive('where')->once()->with('active_user_id', 901201)->andReturn($activeQuery);

        DB::shouldReceive('transaction')->once()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldNotReceive('updateComment');

        $result = (new AdminAuthReviewProcessor($mt4))->submit(
            901201,
            ['id_card_decision' => 1, 'id_card_reason' => ''],
            $this->actorContext()
        );

        $this->assertSame(['status' => 'conflict', 'outbox_id' => 55], $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_submit_observes_active_outbox_created_while_waiting_for_auth_lock(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $authLocked = false;
        $active = Mockery::mock();
        $active->id = 56;
        $activeQuery = Mockery::mock();
        $activeQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $activeQuery->shouldReceive('first')->once()->andReturnUsing(
            function () use (&$authLocked, $active) {
                return $authLocked ? $active : null;
            }
        );
        Mockery::mock('alias:App\Models\AdminAuthReviewOutbox')
            ->shouldReceive('where')->once()->with('active_user_id', 901208)->andReturn($activeQuery)
            ->shouldNotReceive('create');

        $auth = $this->authRow(1, 2);
        $auth->shouldNotReceive('update');
        $authQuery = Mockery::mock();
        $authQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $authQuery->shouldReceive('first')->once()->andReturnUsing(
            function () use (&$authLocked, $auth) {
                $authLocked = true;

                return $auth;
            }
        );
        Mockery::mock('alias:App\Models\UserAuth')
            ->shouldReceive('where')->once()->with('user_id', 901208)->andReturn($authQuery);
        Mockery::mock('alias:App\Models\UserInfo')
            ->shouldReceive('where')
            ->andThrow(new RuntimeException('unexpected local persistence after active outbox appeared'));
        Mockery::mock('alias:App\Models\OperationLog')->shouldNotReceive('create');

        DB::shouldReceive('transaction')->once()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldNotReceive('updateComment');

        $result = (new AdminAuthReviewProcessor($mt4))->submit(
            901208,
            ['id_card_decision' => 1, 'id_card_reason' => ''],
            $this->actorContext()
        );

        $this->assertSame(['status' => 'conflict', 'outbox_id' => 56], $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_local_review_limits_audit_content_without_truncating_valid_reasons(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $idReason = str_repeat('i', 500);
        $bankReason = str_repeat('b', 500);
        $auth = $this->authRow(1, 1);
        $auth->shouldReceive('update')->once()->with([
            'id_card_status' => 4,
            'id_card_remarks' => $idReason,
            'bank_status' => 4,
            'bank_remarks' => $bankReason,
        ])->andReturnTrue();
        $authQuery = Mockery::mock();
        $authQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $authQuery->shouldReceive('first')->once()->andReturn($auth);
        Mockery::mock('alias:App\Models\UserAuth')
            ->shouldReceive('where')->once()->with('user_id', 901212)->andReturn($authQuery);

        $activeQuery = Mockery::mock();
        $activeQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $activeQuery->shouldReceive('first')->once()->andReturnNull();
        Mockery::mock('alias:App\Models\AdminAuthReviewOutbox')
            ->shouldReceive('where')->once()->with('active_user_id', 901212)->andReturn($activeQuery)
            ->shouldNotReceive('create');

        $userInfo = Mockery::mock();
        $userInfo->shouldReceive('update')->once()->with(['auth_status' => 2])->andReturnTrue();
        $userInfoQuery = Mockery::mock();
        $userInfoQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $userInfoQuery->shouldReceive('first')->once()->andReturn($userInfo);
        Mockery::mock('alias:App\Models\UserInfo')
            ->shouldReceive('where')->once()->with('user_id', 901212)->andReturn($userInfoQuery);

        $loggedContent = null;
        Mockery::mock('alias:App\Models\OperationLog')
            ->shouldReceive('create')->once()->andReturnUsing(function (array $log) use (&$loggedContent) {
                $loggedContent = $log['content'];

                return true;
            });
        DB::shouldReceive('transaction')->once()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldNotReceive('updateComment');
        $result = (new AdminAuthReviewProcessor($mt4))->submit(901212, [
            'id_card_decision' => 2,
            'id_card_reason' => $idReason,
            'bank_decision' => 2,
            'bank_reason' => $bankReason,
        ], $this->actorContext());

        $this->assertSame(['status' => 'processed'], $result);
        $this->assertIsString($loggedContent);
        $this->assertLessThanOrEqual(1000, mb_strlen($loggedContent));
    }

    public function test_unique_active_user_race_is_mapped_to_a_review_conflict(): void
    {
        $previous = new PDOException(
            "Duplicate entry '901206' for key 'admin_auth_review_outboxes_active_user_unique'"
        );
        $previous->errorInfo = [
            '23000',
            1062,
            "Duplicate entry '901206' for key 'admin_auth_review_outboxes_active_user_unique'",
        ];
        $exception = new QueryException(
            'insert into admin_auth_review_outboxes',
            [],
            $previous
        );
        DB::shouldReceive('transaction')->once()->andThrow($exception);

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldNotReceive('updateComment');
        $result = (new AdminAuthReviewProcessor($mt4))->submit(
            901206,
            ['bank_decision' => 1, 'bank_reason' => ''],
            $this->actorContext()
        );

        $this->assertSame(['status' => 'conflict'], $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_claim_locks_auth_before_reloading_outbox_for_update(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $userId = 901209;
        $auth = $this->authRow(2, 3);
        $secured = AdminAuthReviewPayload::encrypt([
            'user_id' => $userId,
            'decisions' => ['bank_decision' => 1, 'bank_reason' => ''],
        ]);
        $outbox = Mockery::mock();
        $outbox->id = 82;
        $outbox->user_id = $userId;
        $outbox->status = 'pending';
        $outbox->attempts = 0;
        $outbox->available_at = null;
        $outbox->locked_at = null;
        $outbox->processed_at = null;
        $outbox->payload_ciphertext = $secured['ciphertext'];
        $outbox->payload_hash = $secured['hash'];
        $outbox->auth_snapshot_hash = AdminAuthReviewPayload::snapshotHash($this->authSnapshot($auth));
        $outbox->last_error_code = null;
        $outbox->shouldReceive('saveOrFail')->once()->andReturnTrue();

        $identityQuery = Mockery::mock();
        $identityQuery->shouldReceive('first')->once()->andReturn($outbox);
        $identityQuery->shouldReceive('lockForUpdate')->andThrow(
            new RuntimeException('outbox locked before auth row')
        );

        $authLocked = false;
        $authQuery = Mockery::mock();
        $authQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $authQuery->shouldReceive('first')->once()->andReturnUsing(
            function () use (&$authLocked, $auth) {
                $authLocked = true;

                return $auth;
            }
        );
        Mockery::mock('alias:App\Models\UserAuth')
            ->shouldReceive('where')->once()->with('user_id', $userId)->andReturn($authQuery);

        $lockedQuery = Mockery::mock();
        $lockedQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $lockedQuery->shouldReceive('first')->once()->andReturnUsing(
            function () use (&$authLocked, $outbox) {
                if (!$authLocked) {
                    throw new RuntimeException('outbox locked before auth row');
                }

                return $outbox;
            }
        );
        Mockery::mock('alias:App\Models\AdminAuthReviewOutbox')
            ->shouldReceive('whereKey')->twice()->with(82)->andReturn($identityQuery, $lockedQuery);

        DB::shouldReceive('transaction')->once()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $processor = new AdminAuthReviewProcessor(Mockery::mock(Mt4ManagerService::class));
        $method = new ReflectionMethod($processor, 'claim');
        $method->setAccessible(true);
        $result = $method->invoke($processor, 82);

        $this->assertSame('claimed', $result['status']);
        $this->assertSame($userId, $result['user_id']);
        $this->assertSame(1, $result['attempt']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_finalize_locks_auth_before_reloading_outbox_for_update(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $userId = 901210;
        $auth = $this->authRow(2, 3);
        $secured = AdminAuthReviewPayload::encrypt([
            'user_id' => $userId,
            'decisions' => ['bank_decision' => 1, 'bank_reason' => ''],
        ]);
        $outbox = Mockery::mock();
        $outbox->id = 83;
        $outbox->user_id = $userId;
        $outbox->admin_id = 1;
        $outbox->admin_name = 'review-admin';
        $outbox->request_ip = '127.0.0.1';
        $outbox->status = 'processing';
        $outbox->attempts = 1;
        $outbox->payload_ciphertext = $secured['ciphertext'];
        $outbox->payload_hash = $secured['hash'];
        $outbox->auth_snapshot_hash = AdminAuthReviewPayload::snapshotHash($this->authSnapshot($auth));
        $outbox->shouldReceive('saveOrFail')->once()->andReturnTrue();

        $identityQuery = Mockery::mock();
        $identityQuery->shouldReceive('first')->once()->andReturn($outbox);
        $identityQuery->shouldReceive('lockForUpdate')->andThrow(
            new RuntimeException('outbox locked before auth row')
        );

        $authLocked = false;
        $auth->shouldReceive('update')->once()->andReturnTrue();
        $authQuery = Mockery::mock();
        $authQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $authQuery->shouldReceive('first')->once()->andReturnUsing(
            function () use (&$authLocked, $auth) {
                $authLocked = true;

                return $auth;
            }
        );
        Mockery::mock('alias:App\Models\UserAuth')
            ->shouldReceive('where')->once()->with('user_id', $userId)->andReturn($authQuery);

        $lockedQuery = Mockery::mock();
        $lockedQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $lockedQuery->shouldReceive('first')->once()->andReturnUsing(
            function () use (&$authLocked, $outbox) {
                if (!$authLocked) {
                    throw new RuntimeException('outbox locked before auth row');
                }

                return $outbox;
            }
        );
        Mockery::mock('alias:App\Models\AdminAuthReviewOutbox')
            ->shouldReceive('whereKey')->twice()->with(83)->andReturn($identityQuery, $lockedQuery);

        $userInfo = Mockery::mock();
        $userInfo->shouldReceive('update')->once()->with(['auth_status' => 1])->andReturnTrue();
        $userInfoQuery = Mockery::mock();
        $userInfoQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $userInfoQuery->shouldReceive('first')->once()->andReturn($userInfo);
        Mockery::mock('alias:App\Models\UserInfo')
            ->shouldReceive('where')->once()->with('user_id', $userId)->andReturn($userInfoQuery);
        Mockery::mock('alias:App\Models\OperationLog')
            ->shouldReceive('create')->once()->andReturnTrue();

        DB::shouldReceive('transaction')->once()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $processor = new AdminAuthReviewProcessor(Mockery::mock(Mt4ManagerService::class));
        $method = new ReflectionMethod($processor, 'finalizeProcessed');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($processor, 83, 1));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_connection_failure_marks_claim_retryable_and_retains_encrypted_intent(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        [$outbox, $auth] = $this->mockProcessClaim(901202, 77);
        $outbox->shouldReceive('saveOrFail')->twice()->andReturnTrue();

        DB::shouldReceive('transaction')->twice()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldReceive('updateComment')->once()
            ->with(901202, 'NEW-BANK-NO|New Bank|审核通过')
            ->andReturn(['status' => 'error', 'error_code' => 'connection_failed']);

        $result = (new AdminAuthReviewProcessor($mt4))->process(77);

        $this->assertSame('retryable', $result['status']);
        $this->assertSame('connection_failed', $result['error_code']);
        $this->assertSame('retryable', $outbox->status);
        $this->assertSame(901202, $outbox->active_user_id);
        $this->assertNotNull($outbox->payload_ciphertext);
        $this->assertNotNull($outbox->available_at);
        $this->assertSame(1, $outbox->attempts);
        $this->assertSame(2, $auth->id_card_status);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_invalid_mt4_comment_is_rejected_as_definitely_not_sent(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        [$outbox] = $this->mockProcessClaim(901211, 84, 3, 2, 1, null, 'A&B Bank');
        $outbox->shouldReceive('saveOrFail')->twice()->andReturnTrue();

        DB::shouldReceive('transaction')->twice()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $calledUserId = null;
        $calledComment = null;
        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldReceive('updateComment')->once()
            ->andReturnUsing(function ($userId, $comment) use (&$calledUserId, &$calledComment): void {
                $calledUserId = $userId;
                $calledComment = $comment;
                throw new InvalidArgumentException('MT4 parameter contains a protocol delimiter.');
            });

        $result = (new AdminAuthReviewProcessor($mt4))->process(84);

        $this->assertSame(901211, $calledUserId);
        $this->assertStringContainsString('A&B Bank', (string) $calledComment);
        $this->assertSame([
            'status' => 'rejected',
            'outbox_id' => 84,
            'error_code' => 'invalid_mt4_comment',
        ], $result);
        $this->assertSame('rejected', $outbox->status);
        $this->assertNull($outbox->active_user_id);
        $this->assertNull($outbox->payload_ciphertext);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_confirmed_external_success_followed_by_local_failure_becomes_unknown(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        [$outbox] = $this->mockProcessClaim(901203, 78, 4, 2);
        $outbox->shouldReceive('saveOrFail')->twice()->andReturnTrue();

        $transactionCall = 0;
        DB::shouldReceive('transaction')->times(3)->andReturnUsing(
            function (callable $callback) use (&$transactionCall) {
                $transactionCall++;
                if ($transactionCall === 2) {
                    throw new RuntimeException('simulated local commit failure');
                }

                return $callback();
            }
        );

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldReceive('updateComment')->once()
            ->with(901203, 'NEW-BANK-NO|New Bank|审核通过')
            ->andReturn(['status' => 'ok']);

        $result = (new AdminAuthReviewProcessor($mt4))->process(78);

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('local_commit_after_external_success_failed', $result['error_code']);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame(901203, $outbox->active_user_id);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->payload_hash);
        $this->assertNull($outbox->auth_snapshot_hash);
        $this->assertNull($outbox->locked_at);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_confirmed_external_success_finalizes_the_locked_fresh_snapshot(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        [$outbox, $auth] = $this->mockProcessClaim(901205, 80, 4, 2, 2);
        $outbox->shouldReceive('saveOrFail')->twice()->andReturnTrue();
        $auth->shouldReceive('update')->once()->with([
            'bank_status' => 2,
            'bank_remarks' => '',
            'bank_no' => 'NEW-BANK-NO',
            'bank_no_tmp' => '',
            'bank_name' => 'New Bank',
            'bank_name_tmp' => '',
            'bank_addr' => 'New Branch',
            'bank_addr_tmp' => '',
            'bank_card_img' => 'new-front.jpg',
            'bank_card_img_tmp' => '',
            'bank_card_back_img' => 'new-back.jpg',
            'bank_card_back_img_tmp' => '',
            'is_bank_synced' => 1,
        ])->andReturnTrue();

        $userInfo = Mockery::mock();
        $userInfo->shouldReceive('update')->once()->with(['auth_status' => 1])->andReturnTrue();
        $userInfoQuery = Mockery::mock();
        $userInfoQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $userInfoQuery->shouldReceive('first')->once()->andReturn($userInfo);
        Mockery::mock('alias:App\Models\UserInfo')
            ->shouldReceive('where')->once()->with('user_id', 901205)->andReturn($userInfoQuery);
        Mockery::mock('alias:App\Models\OperationLog')
            ->shouldReceive('create')->once()->with(Mockery::on(function (array $log): bool {
                return $log['target_user_id'] === 901205
                    && strpos($log['content'], 'bank_status:3->2') !== false;
            }))->andReturnTrue();

        DB::shouldReceive('transaction')->twice()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldReceive('updateComment')->once()
            ->with(901205, 'NEW-BANK-NO|New Bank|审核通过')
            ->andReturn(['status' => 'ok']);

        $result = (new AdminAuthReviewProcessor($mt4))->process(80);

        $this->assertSame('processed', $result['status']);
        $this->assertSame(80, $result['outbox_id']);
        $this->assertSame('processed', $outbox->status);
        $this->assertNull($outbox->active_user_id);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->locked_at);
        $this->assertNotNull($outbox->processed_at);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_mt4_success_with_missing_user_info_is_quarantined_as_unknown(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        [$outbox, $auth] = $this->mockProcessClaim(901213, 85, 5, 3, 2);
        $outbox->shouldReceive('saveOrFail')->twice()->andReturnTrue();
        $auth->shouldReceive('update')->once()->andReturnTrue();

        $userInfoQuery = Mockery::mock();
        $userInfoQuery->shouldReceive('update')->andReturn(0);
        $userInfoQuery->shouldReceive('lockForUpdate')->andReturnSelf();
        $userInfoQuery->shouldReceive('first')->andReturnNull();
        Mockery::mock('alias:App\Models\UserInfo')
            ->shouldReceive('where')->once()->with('user_id', 901213)->andReturn($userInfoQuery);
        Mockery::mock('alias:App\Models\OperationLog')
            ->shouldReceive('create')->andReturnTrue();

        DB::shouldReceive('transaction')->times(3)->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldReceive('updateComment')->once()->andReturn(['status' => 'ok']);

        $result = (new AdminAuthReviewProcessor($mt4))->process(85);

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('local_commit_after_external_success_failed', $result['error_code']);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame(901213, $outbox->active_user_id);
        $this->assertNull($outbox->payload_ciphertext);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_snapshot_change_after_mt4_success_is_quarantined_as_unknown(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $changedAuth = $this->authRow(2, 3);
        $changedAuth->bank_no_tmp = 'CHANGED-AFTER-CLAIM';
        [$outbox] = $this->mockProcessClaim(901207, 81, 5, 3, 2, $changedAuth);
        $outbox->shouldReceive('saveOrFail')->twice()->andReturnTrue();
        Mockery::mock('alias:App\Models\UserInfo')->shouldNotReceive('where');
        Mockery::mock('alias:App\Models\OperationLog')->shouldNotReceive('create');

        DB::shouldReceive('transaction')->times(3)->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldReceive('updateComment')->once()
            ->with(901207, 'NEW-BANK-NO|New Bank|审核通过')
            ->andReturn(['status' => 'ok']);

        $result = (new AdminAuthReviewProcessor($mt4))->process(81);

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('local_commit_after_external_success_failed', $result['error_code']);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame(901207, $outbox->active_user_id);
        $this->assertNull($outbox->payload_ciphertext);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_stale_processing_claim_becomes_unknown_without_resending_mt4(): void
    {
        $this->assertTrue(class_exists(AdminAuthReviewProcessor::class));

        $outbox = Mockery::mock();
        $outbox->id = 79;
        $outbox->user_id = 901204;
        $outbox->active_user_id = 901204;
        $outbox->status = 'processing';
        $outbox->attempts = 1;
        $outbox->locked_at = now()->subMinutes(6);
        $outbox->processed_at = null;
        $outbox->available_at = null;
        $outbox->payload_ciphertext = 'ciphertext';
        $outbox->payload_hash = str_repeat('a', 64);
        $outbox->auth_snapshot_hash = str_repeat('b', 64);
        $outbox->last_error_code = null;
        $outbox->shouldReceive('saveOrFail')->once()->andReturnTrue();

        $query = Mockery::mock();
        $query->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $query->shouldReceive('first')->twice()->andReturn($outbox);
        Mockery::mock('alias:App\Models\AdminAuthReviewOutbox')
            ->shouldReceive('whereKey')->twice()->with(79)->andReturn($query);

        $authQuery = Mockery::mock();
        $authQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $authQuery->shouldReceive('first')->once()->andReturn($this->authRow(2, 3));
        Mockery::mock('alias:App\Models\UserAuth')
            ->shouldReceive('where')->once()->with('user_id', 901204)->andReturn($authQuery);

        DB::shouldReceive('transaction')->once()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldNotReceive('updateComment');

        $result = (new AdminAuthReviewProcessor($mt4))->process(79);

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('stale_processing_claim', $result['error_code']);
        $this->assertSame('unknown', $outbox->status);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertSame(901204, $outbox->active_user_id);
    }

    /**
     * @return array{0: object, 1: object}
     */
    private function mockProcessClaim(
        int $userId,
        int $outboxId,
        int $whereKeyCalls = 3,
        int $lockedOutboxCalls = 2,
        int $authWhereCalls = 1,
        $finalAuth = null,
        string $bankName = 'New Bank'
    ): array
    {
        $auth = $this->authRow(2, 3);
        $auth->bank_name_tmp = $bankName;
        $current = $this->authSnapshot($auth);
        $secured = AdminAuthReviewPayload::encrypt([
            'user_id' => $userId,
            'decisions' => [
                'bank_decision' => 1,
                'bank_reason' => '',
            ],
            'status_label' => 'component',
            'id_card_decision_label' => 'none',
            'bank_decision_label' => '1',
        ]);

        $outbox = Mockery::mock();
        $outbox->id = $outboxId;
        $outbox->user_id = $userId;
        $outbox->active_user_id = $userId;
        $outbox->admin_id = 1;
        $outbox->admin_name = 'review-admin';
        $outbox->request_ip = '127.0.0.1';
        $outbox->status = 'pending';
        $outbox->attempts = 0;
        $outbox->available_at = null;
        $outbox->locked_at = null;
        $outbox->processed_at = null;
        $outbox->payload_ciphertext = $secured['ciphertext'];
        $outbox->payload_hash = $secured['hash'];
        $outbox->auth_snapshot_hash = AdminAuthReviewPayload::snapshotHash($current);
        $outbox->last_error_code = null;

        $outboxQuery = Mockery::mock();
        $outboxQuery->shouldReceive('lockForUpdate')->times($lockedOutboxCalls)->andReturnSelf();
        $outboxQuery->shouldReceive('first')->times($whereKeyCalls)->andReturn($outbox);
        Mockery::mock('alias:App\Models\AdminAuthReviewOutbox')
            ->shouldReceive('whereKey')->times($whereKeyCalls)->with($outboxId)->andReturn($outboxQuery);

        $authQuery = Mockery::mock();
        $authQuery->shouldReceive('lockForUpdate')->times($authWhereCalls)->andReturnSelf();
        $authQuery->shouldReceive('first')->times($authWhereCalls)
            ->andReturn($auth, $finalAuth ?: $auth);
        Mockery::mock('alias:App\Models\UserAuth')
            ->shouldReceive('where')->times($authWhereCalls)->with('user_id', $userId)->andReturn($authQuery);

        return [$outbox, $auth];
    }

    private function authRow(int $idCardStatus, int $bankStatus)
    {
        $auth = Mockery::mock();
        $auth->id_card_status = $idCardStatus;
        $auth->id_card_remarks = '';
        $auth->bank_status = $bankStatus;
        $auth->bank_remarks = '';
        $auth->bank_no = 'OLD-BANK-NO';
        $auth->bank_no_tmp = $bankStatus === 3 ? 'NEW-BANK-NO' : '';
        $auth->bank_name = 'Old Bank';
        $auth->bank_name_tmp = $bankStatus === 3 ? 'New Bank' : '';
        $auth->bank_addr = 'Old Branch';
        $auth->bank_addr_tmp = $bankStatus === 3 ? 'New Branch' : '';
        $auth->bank_card_img = 'old-front.jpg';
        $auth->bank_card_img_tmp = $bankStatus === 3 ? 'new-front.jpg' : '';
        $auth->bank_card_back_img = 'old-back.jpg';
        $auth->bank_card_back_img_tmp = $bankStatus === 3 ? 'new-back.jpg' : '';
        $auth->is_bank_synced = $bankStatus === 2 ? 1 : 0;

        return $auth;
    }

    /**
     * @param object $auth
     * @return array<string, int|string>
     */
    private function authSnapshot($auth): array
    {
        return [
            'id_card_status' => $auth->id_card_status,
            'id_card_remarks' => (string) $auth->id_card_remarks,
            'bank_no' => (string) $auth->bank_no,
            'bank_no_tmp' => (string) $auth->bank_no_tmp,
            'bank_name' => (string) $auth->bank_name,
            'bank_name_tmp' => (string) $auth->bank_name_tmp,
            'bank_addr' => (string) $auth->bank_addr,
            'bank_addr_tmp' => (string) $auth->bank_addr_tmp,
            'bank_card_img' => (string) $auth->bank_card_img,
            'bank_card_img_tmp' => (string) $auth->bank_card_img_tmp,
            'bank_card_back_img' => (string) $auth->bank_card_back_img,
            'bank_card_back_img_tmp' => (string) $auth->bank_card_back_img_tmp,
            'bank_status' => $auth->bank_status,
            'bank_remarks' => (string) $auth->bank_remarks,
            'is_bank_synced' => (int) $auth->is_bank_synced,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function actorContext(): array
    {
        return [
            'admin_id' => 1,
            'admin_name' => 'review-admin',
            'request_ip' => '127.0.0.1',
            'status_label' => 'component',
            'id_card_decision_label' => '1',
            'bank_decision_label' => 'none',
        ];
    }
}
