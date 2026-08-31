<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建 MT4 价格表 mt4_prices。
 *
 * 文件功能：
 * - MT4 品种实时报价快照（买价/卖价/最高/最低），供行情展示。
 *
 * 字段语义：
 * - symbol 品种代码；bid/ask 买价/卖价；high/low 最高/最低；
 * - price_time 报价时间（MT4 服务端时间）。
 */

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
