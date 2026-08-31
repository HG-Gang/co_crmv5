<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建系统字典表 sys_dicts。
 *
 * 文件功能：
 * - 通用数据字典（字典类型 + 键值对），供下拉选项等配置使用。
 *
 * 字段语义：
 * - dict_type 字典类型；label 显示名称；value 字典值；
 * - sort 排序权重；status 状态（1=启用 0=停用）；remark 备注。
 */

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
