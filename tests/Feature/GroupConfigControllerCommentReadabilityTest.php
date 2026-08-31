<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 22:08
 */

/**
 * GroupConfigControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 GroupConfigController 对 group_name 到真实表字段 name 的映射及组别分类、开关字段含义均有中文逻辑注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台组别配置控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 该测试通过读取 GroupConfigController 源码，约束控制器级中文逻辑注释。
 * - 重点覆盖页面字段 group_name 到真实表字段 name 的映射，以及组别分类和开关字段含义。
 */
class GroupConfigControllerCommentReadabilityTest extends TestCase
{
    public function test_group_config_controller_keeps_chinese_logic_comments_for_crud_fields(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/GroupConfigController.php')) ?: '';

        foreach ([
            '后台组别配置控制器',
            '数据来源为 group_configs 表',
            'page 表示当前页码',
            'per_page 表示每页数量',
            'id 表示 group_configs.id',
            'name 表示真实入库组别名称',
            'group_name 表示页面表单提交的组别名称',
            'group_name 映射到 group_configs.name',
            'radix 表示组别基数',
            'category 取值 1=代理组、2=用户组',
            'has_commission 表示是否参与返佣',
            'is_enabled 表示是否启用',
            'is_ecn 表示是否 ECN 组',
            'is_default 表示是否默认组',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'GroupConfigController 缺少中文注释：' . $expectedComment);
        }

        foreach ([
            'Group Configuration Controller',
            'List group configurations',
            'Create group configuration',
            'Get group configuration detail',
            'Update group configuration',
            'Delete group configuration',
        ] as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'GroupConfigController 不应保留旧英文标题注释：' . $legacyEnglishComment);
        }
    }
}
