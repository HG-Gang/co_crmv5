> **【2026-08-29 收尾状态】本报告所列事项已全部闭环**：§一 三项缺陷（前台出金来源筛选、CRLF 正则、
> 手机号夹具）已修复；§三 27+1 条失败根因修复（迁移自举，见 2026-08-29-front-serial-pollution-fix.md，
> 此后 6 轮全量串行全绿）；§五.1 批量导入上传 UI、§五.2 旧资料页上传迁移、§五.3 译文排查均已闭环；
> §四.1 comm_rate 契约已按百分数 0..100 统一修复（见 2026-08-29-comm-rate-percent-semantics-fix.md）。
> 唯一遗留：正式库返佣索引迁移 2026_08_28_000001（已只读核验：索引与生成列未建、降级路径运行正常、
> 872140 行完好；执行需运维排期与明确授权）。

# 全量自测自查交接报告（2026-08-28）

> 依据：`scripts/run-full-serial.ps1` 全量串行运行 `storage/logs/full-serial-20260828-024132.out`
> 结论：**当前不可宣称「百分百无问题」**。全量串行 `4302 tests / 80239 assertions / 1 error / 27 failures`（exit 2）。

---

## 一、本轮已修复并验证的真实缺陷（3 项）

### 1. 前台账户流水「出金来源」筛选静默失效（功能缺陷，最严重）

- **文件**：`resources/front/layui/flow/index.blade.php`
- **原状**：`<option value="{{ __('front.bank_transfer') }}">`，即把**当前语言的译文**当作提交值。
- **失效机制**：option 上的 `data-translate` 只改写**文案**，不改写 **value**。页内切换语言后，显示英文而提交值仍是中文，
  `FlowController::applyWithdrawSourceFilter`（`app/Http/Controllers/Front/FlowController.php:262,268`）比对失败，
  静默落到 `bank_name LIKE` 兜底，**返回空列表且不报错**。资金页面的静默错误结果，属最难察觉的一类。
- **修复**：option value 改为与语言无关的稳定键 `bank_transfer` / `crypto_currency`。控制器本就接受稳定键，无需改后端。
- **验证**：新增 `tests/Feature/FrontFlowWithdrawSourceLocaleClosureModuleTest.php`，
  完成完整红绿循环——修复后绿（3 tests / 10 assertions）→ 回退修复后**红（2 failures）**→ 恢复后绿。
  既有 `--filter flow` 回归 `117 tests / 1713 assertions` 通过。

### 2. `Mt4SyncGateClosureModuleTest` CRLF 脆弱正则

- **文件**：`tests/Feature/Mt4SyncGateClosureModuleTest.php:58-67`
- **原因**：`/^MT4_ENABLED=false$/m` 在 CRLF 文件上永远匹配不上——PCRE 的 `$` 在 `/m` 下只锚定 `\n` 之前，行内残留 `\r`。
- **确认非本轮引入**：`.gitattributes` 为 `* text=auto`；`.env`（08-02 写入）CRLF=71/bareLF=0，
  `.editorconfig`、`.styleci.yml`（05-30）同为 CRLF。CRLF 是该 Windows 检出的既有常态。
- **修复**：正则改为 `\r?$`。项目内既有正确写法可参考 `DatabaseSeederDemoGateClosureModuleTest.php:92`（先 `str_replace("\r\n","\n")`）。
- **验证**：`16 tests / 23 assertions` 通过。

### 3. `UserMt4ProvisioningRuntimeClosureModuleTest` 两条失败 —— **需求 4 引入的真实回归**

- **子智能体曾报告此项为「既有且无关」，该结论经核验为错误。**
- **根因**：`fixtureUserId()`（同文件 L1106）为 `random_int(480000000, 489999998)`，即 **9 位**数字；
  测试把它直接当 `phone_number` 提交。需求 4 新增的服务端规则
  `AuthController.php:181` `min:11` 拒绝 9 位 → `VALIDATION_FAILED(4005)`。
  改动前规则为 `max:30` 且**无下限**，故 9 位可通过。
- **修复取向**：生产规则是正确业务逻辑（11–20 位纯数字，客户端 `minlength="11" maxlength="20" size="20"` 与服务端
  `register()`/`registerSendCode()` 两处完全一致），因此**修夹具而非削弱规则**：改为 `'139' . $userId`（12 位且保持唯一）。
- **验证**：`44 tests / 207 assertions` 通过。
- **同类排查**：全库扫描其余 `phone_number` 夹具（`FrontAuthRegistrationLoginClosureTest`、
  `FrontBusinessModulesClosureTest`、`RegisteredUserFixtureCleanerTest`）均为 11 位以上，逐个运行通过。
  `FrontSharedUploadAndFieldErrorContractTest` 中的 `max:30` 是 `assertStringNotContainsString`，属正确断言。

---

## 二、静态全量审计：全部通过（新鲜执行）

| 检查项 | 范围 | 结果 |
| --- | --- | ---: |
| `php -l` | app/config/routes/database 共 466 个文件 | 0 failures |
| `node --check` | public/js 非 vendor 共 41 个文件 | 0 failures |
| Blade 编译 | `view:cache`，196 个模板 | exit 0 |
| `view:clear` | — | exit 0 |
| `route:list` | 1902 行 | exit 0 |
| `config:cache` / `config:clear` | — | exit 0 |

---

## 三、全量串行未闭环项：1 error + 27 failures

### A. 26 条前台失败 —— 顺序依赖，非业务逻辑缺陷

