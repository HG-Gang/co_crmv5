<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminGroupConfigsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证分组配置模块 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台组别配置 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/group-configs/index.blade.php` 负责后台组别配置页面结构、筛选表单、CRUD 弹窗和按钮权限标记。
 * - `public/js/apps/admin/layui/group-configs/index.js` 负责组别配置列表加载、搜索、新增、编辑、删除、表单字段归一化和按钮权限刷新。
 * - 本测试只检查静态页面、脚本注释、字段对齐和乱码黑名单，不连接真实数据库，也不调用真实组别配置接口。
 */
class AdminGroupConfigsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 组别配置 JS 必须说明列表来源、字段含义、CRUD 接口、布尔开关归一化和权限边界。
     *
     * @return void
     */
    public function test_group_configs_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('group-configs/index.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '组别配置 group-configs/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("url: '/api/admin/group-configs'", $script, '组别配置列表必须读取资源化组别配置接口。');
        $this->assertStringContainsString("'/api/admin/updateGroupConfig/' + encodeURIComponent(id)", $script, '组别配置编辑必须通过路由参数 id 调用更新接口。');
        $this->assertDoesNotContainGarbledFragments($script, '组别配置 group-configs/index.js');
    }

    /**
     * 组别配置 Blade 必须说明页面职责、接口来源、字段映射和按钮权限来源。
     *
     * @return void
     */
    public function test_group_configs_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/group-configs/index.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '组别配置 group-configs/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString('name="group_name"', $blade, '组别配置表单必须提交 group_name，再由后端映射为 group_configs.name。');
        $this->assertStringContainsString('data-permission="admin_group_config_create"', $blade, '新增组别配置按钮必须绑定 permissions.slug。');
        $this->assertDoesNotContainGarbledFragments($blade, '组别配置 group-configs/index.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖 group_configs 字段、接口和权限边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '组别配置列表',
            'group_configs',
            'keyword 表示组别名称搜索关键字',
            'name 表示组别名称',
            'group_name 表示页面表单提交的组别名称',
            'radix 表示交易组别基数',
            'category 表示组别分类',
            '1=代理组',
            '2=用户组',
            'has_commission 表示是否参与返佣',
            'is_enabled 表示是否启用',
            'is_ecn 表示是否 ECN 组',
            'is_default 表示是否默认组',
            '/api/admin/group-configs',
            '/api/admin/createGroupConfig',
            '/api/admin/updateGroupConfig/{id}',
            '/api/admin/deleteGroupConfig/{id}',
            'id 表示组别配置主键',
            '重新应用按钮权限',
            'permissions.slug',
        ];
    }

    /**
     * 必须保留的 Blade 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖页面职责、接口来源、字段映射和安全边界。
     */
    private function requiredBladeCommentFragments(): array
    {
        return [
            '组别配置管理页面',
            'admin_api_groupConfigList',
            'admin_api_createGroupConfig',
            'admin_api_updateGroupConfig',
            'admin_api_deleteGroupConfig',
            'group_name 映射到 group_configs.name',
            'category 取值 1=代理组、2=用户组',
            'data-permission 来自 permissions.slug',
            '后端 check.permission:admin',
        ];
    }

    /**
     * 断言目标文本不包含常见乱码片段。
     *
     * @param string $content 被检查的文件内容。
     * @param string $label 错误消息中的文件标签，用于快速定位失败文件。
     * @return void
     */
    private function assertDoesNotContainGarbledFragments(string $content, string $label): void
    {
        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $content, $label . ' 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，用于发现历史编码错乱的中文注释。
     */
    private function garbledFragments(): array
    {
        return [
            '�',
            'å…',
            'é‡',
            'æ‰',
            'é”',
            '鍚',
            '绠',
            '閫',
            '鐢',
            '鏉',
            '鑿',
            '娉',
            '杩',
        ];
    }
}
