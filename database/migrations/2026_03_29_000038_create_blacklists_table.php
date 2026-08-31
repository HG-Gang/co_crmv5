<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建黑名单表 blacklists。
 *
 * 文件功能：
 * - 记录被禁止的用户/设备/IP（类型化黑名单），用于风控拦截。
 *
 * 字段语义：
 * - target_type 拉黑对象类型（用户/设备/IP 等）；target_value 被拉黑的值；
 * - reason 拉黑原因；operator_id 操作人；status 状态（1=生效 0=失效）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlacklistsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blacklists', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('name', 100)->comment('姓名 | Name');
            $blueprint->string('id_card', 50)->comment('身份证号 | ID card');
            $blueprint->string('email', 100)->comment('邮箱 | Email');
            $blueprint->string('phone', 30)->comment('电话 | Phone');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('id_card');
            $blueprint->index('email');
            $blueprint->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blacklists');
    }
}
