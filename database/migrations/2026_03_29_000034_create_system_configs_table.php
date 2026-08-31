<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建系统配置表 system_configs。
 *
 * 文件功能：
 * - 键值对形式的系统参数存储：出入金开关、费率、汇率、提现时段等。
 *
 * 字段语义：
 * - key 配置键（唯一）；value 配置值（文本）；group 分组（如 general/withdrawal）；
 * - description 配置说明（迁移占位值依赖该字段识别）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_configs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('key', 100)->unique()->comment('配置键 | Key');
            $blueprint->text('value')->nullable()->comment('配置值 | Value');
            $blueprint->string('group', 50)->default('general')->comment('分组 | Group');
            $blueprint->string('description', 500)->default('')->comment('描述 | Description');
            
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
        Schema::dropIfExists('system_configs');
    }
}
