# Admin Withdraw Status Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 关闭旧 `WithdrawStatusController` 的 12 条待人工核验记录，使待处理、处理中、已完成、失败四个专属状态页的搜索、导出、权限、数据范围和 Layui/CrmUI 交互形成固定状态且不可绕过的闭环。

**Architecture:** 继续以 `withdraw_records`、`WithdrawRecordQueryService`、`AdminDataScopeService` 和现代导出控制器为唯一事实源。旧状态搜索只负责把 `userId/withdraw_id/withdraw_source/withdraw_startdate/withdraw_enddate` 归一为现代字段、强制覆盖对应状态并返回旧 V2 envelope；旧状态导出同样强制状态后复用现代安全 CSV。专属页面把状态渲染为不可编辑固定筛选，现代汇总页仍保留可切换的全状态筛选。

**Tech Stack:** Laravel 8.83、PHP 7.4+、MySQL 3307 隔离测试库 `co_crmv5_test`、PHPUnit 9.5、Blade、Layui、CrmUI、jQuery/原生 JavaScript、原生 CSS。

**Safety boundary:** 旧库 `hank_zl_data` 永久只读，正式库 `co_crmv5` 禁写，只有 PHPUnit 可写 `co_crmv5_test`；MT4 永久禁用。日期筛选改用 `withdraw_records.created_at` 是对旧 `MT4_TRADES.CLOSE_TIME` 的本地事实源收敛。目录不是 Git 仓库，不初始化 Git、不创建 worktree、不写 commit 记录；每个 RED/GREEN 输出就是检查点。

---

### Task 1: 锁定 12 条路由与权限映射

**Files:**
- Create: `tests/Feature/AdminLegacyWithdrawStatusPermissionClosureModuleTest.php`
- Modify: `tests/Feature/AdminLegacyRouteSemanticClosureTest.php`
- Test: `routes/legacy_admin.php`
- Test: `app/Http/Middleware/LegacyAdminAuthenticate.php`
- Test: `app/Http/Controllers/Admin/LegacyAdminController.php`

- [x] **Step 1: 写 12 条方法级路由与权限 RED**

逐条断言四个 GET 页面与八个 POST 搜索/导出路由的 URI、方法、action、`legacy.admin.auth` 和 `legacy_permission_route`。页面与搜索必须绑定 `admin_api_withdrawList`，导出必须绑定 `admin_api_exportWithdrawals`；匿名请求不能进入控制器。

```php
$route = Route::getRoutes()->getByName('legacy_admin_5269b1fad284b3ad');
$this->assertSame('index/admin/withdraw/pendingSearch', $route->uri());
$this->assertContains('POST', $route->methods());
$this->assertSame('admin_api_withdrawList', $route->defaults['legacy_permission_route'] ?? null);
```

- [x] **Step 2: 运行 RED/基线**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawStatusPermissionClosureModuleTest.php tests\Feature\AdminLegacyRouteSemanticClosureTest.php
```

Expected: 新测试在文件不存在或缺少 12 条精确断言时 RED；若运行时映射已经全部正确，可直接 GREEN，禁止为了制造失败修改权限逻辑。

- [x] **Step 3: 仅在真实偏差存在时修复 URI 权限映射**

保持 `permissionRouteForLegacyUri()` 的事实来源：Search/Page 使用 `admin_api_withdrawList`，Export 使用 `admin_api_exportWithdrawals`。不得让请求中的 `status`、`withdraw_source` 或 `data` 决定权限名。

- [x] **Step 4: 运行 GREEN**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawStatusPermissionClosureModuleTest.php tests\Feature\AdminLegacyRouteSemanticClosureTest.php
```

Expected: 12 条方法级映射全部通过；普通角色缺少 list/export 对应权限时返回 403，超级管理员路径不作为普通权限证明。

### Task 2: 实现四个固定状态 V2 搜索

**Files:**
- Create: `tests/Feature/AdminLegacyWithdrawStatusSearchClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Reuse: `app/Services/WithdrawRecordQueryService.php`

- [x] **Step 1: 写旧字段、固定状态、日期、分页和 envelope RED**

为 0/1/2/3 各种一条记录，对四个 Search 逐条提交旧字段；同时传入相反的根级和嵌套状态，证明 URI 固定状态优先。断言 V2 根级结构固定为 `code/msg/count/data/totalRow`，行包含 `mt4_ticket/userId/username/bank_no/bank_no_info/applyamount/actapplyamount/drawrate/actdraw/drawpoundage/applystatus/apply_remark/rec_crt_date`。

```php
$response = $this->actingAs($admin, 'admin')->postJson(
    '/index/admin/withdraw/pendingSearch',
    [
        'userId' => $userId,
        'withdraw_id' => 'STATUS-MT4-1001',
        'withdraw_source' => '3',
        'status' => '2',
        'withdraw_startdate' => '2026-08-01',
        'withdraw_enddate' => '2026-08-31',
        'page' => 1,
        'limit' => 20,
    ]
);

