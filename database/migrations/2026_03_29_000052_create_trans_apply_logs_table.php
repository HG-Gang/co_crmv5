<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransApplyLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trans_apply_logs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->integer('group_id')->comment('分组ID | Group ID');
            $blueprint->string('group_name', 200)->comment('分组名称 | Group name');
            $blueprint->integer('applicant_id')->comment('申请人ID | Applicant ID');
            $blueprint->string('applicant_name', 200)->comment('申请人姓名 | Applicant name');
            $blueprint->tinyInteger('status')->default(0)->comment('状态: 0=待处理 1=通过 -1=拒绝 | Status: 0=pending 1=approved -1=rejected');
            $blueprint->string('reject_reason', 500)->nullable()->comment('拒绝原因 | Reject reason');
            $blueprint->string('created_by', 100)->default('')->comment('创建人 | Created by');
            $blueprint->string('updated_by', 100)->default('')->comment('更新人 | Updated by');
            
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
        Schema::dropIfExists('trans_apply_logs');
    }
}
