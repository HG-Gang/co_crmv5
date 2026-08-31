<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 11:42
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 返佣转移人工对账静态契约测试。
 *
 * 文件功能：
 * - 验证正常 Saga 与人工对账共用同一账本终结器，避免资金镜像和账本出现双重写入来源。
 * - 验证后台控制器、Layui Blade 与 CrmUI Blade 提交同一组人工证据字段和权限标识。
 * - 验证 MySQL/MyISAM 测试夹具不会覆盖既有财务行，并能在结束后恢复自增值和原始数据。
 *
 * 执行结果：
 * - 通过表示账本唯一性、人工补偿边界和两套服务端后台页面契约一致。
 * - 失败表示资金闭环、并发保护、测试数据恢复或后台证据字段至少有一处发生漂移。
 */
final class CommissionTransferReconciliationContractTest extends TestCase
{
    /**
     * 人工对账证据字段的契约全集：裁决决策、外部备注与三个资金步骤各自的状态/参考号，以及双方余额快照。
     * 后台控制器与两套 Blade 页面必须提交完全一致的字段集合；多字段会让控制器丢证据，少字段则无法裁决。
     *
     * @var array<int, string>
     */
    private const EVIDENCE_FIELDS = [
        'decision',
        'external_reference',
        'withdraw_status',
        'withdraw_reference',
        'deposit_status',
        'deposit_reference',
        'compensation_status',
        'compensation_reference',
        'source_balance_after',
        'target_balance_after',
    ];

    public function test_follow_up_migration_preserves_manual_origin_and_enforces_ledger_identity(): void
    {
        $migration = $this->source('database/migrations/2026_07_19_000008_harden_commission_transfer_reconciliation.php');
        $resolver = $this->source('app/Services/CommissionTransfer/CommissionTransferManualOriginStepBackfillResolver.php');

        foreach (['manual_origin_step', 'reconcile_evidence', 'commission_records_unique_id_unique'] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }
        $this->assertStringNotContainsString('dropColumn', $migration);
        $this->assertStringNotContainsString('dropIfExists', $migration);
        $this->assertStringNotContainsString('->delete()', $migration);
        $this->assertStringContainsString('CommissionTransferManualOriginStepBackfillResolver', $migration);
        $this->assertStringNotContainsString('strpos(', $resolver);
    }

    public function test_normal_saga_and_manual_reconciliation_share_one_ledger_finalizer(): void
    {
        $saga = $this->source('app/Services/CommissionTransfer/CommissionTransferService.php');
        $reconciliation = $this->source('app/Services/CommissionTransfer/CommissionTransferReconciliationService.php');
        $finalizer = $this->source('app/Services/CommissionTransfer/CommissionTransferLedgerFinalizer.php');

        $this->assertSame(1, substr_count($saga, 'ledgerFinalizer->finalizeCompleted('));
        $this->assertSame(1, substr_count($reconciliation, 'ledgerFinalizer->finalizeCompleted('));
        $this->assertStringNotContainsString('CommissionRecord::', $saga);
        $this->assertStringNotContainsString('CommissionRecord::', $reconciliation);
        $this->assertStringContainsString('CommissionRecord::firstOrCreate', $finalizer);
        $this->assertStringContainsString("target->total_funds = \$targetBalanceAfter", $finalizer);
        $this->assertStringNotContainsString("bcadd((string) \$target->total_funds", $finalizer);

        $recordsGuard = strpos($finalizer, 'ensureCommissionRecords');
        $mirrorWrite = strpos($finalizer, 'source->total_funds = $sourceBalanceAfter');
        $this->assertNotFalse($recordsGuard, 'Finalizer must verify/create both ledger legs before mirror writes.');
        $this->assertNotFalse($mirrorWrite, 'Finalizer must update both account mirrors.');
        $this->assertLessThan($mirrorWrite, $recordsGuard, 'Ledger identity must be established before mirrors change.');
    }

