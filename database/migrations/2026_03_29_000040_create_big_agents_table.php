<?php

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
