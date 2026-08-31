<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:23
 */

/**
 * AdminLegacyAuthenticationVoucherClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台实名认证、银行卡与凭证审核入口闭环：V1/V2 行与旧字段别名、缺失或越权记录失败关闭、驳回必须给出理由、重复审核被拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Task 5：旧后台实名认证、银行卡和凭证审核入口的闭环契约。
 *
 * 这些断言刻意从旧 Blade 的真实字段开始，避免只验证现代 API 能返回 200，
 * 却遗漏旧 V1/V2 表格、详情页和审核回执所依赖的字段与权限边界。
 */
class AdminLegacyAuthenticationVoucherClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_auth_searches_return_v1_and_v2_rows_with_old_field_aliases(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 988501;
        $this->seedAuthUser($userId, 'Task 5 Auth Search', 1, 3);

        $v1 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/userExaminSearch', [
                'userId' => $userId,
                'startdate' => '2026-08-01',
                'enddate' => '2026-08-31',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->json();

        $this->assertSame($userId, (int) $v1['rows'][0]['user_id']);
        $this->assertSame(1, (int) $v1['rows'][0]['IDcard_status']);
        $this->assertSame(3, (int) $v1['rows'][0]['bank_status']);
        $this->assertSame(1, (int) $v1['total']);

        $v2 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/userExaminSearchV2', ['userId' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->json();

        $this->assertSame(1, (int) $v2['count']);
        $this->assertSame($userId, (int) $v2['data'][0]['user_id']);
        $this->assertSame(1, (int) $v2['data'][0]['IDcard_status']);
        $this->assertSame($v2['data'][0]['created_at'], $v2['data'][0]['rec_crt_date']);
    }

    public function test_legacy_voucher_search_filters_user_and_date_and_returns_old_aliases(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 988502;
        $otherUserId = 988503;
        $this->seedUser($userId, 'Task 5 Voucher User');
        $this->seedUser($otherUserId, 'Task 5 Other Voucher User');
        $this->insertVoucher($userId, strtotime('2026-08-05 10:00:00'), 'voucher-a.jpg');
        $this->insertVoucher($otherUserId, strtotime('2026-09-05 10:00:00'), 'voucher-b.jpg');

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/voucherInfoSearchV2', [
                'userId' => $userId,
                'startdate' => '2026-08-01',
                'enddate' => '2026-08-31',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->json();

        $this->assertSame(1, (int) $response['count']);
        $this->assertCount(1, $response['data']);
        $this->assertSame($userId, (int) $response['data'][0]['user_id']);
        $this->assertSame('Task 5 Voucher User', $response['data'][0]['user_name']);
        $this->assertSame($response['data'][0]['review_message'], $response['data'][0]['review_msg']);
        $this->assertSame($response['data'][0]['created_at'], $response['data'][0]['rec_crt_date']);
        $this->assertSame('voucher-a.jpg', json_decode($response['data'][0]['images'], true)[0]);
    }

    public function test_legacy_auth_detail_and_voucher_detail_fail_closed_for_missing_or_out_of_scope_records(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 988504;
        $this->seedAuthUser($userId, 'Task 5 Detail User', 1, 1);
        $voucherId = $this->insertVoucher($userId, time(), 'detail.jpg');

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/auth/user_certified_detail/999999991')
            ->assertNotFound();

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/auth/user_voucher/detail/' . $voucherId . '/' . ($userId + 1))
            ->assertNotFound();

        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('canAccessUser')->atLeast()->once()->andReturnFalse();
        $this->app->instance(AdminDataScopeService::class, $scope);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/auth/user_examine/detail/auth/' . $userId)
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/auth/user_voucher/detail/' . $voucherId . '/' . $userId)
            ->assertForbidden();
    }

    public function test_legacy_voucher_detail_renders_its_record_and_reject_requires_a_reason(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 988505;
        $this->seedUser($userId, 'Task 5 Voucher Detail');
        $voucherId = $this->insertVoucher($userId, time(), 'detail-front.jpg', 'detail-back.jpg');

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/auth/user_voucher/detail/' . $voucherId . '/' . $userId)
            ->assertOk()
            ->assertViewIs('admin_layui::vouchers.detail')
            ->assertSee('data-voucher-detail-page="1"', false)
            ->assertSee('detail-front.jpg', false)
            ->assertSee('detail-back.jpg', false);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/voucherReviewSave', [
                'recId' => $voucherId,
                'userId' => $userId,
                'reviewstatus' => 2,
                'reviewmsg' => '   ',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('msg', 'FAIL');

        $this->assertDatabaseHas('voucher_infos', [
            'id' => $voucherId,
            'review_status' => 0,
        ]);
    }

    public function test_legacy_review_mutations_return_old_success_fields_and_reject_duplicates(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 988506;
        $this->seedAuthUser($userId, 'Task 5 Review User', 1, 1);
        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'task5', '1', 1);
            }

            public function updateComment($userId, $comment)
            {
                return ['status' => 'ok', 'message' => 'updated', 'data' => []];
            }
        });

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'userId' => $userId,
                'idcard_auth' => '0',
                'bank_auth' => '0',
                'userIdcard_status' => '1',
                'userbank_status' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'NOERR');

        $voucherId = $this->insertVoucher($userId, time(), 'review.jpg');
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/voucherReviewSave', [
                'recId' => $voucherId,
                'userId' => $userId,
                'reviewstatus' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'NOERR');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/voucherReviewSave', [
                'recId' => $voucherId,
                'userId' => $userId,
                'reviewstatus' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND)
            ->assertJsonPath('msg', 'FAIL');
    }

    private function seedAuthUser(int $userId, string $name, int $idCardStatus, int $bankStatus): void
    {
        $this->seedUser($userId, $name);
        $now = time();
        DB::table('user_auths')->updateOrInsert(['user_id' => $userId], [
            'bank_no' => 'BANK-' . $userId,
            'bank_no_tmp' => '',
            'bank_name' => 'Task 5 Bank',
            'bank_name_tmp' => '',
            'bank_addr' => 'Task 5 Branch',
            'bank_addr_tmp' => '',
            'bank_card_img' => '',
            'bank_card_back_img' => '',
            'bank_card_img_tmp' => '',
            'bank_card_back_img_tmp' => '',
            'bank_status' => $bankStatus,
            'bank_remarks' => '',
            'id_card_no' => 'ID-' . $userId,
            'id_card_front' => '',
            'id_card_back' => '',
            'id_card_status' => $idCardStatus,
            'id_card_remarks' => '',
            'is_bank_synced' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedUser(int $userId, string $name): void
    {
        $now = time();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'task5-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $name,
            'phone' => '86-139' . substr((string) $userId, -8),
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertVoucher(int $userId, int $createdAt, string ...$images): int
    {
        return (int) DB::table('voucher_infos')->insertGetId([
            'user_id' => $userId,
            'images' => json_encode($images, JSON_UNESCAPED_SLASHES),
            'remarks' => 'Task 5 voucher remarks',
            'review_status' => 0,
            'review_message' => '',
            'created_by' => 'task5',
            'updated_by' => '',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ]);
    }
}
