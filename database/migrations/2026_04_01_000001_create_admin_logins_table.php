<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建管理员登录表 admin_logins。
 *
 * 文件功能：
 * - 管理员登录主体表（独立于 admins 的业务登录记录）：登录账号、凭据与状态。
 *
 * 字段语义：
 * - admin_id 管理员 ID；email 登录邮箱（唯一）；password 密码哈希；
 * - is_enabled 是否启用；jwt_token_id SSO 当前有效 JWT ID；
 * - last_login_ip/last_login_at 最后登录信息。
 */

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
