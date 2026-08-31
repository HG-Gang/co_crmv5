# Laravel Dual UI Families Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在现有 `co_crmv5` Laravel Blade 项目内，把前台 A、后台 A、前台 B、后台 B 四个正式 UI 家族的第一阶段实施路径固定下来，并先完成可验证的样板页。

**Architecture:** 不新建项目，不改造成 SPA，不重写业务接口。继续使用 `front_layui::`、`admin_layui::`、`front_crmui::`、`admin_crmui::` 四个 Blade 命名空间；Layui 家族以现有逐页 Blade 为主，CRMUI 家族以配置驱动的模块页为主；两套家族共享后端业务能力，但页面级 CSS、组件类名和 DOM 行为保持隔离。

**Tech Stack:** Laravel 8, Blade, Layui, jQuery, plain CSS, PHPUnit, `php artisan serve`, browser smoke checks.

---

## How To Use This Plan

这份计划给 Codex、人工开发者或后续任务使用。执行前必须先读取：

- `docs/superpowers/specs/2026-07-31-laravel-dual-ui-families-design.md`
- `design-system/co-crm/MASTER.md`
- 本计划文件
- 当前目标页面的 Blade、CSS、JavaScript、路由和控制器

后续给 Codex 的标准提示词：

```text
请先读取 docs/superpowers/specs/2026-07-31-laravel-dual-ui-families-design.md，
再读取 docs/superpowers/plans/2026-08-01-laravel-dual-ui-families-phase1-implementation.md，
然后在当前 Laravel Blade 项目中执行计划中的下一项任务。
保持现有业务接口、权限、认证、国际化、路由行为和数据结构不变。
```

第一阶段固定样板入口：

- 前台 A：`/front/dashboard?frame=1`、`/front/deposit?frame=1`
- 后台 A：`/admin/dashboard`、`/admin/users`
- 前台 B：`/front-crmui/dashboard`、`/front-crmui/deposit`
- 后台 B：`/admin-crmui/dashboard`、`/admin-crmui/users`

`/front-naive` 与 `/admin-naive` 继续作为跳转层保留，不纳入第一阶段视觉实现。

---

### Task 1: Add Dual UI Boundary Contract Tests

**Files:**
- Create: `tests/Feature/DualUiFamilyDesignContractTest.php`
- Read: `docs/superpowers/specs/2026-07-31-laravel-dual-ui-families-design.md`
- Read: `docs/superpowers/plans/2026-08-01-laravel-dual-ui-families-phase1-implementation.md`
- Read: `resources/front/layui/layouts/app.blade.php`
- Read: `resources/admin/layui/layouts/app.blade.php`
- Read: `resources/front/crmui/layouts/app.blade.php`
- Read: `resources/admin/crmui/layouts/app.blade.php`

- [x] **Step 1: Create the failing contract test**

Create `tests/Feature/DualUiFamilyDesignContractTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

final class DualUiFamilyDesignContractTest extends TestCase
{
    public function test_design_source_documents_exist_for_dual_ui_implementation(): void
    {
        $spec = base_path('docs/superpowers/specs/2026-07-31-laravel-dual-ui-families-design.md');
        $plan = base_path('docs/superpowers/plans/2026-08-01-laravel-dual-ui-families-phase1-implementation.md');
        $handbook = base_path('docs/superpowers/guides/dual-ui-implementation-handbook.md');

        $this->assertFileExists($spec);
        $this->assertFileExists($plan);
        $this->assertFileExists($handbook);

        $specText = file_get_contents($spec) ?: '';
        foreach ([
            '前台 A：Layui + Blade',
            '后台 A：Layui + Blade',
            '前台 B：CRMUI + Blade',
            '后台 B：CRMUI + Blade',
            '不把系统改造成 SPA',
            '不新增第三套真实 UI',
        ] as $needle) {
            $this->assertStringContainsString($needle, $specText);
        }
    }

    public function test_four_ui_family_view_namespaces_are_registered(): void
    {
        foreach ([
            'front_layui::layouts.app',
            'admin_layui::layouts.app',
            'front_crmui::layouts.app',
            'admin_crmui::layouts.app',
        ] as $view) {
            $this->assertTrue(view()->exists($view), 'Missing Blade view namespace: ' . $view);
        }
    }

    public function test_public_entry_pages_render_the_expected_family_assets(): void
    {
        $this->get('/front/login')
            ->assertOk()
            ->assertSee('/css/front/', false)
            ->assertDontSee('/css/crmui/front.css', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('/css/admin/style.css', false)
            ->assertDontSee('/css/crmui/admin.css', false);

        $this->get('/front-crmui/login')
            ->assertOk()
            ->assertSee('/css/crmui/front.css', false)
            ->assertDontSee('/css/front/style.css', false);

        $this->get('/admin-crmui/login')
            ->assertOk()
            ->assertSee('/css/crmui/admin.css', false)
            ->assertDontSee('/css/admin/style.css', false);
    }

    public function test_layouts_do_not_cross_link_page_level_family_assets(): void
    {
        $frontLayui = file_get_contents(resource_path('front/layui/layouts/app.blade.php')) ?: '';
        $adminLayui = file_get_contents(resource_path('admin/layui/layouts/app.blade.php')) ?: '';
        $frontCrmui = file_get_contents(resource_path('front/crmui/layouts/app.blade.php')) ?: '';
        $adminCrmui = file_get_contents(resource_path('admin/crmui/layouts/app.blade.php')) ?: '';

        $this->assertStringContainsString('/css/front/style.css', $frontLayui);
        $this->assertStringNotContainsString('/css/crmui/front.css', $frontLayui);

        $this->assertStringContainsString('/css/admin/style.css', $adminLayui);
        $this->assertStringNotContainsString('/css/crmui/admin.css', $adminLayui);

        $this->assertStringContainsString('/css/crmui/front.css', $frontCrmui);
        $this->assertStringNotContainsString('/css/front/style.css', $frontCrmui);

        $this->assertStringContainsString('/css/crmui/admin.css', $adminCrmui);
        $this->assertStringNotContainsString('/css/admin/style.css', $adminCrmui);
    }
}
```

