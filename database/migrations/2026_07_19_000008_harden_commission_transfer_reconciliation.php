<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 加固佣金划转对账流程。
 *
 * 文件功能：
 * - 规范化对账相关数据（孤儿单、重复单、状态不一致记录）并补充索引。
 *
 * 字段语义：
 * - 资金对账记录不可逆；回滚保留规范化结果，防止资金账目被破坏。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\CommissionTransfer\CommissionTransferManualOriginStepBackfillResolver;

class HardenCommissionTransferReconciliation extends Migration
{
    /**
     * 佣金转账主表名。对账证据列（manual_origin_step/reconcile_evidence）写在该表上，
     * 前置断言也按它检查状态机列是否齐全；集中定义防止 SQL 与断言口径不一致。
     */
    private const TRANSFERS = 'commission_transfers';

    /**
     * 佣金流水表名。本迁移为该表的 unique_id 补建唯一约束，防止重复入账；
     * 前置断言按它确认 unique_id 列存在。
     */
    private const RECORDS = 'commission_records';

    /**
     * 佣金流水唯一约束索引名：(unique_id)。unique_id 由 hash(sha256, 'commission-transfer:'+identity) 生成，
     * 该约束是“同一笔转账只入账一次”的数据库级防线；丢失会让重复对账产生双份佣金流水。
     */
    private const RECORD_UNIQUE = 'commission_records_unique_id_unique';

    public function up()
    {
        $this->assertRequiredTables();
        $this->addReconciliationEvidenceColumns();
        $this->backfillManualOriginSteps();
        $this->ensureCommissionRecordIdentityUnique();
    }

    public function down()
    {
        // Financial reconciliation evidence and ledger identities are forward-only safeguards.
    }

    private function assertRequiredTables(): void
    {
        foreach ([self::TRANSFERS, self::RECORDS] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('Cannot harden commission transfer reconciliation: ' . $table . ' is missing.');
            }
        }
        foreach (['status', 'current_step', 'last_error_code'] as $column) {
            if (!Schema::hasColumn(self::TRANSFERS, $column)) {
                throw new RuntimeException(
                    'Cannot harden commission transfer reconciliation: commission_transfers.' . $column . ' is missing.'
                );
            }
        }
        if (!Schema::hasColumn(self::RECORDS, 'unique_id')) {
            throw new RuntimeException(
                'Cannot harden commission transfer reconciliation: commission_records.unique_id is missing.'
            );
        }
    }

    private function addReconciliationEvidenceColumns(): void
    {
        $missing = [];
        foreach (['manual_origin_step', 'reconcile_evidence'] as $column) {
            if (!Schema::hasColumn(self::TRANSFERS, $column)) {
                $missing[] = $column;
            }
        }
        if ($missing === []) {
            return;
        }

        Schema::table(self::TRANSFERS, function (Blueprint $table) use ($missing): void {
            if (in_array('manual_origin_step', $missing, true)) {
                $table->string('manual_origin_step', 40)->nullable()->after('current_step');
            }
            if (in_array('reconcile_evidence', $missing, true)) {
                $table->text('reconcile_evidence')->nullable()->after('reconcile_external_reference');
            }
        });
    }

    private function backfillManualOriginSteps(): void
    {
        DB::table(self::TRANSFERS)
            ->where('status', 'manual_reconcile_required')
            ->whereNull('manual_origin_step')
            ->orderBy('id')
            ->chunkById(100, function (Collection $transfers): void {
                foreach ($transfers as $transfer) {
                    $originStep = $this->inferManualOriginStep($transfer);
                    $updates = ['manual_origin_step' => $originStep];
                    if ($originStep !== 'unknown' && (string) $transfer->current_step === 'manual_reconcile') {
                        $updates['current_step'] = $originStep;
                    }
                    DB::table(self::TRANSFERS)->where('id', $transfer->id)->update($updates);
                }
            });
    }

    private function inferManualOriginStep(object $transfer): string
    {
        return (new CommissionTransferManualOriginStepBackfillResolver())->resolve(
            (string) $transfer->current_step,
            $transfer->last_error_code === null ? null : (string) $transfer->last_error_code
        );
    }

    private function ensureCommissionRecordIdentityUnique(): void
    {
        $duplicate = DB::table(self::RECORDS)
            ->select('unique_id', DB::raw('COUNT(*) AS duplicate_count'))
            ->groupBy('unique_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        if ($duplicate !== null) {
            throw new RuntimeException(
                'Cannot harden commission transfer ledger identity: duplicate commission_records.unique_id values exist.'
            );
        }

        $index = $this->indexes()->get(self::RECORD_UNIQUE, collect());
        if (!$index->isEmpty()) {
            if ($index->pluck('Column_name')->values()->all() !== ['unique_id']
                || (int) $index->first()->Non_unique !== 0
                || $index->pluck('Sub_part')->filter(static function ($part): bool {
                    return $part !== null;
                })->isNotEmpty()) {
                throw new RuntimeException('Unknown index definition for ' . self::RECORD_UNIQUE . '.');
            }

            return;
        }

        DB::statement(
            'ALTER TABLE ' . self::RECORDS
            . ' ADD UNIQUE INDEX `' . self::RECORD_UNIQUE . '` (`unique_id`)'
        );

        $verified = $this->indexes()->get(self::RECORD_UNIQUE, collect());
        if ($verified->isEmpty()
            || $verified->pluck('Column_name')->values()->all() !== ['unique_id']
            || (int) $verified->first()->Non_unique !== 0) {
            throw new RuntimeException('Failed to verify ' . self::RECORD_UNIQUE . '.');
        }
    }

    /** @return Collection<string, Collection<int, object>> */
    private function indexes(): Collection
    {
        return collect(DB::select('SHOW INDEX FROM ' . self::RECORDS))
            ->sortBy('Seq_in_index')
            ->groupBy('Key_name');
    }
}
