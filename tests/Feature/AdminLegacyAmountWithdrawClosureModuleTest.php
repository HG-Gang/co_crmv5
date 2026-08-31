<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/21
 * Time: 23:22
 */

/**
 * AdminLegacyAmountWithdrawClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台入金/出金批量操作与导入闭环：旧载荷与旧订单契约、越权用户与非法金额在调用网关前拒绝、网关拒绝不伪造订单、Excel 导入、OTC 状态机映射与汇率保存。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Contracts\DepositRefundGateway;
use App\Contracts\DepositSettlementGateway;
use App\Constants\ResponseCode;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Models\Admin;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台遗留"资金/出金/批量导入/汇率/权益汇总"入口闭环测试。
 *
 * 文件目的：
 * - 旧后台出金搜索（withdrawApplySearch/V2、withdraw/{status}Search）复用
 *   统一出金查询；状态专属搜索按 URI 固定状态并返回旧 V2 信封；
 * - 旧后台批量导入（batchOperation/batchOperationWithdraw、depositImportExcel、
 *   withdrawImportExcel、creditImportExcel）与导入搜索转发到现代导入控制器，
 *   缺失必填载荷必须 fail-closed（VALIDATION_FAILED）；
 * - 旧后台 order_status 显式携带 status=2 才能完成出金，order_status_OTC 在协议未接入时失败关闭，
 *   whpj_rate_save 写汇率配置，rightsSumExport 输出 CSV；
 * - 逐条断言旧入口返回现代成功信封且能看到按旧条件种子的记录。
 */
class AdminLegacyAmountWithdrawClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_withdraw_apply_searches_see_seeded_records(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984201;
        $this->seedWithdrawRecord($userId, 'WITHDRAW-APPLY-984201', 0, 'debited');

        foreach (['withdrawApplySearch', 'withdrawApplySearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/amount/' . $action, ['user_id' => $userId])
                ->assertOk();

            if ($action === 'withdrawApplySearchV2') {
                $response->assertJsonPath('code', 200);
            } else {
                $response->assertJsonStructure(['rows', 'total', 'footer']);
            }

            $this->assertStringContainsString('WITHDRAW-APPLY-984201', $response->getContent(), $action);
        }
    }

    public function test_legacy_withdraw_status_searches_respect_status_defaults(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984202;
        $this->seedWithdrawRecord($userId, 'WITHDRAW-STATUS-PENDING', 0, 'debited');
        $this->seedWithdrawRecord($userId, 'WITHDRAW-STATUS-PROCESSING', 1, 'debited');
        $this->seedWithdrawRecord($userId, 'WITHDRAW-STATUS-COMPLETED', 2, 'completed');
        $this->seedWithdrawRecord($userId, 'WITHDRAW-STATUS-FAILED', 3, 'cancelled');

        $expectations = [
            ['pendingSearch', 'WITHDRAW-STATUS-PENDING'],
            ['processingSearch', 'WITHDRAW-STATUS-PROCESSING'],
            ['completedSearch', 'WITHDRAW-STATUS-COMPLETED'],
            ['failedSearch', 'WITHDRAW-STATUS-FAILED'],
        ];

        foreach ($expectations as [$action, $orderNo]) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/withdraw/' . $action, ['user_id' => $userId])
                ->assertOk()
                ->assertJsonPath('code', 200)
                ->assertJsonStructure(['code', 'msg', 'count', 'data', 'totalRow']);

            $this->assertStringContainsString($orderNo, $response->getContent(), $action);
        }
    }

    public function test_legacy_deposit_and_withdraw_import_searches_see_seeded_records(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984203;
        $this->seedDepositImport($userId, 'DEPOSIT-IMPORT-984203');
        $this->seedDepositImport($userId, 'DEPOSIT-IMPORT-984203-OTHER');
        $this->seedWithdrawImport($userId, 'WITHDRAW-IMPORT-984203');
        $this->seedWithdrawImport($userId, 'WITHDRAW-IMPORT-984203-OTHER');
        $this->seedCreditImport($userId, 'CREDIT-IMPORT-984203');

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/depositImportSearch', [
                'user_id' => $userId,
                'user_name' => 'legacy-deposit-import',
                'batch_no' => 'DEPOSIT-IMPORT-984203',
                'startdate' => date('Y-m-d', time() - 86400),
                'enddate' => date('Y-m-d'),
                'sync_succ' => '0',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString('DEPOSIT-IMPORT-984203', $response->getContent());
        $this->assertSame(1, $response->json('count'));
        $this->assertIsArray($response->json('data'));
        $this->assertSame(0, $response->json('data.0.is_sync_succ'));
        $this->assertSame('legacy-deposit-import', $response->json('data.0.user_name'));
        $this->assertArrayHasKey('amount', $response->json('totalRow'));

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawImportSearch', [
                'user_id' => $userId,
                'user_name' => 'legacy-withdraw-import',
                'batch_no' => 'WITHDRAW-IMPORT-984203',
                'startdate' => date('Y-m-d', time() - 86400),
                'enddate' => date('Y-m-d'),
                'sync_succ' => '0',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString('WITHDRAW-IMPORT-984203', $response->getContent());
        $this->assertSame(1, $response->json('count'));
        $this->assertIsArray($response->json('data'));
        $this->assertSame(0, $response->json('data.0.is_sync_succ'));
        $this->assertArrayHasKey('amount', $response->json('totalRow'));

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/credit/creditImportSearch', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString('CREDIT-IMPORT-984203', $response->getContent());
    }

    public function test_legacy_deposit_batch_operation_accepts_old_payload_and_returns_old_order_contract(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userIds = [984205, 984206];
        foreach ($userIds as $userId) {
            $this->seedImportUser($userId, 'legacy-deposit-batch-' . $userId);
        }

        $calls = [];
        $this->bindDepositGateway($calls);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/batchOperation', [
                'deposit_amount' => '10.50',
                'deposit_comment' => 'legacy-deposit',
                'id_list' => implode(',', $userIds),
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('no', 2);

        $this->assertSame('984205,984206', $response->json('order'));
        $this->assertSame([
            [$userIds[0], '10.50', 'legacy-deposit-984205'],
            [$userIds[1], '10.50', 'legacy-deposit-984206'],
        ], $calls);
        $this->assertIsInt($response->json('time'));
    }

    public function test_legacy_import_search_uses_old_default_date_window(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984216;
        $this->seedDepositImport($userId, 'DEPOSIT-IMPORT-BEFORE-2024');
        $this->seedDepositImport($userId, 'DEPOSIT-IMPORT-CURRENT');
        DB::table('deposit_imports')
            ->where('batch_no', 'DEPOSIT-IMPORT-BEFORE-2024')
            ->where('user_id', $userId)
            ->update(['updated_at' => strtotime('2023-12-31 23:59:59')]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/depositImportSearch', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 1);

        $this->assertStringContainsString('DEPOSIT-IMPORT-CURRENT', $response->getContent());
        $this->assertStringNotContainsString('DEPOSIT-IMPORT-BEFORE-2024', $response->getContent());
    }

    public function test_legacy_withdraw_batch_operation_accepts_old_payload_and_returns_old_order_contract(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userIds = [984207, 984208];
        foreach ($userIds as $userId) {
            $this->seedImportUser($userId, 'legacy-withdraw-batch-' . $userId);
        }

        $calls = [];
        $this->bindWithdrawGateway($calls);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/batchOperationWithdraw', [
                'withdraw_amount' => '8.75',
                'withdraw_comment' => 'legacy-withdraw',
                'id_list' => implode(',', $userIds),
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('no', 2);

        $this->assertSame('984207,984208', $response->json('order'));
        $this->assertSame([
            [$userIds[0], '8.75', 'legacy-withdraw-984207'],
            [$userIds[1], '8.75', 'legacy-withdraw-984208'],
        ], $calls);
        $this->assertIsInt($response->json('time'));
    }

    public function test_legacy_batch_operation_rejects_invalid_ids_or_amount_before_calling_gateway(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $calls = [];
        $this->bindDepositGateway($calls);

        $this->withoutMiddleware(LegacyAdminAuthenticate::class)
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/batchOperation', [
                'deposit_amount' => '0',
                'deposit_comment' => 'invalid',
                'id_list' => '984209,not-an-id',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame([], $calls);
    }

    public function test_legacy_batch_operation_rejects_users_outside_admin_scope_without_calling_gateway(): void
    {
        $adminId = 984210;
        $roleId = 984211;
        $allowedUserId = 984212;
        $blockedUserId = 984213;
        $this->seedImportUser($allowedUserId, 'legacy-scope-allowed');
        $this->seedImportUser($blockedUserId, 'legacy-scope-blocked');
        $admin = $this->seedScopedAdmin($adminId, $roleId, [$allowedUserId]);
        $calls = [];
        $this->bindDepositGateway($calls);

        $this->withoutMiddleware(LegacyAdminAuthenticate::class)
            ->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/batchOperation', [
                'deposit_amount' => '10.00',
                'deposit_comment' => 'scope',
                'id_list' => (string) $blockedUserId,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $this->assertSame([], $calls);
    }

    public function test_legacy_batch_operation_does_not_fabricate_order_when_gateway_rejects(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984214;
        $this->seedImportUser($userId, 'legacy-gateway-rejected');
        $calls = [];
        app()->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 结算网关替身的调用捕获表。deposit() 记下 [userId, amount, comment]，
             * 断言旧版金额出金被渠道拒绝时不产生入账。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment];

                return DepositSettlementResult::rejected('provider_rejected');
            }
        });

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/batchOperation', [
                'deposit_amount' => '10.00',
                'deposit_comment' => 'rejected',
                'id_list' => (string) $userId,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);

        $this->assertSame('', (string) $response->json('order'));
        $this->assertSame([[$userId, '10.00', 'rejected-' . $userId]], $calls);
    }

    public function test_legacy_import_excel_entries_fail_closed_without_file(): void
    {
        $admin = Admin::query()->findOrFail(1);

        foreach ([
            'index/admin/amount/depositImportExcel',
            'index/admin/amount/withdrawImportExcel',
            'index/admin/credit/creditImportExcel',
        ] as $uri) {
            $this->actingAs($admin, 'admin')
                ->postJson('/' . $uri)
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    public function test_legacy_import_excel_accepts_old_file_field_and_generates_batch_number(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984215;
        $this->seedImportUser($userId, 'legacy-excel-import');
        $depositCalls = [];
        $withdrawCalls = [];
        $this->bindDepositGateway($depositCalls);
        $this->bindWithdrawGateway($withdrawCalls);

        foreach ([
            'depositImportExcel' => 'deposit_imports',
            'withdrawImportExcel' => 'withdraw_imports',
        ] as $action => $table) {
            $response = $this->actingAs($admin, 'admin')
                ->post('/index/admin/amount/' . $action, [
                    'file' => $this->csvUpload(
                        "user_id,user_name,amount,remarks\n" .
                        $userId . ",legacy-excel-import,12.34,legacy file row\n"
                    ),
                ])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::CREATED)
                ->assertJsonPath('data.created', 1);

            $this->assertDatabaseHas($table, [
                'user_id' => $userId,
                'amount' => '12.34',
                'remarks' => 'legacy file row',
                'is_synced' => 1,
                'mt4_order_id' => $userId,
            ]);
            $this->assertNotSame('', (string) DB::table($table)
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->value('batch_no'));
            $this->assertStringContainsString('legacy file row', $response->getContent());
        }

        $this->assertSame([[$userId, '12.34', 'legacy file row']], $depositCalls);
        $this->assertSame([[$userId, '12.34', 'legacy file row']], $withdrawCalls);
    }

    public function test_legacy_credit_import_excel_accepts_old_file_field_and_creates_pending_records(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984217;
        $this->seedImportUser($userId, 'legacy-credit-excel-import');

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/credit/creditImportExcel', [
                'file' => $this->csvUpload(
                    "user_id,user_name,credit_type,amount,batch_no,remarks\n" .
                    $userId . ",legacy-credit-excel-import,1,20.50,LEGACY-CREDIT-984217,legacy credit row\n"
                ),
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.created', 1);

        // 授信导入只落库待同步记录，MT4 授信由重试/同步任务执行，不在上传循环内直接调用网关。
        $this->assertDatabaseHas('credit_imports', [
            'user_id' => $userId,
            'amount' => '20.50',
            'remarks' => 'legacy credit row',
            'is_synced' => 0,
        ]);
        $this->assertStringContainsString('legacy credit row', $response->getContent());
    }

    public function test_legacy_batch_operation_entries_fail_closed_without_payload(): void
    {
        $admin = Admin::query()->findOrFail(1);

        foreach ([
            'index/admin/amount/batchOperation',
            'index/admin/amount/batchOperationWithdraw',
        ] as $uri) {
            $this->actingAs($admin, 'admin')
                ->postJson('/' . $uri)
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    public function test_legacy_order_status_completes_debited_withdrawal(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984204;
        $record = $this->seedWithdrawRecord($userId, 'WITHDRAW-ORDER-STATUS-984204', 1, 'debited');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status', [
                'id' => $record->id,
                'orderStatus' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame(2, (int) DB::table('withdraw_records')->where('id', $record->id)->value('status'));
        $this->assertSame('completed', DB::table('withdraw_records')->where('id', $record->id)->value('funding_status'));
    }

    public function test_legacy_order_status_otc_id_only_payload_fails_closed_without_state_change(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $record = $this->seedWithdrawRecord(984225, 'WITHDRAW-OTC-ID-ONLY-984225', 1, 'debited');
        $outboxCount = DB::table('withdraw_settlement_outbox')
            ->where('withdraw_record_id', $record->id)
            ->count();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status_OTC', ['id' => $record->id])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'invalidValue');

        $this->assertSame(1, (int) DB::table('withdraw_records')->where('id', $record->id)->value('status'));
        $this->assertSame('debited', (string) DB::table('withdraw_records')->where('id', $record->id)->value('funding_status'));
        $this->assertSame($outboxCount, DB::table('withdraw_settlement_outbox')
            ->where('withdraw_record_id', $record->id)
            ->count());
    }

    public function test_legacy_generate_otc_order_fails_closed_without_withdraw_id(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/generate_OTCorder')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_generate_otc_order_maps_order_id_and_user_id_but_fails_closed_without_protocol(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984217;
        $record = $this->seedWithdrawRecord($userId, 'WITHDRAW-OTC-GENERATE-984217', 0, 'debited');
        $originalOrderNo = (string) DB::table('withdraw_records')->where('id', $record->id)->value('local_order_no');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/generate_OTCorder', [
                'orderId' => $record->id,
                'userId' => $userId,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'OTCERR');

        $this->assertSame($originalOrderNo, (string) DB::table('withdraw_records')->where('id', $record->id)->value('local_order_no'));
        $this->assertSame(0, DB::table('withdraw_settlement_outbox')
            ->where('withdraw_record_id', $record->id)
            ->count());
    }

    public function test_legacy_generate_otc_order_preserves_existing_order_contract(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984224;
        $record = $this->seedWithdrawRecord($userId, 'WITHDRAW-OTC-EXISTS-984224', 0, 'debited');
        DB::table('withdraw_records')->where('id', $record->id)->update(['third_order_no' => 'OTC-EXISTING-984224']);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/generate_OTCorder', [
                'orderId' => $record->id,
                'userId' => $userId,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_ALREADY_EXISTS)
            ->assertJsonPath('msg', 'exists order')
            ->assertJsonPath('err', 'errexists')
            ->assertJsonPath('col', 'nocol');
    }

    public function test_legacy_order_status_maps_old_fields_to_modern_state_machine(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984218;
        $record = $this->seedWithdrawRecord($userId, 'WITHDRAW-OTC-STATUS-984218', 0, 'debited');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status', [
                'orderId' => $record->id,
                'orderStatus' => '1',
                'orderRemark' => 'legacy processing',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUC');

        $this->assertSame(1, (int) DB::table('withdraw_records')->where('id', $record->id)->value('status'));
        $this->assertSame('debited', (string) DB::table('withdraw_records')->where('id', $record->id)->value('funding_status'));

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status', [
                'orderId' => $record->id,
                'orderStatus' => '2',
                'orderRemark' => 'legacy completed',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED)
            ->assertJsonPath('msg', 'SUC');

        $this->assertSame(2, (int) DB::table('withdraw_records')->where('id', $record->id)->value('status'));
        $this->assertSame('completed', (string) DB::table('withdraw_records')->where('id', $record->id)->value('funding_status'));
    }

    public function test_legacy_order_status_rejects_invalid_old_status_with_legacy_error_fields(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $record = $this->seedWithdrawRecord(984219, 'WITHDRAW-OTC-INVALID-984219', 0, 'debited');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status', [
                'orderId' => $record->id,
                'orderStatus' => '9',
                'orderRemark' => 'invalid',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'invalidValue')
            ->assertJsonPath('col', 'apply_status');

        $this->assertSame(0, (int) DB::table('withdraw_records')->where('id', $record->id)->value('status'));
        $this->assertSame('debited', (string) DB::table('withdraw_records')->where('id', $record->id)->value('funding_status'));
    }

    public function test_legacy_order_status_otc_does_not_fabricate_success_without_protocol(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $record = $this->seedWithdrawRecord(984220, 'WITHDRAW-OTC-BRANCH-984220', 1, 'debited');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status_OTC', [
                'orderId' => $record->id,
                'orderStatus' => '2',
                'orderRemark' => 'legacy otc complete',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'OTCERR');

        $this->assertSame(1, (int) DB::table('withdraw_records')->where('id', $record->id)->value('status'));
        $this->assertSame('debited', (string) DB::table('withdraw_records')->where('id', $record->id)->value('funding_status'));
        $this->assertSame(0, DB::table('withdraw_settlement_outbox')
            ->where('withdraw_record_id', $record->id)
            ->count());
    }

    public function test_legacy_order_status_fails_closed_for_missing_record(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status', [
                'id' => 987654321,
                'orderStatus' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
    }

    public function test_legacy_whpj_rate_save_persists_exchange_rates(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/whpj_rate_save', [
                'sys_deposit_rate' => '7.1234',
                'sys_draw_rate' => '7.2345',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame('7.1234', DB::table('system_configs')->where('key', 'sys_deposit_rate')->value('value'));
        $this->assertSame('7.2345', DB::table('system_configs')->where('key', 'sys_draw_rate')->value('value'));
    }

    public function test_legacy_whpj_rate_save_fails_closed_without_rates(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/whpj_rate_save')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_rights_summary_search_returns_modern_envelope(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/rightsSummarySearch')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('records', $payload['data']);
        $this->assertArrayHasKey('summary', $payload['data']);
    }

    public function test_legacy_rights_sum_export_returns_csv_stream(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/rightsSumExport')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=rights_summary_export.csv');
    }

    public function test_legacy_rights_summary_search_exposes_old_rows_and_fields(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984221;
        $now = time();
        DB::table('rights_settlements')->insert([
            'user_id' => $userId,
            'amount' => '123.45',
            'status' => 0,
            'remark' => 'legacy rights pending',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/rightsSummarySearch', [
                'userId' => $userId,
                'orderstatus' => '1',
                'rightsUserCycle' => 'weekly',
            ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.rightsUserId', $userId)
            ->assertJsonPath('rows.0.rightsSumStatus', 1)
            ->assertJsonPath('rows.0.rightsSumRealamt', '123.45000000')
            ->assertJsonPath('rows.0.realamt', '123.45000000');

        $this->assertArrayHasKey('records', $response->json('data'));
        $this->assertArrayHasKey('summary', $response->json('data'));
        $this->assertSame($userId, $response->json('data.rows.0.rightsUserId'));
    }

    public function test_legacy_rights_summary_detail_maps_uid_status_and_sum_date(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984222;
        $now = strtotime('2026-08-15 12:00:00');
        DB::table('rights_settlements')->insert([
            'user_id' => $userId,
            'amount' => '88.10',
            'status' => 1,
            'remark' => 'legacy rights detail',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/amount/rightsSummarySearchDetail/' . $userId . '/2/' . date('Ymd', $now))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.rightsUserId', $userId)
            ->assertJsonPath('rows.0.rightsSumStatus', 2)
            ->assertJsonPath('rows.0.rightsSumRealamt', '88.10000000')
            ->assertJsonPath('footer.0.rightsSumRealamt', '88.10000000');
    }

    public function test_legacy_rights_sum_export_contains_legacy_columns_and_message_header(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984223;
        DB::table('rights_settlements')->insert([
            'user_id' => $userId,
            'amount' => '66.60',
            'status' => 1,
            'remark' => 'legacy export row',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/amount/rightsSumExport', ['userId' => $userId])
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-legacy-export-message', 'rights_summary_export.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('rightsId,rightsUserId,rightsSumStatus,rightsSumDate,rightsSumRealamt,realamt,rightsSumRemarks', $content);
        $this->assertStringContainsString((string) $userId, $content);
        $this->assertStringContainsString('66.60000000', $content);
    }

    private function seedWithdrawRecord(int $userId, string $localOrderNo, int $status, string $fundingStatus)
    {
        $now = time();

        DB::table('withdraw_records')->where('local_order_no', $localOrderNo)->delete();

        $id = DB::table('withdraw_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'legacy-amount-' . $userId,
            'mt4_ticket' => '',
            'apply_amount' => '25.00',
            'actual_amount' => '24.00',
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
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (object) ['id' => $id];
    }

    private function seedDepositImport(int $userId, string $batchNo): void
    {
        $now = time();

        DB::table('deposit_imports')->updateOrInsert(
            ['batch_no' => $batchNo, 'user_id' => $userId],
            [
                'user_name' => 'legacy-deposit-import',
                'amount' => 123.45,
                'remarks' => 'closure',
                'mt4_order_id' => 0,
                'is_synced' => 0,
                'fail_reason' => '',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function seedWithdrawImport(int $userId, string $batchNo): void
    {
        $now = time();

        DB::table('withdraw_imports')->updateOrInsert(
            ['batch_no' => $batchNo, 'user_id' => $userId],
            [
                'user_name' => 'legacy-withdraw-import',
                'amount' => 67.89,
                'remarks' => 'closure',
                'mt4_order_id' => 0,
                'is_synced' => 0,
                'fail_reason' => '',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function seedImportUser(int $userId, string $userName): void
    {
        $now = time();
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
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

    private function csvUpload(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy_batch_csv_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'legacy-batch.csv', 'text/csv', null, true);
    }

    private function bindDepositGateway(array &$calls): void
    {
        app()->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 入金结算替身的调用捕获表。deposit() 记下 [userId, amount, comment] 并返回预设结果。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment];

                return DepositSettlementResult::settled((string) $userId);
            }
        });
    }

    private function bindWithdrawGateway(array &$calls): void
    {
        app()->instance(DepositRefundGateway::class, new class($calls) implements DepositRefundGateway {
            /**
             * 出金退款替身的调用捕获表。refund() 记下 [userId, amount, comment] 并返回预设结果。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            public function refund(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment];

                return DepositSettlementResult::settled((string) $userId);
            }
        });
    }

    private function seedScopedAdmin(int $adminId, int $roleId, array $userIds): Admin
    {
        $now = time();
        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            [
                'name' => 'legacy_batch_scope_' . $roleId,
                'guard_type' => 'admin',
                'description' => 'Legacy batch scope test role',
                'permissions' => null,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('role_data_scopes')->updateOrInsert(
            ['role_id' => $roleId],
            [
                'scope_type' => 'custom_users',
                'agent_ids' => null,
                'user_ids' => json_encode($userIds),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'role_id' => (string) $roleId,
                'mobile' => null,
                'email' => 'legacy-batch-' . $adminId . '@example.test',
                'username' => 'legacy_batch_' . $adminId,
                'password' => bcrypt('password'),
                'login_count' => 0,
                'status' => 1,
                'created_by' => 'test',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail($adminId);
    }

    private function seedCreditImport(int $userId, string $batchNo): void
    {
        $now = time();

        DB::table('credit_imports')->updateOrInsert(
            ['batch_no' => $batchNo, 'user_id' => $userId],
            [
                'user_name' => 'legacy-credit-import',
                'amount' => 99.00,
                'remarks' => 'closure',
                'mt4_order_id' => 0,
                'is_synced' => 0,
                'fail_reason' => '',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
