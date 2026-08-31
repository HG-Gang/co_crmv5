# CoCRM v5 编号效果图索引

每张图片左上角含有四位编号。请直接反馈编号，例如“修改 #0063 的状态表格”。

> 大代理端属于用户可见门户，因此与普通前台页面一同存放在 `front/`。

## 前台效果图目录

| 序号 | 界面端 | UI 家族 | 模块 | 页面 | 正式路由 | 对应视图 | 文件 |
|---:|---|---|---|---|---|---|---|
| #0001 | 前台 Layui | Layui | 前台用户门户 / 认证 | 用户登录 | `/front/login` | `front_layui::auth.login` | `front/layui/0001__front__login.png` |
| #0002 | 前台 Layui | Layui | 前台用户门户 / 认证 | 用户注册 | `/front/register/{inviter_id?}` | `front_layui::auth.register` | `front/layui/0002__front__register.png` |
| #0003 | 前台 Layui | Layui | 前台用户门户 / 认证 | 找回密码 | `/front/forgot-password` | `front_layui::auth.forgot-password` | `front/layui/0003__front__forgot-password.png` |
| #0004 | 前台 Layui | Layui | 前台用户门户 / 认证 | 大代理登录 | `/front/big-number/login` | `front_layui::auth.big-number-login` | `front/layui/0004__front__big-number-login.png` |
| #0005 | 前台 Layui | Layui | 前台用户门户 / 工作台 | 用户仪表盘 | `/front/dashboard` | `front_layui::dashboard.index` | `front/layui/0005__front__dashboard.png` |
| #0006 | 前台 Layui | Layui | 前台用户门户 / 资料中心 | 个人资料 | `/front/profile` | `front_layui::profile.index` | `front/layui/0006__front__profile.png` |
| #0007 | 前台 Layui | Layui | 前台用户门户 / 资料中心 | 编辑资料 | `/front/profile/edit` | `front_layui::profile.edit` | `front/layui/0007__front__profile-edit.png` |
| #0008 | 前台 Layui | Layui | 前台用户门户 / 资料中心 | 修改登录密码 | `/front/profile/change-password` | `front_layui::profile.change-password` | `front/layui/0008__front__profile-password.png` |
| #0009 | 前台 Layui | Layui | 前台用户门户 / 资料中心 | 修改邮箱 | `/front/profile/change-email` | `front_layui::profile.change-email` | `front/layui/0009__front__profile-email.png` |
| #0010 | 前台 Layui | Layui | 前台用户门户 / 账户管理 | 交易账户信息 | `/front/account/info` | `front_layui::account.info` | `front/layui/0010__front__account-info.png` |
| #0011 | 前台 Layui | Layui | 前台用户门户 / 账户管理 | 账户余额与净值 | `/front/account/balance` | `front_layui::account.balance` | `front/layui/0011__front__account-balance.png` |
| #0012 | 前台 Layui | Layui | 前台用户门户 / 账户管理 | 入金凭证 | `/front/account/voucher` | `front_layui::account.voucher` | `front/layui/0012__front__account-voucher.png` |
| #0013 | 前台 Layui | Layui | 前台用户门户 / 账户管理 | 账户注销申请 | `/front/account/cancel` | `front_layui::account.cancel` | `front/layui/0013__front__account-cancel.png` |
| #0014 | 前台 Layui | Layui | 前台用户门户 / 资金管理 | 入金申请 | `/front/deposit` | `front_layui::deposit.index` | `front/layui/0014__front__deposit.png` |
| #0015 | 前台 Layui | Layui | 前台用户门户 / 资金管理 | 出金申请 | `/front/withdraw` | `front_layui::withdraw.index` | `front/layui/0015__front__withdraw.png` |
| #0016 | 前台 Layui | Layui | 前台用户门户 / 资金管理 | 资金流水 | `/front/flow` | `front_layui::flow.index` | `front/layui/0016__front__flow.png` |
| #0017 | 前台 Layui | Layui | 前台用户门户 / 交易管理 | 团队持仓汇总 | `/front/position/summary` | `front_layui::position.summary` | `front/layui/0017__front__position-summary.png` |
| #0018 | 前台 Layui | Layui | 前台用户门户 / 交易管理 | 本人 MT4 持仓汇总 | `/front/position/summary2` | `front_layui::position.summary2` | `front/layui/0018__front__position-summary2.png` |
| #0019 | 前台 Layui | Layui | 前台用户门户 / 交易管理 | 当前持仓订单 | `/front/order/open` | `front_layui::order.open` | `front/layui/0019__front__order-open.png` |
| #0020 | 前台 Layui | Layui | 前台用户门户 / 交易管理 | 历史平仓订单 | `/front/order/closed` | `front_layui::order.closed` | `front/layui/0020__front__order-closed.png` |
| #0021 | 前台 Layui | Layui | 前台用户门户 / 代理管理 | 下级代理 | `/front/agent/sub` | `front_layui::agent.sub` | `front/layui/0021__front__agent-sub.png` |
| #0022 | 前台 Layui | Layui | 前台用户门户 / 代理管理 | 直属客户 | `/front/agent/customers` | `front_layui::agent.customers` | `front/layui/0022__front__agent-customers.png` |
| #0023 | 前台 Layui | Layui | 前台用户门户 / 代理管理 | 客户详情 | `/front/agent/customer-detail/{role}/{uid}` | `front_layui::agent.customers` | `front/layui/0023__front__agent-customer-detail.png` |
| #0024 | 前台 Layui | Layui | 前台用户门户 / 代理管理 | 代理级别确认 | `/front/agent/confirm-level` | `front_layui::agent.confirm-level` | `front/layui/0024__front__agent-confirm.png` |
| #0025 | 前台 Layui | Layui | 前台用户门户 / 代理管理 | 直属客户转组 | `/front/agent/group-change` | `front_layui::agent.group-change` | `front/layui/0025__front__agent-group-change.png` |
| #0026 | 前台 Layui | Layui | 前台用户门户 / 返佣管理 | 实时返佣 | `/front/commission/realtime` | `front_layui::commission.realtime` | `front/layui/0026__front__commission-realtime.png` |
| #0027 | 前台 Layui | Layui | 前台用户门户 / 返佣管理 | 返佣历史 | `/front/commission/history` | `front_layui::commission.history` | `front/layui/0027__front__commission-history.png` |
| #0028 | 前台 Layui | Layui | 前台用户门户 / 返佣管理 | 返佣转账 | `/front/commission/transfer` | `front_layui::commission.transfer` | `front/layui/0028__front__commission-transfer.png` |
| #0029 | 前台 Layui | Layui | 前台用户门户 / 权益与礼品 | 收货地址 | `/front/gift/address` | `front_layui::gift.address` | `front/layui/0029__front__gift-address.png` |
| #0030 | 前台 Layui | Layui | 前台用户门户 / 权益与礼品 | 礼品列表 | `/front/gift/list` | `front_layui::gift.list` | `front/layui/0030__front__gift-list.png` |
| #0031 | 前台 Layui | Layui | 前台用户门户 / 消息中心 | 公告列表 | `/front/news` | `front_layui::news.index` | `front/layui/0031__front__news.png` |
| #0032 | 前台 Layui | Layui | 前台用户门户 / 消息中心 | 公告详情 | `/front/news/detail/{newsId}` | `Front\\NewsController@newsPage` | `front/layui/0032__front__news-detail.png` |
| #0033 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 认证 | CrmUI 用户登录 | `/front-crmui/login` | `front_crmui::auth.login` | `front/crmui/0033__front-crmui__login.png` |
| #0034 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 认证 | CrmUI 用户注册 | `/front-crmui/register/{inviter_id?}` | `CrmUi\\Front\\PageController@register` | `front/crmui/0034__front-crmui__register.png` |
| #0035 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 认证 | CrmUI 找回密码 | `/front-crmui/forgot-password` | `CrmUi\\Front\\PageController@forgotPassword` | `front/crmui/0035__front-crmui__forgot-password.png` |
| #0036 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 认证 | CrmUI 大代理登录 | `/front-crmui/big-number/login` | `front_crmui::big-agent.login` | `front/crmui/0036__front-crmui__big-number-login.png` |
| #0037 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 工作台 | CrmUI 用户仪表盘 | `/front-crmui/dashboard` | `front_crmui::dashboard.index` | `front/crmui/0037__front-crmui__dashboard.png` |
| #0038 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 资金管理 | CrmUI 入金申请 | `/front-crmui/deposit` | `front_crmui::deposit.index` | `front/crmui/0038__front-crmui__deposit.png` |
| #0039 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 资金管理 | CrmUI 入金申请 V2 | `/front-crmui/deposit-v2` | `front_crmui::deposit.index-v2` | `front/crmui/0039__front-crmui__deposit-v2.png` |
| #0040 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 资金管理 | CrmUI 出金申请 | `/front-crmui/withdraw` | `front_crmui::withdraw.index` | `front/crmui/0040__front-crmui__withdraw.png` |
| #0041 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 资金管理 | CrmUI 资金流水 | `/front-crmui/flow` | `front_crmui::flow.index` | `front/crmui/0041__front-crmui__flow.png` |
| #0042 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 资料中心 | CrmUI 个人资料 | `/front-crmui/profile` | `front_crmui::profile.index` | `front/crmui/0042__front-crmui__profile.png` |
| #0043 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 资料中心 | CrmUI 个人资料 V2 | `/front-crmui/profile-v2` | `front_crmui::profile.index-v2` | `front/crmui/0043__front-crmui__profile-v2.png` |
| #0044 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 资料中心 | CrmUI 编辑资料 | `/front-crmui/profile/edit` | `front_crmui::profile.edit` | `front/crmui/0044__front-crmui__profile-edit.png` |
| #0045 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 资料中心 | CrmUI 修改密码 | `/front-crmui/profile/change-password` | `front_crmui::profile.change-password` | `front/crmui/0045__front-crmui__profile-password.png` |
| #0046 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 权益与礼品 | CrmUI 礼品列表 | `/front-crmui/gift/list` | `front_crmui::gift.list` | `front/crmui/0046__front-crmui__gift-list.png` |
| #0047 | 前台 CrmUI | CrmUI | 前台 CrmUI 门户 / 消息中心 | CrmUI 公告列表 | `/front-crmui/news` | `front_crmui::news.index` | `front/crmui/0047__front-crmui__news.png` |
| #0048 | 大代理独立门户 | CrmUI 大代理入口 | 大代理独立门户 / 认证 | 大代理登录 | `/front-crmui/big-agent/login` | `CrmUi\\Front\\BigAgentPageController@login` | `front/crmui/big-agent/0048__big-agent__login.png` |
| #0049 | 大代理独立门户 | CrmUI 大代理入口 | 大代理独立门户 / 团队工作台 | 大代理总览 | `/front-crmui/big-agent/dashboard` | `CrmUi\\Front\\BigAgentPageController@dashboard` | `front/crmui/big-agent/0049__big-agent__dashboard.png` |
| #0050 | 大代理独立门户 | CrmUI 大代理入口 | 大代理独立门户 / 团队管理 | 下级代理列表 | `/user/agents/proxy/list` | `Front\\BigNumberController@proxy_agents_list_browse` | `front/crmui/big-agent/0050__big-agent__proxies.png` |
| #0051 | 大代理独立门户 | CrmUI 大代理入口 | 大代理独立门户 / 交易管理 | 团队持仓汇总 | `/user/agents/position/summary` | `Front\\BigNumberController@position_agents_summary_browse` | `front/crmui/big-agent/0051__big-agent__positions.png` |
| #0052 | 大代理独立门户 | CrmUI 大代理入口 | 大代理独立门户 / 交易管理 | 团队当前订单 | `/user/agents/open/order` | `Front\\BigNumberController@big_open_order_browse` | `front/crmui/big-agent/0052__big-agent__orders-open.png` |
| #0053 | 大代理独立门户 | CrmUI 大代理入口 | 大代理独立门户 / 交易管理 | 团队历史订单 | `/user/agents/close/order` | `Front\\BigNumberController@big_close_order_browse` | `front/crmui/big-agent/0053__big-agent__orders-closed.png` |
| #0054 | 大代理独立门户 | CrmUI 大代理入口 | 大代理独立门户 / 账户安全 | 修改登录密码 | `/user/agents/editpsw` | `Front\\BigNumberController@agents_editpsw_browse` | `front/crmui/big-agent/0054__big-agent__password.png` |

