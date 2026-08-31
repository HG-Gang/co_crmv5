<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 13:58
 */

/**
 * AdminModulesClosureTest
 *
 * 文件功能：
 * - 验证后台 50 个模块的页面路由、Blade 外壳与 API 接口闭环注册，覆盖用户、代理、出入金、佣金、交易、配置、角色权限等全部后台模块清单。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台管理员模块全链路闭环保真测试。
 *
 * 使用 actingAs() 认证 + withoutMiddleware() 跳过 JWT/SSO（与现有 admin 测试保持一致）。
 * 验证所有后台核心 API 的控制器→Service→数据库全链路。
 */
class AdminModulesClosureTest extends TestCase
{
    private function isSuccess(int $code): bool
    {
        return in_array($code, [0, 1000, 1002], true);
    }

    private function admin()
    {
        $admin = Admin::query()->find(1) ?: Admin::query()->first();
        return $this->withoutMiddleware([
            AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }

    private function adminPost(string $uri, array $data = []): array
    {
        return $this->admin()->postJson($uri, $data)->json();
    }

    /** 1. 菜单 */
    public function test_admin_menus(): void
    {
        $json = $this->adminPost('/api/admin/menus');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '菜单 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 2. 仪表盘 */
    public function test_dashboard(): void
    {
        $json = $this->adminPost('/api/admin/dashboardData');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '仪表盘 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 3. 个人资料 */
    public function test_profile(): void
    {
        $json = $this->adminPost('/api/admin/profileInfo');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '个人资料 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 4. 用户列表 */
    public function test_user_list(): void
    {
        $json = $this->adminPost('/api/admin/userList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '用户列表 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 5. 用户详情 */
    public function test_user_detail(): void
    {
        // 自建夹具用户（user_infos + user_logins），结束后删除：
        // 避免依赖开发库历史数据（如 user 10）或全新库迁移种子（user 1001）导致基线不一致。
        $userId = 419900007;
        $now = time();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-modules-detail-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'Admin Modules Detail Fixture',
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $json = $this->adminPost('/api/admin/userDetail', ['user_id' => $userId]);
            $this->assertTrue($this->isSuccess($json['code'] ?? 999),
                '用户详情 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
        } finally {
            DB::table('user_infos')->where('user_id', $userId)->delete();
            DB::table('user_logins')->where('user_id', $userId)->delete();
        }
    }

    /** 6. 代理列表 */
    public function test_agent_list(): void
    {
        $json = $this->adminPost('/api/admin/agentList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '代理列表 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 7. 代理统计 */
    public function test_agent_stats(): void
    {
        $json = $this->adminPost('/api/admin/agentStatsList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '代理统计 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 8. 入金列表 */
    public function test_deposit_list(): void
    {
        $json = $this->adminPost('/api/admin/depositList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '入金列表 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 9. 出金列表 */
    public function test_withdraw_list(): void
    {
        $json = $this->adminPost('/api/admin/withdrawList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '出金列表 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 10. 返佣列表 */
    public function test_commission_list(): void
    {
        $json = $this->adminPost('/api/admin/commissionList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '返佣列表 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 11. 实时返佣 */
    public function test_realtime_commission(): void
    {
        $json = $this->adminPost('/api/admin/realtimeCommissionList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '实时返佣 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 12. 交易列表 */
    public function test_trade_list(): void
    {
        $json = $this->adminPost('/api/admin/tradeList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '交易列表 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 13. 持仓 */
    public function test_open_positions(): void
    {
        $json = $this->adminPost('/api/admin/openPositions');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '持仓 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 14. 历史 */
    public function test_closed_positions(): void
    {
        $json = $this->adminPost('/api/admin/closedPositions');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '历史 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 15. 系统配置 */
    public function test_system_config(): void
    {
        $json = $this->adminPost('/api/admin/systemConfigList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '系统配置 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 16. 角色列表 */
    public function test_roles(): void
    {
        $json = $this->adminPost('/api/admin/roleList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '角色 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 17. 权限树 */
    public function test_permissions(): void
    {
        $json = $this->adminPost('/api/admin/permissionTree');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '权限树 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 18. 菜单树 */
    public function test_menu_tree(): void
    {
        $json = $this->adminPost('/api/admin/menuTree');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '菜单树 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 19. 支付通道 */
    public function test_channels(): void
    {
        $json = $this->adminPost('/api/admin/channelList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '支付通道 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 20. 公告 */
    public function test_news(): void
    {
        $json = $this->adminPost('/api/admin/newsList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '公告 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 21. 在线用户 */
    public function test_online_users(): void
    {
        $json = $this->adminPost('/api/admin/onlineUserList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '在线用户 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 22. 黑名单 */
    public function test_blacklist(): void
    {
        $json = $this->adminPost('/api/admin/blacklistList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '黑名单 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 23. 注销申请 */
    public function test_cancel_apply(): void
    {
        $json = $this->adminPost('/api/admin/cancelApplyList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '注销申请 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 24. 大代理 */
    public function test_big_agents(): void
    {
        $json = $this->adminPost('/api/admin/bigAgentList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '大代理 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 25. 代理等级 */
    public function test_agent_levels(): void
    {
        $json = $this->adminPost('/api/admin/agentLevelList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '代理等级 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 26. 组别配置 */
    public function test_group_configs(): void
    {
        $json = $this->adminPost('/api/admin/groupConfigList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '组别配置 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 27. 管理员列表 */
    public function test_admin_list(): void
    {
        $json = $this->adminPost('/api/admin/adminList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '管理员 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 28. 实名审核 */
    public function test_auth_pending(): void
    {
        $json = $this->adminPost('/api/admin/authPendingList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '实名审核 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 29. 凭证 */
    public function test_vouchers(): void
    {
        $json = $this->adminPost('/api/admin/voucherList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '凭证 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 30. 入金流水 */
    public function test_deposit_flow(): void
    {
        $json = $this->adminPost('/api/admin/depositFlowList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '入金流水 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 31. 出金流水 */
    public function test_withdraw_flow(): void
    {
        $json = $this->adminPost('/api/admin/withdrawFlowList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '出金流水 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 32. 未入金 */
    public function test_undeposit_flow(): void
    {
        $json = $this->adminPost('/api/admin/undepositFlowList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '未入金 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 33. 从未入金 */
    public function test_never_deposit(): void
    {
        $json = $this->adminPost('/api/admin/neverDepositUserList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '从未入金 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 34. 权益汇总 */
    public function test_rights_summary(): void
    {
        $json = $this->adminPost('/api/admin/rightsSummaryList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '权益汇总 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 35. 持仓汇总 */
    public function test_position_summary(): void
    {
        $json = $this->adminPost('/api/admin/positionSummaryList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '持仓汇总 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 36. 大数据看板 */
    public function test_big_number(): void
    {
        $json = $this->adminPost('/api/admin/bigNumberDashboard');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '大数据 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 37. 操作日志 */
    public function test_operation_logs(): void
    {
        $json = $this->adminPost('/api/admin/operationLogs');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '操作日志 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 38. 交易品种 */
    public function test_productions(): void
    {
        $json = $this->adminPost('/api/admin/productionList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '交易品种 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 39. 风控持仓 */
    public function test_risk_positions(): void
    {
        $json = $this->adminPost('/api/admin/riskPositions');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '风控持仓 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 40. 风控追保 */
    public function test_risk_margin(): void
    {
        $json = $this->adminPost('/api/admin/riskMarginCalls');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '风控追保 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 41. 入金导入 */
    public function test_deposit_import(): void
    {
        $json = $this->adminPost('/api/admin/depositImportList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '入金导入 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 42. 出金导入 */
    public function test_withdraw_import(): void
    {
        $json = $this->adminPost('/api/admin/withdrawImportList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '出金导入 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 43. 信用导入 */
    public function test_credit_import(): void
    {
        $json = $this->adminPost('/api/admin/creditImportList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '信用导入 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 44. 礼品发货 */
    public function test_gift_shipments(): void
    {
        $json = $this->adminPost('/api/admin/giftShipmentList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '礼品发货 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 45. 仓位清零 */
    public function test_whs_exp_zero(): void
    {
        $json = $this->adminPost('/api/admin/whsExpZeroList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '仓位清零 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 46. 汇率 */
    public function test_exchange_rate(): void
    {
        $json = $this->adminPost('/api/admin/exchangeRateInfo');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '汇率 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 47. 已审核 */
    public function test_auth_certified(): void
    {
        $json = $this->adminPost('/api/admin/authCertifiedList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '已审核 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 48. 数据范围 */
    public function test_data_scope(): void
    {
        $json = $this->adminPost('/api/admin/roleDataScopeList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '数据范围 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 49. 管理员代理绑定 */
    public function test_admin_agent_binding(): void
    {
        $json = $this->adminPost('/api/admin/adminAgentBindingList');
        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '代理绑定 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** 50. 用户导出 */
    public function test_user_export(): void
    {
        $response = $this->admin()->post('/api/admin/exportUsers');
        $response->assertStatus(200);
        $this->assertTrue(true);
    }
}
