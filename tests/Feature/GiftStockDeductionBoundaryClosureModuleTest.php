<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 20:50
 */

/**
 * GiftStockDeductionBoundaryClosureModuleTest
 *
 * 文件功能：
 * - 验证礼品库存/积分联动边界：后台发礼不扣减 gift_items 库存（与旧项目一致）、无目录行也可发礼、前台礼品接口只读且无兑换/领取路由。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 礼品库存/积分联动边界锁定测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `Admin\GiftController@send_gift` 只写 `gift_shipments` 发货记录，没有任何礼品目录库存或积分扣除逻辑。
 * - 旧项目不存在 `gift_items` 表；`gift_items` 与 `points_cost`/`stock_quantity` 是新项目新增的目录能力，
 *   仅用于前台 `available_gifts` 展示（`GET /api/front/gifts`）。
 * - 当前不存在用户积分余额表，也不存在兑换/领取 API，因此第一阶段不做任何“兑换扣库存/积分消耗联动”，
 *   与旧项目行为保持一致，避免伪造不存在的旧业务。
 */
class GiftStockDeductionBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 库存扣减边界用例的夹具礼品名称标记。扣减与回滚断言都围绕该礼品行。
     * @var string
     */
    private const GIFT_NAME = 'Gift Stock Boundary Thermos';

    protected function tearDown(): void
    {
        DB::table('gift_items')->where('name', self::GIFT_NAME)->delete();
        DB::table('gift_shipments')->where('gift_name', self::GIFT_NAME)->delete();

        parent::tearDown();
    }

    public function test_admin_send_gift_does_not_deduct_gift_items_stock_matching_legacy_behavior(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();

        DB::table('gift_items')->where('name', self::GIFT_NAME)->delete();
        DB::table('gift_items')->insert([
            'name' => self::GIFT_NAME,
            'description' => 'Gift stock boundary fixture',
            'points_cost' => 100,
            'stock_quantity' => 5,
            'status' => 1,
            'image_url' => '/images/gifts/stock-boundary.png',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $addressId = $this->createEligibleRecipient(
            982911,
            'Stock Boundary Recipient',
            '13800002911',
            'Stock Boundary Address',
            (int) $admin->id
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/sendGift', [
                'sender_name' => 'Stock Boundary Sender',
                'gift_name' => self::GIFT_NAME,
                'gift_quantity' => 2,
                'tracking_number' => 'STOCK-BOUNDARY-TRACKING',
                'remark' => '',
                'recipients' => [
                    [
                        'user_id' => 982911,
                        'address_id' => $addressId,
                        'recipient_name' => 'Stock Boundary Recipient',
                        'recipient_phone' => '13800002911',
                        'recipient_address' => 'Stock Boundary Address',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::CREATED);
        $response->assertJsonPath('data.count', 1);

        $this->assertDatabaseHas('gift_shipments', [
            'gift_name' => self::GIFT_NAME,
            'gift_quantity' => 2,
            'admin_id' => $admin->id,
        ]);

        $this->assertSame(
            5,
            (int) DB::table('gift_items')->where('name', self::GIFT_NAME)->value('stock_quantity'),
            'sendGift 不得扣除 gift_items.stock_quantity：旧项目发放礼品不联动任何库存。'
        );
    }

    public function test_admin_send_gift_succeeds_without_any_gift_items_catalog_row(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());

        DB::table('gift_items')->delete();
        $addressId = $this->createEligibleRecipient(
            982912,
            'No Catalog Recipient',
            '13800002912',
            'No Catalog Address',
            (int) $admin->id
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/sendGift', [
                'sender_name' => 'No Catalog Sender',
                'gift_name' => self::GIFT_NAME . '-no-catalog',
                'gift_quantity' => 1,
                'tracking_number' => 'NO-CATALOG-TRACKING',
                'remark' => '',
                'recipients' => [
                    [
                        'user_id' => 982912,
                        'address_id' => $addressId,
                        'recipient_name' => 'No Catalog Recipient',
                        'recipient_phone' => '13800002912',
                        'recipient_address' => 'No Catalog Address',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::CREATED);

        $this->assertDatabaseHas('gift_shipments', [
            'gift_name' => self::GIFT_NAME . '-no-catalog',
        ]);
    }

    public function test_front_gift_endpoints_are_read_only_without_redeem_or_exchange_route(): void
    {
        $giftRouteNames = [];
        foreach (Route::getRoutes() as $route) {
            if (str_contains((string) $route->uri(), 'front/gifts') || str_contains((string) $route->uri(), 'gift')) {
                foreach ($route->methods() as $method) {
                    $giftRouteNames[] = $method . ' ' . $route->uri();
                }
            }
        }

        $joined = implode("\n", $giftRouteNames);
        $this->assertStringNotContainsStringIgnoringCase('redeem', $joined, '前台不存在兑换/领取礼品路由。');
        $this->assertStringNotContainsStringIgnoringCase('exchange', $joined, '前台不存在兑换/领取礼品路由。');

        $giftsRoute = Route::getRoutes()->getByName('front_api_gifts');
        $this->assertNotNull($giftsRoute, 'front_api_gifts 路由必须存在。');
        $this->assertSame(['GET', 'HEAD'], array_values($giftsRoute->methods()), 'front_api_gifts 必须只读。');

        $blade = file_get_contents(resource_path('front/layui/gift/list.blade.php')) ?: '';
        $this->assertStringContainsString('/api/front/gifts', $blade);
        $this->assertStringNotContainsStringIgnoringCase('redeem', $blade);
        $this->assertStringNotContainsStringIgnoringCase('exchange', $blade);
    }

    public function test_final_checklist_records_gift_stock_deduction_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 381.', $checklist);
        $this->assertStringContainsString('GiftStockDeductionBoundaryClosureModuleTest', $checklist);
        $this->assertStringContainsString('gift_items.stock_quantity', $checklist);
        $this->assertStringContainsString('sendGift', $checklist);
        $this->assertStringContainsString('redeem/exchange', $checklist);
    }

    private function createEligibleRecipient(
        int $userId,
        string $name,
        string $phone,
        string $address,
        int $createdBy
    ): int {
        $now = time();
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Gift Stock Recipient ' . $userId,
                'phone' => $phone,
                'gender' => 0,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'is_gift_allowed' => 1,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('user_addresses')->insertGetId([
            'user_id' => $userId,
            'recipient_name' => $name,
            'recipient_phone' => $phone,
            'recipient_address' => $address,
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
