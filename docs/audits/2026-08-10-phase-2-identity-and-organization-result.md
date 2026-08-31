# Phase 2 身份与组织结果审计报告

## 1. 报告结论

**状态：DONE_WITH_CONCERNS（自动化门禁通过，Task 10 浏览器终态证据待补）**

截至 2026-08-17，Phase 2 身份与组织范围为 **184/184 verified**，全部行均有
非空显式 `verification_group`。最新 Phase 2 门禁为 **19 tests / 426 assertions / exit 0**；
最新全量串行 PHPUnit 为 **3641 tests / 71097 assertions / exit 0**。

全项目矩阵当前为 **365/475 verified**，仍有 **110 条
`needs_manual_business_review`**。因此，本报告只证明 Phase 2 的业务与自动化门禁已经
闭环，不把 184/184 等同于全项目 100% 完成。

Task 10 的新一轮浏览器验收尚未关闭：隔离测试服务器正常监听
`127.0.0.1:56624`，但当前浏览器安全策略拒绝自动访问该本地地址。本报告不以静态测试
替代真实浏览器截图，也不复用旧截图冒充本轮证据。完成四视口浏览器矩阵前，Phase 2
保持 `DONE_WITH_CONCERNS`。

## 2. 审计边界与运行配置

| 项目 | 路径或配置 | 约束 |
| --- | --- | --- |
| 项目 1 | `D:\Software\PhpProject\Demo\new_co_gmtk_crmv3` | 永久只读 |
| 项目 2 | `D:\Software\PhpProject\Demo\co_crmv5` | 当前工作区 |
| 旧正式库 | `hank_zl_data@127.0.0.1:3307` | 永久只读 |
| 新正式库 | `co_crmv5@127.0.0.1:3307` | 不迁移、不重建、不写入 |
| 测试库 | `co_crmv5_test@127.0.0.1:3307` | PHPUnit 与隔离测试服务器唯一允许写入的库 |
| 测试环境 | `APP_ENV=testing` | `phpunit.xml` 强制 |
| MT4 | `MT4_ENABLED=false` | 禁止真实连接 |
| MT4 用户同步 | `MT4_USER_SYNC_ENABLED=false` | 禁止真实同步 |

`phpunit.xml` 和 `scripts/run-full-serial.ps1` 均固定上述测试库与 MT4 禁用值。当前独立
服务器进程命令为 `php -S 127.0.0.1:56624 server.php`；浏览器验收结束后必须停止该
进程并确认端口无监听。

## 3. 当前矩阵

| 指标 | 数量 |
| --- | ---: |
| 总条目 | 475 |
| `verified` | 365 |
| `needs_manual_business_review` | 110 |
| `unresolved_legacy_source` | 0 |
| `unmatched_current_route` | 0 |

Phase 2 精确范围由计划中的 16 个旧 Controller 加 7 个 `BigNumberController` 身份壳层
入口组成，统计为 184/184 verified、0 空分组。剩余 110 条主要属于账户交易、资金、
返佣结算和运营配置，必须继续逐路由核验。

## 4. Phase 2 的 23 个显式证据分组

