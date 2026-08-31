<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建 WHS 体验零值记录表 whs_exp_zeros。
 *
 * 文件功能：
 * - 记录体验账户/净值清零操作的明细，供风控与对账追溯。
 *
 * 字段语义：
 * - user_id 用户 ID；amount 清零金额；reason 清零原因；
 * - operator_id 操作人；status 处理状态；remark 备注。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhsExpZerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whs_exp_zeros', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('user_name', 100)->comment('用户名 | User name');
            $blueprint->double('balance', 50, 2)->default(0)->comment('余额 | Balance');
            $blueprint->double('credit', 50, 2)->default(0)->comment('信用额 | Credit');
            $blueprint->tinyInteger('status')->default(1)->comment('状态: 1=待处理 2=已清零 | Status: 1=pending 2=cleared');
            $blueprint->string('md5_key', 100)->comment('MD5标识 | MD5 key');
            $blueprint->string('created_by', 100)->default('')->comment('创建人 | Created by');
            $blueprint->string('updated_by', 100)->default('')->comment('更新人 | Updated by');
            
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
        Schema::dropIfExists('whs_exp_zeros');
    }
}
