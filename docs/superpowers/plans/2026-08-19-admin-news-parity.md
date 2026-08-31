# Admin News Legacy Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 关闭旧 `NewsInfoController` 的 7 条待人工业务核验记录，使旧列表、CRUD、页面深链接与现代 Layui/CrmUI 共用一套安全新闻业务逻辑。

**Architecture:** `NewsController` 保持查询和写入的唯一业务入口；`LegacyAdminController` 增加新闻专用字段与 envelope 适配，不复制 SQL。后台写入使用验证后白名单、当前管理员作者快照和事务化镜像翻译同步；旧 GET 使用现有 Layui 页的受控模式，CrmUI 只补筛选与权限声明。

**Tech Stack:** Laravel 8.83、PHP 7.4+、MySQL 3307 隔离测试库 `co_crmv5_test`、PHPUnit 9.6、Blade、Layui、CrmUI、jQuery/原生 JavaScript。

**Safety boundary:** 旧项目/旧库只读，正式 `co_crmv5` 禁写，只有 PHPUnit 可写 `co_crmv5_test`；MT4 永久禁用；禁止执行迁移、Seeder 和 `database/sql/full_reset_and_migrate.sql`。目录不是 Git 仓库，不初始化 Git、不创建 worktree、不提交。

---

### Task 1: 锁定 7 条路由、权限和旧页面模式

**Files:**
- Create: `tests/Feature/AdminLegacyNewsPermissionClosureModuleTest.php`
- Create: `tests/Feature/AdminLegacyNewsPageModeClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `resources/admin/layui/news/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`

- [x] **Step 1: 写 7 条 method+URI 与权限 RED**

逐条断言三个 GET 和四个 POST 的 route name、`LegacyAdminController@handle`、`legacy.admin.auth` 与权限目标。列表页/搜索要求 `admin_api_newsList`，新增页/保存要求 `admin_api_createNews`，编辑页/更新要求 `admin_api_updateNews`，删除要求 `admin_api_deleteNews`。匿名和无权限角色必须失败关闭。

- [x] **Step 2: 写页面模式 RED**

断言列表页输出 `list`，新增页输出 `create`，编辑页输出 `edit` 和目标记录；非法/不存在/软删除 `newsid` 返回 404，query string 不能覆盖模式或记录。

- [x] **Step 3: 运行 RED**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsPermissionClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsPageModeClosureModuleTest.php
```

Expected: 至少页面权限、模式和编辑预载 FAIL。

- [x] **Step 4: 最小实现页面权限和模式**

在 `permissionRouteForLegacyUri()` 为三个新闻 GET 建立精确映射；在 `pageDataFor()` 只接受服务器路由确定的模式。编辑 ID 使用 `/^[1-9]\d*$/D` 校验并查询未删除 `News`，预载字段限定为 `id/title/content/is_published`。

- [x] **Step 5: 运行 GREEN**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsPermissionClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsPageModeClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyPageRenderClosureModuleTest.php
```

### Task 2: 关闭现代列表校验与旧 rows/total 契约

**Files:**
- Create: `tests/Feature/AdminLegacyNewsListParityClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/NewsController.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `tests/Feature/AdminLegacyMiscOperationsClosureModuleTest.php`

- [x] **Step 1: 写旧列表 RED**

夹具包含日期内外、开始日 `00:00:00`、结束日 `23:59:59`、同一更新时间不同 ID、已删除和不同发布状态记录。断言旧 `page/rows/startdate/enddate`、应用时区整日闭区间、默认日期、倒序、空结果严格为 `rows='' / total=''` 和以下行字段：

```php
$response->assertJsonStructure(['rows', 'total'])
    ->assertJsonPath('rows.0.news_id', $expectedId)
    ->assertJsonPath('rows.0.news_title', 'Legacy title')
    ->assertJsonPath('rows.0.news_content', 'Legacy content')
    ->assertJsonPath('rows.0.is_push', 1)
    ->assertJsonPath('rows.0.news_user', 'admin');
```

同步把 `AdminLegacyMiscOperationsClosureModuleTest` 的旧列表断言从现代 `code=SUCCESS` 改成精确顶层 `rows/total` 和旧行字段；不得为了保留陈旧测试向旧 envelope 额外添加现代 `code`。

