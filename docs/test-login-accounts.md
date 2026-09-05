# 测试登录账号

本文档列出所有前后端测试登录账号，用于开发和验收。

## 后台管理端 (Admin Backend)

### 访问地址
- **Layui 版本**: `/admin/login`
- **Tailwind 版本**: `/admin-tailwind/login`

### 测试账号

| 账号类型 | 用户名 | 密码 | 权限级别 | 备注 |
|---------|--------|------|---------|------|
| 超级管理员 | admin | admin123 | 全部权限 | 系统管理员 |
| 普通管理员 | manager | manager123 | 部分权限 | 业务管理员 |
| 客服专员 | service | service123 | 有限权限 | 客服人员 |

---

## 前台用户端 (Frontend User)

### 访问地址
- **CoreUI v2 版本**: `/front-coreui-v2/login`

### 测试账号（实际数据库）

| 账号类型 | 邮箱 | 密码 | 用户ID | 账户余额 | 备注 |
|---------|------|------|--------|---------|------|
| 顶级代理 | info@gmtkg.com | abc123 | 10 | $0.00 | 演示邀请代理 |
| 根代理 | agent@test.com | abc123 | 1001 | $88,000.00 | 根代理 |
| 子代理A | subagent1@test.com | abc123 | 1101 | $42,000.00 | 子代理 |
| 子代理B | subagent2@test.com | abc123 | 1102 | $39,000.00 | 子代理 |
| 客户1 | customer1@test.com | abc123 | 600101 | $13,200.00 | 普通客户 |
| 客户2 | customer2@test.com | abc123 | 600102 | $8,600.00 | ECN客户 |
| 客户3 | customer3@test.com | abc123 | 600103 | $21,500.00 | 普通客户 |

**说明**: 所有演示账号统一密码为 `abc123`，数据来源于 `database/seeders/FrontDemoDataSeeder.php`

---

## 数据库配置

### 新库 (mysql)
- **Host**: localhost
- **Database**: `co_crmv5`
- **Username**: root
- **Password**: （见 `.env` 文件）

### 旧库 (old_crm) - 仅用于数据迁移
- **Host**: localhost
- **Database**: `old_crm`
- **Username**: root
- **Password**: （见 `.env` 文件 `OLD_DB_PASSWORD`）

---

## 测试 API 端点

### 后台 API
```
GET  /admin/api/users/stats        # 用户统计
GET  /admin/api/users/list         # 用户列表
POST /admin/api/users/create       # 创建用户
PUT  /admin/api/users/{id}/update  # 更新用户
```

### 前台 API
```
GET  /front/api/account/list       # MT4账户列表
GET  /front/api/deposit/stats      # 入金统计
POST /front/api/deposit/submit     # 提交入金申请
GET  /front/api/deposit/recent     # 最近入金记录
```

---

## 快速测试流程

### 1. 后台管理员登录测试
```bash
# 访问
http://localhost/admin-tailwind/login

# 输入
Username: admin
Password: admin123
```

### 2. 前台用户登录测试
```bash
# 访问
http://localhost/front-coreui-v2/login

# 输入
Email: test@user.com
Password: user123456
```

### 3. 数据迁移验证
```bash
# 检查配置数据
php artisan migrate:supplement-configs --verify-only

# 查看迁移统计
php scripts/check-migration-gaps.php
```

---

## 注意事项

1. **密码强度**: 测试账号密码为弱密码，仅用于开发环境，生产环境必须强制修改
2. **数据隔离**: 测试数据与生产数据完全隔离，使用独立数据库
3. **权限测试**: 不同角色账号用于测试不同权限级别的功能访问
4. **定期重置**: 测试账号数据每周重置一次，避免脏数据积累

---

## 更新日志

| 日期 | 变更内容 | 操作人 |
|------|---------|--------|
| 2026-09-04 | 创建测试账号文档，列出前后端登录账号 | System |
| 2026-09-04 | 完成 group_configs 数据迁移（27条） | System |
| 2026-09-04 | UI 深度优化：移动端侧边栏、表格堆叠、触摸目标 | System |

---

**最后更新**: 2026-09-04
**文档版本**: v1.0.0
