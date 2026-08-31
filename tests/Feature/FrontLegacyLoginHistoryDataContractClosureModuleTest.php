<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:48
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧前台客户登录历史数据契约闭环测试。
 *
 * 文件功能：
 * - 对齐旧项目 `system_login_log` 的最近四周、有效记录和按时间倒序的读取语义。
 * - 验证新项目 `user_login_logs` 的软删除记录不会作为有效历史泄露给旧 Blade 表格。
 * - 锁定旧表格依赖的 `page + rows` 分页协议，以及 `login_id/login_id_desc/login_date` 字段含义。
 *
 * 适用场景：
 * - 代理从旧客户详情页打开直属或可见下级客户的登录历史时。
 *
 * 返回结果：
 * - 测试通过表示兼容接口返回根级 `rows/total`，且每个字段可直接供旧表格渲染。
 * - 测试失败表示时间范围、软删除、字段映射或旧分页协议至少有一项被破坏。
 */
class FrontLegacyLoginHistoryDataContractClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 旧登录历史接口必须保留四周窗口、有效记录、字段映射和 rows 分页协议。
     *
     * @return void 第二页只返回一条最新记录之后的有效历史；过期和软删除记录均不会计入 total 或 rows。
     */
    public function test_legacy_login_history_preserves_four_week_window_and_rows_pagination(): void
    {
        $agentId = 412510100;
        $customerId = 412510101;
        $this->deleteFixtureRows([$agentId, $customerId]);
        $agentLoginId = $this->insertUser($agentId, 'legacy-history-contract-agent', 1, 0);
        $customerLoginId = $this->insertUser($customerId, 'legacy-history-contract-customer', 2, $agentId);
        $now = time();

        // 三条有效日志用于验证总数、倒序和 page + rows 的第二页切片。
        $this->insertLoginLog($customerLoginId, $customerId, '203.0.113.51', 'history-current-newest', $now - 60);
        $this->insertLoginLog($customerLoginId, $customerId, '203.0.113.52', 'history-current-middle', $now - 120);
        $this->insertLoginLog($customerLoginId, $customerId, '203.0.113.53', 'history-current-oldest', $now - 180);
        // 超过四周的记录不属于旧接口默认展示窗口。
        $this->insertLoginLog($customerLoginId, $customerId, '203.0.113.54', 'history-expired', strtotime('-4 weeks 00:00:00') - 1);
        // 新库以 deleted_at 取代旧库 voided，软删除记录不得进入有效历史。
        $this->insertLoginLog($customerLoginId, $customerId, '203.0.113.55', 'history-deleted', $now - 30, $now - 10);

        $agentLogin = UserLogin::findOrFail($agentLoginId);
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($agentLogin, 'user')
            ->postJson('/user/cust/loginHistorySearch/' . $customerId, [
                'page' => 2,
                'rows' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(1, 'rows')
            // 旧 Eloquent 表格响应按 MySQL 原始属性序列化，业务用户号保持字符串展示契约。
            ->assertJsonPath('rows.0.login_id', (string) $customerId)
            ->assertJsonPath('rows.0.login_id_desc', 'history-current-middle')
            ->assertJsonPath('rows.0.login_ip', '203.0.113.52')
            ->assertJsonPath('rows.0.login_date', date('Y-m-d H:i:s', $now - 120));

        $content = $response->getContent();
        $this->assertStringNotContainsString('history-expired', $content);
        $this->assertStringNotContainsString('history-deleted', $content);
        $this->assertStringNotContainsString('203.0.113.55', $content);
    }

    /**
     * 写入一组可用于代理可见范围校验的登录账号与业务用户资料。
     *
     * @param int $userId 业务用户 ID；代理和客户表均以该值建立层级关系。
     * @param string $userName 测试数据名称；用于使失败输出具备可辨识性。
     * @param int $accountType 账号类型，1=代理，2=普通客户。
     * @param int $parentId 上级代理业务用户 ID；客户必须指向当前测试代理。
     * @return int 新建 `user_logins.id`，供日志表的 `login_id` 外键语义使用。
     */
    private function insertUser(int $userId, string $userName, int $accountType, int $parentId): int
    {
        $now = time();
        $loginId = (int) DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-login-history-contract-' . $userId . '@example.test',
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
            'phone' => '1782510' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
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

        return $loginId;
    }

    /**
     * 写入一条新库登录审计日志，模拟旧 `system_login_log` 的单次登录事实。
     *
     * @param int $loginId 新库认证账号主键，仅用于日志关联，不能冒充旧表格的业务用户号。
     * @param int $userId 业务用户 ID，旧接口根据该值筛选客户的登录历史。
     * @param string $loginIp 登录 IP，旧表格的 `login_ip` 列直接展示该值。
     * @param string $ipLocation IP 地理位置，对应旧表格的 `login_id_desc` 列。
     * @param int $createdAt Unix 时间戳，用于验证最近四周的边界。
     * @param int|null $deletedAt 软删除时间；非空表示该记录等价于旧库 `voided != 1`。
     * @return void 日志只在当前数据库事务内存在，测试结束后自动回滚。
     */
    private function insertLoginLog(
        int $loginId,
        int $userId,
        string $loginIp,
        string $ipLocation,
        int $createdAt,
        int $deletedAt = null
    ): void {
        DB::table('user_login_logs')->insert([
            'login_id' => $loginId,
            'user_id' => $userId,
            'login_ip' => $loginIp,
            'ip_location' => $ipLocation,
            'user_agent' => 'legacy-login-history-contract-test',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => $deletedAt,
        ]);
    }

    /**
     * 清理本测试固定用户号可能遗留的夹具，确保失败重跑和并行回归不会命中唯一键冲突。
     *
     * @param array<int, int> $userIds 本测试专用的业务用户 ID 列表。
     * @return void 仅删除 user_id 命中测试前缀的登录日志、资料和登录账号，不影响其他业务数据。
     */
    private function deleteFixtureRows(array $userIds): void
    {
        // 先删依赖日志，再删资料和认证账号，避免外键或唯一键残留影响下一次运行。
        DB::table('user_login_logs')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
