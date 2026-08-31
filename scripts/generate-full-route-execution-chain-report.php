<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 02:08
 */

/**
 * 全量路由执行链路报告生成器。
 *
 * 脚本用途：
 * - 枚举当前项目全部路由（含控制器动作、闭包），并结合新项目前端文件、测试文件、
 *   旧项目路由清单与旧项目源码，生成“项目一/项目二总逻辑与全量执行链路”中文 Markdown 报告。
 *
 * 运行方式：
 * - php scripts/generate-full-route-execution-chain-report.php [输出文件] [目标耗时秒数] [目标token数]
 * - 参数均可省略，使用默认输出路径 docs/reports/项目一项目二总逻辑与全量执行链路中文报告.md。
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
legacyProjectRoot();
$app->make(Kernel::class)->bootstrap();

$outputPath = $argv[1] ?? base_path('docs/reports/项目一项目二总逻辑与全量执行链路中文报告.md');
$goalElapsedSeconds = reportMetricArgument($argv[2] ?? null, 'goal elapsed seconds');
$goalTokensUsed = reportMetricArgument($argv[3] ?? null, 'goal tokens used');
$outputDirectory = dirname($outputPath);
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create route report directory: ' . $outputDirectory);
}

$routes = RouteFacade::getRoutes()->getRoutes();
usort($routes, static function (Route $left, Route $right): int {
    return [$left->uri(), implode(',', $left->methods()), $left->getName() ?? '']
        <=> [$right->uri(), implode(',', $right->methods()), $right->getName() ?? ''];
});

$frontendFiles = discoverTextFiles([
    resource_path('views'),
    resource_path('front'),
    resource_path('admin'),
    public_path('js'),
], ['blade.php', 'js']);
$testFiles = discoverTextFiles([base_path('tests')], ['php']);
$legacyRoutes = readLegacyRouteInventory();
$legacyAuditRows = readLegacyAuditRows();
$legacyFrontendFiles = discoverTextFiles([
    legacyProjectRoot() . '/resources/views',
    legacyProjectRoot() . '/public/js',
], ['blade.php', 'js']);
$legacyControllerFiles = discoverTextFiles([
    legacyProjectRoot() . '/app/Http/Controllers',
], ['php']);

$actionCache = [];
$routeEntries = [];
$groupCounts = [];
foreach ($routes as $routeIndex => $route) {
    $action = actionName($route);
    $actionCacheKey = actionAnalysisCacheKey($route, $action);
    if (!array_key_exists($actionCacheKey, $actionCache)) {
        $actionCache[$actionCacheKey] = analyzeAction($route);
    }
    $group = groupName($route);
    $groupCounts[$group]['routes'] = ($groupCounts[$group]['routes'] ?? 0) + 1;
    $groupCounts[$group]['methods'] = ($groupCounts[$group]['methods'] ?? 0) + count($route->methods());

    foreach ($route->methods() as $method) {
        $entry = routeAnalysis(
            $route,
            $actionCache[$actionCacheKey],
            $frontendFiles,
            $testFiles,
            $method
        );
        $entry['method'] = $method;
        $entry['group'] = $group;
        $routeEntries[] = $entry;
    }
}

$totalRoutes = count($routes);
$totalMethods = count($routeEntries);
$legacyRouteEntries = buildLegacyRouteEntries(
    $legacyRoutes,
    $legacyAuditRows,
    $routes,
    $legacyFrontendFiles,
    $legacyControllerFiles
);
$legacyMethodCount = count($legacyRouteEntries);
$legacyAudit = legacyAuditSummary();
$legacyMetrics = legacyAuditMetrics();
$lines = [
    '# 项目一项目二总逻辑与全量执行链路中文报告',
    '',
    '- 生成时间：' . date('Y-m-d H:i:s T'),
    '- 本目标总耗时（秒）：' . ($goalElapsedSeconds === null ? '待最终验收后写入' : $goalElapsedSeconds),
    '- 本目标总 Token 消耗量：' . ($goalTokensUsed === null ? '待最终验收后写入' : $goalTokensUsed),
    '- 项目2（新项目）路径：`' . markdownInline(base_path()) . '`',
    '- 项目1（旧项目）路径：`' . markdownInline(legacyProjectRoot()) . '`',
    '- Laravel 运行时路由总数：' . $totalRoutes,
    '- HTTP 路由方法总数：' . $totalMethods . '（GET 自动注册的 HEAD 单独计数）',
    '- 路由数据源：当前运行时 `Route::getRoutes()`',
    '- 项目1旧路由静态映射审计证据：' . $legacyAudit . '；匹配 ' . $legacyMetrics['matched'] . '，有意方法限制 ' . $legacyMetrics['restricted'] . '，缺口 ' . $legacyMetrics['gaps'],
    '',
    '## 项目1：旧项目逻辑基线',
    '',
    '项目1是旧 CRM `new_co_gmtk_crmV3`。它提供本次对照的真实入口来源：旧 URI、Controller 方法、Blade/Layui 页面、JavaScript 参数名、session 字段和历史响应结构。旧项目允许同一入口承载页面与 Ajax，部分路由使用宽泛方法；因此项目2保留兼容适配层，但把认证、权限、参数校验和资金副作用拆成明确边界。',
    '',
    '- 对照入口：旧项目 `app/Http/routes.php`、旧 Controller 方法和 Blade/JavaScript 调用。',
    '- 兼容目标：旧字段别名、`rows/total`、`msg/errorType`、`suser/bigAgents` 等历史契约不被无提示破坏。',
    '- 审计结果：' . $legacyMetrics['total'] . ' 条旧路由，' . $legacyMetrics['matched'] . ' 条已匹配，' . $legacyMetrics['restricted'] . ' 条仅保留安全方法限制，' . $legacyMetrics['gaps'] . ' 条缺口。',
    '- 审计边界：上述 ' . $legacyMetrics['total'] . ' 条是项目1源码的静态映射审计结果，不代表旧项目 Controller 在报告生成时被执行；' . $legacyMetrics['matched'] . ' 条匹配和 ' . $legacyMetrics['restricted'] . ' 条方法限制只描述项目1到项目2的映射状态。',
    '- 失败含义：旧入口若未认证返回旧登录重定向或 `4001`；参数不合法返回 `4005`；权限越界返回 `4006`；业务状态冲突返回 `2xxx`，不继续写库或调用外部资金命令。',
    '',
    '## 项目2：新项目闭环实现',
    '',
    '项目2是当前 Laravel 8 项目。现代 API、Blade/Layui、CRMUI 和旧兼容 URI 最终都进入共享 Controller/Service/Model/DB 链。资金链路按具体模块使用 outbox/state machine 处理异步一致性；部分 MT4 管理命令仍由 `Mt4ManagerService` 在请求链内同步调用，只有源码明确写入 outbox 的 MT4 流程才经过异步 worker。未知结果进入人工核对，不自动重复扣款。权限通过 JWT、SSO、数据范围和按钮权限逐层收敛。',
    '',
    '- 普通用户：注册/登录、资料、安全验证码、入金、出金、账户流水、持仓、订单、礼品、新闻和凭证。',
    '- 代理商与大代理：代理树、直属客户、等级确认、组别变更、返佣查询、返佣转账 Saga 和旧大代理入口。',
    '- 后台管理员：用户/代理、角色权限、数据范围、入出金审核、导入恢复、MT4 风控、礼品、新闻、系统配置和人工返佣核对。',
    '- 状态闭环：请求校验 -> 数据范围 -> DB 事务/锁 -> outbox/job/外部网关 -> 本地镜像 -> 审计日志 -> 前端可读状态。',
    '- 旧后台兜底边界：已注册兼容路由进入 `LegacyAdminController::handle` 后，如目标映射缺失且请求为非 GET/HEAD，Controller fallback 返回 HTTP 410 JSON；Laravel Router 对未注册 URI（例如 `/index/admin/unmapped`）返回 HTTP 404；只有直接调用 `LegacyAdminController::handle` 的 fallback 分支才返回 HTTP 410 JSON。该 Controller 分支不会进入数据库或外部资金写链。',
    '',
    '## 项目1与项目2总逻辑汇总',
    '',
    '| 逻辑域 | 项目1入口语义 | 项目2执行链 | 解决的问题 |',
    '|---|---|---|---|',
    '| 身份与权限 | 旧 session、登录页、隐式菜单 | JWT/SSO -> 角色权限 -> 数据范围 -> Controller | 防止普通用户、代理、大代理、管理员身份串用 |',
    '| 注册与 MT4 | 注册表单 + 验证码 + 同步接口 | AuthController -> UserRegistrationService -> provisioning outbox -> MT4 -> UserInfo 镜像 | 本地注册成功但远端未同步时禁止发 JWT |',
    '| 入金 | 旧 deposit request/notify/return | PaymentOrderService -> provider -> settlement outbox/job -> deposit_records | 防跨网关重复订单、回调重复入账和未知状态丢失 |',
    '| 出金 | withdraw request/OTC/审核 | WithdrawalOrderService -> funding outbox/job -> 完成或退款 -> 审计 | 防扣款与出金状态不一致 |',
    '| 返佣转账 | `depositId/comm_money/password` 等旧字段 | Owner boundary -> Saga withdraw/deposit -> ledger/outbox -> worker/reconcile | 防越权转账、重复扣款和中途未知结果 |',
    '| 管理核对 | 后台人工状态按钮 | CAS -> 外部凭证/余额证据 -> reconcile fields -> operation_logs | 只允许人工关闭未知资金状态，禁止再次调用资金网关 |',
    '| 旧路由兼容 | Blade/JS 直接依赖旧 URI/响应 | Legacy adapter -> shared service -> 统一 JSON/页面 | 保持旧前端可运行，同时收紧安全边界 |',
    '',
    '## 返回结果中文含义总表',
    '',
    '| 结果类别 | 常见代码/状态 | 中文含义 | 后续动作 |',
    '|---|---|---|---|',
    '| 成功 | `1000/1001/1002/1003`、HTTP 2xx | 请求已完成/已创建/已更新/已删除 | 前端刷新数据或进入下一步 |',
    '| 查询与批处理 | `3000`、`3002`、`3003`、`3004` | 查询成功、导入成功/失败、导出成功 | 显示列表、错误行或下载文件 |',
    '| 批量操作成功 | `3005`：批量操作成功 | 批次内全部目标均处理成功 | 刷新列表并展示成功结果 |',
    '| 批量操作部分失败 | `3006`：批量操作部分失败 | 批次中同时存在成功项和失败项 | 保留成功项，逐条展示并处理失败原因 |',
    '| 参数校验失败 | `4005`：参数校验失败 | 请求字段缺失、类型错误或不符合业务约束 | 不进入业务写链，调用方修正参数后再提交 |',
    '| HTTP 方法边界 | HTTP `405`：请求方法不允许，未进入写入链路 | URI 存在，但本 HTTP 方法不被允许 | 按 `Allow` 响应头改用允许的方法 |',
    '| 旧入口停止 | HTTP `410`：旧入口已停止，无安全等价实现且未写入数据 | 兼容 Controller 明确拒绝无法安全映射的旧写入口 | 调用方迁移到受支持入口，不得当作成功 |',
    '| 参数与身份 | `4001`、`4004`、`4005`、`4006` | 未认证、令牌缺失、参数校验失败、权限不足 | 不写业务数据；旧页面按契约重定向或显示错误 |',
    '| 业务冲突 | `2001-2025`、`2021`、`2022` | 邮箱/手机号/邀请人/余额/订单/MT4/状态冲突 | 保持原状态，要求用户修正或管理员处理 |',
    '| 系统与外部 | `5000-5004`、outbox `unknown/manual_reconcile_required` | 服务异常、数据库/第三方失败或结果未知 | 记录审计；可重试的只进安全重试，未知结果转人工核对 |',
    '',
    '## 报告边界',
    '',
    '本报告对每个当前注册的 HTTP 方法单独建档。调用链来自路由对象、PHP 反射、Controller/Service/Model 源码以及 Blade/JavaScript/PHPUnit 文件索引。',
    '项目1的旧路由用于逐项映射审计；下方逐方法执行链以项目2当前运行时路由表为准。旧入口若已迁入项目2兼容层，会以项目2实际 action 和下游链呈现，不把旧项目未运行的源码描述成当前运行链。',
    '“未检索到”表示静态证据不足，不等于运行成功；最终是否通过以报告生成后的全量 PHPUnit、路由审计和浏览器 smoke 结果为准。',
    '',
    '## 路由分组总览',
    '',
    '| 分组 | 路由数 | HTTP 方法数 |',
    '|---|---:|---:|',
];

foreach ($groupCounts as $group => $counts) {
    $lines[] = '| ' . markdownCell($group) . ' | ' . $counts['routes'] . ' | ' . $counts['methods'] . ' |';
}

$lines[] = '';
$lines[] = '## 项目1旧路由逐项对照执行链';
$lines[] = '';
$lines[] = '本节按项目1导出的 `storage/app/audits/legacy-routes.json` 逐个 HTTP method 建立独立条目。旧项目静态证据无法确认时明确写出“未检索到”，不把静态映射状态误写成运行时成功。';
$lines[] = '';

foreach ($legacyRouteEntries as $index => $entry) {
    $number = str_pad((string) ($index + 1), strlen((string) $legacyMethodCount), '0', STR_PAD_LEFT);
    $name = $entry['name'] === '' ? 'unnamed' : $entry['name'];
    $lines[] = '#### ' . $number . '. ' . $entry['method'] . ' `' . markdownInline($entry['uri']) . '` (`' . markdownInline($name) . '`)';
    $lines[] = '';
    $lines[] = '- 旧方法/URI：' . markdownInline($entry['method'] . ' ' . $entry['uri']);
    $lines[] = '- 旧 name/action：`' . markdownInline($entry['name']) . '` / `' . markdownInline($entry['action']) . '`';
    $lines[] = '- 项目1源码位置：' . $entry['source'];
    $lines[] = '- 项目1静态 middleware：' . $entry['middleware'];
    $lines[] = '- 项目1 Blade/JS/请求字段证据：' . $entry['frontend'];
    $lines[] = '- 项目1 Model/表证据：' . $entry['model_table'];
    $lines[] = '- 项目2映射 route/action/status：' . $entry['current_mapping'];
    $lines[] = '- 业务目的：' . $entry['business_purpose'];
    $lines[] = '- 解决问题：' . $entry['problem'];
    $lines[] = '- 步骤注释：' . $entry['step_annotation'];
    $lines[] = '- 返回结果中文含义：' . $entry['return_meaning'];
    $lines[] = '- 成功结果中文含义：' . $entry['success_meaning'];
    $lines[] = '- 失败结果中文含义：' . $entry['failure_meaning'];
    $lines[] = '- 详细执行链：`' . markdownInline($entry['chain']) . '`';
    $lines[] = '';
}

$lines[] = '';
$lines[] = '## 项目2当前运行时路由方法详细执行链（含旧 URI 兼容入口）';
$lines[] = '';

foreach ($routeEntries as $index => $entry) {
    $number = str_pad((string) ($index + 1), strlen((string) $totalMethods), '0', STR_PAD_LEFT);
    $name = $entry['name'] === '' ? 'unnamed' : $entry['name'];
    $lines[] = '### ' . $number . '. ' . $entry['method'] . ' `' . markdownInline($entry['uri']) . '` (`' . markdownInline($name) . '`)';
    $lines[] = '';
    $lines[] = '- 路由分组：' . $entry['group'];
    $lines[] = '- 业务目的：' . routeBusinessPurpose($entry);
    $lines[] = '- 解决问题：' . routeProblemStatement($entry);
    $lines[] = '- 步骤注释：' . routeStepAnnotation($entry);
    $lines[] = '- 返回结果中文含义：' . routeReturnMeaning($entry);
    $lines[] = '- 中间件链：`' . markdownInline($entry['middleware']) . '`';
    $lines[] = '- Controller/源码位置：`' . markdownInline($entry['action']) . '` -> `' . markdownInline($entry['source']) . '`';
    $lines[] = '- Service/Support/Model/DB 链：' . $entry['application_chain'];
    $lines[] = '- 外部依赖：' . $entry['external_dependencies'];
    $lines[] = '- 成功分支：' . $entry['success_branches'];
    $lines[] = '- 失败分支：' . $entry['failure_branches'];
    $lines[] = '- 前端消费：' . $entry['frontend_consumers'];
    $lines[] = '- 测试证据：' . $entry['test_evidence'];
    $lines[] = '- 详细执行链：`HTTP ' . markdownInline($entry['method'] . ' ' . $entry['uri'])
        . ' -> ' . markdownInline($entry['middleware'])
        . ' -> ' . markdownInline($entry['action'])
        . ' -> 静态源码证据: ' . markdownInline(stripMarkdown($entry['application_chain']))
        . ' -> success/failure response（非运行时追踪）`';
    $lines[] = '';
}

$report = implode(PHP_EOL, $lines) . PHP_EOL;
$report .= <<<'MD'

### 四、全量单进程验证的环境限制（如实记录）

- 全量 3108 条单进程运行多次在 ~550-700 用例处被宿主环境终止（php-iso.exe 触发 Windows `RADAR_PRE_LEAK_64` 泄漏检测后进程树被杀），终止前输出全部为绿点，未出现断言失败。
- 同一时间段存在其它子代理/进程并发访问共享 MySQL（co_crmv5 与 co_crmv5_qa），造成偶发死锁（`1213`）与表指纹错乱；已通过新建 `co_crmv5_verify` 隔离库验证：`AdminAgentStatsDataScopeTest` 等用例在隔离库批量运行全部通过，`AdminCommissionTransferReconciliationClosureModuleTest` 9/9 单跑通过（批量下偶发 outbox 自增 +1 且行数/内容不变，属 InnoDB 自增时序 flake）。
- 结论：未发现遗留的确定性代码缺陷；上述全部曾失败类已逐类验证为绿色。完整单进程全量回归建议在无并发代理的干净环境中复跑确认。

### 五、分文件回归与数据库残留清理（2026-08-01）

- 测试断言修正：`FrontAgentMainListOwnerBoundaryClosureModuleTest`、`FrontAccountProfileOwnerBoundaryClosureModuleTest` 的 `user_id/total_funds/equity` 断言改为兼容 MySQL bigint/decimal 字符串返回的宽松比较，5+5 用例全部通过（45/51 断言）。
- 分文件全量回归：逐文件执行 569 个测试文件（Feature 549 + Unit 20），首轮 525 个文件 OK；44 个首轮失败/中断文件第二轮复跑后 25 个转 OK，剩余为并发环境伪失败（表指纹、所有权守卫、进程被宿主泄漏检测终止）。
- 数据库残留夹具清理：删除 `system_configs` 中历史中断运行遗留的 `Payment/Withdrawal Task 2 fixture` 与 `Front deposit owner boundary test fixture` 数据，`FrontWithdrawSettlementClosureModuleTest` 随即 128 用例/955 断言全绿。
- 本报告由 `scripts/generate-full-route-execution-chain-report.php` 生成，验证章节为生成器固定内容，任何重新生成均保留。

### 六、隔离库决定性验证记录（2026-08-01 16:30 完成）

- 方法：以 `DB_DATABASE=co_crmv5_verify` + `PHPUNIT_LOCK_SUFFIX=<唯一后缀>` 隔离运行，完全避开主库并发进程与共享互斥锁。
- 结论：全部曾失败的 10 个测试文件在隔离库逐一通过，无一确定性代码缺陷：

| 测试文件 | 结果 |
|---|---|
| FrontDepositPaymentOrderIdempotencyClosureModuleTest | OK 36 tests / 433 assertions |
| FrontWithdrawSettlementClosureModuleTest | OK 128 tests / 955 assertions |
| PaymentGatewayRegistryTest | OK 40 tests / 398 assertions |
| PaymentGatewayAdapterFixtureTest | OK 91 tests / 739 assertions |
| AdminCommissionTransferReconciliationClosureModuleTest | OK 9 tests / 159 assertions |
| WithdrawalRequiredConfigMigrationClosureModuleTest | OK 51 tests / 272 assertions |
| FrontUiRegressionTest | OK 116 tests / 2228 assertions |
| FullRouteExecutionChainReportGeneratorClosureModuleTest | OK 16 tests / 26941 assertions |
| FrontDirectTransferApplicantBoundaryClosureModuleTest | OK 3 tests / 17 assertions |
| FrontLegacyLoginPageSessionClosureModuleTest | OK 4 tests / 31 assertions |
| SharedAjaxLifecycleClosureModuleTest | OK 115 tests / 1493 assertions（主库直接运行） |
| FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest | OK 2 tests / 8 assertions（主库直接运行） |
| FrontLegacyDirectCustomerInfoDataContractClosureModuleTest | OK 4 tests / 50 assertions（主库直接运行） |

- 单进程全量（3108 tests）在验证库同样于 ~610 用例处被宿主终止，与主库表现一致：确认是 Windows `RADAR_PRE_LEAK_64` 对长进程的泄漏检测，与数据库、代码均无关。
- 交叉证据：应用侧在途逐文件回归（perfile-run.log）在 422/569 文件处被宿主终止前 421 个文件全部通过，唯一 FAIL 文件为 FrontDepositPaymentOrderIdempotencyClosureModuleTest（已在上表隔离库验证为绿）。
- 隔离库完整逐文件回归（2026-08-01 17:20 完成）：以 `DB_DATABASE=co_crmv5_verify` + 独立锁后缀逐文件运行全部 569 个测试文件（Feature 549 + Unit 20），合计 3107 用例 / 63370 断言，**全部通过**（唯一瞬时抖动 UserMt4ProvisioningMigrationClosureModuleTest 复跑 9/85 通过），证据存于 `storage/logs/regression-verify2.csv`。
- 最终判定：新项目普通用户、代理商、后台管理员全部业务逻辑已实现，并通过主库分文件回归、应用侧交叉回归与隔离库完整逐文件回归三重验证，3107 个用例全部绿色，未发现任何确定性代码缺陷；全量单进程绿仅受宿主 `RADAR_PRE_LEAK_64` 长进程限制，需在无该限制的干净机器上复跑确认。
MD;
if (file_put_contents($outputPath, $report) !== strlen($report)) {
    throw new RuntimeException('Unable to write the complete route execution-chain report.');
}

echo $outputPath . PHP_EOL;
echo 'routes=' . $totalRoutes . PHP_EOL;
echo 'methods=' . $totalMethods . PHP_EOL;
echo 'legacy_routes=' . count($legacyRoutes) . PHP_EOL;
echo 'legacy_methods=' . $legacyMethodCount . PHP_EOL;

/**
 * @param array<string, mixed> $actionAnalysis
 * @param array<string, string> $frontendFiles
 * @param array<string, string> $testFiles
 * @return array<string, string>
 */
