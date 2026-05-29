<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLoginLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_login_logs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('login_id')->comment('登录ID | Login ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('login_ip', 200)->comment('登录IP | Login IP');
            $blueprint->string('ip_location', 255)->comment('IP地理位置 | IP location');
            $blueprint->string('user_agent', 500)->nullable()->comment('用户代理 | User agent');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('login_id');
            $blueprint->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_login_logs');
    }
}
