<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWithdrawRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('withdraw_records', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('user_name', 100)->default('')->comment('用户名 | User name');
            $blueprint->string('mt4_ticket', 100)->default('')->comment('MT4订单号 | MT4 ticket');
            $blueprint->double('apply_amount', 12, 2)->comment('申请金额 | Applied amount');
            $blueprint->double('actual_amount', 12, 2)->default(0)->comment('实际金额 | Actual amount');
            $blueprint->double('fee', 12, 2)->default(0)->comment('手续费 | Fee');
            $blueprint->double('exchange_rate', 10, 4)->default(0)->comment('汇率 | Exchange rate');
            $blueprint->double('rmb_fee', 12, 2)->default(0)->comment('人民币手续费 | RMB fee');
            $blueprint->string('bank_no', 100)->default('')->comment('银行卡号 | Bank number');
            $blueprint->string('bank_name', 255)->default('')->comment('银行名称 | Bank name');
            $blueprint->string('bank_addr', 255)->default('')->comment('分行地址 | Bank address');
            $blueprint->tinyInteger('status')->default(0)->comment('状态: 0=待处理 1=处理中 2=完成 3=失败 | Status: 0=pending 1=processing 2=completed 3=failed');
            $blueprint->string('local_order_no', 200)->default('')->comment('本地订单号 | Local order no');
            $blueprint->string('third_order_no', 200)->default('')->comment('第三方订单号 | Third order no');
            $blueprint->string('reject_reason', 500)->nullable()->comment('拒绝原因 | Reject reason');
            $blueprint->string('mt4_return_status', 50)->default('')->comment('MT4返回状态 | MT4 return status');
            $blueprint->string('created_by', 100)->default('')->comment('创建人 | Created by');
            $blueprint->string('updated_by', 100)->default('')->comment('更新人 | Updated by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('user_id');
            $blueprint->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('withdraw_records');
    }
}
