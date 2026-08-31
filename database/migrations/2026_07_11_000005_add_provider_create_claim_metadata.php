<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 为充值/退款增加渠道创建与认领元数据。
 *
 * 文件功能：
 * - 增加 provider_create_claim 相关元数据字段（渠道创建请求/认领结果快照）。
 *
 * 字段语义：
 * - 新增字段可空，用于记录渠道下单与结算认领过程；回滚时仅删除本迁移新增字段。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProviderCreateClaimMetadata extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('deposit_records')) {
            return;
        }

        if (!Schema::hasColumn('deposit_records', 'provider_create_started_at')) {
            Schema::table('deposit_records', function (Blueprint $table) {
                $table->dateTime('provider_create_started_at')->nullable()->after('provider_order_result');
            });
        }
        if (!Schema::hasColumn('deposit_records', 'provider_create_attempts')) {
            Schema::table('deposit_records', function (Blueprint $table) {
                $table->unsignedInteger('provider_create_attempts')->default(0)->after('provider_create_started_at');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('deposit_records')) {
            return;
        }

        foreach (['provider_create_attempts', 'provider_create_started_at'] as $column) {
            if (Schema::hasColumn('deposit_records', $column)) {
                Schema::table('deposit_records', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
}
