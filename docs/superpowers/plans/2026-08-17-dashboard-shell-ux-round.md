# Dashboard Shell UX Round Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将 Layui 与 CrmUI/Naive 主壳的语言/主题入口统一为图标下拉并明确高亮当前项，同时把 Layui Dashboard 改成无渐变的紧凑运营台，支持真实 7/15/30 天统计与图标化图表视图切换。

**Architecture:** 保留 `public/js/shared/theme-sync.js` 为主题唯一状态源，新建无依赖的共享偏好菜单脚本只负责菜单开合、键盘交互和语言跳转；四套主布局只渲染共享 partial，不再各自维护语言按钮。Dashboard 的周期参数由 `DashboardController@dashboardData` 严格校验后驱动真实数据库聚合，前端切换周期时重新请求 API，切换图形时只重绘当前真实快照。

**Tech Stack:** Laravel 8.83、PHP 7.4+、PHPUnit 9.6、Blade、Layui、CrmUI/Naive、原生 JavaScript/jQuery、ECharts、原生 CSS。

**Safety boundary:** 旧库 `hank_zl_data` 永久只读，正式库 `co_crmv5` 禁写；运行时测试只写 `co_crmv5_test`。MT4 永久禁用。当前目录没有 `.git`，不初始化仓库或伪造提交；每个 RED/GREEN 输出和文件 SHA-256 作为检查点。

---

### Task 1: 共享主题图标菜单与当前项语义

**Files:**
- Create: `tests/Feature/DashboardShellUxRoundClosureModuleTest.php`
- Modify: `resources/views/partials/theme-picker.blade.php`
- Modify: `public/js/shared/theme-sync.js`
- Modify: `public/css/common/crm-themes.css`

- [x] **Step 1: 写主题菜单 RED**

新增静态/渲染测试，要求共享 partial 保留兼容 `select`，同时渲染纯图标 trigger、15 个 `data-theme` 菜单项、Lucide `check`、`aria-current` 和主题 token 样式：

```php
public function test_shared_theme_picker_is_an_icon_menu_with_current_item_semantics(): void
{
    $html = view('partials.theme-picker', ['themePickerCompact' => true])->render();
    $sync = file_get_contents(public_path('js/shared/theme-sync.js')) ?: '';
    $css = file_get_contents(public_path('css/common/crm-themes.css')) ?: '';

    $this->assertStringContainsString('data-crm-preference-trigger="theme"', $html);
    $this->assertStringContainsString('data-lucide="palette"', $html);
    $this->assertSame(15, substr_count($html, 'data-theme='));
    $this->assertSame(15, substr_count($html, 'data-lucide="check"'));
    $this->assertStringContainsString('aria-current="', $html);
    $this->assertStringContainsString("setAttribute('aria-current'", $sync);
    $this->assertStringContainsString('.crm-preference-item.is-current', $css);
}
```

- [x] **Step 2: 运行 RED**

Run: `vendor\bin\phpunit --colors=never --filter test_shared_theme_picker_is_an_icon_menu_with_current_item_semantics tests\Feature\DashboardShellUxRoundClosureModuleTest.php`

Expected: FAIL，当前 partial 只有可见 `select`，没有菜单项、check 或 `aria-current`。

- [x] **Step 3: 最小实现共享主题菜单**

`theme-picker.blade.php` 使用配置目录生成菜单，原 `select` 改为 `.crm-sr-only` 兼容控件：

```blade
<div class="crm-preference-menu crm-theme-picker {{ $crmThemePickerCompact ? 'is-compact' : '' }}" data-crm-preference-menu>
    <button class="crm-preference-trigger" type="button" data-crm-preference-trigger="theme"
            aria-haspopup="menu" aria-expanded="false" aria-label="{{ __('front.skin_mode') }}"
            title="{{ __('front.skin_mode') }}">
        <i data-lucide="palette" aria-hidden="true"></i>
    </button>
    <div class="crm-preference-popover" role="menu" hidden>
        @foreach($crmThemeOptions as $crmThemeOptionKey => $crmThemeOption)
            <button type="button" class="crm-preference-item" role="menuitemradio"
                    data-theme="{{ $crmThemeOptionKey }}" aria-current="false" aria-checked="false">
                <span>{{ __($crmThemeOption['label']) }}</span>
                <i data-lucide="check" aria-hidden="true"></i>
            </button>
        @endforeach
    </div>
    <select id="{{ $crmThemePickerId }}" class="crm-sr-only" data-crm-skin-select
            aria-label="{{ __('front.skin_mode') }}">...</select>
</div>
```

