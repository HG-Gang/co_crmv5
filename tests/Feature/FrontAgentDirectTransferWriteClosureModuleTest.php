<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前台代理直接佣金转账写入闭环测试。
 *
 * 文件功能：
 * - 验证旧版直接转账成功后更新双方余额并写入可审计的 commission_records（DBCT/WBCT 备注、manual_reason）。
 * - 验证非直属目标被拒绝且无任何写入，网关不被调用。
 * - 验证不安全的金额格式（科学计数、超精度）被拒绝。
 * - 验证佣金划转最终器按 user_id 顺序锁定双方行并写快照余额。
 * - 验证最终清单文档已记录直接转账写入闭环。
 *
 * 适用场景：
 * - 前台代理直接佣金转账的资金写入与边界防护回归测试。
 *
 * 入参例子：
 * - POST /user/proxy/directUserCommTrans
 *   depositId: {directCustomerId}, comm_money: 12.75, password: transfer-password, remark: {remark}
 *
 * 返回值：
 * - 成功时 msg 为 SUC、code 为 0、comm_money 返回代理扣减后的余额。
 * - 拒绝时 msg 为 FAIL、errorType 为 NOTALLOW/PARAM。
 *
 * 异常或失败场景：
 * - 若拒绝路径发生写入、网关被调用或余额错误，断言失败。
 */

namespace Tests\Feature;

