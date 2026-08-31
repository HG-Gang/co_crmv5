<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建批量出金导入记录表 withdraw_imports。
 *
 * 文件功能：
 * - 后台批量导入出金数据的批次记录：文件名、导入人、结果统计与状态。
 *
 * 字段语义：
 * - batch_no 批次号（唯一）；file_name 导入文件名；total_rows 总行数；
 * - success_rows/fail_rows 成功/失败行数；operator_id 导入人；
 * - status 状态（导入中/成功/失败）；fail_reason 失败原因。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWithdrawImportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('withdraw_imports', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('user_name', 200)->default('')->comment('用户名 | User name');
            $blueprint->string('amount', 100)->default('')->comment('金额 | Amount');
            $blueprint->string('remarks', 500)->default('')->comment('备注 | Remarks');
            $blueprint->integer('mt4_order_id')->default(0)->comment('MT4订单ID | MT4 order ID');
            $blueprint->string('batch_no', 100)->default('')->comment('批次号 | Batch no');
            $blueprint->tinyInteger('is_synced')->default(0)->comment('是否同步: 0=待处理 1=成功 2=失败 | Synced: 0=pending 1=success 2=fail');
            $blueprint->string('fail_reason', 500)->default('')->comment('失败原因 | Fail reason');
            $blueprint->integer('created_by')->default(0)->comment('创建人 | Created by');
            $blueprint->integer('updated_by')->default(0)->comment('更新人 | Updated by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('user_id');
            $blueprint->index('batch_no');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('withdraw_imports');
    }
}