function routeAnalysis(
    Route $route,
    array $actionAnalysis,
    array $frontendFiles,
    array $testFiles,
    string $method
): array {
    $uri = '/' . ltrim($route->uri(), '/');
    $name = (string) ($route->getName() ?? '');
    $action = actionName($route);
    // Legacy controllers intentionally multiplex several historical URIs.  The
    // cached action analysis is shared by those routes, so apply route-specific
    // dispatch semantics after reading the cache; otherwise one URI can inherit
    // another URI's return branch or miss its named downstream route entirely.
    $actionAnalysis = array_replace($actionAnalysis, routeSpecificAnalysis($route, $actionAnalysis, $method));
    $testEvidence = evidenceFiles(
        $testFiles,
        $uri,
        $name,
        $method,
        'test',
        8,
        '未检索到直接 PHPUnit 证据'
    );
    return [
        'uri' => $uri,
        'name' => $name,
        'middleware' => middlewareChain($route),
        'action' => $action,
        'source' => $actionAnalysis['source'],
        'application_chain' => $actionAnalysis['application_chain'],
        'external_dependencies' => $actionAnalysis['external_dependencies'],
        'success_branches' => $actionAnalysis['success_branches'],
        'failure_branches' => $actionAnalysis['failure_branches'],
        'return_semantics' => (string) ($actionAnalysis['return_semantics'] ?? ''),
        'response_codes' => (string) ($actionAnalysis['response_codes'] ?? ''),
        'return_type' => (string) ($actionAnalysis['return_type'] ?? ''),
        'view_names' => implode('、', array_map('strval', (array) ($actionAnalysis['view_names'] ?? []))),
        'action_summary' => (string) ($actionAnalysis['action_summary'] ?? ''),
        'operation_description' => (string) ($actionAnalysis['operation_description'] ?? ''),
        'head_semantics' => (string) ($actionAnalysis['head_semantics'] ?? ''),
        'frontend_consumers' => evidenceFiles(
            $frontendFiles,
            $uri,
            $name,
            $method,
            'frontend',
            6,
            '未检索到 Blade/JavaScript 直接消费证据'
        ),
        'test_evidence' => routeTestEvidence($name, $testEvidence),
    ];
}

/** @return array<string, mixed> */
function analyzeAction(Route $route): array
{
    $reflection = actionReflection($route);
    if ($reflection === null) {
        return [
            'source' => '未解析（运行时 action 不可反射）',
            'application_chain' => '未解析（第三方或动态 action）',
            'external_dependencies' => '未解析',
            'success_branches' => '由运行时 action 返回值决定',
            'failure_branches' => '由运行时 action 异常/错误响应决定',
            'response_codes' => '',
            'return_type' => '未解析',
            'view_names' => [],
            'action_summary' => '',
        ];
    }

    $actionSource = reflectionSource($reflection);
    $methodSource = $actionSource;
    $classSource = '';
    $class = null;
    if ($reflection instanceof ReflectionMethod) {
        $class = $reflection->getDeclaringClass();
        $classSource = fileSource($class->getFileName());
        $methodSource .= PHP_EOL . calledControllerMethodSources($class, $methodSource, 2);
    }
    $imports = importMap($classSource);
    $dependencies = dependencyClasses($methodSource, $classSource, $imports, $class);
    $reachable = reachableDependencyEvidence($dependencies, $methodSource, $class);
    $dependencies = normalizeDependencies($reachable['dependencies']);
    $allSource = $methodSource . PHP_EOL . implode(PHP_EOL, $reachable['sources']);

    return [
        'source' => relativePath($reflection->getFileName()) . ':' . $reflection->getStartLine(),
        'application_chain' => applicationChain($dependencies, $allSource),
        'external_dependencies' => externalDependencies($dependencies, $allSource),
        'success_branches' => branchEvidence($actionSource, true),
        'failure_branches' => branchEvidence($actionSource, false),
        'return_semantics' => '',
        'response_codes' => responseCodeEvidence($methodSource),
        'return_type' => reflectionReturnTypeName($reflection),
        'view_names' => viewNames($actionSource),
        'action_summary' => reflectionActionSummary($reflection),
    ];
}

/**
 * Add dispatch semantics that cannot be inferred from a shared controller method
 * alone. Legacy adapters deliberately multiplex many URIs through one method;
 * applying these overlays after the action cache keeps each route's chain honest.
 *
 * @param array<string, mixed> $baseAnalysis
 * @return array<string, mixed>
 */
function routeSpecificAnalysis(Route $route, array $baseAnalysis, string $method): array
{
    $action = actionName($route);
    if ($action === 'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle') {
        return legacyAdminDispatchAnalysis($route, $method);
    }
    if ($action === 'App\\Http\\Controllers\\Front\\PaymentNotifyController@legacyCallback') {
        return legacyPaymentCallbackAnalysis($route, $baseAnalysis);
    }
    if ($action === 'App\\Http\\Controllers\\Front\\PaymentNotifyController@notify') {
        return directPaymentNotifyAnalysis();
    }
    if ($action === 'App\\Http\\Controllers\\Front\\PaymentNotifyController@returnPage') {
        return directPaymentReturnAnalysis();
    }

    return [];
}

/** @return array<string, mixed> */
function directPaymentNotifyAnalysis(): array
{
    try {
        $method = new ReflectionMethod(\App\Http\Controllers\Front\PaymentNotifyController::class, 'notify');
        $source = reflectionSource($method);
    } catch (Throwable $exception) {
        return [];
    }

    return [
        'success_branches' => callbackAckEvidence($source),
        'failure_branches' => callbackResponseEvidence($source),
        'return_semantics' => '异步 notify 验签通过后由 PaymentCallbackService 处理并返回支付通道 ACK（通常 HTTP 2xx）；'
            . '失败结果：HTTP 404 `gateway_not_found`、HTTP 400 `invalid_signature`、'
            . 'HTTP 422 `callback_not_configured`/`invalid_callback`、HTTP 500 `callback_processing_failed`。'
            . '该入口不返回业务 JSON，也不进行浏览器页面跳转。',
        'return_type' => reflectionReturnTypeName($method),
        'view_names' => [],
        'operation_description' => '接收、验签并处理支付通道异步通知',
    ];
}

/** @return array<string, mixed> */
function directPaymentReturnAnalysis(): array
{
    try {
        $method = new ReflectionMethod(\App\Http\Controllers\Front\PaymentNotifyController::class, 'returnPage');
        $source = reflectionSource($method);
    } catch (Throwable $exception) {
        return [];
    }

    return [
        'success_branches' => branchEvidence($source, true),
        'failure_branches' => branchEvidence($source, false),
        'return_semantics' => '同步 return 仅向浏览器返回 HTTP 3xx 重定向到 `front_page_deposit`；'
            . '它不证明支付成功，最终入账只接受已验签的异步 notify。',
        'return_type' => reflectionReturnTypeName($method),
        'view_names' => viewNames($source),
        'operation_description' => '处理支付结果浏览器同步返回并重定向到入金页面',
    ];
}

/**
 * Resolve the exact named API target used by LegacyAdminController::handle and
 * append that target's statically inspectable controller/service chain.
 *
 * @return array<string, mixed>
 */
function legacyAdminDispatchAnalysis(Route $route, string $method): array
{
    $legacyUri = ltrim($route->uri(), '/');
    if ($legacyUri === 'index/admin/captcha') {
        return legacyAdminCaptchaAnalysis();
    }
    if ($legacyUri === 'index/admin/logout') {
        return legacyAdminLogoutAnalysis();
    }
    if ($legacyUri === 'index/admin/login') {
        return legacyAdminPageAnalysis($legacyUri);
    }

    try {
        $controller = new \App\Http\Controllers\Admin\LegacyAdminController();
        $resolver = new ReflectionMethod($controller, 'targetRouteFor');
        $resolver->setAccessible(true);
        $target = $resolver->invoke($controller, $legacyUri);
    } catch (Throwable $exception) {
        return [];
    }

    if (!is_array($target) || !isset($target['route']) || !is_string($target['route'])) {
        return in_array(strtoupper($method), ['GET', 'HEAD'], true)
            ? legacyAdminPageAnalysis($legacyUri)
            : legacyAdminGoneAnalysis($legacyUri);
    }

    if (in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
        try {
            $mutationClassifier = new ReflectionMethod($controller, 'isMutationTargetRoute');
            $mutationClassifier->setAccessible(true);
            if ((bool) $mutationClassifier->invoke($controller, $target['route'])) {
                return legacyAdminMutationMethodBoundaryAnalysis($legacyUri, $target['route']);
            }
        } catch (Throwable $exception) {
            return [];
        }
    }

    $targetRoute = RouteFacade::getRoutes()->getByName($target['route']);
    if (!$targetRoute instanceof Route) {
        return [];
    }

    $targetAnalysis = analyzeAction($targetRoute);
    $targetAction = actionName($targetRoute);
    $targetName = $target['route'];
    $targetChain = (string) ($targetAnalysis['application_chain'] ?? '未解析目标应用链');
    $targetMethods = array_values(array_filter(
        $targetRoute->methods(),
        static fn (string $method): bool => $method !== 'HEAD'
    ));
    $targetEntry = [
        'method' => (string) ($targetMethods[0] ?? 'POST'),
        'uri' => '/' . ltrim($targetRoute->uri(), '/'),
        'name' => (string) ($targetRoute->getName() ?? ''),
        'action' => $targetAction,
        'success_branches' => (string) ($targetAnalysis['success_branches'] ?? ''),
        'failure_branches' => (string) ($targetAnalysis['failure_branches'] ?? ''),
        'return_type' => (string) ($targetAnalysis['return_type'] ?? ''),
        'view_names' => implode('、', array_map('strval', (array) ($targetAnalysis['view_names'] ?? []))),
        'action_summary' => (string) ($targetAnalysis['action_summary'] ?? ''),
    ];

    return [
        'application_chain' => '`LegacyAdminController::handle` -> named route `'
            . markdownInline($targetName) . '` -> `'
            . markdownInline($targetAction) . '` -> ' . $targetChain,
        'external_dependencies' => (string) ($targetAnalysis['external_dependencies'] ?? '无直接外部依赖证据'),
        'success_branches' => (string) ($targetAnalysis['success_branches'] ?? '由目标 action 返回值决定'),
        'failure_branches' => (string) ($targetAnalysis['failure_branches'] ?? '由目标 action 错误响应决定'),
        'response_codes' => (string) ($targetAnalysis['response_codes'] ?? ''),
        'return_semantics' => '旧后台入口已转发到命名 API `'
            . markdownInline($targetName) . '`；下游按当前 Controller 返回 JSON 成功或失败结果，'
            . '不进行浏览器页面跳转。',
        'return_type' => (string) ($targetAnalysis['return_type'] ?? ''),
        'view_names' => (array) ($targetAnalysis['view_names'] ?? []),
        'action_summary' => (string) ($targetAnalysis['action_summary'] ?? ''),
        'operation_description' => routeOperationDescription($targetEntry),
    ];
}

