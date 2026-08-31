<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 20:46
 */

/**
 * AdminGiftModuleTest
 *
 * 文件功能：
 * - 验证后台礼品模块：页面/路由权限、按收件人生成发货单与操作日志、发货状态更新、礼品目录 CRUD、双端前端入口与权限迁移。
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
 * 后台礼品发放/发货模块覆盖测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `GiftController` 提供礼品发放、发货列表、用户地址列表和导出能力。
 * - 新项目第一阶段基于真实表 `gift_shipments`、`user_addresses`、`user_infos`，先补齐后台 Blade 页面、三个核心 API、权限表配置和多语言文案。
 * - 当前 MySQL 3307 可能不可用，本测试不读真实数据库，只约束页面、路由、中间件、控制器源码和权限迁移契约。
 */
class AdminGiftModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 礼品后台页面必须注册为独立 Blade 路由，避免被后台 Naive 兜底路由接管。
     *
     * @return void
     */
    public function test_gift_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_gifts'), 'admin_page_gifts 页面路由未注册。');
    }

    /**
     * 礼品后台页面必须包含发货列表、地址列表、发放弹窗和页面脚本。
     *
     * @return void
     */
    public function test_gift_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/gifts');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="giftShipmentSearchForm"', false);
        $response->assertSee('id="giftShipmentTable"', false);
        $response->assertSee('id="giftAddressTable"', false);
        $response->assertSee('id="sendGiftForm"', false);
        $response->assertSee('id="exportGiftShipments"', false);
        $response->assertSee('data-permission="admin_gift_export"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"gifts/index\"", false);
    }

    /**
     * 礼品模块三个核心 API 必须注册并挂载后台权限中间件。
     *
     * @return void
     */
    public function test_gift_api_routes_have_permission_middleware(): void
    {
        foreach ([
            'admin_api_giftShipmentList',
            'admin_api_exportGiftShipments',
            'admin_api_giftAddressList',
            'admin_api_sendGift',
            'admin_api_updateGiftShipment',
            'admin_api_giftItemList',
            'admin_api_createGiftItem',
            'admin_api_updateGiftItem',
            'admin_api_deleteGiftItem',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API 路由未注册。');
            $this->assertContains(
                'check.permission:admin',
                Route::getRoutes()->getByName($routeName)->gatherMiddleware()
            );
        }
    }

    /**
     * 礼品发货导出接口必须返回当前筛选结果 CSV，不能只保留空权限按钮。
     *
     * @return void
     */
    public function test_gift_shipment_export_endpoint_returns_current_filter_csv(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $userId = 982701;

        DB::table('gift_shipments')->updateOrInsert(
            ['tracking_number' => 'GIFT-EXPORT-TRACKING'],
            [
                'user_id' => $userId,
                'address_id' => 0,
                'recipient_name' => 'Gift Export Recipient',
                'recipient_phone' => '13800002701',
                'recipient_address' => 'Gift Export Address',
                'sender_name' => 'Gift Export Sender',
                'gift_name' => 'Gift Export Box',
                'gift_quantity' => 2,
                'status' => 1,
                'remark' => 'gift shipment csv export',
                'admin_id' => $admin->id,
                'shipped_at' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportGiftShipments', ['gift_name' => 'Gift Export Box']);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('gift_shipments_export.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Gift Export Box', $content);
        $this->assertStringContainsString('Gift Export Recipient', $content);
        $this->assertStringContainsString('GIFT-EXPORT-TRACKING', $content);
    }

    /**
     * 控制器必须读取真实礼品发货表、用户地址表，并通过事务写入发货记录。
     *
     * @return void
     */
    public function test_send_gift_api_creates_one_shipment_per_recipient(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $giftName = 'Batch Gift ' . time() . '-' . random_int(1000, 9999);
        $addressA = $this->createEligibleGiftRecipient(982811, 'Batch Gift Recipient A', '13800002811', 'Batch Gift Address A', (int) $admin->id);
        $addressB = $this->createEligibleGiftRecipient(982812, 'Batch Gift Recipient B', '13800002812', 'Batch Gift Address B', (int) $admin->id);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/sendGift', [
                'sender_name' => 'Batch Sender',
                'gift_name' => $giftName,
                'gift_quantity' => 3,
                'tracking_number' => 'BATCH-GIFT-TRACKING',
                'remark' => 'batch gift send test',
                'recipients' => [
                    [
                        'user_id' => 982811,
                        'address_id' => $addressA,
                        'recipient_name' => 'Batch Gift Recipient A',
                        'recipient_phone' => '13800002811',
                        'recipient_address' => 'Batch Gift Address A',
                    ],
                    [
                        'user_id' => 982812,
                        'address_id' => $addressB,
                        'recipient_name' => 'Batch Gift Recipient B',
                        'recipient_phone' => '13800002812',
                        'recipient_address' => 'Batch Gift Address B',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::CREATED);
        $response->assertJsonPath('data.count', 2);

        $this->assertSame(2, DB::table('gift_shipments')->where('gift_name', $giftName)->count());
        $this->assertDatabaseHas('gift_shipments', [
            'gift_name' => $giftName,
            'user_id' => 982811,
            'address_id' => $addressA,
            'recipient_name' => 'Batch Gift Recipient A',
            'gift_quantity' => 3,
            'status' => 1,
            'admin_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('gift_shipments', [
            'gift_name' => $giftName,
            'user_id' => 982812,
            'address_id' => $addressB,
            'recipient_name' => 'Batch Gift Recipient B',
            'gift_quantity' => 3,
            'status' => 1,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_send_gift_api_writes_operation_log_for_batch_recipients(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $giftName = 'Audit Gift ' . time() . '-' . random_int(1000, 9999);
        $addressA = $this->createEligibleGiftRecipient(982821, 'Audit Gift Recipient A', '13800002821', 'Audit Gift Address A', (int) $admin->id);
        $addressB = $this->createEligibleGiftRecipient(982822, 'Audit Gift Recipient B', '13800002822', 'Audit Gift Address B', (int) $admin->id);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/sendGift', [
                'sender_name' => 'Audit Sender',
                'gift_name' => $giftName,
                'gift_quantity' => 2,
                'tracking_number' => 'AUDIT-GIFT-TRACKING',
                'remark' => 'gift operation audit test',
                'recipients' => [
                    [
                        'user_id' => 982821,
                        'address_id' => $addressA,
                        'recipient_name' => 'Audit Gift Recipient A',
                        'recipient_phone' => '13800002821',
                        'recipient_address' => 'Audit Gift Address A',
                    ],
                    [
                        'user_id' => 982822,
                        'address_id' => $addressB,
                        'recipient_name' => 'Audit Gift Recipient B',
                        'recipient_phone' => '13800002822',
                        'recipient_address' => 'Audit Gift Address B',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::CREATED);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'gift_send')
            ->where('content', 'LIKE', '%' . $giftName . '%')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'sendGift must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertNull($log->target_user_id);
        $this->assertSame(0, (int) $log->action_type);
        $this->assertNotSame('', (string) $log->ip);
        $this->assertStringContainsString('gift_name:' . $giftName, $log->content);
        $this->assertStringContainsString('gift_quantity:2', $log->content);
        $this->assertStringContainsString('recipients:982821,982822', $log->content);
        $this->assertStringContainsString('tracking_number:AUDIT-GIFT-TRACKING', $log->content);
    }

    public function test_update_gift_shipment_api_updates_status_tracking_number_and_remark(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();

        $shipmentId = DB::table('gift_shipments')->insertGetId([
            'user_id' => 983011,
            'address_id' => 71301,
            'recipient_name' => 'Update Gift Recipient',
            'recipient_phone' => '13800003011',
            'recipient_address' => 'Update Gift Address',
            'sender_name' => 'Update Gift Sender',
            'gift_name' => 'Update Gift Box ' . $now,
            'gift_quantity' => 1,
            'tracking_number' => '',
            'status' => 0,
            'remark' => 'waiting for tracking',
            'admin_id' => 0,
            'shipped_at' => date('Y-m-d H:i:s', $now),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateGiftShipment/' . $shipmentId, [
                'status' => 3,
                'tracking_number' => 'TRACK-UPDATED-983011',
                'remark' => 'delivered by express',
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('gift_shipments', [
            'id' => $shipmentId,
            'status' => 3,
            'tracking_number' => 'TRACK-UPDATED-983011',
            'remark' => 'delivered by express',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_update_gift_shipment_api_writes_operation_log_with_before_and_after_values(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();

        $shipmentId = DB::table('gift_shipments')->insertGetId([
            'user_id' => 983021,
            'address_id' => 71321,
            'recipient_name' => 'Audit Update Gift Recipient',
            'recipient_phone' => '13800003021',
            'recipient_address' => 'Audit Update Gift Address',
            'sender_name' => 'Audit Update Gift Sender',
            'gift_name' => 'Audit Update Gift Box ' . $now,
            'gift_quantity' => 1,
            'tracking_number' => '',
            'status' => 0,
            'remark' => 'waiting for audit tracking',
            'admin_id' => 0,
            'shipped_at' => date('Y-m-d H:i:s', $now),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateGiftShipment/' . $shipmentId, [
                'status' => 3,
                'tracking_number' => 'TRACK-AUDIT-983021',
                'remark' => 'audit delivered by express',
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::UPDATED);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'gift_shipment:' . $shipmentId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'updateGiftShipment must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertSame(983021, (int) $log->target_user_id);
        $this->assertSame(0, (int) $log->action_type);
        $this->assertNotSame('', (string) $log->ip);
        $this->assertStringContainsString('shipment_id:' . $shipmentId, $log->content);
        $this->assertStringContainsString('status:0->3', $log->content);
        $this->assertStringContainsString('tracking_number:->TRACK-AUDIT-983021', $log->content);
        $this->assertStringContainsString('remark:waiting for audit tracking->audit delivered by express', $log->content);
    }

    public function test_gift_item_catalog_crud_api_creates_updates_lists_and_deletes_real_items(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());

        DB::table('gift_items')->whereIn('name', [
            'Admin Catalog Thermos',
            'Admin Catalog Thermos Updated',
        ])->delete();

        $createResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/createGiftItem', [
                'name' => 'Admin Catalog Thermos',
                'description' => 'Admin managed catalog gift',
                'points_cost' => 420,
                'stock_quantity' => 12,
                'status' => 1,
                'image_url' => '/images/gifts/admin-catalog-thermos.png',
            ]);

        $createResponse->assertOk();
        $createResponse->assertJsonPath('code', ResponseCode::CREATED);
        $createResponse->assertJsonPath('data.name', 'Admin Catalog Thermos');
        $giftItemId = (int) $createResponse->json('data.id');

        $updateResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateGiftItem/' . $giftItemId, [
                'name' => 'Admin Catalog Thermos Updated',
                'description' => 'Updated admin managed catalog gift',
                'points_cost' => 450,
                'stock_quantity' => 6,
                'status' => 1,
                'image_url' => '/images/gifts/admin-catalog-thermos-updated.png',
            ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('code', ResponseCode::UPDATED);
        $this->assertDatabaseHas('gift_items', [
            'id' => $giftItemId,
            'name' => 'Admin Catalog Thermos Updated',
            'points_cost' => 450,
            'stock_quantity' => 6,
            'status' => 1,
        ]);

        $listResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/giftItemList', [
                'name' => 'Thermos Updated',
                'points_cost' => 450,
            ]);

        $listResponse->assertOk();
        $listResponse->assertJsonPath('code', ResponseCode::SUCCESS);
        $listResponse->assertJsonPath('data.data.0.id', $giftItemId);
        $listResponse->assertJsonPath('data.data.0.name', 'Admin Catalog Thermos Updated');

        $deleteResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/deleteGiftItem/' . $giftItemId);

        $deleteResponse->assertOk();
        $deleteResponse->assertJsonPath('code', ResponseCode::DELETED);
        $this->assertTrue(
            DB::table('gift_items')->where('id', $giftItemId)->whereNotNull('deleted_at')->exists(),
            'deleteGiftItem 应软删除 gift_items 记录。'
        );
    }

    public function test_gift_item_catalog_admin_frontends_are_exposed(): void
    {
        $html = $this->get('/admin/gifts')->assertOk()->getContent();
        $layuiScript = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmuiController = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $crmuiHtml = $this->get('/admin-crmui/gift-items')->assertOk()->getContent();
        $permissionMigration = file_get_contents(database_path('migrations/2026_06_07_000017_add_admin_gift_item_permissions.php')) ?: '';

        foreach ([
            'id="giftItemSearchForm"',
            'id="giftItemTable"',
            'id="giftItemForm"',
            'id="openGiftItemForm"',
            'data-permission="admin_gift_item_create"',
            'data-permission="admin_gift_item_update"',
            'data-permission="admin_gift_item_delete"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        foreach ([
            '/api/admin/giftItemList',
            '/api/admin/createGiftItem',
            '/api/admin/updateGiftItem/',
            '/api/admin/deleteGiftItem/',
            'giftItemTable',
            'submitGiftItemForm',
        ] as $needle) {
            $this->assertStringContainsString($needle, $layuiScript);
        }

        foreach ([
            "'gift-items' =>",
            "'key' => 'gift_items'",
            "'api' => 'admin_api_giftItemList'",
            "'formApi' => 'admin_api_createGiftItem'",
            "'route' => 'admin_api_updateGiftItem'",
            "'route' => 'admin_api_deleteGiftItem'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $crmuiController);
        }

        foreach ([
            'data-crmui-page="admin.gift_items"',
            'data-api-url="' . url('/api/admin/giftItemList') . '"',
            'data-action-url="' . url('/api/admin/createGiftItem') . '"',
            'data-crmui-row-action="update"',
            'data-crmui-row-action="delete"',
            'data-key="points_cost"',
            'data-key="stock_quantity"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $crmuiHtml);
        }

        foreach ([
            'admin_gift_items',
            'admin_gift_item_create',
            'admin_gift_item_update',
            'admin_gift_item_delete',
            'admin_api_giftItemList',
            'admin_api_createGiftItem',
            'admin_api_updateGiftItem',
            'admin_api_deleteGiftItem',
        ] as $needle) {
            $this->assertStringContainsString($needle, $permissionMigration);
        }
    }

    public function test_gift_shipment_update_frontends_are_exposed(): void
    {
        $html = $this->get('/admin/gifts')->assertOk()->getContent();
        $layuiScript = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmuiController = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $crmuiHtml = $this->get('/admin-crmui/gifts')->assertOk()->getContent();

        foreach ([
            'id="updateGiftShipmentForm"',
            'data-permission="admin_gift_update_shipment"',
            'lay-filter="submitUpdateGiftShipment"',
            'id="giftShipmentActions"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        foreach ([
            '/api/admin/updateGiftShipment/',
            'submitUpdateGiftShipment',
            'updateGiftShipmentForm',
            'updateGiftShipment',
        ] as $needle) {
            $this->assertStringContainsString($needle, $layuiScript);
        }

        foreach ([
            "'route' => 'admin_api_updateGiftShipment'",
            "'params' => ['id' => '__ID__']",
            "'fields' => \$this->giftShipmentStatusFields()",
        ] as $needle) {
            $this->assertStringContainsString($needle, $crmuiController);
        }

        foreach ([
            'data-crmui-row-action="update_shipment"',
            'data-action-url="' . url('/api/admin/updateGiftShipment/__ID__') . '"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $crmuiHtml);
        }
    }

    public function test_layui_admin_gift_send_supports_batch_recipients(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/gifts/index.blade.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';

        foreach ([
            'id="giftAddressTable"',
            'name="address_payload"',
            'lay-filter="submitSendGift"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blade);
        }

        foreach ([
            "table.checkStatus('giftAddressTable').data",
            'selected.map(addressToRecipient)',
            "url: '/api/admin/sendGift'",
            'recipients: recipients',
            'function addressToRecipient',
        ] as $needle) {
            $this->assertStringContainsString($needle, $script);
        }
    }

    public function test_crmui_gift_addresses_page_supports_batch_recipient_picker(): void
    {
        $html = $this->get('/admin-crmui/gift-addresses')->assertOk()->getContent();
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        foreach ([
            '/api/admin/giftAddressList',
            '/api/admin/sendGift',
            'data-crmui-gift-recipient-picker="1"',
            'name="recipients_payload"',
            'data-crmui-gift-recipient-preview',
            'data-crmui-row-action="select_gift_recipient"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        foreach ([
            "'giftRecipientPicker' => true",
            "'formApi' => 'admin_api_sendGift'",
            "'rowActions' =>",
            "'select_gift_recipient'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }

        foreach ([
            'function addCrmUiGiftRecipient',
            'function updateCrmUiGiftRecipientPreview',
            'function crmUiGiftSendPayload',
            'recipients_payload',
        ] as $needle) {
            $this->assertStringContainsString($needle, $script);
        }
    }

    public function test_gift_controller_uses_real_tables_and_transaction(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/GiftController.php');

        $this->assertFileExists($controllerPath, 'GiftController 控制器不存在。');
        $source = file_get_contents($controllerPath);

        $this->assertStringContainsString('GiftShipment::query()', $source);
        $this->assertStringContainsString('UserAddress::query()', $source);
        $this->assertStringContainsString('user_infos', $source);
        $this->assertStringContainsString('DB::transaction', $source);
        $this->assertStringContainsString('gift_quantity', $source);
    }

    public function test_gift_controller_comments_match_export_implementation(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/GiftController.php');

        $this->assertFileExists($controllerPath, 'GiftController 控制器不存在。');
        $source = file_get_contents($controllerPath) ?: '';

        foreach ([
            '本阶段先在权限表声明',
            '后续单独补真实导出接口',
        ] as $staleComment) {
            $this->assertStringNotContainsString($staleComment, $source);
        }

        foreach ([
            'exportGiftShipments',
            'gift_shipments_export.csv',
            '当前筛选条件下的礼品发货记录 CSV',
            '库存/积分联动边界',
            '写入礼品发放/物流更新操作日志',
            '生成 CSV 下载响应',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source);
        }
    }

    public function test_final_checklist_records_current_gift_export_closure(): void
    {
        $checklistPath = base_path('docs/admin-backend-blade-permission-final-checklist.md');

        $this->assertFileExists($checklistPath, '后台最终清单文档不存在。');
        $content = file_get_contents($checklistPath) ?: '';

        foreach ([
            '## 160. 2026-07-07 后台礼品导出状态校准与注释回归保护',
            '`admin_api_exportGiftShipments`',
            '`gift_shipments_export.csv`',
            '`AdminGiftModuleTest::test_gift_shipment_export_endpoint_returns_current_filter_csv`',
            '`AdminGiftModuleTest::test_gift_controller_comments_match_export_implementation`',
            '库存/积分联动边界',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }

        foreach ([
            '导出权限已声明，但真实导出接口留待后续独立补齐',
            '导出权限预留；真实导出接口后续独立补齐',
            '旧项目 `shipment_list_export` 的真实导出能力后续需补独立接口',
        ] as $staleChecklistText) {
            $this->assertStringNotContainsString($staleChecklistText, $content);
        }
    }

    /**
     * 权限迁移必须声明页面、发货列表、地址列表、发放和导出权限。
     *
     * @return void
     */
    public function test_gift_permission_migration_declares_required_permissions(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000011_add_admin_gift_permissions.php');

        $this->assertFileExists($migrationPath, '礼品权限迁移文件不存在。');
        $source = file_get_contents($migrationPath);

        foreach ([
            'admin_gifts',
            'admin_gift_shipments',
            'admin_gift_addresses',
            'admin_gift_send',
            'admin_gift_export',
            'admin_gift_update_shipment',
            'admin_api_giftShipmentList',
            'admin_api_exportGiftShipments',
            'admin_api_giftAddressList',
            'admin_api_sendGift',
            'admin_api_updateGiftShipment',
            '/admin/gifts',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }

    public function test_layui_admin_exposes_gift_address_list_module(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/gifts/index.blade.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';

        foreach (['user_id', 'recipient_name', 'recipient_phone', 'is_default'] as $filterName) {
            $this->assertStringContainsString('name="' . $filterName . '"', $blade, $filterName . ' must be available as a Layui gift-address filter.');
        }

        $this->assertStringContainsString("url: '/api/admin/giftAddressList'", $script);
        $this->assertStringContainsString("table.reload('giftAddressTable', {where: data.field", $script);
        $this->assertStringContainsString("{field: 'recipient_name'", $script);
        // is_default 列使用多行对象定义；只校验真实字段声明，避免格式调整导致误报。
        $this->assertStringContainsString("field: 'is_default'", $script);
    }

    public function test_crmui_admin_exposes_gift_address_list_module(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $zh = file_get_contents(resource_path('lang/zh-CN/crmui.php')) ?: '';
        $en = file_get_contents(resource_path('lang/en/crmui.php')) ?: '';

        $this->assertStringContainsString("'gift-addresses' =>", $controller);
        $this->assertStringContainsString("'key' => 'gift_addresses'", $controller);
        $this->assertStringContainsString("'api' => 'admin_api_giftAddressList'", $controller);
        $this->assertStringContainsString("['name' => 'is_default', 'type' => 'select'", $controller);
        $this->assertStringContainsString("'defaultFilters' => ['is_default' => 1]", $controller);
        $this->assertStringContainsString("'columns' => ['id', 'user_id', 'user_name', 'recipient_name', 'recipient_phone', 'recipient_address', 'is_default', 'updated_at']", $controller);
        $this->assertStringContainsString("'gift_addresses' => ['title'", $zh);
        $this->assertStringContainsString("'gift_addresses' => ['title'", $en);
    }

    private function createEligibleGiftRecipient(
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
                'user_name' => 'Gift Recipient ' . $userId,
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
