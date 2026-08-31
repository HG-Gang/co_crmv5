# 旧项目 → 新项目 全模块对照审计报告（2026-08-29）

> 目的：按用户要求做一次独立于既有矩阵的「项目1 每个模块 → 项目2 前后端四套 UI」全量对照，
> 同时落地 `docs/中文注释标准-v0.0.3.md` 的机器可校验工具链并修复注释欠账。
> 工具：`scripts/audit-module-parity.php`（方法级+视图级清点）、`scripts/classify-unmapped-methods.php`
> （未命中定性）、`scripts/audit-old-routes-coverage.php`（路由行级覆盖）、
> `tools/add_file_headers.php` + `tools/audit-members.php`（注释标准 §4/§5）。

---

## 一、后端维度：旧控制器方法 → 核验矩阵（本轮发现并修复 1 处账本缺行）

| 清点项 | 数量 | 说明 |
| --- | ---: | --- |
| 旧项目控制器公共方法（排除构造/析构） | 439 | 遍历 `new_co_gmtk_crmv3/app/Http/Controllers` 全部 PHP |
| 其中被旧路由引用的「活动方法」 | ~373 | 与 `app/Http/routes.php` + `admin.php` + `routes-admin.php` 交叉 |
| 核验矩阵行（补记后） | **476**（原 475） | 方法级 HTTP 行，`verified=476` |
| 矩阵中未命中的旧公共方法 | 66 | 全部为 `DEAD-IN-OLD`（详见下），0 个活动方法缺口 |

**本轮唯一真实发现（已修复）**：旧项目 `routes.php:35` 注册的 `POST test/withdraw`（HelloWordController@withdraw）
未进入核验矩阵——同组兄弟路由 test/deposit、test/helloRegister、test/getAccountInfo 早在
`front_disabled_maintenance_2026_07_26` 组核验，唯此条漏记。新项目侧代码（`routes/web.php:293`
→ `LegacyMaintenanceController@testWithdraw`）与运行时测试（`FrontLegacyMaintenanceRuntimeClosureModuleTest`
的数据提供器）早已覆盖，属**矩阵记账缺行而非代码缺口**。已同步补记三处账本：

- `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`：476 行 / 476 verified；
- `storage/app/audits/legacy-routes.json`：396 条底稿；
- `docs/audits/旧项目路由核验证据.json`：维护组 17→18 条；
- `storage/app/audits/current-legacy-route-audit.json`：补 matched 映射；
- 矩阵 MD 再生成（替换字符 0）；15 个矩阵门禁的 475 基线全部更新为 476 并全绿
  （`MatrixClosure` 50 tests / 3420 assertions）。

**66 个 DEAD-IN-OLD 方法分类**（旧项目无任何路由指向，不构成新项目缺口）：
- 旧 resource 风格 CRUD 残留：`AgentControllerV3@edit/update/destroy`、`GroupConfigController@index/bind/unbind/destroy`、
  `LoginController@create/edit/update/destroy`、`RoleController@destroy` 等（旧项目无 `Route::resource` 注册，已核实）；
- 支付 SDK/配置内部方法：`PayConfigController` 的 merId/key/notifyUrl 系列、`TigerpaySDK`、`TrustpayApi` RSA、
  `WpPay` 配置访问器（支付流量的活动入口已按支付族核验闭环）；
- 抽象基类/框架辅助：`Abstract_Basic_Controller@parentPath(V2)`（具体路由版本已在矩阵）、
  `MY_Controller@_exte_*` 分页助手、`Controller@json`；
- 历史死代码：`RegisterController@enIndexGmtk/mt4UnlockUserByImportAgents/updateAgentsName`、
  `User\LoginController@test_sms`、`PositionSummaryController@positionSummarySearchV3/realTimerealTime` 等。

## 二、前端维度：旧视图 → 新项目四套 UI 家族

| 旧项目（单 UI） | 新项目（四家族） |
| --- | --- |
| `resources/views/admin/**`（后台 Blade） | `resources/admin/layui`（39 模块目录）+ `resources/admin/crmui`（40 模块目录，配置驱动 `module-page` 局部） |
| `resources/views/user/**`（前台用户 Blade） | `resources/front/layui`（16 模块目录）+ `resources/front/crmui`（16 模块目录） |
| `errors/`、`mail/`、`welcome.blade.php` | 对应错误页/邮件视图保留 |

