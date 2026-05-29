<?php

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
