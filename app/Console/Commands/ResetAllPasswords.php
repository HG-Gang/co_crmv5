<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:45
 */

/**
 * 重置全部用户密码命令。
 *
 * 文件功能：
 * - 将 user_logins（前台用户）、admins（后台管理员）、big_agents（大代理）全部账号密码
 *   统一重置为指定密码（默认 abc123），并输出账号统计与示例登录账号。
 *
 * 适用场景：
 * - 测试/开发环境统一密码时手动执行；迁移数据后密码格式异常时也可用于修复。
 *
 * 入参例子：
 * - php artisan password:reset-all
 * - php artisan password:reset-all mypass123
 *
 * 返回值：
 * - 0=重置成功；
 * - 1=用户取消确认或重置过程抛出异常。
 *
 * 异常或失败场景：
 * - 数据库更新失败时抛出异常，命令捕获后输出错误并返回 1。
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ResetAllPasswords extends Command
{
    /** @var array<int, string> 项目内全部认证凭据表。 */
    private const CREDENTIAL_TABLES = [
        'users',
        'user_logins',
        'admins',
        'admin_logins',
        'big_agents',
    ];

    /** @var string 命令签名：可选密码默认 abc123，--force 用于受控迁移链无人值守执行。 */
    protected $signature = 'password:reset-all
        {password=abc123 : 要设置的新密码}
        {--force : 跳过交互确认，仅供已完成安全校验的迁移命令调用}';

    /** @var string 命令说明。 */
    protected $description = '重置所有用户密码为统一密码（用于测试环境）';

    /**
     * 执行命令：确认后批量重置全部用户密码。
     *
     * @return int 0=成功；1=取消或失败。
     */
    public function handle(): int
    {
        $password = trim((string) $this->argument('password'));
        if ($password === '') {
            $this->error('新密码不能为空。');

            return self::FAILURE;
        }

        $this->warn('警告：此操作将重置全部认证账号密码。');
        if (!$this->option('force') && !$this->confirm('确认要重置全部认证账号密码吗？', false)) {
            $this->error('已取消操作。');

            return self::FAILURE;
        }

        try {
            $hashedPassword = Hash::make($password);
            if (!Hash::check($password, $hashedPassword)) {
                throw new RuntimeException('生成的密码哈希无法通过验证。');
            }

            $counts = DB::transaction(function () use ($hashedPassword): array {
                $counts = [];
                foreach (self::CREDENTIAL_TABLES as $table) {
                    if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'password')) {
                        throw new RuntimeException('认证凭据表或 password 列缺失：' . $table);
                    }

                    $counts[$table] = DB::table($table)->count();
                    DB::table($table)->update(['password' => $hashedPassword]);

                    $hasMismatch = DB::table($table)
                        ->whereNull('password')
                        ->orWhere('password', '<>', $hashedPassword)
                        ->exists();
                    if ($hasMismatch) {
                        throw new RuntimeException('密码更新后验证失败：' . $table);
                    }
                }

                return $counts;
            }, 3);

            $total = array_sum($counts);
            foreach ($counts as $table => $count) {
                $this->line($table . ': ' . $count);
            }
            $this->info('密码重置及逐表一致性验证完成，账号总数：' . $total);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('密码重置失败：' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
