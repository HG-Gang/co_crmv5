# Admin Withdraw Amount Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 关闭旧 `WithdrawAmountController` 的 19 条待人工核验记录，使出金查询、详情、状态、导出、下载、OTC 失败关闭及 Layui/CrmUI 交互在新项目中形成安全、可测试、可审计的业务闭环。

**Architecture:** 继续使用 `withdraw_records`、现代出金状态机、`AdminDataScopeService` 和既有权限体系作为唯一事实源。新增一个小型查询服务统一现代列表、旧 V1/V2、导出三条读取链路；旧适配器只负责字段/envelope 兼容，不能绕过现代权限、数据范围或状态机。未接入可信 OTC 协议前保持 POST 验签失败关闭，旧 `Route::any` 的四种非 POST 方法明确 405；Visual C 仅扩展现有 Layui/CrmUI 页面钩子和 CSS/JavaScript 交互。

**Tech Stack:** PHP 7.4+/Laravel 8.83、MySQL 3307 隔离测试库 `co_crmv5_test`、PHPUnit 9.5、Blade、Layui、CrmUI、jQuery/原生 JavaScript、原生 CSS。

**Safety boundary:** 旧库 `hank_zl_data` 永久只读，正式新库 `co_crmv5` 禁写；只有 PHPUnit 可写 `co_crmv5_test`。MT4 与未配置 OTC 协议始终失败关闭。本目录没有 `.git`，本计划不初始化仓库、不创建 worktree、不写虚假 commit 记录，以每个 RED/GREEN 测试输出作为检查点。

---

### Task 1: 锁定状态机权限与 OTC 失败关闭

**Files:**
- Modify: `tests/Feature/AdminLegacyAmountWithdrawClosureModuleTest.php`
- Modify: `tests/Feature/AdminLegacyRouteSemanticClosureTest.php`
- Create: `tests/Feature/AdminLegacyWithdrawPermissionClosureModuleTest.php`
- Modify: `app/Http/Middleware/LegacyAdminAuthenticate.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`

- [x] **Step 1: 写 `order_status_OTC` id-only 载荷的失败测试**

把现有“`id` 自动 complete”测试拆开：普通 `order_status` 必须显式带 `orderStatus=2`；OTC 入口即使收到 `id` 也只能返回 `THIRD_PARTY_ERROR/OTCERR`，记录状态、funding 状态和 outbox 数量不变。

```php
$this->actingAs($admin, 'admin')
    ->postJson('/index/admin/amount/order_status_OTC', ['id' => $record->id])
    ->assertOk()
    ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

$this->assertSame(1, (int) DB::table('withdraw_records')->where('id', $record->id)->value('status'));
```

- [x] **Step 2: 运行 RED，确认测试因当前 id-only 快捷完成分支失败**

```powershell
vendor\bin\phpunit --colors=never --filter "legacy_order_status_otc" tests\Feature\AdminLegacyAmountWithdrawClosureModuleTest.php
```

Expected: FAIL，数据库中状态实际从 1 变为 2；不得因数据库名或 fixture 错误失败。

- [x] **Step 3: 写动态权限 RED**

构造只具 `admin_api_withdrawProcess`、只具 `admin_api_withdrawComplete`、只具 `admin_api_withdrawReject` 的三个角色，分别请求 `orderStatus=1/2/3`；匹配动作成功，其他动作 403。静态测试同时断言中间件动态 URI 清单包含 `order_status`。

```php
$this->assertSame(
    'admin_api_withdrawReject',
    LegacyAdminController::permissionRouteForLegacyRequest(
        Request::create('/index/admin/amount/order_status', 'POST', ['orderStatus' => '3'])
    )
);
```

- [x] **Step 4: 运行动态权限 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawPermissionClosureModuleTest.php tests\Feature\AdminLegacyRouteSemanticClosureTest.php
```

Expected: FAIL，Session 入口仍固定按 complete 权限授权。

- [x] **Step 5: 最小修复中间件与状态适配器**

在 `LegacyAdminAuthenticate::authorize()` 的动态 URI 中加入 `order_status`；删除 `forwardLegacyOrderStatus()` 的 id-only complete 快捷分支，普通和 OTC 请求统一严格验证 `orderId/id + orderStatus/status`。OTC 在完成记录和 scope 校验后始终返回失败关闭，不调用 `admin_api_withdrawComplete`。

```php
if (in_array($legacyUri, [
    'index/admin/auth/voucherReviewSave',
    'index/admin/cancel/update_cancel',
    'index/admin/amount/batchWithdrawApply',
    'index/admin/amount/order_status',
], true)) {
    $permissionRoute = LegacyAdminController::permissionRouteForLegacyRequest($request);
}
```

- [x] **Step 6: 运行 GREEN 与关联回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyAmountWithdrawClosureModuleTest.php tests\Feature\AdminLegacyWithdrawPermissionClosureModuleTest.php tests\Feature\AdminLegacyRouteSemanticClosureTest.php
```