- `FrontBusinessModulesClosureTest` 21 条 + `FrontAuthRegistrationLoginClosureTest` 5 条（step4–step9）。
- **真实错误**：`code=5000` 内部错误，异常为
  `InvalidArgumentException: 代理未配置有效等级`，抛出点 `app/Services/FamilyTreeService.php:432`
  （`legacyRelationshipCode()`：祖先节点的 `level_id` 无法解析到 `agent_levels` 时抛出）。
  step4 失败后 step5–step9 属级联。
- **已排除的假设**（均有证据）：
  1. ~~迁移与 seeder 顺序洞~~：`agent_levels` 仅由 seeder 写入，
     `2026_08_01_000002_ensure_front_inviter_test_agent.php:68` 在 migrate 阶段读取该表。
     但**实测**邀请代理 `user_id=10` 的 `level_id=1` 可解析，且全库
     `account_type=1 且 level_id 无法解析` 的代理数为 **0**。
  2. ~~trait 混用~~：全库 `RefreshDatabase` **0** 处、`DatabaseTransactions` **330** 处，无 `agent_levels` truncate/delete。
  3. ~~`AdminLegacyAgentStatisticsAdapterTest` 是污染源~~：三个类合并运行
     `43 tests / 170 assertions` **全部通过**。
- **两个类单独运行均通过**：`FrontBusinessModulesClosureTest` 21/21；`test_step4_complete_registration` 1/1。
- **结论**：4302 条串行运行中，某个尚未定位的用例在其事务内制造了 `level_id` 无法解析的祖先状态。
  与项目既有记录的「共享测试库污染 / 夹具 AUTO_INCREMENT 漂移」同类
  （见进度文档 §2.5；另注：`agent_levels` 实际 id 为 `1,2,3,23,24`，**存在 AUTO_INCREMENT 漂移**，
  若有夹具硬编码 level_id=4/5 即会写入非法值——**这是下一步最值得验证的假设**）。

### B. 2 条后台失败（独立问题，未定位）

- `AdminLegacyAgentUserSaveClosureModuleTest::test_legacy_agents_save_creates_agent_with_legacy_field_names`
  断言两个**视觉上相同**的 `'0.85'` 不相等 → 疑为类型/精度/不可见字符差异，
  **疑与下述 `comm_rate` 三方契约冲突同源**。
- `AdminLegacyAgentStatisticsAdapterTest::test_legacy_agent_searches_honor_old_filters_and_return_old_table_contract`（error）。

### 下一步最小复现命令（下一会话首先执行）

```powershell
# 必须先 migrate:fresh --seed 复现全量条件，再取 step4 的真实 errors 字段
vendor\bin\phpunit --% --colors=never --filter "test_step4_complete_registration"
# 验证 AUTO_INCREMENT 漂移假设：搜索硬编码 level_id 的夹具
```
- 优先验证：是否有夹具硬编码 `level_id` 为 4 或 5（实际为 23/24）。
- 该根因一旦确认，很可能一次性解释 26 条中的全部。

---

## 四、需要人工决策的两项（未擅自改动）

### 1. `user_infos.comm_rate` 三方契约冲突（既有缺陷）

| 位置 | 契约 |
| --- | --- |
| 表结构 `2026_03_29_000011_create_user_infos_table.php:59` | `integer('comm_rate')` |
| 迁移种子 `2026_08_01_000002:102` / 生产实测 | `65` / `85`（即百分数） |
| `AgentController.php:430` | `numeric\|min:0\|max:1`（分数） |
| `UserController.php:128` | `numeric\|min:0\|max:1`（分数） |
| `LegacyAdminController.php:4629` | `numeric\|min:0\|max:100`（百分数） |

- **后果**：现代后台表单输入 `0.65` 写入 integer 列会**截断为 0**；`max:1` 下实际只能设 0 或 1，
  **等于现代后台无法设置真实返佣比例**。
- **已确认无算术消费者**：全库 `comm_rate` 仅出现在注释、存储与展示；
  前端 `formatRate()` 用「≤1 视为分数、否则视为百分数」双语义兜底，说明该歧义长期存在。
- **建议**：证据强烈指向**百分数 0..100**，即两个现代控制器的 `max:1` 是错的。
  但这属金额相邻业务语义变更，需明确授权后再改。

### 2. `2026_08_28_000001_add_mt4_trades_rebate_lookup_index` 尚未在 `co_crmv5` 执行

- 迁移本身已核验合格：幂等（`hasColumn`/`indexExists`）、仅 MySQL、`down()` 可回滚、
  关键词与 `RealtimeCommissionController::REBATE_COMMENT_KEYWORDS` 由测试锁定防漂移。
- 控制器**降级路径已核验**：`Schema::hasColumn` 探针在生成列缺失时回退关键词 LIKE + 表达式排序，
  故**生产当前正确运行**；`MAX_PER_PAGE=100` 与前端 debounce/abort 已即时生效。
- 未执行原因：`ALTER` 需重建 872,140 行表（基准约 24 秒），属需排期的运维动作。

---

## 五、其余已知缺口（非本轮引入）

1. **后台批量导入无上传 UI**：credit / deposit / withdraw-imports 的「新增」是手工单条表单；
   旧 Excel URI 经 `LegacyAdminController.php:5316,5337,5360` 映射且有 API 测试，但后台无法上传批量文件。
   `routes/web.php:994` 有既有备注「后续可继续扩展 Excel 上传」。
2. **`resources/front/layui/profile/legacy-action.blade.php`** 仍用自有 `data-profile-file` 上传，
   未并入共享 `layui-upload.js`。该页有独立错误/CSRF 契约，半迁移会威胁
   `FrontLegacyProfileVerificationLifecycleClosureModuleTest`，刻意保留。
3. **建议全库排查同类「译文当 option value」模式**：本轮只修了流水页，该缺陷类未必唯一。
