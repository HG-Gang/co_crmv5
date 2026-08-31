<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/12
 * Time: 14:59
 */

/**
 * AdminLegacyCustApplyApprovalClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台客户转组审批闭环：仅待处理申请可通过并原子更新本地分组、操作日志插入失败整体回滚、MT4 非显式成功时失败关闭、驳回必须理由且越权目标拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use App\Services\Mt4ManagerService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Closure tests for the legacy CustomerController group-change approval routes.
 *
 * These routes approve/reject trans_apply_logs applications. They are not the
 * agent-confirmation workflow and must never mutate user_infos.is_agent_confirmed.
 */
class AdminLegacyCustApplyApprovalClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pass_requires_pending_customer_application_and_atomically_updates_local_group(): void
    {
        $admin = $this->ensureAdmin();
        $userId = 987201;
        $this->seedCustomerWithPendingApplication($userId, 'Legacy pending customer', 2);
        $calls = [];
        $this->bindMt4(function (int $id, string $group) use (&$calls): array {
            $calls[] = [$id, $group];

            return ['status' => 'ok', 'err' => 0];
        });

        $response = $this->legacyPost('/index/admin/cust/cust_apply_pass', ['uid' => $userId]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);
        $this->assertSame([[$userId, 'target-group']], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => 702,
            'mt4_group' => 'target-group',
            'is_agent_confirmed' => 7,
        ]);
        $this->assertDatabaseHas('trans_apply_logs', [
            'user_id' => $userId,
            'status' => 1,
            'reject_reason' => '',
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'target_user_id' => $userId,
            'order_no' => 'legacy_customer_group_approval:' . $userId,
        ]);
    }

    public function test_pass_rolls_back_all_local_writes_when_operation_log_insert_fails(): void
    {
        $this->ensureAdmin();
        $userId = 987219;
        $this->seedCustomerWithPendingApplication($userId, 'Legacy atomic customer', 2);
        $calls = 0;
        $this->bindMt4(function () use (&$calls): array {
            $calls++;

            return ['status' => 'ok', 'err' => '0'];
        });
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $testDispatcher = new Dispatcher($this->app);
        $testDispatcher->listen(QueryExecuted::class, function (QueryExecuted $query): void {
            if (stripos($query->sql, 'insert into `operation_logs`') !== false) {
                throw new \RuntimeException('controlled operation log insert failure');
            }
        });
        $connection->setEventDispatcher($testDispatcher);

        try {
            $response = $this->legacyPost('/index/admin/cust/cust_apply_pass', ['uid' => $userId]);
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
        }

        $response->assertOk()->assertJsonPath('code', ResponseCode::SERVER_ERROR);
        $this->assertSame(1, $calls);
        $this->assertSame($originalDispatcher, $connection->getEventDispatcher());
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'is_agent_confirmed' => 7,
        ]);
        $this->assertDatabaseHas('trans_apply_logs', ['user_id' => $userId, 'status' => 0]);
        $this->assertDatabaseMissing('operation_logs', [
            'target_user_id' => $userId,
            'order_no' => 'legacy_customer_group_approval:' . $userId,
        ]);
    }

    /**
     * @dataProvider mt4FailureProvider
     */
    public function test_pass_fails_closed_without_local_writes_when_mt4_is_not_explicitly_successful(string $mode): void
    {
        $admin = $this->ensureAdmin();
        $userId = 987202 + array_search($mode, array_keys($this->mt4FailureProvider()), true);
        $this->seedCustomerWithPendingApplication($userId, 'Legacy MT4 failure customer', 2);
        $this->bindMt4(function () use ($mode): array {
            if ($mode === 'exception') {
                throw new \RuntimeException('controlled MT4 failure');
            }
            if ($mode === 'empty') {
                return [];
            }
            if ($mode === 'missing_err') {
                return ['status' => 'ok'];
            }
            if ($mode === 'nonzero_err') {
                return ['status' => 'ok', 'err' => '1003'];
            }

            return ['status' => 'error', 'err' => '1003'];
        });

        $response = $this->legacyPost('/index/admin/cust/cust_apply_pass', ['uid' => $userId]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'is_agent_confirmed' => 7,
        ]);
        $this->assertDatabaseHas('trans_apply_logs', ['user_id' => $userId, 'status' => 0]);
        $this->assertDatabaseMissing('operation_logs', [
            'target_user_id' => $userId,
            'order_no' => 'legacy_customer_group_approval:' . $userId,
        ]);
    }

    public function mt4FailureProvider(): array
    {
        return [
            'error' => ['error'],
            'exception' => ['exception'],
            'empty' => ['empty'],
            'missing_err' => ['missing_err'],
            'nonzero_err' => ['nonzero_err'],
        ];
    }

    public function test_nopass_requires_reason_and_rejects_without_mt4_or_group_mutation(): void
    {
        $admin = $this->ensureAdmin();
        $userId = 987210;
        $this->seedCustomerWithPendingApplication($userId, 'Legacy reject customer', 2);
        $calls = 0;
        $this->bindMt4(function () use (&$calls): array {
            $calls++;

            return ['status' => 'ok', 'err' => '0'];
        });

        $missingReason = $this->legacyPost('/index/admin/cust/cust_apply_nopass', ['uid' => $userId]);
        $missingReason->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertSame(0, $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'is_agent_confirmed' => 7,
        ]);
        $this->assertDatabaseHas('trans_apply_logs', [
            'user_id' => $userId,
            'status' => 0,
            'reject_reason' => '',
        ]);
        $this->assertDatabaseMissing('operation_logs', ['target_user_id' => $userId]);

        $response = $this->legacyPost('/index/admin/cust/cust_apply_nopass', [
            'uid' => $userId,
            'trans_apply_reason' => 'documents incomplete',
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);
        $this->assertSame(0, $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'is_agent_confirmed' => 7,
        ]);
        $this->assertDatabaseHas('trans_apply_logs', [
            'user_id' => $userId,
            'status' => -1,
            'reject_reason' => 'documents incomplete',
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'target_user_id' => $userId,
            'order_no' => 'legacy_customer_group_rejection:' . $userId,
        ]);
    }

    public function test_pass_rejects_agent_target_and_repeated_or_missing_application_without_writes(): void
    {
        $admin = $this->ensureAdmin();
        $agentId = 987220;
        $this->seedCustomerWithPendingApplication($agentId, 'Legacy agent target', 1);
        $calls = 0;
        $this->bindMt4(function () use (&$calls): array {
            $calls++;

            return ['status' => 'ok', 'err' => '0'];
        });

        $agentResponse = $this->legacyPost('/index/admin/cust/cust_apply_pass', ['uid' => $agentId]);
        $agentResponse->assertOk()->assertJsonPath('code', ResponseCode::USER_NOT_FOUND);
        $this->assertSame(0, $calls);
        $this->assertDatabaseHas('trans_apply_logs', ['user_id' => $agentId, 'status' => 0]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $agentId,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'is_agent_confirmed' => 7,
        ]);
        $this->assertDatabaseMissing('operation_logs', ['target_user_id' => $agentId]);

        $customerId = 987221;
        $this->seedCustomerWithPendingApplication($customerId, 'Legacy repeated customer', 2);
        DB::table('trans_apply_logs')->where('user_id', $customerId)->update(['status' => 1]);
        $repeated = $this->legacyPost('/index/admin/cust/cust_apply_pass', ['uid' => $customerId]);
        $repeated->assertOk()->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
        $this->assertSame(0, $calls);
        $this->assertDatabaseHas('trans_apply_logs', [
            'user_id' => $customerId,
            'status' => 1,
            'reject_reason' => '',
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $customerId,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'is_agent_confirmed' => 7,
        ]);
        $this->assertDatabaseMissing('operation_logs', ['target_user_id' => $customerId]);

        $missing = $this->legacyPost('/index/admin/cust/cust_apply_pass', ['uid' => 987222]);
        $missing->assertOk()->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
        $this->assertSame(0, $calls);
        $this->assertDatabaseMissing('user_infos', ['user_id' => 987222]);
        $this->assertDatabaseMissing('trans_apply_logs', ['user_id' => 987222]);
        $this->assertDatabaseMissing('operation_logs', ['target_user_id' => 987222]);
    }

    public function test_pass_rejects_non_positive_uid(): void
    {
        $this->ensureAdmin();
        $calls = 0;
        $this->bindMt4(function () use (&$calls): array {
            $calls++;

            return ['status' => 'ok', 'err' => '0'];
        });

        $response = $this->legacyPost('/index/admin/cust/cust_apply_pass', ['uid' => '0']);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertSame(0, $calls);
        $this->assertDatabaseMissing('user_infos', ['user_id' => 0]);
        $this->assertDatabaseMissing('trans_apply_logs', ['user_id' => 0]);
        $this->assertDatabaseMissing('operation_logs', ['target_user_id' => 0]);
    }

    /**
     * @dataProvider invalidRejectionProvider
     */
    public function test_nopass_rejects_invalid_targets_without_mt4_or_local_writes(string $case): void
    {
        $this->ensureAdmin();
        $userId = 987230;
        $expectedCode = ResponseCode::DATA_NOT_FOUND;
        if ($case === 'agent') {
            $this->seedCustomerWithPendingApplication($userId, 'Legacy reject agent', 1);
            $expectedCode = ResponseCode::USER_NOT_FOUND;
        } elseif ($case === 'processed') {
            $this->seedCustomerWithPendingApplication($userId, 'Legacy processed reject', 2);
            DB::table('trans_apply_logs')->where('user_id', $userId)->update(['status' => 1]);
        } elseif ($case === 'invalid_uid') {
            $userId = 0;
            $expectedCode = ResponseCode::VALIDATION_FAILED;
        }
        $calls = 0;
        $this->bindMt4(function () use (&$calls): array {
            $calls++;

            return ['status' => 'ok', 'err' => '0'];
        });

        $response = $this->legacyPost('/index/admin/cust/cust_apply_nopass', [
            'uid' => $userId,
            'trans_apply_reason' => 'reject invalid target',
        ]);

        $response->assertOk()->assertJsonPath('code', $expectedCode);
        $this->assertSame(0, $calls);
        $this->assertDatabaseMissing('operation_logs', ['target_user_id' => $userId]);
        if ($case === 'processed') {
            $this->assertDatabaseHas('trans_apply_logs', [
                'user_id' => $userId,
                'status' => 1,
                'reject_reason' => '',
            ]);
        } elseif ($case === 'agent') {
            $this->assertDatabaseHas('trans_apply_logs', [
                'user_id' => $userId,
                'status' => 0,
                'reject_reason' => '',
            ]);
        } else {
            $this->assertDatabaseMissing('user_infos', ['user_id' => $userId]);
            $this->assertDatabaseMissing('trans_apply_logs', ['user_id' => $userId]);
        }
        if (in_array($case, ['agent', 'processed'], true)) {
            $this->assertDatabaseHas('user_infos', [
                'user_id' => $userId,
                'group_id' => 701,
                'mt4_group' => 'origin-group',
                'is_agent_confirmed' => 7,
            ]);
        }
    }

    /**
     * A customer approval outside the administrator's data scope must close
     * before MT4 or any local approval/rejection write is attempted.
     *
     * @dataProvider crossDataScopeProvider
     */
    public function test_customer_approval_fails_closed_when_target_is_outside_data_scope(string $action): void
    {
        $userId = $action === 'pass' ? 987240 : 987241;
        $this->ensureAdmin();
        $this->seedCustomerWithPendingApplication($userId, 'Out of scope customer', 2);

        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('canAccessUser')->once()->andReturn(false);
        $this->app->instance(AdminDataScopeService::class, $scope);

        $mt4Calls = 0;
        $this->bindMt4(function () use (&$mt4Calls): array {
            $mt4Calls++;

            return ['status' => 'ok', 'err' => 0];
        });

        $payload = ['uid' => $userId];
        if ($action === 'nopass') {
            $payload['trans_apply_reason'] = 'outside scope';
        }

        $response = $this->legacyPost(
            '/index/admin/cust/cust_apply_' . ($action === 'pass' ? 'pass' : 'nopass'),
            $payload
        );

        $response->assertOk()->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertSame(0, $mt4Calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'is_agent_confirmed' => 7,
        ]);
        $this->assertDatabaseHas('trans_apply_logs', [
            'user_id' => $userId,
            'status' => 0,
            'reject_reason' => '',
        ]);
        $this->assertDatabaseMissing('operation_logs', ['target_user_id' => $userId]);
    }

    public function crossDataScopeProvider(): array
    {
        return [
            'pass' => ['pass'],
            'reject' => ['nopass'],
        ];
    }

    public function invalidRejectionProvider(): array
    {
        return [
            'agent target' => ['agent'],
            'already processed application' => ['processed'],
            'missing application' => ['missing'],
            'non-positive uid' => ['invalid_uid'],
        ];
    }

    private function legacyPost(string $uri, array $payload)
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($this->ensureAdmin(), 'admin')->postJson($uri, $payload);
    }

    private function ensureAdmin(): Admin
    {
        return Admin::query()->findOrFail(1);
    }

    private function bindMt4(callable $callback): void
    {
        $mock = Mockery::mock(Mt4ManagerService::class);
        $mock->shouldReceive('changeGroup')->zeroOrMoreTimes()->andReturnUsing($callback);
        $this->app->instance(Mt4ManagerService::class, $mock);
    }

    private function seedCustomerWithPendingApplication(int $userId, string $name, int $accountType): void
    {
        $now = time();
        DB::table('operation_logs')->where('target_user_id', $userId)->delete();
        DB::table('trans_apply_logs')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-cust-apply-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $name,
            'phone' => '178000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'level_id' => 0,
            'auth_status' => 1,
            'is_agent_confirmed' => 7,
            'remark' => 'unchanged',
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('trans_apply_logs')->insert([
            'user_id' => $userId,
            'origin_group_id' => 701,
            'group_id' => 702,
            'group_name' => 'target-group',
            'applicant_id' => $userId,
            'applicant_name' => $name,
            'status' => 0,
            'apply_reason' => 'legacy group change',
            'reject_reason' => '',
            'created_by' => 'legacy-test',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