`syncControls()` 对每个主题项同步 `.is-current`、`aria-current`、`aria-checked`，且不创建第二个主题存储键。

- [x] **Step 4: 运行 GREEN 与既有主题契约**

Run: `vendor\bin\phpunit --colors=never tests\Feature\DashboardShellUxRoundClosureModuleTest.php tests\Feature\GlobalCrmThemeCoverageTest.php`

Expected: 共享菜单与既有 15 主题目录、iframe 同步、唯一状态源测试全部通过。

### Task 2: 共享语言图标菜单与四套主壳接入

**Files:**
- Create: `resources/views/partials/language-picker.blade.php`
- Create: `public/js/shared/preference-menu.js`
- Modify: `resources/views/partials/theme-assets.blade.php`
- Modify: `resources/admin/layui/layouts/app.blade.php`
- Modify: `resources/admin/crmui/layouts/app.blade.php`
- Modify: `resources/front/layui/layouts/app.blade.php`
- Modify: `resources/front/crmui/layouts/app.blade.php`
- Modify: `tests/Feature/DashboardShellUxRoundClosureModuleTest.php`

- [x] **Step 1: 写四主壳语言图标 RED**

```php
public function test_four_main_shells_use_shared_icon_language_picker(): void
{
    foreach ([
        'resources/admin/layui/layouts/app.blade.php',
        'resources/admin/crmui/layouts/app.blade.php',
        'resources/front/layui/layouts/app.blade.php',
        'resources/front/crmui/layouts/app.blade.php',
    ] as $path) {
        $blade = file_get_contents(base_path($path)) ?: '';
        $this->assertStringContainsString("partials.language-picker", $blade);
        $this->assertStringNotContainsString('data-crmui-lang="zh-CN"><i', $blade);
    }
}
```

同时断言共享脚本支持 click、Escape、外部点击关闭、焦点恢复和 `locale` 查询参数跳转。

- [x] **Step 2: 运行 RED**

Run: `vendor\bin\phpunit --colors=never --filter test_four_main_shells_use_shared_icon_language_picker tests\Feature\DashboardShellUxRoundClosureModuleTest.php`

Expected: FAIL，四套布局仍有两种独立语言按钮实现。

- [x] **Step 3: 实现无依赖偏好菜单脚本**

`preference-menu.js` 使用事件委托；主题项仍调用 `CrmTheme.set()`，语言项写 `crm_locale/front_lang` 并以当前 URL 的 `locale` 参数重新加载：

```javascript
function selectLocale(locale) {
    var next = locale === 'en' ? 'en' : 'zh-CN';
    localStorage.setItem('crm_locale', next);
    localStorage.setItem('front_lang', next);
    var url = new URL(window.location.href);
    url.searchParams.set('locale', next);
    window.location.assign(url.toString());
}
```

菜单打开时设置 `aria-expanded=true` 并聚焦当前项；关闭时恢复 trigger 焦点。菜单背景、边框、hover、当前项均使用 `--crm-*` token。

- [x] **Step 4: 四主壳替换独立语言控件**

Layui 的 nav item 与 CrmUI 的 topbar actions 都只 include：

```blade
@include('partials.language-picker', ['languagePickerCompact' => true])
@include('partials.theme-picker', ['themePickerCompact' => true])
```

按钮本体只显示 `languages`/`palette` 图标；可见语言名称只出现在下拉项内。保留现有 UI family 切换和 logout，不改业务导航。

- [x] **Step 5: 运行 GREEN、JS 语法与布局回归**

Run: `node --check public\js\shared\preference-menu.js`

Run: `vendor\bin\phpunit --colors=never tests\Feature\DashboardShellUxRoundClosureModuleTest.php tests\Feature\GlobalCrmThemeCoverageTest.php tests\Feature\UnifiedBladeDesignSystemTest.php`

Expected: 脚本语法正确，四主壳只有一套语言/主题菜单实现，现有布局契约不回退。

### Task 3: Dashboard 去渐变、去装饰圆球并收敛密度

**Files:**
- Modify: `tests/Feature/DashboardShellUxRoundClosureModuleTest.php`
- Modify: `resources/front/layui/dashboard/index.blade.php`
- Modify: `resources/front/layui/dashboard/index_v2.blade.php`
- Modify: `public/css/front/v2.css`

- [x] **Step 1: 写紧凑运营台视觉 RED**

断言 `index.blade.php` 不再使用 `--front-hero-gradient` 和 `.dashboard-hero-main:after`；`v2.css` 的 dashboard hero 规则不含 `linear-gradient`、`radial-gradient` 或圆形装饰伪元素；两页面卡片 body 保持 12–16px，圆角不超过 8px，Dashboard 继续保留未认证 CTA。

