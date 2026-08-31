# 数据迁移执行指南

## 一、准备工作

### 1. 数据库配置

编辑 `.env` 文件，确保新旧数据库配置正确：

```env
# 新数据库（co_crmv5）
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=co_crmv5
DB_USERNAME=root
DB_PASSWORD=your_password

# 旧数据库连接使用相同的mysql连接
# 在迁移脚本中会直接访问 user 和 agents 表
```

### 2. 数据库权限检查

```bash
# 登录MySQL检查权限
mysql -u root -p

# 检查新数据库
USE co_crmv5;
SHOW TABLES;

# 检查旧数据库表（假设在同一MySQL实例）
SELECT COUNT(*) FROM user WHERE voided = '1';
SELECT COUNT(*) FROM agents WHERE voided = '1';
```

## 二、执行迁移

### 方式一：使用 Artisan 命令（推荐）

```bash
# 进入项目目录
cd D:\Software\PhpProject\Demo\co_crmv5

# 模拟运行（不实际执行，查看将要迁移的数据）
php artisan migrate:old-data --dry-run

# 正式执行迁移
php artisan migrate:old-data
```

**执行流程**：
1. 检查旧数据库连接
2. 备份新表数据（如果存在）
3. 清空目标表
4. 迁移 user 表数据
5. 迁移 agents 表数据
6. 数据校验
7. 显示测试账号

### 方式二：直接执行 SQL 脚本

```bash
# 进入项目目录
cd D:\Software\PhpProject\Demo\co_crmv5

# 执行迁移SQL脚本
mysql -u root -p co_crmv5 < database/sql/migration_user_data_from_old_project.sql
```

## 三、预期执行结果

### 成功输出示例

```
========================================
   旧CRM数据迁移工具
========================================

确认开始数据迁移？此操作将清空user_logins、user_infos、user_auths表 (yes/no) [no]:
> yes

步骤1: 检查旧数据库连接...
  ✓ 旧user表记录数: 1000
  ✓ 旧agents表记录数: 500
  ✓ 预计迁移总数: 1500

步骤2: 备份新表数据...
  ✓ 备份 user_logins -> user_logins_backup_20260613120000 (0条)
  ✓ 备份 user_infos -> user_infos_backup_20260613120000 (0条)
  ✓ 备份 user_auths -> user_auths_backup_20260613120000 (0条)

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

========================================
   迁移完成！测试账号信息
========================================

【代理商测试账号】

  📧 agent001@example.com
     ID: 10001 | 姓名: 张代理 | 级别: 2
     密码: [使用原密码]

  📧 agent002@example.com
     ID: 10002 | 姓名: 李代理 | 级别: 3
     密码: [使用原密码]

  📧 agent003@example.com
     ID: 10003 | 姓名: 王代理 | 级别: 1
     密码: [使用原密码]

【普通客户测试账号】

  📧 customer001@example.com
     ID: 20001 | 姓名: 张客户
     密码: [使用原密码]

  📧 customer002@example.com
     ID: 20002 | 姓名: 李客户
     密码: [使用原密码]

  📧 customer003@example.com
     ID: 20003 | 姓名: 王客户
     密码: [使用原密码]

✅ 数据迁移成功完成！
```

## 四、数据验证

### 1. 数据量验证

```sql
-- 检查总记录数
SELECT COUNT(*) FROM co_crmv5.user_logins;   -- 应为 1500
SELECT COUNT(*) FROM co_crmv5.user_infos;    -- 应为 1500
SELECT COUNT(*) FROM co_crmv5.user_auths;    -- 应为 1500

-- 按类型统计
SELECT account_type, COUNT(*) 
FROM co_crmv5.user_logins 
GROUP BY account_type;
-- 结果应为: 1=代理(500), 2=客户(1000)
```

### 2. 邮箱唯一性验证

```sql
-- 检查重复邮箱（应为空结果）
SELECT email, COUNT(*) as count
FROM co_crmv5.user_logins
GROUP BY email
HAVING count > 1;
```

### 3. 表间一致性验证

