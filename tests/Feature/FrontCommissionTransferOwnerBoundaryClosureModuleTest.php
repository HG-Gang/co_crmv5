<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:52
 */

/**
 * 前端佣金转账-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证转账目标代理商选项接口只返回当前代理商自己的直系子代理。
 * - 验证对他人分支子代理的转账请求返回 PERMISSION_DENIED，且余额、转账记录、Saga 状态与网关调用均无变化。
 * - 验证对自身直系子代理的合法转账成功执行并写入双向转账记录。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端佣金转账接口的归属权边界回归测试。
 *
 * 入参例子：
 * - GET /api/front/commissions/transfer-agent-options
 * - POST /api/front/commissions/transfers
 *   请求体：{ "sub_agent_id": 412460203, "amount": 12.50, "remark": "..." }
 *   合法请求另带 "password": "trade-secret" 与 Idempotency-Key 头。
 *
 * 返回值：
 * - 选项接口返回 SUCCESS 且仅含自身直系子代理；越权转账返回 PERMISSION_DENIED；
 *   合法转账返回 SUCCESS，双方余额按网关快照更新并生成两条 transfer 记录。
 *
 * 异常或失败场景：
 * - 若他人分支子代理出现在选项中、越权转账生效或合法转账未落库，测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
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

class FrontCommissionTransferOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 所有转账替身共享的调用日志。越权场景必须为空——证明 owner 边界外的请求未触发任何资金命令。
     * @var array<int, array{0:string,1:int,2?:string}>
     */
    private $transferGatewayCalls = [];

    /**
     * 验证转账目标代理商选项只返回当前代理商自己的直系子代理。
     *
     * 构造自身直系子代理、他人分支子代理与直系客户，
     * 断言选项仅包含自身直系子代理且字段结构完整。
     */
    public function test_transfer_agent_options_only_returns_current_agent_direct_sub_agents(): void
    {
        $viewerAgentId = 412460100;
        $ownDirectAgentId = 412460101;
        $otherRootAgentId = 412460102;
        $otherDirectAgentId = 412460103;
        $directCustomerId = 412460104;

        $this->deleteTransferFixtureRows([$viewerAgentId, $ownDirectAgentId, $otherRootAgentId, $otherDirectAgentId, $directCustomerId]);
        $this->insertUserInfo($viewerAgentId, 'commission-transfer-owner-viewer-agent', 1, 0, 100.00);
        $this->insertUserInfo($ownDirectAgentId, 'commission-transfer-owner-own-agent', 1, $viewerAgentId, 5.00);
        $this->insertUserInfo($otherRootAgentId, 'commission-transfer-owner-other-root', 1, 0, 0.00);
        $this->insertUserInfo($otherDirectAgentId, 'commission-transfer-owner-other-agent', 1, $otherRootAgentId, 7.00);
        $this->insertUserInfo($directCustomerId, 'commission-transfer-owner-direct-customer', 2, $viewerAgentId, 3.00);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/transfer-agent-options');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $options = $response->json('data');
        $this->assertIsArray($options);
        $this->assertCount(1, $options);
        $this->assertIsArray($options[0]);
        $this->assertSame(
            ['value', 'label', 'user_id', 'user_name', 'agent_level_name'],
            array_keys($options[0])
        );
        $this->assertSame($ownDirectAgentId, $options[0]['value']);
        $this->assertSame($ownDirectAgentId, $options[0]['user_id']);
        $this->assertSame('commission-transfer-owner-own-agent', $options[0]['user_name']);
        $this->assertIsString($options[0]['label']);
        $this->assertStringContainsString((string) $ownDirectAgentId, $options[0]['label']);
        $this->assertStringContainsString($options[0]['user_name'], $options[0]['label']);
        $this->assertIsString($options[0]['agent_level_name']);
    }

    /**
     * 验证佣金转账拒绝他人分支子代理且不产生余额或记录变化。
     *
     * 对他人分支子代理转账返回 PERMISSION_DENIED，余额、转账记录、Saga 状态与网关调用均不变；
     * 对自身直系子代理转账成功并生成两条带备注前缀的转账记录。
     */
    public function test_commission_transfer_rejects_other_branch_sub_agent_without_balance_or_records(): void
    {
        $viewerAgentId = 412460200;
        $ownDirectAgentId = 412460201;
        $otherRootAgentId = 412460202;
        $otherDirectAgentId = 412460203;

        $this->deleteTransferFixtureRows([$viewerAgentId, $ownDirectAgentId, $otherRootAgentId, $otherDirectAgentId]);
        $this->insertUserInfo($viewerAgentId, 'commission-transfer-write-viewer-agent', 1, 0, 100.00);
        $this->insertUserInfo($ownDirectAgentId, 'commission-transfer-write-own-agent', 1, $viewerAgentId, 5.00);
        $this->insertUserInfo($otherRootAgentId, 'commission-transfer-write-other-root', 1, 0, 0.00);
        $this->insertUserInfo($otherDirectAgentId, 'commission-transfer-write-other-agent', 1, $otherRootAgentId, 7.00);
        $this->bindTransferFakes([
            $viewerAgentId => '87.50',
            $ownDirectAgentId => '17.50',
        ]);
        $beforeSagaState = $this->sagaStateForUsers([$viewerAgentId, $otherDirectAgentId]);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $rejectedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/commissions/transfers', [
                'sub_agent_id' => $otherDirectAgentId,
                'amount' => 12.50,
                'remark' => 'must not transfer to other branch agent',
            ]);

        $rejectedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertBalanceEquals($viewerAgentId, 100.00);
        $this->assertBalanceEquals($otherDirectAgentId, 7.00);
        $this->assertTransferRecordCount([$viewerAgentId, $otherDirectAgentId], 0);
        $this->assertSame($beforeSagaState, $this->sagaStateForUsers([$viewerAgentId, $otherDirectAgentId]));
        $this->assertSame([], $this->transferGatewayCalls);

        $acceptedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/commissions/transfers', [
                'sub_agent_id' => $ownDirectAgentId,
                'amount' => 12.50,
                'password' => 'trade-secret',
                'remark' => 'owner boundary direct transfer',
            ], ['Idempotency-Key' => 'owner-boundary-key']);

        $acceptedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertBalanceEquals($viewerAgentId, 87.50);
        $this->assertBalanceEquals($ownDirectAgentId, 17.50);
        $this->assertNotEmpty($this->transferGatewayCalls);

        $records = DB::table('commission_records')
            ->where('data_type', 'transfer')
            ->where(function ($query) use ($viewerAgentId, $ownDirectAgentId) {
                $query->whereIn('agent_id', [$viewerAgentId, $ownDirectAgentId])
                    ->orWhereIn('parent_id', [$viewerAgentId, $ownDirectAgentId]);
            })
            ->orderBy('commission_amount', 'desc')
            ->get();

        $this->assertCount(2, $records);
        $this->assertSame($ownDirectAgentId, (int) $records[0]->agent_id);
        $this->assertSame($viewerAgentId, (int) $records[0]->parent_id);
        $this->assertSame(12.50, (float) $records[0]->commission_amount);
        $this->assertStringStartsWith('DBCT-' . $viewerAgentId . '-#', (string) $records[0]->remarks);
        $this->assertStringContainsString('owner boundary direct transfer', (string) $records[0]->remarks);
        $this->assertSame($viewerAgentId, (int) $records[1]->agent_id);
        $this->assertSame($ownDirectAgentId, (int) $records[1]->parent_id);
        $this->assertSame(-12.50, (float) $records[1]->commission_amount);
        $this->assertStringStartsWith('WBCT-' . $ownDirectAgentId . '-#', (string) $records[1]->remarks);
        $this->assertStringContainsString('owner boundary direct transfer', (string) $records[1]->remarks);
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 246 项、CommissionController 相关方法与接口路径、sub_agent_id 及本测试类名。
     */
    public function test_final_checklist_records_commission_transfer_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 246.', $checklist);
        $this->assertStringContainsString('CommissionController::transferAgentOptions', $checklist);
        $this->assertStringContainsString('CommissionController::transfer', $checklist);
        $this->assertStringContainsString('/api/front/commissions/transfer-agent-options', $checklist);
        $this->assertStringContainsString('/api/front/commissions/transfers', $checklist);
        $this->assertStringContainsString('sub_agent_id', $checklist);
        $this->assertStringContainsString('FrontCommissionTransferOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带余额的测试用户数据，代理商默认级别 1、佣金比例 0.2 且已确认。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param int $accountType 账号类型（1=代理商，2=客户）。
     * @param int $parentId 上级用户 ID（0 表示无上级）。
     * @param float $totalFunds 账户总资金。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, float $totalFunds): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-commission-transfer-owner-' . $userId . '@example.test',
            'password' => Hash::make('password'),
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
            'phone' => '1782460' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
            'total_funds' => $totalFunds,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => $totalFunds,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户的转账单、outbox、层级关系、佣金记录及用户信息测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @return void 无返回值。
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

        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }

    /**
     * 断言指定用户的 total_funds 等于期望余额。
     *
     * @param int $userId 用户 ID。
     * @param float $expectedBalance 期望余额。
     * @return void 断言失败时抛出 AssertionFailedError。
     */
    private function assertBalanceEquals(int $userId, float $expectedBalance): void
    {
        $this->assertSame($expectedBalance, (float) DB::table('user_infos')->where('user_id', $userId)->value('total_funds'));
    }

    /**
     * 断言指定用户的 transfer 类型佣金记录数量。
     *
     * @param array<int, int> $userIds 用户 ID 列表。
     * @param int $expectedCount 期望的记录数量。
     * @return void 断言失败时抛出 AssertionFailedError。
     */
    private function assertTransferRecordCount(array $userIds, int $expectedCount): void
    {
        $this->assertSame(
            $expectedCount,
            DB::table('commission_records')
                ->where('data_type', 'transfer')
                ->where(function ($query) use ($userIds) {
                    $query->whereIn('agent_id', $userIds)
                        ->orWhereIn('parent_id', $userIds);
                })
                ->count()
        );
    }

    /**
     * 收集指定用户的转账单与 outbox 当前状态快照。
     *
     * @param array<int, int> $userIds 用户 ID 列表。
     * @return array<string, array<int, array<string, mixed>>> 形如
     *         { "transfers": [...], "outbox": [...] } 的状态快照。
     */
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

    /**
     * 绑定交易密码、资金网关与账户快照网关的测试替身。
     *
     * @param array<int, string> $balances 用户 ID 到账户余额字符串的映射（快照网关返回）。
     * @return void 无返回值，仅替换容器中的网关实例。
     */
    private function bindTransferFakes(array $balances): void
    {
        $calls = &$this->transferGatewayCalls;
        $this->app->instance(TradePasswordGateway::class, new class($calls) implements TradePasswordGateway {
            /**
             * 资金密码替身的调用捕获表（引用共享 $this->transferGatewayCalls）。记录 ['password', userId]。
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
             * 资金网关替身的调用捕获表。记录 ['withdraw'|'deposit'|'compensate', userId, amount]。
             * @var array<int, array{0:string,1:int,2:string}>
             */
            private $calls;
            public function __construct(array &$calls) { $this->calls =& $calls; }
            public function withdraw(int $userId, string $amount, string $comment): CommissionTransferCommandResult
            {
                $this->calls[] = ['withdraw', $userId, $amount];
                return CommissionTransferCommandResult::processed('700001');
            }
            public function deposit(int $userId, string $amount, string $comment): CommissionTransferCommandResult
            {
                $this->calls[] = ['deposit', $userId, $amount];
                return CommissionTransferCommandResult::processed('700002');
            }
            public function compensate(int $userId, string $amount, string $comment): CommissionTransferCommandResult
            {
                $this->calls[] = ['compensate', $userId, $amount];
                return CommissionTransferCommandResult::processed('700003');
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