- [x] **Step 2: 写严格校验 RED**

覆盖数组/对象、`page<=0`、`rows<=0`、`rows>100`、非法日期、倒置日期、非法 `is_published`；响应必须为 `VALIDATION_FAILED` 且不执行无界查询。另锁定无 `rows/limit/per_page` 的旧请求使用 WidgetPage 默认 20，而现代 API 默认仍为 15。

- [x] **Step 3: 运行 RED**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsListParityClosureModuleTest.php
```

Expected: 当前现代 paginator 和旧字段缺失导致 FAIL。

- [x] **Step 4: 实现现代查询和旧列表适配器**

`NewsController::index()` 用 Validator 校验 `page/per_page/title/start_date/end_date/is_published`，用 `config('app.timezone')` 将日期转换为 `startOfDay/endOfDay` Unix 秒，并按 `updated_at DESC, id DESC` 查询。`LegacyAdminController::forwardLegacyNewsList()` 设定旧默认日期和 `rows > limit > per_page > 20` 优先级，调用 `admin_api_newsList` 后只转换 envelope 与字段别名；空 paginator 转为旧空字符串 envelope。

- [x] **Step 5: 运行 GREEN**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsListParityClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyMiscOperationsClosureModuleTest.php
```

### Task 3: 关闭现代写入边界和翻译镜像一致性

**Files:**
- Create: `tests/Feature/AdminNewsWriteBoundaryClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/NewsController.php`

- [x] **Step 1: 写批量赋值攻击和作者 RED**

创建/更新请求额外提交 `id/deleted_at/created_at/updated_at/author_id/author_name`。断言主键、时间戳和软删状态不受请求控制，作者固定取当前管理员 ID 与 username；`title/content/is_published` 分别执行类型、长度和 `0/1` 校验。创建未传 `is_published` 时为 0；现代更新只传标题/正文时保留原发布状态。

新增目标替换回归：`/api/admin/updateNews/0`、`/api/admin/deleteNews/0` 和发布切换携带另一有效 body `id` 时必须 `VALIDATION_FAILED` 且目标记录零变化；负数、小数和带后缀 ID 同样失败关闭。路由参数存在时不得因 PHP falsy 规则回退 body。

- [x] **Step 2: 写镜像翻译 RED**

同一新闻建立一条与主表字节级完全相同的活动翻译和多条仅大小写、重音或尾空格不同的人工翻译。更新后只有 PHP `===` 同时匹配旧标题和旧正文的镜像同步，人工翻译保持不变；任一步异常时主表和翻译都回滚。并发测试或明确的锁调用契约测试必须证明主表与活动镜像在两个更新竞争时不会分叉。

回滚测试使用项目既有的可重复故障注入模式：临时替换当前 DB connection 的 `QueryExecuted` dispatcher，在 `news_langs` 更新或软删除查询执行后抛出 `RuntimeException`，并在 `finally` 恢复原 dispatcher。断言主表、翻译、作者和时间戳全部回到事务前值。

- [x] **Step 2a: 写翻译容量 RED**

覆盖 256 到 500 字符标题和超过 65535 字节正文。主表合法更新必须成功，精确镜像翻译被软删除以触发前台回退；人工翻译不能被删除或截断。

- [x] **Step 3: 运行 RED**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminNewsWriteBoundaryClosureModuleTest.php
```

Expected: `$request->all()` 允许越界字段且翻译保持旧值，测试 FAIL。

- [x] **Step 4: 实现白名单事务写入**

验证通过后显式构造：

```php
$payload = [
    'title' => $validated['title'],
    'content' => $validated['content'],
    'author_id' => (int) $admin->id,
    'author_name' => (string) $admin->username,
];
```

创建时显式补 `$payload['is_published'] = (int) ($validated['is_published'] ?? 0)`；更新时仅当 `array_key_exists('is_published', $validated)` 才写该字段，否则保留锁定行原值。

在同一 `DB::transaction()` 内先 `lockForUpdate()` 读取主表和活动翻译候选行，再用 PHP `===` 识别标题、正文都与旧主表字节级相同的镜像 ID。新标题不超过 255 个字符且正文不超过 65535 字节时更新这些镜像；否则软删除这些镜像，让前台回退主表。禁止用数据库默认 collation 的字符串相等比较代替 PHP 严格比较。

- [x] **Step 5: 运行 GREEN 与现代回归**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminNewsWriteBoundaryClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminNewsRouteIdValidationClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminNewsToggleModuleTest.php
```

