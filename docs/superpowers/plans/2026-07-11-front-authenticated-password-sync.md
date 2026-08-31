# Front Authenticated Password Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 统一已登录主动改密和找回密码的 MT4/本地密码写入顺序，并在成功后失效当前 JWT 与旧 session。

**Architecture:** 新增 `UserPasswordService` 作为唯一密码写入入口：MT4 禁用时进入明确本地模式；MT4 启用时外部同步成功后才更新 `user_logins.password`。三个前台控制器只负责各自参数和响应格式，避免重复业务写入逻辑。

**Tech Stack:** Laravel 8、Eloquent、MT4 Facade、JwtService、PHPUnit 9。

---

### Task 1: Password service RED/GREEN

**Files:**
- Create: `app/Services/UserPasswordService.php`
- Create: `tests/Feature/UserPasswordServiceTest.php`

- [x] Write tests proving local mode updates the hash, MT4 success updates the hash, and MT4 failure preserves the old hash.
- [x] Run the unit test and confirm RED because the service does not exist.
- [x] Implement `change(UserLogin $login, string $newPassword): bool` with MT4-before-local ordering.
- [x] Run the unit test and confirm GREEN.

### Task 2: Authenticated controller failure boundaries

**Files:**
- Modify: `tests/Feature/FrontProfilePasswordOwnerBoundaryClosureModuleTest.php`
- Modify: `app/Http/Controllers/Front/ProfileController.php`
- Modify: `app/Http/Controllers/Front/AuthController.php`
- Modify: `app/Http/Controllers/Front/ForgotPasswordController.php`

- [x] Add failing modern and legacy profile tests: MT4 failure returns `MT4_SYNC_FAILED`/`neterr` and preserves local hashes.
- [x] Inject and use `UserPasswordService` in Profile, Auth and Forgot controllers; remove direct password hash updates and the duplicated Forgot MT4 helper.
- [x] Run profile and forgot-password security tests and confirm GREEN.

### Task 3: Session invalidation

**Files:**
- Modify: `tests/Feature/FrontProfilePasswordOwnerBoundaryClosureModuleTest.php`
- Modify: `app/Http/Controllers/Front/ProfileController.php`

- [x] Add failing tests proving successful modern change invalidates `jwt_token` and successful legacy change logs out the user guard and removes `suser`.
- [x] Implement one post-success helper that invalidates JWT when present, logs out the user guard and removes the legacy session user.
- [x] Run tests and confirm GREEN.

### Task 4: Documentation and regressions

**Files:**
- Modify: `docs/admin-backend-blade-permission-final-checklist.md`
- Verify: `tests/Feature/FrontProfilePasswordOwnerBoundaryClosureModuleTest.php`
- Verify: `tests/Feature/FrontForgotPasswordSecurityClosureModuleTest.php`
- Verify: `tests/Feature/FrontProfileControllerCommentReadabilityTest.php`
- Verify: `tests/Feature/FrontUiRegressionTest.php`

- [x] Add section 341 with RED/GREEN evidence and changed-file logic.
- [x] Run each target test independently and require zero failures.
