# COCRM Global Themes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 CrmUI 与 Layui 的前后台全部 Blade 页面中增加 10 套已批准主题，并保留现有 5 套主题。

**Architecture:** 使用一个 PHP 主题目录提供元数据与颜色令牌，一个共享 Blade 资产入口和选择器覆盖所有布局，一个 JavaScript 状态源负责持久化与跨页同步，一个最终加载的 CSS 文件把语义令牌映射到两套 UI 家族。布局继承完成 173 个页面覆盖，独立 HTML 文档显式接入共享 partial。

**Tech Stack:** Laravel 8、Blade、原生 JavaScript、CSS Custom Properties、PHPUnit

---

### Task 1: 固化主题契约测试

**Files:**
- Create: `tests/Feature/GlobalCrmThemeCoverageTest.php`

- [ ] 写测试，断言配置包含 15 个键和指定 10 个新增键。
- [ ] 写真实 WCAG 相对亮度计算，断言正文/弱文本/强调文字对各自表面达到 4.5:1。
- [ ] 断言共享脚本从页面注入目录读取主题、监听 `data-crm-skin-select`，且 CrmUI 脚本不再包含 `crmui_theme`。
- [ ] 枚举带 `<!DOCTYPE html>` 的目标 Blade，断言每个入口直接或经布局加载 `partials.theme-assets`。
- [ ] 断言四套主布局包含 `partials.theme-picker`，旧 5 项 dashboard 菜单已移除。
- [ ] 运行 `vendor\bin\phpunit --filter GlobalCrmThemeCoverageTest`，确认因配置和 partial 缺失而失败。

### Task 2: 建立单一主题目录与共享组件

**Files:**
- Create: `config/crm_themes.php`
- Create: `resources/views/partials/theme-assets.blade.php`
- Create: `resources/views/partials/theme-picker.blade.php`

- [ ] 在配置中定义 5 个现有主题与 10 个新增主题，每项包含翻译键、表面/文字/边界/强调颜色及几何令牌。
- [ ] 资产 partial 用 `json_encode(array_keys(config('crm_themes.themes')))` 注入 `window.CRM_THEME_VALUES`，加载 `theme-sync.js`、`theme-sync.css` 和 `crm-themes.css`。
- [ ] 选择器 partial 输出关联 label、15 个 option、`data-crm-skin-select` 与可选紧凑样式类。
- [ ] 运行目标测试，保留尚未接入布局导致的预期失败。

### Task 3: 统一主题状态源

**Files:**
- Modify: `public/js/shared/theme-sync.js`
- Modify: `public/js/apps/crmui/admin.js`
- Modify: `public/js/apps/crmui/front.js`

- [ ] 将合法值改为优先读取 `window.CRM_THEME_VALUES`，并在缺失注入时保留 15 键静态兜底。
- [ ] 扩展根节点皮肤类清理规则，设置 `data-crmui-theme-mode`，非 `dark` 主题使用浅色 `color-scheme`。
- [ ] 添加委托 `change` 监听，对 `[data-crm-skin-select]` 调用 `CrmTheme.set()`。
- [ ] 删除 CrmUI 两个脚本中独立的点击切暗色和 `localStorage.crmui_theme` 初始化逻辑。
- [ ] 运行目标测试，确认状态源断言通过。

### Task 4: 实现 15 套语义主题样式

**Files:**
- Create: `public/css/common/crm-themes.css`

- [ ] 为 15 个主题建立 `--crm-*` 语义变量，并兼容现有 `--front-*`、`--admin-*`、`--crmui-*` 变量。
- [ ] 覆盖 body、侧栏、顶栏、卡片、表格、表单、按钮、下拉、分页、标签、弹层、空状态、焦点和滚动条。
- [ ] 分别实现 10 套主题的导航选中、面板强调、圆角、阴影、侧栏宽度和表格密度差异。
- [ ] 明确定义危险红与警告黄，其他成功/在线状态使用当前强调色。
- [ ] 添加小屏选择器样式与 `prefers-reduced-motion`。
- [ ] 运行目标测试中的对比度断言。

### Task 5: 接入所有 Blade 完整入口

**Files:**
- Modify: `resources/admin/crmui/layouts/app.blade.php`
- Modify: `resources/admin/crmui/layouts/auth.blade.php`
- Modify: `resources/admin/layui/layouts/app.blade.php`
- Modify: `resources/admin/layui/auth/login.blade.php`
- Modify: `resources/front/crmui/layouts/app.blade.php`
- Modify: `resources/front/crmui/layouts/auth.blade.php`
- Modify: `resources/front/crmui/big-agent/layout.blade.php`
- Modify: `resources/front/layui/layouts/app.blade.php`
- Modify: `resources/front/layui/legacy-big-agent/layout.blade.php`
- Modify: `resources/front/layui/auth/*.blade.php`

- [ ] 在每个完整 HTML 入口的 head 最后加载 `partials.theme-assets`，移除重复的旧主题脚本/CSS。
- [ ] 将四套主布局旧按钮/5 项菜单替换为 `partials.theme-picker`。
- [ ] 在认证和大代理入口提供紧凑选择器，不破坏现有表单或导航布局。
- [ ] 运行入口覆盖测试并执行 `php artisan view:cache`。

### Task 6: 清理局部主题菜单与补齐翻译

**Files:**
- Modify: `resources/front/layui/dashboard/index.blade.php`
- Modify: `resources/front/layui/dashboard/index_v2.blade.php`
- Modify: `resources/lang/zh-CN/front.php`
- Modify: `resources/lang/en/front.php`
- Modify: `public/js/shared/lang/common/zh-CN.js`
- Modify: `public/js/shared/lang/common/en.js`

- [ ] 删除 dashboard 自建的旧 5 项主题下拉和对应初始化逻辑，复用共享 picker。
- [ ] 增加 10 套主题中英文名称和通用“界面主题”标签。
- [ ] 运行目标测试及现有 `FrontUiRegressionTest`，如旧测试锁定 5 项实现则更新为 15 项共享契约。

### Task 7: 真实验证

**Files:**
- Test: `tests/Feature/GlobalCrmThemeCoverageTest.php`

- [ ] 运行 `vendor\bin\phpunit --filter GlobalCrmThemeCoverageTest`，要求零失败。
- [ ] 运行受影响的前端 UI 回归测试和 `php artisan view:cache`。
- [ ] 启动本地 Laravel 服务，分别打开前台/后台 CrmUI 与 Layui 代表页。
- [ ] 在 1440×900 与 375×812 抽查 15 套切换、持久化、选择器同步、表格溢出、焦点和文本对比度。
- [ ] 检查控制台错误与关键元素重叠，记录无法执行的验证而不宣称通过。