- [x] **Step 2: Run the contract test and confirm the expected failure**

Run:

```powershell
vendor\bin\phpunit --filter DualUiFamilyDesignContractTest
```

Expected: FAIL because `docs/superpowers/guides/dual-ui-implementation-handbook.md` has not been created yet.

- [x] **Step 3: Keep the failing output in the implementation notes**

Record the failure line mentioning `dual-ui-implementation-handbook.md`. This proves the test is guarding the missing usage manual, not failing because of routes or Blade namespaces.

Observed on 2026-08-01:

```text
Failed asserting that file "D:\Software\PhpProject\Demo\co_crmv5\docs/superpowers/guides/dual-ui-implementation-handbook.md" exists.
Tests: 4, Assertions: 27, Failures: 1.
```

---

### Task 2: Create The Dual UI Implementation Handbook

**Files:**
- Create: `docs/superpowers/guides/dual-ui-implementation-handbook.md`
- Test: `tests/Feature/DualUiFamilyDesignContractTest.php`

- [ ] **Step 1: Add the handbook**

Create `docs/superpowers/guides/dual-ui-implementation-handbook.md`:

````markdown
# 双 UI 家族实施手册

## 文档用途

本手册说明如何在当前 Laravel Blade 项目内使用双 UI 家族设计文档。它不是自动执行脚本，也不是新的 UI 框架；它是 Codex、人工开发者和后续任务的共同实施约束。

## 谁来使用

- Codex：首选实施者，负责读取设计文档、实施计划和目标页面文件，然后直接修改当前项目中的 Blade、CSS、JavaScript 与测试。
- 人工开发者：按照同一文档判断页面归属、资源边界、组件隔离、验收状态和回归测试范围。
- 后续 Codex 任务：必须在提示词中引用设计文档和实施计划，不能只凭单页截图重新定义整站风格。

## 每次实施前必须读取

- `docs/superpowers/specs/2026-07-31-laravel-dual-ui-families-design.md`
- `docs/superpowers/plans/2026-08-01-laravel-dual-ui-families-phase1-implementation.md`
- `design-system/co-crm/MASTER.md`
- 目标 UI 家族的 Blade 布局、页面模板、CSS、JavaScript、路由和控制器

## 四个正式 UI 落点

- 前台 A：`front_layui::`，模板目录 `resources/front/layui/`，主要资源 `public/css/front/` 与 `public/js/apps/front/layui/`
- 后台 A：`admin_layui::`，模板目录 `resources/admin/layui/`，主要资源 `public/css/admin/` 与 `public/js/apps/admin/layui/`
- 前台 B：`front_crmui::`，模板目录 `resources/front/crmui/`，主要资源 `public/css/crmui/front.css` 与 `public/js/apps/crmui/front.js`
- 后台 B：`admin_crmui::`，模板目录 `resources/admin/crmui/`，主要资源 `public/css/crmui/admin.css` 与 `public/js/apps/crmui/admin.js`

## 标准实施提示词

