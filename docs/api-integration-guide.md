# UI重写项目 - API对接文档

## 概述

本文档说明新版UI系统（admin-tailwind 和 front-coreui-v2）的API接口对接规范。所有页面使用AJAX方式与后端交互，无刷新体验。

---

## API命名规范

### 后台API路由
- **前缀**: `/api/admin/`
- **命名**: `admin_api_*`
- **认证**: 需要管理员登录态

### 前台API路由
- **前缀**: `/api/front/`
- **命名**: `front_api_*`
- **认证**: 需要用户登录态

---

## 通用请求格式

### 请求头 Headers
```javascript
{
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    'X-Requested-With': 'XMLHttpRequest'
}
```

### GET 请求示例
```javascript
fetch('/api/front/users?page=1&keyword=test', {
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
})
.then(res => res.json())
.then(data => {
    if (data.success || data.code === 200) {
        // 处理成功
    } else {
        // 处理错误
    }
});
```

### POST 请求示例
```javascript
fetch('/api/front/profile/update', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({
        name: 'John Doe',
        email: 'john@example.com'
    })
})
.then(res => res.json())
.then(data => {
    if (data.success || data.code === 200) {
        alert('更新成功');
    } else {
        alert(data.message || '更新失败');
    }
});
```

---

## 通用响应格式

### 成功响应
```json
{
    "success": true,
    "code": 200,
    "message": "操作成功",
    "data": {
        // 具体数据
    }
}
```

### 错误响应
```json
{
    "success": false,
    "code": 400,
    "message": "错误描述",
    "errors": {
        "field": ["具体错误信息"]
    }
}
```

### 分页响应
```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 20,
        "total": 200
    }
}
```

---

## 前台API接口清单

### 认证模块
- `POST /api/front/login` - 用户登录
- `POST /api/front/register` - 用户注册
- `POST /api/front/logout` - 退出登录
- `POST /api/front/forgot-password` - 忘记密码
- `POST /api/front/reset-password` - 重置密码

### 个人信息模块
- `GET /api/front/profile` - 获取个人资料
- `POST /api/front/profile/update` - 更新个人资料
- `POST /api/front/profile/change-password` - 修改密码
- `POST /api/front/profile/change-email` - 修改邮箱

### 账户管理模块
- `GET /api/front/account/info` - 获取账户信息
- `GET /api/front/account/balance` - 获取账户余额
- `GET /api/front/account/vouchers` - 获取凭证列表
- `GET /api/front/account/voucher-detail` - 获取凭证详情
- `POST /api/front/account/cancel` - 申请注销账户

### 资金操作模块
- `GET /api/front/deposit/channels` - 获取入金渠道
- `POST /api/front/deposit/submit` - 提交入金申请
- `GET /api/front/deposit/history` - 获取入金历史
- `GET /api/front/withdraw/info` - 获取出金信息
- `POST /api/front/withdraw/submit` - 提交出金申请
- `GET /api/front/withdraw/history` - 获取出金历史
- `GET /api/front/flow/list` - 获取资金流水

### 持仓订单模块
- `GET /api/front/position/summary` - 获取持仓汇总
- `GET /api/front/position/summary-detail` - 获取持仓详情
- `GET /api/front/position/comm-summary` - 获取佣金持仓汇总
- `GET /api/front/order/open` - 获取开仓订单
- `GET /api/front/order/open-detail` - 获取开仓订单详情
- `GET /api/front/order/closed` - 获取平仓订单
- `GET /api/front/order/closed-detail` - 获取平仓订单详情

### 代理管理模块
- `GET /api/front/agent/sub-agents` - 获取下级代理列表
- `GET /api/front/agent/customers` - 获取客户列表
- `GET /api/front/agent/customer-detail` - 获取客户详情
- `POST /api/front/agent/confirm-level` - 确认代理等级
- `GET /api/front/agent/group-changes` - 获取组变更列表
- `GET /api/front/agent/group-change-detail` - 获取组变更详情
- `POST /api/front/agent/group-change-submit` - 提交组变更申请

### 佣金管理模块
- `GET /api/front/commission/realtime` - 获取实时返佣列表
- `GET /api/front/commission/realtime-detail` - 获取返佣详情
- `GET /api/front/commission/history` - 获取历史返佣
- `GET /api/front/commission/balance` - 获取佣金余额
- `GET /api/front/commission/transfer-targets` - 获取转账目标
- `POST /api/front/commission/transfer-submit` - 提交转账申请
- `GET /api/front/commission/transfer-history` - 获取转账历史

### 礼品管理模块
- `GET /api/front/gift/addresses` - 获取收货地址列表
- `GET /api/front/gift/address-detail` - 获取地址详情
- `POST /api/front/gift/address-add` - 添加收货地址
- `POST /api/front/gift/address-update` - 更新收货地址
- `POST /api/front/gift/address-delete` - 删除收货地址
- `POST /api/front/gift/address-set-default` - 设置默认地址
- `GET /api/front/gift/list` - 获取礼品列表
- `GET /api/front/gift/user-points` - 获取用户积分
- `POST /api/front/gift/exchange` - 兑换礼品

### 新闻模块
- `GET /api/front/news/list` - 获取新闻列表
- `GET /api/front/news/detail` - 获取新闻详情
- `GET /api/front/news/important` - 获取重要新闻
- `GET /api/front/news/related` - 获取相关新闻

---

## 后台API接口清单

### 认证模块
- `POST /api/admin/login` - 管理员登录
- `POST /api/admin/logout` - 退出登录

