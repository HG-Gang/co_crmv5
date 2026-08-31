<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 08:28
 */

/**
 * BladeRouteAliasCompatibilityTest
 *
 * 文件功能：
 * - 验证 Blade 路由别名兼容配置：别名目标路由真实存在且别名已注册，配置文件保留中文参数说明。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 旧 Blade 路由兼容别名一致性测试。
 *
 * 功能逻辑说明：
 * - routes/web.php 末尾的 $crmBladeRouteAliases 用于兼容旧 Blade 模板里的 route('admin.xxx')、route('front.xxx') 等调用。
 * - 如果 targetName 指向不存在的真实命名路由，Laravel 应用在 boot 阶段会抛出异常，后台页面和测试都会被阻断。
 * - 本测试把该配置作为一个独立边界检查，避免后续新增或改名接口时再次出现别名目标缺失。
 */
class BladeRouteAliasCompatibilityTest extends TestCase
{
    /**
     * 旧 Blade 兼容别名必须全部指向真实存在的命名路由。
     *
     * @return void
     */
    public function test_blade_route_alias_targets_exist_and_aliases_are_registered(): void
    {
        foreach ($this->bladeRouteAliases() as $alias => $targetName) {
            $this->assertTrue(Route::has($targetName), $alias . ' 指向的真实路由不存在：' . $targetName);
            $this->assertTrue(Route::has($alias), $alias . ' 兼容别名未注册到 Laravel 路由表。');
        }
    }

    /**
     * 旧 Blade 兼容别名配置必须保留中文参数说明。
     *
     * @return void
     */
    public function test_blade_route_alias_configuration_keeps_chinese_parameter_comments(): void
    {
        $source = file_get_contents(base_path('routes/web.php')) ?: '';

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'routes/web.php 兼容别名配置缺少中文说明：' . $fragment);
        }
    }

    /**
     * 从 routes/web.php 静态解析旧 Blade 兼容别名配置。
     *
     * 参数逻辑说明：
     * - alias 表示旧模板里 route() 使用的历史名称。
     * - targetName 表示当前真实存在的 Laravel 命名路由。
     *
     * @return array<string, string> alias => targetName 映射。
     */
    private function bladeRouteAliases(): array
    {
        $source = file_get_contents(base_path('routes/web.php')) ?: '';
        $matched = preg_match('/\$crmBladeRouteAliases\s*=\s*\[(.*?)\];/s', $source, $block);

        $this->assertSame(1, $matched, 'routes/web.php 未找到 $crmBladeRouteAliases 配置块。');

        preg_match_all("/'([^']+)'\s*=>\s*'([^']+)'/", $block[1], $matches, PREG_SET_ORDER);

        $aliases = [];
        foreach ($matches as $match) {
            $aliases[$match[1]] = $match[2];
        }

        $this->assertNotEmpty($aliases, '$crmBladeRouteAliases 配置不能为空。');

        return $aliases;
    }

    /**
     * 兼容别名配置必须保留的中文说明片段。
     *
     * @return array<int, string> 中文说明片段列表。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '旧 Blade 路由兼容别名',
            'alias 表示旧模板 route() 使用的名称',
            'targetName 表示当前真实 Laravel 命名路由',
            '别名目标必须先在当前路由表中存在',
        ];
    }
}
