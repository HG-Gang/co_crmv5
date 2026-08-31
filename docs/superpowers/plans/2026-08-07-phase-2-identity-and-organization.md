# Phase 2 Identity and Organization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 以 Phase 0 的真实迁移矩阵为唯一范围来源，完成后台管理员、普通用户、代理、大代理、实名、黑名单、角色权限和数据范围的旧逻辑等价替换，并让相关 Layui/CrmUI 页面达到 Visual C、CSS/JavaScript 交互和双 UI 闭环要求。

**Architecture:** 以旧 HTTP 方法 + URI 为最小核验单元，每一批先逐方法阅读项目1控制器、Blade 和脚本，再用运行时测试证明项目2的路由映射、业务状态、输入错误、权限和前端契约；只有七个核验维度全部有证据时才写入显式 `verification_group`。现代 JWT、SSO、关系表权限和数据范围服务可替代旧 Session/ACL 内部实现，但必须保留可观察业务结果，并把防枚举、失败关闭等安全强化记录为有依据的差异。

**Tech Stack:** PHP 7.4+/Laravel 8.83、PHPUnit 9.6、MySQL 3307 隔离测试库、Blade、Layui、CrmUI、jQuery/原生 JavaScript、原生 CSS、PowerShell、Codex in-app browser。

---

## 前置证据与硬边界

