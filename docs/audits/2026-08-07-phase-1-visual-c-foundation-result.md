# Phase 1 Visual C Foundation 验收报告

- 计划日期：2026-08-07
- 验收日期：2026-08-10
- 验收状态：Phase 1 通过（存在下述原始 SHA 证据边界）
- 当前项目：`D:\Software\PhpProject\Demo\co_crmv5`
- 样板路由：`/front/dashboard`、`/admin/users`、`/front-crmui/dashboard`、`/admin-crmui/users`

## 验收结论与范围

本报告是 **Phase 1 验收报告**。Phase 1 已完成以下范围：

- 建立 Layui 与 CrmUI 两个页面家族各自独立的 Visual C 基础，并保持家族资产隔离；
- 验收四个真实样板路由：前台 Layui dashboard、后台 Layui users、前台 CrmUI dashboard、后台 CrmUI users；
- 建立共享主题层及四个布局的主题身份声明、light/dark 切换与恢复；
- 完成四个样板页的移动侧栏打开、关闭、遮罩关闭及可访问状态同步；
- 建立只读 visual audit fixture，用于浏览器证据采集，不触发业务写 API；
- 修复前台 Layui 资金流水隐藏 tab：active tab 使用 `config.url`，hidden tab 使用 `config.data = []`。

该结论只覆盖 Phase 1 Visual C Foundation，不表示全项目旧逻辑已百分百替换，也不表示后续业务核验已经完成。

## 证据边界与数据安全

### 原始 SHA-256 边界

- 本阶段新增文件在修改前不存在，因此没有“修改前文件 SHA-256”。
- 既有文件的原始 SHA-256 曾在中断会话中采集，但没有持久化到可信证据文件；中断后无法可信恢复。
- 本报告绝不反推或编造缺失的原始 SHA-256。下文完整记录当前验收状态下 30 个文件的最终 SHA-256，作为后续阶段的可重复基线。

### 数据与运行边界

- 验收服务身份为 `APP_ENV=testing`、MT4 disabled，运行配置仅指向 `co_crmv5_test`。
- 浏览器验收未主动提交表单，也未放行真实业务写 API。
- 本轮未连接旧库 `hank_zl_data` 或正式新库 `co_crmv5`，因此旧库与正式新库零写入。
- 未对 `co_crmv5_test` 执行 binlog 或验收前后快照审计，因此不声明测试库绝对零写入。
- 宿主环境的 Statsig 网络超时不属于项目页面 console warning/error，不计入项目控制台结果。

## 测试证据

九个测试文件均以 fresh 独立进程分开执行，退出码均为 `0`：

| 测试文件 | Tests | Assertions | Failures | Errors |
|---|---:|---:|---:|---:|
| `tests/Feature/VisualCFoundationContractTest.php` | 17 | 181 | 0 | 0 |
| `tests/Feature/VisualAuditFixtureTest.php` | 4 | 159 | 0 | 0 |
| `tests/Feature/CrmUiStackTest.php` | 6 | 500 | 0 | 0 |
| `tests/Feature/GlobalCrmThemeCoverageTest.php` | 25 | 3348 | 0 | 0 |
| `tests/Feature/DualUiFamilyDesignContractTest.php` | 5 | 35 | 0 | 0 |
| `tests/Feature/BladeOnlyFrontendArchitectureTest.php` | 2 | 13 | 0 | 0 |
| `tests/Feature/BladeLocalAssetReferenceTest.php` | 1 | 1 | 0 | 0 |
| `tests/Feature/LucideIconAndEmojiPolicyTest.php` | 6 | 809 | 0 | 0 |
| `tests/Feature/FrontUiRegressionTest.php` | 119 | 2366 | 0 | 0 |
| **合计** | **185** | **7412** | **0** | **0** |

每个文件使用 `vendor\bin\phpunit --colors=never tests\Feature\<测试文件>` 单独执行，不以聚合输出替代独立结果。

## 静态与构建验证

