<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建点差配置表 spread_configs。
 *
 * 文件功能：
 * - 交易品种点差配置：固定/浮动点差、最大最小点差与启停状态。
 *
 * 字段语义：
 * - symbol 品种代码；spread_type 点差类型（固定/浮动）；value 点差值；
 * - min_value/max_value 浮动点差上下限；status 状态（1=启用 0=停用）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpreadConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spread_configs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->double('spread')->comment('点差 | Spread');
            $blueprint->integer('agent_group_id')->comment('代理组ID | Agent group ID');
            $blueprint->double('spread_ratio')->comment('点差比例 | Spread ratio');
            $blueprint->tinyInteger('status')->default(1)->comment('状态 | Status');
            
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
        Schema::dropIfExists('spread_configs');
    }
}
