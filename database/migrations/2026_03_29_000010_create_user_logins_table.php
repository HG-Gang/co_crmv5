<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLoginsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_logins', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('业务用户ID (来自id_sequences) | Business user ID from id_sequences');
            $blueprint->string('email', 191)->comment('邮箱 | Email');
            $blueprint->string('password', 255)->comment('密码 | Password');
            $blueprint->tinyInteger('account_type')->comment('账户类型: 1=代理, 2=客户 | Account type: 1=agent, 2=customer');
            $blueprint->tinyInteger('is_enabled')->default(1)->comment('是否启用 | Enabled');
            $blueprint->tinyInteger('is_cancelled')->default(0)->comment('是否注销 | Cancelled');
            $blueprint->tinyInteger('source_type')->default(0)->comment('来源: 0=系统, 1=导入 | Source: 0=system, 1=import');
            $blueprint->string('jwt_token_id', 100)->nullable()->comment('SSO: 当前JWT ID | SSO: current JWT ID');
            $blueprint->string('last_login_ip', 100)->default('')->comment('最后登录IP | Last login IP');
            $blueprint->dateTime('last_login_at')->nullable()->comment('最后登录时间 | Last login time');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->unique('email');
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
        Schema::dropIfExists('user_logins');
    }
}