$response->assertOk()
    ->assertJsonPath('code', 200)
    ->assertJsonPath('count', 1)
    ->assertJsonPath('data.0.mt4_ticket', 'STATUS-MT4-1001')
    ->assertJsonPath('data.0.applystatus', 0);
```

- [x] **Step 2: 写数据范围、空结果、非法输入和大额汇总 RED**

受限管理员只能看到授权用户，`count` 和 `totalRow` 不能包含范围外金额；无日期默认 `2024-01-01` 至当天；空结果为 `data=[]/totalRow=[]`；非法日期、倒置日期、非法用户、非法分页返回 `VALIDATION_FAILED`。用 `99999999999999.99` 级别 DECIMAL 值锁定定点汇总，禁止 float。

- [x] **Step 3: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawStatusSearchClosureModuleTest.php
```

Expected: FAIL；当前实现返回现代 paginator envelope，`withdraw_id` 还会错误映射为 `local_order_no`。

- [x] **Step 4: 增加状态 URI 分发并复用旧 V2 查询适配器**

在通用 `targetRouteFor()` 转发前识别四个 Search，按 URI 映射 0/1/2/3。扩展 `forwardLegacyWithdrawApplySearch()` 接受可选强制状态，并让状态 Search 始终走 V2 response；强制状态必须在读取请求后覆盖。

```php
$statusSearches = [
    'index/admin/withdraw/pendingSearch' => 0,
    'index/admin/withdraw/processingSearch' => 1,
    'index/admin/withdraw/completedSearch' => 2,
    'index/admin/withdraw/failedSearch' => 3,
];

if (array_key_exists($legacyUri, $statusSearches)) {
    return $this->forwardLegacyWithdrawApplySearch($request, $legacyUri, $statusSearches[$legacyUri]);
}
```

分页兼容顺序为 `rows -> limit -> per_page -> 15`；`withdraw_id` 精确映射 `mt4_ticket`，不是 `local_order_no`。查询、scope、金额格式和总计继续复用 `WithdrawRecordQueryService` 与现有 format helper。

- [x] **Step 5: 运行 GREEN 与出金申请回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawStatusSearchClosureModuleTest.php tests\Feature\AdminLegacyWithdrawSearchParityClosureModuleTest.php tests\Feature\AdminLegacyAmountWithdrawClosureModuleTest.php
```

Expected: 四个固定状态接口均为旧 V2 envelope，状态不可覆盖，既有 amount V1/V2 行为不变。

### Task 3: 实现四个固定状态安全导出

**Files:**
- Create: `tests/Feature/AdminLegacyWithdrawStatusExportClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Reuse: `app/Http/Controllers/Admin/LegacyAdminExportController.php`
- Reuse: `app/Services/WithdrawRecordQueryService.php`

- [x] **Step 1: 写旧平面字段和固定状态 CSV RED**

四个 Export 分别提交 `userId/withdraw_id/withdraw_source/withdraw_startdate/withdraw_enddate`，并用相反状态尝试覆盖。解析 CSV 后只允许出现 URI 对应状态和指定 MT4 ticket；不得把 `withdraw_id` 当作本地订单号。

- [x] **Step 2: 写 scope、非法输入、空结果和公式注入 RED**

受限管理员导出不能包含范围外用户；非法日期返回 JSON 校验错误而不是 CSV；空结果只保留表头；以 `= + - @ TAB CR LF` 开头的字符串单元格必须加单引号，金额保持纯数值字符串。

- [x] **Step 3: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawStatusExportClosureModuleTest.php
```

Expected: FAIL；当前通用 payload 未把旧 `withdraw_id` 映射到 `mt4_ticket`，也没有状态专项输入契约。

- [x] **Step 4: 增加固定状态导出适配器**

四个 Export 在旧控制器内归一为现代字段并强制状态，再直接复用 `LegacyAdminExportController::exportWithdrawals()`。日期空值补 `2024-01-01` 和当天；不要复制 CSV 生成、scope 或公式防护逻辑。

```php
$request->merge([
    'user_id' => $request->input('userId', $request->input('user_id')),
    'mt4_ticket' => $request->input('withdraw_id', $request->input('mt4_ticket')),
    'status' => $forcedStatus,
    'start_date' => $request->input('withdraw_startdate', $request->input('start_date', '2024-01-01')),
    'end_date' => $request->input('withdraw_enddate', $request->input('end_date', date('Y-m-d'))),
]);

