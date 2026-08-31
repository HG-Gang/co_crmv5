<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:43
 */

/**
 * AdminLegacyMigrationGapAuditTest
 *
 * 文件功能：
 * - 验证遗留迁移差距审计文档：覆盖关键旧控制器、包含真实数据库测试数据、近期闭环模块记录证据且不含过期差距描述。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台旧项目迁移缺口审计文档测试。
 *
 * 功能逻辑说明：
 * - 用户要求继续深入对比新旧项目，不能只靠口头描述，必须沉淀为可复查的 Markdown 文档。
 * - 本测试约束审计文档必须包含旧项目核心控制器、当前迁移状态、缺口优先级和真实 DB 测试数据。
 * - 真实 DB 测试数据用于证明样本来源于当前 `co_crmv5` 数据库，而不是手工编造的示例。
 */
class AdminLegacyMigrationGapAuditTest extends TestCase
{
    /**
     * 审计文档必须存在并覆盖旧项目关键控制器。
     *
     * @return void
     */
    public function test_legacy_migration_gap_audit_document_covers_key_legacy_controllers(): void
    {
        // $auditPath：迁移缺口审计文档路径，用于统一记录旧项目控制器与新项目模块的对应关系。
        $auditPath = base_path('docs/admin-legacy-migration-gap-audit.md');

        $this->assertFileExists($auditPath);

        // $content：审计文档正文，用于断言文档包含必须关注的旧业务域和迁移结论。
        $content = file_get_contents($auditPath);

        foreach ([
            'BatchAmountController',
            'BatchCreditController',
            'ExchangeRateController',
            'GiftController',
            'AuthenticationController',
            'UserLoginOnlineController',
            'WithdrawFlowController',
            'UnDepositAmountController',
            'AdminProductionController',
            'AdminRealCommissionController',
            'RightsSummaryController',
            'PositionSummaryController',
            'AgentControllerV3',
        ] as $legacyController) {
            $this->assertStringContainsString($legacyController, $content);
        }

        foreach (['已迁移', '部分迁移', '未迁移', '优先级', '处理建议'] as $requiredSection) {
            $this->assertStringContainsString($requiredSection, $content);
        }
    }

    /**
     * 审计文档必须包含从真实 DB 获取的测试数据样本。
     *
     * @return void
     */
    public function test_legacy_migration_gap_audit_document_contains_real_database_test_data(): void
    {
        // $auditPath：迁移缺口审计文档路径，用于读取真实 DB 采样章节。
        $auditPath = base_path('docs/admin-legacy-migration-gap-audit.md');

        $this->assertFileExists($auditPath);

        // $content：审计文档正文，用于检查真实数据来源、连接信息、表名和样本字段。
        $content = file_get_contents($auditPath);

        foreach ([
            '真实 DB 测试数据',
            'co_crmv5',
            '127.0.0.1:3307',
            'admins',
            'user_infos',
            'agents',
            'customers',
            'permissions',
            'roles',
            'system_configs',
            'payment_channels',
        ] as $dbEvidence) {
            $this->assertStringContainsString($dbEvidence, $content);
        }
    }

