# 全量旧新等价复审与缺陷修复报告（2026-08-30）

> 本轮触发指令：用户不认可「已全部实现旧项目模块功能」的既有结论，要求全量重新自查自测自检、
> 独立审计前后端四套 UI 的排版美观度，并完整记录各模块测试进度以便下次快速接续。
>
> 本报告的组织原则：**不复述既有账本结论，每一条都独立复算并附取证路径**。
> 凡我无法证实的，一律标注「未能核实」，不编造解释。

---

## 一、先复算账本可信度（结论：可信）

既有账本 `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json` 自称 476 条方法级路由全部 verified。
我不采信，独立重新解析旧项目三个路由文件（`app/Http/routes.php`、`routes-admin.php`、`admin.php`）
并与矩阵做双向差集。

| 口径 | 数量 |
| --- | ---: |
| 源码枚举出的 action 路由 | 467 |
| 源码枚举出的闭包路由 | 2 |
| 源码唯一 `METHOD+Controller@method` | **469** |
| 矩阵同口径唯一值 | **469** |
| 双向差集 | 1（`User\RegisterController@index`） |

唯一差集经核验位于 `routes.php:74-76` 的 `/* */` 注释块内，是**死路由**，属我的解析器误报而非账本遗漏。
矩阵 476 行与 469 个唯一 action 的差额，来自同一 action 挂多个 URI 或多个 HTTP 方法。

**账本口径成立。但路由枚举完整 ≠ 业务逻辑等价**，因此本轮重心放在语义层与视图功能层。

---

## 二、生产代码洁净度（结论：干净）

| 检查项 | 范围 | 结果 |
| --- | --- | ---: |
| TODO/FIXME/未实现标记 | `app/` + `routes/` | 14 命中，**全部为 `1xxx` 错误码注释与 `xxx` 占位示例，非欠账** |
| 模拟数据流入生产路径 | `app/` | 2 命中，**均为注释文字** |
| 「译文当 option value」缺陷类 | 全部 Blade | **0 命中**（独立复验，此前已清零） |

---

## 三、本轮发现并修复的真实缺陷（8 处）

每条均有旧项目双侧行号取证，且完成红绿循环（先证明测试能抓住缺陷，再恢复实现转绿）。

### 3.1 出金批量审核入口在四套 UI 全缺失（高）

- **缺口性质**：后端 `POST /index/admin/amount/batchWithdrawApply` 已实现且有闭环测试
  （`AdminLegacyBatchWithdrawApplyClosureModuleTest`），但**四套 UI 均无入口**，功能不可达。
- **旧行为**：`resources/views/admin/withdraw_status/{pending,processing,completed,failed}_browse.blade.php`
  四页各有勾选列 + 「批量操作」按钮 + 目标状态弹窗（含备注），跃迁规则 `0→{1,2,3}`、`1→{2,3}`，
  目标为 3（拒绝）时备注必填，终态行（status 2/3）禁止勾选。
- **实现取向（关键决策）**：复用旧端点，**后端零改动、零改生产权限表**。
  依据是该端点已具备 Session+JWT 双认证通道（`LegacyAdminAuthenticate`），
  且按 `payload.status` 动态映射到既有权限 `admin_api_withdrawProcess/Complete/Reject`。
  若新增 `/api/admin` 现代路由，必须同步新增 `permissions` 记录，否则非超管一律 403——
  而新增权限记录属于改动生产权限表的高风险操作。
- **改动**：
  - Layui：勾选列、批量按钮、弹窗、跃迁约束、终态禁选、CSRF+JWT 提交
  - CrmUI：以**特性开关**扩展共享渲染器（仅声明 `batch` 的页面渲染勾选列，其余 48 页零变化）
  - 语言键 11 个（中英文）
- **测试**：`AdminWithdrawBatchApplyUiClosureModuleTest` 7 tests / 103 assertions

### 3.2 出金列表「实际金额」列取错字段（高）

- **新行为**：`LegacyAdminController.php:1558` 逐行 `actapplyamount` 映射为 `apply_amount`
- **旧行为**：`WithdrawAmountController.php:185` 与 `:333` 均为 `act_apply_amount as actapplyamount`
- **矛盾点**：同一方法内 `actdraw`（:1560）与合计行（:1587）都正确用 `actual_amount`，
  只有逐行这一处误用——**同一响应内行与合计口径自相矛盾**
