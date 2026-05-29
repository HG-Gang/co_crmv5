<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMt4TradesTable extends Migration
{
    public function up()
    {
        Schema::create('mt4_trades', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('ticket')->unique()->comment('MT4订单号 | MT4 Ticket');
            $blueprint->integer('login')->comment('MT4账号 | MT4 Login');
            $blueprint->string('symbol', 50)->comment('品种 | Symbol');
            $blueprint->integer('cmd')->comment('类型: 0=Buy, 1=Sell | Command');
            $blueprint->double('volume')->comment('成交量 | Volume');
            $blueprint->decimal('open_price', 20, 5)->comment('开仓价 | Open price');
            $blueprint->decimal('close_price', 20, 5)->nullable()->comment('平仓价 | Close price');
            $blueprint->decimal('commission', 20, 2)->default(0)->comment('手续费 | Commission');
            $blueprint->decimal('swaps', 20, 2)->default(0)->comment('库存费 | Swaps');
            $blueprint->decimal('profit', 20, 2)->comment('盈利 | Profit');
            $blueprint->unsignedInteger('open_time')->comment('开仓时间 | Open time');
            $blueprint->unsignedInteger('close_time')->nullable()->comment('平仓时间 | Close time');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');

            $blueprint->index('login');
            $blueprint->index('ticket');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mt4_trades');
    }
}
