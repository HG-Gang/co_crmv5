# Agent Hierarchy Closure Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 完成旧版 `family_tree` 语义到新项目 `parent_id` 权威拓扑及 `agent_descendants` 派生闭包的闭环校验，确保任意代理可正确获取直接代理、间接代理和其名下普通客户，并对异常拓扑失败关闭。

**Architecture:** `user_infos.parent_id` 是唯一拓扑事实源；`family_tree` 和 `agent_descendants` 由拓扑派生。读取范围统一走 parent tree，闭包缺失时可回退，闭包重建前先完整校验并在事务内替换。

**Tech Stack:** Laravel 8/9、PHP、MySQL、PHPUnit 9。

---

### Task 1: 修正拓扑边界测试

**Files:**
- Modify: `tests/Feature/AgentHierarchyClosureRebuildTest.php`
- Test: `tests/Feature/AgentHierarchyClosureRebuildTest.php`

- [ ] **Step 1: Write failing tests** for deleted parents, invalid customer parents, and exact 128-level closure rebuild semantics.
- [ ] **Step 2: Run the focused test file** and confirm each new test fails for the intended topology reason.

### Task 2: Harden topology reads and rebuilds

**Files:**
- Modify: `app/Models/UserInfo.php`
- Modify: `app/Services/FamilyTreeService.php`
- Modify: `app/Support/FrontLegacyData.php`

- [ ] **Step 1: Make ancestor/child reads ignore soft-deleted records and validate active account types.**
- [ ] **Step 2: Make subtree/closure rebuilds reject missing, deleted, cyclic, over-depth, or customer-parent topology before writes.
- [ ] **Step 3: Run the focused tests and the related registration/scope test modules.

### Task 3: Correct the standalone closure audit

**Files:**
- Modify: `scripts/audit_agent_descendants.php`
- Test: `tests/Unit/AgentHierarchyMigrationContractTest.php`

- [ ] **Step 1: Add source-contract assertions for explicit orphan/cycle/depth classification.
- [ ] **Step 2: Replace the ambiguous `depth < 128` loop with the same inclusive 128-level rule used by production code, and do not classify an orphan as a cycle.
- [ ] **Step 3: Run the contract test and the read-only audit command.

### Task 4: Final verification

**Files:**
- Test: `tests/Feature/AgentHierarchyClosureRebuildTest.php`
- Test: related registration, commission, position, flow, and admin scope modules.

- [ ] **Step 1: Run targeted PHPUnit suites serially.
- [ ] **Step 2: Run PHP syntax checks for modified files.
- [ ] **Step 3: Run `php artisan agent-hierarchy:rebuild` without `--apply` and report any remaining real-data anomalies; do not mutate development data without explicit approval.
