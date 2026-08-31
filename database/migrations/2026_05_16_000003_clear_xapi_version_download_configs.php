<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 清理 xapi 版本下载相关系统配置。
 *
 * 文件功能：
 * - 删除旧项目遗留的 xapi 版本下载配置项（system_configs 数据清理）。
 *
 * 字段语义：
 * - 仅操作 system_configs 字典数据，不涉及表结构；回滚不恢复已删除配置（一次性清理）。
 */

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
