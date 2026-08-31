<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建代理级别表 agent_levels。
 *
 * 文件功能：
 * - 代理等级配置：等级代码、名称与佣金上下限，供佣金结算按级别计算。
 *
 * 字段语义：
 * - level_code 级别代码（唯一）；name 级别名称；
 * - max_commission/min_commission 该级别佣金上限/下限（整数）；
 * - user_commission 用户侧佣金比例。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentLevelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agent_levels', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('level_code')->unique()->comment('级别代码 | Level code');
            $blueprint->string('name', 200)->comment('名称 | Name');
            $blueprint->integer('max_commission')->default(0)->comment('最大佣金 | Max commission');
            $blueprint->integer('min_commission')->default(0)->comment('最小佣金 | Min commission');
            $blueprint->integer('user_commission')->default(0)->comment('用户佣金 | User commission');
            
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
        Schema::dropIfExists('agent_levels');
    }
}
