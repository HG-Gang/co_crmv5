<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('news', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('title', 500)->comment('标题 | Title');
            $blueprint->longText('content')->comment('内容 | Content');
            $blueprint->string('image', 500)->nullable()->comment('图片 | Image');
            $blueprint->integer('author_id')->default(0)->comment('作者ID | Author ID');
            $blueprint->string('author_name', 100)->default('')->comment('作者名称 | Author name');
            $blueprint->tinyInteger('is_published')->default(0)->comment('是否发布: 0=草稿 1=已发布 | Published: 0=draft 1=published');
            
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
        Schema::dropIfExists('news');
    }
}