    /**
     * 已有完整代码入口和测试证据的后台模块不能继续留在“未迁移”清单中。
     *
     * @return void
     */
    public function test_audit_document_marks_recently_closed_admin_modules_with_evidence(): void
    {
        $auditPath = base_path('docs/admin-legacy-migration-gap-audit.md');

        $this->assertFileExists($auditPath);

        $content = file_get_contents($auditPath);

        $closedModules = [
            'BatchAmountController' => [
                'BatchAmountImportController',
                'admin_api_depositImportList',
                'admin_api_retryWithdrawImport',
                'AdminBatchAmountImportRetryModuleTest',
            ],
            'BatchCreditController' => [
                'BatchCreditImportController',
                'admin_api_creditImportList',
                'admin_api_retryCreditImport',
                'AdminBatchCreditImportRetryModuleTest',
            ],
            'WithdrawFlowController' => [
                'FundFlowController',
                'admin_api_withdrawFlowList',
                'admin_page_withdraw_flows',
                'AdminFundFlowModuleTest',
            ],
            'UnDepositAmountController' => [
                'FundFlowController',
                'admin_api_undepositFlowList',
                'admin_page_undeposit_flows',
                'AdminFundFlowModuleTest',
            ],
            'PositionSummaryController' => [
                'PositionSummaryController',
                'admin_api_positionSummaryList',
                'admin_page_position_summary',
                'AdminPositionSummaryModuleTest',
            ],
            'RightsSummaryController' => [
                'RightsSummaryController',
                'admin_api_rightsSummaryList',
                'admin_api_manualConfirmRightsSettlement',
                'AdminRightsSummaryManualConfirmModuleTest',
            ],
            'ExchangeRateController' => [
                'ExchangeRateController',
                'admin_api_exchangeRateInfo',
                'admin_api_updateExchangeRate',
                'AdminExchangeRateModuleTest',
            ],
            'GiftController' => [
                'GiftController',
                'admin_api_giftShipmentList',
                'admin_api_sendGift',
                'AdminGiftModuleTest',
            ],
            'CustomerController' => [
                'AdminUserController',
                'admin_api_userList',
                'admin_api_exportUsers',
                'admin_api_updateUser',
                'AdminUserExportModuleTest',
                'AdminUserStatsModuleTest',
                'AdminUserUpdateMt4SyncClosureModuleTest',
                'AdminUserUpdatePasswordClosureModuleTest',
                'AdminUserUpdateAuthAndBankClosureModuleTest',
                'AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest',
                'AdminUserUpdateReadonlyMt4ClosureModuleTest',
                'AdminUserUpdateParentAgentClosureModuleTest',
            ],
            'AgentControllerV3' => [
                'AgentController',
                'admin_api_agentList',
                'admin_api_agentDescendants',
                'admin_api_exportAgents',
                'admin_api_confirmAgent',
                'admin_api_rejectAgentConfirmation',
                'admin_api_agentStatsList',
                'AdminAgentDescendantsModuleTest',
                'AdminAgentExportModuleTest',
                'AdminAgentLevelUpdateFieldModuleTest',
                'AdminAgentConfirmationModuleTest',
                'AdminAgentStatsModuleTest',
            ],
            'CancellationController' => [
                'CancelApplyController',
                'admin_api_cancelApplyApprove',
                'admin_api_cancelApplyReject',
                'operation_logs',
                'AdminCancelApplyReviewModuleTest',
            ],
            'AuthenticationController' => [
                'AdminUserController@reviewAuth',
                'admin_api_authPendingList',
                'admin_api_reviewAuth',
                'operation_logs',
                'AdminAuthenticationModuleTest',
            ],
            'UserLoginOnlineController' => [
                'OnlineUserController',
                'admin_api_onlineUserList',
                'admin_api_forceOfflineUser',
                'admin_page_online_users',
                'AdminOnlineUserModuleTest',
                'AdminOnlineUserForceOfflineSessionInvalidationTest',
            ],
            'AdminRealCommissionController' => [
                'RealtimeCommissionController',
                'admin_api_realtimeCommissionList',
                'admin_page_realtime_commissions',
                'AdminRealtimeCommissionModuleTest',
            ],
            'AdminProductionController' => [
                'ProductionController',
                'admin_api_productionList',
                'admin_page_productions',
                'AdminProductionModuleTest',
            ],
            'BigNumberController' => [
                'BigNumberController',
                'admin_api_bigNumberDashboard',
                'admin_api_bigNumberTrend',
                'AdminBigNumberModuleTest',
            ],
            'AdminWhsExpZeroController' => [
                'AdminWhsExpZeroController',
                'admin_api_whsExpZeroList',
                'admin_page_whs_exp_zero',
                'LegacyUiReplacementCoverageTest',
            ],
        ];

        foreach ($closedModules as $legacyController => $evidence) {
            $row = $this->markdownTableRowFor($content, $legacyController);

            $this->assertStringNotContainsString('未迁移', $row, $legacyController . ' 已有闭环证据，不能继续标记为未迁移。');

            foreach ($evidence as $expected) {
                $this->assertStringContainsString($expected, $row, $legacyController . ' 缺少审计证据：' . $expected);
            }
        }
    }

    /**
     * 审计文档不能把已闭环的未入金运营汇总继续写成缺口。
     *
     * 参数与变量含义：
     * - $auditContent：迁移缺口审计正文，用于检查模块行和落地顺序是否与最新闭环证据一致。
     * - $staleFragments：已经由未入金汇总测试闭环的旧缺口描述，保留任意一条都会误导后续迁移顺序。
     *
     * @return void
     */
    public function test_audit_document_does_not_keep_stale_undeposit_summary_gap_text(): void
    {
        $auditContent = (string) file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md'));

        $this->assertStringContainsString('AdminUndepositFlowSummaryClosureModuleTest', $auditContent);

        $staleFragments = [
            '复杂状态分类和运营跟进统计仍需继续迁移',
            '剩余缺口是未入金复杂状态分类、运营跟进统计和财务复核汇总',
        ];

        foreach ($staleFragments as $fragment) {
            $this->assertStringNotContainsString($fragment, $auditContent, '审计文档仍保留已闭环的未入金缺口描述：' . $fragment);
        }
    }

