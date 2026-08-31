<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建凭证（代金券）信息表 voucher_infos。
 *
 * 文件功能：
 * - 用户代金券/凭证：批次、面值、有效期、状态与使用记录。
 *
 * 字段语义：
 * - user_id 所属用户；code 凭证码（唯一）；amount 面值；
 * - expire_at 到期时间；status 状态（未使用/已使用/已过期）；
 * - used_at 使用时间；source 发放来源。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVoucherInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_infos', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('images', 2000)->default('')->comment('凭证图片 | Images');
            $blueprint->string('remarks', 2000)->default('')->comment('备注 | Remarks');
            $blueprint->tinyInteger('review_status')->default(0)->comment('审核状态: 0=待处理 1=通过 2=拒绝 | Review status: 0=pending 1=approved 2=rejected');
            $blueprint->string('review_message', 2000)->default('')->comment('审核留言 | Review message');
            $blueprint->string('created_by', 100)->default('')->comment('创建人 | Created by');
            $blueprint->string('updated_by', 100)->default('')->comment('更新人 | Updated by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_infos');
    }
}
