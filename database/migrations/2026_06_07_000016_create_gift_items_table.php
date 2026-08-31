<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 01:42
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建礼品配置表 gift_items。
 *
 * 文件功能：
 * - 建立礼品发放/积分兑换的礼品字典：名称、说明、兑换积分、库存、启用状态与图片地址，
 *   为名称、积分成本与 (status, stock_quantity) 建索引；回滚直接删表。
 */
class CreateGiftItemsTable extends Migration
{
    public function up()
    {
        Schema::create('gift_items', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('name', 200)->default('')->comment('礼品名称 | Gift name');
            $blueprint->string('description', 1000)->default('')->comment('礼品说明 | Gift description');
            $blueprint->integer('points_cost')->default(0)->comment('兑换积分 | Points cost');
            $blueprint->integer('stock_quantity')->default(0)->comment('库存数量 | Stock quantity');
            $blueprint->tinyInteger('status')->default(1)->comment('状态：0=停用 1=启用 | Status');
            $blueprint->string('image_url', 500)->default('')->comment('礼品图片 | Gift image URL');
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->index('name');
            $blueprint->index('points_cost');
            $blueprint->index(['status', 'stock_quantity']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('gift_items');
    }
}
