<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSysDictsTable extends Migration
{
    public function up()
    {
        Schema::create('sys_dicts', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('type', 50)->comment('字典类型 | Dict type');
            $blueprint->string('label', 100)->comment('字典名称 | Dict label');
            $blueprint->string('value', 100)->comment('字典值 | Dict value');
            $blueprint->integer('sort')->default(0)->comment('排序 | Sort');
            $blueprint->tinyInteger('status')->default(1)->comment('状态 | Status');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');

            $blueprint->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sys_dicts');
    }
}