- **后果**：申请 25.00 / 实际 24.00 的记录，列表「实际金额」显示 25.00，合计行却是 44.00（=24+20）
- **修复**：改为 `actual_amount`，并补齐该方法与合计方法缺失的方法级 PHPDoc

### 3.3 入金流水「实际支付 RMB」列取成 USD（高）

- **旧语义**：`UserDepositController.php:201` `$insert['dep_amount'] = $act_pay_rmb`（RMB 实付），
  `dep_act_amount` 才是 USD；列头「实际支付 / RMB」见 `DepositAmountController.php:446`
- **新行为**：`FundFlowController.php:449` 取 `$depositRecord->amount`（USD）
- **后果**：100 USD 按 6.97 入金 → 旧显示 **697.00**，新显示 **100.00**
- **修复**：改为 `actual_amount`

### 3.4 入金流水「充值流水号」与「通道单号」同源重复（中）

- **旧语义**：`dep_outTrande` 是本站生成的本地单号（`UserDepositController.php:192,200`），
  `dep_channel_no` 才是通道平台单号（`PayCallBackController.php:494`）
- **新行为**：`FundFlowController.php:450` 与 `:452` 两列都取 `channel_order_no`
- **后果**：两列内容完全重复，本地单号这个唯一追溯键在列表中丢失
- **修复**：`depoutTrande` 改取 `local_order_no`

### 3.5 前台出金卡号脱敏丢失（安全，高）

- **旧行为**：`CustomerFlowController.php:308` 按「前 4 + `****` + 后 4」脱敏
- **新行为**：`Front/FlowController.php:222` 与 `Front/WithdrawController.php:404` 直接下发完整卡号，
  且后者先 `toArray()` 把整行原文摊平下发
- **后果**：19 位卡号 `6222021234567890123` 旧返回 `6222****0123`，新返回完整 19 位
- **修复**：新增共享脱敏函数（不足 8 位整体打码、null 归空串），两处调用点显式覆盖原始 `bank_no` 键

### 3.6 产量报表缺 2 列，CrmUI 缺 6 列（中）

- **旧 7 列**：`sym_symbol`、`avg_buy_price`、`buy_volume`、`avg_sell_price`、`sell_volume`、
  `net_vol`、`float_profit_loss`
- **缺口**：`avg_buy_price` / `avg_sell_price` 后端从未计算。新页的 `bid`/`ask` 是**实时行情报价**，
  与「持仓均价」是不同信息，不能替代
- **旧公式**（`AdminProductionController.php:229,237`）：
  - `avg_buy_price = round(SUM(open_price WHERE cmd=0) / COUNT(cmd=0), 2)`
  - `avg_sell_price = round(SUM(close_price WHERE cmd=1) / COUNT(cmd=1), 2)`
  - **两侧取价字段故意不对称**（买单取开仓价、卖单取平仓价），已原样复刻并在注释中标明是刻意等价而非笔误
- **额外发现**：CrmUI 产量页原本连 Layui 已有的手数/净持仓/浮动盈亏 4 列也缺，共缺 6 列，一并补齐
- **结构差异说明**：旧查询的 `MARGIN_RATE <> 0` 过滤在新库无对应字段
  （`mt4_trades` 表无 `margin_rate` 列），故两个均价与同行 volume/profit 聚合共用同一份 join，保证行内口径自洽
- **测试**：`AdminProductionAvgPriceParityClosureModuleTest` 6 tests / 43 assertions

### 3.7 后台改汇率对入金与出金完全不生效（高）

- **取证结论**：`sys_deposit_rate` / `sys_draw_rate` 的全部实现侧命中只有四类——
  写入方自己（`ExchangeRateController`）、seeder 一次性初始化、Blade 表单字段名、前端回填。
  **无任何业务运行时读取它们做金额计算。**
- **三条真实链路**：
  - 出金结算 → `system_configs.withdraw_exchange_rate_cny`（`WithdrawalOrderService.php:411,540`）
  - 入金结算 → `payment_channels.exchange_rate`（`PaymentOrderService.php:119`）
  - 入金页展示 → `system_configs.deposit_exchange_rate_cny`（`Front/DepositController.php:91`）