| `verification_group` | 路由数 |
| --- | ---: |
| `admin_legacy_administrators_regression_2026_08_10` | 8 |
| `admin_legacy_agent_edit_save_2026_07_29` | 1 |
| `admin_legacy_agent_examine_search_2026_07_29` | 1 |
| `admin_legacy_agent_management_2026_08_16` | 9 |
| `admin_legacy_agent_statistics_2026_07_29` | 4 |
| `admin_legacy_agent_update_debug_2026_07_29` | 1 |
| `admin_legacy_authentication_voucher_2026_08_16` | 14 |
| `admin_legacy_customer_group_approval_2026_08_15` | 2 |
| `admin_legacy_customer_management_2026_08_16` | 10 |
| `admin_legacy_group_config_2026_08_16` | 4 |
| `admin_legacy_login_session_2026_08_10` | 4 |
| `admin_legacy_profile_session_2026_08_10` | 6 |
| `admin_legacy_roles_permissions_2026_08_10` | 6 |
| `admin_legacy_user_group_2026_08_16` | 8 |
| `front_auth_register_forgot_2026_07_26` | 17 |
| `front_dashboard_hot_news_2026_07_26` | 2 |
| `front_disabled_maintenance_2026_07_26` | 12 |
| `front_legacy_direct_customer_module_2026_07_29` | 8 |
| `front_legacy_profile_action_pages_2026_07_29` | 16 |
| `front_profile_relationship_2026_08_15` | 3 |
| `phase2_big_agent_identity_2026_08_16` | 17 |
| `phase2_front_proxy_scope_2026_08_16` | 11 |
| `phase2_front_user_shell_profile_2026_08_16` | 20 |
| **合计** | **184** |

每组证据均记录 `legacy_behavior`、`route_mapping`、`backend_logic`、
`frontend_contract`、`auth_and_scope`、`validation_and_errors`、`automated_tests`
七个维度，并绑定精确 HTTP 方法、URI、当前 route name/action 和实际测试文件。

## 5. 自动化验证证据

### 5.1 本轮新鲜验证

| 命令/测试集 | 结果 | 退出码 |
| --- | ---: | ---: |
| `Phase2IdentityOrganizationMatrixClosureTest.php` | 19 tests / 426 assertions | 0 |
| `Phase2CrmUiAgentHierarchyInteractionContractTest.php` | 10 tests / 51 assertions | 0 |
| `Phase2CrmUiPermissionContractTest.php` | 3 tests / 43 assertions | 0 |
| `CrmUiStackTest.php` | 6 tests / 508 assertions | 0 |
| `VisualAuditFixtureTest.php` | 4 tests / 159 assertions | 0 |
| `UnifiedBladeDesignSystemTest.php` | 6 tests / 65 assertions | 0 |
| `GlobalCrmThemeCoverageTest.php` | 25 tests / 3463 assertions | 0 |
| `LegacyUiReplacementCoverageTest.php` | 64 tests / 451 assertions | 0 |
| `FrontUiRegressionTest.php` | 141 tests / 2978 assertions | 0 |
| `VisualCFoundationContractTest.php` | 18 tests / 184 assertions | 0 |

四个本轮关键 PHP 测试文件均执行 `php -l`，结果均为 `No syntax errors detected`，
退出码均为 0。

### 5.2 专项和全量回归

- Phase 2 权限与组织门禁：19 tests / 426 assertions。
- 新闭环分组完整专项：250 tests / 4923 assertions。
- 订单与上传三组串行：14 tests / 76 assertions。
- 订单双进程并行稳定性：连续 5 轮，每轮 10 tests / 55 assertions，全部退出码 0，
  未再出现 MySQL 1213 deadlock。
- 全量串行：3641 tests / 71097 assertions，耗时 16:06.388，退出码 0。

全量日志：

- `storage/logs/full-serial-20260817-113701.out`
- `storage/logs/full-serial-20260817-113701.err`
- `storage/logs/full-serial-20260817-113701.exit`

stderr 仅有 4 条非致命 `libpng iCCP: known incorrect sRGB profile` 警告；PHPUnit
stdout 明确记录 `OK (3641 tests, 71097 assertions)` 和 `PHPUNIT_EXIT=0`。

## 6. Task 10 页面范围与浏览器矩阵

### 6.1 已提取页面范围

后台 Layui/CrmUI 双入口覆盖：登录、管理员、角色、权限、菜单、数据范围、客户/用户、
代理、大代理、实名认证、代理等级、用户组/组配置、黑名单、本人资料、改密和退出。

普通前台 Layui/CrmUI 双入口覆盖：登录、注册、忘记密码、资料/实名认证、资料编辑、
改密、代理下级、直属客户、等级确认、客户转组、现代大号代理登录、账号注销申请和退出。

