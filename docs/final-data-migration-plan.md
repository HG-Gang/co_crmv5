# 最终全量数据迁移计划

> **执行时间**：所有模块逻辑和UI验证通过后
> **目标**：将旧库 hank_zl_data 数据按新项目逻辑迁移到 co_crmv5

---

## 一、迁移前准备

### 1.1 数据库连接信息
```
旧库：hank_zl_data@127.0.0.1:3307, root, 123456
新库：co_crmv5@127.0.0.1:3307, root, 123456
```

### 1.2 全量重置新库
```sql
-- 清空所有数据表并重置自增
SET FOREIGN_KEY_CHECKS = 0;

-- 用户相关表
TRUNCATE TABLE user_logins;
TRUNCATE TABLE user_infos;
TRUNCATE TABLE user_login_logs;
ALTER TABLE user_logins AUTO_INCREMENT = 1;
ALTER TABLE user_infos AUTO_INCREMENT = 1;

-- 交易相关表
TRUNCATE TABLE mt4_trades;
ALTER TABLE mt4_trades AUTO_INCREMENT = 1;

-- 资金相关表
TRUNCATE TABLE deposit_records;
TRUNCATE TABLE withdraw_records;
ALTER TABLE deposit_records AUTO_INCREMENT = 1;
ALTER TABLE withdraw_records AUTO_INCREMENT = 1;

-- 其他业务表（待补充完整清单）
-- ...

SET FOREIGN_KEY_CHECKS = 1;
```

---

## 二、数据迁移顺序（严格按依赖关系）

### 阶段1：基础配置数据
1. ✅ 系统配置（system_configs）
2. ✅ 菜单权限（menus, permissions, roles）
3. ✅ 代理级别（agent_levels）
4. ✅ 组别配置（group_configs）
5. ✅ 支付通道（payment_channels）
6. ✅ 汇率配置（exchange_rates）

### 阶段2：用户体系（核心）
7. **管理员**（admins）
8. **代理商**（user_logins + user_infos，user_id从1001开始）
   - 特别注意：family_tree 层级关系必须正确
   - 密码统一重置为：123456
9. **普通客户**（user_logins + user_infos，user_id从600001开始）
   - 密码统一重置为：123456
10. **代理层级闭包表**（agent_hierarchies）
    - 根据新的 family_tree 重新构建

### 阶段3：业务数据
11. **MT4交易记录**（mt4_trades）
    - 872,140条交易记录
    - cmd=6 comment 数据已修复（617,352条）
12. **入金记录**（deposit_records）
13. **出金记录**（withdraw_records）
    - 1,197条记录，口径已验证一致
14. **返佣记录**（按新逻辑重新计算）
15. **实名认证**（authentications）
16. **账户流水**（fund_flows）

### 阶段4：运营数据
17. **新闻公告**（news_infos）
18. **礼品地址**（user_addresses, gift_shipments）
19. **黑名单**（blacklists）
20. **注销申请**（cancel_applies）

---

## 三、关键数据转换规则

### 3.1 用户ID映射规则
```
代理商 ID 起始值：1001
普通客户 ID 起始值：600001

旧user表 -> 新user_logins + user_infos
- 代理商：agents表 -> user_logins(id>=1001) + user_infos
- 客户：user表(非代理) -> user_logins(id>=600001) + user_infos
```

### 3.2 密码重置规则
```php
// 所有用户密码统一重置为 123456
$password = Hash::make('123456');
```

### 3.3 层级关系映射（family_tree）
```
旧项目：agents.family_tree (例如：1001,1002,1003,1004)
新项目：user_infos.family_tree (格式保持一致)

必须确保：
1. 根代理的 family_tree = 自己的ID
2. 子代理的 family_tree = 父级完整链路 + 自己的ID
3. 普通客户的 family_tree = 上级代理完整链路 + 自己的ID
```

### 3.4 金额字段口径
```
入金金额：act_apply_amount (USD) -> actual_amount (USD)
出金金额：act_apply_amount (USD) -> actual_amount (USD)
汇率字段：必须匹配对应的 exchange_rates 表记录
```

---

## 四、迁移验证检查点

### 4.1 数据完整性验证
- [ ] 用户总数：代理 + 客户 = 旧库user + agents总数
- [ ] 交易记录总数：872,140条
- [ ] 入金总金额：与旧库一致
- [ ] 出金总金额：3,223,982.77 USD（已验证口径）
- [ ] 层级关系：所有用户的 family_tree 可追溯到根代理

### 4.2 业务逻辑验证
- [ ] 代理层级闭包表数据正确（17,669条记录）
- [ ] 返佣计算结果与旧项目一致
- [ ] 所有统计汇总数据与旧项目一致
- [ ] 权限配置正确（菜单、按钮权限）

### 4.3 登录测试
- [ ] 代理商登录测试（ID: 1001, 密码: 123456）
- [ ] 普通客户登录测试（ID: 600001, 密码: 123456）
- [ ] 管理员登录测试
- [ ] 大代理登录测试

### 4.4 页面功能测试
- [ ] Dashboard 数据展示正确
- [ ] 入金/出金流程正常
- [ ] 持仓订单/历史订单显示正确
- [ ] 实时返佣数据正确
- [ ] 代理管理功能正常

---

## 五、迁移执行脚本

### 5.1 创建迁移命令
```bash
php artisan make:command FinalDataMigration
```

### 5.2 迁移步骤（伪代码）
```php
// app/Console/Commands/FinalDataMigration.php

public function handle()
{
    DB::beginTransaction();
    try {
        $this->info('开始全量数据迁移...');
        
        // 阶段1：基础配置
        $this->migrateSystemConfigs();
        $this->migrateMenusAndPermissions();
        $this->migrateAgentLevels();
        
        // 阶段2：用户体系
        $this->migrateAdmins();
        $this->migrateAgents(); // ID从1001开始
        $this->migrateCustomers(); // ID从600001开始
        $this->rebuildAgentHierarchies();
        
        // 阶段3：业务数据
        $this->migrateMt4Trades();
        $this->migrateDepositRecords();
        $this->migrateWithdrawRecords();
        $this->migrateCommissions();
        
        // 阶段4：运营数据
        $this->migrateNewsAndGifts();
        
        DB::commit();
        $this->info('✅ 数据迁移完成！');
        
        // 执行验证
        $this->verify();
        
    } catch (\Exception $e) {
        DB::rollBack();
        $this->error('❌ 迁移失败：' . $e->getMessage());
    }
}
```

---

## 六、迁移后验收清单

### 6.1 数据验收
- [ ] 运行全量测试：4,377 tests 全部通过
- [ ] 数据口径对比脚本：新旧库统计数据100%一致
- [ ] 层级关系验证：所有用户链路可追溯

### 6.2 UI验收
- [ ] 四套UI × 多主题 × 多视口（PC/iPad/手机）全部完美
- [ ] 所有页面响应式布局正常
- [ ] 所有交互功能正常

### 6.3 性能验收
- [ ] 实时返佣模块不卡顿
- [ ] Dashboard 加载速度 < 2秒
- [ ] 大数据量表格分页流畅

---

## 七、回滚方案

如果迁移失败，执行回滚：
```bash
# 1. 恢复数据库备份
mysql -h 127.0.0.1 -P 3307 -u root -p123456 co_crmv5 < backup_before_migration.sql

# 2. 清理缓存
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 3. 重新运行测试验证
php artisan test
```

---

**状态**：📋 计划已制定，等待所有模块验证通过后执行
**负责人**：主会话
**创建时间**：2026-09-03
