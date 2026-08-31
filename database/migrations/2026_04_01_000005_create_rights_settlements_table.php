<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建权益结算表 rights_settlements。
 *
 * 文件功能：
 * - 用户权益（净值/资金）周期结算结果，供权益汇总报表使用。
 *
 * 字段语义：
 * - user_id 用户 ID；settle_date 结算日期；equity 结算净值；
 * - total_funds 总资金；profit 周期盈亏；status 结算状态。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRightsSettlementsTable extends Migration
{
    public function up()
    {
        Schema::create('rights_settlements', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('user_id')->comment('用户ID | User ID');
            $blueprint->decimal('amount', 20, 8)->comment('结算金额 | Settlement amount');
            $blueprint->tinyInteger('status')->default(0)->comment('状态: 0=未处理, 1=已处理 | Status: 0=pending, 1=processed');
            $blueprint->string('remark', 255)->nullable()->comment('备注 | Remark');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rights_settlements');
    }
}
