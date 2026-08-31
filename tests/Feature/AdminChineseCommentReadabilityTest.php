<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：检查近期新增的后台后端文件（控制器、模型、blade、JS、迁移、
 *           测试与文档）注释可读性：不允许乱码片段，必须包含中文注释或文案。
 *
 * 适用场景：后台代码中文注释质量的门禁测试，防止 GBK/UTF-8 乱码与问号占位符
 *           混入近期文件。
 *
 * 入参例子：
 * - 无入参；内部通过 recentAdminFiles() 枚举受检文件清单。
 *
 * 返回值：
 * - 所有受检文件不含乱码片段且含中文字符时测试通过。
 *
 * 异常或失败场景：
 * - 任一文件包含乱码片段（如 '闂?'）、缺少中文注释或含 '???' 占位符时断言失败。
 */

namespace Tests\Feature;

use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

class AdminChineseCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    // 近期后台文件不得包含乱码片段（GBK/UTF-8 混排产生的乱码字符）。
    public function test_recent_admin_backend_files_do_not_contain_mojibake_comments(): void
    {
        $mojibakeFragments = [
            '闂?',
            '缂?',
            '闁?',
            '閸?',
            '閹?',
            '娣?',
            '鐎?',
            '鏉?',
            '濡?',
            '鐠?',
            '閻?',
            '缁?',
        ];

        foreach ($this->recentAdminFiles() as $file) {
            $content = $this->readAdminFileForCommentCheck($file);
            $this->assertNotSame('', $content, $file . ' content must be readable from file or aggregated pages.js.');

            foreach ($mojibakeFragments as $fragment) {
                $this->assertStringNotContainsString(
                    $fragment,
                    $content,
                    $file . ' contains possible mojibake fragment: ' . $fragment
                );
            }
        }
    }

    // 近期后台文件必须包含中文注释或中文文案。
    public function test_recent_admin_backend_files_contain_chinese_logic_comments(): void
    {
        foreach ($this->recentAdminFiles() as $file) {
            $content = $this->readAdminFileForCommentCheck($file);
            $this->assertNotSame('', $content, $file . ' content must be readable from file or aggregated pages.js.');

            $this->assertMatchesRegularExpression(
                '/[\x{4e00}-\x{9fff}]/u',
                $content,
                $file . ' must contain Chinese logic comments or copy.'
            );
        }
    }

    // 在线用户相关文件不得包含问号占位符（???）。
    public function test_online_user_files_do_not_contain_question_mark_placeholders(): void
    {
        $files = [
            app_path('Http/Controllers/Admin/OnlineUserController.php'),
            app_path('Models/UserOnline.php'),
            resource_path('admin/layui/online-users/index.blade.php'),
            public_path('js/apps/admin/layui/online-users/index.js'),
            database_path('migrations/2026_06_07_000009_add_admin_online_user_permissions.php'),
            base_path('routes/admin.php'),
            base_path('routes/web.php'),
            resource_path('lang/zh-CN/admin.php'),
        ];

        foreach ($files as $file) {
            $content = $this->readAdminFileForCommentCheck($file);
            $this->assertNotSame('', $content, $file . ' content must be readable from file or aggregated pages.js.');

            $this->assertStringNotContainsString(
                '???',
                $content,
                $file . ' must not contain question-mark placeholders.'
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function recentAdminFiles(): array
    {
        return [
            app_path('Http/Middleware/CheckPermission.php'),
            app_path('Http/Controllers/Admin/BatchAmountImportController.php'),
            app_path('Http/Controllers/Admin/BatchCreditImportController.php'),
            app_path('Http/Controllers/Admin/FundFlowController.php'),
            app_path('Http/Controllers/Admin/RightsSummaryController.php'),
            app_path('Http/Controllers/Admin/SystemConfigController.php'),
            app_path('Http/Controllers/Admin/ExchangeRateController.php'),
            app_path('Http/Controllers/Admin/OnlineUserController.php'),
            app_path('Http/Controllers/Admin/ProductionController.php'),
            app_path('Http/Controllers/Admin/GiftController.php'),
            app_path('Http/Controllers/Admin/AuthenticationController.php'),
            app_path('Http/Controllers/Admin/RealtimeCommissionController.php'),
            app_path('Http/Controllers/Admin/PositionSummaryController.php'),
            app_path('Http/Controllers/Admin/AdminUserController.php'),
            app_path('Http/Controllers/Admin/AgentController.php'),
            app_path('Http/Controllers/Admin/VoucherController.php'),
            app_path('Http/Controllers/Admin/RiskController.php'),
            app_path('Http/Controllers/Admin/BlacklistController.php'),
            app_path('Http/Controllers/Admin/CancelApplyController.php'),
            app_path('Http/Controllers/Admin/TradeController.php'),
            app_path('Http/Controllers/Admin/BigAgentController.php'),
            app_path('Models/DepositImport.php'),
            app_path('Models/WithdrawImport.php'),
            app_path('Models/CreditImport.php'),
            app_path('Models/Mt4Trade.php'),
            app_path('Models/Mt4User.php'),
            app_path('Models/UserTrade.php'),
            app_path('Models/UserOnline.php'),
            resource_path('admin/layui/deposit-imports/index.blade.php'),
            resource_path('admin/layui/withdraw-imports/index.blade.php'),
            resource_path('admin/layui/credit-imports/index.blade.php'),
            resource_path('admin/layui/withdraw-flows/index.blade.php'),
            resource_path('admin/layui/undeposit-flows/index.blade.php'),
            resource_path('admin/layui/rights-summary/index.blade.php'),
            resource_path('admin/layui/system-configs/index.blade.php'),
            resource_path('admin/layui/exchange-rates/index.blade.php'),
            resource_path('admin/layui/online-users/index.blade.php'),
            resource_path('admin/layui/productions/index.blade.php'),
            resource_path('admin/layui/gifts/index.blade.php'),
            resource_path('admin/layui/authentications/index.blade.php'),
            resource_path('admin/layui/realtime-commissions/index.blade.php'),
            resource_path('admin/layui/position-summary/index.blade.php'),
            resource_path('admin/layui/risk/index.blade.php'),
            resource_path('admin/layui/agents/index.blade.php'),
            public_path('js/apps/admin/layui/deposit-imports/index.js'),
            public_path('js/apps/admin/layui/withdraw-imports/index.js'),
            public_path('js/apps/admin/layui/credit-imports/index.js'),
            public_path('js/apps/admin/layui/withdraw-flows/index.js'),
            public_path('js/apps/admin/layui/undeposit-flows/index.js'),
            public_path('js/apps/admin/layui/rights-summary/index.js'),
            public_path('js/apps/admin/layui/system-configs/index.js'),
            public_path('js/apps/admin/layui/exchange-rates/index.js'),
            public_path('js/apps/admin/layui/online-users/index.js'),
            public_path('js/apps/admin/layui/productions/index.js'),
            public_path('js/apps/admin/layui/gifts/index.js'),
            public_path('js/apps/admin/layui/authentications/index.js'),
            public_path('js/apps/admin/layui/realtime-commissions/index.js'),
            public_path('js/apps/admin/layui/position-summary/index.js'),
            public_path('js/apps/admin/layui/risk/index.js'),
            public_path('js/apps/admin/layui/agents/index.js'),
            database_path('migrations/2026_06_07_000004_add_admin_batch_amount_import_permissions.php'),
            database_path('migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php'),
            database_path('migrations/2026_06_07_000006_add_admin_fund_flow_permissions.php'),
            database_path('migrations/2026_06_07_000007_add_admin_rights_summary_permissions.php'),
            database_path('migrations/2026_06_07_000008_add_admin_exchange_rate_permissions.php'),
            database_path('migrations/2026_06_07_000009_add_admin_online_user_permissions.php'),
            database_path('migrations/2026_06_07_000010_add_admin_production_permissions.php'),
            database_path('migrations/2026_06_07_000011_add_admin_gift_permissions.php'),
            database_path('migrations/2026_06_07_000012_add_admin_authentication_permissions.php'),
            database_path('migrations/2026_06_07_000013_add_admin_realtime_commission_permissions.php'),
            database_path('migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php'),
            database_path('migrations/2026_06_07_000015_add_admin_position_summary_permissions.php'),
            database_path('migrations/2026_06_07_000002_add_admin_system_config_update_permission.php'),
            database_path('migrations/2026_06_07_000003_add_admin_agent_operation_permissions.php'),
            base_path('tests/Feature/AdminBatchCreditImportModuleTest.php'),
            base_path('tests/Feature/AdminBatchCreditImportPermissionMigrationTest.php'),
            base_path('tests/Feature/AdminRightsSummaryModuleTest.php'),
            base_path('tests/Feature/AdminRightsSummaryPermissionMigrationTest.php'),
            base_path('tests/Feature/AdminExchangeRateModuleTest.php'),
            base_path('tests/Feature/AdminOnlineUserModuleTest.php'),
            base_path('tests/Feature/AdminProductionModuleTest.php'),
            base_path('tests/Feature/AdminGiftModuleTest.php'),
            base_path('tests/Feature/AdminAuthenticationModuleTest.php'),
            base_path('tests/Feature/AdminRealtimeCommissionModuleTest.php'),
            base_path('tests/Feature/AdminPositionSummaryModuleTest.php'),
            base_path('tests/Feature/AdminRiskMt4ModuleTest.php'),
            base_path('tests/Feature/AdminRiskIpModuleTest.php'),
            base_path('tests/Feature/DefaultAdminAndFrontMenuRoleMigrationTest.php'),
            base_path('docs/admin-legacy-migration-gap-audit.md'),
        ];
    }

    private function readAdminFileForCommentCheck(string $file): string
    {
        $normalizedFile = str_replace('\\', '/', $file);
        $adminLayuiRoot = str_replace('\\', '/', public_path('js/apps/admin/layui') . DIRECTORY_SEPARATOR);

        if (strpos($normalizedFile, $adminLayuiRoot) === 0) {
            return $this->adminLayuiScript(substr($normalizedFile, strlen($adminLayuiRoot)));
        }

        return is_file($file) ? (file_get_contents($file) ?: '') : '';
    }
}
