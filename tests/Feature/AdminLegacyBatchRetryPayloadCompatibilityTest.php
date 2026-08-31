<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 文件功能：验证 legacy 批量导入重试接口（againDepositAmount/againWithdrawAmount/
 *           againCreditAmount）兼容顶层 JSON 数组入参、部分失败结果上报，
 *           且绝不允许使用 id=0。
 *
 * 适用场景：/index/admin/amount/againDepositAmount 等 legacy 路由的批量重试
 *           兼容性回归测试。
 *
 * 入参例子：
 * - POST /index/admin/amount/againDepositAmount：[{id, user_id, batch_no}, ...]
 *
 * 返回值：
 * - 全部成功：code=BATCH_SUCCESS，失败记录 is_synced 重置为 0；
 * - 部分失败：code=BATCH_PARTIAL_FAILED，合法记录仍被重置。
 *
 * 异常或失败场景：
 * - 请求行缺少 id 时按部分失败处理，不产生 id=0 的记录。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLegacyBatchRetryPayloadCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, array<int, int>> */
    private array $createdIds = [
        'deposit_imports' => [],
        'withdraw_imports' => [],
        'credit_imports' => [],
    ];

    protected function tearDown(): void
    {
        foreach ($this->createdIds as $table => $ids) {
            if ($ids !== []) {
                DB::table($table)->whereIn('id', $ids)->delete();
            }
        }

        parent::tearDown();
    }

    // legacy 批量重试应接受顶层 JSON 数组入参并重置失败记录。
    /**
     * @dataProvider retryRoutes
     */
    public function test_legacy_batch_retry_accepts_root_json_rows_and_resets_failed_records(
        string $uri,
        string $table
    ): void {
        $admin = Admin::query()->findOrFail(1);
        $firstId = $this->insertImport($table, 'legacy-batch-first-' . uniqid('', true));
        $secondId = $this->insertImport($table, 'legacy-batch-second-' . uniqid('', true));

        $response = $this->actingAs($admin, 'admin')->postJson('/' . $uri, [
            ['id' => $firstId, 'user_id' => 982001, 'batch_no' => 'old-first'],
            ['id' => $secondId, 'user_id' => 982002, 'batch_no' => 'old-second'],
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::BATCH_SUCCESS);
        $this->assertSame(0, (int) DB::table($table)->where('id', $firstId)->value('is_synced'));
        $this->assertSame(0, (int) DB::table($table)->where('id', $secondId)->value('is_synced'));
    }

    // 批量重试应上报部分失败结果，且绝不使用 id=0 的记录。
    public function test_legacy_batch_retry_reports_partial_result_and_never_uses_id_zero(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $validId = $this->insertImport('deposit_imports', 'legacy-batch-partial-' . uniqid('', true));

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/amount/againDepositAmount', [
            ['id' => $validId],
            ['user_id' => 982099, 'batch_no' => 'missing-id'],
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::BATCH_PARTIAL_FAILED);
        $this->assertSame(0, (int) DB::table('deposit_imports')->where('id', $validId)->value('is_synced'));
        $this->assertDatabaseMissing('deposit_imports', ['id' => 0]);
    }

    /** @return array<string, array{string, string}> */
    public static function retryRoutes(): array
    {
        return [
            'deposit' => ['index/admin/amount/againDepositAmount', 'deposit_imports'],
            'withdraw' => ['index/admin/amount/againWithdrawAmount', 'withdraw_imports'],
            'credit' => ['index/admin/credit/againCreditAmount', 'credit_imports'],
        ];
    }

    private function insertImport(string $table, string $batchNo): int
    {
        $now = time();
        $payload = [
            'user_id' => random_int(982100, 982999),
            'user_name' => 'legacy-batch-user',
            'amount' => '10.00',
            'remarks' => 'legacy batch retry test',
            'mt4_order_id' => 0,
            'batch_no' => $batchNo,
            'is_synced' => 2,
            'fail_reason' => 'previous failure',
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
        if ($table === 'credit_imports') {
            $payload['credit_type'] = 1;
        }

        $id = (int) DB::table($table)->insertGetId($payload);
        $this->createdIds[$table][] = $id;

        return $id;
    }
}
