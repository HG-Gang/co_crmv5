<?php

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