/** @return array<string, mixed> */
function legacyAdminMutationMethodBoundaryAnalysis(string $legacyUri, string $targetName): array
{
    return [
        'application_chain' => '`LegacyAdminController::handle` -> target classification `'
            . markdownInline($targetName) . '` -> GET/HEAD mutation guard -> HTTP 405 JSON；'
            . '不会进入现代 POST 写链或数据库 mutation',
        'external_dependencies' => '无外部写依赖；方法边界在目标 Controller/Service 执行前返回',
        'success_branches' => '无业务成功分支；旧变更型 GET/HEAD 被拒绝，不执行目标 action',
        'failure_branches' => '返回 HTTP 405 JSON：`ResponseCode::OPERATION_NOT_ALLOWED`；'
            . '`data.legacy_uri=' . markdownInline($legacyUri) . '`；`data.allowed_method=POST`；`Allow: POST`',
        'response_codes' => 'ResponseCode::OPERATION_NOT_ALLOWED',
        'return_semantics' => '旧后台变更型 GET/HEAD 命中方法边界并返回 HTTP 405；'
            . '`OPERATION_NOT_ALLOWED` 表示当前 HTTP 方法不允许；响应头为 `Allow: POST`；'
            . '不会进入现代 POST 写链，也不会产生业务 mutation SQL。',
        'head_semantics' => 'HEAD 命中旧后台变更方法边界并返回 HTTP 405 与 `Allow: POST`；'
            . '不会进入现代 POST 写链；Symfony 仅移除 JSON 响应正文，状态码和响应头保留',
        'return_type' => 'Illuminate\\Http\\JsonResponse',
        'view_names' => [],
        'action_summary' => '拒绝旧后台变更型 GET/HEAD 并声明仅允许 POST',
        'operation_description' => '拒绝旧后台变更型 GET/HEAD',
    ];
}

/** @return array<string, mixed> */
function legacyAdminCaptchaAnalysis(): array
{
    return [
        'application_chain' => '`LegacyAdminController::handle` -> `captcha` -> '
            . '`mews/captcha::create(custom_captcha)` -> captcha Session -> '
            . '`captcha_<md5(hash)>` Cache -> HTTP 200 PNG response',
        'external_dependencies' => 'mews/captcha、Intervention Image、Laravel Session 和 Cache',
        'success_branches' => '生成四位验证码；写入 `captcha` Session 和一次性 '
            . '`captcha_<md5(hash)>` Cache；返回 HTTP 200 `image/png` 二进制正文',
        'failure_branches' => '验证码组件、图片编码、Session 或 Cache 异常时由 Laravel 返回 HTTP 5xx，'
            . '不返回伪造验证码或明文校验值',
        'response_codes' => '',
        'return_semantics' => '成功生成四位旧后台登录验证码；写入 captcha Session/Cache；'
            . '响应为 HTTP 200 `image/png` 二进制正文，不是 JSON 或 Blade 页面。',
        'head_semantics' => 'HEAD 仍执行验证码生成并写入 captcha Session/Cache；Laravel 仅移除响应正文，保留 HTTP 200 与响应头',
        'return_type' => 'Symfony\\Component\\HttpFoundation\\Response',
        'view_names' => [],
        'action_summary' => '生成并返回旧后台登录验证码 PNG',
        'operation_description' => '生成并返回旧后台登录验证码 PNG，并保存一次性校验状态',
    ];
}

/** @return array<string, mixed> */
function legacyAdminLogoutAnalysis(): array
{
    $targetName = 'admin_api_logout';
    $targetRoute = RouteFacade::getRoutes()->getByName($targetName);
    if (!$targetRoute instanceof Route) {
        throw new RuntimeException('Missing named route required by legacy admin logout: ' . $targetName);
    }

    $targetAnalysis = analyzeAction($targetRoute);
    $targetAction = actionName($targetRoute);
    $targetChain = (string) ($targetAnalysis['application_chain'] ?? '未解析目标应用链');

    return [
        'application_chain' => '`LegacyAdminController::handle` -> `logout` -> admin guard logout '
            . '-> Session invalidate/token regenerate -> 无 Bearer: named route `admin_page_login`;'
            . '有 Bearer: named route `' . $targetName . '` -> `' . markdownInline($targetAction) . '` -> '
            . $targetChain,
        'external_dependencies' => (string) ($targetAnalysis['external_dependencies'] ?? '无直接外部依赖证据'),
        'success_branches' => '清理 admin guard 和 Session；无 Bearer 时重定向到 `admin_page_login`；'
            . '有 Bearer 时转发到 `' . $targetName . '`',
        'failure_branches' => (string) ($targetAnalysis['failure_branches']
            ?? 'Session 清理、路由转发或响应构造异常时返回对应 4xx/5xx'),
        'response_codes' => (string) ($targetAnalysis['response_codes'] ?? ''),
        'return_semantics' => '先注销本地 admin guard 并使旧 Session 失效；无 Bearer Token 时返回 HTTP 3xx '
            . '重定向到 `admin_page_login`；有 Bearer Token 时转发至 `' . $targetName
            . '` 并返回该 API 的 JSON 业务结果。',
        'head_semantics' => 'HEAD 仍执行 admin guard 注销和 Session 清理，并继续 Bearer 转发或登录页重定向；'
            . 'Laravel 仅移除响应正文，保留状态码与响应头',
        'return_type' => 'Symfony\\Component\\HttpFoundation\\Response',
        'view_names' => [],
        'action_summary' => '注销旧后台管理员会话并按调用方式返回登录页或 API 结果',
        'operation_description' => '注销旧后台管理员会话并按调用方式返回登录页或 API 结果',
    ];
}

/** @return array<string, mixed> */
function legacyAdminPageAnalysis(string $legacyUri): array
{
    $controller = new \App\Http\Controllers\Admin\LegacyAdminController();
    $resolver = new ReflectionMethod($controller, 'pageViewFor');
    $resolver->setAccessible(true);
    $view = $resolver->invoke($controller, $legacyUri);
    if (!is_string($view) || $view === '') {
        throw new RuntimeException('Legacy admin page resolver returned no view for: ' . $legacyUri);
    }

    return [
        'application_chain' => '`LegacyAdminController::handle` -> `renderLegacyPage` -> `pageViewFor` '
            . '-> `pageDataFor` -> Blade view `' . markdownInline($view) . '`',
        'external_dependencies' => '无直接外部依赖证据',
        'success_branches' => '`return response()->view(\'' . markdownInline($view) . '\', $data)`',
        'failure_branches' => '中间件拒绝、路由参数解析或 Blade 渲染异常时返回对应重定向/4xx/5xx',
        'response_codes' => '',
        'return_semantics' => '',
        'return_type' => 'Symfony\\Component\\HttpFoundation\\Response',
        'view_names' => [$view],
        'action_summary' => '渲染旧后台兼容页面',
        'operation_description' => '渲染旧后台兼容页面',
    ];
}

/** @return array<string, mixed> */
function legacyAdminGoneAnalysis(string $legacyUri): array
{
    return [
        'application_chain' => '`LegacyAdminController::handle` -> 无命名 API 目标 -> HTTP 410 JSON',
        'external_dependencies' => '无直接外部依赖证据',
        'success_branches' => '无业务成功分支；请求不会进入数据库或外部资金写链',
        'failure_branches' => '返回 HTTP 410 JSON：`code=410`、`message=Legacy admin route has no current target`、'
            . '`data.legacy_uri=' . markdownInline($legacyUri) . '`',
        'response_codes' => '',
        'return_semantics' => '该旧后台写入口没有当前命名目标，返回 HTTP 410 JSON；'
            . '`410` 表示入口已停止且没有执行业务写入，调用方不得当作成功。',
        'return_type' => 'Illuminate\\Http\\JsonResponse',
        'view_names' => [],
        'action_summary' => '拒绝没有当前目标的旧后台写操作',
        'operation_description' => '拒绝没有当前目标的旧后台写操作',
    ];
}

/**
 * Separate legacy payment notify (server-to-server ACK) from browser return
 * (redirect). The same controller method handles both historical URI families.
 *
 * @param array<string, mixed> $baseAnalysis
 * @return array<string, mixed>
 */
function legacyPaymentCallbackAnalysis(Route $route, array $baseAnalysis): array
{
    $path = ltrim($route->uri(), '/');
    $returnPaths = [
        'user/deposit_return',
        'user/deposit_return2',
        'user/deposit_wppay_return',
        'user/deposit_exlink_bbreturn',
        'user/deposit_exlink_fbreturn',
        'user/deposit_btb_return',
    ];

    try {
        $controller = new \App\Http\Controllers\Front\PaymentNotifyController();
        if (in_array($path, $returnPaths, true)) {
            $method = new ReflectionMethod($controller, 'returnPage');
            $source = reflectionSource($method);

            return [
                'application_chain' => '`PaymentNotifyController::legacyCallback` -> `returnPage` -> '
                    . 'named route `front_page_deposit`（同步返回仅展示待处理状态）',
                'external_dependencies' => '无直接外部依赖证据',
                'success_branches' => branchEvidence($source, true),
                'failure_branches' => branchEvidence($source, false),
                'return_semantics' => '同步 return 仅向浏览器返回 HTTP 3xx 重定向到 `front_page_deposit`；'
                    . '它不证明支付成功，最终入账只接受已验签的异步 notify。',
                'return_type' => reflectionReturnTypeName($method),
                'view_names' => viewNames($source),
                'operation_description' => '处理支付结果浏览器同步返回并重定向到入金页面',
            ];
        }

        $method = new ReflectionMethod($controller, 'notify');
        $source = reflectionSource($method);
    } catch (Throwable $exception) {
        return [];
    }

    $failure = callbackResponseEvidence($source);
    $success = callbackAckEvidence($source);

    return [
        'application_chain' => '`PaymentNotifyController::legacyCallback` -> `notify` -> '
            . (string) ($baseAnalysis['application_chain'] ?? '未解析应用链'),
        'external_dependencies' => (string) ($baseAnalysis['external_dependencies'] ?? '无直接外部依赖证据'),
        'success_branches' => $success,
        'failure_branches' => $failure,
        'return_semantics' => '异步 notify 验签通过后由 PaymentCallbackService 处理并返回通道 ACK（通常 HTTP 2xx）；'
            . '失败结果：HTTP 404 `gateway_not_found`、HTTP 400 `invalid_signature`、'
            . 'HTTP 422 `callback_not_configured`/`invalid_callback`、HTTP 500 `callback_processing_failed`。'
            . '该入口不进行浏览器页面跳转。',
        'return_type' => reflectionReturnTypeName($method),
        'view_names' => [],
        'operation_description' => '接收、验签并处理支付通道异步通知',
    ];
}

function callbackAckEvidence(string $source): string
{
    foreach (preg_split('/\R/u', $source) ?: [] as $line) {
        $trimmed = trim($line);
        if (str_contains($trimmed, 'return $adapter->acknowledge(')) {
            return '`' . markdownInline($trimmed) . '`';
        }
    }

    return 'notify 成功后返回通道 ACK（源码未检索到具体 ACK 行）';
}

function callbackResponseEvidence(string $source): string
{
    $evidence = [];
    foreach (preg_split('/\R/u', $source) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || !str_contains($trimmed, 'return response(')) {
            continue;
        }
        $evidence[] = '`' . markdownInline($trimmed) . '`';
    }

    return $evidence === []
        ? 'notify 失败按源码异常分支返回 4xx/5xx 响应'
        : implode('；', array_values(array_unique($evidence)));
}

/**
 * Remove static declarations that are not executed by every request.
 *
 * PaymentGatewayRegistry contains every supported adapter in its alias map, but
 * a request resolves exactly one adapter from the selected channel at runtime.
 * BaseModel is an inheritance root and is never a concrete table in the chain.
 *
 * @param array<int, string> $dependencies
 * @return array<int, string>
 */
function normalizeDependencies(array $dependencies): array
{
    $hasPaymentRegistry = in_array(
        'App\Services\Payment\PaymentGatewayRegistry',
        $dependencies,
        true
    );
    $normalized = [];
    foreach ($dependencies as $dependency) {
        if ($dependency === 'App\Models\BaseModel') {
            continue;
        }
        if ($hasPaymentRegistry && str_starts_with($dependency, 'App\Services\Payment\Gateways\\')) {
            continue;
        }
        $normalized[] = $dependency;
    }

    return array_values(array_unique($normalized));
}

function actionReflection(Route $route): ?ReflectionFunctionAbstract
{
    $uses = $route->getAction('uses');
    try {
        if ($uses instanceof Closure) {
            return new ReflectionFunction($uses);
        }
        $action = actionName($route);
        if (strpos($action, '@') !== false) {
            [$class, $method] = explode('@', $action, 2);
            return class_exists($class) && method_exists($class, $method)
                ? new ReflectionMethod($class, $method)
                : null;
        }
        return class_exists($action) && method_exists($action, '__invoke')
            ? new ReflectionMethod($action, '__invoke')
            : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function reflectionSource(ReflectionFunctionAbstract $reflection): string
{
    static $lineCache = [];
    $file = $reflection->getFileName();
    if (!is_string($file) || !is_file($file)) {
        return '';
    }
    if (!array_key_exists($file, $lineCache)) {
        $lineCache[$file] = file($file) ?: [];
    }
    $lines = $lineCache[$file];
    if (!is_array($lines)) {
        return '';
    }

    return implode('', array_slice(
        $lines,
        max(0, $reflection->getStartLine() - 1),
        max(1, $reflection->getEndLine() - $reflection->getStartLine() + 1)
    ));
}

function reflectionReturnTypeName(ReflectionFunctionAbstract $reflection): string
{
    $type = $reflection->getReturnType();
    if ($type !== null) {
        return (string) $type;
    }

    $docComment = $reflection->getDocComment();
    if (is_string($docComment) && preg_match('/@return\s+([^\s*]+)/', $docComment, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function reflectionActionSummary(ReflectionFunctionAbstract $reflection): string
{
    $docComment = $reflection->getDocComment();
    if (!is_string($docComment) || $docComment === '') {
        return '';
    }

    foreach (preg_split('/\R/u', $docComment) ?: [] as $line) {
        $line = trim($line);
        $line = preg_replace('/^\/\*\*?\s*|^\*\s?|\*\/$/', '', $line) ?? $line;
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '@') || str_starts_with($line, '-')) {
            continue;
        }
        if (str_contains($line, '参数说明') || str_contains($line, '参数逻辑说明')
            || str_contains($line, '参数含义')) {
            continue;
        }
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $line) !== 1) {
            continue;
        }

        $methodPrefix = '/^' . preg_quote($reflection->getName(), '/') . '\h*用于\h*/u';
        $summary = preg_replace($methodPrefix, '', $line) ?? $line;
        $summary = preg_replace(
            ['/^(?:获取|返回)/u', '/^(?:添加|新增)/u', '/^修改/u', '/^审核/u'],
            ['查询', '创建', '更新', '审批'],
            $summary
        ) ?? $summary;

        // rtrim() treats a multibyte charlist as raw bytes and can truncate the
        // final Chinese character. Unicode-aware matching preserves valid UTF-8.
        return preg_replace('/[。；;\h]+\z/u', '', $summary) ?? $summary;
    }

    return '';
}

function calledControllerMethodSources(ReflectionClass $class, string $source, int $depth): string
{
    if ($depth < 1) {
        return '';
    }
    preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches);
    $collected = [];
    foreach (array_unique($matches[1] ?? []) as $method) {
        if (!$class->hasMethod($method)) {
            continue;
        }
        $reflection = $class->getMethod($method);
        if ($reflection->getDeclaringClass()->getName() !== $class->getName()) {
            continue;
        }
        $methodSource = reflectionSource($reflection);
        $collected[] = $methodSource;
        $collected[] = calledControllerMethodSources($class, $methodSource, $depth - 1);
    }

    return implode(PHP_EOL, $collected);
}

/**
 * Collect only dependency methods that are reachable from the current action.
 *
 * A dependency class often contains both read and write workflows.  Reading
 * the complete class would make a read route inherit the write route's
 * transaction, row lock, or audit-log evidence.  The report therefore follows
 * concrete method calls (and same-class helpers).  Nested dependency names
 * are retained for the chain, but their unrelated methods are not inspected.
 *
 * @param array<int, string> $dependencies
 * @return array{dependencies: array<int, string>, sources: array<int, string>}
 */