| 验证项 | 结果 |
|---|---|
| `php -l tests/Feature/VisualCFoundationContractTest.php` | 退出码 `0`，无语法错误 |
| `php -l tests/Feature/VisualAuditFixtureTest.php` | 退出码 `0`，无语法错误 |
| `php -l tests/Feature/GlobalCrmThemeCoverageTest.php` | 退出码 `0`，无语法错误 |
| `node --check public/js/apps/front/layui/layout.js` | 退出码 `0` |
| `node --check public/js/apps/admin/layui/layout.js` | 退出码 `0` |
| `node --check public/js/apps/crmui/front.js` | 退出码 `0` |
| `node --check public/js/apps/crmui/admin.js` | 退出码 `0` |
| `node --check public/js/apps/front/layui/pages.js` | 退出码 `0` |
| `node --check public/js/testing/visual-audit-fixture.js` | 退出码 `0` |
| `php artisan view:cache` | 退出码 `0` |
| `php artisan view:clear` | 退出码 `0` |
| 扫描 `public/css/layui/visual-c.css` 与 `public/css/crmui/visual-c.css` 中的 `linear-gradient|radial-gradient` | 退出码 `1`，无匹配 |

## 浏览器验收证据

### 页面与四视口共同结果

四个真实页面均在 `1440x900`、`1280x720`、`768x1024`、`390x844` 四个固定浏览器视口完成验收，共 16 个页面/视口组合：

- 页面级 overflow 全部为 `false`；
- 每页只加载自身 UI 家族 CSS，未交叉加载另一家族 Visual C CSS；
- `body` 的 `data-ui-family` 分别为 `layui`/`crmui`，`data-ui-surface` 分别为 `front`/`admin`，四页 `data-visual-direction="c"`，均与路由身份一致；
- `data-visual-audit-unknown-count=0`；
- 项目 console warning/error 均为空；
- light 主题 token 为 `#F7F9FC`，dark 主题 token 为 `#101722`；
- 四页均完成 dark/light 切换，并在验收后恢复为 light。

### 16 个页面/视口证据矩阵

