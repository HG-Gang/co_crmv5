<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppVersionsTable extends Migration
{
    public function up()
    {
        Schema::create('app_versions', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('platform', 20)->comment('平台: android, ios | Platform');
            $blueprint->string('version', 20)->comment('版本号 | Version');
            $blueprint->string('download_url', 255)->comment('下载地址 | Download URL');
            $blueprint->text('update_logs')->nullable()->comment('更新日志 | Update logs');
            $blueprint->tinyInteger('is_force')->default(0)->comment('是否强制更新 | Force update');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_versions');
    }
}
