<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建业务编号序列表 id_sequences。
 *
 * 文件功能：
 * - 为代理/客户生成全局唯一业务 ID 的计数器表（替代自增主键暴露业务量）。
 *
 * 字段语义：
 * - type 序列类型（agent 代理 / customer 客户，唯一）；current_value 当前值（取号后自增）；
 * - prefix 编号前缀（当前为空）；step 步长（默认 1）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIdSequencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('id_sequences', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('type', 50)->unique()->comment('类型: agent or customer | Type: agent or customer');
            $blueprint->bigInteger('current_value')->comment('当前值 | Current value');
            $blueprint->string('prefix', 10)->default('')->comment('前缀 | Prefix');
            $blueprint->integer('step')->default(1)->comment('步长 | Step');
            
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
        Schema::dropIfExists('id_sequences');
    }
}
