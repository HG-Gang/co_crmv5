<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:36
 */

/**
 * 加固充值支付订单：精度、幂等键与本地单号唯一。
 *
 * 文件功能：
 * - 为 deposit_records 补充 idempotency_key/gateway_code/currency/payment_status 等字段；
 * - 规范化金额精度（两位小数），并增加 (local_order_no) 与 (idempotency_key, user_id, gateway_code) 唯一索引。
 *
 * 字段语义：
 * - 幂等键保证同一渠道同一用户重复回调不重复入账；回滚保留加固后的结构（资金安全不可逆）。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenDepositPaymentOrders extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('deposit_records')) {
            return;
        }

        $this->addMissingColumns();
        $this->hardenMySqlColumns();
        $this->normalizeLocalOrderNumbers();

        if (!$this->hasUniqueColumns('deposit_records', ['local_order_no'])) {
            Schema::table('deposit_records', function (Blueprint $table) {
                $table->unique('local_order_no', 'deposit_records_local_order_no_unique');
            });
        }
        if (!$this->hasUniqueColumns('deposit_records', ['idempotency_key', 'user_id', 'gateway_code'])) {
            Schema::table('deposit_records', function (Blueprint $table) {
                $table->unique(
                    ['idempotency_key', 'user_id', 'gateway_code'],
                    'deposit_records_idempotency_user_gateway_unique'
                );
            });
        }
    }

    public function down()
    {
        // Payment precision and idempotency are irreversible safety guarantees.
        // Keep the hardened schema to avoid deleting live order metadata.
    }

    private function addMissingColumns(): void
    {
        $columns = [
            'idempotency_key' => function (Blueprint $table) {
                $table->string('idempotency_key', 100)->nullable()->after('local_order_no');
            },
            'gateway_code' => function (Blueprint $table) {
                $table->string('gateway_code', 50)->nullable()->after('idempotency_key');
            },
            'currency' => function (Blueprint $table) {
                $table->string('currency', 10)->nullable()->after('gateway_code');
            },
            'payment_status' => function (Blueprint $table) {
                $table->string('payment_status', 30)->nullable()->after('currency');
            },
            'settlement_status' => function (Blueprint $table) {
                $table->string('settlement_status', 30)->nullable()->after('payment_status');
            },
            'provider_payload_hash' => function (Blueprint $table) {
                $table->char('provider_payload_hash', 64)->nullable()->after('settlement_status');
            },
        ];

        foreach ($columns as $column => $definition) {
            if (!Schema::hasColumn('deposit_records', $column)) {
                Schema::table('deposit_records', $definition);
            }
        }
    }

    private function hardenMySqlColumns(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $engine = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'deposit_records')
            ->value('ENGINE');
        if (strcasecmp((string) $engine, 'InnoDB') !== 0) {
            DB::statement('ALTER TABLE deposit_records ENGINE=InnoDB');
        }

        $this->modifyMySqlColumnIfNeeded('amount', 'decimal(18,2)', false, null);
        $this->modifyMySqlColumnIfNeeded('actual_amount', 'decimal(18,2)', false, '0.00');
        $this->modifyMySqlColumnIfNeeded('exchange_rate', 'decimal(18,8)', false, '0.00000000');
        $this->modifyMySqlColumnIfNeeded('idempotency_key', 'varchar(100)', true, null);
        $this->modifyMySqlColumnIfNeeded('gateway_code', 'varchar(50)', true, null);
        $this->modifyMySqlColumnIfNeeded('currency', 'varchar(10)', true, null);
        $this->modifyMySqlColumnIfNeeded('payment_status', 'varchar(30)', true, null);
        $this->modifyMySqlColumnIfNeeded('settlement_status', 'varchar(30)', true, null);
        $this->modifyMySqlColumnIfNeeded('provider_payload_hash', 'char(64)', true, null);
    }

    private function modifyMySqlColumnIfNeeded(
        string $column,
        string $type,
        bool $nullable,
        ?string $default
    ): void {
        $metadata = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'deposit_records')
            ->where('COLUMN_NAME', $column)
            ->first();
        if ($metadata === null) {
            throw new \RuntimeException('Missing deposit_records.' . $column . ' after schema hardening.');
        }

        $matches = strtolower((string) $metadata->COLUMN_TYPE) === $type
            && ((string) $metadata->IS_NULLABLE === 'YES') === $nullable
            && ($metadata->COLUMN_DEFAULT === null ? null : (string) $metadata->COLUMN_DEFAULT) === $default;
        if ($matches) {
            return;
        }

        $definition = strtoupper($type);
        if ($metadata->CHARACTER_SET_NAME !== null && $metadata->COLLATION_NAME !== null) {
            $definition .= ' CHARACTER SET ' . $metadata->CHARACTER_SET_NAME
                . ' COLLATE ' . $metadata->COLLATION_NAME;
        }
        $definition .= $nullable ? ' NULL' : ' NOT NULL';
        if ($default !== null) {
            $definition .= ' DEFAULT ' . $default;
        }
        $comment = str_replace("'", "''", (string) $metadata->COLUMN_COMMENT);

        DB::statement(
            'ALTER TABLE `deposit_records` MODIFY COLUMN `' . $column . '` '
            . $definition . " COMMENT '" . $comment . "'"
        );
    }

    private function normalizeLocalOrderNumbers(): void
    {
        $duplicate = DB::table('deposit_records')
            ->selectRaw('LOWER(TRIM(local_order_no)) AS normalized_order_no, COUNT(*) AS duplicate_count')
            ->whereNotNull('local_order_no')
            ->whereRaw("TRIM(local_order_no) <> ''")
            ->groupByRaw('LOWER(TRIM(local_order_no))')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        if ($duplicate) {
            throw new \RuntimeException(
                'deposit_records contains duplicate local_order_no: ' . $duplicate->normalized_order_no
            );
        }

        $existing = [];
        $candidates = [];
        DB::table('deposit_records')->orderBy('id')->select(['id', 'local_order_no'])->chunkById(500, function ($rows) use (&$existing, &$candidates) {
            foreach ($rows as $row) {
                $current = trim((string) $row->local_order_no);
                if ($current !== '') {
                    $existing[strtolower($current)] = true;
                    continue;
                }

                $candidate = 'LEGACY-DEP-' . $row->id;
                $candidates[(int) $row->id] = $candidate;
            }
        }, 'id');

        $generated = [];
        foreach ($candidates as $candidate) {
            $key = strtolower($candidate);
            if (isset($existing[$key]) || isset($generated[$key])) {
                throw new \RuntimeException(
                    'deposit_records generated local_order_no conflicts with an existing order: ' . $candidate
                );
            }
            $generated[$key] = true;
        }

        DB::transaction(function () use ($candidates) {
            foreach ($candidates as $id => $candidate) {
                DB::table('deposit_records')->where('id', $id)->update([
                    'local_order_no' => $candidate,
                ]);
            }
        });
    }

    /** @param array<int, string> $columns */
    private function hasUniqueColumns(string $table, array $columns): bool
    {
        switch (DB::getDriverName()) {
            case 'mysql':
                $indexes = DB::table('information_schema.STATISTICS')
                    ->where('TABLE_SCHEMA', DB::getDatabaseName())
                    ->where('TABLE_NAME', $table)
                    ->where('NON_UNIQUE', 0)
                    ->orderBy('SEQ_IN_INDEX')
                    ->get()
                    ->groupBy('INDEX_NAME');
                break;

            case 'sqlite':
                $indexes = collect();
                $tableName = str_replace("'", "''", $table);
                foreach (DB::select("PRAGMA index_list('{$tableName}')") as $index) {
                    if ((int) $index->unique !== 1) {
                        continue;
                    }
                    $indexName = str_replace("'", "''", (string) $index->name);
                    $indexes->put($index->name, collect(DB::select("PRAGMA index_info('{$indexName}')")));
                }
                break;

            case 'pgsql':
                $indexes = collect();
                foreach (DB::select(
                    'SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?',
                    [$table]
                ) as $index) {
                    if (stripos((string) $index->indexdef, ' UNIQUE INDEX ') === false
                        || !preg_match('/\((.+)\)/', (string) $index->indexdef, $matches)) {
                        continue;
                    }
                    $rows = collect(array_map(function ($column) {
                        return (object) ['COLUMN_NAME' => trim(preg_replace('/\s+(ASC|DESC)$/i', '', $column), ' "')];
                    }, explode(',', $matches[1])));
                    $indexes->put($index->indexname, $rows);
                }
                break;

            default:
                return false;
        }

        foreach ($indexes as $rows) {
            $actual = $rows->map(function ($row) {
                return (string) ($row->COLUMN_NAME ?? $row->name ?? '');
            })->values()->all();
            if ($actual === $columns) {
                return true;
            }
        }

        return false;
    }
}
