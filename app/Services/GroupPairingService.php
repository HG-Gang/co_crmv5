<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/12
 * Time: 12:52
 */

namespace App\Services;

use App\Models\GroupConfig;

/**
 * 文件功能：在调用方事务内维护 group_configs 的双向配对不变量。
 */
class GroupPairingService
{
    public function rebind(GroupConfig $group, ?int $peerId, int $isEcn): GroupConfig
    {
        $selfId = (int) $group->id;
        $oldPeer = (int) $group->pair_id > 0
            ? GroupConfig::withTrashed()->whereKey((int) $group->pair_id)->lockForUpdate()->first()
            : null;
        $peer = $this->lockValidPeer($peerId, $isEcn, $selfId);

        if ($oldPeer
            && (int) $oldPeer->pair_id === $selfId
            && (!$peer || (int) $peer->id !== (int) $oldPeer->id)) {
            $oldPeer->update(['pair_id' => null]);
        }

        $group->update(['pair_id' => $peer ? (int) $peer->id : null]);
        if ($peer && (int) $peer->pair_id !== $selfId) {
            $peer->update(['pair_id' => $selfId]);
        }

        return $group->fresh();
    }

    public function unbind(GroupConfig $group): GroupConfig
    {
        $selfId = (int) $group->id;
        if ((int) $group->pair_id > 0) {
            $peer = GroupConfig::withTrashed()->whereKey((int) $group->pair_id)->lockForUpdate()->first();
            if ($peer && (int) $peer->pair_id === $selfId) {
                $peer->update(['pair_id' => null]);
            }
        }

        $inboundReferences = GroupConfig::withTrashed()
            ->where('pair_id', $selfId)
            ->lockForUpdate()
            ->get();
        foreach ($inboundReferences as $reference) {
            $reference->update(['pair_id' => null]);
        }
        $group->update(['pair_id' => null]);

        return $group->fresh();
    }

    private function lockValidPeer(?int $peerId, int $isEcn, int $selfId): ?GroupConfig
    {
        if (!$peerId) {
            return null;
        }

        $peer = GroupConfig::withTrashed()->whereKey($peerId)->lockForUpdate()->first();
        if (!$peer
            || $peer->trashed()
            || (int) $peer->id === $selfId
            || (int) $peer->is_ecn === $isEcn
            || ($peer->pair_id !== null && (int) $peer->pair_id !== $selfId)
        ) {
            throw new \DomainException('无效的关联组：对端组已被绑定或执行类型不匹配');
        }

        return $peer;
    }
}
