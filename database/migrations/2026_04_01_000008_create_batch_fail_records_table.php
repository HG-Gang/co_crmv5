<?php

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
