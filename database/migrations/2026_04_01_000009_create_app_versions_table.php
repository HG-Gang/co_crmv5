<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建 App 版本表 app_versions。
 *
 * 文件功能：
 * - 客户端 App 版本发布记录：版本号、更新说明、强制更新标记与下载地址。
 *
 * 字段语义：
 * - platform 平台（ios/android）；version 版本号；build 构建号；
 * - update_log 更新说明；is_force 是否强制更新；download_url 下载地址；status 状态。
 */

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