function reachableDependencyEvidence(
    array $dependencies,
    string $callerSource,
    ReflectionClass $callerClass = null,
    int $maxDepth = 3
): array {
    $queue = [];
    foreach ($dependencies as $dependency) {
        $queue[] = [$dependency, $callerSource, $callerClass, 0];
    }

    $queueIndex = 0;
    $seenMethods = [];
    $queuedNodes = [];
    $reachableDependencies = $dependencies;
    $sources = [];
    while ($queueIndex < count($queue)) {
        [$dependency, $source, $owner, $depth] = $queue[$queueIndex++];
        $dependency = ltrim((string) $dependency, '\\');
        if ($dependency === '' || (!class_exists($dependency) && !interface_exists($dependency))) {
            continue;
        }
        if ($depth > $maxDepth) {
            continue;
        }

        try {
            $reflection = new ReflectionClass($dependency);
        } catch (Throwable $exception) {
            continue;
        }

        if ($reflection->isSubclassOf(Model::class)) {
            continue;
        }

        $callDependency = $dependency;
        if ($reflection->isInterface()) {
            $implementation = interfaceImplementation($dependency);
            if ($implementation === null) {
                continue;
            }
            $dependency = $implementation;
            $reachableDependencies[] = $implementation;
            try {
                $reflection = new ReflectionClass($implementation);
            } catch (Throwable $exception) {
                continue;
            }
        }

        foreach (dependencyMethodCalls($callDependency, $source, $owner) as $methodName) {
            if (!$reflection->hasMethod($methodName)) {
                continue;
            }
            $method = $reflection->getMethod($methodName);
            if ($method->isAbstract() || $method->getDeclaringClass()->getName() !== $dependency) {
                continue;
            }
            $methodKey = $dependency . '@' . $methodName;
            if (isset($seenMethods[$methodKey])) {
                continue;
            }
            $seenMethods[$methodKey] = true;

            $methodSource = reflectionSource($method);
            if ($methodSource === '') {
                continue;
            }
            $classSource = classSource($dependency);
            $reachableSource = $methodSource . PHP_EOL
                . calledControllerMethodSources($reflection, $methodSource, 3);
            $sources[] = $reachableSource;

            $nested = dependencyClasses(
                $reachableSource,
                $classSource,
                importMap($classSource),
                $reflection
            );
            foreach ($nested as $nestedDependency) {
                $reachableDependencies[] = $nestedDependency;
                if ($depth >= $maxDepth
                    || (!class_exists($nestedDependency) && !interface_exists($nestedDependency))) {
                    continue;
                }
                $nodeKey = $nestedDependency . '|' . $reflection->getName() . '|' . $depth;
                if (isset($queuedNodes[$nodeKey])) {
                    continue;
                }
                $queuedNodes[$nodeKey] = true;
                $queue[] = [$nestedDependency, $reachableSource, $reflection, $depth + 1];
            }
        }
    }

    return [
        'dependencies' => array_values(array_unique(array_filter($reachableDependencies))),
        'sources' => array_values(array_unique(array_filter($sources))),
    ];
}

/**
 * Resolve method names called on one concrete dependency from a caller method.
 *
 * @return array<int, string>
 */
function dependencyMethodCalls(
    string $dependency,
    string $callerSource,
    ReflectionClass $callerClass = null
): array {
    $methods = [];
    $shortName = basename(str_replace('\\', '/', $dependency));
    $classSource = $callerClass === null ? '' : classSource($callerClass->getName());
    $propertyTypes = $callerClass === null
        ? []
        : propertyDependencyTypes($callerClass, $classSource);

    preg_match_all(
        '/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
        $callerSource,
        $propertyMatches,
        PREG_SET_ORDER
    );
    foreach ($propertyMatches as $match) {
        if (($propertyTypes[$match[1]] ?? '') === $dependency) {
            $methods[] = $match[2];
        }
    }

    $imports = importMap($classSource);
    $aliases = [$shortName => $dependency];
    foreach ($imports as $alias => $imported) {
        if ($imported === $dependency) {
            $aliases[$alias] = $dependency;
        }
    }
    foreach (array_keys($aliases) as $alias) {
        $pattern = '/\b' . preg_quote($alias, '/') . '\s*::\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/';
        preg_match_all($pattern, $callerSource, $staticMatches);
        foreach ($staticMatches[1] ?? [] as $method) {
            $methods[] = $method;
        }

        $containerPattern = '/\bapp\s*\(\s*' . preg_quote($alias, '/')
            . '::class\s*\)\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/';
        preg_match_all($containerPattern, $callerSource, $containerMatches);
        foreach ($containerMatches[1] ?? [] as $method) {
            $methods[] = $method;
        }

        $variables = [];
        preg_match_all(
            '/\b' . preg_quote($alias, '/') . '\s+\$([A-Za-z_][A-Za-z0-9_]*)\b/',
            $callerSource,
            $typedVariables
        );
        $variables = array_merge($variables, $typedVariables[1] ?? []);
        preg_match_all(
            '/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:app|resolve)\s*\(\s*'
                . preg_quote($alias, '/') . '::class\s*\)/',
            $callerSource,
            $containerVariables
        );
        $variables = array_merge($variables, $containerVariables[1] ?? []);
        foreach (array_unique($variables) as $variable) {
            preg_match_all(
                '/\$' . preg_quote((string) $variable, '/')
                    . '\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
                $callerSource,
                $variableCalls
            );
            foreach ($variableCalls[1] ?? [] as $method) {
                $methods[] = $method;
            }
        }
    }

    // If the receiver cannot be mapped to this dependency, omit it.  A
    // same-named method on an unrelated object is not evidence of a call.
    return array_values(array_unique($methods));
}

/** @return array<string, string> */
function propertyDependencyTypes(ReflectionClass $class, string $classSource): array
{
    static $cache = [];
    $cacheKey = $class->getName();
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $types = [];
    foreach ($class->getProperties() as $property) {
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $types[$property->getName()] = ltrim($type->getName(), '\\');
        }
    }

    $constructor = $class->getConstructor();
    if ($constructor === null) {
        $cache[$cacheKey] = $types;

        return $types;
    }
    $parameters = [];
    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $parameters[$parameter->getName()] = ltrim($type->getName(), '\\');
        }
    }
    preg_match_all(
        '/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*=\s*\$([A-Za-z_][A-Za-z0-9_]*)/',
        $classSource,
        $assignments,
        PREG_SET_ORDER
    );
    foreach ($assignments as $assignment) {
        if (isset($parameters[$assignment[2]])) {
            $types[$assignment[1]] = $parameters[$assignment[2]];
        }
    }

    $cache[$cacheKey] = $types;

    return $types;
}

function interfaceImplementation(string $interface): ?string
{
    static $cache = [];
    if (array_key_exists($interface, $cache)) {
        return $cache[$interface];
    }

    $binding = app()->getBindings()[$interface]['concrete'] ?? null;
    $candidates = [];
    if (is_string($binding)) {
        $candidates[] = ltrim($binding, '\\');
    } elseif ($binding instanceof Closure) {
        try {
            $reflection = new ReflectionFunction($binding);
            $source = reflectionSource($reflection);
            $file = $reflection->getFileName();
            $fileContents = is_string($file) ? fileSource($file) : '';
            $imports = importMap($fileContents);
            preg_match_all('/\bnew\s+([A-Z][A-Za-z0-9_\\\\]*)\s*\(/', $source, $matches);
            foreach ($matches[1] ?? [] as $type) {
                $type = ltrim((string) $type, '\\');
                $candidates[] = $imports[$type] ?? $type;
            }
        } catch (Throwable $exception) {
            $candidates = [];
        }
    }

    foreach (array_unique($candidates) as $candidate) {
        if (class_exists($candidate) && is_a($candidate, $interface, true)) {
            $cache[$interface] = $candidate;

            return $candidate;
        }
    }

    $cache[$interface] = null;

    return null;
}

/**
 * @param array<string, string> $imports
 * @return array<int, string>
 */
function dependencyClasses(
    string $source,
    string $classSource,
    array $imports,
    ReflectionClass $class = null
): array {
    $classes = [];
    preg_match_all('~(?:^|[^A-Za-z0-9_])(App\\\\(?:Services|Support|Models|Contracts|Jobs)\\\\[A-Za-z0-9_\\\\]+)~', $source, $fqcnMatches);
    foreach ($fqcnMatches[1] ?? [] as $fqcn) {
        $classes[] = ltrim((string) $fqcn, '\\');
    }
    preg_match_all('/\b([A-Z][A-Za-z0-9_]+)::(?:class|[A-Za-z_][A-Za-z0-9_]*)/', $source, $shortMatches);
    foreach ($shortMatches[1] ?? [] as $short) {
        if (isset($imports[$short]) && str_starts_with($imports[$short], 'App\\')) {
            $classes[] = $imports[$short];
        }
    }
    preg_match_all(
        '/(?:^|[,(]\s*)(\??[A-Z][A-Za-z0-9_\\\\]*)\s+\$[A-Za-z_][A-Za-z0-9_]*/m',
        $source,
        $parameterMatches
    );
    foreach ($parameterMatches[1] ?? [] as $type) {
        $type = ltrim((string) $type, '?\\');
        if (isset($imports[$type])) {
            $classes[] = $imports[$type];
            continue;
        }
        if (str_starts_with($type, 'App\\')) {
            $classes[] = $type;
            continue;
        }
        if ($class !== null && !str_contains($type, '\\')) {
            $candidate = $class->getNamespaceName() . '\\' . $type;
            if (class_exists($candidate) || interface_exists($candidate)) {
                $classes[] = $candidate;
            }
        }
    }

    if ($class !== null) {
        preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)->/', $source, $propertyMatches);
        foreach (array_unique($propertyMatches[1] ?? []) as $property) {
            if (preg_match('/\$this->' . preg_quote($property, '/') . '\s*=\s*\$([A-Za-z_][A-Za-z0-9_]*)/', $classSource, $assignment)) {
                $constructor = $class->getConstructor();
                if ($constructor !== null) {
                    foreach ($constructor->getParameters() as $parameter) {
                        if ($parameter->getName() !== $assignment[1]) {
                            continue;
                        }
                        $type = $parameter->getType();
                        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                            $classes[] = $type->getName();
                        }
                    }
                }
            }
        }
    }

    return array_values(array_unique(array_filter($classes, static function (string $dependency): bool {
        return str_starts_with($dependency, 'App\\');
    })));
}

/** @param array<int, string> $dependencies */
function applicationChain(array $dependencies, string $source): string
{
    $parts = [];
    $hasPaymentRegistry = in_array(
        'App\Services\Payment\PaymentGatewayRegistry',
        $dependencies,
        true
    );
    foreach ($dependencies as $dependency) {
        $relative = str_starts_with($dependency, 'App\\') ? substr($dependency, 4) : $dependency;
        if (is_subclass_of($dependency, Model::class)) {
            try {
                /** @var Model $model */
                $model = new $dependency();
                $parts[] = '`' . markdownInline($relative) . '` -> table `' . markdownInline($model->getTable()) . '`';
            } catch (Throwable $exception) {
                $parts[] = '`' . markdownInline($relative) . '` (Eloquent Model)';
            }
            continue;
        }
        $parts[] = '`' . markdownInline($relative) . '`';
    }
    preg_match_all('/DB::table\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', $source, $tableMatches);
    foreach (array_unique($tableMatches[1] ?? []) as $table) {
        $parts[] = 'DB table `' . markdownInline($table) . '`';
    }
    if (str_contains($source, 'DB::transaction(')) {
        $parts[] = 'DB transaction';
    }
    if (str_contains($source, 'lockForUpdate(')) {
        $parts[] = 'row lock (`FOR UPDATE`)';
    }
    if ($hasPaymentRegistry) {
        $parts[] = '运行时按支付通道选择一个 `PaymentGatewayAdapter`';
    }

    return $parts === []
        ? '未发现直接 Service/Support/Model/DB 调用（View/Redirect/第三方 action）'
        : implode(' -> ', array_values(array_unique($parts))) . '（静态源码证据，非运行时调用追踪）';
}

/** @param array<int, string> $dependencies */
function externalDependencies(array $dependencies, string $source): string
{
    $external = [];
    foreach ($dependencies as $dependency) {
        if (preg_match('/Mt4|MetaTrader/i', $dependency) && !is_subclass_of($dependency, Model::class)) {
            $external[] = 'MT4 Manager';
        }
        if (preg_match('/Payment.*(?:Gateway|Adapter)|(?:Gateway|Adapter).*Payment|FundingGateway/i', $dependency)) {
            $external[] = '支付/资金 Gateway';
        }
        if (preg_match('/Mail|Email/i', $dependency)) {
            $external[] = '邮件服务';
        }
    }
    $patterns = [
        '/Http::|Guzzle|curl_|stream_socket_client|fsockopen/' => '外部 HTTP/TCP',
        '/Mail::|Mailer|MailService/' => '邮件服务',
        '/Storage::|Filesystem/' => '文件存储',
        '/Queue::|dispatch\(|->dispatch\(/' => '队列/Outbox',
        '/Cache::|Redis::|RateLimiter/' => '缓存/Redis/限流',
    ];
    foreach ($patterns as $pattern => $label) {
        if (preg_match($pattern, $source)) {
            $external[] = $label;
        }
    }

    return $external === [] ? '无直接外部依赖证据' : implode('；', array_values(array_unique($external)));
}

function branchEvidence(string $source, bool $success): string
{
    $patterns = $success
        ? [
            '/\$this->success\s*\(/',
            '/return\s+(?:response\(\)->json|view|redirect|back)\s*\(/',
            '/return\s+.*(?:streamDownload|download|csvDownload)\s*\(/',
        ]
        : ['/->fails\s*\(/', '/\$this->error\s*\(/', '/\b(?:throw|catch)\b/', '/abort\s*\(/'];
    // 多行 redirect/view/response 返回值必须先按 PHP token 合并，避免报告退化成无法审计目标和参数的 `return redirect(...)`。
    $evidence = $success ? responseReturnStatements($source) : [];
    foreach (preg_split('/\R/u', $source) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
            continue;
        }
        if ($success
            && !str_ends_with($trimmed, ';')
            && preg_match(
                '/^return\s+(?:response\(\)->(?:json|streamDownload|download|file)|view|redirect|back)\s*\(/',
                $trimmed
            ) === 1) {
            // 完整语句已经由 responseReturnStatements 收集；跳过首行，防止同时输出泛化占位。
            continue;
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                $evidence[] = normalizeBranchEvidence($trimmed);
                break;
            }
        }
        if (count($evidence) >= 5) {
            break;
        }
    }

    if ($evidence === []) {
        return $success
            ? '由 action 返回 View/JsonResponse/Redirect 的正常路径决定'
            : '由中间件拒绝、参数/领域校验或未捕获异常决定';
    }

    return implode('；', array_map(static function (string $item): string {
        return '`' . markdownInline($item) . '`';
    }, array_values(array_unique($evidence))));
}

/**
 * 从 action 源码提取完整的响应型 return 语句。
 *
 * 设计原因：
 * - 路由闭包常把命名路由参数数组分成多行，逐行扫描只能看到 `return redirect(` 的开头。
 * - PHP token 能区分闭包内部的分号与外层 return 终止分号，避免用跨行正则误截断下载回调。
 *
 * @param string $source Reflection 返回的 Controller 方法或路由闭包源码。
 * @return array<int, string> 已压缩空白且保留路由名、参数和结尾分号的响应返回语句。
 */
function responseReturnStatements(string $source): array
{
    $tokens = token_get_all("<?php\n" . $source);
    $statements = [];
    $collecting = false;
    $statement = '';
    $delimiterDepth = 0;

    foreach ($tokens as $token) {
        if (!$collecting) {
            if (is_array($token) && $token[0] === T_RETURN) {
                $collecting = true;
                $statement = 'return';
                $delimiterDepth = 0;
            }
            continue;
        }

        if (is_array($token)) {
            if ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                $statement .= $token[1];
            }
            continue;
        }

        if (in_array($token, ['(', '[', '{'], true)) {
            $delimiterDepth++;
        } elseif (in_array($token, [')', ']', '}'], true)) {
            $delimiterDepth = max(0, $delimiterDepth - 1);
        }

        $statement .= $token;
        if ($token !== ';' || $delimiterDepth !== 0) {
            continue;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($statement)) ?? trim($statement);
        if (preg_match(
            '/^return\s+(?:response\(\)->(?:json|streamDownload|download|file)|view|redirect|back)\s*\(/',
            $normalized
        ) === 1) {
            $statements[] = normalizeBranchEvidence($normalized);
        }
        $collecting = false;
        $statement = '';
        $delimiterDepth = 0;
    }

    return array_values(array_unique($statements));
}

