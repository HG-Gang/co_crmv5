<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建权益结算临时表 rights_settlement_temps。
 *
 * 文件功能：
 * - 权益结算计算过程的临时存储（分批计算、断点续算）。
 *
 * 字段语义：
 * - user_id 用户 ID；batch_no 批次号；raw_data 原始计算数据（JSON）；status 处理状态。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRightsSettlementTempsTable extends Migration
{
    public function up()
    {
        Schema::create('rights_settlement_temps', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('user_id')->comment('用户ID | User ID');
            $blueprint->decimal('amount', 20, 8)->comment('临时金额 | Temporary amount');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');

            $blueprint->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rights_settlement_temps');
    }
}
