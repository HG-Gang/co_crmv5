<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 09:03
 */

/**
 * AdminPermissionNameReferenceRowCompletenessTest
 *
 * 文件功能：
 * - 验证每个后台权限在参考文档中都有 name、slug 与生效方式构成的完整一行。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台权限名称说明文档逐行完整性测试。
 *
 * 功能逻辑说明：
 * - 用户要求后台所有权限名称必须在 MD 文件中写明中文注释、对应权限字符串和功能作用。
 * - 本测试直接读取真实数据库 permissions 表，验证每一条后台权限都在文档表格中拥有独立行。
 * - 每一行必须同时包含权限名称、权限字符串、接口路由、页面路由、状态和功能作用，避免文档只零散堆叠字符串。
 */
class AdminPermissionNameReferenceRowCompletenessTest extends TestCase
{
    /**
     * 后台权限说明 MD 必须逐条解释真实数据库中的权限名称、字符串和功能作用。
     *
     * 参数与变量含义：
     * - $documentPath：权限说明文档路径，固定指向 docs/admin-permission-name-reference.md。
     * - $document：MD 文档完整文本，用于检查字段说明、维护规则和权限明细表格。
     * - $rows：从文档表格解析出的权限行，键为 permissions.slug，值为当前行的各列文本。
     * - $permission：真实数据库 permissions 表中的单条后台权限记录。
     * - $slug：权限稳定字符串，对应 permissions.slug，是菜单、按钮和接口鉴权的核心标识。
     * - $name：可读权限名称，对应 permissions.name，必须在同一行里说明当前权限控制的对象。
     *
     * @return void
     */
    public function test_every_admin_permission_has_name_slug_and_effect_in_one_document_row(): void
    {
        $documentPath = base_path('docs/admin-permission-name-reference.md');
        $document = (string) file_get_contents($documentPath);
        $rows = $this->permissionRowsBySlug($document);

        $permissions = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'api_route', 'route']);

        $this->assertStringContainsString('## 权限名称中文注释规则', $document);
        $this->assertCount($permissions->count(), $rows, '权限说明表格行数必须等于真实后台权限数量。');

        foreach ($permissions as $permission) {
            $slug = trim((string) $permission->slug);
            $name = $this->readablePermissionName((string) $permission->name);
            $apiRoute = trim((string) $permission->api_route);
            $pageRoute = trim((string) $permission->route);

            $this->assertArrayHasKey($slug, $rows, $slug . ' 缺少独立权限说明行。');

            $row = $rows[$slug];
            $effect = $row['effect'];

            $this->assertSame($name, $row['name'], $slug . ' 的权限名称必须在同一行保持可读中文。');
            $this->assertSame('`' . $slug . '`', $row['slug'], $slug . ' 的权限字符串必须在同一行用反引号标记。');
            $this->assertSame($apiRoute === '' ? '-' : '`' . $apiRoute . '`', $row['api_route'], $slug . ' 的接口路由说明不完整。');
            $this->assertSame($pageRoute === '' ? '-' : '`' . $pageRoute . '`', $row['page_route'], $slug . ' 的页面路由说明不完整。');
            $this->assertStringContainsString($name, $effect, $slug . ' 的功能作用必须点名当前权限名称。');
            $this->assertGreaterThanOrEqual(30, mb_strlen($effect), $slug . ' 的功能作用说明过短。');
            $this->assertMatchesRegularExpression('/菜单|按钮|接口|页面|鉴权|授权/u', $effect, $slug . ' 的功能作用必须说明业务控制边界。');
        }
    }

    /**
     * 从权限说明文档中解析权限明细表格。
     *
     * 参数与变量含义：
     * - $document：MD 文档完整文本。
     * - $rows：解析后的权限行数组，键为权限字符串，值包含名称、路由和功能作用等列。
     * - $columns：当前表格行按竖线拆分后的列数组。
     * - $slug：去掉 Markdown 反引号后的权限字符串，用作去重键。
     *
     * @param string $document 权限说明 MD 文档完整文本。
     * @return array<string, array{name: string, slug: string, api_route: string, page_route: string, effect: string}> 以权限字符串索引的表格行。
     */
    private function permissionRowsBySlug(string $document): array
    {
        $rows = [];

        foreach (preg_split('/\R/u', $document) as $line) {
            if (! preg_match('/^\|\s*\d+\s*\|/u', $line)) {
                continue;
            }

            $columns = array_map('trim', explode('|', trim($line, '|')));
            if (count($columns) < 9) {
                continue;
            }

            $slug = trim($columns[4], '`');
            $rows[$slug] = [
                'name' => $columns[3],
                'slug' => $columns[4],
                'api_route' => $columns[5],
                'page_route' => $columns[6],
                'effect' => $columns[8],
            ];
        }

        return $rows;
    }

    /**
     * 将历史编码异常的权限名称尽量还原为可读中文。
     *
     * 参数与变量含义：
     * - $name：来自 permissions.name 的原始权限名称。
     * - $converted：尝试把历史 mojibake 文本按 GBK 反向转换后的可读中文。
     *
     * @param string $name 原始权限名称。
     * @return string 可读权限名称；无法安全转换时返回原值。
     */
    private function readablePermissionName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $converted = @iconv('UTF-8', 'GBK//IGNORE', $name);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8') && preg_match('/\p{Han}/u', $converted)) {
            return $converted;
        }

        return $name;
    }
}