Expected: 全部通过，OTC 零写入，普通 0/1/2/3 状态仍经现代状态机。

### Task 2: 统一出金筛选并实现旧 V1/V2 envelope

**Files:**
- Create: `app/Services/WithdrawRecordQueryService.php`
- Modify: `app/Http/Controllers/Admin/WithdrawController.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Create: `tests/Feature/AdminLegacyWithdrawSearchParityClosureModuleTest.php`
- Modify: `tests/Feature/AdminLegacyAmountWithdrawClosureModuleTest.php`

- [x] **Step 1: 写旧别名、日期、分页、汇总和 scope RED**

测试至少种两条不同 `mt4_ticket/status/created_at/user_id` 的记录，验证：

```php
$response = $this->actingAs($admin, 'admin')->postJson(
    '/index/admin/amount/withdrawApplySearchV2',
    [
        'userId' => $userId,
        'withdraw_id' => 'MT4-10001',
        'withdraw_source' => '0',
        'withdraw_startdate' => '2026-08-01',
        'withdraw_enddate' => '2026-08-31',
        'page' => 1,
        'rows' => 20,
    ]
);

$response->assertJsonPath('code', 200)
    ->assertJsonPath('count', 1)
    ->assertJsonPath('data.0.mt4_ticket', 'MT4-10001')
    ->assertJsonPath('data.0.applystatus', 0)
    ->assertJsonPath('totalRow.applyamount', '25.00');
```

V1 断言根级 `rows/total/footer`；V2 断言 `code=200/msg/count/data/totalRow`；空结果保持数组/零值，不再返回未定义变量；受限管理员只能统计可见用户。无日期时排除 2024-01-01 之前的数据。

- [x] **Step 2: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawSearchParityClosureModuleTest.php
```

Expected: FAIL，当前 `withdraw_id` 错映射到 `local_order_no`，且 envelope、日期和汇总缺失。

- [x] **Step 3: 实现单一查询服务**

服务只负责严格校验后的查询组合、数据范围和金额汇总，不返回 HTTP 响应。旧字段在适配器归一化为现代字段：`userId -> user_id`、`withdraw_id -> mt4_ticket`、`withdraw_source -> status`、`withdraw_startdate/enddate -> start_date/end_date`。

```php
public function query(?Admin $admin, array $filters): Builder
{
    $query = WithdrawRecord::query()->with('user');
    if ($admin) {
        $query = $this->dataScope->apply($query, $admin, 'withdraw', 'user_id');
    }
    if (array_key_exists('status', $filters) && $filters['status'] !== '') {
        $query->where('status', (int) $filters['status']);
    }
    if (!empty($filters['mt4_ticket'])) {
        $query->where('mt4_ticket', trim((string) $filters['mt4_ticket']));
    }
    return $query;
}
```

- [x] **Step 4: 实现旧行字段映射和两个 envelope**

行字段至少包含 `record_id/mt4_ticket/userId/username/bank_no/bank_no_info/applyamount/actapplyamount/drawrate/actdraw/drawpoundage/applystatus/rec_crt_date/orderIdLOC/orderIdOTC/orderIdOTCstatus/apply_remark`。`actdraw` 用 `actual_amount * exchange_rate` 计算，金额统一为定点字符串。

- [x] **Step 5: 让现代列表复用查询服务并补筛选校验**

现代 `WithdrawController@index` 支持 `mt4_ticket/start_date/end_date`，保留 `local_order_no/user_id/status`；页大小限制为 1..100，日期严格 `Y-m-d` 且结束日不早于开始日。

