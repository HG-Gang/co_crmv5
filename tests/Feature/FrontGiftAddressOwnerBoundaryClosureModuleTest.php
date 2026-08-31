<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 前台礼品地址属主边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户不能修改/删除其他用户的礼品地址（现代接口 PATCH/DELETE
 *   /api/front/gift-addresses/{address} 与遗留接口 /user/address/update）。
 * - 验证礼品地址列表 is_default 过滤参数严格校验，以及首个地址必须为默认、
 *   唯一默认地址不可取消或删除等规则。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台礼品地址管理的回归测试，覆盖越权修改/删除、过滤注入与默认地址规则。
 *
 * 入参例子：
 * - PATCH /api/front/gift-addresses/{address}：recipient_name、recipient_phone、recipient_address。
 * - 列表查询：is_default=1（合法）或 is_default=1abc（非法）。
 * - 新建地址：recipient_name、recipient_phone、recipient_address、is_default。
 *
 * 返回值：
 * - 越权修改/删除返回 code 为 DATA_NOT_FOUND，原地址数据保持不变。
 * - 非法过滤返回 VALIDATION_FAILED；合法过滤返回 SUCCESS 且只含默认地址。
 * - 违反默认地址规则返回 code 1015。
 *
 * 异常或失败场景：
 * - 修改他人地址、非法 is_default 过滤、首个地址非默认、唯一默认地址被取消/删除时均被拒绝。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontGiftAddressOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能通过现代接口修改或删除其他用户的礼品地址。
    public function test_customer_account_cannot_update_or_delete_another_users_modern_gift_address(): void
    {
        $viewerId = 412170100;
        $ownerId = 412170101;

        $this->deleteFixtureRows([$viewerId, $ownerId]);
        $this->insertUserInfo($viewerId, 'gift-address-boundary-viewer', 2);
        $this->insertUserInfo($ownerId, 'gift-address-boundary-owner', 2);
        $addressId = $this->insertAddress($ownerId, 'Original Gift Owner', '18800000001', 'Original Gift Address');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $acting = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user');

        $update = $acting->patchJson('/api/front/gift-addresses/' . $addressId, [
            'recipient_name' => 'Hacked Gift Owner',
            'recipient_phone' => '18899999999',
            'recipient_address' => 'Hacked Gift Address',
        ]);

        $update->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $delete = $acting->deleteJson('/api/front/gift-addresses/' . $addressId);

        $delete->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $addressId,
            'user_id' => $ownerId,
            'recipient_name' => 'Original Gift Owner',
            'recipient_phone' => '18800000001',
            'recipient_address' => 'Original Gift Address',
            'deleted_at' => null,
        ]);
    }

    // 验证普通客户不能通过遗留接口修改其他用户的礼品地址。
    public function test_customer_account_cannot_update_another_users_legacy_gift_address(): void
    {
        $viewerId = 412170200;
        $ownerId = 412170201;

        $this->deleteFixtureRows([$viewerId, $ownerId]);
        $this->insertUserInfo($viewerId, 'gift-address-legacy-boundary-viewer', 2);
        $this->insertUserInfo($ownerId, 'gift-address-legacy-boundary-owner', 2);
        $addressId = $this->insertAddress($ownerId, 'Legacy Gift Owner', '18800000002', 'Legacy Gift Address');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/address/update', [
                'rec_id' => $addressId,
                'receiver_name' => 'Legacy Hacked Owner',
                'phone' => '18899999998',
                'address' => 'Legacy Hacked Address',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $addressId,
            'user_id' => $ownerId,
            'recipient_name' => 'Legacy Gift Owner',
            'recipient_phone' => '18800000002',
            'recipient_address' => 'Legacy Gift Address',
            'deleted_at' => null,
        ]);
    }

    // 验证礼品地址列表拒绝非严格的 is_default 过滤参数。
    public function test_gift_address_list_rejects_non_strict_default_filter(): void
    {
        $viewerId = 412170300;

        $this->deleteFixtureRows([$viewerId]);
        $this->insertUserInfo($viewerId, 'gift-address-filter-viewer', 2);
        $addressId = $this->insertAddress($viewerId, 'Hidden Default Address', '18800000003', 'Hidden Filter Address');
        DB::table('user_addresses')->where('id', $addressId)->update(['is_default' => 1]);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/gift-addresses?is_default=1abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertStringNotContainsString('Hidden Default Address', $response->getContent());
    }

    // 验证礼品地址列表应用合法的 is_default=1 过滤。
    public function test_gift_address_list_applies_valid_default_filter(): void
    {
        $viewerId = 412170400;

        $this->deleteFixtureRows([$viewerId]);
        $this->insertUserInfo($viewerId, 'gift-address-default-viewer', 2);
        $defaultId = $this->insertAddress($viewerId, 'Visible Default Address', '18800000004', 'Visible Default Filter Address');
        DB::table('user_addresses')->where('id', $defaultId)->update(['is_default' => 1]);
        $this->insertAddress($viewerId, 'Hidden Normal Address', '18800000005', 'Hidden Normal Filter Address');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/gift-addresses?is_default=1');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            ->assertJsonPath('data.list.data.0.recipient_name', 'Visible Default Address');
        $this->assertStringNotContainsString('Hidden Normal Address', $response->getContent());
    }

    // 验证首个礼品地址必须创建为默认地址。
    public function test_first_gift_address_must_be_created_as_default(): void
    {
        $viewerId = 412170500;

        $this->deleteFixtureRows([$viewerId]);
        $this->insertUserInfo($viewerId, 'gift-address-first-default-viewer', 2);
        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/gift-addresses', [
                'recipient_name' => 'First Non Default',
                'recipient_phone' => '18800000006',
                'recipient_address' => 'First Non Default Address',
                'is_default' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', 1015);
        $this->assertDatabaseMissing('user_addresses', [
            'user_id' => $viewerId,
            'recipient_name' => 'First Non Default',
        ]);
    }

    // 验证唯一默认地址不可被取消默认或删除。
    public function test_only_default_gift_address_cannot_be_unset_or_deleted(): void
    {
        $viewerId = 412170600;

        $this->deleteFixtureRows([$viewerId]);
        $this->insertUserInfo($viewerId, 'gift-address-only-default-viewer', 2);
        $addressId = $this->insertAddress($viewerId, 'Only Default Address', '18800000007', 'Only Default Address Detail');
        DB::table('user_addresses')->where('id', $addressId)->update(['is_default' => 1]);
        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $acting = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user');

        $acting->patchJson('/api/front/gift-addresses/' . $addressId, ['is_default' => 0])
            ->assertOk()
            ->assertJsonPath('code', 1015);
        $acting->deleteJson('/api/front/gift-addresses/' . $addressId)
            ->assertOk()
            ->assertJsonPath('code', 1015);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $addressId,
            'user_id' => $viewerId,
            'is_default' => 1,
            'deleted_at' => null,
        ]);
    }


    // 校验权限清单文档记录了礼品地址属主边界闭环。
    public function test_final_checklist_records_gift_address_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 217.', $checklist);
        $this->assertStringContainsString('GiftController::updateAddress', $checklist);
        $this->assertStringContainsString('GiftController::deleteAddress', $checklist);
        $this->assertStringContainsString('/api/front/gift-addresses/{address}', $checklist);
        $this->assertStringContainsString('user/address/update', $checklist);
        $this->assertStringContainsString('FrontGiftAddressOwnerBoundaryClosureModuleTest', $checklist);
        $this->assertStringContainsString('## 345.', $checklist);
        $this->assertStringContainsString('GiftController::addressSearch', $checklist);
        $this->assertStringContainsString('is_default', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-gift-address-boundary-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => $accountType,
            'role_id' => 0,
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
            'user_name' => $userName,
            'phone' => '1782170' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertAddress(int $userId, string $name, string $phone, string $address): int
    {
        $now = time();

        return (int) DB::table('user_addresses')->insertGetId([
            'user_id' => $userId,
            'recipient_name' => $name,
            'recipient_phone' => $phone,
            'recipient_address' => $address,
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('user_addresses')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
