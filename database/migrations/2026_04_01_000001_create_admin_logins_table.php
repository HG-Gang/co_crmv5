<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminLoginsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin_logins', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('username', 100)->comment('用户名 | Username');
            $blueprint->string('password', 100)->comment('密码 | Password');
            $blueprint->unsignedInteger('role_id')->default(0)->comment('角色ID | Role ID');
            $blueprint->tinyInteger('status')->default(1)->comment('状态: 1=启用 0=禁用 | Status: 1=active 0=disabled');
            $blueprint->string('last_login_ip', 50)->nullable()->comment('最后登录IP | Last login IP');
            $blueprint->unsignedInteger('last_login_at')->nullable()->comment('最后登录时间 | Last login time');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->unique('username');
            $blueprint->index('role_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admin_logins');
    }
}