- **隐蔽性**：DB 实测 `sys_draw_rate=6.9` 与 `withdraw_exchange_rate_cny=6.9000` **当前恰好相同**
  （seeder 初始化时同源），所以页面看不出问题——直到管理员改一次汇率，旧键更新而生效键不动。
- **修复**：`update()` 在单一事务内同步四个配置键，并按旧联动清单批量刷新法币通道汇率。
  联动白名单 `[1,2,3,6,7,8,9,10,11]` 取自旧 `whpj_rate_save()`
  （旧代码把 `sys_deposit_rate` 与 `rate2/3/6..11` 写成同值，`rate4/5` 不在联动列内，
  对应加密货币与数字货币通道，固定 1.0 不做本币换算）。
  按通道编号而非「当前汇率是否为 1」判断——后者会随运维改动漂移，旧清单才是稳定事实源。
- **测试**：`AdminExchangeRateEffectivePropagationClosureModuleTest` 5 tests / 47 assertions

### 3.8 未入金流水控制器引用不存在的列（低，死代码）

- **实测**：`deposit_records` 无 `channel` 与 `third_party_order_no` 列，
  复现查询报 `SQLSTATE[42S22] 1054`
- **定性修正**：子智能体判为 Critical「接口永久 500」。我查路由后发现
  **未入金三条路由全部指向 `FundFlowController`**，`UnDepositAmountController` 全项目零路由引用，
  是死代码——缺列永远不会被执行到。**这也解释了为何 4308 条测试一直全绿。**
- **处置**：降级为 Minor。仍修缺列（它已造成过一次审计误导），并在文件注释标注可达性；
  不删类，因为迁移完整性测试依赖它存在。

---

## 四、本轮新增测试

| 测试文件 | 规模 | 锁定内容 |
| --- | ---: | --- |
| `AdminWithdrawBatchApplyUiClosureModuleTest` | 7 / 103 | 批量入口存在性、跃迁白名单、拒绝备注必填、终态禁选、特性开关不外溢、复用旧端点取向、语言键齐备 |
| `AdminProductionAvgPriceParityClosureModuleTest` | 6 / 43 | 均价取价字段不对称、无对应方向归零、无持仓归零、已平仓单排除、两家族列齐备、语言键 |
| `AdminExchangeRateEffectivePropagationClosureModuleTest` | 5 / 47 | 四键同步、法币通道联动、加密通道排除、校验失败零部分写入、控制器声明护栏 |
| `AdminFundFlowFieldSemanticsClosureModuleTest` | 2 / 10 | 入金实付 RMB 口径、本地单号与通道单号不同源、未入金控制器不再引用缺失列 |
| `FrontBankNoMaskingClosureModuleTest` | 4 / 14 | 脱敏规则逐位一致、短卡号整体打码、无卡号返回空串、两处调用点均脱敏且覆盖原始键 |
| `UiOrphanClassAndTouchTargetClosureModuleTest` | 3 / 16 | 12 个补齐类的精确定义存在、副标题显式非加粗、图标触发器 ≥44px |

> **报告自身的一处纠错记录**：本文档初稿把 §3.3、§3.4、§3.5、§3.8 四条写成「已修复」，
> 但当时代码中 `maskBankNo` 并不存在、`third_party_order_no` 仍在原处——即那四条**尚未落地**。
> 该错误由我在写作过程中自查发现（核对文档所列测试文件是否真实存在时暴露）。
> 随后我重新自行取证旧语义（未沿用子智能体结论），确认四条缺陷真实存在，
> 并已全部实际写入代码 + 完成红绿循环 + 通过模块回归。此处保留记录，
> 因为「报告声称已修但代码未改」比缺陷本身更危险。

### 红绿循环验证记录