```text
请先读取 docs/superpowers/specs/2026-07-31-laravel-dual-ui-families-design.md，
再读取 docs/superpowers/plans/2026-08-01-laravel-dual-ui-families-phase1-implementation.md，
然后读取当前目标页面的 Blade、CSS、JavaScript、路由和控制器。
在现有 Laravel Blade 项目中实现 [UI 家族] 的 [前台/后台] [页面名称]，
保持现有业务接口、权限、认证、国际化、路由行为和数据结构不变。
```

## 第一阶段样板顺序

先做四个样板入口：

- 前台 A：`/front/dashboard?frame=1`、`/front/deposit?frame=1`
- 后台 A：`/admin/dashboard`、`/admin/users`
- 前台 B：`/front-crmui/dashboard`、`/front-crmui/deposit`
- 后台 B：`/admin-crmui/dashboard`、`/admin-crmui/users`

完成样板后，再按资金、交易、代理、系统配置等模块扩展到其余页面。

## ui-ux-pro-max 的使用方式

`ui-ux-pro-max` 用于设计决策和质量审查，不作为运行时依赖。使用时只采纳与当前 Laravel Blade 架构兼容的建议：

- 使用它校准可访问性、响应式、表格、表单、状态反馈、图标和动效规则。
- Layui 家族只吸收稳定、紧凑、企业化的规则。
- CRMUI 家族吸收更现代、精致、品牌化的规则。
- 如果技能建议引入 Vue、React、Tailwind 或新的 SPA 架构，本项目第一阶段不采用。

## 禁止事项

- 不把 A/B 做成同一套皮肤。
- 不把任意一套定义为迁移来源或临时版本。
- 不让 Layui 页面引用 CRMUI 页面级 CSS。
- 不让 CRMUI 页面依赖 Layui 的页面级选择器。
- 不新增第三套真实 UI。
- 不删除 `/front-naive` 或 `/admin-naive`。

## 验收命令

```powershell
php artisan view:clear
vendor\bin\phpunit --filter DualUiFamilyDesignContractTest
vendor\bin\phpunit --filter LegacyUiReplacementCoverageTest
vendor\bin\phpunit --filter FrontUiRegressionTest
```

浏览器烟测使用本地服务：

```powershell
php artisan serve --host=127.0.0.1 --port=8025
```

检查这些地址：

- `http://127.0.0.1:8025/front/login`
- `http://127.0.0.1:8025/admin/login`
- `http://127.0.0.1:8025/front-crmui/login`
- `http://127.0.0.1:8025/admin-crmui/login`
- `http://127.0.0.1:8025/front-crmui/dashboard`
- `http://127.0.0.1:8025/admin-crmui/dashboard`

## 完成标准

- 四个 UI 家族入口可以渲染。
- A/B 视觉明显不同。
- 同一业务能力复用相同后端 API 和业务语义。
- 页面没有跨家族引用页面级 CSS 或 DOM 行为。
- 登录、导航、表格、表单、空状态、错误状态和移动端布局通过检查。
````

- [ ] **Step 2: Run the contract test again**

Run:

```powershell
vendor\bin\phpunit --filter DualUiFamilyDesignContractTest
```

Expected: PASS.

- [ ] **Step 3: Commit the documentation and contract test**

Run:

```powershell
git add tests/Feature/DualUiFamilyDesignContractTest.php docs/superpowers/guides/dual-ui-implementation-handbook.md
git commit -m "docs: add dual ui implementation handbook"
```

If this workspace is not a Git checkout, skip the commit and record that Git is unavailable in the execution report.

---

### Task 3: Implement Family A Front Samples

**Files:**
- Modify: `resources/front/layui/dashboard/index_v2.blade.php`
- Modify: `resources/front/layui/deposit/index_v2.blade.php`
- Modify: `resources/front/layui/layouts/app.blade.php`
- Modify: `public/css/front/style.css`
- Modify: `public/js/apps/front/layui/pages.js` only if existing selectors need accessible states
- Test: `tests/Feature/LegacyUiReplacementCoverageTest.php`
- Test: `tests/Feature/FrontUiRegressionTest.php`

- [ ] **Step 1: Inspect the current selector contracts**

Run:

```powershell
rg -n "data-layui-page|dashboard/index|deposit/index|depositForm|depositHistoryTable|contentFrame|front-frame-shell" resources/front/layui public/js/apps/front/layui tests
```

Expected: selectors used by existing tests and JavaScript are visible before editing.

- [ ] **Step 2: Refine the Layui front visual layer**

Keep the existing IDs, form names, table hooks and `data-layui-page` values. Apply only A-family visual refinements:

- denser dashboard metric layout
- clearer page title area
- visible keyboard focus on buttons and links
- deposit form submit/loading/error states
- mobile table wrapper that avoids viewport-breaking layout

- [ ] **Step 3: Verify front A pages**

Run:

```powershell
php artisan view:clear
vendor\bin\phpunit --filter LegacyUiReplacementCoverageTest
vendor\bin\phpunit --filter FrontUiRegressionTest
```

Expected: both filters pass, or failures point to existing database fixture requirements rather than changed Blade/CSS selectors.

- [ ] **Step 4: Browser smoke test front A**

Run:

```powershell
php artisan serve --host=127.0.0.1 --port=8025
```

Open:

- `http://127.0.0.1:8025/front/dashboard?frame=1`
- `http://127.0.0.1:8025/front/deposit?frame=1`

Expected: pages render as Layui A, not CRMUI B; no horizontal page scroll at 375px, 768px, 1024px and 1440px widths.

---

### Task 4: Implement Family A Admin Samples

**Files:**
- Modify: `resources/admin/layui/dashboard/index.blade.php`
- Modify: `resources/admin/layui/users/index.blade.php`
- Modify: `resources/admin/layui/layouts/app.blade.php`
- Modify: `public/css/admin/style.css`
- Modify: `public/js/apps/admin/layui/pages.js` only if existing selectors need accessible states
- Test: `tests/Feature/LegacyUiReplacementCoverageTest.php`
- Test: `tests/Feature/FrontUiRegressionTest.php`

- [ ] **Step 1: Inspect admin A contracts**

Run:

```powershell
rg -n "data-layui-page|users/index|dashboard/index|adminMenu|crm-admin-page-head|layui-table" resources/admin/layui public/js/apps/admin/layui tests
```

Expected: admin A route and selector contracts are visible before editing.

- [ ] **Step 2: Refine admin A dashboard and user list**

Keep the existing admin route names and API URLs. Apply A-family rules:

- high-density table controls
- predictable toolbar and filter spacing
- clear row hover and selected state
- visible focus states
- stable sidebar/topbar spacing

- [ ] **Step 3: Verify admin A pages**

Run:

```powershell
php artisan view:clear
vendor\bin\phpunit --filter LegacyUiReplacementCoverageTest
vendor\bin\phpunit --filter FrontUiRegressionTest
```

Expected: route, Blade and API contract tests pass.

- [ ] **Step 4: Browser smoke test admin A**

Open:

- `http://127.0.0.1:8025/admin/login`
- `http://127.0.0.1:8025/admin/dashboard`
- `http://127.0.0.1:8025/admin/users`

Expected: pages render as Layui A; they do not load `public/css/crmui/admin.css`.

---

### Task 5: Implement Family B Front Samples

**Files:**
- Modify: `resources/front/crmui/layouts/app.blade.php`
- Modify: `resources/front/crmui/partials/module-page.blade.php`
- Modify: `resources/front/crmui/dashboard/index.blade.php`
- Modify: `resources/front/crmui/deposit/index.blade.php`
- Modify: `public/css/crmui/tokens.css`
- Modify: `public/css/crmui/front.css`
- Modify: `public/js/apps/crmui/front.js` only for state, loading, table and form behavior
- Test: `tests/Feature/DualUiFamilyDesignContractTest.php`
- Test: `tests/Feature/LegacyUiReplacementCoverageTest.php`
- Test: `tests/Feature/FrontUiRegressionTest.php`

- [ ] **Step 1: Inspect front B contracts**

Run:

```powershell
rg -n "data-crmui-page|front.dashboard|front.deposit|crmui-page-head|crmui-filter|crmui-table|data-crmui-form" resources/front/crmui public/js/apps/crmui/front.js tests
```

Expected: `front.dashboard` and `front.deposit` both render through CRMUI B templates and shared module partial.

- [ ] **Step 2: Refine CRMUI front tokens and module page**

Keep CRMUI as a Blade/jQuery UI. Apply B-family rules:

- modern tokenized colors and spacing through `public/css/crmui/tokens.css`
- stronger page head hierarchy
- better empty/loading/error states
- accessible icon buttons and tool buttons
- deposit form feedback and amount preview without changing API payload names

- [ ] **Step 3: Verify front B pages**

Run:

```powershell
php artisan view:clear
vendor\bin\phpunit --filter DualUiFamilyDesignContractTest
vendor\bin\phpunit --filter LegacyUiReplacementCoverageTest
vendor\bin\phpunit --filter FrontUiRegressionTest
```

Expected: CRMUI routes still output `data-crmui-page="front.dashboard"` and `data-crmui-page="front.deposit"` with the same API URLs.