- [x] **Step 6: 运行 GREEN 与现代列表回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawSearchParityClosureModuleTest.php tests\Feature\AdminLegacyAmountWithdrawClosureModuleTest.php tests\Feature\AdminWithdrawControllerTest.php
```

Expected: 全部通过；若不存在 `AdminWithdrawControllerTest.php`，用 `rg --files tests | rg "Withdraw"` 选择现有现代出金测试，不创建无意义占位文件。

### Task 3: 实现旧详情页与数据范围闭环

**Files:**
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Create: `resources/admin/layui/withdrawals/detail.blade.php`
- Modify: `tests/Feature/AdminLegacyPageRenderClosureModuleTest.php`
- Create: `tests/Feature/AdminLegacyWithdrawDetailClosureModuleTest.php`

- [x] **Step 1: 写存在、不存在和越权 RED**

```php
$this->actingAs($admin, 'admin')
    ->get('/index/admin/amount/orderId_detail/' . $record->id)
    ->assertOk()
    ->assertViewIs('admin_layui::withdrawals.detail')
    ->assertViewHas('withdraw', fn ($value) => (int) $value->id === $record->id);
```

不存在记录返回 404；scope 外记录也返回 404，避免枚举；详情只展示快照银行卡、订单号、金额、状态、拒绝原因和审计时间，不读取旧库/MT4。

- [x] **Step 2: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawDetailClosureModuleTest.php tests\Feature\AdminLegacyPageRenderClosureModuleTest.php
```

Expected: FAIL，当前路由渲染通用列表且未查询记录。

- [x] **Step 3: 在通用页面 fallback 前增加专用详情分支**

```php
if ($legacyUri === 'index/admin/amount/orderId_detail/{orderId}') {
    return $this->renderLegacyWithdrawDetail($request);
}
```

`renderLegacyWithdrawDetail()` 严格校验正整数、加载 `WithdrawRecord::with('user')`、调用 `AdminDataScopeService::canAccessUser()`，失败统一 `abort(404)`。

- [x] **Step 4: 创建 Visual C 详情 Blade**

使用现有 `admin_layui::layouts.app` 和 Visual C token；语义化 `dl/dt/dd` 或只读表单；终态不可显示可写按钮，非终态操作仍回到列表由现代接口处理。不得加入内联 onclick 或新前端框架。

- [x] **Step 5: 运行 GREEN**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawDetailClosureModuleTest.php tests\Feature\AdminLegacyPageRenderClosureModuleTest.php tests\Feature\VisualCFoundationContractTest.php
```

Expected: 全部通过，详情记录和数据范围均由运行时测试证明。

### Task 4: 恢复旧两阶段导出并实现安全下载

**Files:**
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminExportController.php`
- Modify: `app/Services/WithdrawRecordQueryService.php`
- Create: `tests/Feature/AdminLegacyWithdrawExportDownloadClosureModuleTest.php`
- Modify: `tests/Feature/AdminLegacyExportDateFilterClosureModuleTest.php`

- [x] **Step 1: 写嵌套参数、CSV 内容、空结果和下载边界 RED**

旧入口以 `data[userId|withdraw_id|withdraw_source|withdraw_startdate|withdraw_enddate]` 请求。成功先返回 JSON `msg`，再 GET `withdraw_downloadfile/{file}/admin` 获得 CSV；断言只有筛选后的 scope 行、12 个旧列、UTF-8 BOM、`Content-Disposition`。空结果返回 `msg=FAIL`。非法文件、`..`、错误 role、其他管理员文件均 404。

```php
$prepare = $this->actingAs($admin, 'admin')->postJson(
    '/index/admin/amount/withdrawExport',
    ['data' => ['userId' => $userId, 'withdraw_id' => 'MT4-EXPORT']]
)->assertOk();

$this->assertMatchesRegularExpression(
    '#^index/admin/amount/withdraw_downloadfile/[^/]+/admin$#',
    $prepare->json('msg')
);
```

- [x] **Step 2: 写 CSV 公式注入 RED**

用户/订单/银行/备注字段以 `= + - @` 开头时，输出前加单引号；普通数值金额不加前缀。

