<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 13:33
 */

/**
 * AdminLegacyWhsExpZeroParityClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台仓位清零双口径等价：一键搜索生成单条待处理记录与旧 envelope、不重复登记活跃记录、V1/V2 查询真实清零记录、倒置日期在查询前拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLegacyWhsExpZeroParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_one_key_search_creates_one_pending_record_and_old_envelope(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987701;
        $this->seedUser($userId, 'Legacy WHS Scan Target', -85.50, 10.25);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeySearch', ['userId' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'noerr')
            ->assertJsonPath('col', 1);

        $this->assertDatabaseHas('whs_exp_zeros', [
            'user_id' => $userId,
            'user_name' => 'Legacy WHS Scan Target',
            'balance' => -85.50,
            'credit' => 10.25,
            'status' => 1,
        ]);
    }

    public function test_one_key_search_does_not_duplicate_an_active_record(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987702;
        $this->seedUser($userId, 'Legacy WHS Existing Pending', -40.00, 0);
        $this->seedRecord($userId, 'Legacy WHS Existing Pending', -40.00, 0, 1, strtotime('2026-08-18 10:00:00'));

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeySearch', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'zerofail')
            ->assertJsonPath('col', 0);

        $this->assertSame(1, DB::table('whs_exp_zeros')->where('user_id', $userId)->count());
    }

    public function test_v1_and_v2_search_real_zero_records_with_old_fields_and_dates(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = 987703;
        $otherId = 987704;
        $this->seedUser($targetId, 'Legacy WHS Record Target', -30.00, 5.00);
        $this->seedUser($otherId, 'Legacy WHS Record Other', -20.00, 0);
        $this->seedRecord($targetId, 'Legacy WHS Record Target', -30.00, 5.00, 2, strtotime('2026-08-18 09:30:00'));
        $this->seedRecord($otherId, 'Legacy WHS Record Other', -20.00, 0, 1, strtotime('2026-08-17 09:30:00'));

        $filters = [
            'wez_userid' => $targetId,
            'wez_username' => 'Record Target',
            'wez_status' => 2,
            'startdate' => '2026-08-18',
            'enddate' => '2026-08-18',
        ];

        $v1 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/whsExpZeroListSearch', $filters)
            ->assertOk()
            ->assertJsonPath('rows.0.wezuserid', $targetId)
            ->assertJsonPath('rows.0.wezusername', 'Legacy WHS Record Target')
            ->assertJsonPath('rows.0.wezuserbal', '-30.00')
            ->assertJsonPath('rows.0.wezusercrt', '5.00')
            ->assertJsonPath('rows.0.wezstatus', 2);
        $this->assertSame('', $v1->json('total'));

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/whsExpZeroListSearchV2', $filters)
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'Request data successful.')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.wezuserid', $targetId)
            ->assertJsonPath('totalRow', []);
    }

    public function test_legacy_record_search_rejects_reversed_dates_before_query(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/whsExpZeroListSearchV2', [
                'startdate' => '2026-08-19',
                'enddate' => '2026-08-18',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    private function seedUser(int $userId, string $name, float $balance, float $credit): void
    {
        $now = time();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => $name,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'total_funds' => $balance,
            'equity' => $balance,
            'effective_credit' => $credit,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedRecord(
        int $userId,
        string $name,
        float $balance,
        float $credit,
        int $status,
        int $createdAt
    ): void {
        DB::table('whs_exp_zeros')->insert([
            'user_id' => $userId,
            'user_name' => $name,
            'balance' => $balance,
            'credit' => $credit,
            'status' => $status,
            'md5_key' => md5($userId . '|' . $createdAt),
            'created_by' => '1',
            'updated_by' => '1',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ]);
    }
}
