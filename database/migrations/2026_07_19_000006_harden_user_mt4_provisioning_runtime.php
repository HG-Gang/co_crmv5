<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 加固用户 MT4 开户预配运行时契约。
 *
 * 文件功能：
 * - 为 user_mt4_provisioning_outbox 补充运行时契约字段/索引（如唯一约束、状态机字段）。
 *
 * 字段语义：
 * - 新增字段可空/带默认值；回滚保留加固结构，避免预配流程状态丢失。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenUserMt4ProvisioningRuntime extends Migration
{
    /**
     * 被加固的出箱表名。up/down 均以“该表存在且 payload_hash 列存在”为前置守卫，
     * 表名集中一处定义，避免 MySQL DDL 语句与 Schema 门面操作指向不同表。
     */
    private const TABLE = 'user_mt4_provisioning_outbox';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'payload_hash')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE ' . self::TABLE . ' MODIFY `payload_hash` CHAR(64) NULL'
            );

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->char('payload_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'payload_hash')) {
            return;
        }
        if (DB::table(self::TABLE)->whereNull('payload_hash')->exists()) {
            throw new RuntimeException(
                'Cannot restore a non-null MT4 provisioning payload hash while terminal rows are redacted.'
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE ' . self::TABLE . ' MODIFY `payload_hash` CHAR(64) NOT NULL'
            );

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->char('payload_hash', 64)->nullable(false)->change();
        });
    }
}
