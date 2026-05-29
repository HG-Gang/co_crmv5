<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMt4ConfigsTable extends Migration
{
    public function up()
    {
        Schema::create('mt4_configs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('server_name', 100)->comment('服务器名称 | Server name');
            $blueprint->string('ip', 50)->comment('服务器IP | Server IP');
            $blueprint->integer('port')->comment('端口 | Port');
            $blueprint->string('manager_login', 50)->comment('管理账号 | Manager login');
            $blueprint->string('manager_password', 100)->comment('管理密码 | Manager password');
            $blueprint->tinyInteger('is_active')->default(1)->comment('是否激活 | Active');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mt4_configs');
    }
}
