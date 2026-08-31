<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

namespace App\Support;

/**
 * 旧项目模块逻辑迁移核验矩阵生成器。
 *
 * 文件功能：连接旧路由、旧 Controller/Blade 静态证据、项目2路由映射和人工核验注册表。
 * 只有当前路由映射、七个业务维度和自动化测试结果全部有效时，才允许输出 `verified`；
 * 重复、缺项或过期证据会抛出异常，避免重新生成报告时覆盖或伪造核验结论。
 */
class LegacyImplementationMatrix
{
    /** @var array<int, string> 标记业务闭环完成前必须逐项提供证据的维度。 */
    private const REQUIRED_VERIFICATION_DIMENSIONS = [
        'legacy_behavior',
        'route_mapping',
        'backend_logic',
        'frontend_contract',
        'auth_and_scope',
        'validation_and_errors',
        'automated_tests',
    ];

    /** @var array<int, string> 无法由 Controller 清单直接提取时允许登记的旧源码类型。 */
    private const LEGACY_SOURCE_RESOLUTION_KINDS = [
        'closure',
        'vendor_controller',
        'inherited_or_trait',
        'controller_outside_inventory',
        'legacy_action_missing',
        'intentional_method_restriction',
        'route_definition',
    ];

    /**
     * 构建逐 HTTP 方法迁移核验矩阵。
     *
     * @param array<int, array<string, mixed>> $legacyRoutes 旧项目框架导出的路由。
     * @param array<int, array<string, mixed>> $routeAudit 新旧路由映射审计结果。
     * @param array<string, mixed> $sourceInventory 旧 Controller 与 Blade 静态清单。
     * @param array<string, mixed> $verificationRegistry 人工复核后持久化的逐路由证据。
     * @return array<string, mixed> 包含统计和逐方法记录的核验矩阵。
     *
     * @throws \InvalidArgumentException 当证据重复、缺项、格式错误或与当前路由不一致时抛出。
     */
    public function build(
        array $legacyRoutes,
        array $routeAudit,
        array $sourceInventory,
        array $verificationRegistry = []
    ): array
    {
        $verificationByKey = $this->verificationByKey($verificationRegistry);
        $auditByKey = [];
        foreach ($routeAudit as $row) {
            foreach ($row['legacy_methods'] ?? [] as $method) {
                $auditByKey[$this->key((string) $method, (string) ($row['legacy_uri'] ?? ''))] = $row;
            }
        }

        $methodsByAction = [];
        foreach ($sourceInventory['controllers'] ?? [] as $controller) {
            $class = (string) ($controller['class'] ?? '');
            foreach ($controller['methods'] ?? [] as $method) {
                $methodsByAction[$this->actionKey($class . '@' . (string) ($method['name'] ?? ''))] = [
                    'path' => (string) ($controller['path'] ?? ''),
                    'method' => $method,
                ];
            }
        }

        $rows = [];
        foreach ($legacyRoutes as $legacyRoute) {
            foreach ($legacyRoute['methods'] ?? [] as $method) {
                if (strtoupper((string) $method) === 'HEAD') {
                    continue;
                }

                $uri = ltrim((string) ($legacyRoute['uri'] ?? ''), '/');
                $action = (string) ($legacyRoute['action'] ?? '');
                $source = $methodsByAction[$this->actionKey($action)] ?? null;
                $audit = $auditByKey[$this->key((string) $method, $uri)] ?? [];
                $blades = $this->findBladesForUri($sourceInventory['blades'] ?? [], $uri);
                $sourceMethod = $source['method'] ?? [];
                $routeKey = $this->key((string) $method, $uri);
                $routeEvidence = $verificationByKey[$routeKey] ?? [];
                $sourceResolution = $this->sourceResolution($source, $routeEvidence, $routeKey);
                $verification = $this->validatedVerification($routeEvidence, $audit, $routeKey);

                $rows[] = [
                    'domain' => $this->domainFor($uri, $action),
                    'legacy_method' => strtoupper((string) $method),
                    'legacy_uri' => $uri,
                    'legacy_name' => (string) ($legacyRoute['name'] ?? ''),
                    'legacy_action' => $action,
                    'legacy_source' => $sourceResolution['display'],
                    'legacy_source_kind' => $sourceResolution['kind'],
                    'legacy_source_references' => $sourceResolution['references'],
                    'legacy_source_conclusion' => $sourceResolution['conclusion'],
                    'request_fields' => $sourceMethod['request_fields'] ?? [],
                    'tables' => $sourceMethod['tables'] ?? [],
                    'legacy_views' => $sourceMethod['views'] ?? [],
                    'response_types' => $sourceMethod['response_types'] ?? [],
                    'conditional_branches' => (int) ($sourceMethod['conditional_branches'] ?? 0),
                    'return_statements' => (int) ($sourceMethod['return_statements'] ?? 0),
                    'external_calls' => $sourceMethod['external_calls'] ?? [],
                    'legacy_blades' => $blades,
                    'route_audit_status' => (string) ($audit['status'] ?? 'unmatched'),
                    'current_name' => (string) ($audit['current_name'] ?? ''),
                    'current_action' => (string) ($audit['current_action'] ?? ''),
                    'verification_group' => (string) ($routeEvidence['verification_group'] ?? ''),
                    'verification' => $verification,
                    'evidence_state' => $this->evidenceState($sourceResolution, $audit, $verification),
                ];
            }
        }

        usort($rows, static function (array $left, array $right): int {
            return [$left['domain'], $left['legacy_uri'], $left['legacy_method']]
                <=> [$right['domain'], $right['legacy_uri'], $right['legacy_method']];
        });

        return [
            'summary' => [
                'legacy_route_methods' => count($rows),
                'verified' => count(array_filter($rows, static function (array $row): bool {
                    return $row['evidence_state'] === 'verified';
                })),
                'needs_manual_business_review' => count(array_filter($rows, static function (array $row): bool {
                    return $row['evidence_state'] === 'needs_manual_business_review';
                })),
                'unresolved_legacy_source' => count(array_filter($rows, static function (array $row): bool {
                    return $row['evidence_state'] === 'unresolved_legacy_source';
                })),
                'unmatched_current_route' => count(array_filter($rows, static function (array $row): bool {
                    return $row['evidence_state'] === 'unmatched_current_route';
                })),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 将矩阵渲染为 UTF-8 中文 Markdown。
     *
     * @param array<string, mixed> $matrix build() 返回的矩阵。
     * @return string 可直接写入审计文档的 Markdown 内容。
     */
    public function toMarkdown(array $matrix): string
    {
        $summary = $matrix['summary'] ?? [];
        $lines = [
            '# 旧项目模块逻辑迁移核验矩阵',
            '',
            '> 本矩阵将旧路由、旧 Controller 方法、旧 Blade 交互与项目2路由审计连接。状态只表示静态证据完整度，不表示业务已完成；任何“已匹配但待人工业务核验”都必须在逐项读代码、前端接线、权限、数据状态和自动化测试闭环后才能改为完成。',
            '',
            '## 证据统计',
            '',
            '- 旧路由 HTTP 方法：' . (int) ($summary['legacy_route_methods'] ?? 0),
            '- 已完成业务闭环核验：' . (int) ($summary['verified'] ?? 0),
            '- 已匹配但待人工业务核验：' . (int) ($summary['needs_manual_business_review'] ?? 0),
            '- 未解决旧源码证据：' . (int) ($summary['unresolved_legacy_source'] ?? 0),
            '- 未匹配项目2路由：' . (int) ($summary['unmatched_current_route'] ?? 0),
            '',
        ];

        $domains = [];
        foreach ($matrix['rows'] ?? [] as $row) {
            $domains[(string) ($row['domain'] ?? '未分类')][] = $row;
        }

        foreach ($domains as $domain => $rows) {
            $lines[] = '## ' . $domain;
            $lines[] = '';

            foreach ($rows as $row) {
                $lines[] = '### `' . $this->markdownInline((string) ($row['legacy_method'] ?? '') . ' ' . (string) ($row['legacy_uri'] ?? '')) . '`';
                $lines[] = '';
                $lines[] = '- 旧 action：`' . $this->markdownInline((string) ($row['legacy_action'] ?? '')) . '`';
                $lines[] = '- 旧源码：' . $this->markdownValue((string) ($row['legacy_source'] ?? ''));
                $lines[] = '- 旧源码类型：`' . $this->markdownInline((string) ($row['legacy_source_kind'] ?? 'unresolved')) . '`';
                $lines[] = '- 旧源码补充结论：' . $this->markdownText((string) ($row['legacy_source_conclusion'] ?? ''));
                $lines[] = '- 旧源码补充引用：' . $this->markdownValues($row['legacy_source_references'] ?? []);
                $lines[] = '- 旧请求字段：' . $this->markdownValues($row['request_fields'] ?? []);
                $lines[] = '- 旧数据表：' . $this->markdownValues($row['tables'] ?? []);
                $lines[] = '- 旧渲染视图：' . $this->markdownValues($row['legacy_views'] ?? []);
                $lines[] = '- 旧 Blade/脚本调用：' . $this->markdownValues($row['legacy_blades'] ?? []);
                $lines[] = '- 旧响应类型：' . $this->markdownValues($row['response_types'] ?? []);
                $lines[] = '- 静态条件分支/返回：' . (int) ($row['conditional_branches'] ?? 0) . ' / ' . (int) ($row['return_statements'] ?? 0);
                $lines[] = '- 静态外部调用：' . $this->markdownValues($row['external_calls'] ?? []);
                $lines[] = '- 项目2路由审计：`' . $this->markdownInline((string) ($row['route_audit_status'] ?? 'unmatched')) . '`；当前名称 `'
                    . $this->markdownInline((string) ($row['current_name'] ?? '')) . '`；当前 action `'
                    . $this->markdownInline((string) ($row['current_action'] ?? '')) . '`';
                $verification = $row['verification'] ?? [];
                $lines[] = '- 业务核验结论：' . $this->markdownText((string) ($verification['conclusion'] ?? ''));
                $lines[] = '- 业务核验分组：' . $this->markdownText((string) ($row['verification_group'] ?? ''));
                $lines[] = '- 自动化测试证据：' . $this->testEvidenceMarkdown($verification['test_evidence'] ?? []);
                $lines[] = '- 核验状态：' . $this->evidenceLabel((string) ($row['evidence_state'] ?? ''));
                $lines[] = '';
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * 将注册表转换为按“HTTP 方法 + URI”唯一索引的证据集合。
     *
     * @param array<string, mixed> $registry 持久化证据注册表。
     * @return array<string, array<string, mixed>> 规范化后的证据索引。
     */
    private function verificationByKey(array $registry): array
    {
        if ($registry === []) {
            return [];
        }

        if ((int) ($registry['schema_version'] ?? 0) !== 1) {
            throw new \InvalidArgumentException('路由核验证据 schema_version 必须为 1。');
        }

        $routes = $registry['routes'] ?? null;
        if (! is_array($routes)) {
            throw new \InvalidArgumentException('路由核验证据缺少 routes 数组。');
        }

        $indexed = [];
        foreach ($routes as $index => $routeEvidence) {
            if (! is_array($routeEvidence)) {
                throw new \InvalidArgumentException('第 ' . ($index + 1) . ' 条路由核验证据必须是对象。');
            }

            $method = strtoupper(trim((string) ($routeEvidence['legacy_method'] ?? '')));
            $uri = ltrim(trim((string) ($routeEvidence['legacy_uri'] ?? '')), '/');
            if ($method === '') {
                throw new \InvalidArgumentException('第 ' . ($index + 1) . ' 条路由核验证据缺少 legacy_method。');
            }

            $key = $this->key($method, $uri);
            if (array_key_exists($key, $indexed)) {
                throw new \InvalidArgumentException('重复路由核验证据：' . $key . '。');
            }

            $indexed[$key] = $routeEvidence;
        }

        $verificationGroups = $registry['verification_groups'] ?? [];
        if (! is_array($verificationGroups)) {
            throw new \InvalidArgumentException('verification_groups 必须是数组。');
        }

        $groupIds = [];
        $groupOwnersByKey = [];
        foreach ($verificationGroups as $groupIndex => $group) {
            if (! is_array($group)) {
                throw new \InvalidArgumentException('第 ' . ($groupIndex + 1) . ' 个核验分组必须是对象。');
            }

            $groupId = trim((string) ($group['id'] ?? ''));
            if ($groupId === '') {
                throw new \InvalidArgumentException('第 ' . ($groupIndex + 1) . ' 个核验分组缺少 id。');
            }
            if (isset($groupIds[$groupId])) {
                throw new \InvalidArgumentException('重复核验分组 id：' . $groupId . '。');
            }
            $groupIds[$groupId] = true;

            $groupRoutes = $group['routes'] ?? null;
            $sharedVerification = $group['verification'] ?? null;
            if (! is_array($groupRoutes) || $groupRoutes === [] || ! is_array($sharedVerification)) {
                throw new \InvalidArgumentException($groupId . ' 必须提供非空 routes 和 verification。');
            }
            if (array_key_exists('current_route', $sharedVerification)) {
                throw new \InvalidArgumentException($groupId . ' 的 current_route 必须逐路由登记，不能放在共享 verification。');
            }

            foreach ($groupRoutes as $routeIndex => $groupRoute) {
                if (! is_array($groupRoute)) {
                    throw new \InvalidArgumentException($groupId . ' 第 ' . ($routeIndex + 1) . ' 条路由必须是对象。');
                }

                $method = strtoupper(trim((string) ($groupRoute['legacy_method'] ?? '')));
                $uri = ltrim(trim((string) ($groupRoute['legacy_uri'] ?? '')), '/');
                if ($method === '' || strpos($method, '*') !== false || strpos($uri, '*') !== false) {
                    throw new \InvalidArgumentException($groupId . ' 只能登记显式 HTTP 方法和 URI，不能使用通配符。');
                }

                $key = $this->key($method, $uri);
                if (isset($groupOwnersByKey[$key])) {
                    throw new \InvalidArgumentException(
                        '重复分组核验证据：' . $key . ' 同时属于 '
                        . $groupOwnersByKey[$key] . ' 和 ' . $groupId . '。'
                    );
                }
                if (isset($indexed[$key]['verification'])) {
                    throw new \InvalidArgumentException('重复分组核验证据：' . $key . ' 已有独立 verification。');
                }

                $verification = $sharedVerification;
                $verification['current_route'] = $groupRoute['current_route'] ?? null;
                $indexed[$key] = array_merge($indexed[$key] ?? [], [
                    'legacy_method' => $method,
                    'legacy_uri' => $uri,
                    'verification_group' => $groupId,
                    'verification' => $verification,
                ]);
                $groupOwnersByKey[$key] = $groupId;
            }
        }

        return $indexed;
    }

    /**
     * 解析旧源码证据；Controller 清单无法覆盖 Closure、vendor 或历史坏路由时使用显式结论。
     *
     * @param array<string, mixed>|null $source 静态 Controller 方法证据。
     * @param array<string, mixed> $routeEvidence 当前路由的持久化证据。
     * @param string $routeKey 当前路由唯一键。
     * @return array{kind:string, display:string, references:array<int, string>, conclusion:string}
     */
    private function sourceResolution(array $source = null, array $routeEvidence, string $routeKey): array
    {
        if ($source !== null) {
            $sourceMethod = $source['method'] ?? [];
            $reference = (string) ($source['path'] ?? '') . ':' . (int) ($sourceMethod['line'] ?? 0);

            return [
                'kind' => 'controller_method',
                'display' => $reference,
                'references' => [$reference],
                'conclusion' => '',
            ];
        }

        $resolution = $routeEvidence['legacy_source_resolution'] ?? null;
        if ($resolution === null) {
            return [
                'kind' => 'unresolved',
                'display' => '未检索到旧 Controller 方法静态证据',
                'references' => [],
                'conclusion' => '',
            ];
        }

        if (! is_array($resolution)) {
            throw new \InvalidArgumentException($routeKey . ' 的 legacy_source_resolution 必须是对象。');
        }

        $kind = trim((string) ($resolution['kind'] ?? ''));
        if (! in_array($kind, self::LEGACY_SOURCE_RESOLUTION_KINDS, true)) {
            throw new \InvalidArgumentException($routeKey . ' 的旧源码类型无效：' . $kind . '。');
        }

        $references = $this->nonEmptyStringValues($resolution['references'] ?? []);
        $conclusion = trim((string) ($resolution['conclusion'] ?? ''));
        if ($references === [] || $conclusion === '') {
            throw new \InvalidArgumentException($routeKey . ' 的旧源码补充证据必须同时提供 references 和 conclusion。');
        }

        return [
            'kind' => $kind,
            'display' => implode('、', $references),
            'references' => $references,
            'conclusion' => $conclusion,
        ];
    }

    /**
     * 校验完成证据并确认它仍指向当前运行时路由。
     *
     * @param array<string, mixed> $routeEvidence 当前旧路由的注册表记录。
     * @param array<string, mixed> $audit 当前新旧路由审计记录。
     * @param string $routeKey 当前路由唯一键。
     * @return array<string, mixed> 已校验的 verification 节点；未登记时返回空数组。
     */
    private function validatedVerification(array $routeEvidence, array $audit, string $routeKey): array
    {
        $verification = $routeEvidence['verification'] ?? null;
        if ($verification === null) {
            return [];
        }

        if (! is_array($verification) || (string) ($verification['state'] ?? '') !== 'verified') {
            throw new \InvalidArgumentException($routeKey . ' 的 verification.state 只允许为 verified。');
        }

        $verifiedAt = trim((string) ($verification['verified_at'] ?? ''));
        $conclusion = trim((string) ($verification['conclusion'] ?? ''));
        if ($verifiedAt === '' || strtotime($verifiedAt) === false || $conclusion === '') {
            throw new \InvalidArgumentException($routeKey . ' 的完成证据必须提供有效 verified_at 和 conclusion。');
        }

        if (! in_array((string) ($audit['status'] ?? ''), ['matched', 'intentional_method_restriction'], true)) {
            throw new \InvalidArgumentException($routeKey . ' 尚未匹配项目2路由，不能标记 verified。');
        }

        $currentRoute = $verification['current_route'] ?? null;
        if (! is_array($currentRoute)
            || ! array_key_exists('name', $currentRoute)
            || ! array_key_exists('action', $currentRoute)) {
            throw new \InvalidArgumentException($routeKey . ' 的完成证据缺少 current_route.name/action。');
        }

        if ((string) $currentRoute['name'] !== (string) ($audit['current_name'] ?? '')
            || (string) $currentRoute['action'] !== (string) ($audit['current_action'] ?? '')) {
            throw new \InvalidArgumentException($routeKey . ' 的完成证据与当前路由映射不一致。');
        }

        $dimensions = $verification['dimensions'] ?? null;
        if (! is_array($dimensions)) {
            throw new \InvalidArgumentException($routeKey . ' 缺少核验维度 dimensions。');
        }

        foreach (self::REQUIRED_VERIFICATION_DIMENSIONS as $dimension) {
            $item = $dimensions[$dimension] ?? null;
            if (! is_array($item)) {
                throw new \InvalidArgumentException($routeKey . ' 缺少核验维度：' . $dimension . '。');
            }

            $result = (string) ($item['result'] ?? '');
            if ($result === 'passed') {
                if ($this->nonEmptyEvidenceValues($item['evidence'] ?? []) === []) {
                    throw new \InvalidArgumentException($routeKey . ' 的核验维度 ' . $dimension . ' 缺少通过证据。');
                }
                continue;
            }

            if ($result === 'not_applicable' && trim((string) ($item['reason'] ?? '')) !== '') {
                continue;
            }

            throw new \InvalidArgumentException(
                $routeKey . ' 的核验维度 ' . $dimension . ' 必须为 passed 或提供原因的 not_applicable。'
            );
        }

        $tests = $verification['test_evidence'] ?? null;
        if (! is_array($tests) || $tests === []) {
            throw new \InvalidArgumentException($routeKey . ' 缺少通过的自动化测试证据。');
        }
        foreach ($tests as $test) {
            if (! is_array($test)
                || trim((string) ($test['file'] ?? '')) === ''
                || trim((string) ($test['command'] ?? '')) === ''
                || (string) ($test['result'] ?? '') !== 'passed') {
                throw new \InvalidArgumentException($routeKey . ' 存在无效的自动化测试证据。');
            }
        }

        return $verification;
    }

    /**
     * 过滤空白引用并保持原始顺序。
     *
     * @param mixed $values 待过滤的引用列表。
     * @return array<int, string> 非空字符串引用。
     */
    private function nonEmptyStringValues($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($value): string {
            return trim(is_scalar($value) ? (string) $value : '');
        }, $values), static function (string $value): bool {
            return $value !== '';
        }));
    }

    /**
     * 保留维度中的字符串或结构化证据，拒绝空值。
     *
     * @param mixed $values 待检查的证据列表。
     * @return array<int, mixed> 非空证据。
     */
    private function nonEmptyEvidenceValues($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static function ($value): bool {
            if (is_array($value)) {
                return $value !== [];
            }

            return is_scalar($value) && trim((string) $value) !== '';
        }));
    }

    /**
     * 查找引用了指定 URI 的 Blade 文件（表单 action / AJAX / 脚本端点任一命中即算）。
     *
     * @param array<int, array<string, mixed>> $blades Blade 静态清单。
     * @param string $uri 旧路由 URI。
     * @return array<int, string> 命中 Blade 的相对路径。
     */
    private function findBladesForUri(array $blades, string $uri): array
    {
        $matches = [];
        $needle = '/' . ltrim($uri, '/');

        foreach ($blades as $blade) {
            $found = false;
            foreach ($blade['forms'] ?? [] as $form) {
                if ($this->sameEndpoint((string) ($form['action'] ?? ''), $needle)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                foreach ($blade['ajax_endpoints'] ?? [] as $endpoint) {
                    if ($this->sameEndpoint((string) $endpoint, $needle)) {
                        $found = true;
                        break;
                    }
                }
            }

            if (! $found) {
                foreach ($blade['script_endpoints'] ?? [] as $endpoint) {
                    if ($this->sameEndpoint((string) $endpoint, $needle)) {
                        $found = true;
                        break;
                    }
                }
            }

            if ($found) {
                $matches[] = (string) ($blade['path'] ?? '');
            }
        }

        return array_values(array_unique(array_filter($matches)));
    }

    /**
     * 比较静态端点与目标 URI 是否同路径（忽略域名与 query；含 Blade 模板占位符的端点不参与匹配）。
     *
     * @param string $endpoint 静态扫描到的端点。
     * @param string $uri 目标 URI。
     * @return bool 路径一致为 true。
     */
    private function sameEndpoint(string $endpoint, string $uri): bool
    {
        if ($endpoint === '' || strpos($endpoint, '{{') !== false || strpos($endpoint, '{!!') !== false) {
            return false;
        }

        $endpointPath = parse_url($endpoint, PHP_URL_PATH);
        if (! is_string($endpointPath)) {
            $endpointPath = $endpoint;
        }

        return '/' . trim($endpointPath, '/') === '/' . trim($uri, '/');
    }

    /**
     * 依据旧源码、当前路由和完整业务证据确定最终状态。
     *
     * @param array<string, mixed> $sourceResolution 已解析的旧源码结论。
     * @param array<string, mixed> $audit 当前路由审计记录。
     * @param array<string, mixed> $verification 已通过严格校验的业务证据。
     * @return string 矩阵状态代码。
     */
    private function evidenceState(array $sourceResolution, array $audit, array $verification): string
    {
        if ((string) ($sourceResolution['kind'] ?? 'unresolved') === 'unresolved') {
            return 'unresolved_legacy_source';
        }

        if (! in_array((string) ($audit['status'] ?? ''), ['matched', 'intentional_method_restriction'], true)) {
            return 'unmatched_current_route';
        }

        if ((string) ($verification['state'] ?? '') === 'verified') {
            return 'verified';
        }

        return 'needs_manual_business_review';
    }

    /**
     * 按 URI/action 归属业务域（后台管理员/大代理/代理商/普通用户），用于矩阵分组展示。
     *
     * @param string $uri 旧路由 URI。
     * @param string $action 旧路由 action。
     * @return string 业务域中文名。
     */
    private function domainFor(string $uri, string $action): string
    {
        if (strpos($uri, 'index/') === 0 || strpos($action, '\\Http\\Controllers\\Admin\\') !== false) {
            return '后台管理员';
        }

        if (strpos($uri, 'user/agents/') === 0 || strpos($action, '\\Http\\Controllers\\Admin\\BigNumberController@') !== false) {
            return '大代理';
        }

        if (strpos($uri, 'user/proxy/') === 0
            || strpos($uri, 'user/cust/') === 0
            || strpos($uri, 'user/change/') === 0
            || strpos($uri, 'user/position/') === 0
            || strpos($uri, 'user/realtime/') === 0
            || strpos($action, '\\Http\\Controllers\\User\\Proxy') !== false
            || strpos($action, '\\Http\\Controllers\\User\\DirectCustomer') !== false
            || strpos($action, '\\Http\\Controllers\\User\\PositionSummary') !== false
            || strpos($action, '\\Http\\Controllers\\User\\RealCommission') !== false) {
            return '代理商';
        }

        return '普通用户/公共';
    }

    /**
     * 路由唯一键：大写方法 + 去前导斜杠的 URI。
     *
     * @param string $method HTTP 方法。
     * @param string $uri URI。
     * @return string 唯一键。
     */
    private function key(string $method, string $uri): string
    {
        return strtoupper($method) . ' ' . ltrim($uri, '/');
    }

    /**
     * 生成 action 唯一键。
     *
     * PHP 类名与方法名大小写不敏感，旧路由导出未保持统一大小写风格，
     * 统一转小写后再参与匹配，避免同一 action 因大小写差异被拆成两条证据。
     *
     * @param string $action 旧路由 action（Controller@method 形式）。
     * @return string 小写化后的唯一键。
     */
    private function actionKey(string $action): string
    {
        // PHP resolves class and method names case-insensitively; old route exports do not preserve a single casing style.
        return strtolower($action);
    }

    /**
     * 状态码转中文标签。
     *
     * @param string $state 证据状态码。
     * @return string 中文说明。
     */
    private function evidenceLabel(string $state): string
    {
        $labels = [
            'verified' => '`verified`：旧行为、路由、后端、前端、权限、错误路径和自动化测试均已核验',
            'needs_manual_business_review' => '`needs_manual_business_review`：已匹配但待人工业务核验',
            'unresolved_legacy_source' => '`unresolved_legacy_source`：未解决旧源码证据',
            'unmatched_current_route' => '`unmatched_current_route`：未匹配项目2路由',
        ];

        return $labels[$state] ?? '`unknown`：未知状态';
    }

    /**
     * 值列表渲染为顿号分隔的 Markdown 引用项。
     *
     * @param array<int, mixed> $values 值列表。
     * @return string Markdown 字符串；空列表返回"未检索到"。
     */
    private function markdownValues(array $values): string
    {
        if ($values === []) {
            return '未检索到';
        }

        return implode('、', array_map(function ($value): string {
            return $this->markdownValue((string) $value);
        }, $values));
    }

    /**
     * 单个值渲染为 Markdown 行内引用。
     *
     * @param string $value 值。
     * @return string 行内引用。
     */
    private function markdownValue(string $value): string
    {
        return '`' . $this->markdownInline($value) . '`';
    }

    /**
     * 输出普通中文说明；空值明确显示为“未登记”。
     */
    private function markdownText(string $value): string
    {
        $value = trim($value);

        return $value === '' ? '未登记' : $this->markdownInline($value);
    }

    /**
     * 将自动化测试文件、命令和结果整理为单行中文证据。
     *
     * @param mixed $tests verification.test_evidence 节点。
     */
    private function testEvidenceMarkdown($tests): string
    {
        if (! is_array($tests) || $tests === []) {
            return '未登记';
        }

        $values = [];
        foreach ($tests as $test) {
            if (! is_array($test)) {
                continue;
            }

            $file = trim((string) ($test['file'] ?? ''));
            $command = trim((string) ($test['command'] ?? ''));
            $result = (string) ($test['result'] ?? '');
            if ($file === '' || $command === '') {
                continue;
            }

            $resultLabel = $result === 'passed' ? '通过' : $result;
            $values[] = '`' . $this->markdownInline($file) . '`；命令 `'
                . $this->markdownInline($command) . '`；结果：' . $this->markdownInline($resultLabel);
        }

        return $values === [] ? '未登记' : implode('；', $values);
    }

    /**
     * 转义 Markdown 行内反引号，防止值中反引号破坏表格/引用结构。
     *
     * @param string $value 原始值。
     * @return string 转义后的值。
     */
    private function markdownInline(string $value): string
    {
        return str_replace('`', '\\`', $value);
    }
}
