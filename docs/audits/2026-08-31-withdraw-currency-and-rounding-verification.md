# 出金金额口径与合计舍入三条疑似缺陷的独立取证结论（2026-08-31）

> 依据：`docs/audits/2026-08-30-handoff-resume-here.md` §5.3 列出的 3 条
> 「子智能体报告，我未复核，**不作为已确认缺陷**」。本轮逐条独立取证并定性。
>
> 取证原则：旧项目（`D:\Software\PhpProject\Demo\new_co_gmtk_crmv3`）只读参照，
> 以**写入点**而非字段名推断币种；新项目以行/合计是否同源为判据。

## 结论速览

| 编号 | 疑似缺陷 | 定性 | 处置 |
| --- | --- | --- | --- |
| 1 | 前台 `actdraw` 取 USD 未乘汇率 | **口径分叉，非显示错值** | 记录，不改（属产品决定） |
| 2 | 合计 `drawpoundage` 由 RMB 改 USD | **不是缺陷** | 维持现状 |
| 3 | 合计 `actdraw` 先总后舍 vs 逐行先舍 | **确认缺陷** | 已修复并红绿锁定 |

---

## 一、第 1 条：前台 `actdraw` 币种 —— 口径分叉，不是显示错值

### 旧项目事实（币种以写入点为准）

- 写入点 `app/Http/Controllers/User/UserWithdrawController.php:204`
  `'act_draw' => $withdraw_amt * $withdraw_rate, // 实际出金金额RMB`
  另一分支 `:338` `'act_draw' => $_real_rmb_2, //扣除手续费后的实际出金金额RMB`
  → **`act_draw` 是 RMB**，确定无疑。
- 旧前台读取 `app/Http/Controllers/User/CustomerFlowController.php:371`
  `draw_record_log.act_draw as actdraw`；合计 `:440` `sum(draw_record_log.act_draw) as actdraw`。
- 旧前台列标题 `resources/views/user/customer_flow/main_browse_v2.blade.php:253`
  `trans('systemlanguage.account_apply_actdraw_RMB')`，取值
  `resources/lang/cn/systemlanguage.php:225` = **「实际到账 / RMB」**。

→ 旧前台该列语义：**实际到账 / RMB**。

### 新项目事实

- `app/Http/Controllers/Front/FlowController.php:219`
  `'actdraw' => FrontLegacyData::money($row->actual_amount)` —— USD，未乘汇率。
- `app/Http/Controllers/Front/WithdrawController.php:409` 同源（含 `?: apply_amount` 回退）。
- `app/Support/FrontLegacyData.php:1449` 合计 `money($row->actual_sum)` —— USD 汇总。
- 前台列标题 `public/js/apps/front/layui/pages.js:1687` 与 `:3631` 均为
  `front.actual_amount`，`resources/lang/zh-CN/front.php:163` = **「实际金额」**（无 RMB 字样）。

### 为什么定性为「分叉」而不是「缺陷」

1. **标签与取值自洽**：新前台标题「实际金额」+ 取值 USD `actual_amount`，
   用户看到的数字与列名一致，不存在「显示了错的钱」。真实差异是
   **少了一列 RMB 到账额**，属列语义变更，不属计算错误。
2. **旧项目本身不是可靠口径源**：同一旧前台视图 `:262` 的 `drawpoundage`
   标题是 `account_apply_drawpoundage_RMB`（「手续费 / RMB」），
   取值却是 `draw_poundage`（USD）—— 旧前台自身就有与第 2 条同型的标签/取值矛盾。
3. **项目已有既定取向**：`docs/项目整体进度梳理-2026-08-17.md:70` 已把
   「行与 footer 统一按 USD `fee` 汇总、不混用 RMB `rmb_fee`」复审为 ✅ Approved。

### 处置

不修改取值，不改键名（`actdraw` 是旧 API 契约键，改名会破坏既有调用方）。
需要留意的既存事实：**同一 JSON 键 `actdraw` 在后台是 RMB、在前台是 USD**。
后台 `LegacyAdminController.php:1579` 乘汇率，前台不乘。二者是独立应用、
各自 JS 独立消费，不存在共享消费方，故不构成运行期缺陷。
若后续要给前台补「实际到账 / RMB」列，属产品决定，须同时改四套 UI 家族的列定义与语言键。

---

## 二、第 2 条：合计 `drawpoundage` 由 RMB 改 USD —— 不是缺陷

- 旧后台确有内部矛盾：逐行 `draw_poundage as drawpoundage`（USD），
  合计 `sum(act_pdg_rmb)`（RMB）—— 旧项目自己行与合计就不同币种。
- 新项目统一按 USD `fee`：`LegacyAdminController.php:1583`（行）与 `:1618`（合计）同源。
- 该决定已由 `docs/项目整体进度梳理-2026-08-17.md:70` 复审为 **✅ Approved**。
- 现有测试已反向锁定：`tests/Feature/AdminLegacyWithdrawSearchParityClosureModuleTest.php:112`
  `assertNotSame('304.00', ...footer.drawpoundage)`——若有人改回 RMB 汇总立即失败。

结论：维持现状。这是**修正旧项目矛盾**的主动决策，不是回归。

---

## 三、第 3 条：合计 `actdraw` 舍入顺序 —— 确认缺陷，已修复

### 缺陷事实

