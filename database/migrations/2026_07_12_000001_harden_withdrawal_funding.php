<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:48
 */

/**
 * 加固提现资金链路：精度、Outbox 契约与孤儿单规范化。
 *
 * 文件功能：
 * - 为 withdraw_records 补充缺失字段并规范化金额精度；
 * - 为 withdraw_settlement_outbox 建立安全主键与索引，规范化孤儿待处理提现单。
 *
 * 字段语义：
 * - 资金身份、精度与审计事件不可逆；down() 保留加固后的结构，防止资金数据被破坏。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenWithdrawalFunding extends Migration
{
    public function up()
    {
        // Validate every predictable blocker before changing schema or data.
        $this->assertNonemptyExistingOutboxContract();
        $this->ensureSafeOutboxPrimaryKey(false);
        $this->assertMigrationPreflight();
        $this->ensureSafeOutboxPrimaryKey();

        if (Schema::hasTable('withdraw_records')) {
            $this->addMissingWithdrawColumns();
            $this->normalizeWithdrawRows();
            $this->hardenWithdrawColumns();
            $this->ensureWithdrawIndexes();
        }

        if (!Schema::hasTable('withdraw_settlement_outbox')) {
            $this->createOutbox();
        } else {
            $this->addMissingOutboxColumns();
        }

        $this->normalizeOutboxRows();
        $this->hardenOutboxColumns();
        $this->ensureOutboxIndexes();
        $this->normalizeOrphanPendingWithdrawRows();
    }

    public function down()
    {
        // Financial identity, precision, and audit events are intentionally irreversible.
    }

    private function assertNonemptyExistingOutboxContract(): void
    {
        if (
            !Schema::hasTable('withdraw_settlement_outbox')
            || DB::getDriverName() !== 'mysql'
            || !DB::table('withdraw_settlement_outbox')->limit(1)->exists()
        ) {
            return;
        }

        $id = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'withdraw_settlement_outbox')
            ->where('COLUMN_NAME', 'id')
            ->first();
        $primaryColumns = collect(DB::select('SHOW INDEX FROM withdraw_settlement_outbox'))
            ->where('Key_name', 'PRIMARY')
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
        $validId = $id !== null
            && strtolower((string) $id->DATA_TYPE) === 'bigint'
            && strpos(strtolower((string) $id->COLUMN_TYPE), 'unsigned') !== false
            && strtoupper((string) $id->IS_NULLABLE) === 'NO'
            && strpos(strtolower((string) $id->EXTRA), 'auto_increment') !== false
            && $primaryColumns === ['id'];
        if (!$validId) {
            throw new RuntimeException('Cannot safely repair nonempty withdraw settlement outbox id contract.');
        }

        $requiredColumns = ['withdraw_record_id', 'local_order_no', 'event_type', 'payload_hash'];
        $missingColumns = array_values(array_filter($requiredColumns, static function (string $column): bool {
            return !Schema::hasColumn('withdraw_settlement_outbox', $column);
        }));
        if ($missingColumns !== []) {
            throw new RuntimeException(
                'Cannot safely repair nonempty withdraw settlement outbox required columns: '
                . implode(', ', $missingColumns)
            );
        }

        $invalidIdentityColumns = [];
        if (DB::table('withdraw_settlement_outbox')
            ->where(function ($query): void {
                $query->whereNull('withdraw_record_id')
                    ->orWhere('withdraw_record_id', '<=', 0);
            })
            ->exists()) {
            $invalidIdentityColumns[] = 'withdraw_record_id';
        }
        if (DB::table('withdraw_settlement_outbox')
            ->where(function ($query): void {
                $query->whereNull('event_type')
                    ->orWhereRaw("TRIM(event_type) = ''");
            })
            ->exists()) {
            $invalidIdentityColumns[] = 'event_type';
        }
        if (DB::table('withdraw_settlement_outbox')
            ->where(function ($query): void {
                $query->whereNull('payload_hash')
                    ->orWhereRaw("TRIM(payload_hash) = ''");
            })
            ->exists()) {
            $invalidIdentityColumns[] = 'payload_hash';
        }

        $unrecoverableLocalOrder = DB::table('withdraw_settlement_outbox as outbox')
            ->where(function ($query): void {
                $query->whereNull('outbox.local_order_no')
                    ->orWhereRaw("TRIM(outbox.local_order_no) = ''");
            });
        $canBackfillLocalOrder = Schema::hasTable('withdraw_records')
            && Schema::hasColumn('withdraw_records', 'id')
            && Schema::hasColumn('withdraw_records', 'local_order_no');
        if ($canBackfillLocalOrder) {
            $unrecoverableLocalOrder->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('withdraw_records as withdrawal')
                    ->whereColumn('withdrawal.id', 'outbox.withdraw_record_id')
                    ->whereNotNull('withdrawal.local_order_no')
                    ->whereRaw("TRIM(withdrawal.local_order_no) <> ''");
            });
        }
        if ($unrecoverableLocalOrder->exists()) {
            $invalidIdentityColumns[] = 'local_order_no';
        }

        if ($invalidIdentityColumns !== []) {
            throw new RuntimeException(
                'Cannot safely repair nonempty withdraw settlement outbox unrecoverable identity values: '
                . implode(', ', $invalidIdentityColumns)
            );
        }
    }

    private function assertMigrationPreflight(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Validate the effective outbox values first, including values recovered from withdrawals.
        $this->assertOutboxPreflight();
        $this->assertWithdrawPreflight();
    }

    private function assertWithdrawPreflight(): void
    {
        if (!Schema::hasTable('withdraw_records')) {
            return;
        }

        foreach (['apply_amount', 'actual_amount', 'fee', 'rmb_fee'] as $column) {
            $this->assertDecimalPreflight('withdraw_records', $column, 16, 2);
        }
        $this->assertDecimalPreflight('withdraw_records', 'exchange_rate', 10, 8);

        if (Schema::hasColumn('withdraw_records', 'local_order_no')) {
            if (DB::table('withdraw_records')
                ->whereNotNull('local_order_no')
                ->whereRaw("TRIM(local_order_no) <> ''")
                ->whereRaw('CHAR_LENGTH(TRIM(local_order_no)) > 200')
                ->exists()) {
                $this->throwPreflightFailure('withdraw_records', 'local_order_no exceeds VARCHAR(200) after trim');
            }

            $duplicateLocalOrder = DB::table('withdraw_records')
                ->selectRaw('LOWER(TRIM(local_order_no)) AS normalized_order_no, COUNT(*) AS duplicate_count')
                ->whereNotNull('local_order_no')
                ->whereRaw("TRIM(local_order_no) <> ''")
                ->groupByRaw('LOWER(TRIM(local_order_no))')
                ->havingRaw('COUNT(*) > 1')
                ->first();
            if ($duplicateLocalOrder) {
                $this->throwPreflightFailure(
                    'withdraw_records',
                    'local_order_no case-insensitive trimmed uniqueness'
                );
            }

            $generatedConflict = DB::selectOne(
                "SELECT 1 AS conflict FROM withdraw_records AS blank_order "
                . 'INNER JOIN withdraw_records AS existing_order '
                . "ON LOWER(TRIM(existing_order.local_order_no)) = LOWER(CONCAT('LEGACY-WDR-', blank_order.id)) "
                . "WHERE (blank_order.local_order_no IS NULL OR TRIM(blank_order.local_order_no) = '') "
                . "AND existing_order.local_order_no IS NOT NULL AND TRIM(existing_order.local_order_no) <> '' "
                . 'LIMIT 1'
            );
            if ($generatedConflict !== null) {
                $this->throwPreflightFailure('withdraw_records', 'LEGACY-WDR generated local_order_no conflict');
            }
        }

        $this->assertMaxLengthPreflight('withdraw_records', 'idempotency_key', 100);
        if (
            Schema::hasColumn('withdraw_records', 'idempotency_key')
            && Schema::hasColumn('withdraw_records', 'user_id')
        ) {
            $duplicateIdempotencyKey = DB::table('withdraw_records')
                ->select('user_id', 'idempotency_key', DB::raw('COUNT(*) AS duplicate_count'))
                ->whereNotNull('idempotency_key')
                ->groupBy('user_id', 'idempotency_key')
                ->havingRaw('COUNT(*) > 1')
                ->first();
            if ($duplicateIdempotencyKey) {
                $this->throwPreflightFailure('withdraw_records', 'idempotency_key unique per user_id');
            }
        }

        $this->assertMaxLengthPreflight('withdraw_records', 'funding_status', 30, true);
        $this->assertHashPreflight('withdraw_records', 'funding_payload_hash', true);
        $this->assertUnsignedIntegerPreflight(
            'withdraw_records',
            'refund_mt4_ticket',
            '18446744073709551615'
        );
        $this->assertMaxLengthPreflight('withdraw_records', 'funding_error_code', 100);
    }

    private function assertOutboxPreflight(): void
    {
        if (
            !Schema::hasTable('withdraw_settlement_outbox')
            || !DB::table('withdraw_settlement_outbox')->limit(1)->exists()
        ) {
            return;
        }

        if (Schema::hasColumn('withdraw_settlement_outbox', 'local_order_no')) {
            $localOrderTooLong = DB::table('withdraw_settlement_outbox')
                ->whereNotNull('local_order_no')
                ->whereRaw("TRIM(local_order_no) <> ''")
                ->whereRaw('CHAR_LENGTH(local_order_no) > 200')
                ->exists();
            if (
                !$localOrderTooLong
                && Schema::hasTable('withdraw_records')
                && Schema::hasColumn('withdraw_records', 'id')
                && Schema::hasColumn('withdraw_records', 'local_order_no')
                && Schema::hasColumn('withdraw_settlement_outbox', 'withdraw_record_id')
            ) {
                $localOrderTooLong = DB::table('withdraw_settlement_outbox as outbox')
                    ->join(
                        'withdraw_records as withdrawal',
                        'withdrawal.id',
                        '=',
                        'outbox.withdraw_record_id'
                    )
                    ->where(function ($query): void {
                        $query->whereNull('outbox.local_order_no')
                            ->orWhereRaw("TRIM(outbox.local_order_no) = ''");
                    })
                    ->whereRaw('CHAR_LENGTH(TRIM(withdrawal.local_order_no)) > 200')
                    ->exists();
            }
            if ($localOrderTooLong) {
                $this->throwPreflightFailure(
                    'withdraw_settlement_outbox',
                    'local_order_no exceeds VARCHAR(200) after recovery'
                );
            }
        }

        $this->assertMaxLengthPreflight('withdraw_settlement_outbox', 'event_type', 50);
        $this->assertMaxLengthPreflight('withdraw_settlement_outbox', 'status', 30, true);
        foreach ([
            'attempts',
            'available_at',
            'locked_at',
            'processed_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertUnsignedIntegerPreflight(
                'withdraw_settlement_outbox',
                $column,
                '4294967295'
            );
        }
        $this->assertHashPreflight('withdraw_settlement_outbox', 'payload_hash', false);
        $this->assertMaxLengthPreflight('withdraw_settlement_outbox', 'provider_reference', 100);
        $this->assertMaxLengthPreflight('withdraw_settlement_outbox', 'last_error_code', 100);
        $this->assertUnsignedIntegerPreflight(
            'withdraw_settlement_outbox',
            'withdraw_record_id',
            '18446744073709551615'
        );

        if (
            Schema::hasColumn('withdraw_settlement_outbox', 'event_type')
            && Schema::hasColumn('withdraw_settlement_outbox', 'withdraw_record_id')
        ) {
            $duplicateEvent = DB::table('withdraw_settlement_outbox')
                ->select('event_type', 'withdraw_record_id', DB::raw('COUNT(*) AS duplicate_count'))
                ->groupBy('event_type', 'withdraw_record_id')
                ->havingRaw('COUNT(*) > 1')
                ->first();
            if ($duplicateEvent) {
                $this->throwPreflightFailure(
                    'withdraw_settlement_outbox',
                    'event_type,withdraw_record_id unique contract'
                );
            }
        }
    }

    private function assertDecimalPreflight(
        string $table,
        string $column,
        int $integerDigits,
        int $scale
    ): void {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        $value = "TRIM(CAST({$column} AS CHAR))";
        $decimal = "CAST({$column} AS DECIMAL(65,30))";
        $limit = '1' . str_repeat('0', $integerDigits);
        $invalid = DB::table($table)
            ->whereNotNull($column)
            ->whereRaw(
                "({$value} = '' OR {$value} NOT REGEXP '^[+-]?[0-9]+([.][0-9]+)?$' "
                . "OR ABS({$decimal}) >= ? OR {$decimal} <> ROUND({$decimal}, ?))",
                [$limit, $scale]
            )
            ->exists();
        if ($invalid) {
            $this->throwPreflightFailure(
                $table,
                "{$column} exceeds DECIMAL(18,{$scale}) precision or scale"
            );
        }
    }

    private function assertMaxLengthPreflight(
        string $table,
        string $column,
        int $maxLength,
        bool $ignoreBlank = false
    ): void {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        $query = DB::table($table)
            ->whereNotNull($column)
            ->whereRaw("CHAR_LENGTH({$column}) > ?", [$maxLength]);
        if ($ignoreBlank) {
            $query->whereRaw("TRIM({$column}) <> ''");
        }
        if ($query->exists()) {
            $this->throwPreflightFailure($table, "{$column} exceeds VARCHAR({$maxLength})");
        }
    }

    private function assertHashPreflight(string $table, string $column, bool $nullable): void
    {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        $query = DB::table($table);
        if ($nullable) {
            $query->whereNotNull($column);
        }
        if ($query->whereRaw("{$column} NOT REGEXP '^[0-9A-Fa-f]{64}$'")->exists()) {
            $this->throwPreflightFailure($table, "{$column} must be a 64-character hexadecimal hash");
        }
    }

    private function assertUnsignedIntegerPreflight(
        string $table,
        string $column,
        string $maximum
    ): void {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        $value = "TRIM(CAST({$column} AS CHAR))";
        $integer = "CAST({$column} AS DECIMAL(65,0))";
        $invalid = DB::table($table)
            ->whereNotNull($column)
            ->whereRaw(
                "({$value} NOT REGEXP '^[+]?[0-9]+$' OR {$integer} < 0 OR {$integer} > ?)",
                [$maximum]
            )
            ->exists();
        if ($invalid) {
            $this->throwPreflightFailure($table, "{$column} exceeds unsigned integer range");
        }
    }

    private function throwPreflightFailure(string $table, string $contract): void
    {
        throw new RuntimeException("Migration preflight failed for {$table}: {$contract}.");
    }

    private function addMissingWithdrawColumns(): void
    {
        $columns = [
            'idempotency_key' => function (Blueprint $table): void {
                $table->string('idempotency_key', 100)->nullable();
            },
            'funding_status' => function (Blueprint $table): void {
                $table->string('funding_status', 30)->nullable();
            },
            'funding_payload_hash' => function (Blueprint $table): void {
                $table->char('funding_payload_hash', 64)->nullable();
            },
            'refund_mt4_ticket' => function (Blueprint $table): void {
                $table->unsignedBigInteger('refund_mt4_ticket')->nullable();
            },
            'refund_time' => function (Blueprint $table): void {
                $table->dateTime('refund_time')->nullable();
            },
            'funding_error_code' => function (Blueprint $table): void {
                $table->string('funding_error_code', 100)->nullable();
            },
        ];

        $missing = array_filter(
            $columns,
            static function (callable $definition, string $column): bool {
                return !Schema::hasColumn('withdraw_records', $column);
            },
            ARRAY_FILTER_USE_BOTH
        );
        if ($missing === []) {
            return;
        }

        Schema::table('withdraw_records', function (Blueprint $table) use ($missing): void {
            foreach ($missing as $definition) {
                $definition($table);
            }
        });
    }

    private function normalizeWithdrawRows(): void
    {
        foreach (['apply_amount', 'actual_amount', 'fee', 'exchange_rate', 'rmb_fee'] as $column) {
            DB::table('withdraw_records')->whereNull($column)->update([$column => 0]);
        }
        DB::table('withdraw_records')
            ->whereNull('funding_status')
            ->orWhereRaw("TRIM(funding_status) = ''")
            ->update(['funding_status' => 'unknown']);
        $this->normalizeOrphanPendingWithdrawRows();

        DB::transaction(function (): void {
            DB::table('withdraw_records')
                ->whereNotNull('local_order_no')
                ->whereRaw("TRIM(local_order_no) <> ''")
                ->whereRaw('local_order_no <> TRIM(local_order_no)')
                ->update(['local_order_no' => DB::raw('TRIM(local_order_no)')]);
            DB::table('withdraw_records')
                ->whereNull('local_order_no')
                ->orWhereRaw("TRIM(local_order_no) = ''")
                ->update(['local_order_no' => DB::raw("CONCAT('LEGACY-WDR-', id)")]);
        });
    }

    private function normalizeOrphanPendingWithdrawRows(): void
    {
        if (
            !Schema::hasTable('withdraw_records')
            || !Schema::hasTable('withdraw_settlement_outbox')
            || !Schema::hasColumn('withdraw_settlement_outbox', 'withdraw_record_id')
            || !Schema::hasColumn('withdraw_settlement_outbox', 'event_type')
        ) {
            return;
        }

        DB::table('withdraw_records')
            ->where('funding_status', 'pending')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('withdraw_settlement_outbox as funding_outbox')
                    ->whereColumn('funding_outbox.withdraw_record_id', 'withdraw_records.id')
                    ->where('funding_outbox.event_type', 'withdraw_debit');
            })
            ->update(['funding_status' => 'unknown']);
    }

    private function hardenWithdrawColumns(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE withdraw_records '
            . 'ENGINE=InnoDB, '
            . 'MODIFY apply_amount DECIMAL(18,2) NOT NULL, '
            . 'MODIFY actual_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00, '
            . 'MODIFY fee DECIMAL(18,2) NOT NULL DEFAULT 0.00, '
            . 'MODIFY exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 0.00000000, '
            . 'MODIFY rmb_fee DECIMAL(18,2) NOT NULL DEFAULT 0.00, '
            . 'MODIFY local_order_no VARCHAR(200) NOT NULL, '
            . 'MODIFY idempotency_key VARCHAR(100) NULL, '
            . "MODIFY funding_status VARCHAR(30) NOT NULL DEFAULT 'pending', "
            . 'MODIFY funding_payload_hash CHAR(64) NULL, '
            . 'MODIFY refund_mt4_ticket BIGINT UNSIGNED NULL, '
            . 'MODIFY refund_time DATETIME NULL, '
            . 'MODIFY funding_error_code VARCHAR(100) NULL'
        );
    }

    private function ensureWithdrawIndexes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->ensureIndex(
            'withdraw_records',
            'withdraw_records_local_order_no_unique',
            ['local_order_no'],
            true,
            function (): void {
                $duplicate = DB::table('withdraw_records')
                    ->select('local_order_no', DB::raw('COUNT(*) AS aggregate'))
                    ->groupBy('local_order_no')
                    ->havingRaw('COUNT(*) > 1')
                    ->first();
                if ($duplicate) {
                    throw new RuntimeException('Cannot add withdraw local order unique index: duplicate values exist.');
                }
            }
        );
        $this->ensureIndex(
            'withdraw_records',
            'withdraw_records_idempotency_user_unique',
            ['idempotency_key', 'user_id'],
            true,
            function (): void {
                $duplicate = DB::table('withdraw_records')
                    ->select('idempotency_key', 'user_id', DB::raw('COUNT(*) AS aggregate'))
                    ->whereNotNull('idempotency_key')
                    ->groupBy('idempotency_key', 'user_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->first();
                if ($duplicate) {
                    throw new RuntimeException('Cannot add withdraw idempotency unique index: duplicate user keys exist.');
                }
            }
        );
    }

    private function createOutbox(): void
    {
        Schema::create('withdraw_settlement_outbox', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('withdraw_record_id');
            $table->string('local_order_no', 200);
            $table->string('event_type', 50);
            $table->string('status', 30)->default('pending');
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

    private function addMissingOutboxColumns(): void
    {
        $columns = [
            'withdraw_record_id' => function (Blueprint $table): void {
                $table->unsignedBigInteger('withdraw_record_id');
            },
            'local_order_no' => function (Blueprint $table): void {
                $table->string('local_order_no', 200);
            },
            'event_type' => function (Blueprint $table): void {
                $table->string('event_type', 50);
            },
            'status' => function (Blueprint $table): void {
                $table->string('status', 30)->default('pending');
            },
            'attempts' => function (Blueprint $table): void {
                $table->unsignedInteger('attempts')->default(0);
            },
            'payload_hash' => function (Blueprint $table): void {
                $table->char('payload_hash', 64);
            },
            'available_at' => function (Blueprint $table): void {
                $table->unsignedInteger('available_at')->nullable();
            },
            'locked_at' => function (Blueprint $table): void {
                $table->unsignedInteger('locked_at')->nullable();
            },
            'processed_at' => function (Blueprint $table): void {
                $table->unsignedInteger('processed_at')->nullable();
            },
            'provider_reference' => function (Blueprint $table): void {
                $table->string('provider_reference', 100)->nullable();
            },
            'last_error_code' => function (Blueprint $table): void {
                $table->string('last_error_code', 100)->nullable();
            },
            'created_at' => function (Blueprint $table): void {
                $table->unsignedInteger('created_at')->nullable();
            },
            'updated_at' => function (Blueprint $table): void {
                $table->unsignedInteger('updated_at')->nullable();
            },
            'deleted_at' => function (Blueprint $table): void {
                $table->unsignedInteger('deleted_at')->nullable();
            },
        ];

        $missing = array_filter(
            $columns,
            static function (callable $definition, string $column): bool {
                return !Schema::hasColumn('withdraw_settlement_outbox', $column);
            },
            ARRAY_FILTER_USE_BOTH
        );
        if ($missing === []) {
            return;
        }

        Schema::table('withdraw_settlement_outbox', function (Blueprint $table) use ($missing): void {
            foreach ($missing as $definition) {
                $definition($table);
            }
        });
    }

    private function normalizeOutboxRows(): void
    {
        if (!Schema::hasTable('withdraw_settlement_outbox')) {
            return;
        }
        DB::table('withdraw_settlement_outbox')->whereNull('attempts')->update(['attempts' => 0]);
        DB::table('withdraw_settlement_outbox')
            ->whereNull('status')
            ->orWhereRaw("TRIM(status) = ''")
            ->update(['status' => 'pending']);

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('withdraw_records')) {
            DB::statement(
                "UPDATE withdraw_settlement_outbox AS outbox "
                . 'INNER JOIN withdraw_records AS withdrawal ON withdrawal.id = outbox.withdraw_record_id '
                . 'SET outbox.local_order_no = withdrawal.local_order_no '
                . "WHERE outbox.local_order_no IS NULL OR TRIM(outbox.local_order_no) = ''"
            );
        }

        $invalid = DB::table('withdraw_settlement_outbox')
            ->whereNull('withdraw_record_id')
            ->orWhere('withdraw_record_id', '<=', 0)
            ->orWhereNull('event_type')
            ->orWhereRaw("TRIM(event_type) = ''")
            ->orWhereNull('local_order_no')
            ->orWhereRaw("TRIM(local_order_no) = ''")
            ->orWhereNull('payload_hash')
            ->orWhereRaw("TRIM(payload_hash) = ''")
            ->exists();
        if ($invalid) {
            throw new RuntimeException('Cannot harden withdraw settlement outbox: required identity data is missing.');
        }
    }

    private function hardenOutboxColumns(): void
    {
        if (!Schema::hasTable('withdraw_settlement_outbox') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE withdraw_settlement_outbox '
            . 'ENGINE=InnoDB, '
            . 'MODIFY withdraw_record_id BIGINT UNSIGNED NOT NULL, '
            . 'MODIFY local_order_no VARCHAR(200) NOT NULL, '
            . 'MODIFY event_type VARCHAR(50) NOT NULL, '
            . "MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending', "
            . 'MODIFY attempts INT UNSIGNED NOT NULL DEFAULT 0, '
            . 'MODIFY payload_hash CHAR(64) NOT NULL, '
            . 'MODIFY available_at INT UNSIGNED NULL, '
            . 'MODIFY locked_at INT UNSIGNED NULL, '
            . 'MODIFY processed_at INT UNSIGNED NULL, '
            . 'MODIFY provider_reference VARCHAR(100) NULL, '
            . 'MODIFY last_error_code VARCHAR(100) NULL, '
            . 'MODIFY created_at INT UNSIGNED NULL, '
            . 'MODIFY updated_at INT UNSIGNED NULL, '
            . 'MODIFY deleted_at INT UNSIGNED NULL'
        );
    }

    private function ensureOutboxIndexes(): void
    {
        if (!Schema::hasTable('withdraw_settlement_outbox') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->ensureIndex(
            'withdraw_settlement_outbox',
            'withdraw_settlement_outbox_event_withdraw_unique',
            ['event_type', 'withdraw_record_id'],
            true,
            function (): void {
                $duplicate = DB::table('withdraw_settlement_outbox')
                    ->select('event_type', 'withdraw_record_id', DB::raw('COUNT(*) AS aggregate'))
                    ->groupBy('event_type', 'withdraw_record_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->first();
                if ($duplicate) {
                    throw new RuntimeException('Cannot add withdraw outbox event unique index: duplicate events exist.');
                }
            }
        );
        $this->ensureIndex(
            'withdraw_settlement_outbox',
            'withdraw_settlement_outbox_ready_index',
            ['status', 'available_at'],
            false
        );
        $this->ensureIndex(
            'withdraw_settlement_outbox',
            'withdraw_settlement_outbox_order_index',
            ['local_order_no'],
            false
        );
    }

    private function ensureSafeOutboxPrimaryKey(bool $applyRepair = true): void
    {
        if (!Schema::hasTable('withdraw_settlement_outbox')) {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Cannot repair withdraw settlement outbox id outside MySQL.');
        }

        $id = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'withdraw_settlement_outbox')
            ->where('COLUMN_NAME', 'id')
            ->first();
        $primary = collect(DB::select('SHOW INDEX FROM withdraw_settlement_outbox'))
            ->where('Key_name', 'PRIMARY')
            ->sortBy('Seq_in_index');
        $valid = $id !== null
            && strtolower((string) $id->DATA_TYPE) === 'bigint'
            && strpos(strtolower((string) $id->COLUMN_TYPE), 'unsigned') !== false
            && strtoupper((string) $id->IS_NULLABLE) === 'NO'
            && strpos(strtolower((string) $id->EXTRA), 'auto_increment') !== false
            && $primary->pluck('Column_name')->values()->all() === ['id'];
        if ($valid) {
            return;
        }

        if (DB::table('withdraw_settlement_outbox')->limit(1)->exists()) {
            throw new RuntimeException('Cannot safely repair withdraw settlement outbox id contract: table is nonempty.');
        }
        $otherAutoIncrement = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'withdraw_settlement_outbox')
            ->where('COLUMN_NAME', '<>', 'id')
            ->where('EXTRA', 'like', '%auto_increment%')
            ->exists();
        if ($otherAutoIncrement) {
            throw new RuntimeException('Cannot safely repair withdraw settlement outbox id: another auto-increment column exists.');
        }

        if (!$applyRepair) {
            return;
        }

        if ($id === null) {
            $dropPrimary = $primary->isNotEmpty() ? 'DROP PRIMARY KEY, ' : '';
            DB::statement(
                'ALTER TABLE withdraw_settlement_outbox '
                . $dropPrimary
                . 'ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
            );

            return;
        }

        $dropPrimary = $primary->isNotEmpty() ? 'DROP PRIMARY KEY, ' : '';
        DB::statement(
            'ALTER TABLE withdraw_settlement_outbox '
            . $dropPrimary
            . 'MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)'
        );
    }

    /**
     * @param array<int, string> $columns
     */
    private function ensureIndex(
        string $table,
        string $name,
        array $columns,
        bool $unique,
        callable $beforeCreate = null
    ): void {
        $definition = collect(DB::select("SHOW INDEX FROM {$table}"))
            ->where('Key_name', $name)
            ->sortBy('Seq_in_index');
        $expectedNonUnique = $unique ? 0 : 1;
        $requiredIsValid = $definition->isNotEmpty()
            && $definition->pluck('Column_name')->values()->all() === $columns
            && (int) $definition->first()->Non_unique === $expectedNonUnique
            && $definition->every(static function ($row): bool {
                return $row->Sub_part === null;
            });
        if (!$requiredIsValid && $definition->isNotEmpty()) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$name}");
        }

        if (!$requiredIsValid) {
            $equivalents = $this->equivalentIndexes($table, $columns, $expectedNonUnique);
            foreach ($equivalents as $rows) {
                $ordered = collect($rows)->sortBy('Seq_in_index');
                if (!$ordered->every(static function ($row): bool {
                    return $row->Sub_part === null;
                })) {
                    continue;
                }
                $oldName = (string) $ordered->first()->Key_name;
                DB::statement("ALTER TABLE {$table} RENAME INDEX {$oldName} TO {$name}");
                $requiredIsValid = true;
                break;
            }
        }

        if (!$requiredIsValid) {
            foreach ($this->equivalentIndexes($table, $columns, $expectedNonUnique) as $rows) {
                $oldName = (string) collect($rows)->first()->Key_name;
                DB::statement("ALTER TABLE {$table} DROP INDEX {$oldName}");
            }
            if ($beforeCreate !== null) {
                $beforeCreate();
            }
            $keyword = $unique ? 'UNIQUE INDEX' : 'INDEX';
            DB::statement(
                "ALTER TABLE {$table} ADD {$keyword} {$name} (" . implode(', ', $columns) . ')'
            );
        }

        foreach ($this->equivalentIndexes($table, $columns, $expectedNonUnique) as $indexName => $rows) {
            if ((string) $indexName !== $name) {
                DB::statement("ALTER TABLE {$table} DROP INDEX {$indexName}");
            }
        }
    }

    private function equivalentIndexes(string $table, array $columns, int $expectedNonUnique)
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->where('Key_name', '<>', 'PRIMARY')
            ->groupBy('Key_name')
            ->filter(function ($rows) use ($columns, $expectedNonUnique): bool {
                $ordered = collect($rows)->sortBy('Seq_in_index');

                return $ordered->pluck('Column_name')->values()->all() === $columns
                    && (int) $ordered->first()->Non_unique === $expectedNonUnique;
            });
    }
}