| 缺陷 | 回退方式 | 测试表现 |
| --- | --- | --- |
| 批量入口 | Blade 按钮 id 改名 | 变红，精确指向缺失断言 |
| 出金实际金额 | 恢复为 `apply_amount` | 变红（期望 24.00/20.00，实际 25.00/30.00） |
| 产量均价 | 买入均价改取 `close_price` | 变红（期望 150.00，实际 999.00，探针值命中） |
| 入金实付额 | 恢复为 `amount` | 变红（期望 697.00，实际 100.00） |
| 汇率生效键 | 移除生效键同步 | 变红（期望 7.31，实际 7.1） |
| 汇率通道联动 | 移除通道批量更新 | 变红（期望 7.77，实际夹具初值 1.2345） |
| 入金实付额 | 恢复为 `amount` | 变红（期望 697.00，实际 100.00） |
| 充值流水号 | 恢复为 `channel_order_no` | 变红（期望 `tg...LOCAL`，实际 `CHANNEL-...`） |
| 卡号脱敏 | 脱敏函数直接返回原文 | 变红（期望 `6222****0123`，实际完整 19 位） |

**一处测试自身缺陷已修**：汇率通道联动测试初版用「行不存在就跳过」写法，在测试库
`payment_channels` 无对应行时会**空转通过**（断言全被绕过）。已改为自建夹具 + 「至少校验过 N 个通道」
守卫，断言数从 21 升至 47。这类假绿比缺陷本身更危险。

---

## 五、模块回归覆盖（本轮实际执行）

| 测试范围 | 规模 | 结果 |
| --- | ---: | ---: |
| 出金全族 `--filter withdraw` | 658 / 6968 | ✅ |
| 入金+通道+汇率 `--filter (exchangerate\|channel\|deposit)` | 284 / 1947 | ✅ |
| 产量模块 `--filter production` | 32 / 276 | ✅ |
| 四家族 UI 契约门禁（6 文件） | 261 / 7711 | ✅ |
| 注释门禁 `--filter (Comment\|FileHeader)` | 164 / 3991 | ✅ |

### 静态检查与工具链

| 检查项 | 结果 |
| --- | ---: |
| `php -l` 本轮改动文件 | 全部通过 |
| `node --check` 本轮改动 JS | 全部通过 |
| `php artisan view:cache` / `view:clear` | exit 0 |
| `php tools/add_file_headers.php --check` | **missing headers: 0** |
| `php tools/audit-members.php` | 1114 文件 / 1213 成员 / **missing CJK doc: 0** |

---

## 六、待人工决策项（我未擅自改动）

### 6.1 出金手续费口径 ✅ 已决策：改为可动态设置（实现在途）

> **用户决策（2026-08-30）**：不采用「恒 0」或「固定 5 USD」任一硬编码口径，
> 改为**可动态设置**——「是否扣手续费」与「手续费扣多少」作为两个独立可配置维度。
>
> **实现状态**：代码已完成，目标回归 144 tests / 1078 assertions 全绿；
> 专项测试与完整串行验证**尚未完成**，详见 `2026-08-30-handoff-resume-here.md` §二.1。
>
> 实现要点：
> - 新增 `system_configs.withdrawal_fee_enabled` 总开关（迁移 `2026_08_30_000001`，幂等）
> - 默认 `'1'`：对既有库（已配 5.00）与全新库（金额为 0）**都是零行为变更**，
>   「是否停收」交由管理员在后台显式操作，而不是由 seeder 隐式决定
> - 开关关闭时服务层把固定费与费率**一并按 0 计算**，而非跳过整段算术——
>   这样 `fee < amount` 校验、`actual_amount` 与 `rmb_fee` 推导仍走同一条路径，
>   且原配置值原样保留，重新开启即恢复既有标准
> - `loadConfiguration()` 按**可选键**读取（缺键默认 `'1'`）：若列为必填，
>   未跑迁移的库会因缺键而使全部出金失败关闭，那是拿可用性换配置完整性
> - 后台入口落在汇率页（Layui 开关 + 两个金额输入；CrmUI select + 两个 number）
> - 前台 `fee_enabled` 一并下发，关闭时费率回显 0，避免页面提示扣费而实际不扣
>
> **实现过程中修掉的自身 bug**：`DB::transaction()` 闭包 `use` 子句漏了 `$feeUpdates`，
> 导致汇率保存返回 500。已修并复跑转绿。记录此项是因为它证明「代码已写」不等于「功能可用」。

以下为决策前的取证记录，保留备查。

