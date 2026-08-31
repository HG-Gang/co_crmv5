<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 为 deposit_records 增加支付渠道回调结果字段。
 *
 * 文件功能：
 * - 增加 provider_order_result（渠道回调原始结果快照），便于对账与纠纷排查。
 *
 * 字段语义：
 * - provider_order_result 可空长文本（JSON/原始报文）；回滚时仅删除该字段。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProviderOrderResultToDepositRecords extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('deposit_records') || Schema::hasColumn('deposit_records', 'provider_order_result')) {
            return;
        }

        Schema::table('deposit_records', function (Blueprint $table) {
            $table->json('provider_order_result')->nullable()->after('provider_payload_hash');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('deposit_records') || !Schema::hasColumn('deposit_records', 'provider_order_result')) {
            return;
        }

        Schema::table('deposit_records', function (Blueprint $table) {
            $table->dropColumn('provider_order_result');
        });
    }
}
