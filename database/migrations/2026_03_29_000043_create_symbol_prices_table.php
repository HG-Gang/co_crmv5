<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建交易品种价格表 symbol_prices。
 *
 * 文件功能：
 * - 交易品种实时价格快照：买价、卖价、时间与来源，供前端展示与计算。
 *
 * 字段语义：
 * - symbol 品种代码；bid/ask 买价/卖价；high/low 最高/最低；
 * - price_time 报价时间；source 数据来源（MT4/手动）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSymbolPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('symbol_prices', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('symbol', 16)->comment('交易品种 | Symbol');
            $blueprint->dateTime('time')->comment('时间 | Time');
            $blueprint->double('bid')->comment('买入价 | Bid');
            $blueprint->double('ask')->comment('卖出价 | Ask');
            $blueprint->double('low')->comment('最低价 | Low');
            $blueprint->double('high')->comment('最高价 | High');
            $blueprint->integer('direction')->comment('方向 | Direction');
            $blueprint->integer('digits')->comment('小数位数 | Digits');
            $blueprint->double('spread')->comment('点差 | Spread');
            $blueprint->integer('group_id')->default(0)->comment('分组ID: 1=贵金属 2=能源 3=外汇 4=指数 5=货币 6=股票 | Group ID');
            $blueprint->tinyInteger('status')->default(1)->comment('状态 | Status');
            $blueprint->dateTime('modify_time')->comment('修改时间 | Modify time');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('symbol_prices');
    }
}