#### 取证：旧线上恒 0，新无条件扣 5 USD

**取证已完成，结论明确。** 旧项目线上出金路径 `UserWithdrawController.php:195-220`：

```php
// L201 被注释：($withdraw_amt < 100) ? ($withdraw_amt - $poundagemoney) : $withdraw_amt
'act_apply_amount'  => $withdraw_amt,                    // L202 生效：实际额 = 申请额
// L203 被注释：($withdraw_amt < 100) ? $_real_rmb : (...)
'act_draw'          => $withdraw_amt * $withdraw_rate,   // L204 生效：不扣费
'act_pdg_rmb'       => 0,                                // L205
// L207 被注释：($withdraw_amt < 100) ? $poundagemoney : 0
'draw_poundage'     => 0,                                // L208 生效：手续费恒 0
```

L163-169 计算 `$_real_rmb` 的代码仍在，但 L204 未使用它，属死代码。**旧线上手续费确实恒为 0。**

新项目实测 `system_configs.withdrawal_fixed_fee_usd = 5.00`，根因在
`LegacyFrontReferenceSeeder.php:234` 把旧库 `sys_poundage_money`（旧项目仅作页面展示标准，线上不收）
直接当成生效固定费。而 `InitialDataSeeder.php:160` 与
`2026_07_15_000001_ensure_required_withdrawal_configs.php:118` 默认都是 `'0'`，
说明设计意图本就是 0。

**差异量化**（申请 1000 USD、汇率 6.9）：

| | 实际金额 | 手续费 | 实际出金 RMB |
| --- | ---: | ---: | ---: |
| 旧项目 | 1000.00 | 0 | 6900.00 |
| 新项目 | 995.00 | 5.00 USD / 34.50 RMB | 6865.50 |

**每笔出金少给用户 34.50 RMB。**

**当时未擅自改的理由**：这不是「实现错了」，而是「配置初始化口径与旧项目不符」，
且「要不要收手续费」属产品决策。另需注意：改 seeder 只影响新建库，
**不影响已有生产库的现值**；若要让生产生效，需单独改生产库的配置值——那是独立的运维动作。

**决策结果**：用户选择「可动态设置」，即不锁死任何一方，由后台开关与金额共同决定。
`LegacyFrontReferenceSeeder.php:234` 的 `sys_poundage_money → withdrawal_fixed_fee_usd`
映射**保持不动**（它代表运营在旧系统里登记的费率标准），是否实际扣取由新增开关决定。

### 6.2 其余 4 条待独立取证（下一轮）

来自子智能体报告，我尚未独立复核，不作为已确认缺陷：

1. 前台 `actdraw` 取 USD 未乘汇率，与后台同名字段实现矛盾
2. 出金合计行 `drawpoundage` 口径从旧 RMB（`SUM(act_pdg_rmb)`）改成 USD（`SUM(fee)`）
3. 出金合计 `actdraw` 先乘后总 vs 逐行先舍，存在分位尾差
4. 新库 `mt4_trades` 中 `cmd=6` 的 61 万行 `comment` 疑似全为空，会让实时返佣与流水静默返回空列表
   （若属实，属数据迁移完整性问题，需以生产迁移脚本复核字段映射，非代码缺陷）

### 6.3 既有遗留项

`2026_08_28_000001_add_mt4_trades_rebate_lookup_index` 尚未在正式库执行
（ALTER 需重建 872,140 行表，基准约 24 秒，需运维排期与明确授权）。
控制器降级路径已核验，生产当前正确运行。

---

## 七、未能核实的部分（如实记录，不编造解释）

### 7.1 一个无法解释的时间线矛盾

§3.2 的 `actapplyamount` 缺陷按理必然让
`AdminLegacyWithdrawSearchParityClosureModuleTest` 失败（夹具 `apply_amount=25.00`、
`actual_amount=24.00`，两值不同）。但今天 00:30 的全量串行日志
`storage/logs/full-serial-20260830-003032.out` 明确记录
`OK (4308 tests, 80371 assertions)`，且日志中**不含该测试类名**。

已排除的假设（均有证据）：

