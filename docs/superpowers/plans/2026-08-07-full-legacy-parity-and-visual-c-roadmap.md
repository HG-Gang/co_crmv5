# Full Legacy Parity and Visual C Program Roadmap

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 以可审计、可阻断的阶段完成旧项目业务等价替换、隔离数据迁移演练，以及 Layui/CrmUI 全量视觉 C 改造。

**Architecture:** 全项目采用逐模块纵向闭环，但每个独立业务域使用单独实施计划。阶段 0 先建立可信清单和安全基线；后续计划只能引用上游阶段产出的真实差异，不允许根据历史报告预判“已完成”。

**Tech Stack:** PHP 7.4+/Laravel 8.83、PHPUnit 9.5、MySQL 3307、Blade、Layui、项目内 JavaScript/CSS、PowerShell 测试运行器、浏览器视觉验证。

---

## 计划拆分原则

设计规格同时包含数据库安全、六个业务域、两套 UI 页面族和全量迁移验收。这些子系统具有独立状态机、测试夹具和失败边界，必须拆成单独计划。每个阶段都交付可运行、可测试的软件，并以自己的验收证据作为下一阶段输入。

当前目录没有 `.git`，实施阶段不能执行计划模板中的 Git commit。每个任务改用“修改文件清单 + 修改前后 SHA-256 + 目标测试结果”作为检查点；不得初始化仓库或伪造 commit。

## 执行顺序

### Phase 0：安全基线与全量清单

计划文件：`docs/superpowers/plans/2026-08-07-phase-0-safety-and-parity-baseline.md`

交付测试库脚本单一来源、正确旧项目默认路径、当前项目全部 Controller/路由/Blade/JS/CSS/migration/test 清单，以及重新生成的旧项目源码、路由和业务核验矩阵。

完成门槛：静态工具测试全绿；旧正式库和新正式库均无写入；全部 Blade 归入 Layui、CrmUI 或共享视图族；生成物不存在未知页面族。

### Phase 1：视觉 C 基础层

计划文件：`docs/superpowers/plans/2026-08-07-phase-1-visual-c-foundation.md`

输入：Phase 0 页面和资产清单。交付 Layui/CrmUI 独立 token、布局、表格、表单、弹窗、状态与响应式基础，并以前后台各一个高频页面完成桌面/移动验证。

完成门槛：不跨页面族引用资产；页面业务字段和路由不变；四个目标视口无重叠、溢出或控制台错误。

### Phase 2：身份与组织

计划文件：`docs/superpowers/plans/2026-08-07-phase-2-identity-and-organization.md`

输入：Phase 0 核验矩阵中身份、认证、管理员、用户、代理、大代理、实名、黑名单、权限和数据范围记录。交付业务差分修复、双 UI 页面和完整权限/越权测试。

完成门槛：身份与组织矩阵无未解释差异；层级、权限和数据范围运行时测试全绿。

### Phase 3：账户与交易

计划文件：`docs/superpowers/plans/2026-08-07-phase-3-account-and-trading.md`

输入：账户、产品、订单、持仓、平仓、风险和 MT4 本地状态记录。交付状态机、Decimal 精度、幂等、并发及双 UI 闭环。

完成门槛：MT4 真实连接保持禁用；账户和交易状态迁移无假成功；相关矩阵和测试全绿。

### Phase 4：资金业务

计划文件：`docs/superpowers/plans/2026-08-07-phase-4-funding.md`

输入：入金、出金、渠道、汇率、流水、批量导入、优惠券和礼品记录。交付金额守恒、回调幂等、审批并发、失败补偿和双 UI 闭环。

完成门槛：金额及状态一致性校验通过；重复提交和外部未知结果均有可审计路径。

### Phase 5：返佣与结算

计划文件：`docs/superpowers/plans/2026-08-07-phase-5-commission-and-settlement.md`

输入：普通返佣、实时返佣、权益、仓位汇总和代理结算记录。交付计算口径、周期、层级归属、重复结算防护和双 UI。

完成门槛：明细与汇总一致；重复执行不重复记账；全部合理差异有证据。

### Phase 6：运营配置

计划文件：`docs/superpowers/plans/2026-08-07-phase-6-operations-and-configuration.md`

输入：仪表盘、在线用户、新闻、菜单、角色、权限、系统配置和分组配置记录。交付配置生效、缓存失效、内容状态、审计和双 UI。

完成门槛：配置范围和权限可证明；全部运营页面达到视觉 C 与交互状态要求。

### Phase 7：隔离迁移与全量验收

计划文件：`docs/superpowers/plans/2026-08-07-phase-7-isolated-migration-and-full-acceptance.md`

输入：Phase 0-6 全部通过证据。交付 schema 空库重放、旧库只读画像、迁移演练、测试库构建、完整 PHPUnit、逐文件隔离测试、全 Blade 浏览器验证和最终差异报告。

完成门槛：全部门禁零 Failure、零 Error、零 Crash；正式 `co_crmv5` 仍未写入。正式迁移必须作为用户再次明确授权后的独立计划。

## 阶段门禁

- [ ] 每个阶段开始前读取设计规格、路线图、上一阶段报告和目标模块真实文件。
- [ ] 每个阶段先写失败测试，再做最小根因修复。
- [ ] 每个阶段完成目标测试、受影响回归、PHP/JS/CSS 静态检查和浏览器验证。
- [ ] 每个阶段写入修改文件、SHA-256、测试命令、退出码和未关闭问题。
- [ ] 任一门禁失败即停止，不能用重跑、跳过、放宽断言或手工修数据标记通过。
- [ ] 后续阶段的详细计划只在上游真实清单可用后编写，避免虚构文件和缺陷。

## 规格覆盖

| 设计规格要求 | 实施阶段 |
| --- | --- |
| 新旧路由、Controller、Blade、JS、CSS、migration 和测试全量清单 | Phase 0 |
| 旧缺陷允许修正且差异可审计 | Phase 2-6，Phase 7 汇总 |
| Layui 与 CrmUI 全部保留并采用视觉 C | Phase 1 建立基础，Phase 2-6 扩展，Phase 7 全量验证 |
| Blade 前后端不分离，只使用 CSS/JavaScript 交互 | Phase 1-6，Phase 7 架构门禁 |
| 参数、权限、并发、幂等、外部失败和状态闭环 | Phase 2-6 |
| 旧库只读、正式新库禁写、隔离库迁移演练 | Phase 0 安全基线，Phase 7 数据演练 |
| Unit、Feature、逐文件隔离、全量回归和浏览器验证 | 每阶段目标回归，Phase 7 最终门禁 |
| 修改文件、SHA-256、测试和未关闭项交付 | 每个阶段检查点 |
| 正式库迁移必须再次授权 | Phase 7 只生成候选；正式迁移使用后续独立计划 |

## 授权边界

- 允许读取两个项目和旧正式库。
- 允许修改新项目工作区文件。
- 允许在后续获执行批准后创建和重建明确 allowlist 中的隔离库。
- 不允许写入 `hank_zl_data`。
- 未经再次明确确认，不允许写入、删除或重建正式 `co_crmv5`。
