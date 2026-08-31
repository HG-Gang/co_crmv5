<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建支付渠道表 payment_channels。
 *
 * 文件功能：
 * - 充值支付渠道配置：渠道标识、名称、费率、限额与启停状态。
 *
 * 字段语义：
 * - code 渠道编码（唯一）；name 渠道名称；type 渠道类型（在线/线下等）；
 * - fee_rate 渠道费率；min_amount/max_amount 单笔限额；
 * - status 状态（1=启用 0=停用）；sort 排序权重。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentChannelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_channels', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('name', 100)->comment('名称 | Name');
            $blueprint->string('channel_code', 50)->comment('渠道代码 | Channel code');
            $blueprint->double('exchange_rate', 10, 4)->default(0)->comment('汇率 | Exchange rate');
            $blueprint->tinyInteger('is_enabled')->default(1)->comment('是否启用 | Enabled');
            $blueprint->integer('sort')->default(0)->comment('排序 | Sort');
            $blueprint->json('config')->nullable()->comment('配置 | Config');
            
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
        Schema::dropIfExists('payment_channels');
    }
}
