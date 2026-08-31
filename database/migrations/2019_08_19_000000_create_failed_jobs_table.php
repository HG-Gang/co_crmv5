<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建失败队列任务表（Laravel 框架默认迁移）。
 *
 * 文件功能：
 * - 标准失败任务表：id、uuid（唯一）、connection、queue、payload、exception、failed_at。
 *
 * 字段语义：
 * - uuid 任务唯一标识（幂等重试依据）；connection/queue 来源连接与队列名；
 * - payload 任务序列化数据（longText）；exception 失败异常堆栈；failed_at 失败时间。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFailedJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('failed_jobs');
    }
}
