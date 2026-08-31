<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 创建支付结算 Outbox 表 payment_settlement_outbox。
 *
 * 文件功能：
 * - 支付结算出站消息表（事务性 Outbox）：消息类型、载荷、状态、重试与幂等键。
 *
 * 字段语义：
 * - aggregate_type/aggregate_id 聚合类型与 ID；message_type 消息类型；
 * - payload 消息载荷（JSON）；status 状态（pending/sent/failed 等）；
 * - idempotency_key 幂等键（唯一）；attempts 重试次数；next_retry_at 下次重试时间；
 * - processed_at 处理时间；last_error 最近错误。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePaymentSettlementOutbox extends Migration
{
    public function up()
    {
        $this->assertNonemptyExistingTableContract();
        $this->ensureSafePrimaryKey();

        if (Schema::hasTable('deposit_records')) {
            if (!Schema::hasColumn('deposit_records', 'merchant_id')) {
                Schema::table('deposit_records', function (Blueprint $table) {
                    $table->string('merchant_id', 100)->nullable()->after('gateway_code');
                });
            }
            if (!Schema::hasColumn('deposit_records', 'provider_amount')) {
                Schema::table('deposit_records', function (Blueprint $table) {
                    $table->decimal('provider_amount', 18, 2)->nullable()->after('actual_amount');
                });
            }
            DB::table('deposit_records')->whereNull('provider_amount')->update([
                'provider_amount' => DB::raw("CASE WHEN UPPER(currency) IN ('USD','USDT') THEN amount ELSE actual_amount END"),
            ]);
        }

        if (!Schema::hasTable('payment_settlement_outbox')) {
            Schema::create('payment_settlement_outbox', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('deposit_record_id');
                $table->string('local_order_no', 100);
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
                $table->unique(['event_type', 'deposit_record_id'], 'payment_settlement_outbox_event_deposit_unique');
                $table->index(['status', 'available_at'], 'payment_settlement_outbox_ready_index');
                $table->index('local_order_no', 'payment_settlement_outbox_order_index');
            });
        }
        $this->ensureInnoDbEngine();
        if (Schema::hasTable('payment_settlement_outbox')) {
            $columns = [
                'deposit_record_id' => fn (Blueprint $table) => $table->unsignedBigInteger('deposit_record_id'),
                'local_order_no' => fn (Blueprint $table) => $table->string('local_order_no', 100),
                'event_type' => fn (Blueprint $table) => $table->string('event_type', 50),
                'status' => fn (Blueprint $table) => $table->string('status', 30)->default('pending'),
                'attempts' => fn (Blueprint $table) => $table->unsignedInteger('attempts')->default(0),
                'payload_hash' => fn (Blueprint $table) => $table->char('payload_hash', 64),
                'available_at' => fn (Blueprint $table) => $table->unsignedInteger('available_at')->nullable(),
                'locked_at' => fn (Blueprint $table) => $table->unsignedInteger('locked_at')->nullable(),
                'processed_at' => fn (Blueprint $table) => $table->unsignedInteger('processed_at')->nullable(),
                'provider_reference' => fn (Blueprint $table) => $table->string('provider_reference', 100)->nullable(),
                'last_error_code' => fn (Blueprint $table) => $table->string('last_error_code', 100)->nullable(),
                'created_at' => fn (Blueprint $table) => $table->unsignedInteger('created_at')->nullable(),
                'updated_at' => fn (Blueprint $table) => $table->unsignedInteger('updated_at')->nullable(),
                'deleted_at' => fn (Blueprint $table) => $table->unsignedInteger('deleted_at')->nullable(),
            ];
            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn('payment_settlement_outbox', $column)) {
                    Schema::table('payment_settlement_outbox', $definition);
                }
            }
            $this->ensureIndexes();
        }
        if (Schema::hasTable('payment_settlement_outbox') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payment_settlement_outbox MODIFY available_at INT UNSIGNED NULL');
            DB::statement('ALTER TABLE payment_settlement_outbox MODIFY locked_at INT UNSIGNED NULL');
            DB::statement('ALTER TABLE payment_settlement_outbox MODIFY processed_at INT UNSIGNED NULL');
        }
    }

    private function assertNonemptyExistingTableContract(): void
    {
        if (!Schema::hasTable('payment_settlement_outbox')
            || DB::getDriverName() !== 'mysql'
            || !DB::table('payment_settlement_outbox')->limit(1)->exists()) {
            return;
        }

        $id = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'payment_settlement_outbox')
            ->where('COLUMN_NAME', 'id')
            ->first();
        $primaryColumns = collect(DB::select('SHOW INDEX FROM payment_settlement_outbox'))
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
            throw new RuntimeException('Cannot safely repair payment settlement outbox id contract: table is nonempty.');
        }

        $requiredColumns = [
            'deposit_record_id', 'local_order_no', 'event_type', 'status', 'attempts',
            'payload_hash', 'available_at', 'locked_at', 'processed_at',
            'provider_reference', 'last_error_code', 'created_at', 'updated_at', 'deleted_at',
        ];
        $missingColumns = array_values(array_filter($requiredColumns, static function (string $column): bool {
            return !Schema::hasColumn('payment_settlement_outbox', $column);
        }));
        if ($missingColumns !== []) {
            throw new RuntimeException(
                'Cannot safely repair nonempty payment settlement outbox required columns: '
                . implode(', ', $missingColumns)
            );
        }
    }

    private function ensureInnoDbEngine(): void
    {
        if (!Schema::hasTable('payment_settlement_outbox') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $engine = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'payment_settlement_outbox')
            ->value('ENGINE');
        if (strcasecmp((string) $engine, 'InnoDB') !== 0) {
            DB::statement('ALTER TABLE payment_settlement_outbox ENGINE=InnoDB');
        }
    }

    private function ensureIndexes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $required = [
            'payment_settlement_outbox_event_deposit_unique' => [['event_type', 'deposit_record_id'], 0],
            'payment_settlement_outbox_ready_index' => [['status', 'available_at'], 1],
            'payment_settlement_outbox_order_index' => [['local_order_no'], 1],
        ];
        foreach ($required as $name => [$columns, $nonUnique]) {
            $definition = collect(DB::select('SHOW INDEX FROM payment_settlement_outbox'))
                ->where('Key_name', $name)->sortBy('Seq_in_index');
            if ($definition->pluck('Column_name')->values()->all() === $columns
                && ($definition->isEmpty() || (int) $definition->first()->Non_unique === $nonUnique)) {
                continue;
            }
            if ($name === 'payment_settlement_outbox_event_deposit_unique') {
                $this->assertNoDuplicateEventDepositKeys();
            }
            if ($definition->isNotEmpty()) {
                DB::statement("ALTER TABLE payment_settlement_outbox DROP INDEX {$name}");
            }
            if ($name === 'payment_settlement_outbox_event_deposit_unique') {
                DB::statement('ALTER TABLE payment_settlement_outbox ADD UNIQUE INDEX payment_settlement_outbox_event_deposit_unique (event_type, deposit_record_id)');
            } elseif ($name === 'payment_settlement_outbox_ready_index') {
                DB::statement('ALTER TABLE payment_settlement_outbox ADD INDEX payment_settlement_outbox_ready_index (status, available_at)');
            } else {
                DB::statement('ALTER TABLE payment_settlement_outbox ADD INDEX payment_settlement_outbox_order_index (local_order_no)');
            }
        }
    }

    private function ensureSafePrimaryKey(): void
    {
        if (!Schema::hasTable('payment_settlement_outbox')) {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Cannot repair payment settlement outbox id outside MySQL.');
        }
        $id = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'payment_settlement_outbox')
            ->where('COLUMN_NAME', 'id')->first();
        $primary = collect(DB::select('SHOW INDEX FROM payment_settlement_outbox'))
            ->where('Key_name', 'PRIMARY')->sortBy('Seq_in_index');
        $primaryColumns = $primary->pluck('Column_name')->values()->all();
        $valid = $id !== null
            && strtolower((string) $id->DATA_TYPE) === 'bigint'
            && strpos(strtolower((string) $id->COLUMN_TYPE), 'unsigned') !== false
            && strtoupper((string) $id->IS_NULLABLE) === 'NO'
            && strpos(strtolower((string) $id->EXTRA), 'auto_increment') !== false
            && $primaryColumns === ['id'];
        if ($valid) {
            return;
        }
        $hasRows = DB::table('payment_settlement_outbox')->limit(1)->exists();
        if ($hasRows) {
            throw new RuntimeException('Cannot safely repair payment settlement outbox id contract: table is nonempty.');
        }
        $otherAutoIncrement = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'payment_settlement_outbox')
            ->where('COLUMN_NAME', '<>', 'id')
            ->where('EXTRA', 'like', '%auto_increment%')->exists();
        if ($otherAutoIncrement) {
            throw new RuntimeException('Cannot safely repair payment settlement outbox id contract: another auto-increment column exists.');
        }
        if ($id === null) {
            if ($primary->isNotEmpty()) {
                throw new RuntimeException('Cannot safely add payment settlement outbox id: another primary key exists.');
            }
            DB::statement('ALTER TABLE payment_settlement_outbox ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');

            return;
        }
        $dropPrimary = $primary->isNotEmpty() ? 'DROP PRIMARY KEY, ' : '';
        DB::statement('ALTER TABLE payment_settlement_outbox '
            . $dropPrimary
            . 'MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)');
    }

    private function assertNoDuplicateEventDepositKeys(): void
    {
        $duplicate = DB::table('payment_settlement_outbox')
            ->select('event_type', 'deposit_record_id', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('event_type', 'deposit_record_id')->havingRaw('COUNT(*) > 1')->first();
        if ($duplicate) {
            throw new RuntimeException('Cannot add payment settlement outbox unique index: duplicate event/deposit keys exist.');
        }
    }

    public function down()
    {
        // Intentionally irreversible: payment identity snapshots and outbox events are audit data.
    }
}
