<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建管理员登录日志表 admin_login_logs。
 *
 * 文件功能：
 * - 记录管理员每次登录的 IP、地理位置与 User-Agent，用于安全审计。
 *
 * 字段语义：
 * - admin_id 管理员 ID；login_ip 登录 IP；ip_address IP 地理位置；
 * - user_agent 浏览器标识；created_at 登录时间（10 位时间戳）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminLoginLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin_login_logs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->bigInteger('admin_id')->comment('管理员ID | Admin user ID');
            $blueprint->string('login_ip', 50)->comment('登录IP | Login IP');
            $blueprint->string('ip_address', 200)->nullable()->comment('IP地理位置 | IP geographic address');
            $blueprint->string('user_agent', 500)->nullable()->comment('用户代理 | User Agent string');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admin_login_logs');
    }
}
