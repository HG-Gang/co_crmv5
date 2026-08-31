# Phase 1 Visual C Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在不改变业务字段、API、权限、路由或 Blade 架构的前提下，为 Layui 与 CrmUI 两个页面族建立相互隔离的视觉 C 基础层，并在前台 dashboard、后台 users 四个对应页面完成桌面与移动验收。

**Architecture:** 保留现有大体量家族 CSS 和 JavaScript 作为业务契约来源，在每个家族现有样式之后加载一份独立、可回滚的 Visual C 覆盖层。四个布局通过 `data-ui-family`、`data-ui-surface` 和 `data-visual-direction` 声明边界；Layui 前后台共用视觉基础但不加载 CrmUI 资产，CrmUI 前后台同理。Layui 移动侧栏从内联动画改为显式 class 状态，CrmUI 继续复用现有 `is-open` 交互。

**Tech Stack:** Laravel 8.83、Blade、Layui、原生 CSS、jQuery/原生 JavaScript、PHPUnit 9.6、Codex in-app browser。

---

## 前置证据与边界

- 设计规格：`docs/superpowers/specs/2026-08-07-full-legacy-parity-and-visual-c-design.md`
- 总路线图：`docs/superpowers/plans/2026-08-07-full-legacy-parity-and-visual-c-roadmap.md`
- Phase 0 报告：`docs/audits/2026-08-07-phase-0-result.md`
- 页面清单：`storage/app/audits/2026-08-07-current-project-surface-inventory.json`
- 历史双 UI 计划：`docs/superpowers/plans/2026-08-01-laravel-dual-ui-families-phase1-implementation.md`，仅作为现有路由与资产边界证据。
- 历史文档引用的 `design-system/co-crm/MASTER.md` 当前不存在；本阶段以 2026-08-07 规格和真实 CSS/Blade 为权威来源，不虚构该文件。
- 当前目录不是 Git 仓库。每个任务记录修改前后 SHA-256、目标测试和浏览器证据，不初始化 Git。
- 不连接 `hank_zl_data`，不写入或重建 `co_crmv5`，不创建测试库，MT4 保持禁用。

固定样板入口：

- 前台 Layui：`/front/dashboard`
- 后台 Layui：`/admin/users`
- 前台 CrmUI：`/front-crmui/dashboard`
- 后台 CrmUI：`/admin-crmui/users`

固定视口：`1440x900`、`1280x720`、`768x1024`、`390x844`。

### Task 1: 锁定 Visual C 与双家族边界契约

**Files:**
- Create: `tests/Feature/VisualCFoundationContractTest.php`
- Read: `public/css/front/style.css`
- Read: `public/css/admin/style.css`
- Read: `public/css/crmui/tokens.css`
- Read: `public/css/crmui/front.css`
- Read: `public/css/crmui/admin.css`
- Test: `tests/Feature/VisualCFoundationContractTest.php`

- [x] **Step 1: 记录修改前检查点**

Run:

```powershell
Get-FileHash -Algorithm SHA256 resources\front\layui\layouts\app.blade.php,resources\admin\layui\layouts\app.blade.php,resources\front\crmui\layouts\app.blade.php,resources\admin\crmui\layouts\app.blade.php,resources\front\layui\dashboard\index.blade.php,resources\admin\layui\users\index.blade.php,resources\front\crmui\partials\module-page.blade.php,resources\admin\crmui\partials\module-page.blade.php,public\js\apps\front\layui\layout.js,public\js\apps\admin\layui\layout.js
```

Expected: 十个现有文件均输出 SHA-256；`public/css/layui/visual-c.css` 与 `public/css/crmui/visual-c.css` 尚不存在。

- [x] **Step 2: 创建失败契约测试**

