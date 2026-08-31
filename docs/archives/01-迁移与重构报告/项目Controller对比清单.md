# 新旧项目Controller对比清单

## 旧项目（new_co_gmtk_crmV3）Controller列表

### 共32个Controller文件

1. AdminController.php - 管理员管理
2. NewsInfoController.php - 新闻管理
3. RoleController.php - 角色管理
4. UserLoginOnlineController.php - 在线用户管理
5. LoginController.php - 登录控制
6. BigAgentController.php - 大代理管理
7. BigNumberController.php - 大数据统计
8. RightsSummaryController.php - 权益汇总
9. WithdrawFlowController.php - 提现流程
10. WithdrawStatusController.php - 提现状态
11. WithdrawAmountController.php - 提现金额
12. GroupConfigController.php - 组配置
13. UnDepositAmountController.php - 未入金金额
14. AdminCloseOrderController.php - 管理员平仓订单
15. AdminOpenOrderController.php - 管理员开仓订单
16. AdminProductionController.php - 产品管理
17. AdminRealCommissionController.php - 实时佣金
18. FengXianManageController.php - 风险管理
19. UserGroupController.php - 用户组管理
20. AdminWhsExpZeroController.php - 仓位清零
21. AuthenticationController.php - 认证管理
22. CancellationController.php - 注销管理
23. PositionSummaryController.php - 持仓汇总
24. AdministratorsController.php - 管理员列表
25. GiftController.php - 礼品管理
26. DepositAmountController.php - 入金金额
27. AgentControllerV3.php - 代理管理V3
28. CustomerController.php - 客户管理
29. BatchAmountController.php - 批量金额
30. BatchCreditController.php - 批量信用
31. ExchangeRateController.php - 汇率管理
32. PayChannelController.php - 支付渠道

---

## 新项目（co_crmv5）Controller列表

### 共37个Controller文件

1. DataScopeController.php - 数据权限配置
2. FundFlowController.php - 资金流水
3. TradeController.php - 交易管理
4. ExchangeRateController.php - 汇率管理 ✅
5. OnlineUserController.php - 在线用户 ✅
6. ProductionController.php - 产品管理 ✅
7. GiftController.php - 礼品管理 ✅
8. AuthenticationController.php - 认证管理 ✅
9. RealtimeCommissionController.php - 实时佣金 ✅
10. PositionSummaryController.php - 持仓汇总 ✅
11. RiskController.php - 风险管理 ✅
12. BatchAmountImportController.php - 批量金额导入 ✅
13. BatchCreditImportController.php - 批量信用导入 ✅
14. RightsSummaryController.php - 权益汇总 ✅
15. MenuController.php - 菜单管理
16. PermissionController.php - 权限管理
17. RoleController.php - 角色管理 ✅
18. AdminDashboardController.php - 管理后台仪表盘
19. AdminBaseController.php - 管理后台基类
20. WithdrawController.php - 提现管理 ✅
21. VoucherController.php - 凭证管理
22. SystemConfigController.php - 系统配置
23. UserController.php - 用户管理
24. BigAgentController.php - 大代理管理 ✅
25. NewsController.php - 新闻管理 ✅
26. AgentController.php - 代理管理 ✅
27. CancelApplyController.php - 注销申请 ✅
28. DashboardController.php - 仪表盘
29. AdminController.php - 管理员 ✅
30. BlacklistController.php - 黑名单
31. CommissionController.php - 佣金管理
32. AgentLevelController.php - 代理等级
33. DepositController.php - 入金管理 ✅
34. GroupConfigController.php - 组配置 ✅
35. PaymentChannelController.php - 支付渠道 ✅
36. AdminAuthController.php - 管理员认证
37. AuthController.php - 认证控制
38. AdminUserController.php - 管理员用户

---

## 差异分析

### 旧项目有但新项目缺失的Controller