    public function test_reconciliation_queries_require_one_manual_process_outbox(): void
    {
        $service = $this->source('app/Services/CommissionTransfer/CommissionTransferReconciliationService.php');

        $this->assertStringContainsString('whereHas', $service);
        $this->assertStringContainsString('process_outbox_count', $service);
        $this->assertStringContainsString('= 1', $service);
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($service, "->where('event_type', 'process')"),
            'List, detail, and mutation must each constrain process outboxes.'
        );
    }

    public function test_controller_and_all_admin_surfaces_submit_the_same_evidence_fields(): void
    {
        $controller = $this->source('app/Http/Controllers/Admin/CommissionController.php');
        $layuiBlade = $this->source('resources/admin/layui/commissions/index.blade.php');
        $layuiJs = $this->source('public/js/apps/admin/layui/pages.js');
        $crmPage = $this->source('app/Http/Controllers/CrmUi/Admin/PageController.php');
        $crmModule = $this->source('resources/admin/crmui/partials/module-page.blade.php');
        $surfaces = [
            'Layui Blade' => $layuiBlade,
            'Layui JavaScript' => $layuiJs,
            'CrmUi' => $crmPage . $crmModule,
        ];

        foreach (self::EVIDENCE_FIELDS as $field) {
            $this->assertStringContainsString($field, $controller, $field . ' is missing from the controller.');
            foreach ($surfaces as $name => $source) {
                $this->assertStringContainsString($field, $source, $field . ' is missing from ' . $name . '.');
            }
        }

        foreach ([
            'admin_commission_transfer_reconciliation_list',
            'admin_commission_transfer_reconciliation_detail',
            'admin_commission_transfer_reconcile',
        ] as $permission) {
            foreach ($surfaces as $name => $surface) {
                $this->assertStringContainsString($permission, $surface, $permission . ' is missing from ' . $name . '.');
            }
        }
    }

    public function test_manual_reconciliation_fixture_never_overwrites_existing_financial_rows(): void
    {
        $fixture = $this->source('tests/Feature/CommissionTransferManualReconciliationServiceTest.php');

        $this->assertStringNotContainsString('private const SOURCE', $fixture);
        $this->assertStringNotContainsString('private const TARGET', $fixture);
        $this->assertStringNotContainsString('updateOrInsert', $fixture);
        $this->assertStringContainsString('random_int', $fixture);
        $this->assertStringContainsString('finally', $fixture);
        $this->assertStringContainsString('createdLedgerUniqueIds', $fixture);
        $this->assertStringContainsString('createdOrderNumbers', $fixture);
        $this->assertStringContainsString('originalAutoIncrements', $fixture);
        $this->assertStringContainsString('information_schema_stats_expiry', $fixture);
        $this->assertStringContainsString('MAX(ID)', strtoupper($fixture));
        $this->assertStringContainsString('ALTER TABLE', $fixture);
        $this->assertStringNotContainsString('use DatabaseTransactions', $fixture);
    }

    public function test_mysql_saga_and_admin_fixtures_restore_legacy_myisam_rows_without_fixed_ids(): void
    {
        $saga = $this->source('tests/Feature/CommissionTransferSagaServiceTest.php');
        $admin = $this->source('tests/Feature/AdminCommissionTransferReconciliationClosureModuleTest.php');
        $autoIncrement = $this->source('tests/Support/MySqlAutoIncrementSnapshot.php');

        foreach ([$saga, $admin] as $fixture) {
            $this->assertStringContainsString('MySqlAutoIncrementSnapshot', $fixture);
            $this->assertStringContainsString('MySqlFixtureMutex', $fixture);
            $this->assertStringContainsString('tableFingerprints', $fixture);
            $this->assertStringContainsString('createdUserIds', $fixture);
            $this->assertStringContainsString('createdLedgerUniqueIds', $fixture);
            $this->assertStringContainsString('initialLedgerFingerprints', $fixture);
            $this->assertStringContainsString('ledgerRowFingerprints', $fixture);
            $this->assertStringContainsString('refusing', strtolower($fixture));
            $this->assertStringContainsString('createdOrderNumbers', $fixture);
            $this->assertStringContainsString('cleanupFailures', $fixture);
            $this->assertStringContainsString('finally', $fixture);
            $this->assertStringNotContainsString('use DatabaseTransactions', $fixture);
            $this->assertStringNotContainsString('updateOrInsert', $fixture);
        }

        $this->assertStringNotContainsString('private const SOURCE', $saga);
        $this->assertStringNotContainsString('private const TARGET', $saga);
        $this->assertStringContainsString('unusedUserPair', $saga);
        $this->assertStringContainsString('continue', $autoIncrement);
        $this->assertStringContainsString('restoreFailures', $autoIncrement);
    }

    public function test_admin_reconciliation_closure_covers_mutation_scope_and_real_cas_race(): void
    {
        $admin = $this->source('tests/Feature/AdminCommissionTransferReconciliationClosureModuleTest.php');
        $worker = $this->source('tests/Support/commission_transfer_reconciliation_worker.php');

        $this->assertStringContainsString(
            'test_reconcile_mutation_enforces_admin_data_scope_without_hidden_writes',
            $admin
        );
        $this->assertStringContainsString(
            'test_two_independent_workers_reconcile_one_manual_case_once',
            $admin
        );
        $this->assertStringContainsString('proc_open', $admin);
        $this->assertStringContainsString('waitForWorkerExit', $admin);
        $this->assertStringContainsString('proc_terminate', $admin);
        $this->assertStringContainsString('CommissionTransferReconciliationService', $worker);
    }

    /**
     * 读取仓库内参与契约比对的源文件。
     *
     * @param string $relativePath 相对项目根目录的必传路径，例如 `app/Services/Foo.php`。
     * @return string 文件完整内容；文件不存在时由断言立即失败，禁止以空字符串伪造通过。
     */
    private function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
