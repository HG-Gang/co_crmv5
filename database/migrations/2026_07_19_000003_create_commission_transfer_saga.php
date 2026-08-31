<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:44
 */

/**
 * 创建佣金划转 Saga 表 commission_transfer_saga。
 *
 * 文件功能：
 * - 佣金划转长事务（Saga）状态表：流程步骤、状态、补偿与重试信息。
 *
 * 字段语义：
 * - saga_id 流程 ID（唯一）；step 当前步骤；status 状态（running/succeeded/failed/compensated）；
 * - payload 上下文载荷（JSON）；compensation 补偿数据；attempts 重试次数；
 * - last_error 最近错误；started_at/finished_at 起止时间。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCommissionTransferSaga extends Migration
{
    /**
     * 佣金转账主表名。本迁移同时创建 commission_transfers（主表）与出箱表，
     * 两表名以常量集中定义，建表与回滚断言共用，避免出现两处字面量不一致。
     */
    private const TRANSFERS = 'commission_transfers';

    /**
     * 佣金转账出箱表名。Saga 的每一步资金指令都经该表投递与重试（Outbox 模式）；
     * 与主表名分离定义是为了在 up/down 的存在性断言中互不误判。
     */
    private const OUTBOX = 'commission_transfer_outbox';

    public function up()
    {
        if (!Schema::hasTable(self::TRANSFERS)) {
            Schema::create('commission_transfers', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->string('local_order_no', 64);
                $table->unsignedBigInteger('source_user_id');
                $table->unsignedBigInteger('target_user_id');
                $table->string('request_purpose', 40);
                $table->string('idempotency_key', 100);
                $table->char('payload_hash', 64);
                $table->text('payload_ciphertext')->nullable();
                $table->decimal('amount', 18, 2);
                $table->string('remark', 500)->nullable();
                $table->string('status', 40)->default('pending');
                $table->string('current_step', 40)->default('verify');
                $table->string('reservation_status', 30)->default('pending');
                $table->date('small_limit_day')->nullable();
                $table->string('small_limit_key', 80)->nullable();
                $table->string('withdraw_ticket', 100)->nullable();
                $table->string('deposit_ticket', 100)->nullable();
                $table->string('compensation_ticket', 100)->nullable();
                $table->decimal('source_balance_after', 18, 2)->nullable();
                $table->decimal('target_balance_after', 18, 2)->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedInteger('available_at')->nullable();
                $table->unsignedInteger('locked_at')->nullable();
                $table->unsignedInteger('processed_at')->nullable();
                $table->string('provider_reference', 100)->nullable();
                $table->string('last_error_code', 100)->nullable();
                $table->text('last_error_message')->nullable();
                $table->unsignedInteger('created_at')->nullable();
                $table->unsignedInteger('updated_at')->nullable();
                $table->unsignedInteger('deleted_at')->nullable();
            });
        }

        if (!Schema::hasTable(self::OUTBOX)) {
            Schema::create('commission_transfer_outbox', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('commission_transfer_id');
                $table->string('event_type', 40);
                $table->string('status', 40)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->char('payload_hash', 64);
                $table->unsignedInteger('available_at')->nullable();
                $table->unsignedInteger('locked_at')->nullable();
                $table->unsignedInteger('processed_at')->nullable();
                $table->string('provider_reference', 100)->nullable();
                $table->string('last_error_code', 100)->nullable();
                $table->unsignedInteger('created_at')->nullable();
                $table->unsignedInteger('updated_at')->nullable();
                $table->unsignedInteger('deleted_at')->nullable();
            });
        }

        $this->assertNonemptyTransferContract();
        $this->assertNonemptyOutboxContract();
        $this->ensureSafePrimaryKey(self::TRANSFERS);
        $this->ensureSafePrimaryKey(self::OUTBOX);
        $this->addMissingTransferColumns();
        $this->addMissingOutboxColumns();
        $this->ensureInnoDb(self::TRANSFERS);
        $this->ensureInnoDb(self::OUTBOX);
        $this->ensureTransferIndexes();
        $this->ensureOutboxIndexes();
    }

    public function down()
    {
        // Financial saga rows are an audit trail. Rollback must not destroy them.
    }

    private function assertNonemptyTransferContract(): void
    {
        $this->assertNonemptyTableContract(
            self::TRANSFERS,
            $this->transferColumns(),
            'Cannot safely repair nonempty commission_transfers; missing columns: '
        );
    }

    private function assertNonemptyOutboxContract(): void
    {
        $this->assertNonemptyTableContract(
            self::OUTBOX,
            $this->outboxColumns(),
            'Cannot safely repair nonempty commission_transfer_outbox; missing columns: '
        );
    }

    /** @param array<int, string> $required */
    private function assertNonemptyTableContract(string $table, array $required, string $message): void
    {
        if (!Schema::hasTable($table) || !DB::table($table)->limit(1)->exists()) {
            return;
        }

        $missing = array_values(array_filter($required, static function (string $column) use ($table): bool {
            return !Schema::hasColumn($table, $column);
        }));
        if ($missing !== []) {
            throw new RuntimeException($message . implode(', ', $missing));
        }
    }

    private function addMissingTransferColumns(): void
    {
        foreach ($this->transferColumns() as $column) {
            if ($column === 'id' || Schema::hasColumn(self::TRANSFERS, $column)) {
                continue;
            }
            Schema::table(self::TRANSFERS, function (Blueprint $table) use ($column): void {
                switch ($column) {
                    case 'local_order_no':
                        $table->string($column, 64)->nullable();
                        break;
                    case 'source_user_id':
                    case 'target_user_id':
                        $table->unsignedBigInteger($column)->nullable();
                        break;
                    case 'request_purpose':
                    case 'status':
                    case 'current_step':
                        $table->string($column, 40)->nullable();
                        break;
                    case 'idempotency_key':
                    case 'withdraw_ticket':
                    case 'deposit_ticket':
                    case 'compensation_ticket':
                    case 'provider_reference':
                    case 'last_error_code':
                        $table->string($column, 100)->nullable();
                        break;
                    case 'payload_hash':
                        $table->char($column, 64)->nullable();
                        break;
                    case 'payload_ciphertext':
                    case 'last_error_message':
                        $table->text($column)->nullable();
                        break;
                    case 'amount':
                    case 'source_balance_after':
                    case 'target_balance_after':
                        $table->decimal($column, 18, 2)->nullable();
                        break;
                    case 'remark':
                        $table->string($column, 500)->nullable();
                        break;
                    case 'reservation_status':
                        $table->string($column, 30)->nullable();
                        break;
                    case 'small_limit_day':
                        $table->date($column)->nullable();
                        break;
                    case 'small_limit_key':
                        $table->string($column, 80)->nullable();
                        break;
                    case 'attempts':
                    case 'available_at':
                    case 'locked_at':
                    case 'processed_at':
                    case 'created_at':
                    case 'updated_at':
                    case 'deleted_at':
                        $table->unsignedInteger($column)->nullable();
                        break;
                }
            });
        }
    }

    private function addMissingOutboxColumns(): void
    {
        foreach ($this->outboxColumns() as $column) {
            if ($column === 'id' || Schema::hasColumn(self::OUTBOX, $column)) {
                continue;
            }
            Schema::table(self::OUTBOX, function (Blueprint $table) use ($column): void {
                switch ($column) {
                    case 'commission_transfer_id':
                        $table->unsignedBigInteger($column)->nullable();
                        break;
                    case 'event_type':
                    case 'status':
                        $table->string($column, 40)->nullable();
                        break;
                    case 'payload_hash':
                        $table->char($column, 64)->nullable();
                        break;
                    case 'provider_reference':
                    case 'last_error_code':
                        $table->string($column, 100)->nullable();
                        break;
                    case 'attempts':
                    case 'available_at':
                    case 'locked_at':
                    case 'processed_at':
                    case 'created_at':
                    case 'updated_at':
                    case 'deleted_at':
                        $table->unsignedInteger($column)->nullable();
                        break;
                }
            });
        }
    }

    private function ensureSafePrimaryKey(string $table): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $primary = collect(DB::select('SHOW INDEX FROM ' . $table))
            ->where('Key_name', 'PRIMARY')
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
        if (Schema::hasColumn($table, 'id') && $primary === ['id']) {
            return;
        }
        if (DB::table($table)->limit(1)->exists()) {
            throw new RuntimeException('Cannot safely repair nonempty ' . $table . ' primary key.');
        }
        if (!Schema::hasColumn($table, 'id')) {
            if ($primary !== []) {
                throw new RuntimeException('Cannot add id while another primary key exists on ' . $table . '.');
            }
            DB::statement('ALTER TABLE ' . $table . ' ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');

            return;
        }

        $drop = $primary === [] ? '' : 'DROP PRIMARY KEY, ';
        DB::statement('ALTER TABLE ' . $table . ' ' . $drop
            . 'MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)');
    }

    private function ensureInnoDb(string $table): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $engine = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->value('ENGINE');
        if (strcasecmp((string) $engine, 'InnoDB') !== 0) {
            DB::statement('ALTER TABLE ' . $table . ' ENGINE=InnoDB');
        }
    }

    private function ensureTransferIndexes(): void
    {
        $this->assertNoDuplicateTransferLocalOrders();
        $this->assertNoDuplicateTransferIdempotencyKeys();
        $this->assertNoDuplicateSmallLimitKeys();
        $this->ensureIndex(self::TRANSFERS, 'commission_transfers_local_order_unique', ['local_order_no'], true);
        $this->ensureIndex(
            self::TRANSFERS,
            'commission_transfers_source_purpose_idempotency_unique',
            ['source_user_id', 'request_purpose', 'idempotency_key'],
            true
        );
        $this->ensureIndex(self::TRANSFERS, 'commission_transfers_small_limit_unique', ['small_limit_key'], true);
        $this->ensureIndex(self::TRANSFERS, 'commission_transfers_ready_index', ['status', 'available_at'], false);
        $this->ensureIndex(self::TRANSFERS, 'commission_transfers_stale_index', ['status', 'locked_at'], false);
    }

    private function ensureOutboxIndexes(): void
    {
        $this->assertNoDuplicateOutboxEvents();
        $this->ensureIndex(
            self::OUTBOX,
            'commission_transfer_outbox_event_unique',
            ['commission_transfer_id', 'event_type'],
            true
        );
        $this->ensureIndex(self::OUTBOX, 'commission_transfer_outbox_ready_index', ['status', 'available_at'], false);
        $this->ensureIndex(self::OUTBOX, 'commission_transfer_outbox_stale_index', ['status', 'locked_at'], false);
    }

    private function assertNoDuplicateTransferLocalOrders(): void
    {
        $this->assertNoDuplicate(self::TRANSFERS, ['local_order_no'], 'local_order_no');
    }

    private function assertNoDuplicateTransferIdempotencyKeys(): void
    {
        $this->assertNoDuplicate(
            self::TRANSFERS,
            ['source_user_id', 'request_purpose', 'idempotency_key'],
            'source/purpose/idempotency'
        );
    }

    private function assertNoDuplicateSmallLimitKeys(): void
    {
        $this->assertNoDuplicate(self::TRANSFERS, ['small_limit_key'], 'small_limit_key', 'small_limit_key');
    }

    private function assertNoDuplicateOutboxEvents(): void
    {
        $this->assertNoDuplicate(
            self::OUTBOX,
            ['commission_transfer_id', 'event_type'],
            'commission_transfer_id/event_type'
        );
    }

    /** @param array<int, string> $columns */
    private function assertNoDuplicate(string $table, array $columns, string $identity, string $notNull = null): void
    {
        $query = DB::table($table)->select($columns);
        if ($notNull !== null) {
            $query->whereNotNull($notNull)->where($notNull, '<>', '');
        }
        $duplicate = $query->groupBy($columns)->havingRaw('COUNT(*) > 1')->first();
        if ($duplicate !== null) {
            throw new RuntimeException('Cannot create commission transfer unique index; duplicate ' . $identity . '.');
        }
    }

    /** @param array<int, string> $columns */
    private function ensureIndex(string $table, string $name, array $columns, bool $unique): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $rows = collect(DB::select('SHOW INDEX FROM ' . $table))
            ->where('Key_name', $name)
            ->sortBy('Seq_in_index');
        $matches = $rows->pluck('Column_name')->values()->all() === $columns
            && (!$rows->isEmpty())
            && (int) $rows->first()->Non_unique === ($unique ? 0 : 1);
        if ($matches) {
            return;
        }
        if (!$rows->isEmpty()) {
            DB::statement('ALTER TABLE ' . $table . ' DROP INDEX `' . $name . '`');
        }
        $kind = $unique ? 'UNIQUE ' : '';
        DB::statement('ALTER TABLE ' . $table . ' ADD ' . $kind . 'INDEX `' . $name
            . '` (`' . implode('`,`', $columns) . '`)');
    }

    /** @return array<int, string> */
    private function transferColumns(): array
    {
        return [
            'id', 'local_order_no', 'source_user_id', 'target_user_id', 'request_purpose',
            'idempotency_key', 'payload_hash', 'payload_ciphertext', 'amount', 'remark',
            'status', 'current_step', 'reservation_status', 'small_limit_day', 'small_limit_key',
            'withdraw_ticket', 'deposit_ticket', 'compensation_ticket', 'source_balance_after',
            'target_balance_after', 'attempts', 'available_at', 'locked_at', 'processed_at',
            'provider_reference', 'last_error_code', 'last_error_message', 'created_at',
            'updated_at', 'deleted_at',
        ];
    }

    /** @return array<int, string> */
    private function outboxColumns(): array
    {
        return [
            'id', 'commission_transfer_id', 'event_type', 'status', 'attempts', 'payload_hash',
            'available_at', 'locked_at', 'processed_at', 'provider_reference', 'last_error_code',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }
}