```php
$this->assertStringNotContainsString('background: var(--front-hero-gradient)', $blade);
$this->assertStringNotContainsString('.dashboard-hero-main:after', $blade);
$this->assertStringContainsString('id="identityGuideBtn"', $blade);
```

- [x] **Step 2: 运行 RED**

Run: `vendor\bin\phpunit --colors=never --filter test_layui_dashboards_use_flat_compact_operations_layout tests\Feature\DashboardShellUxRoundClosureModuleTest.php`

Expected: FAIL，两个 Dashboard 仍使用渐变/径向装饰。

- [x] **Step 3: 最小重排两个 Layui Dashboard**

`dashboard-hero-main` 使用 `var(--front-side)` 实色背景和 1px token 边框；CTA 改为 6px 圆角的明确命令按钮；账户/层级摘要改用稳定 grid，不使用 pill 或 `999px` 圆角。`front-v2-dashboard-hero` 只使用 `var(--v2-surface)`、`var(--v2-line)`、`var(--v2-primary)`，删除 dashboard 范围内的装饰伪元素和渐变。

- [x] **Step 4: 运行 GREEN**

Run: `vendor\bin\phpunit --colors=never --filter "dashboard" tests\Feature\DashboardShellUxRoundClosureModuleTest.php tests\Feature\FrontUiRegressionTest.php`

Expected: Dashboard 原有 CTA、图表容器、样式切换路由和多语言 key 均保留，新增无渐变门禁通过。

### Task 4: Dashboard 真实 7/15/30 天统计 API

**Files:**
- Create: `tests/Feature/FrontDashboardRangeClosureModuleTest.php`
- Modify: `app/Http/Controllers/Front/DashboardController.php`
- Modify: `tests/Feature/DashboardControllerCommentReadabilityTest.php`

- [x] **Step 1: 写周期校验与真实聚合 RED**

测试使用 `CreatesLegacyFrontUserFixture + DatabaseTransactions` 创建当前用户，在测试库写入窗口内/外入金；请求 `?days=7` 只统计 7 天内记录，并返回 `period.days=7`。`days=8`、数组和小数均返回 `ResponseCode::VALIDATION_FAILED` 且不执行伪默认。

```php
$response = $this->withToken($token)->getJson('/api/front/dashboard?days=7');
$response->assertJsonPath('data.period.days', 7)
    ->assertJsonPath('data.stats.monthly_deposit', 25.5);

$this->withToken($token)->getJson('/api/front/dashboard?days=8')
    ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
```

- [x] **Step 2: 运行 RED**

Run: `vendor\bin\phpunit --colors=never tests\Feature\FrontDashboardRangeClosureModuleTest.php`

Expected: FAIL，当前控制器固定 30 天且响应没有 `period.days`。

- [x] **Step 3: 实现严格周期参数**

在鉴权后、查询前校验：

```php
$validator = Validator::make($request->only('days'), [
    'days' => 'sometimes|integer|in:7,15,30',
]);
if ($validator->fails()) {
    return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
}
$periodDays = (int) $request->input('days', 30);
$periodStart = time() - $periodDays * 86400;
```

所有原 `monthStart/monthStartDateTime` 查询统一改用该周期；保留响应字段兼容名 `monthly_*`，新增 `period.days` 明确实际口径，不创建缓存或 mock。

- [x] **Step 4: 运行 GREEN 与 Dashboard API 回归**

Run: `vendor\bin\phpunit --colors=never tests\Feature\FrontDashboardRangeClosureModuleTest.php tests\Feature\FrontLegacyDashboardWiringClosureModuleTest.php tests\Feature\DashboardControllerCommentReadabilityTest.php`

Expected: 7/15/30 真实查询、非法参数失败关闭、既有 API 结构全部通过。

### Task 5: 图标化图形切换与周期分段控件

**Files:**
- Modify: `tests/Feature/DashboardShellUxRoundClosureModuleTest.php`
- Modify: `resources/front/layui/dashboard/index.blade.php`
- Modify: `resources/front/layui/dashboard/index_v2.blade.php`
- Modify: `public/js/apps/front/layui/pages.js`
- Modify: `resources/lang/zh-CN/front.php`
- Modify: `resources/lang/en/front.php`
- Modify: `resources/lang/zh_CN/front.php`
- Modify: `public/js/shared/lang/common/zh-CN.js`
- Modify: `public/js/shared/lang/common/en.js`

- [x] **Step 1: 写图标控件与多语言 RED**

