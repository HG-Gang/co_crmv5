# 测试账号清单

## 数据迁移完成后可用的测试账号

### 📋 说明

- 所有账号密码保持原系统加密
- 如需重置密码，使用以下命令：
  ```bash
  php artisan tinker
  use App\Models\UserLogin;
  use Illuminate\Support\Facades\Hash;
  $user = UserLogin::where('email', '邮箱')->first();
  $user->password = Hash::make('新密码');
  $user->save();
  ```

### 🔐 代理商账号（account_type = 1）

迁移完成后，系统会从数据库中自动提取前5个可用代理账号：

```sql
SELECT 
    ul.email,
    ul.user_id,
    ui.user_name,
    ui.level_id,
    CASE ui.level_id
        WHEN 1 THEN '一级代理'
        WHEN 2 THEN '二级代理'
        WHEN 3 THEN '三级代理'
        WHEN 4 THEN '四级代理'
        WHEN 5 THEN '五级代理'
        ELSE '未分级'
    END as level_name
FROM user_logins ul
JOIN user_infos ui ON ul.user_id = ui.user_id
WHERE ul.account_type = 1
  AND ul.is_enabled = 1
ORDER BY ui.level_id ASC, ul.user_id ASC
LIMIT 5;
```

**登录地址**: `http://your-domain.com/agent/login`

**示例账号**（实际账号以迁移命令输出为准）：
- agent001@example.com (一级代理)
- agent002@example.com (二级代理)
- agent003@example.com (三级代理)

### 👤 普通客户账号（account_type = 2）

```sql
SELECT 
    ul.email,
    ul.user_id,
    ui.user_name,
    ui.parent_id,
    parent.user_name as parent_name
FROM user_logins ul
JOIN user_infos ui ON ul.user_id = ui.user_id
LEFT JOIN user_infos parent ON ui.parent_id = parent.user_id
WHERE ul.account_type = 2
  AND ul.is_enabled = 1
ORDER BY ul.user_id ASC
LIMIT 5;
```

**登录地址**: `http://your-domain.com/customer/login`

**示例账号**（实际账号以迁移命令输出为准）：
- customer001@example.com
- customer002@example.com
- customer003@example.com

## 获取实际测试账号

### 方法一：迁移命令自动显示

执行迁移命令后，会自动显示可用测试账号：

```bash
php artisan migrate:old-data
```

### 方法二：手动查询

```bash
# 进入 tinker
php artisan tinker

# 查询代理账号
$agents = DB::table('user_logins')
    ->join('user_infos', 'user_logins.user_id', '=', 'user_infos.user_id')
    ->where('user_logins.account_type', 1)
    ->where('user_logins.is_enabled', 1)
    ->select('user_logins.email', 'user_logins.user_id', 'user_infos.user_name', 'user_infos.level_id')
    ->limit(5)
    ->get();

foreach ($agents as $agent) {
    echo "代理: {$agent->email} (ID: {$agent->user_id}, 姓名: {$agent->user_name}, 级别: {$agent->level_id})\n";
}

# 查询客户账号
$customers = DB::table('user_logins')
    ->join('user_infos', 'user_logins.user_id', '=', 'user_infos.user_id')
    ->where('user_logins.account_type', 2)
    ->where('user_logins.is_enabled', 1)
    ->select('user_logins.email', 'user_logins.user_id', 'user_infos.user_name')
    ->limit(5)
    ->get();

foreach ($customers as $customer) {
    echo "客户: {$customer->email} (ID: {$customer->user_id}, 姓名: {$customer->user_name})\n";
}
```

### 方法三：创建新测试账号

如果需要创建全新的测试账号：

```bash
php artisan tinker

use App\Models\UserLogin;
use App\Models\UserInfo;
use App\Models\UserAuth;
use Illuminate\Support\Facades\Hash;

# 创建代理账号
DB::beginTransaction();

$agentLogin = UserLogin::create([
    'user_id' => 999001,
    'email' => 'test_agent@example.com',
    'password' => Hash::make('123456'),
    'account_type' => 1,
    'is_enabled' => 1,
    'created_at' => time(),
    'updated_at' => time(),
]);

UserInfo::create([
    'user_id' => 999001,
    'login_id' => 999001,
    'user_name' => '测试代理',
    'phone' => '13800138000',
    'gender' => 1,
    'account_type' => 1,
    'level_id' => 2,
    'created_at' => time(),
    'updated_at' => time(),
]);

UserAuth::create([
    'user_id' => 999001,
    'created_at' => time(),
    'updated_at' => time(),
]);

DB::commit();

echo "测试代理账号创建成功！\n";
echo "邮箱: test_agent@example.com\n";
echo "密码: 123456\n";

# 创建客户账号
DB::beginTransaction();

$customerLogin = UserLogin::create([
    'user_id' => 999002,
    'email' => 'test_customer@example.com',
    'password' => Hash::make('123456'),
    'account_type' => 2,
    'is_enabled' => 1,
    'created_at' => time(),
    'updated_at' => time(),
]);

UserInfo::create([
    'user_id' => 999002,
    'login_id' => 999002,
    'user_name' => '测试客户',
    'phone' => '13900139000',
    'gender' => 1,
    'account_type' => 2,
    'parent_id' => 999001, # 设置上级为测试代理
    'created_at' => time(),
    'updated_at' => time(),
]);

UserAuth::create([
    'user_id' => 999002,
    'created_at' => time(),
    'updated_at' => time(),
]);

DB::commit();

echo "测试客户账号创建成功！\n";
echo "邮箱: test_customer@example.com\n";
echo "密码: 123456\n";
```

## 账号权限说明

### 代理商权限

- ✅ 查看下级代理列表
- ✅ 查看直属客户列表
- ✅ 查看客户交易数据
- ✅ 佣金转账
- ✅ 客户组别变更
- ✅ 查看返佣记录
- ✅ 查看实时返佣
- ✅ 个人资料管理

### 普通客户权限

- ✅ 查看个人账户信息
- ✅ 入金申请
- ✅ 出金申请
- ✅ 查看交易记录
- ✅ 查看持仓订单
- ✅ 查看历史订单
- ✅ 凭证上传
- ✅ 个人资料管理

## 功能测试清单

### 代理商功能测试

- [ ] 登录系统
- [ ] 查看仪表盘
- [ ] 查看下级代理列表
- [ ] 查看直属客户列表
- [ ] 查看客户详情
- [ ] 查看实时返佣
- [ ] 查看返佣历史
- [ ] 执行佣金转账
- [ ] 申请组别变更
- [ ] 修改个人资料
- [ ] 修改密码

### 普通客户功能测试

- [ ] 登录系统
- [ ] 查看仪表盘
- [ ] 查看账户信息
- [ ] 申请入金
- [ ] 申请出金
- [ ] 上传凭证
- [ ] 查看凭证审核状态
- [ ] 查看持仓订单
- [ ] 查看历史订单
- [ ] 修改个人资料
- [ ] 上传身份证
- [ ] 绑定银行卡

## 注意事项

1. **密码安全**
   - 测试环境建议统一重置为简单密码（如123456）
   - 生产环境务必保持原密码或强制用户首次登录修改

2. **数据隔离**
   - 测试账号建议使用999xxx范围的user_id
   - 避免与真实用户数据混淆

3. **权限验证**
   - 测试时注意验证代理只能查看自己的下级
   - 客户只能查看自己的数据

4. **日志监控**
   - 测试期间关注 `storage/logs/laravel.log`
   - 记录任何异常或报错信息

---

**最后更新**: 2026-06-13  
**文档版本**: v1.0
