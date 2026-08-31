<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 前台礼品地址默认切换事务闭环测试。
 *
 * 文件功能：
 * - 验证新建默认礼品地址时，若新地址创建失败，旧默认地址不会被取消（事务回滚）。
 * - 验证权限清单文档记录了该事务边界闭环。
 *
 * 适用场景：
 * - 前台礼品地址“切换默认地址”的原子性回归测试，防止地址创建失败导致无默认地址。
 *
 * 入参例子：
 * - POST /api/front/gift-addresses：
 *   recipient_name: Rollback Failing Default、recipient_phone: 18800000009、
 *   recipient_address: Rollback Failing Address、is_default: 1。
 * - 通过 Event::listen 在 UserAddress 创建时注入 RuntimeException 模拟失败。
 *
 * 返回值：
 * - 注入失败时抛出 RuntimeException，旧默认地址仍为 is_default=1 且未被删除。
 *
 * 异常或失败场景：
 * - 新地址创建抛错时整体回滚，不产生半成品默认地址。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserAddress;
use App\Models\UserLogin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class FrontGiftAddressTransactionClosureModuleTest extends TestCase
{
    /**
     * 礼赠地址事务用例的固定业务用户 ID。验证地址保存与礼赠下单在同一事务内原子提交/回滚。
     * @var int
     */
    private const USER_ID = 412170700;

    protected function tearDown(): void
    {
        Event::forget('eloquent.creating: ' . UserAddress::class);
        $this->deleteFixtureRows();

        parent::tearDown();
    }

    // 验证新默认地址创建失败时旧默认地址切换被回滚。
    public function test_default_address_switch_rolls_back_when_new_address_creation_fails(): void
    {
        $this->deleteFixtureRows();
        $login = $this->insertUserInfo();
        $addressId = $this->insertDefaultAddress();
        $eventName = 'eloquent.creating: ' . UserAddress::class;

        Event::listen($eventName, function (UserAddress $address): void {
            if ($address->recipient_name === 'Rollback Failing Default') {
                throw new RuntimeException('Injected address creation failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->postJson('/api/front/gift-addresses', [
                    'recipient_name' => 'Rollback Failing Default',
                    'recipient_phone' => '18800000009',
                    'recipient_address' => 'Rollback Failing Address',
                    'is_default' => 1,
                ]);
            $this->fail('Injected address creation failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected address creation failure', $exception->getMessage());
        }

        $this->assertDatabaseHas('user_addresses', [
            'id' => $addressId,
            'user_id' => self::USER_ID,
            'is_default' => 1,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('user_addresses', [
            'user_id' => self::USER_ID,
            'recipient_name' => 'Rollback Failing Default',
        ]);
    }

    // 校验权限清单文档记录了默认地址切换事务边界闭环。
    public function test_final_checklist_records_default_address_transaction_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 346.', $checklist);
        $this->assertStringContainsString('DEFAULT_ADDRESS_MUST_EXIST', $checklist);
        $this->assertStringContainsString('user_addresses', $checklist);
        $this->assertStringContainsString('InnoDB', $checklist);
        $this->assertStringContainsString('FrontGiftAddressTransactionClosureModuleTest', $checklist);
    }

    private function insertUserInfo(): UserLogin
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => self::USER_ID,
            'email' => 'front-gift-address-transaction@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
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
            'user_id' => self::USER_ID,
            'login_id' => $loginId,
            'user_name' => 'gift-address-rollback-viewer',
            'phone' => '1782170700',
            'gender' => 1,
            'account_type' => 2,
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

        return UserLogin::findOrFail($loginId);
    }

    private function insertDefaultAddress(): int
    {
        $now = time();

        return (int) DB::table('user_addresses')->insertGetId([
            'user_id' => self::USER_ID,
            'recipient_name' => 'Rollback Original Default',
            'recipient_phone' => '18800000008',
            'recipient_address' => 'Rollback Original Address',
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function deleteFixtureRows(): void
    {
        DB::table('user_addresses')->where('user_id', self::USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::USER_ID)->delete();
        DB::table('user_logins')->where('user_id', self::USER_ID)->delete();
        DB::table('user_logins')->where('email', 'front-gift-address-transaction@example.test')->delete();
    }
}
