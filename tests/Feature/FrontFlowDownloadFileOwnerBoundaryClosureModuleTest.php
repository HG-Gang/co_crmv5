<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 前台流水导出文件下载属主边界闭环测试。
 *
 * 文件功能：
 * - 验证导出文件的属主代理可以下载该文件，其他代理下载同一文件时被拒绝（403）。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台流水导出文件下载的属主校验回归测试，防止代理间越权下载导出数据。
 *
 * 入参例子：
 * - 属主代理 ownerAgentId 调用 /user/flow/depositExport 导出其直接客户入金流水，
 *   得到导出文件名 exportFile。
 * - ownerAgentId 与 otherAgentId 分别请求 /user/flow/downloadfile/{exportFile}/agent。
 *
 * 返回值：
 * - 属主代理下载返回 HTTP 200 并触发文件下载（assertDownload）。
 * - 其他代理下载返回 HTTP 403，响应体不含订单号。
 *
 * 异常或失败场景：
 * - 非属主代理下载导出文件被拒绝（403），测试结束后清理 CSV 与 .meta.json 文件。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontFlowDownloadFileOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证导出属主代理可下载文件而其他代理被拒绝（403）。
    public function test_export_owner_agent_can_download_file_but_other_agent_cannot(): void
    {
        $ownerAgentId = 412110100;
        $otherAgentId = 412110101;
        $childCustomerId = 412110102;
        $orderNo = 'FDOW-21101';
        $exportFile = null;

        try {
            $this->deleteFixtureRows([$ownerAgentId, $otherAgentId, $childCustomerId], [$orderNo]);
            $this->insertUserInfo($ownerAgentId, 'flow-download-owner-agent', 1, 0);
            $this->insertUserInfo($otherAgentId, 'flow-download-other-agent', 1, 0);
            $this->insertUserInfo($childCustomerId, 'flow-download-owner-child', 2, $ownerAgentId);
            $this->insertDepositRecord($childCustomerId, 'flow-download-owner-child', $orderNo);

            $ownerLogin = UserLogin::where('user_id', $ownerAgentId)->firstOrFail();
            $exportResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($ownerLogin, 'user')
                ->postJson('/user/flow/depositExport', [
                    'direct_deposit_userId' => $childCustomerId,
                ]);

            $exportResponse->assertOk();
            $exportFile = $exportResponse->json('msg');
            $this->assertIsString($exportFile);
            $this->assertNotSame('FAIL', $exportFile);

            $ownerDownload = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($ownerLogin, 'user')
                ->get('/user/flow/downloadfile/' . $exportFile . '/agent');
            $ownerDownload->assertOk();
            $ownerDownload->assertDownload($exportFile . '.csv');

            $otherLogin = UserLogin::where('user_id', $otherAgentId)->firstOrFail();
            $otherDownload = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($otherLogin, 'user')
                ->get('/user/flow/downloadfile/' . $exportFile . '/agent');

            $otherDownload->assertForbidden();
            $this->assertStringNotContainsString($orderNo, $otherDownload->getContent());
        } finally {
            $this->deleteGeneratedExportFile($exportFile);
        }
    }

    // 校验权限清单文档记录了导出文件下载属主边界闭环。
    public function test_final_checklist_records_download_file_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 211.', $checklist);
        $this->assertStringContainsString('downloadFile', $checklist);
        $this->assertStringContainsString('.meta.json', $checklist);
        $this->assertStringContainsString('user/flow/downloadfile/{file}/{role}', $checklist);
        $this->assertStringContainsString('FrontFlowDownloadFileOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-flow-download-owner-' . $userId . '@example.test',
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
            'phone' => '1782110' . substr((string) $userId, -4),
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
            'amount' => 211.50,
            'actual_amount' => 211.50,
            'exchange_rate' => 1,
            'channel_name' => 'Bank',
            'channel_order_no' => $orderNo . '-CHANNEL',
            'local_order_no' => $orderNo,
            'status' => '02',
            'payment_time' => '2026-07-09 12:11:00',
            'remarks' => 'download owner boundary fixture',
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
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
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

        foreach ([$path, $path . '.meta.json'] as $candidate) {
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }
}
