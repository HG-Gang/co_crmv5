<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:20
 */

/**
 * AdminPermissionNameReferenceDocumentationTest
 *
 * 文件功能：
 * - 验证权限名参考文档覆盖数据库中全部权限，并将历史 mojibake 权限名还原为可读中文。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台权限名称说明文档覆盖测试。
 *
 * 功能逻辑说明：
 * - 用户要求后台所有权限名称都必须在 MD 文件中写明中文注释、对应字符串和功能作用。
 * - 本测试直接读取真实 `permissions` 表中 `guard_type=admin` 的权限记录，验证文档没有遗漏任何后台权限字符串。
 * - 文档是给角色授权、菜单显示、按钮显隐和接口鉴权维护人员阅读的，因此还必须包含字段说明与功能作用说明。
 */
class AdminPermissionNameReferenceDocumentationTest extends TestCase
{
    /**
     * 后台权限说明 MD 必须覆盖真实数据库中的全部后台权限。
     *
     * 参数与变量含义：
     * - $documentPath：后台权限说明 MD 文件路径，当前固定为 docs/admin-permission-name-reference.md。
     * - $document：MD 文件完整内容，用于检查权限字符串、接口路由字符串和页面路由是否存在。
     * - $permissions：真实数据库 `permissions` 表中 `guard_type=admin` 的全部后台权限。
     * - $name：权限中文名称，历史乱码名称会先做可读还原，避免文档继续保留不可读字符串。
     * - $slug：权限稳定字符串，对应 `permissions.slug`，也是 Blade/JS 控制按钮显隐的核心值。
     * - $apiRoute：接口路由字符串，对应 `permissions.api_route`，由 `check.permission:admin` 执行接口鉴权。
     * - $pageRoute：页面路由，对应 `permissions.route`，用于后台 Blade 菜单跳转。
     *
     * @return void
     */
    public function test_admin_permission_reference_document_covers_all_database_permissions(): void
    {
        $documentPath = base_path('docs/admin-permission-name-reference.md');

        $this->assertFileExists($documentPath, '后台权限名称说明 MD 文件不存在。');

        $document = (string) file_get_contents($documentPath);
        $permissions = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->orderBy('id')
            ->get(['name', 'slug', 'api_route', 'route']);

        $this->assertGreaterThan(0, $permissions->count(), '真实数据库中没有读取到后台权限记录。');
        $this->assertStringContainsString('# 后台权限名称、字符串与功能作用说明', $document);
        $this->assertStringContainsString('权限字符串', $document);
        $this->assertStringContainsString('接口路由字符串', $document);
        $this->assertStringContainsString('功能作用', $document);
        $this->assertStringContainsString('后台权限总数：`' . $permissions->count() . '`', $document);

        foreach ($permissions as $permission) {
            $name = $this->readablePermissionName((string) $permission->name);
            $slug = trim((string) $permission->slug);
            $apiRoute = trim((string) $permission->api_route);
            $pageRoute = trim((string) $permission->route);

            $this->assertNotSame('', $slug, '后台权限存在空 slug，无法写入稳定权限字符串说明。');
            $this->assertStringContainsString('`' . $slug . '`', $document, $slug . ' 未写入后台权限说明 MD。');

            if ($name !== '') {
                $this->assertStringContainsString($name, $document, $slug . ' 缺少可读权限名称说明。');
            }

            if ($apiRoute !== '') {
                $this->assertStringContainsString('`' . $apiRoute . '`', $document, $slug . ' 缺少接口路由字符串说明。');
            }

            if ($pageRoute !== '') {
                $this->assertStringContainsString('`' . $pageRoute . '`', $document, $slug . ' 缺少页面路由说明。');
            }
        }
    }

    /**
     * 将历史 mojibake 权限名还原为可读中文。
     *
     * 参数与变量含义：
     * - $name：来自 `permissions.name` 的原始权限名称，部分历史数据可能是 UTF-8 被误按 GBK 展示后的乱码。
     * - $converted：尝试把乱码字符按 GBK 编回原始字节后得到的可读 UTF-8 文本。
     *
     * @param string $name 原始权限名称。
     * @return string 可读权限名称；无法安全还原时返回原始名称。
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
