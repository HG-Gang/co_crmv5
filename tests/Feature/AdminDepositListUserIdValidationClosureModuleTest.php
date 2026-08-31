<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证入金列表（depositList）对 user_id 筛选值的严格校验，
 *           非法筛选值不得返回入金记录，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/depositList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/depositList：{user_id, per_page}
 *
 * 返回值：
 * - user_id 带非数字后缀时返回 code=VALIDATION_FAILED，响应不含任何入金记录。
 *
 * 异常或失败场景：
 * - 非严格数字 user_id（如 '983201abc'）时校验失败，不返回数据。
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

class AdminDepositListUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('deposit_records')
            ->where('local_order_no', 'like', 'deposit-list-user-id-validation-%')
            ->delete();

        parent::tearDown();
    }

    // 入金列表应拒绝非严格 user_id 筛选值且不返回任何记录。
    public function test_deposit_list_rejects_non_strict_user_id_filter_without_returning_records(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983201;
        $this->createDepositRecord($userId, 'deposit-list-user-id-validation-row');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/depositList', [
                'user_id' => $userId . 'abc',
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('deposit-list-user-id-validation-row', $response->getContent());
    }

    // 核对最终检查清单文档记录了入金列表 user_id 校验边界。
    public function test_final_checklist_records_deposit_list_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 309.', $checklist);
        $this->assertStringContainsString('DepositController::index', $checklist);
        $this->assertStringContainsString('/api/admin/depositList', $checklist);
        $this->assertStringContainsString('deposit_records.user_id', $checklist);
        $this->assertStringContainsString('AdminDepositListUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-deposit-list-user-id-super',
                'email' => 'admin-deposit-list-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createDepositRecord(int $userId, string $localOrderNo): int
    {
        $now = time();

        return (int) DB::table('deposit_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Deposit List User ID Validation User',
            'mt4_ticket' => 0,
            'amount' => 138.50,
            'actual_amount' => 0,
            'exchange_rate' => 1,
            'channel_name' => 'test channel',
            'channel_order_no' => '',
            'local_order_no' => $localOrderNo,
            'status' => '01',
            'payment_time' => null,
            'remarks' => 'deposit list validation row',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
