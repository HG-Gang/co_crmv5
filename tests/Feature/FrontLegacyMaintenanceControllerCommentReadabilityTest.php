<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:08
 */

/**
 * FrontLegacyMaintenanceControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证旧维护入口控制器保留禁用边界中文说明，且禁用响应用后端语言包 key 而非硬编码英文。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 旧维护入口控制器中文注释与多语言响应测试。
 *
 * 测试目标：
 * - 旧项目遗留的导入、同步、测试入口不能恢复为公开可执行逻辑，必须保留明确的禁用边界说明。
 * - 禁用响应必须使用后端语言包 key，避免 Blade、Layui 或接口调用侧出现不可维护的英文硬编码。
 * - disabledMaintenanceResponse 的请求参数、旧动作名、日志字段和响应字段必须具备中文逻辑注释。
 */
class FrontLegacyMaintenanceControllerCommentReadabilityTest extends TestCase
{
    public function test_legacy_maintenance_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/LegacyMaintenanceController.php')) ?: '';

        foreach ([
            '旧维护入口控制器',
            '导入用户',
            '同步到 MT4',
            '公开路由禁用',
            'legacyAction 表示旧项目维护入口动作名',
            'action 表示写入日志的旧动作名称',
            'path 表示触发旧维护入口的请求路径',
            'legacy_action 表示返回给调用方的旧动作标识',
            'protected console command or admin task',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'LegacyMaintenanceController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_legacy_maintenance_disabled_response_uses_language_key(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/LegacyMaintenanceController.php')) ?: '';

        $this->assertStringContainsString("__('response.legacy_maintenance_disabled')", $source, '旧维护入口禁用消息必须使用后端多语言 key。');
        $this->assertStringNotContainsString("'Legacy maintenance action is disabled.", $source, '旧维护入口禁用消息不能继续硬编码英文。');

        $zhResponse = require resource_path('lang/zh-CN/response.php');
        $enResponse = require resource_path('lang/en/response.php');
        $this->assertArrayHasKey('legacy_maintenance_disabled', $zhResponse);
        $this->assertArrayHasKey('legacy_maintenance_disabled', $enResponse);
    }
}
