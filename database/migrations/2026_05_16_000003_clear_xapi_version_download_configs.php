<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_configs')) {
            return;
        }

        DB::table('system_configs')
            ->whereIn('key', [
                'download_pc_url',
                'pc_download_url',
                'client_pc_download_url',
                'download_mobile_url',
                'mobile_download_url',
                'app_download_url',
            ])
            ->where(function ($query) {
                $query->where('value', 'like', '%xapi.yhchj.com/version%')
                    ->orWhere('value', 'like', '%/version%');
            })
            ->update([
                'value' => '#',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally no-op: the removed endpoint was an external version probe.
    }
};