- [x] **Step 3: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawExportDownloadClosureModuleTest.php tests\Feature\AdminLegacyExportDateFilterClosureModuleTest.php
```

Expected: FAIL，当前旧入口直接流式 CSV，下载路由重新导出且未处理公式前缀。

- [x] **Step 4: 实现准备/下载两阶段契约**

`withdrawExport` 通过共享查询服务生成最多 5000 行 CSV，写入 `storage/app/legacy-admin-exports/admin/{adminId}/withdrawals_{adminId}_{random}.csv`，响应 `msg` 为受保护下载 URI。`withdraw_downloadfile` 进入现有 `forwardLegacyDownloadFile()`；helper 允许 `.csv/.xlsx`，使用 basename 白名单、realpath containment、管理员目录绑定和错误 role 404。

- [x] **Step 5: 统一现代导出筛选和 CSV 单元格转义**

现代 `exportWithdrawals()` 复用查询服务；将所有字符串交给单一 `sanitizeCsvCell()`：

```php
private function sanitizeCsvCell($value)
{
    $text = (string) $value;
    return preg_match('/^[=+\-@]/u', $text) ? "'" . $text : $value;
}
```

- [x] **Step 6: 运行 GREEN 与权限回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyWithdrawExportDownloadClosureModuleTest.php tests\Feature\AdminLegacyExportDateFilterClosureModuleTest.php tests\Feature\AdminProtectedRoutePermissionClosureModuleTest.php
```

Expected: 全部通过；下载只读取本次管理员已生成文件，不重新查询或写业务表。

### Task 5: 补齐 Layui 与 CrmUI 出金页面交互

**Files:**
- Modify: `resources/admin/layui/withdrawals/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`
- Modify: `public/js/apps/crmui/admin.js` only if the existing generic export/detail behavior cannot satisfy the contract
- Create: `tests/Feature/AdminWithdrawalsUiClosureModuleTest.php`

- [x] **Step 1: 写静态/渲染 UI RED**

断言 Layui 包含 `local_order_no/mt4_ticket/user_id/status/start_date/end_date` 筛选、详情/导出按钮、`apply_amount` 列、reject reason 交互钩子和正确权限 slug；CrmUI `withdrawalPage()` 包含导出 action、日期/票据筛选、详情和拒绝原因字段。

- [x] **Step 2: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminWithdrawalsUiClosureModuleTest.php
```

Expected: FAIL，Layui 仍使用不存在的 `amount`，拒绝未提交 reason，CrmUI 缺导出 action。

- [x] **Step 3: 修改 Layui Blade 与 JavaScript**

表格列改为 `apply_amount/actual_amount/fee/local_order_no/mt4_ticket`；新增查看按钮（本地 Visual C modal）、导出按钮和日期/订单筛选；拒绝用 `layer.prompt` 收集非空 reason，再调用 `/api/admin/withdrawReject`。

```javascript
if (obj.event === 'reject') {
    layer.prompt({formType: 2, title: CrmLang.t('admin.reject_reason')}, function(reason, index) {
        if (!String(reason || '').trim()) return;
        layer.close(index);
        updateWithdraw('/api/admin/withdrawReject', obj.data.id, {reason: reason});
    });
}
```

- [x] **Step 4: 修改 CrmUI 页面声明**

`filters` 增加 `mt4_ticket/start_date/end_date`；`actions` 使用 `$this->exportActions('admin_api_exportWithdrawals', 'withdrawals_export.csv')`；详情继续使用现有 local modal；process/complete/reject 明确 `recordKey=id/payloadName=id` 和各自 permission。

- [x] **Step 5: 运行 GREEN 与全局 UI 契约**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminWithdrawalsUiClosureModuleTest.php tests\Feature\GlobalCrmThemeCoverageTest.php tests\Feature\UnifiedBladeDesignSystemTest.php tests\Feature\VisualCFoundationContractTest.php
```

Expected: 全部通过；双 UI 只加载自身家族资产，交互仍是 Blade + CSS/JavaScript。

### Task 6: 锁定 OTC callback 的 POST 失败关闭与方法策略

**Files:**
- Create: `tests/Feature/FrontLegacyOtcWithdrawalCallbackClosureModuleTest.php`
- Modify: `tests/Feature/FrontLegacyMethodPolicyClosureModuleTest.php`
- Modify only if RED proves a real gap: `app/Http/Controllers/Front/PaymentNotifyController.php`
- Modify only if RED proves a real gap: `routes/web.php`

- [x] **Step 1: 写十条方法级 HTTP 测试**

对 `withdraw_notfiy_otc`、`withdraw_verify_otc` 各验证 POST 与 GET/PUT/PATCH/DELETE：POST 在 adapter 未配置时 422，配置但验签失败时 400；其余四种方法 405 且 `Allow: POST`。

- [x] **Step 2: 写 POST 零写入和重放 RED/基线测试**

种一条能被旧不安全 payload 命中的 `withdraw_records`；依次提交旧字段和重复 payload，断言 `status/local_order_no/third_order_no/updated_at/outbox count` 完全不变，响应不包含假成功 ack。

