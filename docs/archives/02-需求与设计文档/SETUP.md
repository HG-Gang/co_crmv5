# CRM V5 Setup Guide

## 数据库配置
1. 创建数据库：`CREATE DATABASE co_crmv5 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. 修改 .env 中的数据库配置

## 初始化命令（按顺序执行）
```bash
# 生成 APP_KEY（已执行）
php artisan key:generate

# 运行迁移
php artisan migrate

# 填充初始数据（管理员账号等）
php artisan db:seed --class=InitialDataSeeder

# 创建存储软链接（需要管理员权限或在 Linux 执行）
php artisan storage:link
```

## 访问地址
- 前台用户: http://localhost/user/login
- 后台管理: http://localhost/admin/login

## 默认管理员账号
- Email: admin@crmv5.com
- Password: Admin@123456

## 代理商/用户ID规则
- 代理商: 从 1001 开始（由 agent_id_sequence 表控制）
- 普通客户: 从 600001 开始（user_login 表 AUTO_INCREMENT=600001）

## 关系链路(family_tree)示例
- 代理商链路: 1001,1002,1003,1004,1005,1006
- 含普通客户: 1001,1002,1003,1004,1005,1006,600001

## 技术栈
- Laravel 8.x + PHP 7.4+
- MySQL 8.0
- layui 2.9.16 (CDN)
- Naive UI Admin 风格 CSS（自实现）
- 多语言: zh_CN / en
- 多主题: 亮色 / 暗色
