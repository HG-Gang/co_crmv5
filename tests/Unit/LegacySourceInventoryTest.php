<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 11:57
 */

/**
 * 旧项目源码逻辑清单服务单元测试。
 *
 * 文件功能：
 * - 校验 LegacySourceInventory::inspect 从旧 PHP 控制器、Blade 视图与 JS 中提取控制器方法、请求字段、表、视图、表单、AJAX 端点与脚本引用。
 * - 校验 toMarkdown 生成 UTF-8 Markdown 清单，以及 CLI 脚本输出 JSON 与 Markdown 文件。
 *
 * 适用场景：
 * - 改动旧源码扫描器、清单生成或 scripts/generate-legacy-source-inventory.php 后回归。
 *
 * 入参例子：
 * - inspect($fixtureRoot) 传入临时旧项目根目录，内含 DepositController.php、list.blade.php、deposit.js 等。
 * - CLI：php scripts/generate-legacy-source-inventory.php --legacy-root=... --json=... --markdown=... --project-name=测试旧项目。
 *
 * 返回值：断言通过表示提取结果与预期计数/字段完全一致。
 *
 * 异常或失败场景：
 * - 控制器方法、请求字段、表名、表单字段、端点或 JS 文件统计与预期不符，或 CLI 退出码非 0 时失败。
 */
namespace Tests\Unit;

use App\Support\LegacySourceInventory;
use Tests\TestCase;

class LegacySourceInventoryTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'legacy-source-inventory-' . uniqid('', true);
        mkdir($this->fixtureRoot . '/app/Http/Controllers/User', 0777, true);
        mkdir($this->fixtureRoot . '/resources/views/user/deposit', 0777, true);
        mkdir($this->fixtureRoot . '/public/js', 0777, true);

        file_put_contents($this->fixtureRoot . '/app/Http/Controllers/User/DepositController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepositController
{
    public function index(Request $request)
    {
        $status = $request->input('status');
        $rows = DB::table('deposit_records')->where('status', $status)->get();

        return view('user.deposit.list', compact('rows'));
    }

    public function save(Request $request)
    {
        $legacyCode = $request->legacy_code;
        if ((float) $request->input('amount') <= 0) {
            return response()->json(['code' => 4005, 'msg' => 'invalid amount']);
        }

        return response()->json(['code' => 1000]);
    }
}
PHP
        );

        file_put_contents($this->fixtureRoot . '/resources/views/user/deposit/list.blade.php', <<<'BLADE'
<form action="/user/deposit/apply" method="post">
    <input name="amount" type="number">
    <select name="channel_id"></select>
</form>
<script>
$.ajax({ url: '/user/deposit/list', type: 'POST', data: { status: 1 } });
</script>
<script src="{{ asset('js/deposit.js') }}?v=1"></script>
<script src="{{ URL::asset('js/history.js') }}"></script>
BLADE
        );

        file_put_contents($this->fixtureRoot . '/public/js/deposit.js', <<<'JS'
$.post('/user/deposit/history', { page: 1 });
JS
        );
        file_put_contents($this->fixtureRoot . '/public/js/history.js', <<<'JS'
fetch('/user/deposit/export');
JS
        );
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->fixtureRoot);

        parent::tearDown();
    }

    /**
     * 校验从旧源码中提取控制器与 Blade 交互信息。
     *
     * @return void 断言通过不返回值。
     */
    public function test_it_extracts_controller_and_blade_interactions_from_legacy_source(): void
    {
        $inventory = (new LegacySourceInventory())->inspect($this->fixtureRoot);

        $this->assertSame(1, $inventory['summary']['controller_files']);
        $this->assertSame(1, $inventory['summary']['blade_files']);

        $controller = $inventory['controllers'][0];
        $this->assertSame('App\\Http\\Controllers\\User\\DepositController', $controller['class']);
        $this->assertSame(['index', 'save'], array_column($controller['methods'], 'name'));
        $this->assertSame(['status'], $controller['methods'][0]['request_fields']);
        $this->assertSame(['legacy_code', 'amount'], $controller['methods'][1]['request_fields']);
        $this->assertSame(['deposit_records'], $controller['methods'][0]['tables']);
        $this->assertSame(['user.deposit.list'], $controller['methods'][0]['views']);
        $this->assertSame(1, $controller['methods'][1]['conditional_branches']);
        $this->assertSame(2, $controller['methods'][1]['return_statements']);

        $blade = $inventory['blades'][0];
        $this->assertSame('/user/deposit/apply', $blade['forms'][0]['action']);
        $this->assertSame('POST', $blade['forms'][0]['method']);
        $this->assertSame(['amount', 'channel_id'], $blade['forms'][0]['fields']);
        $this->assertSame(['/user/deposit/list'], $blade['ajax_endpoints']);
        $this->assertSame(['js/deposit.js', 'js/history.js'], $blade['script_sources']);
        $this->assertSame(['/user/deposit/history', '/user/deposit/export'], $blade['script_endpoints']);
        $this->assertSame(2, $inventory['summary']['javascript_files']);
    }

    /**
     * 校验输出 UTF-8 Markdown 清单供人工审阅。
     *
     * @return void 断言通过不返回值。
     */
    public function test_it_renders_a_utf8_markdown_inventory_for_human_review(): void
    {
        $inventory = (new LegacySourceInventory())->inspect($this->fixtureRoot);
        $markdown = (new LegacySourceInventory())->toMarkdown($inventory, '测试旧项目');

        $this->assertStringContainsString('# 测试旧项目源码逻辑清单', $markdown);
        $this->assertStringContainsString('Controller 文件：1', $markdown);
        $this->assertStringContainsString('`DepositController::save`', $markdown);
        $this->assertStringContainsString('`/user/deposit/apply`', $markdown);
        $this->assertStringContainsString('`/user/deposit/list`', $markdown);
    }

    /**
     * 校验 CLI 脚本可写出 JSON 与 UTF-8 Markdown 清单文件。
     *
     * @return void 断言通过不返回值。
     */
    public function test_the_inventory_cli_writes_json_and_utf8_markdown(): void
    {
        $jsonPath = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'inventory.json';
        $markdownPath = $this->fixtureRoot . DIRECTORY_SEPARATOR . '清单.md';
        $script = base_path('scripts/generate-legacy-source-inventory.php');
        $command = sprintf(
            '%s %s --legacy-root=%s --json=%s --markdown=%s --project-name=%s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            escapeshellarg($this->fixtureRoot),
            escapeshellarg($jsonPath),
            escapeshellarg($markdownPath),
            escapeshellarg('测试旧项目')
        );

        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
        $this->assertFileExists($jsonPath);
        $this->assertFileExists($markdownPath);
        $this->assertSame(1, json_decode((string) file_get_contents($jsonPath), true)['summary']['controller_files']);
        $this->assertStringContainsString('# 测试旧项目源码逻辑清单', (string) file_get_contents($markdownPath));
    }

    /**
     * 默认 CLI 必须指向已确认旧项目目录，并保持纯文件静态扫描边界。
     */
    public function test_cli_defaults_to_the_confirmed_legacy_workspace_and_remains_static_only(): void
    {
        $source = (string) file_get_contents(base_path('scripts/generate-legacy-source-inventory.php'));

        $confirmedDefault = <<<'PATH'
D:\\Software\\PhpProject\\Demo\\new_co_gmtk_crmv3
PATH;
        $obsoleteDefault = <<<'PATH'
D:\\Php-project\\Php\\new_co_gmtk_crmV3
PATH;

        $this->assertStringContainsString($confirmedDefault, $source);
        $this->assertStringNotContainsString($obsoleteDefault, $source);
        $this->assertStringNotContainsString('new PDO(', $source);
        $this->assertStringNotContainsString('DB::', $source);
        $this->assertStringNotContainsString('bootstrap/app.php', $source);
        $this->assertStringNotContainsString('Kernel::class', $source);
        $this->assertStringContainsString('new LegacySourceInventory()', $source);
        $this->assertStringContainsString('仅静态读取旧项目 PHP 与 Blade 源码', $source);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