- [x] **Step 3: 运行 RED 或确认现有实现已 GREEN**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\FrontLegacyOtcWithdrawalCallbackClosureModuleTest.php tests\Feature\FrontLegacyMethodPolicyClosureModuleTest.php
```

Expected: 若当前路由/adapter 已满足，新增测试可直接 GREEN；这是对既有安全收敛补证据，不为制造 RED 而改生产代码。若失败，仅修复实际偏差。

- [x] **Step 4: 运行支付回调关联回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\FrontPaymentRouteSafetyClosureModuleTest.php tests\Feature\FrontLegacyRouteCompatibilityTest.php tests\Feature\PaymentGatewayAdapterFixtureTest.php
```

Expected: 全部通过；CSRF 豁免仍只包含精确 POST callback URI，未知方法不能写库。

### Task 7: 回归、审查和核验矩阵更新

**Files:**
- Modify: `docs/audits/旧项目路由核验证据.json`
- Regenerate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Regenerate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Modify: `docs/项目整体进度梳理-2026-08-17.md`

- [x] **Step 1: 运行首批专项回归**

```powershell
vendor\bin\phpunit --colors=never --filter "Withdraw|withdraw|OtcWithdrawal" tests\Feature
```

Expected: 相关出金测试零 Failure、零 Error。

- [x] **Step 2: 运行 PHP/JavaScript/Blade 静态检查**

```powershell
php -l app\Http\Controllers\Admin\LegacyAdminController.php
php -l app\Http\Controllers\Admin\WithdrawController.php
php -l app\Http\Controllers\Admin\LegacyAdminExportController.php
php -l app\Services\WithdrawRecordQueryService.php
node --check public\js\apps\admin\layui\pages.js
node --check public\js\apps\crmui\admin.js
php artisan view:cache
php artisan view:clear
```

Expected: 全部 exit 0；`view:clear` 只清编译缓存，不删除业务日志或上传文件。

- [x] **Step 3: 独立代码审查**

按 19 条待核验记录逐条检查：旧行为、路由映射、后端逻辑、前端契约、权限/范围、校验/错误、自动化测试七个维度。Critical/Important 问题修复并重跑对应测试后才能更新证据。

- [x] **Step 4: 写 19 条持久化核验证据**

`docs/audits/旧项目路由核验证据.json` 为唯一人工状态来源。每条记录必须写独立 `legacy_method + legacy_uri`，包含安全收敛理由、当前源码引用和实际通过的测试名称；不得批量声称 OTC 的 8 个非 POST 与 2 个 POST 具有相同行为而省略方法级证据。

- [x] **Step 5: 重新生成矩阵并检查计数**

```powershell
php scripts\generate-legacy-implementation-matrix.php
```

Expected: 出金 19 条写入后至少为 `verified=384`、`needs_manual_business_review=91`；当前同日另有前台大代理 10 条独立证据写入，因此全局实际为 `verified=394`、`needs_manual_business_review=81`、`unresolved_legacy_source=0`、`unmatched_current_route=0`。Markdown 为 UTF-8 可读文本。

- [x] **Step 6: 更新总进度文档并进入下一批**

把实际命令、测试数、断言数、矩阵时间和任何残余风险写入 `docs/项目整体进度梳理-2026-08-17.md`。随后从当前全局剩余 81 条按旧控制器数量和风险选择下一批，先重新做旧/新/测试三方只读审计，再建立下一份专项计划；不得把本批完成表述成全项目完成。

**Actual result (2026-08-17 21:39 +08:00):**

- 完整出金 Feature 回归使用等价大小写不敏感过滤 `--filter "(?i)withdraw"`：`516 tests / 5345 assertions / exit 0`。
- 规格复审 `✅ Spec compliant`；质量复审发现的 3 个 Important（详情金额 float 精度、CSV 控制字符公式注入、footer 手续费币种混用）均按 TDD 修复，复审 `✅ Approved`。
- PHP/JavaScript 语法、`view:cache`、`view:clear` 全部 exit 0。
- 矩阵生成器实际输出 `394/81/0/0`；其中本专项贡献 19 条，另有同日前台大代理业务组贡献 10 条。出金矩阵测试 `2 tests / 387 assertions`，全局矩阵测试 `19 tests / 426 assertions`。
- 下一批仍按剩余控制器风险优先审计 `WithdrawStatusController` 12 条；本专项完成不代表全项目完成。