| 路径 | 位置 | 算法 |
| --- | --- | --- |
| 逐行 | `WithdrawRecordQueryService.php:169` | `formatMoney(bcmul(amount, rate, 10))` —— **乘完立刻舍到分** |
| 合计（修复前） | `WithdrawRecordQueryService.php:93` | `SUM(actual_amount * exchange_rate)` 后 `formatMoney` —— **先累加、只舍一次** |

消费方：`LegacyAdminController.php:1579`（行）与 `:1617`（合计）。
而 `:1599` 的方法注释**自己声明**「合计行与逐行必须同源同口径」——
代码违背了它自身声明的契约，与文档中**已修复**的 `actapplyamount` 行/合计矛盾同型。

### 币种/精度前提已排除浮点因素

`database/migrations/2026_07_12_000001_harden_withdrawal_funding.php:521-524`
把列改为 `DECIMAL(18,2)`（金额）与 `DECIMAL(18,8)`（汇率），
MySQL 侧为精确定点运算。故差异**纯粹**来自舍入顺序，与浮点噪声无关。

### 实测复现（修复前）

4 条 `actual_amount=1.00`、`exchange_rate=1.00500000`：

- 逐行：`1.00 × 1.005 = 1.0050000000` → 各显示 `1.01`，页面 4 个 `1.01`
- 合计（修复前）：`SUM = 4.02000000` → 打印 **`4.02`**
- 正确值：`4 × 1.01 = ` **`4.04`**

红证据（修复前实跑，v1/v2 两套 envelope 同时失败）：

```
Failed asserting that two strings are identical.
-'4.04'
+'4.02'
```

偏差上界为 N × 0.005，N 为筛选命中行数——行数越多越大，属用户可见的对不上账。

### 旧项目基准

旧后台合计是 `sum(act_draw)`（`WithdrawAmountController.php:433`），
而 `act_draw` 是**已按分存储**的列，即旧口径等价于「逐行先舍、再求和」。
新项目合计必须复刻这个顺序，而非先累加。

### 修复

`app/Services/WithdrawRecordQueryService.php:111`

```sql
COALESCE(SUM(ROUND(actual_amount * exchange_rate, 2)), 0) AS total_actual_draw
```

舍入方向一致性：MySQL 对 DECIMAL 的 `ROUND` 为四舍五入（远离零），
`formatMoney()` 对绝对值 `bcadd('0.005')` 同样按量值四舍五入，正负两侧口径对齐。

### 锁定

新增 `AdminLegacyWithdrawSearchParityClosureModuleTest
::test_legacy_footer_actual_draw_equals_the_sum_of_displayed_rows`（v1/v2 双 envelope）。
断言写成两条：**不变式**（合计 == 逐行 BCMath 求和）+ **精确值** `4.04`。
只写不变式会在两侧同时算错成同值时被静默满足；只写硬编码值则夹具一改即失锁。

### 结构性排查（避免只修一处留同型缺陷）

- 全仓 `SUM(<列> * <列>)` 仅此一处。
- 全部 `COALESCE(SUM(...))` / `SUM(...)` 聚合逐一核对：其余均为纯列汇总，
  逐行不做二次换算，直接 SUM 即已同源。
- `BigNumberController.php:197` 的 `SUM(volume)/100` 是按日期分组的趋势序列，
  无并列的逐行显示值，不构成行/合计矛盾（MT4 手数标准换算）。
- 导出链路 `exportRecords()` → `LegacyAdminExportController.php:271` 逐行走
  `multiplyMoneyByRate()`，与列表行同源；CSV 表头 `:256` 已标注「实际出金 / RMB」，无合计行。

### 未受影响的既有锁定

大精度用例 `test_legacy_v2_search_keeps_decimal_precision_for_large_amounts_and_rates`
的 `999999998999999.90`（单行）修复后仍通过；
`test_legacy_search_...envelope` 的合计 `328.00`（168+160，无分位尾差）亦不变。

---

## 四、验证

| 项 | 结果 |
| --- | --- |
| `php -l`（Service + Test） | 无语法错误 |
| 目标测试文件 | `OK (10 tests, 80 assertions)` |
| 修复前红证据 | 期望 `4.04` / 实际 `4.02`，v1+v2 各 1 例 |
| withdraw 家族回归 | `OK (681 tests, 7037 assertions)` |
| `headers-check` | missing headers: **0** |
| `audit-members` | 1119 文件 \ 1231 成员 \ missing CJK doc: **0** |
| 注释门禁 | `OK (164 tests, 3991 assertions)` |
| 全量串行（修复前基线） | `OK (4356 tests, 80684 assertions)` exit 0 |
| 全量串行（修复后） | `OK (4358 tests, 80696 assertions)` exit 0 |

## 五、修复后全量串行

```
storage/logs/full-serial-20260831-235010.out
OK (4358 tests, 80696 assertions)
PHPUNIT_EXIT=0
```

测试数 4358 = 修复前 4356 + 本次新增 2（`actdraw` 合计舍入 v1/v2 双 envelope），
与预期完全一致，无遗漏、无跳过。

## 六、本轮改动文件

```
app/Services/WithdrawRecordQueryService.php
  - summarize() 的 total_actual_draw 改为 SUM(ROUND(amount * rate, 2))
  - summarize() PHPDoc 补「同源同口径契约」与锁定测试指引

tests/Feature/AdminLegacyWithdrawSearchParityClosureModuleTest.php
  - 新增 test_legacy_footer_actual_draw_equals_the_sum_of_displayed_rows（v1/v2 双 envelope）

docs/audits/2026-08-31-withdraw-currency-and-rounding-verification.md（本文件）
```

第 1、2 条无代码改动，结论以本文件留档。

