<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建批量任务失败记录表 batch_fail_records。
 *
 * 文件功能：
 * - 记录批量导入/处理任务中失败行的明细与原因，供重试与人工修正。
 *
 * 字段语义：
 * - batch_type 批次类型；batch_no 批次号；row_no 失败行号；raw_data 原始数据；
 * - fail_reason 失败原因；status 处理状态（待重试/已处理）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBatchFailRecordsTable extends Migration
{
    public function up()
    {
        Schema::create('batch_fail_records', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('batch_type', 50)->comment('批量操作类型 | Batch type');
            $blueprint->string('batch_id', 100)->comment('批量操作ID | Batch ID');
            $blueprint->text('data')->comment('原始数据 | Raw data');
            $blueprint->string('error_msg', 255)->comment('错误信息 | Error message');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');

            $blueprint->index('batch_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('batch_fail_records');
    }
}