use App\Contracts\CommissionTransferAccountSnapshotGateway;
use App\Contracts\CommissionTransferFundingGateway;
use App\Contracts\TradePasswordGateway;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Services\CommissionTransfer\CommissionTransferAccountSnapshotResult;
use App\Services\CommissionTransfer\CommissionTransferCommandResult;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontAgentDirectTransferWriteClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 所有转账替身（资金密码、资金网关、快照）共享的调用日志。
     * 用例断言其内容与顺序；被越权/失败场景断言为空数组，证明没有发生任何资金命令。
     * @var array<int, array{0:string,1:int,2?:string}>
     */
    private $transferGatewayCalls = [];

    /**
     * 验证旧版直接转账成功时写入双方余额与可审计记录。
     */
    public function test_legacy_direct_user_commission_transfer_writes_balances_and_auditable_records(): void
    {
        $agentId = 411830100;
        $directCustomerId = $agentId + 1;
        $remark = 'legacy direct transfer write closure';

        $this->deleteTransferFixtureRows([$agentId, $directCustomerId]);
        $this->insertUserInfo($agentId, 'direct-transfer-root-agent', 1, 0, 100.25);
        $this->insertUserInfo($directCustomerId, 'direct-transfer-customer', 2, $agentId, 8.50);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $agentId)->count());

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $this->bindTransferFakes([
            $agentId => '87.50',
            $directCustomerId => '21.25',
        ]);
        $nonce = $this->issueLegacyTransferIntent($agentId, $directCustomerId);
        $response = $this
            ->withHeader('Idempotency-Key', $nonce)
            ->postJson('/user/proxy/directUserCommTrans', [
                'depositId' => $directCustomerId,
                'comm_money' => 12.75,
                'password' => 'transfer-password',
                'idempotency_key' => $nonce,
                'remark' => $remark,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('code', 0)
            ->assertJsonPath('comm_money', 87.50);

        $this->assertSame(87.50, (float) DB::table('user_infos')->where('user_id', $agentId)->value('total_funds'));
        $this->assertSame(21.25, (float) DB::table('user_infos')->where('user_id', $directCustomerId)->value('total_funds'));

        $records = DB::table('commission_records')
            ->where('data_type', 'transfer')
            ->whereIn('agent_id', [$agentId, $directCustomerId])
            ->orderBy('commission_amount', 'desc')
            ->get();

        $this->assertCount(2, $records);

        $depositRecord = $records->firstWhere('agent_id', $directCustomerId);
        $withdrawRecord = $records->firstWhere('agent_id', $agentId);

        $this->assertNotNull($depositRecord);
        $this->assertNotNull($withdrawRecord);
        $this->assertSame($agentId, (int) $depositRecord->parent_id);
        $this->assertSame($directCustomerId, (int) $withdrawRecord->parent_id);
        $this->assertSame(12.75, (float) $depositRecord->commission_amount);
        $this->assertSame(-12.75, (float) $withdrawRecord->commission_amount);
        $this->assertSame($remark, $depositRecord->manual_reason);
        $this->assertSame($remark, $withdrawRecord->manual_reason);
        $this->assertStringStartsWith('DBCT-' . $agentId . '-#', $depositRecord->remarks);
        $this->assertStringStartsWith('WBCT-' . $directCustomerId . '-#', $withdrawRecord->remarks);
        $this->assertStringContainsString($remark, $depositRecord->remarks);
        $this->assertStringContainsString($remark, $withdrawRecord->remarks);
    }

    /**
     * 验证非直属目标被拒绝且无任何写入、网关不被调用。
     */
    public function test_legacy_direct_user_commission_transfer_refuses_non_direct_target_without_writes(): void
    {
        $agentId = 411830200;
        $directAgentId = $agentId + 1;
        $indirectCustomerId = $agentId + 2;

        $this->deleteTransferFixtureRows([$agentId, $directAgentId, $indirectCustomerId]);
        $this->insertUserInfo($agentId, 'direct-transfer-deny-root', 1, 0, 50.00);
        $this->insertUserInfo($directAgentId, 'direct-transfer-deny-agent', 1, $agentId, 0.00);
        $this->insertUserInfo($indirectCustomerId, 'direct-transfer-deny-customer', 2, $directAgentId, 5.00);
        $this->bindTransferFakes([
            $agentId => '40.00',
            $indirectCustomerId => '15.00',
        ]);
        $beforeSagaState = $this->sagaStateForUsers([$agentId, $indirectCustomerId]);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/directUserCommTrans', [
                'depositId' => $indirectCustomerId,
                'comm_money' => 10.00,
                'password' => 'transfer-password',
                'remark' => 'should not write',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('errorType', 'NOTALLOW');

        $this->assertSame(50.00, (float) DB::table('user_infos')->where('user_id', $agentId)->value('total_funds'));
        $this->assertSame(5.00, (float) DB::table('user_infos')->where('user_id', $indirectCustomerId)->value('total_funds'));
        $this->assertSame(
            0,
            DB::table('commission_records')
                ->where('data_type', 'transfer')
                ->whereIn('agent_id', [$agentId, $indirectCustomerId])
                ->count()
        );
        $this->assertSame($beforeSagaState, $this->sagaStateForUsers([$agentId, $indirectCustomerId]));
        $this->assertSame([], $this->transferGatewayCalls);
    }

    /**
     * 验证不安全的金额格式（科学计数、超精度）被拒绝。
     */
    public function test_legacy_direct_user_commission_transfer_rejects_unsafe_amount_formats(): void
    {
        $agentId = 411830300;
        $directCustomerId = $agentId + 1;
        $this->deleteTransferFixtureRows([$agentId, $directCustomerId]);
        $this->insertUserInfo($agentId, 'direct-transfer-format-root', 1, 0, 50.00);
        $this->insertUserInfo($directCustomerId, 'direct-transfer-format-customer', 2, $agentId, 5.00);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        foreach (['1e2', '1.001'] as $amount) {
            $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->postJson('/user/proxy/directUserCommTrans', [
                    'depositId' => $directCustomerId,
                    'comm_money' => $amount,
                    'password' => 'transfer-password',
                ])
                ->assertOk()
                ->assertJsonPath('msg', 'FAIL')
                ->assertJsonPath('errorType', 'PARAM');
        }

        $this->assertSame(50.00, (float) DB::table('user_infos')->where('user_id', $agentId)->value('total_funds'));
        $this->assertSame(5.00, (float) DB::table('user_infos')->where('user_id', $directCustomerId)->value('total_funds'));
    }

    /**
     * 验证佣金划转最终器按 user_id 顺序锁定双方行并写快照余额。
     */
    public function test_commission_transfer_finalizer_locks_ordered_user_rows_and_writes_both_snapshot_balances(): void
    {
        $finalizer = (string) file_get_contents(
            app_path('Services/CommissionTransfer/CommissionTransferLedgerFinalizer.php')
        );

        $transaction = strpos($finalizer, 'DB::transaction(function ()');
        $loadUsers = strpos(
            $finalizer,
            "UserInfo::whereIn('user_id', [\$transfer->source_user_id, \$transfer->target_user_id])"
        );
        $sortUsers = strpos($finalizer, "->orderBy('user_id')", $loadUsers === false ? 0 : $loadUsers);
        $lockUsers = strpos($finalizer, '->lockForUpdate()', $sortUsers === false ? 0 : $sortUsers);
        $readUsers = strpos($finalizer, '->get()', $lockUsers === false ? 0 : $lockUsers);
        $sourceWrite = strpos($finalizer, '$source->total_funds = $sourceBalanceAfter', $readUsers === false ? 0 : $readUsers);
        $targetWrite = strpos($finalizer, '$target->total_funds = $targetBalanceAfter', $sourceWrite === false ? 0 : $sourceWrite);
        $sourceSave = strpos($finalizer, '$source->saveOrFail()', $targetWrite === false ? 0 : $targetWrite);
        $targetSave = strpos($finalizer, '$target->saveOrFail()', $sourceSave === false ? 0 : $sourceSave);

        foreach ([$transaction, $loadUsers, $sortUsers, $lockUsers, $readUsers, $sourceWrite, $targetWrite, $sourceSave, $targetSave] as $position) {
            $this->assertNotFalse($position);
        }

        $this->assertTrue(
            $transaction < $loadUsers
                && $loadUsers < $sortUsers
                && $sortUsers < $lockUsers
                && $lockUsers < $readUsers
                && $readUsers < $sourceWrite
                && $sourceWrite < $targetWrite
                && $targetWrite < $sourceSave
                && $sourceSave < $targetSave,
            'The finalizer must lock both user rows in user-id order before writing and saving both snapshot balances.'
        );
    }

    /**
     * 验证最终清单文档已记录旧版直接转账写入闭环（## 183）。
     */
    public function test_final_checklist_records_legacy_direct_transfer_write_closure(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 183.', $checklist);
        $this->assertStringContainsString('directUserCommTrans', $checklist);
        $this->assertStringContainsString('DBCT/WBCT', $checklist);
        $this->assertStringContainsString('manual_reason', $checklist);
        $this->assertStringContainsString('FrontAgentDirectTransferWriteClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, float $totalFunds): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-direct-transfer-write-' . $userId . '@example.test',
            'password' => Hash::make('transfer-password'),
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

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1788300' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'total_funds' => $totalFunds,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => $totalFunds,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     */
    private function deleteTransferFixtureRows(array $userIds): void
    {
        $transferIds = DB::table('commission_transfers')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('source_user_id', $userIds)
                    ->orWhereIn('target_user_id', $userIds);
            })
            ->pluck('id')
            ->all();

        if ($transferIds !== []) {
            DB::table('commission_transfer_outbox')
                ->whereIn('commission_transfer_id', $transferIds)
                ->delete();
            DB::table('commission_transfers')->whereIn('id', $transferIds)->delete();
        }

        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();

        DB::table('commission_records')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('parent_id', $userIds);
            })
            ->delete();
    }

    private function issueLegacyTransferIntent(int $agentId, int $targetUserId): string
    {
        $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class]);
        $sessionId = substr(hash('sha256', 'legacy-transfer-' . $agentId), 0, 40);
        session()->setId($sessionId);
        $this->withCookie((string) config('session.cookie'), $sessionId)
            ->withCredentials()
            ->withSession(['suser' => ['user_id' => $agentId]]);
        $page = $this->get('/user/proxy/direct_user_commTrans_browse/' . $targetUserId)->assertOk();
        preg_match('/name="idempotency_key"[^>]+value="([a-f0-9]{64})"/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        return (string) $matches[1];
    }

    /** @param array<int, int> $userIds @return array<string, array<int, array<string, mixed>>> */
    private function sagaStateForUsers(array $userIds): array
    {
        $transferQuery = DB::table('commission_transfers')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('source_user_id', $userIds)
                    ->orWhereIn('target_user_id', $userIds);
            });
        $transferIds = (clone $transferQuery)->pluck('id')->map(function ($id): int {
            return (int) $id;
        })->all();

        return [
            'transfers' => (clone $transferQuery)
                ->orderBy('id')
                ->get()
                ->map(function ($row): array { return (array) $row; })
                ->all(),
            'outbox' => $transferIds === []
                ? []
                : DB::table('commission_transfer_outbox')
                    ->whereIn('commission_transfer_id', $transferIds)
                    ->orderBy('id')
                    ->get()
                    ->map(function ($row): array { return (array) $row; })
                    ->all(),
        ];
    }

    /** @param array<int, string> $balances */
    private function bindTransferFakes(array $balances): void
    {
        $calls = &$this->transferGatewayCalls;
        $this->app->instance(TradePasswordGateway::class, new class($calls) implements TradePasswordGateway {
            /**
             * 资金密码替身的调用捕获表（引用共享 $this->transferGatewayCalls）。记录 ['password', userId]，
             * 验证转账前确实校验了资金密码。
             * @var array<int, array{0:string,1:int}>
             */
            private $calls;
            public function __construct(array &$calls) { $this->calls =& $calls; }
            public function verify(int $userId, string $password): TradePasswordVerificationResult
            {
                $this->calls[] = ['password', $userId];
                return TradePasswordVerificationResult::verified();
            }
        });
        $this->app->instance(CommissionTransferFundingGateway::class, new class($calls) implements CommissionTransferFundingGateway {
            /**
             * 资金网关替身的调用捕获表。记录 ['withdraw'|'deposit'|'compensate', userId, amount]，
             * 断言转账 saga 的命令顺序与金额。
             * @var array<int, array{0:string,1:int,2:string}>
             */
            private $calls;
            public function __construct(array &$calls) { $this->calls =& $calls; }
            public function withdraw(int $userId, string $amount, string $comment): CommissionTransferCommandResult
            {
                $this->calls[] = ['withdraw', $userId, $amount];
                return CommissionTransferCommandResult::processed('710001');
            }
            public function deposit(int $userId, string $amount, string $comment): CommissionTransferCommandResult
            {
                $this->calls[] = ['deposit', $userId, $amount];
                return CommissionTransferCommandResult::processed('710002');
            }
            public function compensate(int $userId, string $amount, string $comment): CommissionTransferCommandResult
            {
                $this->calls[] = ['compensate', $userId, $amount];
                return CommissionTransferCommandResult::processed('710003');
            }
        });
        $this->app->instance(CommissionTransferAccountSnapshotGateway::class, new class($balances, $calls) implements CommissionTransferAccountSnapshotGateway {
            /**
             * userId => 预设余额 的映射。snapshot() 按用户取值，缺失时抛出夹具缺失异常，防止用例误配。
             * @var array<int, string>
             */
            private $balances;
            /**
             * 快照替身的调用捕获表。记录 ['snapshot', userId]，断言余额读取的次数与目标。
             * @var array<int, array{0:string,1:int}>
             */
            private $calls;
            public function __construct(array $balances, array &$calls) { $this->balances = $balances; $this->calls =& $calls; }
            public function snapshot(int $userId): CommissionTransferAccountSnapshotResult
            {
                $this->calls[] = ['snapshot', $userId];
                if (!array_key_exists($userId, $this->balances)) {
                    throw new \LogicException('Missing commission transfer snapshot fixture for user ' . $userId . '.');
                }

                return CommissionTransferAccountSnapshotResult::confirmed($this->balances[$userId]);
            }
        });
    }
}
