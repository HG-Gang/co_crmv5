<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 前台流水导出文件下载申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能通过 /user/flow/downloadfile/{file}/{role}
 *   下载前台流水导出文件（返回 403）。
 * - 验证代理账号（account_type=1）仍可正常下载导出文件。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台流水导出文件下载功能的回归测试，防止普通客户越权下载导出数据。
 *
 * 入参例子：
 * - file: direct_deposit_transactions_210_customer_boundary（导出文件基础名）
 * - role: agent（下载角色）
 *
 * 返回值：
 * - 客户下载返回 HTTP 403，响应体不含导出内容。
 * - 代理下载返回 HTTP 200 且触发文件下载（assertDownload）。
 *
 * 异常或失败场景：
 * - 普通客户下载导出文件被拒绝（403），测试结束后清理生成的 CSV 文件。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontFlowDownloadFileApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户下载前台流水导出文件被拒绝（403）。
    public function test_customer_account_cannot_download_front_flow_export_file(): void
    {
        $customerId = 412100100;
        $fileBaseName = 'direct_deposit_transactions_210_customer_boundary';
        $filePath = $this->writeExportFile($fileBaseName, 'FDDF-21001-sensitive-export-row');

        try {
            $this->deleteFixtureRows([$customerId]);
            $this->insertUserInfo($customerId, 'flow-download-boundary-customer', 2, 0);

            $login = UserLogin::where('user_id', $customerId)->firstOrFail();
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->get('/user/flow/downloadfile/' . $fileBaseName . '/agent');

            $response->assertForbidden();
            $this->assertStringNotContainsString('FDDF-21001-sensitive-export-row', $response->getContent());
        } finally {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }

    // 验证代理账号仍可正常下载前台流水导出文件。
    public function test_agent_account_can_still_download_front_flow_export_file(): void
    {
        $agentId = 412100101;
        $fileBaseName = 'direct_deposit_transactions_210_agent_boundary';
        $filePath = $this->writeExportFile($fileBaseName, 'FDDF-21002-agent-export-row');

        try {
            $this->deleteFixtureRows([$agentId]);
            $this->insertUserInfo($agentId, 'flow-download-boundary-agent', 1, 0);

            $login = UserLogin::where('user_id', $agentId)->firstOrFail();
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->get('/user/flow/downloadfile/' . $fileBaseName . '/agent');

            $response->assertOk();
            $response->assertDownload($fileBaseName . '.csv');
        } finally {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }

    // 校验权限清单文档记录了导出文件下载申请人边界闭环。
    public function test_final_checklist_records_download_file_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 210.', $checklist);
        $this->assertStringContainsString('downloadFile', $checklist);
        $this->assertStringContainsString('user/flow/downloadfile/{file}/{role}', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontFlowDownloadFileApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-flow-download-boundary-' . $userId . '@example.test',
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
            'phone' => '1782100' . substr((string) $userId, -4),
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
     * @param array<int, int> $userIds
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }

    private function writeExportFile(string $fileBaseName, string $content): string
    {
        $dir = storage_path('app/front_exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . $fileBaseName . '.csv';
        file_put_contents($path, $content);

        return $path;
    }
}
