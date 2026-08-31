# Front Forgot Password Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 闭合新旧前台找回密码的用户身份、邮箱验证码、密码确认、邮件发送和一次性消费链路，消除空验证码重置任意账号漏洞。

**Architecture:** `front_reset_code:{email}` 缓存保存 `user_id/email/code` 绑定结构。现代接口按邮箱验证；旧接口额外严格验证 `userId/user_id/accountno`，兼容旧表单 `userverfcode` 与 `againpassword` 字段。验证码邮件发送成功后才写缓存，改密成功后立即删除缓存。

**Tech Stack:** Laravel 8、PHPUnit 9、Cache、Mail、Eloquent、Laravel Validator。

**Repository note:** 当前 `.git` 目录没有 `HEAD`，以 RED/GREEN 测试和文件级检查点代替提交。

---

### Task 1: Reproduce the legacy password reset bypass

**Files:**
- Create: `tests/Feature/FrontForgotPasswordSecurityClosureModuleTest.php`

- [ ] **Step 1: Add failing tests**

Create real `user_logins` fixtures and cover:

- `/user/change_password` with valid `userId` and password but no code must fail and preserve the old hash.
- `userId=<real-id>abc` must return `IDerror` and preserve the real numeric-prefix account.
- valid legacy `userverfcode` plus matching `againpassword` must update only the bound user and remove the cache entry.
- mismatched `againpassword` must return `passworderr` and preserve the old hash.
- disabled user must return `UserDisable` and preserve the old hash.

- [ ] **Step 2: Run the test and confirm RED**

```powershell
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\FrontForgotPasswordSecurityClosureModuleTest.php --colors=never
```

Expected: missing-code and prefixed-ID samples expose the current bypass; the legacy `userverfcode` success sample fails because the controller ignores that alias.

### Task 2: Bind send and verification stages to the same user

**Files:**
- Modify: `tests/Feature/FrontForgotPasswordSecurityClosureModuleTest.php`
- Modify: `app/Http/Controllers/Front/ForgotPasswordController.php`
- Create: `app/Mail/FrontResetPasswordCode.php`
- Create: `resources/views/emails/front-reset-password-code.blade.php`
- Modify: `resources/lang/zh-CN/auth.php`
- Modify: `resources/lang/en/auth.php`

- [ ] **Step 1: Add failing send/verification tests**

Cover:

- legacy send with matching strict `userId + useremail` sends one mail and writes an array payload with the same user ID, normalized email and code.
- mismatched ID/email and prefixed ID return `status=false`, send no mail and write no reset cache.
- legacy verification rejects a code payload bound to a different user ID.
- modern send continues to work without a user ID.

- [ ] **Step 2: Run tests and confirm RED**

Expected: current string cache and missing Mail call fail the new assertions.

- [ ] **Step 3: Implement send-stage closure**

Add a standard Laravel Mailable and reset mail subject/body language keys. In `sendResetCode()`:

- normalize email;
- detect legacy requests by old field presence;
- strictly validate legacy user ID before query;
- require active login and matching email for legacy requests;
- rate-limit by email and requester IP for 60 seconds;
- send localized mail inside `try/catch`;
- only after mail success cache `['user_id' => ..., 'email' => ..., 'code' => ...]` for 600 seconds;
- return legacy `{status:false}` on old-flow validation or mail failure, while preserving modern ApiResponse errors.

Update `forgetPasswordInfoVerification()` to validate strict user ID, matching email and bound cache payload.

### Task 3: Close final password mutation

**Files:**
- Modify: `app/Http/Controllers/Front/ForgotPasswordController.php`
- Modify: `tests/Feature/FrontForgotPasswordSecurityClosureModuleTest.php`

- [ ] **Step 1: Implement minimal final-stage rules**

In `saveChangePassword()`:

- validate raw ID with `required|integer|min:1` before integer conversion or query;
- require active account;
- normalize code from `codedata`, `code`, or `userverfcode` and require it;
- normalize confirmation from `password_confirmation` or `againpassword` and require exact password match;
- require cached payload user ID, email and code to match the target login;
- update password hash and delete reset cache only after successful validation.

Update `resetPassword()` to read the same bound cache payload and verify email/code before updating.

- [ ] **Step 2: Run security tests and confirm GREEN**

Run the Task 1 test file. Expected: all samples pass.

### Task 4: Record closure evidence and regressions

**Files:**
- Modify: `docs/admin-backend-blade-permission-final-checklist.md`
- Verify: `tests/Feature/FrontForgotPasswordControllerCommentReadabilityTest.php`
- Verify: `tests/Feature/FrontLegacyRouteCompatibilityTest.php`
- Verify: `tests/Feature/FrontUiRegressionTest.php`

- [ ] **Step 1: Add checklist section 340**

Record affected routes, changed files, RED/GREEN commands, the empty-code bypass, strict ID behavior, bound cache payload, real mail send, legacy aliases and remaining boundaries.

- [ ] **Step 2: Run targeted regressions**

Run each test file independently:

```powershell
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\FrontForgotPasswordSecurityClosureModuleTest.php --colors=never
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\FrontForgotPasswordControllerCommentReadabilityTest.php --colors=never
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --colors=never
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\FrontUiRegressionTest.php --colors=never
```

Expected: all tests pass with no warnings or errors.
