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
