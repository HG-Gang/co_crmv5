<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:53
 */

/**
 * 前端直系客户明细-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）通过旧接口 /user/proxy/direct_cust_detail_list 查询直系客户明细时返回空结果。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端直系客户明细接口的权限边界回归测试，防止客户账号越权读取客户明细。
 *
 * 入参例子：
 * - POST /user/proxy/direct_cust_detail_list（body: { "puid": 411950100, "per_page": 50 }）
 *
 * 返回值：
 * - 接口返回 HTTP 200，count 为 0、data 为空数组。
 *
 * 异常或失败场景：
 * - 若客户账号能读到任何客户明细（count 非 0 或 data 非空），测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法读取旧接口的直系客户明细表。
     *
     * 构造客户账号与其子客户后请求 direct_cust_detail_list，
     * 断言返回 count=0 与空 data。
     */
    public function test_customer_account_cannot_read_legacy_direct_customer_detail_table(): void
    {
        $customerId = 411950100;
        $childCustomerId = 411950101;

        $this->insertUserInfo($customerId, 'direct-detail-boundary-customer', 2, 0);
        $this->insertUserInfo($childCustomerId, 'direct-detail-boundary-child', 2, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/direct_cust_detail_list', [
                'puid' => $customerId,
                'per_page' => 50,
            ]);

        $response->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', []);
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 195 项、directCustDetailList、count/data 及本测试类名。
     */
    public function test_final_checklist_records_direct_customer_detail_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 195.', $checklist);
        $this->assertStringContainsString('directCustDetailList', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('count/data', $checklist);
        $this->assertStringContainsString('FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest', $checklist);
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
            'email' => 'front-direct-detail-boundary-' . $userId . '@example.test',
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
            'phone' => '1789500' . substr((string) $userId, -4),
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
}