### Task 4: 恢复旧新增、更新、删除字段和响应

**Files:**
- Create: `tests/Feature/AdminLegacyNewsMutationParityClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `tests/Feature/AdminLegacyMiscOperationsClosureModuleTest.php`

- [x] **Step 1: 写真实旧字段 RED**

只使用 `newsTitle/newsContent/ispush/newsId/newsid` 调用旧 URI。新增/更新成功断言 `msg=SUC/code=0/modern_code`，删除成功断言 `code=0/modern_code=1003`，并核对数据库结果和作者快照。

- [x] **Step 2: 写失败路径 RED**

覆盖缺字段、数组字段、非法发布状态、非法/不存在/软删除 ID。旧适配器先用正整数规则校验 `newsId/newsid`，再构造现代 URI；不得把非法旧 ID 替换成其他 body 字段。新增/更新返回 `msg=FAIL` 与真实现代错误码；所有失败路径零写入，不把现代异常改成旧成功。

- [x] **Step 3: 运行 RED**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsMutationParityClosureModuleTest.php
```

Expected: 当前字段无法通过现代校验且 envelope 不兼容，测试 FAIL。

- [x] **Step 4: 实现三个专用旧 mutation 适配器**

在通用转发前分流 `news_save/news_update/del`；使用专属 payload 映射，不把新闻别名加入所有旧模块的全局别名。成功仅在现代成功码 `1001/1002/1003` 时产生旧成功字段，其他响应保留失败码并输出 `msg=FAIL`。

- [x] **Step 5: 运行 GREEN**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsMutationParityClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyMiscOperationsClosureModuleTest.php
```

### Task 5: 完成 Layui/CrmUI Visual C 新闻交互

**Files:**
- Create: `tests/Feature/AdminLegacyNewsUiClosureModuleTest.php`
- Modify: `resources/admin/layui/news/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`
- Modify: `resources/lang/en/admin.php`
- Modify: `resources/lang/zh-CN/admin.php`

- [x] **Step 1: 写 UI RED**

断言 Layui 的标题/开始日期/结束日期/发布状态筛选、可见 labels、固定旧页面模式上下文、搜索/reset 回第一页、响应式弹窗尺寸和提交状态；断言 CrmUI 顶部 create action、表单及三种行操作均绑定对应 permission slug，并具有准确 filters；禁止 mock 行和硬编码业务中文。

- [x] **Step 2: 运行 RED**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsUiClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminCrudUiControlsTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\CrmUiBusinessCodeContractTest.php
```

- [x] **Step 3: 实现最小 Visual C 交互**

Layui 使用现有 token 和控件，初始化 `laydate`；弹窗宽高按 `min(680px, viewport-32px)` 与 `min(600px, viewport-32px)` 计算。服务器输出的 `list/create/edit` JSON 上下文只读，JS 初始化后打开对应表单。CrmUI 增加带 `admin_news_create` 的顶部 create action、`formPermission`、行级 permissions 和 `title/start_date/end_date` filters，`is_published` 明确声明为 1/0 select。