| 假设 | 排除依据 |
| --- | --- |
| 子智能体改过代码 | 00:30 后被修改的源码文件共 11 个，**全部是我本人的改动** |
| 测试文件被改过 | 该文件 mtime 为 2026-08-29 19:02，早于 00:30 串行 |
| 测试库数据残留 | 金额来自夹具显式 insert，与库内既有数据无关 |

因项目**非 git 仓库**、且我 13:55 的修复覆盖了控制器 mtime，无法排除更早的中间改动。
**我不编造解释。** §3.2 修复的正确性依据是旧项目源码两处
`act_apply_amount as actapplyamount`，与此矛盾无关，且修复后出金全族 658 项全绿。

### 7.2 四套 UI 排版美观审计（已改为自行执行，见 §九）

子智能体三次尝试均被网关中断（504 超时 → 502 → 403 无订阅），属基础设施问题。
遂改为自行执行静态审计，已完成「类名失效」与「触控目标」两项并落地修复，
见 §九。仍未覆盖的项目（需真实浏览器）在 §九末尾列明。

### 7.3 旧视图功能标识差集

从旧 223 个视图抽取功能标识（表单字段名、Layui 行事件、表格列），首轮 210 个零命中。
经动态拼接识别（新代码用 `'channel_' . $i` 拼接）与蛇形/驼峰互转判定后，41 个被吸收，
**收敛到 122 个候选**。本轮从中定性出产量报表缺列（§3.6）这一真缺陷，
其余候选中大量为 `lay-event` 命名差异（新家族用 `detail`/`process` 等自有事件名，
名称不同不等于功能缺失），**尚未逐一定性完毕**。

---

## 八、本轮改动文件清单

### 后端（7 个）

| 文件 | 改动 |
| --- | --- |
| `app/Http/Controllers/Admin/LegacyAdminController.php` | 出金逐行实际金额取 `actual_amount`；补两个方法 PHPDoc |
| `app/Http/Controllers/Admin/FundFlowController.php` | 入金实付改 `actual_amount`；充值流水号改 `local_order_no` |
| `app/Http/Controllers/Admin/UnDepositAmountController.php` | 修缺列引用；标注可达性 |
| `app/Http/Controllers/Admin/ProductionController.php` | 新增两个均价聚合；导出补列 |
| `app/Http/Controllers/Admin/ExchangeRateController.php` | 事务内同步四键 + 法币通道联动 |
| `app/Http/Controllers/CrmUi/Admin/PageController.php` | 新增 `batch` 声明与归一化；产量页补 6 列 |
| `app/Support/FrontLegacyData.php` | 新增共享卡号脱敏函数 |

### 前端（4 个）

| 文件 | 改动 |
| --- | --- |
| `public/js/apps/admin/layui/pages.js` | 出金勾选列与批量处理器；产量补两列 |
| `public/js/apps/crmui/admin.js` | 特性开关式批量渲染与交互 |
| `resources/admin/layui/withdrawals/index.blade.php` | 批量按钮与弹窗骨架 |
| `resources/admin/crmui/partials/module-page.blade.php` | 选择列、全选框、批量弹窗（条件渲染） |

### 语言包（4 个）与前台控制器（2 个）

`resources/lang/{zh-CN,en}/{admin,crmui}.php` 补 21 个键；
`app/Http/Controllers/Front/{FlowController,WithdrawController}.php` 卡号脱敏调用点。

### 测试（5 个新增）

见 §四。

---

## 九、四套 UI 静态审计（自行执行）

### 9.1 类名失效：13 个孤儿类，12 个已补齐

审计方法分三道判定，逐道排除假阳性：

1. **抽取**：从四套家族的 Blade 抽取项目自有类（`crm-` / `crmui-` / `front-` / `admin-` /
   `layui-vc-` 等前缀），跳过含 Blade 表达式的动态类串与 Layui 官方类。
2. **精确匹配**：在 `public/css` 全量样式表 + Layui vendor CSS + Blade 内联 `<style>` 中
   用 `\.cls(?![A-Za-z0-9_-])` 精确检索。
   *这一步至关重要*——用子串匹配会让 `crmui-page` 命中 `crmui-page-head` 而误判为已定义。
