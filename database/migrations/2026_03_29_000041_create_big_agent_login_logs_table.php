<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建大代理登录日志表 big_agent_login_logs。
 *
 * 文件功能：
 * - 记录大代理账号登录的 IP、地理位置与 User-Agent，用于安全审计。
 *
 * 字段语义：
 * - big_agent_id 大代理 ID；login_ip 登录 IP；ip_address IP 地理位置；
 * - user_agent 浏览器标识；created_at 登录时间（10 位时间戳）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBigAgentLoginLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('big_agent_login_logs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('big_agent_id')->comment('大代理ID | Big agent ID');
            $blueprint->string('login_ip', 100)->comment('登录IP | Login IP');
            $blueprint->dateTime('login_at')->comment('登录时间 | Login at');
            
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
        Schema::dropIfExists('big_agent_login_logs');
    }
}