- 总路线图：`docs/superpowers/plans/2026-08-07-full-legacy-parity-and-visual-c-roadmap.md`
- Phase 0 报告：`docs/audits/2026-08-07-phase-0-result.md`
- Phase 1 报告：`docs/audits/2026-08-07-phase-1-visual-c-foundation-result.md`
- 迁移矩阵：`storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- 证据注册表：`docs/audits/旧项目路由核验证据.json`
- 旧源码清单：`storage/app/audits/旧项目源码逻辑清单.json`
- 当前路由审计：`storage/app/audits/current-legacy-route-audit.json`
- 项目1根目录：`D:\Software\PhpProject\Demo\new_co_gmtk_crmv3`
- 项目2根目录：`D:\Software\PhpProject\Demo\co_crmv5`
- 当前目录不是 Git 仓库，不初始化 Git、不创建 worktree、不伪造 commit。每批检查点使用修改文件清单、修改前后 SHA-256、RED/GREEN 命令和退出码。
- `hank_zl_data` 永久只读；正式 `co_crmv5` 不写入、不迁移、不重建。运行时测试只允许 PHPUnit 隔离测试库；MT4 保持禁用，不调用真实交易端。
- 本阶段相关页面继续采用前后端不分离 Blade 架构，交互只能由项目内 CSS 和 JavaScript 实现，不引入 SPA 或外部前端运行时。

## Phase 2 真实范围基线

初始全量矩阵为 `475` 条：`verified=185`、`needs_manual_business_review=290`；截至 Task 8 完成后的实时基线为 `verified=299`、`needs_manual_business_review=176`、`unresolved_legacy_source=0`、`unmatched_current_route=0`。

身份与组织的矩阵范围由以下项目1控制器的全部路由，加上 `BigNumberController` 的 7 个身份壳层入口组成：

```text
App\Http\Controllers\Admin\LoginController
App\Http\Controllers\Admin\AdminController
App\Http\Controllers\Admin\AdministratorsController
App\Http\Controllers\Admin\RoleController
App\Http\Controllers\Admin\AuthenticationController
App\Http\Controllers\Admin\AgentControllerV3
App\Http\Controllers\Admin\BigAgentController
App\Http\Controllers\Admin\UserGroupController
App\Http\Controllers\Admin\GroupConfigController
App\Http\Controllers\Admin\CustomerController
App\Http\Controllers\User\LoginController
App\Http\Controllers\User\RegisterController
App\Http\Controllers\User\UserForgetPswController
App\Http\Controllers\User\UserCenterController
App\Http\Controllers\User\DirectCustomerController
App\Http\Controllers\User\ProxyListController
```

`BigNumberController` 只纳入：

```text
GET  agents/login
POST user/agents/signIn
GET  user/agents/loginOut
GET  user/agents/editpsw
POST user/agents/changePassword
GET  user/agents/index
GET  user/agents/main/home
```

其持仓、订单、返佣和代理结算入口分别留给 Phase 3/5，避免跨阶段重复登记。以上范围共 `184` 条，当前 `70` 条已有显式证据、`114` 条待人工业务核验。Phase 2 完成门禁是这 `184` 条全部为 `verified`，并且全量矩阵仍保持两个 unresolved 计数为 `0`。

## 统一 TDD 与证据规则

每个业务批次固定执行以下顺序：

1. 阅读该批全部项目1控制器方法、实际调用的模型/服务、旧 Blade/JS 和路由定义，记录输入、查询范围、写状态、返回、错误分支和外部依赖。
2. 在项目2写一个最小失败运行时测试，运行并确认因真实行为缺失而失败；测试配置、语法或数据库不可用导致的 ERROR 不算 RED。
3. 写一个矩阵闭环失败测试，断言该批精确 HTTP 方法/URI 必须属于唯一显式 verification group；证据未登记时必须失败。
4. 只修改让运行时行为测试通过所需的最小生产代码；禁止为了计数修改测试期待或伪造 evidence。
5. 运行该批测试、受影响回归、PHP 语法、JS 语法和 CSS/Blade 静态检查。
6. 将七维证据与真实 `test_evidence` 写入 `docs/audits/旧项目路由核验证据.json`，再重新生成 JSON/Markdown 矩阵。
7. 复核状态变化量严格等于本批登记路由数，既有 verified 行不回退，两个 unresolved 计数保持 `0`。

七维固定为：`legacy_behavior`、`route_mapping`、`backend_logic`、`frontend_contract`、`auth_and_scope`、`validation_and_errors`、`automated_tests`。

### Task 1: 锁定 Phase 2 范围与状态门禁

**Files:**
- Create: `tests/Unit/Phase2IdentityOrganizationMatrixClosureTest.php`
- Read: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Read: `storage/app/audits/旧项目源码逻辑清单.json`
- Read: `storage/app/audits/current-legacy-route-audit.json`
- Test: `tests/Unit/Phase2IdentityOrganizationMatrixClosureTest.php`

- [x] **Step 1: 记录范围文件 SHA-256**

Run:

```powershell
Get-FileHash -Algorithm SHA256 storage\app\audits\旧项目模块逻辑迁移核验矩阵.json,storage\app\audits\旧项目源码逻辑清单.json,storage\app\audits\current-legacy-route-audit.json,docs\audits\旧项目路由核验证据.json
```

Expected: 四个文件均存在并输出 SHA-256。

- [x] **Step 2: 创建真实范围测试**

测试必须从磁盘矩阵读取路由，不复制生成器结果；控制器集合使用本计划“真实范围基线”的 16 个精确类名，另把 7 个 BigNumber URI 纳入。核心断言：

```php
$this->assertSame(475, $matrix['summary']['legacy_route_methods']);
$this->assertCount(184, $phase2Rows);
$this->assertCount(70, array_filter($phase2Rows, fn (array $row): bool => $row['evidence_state'] === 'verified'));
$this->assertCount(114, array_filter($phase2Rows, fn (array $row): bool => $row['evidence_state'] === 'needs_manual_business_review'));
$this->assertSame(0, $matrix['summary']['unresolved_legacy_source']);
$this->assertSame(0, $matrix['summary']['unmatched_current_route']);
```

同时让测试输出并断言当前 114 条未闭环 key；每个批次在证据生成后按实际变化量同步这一计数，Task 11 再把期待改为 0。具体业务批次仍使用独立的 verification group RED，确保每个失败测试都能在同一批次立即转绿，不让预期中的长期失败污染全量回归。

- [x] **Step 3: 验证范围与当前状态基线**

Run:

```powershell
vendor\bin\phpunit --colors=never tests\Unit\Phase2IdentityOrganizationMatrixClosureTest.php
```

Expected: PASS；范围、当前 70/114 状态和待核验 key 均来自磁盘矩阵，失败清单只允许由后续具体批次的独立 RED 产生。

### Task 2: 闭环旧后台登录、验证码和退出 4 条路由

**Files:**
- Modify: `tests/Unit/Phase2IdentityOrganizationMatrixClosureTest.php`
- Modify: `tests/Feature/AdminLegacyLoginCaptchaClosureTest.php`
- Read: `tests/Feature/AdminLegacyLoginCaptchaImageContractTest.php`
- Read: `tests/Feature/AdminLegacyRouteSemanticClosureTest.php`
- Read: `tests/Feature/AdminLegacyPageRenderClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/AuthController.php`
- Modify only if a failing test proves necessary: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `docs/audits/旧项目路由核验证据.json`
- Regenerate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Regenerate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`