3. **用途分类**：对未定义者再查 JS 与测试引用，区分三类：
   - `JS_HOOK`：被 JS 当选择器用（如 `.crmui-page` 在 `admin.js` 里做 `closest()`），**无需 CSS**
   - `TEST_HOOK`：被测试断言引用，属契约锚点，**无需 CSS**
   - `ORPHAN`：三者皆无 —— 真正写了却完全不起作用

自检对照：匹配器在同一批样式表中找到 104 个 `crmui-*`、4 个 `crm-preference-*`、
5 个 `crm-theme-*` 已定义类，证明匹配器本身工作正常，「未定义」结论可信。

| 家族 | Blade 数 | 项目自有类 | 未定义 | 其中真孤儿 |
| --- | ---: | ---: | ---: | ---: |
| admin/layui | 50 | 62 | 9 | **9** |
| admin/crmui | 49 | 78 | 4 | 1 |
| front/layui | 41 | 91 | 7 | **4** |
| front/crmui | 42 | 75 | 3 | 1 |

**已补齐 12 个**（第 13 个 `crmui-front-body` 判定为语义标记，`.crmui-body` 已提供
`min-height:100vh`、布局由 `.crmui-shell` 承担，不需要额外样式，**不为它编造 CSS**）：

| 类名 | 可见后果 | 补齐位置 |
| --- | --- | --- |
| `crm-page-head` / `crm-page-title` / `crm-page-desc` | **整族零定义**。因 `.crm-admin-panel .layui-card-header` 自带 `font-weight:800` 与 `--crm-ink`，副标题完整继承标题样式 → 渲染成「两行一样粗的标题」，无任何主次层级。影响 admin/layui 至少 6 个页面（position-summary、risk、whs-exp-zero、authentications、exchange-rates、gifts） | `crm-design-system.css` |
| `crm-preference-nav-item` / `crm-theme-picker-nav-item` | **命名漂移**：CSS 实际有 `.crm-preference-item` 与 `.crm-theme-picker`，页面写的是带 `-nav-` 的变体。这两个类是套在 `layui-nav-item` 上的布局钩子，缺失时 44px 方形图标按钮落在为文字链接设计的「左右 20px padding + 60px 行高」盒子里被挤偏。正落在你明确要求过的「语言/主题图标化入口」上 | `crm-themes.css` |
| `crm-chain-nodes` | `.crm-chain-node`（单数）早已定义，复数容器缺失 → 多层链路换行后节点无间距、上下行粘连 | `crm-design-system.css` |
| `crm-form-actions` | 表单操作按钮行无 flex 与间距，窄屏下按钮各占一行 | `crm-design-system.css` |
| `crm-channel-tab` | 支付通道页签容器无间距与换行控制 | `crm-design-system.css` |
| `admin-legacy-captcha-field` | 旧后台登录页：输入框与 132×44 验证码图都是块级元素，无 CSS 时图片掉到输入框下方独占一行 | `admin/style.css` |
| `crm-date-range` | 日期区间的两个输入与连字符各占一行，390px 下筛选区被拉高三行 | `front/style.css` |
| `crm-table-summary-bar` | 统计条紧贴表格顶边，与其它页面留白不一致 | `front/style.css` |
| `crmui-fieldset` | 本轮批量弹窗引入。`<fieldset>` 浏览器默认 2px inset 边框会在弹窗里露出一圈与设计体系无关的粗框 | `crmui/front.css`（经 `admin.css:18` 的 `@import` 对后台生效） |

### 9.2 触控目标：项目有 44px 约定但 58 处未贯彻

项目自身已在 10 个样式表中出现 **46 处 44px 约定**，说明这不是「本来没这要求」，而是约定未贯彻。

**已修（纯图标按钮，无文字扩大可点区域）**：
`.crm-preference-trigger` 38×38 → **44×44**（`crm-themes.css:80-81`）。
图标本身仍保持 18px，视觉重量不随触控区放大而变化。这是你两项明确诉求的交叉点
（图标化入口 + ≥44px）。

**未修，需你决策的密度权衡**：

