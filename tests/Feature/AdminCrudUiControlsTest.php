<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/06
 * Time: 22:54
 */

/**
 * AdminCrudUiControlsTest
 *
 * 文件功能：
 * - 验证后台管理页面（黑名单、大代理商、代理等级、分组配置、通道、管理员、新闻、系统配置）提供必要的 CRUD 与操作控件。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 CRUD 页面控件覆盖测试。
 *
 * 测试目标：
 * - 已经注册 create/update/delete API 的后台模块，Blade 页面必须提供对应入口。
 * - 每个敏感入口必须声明 data-permission，继续使用数据表权限配置驱动按钮显隐。
 * - 表单字段必须能表达接口需要的关键参数，避免页面只有列表不能维护数据。
 */
class AdminCrudUiControlsTest extends TestCase
{
    /**
     * 黑名单页面必须提供新增、编辑和删除控件。
     *
     * @return void
     */
    public function test_blacklist_page_contains_crud_controls(): void
    {
        $response = $this->get('/admin/blacklist');

        $response->assertOk();
        $response->assertSee('id="addBlacklist"', false);
        $response->assertSee('data-permission="admin_blacklist_create"', false);
        $response->assertSee('data-permission="admin_blacklist_update"', false);
        $response->assertSee('data-permission="admin_blacklist_delete"', false);
        $response->assertSee('id="blacklistModal"', false);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="phone"', false);
    }

    /**
     * 大代理页面必须提供新增、编辑和删除控件。
     *
     * @return void
     */
    public function test_big_agent_page_contains_crud_controls(): void
    {
        $response = $this->get('/admin/big-agents');

        $response->assertOk();
        $response->assertSee('id="addBigAgent"', false);
        $response->assertSee('data-permission="admin_big_agent_create"', false);
        $response->assertSee('data-permission="admin_big_agent_update"', false);
        $response->assertSee('data-permission="admin_big_agent_delete"', false);
        $response->assertSee('id="bigAgentModal"', false);
        $response->assertSee('name="username"', false);
        $response->assertSee('name="password"', false);
    }

    /**
     * 代理等级页面必须提供新增、编辑和删除控件。
     *
     * @return void
     */
    public function test_agent_level_page_contains_crud_controls(): void
    {
        $response = $this->get('/admin/agent-levels');

        $response->assertOk();
        $response->assertSee('id="addAgentLevel"', false);
        $response->assertSee('data-permission="admin_agent_level_create"', false);
        $response->assertSee('data-permission="admin_agent_level_update"', false);
        $response->assertSee('data-permission="admin_agent_level_delete"', false);
        $response->assertSee('id="agentLevelModal"', false);
        $response->assertSee('name="level"', false);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="user_commission"', false);
    }

    /**
     * 分组配置页面必须提供新增、编辑和删除控件。
     *
     * @return void
     */
    public function test_group_config_page_contains_crud_controls(): void
    {
        $response = $this->get('/admin/group-configs');

        $response->assertOk();
        $response->assertSee('id="addGroupConfig"', false);
        $response->assertSee('data-permission="admin_group_config_create"', false);
        $response->assertSee('data-permission="admin_group_config_update"', false);
        $response->assertSee('data-permission="admin_group_config_delete"', false);
        $response->assertSee('id="groupConfigModal"', false);
        $response->assertSee('name="group_name"', false);
        $response->assertSee('name="radix"', false);
        $response->assertSee('name="category"', false);
    }

    /**
     * 支付通道页面必须提供新增、编辑、删除和启用状态切换控件。
     *
     * @return void
     */
    public function test_channel_page_contains_crud_controls(): void
    {
        $response = $this->get('/admin/channels');

        $response->assertOk();
        $response->assertSee('id="addChannel"', false);
        $response->assertSee('data-permission="admin_channel_create"', false);
        $response->assertSee('data-permission="admin_channel_update"', false);
        $response->assertSee('data-permission="admin_channel_delete"', false);
        $response->assertSee('data-permission="admin_channel_toggle"', false);
        $response->assertSee('lay-event="toggle"', false);
        $response->assertSee('id="channelModal"', false);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="channel_code"', false);
        $response->assertSee('name="exchange_rate"', false);
    }

    /**
     * 管理员页面必须提供新增、编辑和删除控件。
     *
     * @return void
     */
    public function test_admin_page_contains_crud_controls(): void
    {
        $response = $this->get('/admin/admins');

        $response->assertOk();
        $response->assertSee('id="addAdmin"', false);
        $response->assertSee('data-permission="admin_admin_create"', false);
        $response->assertSee('data-permission="admin_admin_update"', false);
        $response->assertSee('data-permission="admin_admin_delete"', false);
        $response->assertSee('id="adminModal"', false);
        $response->assertSee('name="username"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
    }

    /**
     * 新闻公告页面必须提供新增、编辑、删除和发布状态切换控件。
     *
     * @return void
     */
    public function test_news_page_contains_crud_controls(): void
    {
        $response = $this->get('/admin/news');
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';

        $response->assertOk();
        $this->assertStringContainsString('/api/admin/toggleNews/', $script);
        $response->assertSee('id="addNews"', false);
        $response->assertSee('data-permission="admin_news_create"', false);
        $response->assertSee('data-permission="admin_news_update"', false);
        $response->assertSee('data-permission="admin_news_delete"', false);
        $response->assertSee('data-permission="admin_news_toggle"', false);
        $response->assertSee('lay-event="toggle"', false);
        $response->assertSee('id="newsModal"', false);
        $response->assertSee('name="title"', false);
        $response->assertSee('name="content"', false);
        $response->assertSee('name="is_published"', false);
    }

    /**
     * 系统配置页面必须提供编辑控件，并使用真实 system_configs 表字段。
     *
     * @return void
     */
    public function test_system_config_page_contains_update_controls(): void
    {
        $response = $this->get('/admin/system-configs');

        $response->assertOk();
        $response->assertSee('data-permission="admin_system_config_update"', false);
        $response->assertSee('id="systemConfigModal"', false);
        $response->assertSee('name="key"', false);
        $response->assertSee('name="value"', false);
        $response->assertSee('name="group"', false);
        $response->assertSee('name="description"', false);
    }

    /**
     * 代理管理页面必须提供下级查看、等级调整和佣金调整控件。
     *
     * @return void
     */
    public function test_agent_page_contains_operation_controls(): void
    {
        $response = $this->get('/admin/agents');

        $response->assertOk();
        $response->assertSee('data-permission="admin_agent_descendants"', false);
        $response->assertSee('data-permission="admin_agent_update_level"', false);
        $response->assertSee('data-permission="admin_agent_update_commission"', false);
        $response->assertSee('id="agentLevelUpdateModal"', false);
        $response->assertSee('id="agentCommissionUpdateModal"', false);
        $response->assertSee('name="agent_id"', false);
        $response->assertSee('name="level"', false);
        $response->assertSee('name="comm_rate"', false);
    }
}