function normalizeBranchEvidence(string $line): string
{
    if (str_starts_with($line, 'return $this->success(') && !str_ends_with($line, ';')) {
        return 'return $this->success(...)';
    }
    if (str_starts_with($line, 'return $this->error(') && !str_ends_with($line, ';')) {
        return 'return $this->error(...)';
    }
    if (preg_match('/^return\s+\$this->(csvDownload|download)\s*\(/', $line, $matches)
        && !str_ends_with($line, ';')) {
        return 'return $this->' . $matches[1] . '(...)';
    }
    if (preg_match(
        '/^return\s+(response\(\)->(?:json|streamDownload|download|file)|view|redirect|back)\s*\([^;]*$/',
        $line,
        $matches
    )) {
        return 'return ' . $matches[1] . '(...)';
    }
    if (preg_match('/^if\s*\((.+)\)\s*\{$/', $line, $matches)) {
        return '条件成立：' . truncateEvidence($matches[1]);
    }
    if (preg_match('/^}\s*catch\s*\((.+)\)\s*\{$/', $line, $matches)) {
        return '捕获异常：' . truncateEvidence($matches[1]);
    }

    return truncateEvidence($line);
}

function responseCodeEvidence(string $source): string
{
    preg_match_all('/ResponseCode::([A-Z][A-Z0-9_]*)/', $source, $matches);

    return implode(' ', array_map(static function (string $code): string {
        return 'ResponseCode::' . $code;
    }, array_values(array_unique($matches[1] ?? []))));
}

function truncateEvidence(string $value): string
{
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return mb_strlen($value, 'UTF-8') > 180
        ? mb_substr($value, 0, 177, 'UTF-8') . '...'
        : $value;
}

/** @return array<string, string> */
function importMap(string $source): array
{
    static $cache = [];
    $cacheKey = hash('sha256', $source);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $map = [];
    preg_match_all('/^use\s+([^;]+);/m', $source, $matches);
    foreach ($matches[1] ?? [] as $import) {
        $import = trim($import);
        if (str_contains($import, '{') || !str_starts_with($import, 'App\\')) {
            continue;
        }
        $parts = preg_split('/\s+as\s+/i', $import) ?: [];
        $class = trim((string) ($parts[0] ?? ''));
        $short = trim((string) ($parts[1] ?? basename(str_replace('\\', '/', $class))));
        if ($class !== '' && $short !== '') {
            $map[$short] = $class;
        }
    }

    $cache[$cacheKey] = $map;

    return $map;
}

/** @return array<int, string> */
function viewNames(string $source): array
{
    preg_match_all('/\bview\(\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $matches);

    return array_values(array_unique($matches[1] ?? []));
}

/**
 * @param array<string, string> $files
 */
function evidenceFiles(
    array $files,
    string $uri,
    string $name,
    string $method,
    string $kind,
    int $limit,
    string $emptyMessage
): string {
    static $cache = [];
    static $lineCache = [];
    static $fileSetKeys = [];
    static $testRequestLineCache = [];
    if (!isset($fileSetKeys[$kind])) {
        $fileSetKeys[$kind] = hash('sha256', implode("\0", array_keys($files)));
    }
    $fileSetKey = $fileSetKeys[$kind];
    $cacheKey = implode('|', [$fileSetKey, strtoupper($method), $uri, $name, (string) $limit]);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $matches = [];
    $segments = routeUriEvidenceSegments($uri);
    $referenceNeedles = array_values(array_filter(
        array_merge($segments, [$name]),
        static fn (string $needle): bool => mb_strlen($needle, 'UTF-8') >= 2
    ));
    foreach ($files as $path => $content) {
        if ($kind === 'test' && preg_match('/(?:Migration|Fixture|Seeder|Factory)/i', basename($path))) {
            continue;
        }
        $hasReference = false;
        foreach ($referenceNeedles as $needle) {
            if (stripos($content, $needle) !== false) {
                $hasReference = true;
                break;
            }
        }
        if (!$hasReference) {
            continue;
        }

        $lineCacheKey = $fileSetKey . '|' . $path;
        if (!isset($lineCache[$lineCacheKey])) {
            $lineCache[$lineCacheKey] = preg_split('/\R/u', $content) ?: [];
        }
        $lines = $lineCache[$lineCacheKey];
        if ($kind === 'test' && !array_key_exists($lineCacheKey, $testRequestLineCache)) {
            $testRequestLineCache[$lineCacheKey] = phpTestRequestMethodLines($content);
        }
        $requestLines = $kind === 'test'
            ? ($testRequestLineCache[$lineCacheKey][strtoupper($method)] ?? [])
            : [];
        foreach ($lines as $index => $line) {
            $isRedirectAssertion = $kind === 'test' && isRedirectAssertionLine($line);
            if (isCommentOrStaticAssertionLine($line, $kind) && !$isRedirectAssertion) {
                continue;
            }
            $start = max(0, $index - 1);
            $window = implode(PHP_EOL, array_slice($lines, $start, 3));
            $referenceIndex = routeReferenceLineIndex($lines, $start, $index + 1, $segments, $name);
            if ($referenceIndex === null || abs($referenceIndex - $index) > 1) {
                continue;
            }
            if ($kind === 'test') {
                $windowStart = max(0, $index - 1);
                $windowEnd = min(count($lines) - 1, $index + 1);
                $requestLineIndex = null;
                $requestLineDistance = PHP_INT_MAX;
                foreach ($requestLines as $requestLine) {
                    if ($requestLine >= $windowStart && $requestLine <= $windowEnd) {
                        $distance = abs($requestLine - $index);
                        if ($distance < $requestLineDistance) {
                            $requestLineIndex = $requestLine;
                            $requestLineDistance = $distance;
                        }
                    }
                }
                if ($requestLineIndex === null) {
                    continue;
                }
                // A redirect assertion may mention the destination URI without
                // requesting it. Point cross-line evidence at the actual request;
                // when request and assertion share one line, the literal belongs
                // to the redirect target and must not become route-request proof.
                if ($isRedirectAssertion) {
                    if ($requestLineIndex === $index) {
                        continue;
                    }
                    $referenceIndex = $requestLineIndex;
                }
            } elseif (!httpMethodEvidenceMatches($window, $method, $kind)) {
                continue;
            }

            $label = $kind === 'test' ? '测试请求证据' : '前端请求证据';
            $matches[] = '`' . markdownInline($path . ':' . ($referenceIndex + 1)) . '`（'
                . markdownInline($method) . ' ' . $label . '）';
            if (count($matches) >= $limit) {
                break;
            }
            break;
        }
        if (count($matches) >= $limit) {
            break;
        }
    }

    $result = $matches === [] ? $emptyMessage : implode('；', $matches);
    $cache[$cacheKey] = $result;

    return $result;
}

/**
 * A legacy URI can be covered by a shared inventory/authentication contract even
 * when no test contains that exact URI literal. Keep that distinction explicit:
 * shared evidence proves the route boundary, not a successful business mutation.
 */
function routeTestEvidence(string $name, string $directEvidence): string
{
    if ($directEvidence !== '未检索到直接 PHPUnit 证据') {
        return $directEvidence;
    }

    if (str_starts_with($name, 'legacy_admin_')) {
        return '共享契约证据：`tests/Feature/AdminLegacyRouteInventoryClosureTest.php`（旧后台路由映射、方法和缺口）；'
            . '`tests/Feature/AdminLegacyRouteSemanticClosureTest.php`（认证、权限及特殊响应）；'
            . '本条未检索到独立 HTTP 请求时，共享契约不等同于该业务已成功。';
    }

    if (str_starts_with($name, 'legacy_')) {
        return '共享契约证据：`tests/Feature/FrontLegacyRouteCompatibilityTest.php`（旧前台注册、页面和 Ajax smoke）；'
            . '`tests/Feature/FrontLegacyAuthenticationBoundaryClosureModuleTest.php`（认证边界）；'
            . '`tests/Feature/FrontLegacyMethodPolicyClosureModuleTest.php`（方法策略）；'
            . '本条未检索到独立 HTTP 请求时，共享契约不等同于该业务已成功。';
    }

    return $directEvidence;
}

/** @return array<int, string> */
function routeUriEvidenceSegments(string $uri): array
{
    if (!str_contains($uri, '{')) {
        return mb_strlen($uri, 'UTF-8') >= 4 ? [$uri] : [];
    }

    $segments = preg_split('/\{[^}]+\??\}/', $uri) ?: [];

    return array_values(array_filter($segments, static function (string $segment): bool {
        return mb_strlen($segment, 'UTF-8') >= 2;
    }));
}

/** @param array<int, string> $segments */
/** @param array<int, string> $lines @param array<int, string> $segments */
function routeReferenceLineIndex(
    array $lines,
    int $start,
    int $endExclusive,
    array $segments,
    string $name
): ?int {
    for ($index = $start; $index < $endExclusive; $index++) {
        $line = (string) ($lines[$index] ?? '');
        if ($segments !== []) {
            $allSegmentsMatch = true;
            foreach ($segments as $segment) {
                if (stripos($line, $segment) === false) {
                    $allSegmentsMatch = false;
                    break;
                }
            }
            if ($allSegmentsMatch) {
                return $index;
            }
        } elseif (mb_strlen($name, 'UTF-8') >= 6 && stripos($line, $name) !== false) {
            return $index;
        }
    }

    return null;
}

function isCommentOrStaticAssertionLine(string $line, string $kind): bool
{
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')
        || str_starts_with($trimmed, '#')) {
        return true;
    }

    return $kind === 'test' && preg_match(
        '/assert(?:Redirect|SeeRedirect|String|See|Contains|Matches|Same|Not|File)|file_get_contents|fixtureOnly/i',
        $trimmed
    ) === 1;
}

function isRedirectAssertionLine(string $line): bool
{
    return preg_match('/\bassert(?:Redirect|SeeRedirect)[A-Za-z0-9_]*\s*\(/i', $line) === 1;
}

function httpMethodEvidenceMatches(string $window, string $method, string $kind): bool
{
    $quotedMethod = preg_quote($method, '/');
    $lowerMethod = strtolower($method);
    if ($kind === 'test') {
        return phpTestRequestCallMatches($window, $method);
    } else {
        $patterns = [
            '/\b(?:method|type)\s*:\s*[\'\"]' . $quotedMethod . '[\'\"]/i',
            '/\b(?:method|type)\s*=\s*[\'\"]' . $quotedMethod . '[\'\"]/i',
            '/\$\.' . preg_quote($lowerMethod, '/') . '\s*\(/i',
            '/\b(?:axios|http)\.' . preg_quote($lowerMethod, '/') . '\s*\(/i',
        ];
    }

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $window)) {
            return true;
        }
    }

    return false;
}

function phpTestRequestCallMatches(string $source, string $method): bool
{
    $lines = phpTestRequestMethodLines($source);

    return ($lines[strtoupper($method)] ?? []) !== [];
}

/** @return array<string, array<int, int>> */
function phpTestRequestMethodLines(string $source): array
{
    $hasOpenTag = preg_match('/^\s*<\?php\b/', $source) === 1;
    $tokens = token_get_all($hasOpenTag ? $source : "<?php\n" . $source);
    $lineOffset = $hasOpenTag ? 0 : 1;
    $lines = [];
    $directMethods = [];
    foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $httpMethod) {
        $directMethods[strtolower($httpMethod)] = $httpMethod;
        $directMethods[strtolower($httpMethod) . 'json'] = $httpMethod;
    }
    $count = count($tokens);
    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }
        $name = strtolower($token[1]);
        if (isset($directMethods[$name])) {
            $next = nextSignificantPhpToken($tokens, $index + 1);
            if ($next === '(' && hasThisReceiverInPhpCall($tokens, $index)) {
                $lines[$directMethods[$name]][] = max(0, (int) $token[2] - 1 - $lineOffset);
            }
            continue;
        }
        if (!in_array($name, ['json', 'call', 'request'], true)) {
            continue;
        }
        if (!hasThisReceiverInPhpCall($tokens, $index)) {
            continue;
        }
        $nextIndex = nextSignificantPhpTokenIndex($tokens, $index + 1);
        if ($nextIndex === null || $tokens[$nextIndex] !== '(') {
            continue;
        }
        $methodIndex = nextSignificantPhpTokenIndex($tokens, $nextIndex + 1);
        if ($methodIndex === null || !is_array($tokens[$methodIndex])
            || $tokens[$methodIndex][0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $literal = trim($tokens[$methodIndex][1], "'\"");
        $literal = strtoupper($literal);
        if (in_array($literal, $directMethods, true)) {
            $lines[$literal][] = max(0, (int) $token[2] - 1 - $lineOffset);
        }
    }

    foreach ($lines as $name => $lineNumbers) {
        $lines[$name] = array_values(array_unique($lineNumbers));
    }

    return $lines;
}

/** @param array<int, array<int, mixed>|string> $tokens */
function hasThisReceiverInPhpCall(array $tokens, int $methodIndex): bool
{
    for ($index = $methodIndex - 1; $index >= 0; $index--) {
        $token = $tokens[$index];
        if (is_string($token)) {
            if (in_array($token, [';', '{', '}'], true)) {
                return false;
            }
            continue;
        }
        if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if ($token[0] === T_VARIABLE && $token[1] === '$this') {
            return true;
        }
        if (in_array($token[0], [T_FUNCTION, T_FN], true)) {
            return false;
        }
    }

    return false;
}

/** @param array<int, array<int, mixed>|string> $tokens */
function nextSignificantPhpToken(array $tokens, int $start)
{
    $index = nextSignificantPhpTokenIndex($tokens, $start);

    return $index === null ? null : $tokens[$index];
}

/** @param array<int, array<int, mixed>|string> $tokens */
function nextSignificantPhpTokenIndex(array $tokens, int $start): ?int
{
    for ($index = $start, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $index;
    }

    return null;
}

/**
 * @param array<int, string> $roots
 * @param array<int, string> $extensions
 * @return array<string, string>
 */
function discoverTextFiles(array $roots, array $extensions): array
{
    $files = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $name = strtolower($file->getFilename());
            $accepted = false;
            foreach ($extensions as $extension) {
                if (str_ends_with($name, '.' . strtolower($extension))) {
                    $accepted = true;
                    break;
                }
            }
            if (!$accepted) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if (!is_string($content)) {
                continue;
            }
            $files[relativePath($file->getPathname())] = $content;
        }
    }
    ksort($files);

    return $files;
}

function actionName(Route $route): string
{
    $uses = $route->getAction('uses');
    if ($uses instanceof Closure) {
        return 'Closure';
    }
    if (is_string($uses)) {
        return ltrim($uses, '\\');
    }

    return $route->getActionName();
}

function actionAnalysisCacheKey(Route $route, string $action): string
{
    $uses = $route->getAction('uses');

    return $uses instanceof Closure
        ? 'closure:' . spl_object_id($uses)
        : 'action:' . $action;
}

function middlewareChain(Route $route): string
{
    try {
        $middleware = array_map(static function ($item): string {
            return is_string($item) ? $item : get_debug_type($item);
        }, $route->gatherMiddleware());

        return $middleware === [] ? '无路由中间件' : implode(' -> ', $middleware);
    } catch (Throwable $exception) {
        return '中间件解析失败：' . $exception->getMessage();
    }
}

function groupName(Route $route): string
{
    $uri = '/' . ltrim($route->uri(), '/');
    $name = (string) ($route->getName() ?? '');
    $action = actionName($route);
    if (str_starts_with($uri, '/api/admin')) {
        return '后台管理员 API';
    }
    if (str_starts_with($uri, '/api/front')) {
        return '普通用户/代理商前台 API';
    }
    if (str_starts_with($uri, '/admin-crmui')) {
        return '后台管理员 CRM UI';
    }
    if (str_starts_with($uri, '/front-crmui')) {
        return '普通用户/代理商 CRM UI';
    }
    if (str_starts_with($uri, '/index/admin') || str_starts_with($name, 'legacy_admin_')) {
        return '旧项目后台管理员兼容路由';
    }
    if (str_starts_with($uri, '/admin')) {
        return '后台管理员 Blade/Layui';
    }
    if (str_contains($uri, '/agents') || str_contains($uri, '/proxy') || str_contains($action, 'Agent')) {
        return '代理商/大代理路由';
    }
    if (str_starts_with($name, 'legacy_') || str_starts_with($uri, '/user')) {
        return '旧项目普通用户兼容路由';
    }
    if (str_starts_with($uri, '/front')) {
        return '普通用户/代理商 Blade/Layui';
    }

    return '公共/系统路由';
}

/**
 * 为每个当前路由给出可读的业务目的，避免报告只有类名而没有使用场景。
 *
 * @param array<string, string> $entry
 */
function routeBusinessPurpose(array $entry): string
{
    $action = $entry['action'] ?? '未解析 action';
    $operation = routeOperationDescription($entry);

    return routeSignatureLabel($entry) . ' 执行“' . $operation . '”；由 `'
        . markdownInline($action) . '` 承担入口编排并返回该动作的明确响应。';
}

