<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:26
 */

/**
 * 登录问题调试命令。
 *
 * 文件功能：
 * - 按邮箱定位用户登录记录，逐步检查：用户是否存在、账号是否启用/注销、
 *   密码哈希是否与输入密码匹配，并输出每一步的检查结果与登录地址。
 *
 * 适用场景：
 * - 排查用户无法登录问题（用户不存在、账号禁用、密码不匹配等）时手动执行。
 *
 * 入参例子：
 * - php artisan debug:login user@example.com
 * - php artisan debug:login user@example.com mypassword
 *
 * 返回值：
 * - 0=所有检查通过，账号可正常登录；
 * - 1=用户不存在、账号禁用/注销或密码验证失败（控制台输出失败原因）。
 *
 * 异常或失败场景：
 * - 数据库查询异常由 Laravel 抛出并中断命令；业务失败均以退出码 1 表达。
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DebugLogin extends Command
{
    /** @var string 命令签名：必传邮箱，可选密码（默认 123456）。 */
    protected $signature = 'debug:login {email} {password=123456}';

    /** @var string 命令说明。 */
    protected $description = '调试登录问题';

    /**
     * 执行命令：检查用户记录、账号状态与密码哈希。
     *
     * @return int 0=检查通过；1=存在登录障碍（用户不存在/账号禁用/密码错误）。
     */
    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $this->info("========================================");
        $this->info("   登录调试");
        $this->info("========================================");
        $this->newLine();

        $this->line("输入信息:");
        $this->line("  邮箱: {$email}");
        $this->line("  密码: {$password}");
        $this->newLine();

        // 查找用户
        $this->line("步骤1: 查找用户记录");
        $userLogin = DB::table('user_logins')->where('email', $email)->first();

        if (!$userLogin) {
            $this->error("❌ 用户不存在");
            $this->newLine();

            // 模糊搜索
            $this->line("尝试模糊搜索相似邮箱:");
            $similar = DB::table('user_logins')
                ->where('email', 'like', '%' . substr($email, 0, 5) . '%')
                ->limit(5)
                ->get(['email', 'user_id']);

            if ($similar->isEmpty()) {
                $this->warn("  未找到相似邮箱");
            } else {
                foreach ($similar as $user) {
                    $this->line("  {$user->email} (ID: {$user->user_id})");
                }
            }
            return 1;
        }

        $this->info("✓ 找到用户记录");
        $this->line("  ID: {$userLogin->id}");
        $this->line("  User ID: {$userLogin->user_id}");
        $this->line("  Email: {$userLogin->email}");
        $this->line("  Account Type: " . ($userLogin->account_type == 1 ? '代理' : '客户'));
        $this->newLine();

        // 检查账号状态
        $this->line("步骤2: 检查账号状态");
        $this->line("  is_enabled: {$userLogin->is_enabled}");
        $this->line("  is_cancelled: {$userLogin->is_cancelled}");

        if ($userLogin->is_enabled != 1 || $userLogin->is_cancelled != 0) {
            $this->error("❌ 账号已禁用或注销");
            return 1;
        }
        $this->info("✓ 账号状态正常");
        $this->newLine();

        // 验证密码
        $this->line("步骤3: 验证密码");
        $this->line("  密码哈希长度: " . strlen($userLogin->password));
        $this->line("  密码前缀: " . substr($userLogin->password, 0, 10) . "...");

        $checkResult = Hash::check($password, $userLogin->password);
        if ($checkResult) {
            $this->info("✓ 密码验证通过");
        } else {
            $this->error("❌ 密码验证失败");
            $this->newLine();
            $this->warn("可能原因:");
            $this->line("  1. 输入密码不是 '123456'");
            $this->line("  2. 密码哈希格式错误");
            $this->line("  3. Laravel Hash 配置问题");
            return 1;
        }

        $this->newLine();
        $this->info("========================================");
        $this->info("✅ 所有检查通过，该账号应该可以正常登录");
        $this->info("========================================");
        $this->newLine();

        $this->line("登录地址:");
        if ($userLogin->account_type == 1) {
            $this->line("  " . config('app.url') . "/agent/login");
        } else {
            $this->line("  " . config('app.url') . "/customer/login");
        }

        return 0;
    }
}
