<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:53
 */

/**
 * AgentHierarchyModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证代理层级相关模型保持可读中文注释，禁止旧英文占位注释或乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 代理层级与代理等级模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 本测试约束代理树、代理统计和代理等级模型的中文注释质量。
 * - 这些模型会影响后台数据范围、前台代理客户列表、返佣统计和代理等级配置，字段含义必须写清楚。
 * - 测试只读取源码文件，不写入真实代理关系，也不改变当前数据范围或返佣逻辑。
 */
class AgentHierarchyModelCommentReadabilityTest extends TestCase
{
    /**
     * 代理层级相关模型必须包含真实表职责、关键字段和查询作用域说明。
     *
     * @return void
     */
    public function test_agent_hierarchy_models_contain_readable_chinese_logic_comments(): void
    {
        // $expectations 表示每个模型必须保留的中文说明；键名为模型路径，值为业务职责和字段含义片段。
        $expectations = [
            app_path('Models/AgentDescendant.php') => [
                '代理后代关系模型',
                'agent_descendants 表保存代理与下级代理或客户之间的层级闭包关系',
                'agent_id 表示上级代理业务用户 ID',
                'descendant_id 表示下级业务用户 ID',
                'descendant_type 表示后代类型',
                'is_direct 表示是否直属关系',
                '$agentId 表示当前查询的上级代理业务用户 ID',
            ],
            app_path('Models/AgentNodeStats.php') => [
                '代理节点统计模型',
                'agent_node_stats 表用于保存代理节点统计快照',
                '当前数据库未建表时不得在业务查询中直接依赖该模型',
                'last_calculated_at 表示统计最后计算时间',
            ],
            app_path('Models/AgentLevel.php') => [
                '代理等级模型',
                'agent_levels 表保存代理等级与返佣比例配置',
                'level_code 表示代理等级编码',
                'max_commission 表示代理最大返佣比例',
                'user_commission 表示普通客户默认返佣比例',
            ],
        ];

        foreach ($expectations as $file => $requiredFragments) {
            // $content 表示当前模型源码，用于检查注释是否覆盖代理层级、等级和统计字段含义。
            $content = file_get_contents($file);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $content, $file . ' 缺少中文说明：' . $fragment);
            }
        }
    }

    /**
     * 代理层级相关模型不允许保留旧英文占位注释或乱码片段。
     *
     * @return void
     */
    public function test_agent_hierarchy_models_do_not_contain_mojibake_or_english_placeholders(): void
    {
        // $files 表示本轮直接维护的代理层级模型文件集合，便于失败时快速定位。
        $files = [
            app_path('Models/AgentDescendant.php'),
            app_path('Models/AgentNodeStats.php'),
            app_path('Models/AgentLevel.php'),
        ];

        // $forbiddenFragments 表示历史注释中常见的英文占位和 UTF-8/GBK 错解片段。
        $forbiddenFragments = [
            'Table Name',
            'Relation:',
            'Scope:',
            'Attribute Casting',
            'Maintains hierarchical relationships',
            'Stores statistical data',
            'Defines different agent levels',
            '浠ｇ悊',
            '鏁版嵁',
            '鍏宠仈',
        ];

        foreach ($files as $file) {
            // $content 表示当前模型源码，用于逐项排查不可读注释残留。
            $content = file_get_contents($file);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $file . ' 仍包含不可读或占位注释：' . $fragment);
            }
        }
    }
}
