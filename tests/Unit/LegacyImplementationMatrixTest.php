<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 11:57
 */

namespace Tests\Unit;

use App\Support\LegacyImplementationMatrix;
use Tests\TestCase;

/**
 * 旧项目迁移核验矩阵契约测试。
 *
 * 文件功能：验证旧路由、旧源码、项目2路由和持久化业务核验证据只能按明确规则合并；
 * 证据缺失、重复或与当前路由不一致时必须失败，避免生成文件误报“已完成”。
 */
class LegacyImplementationMatrixTest extends TestCase
{
    public function test_it_connects_a_legacy_route_to_controller_blade_and_current_mapping_evidence(): void
    {
        $legacyRoutes = [[
            'methods' => ['POST'],
            'uri' => 'user/deposit/apply',
            'name' => 'legacy_deposit_apply',
            'action' => 'App\\Http\\Controllers\\User\\DepositController@apply',
        ]];
        $routeAudit = [[
            'legacy_methods' => ['POST'],
            'legacy_uri' => 'user/deposit/apply',
            'legacy_action' => 'App\\Http\\Controllers\\User\\DepositController@apply',
            'status' => 'matched',
            'current_name' => 'front_deposit_apply',
            'current_action' => 'App\\Http\\Controllers\\Front\\DepositController@apply',
        ]];
        $sourceInventory = [
            'controllers' => [[
                'class' => 'App\\Http\\Controllers\\User\\DepositController',
                'path' => 'app/Http/Controllers/User/DepositController.php',
                'methods' => [[
                    'name' => 'apply',
                    'line' => 31,
                    'request_fields' => ['amount', 'channel_id'],
                    'tables' => ['deposit_records'],
                    'views' => [],
                    'response_types' => ['json'],
                    'conditional_branches' => 2,
                    'return_statements' => 3,
                    'external_calls' => ['PaymentService'],
                ]],
            ]],
            'blades' => [[
                'path' => 'resources/views/user/deposit/list.blade.php',
                'forms' => [['method' => 'POST', 'action' => '/user/deposit/apply', 'fields' => ['amount', 'channel_id']]],
                'ajax_endpoints' => ['/user/deposit/apply'],
                'script_sources' => ['js/deposit.js'],
                'script_endpoints' => ['/user/deposit/apply'],
                'route_names' => [],
                'uploads' => 0,
                'downloads' => 0,
            ]],
        ];

        $matrix = (new LegacyImplementationMatrix())->build($legacyRoutes, $routeAudit, $sourceInventory);

        $this->assertSame(1, $matrix['summary']['legacy_route_methods']);
        $this->assertSame('app/Http/Controllers/User/DepositController.php:31', $matrix['rows'][0]['legacy_source']);
        $this->assertSame(['amount', 'channel_id'], $matrix['rows'][0]['request_fields']);
        $this->assertSame(['resources/views/user/deposit/list.blade.php'], $matrix['rows'][0]['legacy_blades']);
        $this->assertSame('front_deposit_apply', $matrix['rows'][0]['current_name']);
        $this->assertSame('needs_manual_business_review', $matrix['rows'][0]['evidence_state']);
    }

    public function test_it_connects_a_route_called_only_by_a_blade_external_script(): void
    {
        $matrix = (new LegacyImplementationMatrix())->build(
            [[
                'methods' => ['POST'],
                'uri' => 'user/deposit/history',
                'name' => null,
                'action' => 'App\\Http\\Controllers\\User\\DepositController@history',
            ]],
            [[
                'legacy_methods' => ['POST'],
                'legacy_uri' => 'user/deposit/history',
                'status' => 'matched',
                'current_name' => 'front_history',
                'current_action' => 'App\\Http\\Controllers\\Front\\DepositController@depositHistory',
            ]],
            ['controllers' => [[
                'class' => 'App\\Http\\Controllers\\User\\DepositController',
                'path' => 'app/Http/Controllers/User/DepositController.php',
                'methods' => [[
                    'name' => 'history', 'line' => 44, 'request_fields' => [], 'tables' => [], 'views' => [],
                    'response_types' => ['json'], 'conditional_branches' => 0, 'return_statements' => 1, 'external_calls' => [],
                ]],
            ]], 'blades' => [[
                'path' => 'resources/views/user/deposit/list.blade.php',
                'forms' => [], 'ajax_endpoints' => [], 'script_sources' => ['js/deposit.js'],
                'script_endpoints' => ['/user/deposit/history'], 'route_names' => [], 'uploads' => 0, 'downloads' => 0,
            ]]]
        );

        $this->assertSame(['resources/views/user/deposit/list.blade.php'], $matrix['rows'][0]['legacy_blades']);
    }