    /**
     * 审计文档不能把已闭环的权益汇总在线结算金额继续写成缺口。
     *
     * 参数与变量含义：
     * - $auditContent：迁移缺口审计正文，用于检查权益汇总模块行和落地顺序是否同步本轮真实接口测试证据。
     * - $staleFragments：已经由权益汇总在线结算测试闭环的旧缺口描述，保留任意一条都会误导后续迁移优先级。
     *
     * @return void
     */
    public function test_audit_document_does_not_keep_stale_rights_online_settlement_gap_text(): void
    {
        $auditContent = (string) file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md'));

        $this->assertStringContainsString('AdminRightsSummaryModuleTest', $auditContent);
        $this->assertStringContainsString('online_settlement_deposit_amount', $auditContent);

        $staleFragments = [
            '在线结算金额统计和 MT4 自动同步仍需继续迁移',
            '剩余缺口是自动确认出入金、在线结算金额统计和真实 MT4 自动同步',
        ];

        foreach ($staleFragments as $fragment) {
            $this->assertStringNotContainsString($fragment, $auditContent, '审计文档仍保留已闭环的权益汇总在线结算缺口描述：' . $fragment);
        }
    }

    /**
     * 审计文档不能把已闭环的后台持仓汇总代理树汇总继续写成缺口。
     *
     * 参数与变量含义：
     * - $auditContent：迁移缺口审计正文，用于检查持仓汇总模块行和落地顺序是否同步本轮代理树汇总证据。
     * - $staleFragments：已经由持仓汇总代理树汇总测试闭环的旧缺口描述，保留任意一条都会误导后续迁移优先级。
     *
     * @return void
     */
    public function test_audit_document_does_not_keep_stale_position_summary_agent_tree_gap_text(): void
    {
        $auditContent = (string) file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md'));

        $this->assertStringContainsString('AdminPositionSummaryModuleTest', $auditContent);
        $this->assertStringContainsString('agent_descendants', $auditContent);
        $this->assertStringContainsString('family_tree', $auditContent);

        $staleFragments = [
            '旧项目按代理树、MT4_USERS、COMMENT/MARGIN_RATE 的多层汇总和明细下钻仍需继续迁移',
            '剩余缺口是代理树多层汇总、MT4_USERS 联动、旧 MARGIN_RATE 口径和明细下钻',
        ];

        foreach ($staleFragments as $fragment) {
            $this->assertStringNotContainsString($fragment, $auditContent, '审计文档仍保留已闭环的持仓汇总代理树缺口描述：' . $fragment);
        }
    }

    /**
     * 审计文档必须记录旧后台下级代理持仓汇总入口的语义归属。
     *
     * 参数与变量含义：
     * - $positionRow：持仓汇总审计行，用于确认旧 `subAgentsListSearchV2` 仍归属持仓汇总接口。
     * - $agentRow：代理审计行，用于防止把持仓汇总旧入口误记为纯代理树列表。
     *
     * @return void
     */
    public function test_audit_document_records_legacy_admin_position_sub_agents_route_semantics(): void
    {
        $auditContent = (string) file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md'));

        $positionRow = $this->markdownTableRowFor($auditContent, 'PositionSummaryController');
        $agentRow = $this->markdownTableRowFor($auditContent, 'AgentControllerV3');

        $this->assertStringContainsString('subAgentsListSearchV2', $positionRow);
        $this->assertStringContainsString('admin_api_positionSummaryList', $positionRow);
        $this->assertStringContainsString('searchtype=subAgentsSearch', $positionRow);
        $this->assertStringContainsString('userPId', $positionRow);
        $this->assertStringContainsString('AdminLegacyRouteSemanticClosureTest', $positionRow);

        $this->assertStringNotContainsString('subAgentsListSearchV2', $agentRow, '旧后台持仓汇总下级入口不能归入纯代理树列表审计行。');
    }

    /**
     * 从 Markdown 表格中提取指定旧控制器所在行。
     *
     * @param string $content 审计文档正文。
     * @param string $needle 旧控制器名称。
     * @return string 匹配到的表格行。
     */
    private function markdownTableRowFor(string $content, string $needle): string
    {
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (str_contains($line, '`' . $needle . '`')) {
                return $line;
            }
        }

        $this->fail('审计文档缺少控制器条目：' . $needle);
    }
}