Layui-only 的旧兼容入口还必须单独验收：`user/login`、`user/index/login` 使用
`auth/login_v2.blade.php`；三组旧注册链接使用 `auth/register_v2.blade.php`；
`user/center` 使用 `profile/index_v2.blade.php`；身份证、银行卡、换卡、头像、联系方式和
`user/editpsw` 动作页使用 `profile/legacy-action.blade.php`。旧退出入口为
`user/loginOut`。后台旧 Session 别名至少包括 `index/admin/login`、`userinfo`、
`userpwd` 和 `logout`，不能用现代 `/admin/*` 入口替代其验收证据。

独立大代理会话覆盖：Layui 旧入口 `agents/login` 与 `user/agents/*` 壳层，以及 CrmUI
`front-crmui/big-agent/*` 登录、仪表盘、代理/仓位/订单列表、改密和退出。

边界说明：

- 用户组与组配置共用 `group-configs` 页面，通过 `category=1/2` 区分代理组与用户组。
- 普通前台代理层级没有独立 Blade。Layui 的 `agent/sub` 包含层级行操作；CrmUI 的
  `agent/sub` 现已声明“直属代理/直属客户”两个 `rowActions` 链接，通用 CrmUI 行链接
  渲染器按 `user_id` 替换 `__ID__`，并由 `PageController` 对 `agent/sub` 与
  `agent/customers` 严格校验正整数 `parent_id`、强制 `direct_only=1`。该闭环由
  `Phase2CrmUiAgentHierarchyInteractionContractTest.php` 覆盖；后端 API 仍负责登录身份、
  代理树归属和直属范围校验。
- `visual_audit=1` 只在 testing 的普通 app layout 生效；其 API fixture 完整白名单主要是
  后台用户/菜单与前台资料/导航/仪表盘。fixture 不支持任何真实写交互；其他页面只能
  验收壳、错误态和失败关闭，不能把 fixture 拒绝响应写成真实业务数据成功。登录、
  注册、忘密、退出、CRUD、审核、改密、注销、层级和全部 legacy/big-agent 动作仍需
  隔离认证会话，详情页还需范围内测试 ID。

### 6.2 必须完成的浏览器门禁

每个 Phase 2 canonical 页面需要在 `1440x900`、`1280x720`、`768x1024`、
`390x844` 下检查：

- HTTP 正常，控制台 0 application error；
- 页面级无横向溢出、无不合理重叠、文字和操作不被遮挡；
- 可见键盘焦点，主要触控目标至少 44px；
- 表格/表单的 loading、empty、error、disabled 状态可辨识；
- 两套壳在移动视口完成侧栏打开、关闭、遮罩、Escape 和焦点恢复；
- 登录、注册、忘记密码、资料、改密、注销等表单以客户端无效输入验证错误与禁用态，
  不提交会写库的有效业务请求；
- 截图写入新的 Phase 2 审计目录，并记录 URL、family、视口、PNG 像素与文件有效性。

### 6.3 当前浏览器证据状态

旧一轮仅确认 `localhost:56623` 的前后台登录页桌面/390px 布局和后台移动侧栏基本交互；
覆盖面不足，不能作为 Task 10 终态证据。

本轮已启动隔离测试服务器 `127.0.0.1:56624`，但浏览器安全策略拒绝自动导航至该本地
地址。当前没有生成或声称生成新的 Phase 2 截图，浏览器矩阵状态为 **BLOCKED_BY_BROWSER_PERMISSION**。

## 7. 测试隔离与 CrmUI 层级闭环修复

此前三个测试文件移除了固定 ID 和事务内宽范围 DELETE：

- 订单测试改用 `1,000,000,000~1,900,000,000` 范围内查重 ID/订单号；
- 上传测试保留真实 `legacy.front.auth` 会话闭环，使用 `DatabaseTransactions`、大范围
  查重 ID 和最小直接插入；
- 不再调用会预先删除相同 ID 数据的 fixture helper。