return app(LegacyAdminExportController::class)->exportWithdrawals($request);
```

- [x] **Step 5: 运行 GREEN 与导出安全回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawStatusExportClosureModuleTest.php tests\Feature\AdminLegacyWithdrawExportDownloadClosureModuleTest.php tests\Feature\AdminLegacyExportDateFilterClosureModuleTest.php
```

Expected: 四个状态导出只含授权范围内的固定状态记录，既有现代/两阶段旧导出保持通过。

### Task 4: 锁定 Layui 与 CrmUI 专属状态页

**Files:**
- Create: `tests/Feature/AdminWithdrawStatusUiClosureModuleTest.php`
- Modify: `resources/admin/layui/withdrawals/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`
- Modify: `resources/admin/crmui/partials/module-page.blade.php` only if hidden filter cannot express the contract
- Modify: `public/js/apps/crmui/admin.js` only if the existing form reset/export path can override a hidden fixed filter

- [x] **Step 1: 写 Layui 初载、搜索、重置、导出锁定 RED**

四个旧 GET 页面和四个 `/admin/withdraw/{state}` 页面必须输出固定状态隐藏字段或不可编辑控件；初次 `table.render` 的 `where`、搜索、reset 和导出都携带固定状态。`/admin/withdrawals` 汇总页仍输出全状态 select。

- [x] **Step 2: 写 CrmUI 固定状态 RED**

四个 `/admin-crmui/withdraw/{state}` 页面不得输出可切换的状态 select，必须输出固定 `status` 隐藏筛选；`/admin-crmui/withdrawals` 仍保留 select。CrmUI 的 load、reset 和 export 都从同一筛选表单取值，隐藏值不可被 query string 覆盖。

- [x] **Step 3: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminWithdrawStatusUiClosureModuleTest.php tests\Feature\LegacyUiReplacementCoverageTest.php
```

Expected: FAIL；当前 Layui 首次加载未发送默认状态，Layui/CrmUI 专属页均允许切换状态。

- [x] **Step 4: 实现两套 UI 的固定筛选**

Layui 在有 `defaultStatus` 时输出 `type=hidden name=status`，并通过页面 marker 把初始 `where.status` 注入表格；没有默认状态时继续渲染全状态 select。CrmUI 的 `withdrawalPage($status)` 在 `$status !== null` 时把 status 声明为 hidden field，并在 `definitionWithRequestDefaults()` 后重新固定，避免 URL `?status=` 覆盖；汇总页不变。

- [x] **Step 5: 运行 GREEN、语法和 Visual C 回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminWithdrawStatusUiClosureModuleTest.php tests\Feature\LegacyUiReplacementCoverageTest.php tests\Feature\AdminWithdrawalsUiClosureModuleTest.php tests\Feature\GlobalCrmThemeCoverageTest.php tests\Feature\UnifiedBladeDesignSystemTest.php tests\Feature\VisualCFoundationContractTest.php
node --check public\js\apps\admin\layui\pages.js
node --check public\js\apps\crmui\admin.js
php artisan view:cache
php artisan view:clear
```

Expected: 双 UI 状态专属页锁定，汇总页仍可筛选；无跨 UI 资产、Blade 编译或 JavaScript 语法回归。

### Task 5: 专项复审、回归和 12 条证据

**Files:**
- Create: `tests/Unit/AdminWithdrawStatusMatrixClosureTest.php`
- Modify: `docs/audits/旧项目路由核验证据.json`
- Regenerate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Regenerate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Modify: `docs/项目整体进度梳理-2026-08-17.md`
- Modify: `docs/superpowers/plans/2026-08-17-admin-withdraw-status-parity.md`

- [x] **Step 1: 规格复审**

由独立审查代理逐条对照 12 条旧 method+URI，核查旧行为、路由、后端、前端、权限/范围、校验/错误、测试七个维度。任一 Critical/Important 必须回到实现代理修复并重新复审。

- [x] **Step 2: 质量复审**

