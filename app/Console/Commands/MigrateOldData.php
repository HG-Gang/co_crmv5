<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 01:56
 */

/**
 * 旧 CRM 数据迁移命令。
 *
 * 文件功能：
 * - 将旧 CRM 数据库（old_crm 连接）中有效用户（user）与代理（agents）数据
 *   迁移到新库 user_logins / user_infos / user_auths 三张表；
 * - 迁移前可备份新表、清空目标表；迁移后执行数据校验并将所有密码重置为
 *   abc123，最后输出测试账号。
 *
 * 适用场景：
 * - 系统升级从旧 CRM 切换到新系统时手动执行的一次性数据迁移。
 *
 * 入参例子：
 * - php artisan migrate:old-data              # 实际执行（需交互确认）
 * - php artisan migrate:old-data --dry-run    # 模拟运行，不实际写入
 *
 * 返回值：
 * - 0=迁移成功完成；
 * - 1=用户取消确认或迁移过程中抛出异常。
 *
 * 异常或失败场景：
 * - 旧数据库无法连接、新库写入失败等均抛出异常，命令捕获后输出错误并返回 1；
 * - 单条用户迁移失败（insertUser 抛异常）只计数失败，不中断整体流程。
 */
namespace App\Console\Commands;

use App\Services\FamilyTreeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MigrateOldData extends Command
{
    /** @var string 命令签名：--dry-run 模拟运行不实际执行。 */
    protected $signature = 'migrate:old-data {--dry-run : 模拟运行不实际执行}';

    /** @var string 命令说明。 */
    protected $description = '从旧CRM系统迁移用户数据到新系统';

    /**
     * 执行命令：按步骤检查旧库、备份/清空新表、迁移用户与代理、校验数据并重置密码。
     *
     * @return int 0=成功；1=取消或失败。
     */
    public function handle(FamilyTreeService $familyTreeService)
    {
        $this->info('========================================');
        $this->info('   旧CRM数据迁移工具');
        $this->info('========================================');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('模拟运行模式，不会实际执行迁移。');
            $this->newLine();
        }

        if (!$this->confirm('确认开始数据迁移？此操作将清空user_logins、user_infos、user_auths表', false)) {
            $this->error('已取消迁移');
            return 1;
        }

        try {
            // 步骤1：检查旧数据库
            $this->info('步骤1: 检查旧数据库连接...');
            $oldStats = $this->checkOldDatabase();
            $this->newLine();

            // 步骤2：备份新表
            if (!$this->option('dry-run')) {
                $this->info('步骤2: 备份新表数据...');
                $this->backupNewTables();
                $this->newLine();
            }

            // 步骤3：清空目标表
            if (!$this->option('dry-run')) {
                $this->info('步骤3: 清空目标表...');
                $this->truncateTables();
                $this->newLine();
            }

            // 步骤4：迁移user表
            $this->info('步骤4: 迁移user表数据...');
            $userStats = $this->migrateUsers($this->option('dry-run'));
            $this->newLine();

            // 步骤5：迁移agents表
            $this->info('步骤5: 迁移agents表数据...');
            $agentStats = $this->migrateAgents($this->option('dry-run'));
            $this->newLine();

            // 步骤6：数据校验
            if (!$this->option('dry-run')) {
                $this->info('步骤6: 按 parent_id 重建代理家谱与闭包...');
                $hierarchyStats = $familyTreeService->rebuildAllHierarchy();
                $this->line("  活动用户: {$hierarchyStats['users']}，闭包关系: {$hierarchyStats['relations']}");
                $this->newLine();

                $this->info('步骤7: 数据校验...');
                $validation = $this->validateData();
                $this->newLine();

                // 步骤8：重置所有密码为abc123
                $this->info('步骤8: 重置所有密码为 abc123...');
                $this->resetAllPasswords();
                $this->newLine();

                // 显示测试账号
                $this->info('========================================');
                $this->info('   迁移完成！测试账号信息');
                $this->info('========================================');
                $this->displayTestAccounts();
            }

            $this->info('数据迁移成功完成。');
            return 0;

        } catch (\Exception $e) {
            $this->error('数据迁移失败：' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * 检查旧数据库连接并统计可迁移记录数。
     *
     * @return array{userCount: int, agentCount: int} 旧库 user 与 agents 表的有效记录数。
     * @throws \Exception 旧数据库无法连接时抛出。
     */
    protected function checkOldDatabase()
    {
        try {
            $userCount = DB::connection('old_crm')->table('user')->where('voided', '1')->count();
            $agentCount = DB::connection('old_crm')->table('agents')->where('voided', '1')->count();

            $this->line("  旧user表记录数: {$userCount}");
            $this->line("  旧agents表记录数: {$agentCount}");
            $this->line("  预计迁移总数: " . ($userCount + $agentCount));

            return compact('userCount', 'agentCount');

        } catch (\Exception $e) {
            throw new \Exception('无法连接旧数据库: ' . $e->getMessage());
        }
    }

    /**
     * 备份新库三张目标表（仅当表内有数据时，备份表名加时间后缀）。
     *
     * @return void 无返回值。
     */
    protected function backupNewTables()
    {
        $tables = ['user_logins', 'user_infos', 'user_auths', 'agent_descendants'];
        $backupSuffix = date('YmdHis');

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            if ($count > 0) {
                $backupTable = "{$table}_backup_{$backupSuffix}";
                DB::statement("CREATE TABLE {$backupTable} LIKE {$table}");
                DB::statement("INSERT INTO {$backupTable} SELECT * FROM {$table}");
                $this->line("  备份 {$table} -> {$backupTable} ({$count}条)");
            }
        }
    }

    /**
     * 清空目标表 user_logins / user_infos / user_auths（临时关闭外键检查）。
     *
     * @return void 无返回值。
     */
    protected function truncateTables()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('agent_descendants')->truncate();
        DB::table('user_auths')->truncate();
        DB::table('user_infos')->truncate();
        DB::table('user_logins')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->line('  目标表已清空');
    }

    /**
     * 迁移旧库 user 表数据（account_type=2 客户）。
     *
     * @param bool $dryRun 是否模拟运行（true 只计数不写入）。
     * @return array{total: int, success: int, failed: int} 总数/成功/失败统计。
     */
    protected function migrateUsers($dryRun = false)
    {
        $users = DB::connection('old_crm')
            ->table('user')
            ->where('voided', '1')
            ->get();

        $total = $users->count();
        $success = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($users as $user) {
            if (!$dryRun) {
                try {
                    $this->insertUser($user, 2); // account_type=2 客户
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                }
            } else {
                $success++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  成功: {$success}, 失败: {$failed}");

        return compact('total', 'success', 'failed');
    }

    /**
     * 迁移旧库 agents 表数据（account_type=1 代理，已存在的 user_id 跳过）。
     *
     * @param bool $dryRun 是否模拟运行（true 只计数不写入）。
     * @return array{total: int, success: int, failed: int} 总数/成功/失败统计。
     */
    protected function migrateAgents($dryRun = false)
    {
        $agents = DB::connection('old_crm')
            ->table('agents')
            ->where('voided', '1')
            ->get();

        $total = $agents->count();
        $success = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($agents as $agent) {
            if (!$dryRun) {
                try {
                    // 检查是否已存在
                    $exists = DB::table('user_logins')->where('user_id', $agent->user_id)->exists();
                    if (!$exists) {
                        $this->insertUser($agent, 1); // account_type=1 代理
                        $success++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                }
            } else {
                $success++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  成功: {$success}, 失败: {$failed}");

        return compact('total', 'success', 'failed');
    }

    /**
     * 在事务内将单条旧记录写入新库三张表（user_logins / user_infos / user_auths）。
     *
     * @param object $user 旧库记录对象（user 或 agents 行）。
     * @param int $accountType 账号类型：1=代理，2=客户。
     * @return void 无返回值；任一步失败则回滚并抛出异常。
     * @throws \Exception 三表任一写入失败时抛出（由调用方计数）。
     */
    protected function insertUser($user, $accountType)
    {
        DB::beginTransaction();

        try {
            // 插入user_logins
            DB::table('user_logins')->insert([
                'user_id' => $user->user_id,
                'email' => $user->email,
                'password' => Hash::make('abc123'),
                'account_type' => $accountType,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 0,
                'last_login_ip' => '',
                'last_login_at' => $user->last_logindate ?? null,
                'created_at' => strtotime($user->rec_crt_date ?? 'now'),
                'updated_at' => strtotime($user->rec_upd_date ?? 'now'),
            ]);

            // 查询头像
            $avatar = DB::connection('old_crm')
                ->table('user_img')
                ->where('user_id', $user->user_id)
                ->where('voided', '1')
                ->value('img_header_path') ?? '';

            // 插入user_infos
            DB::table('user_infos')->insert([
                'user_id' => $user->user_id,
                'login_id' => $user->user_id,
                'user_name' => $user->user_name ?? '',
                'phone' => $user->phone ?? '',
                'gender' => $this->convertGender($user->sex ?? ''),
                'avatar' => $avatar,
                'level_id' => $user->local_level ?? 0,
                'group_id' => $user->group_id ?? 0,
                'parent_id' => $user->parent_id ?? 0,
                'account_type' => $accountType,
                'family_tree' => $user->family_tree ?? '',
                'total_funds' => $user->user_money ?? 0,
                'used_margin' => $user->used_bond_money ?? 0,
                'avail_margin' => $user->available_bond_money ?? 0,
                'equity' => $user->cust_eqy ?? 0,
                'effective_credit' => $user->effective_cdt ?? 0,
                'risk_ratio' => $user->risk_rate ?? 0,
                'margin_amount' => $user->bond_money ?? 0,
                'leverage' => $user->cust_lvg ?? 0,
                'cust_vol' => $user->cust_vol ?? '0',
                'pay_provider_id' => $user->player_Id ?? 0,
                'comm_rate' => $user->commprop ?? 0,
                'is_ecn' => $user->is_enc ?? 0,
                'follow_parent_ecn' => $user->enc_look ?? 0,
                'auth_status' => $this->convertStatus($user->user_status ?? '0'),
                'is_mt4_synced' => 1,
                'is_mt4_enabled' => 1,
                'is_mt4_readonly' => 0,
                'is_withdrawal_allowed' => 0,
                'is_deposit_allowed' => 0,
                'is_agent_confirmed' => 0,
                'original_group' => $user->original_grp ?? '',
                'mt4_group' => $user->mt4_grp ?? '',
                'mt4_code' => $user->mt4_code ?? 0,
                'trading_mode' => intval($user->trans_mode ?? 0),
                'settle_method' => 1,
                'settle_cycle' => 0,
                'country' => '',
                'city' => '',
                'state' => '',
                'address' => null,
                'is_gift_allowed' => 0,
                'data_source' => 0,
                'remark' => '',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => strtotime($user->rec_crt_date ?? 'now'),
                'updated_at' => strtotime($user->rec_upd_date ?? 'now'),
            ]);

            // 插入user_auths
            DB::table('user_auths')->insert([
                'user_id' => $user->user_id,
                'bank_no' => $user->bank_no ?? '',
                'bank_name' => $user->bank_class ?? '',
                'bank_card_img' => $user->bank_img ?? '',
                'bank_card_img_tmp' => $user->bank_img_tmp ?? '',
                'bank_addr' => '',
                'bank_addr_tmp' => '',
                'bank_status' => 0,
                'bank_remarks' => '',
                'id_card_no' => $user->idcard_no ?? '',
                'id_card_status' => 0,
                'id_card_front' => $user->idcard_front ?? '',
                'id_card_back' => $user->idcard_back ?? '',
                'id_card_remarks' => '',
                'is_bank_synced' => 0,
                'created_at' => strtotime($user->rec_crt_date ?? 'now'),
                'updated_at' => strtotime($user->rec_upd_date ?? 'now'),
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 校验迁移后新库数据量、代理/客户数量。
     *
     * @return array{newLogins: int, newInfos: int, newAuths: int, agents: int, customers: int}
     *         新库各表计数与账号类型统计。
     */
    protected function validateData()
    {
        $newLogins = DB::table('user_logins')->count();
        $newInfos = DB::table('user_infos')->count();
        $newAuths = DB::table('user_auths')->count();

        $this->line("  user_logins: {$newLogins}");
        $this->line("  user_infos: {$newInfos}");
        $this->line("  user_auths: {$newAuths}");

        $agents = DB::table('user_logins')->where('account_type', 1)->count();
        $customers = DB::table('user_logins')->where('account_type', 2)->count();

        $this->line("  代理数: {$agents}");
        $this->line("  客户数: {$customers}");

        return compact('newLogins', 'newInfos', 'newAuths', 'agents', 'customers');
    }

    /**
     * 输出代理/客户测试账号样例与登录地址。
     *
     * @return void 无返回值，仅向控制台输出。
     */
    protected function displayTestAccounts()
    {
        // 代理账号
        $this->newLine();
        $this->info('【代理商测试账号】');
        $agents = DB::table('user_logins')
            ->join('user_infos', 'user_logins.user_id', '=', 'user_infos.user_id')
            ->where('user_logins.account_type', 1)
            ->select('user_logins.email', 'user_logins.user_id', 'user_infos.user_name', 'user_infos.level_id')
            ->limit(5)
            ->get();

        foreach ($agents as $agent) {
            $this->line("  {$agent->email}");
            $this->line("     ID: {$agent->user_id} | 姓名: {$agent->user_name} | 级别: {$agent->level_id}");
            $this->line("     密码: abc123");
            $this->newLine();
        }

        // 客户账号
        $this->info('【普通客户测试账号】');
        $customers = DB::table('user_logins')
            ->join('user_infos', 'user_logins.user_id', '=', 'user_infos.user_id')
            ->where('user_logins.account_type', 2)
            ->select('user_logins.email', 'user_logins.user_id', 'user_infos.user_name')
            ->limit(5)
            ->get();

        foreach ($customers as $customer) {
            $this->line("  {$customer->email}");
            $this->line("     ID: {$customer->user_id} | 姓名: {$customer->user_name}");
            $this->line("     密码: abc123");
            $this->newLine();
        }

        $this->info('========================================');
        $this->info('统一密码: abc123');
        $this->info('登录地址:');
        $this->line('  代理: ' . config('app.url') . '/agent/login');
        $this->line('  客户: ' . config('app.url') . '/customer/login');
        $this->info('========================================');
    }

    /**
     * 将全部用户密码批量重置为 abc123。
     *
     * @return void 无返回值，输出受影响行数。
     */
    protected function resetAllPasswords()
    {
        $exitCode = $this->call('password:reset-all', [
            'password' => 'abc123',
            '--force' => true,
        ]);
        if ($exitCode !== self::SUCCESS) {
            throw new \RuntimeException('全部认证账号密码重置失败。');
        }

        $this->line('  全部认证账号密码已统一重置并验证。');
    }

    /**
     * 旧库性别字段转数字：'女'=2，其余（含'男'）=1。
     *
     * @param string $sex 旧库性别原文。
     * @return int 1=男，2=女。
     */
    protected function convertGender($sex)
    {
        return $sex === '女' ? 2 : 1;
    }

    /**
     * 旧库用户状态转新库 auth_status：'0'=0、'1'=1、'2'=2、'-1'=3。
     *
     * @param string $status 旧库状态值。
     * @return int 新库状态码，未知值归 0。
     */
    protected function convertStatus($status)
    {
        $map = ['0' => 0, '1' => 1, '2' => 2, '-1' => 3];
        return $map[$status] ?? 0;
    }
}
