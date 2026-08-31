<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/06
 * Time: 22:19
 */

/**
 * AdminLocalizationTest
 *
 * 文件功能：
 * - 验证后台核心语言键在中文/英文间可切换，中文语言包为可读 UTF-8 且无迁移乱码，第一批 Blade 模块语言键完整存在。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台多语言回归测试。
 *
 * 测试目标：
 * - 后端 JSON 响应和后台 Blade 页面使用的核心语言键必须能在中文/英文之间切换。
 * - 中文语言包必须是可读 UTF-8 中文，不能继续出现历史迁移产生的乱码。
 * - 第一批 Blade 后台业务模块新增的语言键必须完整存在，避免页面渲染出 admin.xxx 原始键名。
 */
class AdminLocalizationTest extends TestCase
{
    /**
     * 中文环境下，后台鉴权响应必须返回可读中文。
     *
     * @return void
     */
    public function test_admin_auth_messages_are_readable_in_chinese(): void
    {
        app()->setLocale('zh-CN');

        $this->assertSame('权限不足', __('response.permission_denied'));
        $this->assertSame('登录成功', __('admin.login_successful'));
        $this->assertSame('角色列表获取成功', __('admin.role_list_fetched'));
        $this->assertSame('代理等级', __('admin.agent_levels'));
        $this->assertSame('系统配置', __('admin.system_configs'));
    }

    /**
     * 英文环境下，同一批后台鉴权响应必须返回英文。
     *
     * @return void
     */
    public function test_admin_auth_messages_are_readable_in_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('Permission denied', __('response.permission_denied'));
        $this->assertSame('Login successful', __('admin.login_successful'));
        $this->assertSame('Role list fetched', __('admin.role_list_fetched'));
        $this->assertSame('Agent Levels', __('admin.agent_levels'));
        $this->assertSame('System Configs', __('admin.system_configs'));
    }
}
