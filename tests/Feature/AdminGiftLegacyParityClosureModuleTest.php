<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 21:38
 */

/**
 * AdminGiftLegacyParityClosureModuleTest
 *
 * 文件功能：
 * - 验证后台礼品旧路由等价：工作流权限与共享页面、地址列表仅默认可发礼品、软删用户不可收礼、发货列表旧 envelope 与日期筛选、整批校验后写库、公式安全 CSV 导出与过期文件清理。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\Admin;
use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GiftController 六条旧后台路由协议与真实数据闭环。
 *
 * 旧入口必须保留 code=0/5000、giftInfo/rec_id、默认日期和两阶段导出协议，
 * 业务数据只能来自新库 user_infos、user_addresses、gift_shipments 与 admins。
 */
class AdminGiftLegacyParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 可正常收礼的业务用户 ID，其默认收货地址以数据库为准参与新旧链路一致性断言。
     * @var int
     */
    private const ELIGIBLE_USER_ID = 988401;
    /**
     * 只有非默认地址的业务用户 ID。验证新旧链路都回退到非默认地址或一致报错。
     * @var int
     */
    private const NON_DEFAULT_USER_ID = 988402;
    /**
     * 被禁用（is_enabled=0）的业务用户 ID。验证赠送链路对其拒绝。
     * @var int
     */
    private const DISABLED_USER_ID = 988403;
    /**
     * 被软删除的业务用户 ID。验证新旧链路都不能为已删除用户送礼。
     * @var int
     */
    private const SOFT_DELETED_USER_ID = 988404;
    /**
     * 数据范围用例中创建的后台管理员 ID，其可见性由角色数据范围决定。
     * @var int
     */
    private const CREATED_ADMIN_ID = 988450;
    /**
     * 为 CREATED_ADMIN_ID 创建的角色 ID，绑定自定义用户数据范围。
     * @var int
     */
    private const CREATED_ROLE_ID = 988451;
    /**
     * 数据范围内的业务用户 ID，其礼赠记录对受限管理员可见。
     * @var int
     */
    private const CREATED_VISIBLE_USER_ID = 988411;
    /**
     * 数据范围外的业务用户 ID，其礼赠记录必须被隔离。
     * @var int
     */
    private const CREATED_HIDDEN_USER_ID = 988412;
    /**
     * 导出用例专用的隔离管理员 ID，避免与数据范围用例的角色绑定互相污染。
     * @var int
     */
    private const ISOLATED_EXPORT_ADMIN_ID = 988499;

    /**
     * setUp 取出的 1 号超级管理员实例，作为主用例的登录身份。
     * @var Admin
     */
    private $admin;

    /**
     * ELIGIBLE_USER 默认收货地址的主键。新旧链路一致性断言以该地址为权威数据。
     * @var int
     */
    private $eligibleAddressId;

    /**
     * 导出用例生成的文件路径清单。tearDown 逐个删除，防止导出文件残留。
     * @var array<int, string>
     */
    private $exportFixturePaths = [];

    /**
     * 导出用例创建的目录清单。tearDown 在删除文件后移除空目录。
     * @var array<int, string>
     */
    private $exportFixtureDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->findOrFail(1);
        DB::table('gift_shipments')->whereIn('user_id', [
            self::ELIGIBLE_USER_ID,
            self::NON_DEFAULT_USER_ID,
            self::DISABLED_USER_ID,
            self::SOFT_DELETED_USER_ID,
            self::CREATED_VISIBLE_USER_ID,
            self::CREATED_HIDDEN_USER_ID,
        ])->delete();
        DB::table('user_addresses')->whereIn('user_id', [
            self::ELIGIBLE_USER_ID,
            self::NON_DEFAULT_USER_ID,
            self::DISABLED_USER_ID,
            self::SOFT_DELETED_USER_ID,
            self::CREATED_VISIBLE_USER_ID,
            self::CREATED_HIDDEN_USER_ID,
        ])->delete();
        DB::table('user_infos')->whereIn('user_id', [
            self::ELIGIBLE_USER_ID,
            self::NON_DEFAULT_USER_ID,
            self::DISABLED_USER_ID,
            self::SOFT_DELETED_USER_ID,
            self::CREATED_VISIBLE_USER_ID,
            self::CREATED_HIDDEN_USER_ID,
        ])->delete();
        $this->createUser(self::ELIGIBLE_USER_ID, 'Gift Eligible User', 1, (int) $this->admin->id);
        $this->createUser(self::NON_DEFAULT_USER_ID, 'Gift Non Default User', 1, (int) $this->admin->id);
        $this->createUser(self::DISABLED_USER_ID, 'Gift Disabled User', 0, (int) $this->admin->id);

        $this->eligibleAddressId = $this->createAddress(
            self::ELIGIBLE_USER_ID,
            'DB Authoritative Recipient',
            '13800008401',
            'DB Authoritative Address',
            1
        );
        $this->createAddress(
            self::NON_DEFAULT_USER_ID,
            'Non Default Recipient',
            '13800008402',
            'Non Default Address',
            0
        );
        $this->createAddress(
            self::DISABLED_USER_ID,
            'Gift Disabled Recipient',
            '13800008403',
            'Gift Disabled Address',
            1
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        foreach (array_unique($this->exportFixturePaths) as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
        foreach (array_reverse(array_unique($this->exportFixtureDirectories)) as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }

        parent::tearDown();
    }

    public function test_legacy_gift_browse_routes_use_workflow_permissions_and_render_shared_page(): void
    {
        $this->assertSame(
            'admin_api_giftAddressList',
            LegacyAdminController::permissionRouteForLegacyUri('index/admin/gift/send_gift_browse')
        );
        $this->assertSame(
            'admin_api_giftShipmentList',
            LegacyAdminController::permissionRouteForLegacyUri('index/admin/gift/shipment_list_browse')
        );

        $routes = collect(Route::getRoutes()->getRoutes());
        $sendRoute = $routes->first(static function ($route) {
            return $route->uri() === 'index/admin/gift/send_gift_browse';
        });
        $shipmentRoute = $routes->first(static function ($route) {
            return $route->uri() === 'index/admin/gift/shipment_list_browse';
        });
        $this->assertNotNull($sendRoute);
        $this->assertNotNull($shipmentRoute);
        $this->assertContains('legacy.admin.auth', $sendRoute->gatherMiddleware());
        $this->assertContains('legacy.admin.auth', $shipmentRoute->gatherMiddleware());
        $this->assertSame('admin_api_giftAddressList', $sendRoute->defaults['legacy_permission_route']);
        $this->assertSame('admin_api_giftShipmentList', $shipmentRoute->defaults['legacy_permission_route']);

        $sendHtml = $this->actingAs($this->admin, 'admin')
            ->get('/index/admin/gift/send_gift_browse')
            ->assertOk()
            ->getContent();
        $shipmentHtml = $this->actingAs($this->admin, 'admin')
            ->get('/index/admin/gift/shipment_list_browse')
            ->assertOk()
            ->getContent();
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';

        $this->assertStringContainsString('data-gift-page-mode="send"', $sendHtml);
        $this->assertStringContainsString('id="giftAddressTable"', $sendHtml);
        $this->assertStringContainsString('id="openSendGift"', $sendHtml);
        $this->assertStringContainsString('id="sendGiftModal"', $sendHtml);
        $this->assertMatchesRegularExpression(
            '/<option value="1"[^>]*selected[^>]*>/',
            $sendHtml,
            '旧发送页必须默认只加载默认地址。'
        );
        $this->assertStringNotContainsString('id="giftShipmentTable"', $sendHtml);
        $this->assertStringNotContainsString('id="exportGiftShipments"', $sendHtml);
        $this->assertStringNotContainsString('id="giftItemTable"', $sendHtml);

        $this->assertStringContainsString('data-gift-page-mode="shipments"', $shipmentHtml);
        $this->assertStringContainsString('id="giftShipmentTable"', $shipmentHtml);
        $this->assertStringContainsString('id="exportGiftShipments"', $shipmentHtml);
        $this->assertStringContainsString('id="updateGiftShipmentModal"', $shipmentHtml);
        $this->assertStringNotContainsString('id="giftAddressTable"', $shipmentHtml);
        $this->assertStringNotContainsString('id="openSendGift"', $shipmentHtml);
        $this->assertStringNotContainsString('id="sendGiftModal"', $shipmentHtml);
        $this->assertStringNotContainsString('id="giftItemTable"', $shipmentHtml);

        foreach (['giftItemTable', 'giftShipmentTable', 'giftAddressTable'] as $tableId) {
            $this->assertStringContainsString("if (document.getElementById('{$tableId}'))", $script);
        }
        $this->assertStringContainsString("where: pageMode === 'send' ? {is_default: 1} : {}", $script);
    }

    public function test_legacy_address_list_returns_only_default_gift_enabled_db_rows(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/addressList', [
                'page' => 1,
                'limit' => 20,
                'user_id' => self::ELIGIBLE_USER_ID,
                'is_default' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.rec_id', $this->eligibleAddressId)
            ->assertJsonPath('data.0.user_id', self::ELIGIBLE_USER_ID)
            ->assertJsonPath('data.0.recipient_name', 'DB Authoritative Recipient')
            ->assertJsonPath('data.0.is_default', 1)
            ->assertJsonPath('totalRow', []);

        $body = $response->getContent();
        $this->assertStringNotContainsString('Non Default Recipient', $body);
        $this->assertStringNotContainsString('Gift Disabled Recipient', $body);
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
    }

    public function test_soft_deleted_gift_user_is_hidden_from_both_address_lists_and_cannot_receive_shipments(): void
    {
        $this->createUser(
            self::SOFT_DELETED_USER_ID,
            'Soft Deleted Gift User',
            1,
            (int) $this->admin->id
        );
        $addressId = $this->createAddress(
            self::SOFT_DELETED_USER_ID,
            'Soft Deleted Gift Recipient',
            '13800008404',
            'Soft Deleted Gift Address',
            1
        );
        DB::table('user_infos')
            ->where('user_id', self::SOFT_DELETED_USER_ID)
            ->update(['deleted_at' => time()]);

        $modernList = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($this->admin, 'admin')
            ->postJson('/api/admin/giftAddressList', [
                'user_id' => self::SOFT_DELETED_USER_ID,
                'is_default' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.total', 0);
        $this->assertStringNotContainsString('Soft Deleted Gift Recipient', $modernList->getContent());

        $legacyList = $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/addressList', [
                'user_id' => self::SOFT_DELETED_USER_ID,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('count', 0);
        $this->assertStringNotContainsString('Soft Deleted Gift Recipient', $legacyList->getContent());

        $modernGiftName = 'Soft Deleted Modern Gift ' . uniqid('', true);
        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/admin/sendGift', [
                'sender_name' => 'Modern Sender',
                'gift_name' => $modernGiftName,
                'gift_quantity' => 1,
                'recipients' => [[
                    'user_id' => self::SOFT_DELETED_USER_ID,
                    'address_id' => $addressId,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $legacyGiftName = 'Soft Deleted Legacy Gift ' . uniqid('', true);
        $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/send_gift', [
                'giftInfo' => [
                    'sender_name' => 'Legacy Sender',
                    'gift_name' => $legacyGiftName,
                    'gift_quantity' => 1,
                ],
                'recipients' => [[
                    'user_id' => self::SOFT_DELETED_USER_ID,
                    'rec_id' => $addressId,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('code', 5000);

        $this->assertDatabaseMissing('gift_shipments', ['gift_name' => $modernGiftName]);
        $this->assertDatabaseMissing('gift_shipments', ['gift_name' => $legacyGiftName]);
    }

    public function test_legacy_shipment_list_applies_default_dates_and_old_envelope(): void
    {
        $giftPrefix = 'Legacy Date Range ' . uniqid('', true);
        $currentId = $this->createShipment([
            'gift_name' => $giftPrefix . ' Current',
            'recipient_name' => 'Current Recipient',
            'shipped_at' => date('Y-m-d H:i:s'),
        ]);
        $this->createShipment([
            'gift_name' => $giftPrefix . ' Before Default Range',
            'recipient_name' => 'Old Recipient',
            'shipped_at' => '2023-12-31 23:59:59',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/shipment_list', [
                'gift_name' => $giftPrefix,
                'page' => 1,
                'limit' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.rec_id', $currentId)
            ->assertJsonPath('data.0.gift_name', $giftPrefix . ' Current')
            ->assertJsonPath('data.0.user_id', self::ELIGIBLE_USER_ID)
            ->assertJsonPath('totalRow', []);

        $this->assertStringNotContainsString($giftPrefix . ' Before Default Range', $response->getContent());
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
        $this->assertArrayNotHasKey('status', $response->json('data.0'));
        $this->assertArrayNotHasKey('address_id', $response->json('data.0'));
    }

    public function test_legacy_shipment_list_maps_explicit_startdate_and_enddate_filters(): void
    {
        $giftPrefix = 'Legacy Explicit Date ' . uniqid('', true);
        $includedId = $this->createShipment([
            'gift_name' => $giftPrefix . ' Included',
            'shipped_at' => '2026-04-15 12:00:00',
        ]);
        $this->createShipment([
            'gift_name' => $giftPrefix . ' Excluded',
            'shipped_at' => '2026-04-16 00:00:00',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/shipment_list', [
                'gift_name' => $giftPrefix,
                'startdate' => '2026-04-15',
                'enddate' => '2026-04-15',
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.rec_id', $includedId);

        $this->assertStringNotContainsString($giftPrefix . ' Excluded', $response->getContent());
    }

    public function test_legacy_send_accepts_gift_info_and_rec_id_but_writes_db_address_snapshot(): void
    {
        $giftName = 'Legacy Nested Gift ' . uniqid('', true);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/send_gift', [
                'giftInfo' => [
                    'sender_name' => 'Legacy Sender',
                    'gift_name' => $giftName,
                    'gift_quantity' => 2,
                    'tracking_number' => '',
                    'remark' => 'legacy nested payload',
                ],
                'recipients' => [[
                    'rec_id' => $this->eligibleAddressId,
                    'user_id' => self::ELIGIBLE_USER_ID,
                    'recipient_name' => 'FORGED RECIPIENT',
                    'recipient_phone' => 'FORGED PHONE',
                    'recipient_address' => 'FORGED ADDRESS',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', '寄送成功');

        $this->assertDatabaseHas('gift_shipments', [
            'gift_name' => $giftName,
            'user_id' => self::ELIGIBLE_USER_ID,
            'address_id' => $this->eligibleAddressId,
            'recipient_name' => 'DB Authoritative Recipient',
            'recipient_phone' => '13800008401',
            'recipient_address' => 'DB Authoritative Address',
            'tracking_number' => '0',
            'status' => 1,
            'admin_id' => $this->admin->id,
        ]);
        $this->assertStringNotContainsString('FORGED', (string) DB::table('gift_shipments')
            ->where('gift_name', $giftName)
            ->value('recipient_name'));
    }

    public function test_legacy_send_validates_whole_batch_before_writing_any_row(): void
    {
        $giftName = 'Legacy Atomic Gift ' . uniqid('', true);

        $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/send_gift', [
                'giftInfo' => [
                    'sender_name' => 'Legacy Sender',
                    'gift_name' => $giftName,
                    'gift_quantity' => 1,
                ],
                'recipients' => [
                    [
                        'rec_id' => $this->eligibleAddressId,
                        'user_id' => self::ELIGIBLE_USER_ID,
                        'recipient_name' => 'ignored',
                        'recipient_phone' => 'ignored',
                        'recipient_address' => 'ignored',
                    ],
                    [
                        'rec_id' => 999999999,
                        'user_id' => self::ELIGIBLE_USER_ID,
                        'recipient_name' => 'missing',
                        'recipient_phone' => 'missing',
                        'recipient_address' => 'missing',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('code', 5000)
            ->assertJsonPath('data', []);

        $this->assertDatabaseMissing('gift_shipments', ['gift_name' => $giftName]);
    }

    public function test_legacy_export_returns_json_path_then_downloads_formula_safe_csv_once(): void
    {
        $this->createShipment([
            'gift_name' => '=Legacy Formula Gift',
            'recipient_name' => '+Formula Recipient',
            'recipient_phone' => '@13800008401',
            'recipient_address' => "\tFormula Address",
            'sender_name' => '-Formula Sender',
            'remark' => "\rFormula Remark",
            'shipped_at' => date('Y-m-d H:i:s'),
        ]);

        $prepare = $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/shipment_list_export', [
                'gift_name' => 'Legacy Formula',
            ])
            ->assertOk();

        $this->assertStringContainsString(
            'application/json',
            (string) $prepare->headers->get('content-type'),
            '旧导出第一阶段必须返回 JSON 下载路径，不能直接输出 CSV。'
        );
        $prepare->assertJsonPath('code', 0)
            ->assertJsonStructure(['data' => ['path']]);

        $path = (string) $prepare->json('data.path');
        $this->assertMatchesRegularExpression('#^index/admin/gift/shipment_list_download/[a-f0-9]{32}$#', $path);

        $download = $this->actingAs($this->admin, 'admin')
            ->get('/' . ltrim($path, '/'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $download->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        foreach (["'=Legacy Formula Gift", "'+Formula Recipient", "'@13800008401", "'\tFormula Address", "'-Formula Sender", "'\rFormula Remark"] as $escaped) {
            $this->assertStringContainsString($escaped, $csv);
        }

        $this->actingAs($this->admin, 'admin')
            ->get('/' . ltrim($path, '/'))
            ->assertNotFound();
    }

    public function test_legacy_export_returns_failure_json_when_filter_has_no_rows(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/shipment_list_export', [
                'gift_name' => 'NO_SUCH_GIFT_' . uniqid('', true),
            ])
            ->assertOk();

        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type'),
            '旧导出空结果必须返回失败 JSON，不能返回空 CSV。'
        );
        $response->assertJsonPath('code', 5000)
            ->assertJsonPath('data', []);
    }

    public function test_legacy_export_prunes_only_expired_matching_files_in_current_admin_directory(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 19, 12, 0, 0, config('app.timezone')));
        $now = now()->timestamp;
        $seed = uniqid('gift-export-ttl-', true);
        $expiredToken = md5($seed . '-expired');
        $boundaryToken = md5($seed . '-boundary');
        $freshToken = md5($seed . '-fresh');
        $otherAdminToken = md5($seed . '-other-admin');

        $expired = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            $expiredToken . '.csv',
            $now - 3601
        );
        $boundary = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            $boundaryToken . '.csv',
            $now - 3600
        );
        $fresh = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            $freshToken . '.csv',
            $now - 3599
        );
        $nonMatching = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            $expiredToken . '.txt',
            $now - 7200
        );
        $invalidCsvName = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            str_repeat('z', 32) . '.csv',
            $now - 7200
        );
        $otherAdmin = $this->createLegacyExportFixture(
            self::ISOLATED_EXPORT_ADMIN_ID,
            $otherAdminToken . '.csv',
            $now - 7200
        );

        $giftName = 'Legacy TTL Gift ' . $seed;
        $this->createShipment(['gift_name' => $giftName]);
        $prepare = $this->actingAs($this->admin, 'admin')
            ->postJson('/index/admin/gift/shipment_list_export', [
                'gift_name' => $giftName,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure(['data' => ['path']]);

        $generatedToken = basename((string) $prepare->json('data.path'));
        $generatedPath = storage_path(
            'app/legacy-admin-exports/admin/' . (int) $this->admin->id . '/' . $generatedToken . '.csv'
        );
        $this->exportFixturePaths[] = $generatedPath;

        clearstatcache();
        $this->assertFileDoesNotExist($expired);
        $this->assertFileExists($boundary, '恰好达到 TTL 的文件不应被提前删除。');
        $this->assertFileExists($fresh);
        $this->assertFileExists($nonMatching);
        $this->assertFileExists($invalidCsvName);
        $this->assertFileExists($otherAdmin);
        $this->assertFileExists($generatedPath);
    }

    public function test_legacy_export_download_deletes_expired_file_and_returns_not_found_without_cross_directory_cleanup(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 19, 12, 0, 0, config('app.timezone')));
        $now = now()->timestamp;
        $seed = uniqid('gift-download-expired-', true);
        $expiredToken = md5($seed . '-current');
        $otherAdminToken = md5($seed . '-other-admin');

        $expired = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            $expiredToken . '.csv',
            $now - 3601
        );
        $nonMatching = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            $expiredToken . '.txt',
            $now - 7200
        );
        $otherAdmin = $this->createLegacyExportFixture(
            self::ISOLATED_EXPORT_ADMIN_ID,
            $otherAdminToken . '.csv',
            $now - 7200
        );

        clearstatcache(true, $expired);
        $this->actingAs($this->admin, 'admin')
            ->get('/index/admin/gift/shipment_list_download/' . $expiredToken)
            ->assertNotFound();

        clearstatcache();
        $this->assertFileDoesNotExist($expired);
        $this->assertFileExists($nonMatching);
        $this->assertFileExists($otherAdmin);
    }

    public function test_legacy_export_download_allows_boundary_and_fresh_files_exactly_once(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 19, 12, 0, 0, config('app.timezone')));
        $now = now()->timestamp;
        $seed = uniqid('gift-download-valid-', true);
        $boundaryToken = md5($seed . '-boundary');
        $freshToken = md5($seed . '-fresh');
        $boundary = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            $boundaryToken . '.csv',
            $now - 3600
        );
        $fresh = $this->createLegacyExportFixture(
            (int) $this->admin->id,
            $freshToken . '.csv',
            $now - 1
        );

        foreach ([$boundaryToken => $boundary, $freshToken => $fresh] as $token => $path) {
            clearstatcache(true, $path);
            $download = $this->actingAs($this->admin, 'admin')
                ->get('/index/admin/gift/shipment_list_download/' . $token)
                ->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertSame("fixture\n", $download->getContent());
            $this->assertFileDoesNotExist($path);

            $this->actingAs($this->admin, 'admin')
                ->get('/index/admin/gift/shipment_list_download/' . $token)
                ->assertNotFound();
        }
    }

    public function test_created_scope_legacy_export_excludes_shipments_owned_by_other_admin_and_downloads_once(): void
    {
        $actor = $this->createCreatedScopeActor();
        $giftPrefix = 'Created Export Scope ' . uniqid('', true);
        $this->createShipment([
            'gift_name' => $giftPrefix . ' Visible',
            'admin_id' => self::CREATED_ADMIN_ID,
        ]);
        $this->createShipment([
            'gift_name' => $giftPrefix . ' Hidden',
            'admin_id' => 1,
        ]);

        $prepare = $this->actingAs($actor, 'admin')
            ->postJson('/index/admin/gift/shipment_list_export', [
                'gift_name' => $giftPrefix,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure(['data' => ['path']]);

        $path = (string) $prepare->json('data.path');
        $csv = $this->actingAs($actor, 'admin')
            ->get('/' . ltrim($path, '/'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString($giftPrefix . ' Visible', $csv);
        $this->assertStringNotContainsString($giftPrefix . ' Hidden', $csv);

        $this->actingAs($actor, 'admin')
            ->get('/' . ltrim($path, '/'))
            ->assertNotFound();
    }

    public function test_created_scope_uses_user_creator_for_addresses_and_send_but_shipment_creator_for_records(): void
    {
        $actor = $this->createCreatedScopeActor();
        $this->createUser(self::CREATED_VISIBLE_USER_ID, 'Created Visible Gift User', 1, self::CREATED_ADMIN_ID);
        $this->createUser(self::CREATED_HIDDEN_USER_ID, 'Created Hidden Gift User', 1, 1);
        $visibleAddressId = $this->createAddress(
            self::CREATED_VISIBLE_USER_ID,
            'Created Visible Recipient',
            '13800008411',
            'Created Visible Address',
            1
        );
        $this->createAddress(
            self::CREATED_HIDDEN_USER_ID,
            'Created Hidden Recipient',
            '13800008412',
            'Created Hidden Address',
            1
        );

        $visibleShipmentId = $this->createShipment([
            'user_id' => self::CREATED_VISIBLE_USER_ID,
            'address_id' => $visibleAddressId,
            'recipient_name' => 'Created Visible Recipient',
            'gift_name' => 'Created Visible Shipment',
            'admin_id' => self::CREATED_ADMIN_ID,
        ]);
        $hiddenShipmentId = $this->createShipment([
            'user_id' => self::CREATED_VISIBLE_USER_ID,
            'address_id' => $visibleAddressId,
            'recipient_name' => 'Created Visible Recipient',
            'gift_name' => 'Created Hidden Shipment',
            'admin_id' => 1,
        ]);

        $addressResponse = $this->actingAs($actor, 'admin')
            ->postJson('/index/admin/gift/addressList')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.user_id', self::CREATED_VISIBLE_USER_ID);
        $this->assertStringNotContainsString('Created Hidden Recipient', $addressResponse->getContent());

        $shipmentResponse = $this->actingAs($actor, 'admin')
            ->postJson('/index/admin/gift/shipment_list', ['gift_name' => 'Created'])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.rec_id', $visibleShipmentId);
        $this->assertStringNotContainsString('Created Hidden Shipment', $shipmentResponse->getContent());

        $giftName = 'Created Scope Sent Gift ' . uniqid('', true);
        $this->actingAs($actor, 'admin')
            ->postJson('/index/admin/gift/send_gift', [
                'giftInfo' => [
                    'sender_name' => 'Created Scope Sender',
                    'gift_name' => $giftName,
                    'gift_quantity' => 1,
                ],
                'recipients' => [[
                    'rec_id' => $visibleAddressId,
                    'user_id' => self::CREATED_VISIBLE_USER_ID,
                    'recipient_name' => 'ignored',
                    'recipient_phone' => 'ignored',
                    'recipient_address' => 'ignored',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0);
        $this->assertDatabaseHas('gift_shipments', [
            'gift_name' => $giftName,
            'user_id' => self::CREATED_VISIBLE_USER_ID,
            'admin_id' => self::CREATED_ADMIN_ID,
        ]);

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin')
            ->postJson('/api/admin/updateGiftShipment/' . $visibleShipmentId, [
                'status' => 3,
                'tracking_number' => 'CREATED-VISIBLE-UPDATED',
                'remark' => 'visible update',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin')
            ->postJson('/api/admin/updateGiftShipment/' . $hiddenShipmentId, [
                'status' => 3,
                'tracking_number' => 'CREATED-HIDDEN-UPDATED',
                'remark' => 'hidden update',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    private function createUser(int $userId, string $name, int $giftAllowed, int $createdBy): void
    {
        $now = time();
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => $name,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'is_gift_allowed' => $giftAllowed,
            'total_funds' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function createAddress(int $userId, string $name, string $phone, string $address, int $isDefault): int
    {
        $now = time();

        return (int) DB::table('user_addresses')->insertGetId([
            'user_id' => $userId,
            'recipient_name' => $name,
            'recipient_phone' => $phone,
            'recipient_address' => $address,
            'is_default' => $isDefault,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createShipment(array $overrides): int
    {
        $now = time();
        $row = array_replace([
            'user_id' => self::ELIGIBLE_USER_ID,
            'address_id' => $this->eligibleAddressId,
            'recipient_name' => 'DB Authoritative Recipient',
            'recipient_phone' => '13800008401',
            'recipient_address' => 'DB Authoritative Address',
            'sender_name' => 'Legacy DB Sender',
            'tracking_number' => 'LEGACY-TRACK-' . uniqid(),
            'gift_name' => 'Legacy DB Gift',
            'gift_quantity' => 1,
            'status' => 1,
            'remark' => '',
            'admin_id' => $this->admin->id,
            'shipped_at' => date('Y-m-d H:i:s'),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $overrides);

        return (int) DB::table('gift_shipments')->insertGetId($row);
    }

    private function createLegacyExportFixture(int $adminId, string $fileName, int $modifiedAt): string
    {
        $directory = storage_path('app/legacy-admin-exports/admin/' . $adminId);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            $this->fail('Unable to create legacy Gift export fixture directory.');
        }

        $path = $directory . DIRECTORY_SEPARATOR . $fileName;
        $this->assertNotFalse(file_put_contents($path, "fixture\n"));
        $this->assertTrue(touch($path, $modifiedAt));
        $this->exportFixturePaths[] = $path;
        $this->exportFixtureDirectories[] = $directory;

        return $path;
    }

    private function createCreatedScopeActor(): Admin
    {
        $now = time();
        DB::table('roles')->insert([
            'id' => self::CREATED_ROLE_ID,
            'name' => 'gift_created_scope_' . self::CREATED_ROLE_ID,
            'guard_type' => 'admin',
            'description' => 'Gift created-scope parity actor',
            'permissions' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->insert([
            'role_id' => self::CREATED_ROLE_ID,
            'scope_type' => 'created',
            'agent_ids' => null,
            'user_ids' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admins')->insert([
            'id' => self::CREATED_ADMIN_ID,
            'role_id' => (string) self::CREATED_ROLE_ID,
            'mobile' => null,
            'email' => 'gift-created-scope@example.test',
            'username' => 'gift_created_scope',
            'password' => bcrypt('password'),
            'login_count' => 0,
            'last_login_ip' => null,
            'last_login_at' => null,
            'last_login_address' => null,
            'status' => 1,
            'jwt_token_id' => null,
            'created_by' => 'gift-parity-test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        foreach ([
            'admin_api_giftAddressList',
            'admin_api_giftShipmentList',
            'admin_api_exportGiftShipments',
            'admin_api_sendGift',
            'admin_api_updateGiftShipment',
        ] as $apiRoute) {
            $permissionId = DB::table('permissions')
                ->where('guard_type', 'admin')
                ->where('api_route', $apiRoute)
                ->where('status', 1)
                ->value('id');
            $this->assertNotNull($permissionId, $apiRoute . ' permission must exist.');
            DB::table('role_permissions')->insert([
                'role_id' => self::CREATED_ROLE_ID,
                'permission_id' => (int) $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return Admin::query()->findOrFail(self::CREATED_ADMIN_ID);
    }
}
