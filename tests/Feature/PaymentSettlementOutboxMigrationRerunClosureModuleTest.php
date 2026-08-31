<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:08
 */

/**
 * CreatePaymentSettlementOutbox 迁移重跑(幂等)行为
 * (PaymentSettlementOutboxMigrationRerunClosureModuleTest)的测试。
 *
 * 文件功能:
 * - 验证迁移在表已存在时重跑仍能:强制 InnoDB 引擎、回填 provider_amount 空值、
 *   恢复缺失或修复错误定义的必需索引、补齐 id 主键契约(bigint unsigned auto_increment)。
 * - 验证非空残缺表(缺 id 或 id 契约错误)重跑时在其它 DDL 之前失败关闭(RuntimeException),
 *   绝不静默改坏已有数据。
 *
 * 适用场景:该迁移在生产环境可能已执行过、表结构被手工改动或部分执行后,
 * 重跑迁移时的回归保护;任何调整迁移 DDL 顺序或校验逻辑后必须回归。
 *
 * 入参例子:直接调用迁移类 up(),测试通过 RENAME TABLE 临时换入残缺表结构、
 * 插入 fixture 数据(如 provider_amount 为 null 的 deposit_records)来构造重跑现场。
 *
 * 返回值:up() 无返回值;断言通过表示重跑后的引擎、索引、主键契约与预期一致,闭环成立。
 *
 * 失败场景:断言失败说明重跑逻辑破坏已有表结构或未按预期失败关闭,
 * 可能造成生产数据不一致;失败关闭用例中未抛 RuntimeException 同样视为失败。
 */

declare(strict_types=1);

namespace Tests\Feature;

use CreatePaymentSettlementOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PaymentSettlementOutboxMigrationRerunClosureModuleTest extends TestCase
{
    public function test_rerun_enforces_innodb_engine(): void
    {
        DB::statement('ALTER TABLE payment_settlement_outbox ENGINE=MyISAM');

        try {
            $this->migration()->up();

            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'payment_settlement_outbox')
                ->value('ENGINE');

            $this->assertSame('InnoDB', $engine);
        } finally {
            $this->migration()->up();
        }
    }

    public function test_rerun_backfills_null_provider_amount_when_column_already_exists(): void
    {
        $id = DB::table('deposit_records')->insertGetId([
            'user_id' => 99110001, 'user_name' => 'migration-rerun', 'mt4_ticket' => 0,
            'amount' => '12.34', 'actual_amount' => '88.88', 'exchange_rate' => '7.00000000',
            'channel_name' => 'fixture', 'local_order_no' => 'MIG-RERUN-PROVIDER-AMOUNT',
            'gateway_code' => 'fixture', 'currency' => 'USD', 'provider_amount' => null,
            'status' => '01', 'created_at' => time(), 'updated_at' => time(),
        ]);
        try {
            $this->migration()->up();
            $this->assertSame('12.34', (string) DB::table('deposit_records')->where('id', $id)->value('provider_amount'));
        } finally {
            DB::table('deposit_records')->where('id', $id)->delete();
        }
    }

    public function test_rerun_restores_missing_required_outbox_indexes(): void
    {
        $indexes = [
            'payment_settlement_outbox_event_deposit_unique',
            'payment_settlement_outbox_ready_index',
            'payment_settlement_outbox_order_index',
        ];
        try {
            foreach ($indexes as $index) {
                DB::statement("ALTER TABLE payment_settlement_outbox DROP INDEX {$index}");
            }
            $this->migration()->up();
            $actual = collect(DB::select('SHOW INDEX FROM payment_settlement_outbox'))->pluck('Key_name')->unique()->all();
            foreach ($indexes as $index) {
                $this->assertContains($index, $actual);
            }
        } finally {
            $this->migration()->up();
        }
    }

    public function test_rerun_repairs_wrong_required_index_definitions(): void
    {
        $this->dropRequiredIndexes();
        try {
            DB::statement('ALTER TABLE payment_settlement_outbox ADD INDEX payment_settlement_outbox_event_deposit_unique (deposit_record_id, event_type)');
            DB::statement('ALTER TABLE payment_settlement_outbox ADD INDEX payment_settlement_outbox_ready_index (available_at, status)');
            DB::statement('ALTER TABLE payment_settlement_outbox ADD INDEX payment_settlement_outbox_order_index (status)');

            $this->migration()->up();

            $this->assertSame(['event_type', 'deposit_record_id'], $this->indexColumns('payment_settlement_outbox_event_deposit_unique'));
            $this->assertSame(0, $this->indexNonUnique('payment_settlement_outbox_event_deposit_unique'));
            $this->assertSame(['status', 'available_at'], $this->indexColumns('payment_settlement_outbox_ready_index'));
            $this->assertSame(['local_order_no'], $this->indexColumns('payment_settlement_outbox_order_index'));
        } finally {
            $this->dropRequiredIndexes();
            $this->migration()->up();
        }
    }

    public function test_rerun_adds_id_primary_key_to_empty_partial_table(): void
    {
        $this->withPartialOutbox(function (): void {
            Schema::create('payment_settlement_outbox', function ($table): void {
                $table->string('event_type', 50);
            });

            $this->migration()->up();

            $column = DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'payment_settlement_outbox')
                ->where('COLUMN_NAME', 'id')->first();
            $this->assertSame('bigint', strtolower((string) $column->DATA_TYPE));
            $this->assertSame('PRI', $column->COLUMN_KEY);
            $this->assertStringContainsString('auto_increment', (string) $column->EXTRA);
        });
    }

    public function test_rerun_fails_before_other_ddl_when_nonempty_partial_table_has_no_id(): void
    {
        $this->withPartialOutbox(function (): void {
            Schema::create('payment_settlement_outbox', function ($table): void {
                $table->string('event_type', 50);
            });
            DB::table('payment_settlement_outbox')->insert(['event_type' => 'existing']);

            try {
                $this->migration()->up();
                $this->fail('Nonempty outbox without id must fail closed.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('id', strtolower($exception->getMessage()));
            }
            $this->assertFalse(Schema::hasColumn('payment_settlement_outbox', 'deposit_record_id'));
        });
    }

    public function test_rerun_rejects_nonempty_partial_table_with_valid_id_before_other_ddl(): void
    {
        $this->withPartialOutbox(function (): void {
            DB::statement('CREATE TABLE payment_settlement_outbox ('
                . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
                . 'event_type VARCHAR(50) NOT NULL'
                . ') ENGINE=InnoDB');
            DB::table('payment_settlement_outbox')->insert(['event_type' => 'existing']);

            try {
                $this->migration()->up();
                $this->fail('Nonempty partial outbox must fail before adding required business columns.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('required', strtolower($exception->getMessage()));
            }

            $this->assertFalse(Schema::hasColumn('payment_settlement_outbox', 'deposit_record_id'));
        });
    }

    public function test_rerun_repairs_wrong_id_contract_on_empty_partial_table(): void
    {
        $this->withPartialOutbox(function (): void {
            DB::statement('CREATE TABLE payment_settlement_outbox (id INT NULL, legacy_key INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');

            $this->migration()->up();

            $id = $this->column('id');
            $this->assertSame('bigint(20) unsigned', strtolower((string) $id->COLUMN_TYPE));
            $this->assertSame('NO', $id->IS_NULLABLE);
            $this->assertStringContainsString('auto_increment', strtolower((string) $id->EXTRA));
            $this->assertSame(['id'], $this->primaryColumns());
        });
    }

    public function test_rerun_rejects_wrong_id_contract_on_nonempty_table_before_other_ddl(): void
    {
        $this->withPartialOutbox(function (): void {
            DB::statement('CREATE TABLE payment_settlement_outbox (id INT NULL, legacy_key INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
            DB::table('payment_settlement_outbox')->insert(['id' => 7, 'legacy_key' => 1]);

            try {
                $this->migration()->up();
                $this->fail('Nonempty outbox with invalid id contract must fail closed.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('id', strtolower($exception->getMessage()));
            }
            $this->assertFalse(Schema::hasColumn('payment_settlement_outbox', 'deposit_record_id'));
        });
    }

    public function test_rerun_preserves_already_correct_id_contract(): void
    {
        $before = $this->column('id');
        $beforePrimary = $this->primaryColumns();

        $this->migration()->up();

        $after = $this->column('id');
        $this->assertSame($before->COLUMN_TYPE, $after->COLUMN_TYPE);
        $this->assertSame($before->IS_NULLABLE, $after->IS_NULLABLE);
        $this->assertSame($before->EXTRA, $after->EXTRA);
        $this->assertSame($beforePrimary, $this->primaryColumns());
    }

    private function withPartialOutbox(callable $callback): void
    {
        $backup = 'payment_settlement_outbox_task5_backup';
        DB::statement("RENAME TABLE payment_settlement_outbox TO {$backup}");
        try {
            $callback();
        } finally {
            Schema::dropIfExists('payment_settlement_outbox');
            DB::statement("RENAME TABLE {$backup} TO payment_settlement_outbox");
        }
    }

    private function dropRequiredIndexes(): void
    {
        foreach (['payment_settlement_outbox_event_deposit_unique', 'payment_settlement_outbox_ready_index', 'payment_settlement_outbox_order_index'] as $index) {
            if ($this->indexColumns($index) !== []) {
                DB::statement("ALTER TABLE payment_settlement_outbox DROP INDEX {$index}");
            }
        }
    }

    private function indexColumns(string $name): array
    {
        return collect(DB::select('SHOW INDEX FROM payment_settlement_outbox'))
            ->where('Key_name', $name)->sortBy('Seq_in_index')->pluck('Column_name')->values()->all();
    }

    private function indexNonUnique(string $name): ?int
    {
        $row = collect(DB::select('SHOW INDEX FROM payment_settlement_outbox'))->firstWhere('Key_name', $name);

        return $row ? (int) $row->Non_unique : null;
    }

    private function column(string $name)
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'payment_settlement_outbox')
            ->where('COLUMN_NAME', $name)->first();
    }

    private function primaryColumns(): array
    {
        return collect(DB::select('SHOW INDEX FROM payment_settlement_outbox'))
            ->where('Key_name', 'PRIMARY')->sortBy('Seq_in_index')->pluck('Column_name')->values()->all();
    }

    private function migration()
    {
        require_once database_path('migrations/2026_07_11_000006_create_payment_settlement_outbox.php');

        return new CreatePaymentSettlementOutbox();
    }
}
