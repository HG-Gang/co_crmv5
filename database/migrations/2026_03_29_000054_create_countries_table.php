<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建国家表 countries。
 *
 * 文件功能：
 * - 国家基础字典（代码、电话区号、币种、启停状态），多语言名称见 country_langs。
 *
 * 字段语义：
 * - code 国家代码（ISO，唯一）；phone_code 国际电话区号；currency 币种代码；
 * - status 状态（1=启用 0=停用）；sort 排序权重。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCountriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('countries', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('iso_code', 5)->comment('ISO代码 | ISO code');
            $blueprint->integer('zone_id')->default(0)->comment('时区ID | Zone ID');
            $blueprint->integer('currency_id')->default(0)->comment('货币ID | Currency ID');
            $blueprint->tinyInteger('is_active')->default(0)->comment('是否启用 | Active');
            $blueprint->integer('call_prefix')->default(0)->comment('电话前缀 | Call prefix');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('iso_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('countries');
    }
}
