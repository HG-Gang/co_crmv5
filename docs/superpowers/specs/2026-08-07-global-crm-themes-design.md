# COCRM 全局主题设计规范

## 背景

COCRM 是面向 MT4/外汇交易运营的 Laravel 8 CRM。界面以资金、持仓、客户、代理、审核和风险数据为主，属于高频、高密度的工作台，而不是营销网站。主题必须优先保证扫读效率、状态识别和长时间使用的舒适度。

## 已批准范围

保留现有 `light`、`dark`、`sea`、`warm`、`contrast`，额外实现以下 10 套清爽主题，最终共 15 套：

1. `clear-blue` 晴空蓝
2. `mint-ledger` 薄荷账本
3. `cloud-minimal` 云灰极简
4. `sea-salt` 海盐青
5. `indigo-order` 靛蓝秩序
6. `coral-note` 珊瑚提示
7. `celadon-ops` 青瓷运营
8. `sunlit-mark` 日光标记
9. `steel-table` 银蓝表格
10. `ink-sidebar` 墨青侧栏

覆盖 `resources/admin` 与 `resources/front` 下 CrmUI、Layui 的全部 Blade 页面，包括主布局、认证布局、独立认证文档和两套大代理旧布局。

## 视觉原则

- 每套主题使用中性色工作区与单一强调色，避免多色疲劳。
- 正常正文与表面颜色对比度不低于 4.5:1；焦点、边框和交互状态在各主题下均清晰可见。
- 红色只表达高风险、错误或破坏性操作；黄色只表达待审与警告。
- 完成、在线、同步和普通信息状态跟随当前主题强调色，状态同时保留文字或图标，不只依赖颜色。
- 主题差异同时来自圆角、边界、阴影、侧栏宽度、导航选中方式和表格密度，而不是堆叠更多颜色。
- 动效限于 150–200ms 的颜色、边框和阴影过渡，并尊重 `prefers-reduced-motion`。

## 架构

`config/crm_themes.php` 是主题键、显示名和视觉令牌的唯一目录。`partials/theme-assets.blade.php` 把合法主题键注入页面并加载共享脚本与最后覆盖的主题 CSS；`partials/theme-picker.blade.php` 提供可访问的原生选择器。

`public/js/shared/theme-sync.js` 是唯一状态源。它读取历史键以兼容旧用户，但只写 `front_theme`、`crm_theme` 和 `crm_color_mode`，统一设置 `data-front-theme`、`data-crm-theme` 与 `data-crmui-theme-mode`，并同步页面上的全部选择器。CrmUI 不再读写 `localStorage.crmui_theme`。

`public/css/common/crm-themes.css` 只消费语义令牌并覆盖两套 UI 家族。组件页面不写主题专属颜色，从而让 173 个 Blade 页面通过布局继承自动覆盖。

## 十套视觉参数

| 主题 | 强调方式 | 几何与密度 |
| --- | --- | --- |
| 晴空蓝 | 导航左侧选中线、指标顶部线 | 6px 圆角，236px 侧栏 |
| 薄荷账本 | 浅填充侧栏、柔和层级 | 8px 圆角，244px 侧栏 |
| 云灰极简 | 黑灰文字与细边界 | 2px 圆角，216px 侧栏，无阴影 |
| 海盐青 | 顶栏强调线 | 8px 圆角，232px 侧栏 |
| 靛蓝秩序 | 面板左侧强调边 | 6px 圆角，246px 侧栏 |
| 珊瑚提示 | 标题与面板标题强调线 | 8px 圆角，238px 侧栏 |
| 青瓷运营 | 清晰边界、低阴影 | 4px 圆角，252px 侧栏 |
| 日光标记 | 标记式导航选中 | 4px 圆角，228px 侧栏 |
| 银蓝表格 | 表格优先、直角结构 | 0px 圆角，224px 侧栏，38px 行高 |
| 墨青侧栏 | 深色侧栏、浅色工作区 | 6px 圆角，240px 侧栏 |

## 交互与兼容

- 主题选择器使用 `<label>` 与 `<select>`，支持键盘、屏幕阅读器和 15 个选项的快速扫描。
- 切换后立即应用、持久化并广播 `crm:theme-change`；同页多个选择器与其他标签页同步更新。
- 旧主题别名继续映射至现有 5 套主题；非法值回退 `light`。
- `dark` 继续设置深色 `color-scheme`，其余主题使用浅色表单控件。
- 375px 下侧栏沿用现有抽屉行为，主题选择器不可造成顶栏横向溢出。

## 验收

- 配置恰好包含 15 个唯一主题键，10 个新增主题的正文、弱文本、强调文字均满足 WCAG AA。
- 所有完整 HTML 入口加载共享主题资产；可交互主布局显示 15 项选择器。
- 两套 dashboard 不再维护仅含 5 项的局部主题菜单。
- 代码库不再存在 CrmUI 独立 `crmui_theme` 存储逻辑。
- 目标 PHPUnit、Blade 编译通过，并在 CrmUI/Layui、前台/后台、桌面/移动视口实际切换抽样验证。

