<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建转账申请日志表 trans_apply_logs。
 *
 * 文件功能：
 * - 记录转账/调拨申请（含旧前台申请字段兼容），用于资金调拨审计。
 *
 * 字段语义：
 * - user_id 申请人；apply_type 申请类型；amount 金额；
 * - status 状态（待审核/通过/拒绝）；audit_by/audit_at 审核人与时间；remark 备注。
 */

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
