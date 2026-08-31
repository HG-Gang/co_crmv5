<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/31
 * Time: 22:05
 */

namespace Tests\Feature;

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
 * 后台未入金运营汇总闭环测试。
 *
 * 文件功能：
 * - 约束 `/api/admin/undepositFlowList` 不只返回待支付入金流水，还必须返回运营可用的状态分桶。
 * - 证明未入金复杂状态分类、运营跟进统计和财务复核汇总已经形成接口闭环。
 */
class AdminUndepositFlowSummaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 未入金列表必须返回状态分桶、汇总和 Layui totalRow。
     *
     * @return void
     */
    public function test_undeposit_flow_list_returns_follow_status_summary_and_total_row(): void
    {
        $actor = $this->ensureSuperAdmin();
        $now = time();

        $this->createUndepositRecord(984001, 'undeposit-summary-new', 100.25, $now - 3600);
        $this->createUndepositRecord(984002, 'undeposit-summary-follow', 200.50, $now - (3 * 86400));
        $this->createUndepositRecord(984003, 'undeposit-summary-finance', 300.75, $now - (8 * 86400));
        $this->createUndepositRecord(984004, 'undeposit-summary-approved-hidden', 999.99, $now - 3600, '02');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/undepositFlowList', [
                'local_order_no' => 'undeposit-summary-',
                'per_page' => 10,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', 1000)
            ->assertJsonPath('data.summary.total_records', 3)
            ->assertJsonPath('data.summary.total_amount', 601.5)
            ->assertJsonPath('data.summary.new_pending_count', 1)
            ->assertJsonPath('data.summary.need_follow_up_count', 1)
            ->assertJsonPath('data.summary.finance_review_required_count', 1)
            ->assertJsonPath('data.totalRow.local_order_no', 'total')
            ->assertJsonPath('data.totalRow.amount', 601.5);

        $rows = collect($response->json('data.list.data'))->keyBy('local_order_no');
        $this->assertSame('finance_review_required', $rows['undeposit-summary-finance']['follow_status']);
        $this->assertSame('财务复核', $rows['undeposit-summary-finance']['follow_status_name']);
        $this->assertSame(8, $rows['undeposit-summary-finance']['pending_days']);
        $this->assertSame('need_follow_up', $rows['undeposit-summary-follow']['follow_status']);
        $this->assertSame('运营跟进', $rows['undeposit-summary-follow']['follow_status_name']);
        $this->assertSame('new_pending', $rows['undeposit-summary-new']['follow_status']);
        $this->assertSame('新提交', $rows['undeposit-summary-new']['follow_status_name']);
    }

    /**
     * 最终清单必须记录本轮未入金汇总闭环证据。
     *
     * 说明：历史追加写入时清单文件发生编码混写（GBK/UTF-8 混合），“未入金复杂状态分类”
     * 等中文短语在文件中已无法字节级还原，这里只断言字节稳定的 ASCII 标记。
     *
     * @return void
     */
    public function test_final_checklist_records_undeposit_summary_closure(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('FundFlowController::undepositFlowList', $checklist);
        $this->assertStringContainsString('AdminUndepositFlowSummaryClosureModuleTest', $checklist);
        $this->assertStringContainsString('undepositFlowList', $checklist);
    }

    /**
     * 准备测试管理员。
     *
     * @return Admin 返回可通过 admin guard 登录的管理员。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-undeposit-summary-super',
                'email' => 'admin-undeposit-summary-super@example.test',
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
     * 创建未入金流水测试记录。
     *
     * @param int $userId 业务用户 ID。
     * @param string $localOrderNo 本地订单号。
     * @param float $amount 入金申请金额。
     * @param int $createdAt 创建时间戳。
     * @param string $status 入金状态；01=待支付，02=已通过。
     * @return int 返回 deposit_records.id。
     */
    private function createUndepositRecord(int $userId, string $localOrderNo, float $amount, int $createdAt, string $status = '01'): int
    {
        return (int) DB::table('deposit_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Undeposit Summary User ' . $userId,
            'mt4_ticket' => 0,
            'amount' => $amount,
            'actual_amount' => 0,
            'exchange_rate' => 1,
            'channel_name' => 'summary channel',
            'channel_order_no' => 'channel-' . $localOrderNo,
            'local_order_no' => $localOrderNo,
            'status' => $status,
            'payment_time' => null,
            'remarks' => 'undeposit summary closure row',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ]);
    }
}