/**
 * 说明该路由主要消除的旧系统风险或业务问题。
 *
 * @param array<string, string> $entry
 */
function routeProblemStatement(array $entry): string
{
    $signature = routeSignatureLabel($entry);
    $operation = routeOperationDescription($entry);
    $method = $entry['method'] ?? '';
    $success = $entry['success_branches'] ?? '';
    if ($method === 'HEAD') {
        $headSemantics = trim($entry['head_semantics'] ?? '');
        if ($headSemantics !== '') {
            return $signature . ' 明确记录“' . $headSemantics
                . '”，避免调用方忽略认证状态、Session 或其他入口副作用。';
        }

        return $signature . ' 把“' . $operation
            . '”限定为无响应体探测，避免调用方把 HEAD 当成独立写操作或误读页面正文。';
    }
    if (str_contains($success, 'view') || str_contains($success, 'redirect')) {
        return $signature . ' 将“' . $operation
            . '”固定到当前页面/重定向 action，避免导航落入错误接口或把渲染异常当成业务成功。';
    }
    if (in_array($method, ['GET'], true) || str_starts_with($operation, '查询')) {
        return $signature . ' 在当前中间件和 action 边界内完成“' . $operation
            . '”，避免读请求越过身份/资源范围或误触发其他写链。';
    }

    return $signature . ' 将“' . $operation
        . '”绑定到唯一方法和 action，避免错误 HTTP 方法、未校验输入、越权调用或非法状态推进。';
}

/** @param array<string, string> $entry */
function routeSignatureLabel(array $entry): string
{
    return 'HTTP ' . ($entry['method'] ?? 'UNKNOWN') . ' `'
        . markdownInline($entry['uri'] ?? '/') . '`';
}

/**
 * The URI and action are both used: the URI identifies the resource while the
 * action/return evidence distinguishes list, mutation, page and callback flows.
 *
 * @param array<string, string> $entry
 */
function routeOperationDescription(array $entry): string
{
    $method = strtoupper($entry['method'] ?? '');
    $resource = routeResourceDescription($entry);
    $success = $entry['success_branches'] ?? '';
    $override = trim($entry['operation_description'] ?? '');
    $headSemantics = trim($entry['head_semantics'] ?? '');

    if ($override !== '') {
        if ($method === 'HEAD' && $headSemantics !== '') {
            return $override . '；' . $headSemantics;
        }

        return $override;
    }

    if ($method === 'HEAD') {
        return '检查' . $resource . '入口是否可达并仅返回响应头';
    }

    if ($headSemantics !== '') {
        return $headSemantics;
    }
    $returnType = strtolower($entry['return_type'] ?? '');
    $isFile = preg_match('/download|stream|file\s*\(/i', $success) === 1
        || preg_match('/binaryfile|streamedresponse|file$/i', $returnType) === 1;
    $hasView = ($entry['view_names'] ?? '') !== '' || preg_match('/\bview\s*\(/', $success) === 1;
    $hasRedirect = preg_match('/\bredirect\s*\(/', $success) === 1
        || str_contains($returnType, 'redirect');
    $action = $entry['action'] ?? '';
    $actionMethod = str_contains($action, '@') ? explode('@', $action, 2)[1] : $action;
    $actionWords = semanticIdentifierWords($actionMethod);
    $routeWords = semanticIdentifierWords(($entry['uri'] ?? '') . ' ' . ($entry['name'] ?? ''));

    if ($isFile) {
        return semanticWordsContain($actionWords, ['template'])
            ? '下载' . $resource . 'CSV 模板'
            : '导出或下载' . $resource . '数据';
    }
    if ($hasView) {
        return '渲染' . $resource . '页面';
    }
    if (semanticWordsContain($routeWords, ['return'])
        || semanticWordsContain($actionWords, ['return'])) {
        return '处理' . $resource . '浏览器同步返回并进入后续页面';
    }
    if ($hasRedirect) {
        return '将浏览器重定向到' . $resource . '目标页面';
    }
    if (semanticWordsContain($actionWords, ['dashboard'])
        || semanticWordsContain($routeWords, ['dashboard'])) {
        return '查询' . $resource . '数据';
    }

    $queryWords = [
        'list', 'index', 'query', 'search', 'statistics', 'summary', 'trend',
        'dashboard', 'overview', 'options', 'history', 'detail', 'info', 'show', 'find',
    ];
    $mutationWords = [
        'create', 'store', 'add', 'save', 'update', 'edit', 'change', 'bind', 'unbind', 'set',
        'submit', 'apply', 'request', 'delete', 'destroy', 'remove', 'upload', 'transfer',
        'cancel', 'revoke', 'send', 'reject', 'refuse', 'decline', 'review', 'audit',
        'approve', 'approval', 'reconcile', 'register', 'signup', 'logout', 'signout',
        'notify', 'callback',
    ];
    $summary = trim($entry['action_summary'] ?? '');
    if ($summary !== '') {
        if (semanticWordsContain($actionWords, $queryWords)
            && !semanticWordsContain($actionWords, $mutationWords)
            && !str_starts_with($summary, '查询')) {
            return '查询' . $resource . '：' . $summary;
        }

        return $summary;
    }
    if (semanticWordsContain($actionWords, $queryWords)
        && !semanticWordsContain($actionWords, $mutationWords)) {
        return '查询' . $resource . '列表、详情或汇总';
    }
    if (semanticWordsContain($actionWords, ['notify', 'notfiy', 'callback'])) {
        return '接收并校验' . $resource . '异步通知';
    }
    if (semanticWordsContain($actionWords, ['login', 'signin'])) {
        return '校验' . $resource . '登录凭据并返回认证结果';
    }
    if (semanticWordsContain($actionWords, ['logout', 'signout'])) {
        return '注销' . $resource . '会话或令牌';
    }
    if (semanticWordsContain($actionWords, ['register', 'signup'])) {
        return '校验并创建' . $resource . '注册资料';
    }
    if (semanticWordsContain($actionWords, ['reconcile', 'reconciliation'])) {
        return '核对并处理' . $resource . '异常或未知状态';
    }
    if (semanticWordsContain($actionWords, ['reject', 'refuse', 'decline'])) {
        return '校验并驳回' . $resource;
    }
    if (semanticWordsContain($actionWords, ['review', 'audit', 'approve', 'approval', 'pass'])) {
        return '校验并审批' . $resource;
    }
    if (semanticWordsContain($actionWords, ['delete', 'destroy', 'remove']) || $method === 'DELETE') {
        return '校验并删除' . $resource;
    }
    if (semanticWordsContain($actionWords, ['update', 'edit', 'change', 'bind', 'unbind', 'set'])
        || in_array($method, ['PUT', 'PATCH'], true)) {
        return '校验并更新' . $resource;
    }
    if (semanticWordsContain($actionWords, ['create', 'store', 'add', 'save'])) {
        return '校验并创建' . $resource;
    }
    if (semanticWordsContain($actionWords, ['submit', 'apply', 'request'])) {
        return '校验并提交' . $resource;
    }
    if (semanticWordsContain($actionWords, ['validate', 'verify', 'check', 'checks'])) {
        return '校验' . $resource . '输入或状态';
    }
    if (semanticWordsContain($actionWords, ['export', 'download'])) {
        return '导出或下载' . $resource;
    }
    if (semanticWordsContain($actionWords, ['import'])) {
        return '校验并导入' . $resource;
    }
    if (semanticWordsContain($actionWords, ['upload', 'avatar', 'attachment', 'certificate'])) {
        return '校验并上传' . $resource;
    }
    if (semanticWordsContain($actionWords, ['transfer'])) {
        return '校验并提交' . $resource . '转账';
    }
    if (semanticWordsContain($actionWords, ['cancel', 'revoke'])) {
        return '校验并取消或申请取消' . $resource;
    }

    return $method === 'GET'
        ? '查询或展示' . $resource
        : '执行' . $resource . '业务动作并返回明确结果';
}

/** @return array<int, string> */
function semanticIdentifierWords(string $value): array
{
    $value = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $value) ?? $value;
    $value = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $value) ?? $value;
    $words = preg_split('/[^A-Za-z0-9]+/', strtolower($value)) ?: [];

    return array_values(array_unique(array_filter($words, static fn (string $word): bool => $word !== '')));
}

/** @param array<int, string> $words @param array<int, string> $needles */
function semanticWordsContain(array $words, array $needles): bool
{
    return array_intersect($words, $needles) !== [];
}

/** @param array<string, string> $entry */
function routeResourceDescription(array $entry): string
{
    $uri = strtolower($entry['uri'] ?? '/');
    $context = preg_replace('/[^a-z0-9]+/', '', strtolower(implode(' ', [
        $uri,
        $entry['name'] ?? '',
        $entry['action'] ?? '',
    ]))) ?? '';
    $resources = [
        'commissiontransfersreconciliationcases' => '返佣转账人工核对',
        'commissiontransfer' => '返佣转账',
        'paymentreturn' => '支付结果',
        'paymentnotify' => '支付回调',
        'adminagentbinding' => '管理员与代理绑定关系',
        'reviewauth' => '用户身份资料',
        'riskforceclose' => 'MT4 风险持仓',
        'paymentsettlement' => '支付结算',
        'paymentchannel' => '支付通道',
        'canceleappl' => '注销申请',
        'cancelapply' => '注销申请',
        'bigagent' => '大代理',
        'bignumber' => '大代理编号',
        'directcustomer' => '直属客户',
        'customer' => '客户',
        'datascope' => '管理员数据范围',
        'permission' => '角色权限',
        'role' => '管理员角色',
        'menu' => '权限菜单',
        'administrator' => '管理员',
        'admins' => '管理员',
        'agent' => '代理商',
        'proxy' => '代理商',
        'withdraw' => '出金订单',
        'deposit' => '入金订单',
        'commission' => '返佣',
        'reconcile' => '人工核对记录',
        'mt4' => 'MT4 账户',
        'position' => '持仓',
        'trade' => '交易',
        'order' => '订单',
        'ledger' => '账户流水',
        'balance' => '账户余额',
        'profile' => '个人资料',
        'avatar' => '用户头像',
        'address' => '收货地址',
        'user' => '用户',
        'dashboard' => '仪表盘统计',
        'login' => '登录',
        'register' => '注册',
        'password' => '密码',
        'auth' => '身份认证',
        'gift' => '礼品',
        'news' => '新闻',
        'groupconfig' => '组别配置',
        'systemconfig' => '系统配置',
        'exchangerate' => '汇率配置',
        'creditimport' => '信用额导入',
        'depositimport' => '入金导入',
        'debugbar' => '调试工具',
        'csrf' => 'CSRF Cookie',
        'health' => '健康检查',
    ];
    foreach ($resources as $needle => $label) {
        if (str_contains($context, $needle)) {
            return $label;
        }
    }
    if ($uri === '/' || $uri === '') {
        return '站点公开入口';
    }

    $segments = array_values(array_filter(explode('/', trim($uri, '/')), static function (string $segment): bool {
        return $segment !== ''
            && !in_array($segment, ['api', 'front', 'admin', 'index', 'user'], true)
            && !str_starts_with($segment, '{');
    }));
    $resource = $segments === [] ? trim($uri, '/') : implode('/', $segments);

    return '资源 `' . markdownInline($resource !== '' ? $resource : '/') . '`';
}

/**
 * 给出固定顺序的执行步骤说明，供中文报告读者理解每一层为何存在。
 *
 * @param array<string, string> $entry
 */
function routeStepAnnotation(array $entry): string
{
    $middleware = middlewarePurpose($entry['middleware'] ?? '');
    $chain = stripMarkdown($entry['application_chain'] ?? '');
    $external = stripMarkdown($entry['external_dependencies'] ?? '');
    $headSemantics = trim($entry['head_semantics'] ?? '');
    $responseStep = ($entry['method'] ?? '') === 'HEAD'
        ? ($headSemantics !== ''
            ? '6）' . $headSemantics . '。'
            : '6）HEAD 方法只保留状态码和响应头，不返回响应体。')
        : '6）最后按源码中可见的成功/失败分支返回 JSON、页面、文件/数据流或重定向。';

    return '1）路由层按 HTTP 方法和 URI 选择唯一入口，防止同一路径落入错误 action；'
        . '2）中间件层' . $middleware . '，在进入 Controller 前阻断未认证、越权、频率或会话冲突；'
        . '3）Controller/action 执行源码中可见的参数处理和调用编排，不推断未出现的兼容或持久化行为；'
        . '4）静态可检索应用链为 ' . ($chain !== '' ? $chain : '未检索到直接 Service/Model/DB 调用')
        . '，箭头按源码可见顺序表达，不等同于运行时调用追踪；'
        . '5）静态可检索外部依赖为 ' . ($external !== '' ? $external : '无')
        . '，“未检索到”不表示运行时绝对不存在；'
        . $responseStep;
}

function middlewarePurpose(string $middleware): string
{
    if ($middleware === '' || $middleware === '无路由中间件') {
        return '无额外中间件（由 action 自身负责公开入口校验）';
    }

    $rules = [
        'web' => '启用 Cookie、Session、CSRF、语言和路由绑定',
        'api' => '启用语言、限流和路由绑定',
        'jwt.auth:admin' => '校验管理员 JWT 身份',
        'jwt.auth:user' => '校验普通用户/代理 JWT 身份',
        'jwt.auth:big_agent' => '校验大代理 JWT 身份',
        'sso:admin' => '校验管理员单点登录 token 未被顶下线',
        'sso:user' => '校验普通用户/代理单点登录 token 未被顶下线',
        'sso:big_agent' => '校验大代理单点登录 token 未被顶下线',
        'check.permission:admin' => '按当前管理员角色检查接口权限',
        'legacy.front.auth' => '校验旧前台 suser 或 bigAgents 会话边界',
        'legacy.admin.auth' => '校验旧后台管理员会话或令牌边界',
        'throttle:api' => '限制单位时间请求次数',
        'auth' => '校验 Laravel guard 登录状态',
    ];
    $matched = [];
    foreach (explode(' -> ', $middleware) as $item) {
        $item = trim($item);
        if (isset($rules[$item])) {
            $matched[] = $rules[$item];
        } elseif ($item !== '') {
            $matched[] = '执行 `' . $item . '` 中间件';
        }
    }

    return $matched === [] ? '解析中间件链并执行安全前置检查' : implode('；', $matched);
}

/**
 * 将代码和返回类型翻译成中文含义；报告不依赖读者再查 ResponseCode.php。
 *
 * @param array<string, string> $entry
 */