要求页面包含全局 `data-dashboard-range="7|15|30"` 分段按钮，每个图表包含 `data-chart-type="bar|line|area|pie"` 图标按钮；按钮有 Lucide 图标、`title`、屏幕阅读器文本和 `aria-pressed`。JS 必须把周期传给 API，取消过期请求，并只用最新响应更新图表。

- [x] **Step 2: 运行 RED**

Run: `vendor\bin\phpunit --colors=never --filter test_dashboard_chart_controls_are_icon_based_localized_and_range_aware tests\Feature\DashboardShellUxRoundClosureModuleTest.php`

Expected: FAIL，当前图形切换是文本 select，API 请求不带 days。

- [x] **Step 3: 实现稳定尺寸图标控件**

视图类型按钮使用 Lucide `chart-column/chart-line/chart-area/chart-pie`；CSS 固定为 44×44px，当前项使用 `is-active + aria-pressed=true`。周期按钮显示本地化的 `近 7/15/30 天`，点击后更新 current range、终止上一请求并重新调用：

```javascript
dashboardRequest = CrmAjax.request({
    guard: 'front',
    url: '/api/front/dashboard',
    method: 'GET',
    data: {days: activeRange}
});
```

若 `CrmAjax.request` 不返回可 abort 对象，则用递增 request token 忽略过期响应，不新增依赖。

- [x] **Step 4: 图表颜色改为主题 token**

`chartOption()` 从 `getComputedStyle(document.documentElement)` 读取 `--front-blue/--front-accent/--front-warn/--front-danger/--front-cyan`，不再写死大面积蓝紫调色板。legend、tooltip、轴标签继续读取 `CrmLang.t` 和当前主题文本 token。

- [x] **Step 5: 运行 GREEN 与语法检查**

Run: `vendor\bin\phpunit --colors=never tests\Feature\DashboardShellUxRoundClosureModuleTest.php tests\Feature\FrontDashboardRangeClosureModuleTest.php tests\Feature\FrontUiRegressionTest.php`

Run: `node --check public\js\apps\front\layui\pages.js`

Expected: 图标/周期/多语言/真实数据切换全绿，JS 语法正确。

### Task 6: Blade、主题和四视口验收

**Files:**
- Modify: `docs/项目整体进度梳理-2026-08-17.md`
- Create: `docs/audits/2026-08-17-dashboard-shell-ux-round-result.md`

- [x] **Step 1: 运行目标回归**

Run: `vendor\bin\phpunit --colors=never tests\Feature\DashboardShellUxRoundClosureModuleTest.php tests\Feature\FrontDashboardRangeClosureModuleTest.php tests\Feature\GlobalCrmThemeCoverageTest.php tests\Feature\FrontUiRegressionTest.php tests\Feature\UnifiedBladeDesignSystemTest.php tests\Feature\VisualCFoundationContractTest.php`

Expected: 0 failure / 0 error。

- [x] **Step 2: 运行语法与 Blade 编译**

Run: `php -l app\Http\Controllers\Front\DashboardController.php`

Run: `node --check public\js\shared\theme-sync.js`

Run: `node --check public\js\shared\preference-menu.js`

Run: `node --check public\js\apps\front\layui\pages.js`

Run: `php artisan view:cache`

Run: `php artisan view:clear`

Expected: 全部 exit 0，编译缓存清理不触碰业务数据。

- [x] **Step 3: 启动隔离开发服务器**

使用未占用本地端口启动 Laravel，只连接当前测试/只读配置；不得提交会写业务表的表单。

- [ ] **Step 4: Playwright 四视口验收**

`BLOCKED_BY_BROWSER_POLICY`：2026-08-18 隔离测试库服务已成功启动，但浏览器在导航到 `127.0.0.1:8098` 前被运行环境安全策略拒绝；策略禁止改用其他浏览器表面绕过。服务已停止，未生成截图，不得勾选本步骤。

在 `1440×900 / 1280×720 / 768×1024 / 390×844` 验证 Layui Dashboard、CrmUI/Naive Dashboard 和四主壳：无横向页面滚动、无文字/菜单重叠；主题/语言菜单可点击和键盘操作；当前项有 check、高亮和 `aria-current`；7/15/30 与图形按钮稳定不位移；未认证 CTA 可见且跳转资料页。截图写入 `storage/app/visual-audits/2026-08-17-dashboard-shell-ux-round/`。

- [x] **Step 5: 回写审计和总进度**

只记录真实执行的测试数、断言数、浏览器视口和残余风险。浏览器或鉴权受限时明确写 `BLOCKED_BY_*`，不得将静态检查表述为运行时验收。
