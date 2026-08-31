<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 05:20
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\UserInfo;

$users = DB::table('user_infos')
    ->whereNull('deleted_at')
    ->get(['user_id', 'parent_id', 'account_type']);

$byId = [];
foreach ($users as $user) {
    $byId[(int) $user->user_id] = $user;
}

$expected = [];
$expectedFromFamilyTree = [];
$brokenChains = [];
$maxDepth = UserInfo::MAX_HIERARCHY_DEPTH;
foreach ($users as $user) {
    $descendantId = (int) $user->user_id;
    $cursor = (int) $user->parent_id;
    $depth = 1;
    $visited = [$descendantId => true];

    while ($cursor > 0) {
        if (isset($visited[$cursor])) {
            $brokenChains[] = ['descendant_id' => $descendantId, 'cycle_at_id' => $cursor];
            break;
        }
        if ($depth > $maxDepth) {
            $brokenChains[] = ['descendant_id' => $descendantId, 'max_depth' => $maxDepth];
            break;
        }

        $ancestor = $byId[$cursor] ?? null;
        if (!$ancestor) {
            $brokenChains[] = ['descendant_id' => $descendantId, 'missing_parent_id' => $cursor];
            break;
        }
        if ((int) $ancestor->account_type !== 1) {
            $brokenChains[] = [
                'descendant_id' => $descendantId,
                'invalid_parent_type' => $cursor,
            ];
            break;
        }

        $visited[$cursor] = true;

        $key = $cursor . ':' . $descendantId;
        $expected[$key] = [
            'type' => (int) $user->account_type,
            'direct' => $depth === 1 ? 1 : 0,
            'depth' => $depth,
        ];

        $cursor = (int) $ancestor->parent_id;
        $depth++;
    }

    $treeIds = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) DB::table('user_infos')->where('user_id', $descendantId)->value('family_tree'))
    )));
    $selfIndex = array_search($descendantId, $treeIds, true);
    if ($selfIndex !== false) {
        foreach (array_slice($treeIds, 0, $selfIndex) as $index => $ancestorId) {
            $ancestor = $byId[$ancestorId] ?? null;
            if (!$ancestor || (int) $ancestor->account_type !== 1) {
                continue;
            }

            $expectedFromFamilyTree[$ancestorId . ':' . $descendantId] = [
                'type' => (int) $user->account_type,
                'direct' => (int) $user->parent_id === $ancestorId ? 1 : 0,
                'depth' => $selfIndex - $index,
            ];
        }
    }
}

$rows = DB::table('agent_descendants')
    ->whereNull('deleted_at')
    ->get(['agent_id', 'descendant_id', 'descendant_type', 'is_direct', 'depth']);

$actual = [];
foreach ($rows as $row) {
    $key = (int) $row->agent_id . ':' . (int) $row->descendant_id;
    $actual[$key] = [
        'type' => (int) $row->descendant_type,
        'direct' => (int) $row->is_direct,
        'depth' => (int) $row->depth,
    ];
}

$missing = [];
$mismatch = [];
foreach ($expected as $key => $want) {
    if (!isset($actual[$key])) {
        $missing[$key] = $want;
        continue;
    }

    if ($actual[$key] !== $want) {
        $mismatch[$key] = ['actual' => $actual[$key], 'expected' => $want];
    }
}

$extra = [];
foreach ($actual as $key => $row) {
    if (!isset($expected[$key])) {
        $extra[$key] = $row;
    }
}

$familyMissing = [];
$familyMismatch = [];
foreach ($expectedFromFamilyTree as $key => $want) {
    if (!isset($actual[$key])) {
        $familyMissing[$key] = $want;
        continue;
    }

    if ($actual[$key] !== $want) {
        $familyMismatch[$key] = ['actual' => $actual[$key], 'expected' => $want];
    }
}

$familyExtra = [];
foreach ($actual as $key => $row) {
    if (!isset($expectedFromFamilyTree[$key])) {
        $familyExtra[$key] = $row;
    }
}

$classifyExtra = [
    'deleted_agent' => 0,
    'deleted_descendant' => 0,
    'missing_agent' => 0,
    'missing_descendant' => 0,
    'active_but_not_in_parent_tree' => 0,
];
foreach ($extra as $key => $_row) {
    [$agentId, $descendantId] = array_map('intval', explode(':', $key, 2));
    $agent = DB::table('user_infos')->where('user_id', $agentId)->first(['deleted_at']);
    $descendant = DB::table('user_infos')->where('user_id', $descendantId)->first(['deleted_at']);
    if (!$agent) {
        $classifyExtra['missing_agent']++;
    } elseif ($agent->deleted_at !== null) {
        $classifyExtra['deleted_agent']++;
    } elseif (!$descendant) {
        $classifyExtra['missing_descendant']++;
    } elseif ($descendant->deleted_at !== null) {
        $classifyExtra['deleted_descendant']++;
    } else {
        $classifyExtra['active_but_not_in_parent_tree']++;
    }
}

echo json_encode([
    'active_users' => count($users),
    'expected_relations' => count($expected),
    'actual_relations' => count($actual),
    'missing' => count($missing),
    'mismatch' => count($mismatch),
    'extra' => count($extra),
    'broken_chains' => count($brokenChains),
    'family_tree_expected_relations' => count($expectedFromFamilyTree),
    'family_tree_missing' => count($familyMissing),
    'family_tree_mismatch' => count($familyMismatch),
    'family_tree_extra' => count($familyExtra),
    'extra_classification' => $classifyExtra,
    'missing_sample' => array_slice($missing, 0, 10, true),
    'mismatch_sample' => array_slice($mismatch, 0, 10, true),
    'extra_sample' => array_slice($extra, 0, 10, true),
    'broken_chain_sample' => array_slice($brokenChains, 0, 10),
    'family_tree_missing_sample' => array_slice($familyMissing, 0, 10, true),
    'family_tree_mismatch_sample' => array_slice($familyMismatch, 0, 10, true),
    'family_tree_extra_sample' => array_slice($familyExtra, 0, 10, true),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