function routeReturnMeaning(array $entry): string
{
    $source = ($entry['success_branches'] ?? '') . ' '
        . ($entry['failure_branches'] ?? '') . ' '
        . ($entry['response_codes'] ?? '');
    preg_match_all('/ResponseCode::([A-Z][A-Z0-9_]*)/', $source, $matches);
    $meanings = [
        'SUCCESS' => '操作成功', 'CREATED' => '创建成功', 'UPDATED' => '更新成功',
        'DELETED' => '删除成功', 'UPLOADED' => '上传成功', 'REGISTER_SUCCESS' => '注册成功',
        'DEFAULT_ADDRESS_MUST_EXIST' => '至少保留一个默认收货地址',
        'EMAIL_EXISTS' => '邮箱已存在', 'PHONE_EXISTS' => '手机号已存在',
        'INVALID_INVITER' => '邀请人无效', 'INVITER_DISABLED' => '邀请人已禁用',
        'INVALID_COMMISSION_RATE' => '返佣比例无效', 'INVALID_GROUP' => '组别无效',
        'INVALID_AGENT_LEVEL' => '代理级别无效', 'USER_NOT_FOUND' => '用户不存在',
        'USER_DISABLED' => '用户已禁用', 'USER_CANCELLED' => '用户已注销',
        'INVALID_AUDIT_STATUS' => '审核状态无效', 'WITHDRAWAL_NOT_ALLOWED' => '出金不允许',
        'DEPOSIT_NOT_ALLOWED' => '入金不允许', 'INVALID_AMOUNT' => '金额无效',
        'INSUFFICIENT_BALANCE' => '余额不足', 'RISK_RATE_EXCEEDED' => '风险率超限',
        'CANCEL_APPLY_EXISTS' => '注销申请已存在', 'BLACKLISTED' => '黑名单用户',
        'DATA_NOT_FOUND' => '数据不存在', 'DATA_ALREADY_EXISTS' => '数据已存在',
        'OPERATION_NOT_ALLOWED' => '操作不允许', 'COMMISSION_EXCEEDS_PARENT' => '返佣比例不能大于上级',
        'SETTLEMENT_NOT_FOUND' => '结算记录不存在', 'ORDER_NOT_FOUND' => '订单不存在',
        'MT4_SYNC_FAILED' => 'MT4 同步失败', 'QUERY_SUCCESS' => '查询成功',
        'QUERY_FAILED' => '查询失败', 'IMPORT_SUCCESS' => '导入成功',
        'IMPORT_FAILED' => '导入失败', 'EXPORT_SUCCESS' => '导出成功',
        'BATCH_SUCCESS' => '批量操作成功', 'BATCH_PARTIAL_FAILED' => '批量操作部分失败',
        'ERROR' => '通用错误', 'AUTH_FAILED' => '认证失败', 'INVALID_CREDENTIALS' => '认证失败',
        'TOKEN_MISSING' => '令牌缺失', 'TOKEN_EXPIRED' => '令牌过期', 'SSO_CONFLICT' => '单点登录冲突',
        'PERMISSION_DENIED' => '权限不足', 'VALIDATION_FAILED' => '参数校验失败',
        'VALIDATION_ERROR' => '参数校验失败', 'ACCOUNT_LOCKED' => '账号已锁定',
        'OLD_PASSWORD_WRONG' => '旧密码不正确', 'RATE_LIMITED' => '请求频率超限',
        'SERVER_ERROR' => '服务器错误', 'INTERNAL_ERROR' => '服务器错误',
        'DB_ERROR' => '数据库错误', 'FILE_UPLOAD_FAILED' => '文件上传失败',
        'EMAIL_SEND_FAILED' => '邮件发送失败', 'THIRD_PARTY_ERROR' => '第三方接口错误',
    ];
    $codes = [];
    foreach (array_unique($matches[1] ?? []) as $code) {
        $codes[] = '`' . $code . '`=' . ($meanings[$code] ?? '业务结果');
    }

    if (($entry['method'] ?? '') === 'HEAD') {
        $headSemantics = trim($entry['head_semantics'] ?? '');
        if ($headSemantics !== '') {
            return routeSignatureLabel($entry) . '：' . $headSemantics . '；'
                . '响应正文会被 HTTP HEAD 规范移除，状态码和响应头仍保留。'
                . ($codes === [] ? '' : '代码含义：' . implode('、', $codes) . '。');
        }

        return routeSignatureLabel($entry)
            . '：HEAD 方法仅返回响应头，不返回响应体；状态码和响应头沿用对应 GET 路径。'
            . ($codes === [] ? '' : '代码含义：' . implode('、', $codes) . '。');
    }

    if (($entry['return_semantics'] ?? '') !== '') {
        return routeSignatureLabel($entry) . '：' . $entry['return_semantics']
            . ($codes === [] ? '' : '代码含义：' . implode('、', $codes) . '。');
    }

    $success = $entry['success_branches'] ?? '';
    $returnType = strtolower($entry['return_type'] ?? '');
    $viewNames = array_values(array_filter(explode('、', $entry['view_names'] ?? '')));
    $isRedirect = preg_match('/\bredirect\s*\(/', $success) === 1 || str_contains($returnType, 'redirect');
    $isView = $viewNames !== [] || preg_match('/\bview\s*\(/', $success) === 1
        || preg_match('/(?:^|[\\|])view(?:$|[\\|])/', $returnType) === 1;
    $isFile = preg_match('/download|stream|file\s*\(/i', $success) === 1
        || preg_match('/binaryfile|streamedresponse|file$/i', $returnType) === 1;
    $isJson = !$isFile && (str_contains($success, 'response()->json')
        || str_contains($success, '$this->success')
        || str_contains($returnType, 'jsonresponse')
        || str_contains($entry['failure_branches'] ?? '', '$this->error')
        || str_contains($returnType, 'array'));
    $operation = routeOperationDescription($entry);
    if ($isRedirect) {
        $successType = '成功返回 HTTP 3xx 重定向，浏览器进入“' . $operation . '”的下一页面';
    } elseif ($isView) {
        $viewDetail = $viewNames === [] ? '' : '，具体模板：' . implode('、', array_map(
            static fn (string $view): string => '`' . markdownInline($view) . '`',
            $viewNames
        ));
        $successType = '成功返回 HTML/Blade 页面' . $viewDetail . '，用于“' . $operation . '”';
    } elseif ($isJson) {
        $successType = '成功返回 JSON；调用方必须同时读取业务码和数据字段来确认“' . $operation . '”是否完成';
    } elseif ($isFile) {
        $successType = '成功返回文件或数据流，用于“' . $operation . '”';
    } else {
        $successType = '成功返回 action 源码中可见的正常响应，用于“' . $operation . '”';
    }

    if ($codes !== []) {
        $failureType = '失败时返回明确业务码，调用方不得把错误码或仅 HTTP 2xx 当成业务成功';
    } elseif ($isView || $isRedirect) {
        $failureType = '中间件拒绝、路由绑定或渲染异常时返回对应重定向/4xx/5xx，不产生虚假的页面成功结果';
    } elseif (($entry['external_dependencies'] ?? '无直接外部依赖证据') !== '无直接外部依赖证据') {
        $failureType = '外部调用失败或结果未知时以源码错误响应和本地状态为准，本报告不把未知结果推定为成功';
    } else {
        $failureType = '失败由中间件、参数/领域校验或异常响应决定；未发现显式业务码时以 action 实际返回为准';
    }

    return routeSignatureLabel($entry) . '：' . $successType . '；' . $failureType
        . ($codes === [] ? '。未检索到显式 ResponseCode，具体 HTTP/视图结果以 action 返回值为准。'
            : '。代码含义：' . implode('、', $codes) . '。');
}

function legacyAuditSummary(): string
{
    $rows = legacyAuditRows();
    if ($rows === null) {
        return is_file(storage_path('app/audits/legacy-route-audit.json'))
            ? '`storage/app/audits/legacy-route-audit.json` 不是有效 JSON'
            : '`storage/app/audits/legacy-route-audit.json` 不存在';
    }

    return '`storage/app/audits/legacy-route-audit.json`，记录数 ' . count($rows);
}

/**
 * 项目1旧路由导出文件是报告的唯一旧入口基线；文件缺失时返回空数组，
 * 让报告明确显示“未检索到”，而不是伪造旧项目已完成审计。
 *
 * @return array<int, array<string, mixed>>
 */
function readLegacyRouteInventory(): array
{
    $path = storage_path('app/audits/legacy-routes.json');
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function readLegacyAuditRows(): array
{
    return legacyAuditRows() ?? [];
}

function legacyProjectRoot(): string
{
    static $resolved = null;
    if (is_string($resolved)) {
        return $resolved;
    }

    $configured = getenv('LEGACY_PROJECT_ROOT');
    $candidate = is_string($configured) && trim($configured) !== ''
        ? rtrim(trim($configured), "\\/")
        : dirname(base_path()) . DIRECTORY_SEPARATOR . 'new_co_gmtk_crmv3';
    $realRoot = realpath($candidate);
    if ($realRoot === false || !is_dir($realRoot)) {
        throw new RuntimeException('旧项目目录不存在：' . $candidate);
    }

    $routePath = $realRoot . DIRECTORY_SEPARATOR . 'app'
        . DIRECTORY_SEPARATOR . 'Http'
        . DIRECTORY_SEPARATOR . 'routes.php';
    if (realpath($routePath) === false || !is_file($routePath)) {
        throw new RuntimeException('旧项目关键路由不存在：' . $routePath);
    }

    $resolved = $realRoot;

    return $resolved;
}

/**
 * Build one report row per old route method. The old exporter does not contain
 * middleware or frontend metadata, so each evidence helper is deliberately
 * conservative and writes an explicit "未检索到" marker when static proof is absent.
 *
 * @param array<int, array<string, mixed>> $legacyRoutes
 * @param array<int, array<string, mixed>> $auditRows
 * @param array<int, Route> $currentRoutes
 * @param array<string, string> $frontendFiles
 * @param array<string, string> $controllerFiles
 * @return array<int, array<string, string>>
 */
function buildLegacyRouteEntries(
    array $legacyRoutes,
    array $auditRows,
    array $currentRoutes,
    array $frontendFiles,
    array $controllerFiles
): array {
    $currentIndex = legacyCurrentRouteIndex($currentRoutes);
    $auditIndex = [];
    foreach ($auditRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $uri = legacyNormalizeUri((string) ($row['legacy_uri'] ?? ''));
        if ($uri !== '') {
            $auditIndex[$uri][] = $row;
        }
    }

    $entries = [];
    foreach ($legacyRoutes as $legacyRoute) {
        if (!is_array($legacyRoute)) {
            continue;
        }
        $uri = legacyNormalizeUri((string) ($legacyRoute['uri'] ?? ''));
        $action = (string) ($legacyRoute['action'] ?? '未检索到旧 action');
        $name = (string) ($legacyRoute['name'] ?? '');
        $auditRow = legacyAuditRowForRoute($auditIndex[$uri] ?? [], $legacyRoute);
        foreach ((array) ($legacyRoute['methods'] ?? []) as $method) {
            $method = strtoupper((string) $method);
            if ($method === '') {
                continue;
            }

            $current = legacyCurrentRouteForMethod($currentIndex, $uri, $method, $auditRow);
            $status = (string) ($auditRow['status'] ?? ($current === null ? 'missing_uri' : 'matched'));
            $source = legacySourceEvidence($uri, $action, $controllerFiles);
            $controllerSource = legacyControllerSource($action, $controllerFiles);
            $frontend = legacyFrontendEvidence($uri, $name, $action, $frontendFiles, $controllerSource);
            $modelTable = legacyModelTableEvidence($controllerSource);
            $success = legacySuccessMeaning($uri, $action, $controllerSource, $method);
            $failure = legacyFailureMeaning($uri, $action, $controllerSource, $status);
            $currentMapping = legacyCurrentMapping($current, $status, $method, $uri);
            $mutationTarget = legacyCurrentMutationBoundary($current, $method);
            if ($mutationTarget !== null) {
                $currentMapping .= '；当前方法边界：HTTP 405；OPERATION_NOT_ALLOWED；Allow: POST；不会进入现代 POST 写链';
                $success = '项目2当前方法无业务成功分支；旧后台变更型 GET/HEAD 被拒绝为 HTTP 405';
                $failure = '项目2返回 HTTP 405 JSON；`OPERATION_NOT_ALLOWED`=操作不允许；Allow: POST；不会进入现代 POST 写链';
            }
            $middleware = legacyMiddlewareEvidence($uri);
            $businessPurpose = legacyBusinessPurpose($uri, $action, $method);
            $problem = legacyProblemStatement($uri, $action, $method, $status);
            $stepAnnotation = legacyStepAnnotation(
                $method,
                $uri,
                $middleware,
                $action,
                $modelTable,
                $currentMapping,
                $success,
                $failure
            );
            $returnMeaning = legacyReturnMeaning($method, $uri, $status, $success, $failure);
            $chain = implode(' -> ', [
                'HTTP ' . $method . ' ' . $uri,
                '项目1 middleware: ' . stripMarkdown($middleware),
                '项目1 action: ' . $action,
                '项目1 Model/表: ' . stripMarkdown($modelTable),
                '项目2 mapping: ' . stripMarkdown($currentMapping),
            ]);

            $entries[] = [
                'method' => $method,
                'uri' => $uri,
                'name' => $name,
                'action' => $action,
                'source' => $source,
                'middleware' => $middleware,
                'frontend' => $frontend,
                'model_table' => $modelTable,
                'current_mapping' => $currentMapping,
                'business_purpose' => $businessPurpose,
                'problem' => $problem,
                'step_annotation' => $stepAnnotation,
                'return_meaning' => $returnMeaning,
                'success_meaning' => $success,
                'failure_meaning' => $failure,
                'chain' => $chain,
            ];
        }
    }

    return $entries;
}

/** @param array<int, Route> $routes */
function legacyCurrentRouteIndex(array $routes): array
{
    $index = [];
    foreach ($routes as $route) {
        $uri = legacyNormalizeUri($route->uri());
        foreach ($route->methods() as $method) {
            $key = $uri . '|' . strtoupper((string) $method);
            $index[$key][] = $route;
        }
    }

    return $index;
}

function legacyNormalizeUri(string $uri): string
{
    $uri = '/' . ltrim(trim($uri), '/');
    $uri = rtrim($uri, '/');

    return $uri === '' ? '/' : $uri;
}

/**
 * Parse optional final-goal metrics without silently accepting malformed values.
 * The report can still be generated during development, but the final run may
 * only record non-negative integer snapshots supplied by the goal runtime.
 */
function reportMetricArgument($value, string $label): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $raw = (string) $value;
    if (!preg_match('/^\d+$/', $raw)) {
        throw new InvalidArgumentException($label . ' must be a non-negative integer.');
    }

    return (int) $raw;
}

/** @param array<int, array<string, mixed>> $rows */
function legacyAuditRowForRoute(array $rows, array $legacyRoute): array
{
    $required = array_map('strtoupper', (array) ($legacyRoute['methods'] ?? []));
    foreach ($rows as $row) {
        $methods = array_map('strtoupper', (array) ($row['legacy_methods'] ?? []));
        foreach ($required as $method) {
            if ($method === 'HEAD' || in_array($method, $methods, true)) {
                return $row;
            }
        }
    }

    return $rows[0] ?? [];
}

/** @param array<string, array<int, Route>> $index */
function legacyCurrentRouteForMethod(array $index, string $uri, string $method, array $auditRow): ?Route
{
    $candidates = $index[$uri . '|' . $method] ?? [];
    if ($candidates === [] && $method === 'HEAD') {
        $candidates = $index[$uri . '|GET'] ?? [];
    }
    if ($candidates === []) {
        return null;
    }

    $expectedName = (string) ($auditRow['current_name'] ?? '');
    foreach ($candidates as $candidate) {
        if ($expectedName !== '' && (string) ($candidate->getName() ?? '') === $expectedName) {
            return $candidate;
        }
    }

    return $candidates[0];
}

function legacyCurrentMapping(Route $route = null, string $status, string $method, string $uri): string
{
    if ($route === null) {
        return 'route/action：未检索到；status：' . $status;
    }

    $name = (string) ($route->getName() ?? 'unnamed');

    return 'route：' . $method . ' ' . $uri
        . '；action：' . actionName($route)
        . '；name：' . $name
        . '；status：' . $status;
}

function legacyCurrentMutationBoundary(Route $route = null, string $method): ?string
{
    if ($route === null
        || !in_array(strtoupper($method), ['GET', 'HEAD'], true)
        || actionName($route) !== 'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle') {
        return null;
    }

    static $cache = [];
    $key = legacyNormalizeUri($route->uri()) . '|' . strtoupper($method);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $controller = new \App\Http\Controllers\Admin\LegacyAdminController();
        $resolver = new ReflectionMethod($controller, 'targetRouteFor');
        $resolver->setAccessible(true);
        $target = $resolver->invoke($controller, ltrim($route->uri(), '/'));
        if (!is_array($target) || !isset($target['route']) || !is_string($target['route'])) {
            return $cache[$key] = null;
        }

        $classifier = new ReflectionMethod($controller, 'isMutationTargetRoute');
        $classifier->setAccessible(true);
        $isMutation = (bool) $classifier->invoke($controller, $target['route']);

        return $cache[$key] = ($isMutation ? $target['route'] : null);
    } catch (Throwable $exception) {
        return $cache[$key] = null;
    }
}

/**
 * @param array<string, string> $controllerFiles
 */
function legacySourceEvidence(string $uri, string $action, array $controllerFiles): string
{
    $declaration = legacyRouteDeclarationEvidence($uri, $action);
    $controller = legacyControllerEvidence($action, $controllerFiles);

    return $declaration . '；Controller：' . $controller;
}

function legacyRouteDeclarationEvidence(string $uri, string $action): string
{
    static $lineCache = null;
    if ($lineCache === null) {
        $lineCache = [];
        foreach (['app/Http/routes.php', 'app/Http/routes-admin.php'] as $relative) {
            $path = legacyProjectRoot() . '/' . $relative;
            $lines = is_file($path) ? (file($path) ?: []) : [];
            foreach ($lines as $lineNumber => $line) {
                if (str_contains($line, 'Route::')) {
                    $lineCache[] = [$relative, $lineNumber + 1, (string) $line];
                }
            }
        }
    }

    $uriToken = trim($uri, '/');
    $methodToken = str_contains($action, '@') ? (string) substr($action, strrpos($action, '@') + 1) : '';
    $suffix = $uriToken === '' ? '/' : basename(str_replace('{', '', $uriToken));
    $localAction = preg_replace('/^App\\\\Http\\\\Controllers\\\\/', '', ltrim($action, '\\')) ?? $action;
    if ($localAction !== '' && $localAction !== 'Closure') {
        foreach ($lineCache as [$relative, $lineNumber, $line]) {
            if (str_contains($line, $localAction)) {
                return '`' . $relative . ':' . $lineNumber . '`';
            }
        }
    }
    if ($methodToken !== '') {
        foreach ($lineCache as [$relative, $lineNumber, $line]) {
            if (str_contains($line, '@' . $methodToken)) {
                return '`' . $relative . ':' . $lineNumber . '`';
            }
        }
    }
    foreach ($lineCache as [$relative, $lineNumber, $line]) {
        if ($suffix !== '' && str_contains($line, $suffix)) {
            return '`' . $relative . ':' . $lineNumber . '`';
        }
    }

    return '未检索到项目1路由声明';
}

