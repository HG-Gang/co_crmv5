<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:01
 */

/**
 * 旧项目路由清单对比工具。
 *
 * 文件功能：
 * - 把旧项目路由清单与当前项目路由清单按 URI 分组对比，输出逐条匹配状态。
 * - 状态包括 matched（方法完全匹配）、missing_uri（当前无此 URI）、missing_methods（URI 存在但方法缺失）。
 * - 支持 methodPolicies 声明“有意限制方法”的豁免：当缺失方法集合与策略中 accepted_current_methods 完全一致且附有 reason 时，状态标记为 intentional_method_restriction。
 *
 * 适用场景：
 * - 迁移核验时生成旧路由是否已被新项目覆盖的报告，供 LegacyImplementationMatrix 引用。
 *
 * 入参例子：
 * - compare($legacyRoutes, $currentRoutes, ['/user/list' => ['accepted_current_methods' => ['GET'], 'reason' => '仅保留查询']])
 *
 * 返回值：
 * - 每条旧路由返回一行数组：legacy_methods / legacy_uri / legacy_name / legacy_action / status / missing_methods / decision_reason / current_methods / current_name / current_action。
 *
 * 异常或失败场景：
 * - 纯数组对比，无异常抛出；HEAD 方法在对比前被归一化剔除。
 */
namespace App\Support;

class LegacyRouteInventory
{
    /**
     * 对比新旧路由清单：按 URI 分组后逐条判定匹配状态。
     *
     * 状态语义：matched（方法完全覆盖）；missing_uri（当前无此 URI）；missing_methods（URI 存在但方法缺失）。
     * 缺失方法集合与 methodPolicies 中 accepted_current_methods 完全一致且附 reason 时，
     * 状态升级为 intentional_method_restriction（声明式豁免，不作为迁移缺项）。
     *
     * @param array<int, array<string, mixed>> $legacyRoutes 旧项目路由清单。
     * @param array<int, array<string, mixed>> $currentRoutes 当前项目路由清单。
     * @param array<string, array<string, mixed>> $methodPolicies 按 URI 声明的方法豁免策略。
     * @return array<int, array<string, mixed>> 逐条匹配结果行。
     */
    public function compare(array $legacyRoutes, array $currentRoutes, array $methodPolicies = []): array
    {
        $currentByUri = [];
        foreach ($currentRoutes as $route) {
            $currentByUri[$route['uri']][] = $route;
        }

        $rows = [];
        foreach ($legacyRoutes as $legacy) {
            $required = $this->normalizeMethods($legacy['methods'] ?? []);
            $candidates = $currentByUri[$legacy['uri']] ?? [];
            $matched = null;
            $bestMissing = $required;

            foreach ($candidates as $candidate) {
                $available = $this->normalizeMethods($candidate['methods'] ?? []);
                $missing = array_values(array_diff($required, $available));

                if ($missing === []) {
                    $matched = $candidate;
                    $bestMissing = [];
                    break;
                }

                if ($matched === null || count($missing) < count($bestMissing)) {
                    $matched = $candidate;
                    $bestMissing = $missing;
                }
            }

            $status = $candidates === []
                ? 'missing_uri'
                : ($bestMissing === [] ? 'matched' : 'missing_methods');
            $currentMethods = $matched ? $this->normalizeMethods($matched['methods'] ?? []) : [];
            $decisionReason = null;

            if ($status === 'missing_methods') {
                $policy = $methodPolicies[$legacy['uri']] ?? null;
                $acceptedMethods = is_array($policy)
                    ? $this->normalizeMethods($policy['accepted_current_methods'] ?? [])
                    : [];
                $reason = is_array($policy) ? trim((string) ($policy['reason'] ?? '')) : '';

                if ($acceptedMethods === $currentMethods && $reason !== '') {
                    $status = 'intentional_method_restriction';
                    $decisionReason = $reason;
                }
            }

            $rows[] = [
                'legacy_methods' => $required,
                'legacy_uri' => $legacy['uri'],
                'legacy_name' => $legacy['name'] ?? null,
                'legacy_action' => $legacy['action'] ?? null,
                'status' => $status,
                'missing_methods' => $bestMissing,
                'decision_reason' => $decisionReason,
                'current_methods' => $currentMethods,
                'current_name' => $matched['name'] ?? null,
                'current_action' => $matched['action'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * 归一化方法列表：转大写、去重、剔除隐式 HEAD、排序，保证两侧对比口径一致。
     *
     * @param array<int, mixed> $methods 原始方法列表。
     * @return array<int, string> 归一化后的方法列表。
     */
    private function normalizeMethods(array $methods): array
    {
        $methods = array_values(array_unique(array_filter(
            array_map('strtoupper', $methods),
            static function ($method) {
                return $method !== 'HEAD';
            }
        )));
        sort($methods);

        return $methods;
    }
}
