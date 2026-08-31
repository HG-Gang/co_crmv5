# 后台普通客户上级代理变更闭环 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 让后台普通客户资料编辑的旧字段 `userparentId` 完成 MT4 与本地代理层级的一致迁移。

**Architecture:** 控制器负责身份、字段和外部调用编排；`FamilyTreeService` 负责层级计算与闭包表重建；`Mt4ManagerService` 负责旧协议 `update_user` 帧。所有本地层级写入位于同一事务，外部失败关闭，本地失败执行 MT4 补偿。

**Tech Stack:** Laravel 8、PHP 7.4、Eloquent、MySQL、PHPUnit 9、旧 MT4 Socket 协议。

---

### Task 1: 写红灯业务测试

**Files:**
- Create: `tests/Feature/AdminUserUpdateParentHierarchyClosureModuleTest.php`

- [ ] **Step 1: 写成功迁移测试**

```php
$response = $this->postJson('/api/admin/updateUser', [
    'data' => ['userId' => $customerId, 'userparentId' => $newAgentId],
]);
$response->assertJsonPath('code', ResponseCode::UPDATED);
$this->assertDatabaseHas('user_infos', ['user_id' => $customerId, 'parent_id' => $newAgentId]);
```

- [ ] **Step 2: 写失败关闭与字段边界测试**

覆盖 MT4 失败、非代理上级、数据范围外上级和现代 `parent_id` 被忽略。

- [ ] **Step 3: 运行红灯**

Run: `php vendor/bin/phpunit --colors=never tests/Feature/AdminUserUpdateParentHierarchyClosureModuleTest.php`

Expected: FAIL，原因是 `userparentId` 尚未归一，MT4 层级方法和本地闭包重建尚不存在。

### Task 2: 写 MT4 协议红灯测试

**Files:**
- Modify: `tests/Feature/Mt4ManagerServiceLegacyProtocolClosureModuleTest.php`

- [ ] **Step 1: 新增 update_user 层级帧断言**

```php
$result = $service->updateUserHierarchy(1001, 2002, '00002002000000000000');
$this->assertStringContainsString('acc=1001&zip=2002&cny=00002002000000000000', $written);
```

- [ ] **Step 2: 运行红灯**

Run: `php vendor/bin/phpunit --colors=never --filter update_user_hierarchy tests/Feature/Mt4ManagerServiceLegacyProtocolClosureModuleTest.php`

Expected: FAIL，原因是 `Mt4ManagerService::updateUserHierarchy` 尚不存在。

### Task 3: 实现层级服务与控制器编排

**Files:**
- Modify: `app/Services/Mt4ManagerService.php`
- Modify: `app/Services/FamilyTreeService.php`
- Modify: `app/Http/Controllers/Admin/AdminUserController.php`

- [ ] **Step 1: 增加 MT4 幂等层级更新方法**

```php
public function updateUserHierarchy($userId, $parentId, string $relationshipCode)
{
    return $this->sendCommand('update_user', [
        'acc' => $userId,
        'zip' => $parentId,
        'cny' => $relationshipCode,
    ]);
}
```

- [ ] **Step 2: 增加层级计算和闭包表同步方法**

`FamilyTreeService` 必须沿 `parent_id` 解析祖先、检测循环、生成 `family_tree` 和五段关系码，并在调用方事务内重建受影响代理的闭包关系。

- [ ] **Step 3: 接入 updateUser**

只从 `userparentId` 归一出 `legacy_parent_id`；校验普通客户、代理上级和管理员范围；先调用 MT4，再在事务内写层级与日志；事务异常时补偿 MT4。

- [ ] **Step 4: 运行目标测试到绿灯**

Run: `php vendor/bin/phpunit --colors=never tests/Feature/AdminUserUpdateParentHierarchyClosureModuleTest.php tests/Feature/Mt4ManagerServiceLegacyProtocolClosureModuleTest.php`

Expected: 目标用例全部通过。

### Task 4: 文档与回归

**Files:**
- Modify: `docs/admin-backend-blade-permission-final-checklist.md`

- [ ] **Step 1: 追加中文执行链路和返回码说明**

记录 `/api/admin/updateUser` 到 `Mt4ManagerService`、`FamilyTreeService`、本地事务和补偿分支的完整链路。

- [ ] **Step 2: 运行专项回归**

Run: `php vendor/bin/phpunit --colors=never --filter AdminUserUpdate tests/Feature`

Run: `php vendor/bin/phpunit --colors=never tests/Feature/AdminDataScopeControllerWiringTest.php tests/Feature/Mt4ManagerServiceLegacyProtocolClosureModuleTest.php`

- [ ] **Step 3: 运行语法检查**

Run: `php -l app/Http/Controllers/Admin/AdminUserController.php`

Run: `php -l app/Services/FamilyTreeService.php`

Run: `php -l app/Services/Mt4ManagerService.php`

Run: `php -l tests/Feature/AdminUserUpdateParentHierarchyClosureModuleTest.php`

- [ ] **Step 4: 记录版本控制限制**

当前工作目录没有 Git 元数据，无法执行计划技能要求的 commit；以文件哈希、专项测试和语法检查作为本轮可复核证据，不伪造提交记录。