- [ ] **Step 4: Browser smoke test front B**

Open:

- `http://127.0.0.1:8025/front-crmui/dashboard`
- `http://127.0.0.1:8025/front-crmui/deposit`

Expected: pages render as CRMUI B; they do not load `public/css/front/style.css`.

---

### Task 6: Implement Family B Admin Samples

**Files:**
- Modify: `resources/admin/crmui/layouts/app.blade.php`
- Modify: `resources/admin/crmui/partials/module-page.blade.php`
- Modify: `resources/admin/crmui/dashboard/index.blade.php`
- Modify: `resources/admin/crmui/users/index.blade.php`
- Modify: `public/css/crmui/tokens.css`
- Modify: `public/css/crmui/admin.css`
- Modify: `public/js/apps/crmui/admin.js` only for state, loading, table and form behavior
- Test: `tests/Feature/DualUiFamilyDesignContractTest.php`
- Test: `tests/Feature/LegacyUiReplacementCoverageTest.php`
- Test: `tests/Feature/FrontUiRegressionTest.php`

- [ ] **Step 1: Inspect admin B contracts**

Run:

```powershell
rg -n "data-crmui-page|admin.dashboard|admin.users|crmui-page-head|crmui-filter|crmui-table|data-crmui-row-action" resources/admin/crmui public/js/apps/crmui/admin.js tests
```

Expected: `admin.dashboard` and `admin.users` both render through CRMUI B templates and shared module partial.

- [ ] **Step 2: Refine CRMUI admin density and table behavior**

Apply B-family admin rules:

- medium-high density table layout
- compact but readable filter controls
- clear row action buttons
- visible permission/action disabled states
- modal focus and close behavior
- no reliance on Layui page-level visual selectors

- [ ] **Step 3: Verify admin B pages**

Run:

```powershell
php artisan view:clear
vendor\bin\phpunit --filter DualUiFamilyDesignContractTest
vendor\bin\phpunit --filter LegacyUiReplacementCoverageTest
vendor\bin\phpunit --filter FrontUiRegressionTest
```

Expected: CRMUI admin routes still output `data-crmui-page="admin.dashboard"` and `data-crmui-page="admin.users"` with the same API URLs.

- [ ] **Step 4: Browser smoke test admin B**

Open:

- `http://127.0.0.1:8025/admin-crmui/dashboard`
- `http://127.0.0.1:8025/admin-crmui/users`

Expected: pages render as CRMUI B; they do not load `public/css/admin/style.css`.

---

### Task 7: Final Phase 1 Verification

**Files:**
- No new production files beyond the files changed in Tasks 1-6

- [ ] **Step 1: Clear compiled Blade views**

Run:

```powershell
php artisan view:clear
```

Expected: compiled views are cleared without exception.

- [ ] **Step 2: Run targeted UI contract tests**

Run:

```powershell
vendor\bin\phpunit --filter DualUiFamilyDesignContractTest
vendor\bin\phpunit --filter LegacyUiReplacementCoverageTest
vendor\bin\phpunit --filter FrontUiRegressionTest
```

Expected: targeted tests pass, or the report clearly separates pre-existing fixture failures from UI boundary regressions.

- [ ] **Step 3: Smoke check the eight sample routes**

Run a local server:

```powershell
php artisan serve --host=127.0.0.1 --port=8025
```

Open:

- `http://127.0.0.1:8025/front/dashboard?frame=1`
- `http://127.0.0.1:8025/front/deposit?frame=1`
- `http://127.0.0.1:8025/admin/dashboard`
- `http://127.0.0.1:8025/admin/users`
- `http://127.0.0.1:8025/front-crmui/dashboard`
- `http://127.0.0.1:8025/front-crmui/deposit`
- `http://127.0.0.1:8025/admin-crmui/dashboard`
- `http://127.0.0.1:8025/admin-crmui/users`

Expected: all reachable routes render their intended UI family, no sample page visually crosses into the other family, and mobile widths do not create unintended horizontal page scroll.

- [ ] **Step 4: Record the delivery report**

Create a short report in `docs/superpowers/reports/` with:

- changed files
- commands run
- test results
- browser routes checked
- known limits
- next recommended module batch

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-01-laravel-dual-ui-families-phase1-implementation.md`. Two execution options:

**1. Subagent-Driven (recommended)** - Dispatch a fresh worker per task, review between tasks, fast iteration.

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints.

For this project, choose Inline Execution unless the user explicitly wants parallel workers, because the same CSS and Blade boundaries are shared across the first four sample pages.
