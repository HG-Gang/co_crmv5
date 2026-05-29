<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserTradesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_trades', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->integer('ticket')->comment('订单号 | Ticket');
            $blueprint->char('symbol', 16)->comment('交易品种 | Symbol');
            $blueprint->integer('digits')->comment('小数位数 | Digits');
            $blueprint->integer('cmd')->comment('类型 | Cmd');
            $blueprint->integer('volume')->comment('成交量 | Volume');
            $blueprint->dateTime('open_time')->comment('开仓时间 | Open time');
            $blueprint->double('open_price')->comment('开仓价格 | Open price');
            $blueprint->double('stop_loss')->default(0)->comment('止损 | Stop loss');
            $blueprint->double('take_profit')->default(0)->comment('止盈 | Take profit');
            $blueprint->dateTime('close_time')->comment('平仓时间 | Close time');
            $blueprint->dateTime('expiration')->nullable()->comment('到期时间 | Expiration');
            $blueprint->integer('reason')->default(0)->comment('原因 | Reason');
            $blueprint->double('conv_rate1')->default(0)->comment('转换率1 | Conv rate 1');
            $blueprint->double('conv_rate2')->default(0)->comment('转换率2 | Conv rate 2');
            $blueprint->double('commission')->default(0)->comment('佣金 | Commission');
            $blueprint->double('commission_agent')->default(0)->comment('代理佣金 | Agent commission');
            $blueprint->double('swaps')->default(0)->comment('隔夜利息 | Swaps');
            $blueprint->double('close_price')->default(0)->comment('平仓价格 | Close price');
            $blueprint->double('profit')->default(0)->comment('利润 | Profit');
            $blueprint->double('taxes')->default(0)->comment('税费 | Taxes');
            $blueprint->string('comment', 100)->default('')->comment('评论 | Comment');
            $blueprint->integer('internal_id')->default(0)->comment('内部ID | Internal ID');
            $blueprint->double('margin_rate')->default(0)->comment('保证金率 | Margin rate');
            $blueprint->integer('timestamp_val')->default(0)->comment('时间戳 | Timestamp');
            $blueprint->integer('magic')->default(0)->comment('魔法号 | Magic number');
            $blueprint->integer('gw_volume')->default(0)->comment('网关成交量 | GW volume');
            $blueprint->integer('gw_open_price')->default(0)->comment('网关开仓价 | GW open price');
            $blueprint->integer('gw_close_price')->default(0)->comment('网关平仓价 | GW close price');
            $blueprint->dateTime('modify_time')->comment('修改时间 | Modify time');
            $blueprint->tinyInteger('settlement_status')->default(0)->comment('结算状态: 0=未结算 1=已结算 | Settlement status: 0=unsettled 1=settled');
            $blueprint->dateTime('settled_at')->nullable()->comment('结算时间 | Settled at');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('user_id');
            $blueprint->index('ticket');
            $blueprint->index('cmd');
            $blueprint->index('open_time');
            $blueprint->index('close_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_trades');
    }
}