规格通过后由另一个独立代理检查：固定状态不可被根级/嵌套/query string 覆盖、DECIMAL 无 float、CSV 防护、scope 汇总、空结果、双 UI reset/export、无重复查询/导出逻辑。Critical/Important 清零后才能写 evidence。

- [x] **Step 3: 运行完整出金专项回归**

```powershell
vendor\bin\phpunit --colors=never --filter "(?i)withdraw" tests\Feature
```

Expected: exit 0，零 Failure/零 Error；记录实际 tests/assertions/time，不沿用旧计数。

- [x] **Step 4: 写 12 条独立七维证据并新增矩阵门禁**

证据组固定为 `admin_withdraw_status_business_2026_08_17`。每个 GET/Search/Export 都必须有独立 `legacy_method + legacy_uri` 和实际通过的测试名，不用一条批量描述代替 12 条方法级证据。

```php
private const EXPECTED_ROUTE_KEYS = [
    'GET index/admin/withdraw/pending',
    'POST index/admin/withdraw/pendingSearch',
    'POST index/admin/withdraw/pendingExport',
    'GET index/admin/withdraw/processing',
    'POST index/admin/withdraw/processingSearch',
    'POST index/admin/withdraw/processingExport',
    'GET index/admin/withdraw/completed',
    'POST index/admin/withdraw/completedSearch',
    'POST index/admin/withdraw/completedExport',
    'GET index/admin/withdraw/failed',
    'POST index/admin/withdraw/failedSearch',
    'POST index/admin/withdraw/failedExport',
];
```

- [x] **Step 5: 重新生成矩阵并验证计数**

```powershell
php scripts\generate-legacy-implementation-matrix.php
vendor\bin\phpunit --colors=never tests\Unit\AdminWithdrawStatusMatrixClosureTest.php tests\Unit\AdminWithdrawAmountMatrixClosureTest.php tests\Unit\Phase2IdentityOrganizationMatrixClosureTest.php tests\Unit\FrontBigNumberBusinessMatrixClosureTest.php
```

Expected: 在前台 BigNumber 两个 Important 已关闭且原 10 条证据仍有效的前提下，矩阵为 `legacy_route_methods=475 / verified=406 / needs_manual_business_review=69 / unresolved_legacy_source=0 / unmatched_current_route=0`。

- [x] **Step 6: 更新总进度并进入下一批**

把新鲜回归、复审结论、矩阵时间和残余风险写入总进度文档。随后从剩余 69 条选择风险最高且边界清晰的下一控制器，先完成旧源码/新实现/现有测试三方只读审计，再创建下一份专项计划；不得把本批完成写成全项目完成。

### Task 5 实施结果（2026-08-18 16:46）

- 首次在无代理测试进程的干净状态下重跑完整出金 Feature：`615 tests / 5977 assertions / exit 0 / 05:51.180`；此前一次 `AUTO_INCREMENT` 指纹变化未复现，因此未放宽夹具门禁。
- 规格复审发现 1 个 Important：状态 Export 接受 `user_id<=0`。新增四个 Export × 旧/现代别名 8 个 RED 数据集，确认 8 failures 后，将直接导出与两阶段导出的 `user_id` 统一收紧为 `integer|min:1`。
- 修复后状态导出为 `31 tests / 141 assertions`，两阶段下载为 `7 / 89`，日期回归为 `2 / 6`；最终完整出金 Feature 为 `623 tests / 6001 assertions / exit 0 / 04:59.786`。
- 最终独立规格复审：`SPEC APPROVED`。独立质量复审：`QUALITY APPROVED`，Critical/Important 为 0；其“未运行测试/两阶段路径”静态残余风险由上述实际回归覆盖。
- 新增 `AdminWithdrawStatusMatrixClosureTest`，12 条方法级路由各有固定证据组、七维独立说明和方法级测试名；门禁为 `2 tests / 347 assertions`。
- 迁移矩阵于 `2026-08-18T16:43:11+08:00` 重新生成：`475 / 406 verified / 69 needs_manual_business_review / 0 unresolved / 0 unmatched`。
- 浏览器四视口验收仍受 `BLOCKED_BY_BROWSER_POLICY` 限制，本轮没有绕过策略，也未声明浏览器运行时通过。
- 下一批选择剩余单控制器中条数最多且风险边界清晰的 `FengXianManageController` 9 条；三方只读审计已确认现有 profit Search 被错误弱化为按品种 `tradeSummary`，V1/V2 envelope 与专属页面模式也需专项锁定。
