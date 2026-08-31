<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 23:27
 */

/**
 * FrontAgentControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 AgentController 对代理树、直属客户、佣金转账、等级确认、组别变更、用户详情与旧前台兼容入口均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台代理管理控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\AgentController 源码，不连接真实数据库。
 * - 约束代理树、直属客户、佣金转账、等级确认、组别变更、用户详情和旧前台兼容入口必须有中文逻辑说明。
 */
class FrontAgentControllerCommentReadabilityTest extends TestCase
{
    public function test_front_agent_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';

        $expectedComments = [
            '前台代理管理控制器',
            '处理下级代理列表、直属客户列表、代理统计、等级确认、客户组别变更、用户详情和旧前台兼容入口',
            'familyTreeService 表示代理树统计服务',
            'subList 用于返回当前代理可见的下级代理列表',
            'parent_id 表示当前要展开查询的代理业务用户 ID',
            'direct_only 表示是否只查询直属下级',
            'descendant_type=1 表示下级代理',
            'proxyListSearch 用于兼容旧前台代理列表搜索入口',
            'customerList 用于返回当前代理可见的下级客户列表',
            'descendant_type=2 表示普通客户',
            'available_groups 表示当前代理可申请切换的客户组别',
            'directCustListSearch 用于兼容旧前台直属客户列表搜索入口',
            'directUserCommTrans 用于兼容旧前台直属客户佣金转账入口',
            'depositId 表示旧前台提交的目标用户 ID',
            'comm_money 表示旧前台提交的转账金额',
            'password 表示当前代理登录密码',
            'DBCT 表示接收方入账流水',
            'WBCT 表示当前代理出账流水',
            'getSubAgentsGrpIdList 用于返回代理等级候选列表',
            'agentGId 表示旧前台传入的当前代理等级 ID',
            'getParentPath 用于返回旧前台代理层级路径 HTML',
            'event_name 表示旧前台 Layui 点击事件名称',
            'directCustDetailList 用于返回指定代理的直属客户明细',
            'puid 表示旧前台传入的父级代理用户 ID',
            'statistics 用于返回当前代理统计数据',
            'userDetail 用于返回当前代理可见的单个用户详情',
            'agentLevelDetailPayload 用于只给代理账号返回等级字段',
            'userLoginHistory 用于返回当前代理可见用户的登录历史',
            'confirmLevel 用于返回待确认下级代理等级列表',
            'confirmLevelChange 用于确认直属下级代理等级',
            'agent_gId 表示选择的代理等级 ID',
            'groupChangeList 用于返回当前代理提交的客户组别变更申请列表',
            'groupChange 用于提交客户组别变更申请',
            'target_user_id 表示申请切换组别的客户业务用户 ID',
            'new_group_id 表示目标客户组别 ID',
            'canViewUser 用于判断当前代理是否可以查看目标用户',
            'isDirectTransferTarget 用于判断佣金转账目标是否为直属关系',
            'availableGroupOptions 用于返回当前代理可申请切换的客户组别选项',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front AgentController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_agent_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';

        $legacyEnglishComments = [
            'Front Agent Management Controller',
            'Provides sub-agent and customer lists, and statistics for agents.',
            'List all sub-agents (direct and indirect)',
            'List all customers (direct and indirect)',
            'Add hierarchy and trade stats for each agent',
            'Get agent statistics',
            'View/confirm agent level',
            'Request customer group change',
            'Verify the target exists before creating the application.',
            'Confirm the requested group is a real enabled group in the new schema.',
            'Verify target user is descendant.',
            'Base columns follow the current co_crmv5 table.',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front AgentController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
