<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建默认 users 表（Laravel 框架默认迁移）。
 *
 * 文件功能：
 * - 框架标准用户表：id、name、email（唯一）、email_verified_at、password、remember_token、时间戳。
 * - 项目业务登录实际使用 user_logins 表，本表保留框架默认能力（Sanctum/会话认证兜底）。
 *
 * 字段语义：
 * - name 用户名；email 登录邮箱（唯一索引）；password bcrypt 密码哈希；
 * - email_verified_at 邮箱验证时间（可空）；remember_token “记住我”会话令牌。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