```sql
-- 检查三张表的user_id是否一致（应为空结果）
SELECT 'logins缺失infos' as issue, ul.user_id
FROM co_crmv5.user_logins ul
LEFT JOIN co_crmv5.user_infos ui ON ul.user_id = ui.user_id
WHERE ui.user_id IS NULL

UNION ALL

SELECT 'logins缺失auths', ul.user_id
FROM co_crmv5.user_logins ul
LEFT JOIN co_crmv5.user_auths ua ON ul.user_id = ua.user_id
WHERE ua.user_id IS NULL;
```

## 五、测试登录

### 前端登录地址

**代理商登录**：
```
URL: http://localhost/agent/login
或: http://your-domain.com/agent/login
```

**普通客户登录**：
```
URL: http://localhost/customer/login
或: http://your-domain.com/customer/login
```

### 测试账号

迁移完成后，命令行会显示5个代理账号和5个客户账号供测试。

**重要**：密码保持原系统加密，使用原密码登录。

如果不知道原密码，可以手动重置：

```php
// 使用 php artisan tinker
use App\Models\UserLogin;
use Illuminate\Support\Facades\Hash;

$user = UserLogin::where('email', 'test@example.com')->first();
$user->password = Hash::make('123456');
$user->save();
```

## 六、回滚方案

### 如果迁移失败或需要重新迁移

```sql
-- 1. 从备份恢复（如果有备份）
DROP TABLE co_crmv5.user_logins;
DROP TABLE co_crmv5.user_infos;
DROP TABLE co_crmv5.user_auths;

CREATE TABLE co_crmv5.user_logins LIKE co_crmv5.user_logins_backup_20260613120000;
INSERT INTO co_crmv5.user_logins SELECT * FROM co_crmv5.user_logins_backup_20260613120000;

CREATE TABLE co_crmv5.user_infos LIKE co_crmv5.user_infos_backup_20260613120000;
INSERT INTO co_crmv5.user_infos SELECT * FROM co_crmv5.user_infos_backup_20260613120000;

CREATE TABLE co_crmv5.user_auths LIKE co_crmv5.user_auths_backup_20260613120000;
INSERT INTO co_crmv5.user_auths SELECT * FROM co_crmv5.user_auths_backup_20260613120000;

-- 2. 或者直接清空重新迁移
TRUNCATE TABLE co_crmv5.user_logins;
TRUNCATE TABLE co_crmv5.user_infos;
TRUNCATE TABLE co_crmv5.user_auths;

-- 然后重新执行迁移命令
php artisan migrate:old-data
```

## 七、常见问题

### Q1: 提示无法连接旧数据库

**解决方案**：
```bash
# 检查旧数据库是否在同一MySQL实例
mysql -u root -p -e "SHOW DATABASES;"

# 如果旧数据库在不同服务器，需要配置第二个数据库连接
# 编辑 config/database.php，添加：
'old_crm' => [
    'driver' => 'mysql',
    'host' => '旧服务器IP',
    'port' => '3306',
    'database' => 'crm_db',
    'username' => 'root',
    'password' => 'password',
],

# 然后修改命令中的 DB::connection('mysql') 为 DB::connection('old_crm')
```

### Q2: 迁移中途报错

**解决方案**：
```bash
# 查看详细错误日志
tail -f storage/logs/laravel.log

# 清空数据重新迁移
php artisan migrate:old-data
```

### Q3: 密码无法登录

**解决方案**：
```bash
# 重置特定用户密码
php artisan tinker

use App\Models\UserLogin;
use Illuminate\Support\Facades\Hash;

$user = UserLogin::where('email', 'test@example.com')->first();
$user->password = Hash::make('新密码');
$user->save();
exit
```

## 八、执行清单

- [ ] 备份旧数据库
- [ ] 检查新数据库配置
- [ ] 模拟运行迁移 `--dry-run`
- [ ] 正式执行迁移
- [ ] 验证数据量
- [ ] 验证邮箱唯一性
- [ ] 验证表间一致性
- [ ] 测试代理登录
- [ ] 测试客户登录
- [ ] 清除Laravel缓存
- [ ] 测试业务功能

## 九、迁移后操作

```bash
# 1. 清除所有缓存
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 2. 重新生成缓存
php artisan config:cache
php artisan route:cache

# 3. 重启队列（如果使用）
php artisan queue:restart

# 4. 检查应用状态
php artisan about
```

---

**注意**：
- 建议在低峰期执行迁移
- 迁移前务必备份数据
- 首次执行建议使用 `--dry-run` 模拟
- 迁移完成后立即验证数据
