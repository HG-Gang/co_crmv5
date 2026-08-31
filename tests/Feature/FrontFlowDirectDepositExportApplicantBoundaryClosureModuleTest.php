<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 前台直接客户入金流水导出申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能通过遗留接口 /user/flow/depositExport
 *   导出直接客户的入金流水。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台流水导出功能的回归测试，防止普通客户越权导出他人流水文件。
 *
 * 入参例子：
 * - 登录账号：普通客户（account_type=2），其下挂一个直接客户（account_type=2）。
 * - direct_deposit_userId: 412090101（试图导出的直接客户 ID）
 *
 * 返回值：
 * - 接口返回 HTTP 200，msg 为 FAIL，不生成导出文件。
 *
 * 异常或失败场景：
 * - 普通客户调用入金流水导出接口时被拒绝（msg 为 FAIL）。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontFlowDirectDepositExportApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能导出直接客户的入金流水。
    public function test_customer_account_cannot_export_direct_customer_deposit_flows(): void
    {
        $customerId = 412090100;
        $childId = 412090101;
        $orderNo = 'FDDE-20901';

        $this->deleteFixtureRows([$customerId, $childId], [$orderNo]);
        $this->insertUserInfo($customerId, 'flow-direct-export-boundary-customer', 2, 0);
        $this->insertUserInfo($childId, 'flow-direct-export-boundary-child', 2, $customerId);
        $this->insertDepositRecord($childId, 'flow-direct-export-boundary-child', $orderNo);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/flow/depositExport', [
                'direct_deposit_userId' => $childId,
            ]);

        $this->deleteGeneratedExportFile($response->json('msg'));

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL');
    }

    // 校验权限清单文档记录了入金流水导出申请人边界闭环。
    public function test_final_checklist_records_direct_deposit_export_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 209.', $checklist);
        $this->assertStringContainsString('depositExport', $checklist);
        $this->assertStringContainsString('user/flow/depositExport', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontFlowDirectDepositExportApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-flow-direct-export-boundary-' . $userId . '@example.test',
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
            'phone' => '1782090' . substr((string) $userId, -4),
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

    private function insertDepositRecord(int $userId, string $userName, string $orderNo): void
    {
        $now = time();

        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => 0,
            'amount' => 209.50,
            'actual_amount' => 209.50,
            'exchange_rate' => 1,
            'channel_name' => 'Bank',
            'channel_order_no' => $orderNo . '-CHANNEL',
            'local_order_no' => $orderNo,
            'status' => '02',
            'payment_time' => '2026-07-09 12:00:00',
            'remarks' => 'ordinary customer direct deposit export boundary fixture',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $orderNos
     */
    private function deleteFixtureRows(array $userIds, array $orderNos): void
    {
        DB::table('deposit_records')
            ->whereIn('user_id', $userIds)
            ->orWhereIn('local_order_no', $orderNos)
            ->delete();

        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('commission_records')->whereIn('agent_id', $userIds)->orWhereIn('parent_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }

    private function deleteGeneratedExportFile($file): void
    {
        if (!is_string($file) || $file === '' || $file === 'FAIL') {
            return;
        }

        $safeName = basename($file);
        $fileName = str_ends_with($safeName, '.csv') ? $safeName : $safeName . '.csv';
        $path = storage_path('app/front_exports' . DIRECTORY_SEPARATOR . $fileName);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
