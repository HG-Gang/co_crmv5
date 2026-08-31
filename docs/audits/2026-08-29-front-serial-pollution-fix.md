# 全量串行 27+1 条失败根因修复报告（2026-08-29）

> 依据：`docs/audits/2026-08-28-full-serial-audit-handoff.md` 遗留的 1 error + 27 failures；
> 本轮以 MySQL general_log 全程取证 + 三次全量串行（12:26 / 12:48 / 13:16）复现与验证。
> 结论：**27 条失败为单一确定性根因，已修复并以红绿循环 + 回归锁定测试 + 全量串行验证闭环。**

---

## 一、失败画像（修复前，两次全量串行均复现）

| # | 测试 | 症状 |
| --- | --- | --- |
| 1 | `AdminLegacyAgentUserSaveClosureModuleTest::test_legacy_agents_save_creates_agent_with_legacy_field_names` | agents_save 返回 **4005**（VALIDATION_FAILED，`agent_level_not_found`），期望 1001 |
| 2 | `FrontAuthRegistrationLoginClosureTest` step4–step9（5 条） | 注册返回 **5000**，异常 `InvalidArgumentException: 代理未配置有效等级。`（`FamilyTreeService.php:432`） |
| 3 | `FrontBusinessModulesClosureTest` 21 条 | 级联：注册失败 → 登录 token 为 NULL → `assertNotEmpty` 于 `FrontBusinessModulesClosureTest.php:77` 失败 |
| 4 | `AdminLegacyAgentStatisticsAdapterTest`（仅 08-28 出现 1 error） | `table_fingerprint_mismatch`（详见 §四，独立缺陷） |

三个失败类的共同前提：注册/建档链路的根代理 **user_id=10**（`LEGACY_ROOT_AGENT_ID = 10`，前台注册默认邀请人）。

## 二、根因链（general_log 逐条 SQL 铁证）

1. `php artisan migrate:fresh --seed` 中，**migrations 先于全部 seeders 执行**；
2. 迁移 `2026_08_01_000002_ensure_front_inviter_test_agent.php` 在 migrate 阶段创建/修复 user 10 时读取
   `agent_levels`——**该表此刻是空表**，旧代码回退链 `where('level_code',1)->value('id') ?: value('id') ?: 0` 落到
   **0**，user 10 以 `level_id=0, group_id=0` 落库（general_log 行 1660：`insert into user_infos ... values (10, 2,
   'Demo Inviter Agent', ..., 0, 0, ...)`，remark='Permanent front inviter test agent'）；
3. 历史上由 `FrontDemoDataSeeder::seedUsers → upsertUserInfo(level = $levelIds[1])` 事后把 user 10 的等级修为 1；
   但 2026-08-17 22:18 上线的 Demo Seeder 安全门禁（§2.3）要求 `FRONT_DEMO_SEEDER_ENABLED=true`，串行脚本不设置该
   开关 → **seeder 被跳过 → user 10 全程 level_id=0**；
4. 运行窗口内：agents_save 走 `UserRegistrationService::resolveAgentLevel` 读 user 10 的 level → `agent_levels
   where id = 0` 落空 → 4005（general_log 行 13778-13779）；前台注册走
   `resolveCustomerHierarchy → legacyRelationshipCode` → `select level_code, id from agent_levels where id in (0)`
   落空 → 抛异常 → 5000（general_log 行 51701-51702，连续 20+ 个连接同一症状）；
5. 「污染事后消失」的假象解释：失败窗口之后的 admin legacy 资料类测试（04:52:34 起）以完整旧字段集
   `update user_infos set ... level_id = 1 ... where user_id = 10`，顺带把 user 10 修回——串行结束后探测库状态完好，
   与「事务击穿后自愈」画像吻合，实为确定性种子缺陷。

**为何 08-17 11:53 基线（3641 tests 全绿）之后才爆发**：该基线运行于 Demo Seeder 门禁（08-17 22:18）生效之前，
seeder 当时尚会在串行内执行并修复 user 10；门禁上线后首次全量串行（08-28）即暴露本缺陷。

## 三、修复内容（红绿循环）

### 3.1 迁移自举修复（本轮核心修复）

- **文件**：`database/migrations/2026_08_01_000002_ensure_front_inviter_test_agent.php`
- **改动**：`agent_levels` 无 `level_code=1` 行时，迁移内幂等 `insertGetId` 自举基础行
  （`一级代理 / max 80 / min 60 / user 0`，与 `InitialDataSeeder` 基线一致；seeder 随后 `updateOrInsert` 无缝接管），
  再取其 id 作为 user 10 的 `level_id`；不再存在「空表 → 0」回退路径。
- **安全性**：迁移为幂等 `updateOrInsert` 语义；生产库 `co_crmv5` 中该迁移已执行过（migrations 表在册），
  不会重跑；对测试库每次 `migrate:fresh` 均生效。
- **红证据**（修复前 fresh）：`user10 RED state: level_id=0 group_id=0`。
- **绿证据**（修复后 fresh）：`user10 GREEN state: level_id=1`，agent_levels 含 code 1/2/3。

### 3.2 回归锁定测试（新增）

