<?php

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
