<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:06
 */

/**
 * Phase2Task5LegacyAdminCustomerPageClosureTest
 *
 * 文件功能：
 * - 验证 Phase2 旧后台客户页契约：新增页仅列出启用的客户分组并保留旧表单字段、详情页渲染资料与财务快照、代理与未知用户拒绝、数据范围外客户拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\AdminDataScopeService;
use App\Constants\ResponseCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 2 Task 5: lock the old CustomerController page contracts.
 */
final class Phase2Task5LegacyAdminCustomerPageClosureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_add_page_lists_only_enabled_customer_groups_and_keeps_legacy_form_fields(): void
    {
        $admin = $this->ensureSuperAdmin();
        $activeCustomerGroup = 'Legacy Add Active Customer Group';
        $disabledCustomerGroup = 'Legacy Add Disabled Customer Group';
        $agentGroup = 'Legacy Add Agent Group';
        $this->seedGroup($activeCustomerGroup, 2, 1);
        $this->seedGroup($disabledCustomerGroup, 2, 0);
        $this->seedGroup($agentGroup, 1, 1);

        $response = $this->actingAs($admin, 'admin')
            ->get('/index/admin/cust/add');

        $response->assertOk()
            ->assertViewIs('admin_layui::users.customer-add')
            ->assertSee('data-legacy-customer-add', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="userIdcardNo"', false)
            ->assertSee('name="userInviterId"', false)
            ->assertSee('name="usergrpId"', false)
            ->assertSee('name="usergrpName"', false)
            ->assertSee((string) $activeCustomerGroup, false)
            ->assertDontSee((string) $disabledCustomerGroup, false)
            ->assertDontSee((string) $agentGroup, false);
    }

    public function test_customer_detail_page_renders_customer_profile_and_financial_snapshot(): void
    {
        $admin = $this->ensureSuperAdmin();
        $parentId = $this->unusedUserId();
        $customerId = $this->unusedUserId();
        $groupName = 'Legacy Detail Customer Group';
        $groupId = $this->seedGroup($groupName, 2, 1);

        $this->seedUser($parentId, 1, 'Legacy Detail Parent Agent');
        $this->seedUser($customerId, 2, 'Legacy Detail Customer');
        DB::table('user_infos')->where('user_id', $parentId)->update([
            'parent_id' => 0,
            'family_tree' => (string) $parentId,
        ]);
        DB::table('user_infos')->where('user_id', $customerId)->update([
            'parent_id' => $parentId,
            'family_tree' => $parentId . ',' . $customerId,
            'group_id' => $groupId,
            'mt4_group' => $groupName,
            'total_funds' => 1234.56,
            'equity' => 1200.34,
            'is_mt4_readonly' => 1,
            'is_withdrawal_allowed' => 1,
            'is_deposit_allowed' => 0,
            'remark' => 'Legacy detail remark',
        ]);
        DB::table('user_auths')->insert([
            'user_id' => $customerId,
            'id_card_no' => 'LEGACY-DETAIL-ID',
            'id_card_status' => 2,
            'bank_no' => 'LEGACY-DETAIL-BANK',
            'bank_name' => 'Legacy Detail Bank',
            'bank_addr' => 'Legacy Detail Branch',
            'bank_status' => 2,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get('/index/admin/cust/cust_detail/' . $customerId);

        $response->assertOk()
            ->assertViewIs('admin_layui::users.customer-detail')
            ->assertSee('data-legacy-customer-detail', false)
            ->assertSee('Legacy Detail Customer')
            ->assertSee('legacy-detail-' . $customerId . '@example.test')
            ->assertSee('13' . substr((string) $customerId, -9))
            ->assertSee('LEGACY-DETAIL-ID')
            ->assertSee('LEGACY-DETAIL-BANK')
            ->assertSee('Legacy Detail Bank')
            ->assertSee('Legacy Detail Parent Agent')
            ->assertSee($groupName)
            ->assertSee('1234.56')
            ->assertSee('1200.34')
            ->assertSee('Legacy detail remark')
            ->assertSee('index/admin/cust/cust_save_info', false);
    }

    public function test_customer_detail_page_rejects_agents_and_unknown_users(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentId = $this->unusedUserId();
        $this->seedUser($agentId, 1, 'Not a customer detail target');

        $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/cust/cust_detail/' . $agentId)
            ->assertNotFound();

        $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/cust/cust_detail/' . $this->unusedUserId())
            ->assertNotFound();
    }

    public function test_customer_detail_page_rejects_customer_outside_admin_data_scope(): void
    {
        $visibleAgentId = $this->unusedUserId();
        $hiddenAgentId = $this->unusedUserId();
        $customerId = $this->unusedUserId();
        $this->seedUser($visibleAgentId, 1, 'Visible scope agent');
        $this->seedUser($hiddenAgentId, 1, 'Hidden scope agent');
        $this->seedUser($customerId, 2, 'Hidden scope customer');
        DB::table('user_infos')->where('user_id', $customerId)->update([
            'parent_id' => $hiddenAgentId,
            'family_tree' => $hiddenAgentId . ',' . $customerId,
        ]);
        $admin = $this->createAgentTreeAdmin($visibleAgentId);

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/cust/cust_detail/' . $customerId);

        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 404], true)
                || (int) $response->json('code') === ResponseCode::PERMISSION_DENIED,
            'Unexpected scoped detail response: ' . $response->getStatusCode() . ' ' . $response->getContent()
        );
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'phase2-task5-super',
                'email' => 'phase2-task5-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'role_id' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createAgentTreeAdmin(int $visibleAgentId): Admin
    {
        $now = time();
        $token = bin2hex(random_bytes(5));
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'phase2-task5-scope-' . $token,
            'guard_type' => 'admin',
            'description' => 'Phase2 Task5 route scope fixture',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->insert([
            'role_id' => $roleId,
            'scope_type' => 'agent_tree',
            'agent_ids' => null,
            'user_ids' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $adminId = DB::table('admins')->insertGetId([
            'username' => 'phase2-task5-scope-' . $token,
            'email' => 'phase2-task5-scope-' . $token . '@example.test',
            'password' => Hash::make('password'),
            'role_id' => $roleId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admin_agent_bindings')->insert([
            'admin_id' => $adminId,
            'agent_id' => $visibleAgentId,
            'binding_type' => 'primary',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }

    private function seedGroup(string $name, int $category, int $enabled): int
    {
        $now = time();

        return (int) DB::table('group_configs')->insertGetId([
            'name' => $name,
            'radix' => 50,
            'category' => $category,
            'has_commission' => 0,
            'is_enabled' => $enabled,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedUser(int $userId, int $accountType, string $name): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-detail-' . $userId . '@example.test',
            'password' => Hash::make('OriginalA123'),
            'account_type' => $accountType,
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
            'phone' => '86-13' . substr((string) $userId, -9),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function unusedUserId(): int
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidate = random_int(1200000000, 1900000000);
            if (!DB::table('user_logins')->where('user_id', $candidate)->exists()
                && !DB::table('user_infos')->where('user_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to allocate a Phase2 Task5 fixture user ID.');
    }
}
