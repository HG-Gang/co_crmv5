<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCountryLangsTable extends Migration
{
    public function up()
    {
        Schema::create('country_langs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('country_id')->comment('国家ID | Country ID');
            $blueprint->string('lang_code', 10)->comment('语言代码 | Language code');
            $blueprint->string('name', 100)->comment('国家名称 | Country name');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->index(['country_id', 'lang_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('country_langs');
    }
}
