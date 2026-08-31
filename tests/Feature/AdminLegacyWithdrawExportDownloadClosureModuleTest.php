<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 12:39
 */

/**
 * AdminLegacyWithdrawExportDownloadClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台出金导出与下载闭环：嵌套筛选/范围与旧 12 列、空结果不建文件、CSV 公式单元格中和且金额不转义、大精度金额精确保留、下载拒绝未知/不安全/他人文件。
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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLegacyWithdrawExportDownloadClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, string> */
    private array $generatedFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
            $directory = dirname($path);
            if (is_dir($directory) && count(scandir($directory) ?: []) === 2) {
                rmdir($directory);
            }
        }

        parent::tearDown();
    }

    public function test_legacy_prepare_and_download_use_nested_filters_scope_and_the_old_twelve_columns(): void
    {
        $allowedUserId = 993301;
        $blockedUserId = 993302;
        $admin = $this->seedScopedAdmin(993303, 993303, [$allowedUserId]);
        $this->seedWithdraw($allowedUserId, [
            'local_order_no' => 'WITHDRAW-EXPORT-ALLOWED',
            'mt4_ticket' => 'MT4-EXPORT-SCOPE',
        ]);
        $this->seedWithdraw($blockedUserId, [
            'local_order_no' => 'WITHDRAW-EXPORT-BLOCKED',
            'mt4_ticket' => 'MT4-EXPORT-SCOPE',
        ]);

        $prepare = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/amount/withdrawExport',
            ['data' => [
                'withdraw_id' => 'MT4-EXPORT-SCOPE',
                'withdraw_source' => '0',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ]]
        )->assertOk();
        $this->assertInstanceOf(JsonResponse::class, $prepare->baseResponse);
        $prepare->assertJsonStructure(['msg']);

        $message = (string) $prepare->json('msg');
        $this->assertMatchesRegularExpression(
            '#^index/admin/amount/withdraw_downloadfile/[A-Za-z0-9._-]+\.csv/admin$#',
            $message
        );
        $path = $this->rememberGeneratedFile($admin, $message);
        $this->assertFileExists($path);

        $download = $this->actingAs($admin, 'admin')
            ->get('/' . $message)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment;', (string) $download->headers->get('content-disposition'));

        $content = (string) file_get_contents($download->baseResponse->getFile()->getPathname());
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $rows = $this->parseCsv($content);
        $this->assertCount(12, $rows[0]);
        $this->assertCount(12, $rows[1]);
        $this->assertSame('MT4-EXPORT-SCOPE', $rows[1][0]);
        $this->assertSame((string) $allowedUserId, $rows[1][1]);
        $this->assertCount(2, $rows);
        $this->assertStringNotContainsString('WITHDRAW-EXPORT-BLOCKED', $content);
    }

    public function test_legacy_prepare_returns_fail_without_creating_a_file_for_an_empty_result(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $before = glob(storage_path('app/legacy-admin-exports/admin/1/withdrawals_1_*.csv')) ?: [];

        $prepare = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawExport', [
                'data' => ['withdraw_id' => 'MT4-EXPORT-DOES-NOT-EXIST'],
            ])
            ->assertOk();
        $this->assertInstanceOf(JsonResponse::class, $prepare->baseResponse);
        $prepare->assertJsonPath('msg', 'FAIL');

        $after = glob(storage_path('app/legacy-admin-exports/admin/1/withdrawals_1_*.csv')) ?: [];
        $this->assertSame($before, $after);
    }

    public function test_legacy_prepare_rejects_array_nested_filters_before_querying(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->seedWithdraw(993350, [
            'local_order_no' => 'WITHDRAW-EXPORT-ARRAY-FILTER',
            'mt4_ticket' => 'MT4-EXPORT-ARRAY-FILTER',
            'status' => 1,
        ]);

        $response = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/amount/withdrawExport',
            [
                'data' => [
                    'withdraw_id' => 'MT4-EXPORT-ARRAY-FILTER',
                    'withdraw_source' => [],
                    'withdraw_startdate' => '2026-08-01',
                    'withdraw_enddate' => '2026-08-31',
                ],
            ]
        );

        $message = (string) $response->json('msg', '');
        if (strpos($message, 'index/admin/amount/withdraw_downloadfile/') === 0) {
            $this->rememberGeneratedFile($admin, $message);
        }

        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_and_modern_csv_exports_neutralize_formula_cells_but_not_amounts(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->seedWithdraw(993304, [
            'local_order_no' => '=LOCAL-FORMULA',
            'third_order_no' => '+THIRD-FORMULA',
            'mt4_ticket' => '-MT4-FORMULA',
            'user_name' => '@USER-FORMULA',
            'bank_no' => '=BANK-NO',
            'bank_name' => '+BANK-NAME',
            'bank_addr' => '-BANK-ADDR',
            'reject_reason' => '@REJECT-REASON',
        ]);

        $prepare = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawExport', [
                'data' => ['withdraw_id' => '-MT4-FORMULA'],
            ])
            ->assertOk();
        $this->assertInstanceOf(JsonResponse::class, $prepare->baseResponse);
        $prepare->assertJsonStructure(['msg']);
        $legacyPath = $this->rememberGeneratedFile($admin, (string) $prepare->json('msg'));
        $legacyRows = $this->parseCsv((string) file_get_contents($legacyPath));
        $this->assertSame("'-MT4-FORMULA", $legacyRows[1][0]);
        $this->assertSame("'@USER-FORMULA", $legacyRows[1][2]);
        $this->assertSame("'=BANK-NO", $legacyRows[1][3]);
        $this->assertSame("'+BANK-NAME-BANK-ADDR", $legacyRows[1][4]);
        $this->assertSame('125.00', $legacyRows[1][5]);

        $modern = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')
            ->post('/api/admin/exportWithdrawals', ['mt4_ticket' => '-MT4-FORMULA'])
            ->assertOk();
        $modernRows = $this->parseCsv($modern->streamedContent());
        $header = array_flip($modernRows[0]);
        $row = $modernRows[1];
        $this->assertSame("'=LOCAL-FORMULA", $row[$header['local_order_no']]);
        $this->assertSame("'+THIRD-FORMULA", $row[$header['third_order_no']]);
        $this->assertSame("'-MT4-FORMULA", $row[$header['mt4_ticket']]);
        $this->assertSame("'@USER-FORMULA", $row[$header['user_name']]);
        $this->assertSame("'=BANK-NO", $row[$header['bank_no']]);
        $this->assertSame("'+BANK-NAME", $row[$header['bank_name']]);
        $this->assertSame("'-BANK-ADDR", $row[$header['bank_addr']]);
        $this->assertSame("'@REJECT-REASON", $row[$header['reject_reason']]);
        $this->assertSame('125.00', $row[$header['apply_amount']]);
    }

    public function test_legacy_and_modern_csv_exports_neutralize_every_dangerous_prefix(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $mt4Ticket = 'MT4-EXPORT-DANGEROUS-PREFIXES';
        $dangerousValues = [
            '=FORMULA',
            '+FORMULA',
            '-FORMULA',
            '@FORMULA',
            "\tFORMULA",
            "\rFORMULA",
            "\nFORMULA",
        ];

        foreach ($dangerousValues as $index => $dangerousValue) {
            $this->seedWithdraw(993310 + $index, [
                'local_order_no' => 'WITHDRAW-EXPORT-DANGEROUS-' . $index,
                'mt4_ticket' => $mt4Ticket,
                'user_name' => $dangerousValue,
            ]);
        }

        $prepare = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawExport', [
                'data' => ['withdraw_id' => $mt4Ticket],
            ])
            ->assertOk();
        $this->assertInstanceOf(JsonResponse::class, $prepare->baseResponse);
        $legacyPath = $this->rememberGeneratedFile($admin, (string) $prepare->json('msg'));
        $legacyRows = $this->parseCsv((string) file_get_contents($legacyPath));
        $legacyUserNames = array_column(array_slice($legacyRows, 1), 2);

        $modern = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')
            ->post('/api/admin/exportWithdrawals', ['mt4_ticket' => $mt4Ticket])
            ->assertOk();
        $modernRows = $this->parseCsv($modern->streamedContent());
        $header = array_flip($modernRows[0]);
        $modernDataRows = array_slice($modernRows, 1);
        $modernUserNames = array_column($modernDataRows, $header['user_name']);

        $this->assertCount(count($dangerousValues), $legacyUserNames);
        $this->assertCount(count($dangerousValues), $modernUserNames);
        foreach ($dangerousValues as $dangerousValue) {
            $expected = "'" . $dangerousValue;
            $this->assertContains($expected, $legacyUserNames);
            $this->assertContains($expected, $modernUserNames);
        }
        foreach (array_slice($legacyRows, 1) as $row) {
            $this->assertSame('125.00', $row[5]);
        }
        foreach ($modernDataRows as $row) {
            $this->assertSame('125.00', $row[$header['apply_amount']]);
        }
    }

    public function test_legacy_and_modern_exports_preserve_large_decimal_amounts_and_exact_conversion(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->seedWithdraw(993305, [
            'local_order_no' => 'WITHDRAW-EXPORT-LARGE-DECIMAL',
            'mt4_ticket' => 'MT4-EXPORT-LARGE-DECIMAL',
            'apply_amount' => '99999999999999.99',
            'actual_amount' => '99999999999999.99',
            'exchange_rate' => '9.99999999',
        ]);

        $prepare = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawExport', [
                'data' => ['withdraw_id' => 'MT4-EXPORT-LARGE-DECIMAL'],
            ])
            ->assertOk();
        $this->assertInstanceOf(JsonResponse::class, $prepare->baseResponse);
        $legacyPath = $this->rememberGeneratedFile($admin, (string) $prepare->json('msg'));
        $legacyRows = $this->parseCsv((string) file_get_contents($legacyPath));
        $this->assertSame('99999999999999.99', $legacyRows[1][5]);
        $this->assertSame('99999999999999.99', $legacyRows[1][6]);
        $this->assertSame('9.99999999', $legacyRows[1][7]);
        $this->assertSame('999999998999999.90', $legacyRows[1][8]);

        $modern = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')
            ->post('/api/admin/exportWithdrawals', ['mt4_ticket' => 'MT4-EXPORT-LARGE-DECIMAL'])
            ->assertOk();
        $modernRows = $this->parseCsv($modern->streamedContent());
        $header = array_flip($modernRows[0]);
        $row = $modernRows[1];
        $this->assertSame('99999999999999.99', $row[$header['apply_amount']]);
        $this->assertSame('99999999999999.99', $row[$header['actual_amount']]);
        $this->assertSame('9.99999999', $row[$header['exchange_rate']]);
    }

    public function test_download_rejects_unknown_unsafe_wrong_role_and_other_admin_files(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $otherDirectory = storage_path('app/legacy-admin-exports/admin/993399');
        if (!is_dir($otherDirectory)) {
            mkdir($otherDirectory, 0750, true);
        }
        $otherFile = $otherDirectory . DIRECTORY_SEPARATOR . 'withdrawals_993399_private.csv';
        file_put_contents($otherFile, "\xEF\xBB\xBFprivate");
        $this->generatedFiles[] = $otherFile;

        foreach ([
            '/index/admin/amount/withdraw_downloadfile/not-created.csv/admin',
            '/index/admin/amount/withdraw_downloadfile/not-created.php/admin',
            '/index/admin/amount/withdraw_downloadfile/withdrawals_1_..csv/admin',
            '/index/admin/amount/withdraw_downloadfile/not-created.csv/front',
            '/index/admin/amount/withdraw_downloadfile/withdrawals_993399_private.csv/admin',
        ] as $uri) {
            $this->actingAs($admin, 'admin')->get($uri)->assertNotFound();
        }
    }

    private function seedWithdraw(int $userId, array $overrides): int
    {
        $localOrderNo = (string) ($overrides['local_order_no'] ?? ('WITHDRAW-EXPORT-' . $userId));
        $timestamp = strtotime('2026-08-10 12:00:00');

        return (int) DB::table('withdraw_records')->insertGetId(array_replace([
            'user_id' => $userId,
            'user_name' => 'withdraw-export-' . $userId,
            'mt4_ticket' => 'MT4-EXPORT-' . $userId,
            'apply_amount' => '125.00',
            'actual_amount' => '120.00',
            'fee' => '5.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '35.00',
            'bank_no' => '62220000' . $userId,
            'bank_name' => 'Export Bank',
            'bank_addr' => 'Shanghai Branch',
            'status' => 0,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => $localOrderNo,
            'funding_status' => 'debited',
            'funding_payload_hash' => hash('sha256', $localOrderNo),
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ], $overrides));
    }

    private function seedScopedAdmin(int $adminId, int $roleId, array $userIds): Admin
    {
        $now = time();
        DB::table('roles')->updateOrInsert(['id' => $roleId], [
            'name' => 'legacy-withdraw-export-' . $roleId,
            'guard_type' => 'admin',
            'description' => 'Legacy withdrawal export scope',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->updateOrInsert(['role_id' => $roleId], [
            'scope_type' => 'custom_users',
            'agent_ids' => null,
            'user_ids' => json_encode($userIds),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->where('role_id', $roleId)->delete();
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $this->permissionIdForRoute('admin_api_exportWithdrawals'),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admins')->updateOrInsert(['id' => $adminId], [
            'role_id' => (string) $roleId,
            'email' => 'legacy-withdraw-export-' . $adminId . '@example.test',
            'username' => 'legacy_withdraw_export_' . $adminId,
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }

    private function permissionIdForRoute(string $apiRoute): int
    {
        $permission = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('api_route', $apiRoute)
            ->orderBy('id')
            ->first();
        if ($permission) {
            DB::table('permissions')->where('id', $permission->id)->update([
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

            return (int) $permission->id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'parent_id' => 0,
            'name' => $apiRoute,
            'slug' => 'test_' . md5($apiRoute),
            'api_route' => $apiRoute,
            'route' => '',
            'icon' => '',
            'type' => 3,
            'guard_type' => 'admin',
            'sort' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    private function rememberGeneratedFile(Admin $admin, string $message): string
    {
        $segments = explode('/', str_replace('\\', '/', trim($message, '/')));
        $filename = (string) ($segments[count($segments) - 2] ?? '');
        $path = storage_path('app/legacy-admin-exports/admin/' . $admin->id . '/' . $filename);
        $this->generatedFiles[] = $path;

        return $path;
    }

    /** @return array<int, array<int, string|null>> */
    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $content);
        rewind($stream);
        if (fread($stream, 3) !== "\xEF\xBB\xBF") {
            rewind($stream);
        }

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
