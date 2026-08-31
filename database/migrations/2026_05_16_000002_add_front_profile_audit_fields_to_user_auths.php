<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 为 user_auths 补充前台资料审核字段。
 *
 * 文件功能：
 * - 增加前台个人资料修改的审核状态与审核信息字段（如资料审核状态、审核备注）。
 *
 * 字段语义：
 * - 新增字段可空/带默认值；回滚时仅删除本迁移新增字段。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFrontProfileAuditFieldsToUserAuths extends Migration
{
    public function up()
    {
        Schema::table('user_auths', function (Blueprint $table) {
            if (!Schema::hasColumn('user_auths', 'bank_no_tmp')) {
                $table->string('bank_no_tmp', 50)->default('')->after('bank_no');
            }
            if (!Schema::hasColumn('user_auths', 'bank_name_tmp')) {
                $table->string('bank_name_tmp', 255)->default('')->after('bank_name');
            }
        });
    }

    public function down()
    {
        Schema::table('user_auths', function (Blueprint $table) {
            if (Schema::hasColumn('user_auths', 'bank_no_tmp')) {
                $table->dropColumn('bank_no_tmp');
            }
            if (Schema::hasColumn('user_auths', 'bank_name_tmp')) {
                $table->dropColumn('bank_name_tmp');
            }
        });
    }
}