| Route | Viewport | Overflow | Console warning/error | Own-family asset | unknownCount | Theme | Sidebar |
|---|---|---|---|---|---|---|---|
| `/front/dashboard` | `1440x900` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/front/dashboard` | `1280x720` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/front/dashboard` | `768x1024` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/front/dashboard` | `390x844` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `open/close/overlay/ARIA pass` |
| `/admin/users` | `1440x900` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/admin/users` | `1280x720` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/admin/users` | `768x1024` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/admin/users` | `390x844` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `open/close/overlay/ARIA pass` |
| `/front-crmui/dashboard` | `1440x900` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/front-crmui/dashboard` | `1280x720` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/front-crmui/dashboard` | `768x1024` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/front-crmui/dashboard` | `390x844` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `open/close/overlay/ARIA pass` |
| `/admin-crmui/users` | `1440x900` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/admin-crmui/users` | `1280x720` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/admin-crmui/users` | `768x1024` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `N/A` |
| `/admin-crmui/users` | `390x844` | `false` | `0/0` | `pass` | `0` | `light->dark->light (pass)` | `open/close/overlay/ARIA pass` |

### 前台 Layui dashboard iframe

`/front/dashboard` 使用同源 iframe。外层 shell 没有 Visual C 页面 marker 属于正常设计；同源 iframe `/front/dashboard?frame=1` 内证据为：

- `data-visual-c-reference=front-dashboard`；
- `data-layui-page=dashboard/index`；
- `overflow=false`；
- `unknownCount=0`。

### 移动侧栏

四个样板页在移动视口的共同证据：

- 打开后 `left=0`、`right=236`、`width=236`、`aria-expanded=true`；
- 关闭后 `open=false`、`aria-expanded=false`；
- 两个 CrmUI 页面关闭后额外为 `aria-hidden=true`；
- 遮罩关闭使用真实页面坐标 `(350,400)` 点击验证，四页均成功关闭。

## 截图证据

截图目录：`docs/audits/visual-c-phase-1`。共 16 张；逐文件 PNG 签名均为 `89504E470D0A1A0A`，汇总为 `PNG_COUNT=16`、`PNG_INVALID=0`。

| 文件名 | 浏览器视口 | PNG 实际像素 |
|---|---|---|
| `admin-crmui-users-1280x720.png` | `1280x720` | `1280x720` |
| `admin-crmui-users-1440x900.png` | `1440x900` | `1440x900` |
| `admin-crmui-users-390x844.png` | `390x844` | `380x1240`（full-page） |
| `admin-crmui-users-768x1024.png` | `768x1024` | `768x1024` |
| `admin-layui-users-1280x720.png` | `1280x720` | `1280x720` |
| `admin-layui-users-1440x900.png` | `1440x900` | `1440x900` |
| `admin-layui-users-390x844.png` | `390x844` | `390x843` |
| `admin-layui-users-768x1024.png` | `768x1024` | `768x1024` |
| `front-crmui-dashboard-1280x720.png` | `1280x720` | `1280x720` |
| `front-crmui-dashboard-1440x900.png` | `1440x900` | `1440x900` |
| `front-crmui-dashboard-390x844.png` | `390x844` | `380x1000`（full-page） |
| `front-crmui-dashboard-768x1024.png` | `768x1024` | `768x1024` |
| `front-layui-dashboard-1280x720.png` | `1280x720` | `1280x720` |
| `front-layui-dashboard-1440x900.png` | `1440x900` | `1440x900` |
| `front-layui-dashboard-390x844.png` | `390x844` | `390x843` |
| `front-layui-dashboard-768x1024.png` | `768x1024` | `768x1024` |

PNG 实际像素与浏览器视口不同不表示视口配置发生变化：两个 CrmUI 移动截图使用 full-page 模式，PNG 记录浏览器返回的可截图内容宽度与完整文档高度，因此分别为 `380x1240` 和 `380x1000`；两个 Layui 移动截图使用视口截图，浏览器返回的实际可截图高度为 `843`，因此为 `390x843`。浏览器验收视口仍统一设置为 `390x844`。

## 业务钩子与资产契约

- 前台 Layui dashboard 保留 `data-layui-page="dashboard/index"` 及原有元素 ID，包括 dashboard 指标、图表、下载、分享、新闻和路由数据容器所用 ID；Visual C 只增加样板身份，不重命名业务挂载点。
- 后台 Layui users 保留 `userSearchForm`、`userTable`、既有字段、API、权限及操作钩子。
- 前后台 CrmUI 通用模块页保留 `data-crmui-page`、`data-crmui-table-body`、`data-crmui-action-modal`，以及 API、method、field、column、action、permission、form、modal 数据属性。
- 前台 Layui 资金流水 active tab 继续使用 `config.url`，hidden tab 使用 `config.data = []`，避免隐藏 tab 误发请求。
- `public/js/apps/front/layui/pages.js` 版本为 `2026081001`；前后台 Layui `layout.js` 版本均为 `2026080801`。
- 四个布局 CSS 加载顺序均为 `@yield('styles')` -> 自身家族 Visual C -> `theme-assets`。

## 最终 SHA-256

| 文件 | 最终 SHA-256 |
|---|---|
| `public/css/layui/visual-c.css` | `C2D5E366C6A6387C25BA0D9F0F74A09E85F7153C20089ECDE3B94F35A6131735` |
| `public/css/crmui/visual-c.css` | `03BECF95D8AB941C993CF7A213CE654AC982A3CE160D93F941202D9753E6E49D` |
| `public/css/common/crm-design-system.css` | `37B15FD24A43C92006AEC313DEC03FC30A3293D3B53DA0C3A85BCF32B7E6D094` |
| `public/css/common/crm-themes.css` | `D6487F67DF902E28D517C844986B694502E42913CAD8DF83E9B8B38C2AAB8B22` |
| `public/css/front/style.css` | `009630FC068CA6A6D0AE6FC2ACB9135ADD2AE4C08B734C2386A9FF86B91240C7` |
| `public/css/admin/style.css` | `29D808940B114AB393E082371D10402B42FED9CE7B70B4C0D040A4931577E20B` |
| `public/css/crmui/front.css` | `4D50C38109B947FBADD2E9B0D25730F8E018CD5D34A9EE13E4816C09EFC0E9EF` |
| `public/css/crmui/admin.css` | `A50ED08D18080FFFDE5E5D211B464ECD7CD9E8A0A7068BC054AA64BF2D987C47` |
| `resources/front/layui/layouts/app.blade.php` | `21E34658FC80CB057C7D85F9FD348E87A2039DA8E0860428FA499C8FDDA5CFD1` |
| `resources/admin/layui/layouts/app.blade.php` | `E472ED4DB73616E5F18598B6FF20D30ECE10EF9769C7B739968E79054BBB4915` |
| `resources/front/crmui/layouts/app.blade.php` | `CF6888D4B58A9025223D8C26B8B6944B53EC5FCB2A3FF84CCBEB804B77C2B221` |
| `resources/admin/crmui/layouts/app.blade.php` | `08494CFD9A9D40B78751742FB9C0F39C5DFF7831A163CABC7250E823F979C95C` |
| `resources/front/layui/dashboard/index.blade.php` | `DD679D7D9B64CB4454E43CAE2AB3A3BCA760A2603D9921952F3000FEFBB1DF3F` |
| `resources/admin/layui/users/index.blade.php` | `A71A995CF38CCCFFE094E79434C13DF1C3E10951DC57555447FBC7B25213EE93` |
| `resources/front/crmui/partials/module-page.blade.php` | `902399ABCE3E5A876B14B6B5D4C43E3954DCF7CF560E2D08D17A8834FF231352` |
| `resources/admin/crmui/partials/module-page.blade.php` | `47943224D16276AE6BFD8EA2D977C18785751C18C2070AE910B8318EE79642D6` |
| `resources/views/partials/theme-assets.blade.php` | `A1356772C5FAF3A729505C6C2801CCDFB59BF33B361262784BE42BDCD606ACDC` |
| `resources/views/partials/theme-picker.blade.php` | `6435A837C3B957C78866E5C7BAFCF98F978BDCAC5D3FC864AAD8DC928B77F214` |
| `resources/views/partials/visual-audit-fixture.blade.php` | `7590F854E8FF36E1F7AA623B2EF660C9004B517E9DD0054E45C208086B249414` |
| `public/js/apps/front/layui/layout.js` | `1E5B88D3339C625C177051A5EE350A31BCAB0F27E754657414F2D16DD055F8A4` |
| `public/js/apps/admin/layui/layout.js` | `B19A074682310C0335F88EE989CDA08F32AE1500E03835765FEF5DF2FDF3732D` |
| `public/js/apps/front/layui/pages.js` | `78A563088DA35BC0C5E65914C73316C6881902B4A244A99DABF0DD5683BAEE11` |
| `public/js/apps/crmui/front.js` | `9C5A4C899EA7F3F3EE76E1AEB7928C4B99A65762998B8F671FC91B6E9973B0C9` |
| `public/js/apps/crmui/admin.js` | `0833F3920E8F3898715618EF9549E82066E1F9868C9C05B3187A1A1E4800E360` |
| `public/js/shared/theme-sync.js` | `B69D81E0DE5D21BB8CAFB5C26318F1543ED6CABD087BF8DA3387FAF148125CB8` |
| `public/js/testing/visual-audit-fixture.js` | `81F10970683C75ED53C2B9990FDF456392D3CE89D5EE545CF2EEC3E7C19148E2` |
| `tests/Feature/VisualCFoundationContractTest.php` | `F4D88A86C0FCA7A89BE5B50AB228759073B962F8FEC396198668F6AC2EAF09E9` |
| `tests/Feature/VisualAuditFixtureTest.php` | `F8E52FA9DF2FDDE9F0E524424C63D2558FFF55D0A072AE011F8D80B416D1F0D9` |
| `tests/Feature/CrmUiStackTest.php` | `A2EA7FCC6EDEAC6C8AF2ED20475326FF940F203AFE3723C88CD2C5CA006BAD46` |
| `tests/Feature/GlobalCrmThemeCoverageTest.php` | `CCA9493D2D144E38FDE7D3CEEB30B9B0539DC2C3F2557C8CDDD31F42F09DA2B1` |

## 未关闭项与 Phase 2 输入

- Phase 2 仍有约 290 个业务核验项，需要继续按旧项目逐模块对照处理。
- Phase 1 完成只证明 Visual C 基础、四个样板路由及上述契约通过，不等于总目标完成。
- 原始 SHA-256 缺失边界保留在本报告中；后续阶段应以前述 30 个最终 SHA-256 作为可信起点。

## 完成判断

- 两个家族只加载自身 Visual C 资产；
- 四个真实样板路由的字段、API、权限和 DOM/数据属性业务钩子保持；
- 目标测试为 185 tests、7412 assertions、0 failures、0 errors；
- 四页四视口无页面级横向溢出，项目控制台无 warning/error；
- 四页移动侧栏可打开、关闭，并同步可访问状态；
- 运行配置仅指向 `co_crmv5_test`；本轮未连接 `hank_zl_data` 或 `co_crmv5`，因此旧库与正式新库零写入；未对测试库执行 binlog 或前后快照审计，不声明测试库绝对零写入；
- 16 张截图均为有效 PNG，且文件名、视口和实际像素已完整记录。

据此，Phase 1 Visual C Foundation 验收通过。该结论不扩展到 Phase 2 约 290 个业务核验项，也不构成全项目旧逻辑百分百替换完成的声明。
