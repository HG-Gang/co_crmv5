<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepositRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deposit_records', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('user_name', 100)->default('')->comment('用户名 | User name');
            $blueprint->integer('mt4_ticket')->default(0)->comment('MT4订单号 | MT4 ticket');
            $blueprint->double('amount', 12, 2)->comment('金额 | Amount');
            $blueprint->double('actual_amount', 12, 2)->default(0)->comment('实际金额 | Actual amount');
            $blueprint->double('exchange_rate', 10, 4)->default(0)->comment('汇率 | Exchange rate');
            $blueprint->string('channel_name', 100)->default('')->comment('渠道名称 | Channel name');
            $blueprint->string('channel_order_no', 200)->default('')->comment('渠道订单号 | Channel order no');
            $blueprint->string('local_order_no', 200)->default('')->comment('本地订单号 | Local order no');
            $blueprint->string('status', 10)->default('01')->comment('状态: 01=待支付 02=已支付 05=退款 09=失败 10=超时 | Status: 01=unpaid 02=paid 05=refunded 09=failed 10=timeout');
            $blueprint->dateTime('payment_time')->nullable()->comment('支付时间 | Payment time');
            $blueprint->string('remarks', 500)->default('')->comment('备注 | Remarks');
            $blueprint->string('created_by', 100)->default('')->comment('创建人 | Created by');
            $blueprint->string('updated_by', 100)->default('')->comment('更新人 | Updated by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('user_id');
            $blueprint->index('status');
            $blueprint->index('mt4_ticket');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deposit_records');
    }
}
