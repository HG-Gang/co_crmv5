<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/11
 * Time: 00:58
 */

/**
 * FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest
 *
 * 文件功能：
 * - 验证注册规则与家谱服务对邀请人参数、树与统计参数均有中文说明，且无英文占位或乱码注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台注册规则与代理家族链服务中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - FrontRegisterRuleService 决定代理商和普通客户注册时邀请人是否合法。
 * - FamilyTreeService 决定代理家族链、下级用户、团队统计和代理数据范围基础关系。
 * - 两个服务会影响前台菜单数据可见性、团队统计和返佣链路，因此关键参数必须有中文逻辑说明。
 */
class FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest extends TestCase
{
    /**
     * 前台注册规则服务必须说明邀请人、账号类型和返佣模式参数含义。
     *
     * @return void
     */
    public function test_front_register_rule_service_documents_inviter_parameters_in_chinese(): void
    {
        $source = file_get_contents(app_path('Services/FrontRegisterRuleService.php')) ?: '';

        foreach ([
            '前台注册邀请规则服务。',
            '$inviterId 表示邀请人的业务 user_id',
            '$accountType 表示被注册账号类型',
            '$commissionMode 表示注册返佣模式',
            '$login 表示邀请人的登录账号记录',
            '$info 表示邀请人的业务资料记录',
            'message 返回 register 语言包 key',
        ] as $expectedPhrase) {
            $this->assertStringContainsString($expectedPhrase, $source, $expectedPhrase . ' 缺少中文逻辑注释。');
        }
    }

    /**
     * 代理家族链服务必须说明统计、树结构和重建参数含义。
     *
     * @return void
     */
    public function test_family_tree_service_documents_tree_and_stats_parameters_in_chinese(): void
    {
        $source = file_get_contents(app_path('Services/FamilyTreeService.php')) ?: '';

        foreach ([
            '代理家族链服务。',
            '$userId 表示目标业务用户 ID',
            '$agentId 表示代理商业务用户 ID',
            '$dateFrom 表示统计开始时间',
            '$dateTo 表示统计结束时间',
            '$descendantIds 表示当前代理可见的全部下级用户 ID',
            '$treeIds 表示从 family_tree 拆分出的用户链路',
            '$depth 表示下级用户相对代理商的层级深度',
            'agent_descendants 表保存代理与所有下级用户的祖先后代关系',
        ] as $expectedPhrase) {
            $this->assertStringContainsString($expectedPhrase, $source, $expectedPhrase . ' 缺少中文逻辑注释。');
        }
    }

    /**
     * 两个服务不得继续保留英文占位标题或历史编码乱码。
     *
     * @return void
     */
    public function test_services_have_no_english_placeholder_or_mojibake_comments(): void
    {
        $combinedSource = implode("\n", [
            file_get_contents(app_path('Services/FrontRegisterRuleService.php')) ?: '',
            file_get_contents(app_path('Services/FamilyTreeService.php')) ?: '',
        ]);

        foreach ([
            'Port of legacy',
            'Get the full ancestor chain',
            'Get all direct children',
            'Get all descendants',
            'Get agent',
            'Get comprehensive statistics',
            'Get full network tree',
            'Rebuild family_tree',
            'Rebuild agent_descendants',
            'Remove self from the chain',
            'Recursively rebuild children',
            'Delete existing records',
            'Find all users whose family_tree contains this agent',
            '鐢',
            '璇',
            '鍙',
            '閭',
            '缁',
            '锟',
            '€?',
        ] as $forbiddenFragment) {
            $this->assertStringNotContainsString($forbiddenFragment, $combinedSource, '服务仍包含不可读注释片段：' . $forbiddenFragment);
        }
    }
}