    public function test_it_marks_a_route_without_a_source_method_as_unresolved_source_evidence(): void
    {
        $matrix = (new LegacyImplementationMatrix())->build(
            [[
                'methods' => ['POST'],
                'uri' => 'user/missing',
                'name' => null,
                'action' => 'App\\Http\\Controllers\\User\\MissingController@save',
            ]],
            [],
            ['controllers' => [], 'blades' => []]
        );

        $this->assertSame('unresolved_legacy_source', $matrix['rows'][0]['evidence_state']);
        $this->assertSame('未检索到旧 Controller 方法静态证据', $matrix['rows'][0]['legacy_source']);
    }

    public function test_it_resolves_non_controller_legacy_source_from_persisted_evidence_without_claiming_business_completion(): void
    {
        $matrix = (new LegacyImplementationMatrix())->build(
            [[
                'methods' => ['GET'],
                'uri' => '',
                'name' => null,
                'action' => 'Closure',
            ]],
            [[
                'legacy_methods' => ['GET'],
                'legacy_uri' => '',
                'status' => 'matched',
                'current_name' => '',
                'current_action' => 'Closure',
            ]],
            ['controllers' => [], 'blades' => []],
            [
                'schema_version' => 1,
                'routes' => [[
                    'legacy_method' => 'GET',
                    'legacy_uri' => '',
                    'legacy_source_resolution' => [
                        'kind' => 'closure',
                        'references' => ['app/Http/routes.php:17'],
                        'conclusion' => '旧入口由路由 Closure 直接重定向，不存在 Controller 方法。',
                    ],
                ]],
            ]
        );

        $this->assertSame(0, $matrix['summary']['unresolved_legacy_source']);
        $this->assertSame(1, $matrix['summary']['needs_manual_business_review']);
        $this->assertSame('closure', $matrix['rows'][0]['legacy_source_kind']);
        $this->assertSame(['app/Http/routes.php:17'], $matrix['rows'][0]['legacy_source_references']);
        $this->assertSame('needs_manual_business_review', $matrix['rows'][0]['evidence_state']);
    }

    public function test_it_marks_a_route_verified_only_with_complete_persisted_business_evidence(): void
    {
        $matrix = (new LegacyImplementationMatrix())->build(
            [[
                'methods' => ['POST'],
                'uri' => 'user/deposit/apply',
                'name' => null,
                'action' => 'App\\Http\\Controllers\\User\\DepositController@apply',
            ]],
            [[
                'legacy_methods' => ['POST'],
                'legacy_uri' => 'user/deposit/apply',
                'status' => 'matched',
                'current_name' => 'front_deposit_apply',
                'current_action' => 'App\\Http\\Controllers\\Front\\DepositController@apply',
            ]],
            ['controllers' => [[
                'class' => 'App\\Http\\Controllers\\User\\DepositController',
                'path' => 'app/Http/Controllers/User/DepositController.php',
                'methods' => [[
                    'name' => 'apply',
                    'line' => 31,
                    'request_fields' => [],
                    'tables' => [],
                    'views' => [],
                    'response_types' => ['json'],
                    'conditional_branches' => 1,
                    'return_statements' => 2,
                    'external_calls' => [],
                ]],
            ]], 'blades' => []],
            [
                'schema_version' => 1,
                'routes' => [[
                    'legacy_method' => 'POST',
                    'legacy_uri' => 'user/deposit/apply',
                    'verification' => $this->completeVerification(
                        'front_deposit_apply',
                        'App\\Http\\Controllers\\Front\\DepositController@apply'
                    ),
                ]],
            ]
        );

        $this->assertSame(1, $matrix['summary']['verified']);
        $this->assertSame(0, $matrix['summary']['needs_manual_business_review']);
        $this->assertSame('verified', $matrix['rows'][0]['evidence_state']);
        $this->assertSame('入金申请的新旧入口已完成同一业务闭环。', $matrix['rows'][0]['verification']['conclusion']);
    }

