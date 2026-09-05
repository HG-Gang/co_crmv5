# 新版 UI 使用指南

**版本**: v1.0.0  
**更新时间**: 2026-09-04  
**适用人群**: 开发者、测试人员、运营人员

---

## 📋 目录

1. [前后端 UI 访问地址](#前后端-ui-访问地址)
2. [后台管理端（Admin Backend）](#后台管理端admin-backend)
3. [前台用户端（Frontend User）](#前台用户端frontend-user)
4. [新旧 UI 对比](#新旧-ui-对比)
5. [常见问题](#常见问题)

---

## 🌐 前后端 UI 访问地址

### 后台管理端

| UI 版本 | 访问地址 | 技术栈 | 状态 | 推荐 |
|---------|----------|--------|------|------|
| **Tailwind 新版** | `/admin-tailwind/login` | Tailwind CSS + Alpine.js | ✅ 已优化 | ⭐ 推荐 |
| Layui 旧版 | `/admin/login` | Layui + jQuery | ⚠️ 维护模式 | - |

### 前台用户端

| UI 版本 | 访问地址 | 技术栈 | 状态 | 推荐 |
|---------|----------|--------|------|------|
| **CoreUI v2 新版** | `/front-coreui-v2/login` | CoreUI v5 + Bootstrap 5 | ✅ 已优化 | ⭐ 推荐 |
| 旧版前台 | `/front/login` | 老版本 | ⚠️ 即将废弃 | - |

---

## 🔐 后台管理端（Admin Backend）

### 快速开始

#### 1. 访问登录页面

```
方式一：直接访问
http://your-domain.com/admin-tailwind/login

方式二：本地开发
http://localhost/admin-tailwind/login
```

#### 2. 测试账号登录

```
用户名: admin
密码: admin123
```

#### 3. 访问仪表盘

登录成功后自动跳转到：`/admin-tailwind/dashboard`

---

### 🗂️ 后台功能模块列表

#### 仪表盘
- **路由**: `/admin-tailwind/dashboard`
- **功能**: 系统概览、统计数据、实时监控

#### 用户管理
- **用户列表**: `/admin-tailwind/users`
- **用户详情**: `/admin-tailwind/users/{id}`
- **功能**: 查看、编辑、冻结、删除用户

#### 代理管理
- **代理列表**: `/admin-tailwind/agents`
- **大代理**: `/admin-tailwind/big-agents`
- **代理等级**: `/admin-tailwind/agent-levels`

#### 资金管理
- **入金管理**: `/admin-tailwind/deposits`
- **入金导入**: `/admin-tailwind/deposit-imports`
- **出金管理**: `/admin-tailwind/withdrawals`
  - 待处理: `/admin-tailwind/withdrawals/pending`
  - 处理中: `/admin-tailwind/withdrawals/processing`
  - 已完成: `/admin-tailwind/withdrawals/completed`
  - 失败记录: `/admin-tailwind/withdrawals/failed`
- **出金导入**: `/admin-tailwind/withdraw-imports`
- **流水记录**: `/admin-tailwind/withdraw-flows`
- **凭证管理**: `/admin-tailwind/vouchers`

#### 报表统计
- **权益汇总**: `/admin-tailwind/reports/rights-summary`
- **持仓汇总**: `/admin-tailwind/reports/position-summary`
- **佣金报表**: `/admin-tailwind/reports/commissions`
- **实时返佣**: `/admin-tailwind/reports/realtime-commissions`
- **交易记录**: `/admin-tailwind/reports/trades`

#### 系统管理
- **系统配置**: `/admin-tailwind/system/configs`
- **组配置**: `/admin-tailwind/system/group-configs`
- **汇率配置**: `/admin-tailwind/system/exchange-rates`
- **渠道管理**: `/admin-tailwind/system/channels`
- **产品管理**: `/admin-tailwind/system/productions`
- **礼品管理**: `/admin-tailwind/system/gifts`
- **新闻管理**: `/admin-tailwind/system/news`
- **信用导入**: `/admin-tailwind/system/credit-imports`
- **在线用户**: `/admin-tailwind/system/online-users`

#### 权限管理
- **角色管理**: `/admin-tailwind/roles`
- **权限管理**: `/admin-tailwind/permissions`
- **菜单管理**: `/admin-tailwind/menus`
- **数据权限**: `/admin-tailwind/data-scopes`
- **管理员**: `/admin-tailwind/admins`

#### 风控管理
- **风控概览**: `/admin-tailwind/risk`
- **黑名单**: `/admin-tailwind/risk/blacklist`
- **实名认证**: `/admin-tailwind/risk/authentications`
- **注销申请**: `/admin-tailwind/risk/cancel-applies`
- **异常监控**: `/admin-tailwind/risk/whs-exp-zero`

#### 个人设置
- **编辑资料**: `/admin-tailwind/profile/edit`
- **修改密码**: `/admin-tailwind/profile/change-password`

---

### ✨ 新版后台特性

#### 响应式设计
- ✅ 支持桌面、平板、手机全设备
- ✅ 侧边栏自适应折叠/展开
- ✅ 表格自动适配屏幕宽度

#### 现代化交互
- ✅ 深色侧边栏 + 渐变卡片
- ✅ 操作按钮 44×44px 触摸目标（WCAG 标准）
- ✅ 悬停反馈 + 过渡动画

#### 性能优化
- ✅ Tailwind CSS JIT 按需生成
- ✅ Alpine.js 轻量级状态管理
- ✅ 无 jQuery 依赖

---

## 👤 前台用户端（Frontend User）

### 快速开始

#### 1. 访问登录页面

```
方式一：直接访问
http://your-domain.com/front-coreui-v2/login

方式二：本地开发
http://localhost/front-coreui-v2/login
```

#### 2. 测试账号登录

```
邮箱: test@user.com
密码: user123456
```

#### 3. 访问仪表盘

登录成功后自动跳转到：`/front-coreui-v2/dashboard`

---

### 🗂️ 前台功能模块列表

#### 认证模块
- **登录**: `/front-coreui-v2/login`
- **注册**: `/front-coreui-v2/register/{inviter_id?}`（支持邀请码）
- **忘记密码**: `/front-coreui-v2/forgot-password`
- **大客户登录**: `/front-coreui-v2/big-number-login`

#### 仪表盘
- **首页**: `/front-coreui-v2/dashboard`
- **功能**: 账户概览、持仓订单、权益趋势图

#### 个人信息
- **个人中心**: `/front-coreui-v2/profile`
- **编辑资料**: `/front-coreui-v2/profile/edit`
- **修改密码**: `/front-coreui-v2/profile/change-password`
- **修改邮箱**: `/front-coreui-v2/profile/change-email`

#### 账户管理
- **账户信息**: `/front-coreui-v2/account/info`
- **账户余额**: `/front-coreui-v2/account/balance`
- **实名认证**: `/front-coreui-v2/account/voucher`
- **凭证浏览**: `/front-coreui-v2/account/voucher-browse`
- **账户注销**: `/front-coreui-v2/account/cancel`

#### 资金操作
- **在线入金**: `/front-coreui-v2/deposit`
- **在线出金**: `/front-coreui-v2/withdraw`
- **流水记录**: `/front-coreui-v2/flow`

#### 持仓订单
- **持仓汇总**: `/front-coreui-v2/position/summary`
- **持仓详情**: `/front-coreui-v2/position/summary-detail/{id}`
- **佣金汇总**: `/front-coreui-v2/position/comm-summary`
- **持仓订单**: `/front-coreui-v2/order/open`
- **订单详情**: `/front-coreui-v2/order/open-detail/{orderId}`
- **历史订单**: `/front-coreui-v2/order/closed`
- **历史详情**: `/front-coreui-v2/order/closed-detail/{orderId}`

#### 代理管理
- **下级代理**: `/front-coreui-v2/agent/sub`
- **客户列表**: `/front-coreui-v2/agent/customers`
- **客户详情**: `/front-coreui-v2/agent/customers-detail/{puid}`
- **客户信息**: `/front-coreui-v2/agent/customer-detail/{role}/{uid}`
- **等级确认**: `/front-coreui-v2/agent/confirm-level`
- **组别切换**: `/front-coreui-v2/agent/group-change`
- **组别详情**: `/front-coreui-v2/agent/group-change-detail/{uid}`

#### 佣金管理
- **实时返佣**: `/front-coreui-v2/commission/realtime`
- **返佣详情**: `/front-coreui-v2/commission/realtime-detail/{orderNo}`
- **历史佣金**: `/front-coreui-v2/commission/history`
- **佣金转账**: `/front-coreui-v2/commission/transfer`
- **转账目标**: `/front-coreui-v2/commission/transfer-target`

#### 礼品管理
- **地址管理**: `/front-coreui-v2/gift/address`
- **新增地址**: `/front-coreui-v2/gift/address-add`
- **编辑地址**: `/front-coreui-v2/gift/address-edit`
- **礼品列表**: `/front-coreui-v2/gift/list`

#### 新闻公告
- **新闻列表**: `/front-coreui-v2/news`
- **新闻详情**: `/front-coreui-v2/news/detail`

---

### ✨ 新版前台特性

#### 移动端优化
- ✅ 侧边栏完整体验（遮罩层 + 滚动锁定）
- ✅ 表格自动切换卡片布局（<768px）
- ✅ 触摸友好的按钮和链接

#### 现代化设计
- ✅ CoreUI v5 + Bootstrap 5
- ✅ 渐变卡片 + 阴影效果
- ✅ Font Awesome 6 图标库

#### 数据可视化
- ✅ Chart.js 权益趋势图
- ✅ 实时盈亏计算
- ✅ 动态统计卡片

---

## 📊 新旧 UI 对比

### 后台管理端

| 特性 | Layui 旧版 | Tailwind 新版 |
|------|------------|--------------|
| 技术栈 | Layui + jQuery | Tailwind + Alpine.js |
| 响应式 | ⚠️ 部分支持 | ✅ 完整支持 |
| 移动端 | ❌ 体验较差 | ✅ 优化完善 |
| 触摸目标 | ❌ 不符合标准 | ✅ WCAG 标准 |
| 侧边栏 | 固定宽度 | ✅ 自适应折叠 |
| 加载速度 | ⚠️ 中等 | ✅ 快速 |
| 维护性 | ⚠️ jQuery 依赖 | ✅ 现代化 |

### 前台用户端

| 特性 | 旧版前台 | CoreUI v2 新版 |
|------|---------|---------------|
| 技术栈 | 老版本 | CoreUI v5 + Bootstrap 5 |
| 移动端表格 | ❌ 横向滚动 | ✅ 卡片堆叠 |
| 侧边栏遮罩 | ❌ 无 | ✅ 完整体验 |
| 数据可视化 | ⚠️ 简单 | ✅ Chart.js |
| 支付方式 | ⚠️ 单一 | ✅ 多种选择 |
| 用户体验 | ⚠️ 一般 | ✅ 现代化 |

---

## ❓ 常见问题

### Q1: 如何切换到新版 UI？

**A**: 直接访问新版 URL 即可：
- 后台：`/admin-tailwind/login`
- 前台：`/front-coreui-v2/login`

### Q2: 旧版 UI 还能用吗？

**A**: 可以，旧版 UI 处于维护模式，但推荐使用新版以获得更好的体验。

### Q3: 新版 UI 的数据是否与旧版同步？

**A**: 是的，新旧版本使用同一套数据库和 API，数据完全同步。

### Q4: 移动端访问有什么区别？

**A**: 新版 UI 针对移动端做了深度优化：
- 表格自动切换为卡片布局
- 侧边栏有遮罩层和滚动锁定
- 所有按钮符合 44×44px 触摸目标标准

### Q5: 如何在移动端测试？

**A**: 
```
方式一：Chrome DevTools
1. 打开开发者工具（F12）
2. 切换到移动设备模式（Ctrl+Shift+M）
3. 选择设备型号（iPhone 12, iPad 等）

方式二：实际设备
直接用手机/平板访问测试地址
```

### Q6: 新版 UI 支持哪些浏览器？

**A**: 
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ❌ IE 11（不支持）

### Q7: 登录后找不到某个功能怎么办？

**A**: 
1. 检查侧边栏导航菜单
2. 使用顶部搜索框（后台 Tailwind 版本）
3. 查看本文档的模块列表找到对应路由

### Q8: 如何设置默认使用新版 UI？

**A**: 在项目根目录修改默认路由，或在登录成功后重定向到新版地址。

### Q9: 新版 UI 性能如何？

**A**:
- Tailwind CSS JIT 模式：按需生成，体积更小
- Alpine.js：仅 15KB，比 jQuery 轻量 90%
- 首屏加载：<2s（在 4G 网络下）

### Q10: 发现 Bug 怎么反馈？

**A**: 
1. 记录复现步骤
2. 截图或录屏
3. 浏览器版本 + 设备信息
4. 提交到项目 Issue 或联系开发团队

---

## 🚀 快速链接

### 后台管理端（推荐）
```
登录: http://localhost/admin-tailwind/login
账号: admin
密码: admin123
```

### 前台用户端（推荐）
```
登录: http://localhost/front-coreui-v2/login
账号: test@user.com
密码: user123456
```

### 文档
- [测试登录账号](./test-login-accounts.md)
- [UI 优化完成报告](./ui-optimization-complete-report.md)

---

**最后更新**: 2026-09-04  
**文档版本**: v1.0.0  
**作者**: System
