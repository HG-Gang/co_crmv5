<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 为充值记录增加退款结算字段。
 *
 * 文件功能：
 * - 增加退款结算相关字段（退款金额、退款单号、结算状态等）。
 *
 * 字段语义：
 * - 新增字段可空/带默认值；回滚时仅删除本迁移新增字段，不影响既有充值数据。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDepositRefundSettlementFields extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('deposit_records')) {
            return;
        }
        if (!Schema::hasColumn('deposit_records', 'refund_mt4_ticket')) {
            Schema::table('deposit_records', function (Blueprint $table) {
                $table->unsignedBigInteger('refund_mt4_ticket')->nullable()->after('mt4_ticket');
            });
        }
        if (!Schema::hasColumn('deposit_records', 'refund_time')) {
            Schema::table('deposit_records', function (Blueprint $table) {
                $table->dateTime('refund_time')->nullable()->after('payment_time');
            });
        }
    }

    public function down()
    {
        // Intentionally irreversible: refund ticket and time are financial audit data.
    }
}
