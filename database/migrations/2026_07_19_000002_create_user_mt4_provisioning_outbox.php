<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 创建用户 MT4 开户预配 Outbox 表 user_mt4_provisioning_outbox。
 *
 * 文件功能：
 * - 用户开户/资料变更的 MT4 同步出站消息表（事务性 Outbox）：消息载荷、状态与重试。
 *
 * 字段语义：
 * - user_id 用户 ID；action 动作（provision/reconcile 等）；payload 载荷（JSON）；
 * - status 状态（pending/sent/failed）；idempotency_key 幂等键（唯一）；
 * - attempts 重试次数；next_retry_at 下次重试时间；processed_at 处理时间；last_error 最近错误。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUserMt4ProvisioningOutbox extends Migration
{
    /**
     * 本迁移创建的出箱表名。up/down 与运行时断言共用一个常量，表名只在一处定义，
     * 避免迁移自检与实际建表名不一致导致重复建表或漏删。
     */
    private const TABLE = 'user_mt4_provisioning_outbox';

    public function up()
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_login_id');
                $table->unsignedBigInteger('user_info_id');
                $table->unsignedBigInteger('user_id');
                $table->string('status', 40)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedInteger('reconciliation_attempts')->default(0);
                $table->text('payload_ciphertext')->nullable();
                $table->char('payload_hash', 64)->nullable();
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

        $this->assertNonemptyExistingTableContract();
        $this->ensureSafePrimaryKey();
        $this->ensureColumns();
        $this->ensureColumnTypes();
        $this->ensureInnoDb();
        $this->ensureIndexes();
    }

    public function down()
    {
    }

    private function ensureColumns(): void
    {
        $columns = [
            'user_login_id', 'user_info_id', 'user_id', 'status', 'attempts',
            'reconciliation_attempts', 'payload_ciphertext', 'payload_hash',
            'available_at', 'locked_at', 'processed_at', 'provider_reference',
            'last_error_code', 'created_at', 'updated_at', 'deleted_at',
        ];
        foreach ($columns as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                continue;
            }
            Schema::table(self::TABLE, function (Blueprint $table) use ($column): void {
                switch ($column) {
                    case 'user_login_id':
                        $table->unsignedBigInteger($column)->nullable();
                        break;
                    case 'user_info_id':
                        $table->unsignedBigInteger($column)->nullable();
                        break;
                    case 'user_id':
                        $table->unsignedBigInteger($column)->nullable();
                        break;
                    case 'status':
                        $table->string($column, 40)->default('pending');
                        break;
                    case 'attempts':
                    case 'reconciliation_attempts':
                        $table->unsignedInteger($column)->default(0);
                        break;
                    case 'payload_ciphertext':
                        $table->text($column)->nullable();
                        break;
                    case 'payload_hash':
                        $table->char($column, 64)->nullable();
                        break;
                    case 'available_at':
                    case 'locked_at':
                    case 'processed_at':
                    case 'created_at':
                    case 'updated_at':
                    case 'deleted_at':
                        $table->unsignedInteger($column)->nullable();
                        break;
                    case 'provider_reference':
                    case 'last_error_code':
                        $table->string($column, 100)->nullable();
                        break;
                }
            });
        }
    }

    private function assertNonemptyExistingTableContract(): void
    {
        if (!Schema::hasTable(self::TABLE)
            || DB::getDriverName() !== 'mysql'
            || !DB::table(self::TABLE)->limit(1)->exists()) {
            return;
        }

        $required = [
            'id', 'user_login_id', 'user_info_id', 'user_id', 'status', 'attempts',
            'reconciliation_attempts', 'payload_ciphertext', 'payload_hash',
            'available_at', 'locked_at', 'processed_at', 'provider_reference',
            'last_error_code', 'created_at', 'updated_at', 'deleted_at',
        ];
        $missing = array_values(array_filter($required, static function (string $column): bool {
            return !Schema::hasColumn(self::TABLE, $column);
        }));
        if ($missing !== []) {
            throw new RuntimeException(
                'Cannot safely repair nonempty MT4 provisioning outbox; required columns are missing: '
                . implode(', ', $missing)
            );
        }

        $id = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('COLUMN_NAME', 'id')
            ->first();
        $primary = collect(DB::select('SHOW INDEX FROM ' . self::TABLE))
            ->where('Key_name', 'PRIMARY')
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
        if ($id === null
            || strtolower((string) $id->DATA_TYPE) !== 'bigint'
            || strpos(strtolower((string) $id->COLUMN_TYPE), 'unsigned') === false
            || strtoupper((string) $id->IS_NULLABLE) !== 'NO'
            || strpos(strtolower((string) $id->EXTRA), 'auto_increment') === false
            || $primary !== ['id']) {
            throw new RuntimeException(
                'Cannot safely repair nonempty MT4 provisioning outbox id contract.'
            );
        }

        $definitions = [
            'user_login_id' => 'BIGINT UNSIGNED NOT NULL',
            'user_info_id' => 'BIGINT UNSIGNED NOT NULL',
            'user_id' => 'BIGINT UNSIGNED NOT NULL',
            'status' => "VARCHAR(40) NOT NULL DEFAULT 'pending'",
            'attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'reconciliation_attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'payload_ciphertext' => 'TEXT NULL',
            'payload_hash' => 'CHAR(64) NULL',
            'available_at' => 'INT UNSIGNED NULL',
            'locked_at' => 'INT UNSIGNED NULL',
            'processed_at' => 'INT UNSIGNED NULL',
            'provider_reference' => 'VARCHAR(100) NULL',
            'last_error_code' => 'VARCHAR(100) NULL',
            'created_at' => 'INT UNSIGNED NULL',
            'updated_at' => 'INT UNSIGNED NULL',
            'deleted_at' => 'INT UNSIGNED NULL',
        ];
        foreach ($definitions as $column => $definition) {
            $current = DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', self::TABLE)
                ->where('COLUMN_NAME', $column)
                ->first();
            if ($current === null || !$this->columnMatches($current, $definition)) {
                throw new RuntimeException(
                    'Cannot safely repair nonempty MT4 provisioning outbox column: ' . $column
                );
            }
        }
    }

    private function ensureSafePrimaryKey(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $id = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('COLUMN_NAME', 'id')
            ->first();
        $primary = collect(DB::select('SHOW INDEX FROM ' . self::TABLE))
            ->where('Key_name', 'PRIMARY')
            ->sortBy('Seq_in_index');
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
        if (DB::table(self::TABLE)->limit(1)->exists()) {
            throw new RuntimeException(
                'Cannot safely repair MT4 provisioning outbox id contract: table is nonempty.'
            );
        }
        $otherAutoIncrement = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('COLUMN_NAME', '<>', 'id')
            ->where('EXTRA', 'like', '%auto_increment%')
            ->exists();
        if ($otherAutoIncrement) {
            throw new RuntimeException(
                'Cannot safely repair MT4 provisioning outbox id contract: another auto-increment column exists.'
            );
        }
        if ($id === null) {
            if ($primary->isNotEmpty()) {
                throw new RuntimeException(
                    'Cannot safely add MT4 provisioning outbox id: another primary key exists.'
                );
            }
            DB::statement(
                'ALTER TABLE ' . self::TABLE . ' ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
            );

            return;
        }

        $dropPrimary = $primary->isNotEmpty() ? 'DROP PRIMARY KEY, ' : '';
        DB::statement(
            'ALTER TABLE ' . self::TABLE . ' '
            . $dropPrimary
            . 'MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)'
        );
    }

    private function ensureColumnTypes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $definitions = [
            'user_login_id' => 'BIGINT UNSIGNED NOT NULL',
            'user_info_id' => 'BIGINT UNSIGNED NOT NULL',
            'user_id' => 'BIGINT UNSIGNED NOT NULL',
            'status' => "VARCHAR(40) NOT NULL DEFAULT 'pending'",
            'attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'reconciliation_attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'payload_ciphertext' => 'TEXT NULL',
            'payload_hash' => 'CHAR(64) NULL',
            'available_at' => 'INT UNSIGNED NULL',
            'locked_at' => 'INT UNSIGNED NULL',
            'processed_at' => 'INT UNSIGNED NULL',
            'provider_reference' => 'VARCHAR(100) NULL',
            'last_error_code' => 'VARCHAR(100) NULL',
            'created_at' => 'INT UNSIGNED NULL',
            'updated_at' => 'INT UNSIGNED NULL',
            'deleted_at' => 'INT UNSIGNED NULL',
        ];
        foreach ($definitions as $column => $definition) {
            $current = DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', self::TABLE)
                ->where('COLUMN_NAME', $column)
                ->first();
            if ($current === null) {
                throw new RuntimeException('Missing MT4 provisioning outbox column after repair: ' . $column);
            }
            if ($this->columnMatches($current, $definition)) {
                continue;
            }
            DB::statement(
                'ALTER TABLE ' . self::TABLE . ' MODIFY `' . $column . '` ' . $definition
            );
        }
    }

    private function columnMatches(object $column, string $definition): bool
    {
        $type = strtolower((string) $column->COLUMN_TYPE);
        $nullable = strtoupper((string) $column->IS_NULLABLE) === 'YES';
        $definition = strtolower($definition);
        $expectsNullable = strpos($definition, ' null') !== false
            && strpos($definition, ' not null') === false;
        $expectsUnsigned = strpos($definition, 'unsigned') !== false;
        $hasUnsigned = strpos($type, 'unsigned') !== false;
        if ($expectsNullable !== $nullable || $expectsUnsigned !== $hasUnsigned) {
            return false;
        }
        if (preg_match('/default\s+([^\s]+)/', $definition, $matches)) {
            $expectedDefault = trim((string) $matches[1], "'\"");
            if ((string) $column->COLUMN_DEFAULT !== $expectedDefault) {
                return false;
            }
        }

        $expectedType = preg_replace('/\s+(not null|null)\b.*$/', '', $definition);
        $expectedType = preg_replace('/\s+unsigned\b/', '', (string) $expectedType);
        $actualType = preg_replace('/\s+unsigned\b/', '', $type);
        $expectedType = $this->normalizeDisplayWidth(trim((string) $expectedType));
        $actualType = $this->normalizeDisplayWidth(trim((string) $actualType));

        return $expectedType === $actualType;
    }

    private function normalizeDisplayWidth(string $type): string
    {
        return (string) preg_replace(
            '/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/',
            '$1',
            strtolower($type)
        );
    }

    private function ensureInnoDb(): void
    {
        if (!Schema::hasTable(self::TABLE) || DB::getDriverName() !== 'mysql') {
            return;
        }

        $engine = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->value('ENGINE');
        if (strcasecmp((string) $engine, 'InnoDB') !== 0) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' ENGINE=InnoDB');
        }
    }

    private function ensureIndexes(): void
    {
        if (!Schema::hasTable(self::TABLE) || DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->assertNoDuplicateUserIds();
        $indexes = [
            'user_mt4_provisioning_user_unique' => [['user_id'], true],
            'user_mt4_provisioning_ready_index' => [['status', 'available_at'], false],
            'user_mt4_provisioning_stale_index' => [['status', 'locked_at'], false],
        ];

        foreach ($indexes as $name => [$columns, $unique]) {
            if ($this->indexMatches($name, $columns, (bool) $unique)) {
                continue;
            }
            if ($this->indexExists($name)) {
                DB::statement('ALTER TABLE ' . self::TABLE . ' DROP INDEX `' . $name . '`');
            }

            $columnSql = implode('`,`', $columns);
            $uniqueSql = $unique ? 'UNIQUE ' : '';
            DB::statement(
                'ALTER TABLE ' . self::TABLE . ' ADD ' . $uniqueSql . 'INDEX `' . $name . '` (`' . $columnSql . '`)' 
            );
        }
    }

    private function assertNoDuplicateUserIds(): void
    {
        $duplicate = DB::table(self::TABLE)
            ->select('user_id')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('user_id');
        if ($duplicate !== null) {
            throw new RuntimeException(
                'Cannot create unique MT4 provisioning user_id index; duplicate user_id: ' . $duplicate
            );
        }
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select('SHOW INDEX FROM ' . self::TABLE))
            ->contains(static function ($row) use ($name): bool {
                return (string) $row->Key_name === $name;
            });
    }

    /** @param array<int, string> $columns */
    private function indexMatches(string $name, array $columns, bool $unique): bool
    {
        $rows = collect(DB::select('SHOW INDEX FROM ' . self::TABLE))
            ->where('Key_name', $name)
            ->sortBy('Seq_in_index');
        if ($rows->isEmpty()) {
            return false;
        }

        return $rows->pluck('Column_name')->values()->all() === $columns
            && (int) $rows->first()->Non_unique === ($unique ? 0 : 1);
    }
}
