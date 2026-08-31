<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证入金列表（depositList）对 status 筛选值的映射与严格校验：
 *           legacy 值（0/1/2）与数据库值（01/02/09）应命中同一状态，
 *           非法或未支持的值必须被拒绝，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/depositList 接口的筛选参数回归测试。
 *
 * 入参例子：
 * - POST /api/admin/depositList：{status, per_page}
 *
 * 返回值：
 * - 合法筛选值（0/1/2/01/02/09）返回 code=SUCCESS 且仅命中对应状态记录；
 * - 非法筛选值（如 '1abc'、'03'、'9'、'-1'）返回 code=VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 未支持的筛选值不会被映射，直接校验失败且不返回任何记录。
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

class AdminDepositListStatusFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 本夹具入金订单号前缀（deposit-list-status-validation-）。验证状态过滤拒绝非法值；清理按前缀删除。
     * @var string
     */
    private const ORDER_PREFIX = 'deposit-list-status-validation-';

    protected function tearDown(): void
    {
        DB::table('deposit_records')
            ->where('local_order_no', 'like', self::ORDER_PREFIX . '%')
            ->delete();

        parent::tearDown();
    }

    // 入金列表应将 legacy 状态值与数据库状态值映射到同一批记录。
    public function test_deposit_list_maps_legacy_and_database_status_filters_to_canonical_records(): void
    {
        $actor = $this->ensureSuperAdmin();
        $records = [
            '01' => self::ORDER_PREFIX . 'pending',
            '02' => self::ORDER_PREFIX . 'approved',
            '09' => self::ORDER_PREFIX . 'rejected',
        ];

        foreach ($records as $status => $localOrderNo) {
            $this->createDepositRecord($localOrderNo, $status);
        }

        $cases = [
            '0' => '01',
            '1' => '02',
            '2' => '09',
            '01' => '01',
            '02' => '02',
            '09' => '09',
        ];

        foreach ($cases as $filter => $expectedStatus) {
            $response = $this->withoutMiddleware([
                AdminAuthenticate::class,
                JwtAuthMiddleware::class,
                SingleSignOn::class,
                CheckPermission::class,
            ])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/depositList', [
                    'status' => $filter,
                    'per_page' => 20,
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $orderNos = array_column((array) data_get($response->json(), 'data.data', []), 'local_order_no');
            $this->assertContains($records[$expectedStatus], $orderNos, '筛选值 ' . $filter . ' 未命中预期入金状态 ' . $expectedStatus);

            foreach ($records as $status => $localOrderNo) {
                if ($status !== $expectedStatus) {
                    $this->assertNotContains($localOrderNo, $orderNos, '筛选值 ' . $filter . ' 错误命中入金状态 ' . $status);
                }
            }
        }
    }

    // 入金列表应拒绝非严格或未支持的 status 筛选值。
    public function test_deposit_list_rejects_non_strict_or_unsupported_status_filters(): void
    {
        $actor = $this->ensureSuperAdmin();
        $localOrderNo = self::ORDER_PREFIX . 'invalid-filter';
        $this->createDepositRecord($localOrderNo, '02');

        foreach (['1abc', '03', '9', '-1'] as $invalidStatus) {
            $response = $this->withoutMiddleware([
                AdminAuthenticate::class,
                JwtAuthMiddleware::class,
                SingleSignOn::class,
                CheckPermission::class,
            ])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/depositList', [
                    'status' => $invalidStatus,
                    'per_page' => 20,
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

            $this->assertStringNotContainsString($localOrderNo, $response->getContent());
        }
    }

    // 核对最终检查清单文档记录了入金列表状态映射边界。
    public function test_final_checklist_records_deposit_list_status_mapping_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 337.', $checklist);
        $this->assertStringContainsString('DepositController::index', $checklist);
        $this->assertStringContainsString('/api/admin/depositList', $checklist);
        $this->assertStringContainsString('01/02/09', $checklist);
        $this->assertStringContainsString('0/1/2', $checklist);
        $this->assertStringContainsString('AdminDepositListStatusFilterValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-deposit-list-status-super',
                'email' => 'admin-deposit-list-status-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createDepositRecord(string $localOrderNo, string $status): int
    {
        $now = time();

        return (int) DB::table('deposit_records')->insertGetId([
            'user_id' => 983337,
            'user_name' => 'Deposit List Status Validation User',
            'mt4_ticket' => 0,
            'amount' => 337.50,
            'actual_amount' => 0,
            'exchange_rate' => 1,
            'channel_name' => 'test channel',
            'channel_order_no' => '',
            'local_order_no' => $localOrderNo,
            'status' => $status,
            'payment_time' => null,
            'remarks' => 'deposit list status validation row',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
