<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMt4PricesTable extends Migration
{
    public function up()
    {
        Schema::create('mt4_prices', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('symbol', 50)->comment('交易品种 | Symbol');
            $blueprint->decimal('bid', 20, 5)->comment('卖出价 | Bid');
            $blueprint->decimal('ask', 20, 5)->comment('买入价 | Ask');
            $blueprint->unsignedInteger('timestamp')->comment('价格时间戳 | Timestamp');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');

            $blueprint->index('symbol');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mt4_prices');
    }
}