精确路由和目标分组：

```text
GET  index/admin/captcha  legacy_admin_112ffed00382cbc4
GET  index/admin/login    legacy_admin_ab3b75e4093e3bd8
POST index/admin/logon    legacy_admin_ac69070e4a51d6c5
GET  index/admin/logout   legacy_admin_9a18d0d69737a781
verification_group: admin_legacy_login_session_2026_08_10
current_action: App\Http\Controllers\Admin\LegacyAdminController@handle
```

- [x] **Step 1: 写矩阵闭环 RED**

在 Phase 2 矩阵测试中精确查找上面 4 条路由，断言数量为 4、`verification_group` 等于目标分组、`evidence_state=verified`、`current_name/current_action` 与当前审计一致。

Run:

```powershell
vendor\bin\phpunit --colors=never --filter test_admin_legacy_login_routes_are_verified_by_one_explicit_group tests\Unit\Phase2IdentityOrganizationMatrixClosureTest.php
```

Expected: FAIL，当前 4 条均无 verification group 且为 `needs_manual_business_review`。

- [x] **Step 2: 写旧登录状态 RED**

在 `AdminLegacyLoginCaptchaClosureTest` 增加以下真实行为：

```php
public function test_successful_legacy_login_updates_metadata_count_and_one_audit_row(): void;
public function test_legacy_login_rejects_wrong_password_without_mutating_admin_or_audit(): void;
public function test_legacy_login_rejects_disabled_admin_without_mutating_admin_or_audit(): void;
```

成功用例把初始 `login_count` 设为 7，固定 `REMOTE_ADDR=127.0.0.42`，断言成功后为 8、`last_login_ip` 更新、`last_login_at` 非空、审计只增加 1 条、一次性验证码已消费。失败用例断言统一 `AUTH_FAILED`、`login_count/last_login_ip/last_login_at` 不变、审计不增加；账号不存在与密码错误继续共用错误语义，避免枚举账号。

Run:

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyLoginCaptchaClosureTest.php
```

Expected: 新成功状态测试因 `login_count` 仍为 7 而 FAIL；既有验证码、页面和现代 API 隔离用例保持通过。

- [x] **Step 3: 最小修复成功登录状态**

在 `AuthController::login()` 的成功分支把登录次数与 IP/时间一起持久化，不改失败分支：

```php
$admin->update([
    'login_count' => ((int) $admin->login_count) + 1,
    'last_login_ip' => $request->ip(),
    'last_login_at' => date('Y-m-d H:i:s'),
]);
```

保留 JWT/SSO 和 `AdminLoginLog`；不重新建立旧 `roles.acl` Session 第二数据源，现代 `role_permissions` + `/api/admin/menus` 是权限唯一来源。证据中明确记录这一内部替换以及账号枚举防护强化。

- [x] **Step 4: 运行四个真实行为测试**

Run:

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyLoginCaptchaClosureTest.php
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyLoginCaptchaImageContractTest.php
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRouteSemanticClosureTest.php
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyPageRenderClosureModuleTest.php
```

Expected: 全部通过；验证码是 PNG 且 Session/Cache 一次性消费，旧/现代登录页隔离，4 个入口公开，其余旧后台路由受保护，退出清 Session 并跳转 `/admin/login`。

- [x] **Step 5: 登记七维证据并重生成矩阵**

向 `verification_groups` 追加 `admin_legacy_login_session_2026_08_10`，路由必须逐条登记上面的 name/action，`test_evidence` 必须使用 Step 4 的四个实际测试文件和已通过命令。不得使用通配符或把 `current_route` 放入共享 verification。

Run:

