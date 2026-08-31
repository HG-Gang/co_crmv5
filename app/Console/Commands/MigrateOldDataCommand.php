<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 01:56
 */

/**
 * 旧项目数据迁移执行类（遗留脚本类）。
 *
 * 文件功能：
 * - 将旧 CRM 数据库（old_crm 连接）中有效用户（user）与代理（agents）数据
 *   迁移到新库 user_logins / user_infos / user_auths 三张表；
 * - 迁移前备份新表、清空目标表；迁移后校验数据完整性、统计重复邮箱与
 *   表间一致性，并生成测试账号清单。
 *
 * 适用场景：
 * - 系统升级切换数据库时手动执行的一次性迁移脚本（非 Artisan Command，
 *   由外部调用 execute() 触发）。
 *
 * 入参例子：
 * - $migrator = new MigrateOldDataCommand(); $result = $migrator->execute();
 *
 * 返回值：
 * - execute() 成功返回数组：新旧库各表记录数、数据完整性标记、
 *   重复邮箱数、代理/客户数量及测试账号清单；
 * - 失败时抛出异常（异常信息同时写入日志）。
 *
 * 异常或失败场景：
 * - 旧数据库无法连接、迁移写入失败等抛出 \Exception；
 * - 单条记录迁移失败仅计数（成功/失败数），不中断整体流程；
 * - execute() 捕获异常后记录错误日志并重新抛出。
 */

namespace App\Console\Commands;