- 旧项目视图合计 223 个 Blade；新旧目录结构不同构（新项目按模块重组且 CrmUI 由
  `PageController` 模块配置渲染），因此视图级等价以**页面渲染契约门禁**锁定而非逐文件名对照：
  `LegacyUiReplacementCoverageTest`（64 tests / 455 assertions，逐模块断言两家族均输出
  `data-layui-page`/`data-crmui-page` 与业务 API）、`FrontUiRegressionTest`、`GlobalCrmThemeCoverageTest`、
  `UnifiedBladeDesignSystemTest`、`VisualCFoundationContractTest`——本轮头部插入后复跑全部通过（423 tests / 11341 assertions）。
- **四套 UI 家族逐模块覆盖表（2026-08-29 晚机械清点）**：全库 51 个模块键中，
  43 个 admin 模块在 admin_layui + admin_crmui 双家族齐全；9 个前台模块（account/agent/commission/
  deposit/flow/gift/order/position/withdraw）在 front_layui + front_crmui 双家族齐全；
  big-agent 在两套前台家族分别以 `legacy-big-agent`（layui 旧壳）与 `big-agent`（CrmUI）承载，
  与 §2.6 的 `/front-naive/big-agent/*` 路由族设计一致——**无单家族孤儿模块**。
  家族契约门禁合并复跑 `LegacyUiReplacement|CrmUiStack|FrontUiRegression|GlobalCrmTheme|DualUiFamily|
  UnifiedBlade|VisualC|BladeOnlyFrontend` 共 **268 tests / 7759 assertions 全绿**。

## 三、中文注释标准 v0.0.3 落地（本轮新增能力）

| 项 | 状态 |
| --- | --- |
| `tools/add_file_headers.php`（插入 + `--check`，LF/无 BOM） | ✅ 已实现并全量执行：**1483 个文件补齐 PhpStorm 头部**（PHP/Blade/JS），幂等复检 0 缺失 |
| `tools/audit-members.php`（§5.7 口径：成员注释必须含中文） | ✅ 已实现：1109 文件 / 1203 成员，初始缺失 881 |
| `composer run headers-check` / `audit-members` | ✅ 已接入 composer.json |
| 成员注释补齐 | ✅ 双子智能体并行完成（A：tests/Feature 596 处/128 文件；B：app/Unit/Support/database 285 处/118 文件），全库审计 **缺失 0** |
| 文件级功能说明补齐（§4.2 第二轮） | ✅ 双子智能体并行完成（A：tests/routes 335 文件；B：app/database/config 242 文件），宽措辞扫描 **缺失 0** |
| 配置键中文说明补齐（§6.1 第二轮） | ✅ B 智能体补 **595 键**（crm_themes 302、logging 114、database 42、app 39、trace 20、auth 16、cache/mail/queue/filesystems/broadcasting/hashing/captcha/sanctum 等），有序键值对比对 **0 差异**（键名/键值/结构未动） |
| `tests/Unit/MemberCommentCoverageTest`（§5.7 CI 门禁） | ✅ 已实现并纳入 Unit 套件（复用审计工具口径，断言退出码 0） |
| 插入与补齐后全量验证 | ✅ php -l 全量 0 失败、`config:cache`/`view:cache` 通过、JS `node --check` 通过；**逐文件隔离复跑 679/679 全绿**（193221 轮日志）；最终全量串行 **`OK (4306 tests, 80366 assertions)` / exit 0**（213 轮） |

**审计终值（本报告收尾时点）**：PhpStorm 头部 1483/1483、文件级功能说明 1160/1160（宽措辞口径）、
成员中文注释 1203/1203、config 键中文说明 912/912——四项机器审计全部清零。

**过程事故与修复（如实记录）**：首次用内联 PHP 编辑 composer.json 时，bash 转义把 `\n` 写成字面量导致 JSON 失效，
引发 `Application::getNamespace()` RuntimeException（168 个测试报错）。已定位并重写 scripts 区块恢复合法 JSON
（`composer validate` 通过），可读性门禁恢复全绿。教训：composer.json 等结构化文件一律用脚本文件修改并立即断言 JSON 可解析。
另：`TestRunnerContractTest` 的库名白名单断言被头部 `Project name co_crmv5.` 误触发，已修正为剥离 PHPDoc 头部块后再扫描
（安全意图不变：脚本可执行正文仍禁止引用生产库名）。

## 四、结论

- 后端：旧项目**全部活动路由方法**均已在矩阵闭环（补记 test/withdraw 后 476/476）；
  66 个未路由方法为旧项目死代码或基类内部实现，新项目无需承接。
- 前端：旧项目全部业务页面在新项目四套 UI 家族中均有对应实现，并由既有 UI 契约门禁锁定。
- 注释标准 v0.0.3 从「文档」变为「工具链 + 基线」；成员注释剩余缺口由并行智能体收尾中，
  完成后补 CI 门禁并做最终全量串行。
