# 旧前台资料操作专用页面闭环实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 恢复项目1的七类旧资料操作专用页面，使旧字段、会话提交、验证码阶段、上传预览和旧响应语义在项目2完整闭环。

**Architecture:** 每个旧 GET 入口使用语义明确的 `LegacyPageController` 动作，动作只校验路由参数并向一个共享 Blade 传递白名单 action 和端点。共享 Blade/CSS/JS 按 action 输出并驱动单一任务表单，所有业务校验与写入继续复用现有 `ProfileController` 和 `CancelController`，不新增业务数据源。

**Tech Stack:** PHP 7.4、Laravel 8、Blade、Layui/jQuery、Lucide、PHPUnit 9、MySQL。

---

### Task 1: 专用路由和 Controller 动作

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Front/LegacyPageController.php`
- Test: `tests/Feature/FrontLegacyRouteCompatibilityTest.php`
- Test: `tests/Feature/FrontLegacyProfilePageClosureModuleTest.php`

- [x] **Step 1: 保留已确认的 RED 证据**

运行：

```powershell
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --configuration phpunit.xml tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_user_module_routes_are_registered --colors=never
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --configuration phpunit.xml tests\Feature\FrontLegacyProfilePageClosureModuleTest.php --colors=never
```

预期：第一个测试因 `profileIdentity` 实际仍为 `profile` 失败；第二个测试因缺少专用表单和 `legacy-action.blade.php` 失败。

- [x] **Step 2: 增加专用动作并改路由**

动作签名固定为：

```php
public function profileIdentity(Request $request)
public function profileBank(Request $request)
public function profileBankChange(Request $request, string $type)
public function profileAvatar(Request $request)
public function profileContact(Request $request, string $type)
public function profilePassword(Request $request)
```

六个动作统一调用私有 `legacyProfileActionView()`，传入 `action`、`submitUrl`、可选 `verifyUrl/codeUrl/type`。`profileContact` 只接受 `phone`、`email`，路由映射如下：

```php
Route::get('user/center/uploadIdCard', 'Front\LegacyPageController@profileIdentity');
Route::get('user/center/uploadBank', 'Front\LegacyPageController@profileBank');
Route::get('user/center/uploadChangeBank/{type}', 'Front\LegacyPageController@profileBankChange');
Route::get('user/center/uploadHead_browse', 'Front\LegacyPageController@profileAvatar');
Route::get('user/center/updPhoneEmail/{type}', 'Front\LegacyPageController@profileContact');
Route::get('user/editpsw', 'Front\LegacyPageController@profilePassword');
```

- [x] **Step 3: 运行路由测试**

预期：路由动作断言通过；页面测试仍因 Blade 缺失保持失败，证明路由层已修复而 UI 尚未误报完成。

### Task 2: 共享 Blade 与响应式样式

**Files:**
- Create: `resources/front/layui/profile/legacy-action.blade.php`
- Create: `public/css/front/profile-legacy-action.css`
- Test: `tests/Feature/FrontLegacyProfilePageClosureModuleTest.php`

- [x] **Step 1: 创建参数化 Blade**

页面根节点必须包含：

```blade
<main class="crm-profile-action" data-legacy-profile-action="{{ $legacyProfileAction }}"
      data-submit-url="{{ $legacySubmitUrl }}"
      @isset($legacyVerifyUrl) data-verify-url="{{ $legacyVerifyUrl }}" @endisset
      @isset($legacyCodeUrl) data-code-url="{{ $legacyCodeUrl }}" @endisset>
