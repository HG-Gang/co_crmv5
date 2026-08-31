<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/19
 * Time: 15:26
 */

/**
 * AdminWhsExpZeroModuleTest
 *
 * 文件功能：
 * - 验证仓位清零模块基于当前表结构：候选名单跳过不符合条件用户、清零动作在当前表创建记录、记录列表以兼容字段读取当前表。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Constants\ResponseCode;
use App\Contracts\DepositSettlementGateway;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台仓位清零旧模块闭环测试。
 *
 * 业务说明：
 * - 旧项目仓位清零记录迁移到当前真实表 whs_exp_zeros。
 * - 当前用户信用字段为 user_infos.effective_credit，不能继续使用旧字段 credit_amount。
 * - 前端页面仍读取 balance_before、credit_amount、zero_amount 等兼容字段，控制器负责从真实字段映射。
 */
class AdminWhsExpZeroModuleTest extends TestCase
{
    /**
     * 仓位清零候选列表必须使用真实字段，并排除已有持仓或待处理清零记录的用户。
     *
     * @return void
     */
    public function test_whs_exp_zero_candidates_use_current_schema_and_skip_ineligible_users(): void
    {
        $now = time();
        $candidateUserId = 983401;
        $openPositionUserId = 983402;
        $pendingRecordUserId = 983403;

        $this->resetWhsUsers([$candidateUserId, $openPositionUserId, $pendingRecordUserId]);
        $this->seedUserInfo($candidateUserId, 'WHS Zero Candidate User', -120.50, 15.25, $now);
        $this->seedUserInfo($openPositionUserId, 'WHS Zero Open Position User', -90.00, 0, $now);
        $this->seedUserInfo($pendingRecordUserId, 'WHS Zero Pending User', -80.00, 0, $now);

        DB::table('user_trades')->insert([
            'user_id' => $openPositionUserId,
            'ticket' => 98340201,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-07-06 10:00:00',
            'open_price' => 2300,
            'close_time' => '1970-01-01 00:00:00',
            'modify_time' => '2026-07-06 10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('whs_exp_zeros')->insert([
            'user_id' => $pendingRecordUserId,
            'user_name' => 'WHS Zero Pending User',
            'balance' => -80.00,
            'credit' => 0,
            'status' => 1,
            'md5_key' => 'pending-zero-user',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->post('/api/admin/whsExpZeroList', ['limit' => 20, 'user_name' => 'WHS Zero']);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.data.0.userId', $candidateUserId)
            ->assertJsonPath('data.data.0.userCredit', '15.25')
            ->assertJsonPath('data.data.0.needZeroAmount', '120.50');
    }

    /**
     * 一键清零必须写入 whs_exp_zeros，并在 MT4 成功后标记已清零、保留前端兼容响应字段。
     *
     * @return void
     */
    public function test_whs_exp_zero_action_creates_record_in_current_table(): void
    {
        $now = time();
        $userId = 983411;

        $this->resetWhsUsers([$userId]);
        $this->seedUserInfo($userId, 'Create Zero User', -66.60, 11.10, $now);

        $this->app->instance(DepositSettlementGateway::class, new class implements DepositSettlementGateway {
            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                return DepositSettlementResult::settled('6611001');
            }
        });

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->post('/api/admin/whsExpZero', ['user_id' => $userId]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.balance_before', '-66.60')
            ->assertJsonPath('data.credit_amount', '11.10')
            ->assertJsonPath('data.zero_amount', '66.60')
            ->assertJsonPath('data.status', 2);

        $this->assertDatabaseHas('whs_exp_zeros', [
            'user_id' => $userId,
            'user_name' => 'Create Zero User',
            'balance' => -66.60,
            'credit' => 11.10,
            'status' => 2,
        ]);
    }

    /**
     * 清零记录列表必须从 whs_exp_zeros 输出前端兼容字段。
     *
     * @return void
     */
    public function test_whs_exp_zero_records_list_reads_current_table_with_compatible_fields(): void
    {
        $now = time();
        $userId = 983421;

        $this->resetWhsUsers([$userId]);
        $this->seedUserInfo($userId, 'Listed Zero User', -45.00, 5.00, $now);

        DB::table('whs_exp_zeros')->insert([
            'user_id' => $userId,
            'user_name' => 'Listed Zero User',
            'balance' => -45.00,
            'credit' => 5.00,
            'status' => 2,
            'md5_key' => 'listed-zero-user',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now + 60,
            'deleted_at' => null,
        ]);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->post('/api/admin/whsExpZeroRecords', ['user_id' => $userId]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.data.0.user_id', $userId)
            ->assertJsonPath('data.data.0.balance_before', '-45.00')
            ->assertJsonPath('data.data.0.credit_amount', '5.00')
            ->assertJsonPath('data.data.0.zero_amount', '45.00')
            ->assertJsonPath('data.data.0.status_name', '已清零')
            ->assertJsonPath('data.data.0.fail_reason', '')
            ->assertJsonPath('data.data.0.processed_at', date('Y-m-d H:i:s', $now + 60));
    }

    /**
     * 清理本测试固定业务用户，避免无事务测试之间互相污染。
     *
     * @param array<int, int> $userIds
     * @return void
     */
    private function resetWhsUsers(array $userIds): void
    {
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('whs_exp_zeros')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
    }

    /**
     * 写入仓位清零测试所需的最小用户资料。
     *
     * @param int $userId 业务用户 ID。
     * @param string $userName 用户名称。
     * @param float $totalFunds 当前余额。
     * @param float $effectiveCredit 当前有效信用额度。
     * @param int $now 当前 Unix 时间戳。
     * @return void
     */
    private function seedUserInfo(int $userId, string $userName, float $totalFunds, float $effectiveCredit, int $now): void
    {
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => $userName,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'total_funds' => $totalFunds,
            'equity' => $totalFunds,
            'effective_credit' => $effectiveCredit,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
