<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOperationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('operation_logs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('admin_id')->comment('管理员ID | Admin ID');
            $blueprint->string('admin_name', 100)->comment('管理员名称 | Admin name');
            $blueprint->integer('target_user_id')->nullable()->comment('目标用户ID | Target user ID');
            $blueprint->string('order_no', 100)->nullable()->comment('订单号 | Order no');
            $blueprint->string('content', 1000)->comment('操作内容 | Content');
            $blueprint->string('ip', 100)->comment('IP | IP');
            $blueprint->tinyInteger('action_type')->default(0)->comment('行为类型: 0=普通 1=提现 | Action type: 0=general 1=withdraw');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('admin_id');
            $blueprint->index('target_user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('operation_logs');
    }
}
