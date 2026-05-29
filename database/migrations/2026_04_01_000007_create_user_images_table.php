<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserImagesTable extends Migration
{
    public function up()
    {
        Schema::create('user_images', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('user_id')->comment('用户ID | User ID');
            $blueprint->string('type', 50)->comment('图片类型: kyc_front, kyc_back, avatar | Image type');
            $blueprint->string('path', 255)->comment('文件路径 | File path');
            $blueprint->string('mime_type', 50)->nullable()->comment('MIME类型 | MIME type');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_images');
    }
}
