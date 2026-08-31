<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 15:48
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 前台代理直属客户转组状态边界闭环测试。
 *
 * 文件功能：
 * - 覆盖旧版 Blade 已提示、但写接口原先可被直接绕过的四类限制。
 * - 验证现代转组申请接口在拒绝时不会新增 trans_apply_logs 记录。
 * - 使用真实登录态、真实路由和真实数据库事务，避免仅验证控制器私有实现。
 *
 * 适用场景：
 * - 代理只能为直属普通客户提交转组申请。
 * - 原组相同、已有待审核申请、存在有效持仓时必须拒绝申请。
 *
 * 入参示例：
 * - target_user_id: 411910101
 * - new_group_id: 123
 * - reason: 客户申请调整交易组
 *
 * 返回值：
 * - 规则不满足时返回对应业务错误码，且申请日志数量不增加。
 */
class FrontAgentGroupChangeStateBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 间接客户不可绕过直属关系限制提交转组申请。
     *
     * @return void 间接客户请求必须返回权限拒绝，且不新增申请日志。
     */
    public function test_agent_cannot_submit_group_change_for_indirect_customer(): void
    {
        $agentId = 411910101;
        $subAgentId = $agentId + 1;
        $customerId = $agentId + 2;
        $originGroupId = $this->insertCustomerGroup('state-indirect-origin-' . $agentId);
        $targetGroupId = $this->insertCustomerGroup('state-indirect-target-' . $agentId);

        $this->prepareUsers([$agentId, $subAgentId, $customerId]);
        $this->insertUserInfo($agentId, 'state-indirect-root-agent', 1, 0, 0);
        $this->insertUserInfo($subAgentId, 'state-indirect-sub-agent', 1, $agentId, 0);
        $this->insertUserInfo($customerId, 'state-indirect-customer', 2, $subAgentId, $originGroupId);

        $response = $this->submitGroupChange($agentId, $customerId, $targetGroupId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertGroupChangeCount($agentId, $customerId, 0);
    }

    /**
     * 新目标组等于客户当前组时不可创建无效申请。
     *
     * @return void 相同组别请求必须返回操作不允许，且不新增申请日志。
     */
    public function test_agent_cannot_submit_group_change_to_customer_current_group(): void
    {
        $agentId = 411910201;
        $customerId = $agentId + 1;
        $currentGroupId = $this->insertCustomerGroup('state-same-group-' . $agentId);

        $this->prepareUsers([$agentId, $customerId]);
        $this->insertUserInfo($agentId, 'state-same-root-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'state-same-direct-customer', 2, $agentId, $currentGroupId);

        $response = $this->submitGroupChange($agentId, $customerId, $currentGroupId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertGroupChangeCount($agentId, $customerId, 0);
    }

    /**
     * 同一客户已有待审核转组申请时不可重复提交。
     *
     * @return void 重复申请必须返回操作不允许，并保持原待审核记录数量不变。
     */
    public function test_agent_cannot_submit_group_change_when_customer_has_pending_application(): void
    {
        $agentId = 411910301;
        $otherAgentId = $agentId + 100;
        $customerId = $agentId + 1;
        $originGroupId = $this->insertCustomerGroup('state-pending-origin-' . $agentId);
        $targetGroupId = $this->insertCustomerGroup('state-pending-target-' . $agentId);

        $this->prepareUsers([$agentId, $otherAgentId, $customerId]);
        $this->insertUserInfo($agentId, 'state-pending-root-agent', 1, 0, 0);
        $this->insertUserInfo($otherAgentId, 'state-pending-other-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'state-pending-direct-customer', 2, $agentId, $originGroupId);
        $this->insertPendingApplication($customerId, $otherAgentId, $targetGroupId);

        $response = $this->submitGroupChange($agentId, $customerId, $targetGroupId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertGroupChangeCount($agentId, $customerId, 0);
        $this->assertSame(1, DB::table('trans_apply_logs')->where('user_id', $customerId)->where('status', 0)->count());
    }

    /**
     * 客户存在未平仓交易订单时不可提交转组申请。
     *
     * @return void 持仓客户请求必须返回操作不允许，且不新增申请日志。
     */
    public function test_agent_cannot_submit_group_change_when_customer_has_open_trade(): void
    {
        $agentId = 411910401;
        $customerId = $agentId + 1;
        $originGroupId = $this->insertCustomerGroup('state-open-origin-' . $agentId);
        $targetGroupId = $this->insertCustomerGroup('state-open-target-' . $agentId);

        $this->prepareUsers([$agentId, $customerId]);
        $this->insertUserInfo($agentId, 'state-open-root-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'state-open-direct-customer', 2, $agentId, $originGroupId);
        $this->insertOpenTrade($customerId, $agentId + 9000);

        $response = $this->submitGroupChange($agentId, $customerId, $targetGroupId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertGroupChangeCount($agentId, $customerId, 0);
    }

    /**
     * 使用真实用户守卫提交现代转组申请。
     *
     * @param int $agentId 当前登录代理业务用户 ID，必须已存在对应 user_logins 记录。
     * @param int $customerId 申请转组的普通客户业务用户 ID。
     * @param int $targetGroupId 目标客户组 ID，必须为已启用的 category=2 组。
     * @return \Illuminate\Testing\TestResponse 现代接口的标准 JSON 响应。
     */
    private function submitGroupChange(int $agentId, int $customerId, int $targetGroupId)
    {
        $login = UserLogin::where('user_id', $agentId)->firstOrFail();

        return $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/group-change-applications', [
                'target_user_id' => $customerId,
                'new_group_id' => $targetGroupId,
                'reason' => 'state boundary closure',
            ]);
    }

    /**
     * 创建可用于客户转组的启用组别。
     *
     * @param string $name 组别名称，测试内使用唯一前缀避免与已有数据混淆。
     * @return int 新建 group_configs 主键，作为新组或原组 ID 使用。
     */
    private function insertCustomerGroup(string $name): int
    {
        $now = time();

        return (int) DB::table('group_configs')->insertGetId([
            'pair_id' => null,
            'name' => $name,
            'radix' => 50,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 创建前台登录账号及业务用户资料。
     *
     * @param int $userId 业务用户 ID，同时写入 user_logins.user_id 与 user_infos.user_id。
     * @param string $userName 用户姓名快照，用于申请日志审计字段。
     * @param int $accountType 账号类型，1=代理商，2=普通客户。
     * @param int $parentId 直属上级代理业务用户 ID，0 表示根代理。
     * @param int $groupId 当前交易组 ID，代理没有客户组时传 0。
     * @return void 数据写入当前测试事务，测试结束后自动回滚。
     */
    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, int $groupId): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-group-state-' . $userId . '@example.test',
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
            'phone' => '1789100' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'group_id' => $groupId,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 20 : 0,
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
    }

    /**
     * 写入其它申请人已提交的待审核转组记录。
     *
     * @param int $customerId 被申请转组的客户业务用户 ID。
     * @param int $applicantId 已占用审核队列的代理业务用户 ID。
     * @param int $targetGroupId 已申请的目标客户组 ID。
     * @return void status=0 表示待审核，必须阻止任何后续转组申请。
     */
    private function insertPendingApplication(int $customerId, int $applicantId, int $targetGroupId): void
    {
        $now = time();

        DB::table('trans_apply_logs')->insert([
            'user_id' => $customerId,
            'origin_group_id' => 0,
            'group_id' => $targetGroupId,
            'group_name' => 'state-pending-existing',
            'applicant_id' => $applicantId,
            'applicant_name' => 'state-pending-other-agent',
            'status' => 0,
            'apply_reason' => 'existing pending application',
            'reject_reason' => '',
            'created_by' => 'state-pending-other-agent',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入一笔有效未平仓订单。
     *
     * @param int $customerId 订单所属客户业务用户 ID。
     * @param int $ticket MT4 订单号，测试内保持唯一。
     * @return void close_time 固定为旧 MT4 未平仓哨兵值，cmd=0 表示市价买入订单。
     */
    private function insertOpenTrade(int $customerId, int $ticket): void
    {
        $now = time();

        DB::table('user_trades')->insert([
            'user_id' => $customerId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-07-25 10:00:00',
            'open_price' => 2300.10,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => '1970-01-01 00:00:00',
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 0,
            'profit' => 0,
            'taxes' => 0,
            'comment' => 'state boundary open trade',
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => '2026-07-25 10:00:00',
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 删除可能与固定测试 ID 冲突的业务夹具。
     *
     * @param array<int, int> $userIds 本次测试创建的业务用户 ID 列表。
     * @return void 仅清理当前事务内的测试键关联数据，不影响测试结束后的真实数据。
     */
    private function prepareUsers(array $userIds): void
    {
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
        DB::table('trans_apply_logs')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->orWhereIn('applicant_id', $userIds);
            })
            ->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }

    /**
     * 断言指定代理没有为指定客户新增申请记录。
     *
     * @param int $agentId 当前请求代理业务用户 ID。
     * @param int $customerId 被申请转组的客户业务用户 ID。
     * @param int $expectedCount 预期申请记录数量，拒绝场景固定为 0。
     * @return void 断言失败时显示实际日志数量，提示出现了越权写入。
     */
    private function assertGroupChangeCount(int $agentId, int $customerId, int $expectedCount): void
    {
        $actualCount = DB::table('trans_apply_logs')
            ->where('user_id', $customerId)
            ->where('applicant_id', $agentId)
            ->count();

        $this->assertSame($expectedCount, $actualCount);
    }
}
