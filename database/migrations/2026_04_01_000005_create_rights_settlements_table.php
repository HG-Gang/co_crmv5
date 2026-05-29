<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRightsSettlementsTable extends Migration
{
    public function up()
    {
        Schema::create('rights_settlements', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('user_id')->comment('用户ID | User ID');
            $blueprint->decimal('amount', 20, 8)->comment('结算金额 | Settlement amount');
            $blueprint->tinyInteger('status')->default(0)->comment('状态: 0=未处理, 1=已处理 | Status: 0=pending, 1=processed');
            $blueprint->string('remark', 255)->nullable()->comment('备注 | Remark');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rights_settlements');
    }
}
