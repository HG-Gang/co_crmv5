<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admins', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('role_id', 60)->nullable()->comment('角色ID | Role ID');
            $blueprint->char('mobile', 20)->nullable()->comment('手机号 | Mobile number');
            $blueprint->string('email', 100)->nullable()->comment('邮箱 | Email address');
            $blueprint->string('username', 100)->comment('用户名 | Username');
            $blueprint->string('password', 100)->comment('密码 | Password');
            $blueprint->integer('login_count')->default(0)->comment('登录次数 | Login count');
            $blueprint->string('last_login_ip', 50)->nullable()->comment('最后登录IP | Last login IP');
            $blueprint->dateTime('last_login_at')->nullable()->comment('最后登录时间 | Last login time');
            $blueprint->string('last_login_address', 200)->nullable()->comment('最后登录地址 | Last login address');
            $blueprint->tinyInteger('status')->default(1)->comment('状态: 1=启用 0=禁用 | Status: 1=active 0=disabled');
            $blueprint->string('jwt_token_id', 100)->nullable()->comment('SSO: 当前JWT ID | SSO: current JWT ID');
            $blueprint->string('created_by', 50)->nullable()->comment('创建人 | Created by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('username');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admins');
    }
}
