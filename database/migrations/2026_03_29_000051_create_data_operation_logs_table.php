<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建数据操作日志表 data_operation_logs。
 *
 * 文件功能：
 * - 记录数据变更操作（增删改）的前后值快照，用于数据审计与回滚追溯。
 *
 * 字段语义：
 * - table_name 操作表名；record_id 记录 ID；action 操作类型（insert/update/delete）；
 * - before_data/after_data 变更前后数据快照（JSON）；operator_id 操作人；ip 操作 IP。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataOperationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_operation_logs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('model_type', 100)->comment('模型类型 | Model type');
            $blueprint->integer('model_id')->comment('模型ID | Model ID');
            $blueprint->json('before_data')->nullable()->comment('修改前数据 | Before data');
            $blueprint->json('after_data')->nullable()->comment('修改后数据 | After data');
            $blueprint->integer('operator_id')->comment('操作人ID | Operator ID');
            
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
        Schema::dropIfExists('data_operation_logs');
    }
}
