<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建密码重置令牌表（Laravel 框架默认迁移）。
 *
 * 文件功能：
 * - 标准密码重置表：email（索引）、token、created_at。
 *
 * 字段语义：
 * - email 申请重置的邮箱；token 重置令牌（哈希后存储）；created_at 令牌生成时间，
 *   与 config/auth.php 的 expire（60 分钟）配合判断令牌是否过期。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePasswordResetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('password_resets');
    }
}