| 选择器 | 现值 | 为何没擅自改 |
| --- | ---: | --- |
| `.crmui-row-button` | min-height 30px | 表格行操作按钮。直接放到 44px 会撑高整行、破坏密集表格的信息密度；用伪元素扩大命中区在这里也有风险——按钮横向排列，纵向扩 7px 可能跨到相邻行导致误点 |
| `.crm-chain-trigger` | min-height 24px | 链路展开触发器，同属行内密集元素 |
| `.layui-btn` | height 34px | 全站主按钮，改动影响面覆盖所有页面 |
| `.crm-ui-admin-shell .layui-tab-title li` | 34px | 页签 |
| `.crmui-tab` | 38px | 页签 |
| 支付通道 tab | 36–38px | 页签 |

另有表格 `th`/`td` 42px、统计项 26–28px、图标本身 20px 等——这些不是独立触控目标，
42px 行高在实际渲染中通常仍有足够可点区域，**不列为缺陷**。

### 9.3 语言键缺失：14 个已补齐（从 70 个假阳性收敛而来）

审计方法与两次自我纠错：

1. 首轮抽取 948 个语言键引用（Blade 的 `__()` / `trans()` / `data-translate`，
   JS 的 `CrmLang.t()`），只在 `resources/lang/{zh-CN,en}` 的 PHP 文件中校验 → 报 **70 个缺失**。
2. **第一次纠错**：`data-translate` 是**客户端** CrmLang 解析的，键定义在
   `public/js/shared/lang/` 的 JS 语言包里，不在 PHP lang 文件。补上这个定义来源后 → **14 个**。
3. **第二次纠错**：中途用 `grep bankCardNo|idCardFront` 做过一次抽查，误以为键已存在。
   实际是 OR 匹配只命中了 `idCardFront`。精确单键复验确认 `bankCardNo` 确实缺失。

**缺陷机制（决定了它为什么极易漏测）**：`i18n.js:1535` 的 `translate()` 在
「运行时语言包 → 内置 fallback → 中文 fallback」三级查找全部失败后，
执行 `return humanizeKey(key)`（`i18n.js:1509`）——取键末段、下划线转空格、首字母大写。

所以缺键**不报错、也不显示原始点号键**，而是渲染成 `BankCardNo`、`IdentityVerification`、
`PhoneSettings` 这类**裸驼峰英文**（驼峰不被拆分，只首字母大写）。中文界面下尤为突兀，
且只在切换语言后暴露。

补齐的 14 个键，集中在前台资料页：

| 分区 | 键 | 位置 |
| --- | --- | --- |
| 实名认证 | `identityVerification` / `realName` / `idCardNo` | `front/layui/profile/index_v2.blade.php:186,198,192` |
| 手机与验证码 | `phoneSettings` / `phoneCode` / `emailCode` / `sendCode` / `verificationCode` | `index_v2.blade.php:159,171,142` 与 `index.blade.php:169,162` |
| 银行卡 | `bankCardInfo` / `bankName` / `bankBranch` / `bankCardNo` / `bankAccountName` | `index_v2.blade.php:254,261,273,267,279` |
| 认证共用 | `auth.send_code`（蛇形写法，此前只有 `profile.sendCode`） | `index_v2.blade.php:145` |

复跑审计确认 **zh-CN 与 en 均从 14 降至 0**。

### 9.4 本项新增门禁

`UiOrphanClassAndTouchTargetClosureModuleTest`（4 tests / 46 assertions）：
锁定 12 个类的精确定义存在、副标题显式非加粗、图标触发器 ≥44px、
14 个前台资料页语言键在两套 JS 语言包中均存在。
红绿验证：把触控尺寸退回 38px 后测试变红。

### 9.4 UI 审计仍未覆盖的部分（需真实浏览器）

静态审计能证明「类有定义」「尺寸达标」，**不能证明视觉美观**。以下项本轮未做：

- 四视口（1440/1280/768/390）真实渲染下的横向溢出与元素重叠
- 间距密度的视觉一致性（同一家族内 padding/margin 是否真的成体系）
- 深浅主题下 disabled 文本、placeholder、表格斑马纹的实际对比度
- 长用户名/长金额/长币种撑破单元格的真实表现
- 键盘焦点可见性、移动端侧栏开关/遮罩/Escape/焦点恢复
- 表格 loading / empty / error / disabled 四态的实际观感

**不得把本节的静态结论表述为浏览器验收通过。**

