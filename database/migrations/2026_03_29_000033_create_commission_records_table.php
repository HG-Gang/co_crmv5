<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commission_records', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('unique_id', 100)->comment('MD5唯一标识 | MD5 unique identifier');
            $blueprint->integer('agent_id')->comment('代理ID | Agent ID');
            $blueprint->integer('parent_id')->comment('父代理ID | Parent agent ID');
            $blueprint->double('agent_profit', 12, 2)->default(0)->comment('代理利润 | Agent profit');
            $blueprint->double('agent_volume', 12, 2)->default(0)->comment('代理交易量 | Agent volume');
            $blueprint->integer('equity_value')->default(0)->comment('净值 | Equity value');
            $blueprint->integer('equity_diff')->default(0)->comment('净值差 | Equity diff');
            $blueprint->tinyInteger('settle_cycle')->default(0)->comment('结算周期 | Settle cycle');
            $blueprint->integer('mt4_order_id')->default(0)->comment('MT4订单ID | MT4 order ID');
            $blueprint->string('date_range', 500)->default('')->comment('日期范围 | Date range');
            $blueprint->tinyInteger('settle_status')->default(1)->comment('结算状态: 1=待结算 2=已结算 | Settle status: 1=pending 2=settled');
            $blueprint->double('fee', 12, 2)->default(0)->comment('手续费 | Fee');
            $blueprint->double('swap', 12, 2)->default(0)->comment('隔夜利息 | Swap');
            $blueprint->double('commission_amount', 12, 2)->default(0)->comment('佣金金额 | Commission amount');
            $blueprint->double('returned_amount', 12, 2)->default(0)->comment('返还金额 | Returned amount');
            $blueprint->double('deposit', 12, 2)->default(0)->comment('入金 | Deposit');
            $blueprint->double('real_amount', 12, 2)->default(0)->comment('实际金额 | Real amount');
            $blueprint->string('data_type', 20)->default('mainData')->comment('数据类型 | Data type');
            $blueprint->string('manual_reason', 500)->default('')->comment('手动调整原因 | Manual adjustment reason');
            $blueprint->string('remarks', 500)->default('')->comment('备注 | Remarks');
            $blueprint->string('created_by', 100)->default('')->comment('创建人 | Created by');
            $blueprint->string('updated_by', 100)->default('')->comment('更新人 | Updated by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('agent_id');
            $blueprint->index('parent_id');
            $blueprint->index('settle_status');
            $blueprint->index('unique_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commission_records');
    }
}
