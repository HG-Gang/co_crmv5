<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 为 cancel_applies 补充取消备注字段。
 *
 * 文件功能：
 * - 增加 cancel_remark（销户取消原因/备注），供审核记录与前台展示。
 *
 * 字段语义：
 * - cancel_remark 可空字符串；回滚时仅删除该字段，不影响其他销户数据。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCancelRemarkToCancelAppliesTable extends Migration
{
    /**
     * Add the user-submitted cancellation reason restored from the old CRM.
     *
     * hank_zl_data.cancel_apply used cancel_remark for the applicant's reason.
     * co_crmv5 previously kept only reject_reason, which belongs to the admin
     * review step.  Keeping both fields avoids mixing applicant and reviewer text.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cancel_applies', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('cancel_applies', 'cancel_remark')) {
                $blueprint->string('cancel_remark', 500)->default('')->after('status')->comment('Cancellation reason submitted by user');
            }
        });
    }

    /**
     * Remove the restored applicant cancellation reason field.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cancel_applies', function (Blueprint $blueprint) {
            if (Schema::hasColumn('cancel_applies', 'cancel_remark')) {
                $blueprint->dropColumn('cancel_remark');
            }
        });
    }
}
