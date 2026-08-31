<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 提现流水列表按 MT4 COMMENT 细分分类（withdraw_source）的闭环测试。
 *
 * 文件功能：
 * - 验证 /api/admin/withdrawFlowList 按 comment 前缀（WBIN/WBAD）分类流水来源并返回汇总行。
 * - 验证 /api/admin/exportWithdrawFlows 导出 CSV 包含来源分类与当前筛选合计。
 * - 验证前端配置与文档已记录 comment 分类和当前筛选汇总。
 *
 * 适用场景：
 * - 管理员提现流水查询与导出功能的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/withdrawFlowList
 *   user_id: 984201
 *   withdraw_source: WBIN
 *   per_page: 5
 *
 * 返回值：
 * - 列表接口 code 为 SUCCESS，data.list.total、data.totalRow、data.summary 与筛选一致。
 * - 导出接口返回 text/csv 流，内容含 flow_source_name、comment、total 与合计金额。
 *
 * 异常或失败场景：
 * - 若分类错误、导出缺列或合计不符，断言失败。
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

class AdminWithdrawFlowCommentClassificationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('mt4_trades')
            ->where('ticket', '>=', 98420100)
            ->where('ticket', '<', 98420200)
            ->delete();

        DB::table('user_infos')
            ->where('user_id', 984201)
            ->delete();

        parent::tearDown();
    }

    /**
     * 验证提现流水列表按 comment 分类来源并返回当前筛选的汇总行。
     */
    public function test_withdraw_flow_list_classifies_comment_filters_source_and_returns_total_row(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 984201;
        $this->ensureUser($userId);
        $this->createWithdrawFlowTrade($userId, 98420101, -101.25, 'WBIN-984201-normal-withdraw');
        $this->createWithdrawFlowTrade($userId, 98420102, -50.75, 'WBAD-984201-platform-withdraw');
        $this->createWithdrawFlowTrade($userId, 98420103, -999.00, 'manual-negative-without-withdraw-keyword');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/withdrawFlowList', [
                'user_id' => $userId,
                'withdraw_source' => 'WBIN',
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.total', 1)
            ->assertJsonPath('data.list.data.0.ticket', 98420101)
            ->assertJsonPath('data.list.data.0.flow_source', 'WBIN')
            ->assertJsonPath('data.list.data.0.flow_source_name', '账户取款')
            ->assertJsonPath('data.list.data.0.directTypeName', '账户取款')
            ->assertJsonPath('data.list.data.0.comment', 'WBIN-984201-normal-withdraw')
            ->assertJsonPath('data.totalRow.order_no', 'total')
            ->assertJsonPath('data.totalRow.directProfit', -101.25)
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.summary.total_profit', -101.25);

        $this->assertStringNotContainsString('98420102', $response->getContent());
        $this->assertStringNotContainsString('98420103', $response->getContent());
    }

    /**
     * 验证提现流水导出 CSV 包含来源分类与当前筛选合计。
     */
    public function test_withdraw_flow_export_contains_comment_source_and_current_filter_total(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 984201;
        $this->ensureUser($userId);
        $this->createWithdrawFlowTrade($userId, 98420101, -101.25, 'WBIN-984201-normal-withdraw');
        $this->createWithdrawFlowTrade($userId, 98420102, -50.75, 'WBAD-984201-platform-withdraw');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/exportWithdrawFlows', [
                'user_id' => $userId,
                'withdraw_source' => 'WBAD',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('flow_source_name', $csv);
        $this->assertStringContainsString('comment', $csv);
        $this->assertStringContainsString('98420102', $csv);
        $this->assertStringContainsString('WBAD', $csv);
        $this->assertStringContainsString('平台出金', $csv);
        $this->assertStringContainsString('total', $csv);
        $this->assertStringContainsString('-50.75', $csv);
        $this->assertStringNotContainsString('98420101', $csv);
    }

    /**
     * 验证前端页面、脚本与文档均已覆盖提现流水 comment 细分分类。
     */
    public function test_frontend_configs_and_docs_record_withdraw_flow_comment_classification(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/withdraw-flows/index.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $audit = file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md')) ?: '';
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('name="withdraw_source"', $blade);
        $this->assertStringContainsString("field: 'flow_source_name'", $layui);
        $this->assertStringContainsString("field: 'comment'", $layui);
        $this->assertStringContainsString('totalRow: true', $layui);
        $this->assertStringContainsString("'filters' => ['user_id', 'ticket', 'withdraw_source', 'start_date', 'end_date']", $crmui);
        $this->assertStringContainsString("'columns' => ['ticket', 'login', 'user_name', 'profit', 'flow_source_name', 'comment', 'close_time']", $crmui);
        $this->assertStringContainsString('MT4 COMMENT 细分分类和当前筛选汇总已覆盖', $audit);
        $this->assertStringContainsString('AdminWithdrawFlowCommentClassificationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-withdraw-flow-comment-super',
                'email' => 'admin-withdraw-flow-comment-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function ensureUser(int $userId): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Withdraw Flow Comment User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function createWithdrawFlowTrade(int $userId, int $ticket, float $profit, string $comment): void
    {
        $now = time();

        DB::table('mt4_trades')->insert([
            'ticket' => $ticket,
            'login' => $userId,
            'symbol' => 'BALANCE',
            'cmd' => 6,
            'volume' => 0,
            'open_price' => 0,
            'close_price' => 0,
            'commission' => 0,
            'swaps' => 0,
            'profit' => $profit,
            'open_time' => $now - 3600,
            'close_time' => $now,
            'comment' => $comment,
            'modify_time' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
