<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证 legacy 导出接口（exportDeposits、exportWithdrawals）的日期筛选
 *           必须按 Unix 时间戳边界比较 created_at，与 BaseModel 的 schema 契约一致。
 *
 * 适用场景：后台 /api/admin/exportDeposits、/api/admin/exportWithdrawals
 *           接口的日期范围导出回归测试。
 *
 * 入参例子：
 * - POST /api/admin/exportDeposits：{start_date, end_date}（YYYY-MM-DD）
 * - POST /api/admin/exportWithdrawals：{start_date, end_date}
 *
 * 返回值：
 * - 导出成功返回 CSV 流，仅包含落在 start_date~end_date 区间内的记录。
 *
 * 异常或失败场景：
 * - 区间外的记录（如 2026-05-10 超出 2026-04-01~2026-04-30）不会出现在 CSV 中。
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
 * Legacy export date filters must compare Unix timestamp columns with Unix
 * timestamp bounds, matching the schema contract used by BaseModel.
 */
class AdminLegacyExportDateFilterClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 入金导出样本的订单号前缀。验证导出按日期过滤只含预期订单；清理按前缀删除。
     * @var string
     */
    private const DEPOSIT_PREFIX = 'legacy-export-date-deposit-';
    /**
     * 出金导出样本的订单号前缀。验证导出按日期过滤只含预期订单；清理按前缀删除。
     * @var string
     */
    private const WITHDRAW_PREFIX = 'legacy-export-date-withdraw-';

    protected function tearDown(): void
    {
        DB::table('deposit_records')
            ->where('local_order_no', 'like', self::DEPOSIT_PREFIX . '%')
            ->delete();
        DB::table('withdraw_records')
            ->where('local_order_no', 'like', self::WITHDRAW_PREFIX . '%')
            ->delete();

        parent::tearDown();
    }

    // legacy 入金导出应按 Unix 时间戳边界应用日期筛选。
    public function test_legacy_deposit_export_applies_date_bounds_to_unix_created_at(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->createDeposit(self::DEPOSIT_PREFIX . 'in', '2026-04-10 10:00:00');
        $this->createDeposit(self::DEPOSIT_PREFIX . 'out', '2026-05-10 10:00:00');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportDeposits', [
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString(self::DEPOSIT_PREFIX . 'in', $content);
        $this->assertStringNotContainsString(self::DEPOSIT_PREFIX . 'out', $content);
    }

    // legacy 出金导出应按 Unix 时间戳边界应用日期筛选。
    public function test_legacy_withdraw_export_applies_date_bounds_to_unix_created_at(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->createWithdraw(self::WITHDRAW_PREFIX . 'in', '2026-04-10 10:00:00');
        $this->createWithdraw(self::WITHDRAW_PREFIX . 'out', '2026-05-10 10:00:00');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportWithdrawals', [
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString(self::WITHDRAW_PREFIX . 'in', $content);
        $this->assertStringNotContainsString(self::WITHDRAW_PREFIX . 'out', $content);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'legacy-export-date-admin',
                'email' => 'legacy-export-date-admin@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createDeposit(string $orderNo, string $createdAt): void
    {
        $timestamp = strtotime($createdAt);

        DB::table('deposit_records')->insert([
            'user_id' => 998801,
            'user_name' => 'Legacy Export Date User',
            'mt4_ticket' => 0,
            'amount' => 10,
            'actual_amount' => 10,
            'exchange_rate' => 1,
            'channel_name' => 'Test Channel',
            'channel_order_no' => $orderNo . '-channel',
            'local_order_no' => $orderNo,
            'status' => '02',
            'payment_time' => $createdAt,
            'remarks' => '',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
    }

    private function createWithdraw(string $orderNo, string $createdAt): void
    {
        $timestamp = strtotime($createdAt);

        DB::table('withdraw_records')->insert([
            'user_id' => 998802,
            'user_name' => 'Legacy Export Date User',
            'mt4_ticket' => '',
            'apply_amount' => 20,
            'actual_amount' => 19,
            'fee' => 1,
            'exchange_rate' => 1,
            'rmb_fee' => 1,
            'bank_no' => 'TEST-BANK',
            'bank_name' => 'Test Bank',
            'bank_addr' => 'Test Address',
            'status' => 0,
            'local_order_no' => $orderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => $orderNo,
            'funding_status' => 'pending',
            'funding_payload_hash' => hash('sha256', $orderNo),
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
    }
}
