<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:13
 */

/**
 * AdminDataScopeRuntimeLocalizationTest
 *
 * 文件功能：
 * - 验证数据范围 Layui 脚本使用的运行时语言 key 在 shared/lang/common 中英文语言文件中均存在。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台数据范围页面运行时多语言测试。
 *
 * 功能逻辑说明：
 * - 数据范围页面由 Laravel Blade 渲染静态表单文案，但 Layui 表格列名、状态徽标、弹窗标题和范围标签由 JS 运行时生成。
 * - 如果 JS 里继续写死英文，切换中文语言后仍会看到英文表头和弹窗标题，不符合“后端/后台必须支持多语言”的目标。
 * - 本测试不连接数据库，只检查 `public/js/apps/admin/layui/data-scopes/index.js` 与 shared/lang/common 中英语言包的静态契约。
 */
class AdminDataScopeRuntimeLocalizationTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 数据范围 Layui 脚本不能写死运行时英文文案，必须通过 CrmLang.t 读取语言包。
     *
     * @return void
     */
    public function test_data_scope_layui_script_uses_runtime_language_keys(): void
    {
        $script = $this->adminLayuiScript('data-scopes/index.js');

        foreach ($this->hardcodedRuntimeTexts() as $text) {
            $this->assertStringNotContainsString($text, $script, '数据范围 JS 仍存在硬编码运行时文案：' . $text);
        }

        foreach ($this->requiredRuntimeKeys() as $key) {
            $this->assertStringContainsString("CrmLang.t('" . $key . "')", $script, '数据范围 JS 必须通过 CrmLang.t 读取：' . $key);
        }
    }

    /**
     * 数据范围运行时语言 key 必须同时存在于中文和英文 shared/lang/common 文件。
     *
     * @return void
     */
    public function test_data_scope_runtime_language_keys_exist_in_common_language_files(): void
    {
        $zhSource = file_get_contents(public_path('js/shared/lang/common/zh-CN.js')) ?: '';
        $enSource = file_get_contents(public_path('js/shared/lang/common/en.js')) ?: '';

        foreach ($this->requiredRuntimeLanguageLeafKeys() as $leafKey) {
            $this->assertStringContainsString($leafKey . ':', $zhSource, '中文运行时语言包缺少 key：' . $leafKey);
            $this->assertStringContainsString($leafKey . ':', $enSource, '英文运行时语言包缺少 key：' . $leafKey);
        }
    }

    /**
     * 数据范围 JS 中不应继续出现的硬编码英文运行时文案。
     *
     * @return array<int, string> 英文文案列表。
     */
    private function hardcodedRuntimeTexts(): array
    {
        return [
            'Role Name',
            'Data Scope',
            'Agent IDs',
            'User IDs',
            'Admin ID',
            'Admin Name',
            'Agent ID',
            'Agent Name',
            'Binding Type',
            'Saved successfully',
            'Delete this admin-agent binding?',
            'Deleted successfully',
            'Configure Role Data Scope',
            'Maintain Admin-Agent Binding',
            'All Data',
            'Own Data',
            'Created By Me',
            'Bound Agent Tree',
            'Specified Agents',
            'Specified Users',
        ];
    }

    /**
     * 数据范围 JS 必须通过 CrmLang.t 读取的完整运行时 key。
     *
     * @return array<int, string> 完整语言 key。
     */
    private function requiredRuntimeKeys(): array
    {
        return [
            'admin.role_data_scope_role_name',
            'admin.scope_type',
            'admin.agent_ids',
            'admin.user_ids',
            'admin.admin_id',
            'admin.admin_name',
            'admin.agent_id',
            'admin.agent_name',
            'admin.binding_type',
            'admin.binding_primary',
            'admin.binding_extra',
            'admin.data_scope_saved',
            'admin.admin_agent_binding_deleted',
            'admin.admin_agent_binding_delete_confirm',
            'admin.role_data_scope_modal_title',
            'admin.admin_agent_binding_modal_title',
            'admin.scope_all',
            'admin.scope_self',
            'admin.scope_created',
            'admin.scope_agent_tree',
            'admin.scope_custom_agents',
            'admin.scope_custom_users',
        ];
    }

    /**
     * shared/lang/common 文件中需要存在的叶子 key。
     *
     * @return array<int, string> 语言包叶子 key。
     */
    private function requiredRuntimeLanguageLeafKeys(): array
    {
        return [
            'role_data_scope_role_name',
            'scope_type',
            'agent_ids',
            'user_ids',
            'admin_id',
            'admin_name',
            'agent_id',
            'agent_name',
            'binding_type',
            'binding_primary',
            'binding_extra',
            'data_scope_saved',
            'admin_agent_binding_deleted',
            'admin_agent_binding_delete_confirm',
            'role_data_scope_modal_title',
            'admin_agent_binding_modal_title',
            'scope_all',
            'scope_self',
            'scope_created',
            'scope_agent_tree',
            'scope_custom_agents',
            'scope_custom_users',
        ];
    }
}
