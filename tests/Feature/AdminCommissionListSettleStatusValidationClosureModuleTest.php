<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:20
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台返佣列表结算状态筛选严格校验闭环测试。
 *
 * 文件功能：
 * - 覆盖 /api/admin/commissionList 的 settle_status 参数边界。
 * - 验证非严格枚举值不会进入 commission_records.settle_status 查询。
 * - 约束最终迁移清单记录本轮返佣列表筛选闭环，便于后续按旧项目模块继续追踪。
 */
class AdminCommissionListSettleStatusValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 测试结束后清理返佣列表状态筛选专用数据。
     *
     * @return void
     */
    protected function tearDown(): void
    {
        DB::table('commission_records')
            ->where('unique_id', 'like', 'commission-list-settle-status-validation-%')
            ->delete();

        parent::tearDown();
    }

    /**
     * 返佣列表必须拒绝非严格结算状态筛选值。
     *
     * 参数逻辑说明：
     * - settle_status 只允许 1=待结算、2=已结算。
     * - 1abc、3、-1 都不能下推到 commission_records.settle_status 查询。
     * - 响应内容不能包含测试返佣记录唯一编号，证明非法筛选在查询前已被拦截。
     *
     * @dataProvider invalidSettleStatusProvider
     * @param string $invalidStatus 非法结算状态筛选值。
     * @return void
     */
    public function test_commission_list_rejects_invalid_settle_status_filter_without_returning_records(string $invalidStatus): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createCommissionRecord('commission-list-settle-status-validation-row-' . $invalidStatus, 984301);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/commissionList', [
                'settle_status' => $invalidStatus,
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('commission-list-settle-status-validation-row-', $response->getContent());
    }

    /**
     * 非法结算状态枚举样例。
     *
     * @return array<string, array{0:string}> 返回非法 settle_status 样例集合。
     */
    public function invalidSettleStatusProvider(): array
    {
        return [
            '数字前缀字符串' => ['1abc'],
            '超出旧项目枚举' => ['3'],
            '负数状态' => ['-1'],
        ];
    }

    /**
     * 最终清单必须记录返佣列表结算状态校验闭环。
     *
     * @return void
     */
    public function test_final_checklist_records_commission_list_settle_status_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 370.', $checklist);
        $this->assertStringContainsString('CommissionController::index', $checklist);
        $this->assertStringContainsString('/api/admin/commissionList', $checklist);
        $this->assertStringContainsString('commission_records.settle_status', $checklist);
        $this->assertStringContainsString('AdminCommissionListSettleStatusValidationClosureModuleTest', $checklist);
    }

    /**
     * 准备后台超级管理员测试账号。
     *
     * @return Admin 返回 admin guard 可登录的测试管理员。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-commission-list-settle-status-super',
                'email' => 'admin-commission-list-settle-status-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 写入返佣列表测试记录。
     *
     * @param string $uniqueId 测试记录唯一编号，用于响应断言和 tearDown 清理。
     * @param int $agentId 返佣所属代理业务用户 ID。
     * @return int 返回新写入的 commission_records.id。
     */
    private function createCommissionRecord(string $uniqueId, int $agentId): int
    {
        $now = time();

        return (int) DB::table('commission_records')->insertGetId([
            'unique_id' => $uniqueId,
            'agent_id' => $agentId,
            'parent_id' => $agentId - 1,
            'agent_profit' => 18.50,
            'agent_volume' => 1.80,
            'equity_value' => 1000,
            'equity_diff' => 10,
            'settle_cycle' => 1,
            'mt4_order_id' => 0,
            'date_range' => '2026-07-28',
            'settle_status' => 1,
            'fee' => 0,
            'swap' => 0,
            'commission_amount' => 18.50,
            'returned_amount' => 0,
            'deposit' => 0,
            'real_amount' => 18.50,
            'data_type' => 'manual',
            'manual_reason' => 'settle status validation test',
            'remarks' => 'commission list settle status validation row',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
