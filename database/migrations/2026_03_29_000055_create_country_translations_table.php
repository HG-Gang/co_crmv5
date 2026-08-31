<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建国家翻译表 country_translations。
 *
 * 文件功能：
 * - 国家名称的多语言翻译（与 countries 一对多）。
 *
 * 字段语义：
 * - country_id 国家 ID；locale 语言代码（如 zh-CN、en）；name 翻译后的国家名称。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCountryTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('country_translations', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('country_id')->comment('国家ID | Country ID');
            $blueprint->string('lang_code', 10)->comment('语言代码 | Language code');
            $blueprint->string('name', 100)->comment('名称 | Name');
            $blueprint->string('initials', 10)->default('')->comment('首字母 | Initials');

            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->unique(['country_id', 'lang_code']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('country_translations');
    }
}
