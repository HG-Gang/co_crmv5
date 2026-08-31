<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端实时佣金-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）无法读取现代接口 /api/front/commissions/realtime 的实时佣金列表。
 * - 验证普通客户账号无法读取旧接口 /user/realtime/realtimeRebateSearch 的实时佣金列表。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端实时佣金接口的权限边界回归测试，防止客户账号越权读取佣金数据。
 *
 * 入参例子：
 * - GET /api/front/commissions/realtime（现代接口，JWT 用户态）
 * - POST /user/realtime/realtimeRebateSearch（旧接口）
 *
 * 返回值：
 * - 两个接口均返回 HTTP 200，业务 code 为 PERMISSION_DENIED。
 *
 * 异常或失败场景：
 * - 若客户账号能读到实时佣金列表，或返回码不是 PERMISSION_DENIED，测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontCommissionRealtimeApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法读取现代接口的实时佣金列表。
     *
     * 构造客户账号与其子客户后请求 GET /api/front/commissions/realtime，
     * 断言返回 PERMISSION_DENIED。
     */
    public function test_customer_account_cannot_read_modern_realtime_commission_list(): void
    {
        $customerId = 412020100;
        $directCustomerId = 412020101;

        $this->deleteFixtureRows([$customerId, $directCustomerId]);
        $this->insertUserInfo($customerId, 'commission-realtime-modern-boundary-customer', 2, 0);
        $this->insertUserInfo($directCustomerId, 'commission-realtime-modern-boundary-child', 2, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/realtime');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 验证客户账号无法读取旧接口的实时佣金列表。
     *
     * 请求 POST /user/realtime/realtimeRebateSearch，断言返回 PERMISSION_DENIED。
     */
    public function test_customer_account_cannot_read_legacy_realtime_commission_list(): void
    {
        $customerId = 412020200;
        $directCustomerId = 412020201;

        $this->deleteFixtureRows([$customerId, $directCustomerId]);
        $this->insertUserInfo($customerId, 'commission-realtime-legacy-boundary-customer', 2, 0);
        $this->insertUserInfo($directCustomerId, 'commission-realtime-legacy-boundary-child', 2, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/realtime/realtimeRebateSearch');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 202 项、realTime、realtimeRebateSearch 及本测试类名。
     */
    public function test_final_checklist_records_realtime_commission_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 202.', $checklist);
        $this->assertStringContainsString('realTime', $checklist);
        $this->assertStringContainsString('realtimeRebateSearch', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontCommissionRealtimeApplicantBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带父子关系的测试用户数据。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param int $accountType 账号类型（1=代理商，2=客户）。
     * @param int $parentId 上级用户 ID（0 表示无上级）。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-commission-realtime-boundary-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => $accountType,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1782020' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户相关的 agent_descendants 测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }
}
