<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:32
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Database\Seeders\FrontDemoDataSeeder;
use Database\Seeders\LegacyFrontBusinessDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 前台演示数据登录闭环测试。
 *
 * 文件功能：
 * - 验证 FrontDemoDataSeeder 写入的登录记录和业务资料满足新版登录前置条件。
 * - 验证已标记 MT4 同步成功的演示账户，其 mt4_code 与业务 user_id 保持一致。
 * - 验证演示账户可以通过真实登录接口取得 JWT，不会被误判为 MT4 同步失败。
 *
 * 入参示例：
 * - account=demo-seeder-login@example.test
 * - password=demo-login-password
 *
 * 返回值：
 * - 成功返回 code=1000、access_token 和当前业务 user_id。
 * - 演示资料不完整时返回对应业务失败码，本测试必须失败并暴露断链位置。
 */
class FrontDemoDataSeederLoginClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证 Seeder 生成的演示账户满足 MT4 状态不变量并能完成真实登录。
     *
     * @return void 登录成功且数据库状态一致时无返回值；断链时由断言报告实际业务码。
     */
    public function test_front_demo_seeder_creates_a_fully_provisioned_login_account(): void
    {
        $userId = 413280001;
        $now = time();
        $user = [
            'email' => 'demo-seeder-login@example.test',
            'password' => 'demo-login-password',
            'name' => '演示数据登录闭环用户',
            'type' => 2,
            'parent' => 0,
            'level' => 0,
            'group' => 0,
            'rate' => 0,
            'funds' => 3500,
            'is_mt4_enabled' => 1,
        ];

        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $seeder = new FrontDemoDataSeeder();
        $loginId = (int) $this->invokePrivateMethod($seeder, 'upsertLogin', [$userId, $user, $now]);
        $this->invokePrivateMethod($seeder, 'upsertUserInfo', [$userId, $loginId, $user, $now]);

        $response = $this->postJson('/api/front/auth/login', [
            'account' => $user['email'],
            'password' => $user['password'],
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.user.user_id', (string) $userId);
        $this->assertNotEmpty((string) $response->json('data.access_token'));
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_synced' => 1,
            'is_mt4_enabled' => 1,
            'mt4_code' => $userId,
        ]);
    }

    /**
     * 验证旧业务数据 Seeder 恢复的永久测试代理满足新版登录的 MT4 状态不变量。
     *
     * @return void 登录成功且 mt4_code=1001 时无返回值；恢复逻辑写入占位值时断言失败。
     */
    public function test_legacy_business_seeder_restores_a_fully_provisioned_test_agent(): void
    {
        $seeder = new LegacyFrontBusinessDataSeeder();
        $this->invokePrivateMethod($seeder, 'restoreFrontTestAgent');

        $response = $this->postJson('/api/front/auth/login', [
            'account' => 'agent@test.com',
            'password' => 'abc123',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.user.user_id', (string) 1001);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => 1001,
            'is_mt4_synced' => 1,
            'is_mt4_enabled' => 1,
            'mt4_code' => 1001,
        ]);
    }

    /**
     * 验证永久测试代理历史迁移在全新数据库中写入可登录的完整 MT4 状态。
     *
     * @return void 迁移产物可登录时无返回值；mt4_code 仍为占位值时断言失败。
     */
    public function test_permanent_front_test_agent_migration_creates_a_fully_provisioned_account(): void
    {
        require_once database_path('migrations/2026_05_28_000001_ensure_front_test_agent_login.php');

        $migration = new \EnsureFrontTestAgentLogin();
        $migration->up();

        $response = $this->postJson('/api/front/auth/login', [
            'account' => 'agent@test.com',
            'password' => 'abc123',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.user.user_id', (string) 1001);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => 1001,
            'is_mt4_synced' => 1,
            'is_mt4_enabled' => 1,
            'mt4_code' => 1001,
        ]);
    }

    /**
     * 验证历史数据修复迁移只处理精确匹配的固定演示身份。
     *
     * @return void 精确身份被修复且伪造邮箱身份保持原值时无返回值。
     */
    public function test_demo_mt4_repair_migration_only_updates_exact_known_login_identities(): void
    {
        $now = time();
        $exactUser = [
            'email' => 'agent@test.com',
            'password' => 'abc123',
            'name' => '迁移精确身份',
            'type' => 1,
            'parent' => 0,
            'level' => 0,
            'group' => 0,
            'rate' => 0,
            'funds' => 0,
            'is_mt4_enabled' => 1,
        ];
        $mismatchUser = array_merge($exactUser, [
            'email' => 'subagent1@test.com',
            'name' => '迁移伪造身份',
        ]);
        $seeder = new FrontDemoDataSeeder();

        $exactLoginId = (int) $this->invokePrivateMethod($seeder, 'upsertLogin', [1001, $exactUser, $now]);
        $this->invokePrivateMethod($seeder, 'upsertUserInfo', [1001, $exactLoginId, $exactUser, $now]);
        $mismatchLoginId = (int) $this->invokePrivateMethod($seeder, 'upsertLogin', [1101, $mismatchUser, $now]);
        $this->invokePrivateMethod($seeder, 'upsertUserInfo', [1101, $mismatchLoginId, $mismatchUser, $now]);

        DB::table('user_infos')->whereIn('user_id', [1001, 1101])->update(['mt4_code' => 0]);
        DB::table('user_logins')->where('id', $mismatchLoginId)->update([
            'email' => 'identity-mismatch@example.test',
        ]);

        require_once database_path('migrations/2026_07_28_000001_repair_front_demo_mt4_codes.php');
        $migration = new \RepairFrontDemoMt4Codes();
        $migration->up();

        $this->assertDatabaseHas('user_infos', ['user_id' => 1001, 'mt4_code' => 1001]);
        $this->assertDatabaseHas('user_infos', ['user_id' => 1101, 'mt4_code' => 0]);
    }

    /**
     * 调用 Seeder 的受控私有写入方法，避免执行与本测试无关的全量演示数据步骤。
     *
     * @param object $subject 待验证的 Seeder 或迁移对象。
     * @param string $methodName 私有方法名称，仅允许当前测试指定的方法。
     * @param array<int, mixed> $arguments 按目标方法签名排列的参数，默认无参数。
     * @return mixed 返回目标私有方法的真实执行结果。
     */
    private function invokePrivateMethod(object $subject, string $methodName, array $arguments = [])
    {
        $method = new ReflectionMethod($subject, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($subject, $arguments);
    }
}
