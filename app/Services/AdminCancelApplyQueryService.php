<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 22:30
 */

namespace App\Services;

use App\Models\Admin;
use App\Models\CancelApply;
use App\Models\UserInfo;
use App\Models\UserTrade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 后台销户申请统一查询服务。
 *
 * 文件功能：
 *
 * 列表主体来自 cancel_applies，余额来自 user_infos.total_funds，当前持仓数来自
 * user_trades。现代接口与旧 V1/V2 适配器共用该读模型，避免形成两套筛选口径。
 */
class AdminCancelApplyQueryService
{
    /**
     * 后台数据范围服务：销户申请列表按申请归属用户的代理树过滤管理员可见范围；
     * 缺失时任何管理员可查看数据范围外用户的销户申请及其余额/持仓信息。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 按管理员数据范围查询销户申请并补齐真实资金与持仓数据。
     *
     * @param Admin $admin 当前后台管理员。
     * @param array<string, mixed> $filters 已校验的现代筛选参数。
     * @return LengthAwarePaginator 分页器的 data 已替换为可直接输出的数组行。
     */
    public function paginate(Admin $admin, array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = CancelApply::query()->select([
            'cancel_applies.id',
            'cancel_applies.user_id',
            'cancel_applies.user_name',
            'cancel_applies.status',
            'cancel_applies.cancel_remark',
            'cancel_applies.reject_reason',
            'cancel_applies.created_by',
            'cancel_applies.updated_by',
            'cancel_applies.created_at',
            'cancel_applies.updated_at',
        ]);

        $this->adminDataScopeService->apply(
            $query,
            $admin,
            'user',
            'cancel_applies.user_id',
            null,
            'cancel_applies.created_by'
        );

        if (array_key_exists('user_id', $filters)) {
            $query->where('cancel_applies.user_id', (int) $filters['user_id']);
        }
        if (array_key_exists('status', $filters)) {
            $query->where('cancel_applies.status', (int) $filters['status']);
        }
        if (array_key_exists('start_date', $filters)) {
            $query->where('cancel_applies.created_at', '>=', strtotime($filters['start_date'] . ' 00:00:00'));
        }
        if (array_key_exists('end_date', $filters)) {
            $query->where('cancel_applies.created_at', '<=', strtotime($filters['end_date'] . ' 23:59:59'));
        }

        $paginator = $query
            ->orderByDesc('cancel_applies.created_at')
            ->orderByDesc('cancel_applies.id')
            ->paginate($perPage, ['*'], 'page', $page);

        $userIds = $paginator->getCollection()
            ->pluck('user_id')
            ->map(function ($userId) {
                return (int) $userId;
            })
            ->unique()
            ->values()
            ->all();

        $balances = collect();
        $openPositions = collect();
        if (!empty($userIds)) {
            // 已通过的用户资料会软删除，历史审核列表仍须展示审核时保留的真实资金快照。
            $balances = UserInfo::withTrashed()
                ->whereIn('user_id', $userIds)
                ->pluck('total_funds', 'user_id');
            $openPositions = UserTrade::query()
                ->selectRaw('user_id, COUNT(*) as open_position_count')
                ->whereIn('user_id', $userIds)
                ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
                ->where('close_time', '1970-01-01 00:00:00')
                ->where('margin_rate', '<>', 0)
                ->groupBy('user_id')
                ->pluck('open_position_count', 'user_id');
        }

        $paginator->setCollection($paginator->getCollection()->map(function (CancelApply $apply) use ($balances, $openPositions) {
            $userId = (int) $apply->user_id;
            $createdAt = (int) $apply->getRawOriginal('created_at');
            $updatedAt = (int) $apply->getRawOriginal('updated_at');

            return [
                'id' => (int) $apply->id,
                'user_id' => $userId,
                'user_name' => (string) $apply->user_name,
                'balance' => number_format((float) ($balances[$userId] ?? 0), 2, '.', ''),
                'open_positions' => (int) ($openPositions[$userId] ?? 0),
                'status' => (int) $apply->status,
                'cancel_remark' => (string) ($apply->cancel_remark ?? ''),
                'reject_reason' => (string) ($apply->reject_reason ?? ''),
                'created_by' => (string) $apply->created_by,
                'updated_by' => (string) $apply->updated_by,
                'created_at' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
                'updated_at' => $updatedAt > 0 ? date('Y-m-d H:i:s', $updatedAt) : '',
            ];
        }));

        return $paginator;
    }
}
