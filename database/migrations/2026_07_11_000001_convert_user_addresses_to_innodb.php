<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 将 user_addresses 表转换为 InnoDB 引擎。
 *
 * 文件功能：
 * - 将 user_addresses 从 MyISAM 转换为 InnoDB，支持事务、行锁与测试回滚。
 *
 * 字段语义：
 * - 仅修改存储引擎，不改字段定义；回滚不降级（保持 InnoDB，避免数据风险）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConvertUserAddressesToInnodb extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_addresses')) {
            DB::statement('ALTER TABLE user_addresses ENGINE = InnoDB');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_addresses')) {
            DB::statement('ALTER TABLE user_addresses ENGINE = MyISAM');
        }
    }
}
