<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 21:18
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修复固定前台演示账户的 MT4 编号不变量。
 *
 * 文件功能：
 * - 仅识别 FrontDemoDataSeeder 维护的 9 个固定 user_id 与精确登录邮箱。
 * - 修复 is_mt4_synced=1、is_mt4_enabled=1 但 mt4_code=0 的历史演示资料。
 * - 保留真实迁移账户、测试夹具和任何非零 MT4 编号，避免扩大数据修改范围。
 *
 * 返回值：
 * - up 成功后，匹配的固定演示账户满足 mt4_code=user_id，可通过新版登录前置校验。
 * - 必要表或字段不存在时安全跳过；数据库更新失败时抛出异常并终止迁移。
 */
class RepairFrontDemoMt4Codes extends Migration
{
    /** @var array<int, string> DEMO_IDENTITIES 固定演示业务编号到登录邮箱的白名单。 */
    private const DEMO_IDENTITIES = [
        1001 => 'agent@test.com',
        1101 => 'subagent1@test.com',
        1102 => 'subagent2@test.com',
        600101 => 'customer1@test.com',
        600102 => 'customer2@test.com',
        600103 => 'customer3@test.com',
        600104 => 'customer4@test.com',
        600105 => 'customer5@test.com',
        600106 => 'customer6@test.com',
    ];

    /**
     * 修复精确匹配的固定演示账户。
     *
     * @return void 更新成功或前置表结构不存在时无返回值。
     */
    public function up(): void
    {
        if (!$this->hasRequiredSchema()) {
            return;
        }

        foreach (self::DEMO_IDENTITIES as $userId => $email) {
            $loginId = (int) DB::table('user_logins')
                ->where('user_id', $userId)
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->value('id');
            if ($loginId <= 0) {
                continue;
            }

            DB::table('user_infos')
                ->where('user_id', $userId)
                ->where('login_id', $loginId)
                ->where('is_mt4_synced', 1)
                ->where('is_mt4_enabled', 1)
                ->where('mt4_code', 0)
                ->whereNull('deleted_at')
                ->update([
                    'mt4_code' => $userId,
                    'updated_at' => time(),
                ]);
        }
    }

    /**
     * 保留修复后的可登录状态，不恢复已知错误的 MT4 占位编号。
     *
     * @return void 不修改任何业务数据。
     */
    public function down(): void
    {
        // mt4_code=user_id 是登录安全不变量，回滚迁移记录时不能重新制造不可登录账户。
    }

    /**
     * 检查迁移所需数据表和关键字段是否完整。
     *
     * @return bool 结构完整返回 true，否则返回 false 并由 up 安全跳过。
     */
    private function hasRequiredSchema(): bool
    {
        return Schema::hasTable('user_logins')
            && Schema::hasTable('user_infos')
            && Schema::hasColumn('user_logins', 'user_id')
            && Schema::hasColumn('user_logins', 'email')
            && Schema::hasColumn('user_infos', 'user_id')
            && Schema::hasColumn('user_infos', 'login_id')
            && Schema::hasColumn('user_infos', 'is_mt4_synced')
            && Schema::hasColumn('user_infos', 'is_mt4_enabled')
            && Schema::hasColumn('user_infos', 'mt4_code');
    }
}
