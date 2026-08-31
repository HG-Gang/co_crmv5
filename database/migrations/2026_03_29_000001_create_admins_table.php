<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建后台管理员表 admins。
 *
 * 文件功能：
 * - 管理员账号主体表：角色、账号凭据、登录统计、状态与 SSO 令牌记录。
 *
 * 字段语义：
 * - role_id 角色 ID；mobile/email 手机与邮箱；username 登录用户名（唯一）；
 * - password 密码哈希；login_count 累计登录次数；last_login_* 最后登录信息；
 * - status 状态（1=启用 0=禁用）；jwt_token_id SSO 当前有效 JWT ID（互踢依据）；
 * - created_by/updated_by 创建/更新人；时间字段均为 10 位时间戳（0=未设置）。
 */

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