    public function test_it_rejects_incomplete_verified_evidence_instead_of_silently_downgrading_it(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('缺少核验维度');

        (new LegacyImplementationMatrix())->build(
            [['methods' => ['GET'], 'uri' => 'test', 'name' => null, 'action' => 'Closure']],
            [[
                'legacy_methods' => ['GET'],
                'legacy_uri' => 'test',
                'status' => 'matched',
                'current_name' => 'legacy_test',
                'current_action' => 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@test',
            ]],
            ['controllers' => [], 'blades' => []],
            [
                'schema_version' => 1,
                'routes' => [[
                    'legacy_method' => 'GET',
                    'legacy_uri' => 'test',
                    'legacy_source_resolution' => [
                        'kind' => 'closure',
                        'references' => ['app/Http/routes.php:20'],
                        'conclusion' => '旧测试页由 Closure 返回。',
                    ],
                    'verification' => [
                        'state' => 'verified',
                        'verified_at' => '2026-07-26T12:00:00+08:00',
                        'conclusion' => '证据不完整，不能标记完成。',
                        'current_route' => [
                            'name' => 'legacy_test',
                            'action' => 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@test',
                        ],
                        'dimensions' => [],
                        'test_evidence' => [],
                    ],
                ]],
            ]
        );
    }

    public function test_it_rejects_duplicate_route_evidence_to_keep_one_source_of_truth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('重复路由核验证据');