```powershell
php scripts\generate-legacy-implementation-matrix.php --legacy-routes=storage\app\audits\legacy-routes.json --route-audit=storage\app\audits\current-legacy-route-audit.json --source-inventory=storage\app\audits\旧项目源码逻辑清单.json --verification-evidence=docs\audits\旧项目路由核验证据.json --json=storage\app\audits\旧项目模块逻辑迁移核验矩阵.json --markdown=docs\audits\旧项目模块逻辑迁移核验矩阵.md
vendor\bin\phpunit --colors=never --filter test_admin_legacy_login_routes_are_verified_by_one_explicit_group tests\Unit\Phase2IdentityOrganizationMatrixClosureTest.php
```

Expected: 全量变为 `verified=189`、`needs_manual_business_review=286`；Phase 2 变为 `verified=74`、待核验 `110`；两个 unresolved 计数保持 0。

### Task 3: 闭环后台壳层、本人资料、管理员账号和角色权限

**Files:**
- Modify: `tests/Unit/Phase2IdentityOrganizationMatrixClosureTest.php`
- Modify/Create runtime tests under: `tests/Feature/AdminLegacyAdministratorsClosureModuleTest.php`, `tests/Feature/AdminProfile*ClosureModuleTest.php`, `tests/Feature/AdminRole*ClosureModuleTest.php`
- Read: project1 `AdminController.php`, `AdministratorsController.php`, `RoleController.php` and their Blade/JS dependencies
- Modify only after RED: `app/Http/Controllers/Admin/LegacyAdminController.php`, `app/Http/Controllers/Admin/AdminController.php`, `app/Http/Controllers/Admin/RoleController.php`
- Modify relevant Blade/JS under: `resources/admin/layui`, `resources/admin/crmui`, `public/js/apps/admin`
- Modify evidence and regenerate both matrix artifacts

本批待核验 12 条：`index/admin/index`、`welcome`、`userinfo`、`userinfo/save`、`userpwd`、`userpwd/save`，以及 role 的 list/add/addsave/edit/editsave/del。另对已 verified 的 8 条 `AdministratorsController` 路由做回归，禁止证据回退。

- [x] **Step 1: 逐方法建立行为表**

记录管理员本人更新的 owner 边界、旧密码校验、新密码哈希、角色 CRUD、角色删除引用保护、管理员启停/删除和权限树写入。项目2必须以 `roles`、`permissions`、`role_permissions` 为唯一权限源。

- [x] **Step 2: 先写失败测试**

测试至少覆盖：匿名/越权拒绝；本人资料不可通过 request id 更新他人；改密旧密码错误不写库；角色 permission IDs 严格整数且事务替换；被管理员引用的角色删除失败；旧 GET 删除/启停不执行写动作并返回 405。

