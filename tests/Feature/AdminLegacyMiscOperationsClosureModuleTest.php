<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/21
 * Time: 21:47
 */

/**
 * AdminLegacyMiscOperationsClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台零散操作闭环：销户拒绝、礼品地址/发货/导出/发放、组别与用户分组增改、新闻 CRUD、在线用户搜索、角色增改、个人资料与密码维护、风控 IP 与大代理商保存。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台遗留"注销/礼品/组别/新闻/在线用户/角色/个人资料/风控IP"操作闭环测试。
 *
 * 文件目的：
 * - cancel_apply_nopass 按业务 user_id 定位待处理申请并拒绝；
 * - gift 地址列表/发货列表/导出/发送、group 组别增改、user_group 搜索与增改；
 * - news 列表/新增/更新/删除、online 在线用户搜索、role 角色增改、
 *   userinfo/save 与 userpwd/save 个人资料与密码维护；
 * - fengXian/IpaddressSearch 同 IP 多账号风险列表；
 * - 逐条断言旧入口转发现代端点后的成功信封、数据可见性与 fail-closed 校验。
 */
class AdminLegacyMiscOperationsClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_cancel_nopass_rejects_pending_apply(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984401;
        $applyId = $this->seedCancelApply($userId);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/cancel_apply_nopass', [
                'cancel_userid' => $userId,
                'reason' => 'legacy reject reason',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('cancel_applies', [
            'id' => $applyId,
            'status' => -1,
            'reject_reason' => 'legacy reject reason',
        ]);
    }

    public function test_legacy_cancel_nopass_fails_closed_without_business_user_id(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/cancel_apply_nopass')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_cancel_userlist_searches_see_seeded_applies(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984402;
        $this->seedCancelApply($userId);

        // 旧 V1 列表保留 rows/total 信封，旧 V2 列表保留 code=200 的 Layui 信封；
        // 两个入口都使用旧字段 userId 精确过滤，并能看到同一条待处理申请。
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/userlistSearch', ['userId' => $userId])
            ->assertOk()
            ->assertJsonPath('rows.0.cancel_userid', $userId)
            ->assertJsonPath('total', 1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/userlistSearchV2', ['userId' => $userId])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.cancel_userid', $userId);
    }

    public function test_legacy_gift_address_list_sees_seeded_address(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984403;
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-gift-addr-' . $userId . '@example.test',
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
            'user_name' => 'Legacy Gift Address User',
            'phone' => '178984403',
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'is_gift_allowed' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_addresses')->updateOrInsert(
            ['user_id' => $userId],
            [
                'recipient_name' => 'Gift Recipient',
                'recipient_phone' => '13800009844',
                'recipient_address' => 'Legacy Gift Address',
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/gift/addressList', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertStringContainsString('Legacy Gift Address', $response->getContent());
    }

    public function test_legacy_gift_shipment_list_and_export_see_seeded_shipments(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984404;
        $this->seedGiftShipment($userId);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/gift/shipment_list', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertStringContainsString('GIFT-SHIP-984404', $response->getContent());

        $export = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/gift/shipment_list_export', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', 0);
        $path = (string) $export->json('data.path');
        $this->assertNotSame('', $path);
        $this->actingAs($admin, 'admin')
            ->get('/' . ltrim($path, '/'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_legacy_gift_send_creates_shipment_for_seeded_user(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984405;
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-gift-' . $userId . '@example.test',
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
            'user_name' => 'Legacy Gift User',
            'phone' => '178984405',
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'is_gift_allowed' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $addressId = DB::table('user_addresses')->insertGetId([
            'user_id' => $userId,
            'recipient_name' => 'Send Gift Recipient',
            'recipient_phone' => '13800009845',
            'recipient_address' => 'Send Gift Address',
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/gift/send_gift', [
                'giftInfo' => [
                    'sender_name' => 'Legacy Admin',
                    'gift_name' => 'Closure Gift',
                    'gift_quantity' => 2,
                    'tracking_number' => 'TRK-984405',
                ],
                'recipients' => [[
                    'user_id' => $userId,
                    'rec_id' => $addressId,
                    'recipient_name' => 'Send Gift Recipient',
                    'recipient_phone' => '13800009845',
                    'recipient_address' => 'Send Gift Address',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertDatabaseHas('gift_shipments', [
            'user_id' => $userId,
            'gift_name' => 'Closure Gift',
            'tracking_number' => 'TRK-984405',
        ]);
    }

    public function test_legacy_gift_send_fails_closed_without_recipients(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/gift/send_gift', [
                'sender_name' => 'Legacy Admin',
                'gift_name' => 'Closure Gift',
                'gift_quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', 5000);
    }

    public function test_legacy_group_store_creates_group_config(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $name = 'Legacy Group ' . time();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/store', [
                'group_name' => $name,
                'group_type' => 2,
                'group_id' => 0,
                'group_enable' => 1,
                'is_default' => 0,
                'is_enc' => 0,
                'radix' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $this->assertDatabaseHas('group_configs', [
            'name' => $name,
            'category' => 2,
            'radix' => 5,
        ]);
    }

    public function test_legacy_group_update_updates_group_config(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $now = time();
        $id = DB::table('group_configs')->insertGetId([
            'name' => 'Legacy Group Update ' . $now,
            'radix' => 3,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/update', [
                'id' => $id,
                'group_name' => 'Legacy Group Renamed ' . $now,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame('Legacy Group Renamed ' . $now, DB::table('group_configs')->where('id', $id)->value('name'));
    }

    public function test_legacy_group_user_group_searches_see_seeded_groups(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $now = time();
        $name = 'Legacy User Group ' . $now;

        DB::table('group_configs')->where('name', $name)->delete();
        DB::table('group_configs')->insert([
            'name' => $name,
            'radix' => 1,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $v1Response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/user_group_search', ['group_type' => 2])
            ->assertOk();
        $this->assertArrayNotHasKey('code', $v1Response->json());
        $this->assertStringContainsString($name, $v1Response->getContent(), 'user_group_search');

        $v2Response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/user_group_searchV2', ['group_type' => 2])
            ->assertOk()
            ->assertJsonPath('code', 200);
        $this->assertStringContainsString($name, $v2Response->getContent(), 'user_group_searchV2');
    }

    public function test_legacy_group_user_group_store_creates_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $name = 'Legacy Stored User Group ' . time();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/user_group_store', [
                'group_name' => $name,
                'group_type' => 2,
                'group_id' => 0,
                'group_enable' => 1,
                'is_default' => 0,
                'is_enc' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::CREATED);

        $this->assertDatabaseHas('group_configs', ['name' => $name]);
    }

    public function test_legacy_group_user_group_update_updates_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $now = time();
        $id = DB::table('group_configs')->insertGetId([
            'name' => 'Legacy User Group To Update ' . $now,
            'radix' => 1,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/user_group_update', [
                'grp_recId' => $id,
                'group_enable' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::UPDATED);

        $this->assertSame(0, (int) DB::table('group_configs')->where('id', $id)->value('is_enabled'));
    }

    public function test_legacy_news_search_and_crud_roundtrip(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $now = time();
        $title = 'Legacy News ' . $now;

        DB::table('news')->where('title', $title)->delete();
        $id = DB::table('news')->insertGetId([
            'title' => $title,
            'content' => 'Legacy News Content',
            'image' => '',
            'author_id' => 0,
            'author_name' => 'test',
            'is_published' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/news/newsListSearch', [
                'title' => 'Legacy News ' . $now,
                'startdate' => date('Y-m-d', $now),
                'enddate' => date('Y-m-d', $now),
            ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.news_title', $title);
        $this->assertStringContainsString($title, $response->getContent());

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/news/news_save', [
                'newsTitle' => 'Legacy News Created ' . $now,
                'newsContent' => 'Legacy News Created Content',
                'ispush' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::CREATED);
        $this->assertDatabaseHas('news', ['title' => 'Legacy News Created ' . $now]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/news/news_update', [
                'newsId' => $id,
                'newsTitle' => $title,
                'newsContent' => 'Legacy News Updated Content',
                'ispush' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::UPDATED);
        $this->assertSame('Legacy News Updated Content', DB::table('news')->where('id', $id)->value('content'));

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/news/del', ['newsid' => $id])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::DELETED);
        $this->assertSame(0, DB::table('news')->where('id', $id)->whereNull('deleted_at')->count());
    }

    public function test_legacy_news_save_fails_closed_without_title(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/news/news_save', ['newsContent' => 'Missing Title'])
            ->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_online_search_sees_seeded_online_users(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984406;
        $now = time();

        $this->seedUserInfoOnly($userId, 'Legacy Online User');

        DB::table('user_onlines')->where('user_id', $userId)->delete();
        DB::table('user_onlines')->insert([
            'user_id' => $userId,
            'last_activity' => $now,
            'ip_address' => '203.0.113.66',
            'user_agent' => 'Legacy Online UA',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/online/search', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertStringContainsString((string) $userId, $response->getContent());
    }

    public function test_legacy_online_search_fails_closed_for_non_integer_user_id(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/online/search', ['user_id' => 'abc'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_role_addsave_creates_role(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $name = 'legacy_role_' . time();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/role/addsave', [
                'name' => $name,
                'guard_type' => 'admin',
                'description' => 'Legacy Role Created',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $this->assertDatabaseHas('roles', ['name' => $name, 'guard_type' => 'admin']);
    }

    public function test_legacy_role_editsave_updates_role(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $now = time();
        $name = 'legacy_role_update_' . $now;

        DB::table('roles')->where('name', $name)->delete();
        $id = DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_type' => 'admin',
            'description' => 'Before',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/role/editsave', [
                'id' => $id,
                'name' => $name,
                'guard_type' => 'admin',
                'description' => 'After Edit',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame('After Edit', DB::table('roles')->where('id', $id)->value('description'));
    }

    public function test_legacy_role_addsave_fails_closed_without_name(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/role/addsave', ['guard_type' => 'admin'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_userinfo_save_updates_admin_profile(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $email = 'legacy-profile-' . time() . '@example.test';

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/userinfo/save', [
                'email' => $email,
                'mobile' => '13900001111',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame($email, DB::table('admins')->where('id', $admin->id)->value('email'));
        $this->assertSame('13900001111', DB::table('admins')->where('id', $admin->id)->value('mobile'));
    }

    public function test_legacy_userpwd_save_changes_admin_password(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $oldPassword = 'legacy-old-password';
        $newPassword = 'legacy-new-password';

        DB::table('admins')->where('id', $admin->id)->update([
            'password' => Hash::make($oldPassword),
        ]);

        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/userpwd/save', [
                'old_password' => $oldPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertTrue(Hash::check($newPassword, DB::table('admins')->where('id', $admin->id)->value('password')));
    }

    public function test_legacy_userpwd_save_fails_closed_with_wrong_old_password(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/userpwd/save', [
                'old_password' => 'definitely-wrong',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OLD_PASSWORD_WRONG);
    }

    public function test_legacy_ipaddress_search_sees_same_ip_users(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $ip = '192.168.77.1';
        $now = time();

        DB::table('user_login_logs')->where('login_ip', $ip)->delete();
        DB::table('user_login_logs')->insert([
            'login_id' => 984407,
            'user_id' => 984407,
            'login_ip' => $ip,
            'ip_location' => 'Legacy Loc',
            'user_agent' => 'Legacy UA',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_login_logs')->insert([
            'login_id' => 984408,
            'user_id' => 984408,
            'login_ip' => $ip,
            'ip_location' => 'Legacy Loc',
            'user_agent' => 'Legacy UA',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/IpaddressSearch', ['login_ip' => '192.168.77'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.login_ip', $ip);

        $this->assertStringContainsString($ip, $response->getContent());
    }

    public function test_legacy_big_agents_save_fails_closed_without_required_fields(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/bigAgents/save')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_big_agents_update_info_fails_closed_without_id(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/bigAgents/updateInfo')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    private function seedCancelApply(int $userId): int
    {
        $now = time();

        DB::table('cancel_applies')->where('user_id', $userId)->delete();

        return (int) DB::table('cancel_applies')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'legacy-cancel-' . $userId,
            'status' => 0,
            'cancel_remark' => '',
            'reject_reason' => '',
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedGiftShipment(int $userId): void
    {
        $now = time();

        DB::table('gift_shipments')->where('user_id', $userId)->delete();

        DB::table('gift_shipments')->insert([
            'user_id' => $userId,
            'address_id' => 0,
            'recipient_name' => 'Shipment Recipient',
            'recipient_phone' => '13800009844',
            'recipient_address' => 'Shipment Address',
            'sender_name' => 'Legacy Sender',
            'gift_name' => 'GIFT-SHIP-984404',
            'gift_quantity' => 1,
            'tracking_number' => 'TRK-984404',
            'status' => 1,
            'remark' => '',
            'admin_id' => 0,
            'shipped_at' => date('Y-m-d H:i:s', $now),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedUserInfoOnly(int $userId, string $userName): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-misc-' . $userId . '@example.test',
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
            'phone' => '178' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