```

`@switch($legacyProfileAction)` 分别输出以下旧字段：

```text
identity: username, userIdcardNo, Idphoto1, Idphoto2
bank: username, bankclass, bankno, bankinfo, bankimg
bank-change: bankclass, bankno, bankinfo, useremail, userverfcode, password, bankimg
avatar: headimg
contact-phone: oldphonefill, userphoneNo, newuserphoneNo, password
contact-email: oldemail, useremail, updVerifyCode, password
password: olduserpsw, newuserpsw, confirmuserpsw
```

所有按钮使用 `data-lucide`，文件字段限制为图片和 10MB，表单包含 `@csrf`、白名单 hidden type/uploadType 和 `data-layui-page="legacy/profile/action"`。

- [x] **Step 2: 创建样式**

样式仅使用 `--crm-*` tokens，表单最大宽度 760px、圆角不超过 8px；使用 Flex/Grid 的稳定尺寸，提供 focus/error/disabled/file-preview 状态和 `prefers-reduced-motion` 分支，移动端不出现横向溢出。

- [x] **Step 3: 运行页面结构测试**

预期：字段、端点、Lucide、中文职责注释和 CSS 文件断言通过；会话提交脚本断言仍待 Task 3 完成。

### Task 3: 旧 Web Session 表单脚本

**Files:**
- Modify: `public/js/apps/front/layui/pages.js`
- Test: `tests/Feature/FrontLegacyProfilePageClosureModuleTest.php`

- [x] **Step 1: 注册页面初始化器**

新增：

```javascript
registry['legacy/profile/action'] = once(function () {
    // 旧前台资料操作页使用 Web Session 和 CSRF，不读取 JWT。
});
```

初始化器读取根节点的 action 和 URL；上传 action 使用 `FormData`，其它 action 使用表单序列化。所有请求发送期间禁用按钮并显示提交状态，完成后恢复。

- [x] **Step 2: 恢复验证码两阶段链路**

银行卡换绑和邮箱修改先 POST `data-verify-url`；成功后 POST `data-code-url`。只有返回 `msg=SUC` 或 `status=true` 才启动 60 秒倒计时；失败立即恢复按钮并展示 `err/col` 对应提示。

- [x] **Step 3: 恢复旧结果含义**

```text
msg=SUC / SUCCESS: 操作成功
msg=FAIL: 业务失败，按 err/col 显示并聚焦字段
localpswerr / apipswerr / errpassword / pswErr: 当前密码错误
erruserverfcode / codeErr: 验证码错误或失效
UPDATEFAIL: 本地写入失败
FATALCANOTCONNECT / NETWORKFAIL: 远端服务不可用
```

密码成功跳转 `/user/loginOut`；其它成功关闭父 Layui 弹层并刷新 `/user/index`。文件选择展示名称、大小和本地预览。

- [x] **Step 4: 运行 JS 和页面测试**

```powershell
node --check public\js\apps\front\layui\pages.js
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --configuration phpunit.xml tests\Feature\FrontLegacyProfilePageClosureModuleTest.php --colors=never
```

预期：语法和 3 项页面测试全部通过。

### Task 4: 资料、上传、注销全链回归

**Files:**
- Verify: `app/Http/Controllers/Front/ProfileController.php`
- Verify: `app/Http/Controllers/Front/UploadController.php`
- Verify: `app/Http/Controllers/Front/CancelController.php`
- Verify: `tests/Feature/*Profile*Test.php`
- Verify: `tests/Feature/*Cancel*Test.php`

- [x] **Step 1: 串行运行资料和上传测试**

运行专用页面、资料验证码生命周期、身份/银行卡/头像/联系方式/密码/关系链 owner boundary、上传 Session 和路由认证边界测试；任何失败先按 systematic-debugging 定位根因。

- [x] **Step 2: 串行运行注销测试**

运行 `FrontCancelLifecycleClosureModuleTest`、三个旧入口 owner boundary 测试和控制器注释测试，确认专用资料页面没有破坏验证码缓存或销户申请。

- [x] **Step 3: 静态与 Blade 验证**

```powershell
php -l app\Http\Controllers\Front\LegacyPageController.php
node --check public\js\apps\front\layui\pages.js
php artisan view:cache
```

- [x] **Step 4: 登记逐路由证据并生成矩阵**

在 `docs/audits/旧项目路由核验证据.json` 显式登记本批所有 HTTP 方法、URI、当前 route name/action 和通过的测试，再运行：

```powershell
php scripts\generate-legacy-implementation-matrix.php
```

预期：本批路由全部变为 `verified`，未匹配和旧源码缺失保持 0。

**环境说明：** 当前工作区 Git 元数据不可用，因此每个任务以 RED/GREEN 测试、语法、Blade 缓存和矩阵生成器作为检查点，不执行提交命令。

**完成记录（2026-07-29）：** 六个专用 GET 动作、七类共享 Blade 表单、Session POST、两阶段验证码、60 秒发码锁定、文件预览与辅助技术错误关联均已实现。最终验证包含页面 3 项、路由 14 项、资料 58 项、验证码生命周期 10 项、注销 23 项、上传 3 项、UI 回归 118 项，并通过 PHP/JS 语法、Blade 缓存、全量旧路由审计和迁移矩阵生成。
