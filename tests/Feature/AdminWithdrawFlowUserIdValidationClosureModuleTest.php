<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 提现流水查询与导出接口 user_id 参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 验证 /api/admin/withdrawFlowList 与 /api/admin/exportWithdrawFlows 均拒绝非严格 user_id。
 * - 验证校验失败时列表不返回流水记录，导出不生成 CSV。
 * - 验证最终清单文档已记录该 user_id 校验边界。
 *
 * 适用场景：
 * - 管理员提现流水模块入参安全的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/withdrawFlowList
 *   user_id: 983401abc
 *   per_page: 5
 *
 * 返回值：
 * - 列表接口返回 code 为 VALIDATION_FAILED，且响应不含目标 ticket。
 * - 导出接口返回 JSON 且 code 为 VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 若非严格 user_id 被放行并返回流水，断言失败。
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

class AdminWithdrawFlowUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('mt4_trades')
            ->where('ticket', '>=', 98340100)
            ->where('ticket', '<', 98340200)
            ->delete();

        parent::tearDown();
    }

    /**
     * 验证提现流水列表拒绝非严格 user_id 且不返回记录。
     */
    public function test_withdraw_flow_list_rejects_non_strict_user_id_filter_without_returning_records(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983401;
        $ticket = 98340101;
        $this->createWithdrawFlowTrade($userId, $ticket);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/withdrawFlowList', [
                'user_id' => $userId . 'abc',
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString((string) $ticket, $response->getContent());
    }

    /**
     * 验证提现流水导出拒绝非严格 user_id 筛选值。
     */
    public function test_withdraw_flow_export_rejects_non_strict_user_id_filter(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983401;
        $this->createWithdrawFlowTrade($userId, 98340102);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/exportWithdrawFlows', [
                'user_id' => $userId . 'abc',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    /**
     * 验证最终清单文档已记录提现流水 user_id 校验边界（## 311）。
     */
    public function test_final_checklist_records_withdraw_flow_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 311.', $checklist);
        $this->assertStringContainsString('FundFlowController::withdrawFlowList', $checklist);
        $this->assertStringContainsString('FundFlowController::exportWithdrawFlows', $checklist);
        $this->assertStringContainsString('/api/admin/withdrawFlowList', $checklist);
        $this->assertStringContainsString('/api/admin/exportWithdrawFlows', $checklist);
        $this->assertStringContainsString('mt4_trades.login', $checklist);
        $this->assertStringContainsString('AdminWithdrawFlowUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-withdraw-flow-user-id-super',
                'email' => 'admin-withdraw-flow-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createWithdrawFlowTrade(int $userId, int $ticket): void
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
            'profit' => -45.50,
            'open_time' => $now - 3600,
            'close_time' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