1. ❌ BigNumberController.php - 大数据统计（需要迁移）
2. ❌ WithdrawFlowController.php - 提现流程（需要迁移）
3. ❌ WithdrawStatusController.php - 提现状态（需要迁移）
4. ❌ WithdrawAmountController.php - 提现金额（需要迁移）
5. ❌ UnDepositAmountController.php - 未入金金额（需要迁移）
6. ❌ AdminCloseOrderController.php - 管理员平仓订单（需要迁移）
7. ❌ AdminOpenOrderController.php - 管理员开仓订单（需要迁移）
8. ❌ AdminProductionController.php - 产品管理（新项目有ProductionController，需对比）
9. ❌ AdminRealCommissionController.php - 实时佣金（新项目有RealtimeCommissionController，需对比）
10. ❌ FengXianManageController.php - 风险管理（新项目有RiskController，需对比）
11. ❌ UserGroupController.php - 用户组管理（需要迁移）
12. ❌ AdminWhsExpZeroController.php - 仓位清零（需要迁移）
13. ❌ CancellationController.php - 注销管理（新项目有CancelApplyController，需对比）
14. ❌ AdministratorsController.php - 管理员列表（新项目有AdminController，需对比）
15. ❌ DepositAmountController.php - 入金金额（新项目有DepositController，需对比）
16. ❌ AgentControllerV3.php - 代理管理V3（新项目有AgentController，需对比）
17. ❌ CustomerController.php - 客户管理（新项目有UserController，需对比）
18. ❌ BatchAmountController.php - 批量金额（新项目有BatchAmountImportController，需对比）
19. ❌ BatchCreditController.php - 批量信用（新项目有BatchCreditImportController，需对比）
20. ❌ LoginController.php - 登录控制（新项目有AdminAuthController，需对比）
21. ❌ UserLoginOnlineController.php - 在线用户（新项目有OnlineUserController，需对比）

### 新项目有但旧项目没有的Controller

1. ✅ DataScopeController.php - 数据权限配置（新功能）
2. ✅ FundFlowController.php - 资金流水（新功能）
3. ✅ TradeController.php - 交易管理（新功能）
4. ✅ MenuController.php - 菜单管理（新功能）
5. ✅ PermissionController.php - 权限管理（新功能）
6. ✅ AdminDashboardController.php - 管理后台仪表盘（新功能）
7. ✅ VoucherController.php - 凭证管理（新功能）
8. ✅ SystemConfigController.php - 系统配置（新功能）
9. ✅ BlacklistController.php - 黑名单（新功能）
10. ✅ DashboardController.php - 仪表盘（新功能）
11. ✅ AuthController.php - 认证控制（新功能）
12. ✅ AdminUserController.php - 管理员用户（新功能）

---

## 迁移优先级

### 🔴 高优先级（核心业务逻辑，必须迁移）

1. CustomerController.php → UserController.php（客户管理）
2. AgentControllerV3.php → AgentController.php（代理管理）
3. DepositAmountController.php → DepositController.php（入金管理）
4. WithdrawFlowController.php + WithdrawAmountController.php → WithdrawController.php（提现管理）
5. AdminCloseOrderController.php + AdminOpenOrderController.php → TradeController.php（订单管理）
6. BatchAmountController.php → BatchAmountImportController.php（批量金额）
7. BatchCreditController.php → BatchCreditImportController.php（批量信用）

### 🟡 中优先级（重要统计和报表功能）

8. BigNumberController.php（大数据统计）
9. AdminRealCommissionController.php → RealtimeCommissionController.php（实时佣金）
10. FengXianManageController.php → RiskController.php（风险管理）
11. UnDepositAmountController.php（未入金金额）
12. WithdrawStatusController.php（提现状态）

### 🟢 低优先级（辅助功能）

13. UserGroupController.php（用户组管理）
14. AdminWhsExpZeroController.php（仓位清零）
15. UserLoginOnlineController.php → OnlineUserController.php（在线用户）
16. CancellationController.php → CancelApplyController.php（注销管理）
17. AdministratorsController.php → AdminController.php（管理员列表）
18. LoginController.php → AdminAuthController.php（登录控制）

---

## 下一步行动计划

### 第一步：读取旧项目核心Controller
1. 读取 CustomerController.php（客户管理）
2. 读取 AgentControllerV3.php（代理管理）
3. 读取 DepositAmountController.php（入金管理）

### 第二步：对比新项目对应Controller
1. 对比 UserController.php
2. 对比 AgentController.php
3. 对比 DepositController.php

### 第三步：补充缺失的业务逻辑
1. 将旧项目的完整业务逻辑迁移到新项目
2. 替换Mock数据为真实数据库查询
3. 确保所有接口返回真实数据

### 第四步：数据库数据迁移
1. 检查新数据库是否有基础数据
2. 从旧数据库迁移必要的基础数据
3. 验证数据完整性

---

## 状态标记说明

- ✅ 已存在且功能相似
- ❌ 缺失或需要补充
- 🔄 需要对比和合并
