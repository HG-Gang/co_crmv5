# Dashboard Shell UX Round 结果审计

> 实施日期：2026-08-17 至 2026-08-18  
> 结果状态：`DONE_WITH_CONCERNS`  
> 唯一未完成项：`BLOCKED_BY_BROWSER_POLICY`（四视口运行时验收与截图）

## 一、范围

本轮覆盖四套主壳共享语言/主题入口，以及前台 Layui 两份 Dashboard 的平面化、真实周期统计、图表查看方式和响应式控件：

- 语言与主题入口统一为 Lucide 图标下拉，当前项同步 check、高亮、`aria-current` 和 `aria-checked`。
- Dashboard 移除渐变和装饰圆球，清理无消费者的 `--front-hero-gradient`、`--front-control-gradient`、`--front-panel-glass`。
- `GET /api/front/dashboard` 严格接受 `days=7|15|30`，默认 30；入金、出金、返佣、开仓和平仓统一使用真实周期窗口。
- 两份 Layui Dashboard 均提供 7/15/30 天分段按钮；四个图表均支持柱状图、折线图、面积图和饼图图标切换。
- 图表按钮固定 44 x 44px，具备 `title`、`aria-label`、屏幕阅读器文本和 `aria-pressed`。
- 快速切换周期时使用递增请求序号忽略过期响应，防止旧响应覆盖新数据。
- ECharts 系列色、文字、轴线、分割线和 tooltip 改为读取当前 `--front-*` 主题 token。
- 周期文案改为“当前周期 / Current Period”，避免 7/15 天结果仍被误标为固定 30 天。

## 二、TDD 证据

- 周期 API RED：`FrontDashboardRangeClosureModuleTest` 初始 `5 tests / 11 assertions / 5 failures`，失败点为缺少 `period.days` 且非法 `days` 未拒绝。
- 图标和周期控件 RED：专项 `1 test / 1 assertion / 1 failure`，失败点为页面无 `data-dashboard-range="7"`。
- 动态周期文案 RED：专项 `1 test / 1 assertion / 1 failure`，失败点为中文仍显示“近 1 个月”。
- 完成后的 `DashboardShellUxRoundClosureModuleTest`：`5 tests / 106 assertions / exit 0`。
- 完成后的 `FrontDashboardRangeClosureModuleTest`：`5 tests / 15 assertions / exit 0`。

## 三、目标回归

2026-08-18 重新逐文件串行执行，避免 PHPUnit 多路径参数只运行首个文件：

| 测试 | 结果 |
| --- | ---: |
| `DashboardShellUxRoundClosureModuleTest` | 5 tests / 106 assertions |
| `FrontDashboardRangeClosureModuleTest` | 5 tests / 15 assertions |
| `GlobalCrmThemeCoverageTest` | 25 tests / 3468 assertions |
| `FrontUiRegressionTest` | 141 tests / 2985 assertions |
| `UnifiedBladeDesignSystemTest` | 6 tests / 65 assertions |
| `VisualCFoundationContractTest` | 18 tests / 184 assertions |
| **合计** | **200 tests / 6823 assertions / 0 failures / 0 errors** |

额外兼容回归：

- `FrontLegacyDashboardWiringClosureModuleTest`：`6 tests / 68 assertions / exit 0`。
- `DashboardControllerCommentReadabilityTest`：`1 test / 15 assertions / exit 0`。
- `FrontUiRegressionTest --filter dashboard`：`12 tests / 211 assertions / exit 0`。

## 四、语法与 Blade

- `php -l app/Http/Controllers/Front/DashboardController.php`：无语法错误。
- `node --check public/js/shared/theme-sync.js`：exit 0。
- `node --check public/js/shared/preference-menu.js`：exit 0。
- `node --check public/js/apps/front/layui/pages.js`：exit 0。
- `php artisan view:cache`：Blade templates cached successfully。
- `php artisan view:clear`：Compiled views cleared。

## 五、浏览器验收状态

- 隔离服务成功启动于 `127.0.0.1:8098`，进程级覆盖 `APP_ENV=testing`、`DB_DATABASE=co_crmv5_test`，未连接正式库执行写操作。
- 浏览器在导航到本地地址前被运行环境安全策略拒绝，错误分类为 `BLOCKED_BY_BROWSER_POLICY`。
- 安全策略明确禁止改用其他浏览器自动化表面绕过，因此未执行 `1440x900 / 1280x720 / 768x1024 / 390x844` 四视口检查，也未生成截图。
- 隔离服务已停止；复核 `56624` 与 `8098` 均无监听。
- 因缺少运行时视觉证据，不将静态测试、Blade 编译或浏览器启动表述为四视口验收通过。

## 六、残余风险

1. 四视口下的横向溢出、文字重叠、菜单定位、实际触控尺寸和图表画布非空仍需浏览器授权后验证。
2. 主题/语言菜单的点击、Escape、焦点恢复以及 7/15/30 快速切换虽有静态契约与 API 测试，仍缺真实浏览器交互证据。
3. 本轮不改变迁移矩阵 394/475 的业务核验计数；Dashboard Shell UX 是 UI/API 增强，不冒充剩余旧路由业务等价核验。

