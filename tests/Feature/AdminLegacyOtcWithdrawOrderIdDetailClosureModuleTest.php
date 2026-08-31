<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:20
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Models\WithdrawRecord;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧后台 OTC 临时出金订单详情入口闭环测试。
 *
 * 文件功能：
 * - 验证旧 `OTCwithdrawOrderIdDetail` 的 recordId/userId 会被严格解析为同一条出金记录。
 * - 验证项目2不伪造未验证 OTC 第三方下单成功，只返回兼容旧 Layui 的 OTCERR 失败结构。
 * - 验证该旧入口不写入 withdraw_records 或 withdraw_settlement_outbox，避免破坏资金幂等链。
 */
class AdminLegacyOtcWithdrawOrderIdDetailClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 本夹具 OTC 出金单号前缀（LEGACY-OTC-DETAIL-）。详情断言与清理都按前缀圈定。
     * @var string
     */
    private const PREFIX = 'LEGACY-OTC-DETAIL-';

    /**
     * OTC 协议未验证时，旧详情入口应返回兼容失败结果且不写库。
     *
     * 执行链路说明：
     * - 旧页面提交 recordId/userId。
     * - 兼容层定位 withdraw_records 后只生成 BRTMP 临时单号用于说明旧请求意图。
     * - 因项目2 OTC 协议未接入，返回 THIRD_PARTY_ERROR 与 err=OTCERR，不创建 outbox。
     *
     * @return void
     */
    public function test_legacy_otc_detail_returns_unsupported_otc_error_without_writing(): void
    {
        $admin = $this->ensureSuperAdmin();
        $withdraw = $this->createWithdrawal('debited', 0);
        $originalOrderNo = (string) $withdraw->local_order_no;

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/OTCwithdrawOrderIdDetail', [
                'recordId' => $withdraw->id,
                'userId' => $withdraw->user_id,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'OTCERR')
            ->assertJsonPath('col.recordId', $withdraw->id)
            ->assertJsonPath('col.reason', 'OTC payment protocol is unsupported.');

        $this->assertMatchesRegularExpression(
            '/^BRTMP-\d{14}-WR-' . $withdraw->user_id . '$/',
            (string) $response->json('data.order_id')
        );
        $this->assertSame($response->json('data.order_id'), $response->json('col.orderId'));

        $withdraw->refresh();
        $this->assertSame(0, (int) $withdraw->status);
        $this->assertSame('debited', (string) $withdraw->funding_status);
        $this->assertSame($originalOrderNo, (string) $withdraw->local_order_no);
        $this->assertSame('', (string) $withdraw->third_order_no);
        $this->assertSame('', (string) $withdraw->updated_by);
        $this->assertSame(0, DB::table('withdraw_settlement_outbox')->where('withdraw_record_id', $withdraw->id)->count());
    }

    /**
     * 缺少 userId 时必须参数失败，不允许只用 recordId 拼临时 OTC 单号。
     *
     * @return void
     */
    public function test_legacy_otc_detail_rejects_missing_user_id_without_writing(): void
    {
        $admin = $this->ensureSuperAdmin();
        $withdraw = $this->createWithdrawal('debited', 0);
        $originalOrderNo = (string) $withdraw->local_order_no;

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/OTCwithdrawOrderIdDetail', [
                'recordId' => $withdraw->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'VALIDATION_FAILED')
            ->assertJsonPath('col', 'userId');

        $withdraw->refresh();
        $this->assertSame($originalOrderNo, (string) $withdraw->local_order_no);
        $this->assertSame('', (string) $withdraw->updated_by);
        $this->assertSame(0, DB::table('withdraw_settlement_outbox')->where('withdraw_record_id', $withdraw->id)->count());
    }

    /**
     * recordId 与 userId 不属于同一条记录时必须失败，避免把他人的出金资料提交给 OTC。
     *
     * @return void
     */
    public function test_legacy_otc_detail_rejects_record_user_mismatch_without_writing(): void
    {
        $admin = $this->ensureSuperAdmin();
        $withdraw = $this->createWithdrawal('debited', 0);
        $originalOrderNo = (string) $withdraw->local_order_no;

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/OTCwithdrawOrderIdDetail', [
                'recordId' => $withdraw->id,
                'userId' => $withdraw->user_id + 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'UPDATEFAIL')
            ->assertJsonPath('col', 'NOCOL');

        $withdraw->refresh();
        $this->assertSame($originalOrderNo, (string) $withdraw->local_order_no);
        $this->assertSame('', (string) $withdraw->updated_by);
        $this->assertSame(0, DB::table('withdraw_settlement_outbox')->where('withdraw_record_id', $withdraw->id)->count());
    }

    /**
     * 创建测试后台管理员。
     *
     * @return Admin 可绑定 admin guard 的超级管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-legacy-otc-detail-super',
                'email' => 'admin-legacy-otc-detail-super@example.test',
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
     * 创建测试出金记录。
     *
     * @param string $fundingStatus 资金状态；debited 表示前置扣款已经成功。
     * @param int $status 出金审核状态；0=待处理，1=处理中。
     * @return WithdrawRecord 出金记录模型。
     */
    private function createWithdrawal(string $fundingStatus, int $status): WithdrawRecord
    {
        $localOrderNo = self::PREFIX . uniqid('', true);

        return WithdrawRecord::create([
            'user_id' => 412355326,
            'user_name' => 'legacy-otc-detail-user',
            'mt4_ticket' => '88326',
            'apply_amount' => '50.00',
            'actual_amount' => '49.00',
            'fee' => '1.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '7.00',
            'bank_no' => 'BANK',
            'bank_name' => 'Bank',
            'bank_addr' => 'Addr',
            'status' => $status,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => $localOrderNo,
            'funding_status' => $fundingStatus,
            'funding_payload_hash' => hash('sha256', $localOrderNo),
            'created_by' => 'test',
            'updated_by' => '',
        ]);
    }
}
