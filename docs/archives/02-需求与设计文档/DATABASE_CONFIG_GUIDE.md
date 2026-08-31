# 数据库配置指南

## 问题说明

执行 `php artisan migrate:old-data` 时报错：
```
Table 'co_crmv5.user' doesn't exist
```

**原因**: 旧数据库表不在新数据库中，需要配置旧数据库连接。

## 解决方案

### 步骤1: 编辑 .env 文件

在 `.env` 文件中添加旧数据库配置：

```env
# 新数据库配置（保持不变）
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=co_crmv5
DB_USERNAME=root
DB_PASSWORD=123456

# 旧CRM数据库配置（新增以下内容）
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3307
OLD_DB_DATABASE=旧数据库名称
OLD_DB_USERNAME=root
OLD_DB_PASSWORD=123456
```

### 步骤2: 确认旧数据库信息

需要确认以下信息：
1. **旧数据库名称** - 包含 `user` 和 `agents` 表的数据库
2. **数据库地址** - 通常是 127.0.0.1 或 localhost
3. **端口号** - 通常是 3306 或 3307
4. **用户名和密码**

### 步骤3: 查找旧数据库

如果不确定旧数据库名称，可以通过以下方式查找：

```bash
# 方式1: 登录MySQL查看所有数据库
mysql -u root -p -P 3307
SHOW DATABASES;

# 方式2: 查找包含user表的数据库
mysql -u root -p -P 3307 -e "
SELECT table_schema 
FROM information_schema.tables 
WHERE table_name = 'user' 
  AND table_schema NOT IN ('mysql', 'information_schema', 'performance_schema', 'sys');"
```

### 步骤4: 常见旧数据库名称

根据你的项目，旧数据库可能是：
- `new_co_gmtk_crmV3`
- `crm_db`
- `gmtkcrm`
- `zlbtcrm`
- 其他CRM相关名称

## 配置示例

### 示例1: 旧数据库在同一服务器

```env
# .env 文件配置
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=co_crmv5
DB_USERNAME=root
DB_PASSWORD=123456

# 旧数据库配置
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3307
OLD_DB_DATABASE=new_co_gmtk_crmV3
OLD_DB_USERNAME=root
OLD_DB_PASSWORD=123456
```

### 示例2: 旧数据库在不同服务器

```env
# 新数据库
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=co_crmv5
DB_USERNAME=root
DB_PASSWORD=123456

# 旧数据库（不同服务器）
OLD_DB_HOST=192.168.1.100
OLD_DB_PORT=3306
OLD_DB_DATABASE=old_crm_db
OLD_DB_USERNAME=old_user
OLD_DB_PASSWORD=old_password
```

## 验证配置

配置完成后，验证连接是否正常：

```bash
# 清除配置缓存
php artisan config:clear

# 测试旧数据库连接
php artisan tinker

# 在tinker中执行
DB::connection('old_crm')->table('user')->where('voided', '1')->count();
DB::connection('old_crm')->table('agents')->where('voided', '1')->count();

# 如果返回数字则连接成功，按 Ctrl+C 退出
```

## 执行迁移

配置正确后，再次执行迁移命令：

```bash
php artisan migrate:old-data
```

## 常见问题

### Q1: 如何找到旧数据库？

**方案1**: 检查旧项目配置文件
```bash
# 查看旧项目的数据库配置
cat D:\Php-project\Php\new_co_gmtk_crmV3\.env
cat D:\Php-project\Php\new_co_gmtk_crmV3\config\database.php
```

**方案2**: 使用phpMyAdmin或Navicat等工具查看

**方案3**: 命令行查找
```bash
mysql -u root -p -P 3307 -e "
SELECT 
    table_schema as database_name,
    COUNT(*) as table_count
FROM information_schema.tables 
WHERE table_schema NOT IN ('mysql', 'information_schema', 'performance_schema', 'sys')
GROUP BY table_schema
ORDER BY table_count DESC;"
```

### Q2: 端口号是多少？

默认MySQL端口：
- 标准端口: `3306`
- 你的配置中使用: `3307`

可以通过以下方式确认：
```bash
# 查看MySQL进程
netstat -ano | findstr :3306
netstat -ano | findstr :3307

# 或查看MySQL配置
type "C:\xampp\mysql\bin\my.ini" | findstr port
```

### Q3: 连接被拒绝怎么办？

```bash
# 1. 确认MySQL服务运行
mysql -u root -p -P 3307 -e "SELECT 1;"

# 2. 检查防火墙设置

# 3. 检查用户权限
mysql -u root -p -P 3307 -e "
SELECT user, host FROM mysql.user WHERE user='root';"
```

### Q4: 数据库不在本地怎么办？

如果旧数据库在远程服务器：
1. 配置 `OLD_DB_HOST` 为远程IP
2. 确保远程MySQL允许外部连接
3. 配置防火墙允许3306/3307端口

## 完整执行流程

```bash
# 1. 编辑 .env 文件，添加旧数据库配置
notepad .env

# 2. 清除配置缓存
php artisan config:clear

# 3. 测试连接
php artisan tinker
DB::connection('old_crm')->getPdo();
exit

# 4. 执行迁移
php artisan migrate:old-data

# 5. 验证数据
php artisan tinker
DB::table('user_logins')->count();
exit
```

## 需要提供的信息

请确认以下信息后再执行迁移：

- [ ] 旧数据库名称: _______________
- [ ] 数据库地址: _______________
- [ ] 端口号: _______________
- [ ] 用户名: _______________
- [ ] 密码: _______________

---

**更新时间**: 2026-06-13  
**版本**: v1.0
