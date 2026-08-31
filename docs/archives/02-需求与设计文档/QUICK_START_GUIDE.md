# 🚀 数据迁移最终执行指南

## ✅ 已完成功能

1. **自动迁移旧数据库**
2. **自动重置所有密码为 123456**
3. **自动显示测试账号**

## 📋 执行步骤

### 步骤1: 执行数据迁移

```bash
# 进入项目目录
cd D:\Software\PhpProject\Demo\co_crmv5

# 执行迁移（会自动重置密码为123456）
php artisan migrate:old-data
```

### 步骤2: 查看执行结果

命令执行完成后，会显示如下信息：

```
========================================
   旧CRM数据迁移工具
========================================

步骤1: 检查旧数据库连接...
  ✓ 旧user表记录数: 1000
  ✓ 旧agents表记录数: 500
  ✓ 预计迁移总数: 1500

步骤2: 备份新表数据...
  ✓ 备份完成

步骤3: 清空目标表...
  ✓ 目标表已清空

步骤4: 迁移user表数据...
1000/1000 [============================] 100%
  ✓ 成功: 1000, 失败: 0

步骤5: 迁移agents表数据...
500/500 [============================] 100%
  ✓ 成功: 500, 失败: 0

步骤6: 数据校验...
  ✓ user_logins: 1500
  ✓ user_infos: 1500
  ✓ user_auths: 1500
  ✓ 代理数: 500
  ✓ 客户数: 1000

步骤7: 重置所有密码为 123456...
  ✓ 成功重置 1500 个账号的密码为: 123456

========================================
   迁移完成！测试账号信息
========================================

【代理商测试账号】

  📧 agent001@example.com
     ID: 10001 | 姓名: 张代理 | 级别: 2
     密码: 123456

  📧 agent002@example.com
     ID: 10002 | 姓名: 李代理 | 级别: 3
     密码: 123456

  📧 agent003@example.com
     ID: 10003 | 姓名: 王代理 | 级别: 1
     密码: 123456

【普通客户测试账号】

  📧 customer001@example.com
     ID: 20001 | 姓名: 张客户
     密码: 123456

  📧 customer002@example.com
     ID: 20002 | 姓名: 李客户
     密码: 123456

  📧 customer003@example.com
     ID: 20003 | 姓名: 王客户
     密码: 123456

========================================
统一密码: 123456
登录地址:
  代理: http://localhost/agent/login
  客户: http://localhost/customer/login
========================================

✅ 数据迁移成功完成！
```

## 🔐 登录信息

### 统一密码

**所有账号密码**: `123456`

### 登录地址

**代理商登录**:
```
http://localhost/agent/login
或
http://your-domain.com/agent/login
```

**普通客户登录**:
```
http://localhost/customer/login
或
http://your-domain.com/customer/login
```

## 🎯 测试账号

迁移完成后，命令会自动显示：
- **5个代理账号**（不同级别）
- **5个客户账号**

所有账号密码统一为：**123456**

## 🔧 其他命令

### 单独重置密码（如果需要）

```bash
# 重置所有密码为123456（默认）
php artisan password:reset-all

# 重置为其他密码
php artisan password:reset-all 888888
```

### 清除缓存

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 重新生成缓存

```bash
php artisan config:cache
php artisan route:cache
```

## ⚠️ 注意事项

1. **备份数据**
   - 迁移前会自动备份新表数据
   - 备份表命名格式: `user_logins_backup_YmdHis`

2. **密码安全**
   - 测试环境使用统一密码123456
   - 生产环境请使用更强密码

3. **数据验证**
   - 迁移完成后会自动校验数据完整性
   - 检查邮箱唯一性
   - 检查表间一致性

## 📊 数据验证SQL

如需手动验证数据：

```sql
-- 1. 检查数据量
SELECT COUNT(*) FROM user_logins;   -- 应等于 旧user数+旧agents数
SELECT COUNT(*) FROM user_infos;    -- 应等于 旧user数+旧agents数
SELECT COUNT(*) FROM user_auths;    -- 应等于 旧user数+旧agents数

-- 2. 按类型统计
SELECT account_type, COUNT(*) 
FROM user_logins 
GROUP BY account_type;
-- 结果: 1=代理, 2=客户

-- 3. 检查重复邮箱（应为空）
SELECT email, COUNT(*) as count
FROM user_logins
GROUP BY email
HAVING count > 1;

-- 4. 随机抽取账号测试
SELECT ul.email, ul.user_id, ui.user_name, ul.account_type
FROM user_logins ul
JOIN user_infos ui ON ul.user_id = ui.user_id
ORDER BY RAND()
LIMIT 10;
```

## 🎉 完成！

执行 `php artisan migrate:old-data` 后：

✅ 数据已迁移  
✅ 密码已重置为 123456  
✅ 测试账号已显示  
✅ 可以立即登录测试

---

**文档更新**: 2026-06-13  
**版本**: v2.0（包含自动密码重置）
