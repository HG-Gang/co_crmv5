<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建在线用户表 user_onlines。
 *
 * 文件功能：
 * - 记录当前在线用户（心跳维护），供后台在线用户列表与强制下线使用。
 *
 * 字段语义：
 * - user_id 用户 ID（唯一）；login_id 登录记录 ID；last_seen_at 最后心跳时间；
 * - ip 在线 IP；device 设备标识；is_force_offline 是否被强制下线。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserOnlinesTable extends Migration
{
    public function up()
    {
        Schema::create('user_onlines', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('user_id')->comment('用户ID | User ID');
            $blueprint->unsignedInteger('last_activity')->comment('最后活跃时间 | Last activity');
            $blueprint->string('ip_address', 45)->nullable()->comment('IP地址 | IP address');
            $blueprint->text('user_agent')->nullable()->comment('浏览器代理 | User agent');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');

            $blueprint->index('user_id');
            $blueprint->index('last_activity');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_onlines');
    }
}
