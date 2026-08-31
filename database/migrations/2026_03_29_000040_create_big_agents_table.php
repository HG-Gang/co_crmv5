<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建大代理（集团代理）表 big_agents。
 *
 * 文件功能：
 * - 集团/大代理主体：名称、负责人、级别与状态，独立于普通代理层级。
 *
 * 字段语义：
 * - name 大代理名称；contact 联系人；level 级别；commission_rate 佣金比例；
 * - status 状态（1=启用 0=停用）；remark 备注。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBigAgentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('big_agents', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('email', 191)->comment('邮箱 | Email');
            $blueprint->string('username', 200)->comment('用户名 | Username');
            $blueprint->string('password', 255)->comment('密码 | Password');
            $blueprint->string('sub_agent_ids', 500)->default('')->comment('下级代理ID | Sub agent IDs');
            $blueprint->tinyInteger('is_enabled')->default(1)->comment('是否启用 | Enabled');
            $blueprint->string('jwt_token_id', 100)->nullable()->comment('JWT Token ID');
            $blueprint->string('created_by', 100)->default('')->comment('创建人 | Created by');
            
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
        Schema::dropIfExists('big_agents');
    }
}
