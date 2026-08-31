<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 为 mt4_trades 补充 comment 与 modify_time 字段。
 *
 * 文件功能：
 * - 增加 comment（MT4 交易备注，出入金流水按 WBIN/WBAD 等关键字识别来源）；
 * - 增加 modify_time（MT4 修改时间，供实时返佣与资金流水精确时间口径复用）。
 *
 * 字段语义：
 * - 两个字段均可空，回滚时仅删除本迁移新增字段。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCommentAndModifyTimeToMt4TradesTable extends Migration
{
    /**
     * 为 MT4 交易表补齐旧项目资金流水依赖字段。
     *
     * 字段说明：
     * - comment 保存 MT4 余额交易备注，出金流水按 WBIN、WBAD 等关键字识别来源。
     * - modify_time 保存 MT4 修改时间，后续实时返佣或资金流水精确时间口径可直接复用。
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('mt4_trades')) {
            return;
        }

        Schema::table('mt4_trades', function (Blueprint $table): void {
            if (!Schema::hasColumn('mt4_trades', 'comment')) {
                $table->string('comment', 255)->default('')->after('close_time')->comment('MT4 交易备注，用于出入金来源识别');
            }

            if (!Schema::hasColumn('mt4_trades', 'modify_time')) {
                $table->unsignedInteger('modify_time')->nullable()->after('comment')->comment('MT4 修改时间');
            }
        });
    }

    /**
     * 回滚本次补充字段。
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('mt4_trades')) {
            return;
        }

        Schema::table('mt4_trades', function (Blueprint $table): void {
            if (Schema::hasColumn('mt4_trades', 'modify_time')) {
                $table->dropColumn('modify_time');
            }

            if (Schema::hasColumn('mt4_trades', 'comment')) {
                $table->dropColumn('comment');
            }
        });
    }
}
