<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 21:01
 */

/**
 * AdminDataScopeSchemaCommentReadabilityTest
 *
 * 文件功能：
 * - 验证数据范围模型与迁移文件保持可读中文注释，并禁止历史乱码或英文占位片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台数据范围模型与迁移中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 后台权限不仅控制能否进入菜单或接口，还必须控制不同管理员角色能查看哪些业务数据。
 * - role_data_scopes 和 admin_agent_bindings 是管理员数据范围配置的数据表来源。
 * - 本测试防止这些关键模型和迁移继续保留历史编码乱码，确保字段参数和业务边界都有中文说明。
 */
class AdminDataScopeSchemaCommentReadabilityTest extends TestCase
{
    /**
     * 数据范围模型和迁移必须说明字段含义、参数作用和权限边界。
     *
     * 参数与变量含义：
     * - $expectations：键为文件路径，值为该文件必须包含的中文说明片段。
     * - $path：当前被检查的模型或迁移文件绝对路径。
     * - $content：当前文件完整源码，用于检查中文注释是否覆盖关键字段和逻辑边界。
     *
     * @return void
     */
    public function test_data_scope_models_and_migrations_have_readable_chinese_comments(): void
    {
        $expectations = [
            app_path('Models/RoleDataScope.php') => [
                '角色数据范围模型',
                'role_data_scopes 表保存角色级数据查看范围配置',
                'scope_type 表示数据范围类型',
                'agent_ids 表示指定代理 ID 数组',
                'user_ids 表示指定用户 ID 数组',
                'role() 返回当前数据范围所属角色',
            ],
            app_path('Models/AdminAgentBinding.php') => [
                '管理员代理绑定模型',
                'admin_agent_bindings 表保存后台管理员与代理节点的绑定关系',
                'admin_id 表示后台管理员 ID',
                'agent_id 表示被授权管理的代理用户 ID',
                'binding_type 表示绑定类型',
                'agent() 返回被绑定的代理业务用户资料',
            ],
            database_path('migrations/2026_06_06_000001_create_role_data_scopes_table.php') => [
                '创建角色数据范围配置表',
                'scope_type 表示数据范围类型',
                'all=全部数据',
                'agent_tree=绑定代理树',
                'custom_users=指定用户集合',
                'role_id 唯一约束保证每个角色最多只有一条启用配置来源',
            ],
            database_path('migrations/2026_06_06_000002_create_admin_agent_bindings_table.php') => [
                '创建管理员与代理绑定关系表',
                'admin_id 表示后台管理员 ID',
                'agent_id 表示代理业务用户 ID',
                'binding_type 表示绑定类型',
                'primary=主绑定',
                'extra=额外绑定',
            ],
        ];

        foreach ($expectations as $path => $needles) {
            $content = (string) file_get_contents($path);

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $content, $path . ' 缺少中文说明片段：' . $needle);
            }
        }
    }

    /**
     * 数据范围模型和迁移不得保留历史乱码或英文占位片段。
     *
     * 参数与变量含义：
     * - $paths：需要扫描的模型和迁移文件路径。
     * - $forbiddenFragments：禁止出现的历史 mojibake 片段和英文占位说明。
     * - $content：当前文件完整源码。
     *
     * @return void
     */
    public function test_data_scope_models_and_migrations_do_not_contain_mojibake_fragments(): void
    {
        $paths = [
            app_path('Models/RoleDataScope.php'),
            app_path('Models/AdminAgentBinding.php'),
            database_path('migrations/2026_06_06_000001_create_role_data_scopes_table.php'),
            database_path('migrations/2026_06_06_000002_create_admin_agent_bindings_table.php'),
        ];

        $forbiddenFragments = [
            '鏁版嵁',
            '鍏宠仈',
            '瑙掕壊',
            '绠＄悊',
            '浠ｇ悊',
            '鐢ㄦ埛',
            'Table Name',
            'Relation:',
            'Attribute Casting',
        ];

        foreach ($paths as $path) {
            $content = (string) file_get_contents($path);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $path . ' 仍包含历史乱码或英文占位片段：' . $fragment);
            }
        }
    }
}
