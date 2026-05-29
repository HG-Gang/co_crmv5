<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAuthsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_auths', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('bank_no', 50)->default('')->comment('银行卡号 | Bank number');
            $blueprint->string('bank_name', 255)->default('')->comment('银行名称 | Bank name');
            $blueprint->string('bank_card_img', 500)->default('')->comment('银行卡图片 | Bank card image');
            $blueprint->string('bank_card_img_tmp', 500)->default('')->comment('银行卡临时图片 | Bank card temp image');
            $blueprint->string('bank_addr', 500)->default('')->comment('分行地址 | Branch address');
            $blueprint->string('bank_addr_tmp', 500)->default('')->comment('分行临时地址 | Branch temp address');
            $blueprint->tinyInteger('bank_status')->default(0)->comment('银行卡状态: 0=未通过 1=审核中 2=已通过 3=变更中 4=已拒绝 | Bank status: 0=not passed 1=reviewing 2=passed 3=changed 4=rejected');
            $blueprint->string('bank_remarks', 500)->default('')->comment('银行备注 | Bank remarks');
            $blueprint->string('id_card_no', 50)->default('')->comment('身份证号 | ID card number');
            $blueprint->tinyInteger('id_card_status')->default(0)->comment('身份证状态: 0=未通过 1=审核中 2=已通过 4=已退回 | ID card status: 0=not passed 1=reviewing 2=passed 4=returned');
            $blueprint->string('id_card_front', 500)->default('')->comment('身份证正面 | ID front');
            $blueprint->string('id_card_back', 500)->default('')->comment('身份证背面 | ID back');
            $blueprint->string('id_card_remarks', 500)->default('')->comment('身份证备注 | ID remarks');
            $blueprint->tinyInteger('is_bank_synced')->default(0)->comment('银行信息同步 | Bank synced');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_auths');
    }
}