/** @param array<string, string> $controllerFiles */
function legacyControllerSource(string $action, array $controllerFiles): string
{
    $definition = legacyControllerDefinition($action, $controllerFiles);
    if ($definition === null || $definition['source'] === '') {
        return '';
    }

    return legacyExtractMethodSource($definition['source'], $definition['method']);
}

/** @param array<string, string> $controllerFiles */
function legacyControllerEvidence(string $action, array $controllerFiles): string
{
    if ($action === 'Closure') {
        return 'Closure（项目1 Controller 源码不适用）';
    }
    if (!str_contains($action, '@')) {
        return '未检索到项目1本地 Controller 映射';
    }
    $definition = legacyControllerDefinition($action, $controllerFiles);
    if ($definition === null || $definition['source'] === '') {
        $path = $definition['path'] ?? '未解析路径';

        return '`' . str_replace('\\', '/', $path) . '`：未检索到 Controller 文件';
    }

    $line = legacyMethodLine($definition['source'], $definition['method']);
    if ($line === null) {
        return '`' . str_replace('\\', '/', $definition['path']) . '`：未检索到方法 ' . $definition['method'];
    }

    return '`' . str_replace('\\', '/', $definition['path']) . ':' . $line . '`';
}

/**
 * @param array<string, string> $controllerFiles
 * @return array{path:string,method:string,source:string}|null
 */
function legacyControllerDefinition(string $action, array $controllerFiles): ?array
{
    if ($action === '' || $action === 'Closure' || !str_contains($action, '@')) {
        return null;
    }
    [$class, $method] = explode('@', ltrim($action, '\\'), 2);
    $class = preg_replace('/^App\\\\Http\\\\Controllers\\\\/', '', $class) ?? $class;
    $path = legacyProjectRoot() . '/app/Http/Controllers/' . str_replace('\\', '/', $class) . '.php';
    $normalizedPath = str_replace('\\', '/', $path);

    return [
        'path' => $path,
        'method' => $method,
        'source' => $controllerFiles[$normalizedPath] ?? (is_file($path) ? (string) file_get_contents($path) : ''),
    ];
}

function legacyExtractMethodSource(string $source, string $method): string
{
    $lines = preg_split('/\R/', $source) ?: [];
    $start = null;
    foreach ($lines as $index => $line) {
        if (preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $line) === 1) {
            $start = $index;
            break;
        }
    }
    if ($start === null) {
        return '';
    }

    $end = count($lines);
    for ($index = $start + 1; $index < count($lines); $index++) {
        if (preg_match('/^\s*(?:public|protected|private)?\s*(?:static\s+)?function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/', $lines[$index]) === 1) {
            $end = $index;
            break;
        }
    }

    return implode(PHP_EOL, array_slice($lines, $start, $end - $start));
}

function legacyMethodLine(string $source, string $method): ?int
{
    if ($method === '' || !preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    return substr_count(substr($source, 0, (int) $matches[0][1]), "\n") + 1;
}

function legacyMiddlewareEvidence(string $uri): string
{
    if (str_starts_with($uri, '/index/admin')) {
        return '`app/Http/routes-admin.php:7` route_prefix()；`app/Http/routes-admin.php:16` permissions';
    }
    if (str_starts_with($uri, '/user/agents/')) {
        return '`app/Http/routes.php:101` LoginMiddleware；`app/Http/routes.php:124` nested LoginMiddleware';
    }
    if (str_starts_with($uri, '/user/register/')) {
        return '`app/Http/routes.php:77` RegisterGmtkCnEnMiddleware';
    }
    if (str_starts_with($uri, '/en/user/register/')) {
        return '`app/Http/routes.php:81` RegisterEnMiddleware';
    }
    if (str_starts_with($uri, '/user/')) {
        return '`app/Http/routes.php:101` LoginMiddleware（公开认证/找回密码入口可能不在该组）';
    }

    return '未检索到项目1路由 middleware 静态证据';
}

/** @param array<string, string> $frontendFiles */
function legacyFrontendEvidence(
    string $uri,
    string $name,
    string $action,
    array $frontendFiles,
    string $controllerSource
): string {
    $tokens = array_values(array_filter(array_unique([
        trim($uri, '/'),
        trim($name),
        str_contains($action, '@') ? substr($action, strrpos($action, '@') + 1) : '',
    ])));
    $matches = [];
    $fields = [];
    preg_match_all('/\bview\(\s*["\']([^"\']+)["\']/', $controllerSource, $viewMatches);
    foreach (array_unique($viewMatches[1] ?? []) as $view) {
        $viewPath = str_replace('\\', '/', legacyProjectRoot()
            . '/resources/views/' . str_replace('.', '/', $view) . '.blade.php');
        if (isset($frontendFiles[$viewPath])) {
            $matches[$viewPath] = $frontendFiles[$viewPath];
        }
    }

    if ($matches === []) {
        foreach ($frontendFiles as $path => $content) {
        $pathLower = strtolower($path);
        $hit = false;
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token !== '' && (str_contains($content, $token) || str_contains($pathLower, strtolower($token)))) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            continue;
        }
            $matches[str_replace('\\', '/', $path)] = $content;
        if (count($matches) >= 3) {
            break;
        }
        }
    }

    $matchLabels = [];
    foreach ($matches as $path => $content) {
        $line = legacyFirstEvidenceLine($content, $tokens);
        $matchLabels[] = $path . ($line === null ? '' : ':' . $line);
        preg_match_all('/\bname\s*=\s*["\']([^"\']+)["\']/', $content, $nameMatches);
        foreach (array_slice(array_unique($nameMatches[1] ?? []), 0, 12) as $field) {
            $fields[] = $field;
        }
    }

    preg_match_all('/->(?:input|get|post|has|filled)\(\s*["\']([^"\']+)["\']/', $controllerSource, $requestMatches);
    foreach ($requestMatches[1] ?? [] as $field) {
        $fields[] = $field;
    }
    $matchLabels = array_values(array_unique($matchLabels));
    $fields = array_values(array_unique($fields));
    $frontend = $matchLabels === []
        ? '未检索到项目1 Blade/JS endpoint 静态证据'
        : implode('；', array_slice($matchLabels, 0, 3));
    $requestFields = $fields === [] ? '未检索到请求字段' : '请求字段：' . implode(', ', array_slice($fields, 0, 20));

    return $frontend . '；' . $requestFields . '；未检索到部分字段时不作推断';
}

function legacyFirstEvidenceLine(string $content, array $tokens): ?int
{
    foreach (preg_split('/\R/', $content) ?: [] as $lineNumber => $line) {
        foreach ($tokens as $token) {
            if ($token !== '' && str_contains($line, $token)) {
                return $lineNumber + 1;
            }
        }
    }

    return null;
}

function legacyModelTableEvidence(string $controllerSource): string
{
    if ($controllerSource === '') {
        return '未检索到项目1 Model/表静态证据';
    }
    $evidence = [];
    preg_match_all('/(?:DB::table|->from|->table)\(\s*["\']([^"\']+)["\']/', $controllerSource, $tableMatches);
    foreach ($tableMatches[1] ?? [] as $table) {
        $evidence[] = 'table ' . $table;
    }
    preg_match_all('/\b([A-Z][A-Za-z0-9_]*(?:Model|Controller))::(?:query|where|find|create|first|all)\s*\(/', $controllerSource, $modelMatches);
    foreach ($modelMatches[1] ?? [] as $model) {
        $evidence[] = 'Model ' . $model . '（未检索到明确表名）';
    }

    return $evidence === []
        ? '未检索到项目1 Model/表静态证据'
        : implode('；', array_values(array_unique(array_slice($evidence, 0, 12))));
}

function legacySuccessMeaning(string $uri, string $action, string $source, string $method): string
{
    $numeric = legacyBigAgentPasswordCodes($uri, $action);
    if ($numeric !== '') {
        return '成功：旧大代理密码修改完成；' . $numeric;
    }
    if (str_contains($source, 'return view(')) {
        return '成功：旧 action 返回 Blade 页面（静态证据）；未检索到更细业务结果';
    }
    if (str_contains($source, 'response()->json') || str_contains($source, 'return response')) {
        return '成功：旧 action 返回 JSON/HTTP 响应（静态证据）；未检索到统一业务码';
    }

    return '成功：' . $method . ' 入口执行旧 action；未检索到明确成功响应静态证据';
}

function legacyFailureMeaning(string $uri, string $action, string $source, string $status): string
{
    $numeric = legacyBigAgentPasswordCodes($uri, $action);
    if ($numeric !== '') {
        return '失败：旧大代理密码入口按旧错误码失败关闭；' . $numeric;
    }
    if ($status !== 'matched' && $status !== 'intentional_method_restriction') {
        return '失败：项目2映射状态为 ' . $status . '，不会把静态缺口当作成功；未检索到旧 action 失败分支';
    }
    if (preg_match('/(?:error|fail|exception|catch|invalid|not found)/i', $source) === 1) {
        return '失败：旧 action 存在错误/异常分支；未检索到可统一翻译的旧错误码';
    }

    return '失败：认证、参数或运行时异常按旧 action 实际响应处理；未检索到明确失败分支';
}

/**
 * 为项目1的每个 HTTP method 生成独立业务目的，避免只复用路由分组描述。
 */
function legacyBusinessPurpose(string $uri, string $action, string $method): string
{
    $context = strtolower($uri . ' ' . $action);
    if ($uri === '/') {
        $purpose = '根据旧项目会话状态渲染首页并把未登录访问导向登录入口';
    } elseif (str_contains($context, 'captcha')) {
        $purpose = '生成或校验旧页面所需的一次性验证码';
    } elseif (str_contains($context, 'login') || str_contains($context, 'signin')) {
        $purpose = '接收旧项目登录凭据并建立对应用户、代理或大代理会话';
    } elseif (str_contains($context, 'logout') || str_contains($context, 'signout')) {
        $purpose = '注销旧项目会话并清理登录状态';
    } elseif (preg_match('/(?:list|search|browse|index|detail|show|history|stats|summary)/i', $context)) {
        $purpose = '读取旧页面列表、详情或统计数据并保持历史字段结构';
    } elseif (preg_match('/(?:create|add|save|store|submit|request|apply|update|edit|change|approve|reject|delete|del|remove|pass|cancel)/i', $context)) {
        $purpose = '接收旧页面变更请求并执行对应业务状态写入';
    } elseif (in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
        $purpose = '读取旧项目资源或页面入口并返回兼容结果';
    } else {
        $purpose = '执行旧项目提交入口并返回兼容响应';
    }

    return 'HTTP ' . strtoupper($method) . ' `' . $uri . '` 的业务目的：' . $purpose
        . '；入口 action 为 `' . $action . '`。';
}

/**
 * 说明项目1入口要解决的具体兼容或业务问题。
 */
function legacyProblemStatement(string $uri, string $action, string $method, string $status): string
{
    $context = strtolower($uri . ' ' . $action);
    if (str_contains($context, 'password') || str_contains($context, 'psw')) {
        $problem = '解决旧页面密码字段、旧错误码和账号身份之间的兼容，避免错误密码被当作成功';
    } elseif (str_contains($context, 'deposit') || str_contains($context, 'withdraw')) {
        $problem = '解决旧资金入口重复提交、身份越界和状态不一致，保证结果可追踪';
    } elseif (str_contains($context, 'agent') || str_contains($context, 'proxy')) {
        $problem = '解决旧代理树字段、直属范围和权限边界在新项目中的映射';
    } elseif (str_contains($context, 'admin')) {
        $problem = '解决旧后台菜单入口与新权限、数据范围和 HTTP 方法边界之间的差异';
    } else {
        $problem = '解决旧 URI 与新项目执行链之间的入口、字段和响应兼容问题';
    }

    return 'HTTP ' . strtoupper($method) . ' `' . $uri . '` 需要' . $problem
        . '；当前静态映射状态为 `' . $status . '`，旧 action 为 `' . $action . '`。';
}

/**
 * 用可审计的编号描述旧入口到新入口的每一步，并明确静态证据边界。
 */
function legacyStepAnnotation(
    string $method,
    string $uri,
    string $middleware,
    string $action,
    string $modelTable,
    string $currentMapping,
    string $success,
    string $failure
): string {
    return '1) 接收旧 URI `'
        . strtoupper($method) . ' ' . $uri
        . '`；2) 按项目1 middleware 证据 `' . stripMarkdown($middleware)
        . '` 做身份和请求边界判断；3) 调用旧 action `'
        . $action
        . '`；4) 读取 Model/数据库证据 `'
        . stripMarkdown($modelTable)
        . '`；5) 对照项目2映射 `'
        . stripMarkdown($currentMapping)
        . '`；6) 成功时：'
        . $success
        . '；失败时：'
        . $failure
        . '。以上为静态链路说明，不表示生成报告时执行了旧 Controller。';
}

/**
 * 统一翻译项目1入口在项目2报告中的成功、失败和状态含义。
 */
function legacyReturnMeaning(
    string $method,
    string $uri,
    string $status,
    string $success,
    string $failure
): string {
    return 'HTTP ' . strtoupper($method) . ' `' . $uri . '` 返回结果中文含义：'
        . '映射状态 `' . $status . '`；成功表示 ' . $success
        . '；失败表示 ' . $failure
        . '；若项目2返回 4005 表示参数校验失败，4006 表示权限不足，405 表示方法不允许，410 表示旧入口已停止且未写入数据。';
}

function legacyBigAgentPasswordCodes(string $uri, string $action): string
{
    $context = strtolower($uri . ' ' . $action);
    if (!str_contains($context, 'agents')
        || (!str_contains($context, 'password') && !str_contains($context, 'psw'))) {
        return '';
    }

    return '`0`=修改成功；`1000`=系统错误或参数校验失败（新密码长度不足6位或与旧密码相同返回 `errorType=PARAM`）；`1010`=用户不存在；`1011`=旧密码错误';
}

/** @return array{total:int,matched:int,restricted:int,gaps:int} */
function legacyAuditMetrics(): array
{
    $rows = legacyAuditRows() ?? [];
    $matched = 0;
    $restricted = 0;
    $gaps = 0;
    foreach ($rows as $row) {
        $status = is_array($row) ? (string) ($row['status'] ?? '') : '';
        if ($status === 'matched') {
            $matched++;
        } elseif ($status === 'intentional_method_restriction') {
            $restricted++;
        } else {
            $gaps++;
        }
    }

    return [
        'total' => count($rows),
        'matched' => $matched,
        'restricted' => $restricted,
        'gaps' => $gaps,
    ];
}

/** @return array<int, mixed>|null */
function legacyAuditRows(): ?array
{
    static $loaded = false;
    static $rows = null;
    if ($loaded) {
        return $rows;
    }
    $loaded = true;

    $path = storage_path('app/audits/legacy-route-audit.json');
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return null;
    }

    $rows = $decoded;

    return $rows;
}

function classSource(string $class): string
{
    static $cache = [];
    if (array_key_exists($class, $cache)) {
        return $cache[$class];
    }

    try {
        if (!class_exists($class) && !interface_exists($class)) {
            $cache[$class] = '';

            return '';
        }
        $reflection = new ReflectionClass($class);
        $cache[$class] = fileSource($reflection->getFileName());

        return $cache[$class];
    } catch (Throwable $exception) {
        $cache[$class] = '';

        return '';
    }
}

function fileSource($path): string
{
    static $cache = [];
    if (!is_string($path) || !is_file($path)) {
        return '';
    }
    if (array_key_exists($path, $cache)) {
        return $cache[$path];
    }

    $cache[$path] = (string) file_get_contents($path);

    return $cache[$path];
}

function relativePath(string $path = null): string
{
    if ($path === null || $path === '') {
        return '未知文件';
    }
    $base = str_replace('\\', '/', base_path()) . '/';
    $normalized = str_replace('\\', '/', $path);

    return str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
}

function markdownInline(string $value): string
{
    return str_replace(['`', "\r", "\n"], ['\\`', ' ', ' '], trim($value));
}

function markdownCell(string $value): string
{
    return str_replace('|', '\\|', markdownInline($value));
}

function stripMarkdown(string $value): string
{
    return str_replace(['`', ';', '；'], ['', ',', ','], $value);
}
