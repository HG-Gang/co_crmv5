<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/15
 * Time: 23:45
 */

/**
 * AdminLegacyCustChangeSearchClosureTest
 *
 * 文件功能：
 * - 验证旧后台客户转组申请列表闭环：查询 trans_apply_log 并补充余额与未平仓量，保留 V1 rows/total 与 V2 count/data/totalRow 两种旧契约，非法状态与倒置日期被拒绝，数据范围失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use App\Services\LegacyAdminCustomerChangeSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * 后台旧 Customer 转组申请列表闭环测试。
 *
 * 旧项目 custChangeListSearch/V2 查询 trans_apply_log，而不是普通用户列表，
 * 并为每条申请补充余额与未平仓量；两种旧响应契约分别保留 rows/total 与
 * count/data/totalRow。
 */
class AdminLegacyCustChangeSearchClosureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_v1_search_reads_pending_change_logs_and_adds_balance_and_open_volume(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = 984201;
        $otherId = 984202;
        $this->seedCustomer($targetId, 'legacy change target', 123.45);
        $this->seedCustomer($otherId, 'legacy change other', 999.99);
        $this->seedOpenTrades($targetId, 2);
        $this->insertApply($targetId, 0, 'target reason', time() - 60);
        $this->insertApply($otherId, 1, 'already approved', time() - 30);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custChangeListSearch', [
                'userId' => $targetId,
                'trans_apply_status' => 0,
                'page' => 1,
                'rows' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.transUid', $targetId)
            ->assertJsonPath('rows.0.transApplyStatus', 0)
            ->assertJsonPath('rows.0.transApplyReason', 'target reason')
            ->assertJsonPath('rows.0.bal', '123.45')
            ->assertJsonPath('rows.0.vol', 2);

        $this->assertStringNotContainsString((string) $otherId, $response->getContent());
    }

    public function test_v2_search_preserves_legacy_count_data_and_total_row_contract(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = 984203;
        $this->seedCustomer($targetId, 'legacy change v2 target', 77.7);
        $this->insertApply($targetId, -1, 'rejected reason', time());

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custChangeListSearchV2', [
                'userId' => $targetId,
                'trans_apply_status' => -1,
                'page' => 1,
                'rows' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.transUid', $targetId)
            ->assertJsonPath('data.0.transApplyStatus', -1)
            ->assertJsonPath('data.0.bal', '77.70')
            ->assertJsonPath('totalRow', []);
    }

    public function test_search_rejects_invalid_status_and_reversed_date_range(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custChangeListSearch', [
                'trans_apply_status' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custChangeListSearchV2', [
                'startdate' => '2026-08-15',
                'enddate' => '2026-08-14',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_search_applies_admin_user_data_scope_and_fails_closed_for_empty_scope(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('apply')
            ->once()
            ->withArgs(function ($query, $actualAdmin, $targetType, $userIdColumn) use ($admin): bool {
                return (int) $actualAdmin->id === (int) $admin->id
                    && $targetType === 'user'
                    && $userIdColumn === 'trans_apply_logs.user_id';
            })
            ->andReturnUsing(function ($query) {
                return $query->whereRaw('1 = 0');
            });
        $this->app->instance(AdminDataScopeService::class, $scope);

        $result = app(LegacyAdminCustomerChangeSearchService::class)->search([], $admin);

        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['total']);
    }

    private function seedCustomer(int $userId, string $name, float $balance): void
    {
        $now = time();
        DB::table('trans_apply_logs')->where('user_id', $userId)->delete();
        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-cust-change-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $name,
            'phone' => '178000' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'total_funds' => $balance,
            'equity' => $balance,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertApply(int $userId, int $status, string $reason, int $createdAt): void
    {
        DB::table('trans_apply_logs')->insert([
            'user_id' => $userId,
            'origin_group_id' => 1,
            'group_id' => 2,
            'group_name' => 'target-group',
            'applicant_id' => 900001,
            'applicant_name' => 'legacy admin applicant',
            'status' => $status,
            'apply_reason' => $reason,
            'reject_reason' => $status === -1 ? $reason : '',
            'created_by' => 'fixture',
            'updated_by' => '',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ]);
    }

    private function seedOpenTrades(int $userId, int $count): void
    {
        $now = date('Y-m-d H:i:s');
        for ($index = 1; $index <= $count; $index++) {
            DB::table('user_trades')->insert([
                'user_id' => $userId,
                'ticket' => $userId + $index,
                'symbol' => 'EURUSD',
                'digits' => 5,
                'cmd' => $index - 1,
                'volume' => 100,
                'open_time' => $now,
                'open_price' => 1.1,
                'close_time' => '1970-01-01 00:00:00',
                'modify_time' => $now,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]);
        }
    }
}