### 用户管理模块
- `GET /api/admin/users` - 获取用户列表
- `GET /api/admin/users/{id}` - 获取用户详情
- `POST /api/admin/users/create` - 创建用户
- `POST /api/admin/users/update` - 更新用户
- `POST /api/admin/users/delete` - 删除用户
- `POST /api/admin/users/status` - 更改用户状态

### 权限管理模块
- `GET /api/admin/roles` - 获取角色列表
- `POST /api/admin/roles/save` - 保存角色
- `GET /api/admin/permissions` - 获取权限列表
- `POST /api/admin/permissions/save` - 保存权限
- `GET /api/admin/menus` - 获取菜单列表
- `POST /api/admin/menus/save` - 保存菜单
- `GET /api/admin/data-scopes` - 获取数据范围
- `GET /api/admin/admins` - 获取管理员列表

### 代理管理模块
- `GET /api/admin/agents` - 获取代理列表
- `GET /api/admin/agents/detail` - 获取代理详情
- `GET /api/admin/big-agents` - 获取大代理列表
- `GET /api/admin/agent-levels` - 获取代理等级配置
- `POST /api/admin/agent-levels/save` - 保存代理等级

### 资金管理模块
- `GET /api/admin/deposits` - 获取入金列表
- `POST /api/admin/deposits/approve` - 审核入金
- `GET /api/admin/deposit-imports` - 获取入金导入记录
- `GET /api/admin/withdrawals` - 获取出金列表
- `GET /api/admin/withdrawals/pending` - 获取待处理出金
- `GET /api/admin/withdrawals/processing` - 获取处理中出金
- `GET /api/admin/withdrawals/completed` - 获取已完成出金
- `GET /api/admin/withdrawals/failed` - 获取失败出金
- `POST /api/admin/withdrawals/approve` - 审核出金
- `POST /api/admin/withdrawals/reject` - 拒绝出金
- `GET /api/admin/withdraw-flows` - 获取出金流水
- `GET /api/admin/undeposit-flows` - 获取未入金流水
- `GET /api/admin/vouchers` - 获取凭证列表
- `POST /api/admin/vouchers/review` - 审核凭证

### 报表统计模块
- `GET /api/admin/reports/rights-summary` - 获取权益汇总
- `GET /api/admin/reports/position-summary` - 获取持仓汇总
- `GET /api/admin/reports/commissions` - 获取佣金报表
- `GET /api/admin/reports/realtime-commissions` - 获取实时返佣报表
- `GET /api/admin/reports/trades` - 获取交易记录
- `POST /api/admin/reports/export` - 导出报表

### 系统管理模块
- `GET /api/admin/system/configs` - 获取系统配置
- `POST /api/admin/system/configs/save` - 保存系统配置
- `GET /api/admin/system/group-configs` - 获取组配置
- `POST /api/admin/system/group-configs/save` - 保存组配置
- `GET /api/admin/system/exchange-rates` - 获取汇率列表
- `POST /api/admin/system/exchange-rates/save` - 保存汇率
- `GET /api/admin/system/channels` - 获取渠道列表
- `POST /api/admin/system/channels/save` - 保存渠道
- `GET /api/admin/system/productions` - 获取产品列表
- `POST /api/admin/system/productions/save` - 保存产品
- `GET /api/admin/system/gifts` - 获取礼品列表
- `POST /api/admin/system/gifts/save` - 保存礼品
- `GET /api/admin/system/news` - 获取新闻列表
- `POST /api/admin/system/news/save` - 保存新闻
- `GET /api/admin/system/online-users` - 获取在线用户

### 风控管理模块
- `GET /api/admin/risk/list` - 获取风控列表
- `GET /api/admin/risk/blacklist` - 获取黑名单
- `POST /api/admin/risk/blacklist/add` - 添加黑名单
- `GET /api/admin/risk/authentications` - 获取身份认证列表
- `POST /api/admin/risk/authentications/review` - 审核身份认证
- `GET /api/admin/risk/cancel-applies` - 获取注销申请列表
- `POST /api/admin/risk/cancel-applies/review` - 审核注销申请

---

## 错误码说明

| 错误码 | 说明 |
|-------|------|
| 200 | 成功 |
| 400 | 请求参数错误 |
| 401 | 未认证 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 422 | 表单验证失败 |
| 500 | 服务器内部错误 |

---

## 开发建议

1. **统一错误处理**: 在全局添加fetch拦截器统一处理错误
2. **加载状态管理**: 提交前禁用按钮，完成后恢复
3. **表单验证**: 前端先进行基础验证，减少无效请求
4. **数据缓存**: 适当使用LocalStorage缓存静态数据
5. **防重复提交**: 使用防抖或节流控制提交频率

---

## 示例：完整表单提交流程

```javascript
function submitForm() {
    // 1. 获取表单数据
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();

    // 2. 前端验证
    if (!name) {
        alert('请输入姓名');
        return;
    }
    if (!email) {
        alert('请输入邮箱');
        return;
    }

    // 3. 禁用提交按钮
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = '提交中...';

    // 4. 发送请求
    fetch('/api/front/profile/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ name, email })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('更新成功');
            // 可选：跳转或刷新
            // window.location.reload();
        } else {
            alert(data.message || '更新失败');
        }
    })
    .catch(err => {
        console.error('Request error:', err);
        alert('网络错误，请稍后重试');
    })
    .finally(() => {
        // 5. 恢复按钮状态
        submitBtn.disabled = false;
        submitBtn.textContent = '提交';
    });
}
```