- [x] **Step 3: 最小修复并运行批次回归**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter "AdminLegacyAdministrators|AdminProfile|AdminRole|AdminPermission|AdminProtectedRoutePermission"
```

Expected: 目标用例全绿，无真实 `co_crmv5` 写入。

- [x] **Step 4: 登记三个显式分组**

使用 `admin_legacy_profile_session_*`、`admin_legacy_roles_permissions_*`、`admin_legacy_administrators_regression_*`，每组只含精确路由。状态变化量必须等于本批原待核验 12 条。

完成结果：三个分组分别登记 6、6、8 条精确路由；本批 12 条待核验路由全部转为 `verified`，全局矩阵为 `verified=201`、`needs_manual_business_review=274`，Phase 2 为 `verified=86`、待核验 `98`，两个 unresolved 计数保持 0。

### Task 4: 闭环后台客户、代理、组配置和用户组 33 条待核验路由

**Files:**
- Modify: Phase 2 matrix test
- Read: project1 `AgentControllerV3.php`, `CustomerController.php`, `GroupConfigController.php`, `UserGroupController.php`
- Modify/Create focused tests: `AdminLegacyAgent*`, `AdminUser*`, `AdminGroupConfig*`, `AdminUserGroup*`, `AdminDataScope*`
- Modify only after RED: corresponding project2 controllers/services/Blade/JS
- Modify evidence and regenerate matrix artifacts

待核验组成：Agent 9、Customer 12、GroupConfig 4、UserGroup 8。必须覆盖新增/编辑/审批/状态、父代理变更、代理树环路、邮箱/手机号/证件唯一、MT4 失败关闭、分页筛选、用户组成员关系、组配置值和 ID 严格校验。

- [ ] **Step 1: 逐方法阅读并建立 33 条精确清单**
- [ ] **Step 2: 按“读列表/写状态/层级变化/组关系”分别写 RED**
- [ ] **Step 3: 最小修复后运行所有 `AdminLegacyAgent|AdminUserUpdate|AdminUserGroup|AdminGroupConfig|AdminDataScope` 测试**
- [ ] **Step 4: 以精确路由分组登记证据，确认待核验减少 33**

错误分支必须显式验证：非法/缺失主键、跨数据范围用户、代理互为父子、重复联系信息、MT4 未确认、无权限、重复提交。任何外部状态未知都不得返回成功。

### Task 5: 闭环实名认证、银行卡和凭证审核 14 条路由

**Files:**
- Modify: Phase 2 matrix test
- Read: project1 `AuthenticationController.php` and related views/scripts
- Modify/Create: `AdminLegacyAuthAndCustSearchClosureModuleTest.php`, `AdminReviewAuth*`, `AdminVoucher*`, `AdminLegacyUserIdCardBankClosureModuleTest.php`
- Modify only after RED: `AuthenticationController.php`, `VoucherController.php`, legacy adapter, Blade/JS
- Modify evidence and regenerate matrix artifacts

- [x] **Step 1: 阅读 14 个方法并区分待审、已审、详情、身份证/银行卡审核和凭证审核状态机**
- [x] **Step 2: 写 RED 覆盖状态映射、理由必填、重复审核、跨范围读取、事务和审计日志**
- [x] **Step 3: 修根因并运行 `AdminLegacyAuth|AdminReviewAuth|AdminVoucher|AdminLegacyUserIdCardBank` 回归**
- [x] **Step 4: 登记 14 条显式证据，确认状态变化量为 14**

完成结果：新增 `admin_legacy_authentication_voucher_2026_08_16` 精确分组，14 条认证/凭证路由由 `needs_manual_business_review` 转为 `verified`；相关 10 个专项测试文件、页面渲染与路由语义回归全部通过。全量矩阵变为 `verified=251`、`needs_manual_business_review=224`，unresolved 与 unmatched 均保持 0。

详情页必须展示真实上传资产且对不存在/越权用户失败；审核写入 `user_auths` 与 `user_infos.auth_status` 必须原子一致，MT4 同步只走 outbox/失败关闭，不伪造同步成功。

### Task 6: 闭环后台和大账号的大代理身份链路

**Files:**
- Modify: Phase 2 matrix test
- Read: project1 `BigAgentController.php`, `BigNumberController.php`, `UserCenterController.php`
- Modify/Create: `AdminBigAgent*`, `FrontBigAgent*`, `FrontBigNumber*` focused tests
- Modify only after RED: project2 BigAgent/BigNumber/Profile controllers, middleware, Blade/JS
- Modify evidence and regenerate matrix artifacts

本批包括：后台 BigAgent 9 条待核验、BigNumber 7 个身份壳层入口、UserCenter 的 `user/agents/editpsw_save` 和 `user/agents/relationShipHtml` 两条大代理入口。

- [x] **Step 1: 区分后台管理身份、独立大账号登录身份和普通前台 JWT，禁止 guard 混用**
- [x] **Step 2: 写 RED 覆盖登录、登出、改密、启停、删除引用保护、下级代理完整性和 Session/JWT 冲突**
- [x] **Step 3: 最小修复并运行 `AdminBigAgent|FrontBigAgent|FrontBigNumber` 回归**
- [x] **Step 4: 登记本批仍待核验的 17 条显式证据，确认大代理层级与数据范围无越权**

完成结果：`user/agents/relationShipHtml` 已先由 `front_profile_relationship_2026_08_15` 闭环，因此本批新增 `phase2_big_agent_identity_2026_08_16` 精确登记其余 17 条。后台大代理、独立 `bigAgents` Session、登录/登出/改密、页面和数据范围专项回归全部通过；全量矩阵更新为 `verified=268`、`needs_manual_business_review=207`。

### Task 7: 闭环普通用户认证、注册、资料和注销 22 条待核验路由

**Files:**
- Modify: Phase 2 matrix test
- Read: project1 `LoginController.php`, `RegisterController.php`, `UserForgetPswController.php`, `UserCenterController.php`
- Modify/Create focused tests under: `FrontAuth*`, `FrontLegacyLogin*`, `FrontRegistration*`, `FrontAccountProfile*`, `FrontCancel*`
- Modify only after RED: project2 Front Auth/Profile/Account/Cancel controllers, Blade/JS
- Modify evidence and regenerate matrix artifacts

本批组成：LoginController 待核验 8、RegisterController 待核验 3、UserCenterController 除 Task 6 两条外待核验 11；已 verified 的 Login 8、Register 18、ForgetPassword 5 与 UserCenter 16 条全部回归。

- [x] **Step 1: 逐方法核对登录/登出、主页会话、注册防重、验证码限流、资料 owner 边界、账户切换、关系树、凭证和注销状态机**
- [x] **Step 2: 写 RED 覆盖禁用/注销账号、错误凭据、验证码消费、限流、重复账号、跨 owner、注销重复提交和外部失败**
- [x] **Step 3: 最小修复并运行 `FrontAuth|FrontLegacyLogin|FrontRegistration|FrontAccountProfile|FrontCancel` 回归**
- [x] **Step 4: 登记本批仍待核验的 20 条显式证据，确认状态变化量为 20**

完成结果：普通用户旧首页/消息、资料、账户切换、凭证、注销、新闻、反馈、邮箱检查与详情边界的 20 条路由登记到 `phase2_front_user_shell_profile_2026_08_16`；关系树入口已在前置分组完成。专项回归全部通过，全量矩阵更新为 `verified=288`、`needs_manual_business_review=187`。

### Task 8: 闭环前台代理层级与可见范围 11 条路由

**Files:**
- Modify: Phase 2 matrix test
- Read: project1 `ProxyListController.php`
- Modify/Create: `FrontProxy*`, `FrontPositionScope*`, `FrontLegacyDirectCustomer*` tests
- Modify only after RED: `Front/AgentController.php`, hierarchy services, Blade/JS
- Modify evidence and regenerate matrix artifacts

- [x] **Step 1: 读取 11 条 proxy 路由，记录父链、下级组、直接客户详情、等级确认和佣金转账边界**
- [x] **Step 2: 写 RED 覆盖跨树目标、普通客户身份、树环路、失效代理、重复等级确认和转账幂等**
- [x] **Step 3: 最小修复并运行 `FrontProxy|FrontPositionScope|FrontLegacyDirectCustomer` 回归**
- [x] **Step 4: 登记 11 条显式证据，确认状态变化量为 11**

完成结果：新增 `phase2_front_proxy_scope_2026_08_16` 精确分组，代理列表/父链/组列表/等级确认/直属客户/佣金转账 11 条入口全部验证通过。Phase 2 的 184 条身份与组织路由现已全部 `verified`，全量矩阵为 `verified=299`、`needs_manual_business_review=176`，两个 unresolved 计数均为 0。

### Task 9: 统一 RBAC、数据范围和黑名单横切门禁

**Files:**
- Modify/Create: `tests/Feature/Phase2AuthorizationAndBlacklistClosureTest.php`
- Read/Modify after RED: `CheckPermission.php`, `AdminDataScopeService.php`, `LegacyAdminAuthenticate.php`, role/data-scope/blacklist controllers and models
- Modify relevant permission migrations only when runtime schema evidence proves a missing mapping

- [x] **Step 1: 写横切 RED**

矩阵数据驱动覆盖 Phase 2 所有受保护当前 route name：无 token/session 返回 401；有身份无权限返回 403；有权限仍必须受 role/admin binding 数据范围限制。黑名单 create/update/delete/list 要求严格 ID、唯一规则、审计与权限，禁止前端显隐代替后端授权。

- [x] **Step 2: 修复权限/范围单一来源**

只允许 `permissions` + `role_permissions` 决定能力、`role_data_scopes` + `admin_agent_bindings` 决定范围；删除同一修复内仍读取旧 ACL JSON 或由请求参数扩大范围的重复分支。

- [x] **Step 3: 运行权限与范围回归**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter "Permission|DataScope|Blacklist|ProtectedRoute"
```