该修改针对并发测试死锁根因，不改变业务生产路径，也不通过重跑或放宽断言掩盖失败。

本轮补齐 CrmUI 普通代理层级的生产闭环：

- `PageController` 仅对 `agent/sub` 与 `agent/customers` 接受正整数 `parent_id`，并强制 `direct_only=1`；
- `agent/sub` 声明直属代理/直属客户两个行链接，`rowActions()` 透传 `href` 供通用 Blade/JavaScript 行链接渲染；
- 中英文 `crmui` 语言包补齐 `direct_agents` 与 `direct_customers`；
- 回归测试显式通过 `X-Locale: zh-CN` 固定文案语言，并对两个入口完成正数、非法值、越权忽略和链接契约验证。

## 8. 关键文件 SHA-256

| 文件 | SHA-256 |
| --- | --- |
| `tests/Feature/FrontUploadSessionCompatibilityTest.php` | `F3796AE69AA0FA89EFD6257BEF63F68BFAE2090D39DB630BFD4C86B85ACD5423` |
| `tests/Feature/FrontOrderDetailOwnerBoundaryClosureModuleTest.php` | `C4C745C764DA3A605C1F2B8B2D8BF3A9A68771FE9DCFF6E4DB2CBBB068510BF9` |
| `tests/Feature/FrontOrderOwnScopeOwnerBoundaryClosureModuleTest.php` | `84D21A644ADE26C8D15A5BA2B70C1D0A9C37FE18AC19377A152DF25738E12749` |
| `tests/Unit/Phase2IdentityOrganizationMatrixClosureTest.php` | `58CA7692AF1CEBB067A26E9B4DEF710EFAB0C8429E8812375951363A3875D266` |
| `app/Http/Controllers/CrmUi/Front/PageController.php` | `32B2379FC072DD033675D9174CE5425286D77CC4C3118F172B32454F71D2AA2C` |
| `tests/Feature/Phase2CrmUiAgentHierarchyInteractionContractTest.php` | `47E1228FDC8EB0AF8881FE9393620DB5113C70AE5A431FC7FC3D971F9CF400C1` |
| `resources/lang/zh-CN/crmui.php` | `5EE932B28A18F566937933022ACDEBD43478A3F7485AC2EF49E85A3FA443AC61` |
| `resources/lang/en/crmui.php` | `7838DF8010E8C63EE7F4BB4B5E31DDDAF0A0F823FE4116EC5DE435C7449CF385` |
| `docs/audits/旧项目路由核验证据.json` | `FD08F3593C45AB2369904CAFE72A8CE9DCC14162790B39296826F504E39AAB26` |
| `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json` | `0B9BBCCADD11E5AB406F3D5ECD5719F40579DD2B0329134EA735D53888261129` |
| `docs/audits/旧项目模块逻辑迁移核验矩阵.md` | `2EE108DDA79065519DDA51EDFFC0380C007B1F848F0460EFE7006F969BEEDF19` |

## 9. 剩余工作与关闭条件

1. 获得浏览器对 `127.0.0.1:56624` 的访问授权，完成 Task 10 四视口与交互矩阵，保存并
   校验新 PNG；若发现真实 UI 缺陷，先补失败契约，再做最小 Blade/CSS/JavaScript 修复。
2. 浏览器通过后停止测试服务器 session，确认 56624 无监听。
3. 将 Task 10/11 的完成勾选、截图矩阵、最终修改文件与 SHA 回写本报告。
4. 继续全项目剩余 110 条路由；当前数量最多的控制器为 `WithdrawAmountController` 19、
   `WithdrawStatusController` 12、`BigNumberController` 10、`FengXianManageController` 9、
   `NewsInfoController` 7，其余按 Phase 3~6 业务域逐批闭环。

上述第 1~3 项完成前，不宣称 Phase 2 Task 10/11 全部完成；110 条全部关闭前，不宣称
新项目已经百分百替换旧项目。