        (new LegacyImplementationMatrix())->build(
            [['methods' => ['GET'], 'uri' => '', 'name' => null, 'action' => 'Closure']],
            [],
            ['controllers' => [], 'blades' => []],
            [
                'schema_version' => 1,
                'routes' => [
                    ['legacy_method' => 'GET', 'legacy_uri' => ''],
                    ['legacy_method' => 'get', 'legacy_uri' => '/'],
                ],
            ]
        );
    }

    public function test_it_expands_an_explicit_verification_group_without_route_wildcards(): void
    {
        $verification = $this->completeVerification('', '');
        unset($verification['current_route']);
        $verification['conclusion'] = '认证模块的显式路由清单已完成共享证据核验。';

        $matrix = (new LegacyImplementationMatrix())->build(
            [[
                'methods' => ['POST'],
                'uri' => 'user/signIn',
                'name' => null,
                'action' => 'App\\Http\\Controllers\\User\\LoginController@signIn',
            ]],
            [[
                'legacy_methods' => ['POST'],
                'legacy_uri' => 'user/signIn',
                'status' => 'matched',
                'current_name' => 'legacy_user_sign_in',
                'current_action' => 'App\\Http\\Controllers\\Front\\AuthController@legacySignIn',
            ]],
            ['controllers' => [[
                'class' => 'App\\Http\\Controllers\\User\\LoginController',
                'path' => 'app/Http/Controllers/User/LoginController.php',
                'methods' => [[
                    'name' => 'signIn', 'line' => 80, 'request_fields' => [], 'tables' => [], 'views' => [],
                    'response_types' => ['json'], 'conditional_branches' => 1, 'return_statements' => 2,
                    'external_calls' => [],
                ]],
            ]], 'blades' => []],
            [
                'schema_version' => 1,
                'routes' => [],
                'verification_groups' => [[
                    'id' => 'front_auth_registration_2026_07_26',
                    'routes' => [[
                        'legacy_method' => 'POST',
                        'legacy_uri' => 'user/signIn',
                        'current_route' => [
                            'name' => 'legacy_user_sign_in',
                            'action' => 'App\\Http\\Controllers\\Front\\AuthController@legacySignIn',
                        ],
                    ]],
                    'verification' => $verification,
                ]],
            ]
        );

        $this->assertSame('verified', $matrix['rows'][0]['evidence_state']);
        $this->assertSame('front_auth_registration_2026_07_26', $matrix['rows'][0]['verification_group']);
        $this->assertSame(
            '认证模块的显式路由清单已完成共享证据核验。',
            $matrix['rows'][0]['verification']['conclusion']
        );
    }

    public function test_it_rejects_the_same_route_in_two_verification_groups(): void
    {
        $verification = $this->completeVerification('', '');
        unset($verification['current_route']);

        $group = [
            'id' => 'first_group',
            'routes' => [[
                'legacy_method' => 'GET',
                'legacy_uri' => '',
                'current_route' => ['name' => '', 'action' => 'Closure'],
            ]],
            'verification' => $verification,
        ];
        $second = $group;
        $second['id'] = 'second_group';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('重复分组核验证据');

        (new LegacyImplementationMatrix())->build(
            [['methods' => ['GET'], 'uri' => '', 'name' => null, 'action' => 'Closure']],
            [['legacy_methods' => ['GET'], 'legacy_uri' => '', 'status' => 'matched', 'current_name' => '', 'current_action' => 'Closure']],
            ['controllers' => [], 'blades' => []],
            ['schema_version' => 1, 'routes' => [], 'verification_groups' => [$group, $second]]
        );
    }

    public function test_it_matches_php_controller_actions_case_insensitively(): void
    {
        $matrix = (new LegacyImplementationMatrix())->build(
            [[
                'methods' => ['GET'],
                'uri' => 'index/admin/agents',
                'name' => null,
                'action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@index',
            ]],
            [[
                'legacy_methods' => ['GET'],
                'legacy_uri' => 'index/admin/agents',
                'status' => 'matched',
                'current_name' => 'legacy_agents',
                'current_action' => 'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle',
            ]],
            ['controllers' => [[
                'class' => 'App\\Http\\Controllers\\admin\\AgentControllerV3',
                'path' => 'app/Http/Controllers/Admin/AgentControllerV3.php',
                'methods' => [[
                    'name' => 'Index',
                    'line' => 12,
                    'request_fields' => [],
                    'tables' => [],
                    'views' => [],
                    'response_types' => [],
                    'conditional_branches' => 0,
                    'return_statements' => 1,
                    'external_calls' => [],
                ]],
            ]], 'blades' => []]
        );

        $this->assertSame('app/Http/Controllers/Admin/AgentControllerV3.php:12', $matrix['rows'][0]['legacy_source']);
        $this->assertSame('needs_manual_business_review', $matrix['rows'][0]['evidence_state']);
    }

    public function test_it_renders_a_chinese_markdown_matrix_with_conservative_review_states(): void
    {
        $matrix = (new LegacyImplementationMatrix())->build(
            [[
                'methods' => ['POST'],
                'uri' => 'user/missing',
                'name' => null,
                'action' => 'App\\Http\\Controllers\\User\\MissingController@save',
            ]],
            [],
            ['controllers' => [], 'blades' => []]
        );

        $markdown = (new LegacyImplementationMatrix())->toMarkdown($matrix);

        $this->assertStringContainsString('# 旧项目模块逻辑迁移核验矩阵', $markdown);
        $this->assertStringContainsString('`POST user/missing`', $markdown);
        $this->assertStringContainsString('未解决旧源码证据', $markdown);
    }

    public function test_the_matrix_cli_writes_json_and_markdown_from_fixture_evidence(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'legacy-matrix-' . uniqid('', true);
        mkdir($directory, 0777, true);
        $legacyRoutes = $directory . DIRECTORY_SEPARATOR . 'routes.json';
        $audit = $directory . DIRECTORY_SEPARATOR . 'audit.json';
        $inventory = $directory . DIRECTORY_SEPARATOR . 'inventory.json';
        $verificationEvidence = $directory . DIRECTORY_SEPARATOR . 'verification-evidence.json';
        $json = $directory . DIRECTORY_SEPARATOR . 'matrix.json';
        $markdown = $directory . DIRECTORY_SEPARATOR . '矩阵.md';

        file_put_contents($legacyRoutes, json_encode([[
            'methods' => ['POST'],
            'uri' => 'user/missing',
            'name' => null,
            'action' => 'App\\Http\\Controllers\\User\\MissingController@save',
        ]]));
        file_put_contents($audit, '[]');
        file_put_contents($inventory, json_encode(['controllers' => [], 'blades' => []]));
        file_put_contents($verificationEvidence, json_encode([
            'schema_version' => 1,
            'routes' => [[
                'legacy_method' => 'POST',
                'legacy_uri' => 'user/missing',
                'legacy_source_resolution' => [
                    'kind' => 'legacy_action_missing',
                    'references' => ['app/Http/routes.php:10'],
                    'conclusion' => '旧路由指向不存在的方法，保留为历史缺陷证据。',
                ],
            ]],
        ]));

        $command = sprintf(
            '%s %s --legacy-routes=%s --route-audit=%s --source-inventory=%s --verification-evidence=%s --json=%s --markdown=%s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('scripts/generate-legacy-implementation-matrix.php')),
            escapeshellarg($legacyRoutes),
            escapeshellarg($audit),
            escapeshellarg($inventory),
            escapeshellarg($verificationEvidence),
            escapeshellarg($json),
            escapeshellarg($markdown)
        );

        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
        $this->assertFileExists($json);
        $this->assertFileExists($markdown);
        $this->assertSame(1, json_decode((string) file_get_contents($json), true)['summary']['legacy_route_methods']);
        $this->assertStringContainsString('旧路由指向不存在的方法', (string) file_get_contents($markdown));

        unlink($legacyRoutes);
        unlink($audit);
        unlink($inventory);
        unlink($verificationEvidence);
        unlink($json);
        unlink($markdown);
        rmdir($directory);
    }

    /**
     * 矩阵生成器只读取 JSON 文件，不应为了容器解析而引导完整 Laravel 应用。
     */
    public function test_matrix_cli_is_a_filesystem_only_tool(): void
    {
        $source = (string) file_get_contents(base_path('scripts/generate-legacy-implementation-matrix.php'));

        $this->assertStringNotContainsString('bootstrap/app.php', $source);
        $this->assertStringNotContainsString('Kernel::class', $source);
        $this->assertStringNotContainsString('app(LegacyImplementationMatrix::class)', $source);
        $this->assertStringContainsString('new LegacyImplementationMatrix()', $source);
    }

    /**
     * 构造一条满足全部核验维度的最小完成证据。
     *
     * @param string $currentName 项目2当前路由名，可为空字符串。
     * @param string $currentAction 项目2当前处理器。
     * @return array<string, mixed> 可直接写入证据注册表的 verification 节点。
     */
    private function completeVerification(string $currentName, string $currentAction): array
    {
        $dimensions = [];
        foreach ([
            'legacy_behavior',
            'route_mapping',
            'backend_logic',
            'frontend_contract',
            'auth_and_scope',
            'validation_and_errors',
            'automated_tests',
        ] as $dimension) {
            $dimensions[$dimension] = [
                'result' => 'passed',
                'evidence' => ['tests/Feature/DepositClosureTest.php::test_apply'],
            ];
        }

        return [
            'state' => 'verified',
            'verified_at' => '2026-07-26T12:00:00+08:00',
            'conclusion' => '入金申请的新旧入口已完成同一业务闭环。',
            'current_route' => [
                'name' => $currentName,
                'action' => $currentAction,
            ],
            'dimensions' => $dimensions,
            'test_evidence' => [[
                'file' => 'tests/Feature/DepositClosureTest.php',
                'command' => 'php artisan test tests/Feature/DepositClosureTest.php',
                'result' => 'passed',
            ]],
        ];
    }
}
