<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 05:36
 */

/**
 * AdminLegacyFundingPageAndDownloadClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台资金页面与导出文件下载入口：页面 layui 模块目标、导出文件可下载、路径穿越与缺失文件被拒绝。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * 锁定旧后台资金页面和导出文件下载入口的兼容行为。
 */
class AdminLegacyFundingPageAndDownloadClosureModuleTest extends TestCase
{
    /**
     * 旧版导出文件的存储目录（storage/app/legacy-admin-exports/admin）。用例前后清理，防止导出文件残留。
     * @var string
     */
    private const EXPORT_DIR = 'app/legacy-admin-exports/admin';
    /**
     * 下载用例使用的固定导出文件名。验证旧版下载路由能正确返回该文件。
     * @var string
     */
    private const EXPORT_FILE = 'legacy-deposit-download-test.xlsx';

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(storage_path(self::EXPORT_DIR));
        File::delete(storage_path(self::EXPORT_DIR . '/' . self::EXPORT_FILE));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path(self::EXPORT_DIR . '/' . self::EXPORT_FILE));

        parent::tearDown();
    }

    public function test_legacy_funding_pages_keep_their_layui_module_targets(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->legacyRequest($admin)
            ->get('/index/admin/amount/deposit_flow')
            ->assertOk()
            ->assertSee('data-layui-page="deposits/index"', false);

        $this->legacyRequest($admin)
            ->get('/index/admin/amount/undeposit_flow')
            ->assertOk()
            ->assertSee('data-layui-page="undeposit-flows/index"', false);

        $this->legacyRequest($admin)
            ->get('/index/admin/amount/rights_summary')
            ->assertOk()
            ->assertSee('data-layui-page="rights-summary/index"', false);
    }

    public function test_legacy_deposit_downloadfile_serves_the_generated_admin_export(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $contents = "legacy deposit export\n";
        file_put_contents(storage_path(self::EXPORT_DIR . '/' . self::EXPORT_FILE), $contents);

        $response = $this->legacyRequest($admin)
            ->get('/index/admin/amount/depositDownloadfile/legacy-deposit-download-test/admin');

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame($contents, file_get_contents($response->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString(self::EXPORT_FILE, (string) $response->headers->get('content-disposition'));
    }

    public function test_legacy_deposit_downloadfile_rejects_path_traversal_and_missing_files(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->legacyRequest($admin)
            ->get('/index/admin/amount/depositDownloadfile/../../.env/admin')
            ->assertNotFound();

        $this->legacyRequest($admin)
            ->get('/index/admin/amount/depositDownloadfile/not-present/admin')
            ->assertNotFound();
    }

    public function test_legacy_rights_downloadfile_serves_the_generated_admin_export(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $contents = "legacy rights export\n";
        file_put_contents(storage_path(self::EXPORT_DIR . '/' . self::EXPORT_FILE), $contents);

        $response = $this->legacyRequest($admin)
            ->get('/index/admin/amount/rights_downloadfile/legacy-deposit-download-test/admin');

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame($contents, file_get_contents($response->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString(self::EXPORT_FILE, (string) $response->headers->get('content-disposition'));
    }

    private function legacyRequest(Admin $admin): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }
}
