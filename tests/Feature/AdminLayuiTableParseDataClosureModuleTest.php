<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 13:20
 */

/**
 * AdminLayuiTableParseDataClosureModuleTest
 *
 * 文件功能：
 * - 锁定 layui 表格的响应解析器必须写成 CrmTable.layuiParseData() 工厂调用形式。
 * - 输入：public/js 下的前端源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖表格运行时渲染结果（由 scripts/ui-acceptance 浏览器验收锁定）。
 */

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * 锁定 layui 表格响应解析器的调用形式。
 *
 * 背景（真实缺陷，非假设）：
 * - layuiParseData(listPath) 是**工厂函数**，返回的才是真正的解析器。
 * - 一旦写成 CrmTable.layuiParseData(response)，响应体会被当作 listPath 传入，
 *   返回值是内层函数本身而不是解析结果。layui 随后读 res.code 得 undefined
 *   （≠ 0），读 res.msg 也得 undefined（falsy），于是回退到
 *   「返回的数据不符合规范，正确的成功状态码应为：'code': 0」提示，
 *   表格永远加载不出任何数据。
 * - 该缺陷曾同时命中 /admin/gifts 的三张表与 /admin/online-users 的一张表。
 *   接口本身完全正常（POST 返回 code=1000 且有数据），所以只打接口的测试全绿，
 *   必须靠本测试从调用形式上锁住。
 */
class AdminLayuiTableParseDataClosureModuleTest extends TestCase
{
    /**
     * 任何调用点都不得给 layuiParseData 工厂传参。
     *
     * @return void
     */
    public function test_no_call_site_passes_argument_to_layui_parse_data_factory(): void
    {
        $offenders = [];

        foreach ($this->projectJsFiles() as $path) {
            $source = file_get_contents($path) ?: '';
            // 调用点一律通过 CrmTable 前缀访问，因此该模式不会命中 table-common.js 里的函数定义。
            if (preg_match_all('/CrmTable\.layuiParseData\(\s*[^)\s]/', $source, $matches) > 0) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path)
                    . '（' . count($matches[0]) . ' 处）';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'layuiParseData 是工厂函数，必须写成 CrmTable.layuiParseData()（可再接 (res) 调用）。'
            . '传参会把响应体当 listPath，返回内层函数本身，导致 layui 表格永远加载不出数据。'
            . '违规文件：' . implode('、', $offenders)
        );
    }

    /**
     * 工厂必须被导出且确实被使用，防止「删掉调用点」这种反向绕过。
     *
     * @return void
     */
    public function test_factory_is_exported_and_actually_used(): void
    {
        $shared = file_get_contents(public_path('js/shared/table-common.js')) ?: '';
        $this->assertStringContainsString('function layuiParseData(listPath)', $shared);
        $this->assertStringContainsString('layuiParseData: layuiParseData', $shared);

        $adminPages = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $this->assertGreaterThan(
            20,
            substr_count($adminPages, 'CrmTable.layuiParseData()'),
            '后台 layui 页面应普遍使用 CrmTable.layuiParseData() 解析响应封套。'
        );
    }

    /**
     * 收集需要检查的项目自有 JS 文件。
     *
     * 逻辑说明：
     * - 递归扫描而非硬编码文件清单：以后新增页面脚本会自动纳入检查，
     *   避免「加了新页面但忘了加进测试」这类漏网。
     * - 跳过 vendor：第三方库不受本项目调用约定管辖。
     *
     * @return array<int, string> 绝对路径列表。
     */
    private function projectJsFiles(): array
    {
        $root = public_path('js');
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'js') {
                continue;
            }
            $path = $file->getPathname();
            if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }
            $files[] = $path;
        }

        return $files;
    }
}
