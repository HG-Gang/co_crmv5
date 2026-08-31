<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 10:40
 */

/**
 * FrontLegacyGiftAddressClosureModuleTest
 *
 * 文件功能：
 * - 验证旧前台礼品地址闭环：登录后可访问、仅返回本人地址旧行契约、非布尔默认筛选拒绝、首个地址不可设为非默认、默认地址切换与必填校验。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧前台收货地址模块闭环测试。
 *
 * 测试目标：
 * - user/address/list、user/address/add、user/address/info/{recId} 三个旧页面入口必须渲染。
 * - user/address/search 旧搜索入口必须只返回本人地址，且行结构带 rec_id 与 gift_allowed。
 * - user/address/update 旧新增/编辑统一入口必须继承旧项目默认地址规则。
 *
 * 闭环说明：
 * - 地址数据源为 user_addresses，按当前登录用户隔离，防止越权读取或编辑他人地址。
 * - 首个地址必须为默认地址（1015），默认地址不能被直接取消或删除（1015）。
 */
class FrontLegacyGiftAddressClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 三个旧地址页面必须能渲染，且共享同一个模块页配置。
     *
     * @return void
     */
    public function test_legacy_address_pages_render_for_authenticated_user(): void
    {
        $userId = 412180100;
        $this->deleteFixtureRows([$userId]);
        $this->insertUserInfo($userId, 'legacy-address-page-user', 2);
        $addressId = $this->insertAddress($userId, 'Page Address Owner', '18800001001', 'Page Address Detail');
        $login = UserLogin::where('user_id', $userId)->firstOrFail();
        $acting = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user');

        // 外壳页面只渲染 iframe 容器，模块内容在 frame=1 版本中。
        $list = $acting->get('/user/address/list');
        $list->assertOk();
        $list->assertSee('contentFrame', false);
        $list->assertSee('frame=1', false);

        $listFrame = $acting->get('/user/address/list?frame=1');
        $listFrame->assertOk();
        $listFrame->assertSee('/js/apps/front/layui/module-page.js', false);
        $listFrame->assertSee('/api/front/gift-addresses', false);

        $add = $acting->get('/user/address/add?frame=1');
        $add->assertOk();

        $edit = $acting->get('/user/address/info/' . $addressId . '?frame=1');
        $edit->assertOk();
        $this->assertStringContainsString((string) $addressId, $edit->getContent());
    }

    /**
     * 未登录用户访问旧地址页面必须被重定向到登录页。
     *
     * @return void
     */
    public function test_legacy_address_pages_require_authentication(): void
    {
        foreach (['/user/address/list', '/user/address/add', '/user/address/info/412180201'] as $uri) {
            $response = $this->get($uri);
            $response->assertRedirect();
            $this->assertTrue(
                strpos((string) $response->headers->get('Location'), 'login') !== false,
                $uri . ' 必须重定向到登录页'
            );
        }
    }

    /**
     * 旧地址搜索只返回本人地址，行结构包含 rec_id 与 gift_allowed，默认地址排前。
     *
     * @return void
     */
    public function test_legacy_address_search_returns_only_own_addresses_with_legacy_row_contract(): void
    {
        $viewerId = 412180300;
        $ownerId = 412180301;
        $this->deleteFixtureRows([$viewerId, $ownerId]);
        $this->insertUserInfo($viewerId, 'legacy-address-search-viewer', 2);
        $this->insertUserInfo($ownerId, 'legacy-address-search-owner', 2);
        $viewerNormalId = $this->insertAddress($viewerId, 'Viewer Normal Address', '18800001002', 'Viewer Normal Detail');
        $viewerDefaultId = $this->insertAddress($viewerId, 'Viewer Default Address', '18800001003', 'Viewer Default Detail');
        DB::table('user_addresses')->where('id', $viewerDefaultId)->update(['is_default' => 1]);
        $hiddenId = $this->insertAddress($ownerId, 'Hidden Owner Address', '18800001004', 'Hidden Owner Detail');
        DB::table('user_addresses')->where('id', $hiddenId)->update(['is_default' => 1]);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/address/search', ['rows' => 15]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = $response->json('data.list.data');
        $this->assertCount(2, $rows);
        $this->assertSame((string) $viewerDefaultId, (string) $rows[0]['rec_id']);
        $this->assertSame('Viewer Default Address', $rows[0]['recipient_name']);
        $this->assertSame(1, (int) $rows[0]['is_default']);
        $this->assertSame((string) $viewerId, (string) $rows[0]['user_id']);
        $this->assertSame(1, (int) $rows[0]['gift_allowed']);
        $this->assertSame((string) $viewerNormalId, (string) $rows[1]['rec_id']);
        $this->assertSame('Viewer Normal Address', $rows[1]['recipient_name']);
        $this->assertStringNotContainsString('Hidden Owner Address', $response->getContent());
    }

    /**
     * 旧地址搜索的 is_default 筛选必须拒绝非布尔值。
     *
     * @return void
     */
    public function test_legacy_address_search_rejects_non_boolean_default_filter(): void
    {
        $userId = 412180400;
        $this->deleteFixtureRows([$userId]);
        $this->insertUserInfo($userId, 'legacy-address-filter-user', 2);
        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/address/search', ['is_default' => '1abc']);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    /**
     * 旧地址更新入口必须拒绝新增非默认地址：用户还没有任何地址时第一个必须是默认地址。
     *
     * @return void
     */
    public function test_legacy_address_update_rejects_first_non_default_address(): void
    {
        $userId = 412180500;
        $this->deleteFixtureRows([$userId]);
        $this->insertUserInfo($userId, 'legacy-address-first-user', 2);
        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/address/update', [
                'rec_id' => 0,
                'receiver_name' => 'First Non Default',
                'phone' => '18800001005',
                'address' => 'First Non Default Detail',
                'is_default' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::DEFAULT_ADDRESS_MUST_EXIST);
        $this->assertDatabaseMissing('user_addresses', [
            'user_id' => $userId,
            'recipient_name' => 'First Non Default',
        ]);
    }

    /**
     * 旧地址更新入口必须用旧字段别名新增默认地址。
     *
     * @return void
     */
    public function test_legacy_address_update_creates_default_address_with_legacy_aliases(): void
    {
        $userId = 412180600;
        $this->deleteFixtureRows([$userId]);
        $this->insertUserInfo($userId, 'legacy-address-create-user', 2);
        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/address/update', [
                'rec_id' => 0,
                'receiver_name' => 'Alias Created Owner',
                'phone' => '18800001006',
                'address' => 'Alias Created Detail',
                'is_default' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $userId,
            'recipient_name' => 'Alias Created Owner',
            'recipient_phone' => '18800001006',
            'recipient_address' => 'Alias Created Detail',
            'is_default' => 1,
            'deleted_at' => null,
        ]);
    }

    /**
     * 旧地址更新入口必须支持编辑本人地址，并把默认地址切换过去。
     *
     * @return void
     */
    public function test_legacy_address_update_edits_own_address_and_switches_default(): void
    {
        $userId = 412180700;
        $this->deleteFixtureRows([$userId]);
        $this->insertUserInfo($userId, 'legacy-address-edit-user', 2);
        $firstId = $this->insertAddress($userId, 'Original Default', '18800001007', 'Original Default Detail');
        DB::table('user_addresses')->where('id', $firstId)->update(['is_default' => 1]);
        $secondId = $this->insertAddress($userId, 'Original Normal', '18800001008', 'Original Normal Detail');
        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/address/update', [
                'rec_id' => $secondId,
                'receiver_name' => 'Edited Normal To Default',
                'phone' => '18800001009',
                'address' => 'Edited Normal Detail',
                'is_default' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertDatabaseHas('user_addresses', [
            'id' => $secondId,
            'recipient_name' => 'Edited Normal To Default',
            'is_default' => 1,
        ]);
        $this->assertDatabaseHas('user_addresses', [
            'id' => $firstId,
            'is_default' => 0,
        ]);
    }

    /**
     * 旧地址更新入口必须校验必填字段。
     *
     * @return void
     */
    public function test_legacy_address_update_validates_required_fields(): void
    {
        $userId = 412180800;
        $this->deleteFixtureRows([$userId]);
        $this->insertUserInfo($userId, 'legacy-address-validate-user', 2);
        $login = UserLogin::where('user_id', $userId)->firstOrFail();

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/address/update', [
                'rec_id' => 0,
                'receiver_name' => '',
                'phone' => '',
                'address' => '',
                'is_default' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-legacy-gift-address-' . $userId . '@example.test',
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
            'is_gift_allowed' => 1,
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
