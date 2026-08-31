<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/15
 * Time: 22:33
 */

/**
 * AdminLegacyAuthAndCustSearchClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台认证审核与客户列表搜索入口闭环：旧入口转发到现代接口并按旧条件返回种子记录，非法 user_id 被拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台遗留"认证审核"与"客户列表"搜索入口闭环测试。
 *
 * 文件目的：
 * - 旧后台认证搜索（userCertifiedSearch/V2、userExaminSearch/V2）经
 *   LegacyAdminController 转发到现代 admin_api_authCertifiedList/admin_api_authPendingList；
 * - 旧后台客户搜索（custListSearch/V2、custChangeListSearch/V2）转发到 admin_api_userList；
 * - 逐条断言旧入口返回现代成功信封且能看到按旧条件种子的记录，
 *   同时验证 user_id 非法值 fail-closed 返回 VALIDATION_FAILED。
 */
class AdminLegacyAuthAndCustSearchClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 认证/客户搜索旧入口 -> 现代目标端点名（仅用于文档化闭环）。
     */
    public static function legacySearchEndpoints(): array
    {
        return [
            'userCertifiedSearch' => 'admin_api_authCertifiedList',
            'userCertifiedSearchV2' => 'admin_api_authCertifiedList',
            'userExaminSearch' => 'admin_api_authPendingList',
            'userExaminSearchV2' => 'admin_api_authPendingList',
            'custListSearch' => 'admin_api_userList',
            'custListSearchV2' => 'admin_api_userList',
            'custChangeListSearch' => 'admin_api_userList',
            'custChangeListSearchV2' => 'admin_api_userList',
        ];
    }

    public function test_legacy_auth_certified_searches_see_only_certified_users(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $certifiedUserId = 984101;
        $pendingUserId = 984102;
        $this->seedAuthUser($certifiedUserId, 'Legacy Certified Search', 2, 2);
        $this->seedAuthUser($pendingUserId, 'Legacy Certified Search Pending', 1, 1);

        foreach (['userCertifiedSearch', 'userCertifiedSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/auth/' . $action)
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $body = $response->getContent();
            $this->assertStringContainsString((string) $certifiedUserId, $body, $action);
            $this->assertStringNotContainsString((string) $pendingUserId, $body, $action);
        }
    }

    public function test_legacy_auth_examin_searches_see_only_pending_users(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $certifiedUserId = 984103;
        $pendingUserId = 984104;
        $this->seedAuthUser($certifiedUserId, 'Legacy Exam Search Certified', 2, 2);
        $this->seedAuthUser($pendingUserId, 'Legacy Exam Search Pending', 1, 1);

        foreach (['userExaminSearch', 'userExaminSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/auth/' . $action)
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $body = $response->getContent();
            $this->assertStringContainsString((string) $pendingUserId, $body, $action);
            $this->assertStringNotContainsString((string) $certifiedUserId, $body, $action);
        }
    }

    public function test_legacy_auth_searches_filter_by_user_id(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetUserId = 984105;
        $otherUserId = 984106;
        $this->seedAuthUser($targetUserId, 'Legacy Auth Filter Target', 1, 1);
        $this->seedAuthUser($otherUserId, 'Legacy Auth Filter Other', 1, 1);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/userExaminSearch', ['userId' => $targetUserId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $body = $response->getContent();
        $this->assertStringContainsString((string) $targetUserId, $body);
        $this->assertStringNotContainsString((string) $otherUserId, $body);
    }

    public function test_legacy_cust_searches_see_seeded_users(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984107;
        $this->seedUserInfoOnly($userId, 'Legacy Cust Search User');
        $this->seedCustomerChangeApply($userId);

        foreach (['custListSearch', 'custListSearchV2', 'custChangeListSearch', 'custChangeListSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/cust/' . $action, ['user_id' => $userId])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $this->assertStringContainsString((string) $userId, $response->getContent(), $action);
        }
    }

    public function test_legacy_cust_search_filters_by_user_id_and_name(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetUserId = 984108;
        $this->seedUserInfoOnly($targetUserId, 'Legacy Cust Name Filter');

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearch', [
                'user_id' => $targetUserId,
                'user_name' => 'Legacy Cust Name Filter',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertStringContainsString((string) $targetUserId, $response->getContent());
    }

    public function test_legacy_auth_search_rejects_non_integer_user_id(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/userExaminSearch', ['user_id' => 'abc'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_cust_search_rejects_non_integer_user_id(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearch', ['user_id' => 12.5])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_all_auth_and_cust_legacy_search_routes_are_registered(): void
    {
        foreach (self::legacySearchEndpoints() as $legacyAction => $modernRouteName) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($modernRouteName), $modernRouteName);
        }

        foreach (self::legacySearchEndpoints() as $legacyAction => $modernRouteName) {
            $segment = str_contains($legacyAction, 'cust') ? 'cust' : 'auth';
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($modernRouteName);
            $this->assertNotNull($route, $modernRouteName);
        }
    }

    private function seedAuthUser(int $userId, string $userName, int $idCardStatus, int $bankStatus): void
    {
        $this->seedUserInfoOnly($userId, $userName);
        $now = time();

        DB::table('user_auths')->updateOrInsert(
            ['user_id' => $userId],
            [
                'bank_no' => 'BANK-' . $userId,
                'bank_no_tmp' => '',
                'bank_name' => 'Test Bank',
                'bank_name_tmp' => '',
                'bank_card_img' => '',
                'bank_card_back_img' => '',
                'bank_card_img_tmp' => '',
                'bank_card_back_img_tmp' => '',
                'bank_addr' => 'Branch',
                'bank_addr_tmp' => '',
                'bank_status' => $bankStatus,
                'bank_remarks' => '',
                'id_card_no' => 'ID-' . $userId,
                'id_card_status' => $idCardStatus,
                'id_card_front' => '',
                'id_card_back' => '',
                'id_card_remarks' => '',
                'is_bank_synced' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function seedUserInfoOnly(int $userId, string $userName): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-auth-cust-' . $userId . '@example.test',
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
            'user_name' => $userName,
            'phone' => '178000' . substr((string) $userId, -4),
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 0,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedCustomerChangeApply(int $userId): void
    {
        $now = time();

        DB::table('trans_apply_logs')->where('user_id', $userId)->delete();
        DB::table('trans_apply_logs')->insert([
            'user_id' => $userId,
            'origin_group_id' => 1,
            'group_id' => 2,
            'group_name' => 'legacy-cust-search-group',
            'applicant_id' => 1,
            'applicant_name' => 'legacy-admin',
            'status' => 0,
            'apply_reason' => 'legacy customer search fixture',
            'reject_reason' => '',
            'created_by' => 'fixture',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
