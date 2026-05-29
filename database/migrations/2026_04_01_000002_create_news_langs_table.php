<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNewsLangsTable extends Migration
{
    public function up()
    {
        Schema::create('news_langs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('news_id')->comment('新闻ID | News ID');
            $blueprint->string('lang_code', 10)->comment('语言代码 | Language code');
            $blueprint->string('title', 255)->comment('标题 | Title');
            $blueprint->text('content')->nullable()->comment('内容 | Content');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->index(['news_id', 'lang_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('news_langs');
    }
}
