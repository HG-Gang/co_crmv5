<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/15
 * Time: 22:29
 */

namespace App\Services;

use App\Models\Admin;
use App\Models\TransApplyLog;
use App\Models\UserInfo;
use App\Models\UserTrade;

/**
 * 旧后台 Customer 转组申请列表查询服务。
 *
 * 文件功能：
 *
 * 旧项目 custChangeListSearch/V2 的数据源是 trans_apply_log；普通用户列表
 * 不能替代它，因为审核状态、申请原因和余额/持仓列都来自申请上下文。
 */
class LegacyAdminCustomerChangeSearchService
{
    /**
     * 查询转组申请并补齐旧表格所需的余额和未平仓量。
     *
     * @param array<string, mixed> $payload 已归一化的旧请求字段。
     * @param Admin $admin 当前后台管理员。
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function search(array $payload, Admin $admin): array
    {
        $page = max(1, (int) ($payload['page'] ?? 1));
        $perPage = (int) ($payload['rows'] ?? ($payload['limit'] ?? 15));
        $perPage = max(1, min(100, $perPage));

        $query = TransApplyLog::query()
            ->select([
                'trans_apply_logs.id',
                'trans_apply_logs.user_id as transUid',
                'trans_apply_logs.group_id as transTypeGid',
                'trans_apply_logs.group_name as transTypeName',
                'trans_apply_logs.applicant_id as transApplyUid',
                'trans_apply_logs.applicant_name as transApplyUname',
                'trans_apply_logs.status as transApplyStatus',
                'trans_apply_logs.apply_reason as transApplyReason',
                'trans_apply_logs.created_at as recCrtDate',
            ])
            ->where('trans_apply_logs.created_at', '>', 0);

        if (array_key_exists('user_id', $payload) && $payload['user_id'] !== '') {
            $query->where('trans_apply_logs.user_id', 'like', '%' . (string) $payload['user_id'] . '%');
        }

        if (array_key_exists('trans_apply_status', $payload) && $payload['trans_apply_status'] !== '') {
            $query->where('trans_apply_logs.status', (int) $payload['trans_apply_status']);
        }

        $startDate = (string) ($payload['start_date'] ?? '2024-01-01');
        $endDate = (string) ($payload['end_date'] ?? date('Y-m-d'));
        $query->whereBetween('trans_apply_logs.created_at', [
            strtotime($startDate . ' 00:00:00'),
            strtotime($endDate . ' 23:59:59'),
        ]);

        app(AdminDataScopeService::class)->apply(
            $query,
            $admin,
            'user',
            'trans_apply_logs.user_id'
        );

        $total = (clone $query)->count('trans_apply_logs.id');
        $logs = $query
            ->orderByDesc('trans_apply_logs.created_at')
            ->forPage($page, $perPage)
            ->get();

        if ($logs->isEmpty()) {
            return ['rows' => [], 'total' => (int) $total];
        }

        $userIds = $logs->pluck('transUid')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $balances = UserInfo::query()
            ->whereIn('user_id', $userIds)
            ->pluck('total_funds', 'user_id');
        $openVolumes = UserTrade::query()
            ->selectRaw('user_id, COUNT(*) as open_volume')
            ->whereIn('user_id', $userIds)
            ->where('close_time', '1970-01-01 00:00:00')
            ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
            ->groupBy('user_id')
            ->pluck('open_volume', 'user_id');

        $rows = $logs->map(function ($log) use ($balances, $openVolumes): array {
            $userId = (int) $log->transUid;
            $createdAt = (int) $log->recCrtDate;

            return [
                'transId' => (int) $log->id,
                'transUid' => $userId,
                'transTypeGid' => (int) $log->transTypeGid,
                'transTypeName' => (string) $log->transTypeName,
                'transApplyUid' => (int) $log->transApplyUid,
                'transApplyUname' => (string) $log->transApplyUname,
                'transApplyStatus' => (int) $log->transApplyStatus,
                'transApplyReason' => (string) ($log->transApplyReason ?? ''),
                'recCrtDate' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
                'bal' => number_format((float) ($balances[$userId] ?? 0), 2, '.', ''),
                'vol' => (int) ($openVolumes[$userId] ?? 0),
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'total' => (int) $total,
        ];
    }
}
