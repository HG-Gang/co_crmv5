<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMailSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mail_settings', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('driver', 50)->nullable()->comment('驱动 | Driver');
            $blueprint->string('host', 255)->nullable()->comment('主机 | Host');
            $blueprint->string('port', 10)->nullable()->comment('端口 | Port');
            $blueprint->string('username', 255)->nullable()->comment('用户名 | Username');
            $blueprint->string('password', 255)->nullable()->comment('密码 | Password');
            $blueprint->string('encryption', 20)->nullable()->comment('加密方式 | Encryption');
            $blueprint->string('from_address', 255)->nullable()->comment('发件人地址 | From address');
            $blueprint->string('from_name', 255)->nullable()->comment('发件人名称 | From name');
            
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
        Schema::dropIfExists('mail_settings');
    }
}