## 后台效果图目录

| 序号 | 界面端 | UI 家族 | 模块 | 页面 | 正式路由 | 对应视图 | 文件 |
|---:|---|---|---|---|---|---|---|
| #0055 | 后台 Layui | Layui | 后台运营管理 / 认证 | 后台登录 | `/admin/login` | `admin_layui::auth.login` | `admin/layui/0055__admin__login.png` |
| #0056 | 后台 Layui | Layui | 后台运营管理 / 运营总览 | 后台仪表盘 | `/admin/dashboard` | `admin_layui::dashboard.index` | `admin/layui/0056__admin__dashboard.png` |
| #0057 | 后台 Layui | Layui | 后台运营管理 / 用户与组织 | 用户列表 | `/admin/users` | `admin_layui::users.index` | `admin/layui/0057__admin__users.png` |
| #0058 | 后台 Layui | Layui | 后台运营管理 / 用户与组织 | 用户详情 | `/admin/users/{id}` | `admin_layui::users.detail` | `admin/layui/0058__admin__user-detail.png` |
| #0059 | 后台 Layui | Layui | 后台运营管理 / 用户与组织 | 代理管理 | `/admin/agents` | `admin_layui::agents.index` | `admin/layui/0059__admin__agents.png` |
| #0060 | 后台 Layui | Layui | 后台运营管理 / 用户与组织 | 在线用户 | `/admin/online-users` | `admin_layui::online-users.index` | `admin/layui/0060__admin__online-users.png` |
| #0061 | 后台 Layui | Layui | 后台运营管理 / 用户与组织 | 大代理管理 | `/admin/big-agents` | `admin_layui::big-agents.index` | `admin/layui/0061__admin__big-agents.png` |
| #0062 | 后台 Layui | Layui | 后台运营管理 / 用户与组织 | 代理等级 | `/admin/agent-levels` | `admin_layui::agent-levels.index` | `admin/layui/0062__admin__agent-levels.png` |
| #0063 | 后台 Layui | Layui | 后台运营管理 / 用户与组织 | 组别配置 | `/admin/group-configs` | `admin_layui::group-configs.index` | `admin/layui/0063__admin__group-configs.png` |
| #0064 | 后台 Layui | Layui | 后台运营管理 / 审核与合规 | 实名认证审核 | `/admin/authentications` | `admin_layui::authentications.index` | `admin/layui/0064__admin__authentications.png` |
| #0065 | 后台 Layui | Layui | 后台运营管理 / 审核与合规 | 认证审核详情 | `/admin/authentications/{user}/detail/{mode}` | `admin_layui::authentications.detail` | `admin/layui/0065__admin__authentication-detail.png` |
| #0066 | 后台 Layui | Layui | 后台运营管理 / 审核与合规 | 黑名单管理 | `/admin/blacklist` | `admin_layui::blacklist.index` | `admin/layui/0066__admin__blacklist.png` |
| #0067 | 后台 Layui | Layui | 后台运营管理 / 审核与合规 | 销户申请审核 | `/admin/cancel-applies` | `admin_layui::cancel-applies.index` | `admin/layui/0067__admin__cancel-applies.png` |
| #0068 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 入金审核 | `/admin/deposits` | `admin_layui::deposits.index` | `admin/layui/0068__admin__deposits.png` |
| #0069 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 批量入金导入 | `/admin/deposit-imports` | `admin_layui::deposit-imports.index` | `admin/layui/0069__admin__deposit-imports.png` |
| #0070 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 出金审核 | `/admin/withdrawals` | `admin_layui::withdrawals.index` | `admin/layui/0070__admin__withdrawals.png` |
| #0071 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 待审核出金 | `/admin/withdraw/pending` | `admin_layui::withdrawals.index` | `admin/layui/0071__admin__withdraw-pending.png` |
| #0072 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 处理中出金 | `/admin/withdraw/processing` | `admin_layui::withdrawals.index` | `admin/layui/0072__admin__withdraw-processing.png` |
| #0073 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 已完成出金 | `/admin/withdraw/completed` | `admin_layui::withdrawals.index` | `admin/layui/0073__admin__withdraw-completed.png` |
| #0074 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 失败出金 | `/admin/withdraw/failed` | `admin_layui::withdrawals.index` | `admin/layui/0074__admin__withdraw-failed.png` |
| #0075 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 批量出金导入 | `/admin/withdraw-imports` | `admin_layui::withdraw-imports.index` | `admin/layui/0075__admin__withdraw-imports.png` |
| #0076 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 出金流水核对 | `/admin/withdraw-flows` | `admin_layui::withdraw-flows.index` | `admin/layui/0076__admin__withdraw-flows.png` |
| #0077 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 未入金流水 | `/admin/undeposit-flows` | `admin_layui::undeposit-flows.index` | `admin/layui/0077__admin__undeposit-flows.png` |
| #0078 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 凭证审核 | `/admin/vouchers` | `admin_layui::vouchers.index` | `admin/layui/0078__admin__vouchers.png` |
| #0079 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 汇率配置 | `/admin/exchange-rates` | `admin_layui::exchange-rates.index` | `admin/layui/0079__admin__exchange-rates.png` |
| #0080 | 后台 Layui | Layui | 后台运营管理 / 资金运营 | 支付通道配置 | `/admin/channels` | `admin_layui::channels.index` | `admin/layui/0080__admin__channels.png` |
| #0081 | 后台 Layui | Layui | 后台运营管理 / 交易与风控 | 权益汇总 | `/admin/rights-summary` | `admin_layui::rights-summary.index` | `admin/layui/0081__admin__rights-summary.png` |
| #0082 | 后台 Layui | Layui | 后台运营管理 / 交易与风控 | 持仓汇总 | `/admin/position-summary` | `admin_layui::position-summary.index` | `admin/layui/0082__admin__position-summary.png` |
| #0083 | 后台 Layui | Layui | 后台运营管理 / 交易与风控 | 交易订单 | `/admin/trades` | `admin_layui::trades.index` | `admin/layui/0083__admin__trades.png` |
| #0084 | 后台 Layui | Layui | 后台运营管理 / 交易与风控 | 风险管理 | `/admin/risk` | `admin_layui::risk.index` | `admin/layui/0084__admin__risk.png` |
| #0085 | 后台 Layui | Layui | 后台运营管理 / 交易与风控 | 仓位清零 | `/admin/whs-exp-zero` | `admin_layui::whs-exp-zero.index` | `admin/layui/0085__admin__whs-exp-zero.png` |
| #0086 | 后台 Layui | Layui | 后台运营管理 / 交易与风控 | 交易品种 | `/admin/productions` | `admin_layui::productions.index` | `admin/layui/0086__admin__productions.png` |
| #0087 | 后台 Layui | Layui | 后台运营管理 / 返佣结算 | 周期返佣 | `/admin/commissions` | `admin_layui::commissions.index` | `admin/layui/0087__admin__commissions.png` |
| #0088 | 后台 Layui | Layui | 后台运营管理 / 返佣结算 | 实时返佣 | `/admin/realtime-commissions` | `admin_layui::realtime-commissions.index` | `admin/layui/0088__admin__realtime-commissions.png` |
| #0089 | 后台 Layui | Layui | 后台运营管理 / 返佣结算 | 批量信用导入 | `/admin/credit-imports` | `admin_layui::credit-imports.index` | `admin/layui/0089__admin__credit-imports.png` |
| #0090 | 后台 Layui | Layui | 后台运营管理 / 权限与系统 | 角色管理 | `/admin/roles` | `admin_layui::roles.index` | `admin/layui/0090__admin__roles.png` |
| #0091 | 后台 Layui | Layui | 后台运营管理 / 权限与系统 | 权限管理 | `/admin/permissions` | `admin_layui::permissions.index` | `admin/layui/0091__admin__permissions.png` |
| #0092 | 后台 Layui | Layui | 后台运营管理 / 权限与系统 | 菜单管理 | `/admin/menus` | `admin_layui::menus.index` | `admin/layui/0092__admin__menus.png` |
| #0093 | 后台 Layui | Layui | 后台运营管理 / 权限与系统 | 数据范围 | `/admin/data-scopes` | `admin_layui::data-scopes.index` | `admin/layui/0093__admin__data-scopes.png` |
| #0094 | 后台 Layui | Layui | 后台运营管理 / 权限与系统 | 管理员账号 | `/admin/admins` | `admin_layui::admins.index` | `admin/layui/0094__admin__admins.png` |
| #0095 | 后台 Layui | Layui | 后台运营管理 / 权限与系统 | 系统配置 | `/admin/system-configs` | `admin_layui::system-configs.index` | `admin/layui/0095__admin__system-configs.png` |
| #0096 | 后台 Layui | Layui | 后台运营管理 / 内容与权益 | 新闻公告管理 | `/admin/news` | `admin_layui::news.index` | `admin/layui/0096__admin__news.png` |
| #0097 | 后台 Layui | Layui | 后台运营管理 / 内容与权益 | 礼品发货管理 | `/admin/gifts` | `admin_layui::gifts.index` | `admin/layui/0097__admin__gifts.png` |
| #0098 | 后台 Layui | Layui | 后台运营管理 / 个人中心 | 编辑管理员资料 | `/admin/profile/edit` | `admin_layui::profile.edit` | `admin/layui/0098__admin__profile-edit.png` |
| #0099 | 后台 Layui | Layui | 后台运营管理 / 个人中心 | 修改管理员密码 | `/admin/profile/change-password` | `admin_layui::profile.change-password` | `admin/layui/0099__admin__profile-password.png` |
| #0100 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 认证 | CrmUI 后台登录 | `/admin-crmui/login` | `CrmUi\\Admin\\PageController@login` | `admin/crmui/0100__admin-crmui__login.png` |
| #0101 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 运营总览 | CrmUI 后台仪表盘 | `/admin-crmui/dashboard` | `admin_crmui::dashboard.index` | `admin/crmui/0101__admin-crmui__dashboard.png` |
| #0102 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 用户与组织 | CrmUI 用户列表 | `/admin-crmui/users` | `admin_crmui::users.index` | `admin/crmui/0102__admin-crmui__users.png` |
| #0103 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 用户与组织 | CrmUI 用户详情 | `/admin-crmui/users/{id}` | `admin_crmui::users.detail` | `admin/crmui/0103__admin-crmui__user-detail.png` |
| #0104 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 用户与组织 | CrmUI 代理管理 | `/admin-crmui/agents` | `admin_crmui::agents.index` | `admin/crmui/0104__admin-crmui__agents.png` |
| #0105 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 用户与组织 | CrmUI 在线用户 | `/admin-crmui/online-users` | `admin_crmui::online-users.index` | `admin/crmui/0105__admin-crmui__online-users.png` |
| #0106 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 用户与组织 | CrmUI 大代理管理 | `/admin-crmui/big-agents` | `admin_crmui::big-agents.index` | `admin/crmui/0106__admin-crmui__big-agents.png` |
| #0107 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 用户与组织 | CrmUI 代理等级 | `/admin-crmui/agent-levels` | `admin_crmui::agent-levels.index` | `admin/crmui/0107__admin-crmui__agent-levels.png` |
| #0108 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 用户与组织 | CrmUI 组别配置 | `/admin-crmui/group-configs` | `admin_crmui::group-configs.index` | `admin/crmui/0108__admin-crmui__group-configs.png` |
| #0109 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 审核与合规 | CrmUI 实名认证审核 | `/admin-crmui/authentications` | `admin_crmui::authentications.index` | `admin/crmui/0109__admin-crmui__authentications.png` |
| #0110 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 审核与合规 | CrmUI 黑名单管理 | `/admin-crmui/blacklist` | `admin_crmui::blacklist.index` | `admin/crmui/0110__admin-crmui__blacklist.png` |
| #0111 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 审核与合规 | CrmUI 销户申请审核 | `/admin-crmui/cancel-applies` | `admin_crmui::cancel-applies.index` | `admin/crmui/0111__admin-crmui__cancel-applies.png` |
| #0112 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 入金审核 | `/admin-crmui/deposits` | `admin_crmui::deposits.index` | `admin/crmui/0112__admin-crmui__deposits.png` |
| #0113 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 批量入金导入 | `/admin-crmui/deposit-imports` | `admin_crmui::deposit-imports.index` | `admin/crmui/0113__admin-crmui__deposit-imports.png` |
| #0114 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 出金审核 | `/admin-crmui/withdrawals` | `admin_crmui::withdrawals.index` | `admin/crmui/0114__admin-crmui__withdrawals.png` |
| #0115 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 批量出金导入 | `/admin-crmui/withdraw-imports` | `admin_crmui::withdraw-imports.index` | `admin/crmui/0115__admin-crmui__withdraw-imports.png` |
| #0116 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 出金流水核对 | `/admin-crmui/withdraw-flows` | `admin_crmui::withdraw-flows.index` | `admin/crmui/0116__admin-crmui__withdraw-flows.png` |
| #0117 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 未入金流水 | `/admin-crmui/undeposit-flows` | `admin_crmui::undeposit-flows.index` | `admin/crmui/0117__admin-crmui__undeposit-flows.png` |
| #0118 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 凭证审核 | `/admin-crmui/vouchers` | `admin_crmui::vouchers.index` | `admin/crmui/0118__admin-crmui__vouchers.png` |
| #0119 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 汇率配置 | `/admin-crmui/exchange-rates` | `admin_crmui::exchange-rates.index` | `admin/crmui/0119__admin-crmui__exchange-rates.png` |
| #0120 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 资金运营 | CrmUI 支付通道配置 | `/admin-crmui/channels` | `admin_crmui::channels.index` | `admin/crmui/0120__admin-crmui__channels.png` |
| #0121 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 交易与风控 | CrmUI 权益汇总 | `/admin-crmui/rights-summary` | `admin_crmui::rights-summary.index` | `admin/crmui/0121__admin-crmui__rights-summary.png` |
| #0122 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 交易与风控 | CrmUI 持仓汇总 | `/admin-crmui/position-summary` | `admin_crmui::position-summary.index` | `admin/crmui/0122__admin-crmui__position-summary.png` |
| #0123 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 交易与风控 | CrmUI 交易订单 | `/admin-crmui/trades` | `admin_crmui::trades.index` | `admin/crmui/0123__admin-crmui__trades.png` |
| #0124 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 交易与风控 | CrmUI 风险管理 | `/admin-crmui/risk` | `admin_crmui::risk.index` | `admin/crmui/0124__admin-crmui__risk.png` |
| #0125 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 交易与风控 | CrmUI 仓位清零 | `/admin-crmui/whs-exp-zero` | `admin_crmui::whs-exp-zero.index` | `admin/crmui/0125__admin-crmui__whs-exp-zero.png` |
| #0126 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 交易与风控 | CrmUI 交易品种 | `/admin-crmui/productions` | `admin_crmui::productions.index` | `admin/crmui/0126__admin-crmui__productions.png` |
| #0127 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 返佣结算 | CrmUI 周期返佣 | `/admin-crmui/commissions` | `admin_crmui::commissions.index` | `admin/crmui/0127__admin-crmui__commissions.png` |
| #0128 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 返佣结算 | CrmUI 实时返佣 | `/admin-crmui/realtime-commissions` | `admin_crmui::realtime-commissions.index` | `admin/crmui/0128__admin-crmui__realtime-commissions.png` |
| #0129 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 返佣结算 | CrmUI 批量信用导入 | `/admin-crmui/credit-imports` | `admin_crmui::credit-imports.index` | `admin/crmui/0129__admin-crmui__credit-imports.png` |
| #0130 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 权限与系统 | CrmUI 角色管理 | `/admin-crmui/roles` | `admin_crmui::roles.index` | `admin/crmui/0130__admin-crmui__roles.png` |
| #0131 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 权限与系统 | CrmUI 权限管理 | `/admin-crmui/permissions` | `admin_crmui::permissions.index` | `admin/crmui/0131__admin-crmui__permissions.png` |
| #0132 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 权限与系统 | CrmUI 菜单管理 | `/admin-crmui/menus` | `admin_crmui::menus.index` | `admin/crmui/0132__admin-crmui__menus.png` |
| #0133 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 权限与系统 | CrmUI 数据范围 | `/admin-crmui/data-scopes` | `admin_crmui::data-scopes.index` | `admin/crmui/0133__admin-crmui__data-scopes.png` |
| #0134 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 权限与系统 | CrmUI 系统配置 | `/admin-crmui/system-configs` | `admin_crmui::system-configs.index` | `admin/crmui/0134__admin-crmui__system-configs.png` |
| #0135 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 内容与权益 | CrmUI 新闻公告管理 | `/admin-crmui/news` | `admin_crmui::news.index` | `admin/crmui/0135__admin-crmui__news.png` |
| #0136 | 后台 CrmUI | CrmUI | 后台 CrmUI 工作台 / 内容与权益 | CrmUI 礼品发货管理 | `/admin-crmui/gifts` | `admin_crmui::gifts.index` | `admin/crmui/0136__admin-crmui__gifts.png` |

## 资料说明

图片内的姓名、金额、单号和统计数字仅作页面结构展示，不代表真实生产数据。页面名称、路由和视图映射来自已解压并可读取的项目源码；对压缩包内无法解压的条目保留“待复核”标识。