use App\Services\FamilyTreeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class MigrateOldDataCommand
{
    /** @var string 旧数据库连接名。 */
    protected $oldDb = 'crm_db';

    /** @var string 新数据库连接名。 */
    protected $newDb = 'co_crmv5';

    /**
     * 执行完整迁移流程：检查旧库、备份新表、清空目标表、迁移 user/agents、
     * 数据校验并生成测试账号。
     *
     * @return array 迁移结果数组（各表计数、完整性标记、重复邮箱数、
     *               代理/客户数量、测试账号清单）。
     * @throws \Exception 任一步骤失败时抛出（并记录错误日志）。
     */
    public function execute()
    {
        try {
            Log::info('开始执行旧项目数据迁移');

            // 1. 检查旧数据库连接
            $this->checkOldDatabase();

            // 2. 备份新表
            $this->backupNewTables();

            // 3. 清空目标表
            $this->truncateTargetTables();

            // 4. 迁移user表数据
            $this->migrateUserTable();

            // 5. 迁移agents表数据
            $this->migrateAgentsTable();

            // 6. parent_id 是直属拓扑事实源，旧 family_tree 只作迁移输入审计。
            $hierarchy = app(FamilyTreeService::class)->rebuildAllHierarchy();
            Log::info('代理层级派生数据重建完成', $hierarchy);

            // 7. 五类认证账号统一重置为 abc123，并在命令内部完成逐表一致性验证。
            $passwordExitCode = Artisan::call('password:reset-all', [
                'password' => 'abc123',
                '--force' => true,
            ]);
            if ($passwordExitCode !== 0) {
                throw new \RuntimeException('全部认证账号密码重置失败。');
            }

            // 8. 数据校验
            $result = $this->validateMigration();

            Log::info('数据迁移完成', $result);

            return $result;

        } catch (\Exception $e) {
            Log::error('数据迁移失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 检查旧数据库连接并统计 user 表有效记录数。
     *
     * @return int 旧库 user 表 voided='1' 的记录数。
     * @throws \Exception 旧数据库无法连接时抛出。
     */
    protected function checkOldDatabase()
    {
        try {
            $count = DB::connection('old_crm')
                ->table('user')
                ->where('voided', '1')
                ->count();

            echo "旧数据库连接成功，user表记录数: {$count}\n";
            return $count;

        } catch (\Exception $e) {
            throw new \Exception("无法连接旧数据库: " . $e->getMessage());
        }
    }

    /**
     * 备份新库三张目标表（仅当表内有数据时，备份表名加时间后缀）。
     *
     * @return void 无返回值。
     */
    protected function backupNewTables()
    {
        echo "正在备份新表数据...\n";

        $tables = ['user_logins', 'user_infos', 'user_auths', 'agent_descendants'];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            if ($count > 0) {
                $backupTable = $table . '_backup_' . date('YmdHis');
                DB::statement("CREATE TABLE {$backupTable} LIKE {$table}");
                DB::statement("INSERT INTO {$backupTable} SELECT * FROM {$table}");
                echo "备份 {$table} 到 {$backupTable}，记录数: {$count}\n";
            }
        }
    }

    /**
     * 清空目标表 user_logins / user_infos / user_auths（临时关闭外键检查）。
     *
     * @return void 无返回值。
     */
    protected function truncateTargetTables()
    {
        echo "正在清空目标表...\n";

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('agent_descendants')->truncate();
        DB::table('user_auths')->truncate();
        DB::table('user_infos')->truncate();
        DB::table('user_logins')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        echo "目标表已清空\n";
    }

    /**
     * 迁移旧库 user 表数据到新库三张表（account_type=2 客户）。
     *
     * @return void 无返回值；单条失败仅计数，输出成功/失败统计。
     */
    protected function migrateUserTable()
    {
        echo "正在迁移user表数据...\n";

        // 查询旧数据
        $users = DB::connection('old_crm')
            ->table('user')
            ->where('voided', '1')
            ->get();

        $successCount = 0;
        $errorCount = 0;

        foreach ($users as $user) {
            try {
                DB::beginTransaction();

                // 1. 插入user_logins
                DB::table('user_logins')->insert([
                    'user_id' => $user->user_id,
                    'email' => $user->email,
                    'password' => Hash::make('abc123'),
                    'account_type' => 2, // 普通客户
                    'is_enabled' => ($user->voided == '1' && $user->local_enable == 0) ? 1 : 0,
                    'is_cancelled' => 0,
                    'source_type' => 0,
                    'last_login_ip' => '',
                    'last_login_at' => $user->last_logindate ?? null,
                    'created_at' => strtotime($user->rec_crt_date ?? 'now'),
                    'updated_at' => strtotime($user->rec_upd_date ?? 'now'),
                ]);

                // 2. 查询头像
                $avatar = DB::connection('old_crm')
                    ->table('user_img')
                    ->where('user_id', $user->user_id)
                    ->where('voided', '1')
                    ->value('img_header_path');

                // 3. 插入user_infos
                DB::table('user_infos')->insert([
                    'user_id' => $user->user_id,
                    'login_id' => $user->user_id,
                    'user_name' => $user->user_name ?? '',
                    'phone' => $user->phone ?? '',
                    'gender' => $this->convertGender($user->sex ?? ''),
                    'avatar' => $avatar ?? '',
                    'level_id' => $user->local_level ?? 0,
                    'group_id' => $user->group_id ?? 0,
                    'parent_id' => $user->parent_id ?? 0,
                    'account_type' => 2,
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
                    'comm_rate' => $user->commprop ?? $user->comm_prop ?? 0,
                    'is_ecn' => $user->is_enc ?? 0,
                    'follow_parent_ecn' => $user->enc_look ?? 0,
                    'auth_status' => $this->convertUserStatus($user->user_status ?? '0'),
                    'is_mt4_synced' => ($user->voided == '2') ? 0 : 1,
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

                // 4. 插入user_auths
                DB::table('user_auths')->insert([
                    'user_id' => $user->user_id,
                    'bank_no' => $user->bank_no ?? '',
                    'bank_name' => $user->bank_class ?? '',
                    'bank_card_img' => $user->bank_img ?? '',
                    'bank_card_img_tmp' => $user->bank_img_tmp ?? '',
                    'bank_addr' => '',
                    'bank_addr_tmp' => '',
                    'bank_status' => $this->calcBankStatus($user),
                    'bank_remarks' => '',
                    'id_card_no' => $user->idcard_no ?? '',
                    'id_card_status' => $this->calcIdCardStatus($user),
                    'id_card_front' => $user->idcard_front ?? '',
                    'id_card_back' => $user->idcard_back ?? '',
                    'id_card_remarks' => '',
                    'is_bank_synced' => 0,
                    'created_at' => strtotime($user->rec_crt_date ?? 'now'),
                    'updated_at' => strtotime($user->rec_upd_date ?? 'now'),
                ]);

                DB::commit();
                $successCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $errorCount++;
                echo "迁移用户 {$user->user_id} 失败: " . $e->getMessage() . "\n";
            }
        }

        echo "user表迁移完成: 成功 {$successCount}, 失败 {$errorCount}\n";
    }

    /**
     * 迁移旧库 agents 表数据到新库三张表（account_type=1 代理，已存在跳过）。
     *
     * @return void 无返回值；单条失败仅计数，输出成功/失败统计。
     */
    protected function migrateAgentsTable()
    {
        echo "正在迁移agents表数据...\n";

        $agents = DB::connection('old_crm')
            ->table('agents')
            ->where('voided', '1')
            ->get();

        $successCount = 0;
        $errorCount = 0;

        foreach ($agents as $agent) {
            try {
                // 检查是否已存在（避免重复）
                $exists = DB::table('user_logins')
                    ->where('user_id', $agent->user_id)
                    ->exists();

                if ($exists) {
                    echo "代理 {$agent->user_id} 已存在，跳过\n";
                    continue;
                }

                DB::beginTransaction();

                // 插入逻辑与user表类似，account_type改为1（代理）
                DB::table('user_logins')->insert([
                    'user_id' => $agent->user_id,
                    'email' => $agent->email,
                    'password' => Hash::make('abc123'),
                    'account_type' => 1, // 代理商
                    'is_enabled' => ($agent->voided == '1' && $agent->local_enable == 0) ? 1 : 0,
                    'is_cancelled' => 0,
                    'source_type' => 0,
                    'last_login_ip' => '',
                    'last_login_at' => $agent->last_logindate ?? null,
                    'created_at' => strtotime($agent->rec_crt_date ?? 'now'),
                    'updated_at' => strtotime($agent->rec_upd_date ?? 'now'),
                ]);

                $avatar = DB::connection('old_crm')
                    ->table('user_img')
                    ->where('user_id', $agent->user_id)
                    ->where('voided', '1')
                    ->value('img_header_path');

                DB::table('user_infos')->insert([
                    'user_id' => $agent->user_id,
                    'login_id' => $agent->user_id,
                    'user_name' => $agent->user_name ?? '',
                    'phone' => $agent->phone ?? '',
                    'gender' => $this->convertGender($agent->sex ?? ''),
                    'avatar' => $avatar ?? '',
                    'level_id' => $agent->local_level ?? 0,
                    'group_id' => $agent->group_id ?? 0,
                    'parent_id' => $agent->parent_id ?? 0,
                    'account_type' => 1, // 代理
                    'family_tree' => $agent->family_tree ?? '',
                    'total_funds' => $agent->user_money ?? 0,
                    'used_margin' => $agent->used_bond_money ?? 0,
                    'avail_margin' => $agent->available_bond_money ?? 0,
                    'equity' => $agent->cust_eqy ?? 0,
                    'effective_credit' => $agent->effective_cdt ?? 0,
                    'risk_ratio' => $agent->risk_rate ?? 0,
                    'margin_amount' => $agent->bond_money ?? 0,
                    'leverage' => $agent->cust_lvg ?? 0,
                    'cust_vol' => $agent->cust_vol ?? '0',
                    'pay_provider_id' => $agent->player_Id ?? 0,
                    'comm_rate' => $agent->commprop ?? $agent->comm_prop ?? 0,
                    'is_ecn' => $agent->is_enc ?? 0,
                    'follow_parent_ecn' => $agent->enc_look ?? 0,
                    'auth_status' => $this->convertUserStatus($agent->user_status ?? '0'),
                    'is_mt4_synced' => ($agent->voided == '2') ? 0 : 1,
                    'is_mt4_enabled' => 1,
                    'is_mt4_readonly' => 0,
                    'is_withdrawal_allowed' => 0,
                    'is_deposit_allowed' => 0,
                    'is_agent_confirmed' => 0,
                    'original_group' => $agent->original_grp ?? '',
                    'mt4_group' => $agent->mt4_grp ?? '',
                    'mt4_code' => $agent->mt4_code ?? 0,
                    'trading_mode' => intval($agent->trans_mode ?? 0),
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
                    'created_at' => strtotime($agent->rec_crt_date ?? 'now'),
                    'updated_at' => strtotime($agent->rec_upd_date ?? 'now'),
                ]);

                DB::table('user_auths')->insert([
                    'user_id' => $agent->user_id,
                    'bank_no' => $agent->bank_no ?? '',
                    'bank_name' => $agent->bank_class ?? '',
                    'bank_card_img' => $agent->bank_img ?? '',
                    'bank_card_img_tmp' => $agent->bank_img_tmp ?? '',
                    'bank_addr' => '',
                    'bank_addr_tmp' => '',
                    'bank_status' => $this->calcBankStatus($agent),
                    'bank_remarks' => '',
                    'id_card_no' => $agent->idcard_no ?? '',
                    'id_card_status' => $this->calcIdCardStatus($agent),
                    'id_card_front' => $agent->idcard_front ?? '',
                    'id_card_back' => $agent->idcard_back ?? '',
                    'id_card_remarks' => '',
                    'is_bank_synced' => 0,
                    'created_at' => strtotime($agent->rec_crt_date ?? 'now'),
                    'updated_at' => strtotime($agent->rec_upd_date ?? 'now'),
                ]);

                DB::commit();
                $successCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $errorCount++;
                echo "迁移代理 {$agent->user_id} 失败: " . $e->getMessage() . "\n";
            }
        }

        echo "agents表迁移完成: 成功 {$successCount}, 失败 {$errorCount}\n";
    }

    /**
     * 校验迁移结果：新旧库数据量对比、数据完整性、重复邮箱、表间一致性、
     * 账号类型统计，并生成测试账号。
     *
     * @return array 校验结果数组（含 old_user_count、new_login_count、
     *               data_complete、duplicate_emails、test_accounts 等）。
     */
    protected function validateMigration()
    {
        echo "\n=== 数据校验 ===\n";

        $result = [];

        // 1. 统计数据量
        $oldUserCount = DB::connection('old_crm')->table('user')->where('voided', '1')->count();
        $oldAgentCount = DB::connection('old_crm')->table('agents')->where('voided', '1')->count();
        $newLoginCount = DB::table('user_logins')->count();
        $newInfoCount = DB::table('user_infos')->count();
        $newAuthCount = DB::table('user_auths')->count();

        $result['old_user_count'] = $oldUserCount;
        $result['old_agent_count'] = $oldAgentCount;
        $result['new_login_count'] = $newLoginCount;
        $result['new_info_count'] = $newInfoCount;
        $result['new_auth_count'] = $newAuthCount;
        $result['expected_total'] = $oldUserCount + $oldAgentCount;
        $result['data_complete'] = ($newLoginCount == $result['expected_total']);

        echo "旧user表记录: {$oldUserCount}\n";
        echo "旧agents表记录: {$oldAgentCount}\n";
        echo "新user_logins记录: {$newLoginCount}\n";
        echo "新user_infos记录: {$newInfoCount}\n";
        echo "新user_auths记录: {$newAuthCount}\n";
        echo "数据完整性: " . ($result['data_complete'] ? '通过' : '不一致') . "\n";

        // 2. 检查邮箱重复
        $duplicateEmails = DB::table('user_logins')
            ->select('email', DB::raw('COUNT(*) as count'))
            ->groupBy('email')
            ->having('count', '>', 1)
            ->get();

        $result['duplicate_emails'] = $duplicateEmails->count();
        echo "重复邮箱数: " . $result['duplicate_emails'] . ($result['duplicate_emails'] > 0 ? ' 不通过' : ' 通过') . "\n";

        // 3. 检查表间一致性
        $missingInInfo = DB::table('user_logins')
            ->leftJoin('user_infos', 'user_logins.user_id', '=', 'user_infos.user_id')
            ->whereNull('user_infos.user_id')
            ->count();

        $missingInAuth = DB::table('user_logins')
            ->leftJoin('user_auths', 'user_logins.user_id', '=', 'user_auths.user_id')
            ->whereNull('user_auths.user_id')
            ->count();

        $result['missing_in_info'] = $missingInInfo;
        $result['missing_in_auth'] = $missingInAuth;
        $result['table_consistency'] = ($missingInInfo == 0 && $missingInAuth == 0);

        echo "user_infos缺失记录: {$missingInInfo}\n";
        echo "user_auths缺失记录: {$missingInAuth}\n";
        echo "表间一致性: " . ($result['table_consistency'] ? '通过' : '不一致') . "\n";

        // 4. 按类型统计
        $agentCount = DB::table('user_logins')->where('account_type', 1)->count();
        $customerCount = DB::table('user_logins')->where('account_type', 2)->count();

        $result['agent_count'] = $agentCount;
        $result['customer_count'] = $customerCount;

        echo "代理数量: {$agentCount}\n";
        echo "客户数量: {$customerCount}\n";

        // 5. 生成测试账号
        $result['test_accounts'] = $this->generateTestAccounts();

        return $result;
    }

    /**
     * 从新库查询并生成代理/客户测试账号清单。
     *
     * @return array 测试账号数组：['agents' => [...], 'customers' => [...]]，
     *               每项含 email、user_id、user_name、level、password。
     */
    protected function generateTestAccounts()
    {
        echo "\n=== 生成测试账号 ===\n";

        $accounts = [];

        // 获取代理账号
        $agents = DB::table('user_logins')
            ->join('user_infos', 'user_logins.user_id', '=', 'user_infos.user_id')
            ->where('user_logins.account_type', 1)
            ->where('user_logins.is_enabled', 1)
            ->select('user_logins.email', 'user_logins.user_id', 'user_infos.user_name', 'user_infos.level_id')
            ->limit(5)
            ->get();

        foreach ($agents as $agent) {
            $accounts['agents'][] = [
                'email' => $agent->email,
                'user_id' => $agent->user_id,
                'user_name' => $agent->user_name,
                'level' => $agent->level_id,
                'password' => 'abc123',
            ];
            echo "代理: {$agent->email} (ID: {$agent->user_id}, 姓名: {$agent->user_name}, 级别: {$agent->level_id})\n";
        }

        // 获取客户账号
        $customers = DB::table('user_logins')
            ->join('user_infos', 'user_logins.user_id', '=', 'user_infos.user_id')
            ->where('user_logins.account_type', 2)
            ->where('user_logins.is_enabled', 1)
            ->select('user_logins.email', 'user_logins.user_id', 'user_infos.user_name')
            ->limit(5)
            ->get();

        foreach ($customers as $customer) {
            $accounts['customers'][] = [
                'email' => $customer->email,
                'user_id' => $customer->user_id,
                'user_name' => $customer->user_name,
                'password' => 'abc123',
            ];
            echo "客户: {$customer->email} (ID: {$customer->user_id}, 姓名: {$customer->user_name})\n";
        }

        return $accounts;
    }

    // 辅助方法

    /**
     * 旧库性别字段转数字：'男'=1，'女'=2，其他默认 1。
     *
     * @param string $sex 旧库性别原文。
     * @return int 1=男，2=女。
     */
    protected function convertGender($sex)
    {
        if ($sex === '男') return 1;
        if ($sex === '女') return 2;
        return 1;
    }

    /**
     * 旧库用户状态转新库 auth_status：'0'=0 未验证、'1'=1 已验证、
     * '2'=2 已退回、'-1'=3 已禁用。
     *
     * @param string $status 旧库状态值。
     * @return int 新库状态码，未知值归 0。
     */
    protected function convertUserStatus($status)
    {
        switch ($status) {
            case '0': return 0; // 未验证
            case '1': return 1; // 已验证
            case '2': return 2; // 已退回
            case '-1': return 3; // 已禁用
            default: return 0;
        }
    }

    /**
     * 计算银行卡审核状态：正式卡号存在且无待审卡号=2 已通过；
     * 有待审卡号=1 审核中；否则=0 未通过。
     *
     * @param object $user 旧库记录对象。
     * @return int 状态码 0/1/2。
     */
    protected function calcBankStatus($user)
    {
        $bankNo = $user->bank_no ?? '';
        $bankNoTmp = $user->bank_no_tmp ?? '';

        if ($bankNo != '' && $bankNoTmp == '') return 2; // 已通过
        if ($bankNoTmp != '') return 1; // 审核中
        return 0; // 未通过
    }

    /**
     * 计算身份证审核状态：正式证件号存在且无待审号=2 已通过；
     * 有待审号=1 审核中；否则=0 未通过。
     *
     * @param object $user 旧库记录对象。
     * @return int 状态码 0/1/2。
     */
    protected function calcIdCardStatus($user)
    {
        $idCardNo = $user->idcard_no ?? '';
        $idCardNoTmp = $user->idcard_no_tmp ?? '';

        if ($idCardNo != '' && $idCardNoTmp == '') return 2; // 已通过
        if ($idCardNoTmp != '') return 1; // 审核中
        return 0; // 未通过
    }
}