- [x] **Step 4: 运行 GREEN、语法和 Blade 编译**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminLegacyNewsUiClosureModuleTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminCrudUiControlsTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\CrmUiBusinessCodeContractTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\AdminContentCrudPermissionMigrationTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\LegacyUiReplacementCoverageTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\GlobalCrmThemeCoverageTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\UnifiedBladeDesignSystemTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\VisualCFoundationContractTest.php
node --check public\js\apps\admin\layui\pages.js
& 'D:\Software\PHP-TOOL\phpStudy64\phpstudy_pro\Extensions\php\php7.4.3nts\php.exe' -l app\Http\Controllers\Admin\NewsController.php
& 'D:\Software\PHP-TOOL\phpStudy64\phpstudy_pro\Extensions\php\php7.4.3nts\php.exe' -l app\Http\Controllers\Admin\LegacyAdminController.php
php artisan view:cache
php artisan view:clear
```

以上绝对路径已在 2026-08-19 解析并确认为 PHP 7.4.3；不得把默认 PHP 8 的结果冒充 PHP 7.4 校验。

### Task 6: 双复审、回归、7 条证据和下一轮

**Files:**
- Create: `tests/Unit/AdminNewsBusinessMatrixClosureTest.php`
- Modify: `docs/audits/旧项目路由核验证据.json`
- Regenerate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Regenerate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Modify: `docs/项目整体进度梳理-2026-08-17.md`
- Modify: `docs/superpowers/plans/2026-08-19-admin-news-parity.md`

- [x] **Step 1: 独立规格复审**

逐条核查 7 个 method+URI 的旧字段、旧 envelope、页面模式、权限、错误路径与双 UI。Critical/Important 必须修复并重审。

- [x] **Step 2: 独立质量复审**

检查白名单、路由 ID 不回退 body、正整数目标、事务行锁、翻译字节级镜像条件、翻译列容量回退、应用时区日期边界、旧空结果、数组输入失败关闭、分页上限、软删除、重复路由、N+1、MT4 零调用和前端注入安全。Critical/Important 清零后才能写 evidence。

- [x] **Step 3: 运行完整新闻专项回归**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never --filter '(?i)news' tests\Feature
```

Expected: exit 0，并记录 tests/assertions/time。

- [x] **Step 4: 写七维证据并重生成矩阵**

证据组固定为 `admin_news_business_2026_08_19`。每条路由登记旧行为、路由、后端、前端、权限、错误路径和实际自动化测试证据，然后执行：

```powershell
php scripts\generate-legacy-implementation-matrix.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\AdminNewsBusinessMatrixClosureTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\AdminFengXianRiskMatrixClosureTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\AdminWithdrawStatusMatrixClosureTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\Phase2IdentityOrganizationMatrixClosureTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\FrontBigNumberBusinessMatrixClosureTest.php
```

Expected: `legacy_route_methods=475 / verified=422 / needs_manual_business_review=53 / unresolved_legacy_source=0 / unmatched_current_route=0`。

- [x] **Step 5: 更新进度并继续下一控制器**

记录回归、双复审、矩阵、浏览器策略和残余风险。浏览器若仍为 `BLOCKED_BY_BROWSER_POLICY`，不绕过也不写成通过。新闻 7 条完成不代表全项目完成，下一批从剩余 53 条继续只读审计。

## 执行结果（2026-08-19 13:02 +08:00）

- 7 条旧路由已写入 `admin_news_business_2026_08_19` 七维证据组；权威矩阵为 `475/422/53/0/0`。
- 主线程新鲜核心回归：权限 `21/91`、页面模式 `7/16`、列表 `33/104`、写边界 `26/164`、旧 mutation `23/129`、UI `4/111`，全部 exit 0。
- 完整 News Feature 关键词回归：`162 tests / 1132 assertions / exit 0`。
- 通用 UI 门禁：`AdminCrudUiControlsTest`、`CrmUiBusinessCodeContractTest`、`AdminContentCrudPermissionMigrationTest`、`LegacyUiReplacementCoverageTest`、`GlobalCrmThemeCoverageTest`、`UnifiedBladeDesignSystemTest`、`VisualCFoundationContractTest` 全部 exit 0。
- 独立复审先后发现并关闭锁候选范围、空筛选、极小视口弹窗和 create/update 共用提交按钮权限四类 Important；最终后端与 UI 均为 `SPEC APPROVED / QUALITY APPROVED`，Critical/Important/Minor 均为 0。
- PHP 7.4.3 lint、`node --check`、`view:cache`、`view:clear` 全部 exit 0。浏览器四视口仍为 `BLOCKED_BY_BROWSER_POLICY`，未绕过也未表述为通过。
- 后端补强阶段新增的部分验收断言针对已存在的正确生产实现，首次即 GREEN；文档不伪造对应 RED。动态提交权限修复有真实 `4 tests / 105 assertions / 1 failure` RED，修复后为 `4/111` GREEN。
- 下一控制器已进入只读审计：`AdminWhsExpZeroController` 的 6 条待核验路由。
