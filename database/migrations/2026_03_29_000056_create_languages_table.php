<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建语言表 languages。
 *
 * 文件功能：
 * - 系统支持的语言字典（代码、名称、默认标记与启停状态）。
 *
 * 字段语义：
 * - code 语言代码（唯一，如 zh-CN）；name 语言名称；
 * - is_default 是否默认语言；status 状态（1=启用 0=停用）；sort 排序权重。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLanguagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('languages', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('name', 50)->comment('名称 | Name');
            $blueprint->string('iso_code', 5)->comment('ISO代码 | ISO code');
            $blueprint->string('language_code', 10)->comment('语言代码 | Language code');
            $blueprint->string('locale', 10)->comment('本地化 | Locale');
            $blueprint->tinyInteger('is_active')->default(1)->comment('是否启用 | Active');
            
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
        Schema::dropIfExists('languages');
    }
}