完成结果：ProtectedRoute、AdminDataScope、Blacklist、Blade permission coverage 与 CRUD UI 控件回归全部通过；未知 scope fail-closed、严格 ID、后端权限边界和前端按钮权限契约均已锁定。

Expected: 全部通过；前端按钮显隐与后端 401/403 结果一致，但后端是最终边界。

### Task 10: 完成身份与组织相关 Blade 的 Visual C 双 UI 验收

**Files:**
- Modify relevant Blade under `resources/admin/layui`, `resources/admin/crmui`, `resources/front/layui`, `resources/front/crmui`
- Modify only family-scoped CSS under `public/css/layui`, `public/css/crmui`, `public/css/admin`, `public/css/front`
- Modify related JavaScript under `public/js/apps/admin`, `public/js/apps/front`, `public/js/apps/crmui`
- Create/Modify: focused UI contract tests and browser evidence report

- [ ] **Step 1: 从当前项目页面清单提取 Phase 2 页面**

至少覆盖登录、管理员、角色、权限、客户、代理、大代理、实名认证、用户组、组配置、黑名单、个人资料、改密、注册、忘记密码、注销和代理层级页面；Layui 与 CrmUI 有对应入口时必须双页面验收。

- [ ] **Step 2: 写失败 UI 契约**

