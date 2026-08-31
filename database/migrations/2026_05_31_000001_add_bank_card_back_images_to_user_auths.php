<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 为 user_auths 补充银行卡背面图片字段。
 *
 * 文件功能：
 * - 增加 bank_card_back_img（银行卡背面照片），完善实名认证资料。
 *
 * 字段语义：
 * - bank_card_back_img 可空字符串（存储路径）；回滚时仅删除该字段。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBankCardBackImagesToUserAuths extends Migration
{
    public function up()
    {
        Schema::table('user_auths', function (Blueprint $table) {
            if (!Schema::hasColumn('user_auths', 'bank_card_back_img')) {
                $table->string('bank_card_back_img', 500)->default('')->after('bank_card_img');
            }
            if (!Schema::hasColumn('user_auths', 'bank_card_back_img_tmp')) {
                $table->string('bank_card_back_img_tmp', 500)->default('')->after('bank_card_img_tmp');
            }
        });
    }

    public function down()
    {
        Schema::table('user_auths', function (Blueprint $table) {
            if (Schema::hasColumn('user_auths', 'bank_card_back_img')) {
                $table->dropColumn('bank_card_back_img');
            }
            if (Schema::hasColumn('user_auths', 'bank_card_back_img_tmp')) {
                $table->dropColumn('bank_card_back_img_tmp');
            }
        });
    }
}