- **文件**：`tests/Feature/FrontInviterAgentLevelBootstrapClosureModuleTest.php`（2 tests / 6 assertions，绿）
- 锁定点：① 空 agent_levels 上执行迁移 up() 必须自举 level_code=1 行且 user 10.level_id 指向它；
  ② `FamilyTreeService::resolveCustomerHierarchy(x, 10)` 祖先链与关系码可正常生成；
  ③ 重复执行 up() 不产生重复基础行。

### 3.3 附带修复：MySqlTableFingerprint 指纹间歇失败

- **现象**：08-28 串行 1 error + 本地/Admin 子集各 1 error，`table_fingerprint_mismatch`；
  diff 显示 `row_count / content_digest / structure_hash` 前后完全一致，**仅 `auto_increment` 字段漂移 ±1**
  （如 commission_transfers 18→19、role_permissions 142→143），且 `MySqlAutoIncrementSnapshot::restore()` 的
  捕获-预检-复核门禁在同一窗口内已确认恢复成功——属 MySQL 8.0.12 对 `information_schema.TABLES.AUTO_INCREMENT`
  的间歇性陈旧视图，非业务行残留。
- **修复**：`tests/Support/MySqlTableFingerprint::capture()` 不再把 `auto_increment` 纳入指纹（行数/内容摘要/
  引擎/结构哈希四字段照旧严格比对）；自增门禁由 `MySqlAutoIncrementSnapshot::restore()` 独立承担。类 docblock
  已记录理由与证据。
- **契约同步**：`tests/Unit/PaymentFixtureContractTest` 的确定性指纹契约断言由五字段改为四字段，并新增
  反向断言（禁止 `auto_increment` 指纹字段回归）与 docblock 理由锁定（2026-08-29-fingerprint-contract-sync.md）。
- **验证**：`AdminLegacyAgentStatisticsAdapterTest` 13/13、`CommissionTransferReconciliation` 11/11、
  指纹单测 1/1 全绿；修复后两次全量串行 0 error。

## 四、修复后验证

| 验证项 | 结果 |
| --- | --- |
| `migrate:fresh --seed` 后 user 10 等级 | level_id=1（可解析），agent_levels code 1/2/3 在位 |
| 三个原失败类合并回归 | `44 tests / 137 assertions` exit 0 |
| 新增回归锁定测试 | `2 tests / 6 assertions` exit 0 |
| 全量串行（修复后最终轮） | 见文末「最终结果」小节 |
| `php -l` | 全部改动文件 0 failures |

## 五、子智能体并行审计结论（存档）

- **事务击穿机制**（子智能体静态审计）：Laravel 8.83 `DatabaseTransactions` + 中途 `disconnect()` 会击穿测试事务
  （`Connection::disconnect` 不重置 `$transactions` 计数器），可达路径为
  `WithdrawalOrderService::releaseReservationLock` 的 disconnect 兜底（app/Services/Withdrawal/WithdrawalOrderService.php:298）
  与 `MySqlFixtureMutex::releaseWithDisconnectFallback`（tests/Support/MySqlFixtureMutex.php:116）。
  本轮 27 条失败**并非**该机制所致（general_log 已锁定为确定性种子缺陷）。
- **护栏已落地（第二轮加固，2026-08-29）**：
  ① `MySqlFixtureMutex::releaseWithDisconnectFallback` 在 disconnect 前校验 `DB::connection()->transactionLevel()`，
  挂起事务存在时跳过 disconnect 并失败关闭抛出（锁残留显式暴露，不再静默击穿测试事务）；新增 `isHeld()` 访问器；
  单测扩展为 3 tests（含跳过路径用例）。
  ② `WithdrawalOrderService::releaseReservationLock` 的 disconnect 兜底同样加 `transactionLevel() > 0` 护栏，
  挂起事务时改为记录 `reservation_lock_disconnect_skipped_active_transaction` 日志并保持连接存活；
  正常提现流程（释放时事务已结束）行为不变，无测试锁定旧行为（全库无 lockDisconnector 引用断言）。
  ③ `AgentLevelController@destroy` 的无引用校验为文档明示契约（"应由后续业务规则或数据库约束控制"），本轮不改。
- **译文当提交值排查**：全库 Blade 静态扫描 0 命中（详见 2026-08-29-translation-as-option-value-sweep.md）。

## 六、最终结果

> 全量串行 `storage/logs/full-serial-20260829-132018.out`（2026-08-29 13:20 启动，13:48 完成，13:48.705）：
> **`OK (4304 tests, 80304 assertions)` / `PHPUNIT_EXIT=0`** —— 含 2 条新增回归锁定测试，
> 较修复前（4302 tests / 1 error + 27 failures / exit 2）实现全绿闭环。
> general_log 取证文件（已关闭）：`<MySQL datadir>/general-serial-20260829.log`（约 23MB）。
>
> 第二轮加固（事务击穿护栏 + 互斥锁单测 +1）后的确认串行 `storage/logs/full-serial-20260829-135656.out`（14:04.505）：
> **`OK (4305 tests, 80315 assertions)` / `PHPUNIT_EXIT=0`** —— 加固未引入任何回归。
