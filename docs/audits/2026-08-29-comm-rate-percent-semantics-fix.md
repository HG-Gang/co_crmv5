# user_infos.comm_rate 百分数语义统一修复报告（2026-08-29）

> 依据：`docs/audits/2026-08-28-full-serial-audit-handoff.md` §四.1 记录的「user_infos.comm_rate
> 三方契约冲突（既有缺陷）」。用户以「全量检查每一个逻辑都必须实现逻辑百分百正确，逻辑无误」
> 指令授权修复。本报告记录证据链、修复集与验证结果。

---

## 一、证据链：百分数（0..100）是唯一正确语义

| 证据点 | 位置 | 语义 |
| --- | --- | --- |
| 表结构 | `2026_03_29_000011_create_user_infos_table.php:59` | `integer('comm_rate')`——0..1 分数写入整数列必然截断/取整失真 |
| 种子/生产数据 | InitialDataSeeder / 生产实测 | 65 / 85（百分数） |
| 迁移种子 | `2026_08_01_000002`（comm_rate=65） | 百分数 |
| 佣金引擎 | `CommissionService.php:254,328` | `((float)$agent->comm_rate - $next) / 100`——按百分数计算 |
| 注册规则 | `FrontRegisterRuleService.php:79` | `(float)$info->comm_rate <= 50` 阈值——按百分数比较 |
| 建档继承 | `UserRegistrationService.php:396` | `min((int)$parentInfo->comm_rate, (int)$agentLevel->max_commission)`，等级 max=85——按百分数取整继承 |
| 旧后台验证 | `LegacyAdminController.php:4644` | `min:0|max:100`——按百分数校验 |
| **历史缺陷离群点** | `AgentController.php:438` 与未路由死控制器 `UserController.php:136` | `min:0|max:1`——使现代入口只能设置 0 或 1，分数输入（0.85）落库被整数列截断 |

结论：全代码库唯一语义为百分数 0..100；两处 `max:1` 是缺陷离群点。

## 二、修复集

| # | 文件 | 修复 |
| --- | --- | --- |
| 1 | `app/Http/Controllers/Admin/AgentController.php` | `updateCommission` 验证 `max:1` → `max:100`；参数逻辑说明改写为百分数口径并列四方一致性证据 |
| 2 | `app/Http/Controllers/Admin/UserController.php`（未路由死控制器，卫生性同步修正） | 同上 |
| 3 | `resources/admin/layui/agents/index.blade.php` | 表单注释「0.2 表示 20%」改写为百分数口径；输入约束 `max="1" step="0.0001"` → `max="100" step="1"` 并加 `placeholder="0-100"` |
| 4 | `tests/Feature/AdminLegacyAgentUserSaveClosureModuleTest.php:695` | 夹具 `comm_rate => 0.2` → `20`（原值在整数列本就失真） |
| 5 | 新增 `tests/Feature/AdminCommRatePercentSemanticsClosureModuleTest.php` | 语义锁定：85 被接受并原样落库为 85；150 被拒且原值不变；并澄清 `/api/admin/updateUser` 白名单不含 comm_rate（佣金只能走佣金更新入口） |

**关键澄清（修正 08-28 交接报告的三方表述）**：交接报告把「UserController.php:128」列为三方之一，
实际该控制器**未被任何路由引用**（死代码）；真实活动入口为 `AgentController::updateCommission`
（已修复）与旧代理编辑保存（本就 max:100）。`AdminUserController::updateUser` 的字段白名单
不含 comm_rate，不构成佣金写路径。

**前端展示兼容性**：前台 `AgentController@1046` 等展示位直接输出 `%` 文案；`formatRate()` 的
「≤1 视为分数」启发式仅用于历史数据展示容错，统一百分数后存值恒 ≥ 整数位，展示不受影响。

## 三、验证

| 验证项 | 结果 |
| --- | --- |
| 新增语义锁定测试 | `2 tests / 5 assertions` 绿（修正 1 版：第三用例针对不存在的写路径被移除并改为文档澄清） |
| 佣金/代理/等级族回归 | `105 tests / 637 assertions` 绿（含夹具 0.2→20 修正） |
| 佣金引擎 + 注册族回归 | `322 tests / 17072 assertions` 绿 |
| 最终全量串行 | 见文末 |
| 逐文件隔离复跑（comm_rate 修复后） | **680/680 个测试文件独立进程全部通过**（`per-file-tests-20260829-212515.log`，1016 秒 0 失败）——修复在隔离口径下同样零破坏 |
| php -l | 全部改动文件通过 |

## 四、最终结果

> 全量串行 `storage/logs/full-serial-20260829-204006.out`（13:53.560）：
> **`OK (4308 tests, 80371 assertions)` / `PHPUNIT_EXIT=0`**（4306 + 新增语义锁定测试 2 条）。
> 至此 08-28 交接报告 §四.1「待人工决策」项中的 comm_rate 契约冲突按用户「每一逻辑百分百正确」
> 指令完成修复并闭环；另一项（正式库返佣索引 ALTER）仍受「正式库禁写」红线约束待运维排期。