断言业务字段、form action、route name、`data-permission` 和脚本绑定不变；Visual C family 资产隔离；无内联事件新增；移动侧栏、表格操作、弹窗、错误/空/加载/禁用状态完整。

- [ ] **Step 3: 做最小 CSS/JS/Blade 优化**

遵循 Phase 1 token 和 family 边界，不创建卡片套卡片、不使用渐变/装饰光斑；工具按钮优先使用现有 Lucide 资产，卡片圆角不超过 8px，文字在 `1440x900`、`1280x720`、`768x1024`、`390x844` 不溢出或重叠。

- [ ] **Step 4: 浏览器验收**

对匿名、已登录、无权限、空数据、表单错误和移动侧栏状态做截图与控制台检查。每个页面要求 HTTP 正常、控制台 0 error、无横向溢出、无不 coherent 重叠、交互可闭环。

### Task 11: Phase 2 全量重生成、自检和报告

**Files:**
- Modify: `tests/Unit/Phase2IdentityOrganizationMatrixClosureTest.php`
- Regenerate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Regenerate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Create: `docs/audits/2026-08-10-phase-2-identity-and-organization-result.md`

- [ ] **Step 1: 运行矩阵生成器和 Phase 2 终态测试**

Expected: 184/184 Phase 2 行都有非空唯一 verification group 且 `verified`；全量 unresolved 两项仍为 0。

- [ ] **Step 2: 运行目标、受影响和全量 PHPUnit**

先按批次运行，再运行 `vendor\bin\phpunit --colors=never`。任何 Failure/Error/Crash 都阻止完成；禁止以重跑或放宽断言掩盖失败。

- [ ] **Step 3: 运行静态与页面冒烟**

对所有修改 PHP 执行 `php -l`，对所有修改 JavaScript 执行 `node --check`，检查 CSS 大括号与禁止模式；启动隔离本地服务器后完成 Task 10 视口验收并停止服务器，确认端口无监听。

- [ ] **Step 4: 写审计报告**

报告必须列出：本阶段范围和最终计数、每个 verification group、修改文件及 SHA-256、全部命令/退出码/测试与 assertions 数、浏览器矩阵/截图、数据库写边界、MT4 禁用证据、合理安全差异、未关闭项。SHA 必须在最终写盘后重新计算并与报告逐项核对。

## 自审结果

- 规格覆盖：后台管理员、用户、代理、大代理、实名、黑名单、角色权限、数据范围、双 UI、CSS/JS 交互和矩阵报告均有独立任务。
- 范围一致性：Phase 2 路由范围由当前 475 行矩阵精确筛出 184 行，不引用历史报告推断完成；114 条待核验被分批完整覆盖。
- 类型与命名：所有证据分组使用精确 HTTP 方法/URI 和当前 route name/action；七维字段与 `LegacyImplementationMatrix` 常量一致。
- 占位检查：所有任务均给出精确范围、目标文件、测试命令和完成门禁；后续生产文件只允许在真实 RED 指向根因后修改。
- Git 适配：仓库不存在 `.git`，所有 commit 步骤已替换为 SHA-256 和可复现命令检查点。