创建 `tests/Feature/VisualCFoundationContractTest.php`，完整覆盖以下行为：

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class VisualCFoundationContractTest extends TestCase
{
    public function test_each_ui_family_has_an_isolated_visual_c_asset(): void
    {
        $layui = $this->source('public/css/layui/visual-c.css');
        $crmui = $this->source('public/css/crmui/visual-c.css');

        foreach (['#171717', '#242424', '#3A3A3A', '#F5F5F5', '#A3A3A3', '#F2C94C', '#34A853', '#EF5350', '#4DA3FF'] as $color) {
            $this->assertStringContainsString($color, $layui);
            $this->assertStringContainsString($color, $crmui);
        }

        $this->assertStringContainsString('[data-ui-family="layui"]', $layui);
        $this->assertStringNotContainsString('[data-ui-family="crmui"]', $layui);
        $this->assertStringContainsString('[data-ui-family="crmui"]', $crmui);
        $this->assertStringNotContainsString('[data-ui-family="layui"]', $crmui);
        $this->assertStringNotContainsString('linear-gradient', $layui);
        $this->assertStringNotContainsString('radial-gradient', $layui);
        $this->assertStringNotContainsString('linear-gradient', $crmui);
        $this->assertStringNotContainsString('radial-gradient', $crmui);
    }

    public function test_layouts_load_only_their_family_visual_c_asset(): void
    {
        $layouts = [
            'resources/front/layui/layouts/app.blade.php' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css', 'layui', 'front'],
            'resources/admin/layui/layouts/app.blade.php' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css', 'layui', 'admin'],
            'resources/front/crmui/layouts/app.blade.php' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css', 'crmui', 'front'],
            'resources/admin/crmui/layouts/app.blade.php' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css', 'crmui', 'admin'],
        ];

        foreach ($layouts as $path => [$ownAsset, $foreignAsset, $family, $surface]) {
            $source = $this->source($path);
            $this->assertStringContainsString($ownAsset, $source);
            $this->assertStringNotContainsString($foreignAsset, $source);
            $this->assertStringContainsString('data-ui-family="' . $family . '"', $source);
            $this->assertStringContainsString('data-ui-surface="' . $surface . '"', $source);
            $this->assertStringContainsString('data-visual-direction="c"', $source);
        }
    }

    public function test_foundations_cover_required_components_states_and_breakpoints(): void
    {
        $layui = $this->source('public/css/layui/visual-c.css');
        $crmui = $this->source('public/css/crmui/visual-c.css');

        foreach (['.layui-layout-admin', '.layui-table', '.layui-form', '.layui-layer', '[data-ui-state="loading"]', '[data-ui-state="empty"]', '[data-ui-state="error"]', '[data-ui-state="success"]', '[aria-disabled="true"]', '@media (max-width: 768px)', '@media (max-width: 480px)'] as $needle) {
            $this->assertStringContainsString($needle, $layui);
        }
        foreach (['.crmui-shell', '.crmui-table', '.crmui-form', '.crmui-modal', '[data-ui-state="loading"]', '[data-ui-state="empty"]', '[data-ui-state="error"]', '[data-ui-state="success"]', '[aria-disabled="true"]', '@media (max-width: 768px)', '@media (max-width: 480px)'] as $needle) {
            $this->assertStringContainsString($needle, $crmui);
        }
    }

    public function test_layui_mobile_sidebar_uses_explicit_accessible_state(): void
    {
        $frontLayout = $this->source('resources/front/layui/layouts/app.blade.php');
        $adminLayout = $this->source('resources/admin/layui/layouts/app.blade.php');
        $frontScript = $this->source('public/js/apps/front/layui/layout.js');
        $adminScript = $this->source('public/js/apps/admin/layui/layout.js');

        foreach ([$frontLayout, $adminLayout] as $layout) {
            $this->assertStringContainsString('data-layui-sidebar-toggle', $layout);
            $this->assertStringContainsString('aria-controls=', $layout);
            $this->assertStringContainsString('aria-expanded="false"', $layout);
            $this->assertStringContainsString('data-layui-sidebar-dismiss', $layout);
        }
        foreach ([$frontScript, $adminScript] as $script) {
            $this->assertStringContainsString('is-sidebar-open', $script);
            $this->assertStringContainsString('is-sidebar-collapsed', $script);
            $this->assertStringContainsString("aria-expanded", $script);
            $this->assertStringContainsString("matchMedia('(max-width: 768px)')", $script);
        }
    }

    public function test_reference_pages_keep_business_hooks_and_declare_visual_reference(): void
    {
        $frontDashboard = $this->source('resources/front/layui/dashboard/index.blade.php');
        $adminUsers = $this->source('resources/admin/layui/users/index.blade.php');
        $frontCrmui = $this->source('resources/front/crmui/partials/module-page.blade.php');
        $adminCrmui = $this->source('resources/admin/crmui/partials/module-page.blade.php');

        $this->assertStringContainsString('data-visual-c-reference="front-dashboard"', $frontDashboard);
        $this->assertStringContainsString('data-layui-page="dashboard/index"', $frontDashboard);
        $this->assertStringContainsString('data-visual-c-reference="admin-users"', $adminUsers);
        $this->assertStringContainsString('id="userSearchForm"', $adminUsers);
        $this->assertStringContainsString('id="userTable"', $adminUsers);
        foreach ([$frontCrmui, $adminCrmui] as $partial) {
            $this->assertStringContainsString('data-visual-c-reference=', $partial);
            $this->assertStringContainsString('data-crmui-page=', $partial);
            $this->assertStringContainsString('data-crmui-table-body', $partial);
            $this->assertStringContainsString('data-crmui-action-modal', $partial);
        }
    }

    private function source(string $relativePath): string
    {
        $path = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
```

- [x] **Step 3: 运行测试并确认正确红灯**

Run:

```powershell
vendor\bin\phpunit --colors=never tests\Feature\VisualCFoundationContractTest.php
```

Expected: FAIL，首先报告两份 Visual C CSS 文件不存在；不得因数据库连接或 PHP 语法错误失败。

### Task 2: 建立 Layui Visual C 基础与移动侧栏闭环

**Files:**
- Create: `public/css/layui/visual-c.css`
- Modify: `resources/front/layui/layouts/app.blade.php`
- Modify: `resources/admin/layui/layouts/app.blade.php`
- Modify: `public/js/apps/front/layui/layout.js`
- Modify: `public/js/apps/admin/layui/layout.js`
- Modify: `resources/front/layui/dashboard/index.blade.php`
- Modify: `resources/admin/layui/users/index.blade.php`
- Test: `tests/Feature/VisualCFoundationContractTest.php`
- Test: `tests/Feature/DualUiFamilyDesignContractTest.php`

- [x] **Step 1: 创建 Layui 家族覆盖层**

`public/css/layui/visual-c.css` 必须只作用于 `[data-ui-family="layui"][data-visual-direction="c"]`，并完整定义：

```css
:root {
    --layui-vc-bg: #171717;
    --layui-vc-surface: #242424;
    --layui-vc-surface-raised: #2C2C2C;
    --layui-vc-border: #3A3A3A;
    --layui-vc-text: #F5F5F5;
    --layui-vc-muted: #A3A3A3;
    --layui-vc-accent: #F2C94C;
    --layui-vc-success: #34A853;
    --layui-vc-danger: #EF5350;
    --layui-vc-info: #4DA3FF;
    --layui-vc-radius: 6px;
    --layui-vc-sidebar: 224px;
    --layui-vc-header: 56px;
}
```

同一文件实现布局、紧凑表格、筛选表单、按钮、Layui 弹层、状态块、可见焦点、禁用态、dashboard 指标/图表表面、users 表格工具栏，以及 `768px`/`480px` 响应式。所有背景为纯色，禁止渐变、装饰光球和大面积阴影；表格横向滚动只能发生在表格容器内。

- [x] **Step 2: 布局加载顺序和身份声明**

四个要求必须同时满足：

```blade
@yield('styles')
<link rel="stylesheet" href="{{ asset('/css/layui/visual-c.css') }}?v=2026080701">
```

前台 body 增加：

```blade
data-ui-family="layui" data-ui-surface="front" data-visual-direction="c"
```

后台 body 增加：

```blade
data-ui-family="layui" data-ui-surface="admin" data-visual-direction="c"
```

视觉 C 文件必须在现有页面 `@yield('styles')` 之后加载，确保旧页面内联浅色/渐变样式不会盖过最终方向。

- [x] **Step 3: 修复前后台 Layui 移动侧栏**

前后台切换按钮统一增加 `data-layui-sidebar-toggle`、`aria-controls`、`aria-expanded="false"`；布局增加 `data-layui-sidebar-dismiss` 遮罩按钮。JavaScript 使用 `.is-sidebar-open` 和 `.is-sidebar-collapsed` 管理状态：移动端点击打开/关闭抽屉，桌面端切换紧凑侧栏，Escape、遮罩点击、移动导航点击和从移动切回桌面都会关闭抽屉。禁止继续使用 jQuery `animate()` 写入宽度和 left 内联样式。

- [x] **Step 4: 标记真实样板页面但保留业务钩子**

前台 dashboard 根节点增加 `data-visual-c-reference="front-dashboard"`；后台 users 根面板增加 `data-visual-c-reference="admin-users"`。不得改动 dashboard 元素 ID、`data-layui-page`、`userSearchForm`、`userTable`、字段 name、权限属性或 API 路由。

- [x] **Step 5: 验证 Layui 目标测试**

Run:

```powershell
vendor\bin\phpunit --colors=never tests\Feature\VisualCFoundationContractTest.php
vendor\bin\phpunit --colors=never tests\Feature\DualUiFamilyDesignContractTest.php
node --check public\js\apps\front\layui\layout.js
node --check public\js\apps\admin\layui\layout.js
```

Expected: Visual C 测试只剩 CrmUI 文件相关失败；双 UI 边界测试和两个 JavaScript 语法检查通过。

### Task 3: 建立 CrmUI Visual C 基础与通用页面状态

**Files:**
- Create: `public/css/crmui/visual-c.css`
- Modify: `resources/front/crmui/layouts/app.blade.php`
- Modify: `resources/admin/crmui/layouts/app.blade.php`
- Modify: `resources/front/crmui/partials/module-page.blade.php`
- Modify: `resources/admin/crmui/partials/module-page.blade.php`
- Test: `tests/Feature/VisualCFoundationContractTest.php`
- Test: `tests/Feature/CrmUiStackTest.php`

- [x] **Step 1: 创建 CrmUI 家族覆盖层**

`public/css/crmui/visual-c.css` 必须只作用于 `[data-ui-family="crmui"][data-visual-direction="c"]`。它用同一视觉 C 基础色覆盖现有 `--crmui-*`，但保持 CrmUI 自身更精细的层级、标签页、表格工具栏和详情密度；不得引用 Layui family selector。

```css
:root {
    --crmui-vc-bg: #171717;
    --crmui-vc-surface: #242424;
    --crmui-vc-surface-raised: #2C2C2C;
    --crmui-vc-border: #3A3A3A;
    --crmui-vc-text: #F5F5F5;
    --crmui-vc-muted: #A3A3A3;
    --crmui-vc-accent: #F2C94C;
    --crmui-vc-success: #34A853;
    --crmui-vc-danger: #EF5350;
    --crmui-vc-info: #4DA3FF;
    --crmui-vc-radius: 6px;
}
```

同一文件必须覆盖 `.crmui-shell`、sidebar/topbar/page head、metrics、panel、tabs、filter/form/input/button、table、modal、loading/empty/error/success/disabled/unauthorized 状态、焦点和 `768px`/`480px` 响应式。移除可见渐变和装饰动画，保留短促状态过渡及 `prefers-reduced-motion`。

- [x] **Step 2: 布局加载和身份声明**

两个 CrmUI 布局在 `@yield('styles')` 后加载：

```blade
<link rel="stylesheet" href="{{ asset('/css/crmui/visual-c.css') }}?v=2026080701">
```

前后台 body 分别声明 `data-ui-family="crmui"`、`data-ui-surface="front|admin"`、`data-visual-direction="c"`。

- [x] **Step 3: 让通用模块页声明真实样板身份**

前后台 `module-page.blade.php` 的根 section 增加：

```blade
data-visual-c-reference="{{ $page['key'] ?? '' }}"
```

不得改动 `data-crmui-page`、API URL/method、字段、列、行操作、权限、表单或 modal 数据属性。

- [x] **Step 4: 运行目标测试**

Run:

```powershell
vendor\bin\phpunit --colors=never tests\Feature\VisualCFoundationContractTest.php
vendor\bin\phpunit --colors=never tests\Feature\DualUiFamilyDesignContractTest.php
vendor\bin\phpunit --colors=never tests\Feature\CrmUiStackTest.php
```

Expected: 全部通过，零 Failure、零 Error。

### Task 4: 真实路由与交互回归

**Files:**
- Modify: `tests/Feature/VisualCFoundationContractTest.php`
- Test: `tests/Feature/VisualCFoundationContractTest.php`
- Test: `tests/Feature/BladeOnlyFrontendArchitectureTest.php`
- Test: `tests/Feature/BladeLocalAssetReferenceTest.php`
- Test: `tests/Feature/LucideIconAndEmojiPolicyTest.php`

- [x] **Step 1: 先加入四个真实路由的失败渲染断言**

在 Visual C 测试加入：

```php
public function test_reference_routes_render_visual_c_without_cross_family_assets(): void
{
    foreach ([
        '/front/dashboard' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css', 'front-dashboard'],
        '/admin/users' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css', 'admin-users'],
        '/front-crmui/dashboard' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css', 'front.dashboard'],
        '/admin-crmui/users' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css', 'admin.users'],
    ] as $uri => [$ownAsset, $foreignAsset, $reference]) {
        $response = $this->get($uri)->assertOk();
        $response->assertSee($ownAsset, false);
        $response->assertDontSee($foreignAsset, false);
        $response->assertSee('data-visual-c-reference="' . $reference . '"', false);
    }
}
```

Run once before implementation completion if the route markers are not yet present; Expected: FAIL on missing asset/marker, not database access.

- [x] **Step 2: 运行架构和资产回归**

Run:

```powershell
vendor\bin\phpunit --colors=never tests\Feature\VisualCFoundationContractTest.php
vendor\bin\phpunit --colors=never tests\Feature\DualUiFamilyDesignContractTest.php
vendor\bin\phpunit --colors=never tests\Feature\BladeOnlyFrontendArchitectureTest.php
vendor\bin\phpunit --colors=never tests\Feature\BladeLocalAssetReferenceTest.php
vendor\bin\phpunit --colors=never tests\Feature\LucideIconAndEmojiPolicyTest.php
php artisan view:clear
```

Expected: 全部通过；Blade 仍为前后端不分离架构，资产均为本地路径，图标策略不回归，视图缓存可清理。

### Task 5: 浏览器四视口验收与阶段报告

**Files:**
- Create: `docs/audits/visual-c-phase-1/*.png`
- Create: `docs/audits/2026-08-07-phase-1-visual-c-foundation-result.md`

- [x] **Step 1: 以测试身份启动本地服务**

服务进程必须显式设置：

```powershell
$env:APP_ENV='testing'
$env:DATABASE_URL=''
$env:DB_CONNECTION='mysql'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3307'
$env:DB_DATABASE='co_crmv5_test'
$env:MT4_ENABLED='false'
$env:MT4_USER_SYNC_ENABLED='false'
php artisan serve --host=127.0.0.1 --port=8091
```

Expected: 服务启动；若页面启动访问数据库，只能失败于 `co_crmv5_test`，不得回退正式库。

- [x] **Step 2: 浏览器验证 16 个页面/视口组合**

对四个固定样板入口分别验证四个固定视口。每次导航后检查：

- `document.documentElement.scrollWidth <= document.documentElement.clientWidth`；
- Visual C 计算色值为 `rgb(23, 23, 23)` 背景、`rgb(242, 201, 76)` 主强调；
- 页面不存在可见元素互相遮挡，文本/按钮没有裁切；
- 表格横向溢出只在 `.layui-table-view` 或 `.crmui-table-wrap` 内；
- 控制台无 JavaScript error；API 无数据/失败时显示明确状态，不出现假成功；
- `390x844` 下三个 UI 家族侧栏按钮均可打开、Escape/遮罩可关闭，`aria-expanded` 同步；
- 只进行读操作，不提交表单、不触发写 API。

每个组合保存 PNG 到 `docs/audits/visual-c-phase-1/`，文件名包含家族、页面和视口。

- [x] **Step 3: 最终静态验证和检查点**

Run:

```powershell
vendor\bin\phpunit --colors=never tests\Feature\VisualCFoundationContractTest.php
vendor\bin\phpunit --colors=never tests\Feature\DualUiFamilyDesignContractTest.php
vendor\bin\phpunit --colors=never tests\Feature\CrmUiStackTest.php
vendor\bin\phpunit --colors=never tests\Feature\BladeOnlyFrontendArchitectureTest.php
vendor\bin\phpunit --colors=never tests\Feature\BladeLocalAssetReferenceTest.php
vendor\bin\phpunit --colors=never tests\Feature\LucideIconAndEmojiPolicyTest.php
php -l tests\Feature\VisualCFoundationContractTest.php
node --check public\js\apps\front\layui\layout.js
node --check public\js\apps\admin\layui\layout.js
rg -n "linear-gradient|radial-gradient" public\css\layui\visual-c.css public\css\crmui\visual-c.css
Get-FileHash -Algorithm SHA256 public\css\layui\visual-c.css,public\css\crmui\visual-c.css,resources\front\layui\layouts\app.blade.php,resources\admin\layui\layouts\app.blade.php,resources\front\crmui\layouts\app.blade.php,resources\admin\crmui\layouts\app.blade.php,public\js\apps\front\layui\layout.js,public\js\apps\admin\layui\layout.js,tests\Feature\VisualCFoundationContractTest.php
```

Expected: 测试、PHP/JS 语法均通过；渐变扫描退出码 1 且无匹配；九个文件输出 SHA-256。

- [x] **Step 4: 写入阶段报告**

`docs/audits/2026-08-07-phase-1-visual-c-foundation-result.md` 必须记录：修改文件、修改前后哈希、测试命令/退出码/数量、16 张截图、每个视口 overflow/console/sidebar 结果、保留的业务钩子、数据库零写入声明、未关闭问题和 Phase 2 输入。

只有以下条件同时满足才标记 Phase 1 完成：

```text
两个家族只加载自身 Visual C 资产
四个真实样板路由字段/API/权限钩子不变
目标测试零 Failure、零 Error
四视口无页面级横向溢出、遮挡或控制台错误
移动侧栏可打开、关闭并同步可访问状态
旧库与正式新库零写入
```
