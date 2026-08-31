# 后台代理商上级迁移与整棵子树闭环 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 新增后台代理专用上级迁移入口，使整棵代理子树的 MT4 关系码、本地家谱、闭包关系和审计日志保持一致。

**Architecture:** `AgentController` 负责请求、权限、等级和 Saga 编排；`FamilyTreeService` 负责生成新旧子树快照与事务内确定性应用；`Mt4ManagerService` 复用幂等 `update_user` 层级帧。前端三套后台只调用同一权限路由。

**Tech Stack:** Laravel 8、PHP 7.4、Eloquent、MySQL、PHPUnit 9、Blade/Layui、CrmUI、Naive、Lucide、旧 MT4 Socket 协议。

---

### Task 1: 写代理子树迁移红灯测试

**Files:**
- Create: `tests/Feature/AdminAgentParentSubtreeClosureModuleTest.php`

- [ ] **Step 1: 写成功迁移行为测试**

覆盖代理根、下级代理和客户，记录 MT4 调用时数据库旧快照，并断言成功后的 `parent_id`、全部 `family_tree`、`agent_descendants` 与 `operation_logs`。

- [ ] **Step 2: 写失败与权限测试**

覆盖 MT4 中途失败补偿、目标为子孙导致循环、等级倒挂、普通客户目标、目标代理越过管理员数据范围和现代普通资料入口拒绝代理迁移。

- [ ] **Step 3: 运行红灯**

Run: `php vendor/bin/phpunit --colors=never tests/Feature/AdminAgentParentSubtreeClosureModuleTest.php`

Expected: FAIL，原因是代理专用路由、控制器方法和子树快照方法尚不存在。

### Task 2: 实现层级快照与原子本地应用

**Files:**
- Modify: `app/Services/FamilyTreeService.php`

- [ ] **Step 1: 增加代理子树迁移快照方法**

从真实 `parent_id` 图读取子树、旧祖先和目标祖先，检测缺失节点、非代理祖先和循环，并为每个受影响账号生成旧/新 `zip/cny/family_tree`。

- [ ] **Step 2: 增加事务内应用方法**

调用方持有数据库事务与锁时复核快照，更新根代理上级、逐节点写 `family_tree`，再重建受影响闭包行；缺失记录必须抛异常，不能静默返回。

- [ ] **Step 3: 运行服务相关红灯到目标阶段**

Run: `php vendor/bin/phpunit --colors=never --filter hierarchy_snapshot tests/Feature/AdminAgentParentSubtreeClosureModuleTest.php`

Expected: 服务快照用例通过，路由用例仍失败。

### Task 3: 实现控制器远端同步与补偿

**Files:**
- Modify: `app/Http/Controllers/Admin/AgentController.php`
- Modify: `routes/admin.php`

- [ ] **Step 1: 增加专用路由与严格请求校验**

兼容资源路由与旧字段，校验代理身份、目标代理、等级规则和双向数据范围。

- [ ] **Step 2: 按快照同步 MT4**

逐账号调用 `updateUserHierarchy`；失败时逆序补偿已成功账号并返回 `MT4_SYNC_FAILED`。

- [ ] **Step 3: 提交本地事务并处理补偿**

锁定和复核快照后应用本地树，写操作日志；事务异常后逆序补偿全部 MT4 账号并返回 `INTERNAL_ERROR`。

- [ ] **Step 4: 运行目标测试到绿灯**

Run: `php vendor/bin/phpunit --colors=never tests/Feature/AdminAgentParentSubtreeClosureModuleTest.php`

Expected: 后端行为用例全部通过。

### Task 4: 接入权限与三套后台入口

**Files:**
- Create: `database/migrations/2026_07_29_000001_add_admin_agent_parent_update_permission.php`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`
- Modify: `resources/admin/layui/agents/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`
- Modify: relevant Naive admin files discovered by the failing frontend contract test
- Test: `tests/Feature/AdminAgentParentSubtreeFrontendClosureModuleTest.php`

- [ ] **Step 1: 写前端与权限红灯测试**

断言权限路由、三套入口、字段含义、Lucide 图标和无表情符号。

- [ ] **Step 2: 增加可重复执行的权限迁移**

写入或恢复 `admin_api_updateAgentParent` 动作权限，并绑定现有后台管理员角色策略。

- [ ] **Step 3: 增加三套“调整上级”操作**

统一提交目标代理 ID，成功后刷新，失败展示后端消息。

- [ ] **Step 4: 运行前端契约测试**

Run: `php vendor/bin/phpunit --colors=never tests/Feature/AdminAgentParentSubtreeFrontendClosureModuleTest.php`

Expected: 权限和三套入口契约全部通过。

### Task 5: 文档与回归

**Files:**
- Modify: `docs/admin-legacy-migration-gap-audit.md`
- Modify: `docs/admin-backend-blade-permission-final-checklist.md`

- [ ] **Step 1: 追加中文执行链路与返回码**

记录路由、参数、权限、MT4 顺序、补偿、本地事务、各返回码中文含义和测试证据。

- [ ] **Step 2: 运行专项与关联回归**

Run: `php vendor/bin/phpunit --colors=never --filter AdminAgent tests/Feature`

Run: `php vendor/bin/phpunit --colors=never --filter AdminUserUpdate tests/Feature`

- [ ] **Step 3: 运行语法、路由和构建检查**

Run: `php -l app/Http/Controllers/Admin/AgentController.php`

Run: `php -l app/Services/FamilyTreeService.php`

Run: `php artisan route:list --name=admin_api_updateAgentParent`

Run: `npm run build`

- [ ] **Step 4: 启动服务并做浏览器闭环**

验证桌面与移动视口中的代理列表、调整上级弹窗、Lucide 图标、请求结果和无重叠状态。

- [ ] **Step 5: 记录版本控制限制**

当前目录没有可靠 Git 元数据时，不伪造 commit；以文件哈希、红绿测试、路由输出、构建和浏览器截图作为证据。
