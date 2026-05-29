SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS co_crmv5 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE co_crmv5;

DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `role_id` VARCHAR(60) COMMENT '角色ID',
  `mobile` CHAR(20) COMMENT '手机号',
  `email` VARCHAR(100) COMMENT '邮箱',
  `username` VARCHAR(100) NOT NULL COMMENT '用户名',
  `password` VARCHAR(100) NOT NULL COMMENT '密码',
  `login_count` INT NOT NULL DEFAULT 0 COMMENT '登录次数',
  `last_login_ip` VARCHAR(50) COMMENT '最后登录IP',
  `last_login_at` DATETIME COMMENT '最后登录时间',
  `last_login_address` VARCHAR(200) COMMENT '最后登录地址',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 1=启用 0=禁用',
  `jwt_token_id` VARCHAR(100) COMMENT 'SSO: 当前JWT ID',
  `created_by` VARCHAR(50) COMMENT '创建人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `admins_username_index` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admin_login_logs`;
CREATE TABLE `admin_login_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `admin_id` BIGINT NOT NULL COMMENT '管理员ID',
  `login_ip` VARCHAR(50) NOT NULL COMMENT '登录IP',
  `ip_address` VARCHAR(200) COMMENT 'IP地理位置',
  `user_agent` VARCHAR(500) COMMENT '用户代理',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `admin_login_logs_admin_id_index` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` VARCHAR(100) NOT NULL COMMENT '角色名称',
  `guard_type` VARCHAR(20) NOT NULL COMMENT '守卫类型: admin or front',
  `description` TEXT COMMENT '描述',
  `permissions` JSON COMMENT '权限 slugs 数组',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 1=启用 0=禁用',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '',
  `name` VARCHAR(100) NOT NULL COMMENT '名称',
  `slug` VARCHAR(150) NOT NULL COMMENT '标识符',
  `guard_type` VARCHAR(20) NOT NULL DEFAULT 'admin' COMMENT '守卫类型: admin/front',
  `parent_id` INT NOT NULL DEFAULT 0 COMMENT '父ID',
  `type` TINYINT NOT NULL DEFAULT 1 COMMENT '类型: 1=菜单 2=页面 3=按钮',
  `icon` VARCHAR(100) COMMENT '图标',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `route` VARCHAR(200) COMMENT '前端路由路径',
  `api_route` VARCHAR(200) COMMENT '后端API路由名称',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`),
  INDEX `permissions_slug_index` (`slug`),
  INDEX `permissions_guard_type_index` (`guard_type`),
  INDEX `permissions_parent_id_index` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `role_id` BIGINT NOT NULL COMMENT '角色ID',
  `permission_id` BIGINT NOT NULL COMMENT '权限ID',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE `role_permissions_role_id_permission_id_unique` (`role_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_logins`;
CREATE TABLE `user_logins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '业务用户ID (来自id_sequences)',
  `email` VARCHAR(255) NOT NULL COMMENT '邮箱',
  `password` VARCHAR(255) NOT NULL COMMENT '密码',
  `account_type` TINYINT NOT NULL COMMENT '账户类型: 1=代理, 2=客户',
  `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用',
  `is_cancelled` TINYINT NOT NULL DEFAULT 0 COMMENT '是否注销',
  `source_type` TINYINT NOT NULL DEFAULT 0 COMMENT '来源: 0=系统, 1=导入',
  `jwt_token_id` VARCHAR(100) COMMENT 'SSO: 当前JWT ID',
  `last_login_ip` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '最后登录IP',
  `last_login_at` DATETIME COMMENT '最后登录时间',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE `user_logins_email_unique` (`email`),
  INDEX `user_logins_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_infos`;
CREATE TABLE `user_infos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` BIGINT NOT NULL COMMENT '用户ID',
  `login_id` INT NOT NULL COMMENT '登录ID',
  `user_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '用户名',
  `phone` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '电话',
  `gender` TINYINT NOT NULL DEFAULT 1 COMMENT '性别',
  `avatar` VARCHAR(500) COMMENT '头像',
  `level_id` INT NOT NULL DEFAULT 0 COMMENT '级别ID',
  `group_id` INT NOT NULL DEFAULT 0 COMMENT '分组ID',
  `parent_id` INT NOT NULL DEFAULT 0 COMMENT '父ID',
  `account_type` TINYINT NOT NULL DEFAULT 1 COMMENT '账户类型',
  `family_tree` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '家谱树: 逗号分隔祖先链',
  `total_funds` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '总资金',
  `used_margin` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '已用保证金',
  `avail_margin` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '可用保证金',
  `equity` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '净值',
  `effective_credit` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '有效信用额',
  `risk_ratio` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '风险率',
  `margin_amount` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '保证金金额',
  `leverage` INT NOT NULL DEFAULT 0 COMMENT '杠杆',
  `cust_vol` VARCHAR(255) NOT NULL DEFAULT '0' COMMENT '客户交易量',
  `pay_provider_id` INT NOT NULL DEFAULT 0 COMMENT '支付提供商ID',
  `equity_ratio` INT NOT NULL DEFAULT 0 COMMENT '净值比例',
  `comm_rate` INT NOT NULL DEFAULT 0 COMMENT '佣金率',
  `is_ecn` TINYINT NOT NULL DEFAULT 0 COMMENT '是否ECN',
  `follow_parent_ecn` TINYINT NOT NULL DEFAULT 0 COMMENT '跟随父级ECN',
  `auth_status` TINYINT NOT NULL DEFAULT 0 COMMENT '认证状态: 0=未验证 1=已验证 2=已退回 3=已禁用',
  `is_mt4_synced` TINYINT NOT NULL DEFAULT 0 COMMENT '是否同步MT4',
  `is_mt4_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT 'MT4是否启用',
  `is_mt4_readonly` TINYINT NOT NULL DEFAULT 0 COMMENT 'MT4是否只读',
  `is_withdrawal_allowed` TINYINT NOT NULL DEFAULT 0 COMMENT '允许提现: 0=是 1=否',
  `is_deposit_allowed` TINYINT NOT NULL DEFAULT 0 COMMENT '允许充值: 0=是 1=否',
  `is_agent_confirmed` TINYINT NOT NULL DEFAULT 0 COMMENT '代理确认',
  `original_group` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '原分组',
  `mt4_group` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'MT4分组',
  `mt4_code` INT NOT NULL DEFAULT 0 COMMENT 'MT4代码',
  `trading_mode` TINYINT NOT NULL DEFAULT 0 COMMENT '交易模式: 0=佣金 1=净值',
  `settle_method` TINYINT NOT NULL DEFAULT 1 COMMENT '结算方式: 1=线上 2=线下',
  `settle_cycle` TINYINT NOT NULL DEFAULT 0 COMMENT '结算周期: 1=每周 2=每两周 3=每月',
  `country` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '国家',
  `city` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '城市',
  `state` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '州/省',
  `address` VARCHAR(500) COMMENT '地址',
  `is_gift_allowed` TINYINT NOT NULL DEFAULT 0 COMMENT '允许礼品',
  `data_source` TINYINT NOT NULL DEFAULT 0 COMMENT '数据来源',
  `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
  `created_by` INT NOT NULL DEFAULT 0 COMMENT '创建人',
  `updated_by` INT NOT NULL DEFAULT 0 COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE `user_infos_user_id_unique` (`user_id`),
  INDEX `user_infos_login_id_index` (`login_id`),
  INDEX `user_infos_parent_id_index` (`parent_id`),
  INDEX `user_infos_account_type_index` (`account_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_login_logs`;
CREATE TABLE `user_login_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `login_id` INT NOT NULL COMMENT '登录ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `login_ip` VARCHAR(200) NOT NULL COMMENT '登录IP',
  `ip_location` VARCHAR(255) NOT NULL COMMENT 'IP地理位置',
  `user_agent` VARCHAR(500) COMMENT '用户代理',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `user_login_logs_login_id_index` (`login_id`),
  INDEX `user_login_logs_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_auths`;
CREATE TABLE `user_auths` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `bank_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '银行卡号',
  `bank_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '银行名称',
  `bank_card_img` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '银行卡图片',
  `bank_card_img_tmp` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '银行卡临时图片',
  `bank_addr` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '分行地址',
  `bank_addr_tmp` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '分行临时地址',
  `bank_status` TINYINT NOT NULL DEFAULT 0 COMMENT '银行卡状态: 0=未通过 1=审核中 2=已通过 3=变更中 4=已拒绝',
  `bank_remarks` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '银行备注',
  `id_card_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '身份证号',
  `id_card_status` TINYINT NOT NULL DEFAULT 0 COMMENT '身份证状态: 0=未通过 1=审核中 2=已通过 4=已退回',
  `id_card_front` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '身份证正面',
  `id_card_back` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '身份证背面',
  `id_card_remarks` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '身份证备注',
  `is_bank_synced` TINYINT NOT NULL DEFAULT 0 COMMENT '银行信息同步',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE `user_auths_user_id_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `agent_levels`;
CREATE TABLE `agent_levels` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `level_code` INT NOT NULL COMMENT '级别代码',
  `name` VARCHAR(200) NOT NULL COMMENT '名称',
  `max_commission` INT NOT NULL DEFAULT 0 COMMENT '最大佣金',
  `min_commission` INT NOT NULL DEFAULT 0 COMMENT '最小佣金',
  `user_commission` INT NOT NULL DEFAULT 0 COMMENT '用户佣金',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `agent_levels_level_code_unique` (`level_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `group_configs`;
CREATE TABLE `group_configs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `pair_id` INT COMMENT '交易对ID',
  `name` VARCHAR(255) NOT NULL COMMENT '名称',
  `radix` DOUBLE(8,2) NOT NULL DEFAULT 50 COMMENT '基数',
  `category` TINYINT NOT NULL DEFAULT 2 COMMENT '分类: 1=代理 2=用户',
  `has_commission` TINYINT NOT NULL DEFAULT 0 COMMENT '是否有佣金',
  `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用',
  `is_ecn` TINYINT NOT NULL DEFAULT 0 COMMENT '是否ECN',
  `is_default` TINYINT NOT NULL DEFAULT 0 COMMENT '是否默认',
  `created_by` INT NOT NULL DEFAULT 0 COMMENT '创建人',
  `updated_by` INT NOT NULL DEFAULT 0 COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `group_configs_pair_id_index` (`pair_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `agent_descendants`;
CREATE TABLE `agent_descendants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `agent_id` INT NOT NULL COMMENT '代理ID',
  `descendant_id` INT NOT NULL COMMENT '下级ID',
  `descendant_type` TINYINT NOT NULL COMMENT '下级类型: 1=代理 2=客户',
  `is_direct` TINYINT NOT NULL DEFAULT 0 COMMENT '是否直属',
  `depth` INT NOT NULL DEFAULT 1 COMMENT '深度',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE `agent_descendants_agent_id_descendant_id_unique` (`agent_id`,`descendant_id`),
  INDEX `agent_descendants_agent_id_index` (`agent_id`),
  INDEX `agent_descendants_descendant_id_index` (`descendant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `id_sequences`;
CREATE TABLE `id_sequences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `type` VARCHAR(50) NOT NULL COMMENT '类型: agent or customer',
  `current_value` BIGINT NOT NULL COMMENT '当前值',
  `prefix` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '前缀',
  `step` INT NOT NULL DEFAULT 1 COMMENT '步长',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_sequences_type_unique` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `deposit_records`;
CREATE TABLE `deposit_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `user_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '用户名',
  `mt4_ticket` INT NOT NULL DEFAULT 0 COMMENT 'MT4订单号',
  `amount` DOUBLE(12,2) NOT NULL COMMENT '金额',
  `actual_amount` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '实际金额',
  `exchange_rate` DOUBLE(10,4) NOT NULL DEFAULT 0 COMMENT '汇率',
  `channel_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `channel_order_no` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '渠道订单号',
  `local_order_no` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '本地订单号',
  `status` VARCHAR(10) NOT NULL DEFAULT '01' COMMENT '状态: 01=待支付 02=已支付 05=退款 09=失败 10=超时',
  `payment_time` DATETIME COMMENT '支付时间',
  `remarks` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
  `created_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '创建人',
  `updated_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `deposit_records_user_id_index` (`user_id`),
  INDEX `deposit_records_status_index` (`status`),
  INDEX `deposit_records_mt4_ticket_index` (`mt4_ticket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `withdraw_records`;
CREATE TABLE `withdraw_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `user_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '用户名',
  `mt4_ticket` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'MT4订单号',
  `apply_amount` DOUBLE(12,2) NOT NULL COMMENT '申请金额',
  `actual_amount` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '实际金额',
  `fee` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '手续费',
  `exchange_rate` DOUBLE(10,4) NOT NULL DEFAULT 0 COMMENT '汇率',
  `rmb_fee` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '人民币手续费',
  `bank_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '银行卡号',
  `bank_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '银行名称',
  `bank_addr` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '分行地址',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=处理中 2=完成 3=失败',
  `local_order_no` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '本地订单号',
  `third_order_no` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '第三方订单号',
  `reject_reason` VARCHAR(500) COMMENT '拒绝原因',
  `mt4_return_status` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'MT4返回状态',
  `created_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '创建人',
  `updated_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `withdraw_records_user_id_index` (`user_id`),
  INDEX `withdraw_records_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_trades`;
CREATE TABLE `user_trades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `ticket` INT NOT NULL COMMENT '订单号',
  `symbol` CHAR(16) NOT NULL COMMENT '交易品种',
  `digits` INT NOT NULL COMMENT '小数位数',
  `cmd` INT NOT NULL COMMENT '类型',
  `volume` INT NOT NULL COMMENT '成交量',
  `open_time` DATETIME NOT NULL COMMENT '开仓时间',
  `open_price` DOUBLE NOT NULL COMMENT '开仓价格',
  `stop_loss` DOUBLE NOT NULL DEFAULT 0 COMMENT '止损',
  `take_profit` DOUBLE NOT NULL DEFAULT 0 COMMENT '止盈',
  `close_time` DATETIME NOT NULL COMMENT '平仓时间',
  `expiration` DATETIME COMMENT '到期时间',
  `reason` INT NOT NULL DEFAULT 0 COMMENT '原因',
  `conv_rate1` DOUBLE NOT NULL DEFAULT 0 COMMENT '转换率1',
  `conv_rate2` DOUBLE NOT NULL DEFAULT 0 COMMENT '转换率2',
  `commission` DOUBLE NOT NULL DEFAULT 0 COMMENT '佣金',
  `commission_agent` DOUBLE NOT NULL DEFAULT 0 COMMENT '代理佣金',
  `swaps` DOUBLE NOT NULL DEFAULT 0 COMMENT '隔夜利息',
  `close_price` DOUBLE NOT NULL DEFAULT 0 COMMENT '平仓价格',
  `profit` DOUBLE NOT NULL DEFAULT 0 COMMENT '利润',
  `taxes` DOUBLE NOT NULL DEFAULT 0 COMMENT '税费',
  `comment` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '评论',
  `internal_id` INT NOT NULL DEFAULT 0 COMMENT '内部ID',
  `margin_rate` DOUBLE NOT NULL DEFAULT 0 COMMENT '保证金率',
  `timestamp_val` INT NOT NULL DEFAULT 0 COMMENT '时间戳',
  `magic` INT NOT NULL DEFAULT 0 COMMENT '魔法号',
  `gw_volume` INT NOT NULL DEFAULT 0 COMMENT '网关成交量',
  `gw_open_price` INT NOT NULL DEFAULT 0 COMMENT '网关开仓价',
  `gw_close_price` INT NOT NULL DEFAULT 0 COMMENT '网关平仓价',
  `modify_time` DATETIME NOT NULL COMMENT '修改时间',
  `settlement_status` TINYINT NOT NULL DEFAULT 0 COMMENT '结算状态: 0=未结算 1=已结算',
  `settled_at` DATETIME COMMENT '结算时间',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `user_trades_user_id_index` (`user_id`),
  INDEX `user_trades_ticket_index` (`ticket`),
  INDEX `user_trades_cmd_index` (`cmd`),
  INDEX `user_trades_open_time_index` (`open_time`),
  INDEX `user_trades_close_time_index` (`close_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `commission_records`;
CREATE TABLE `commission_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `unique_id` VARCHAR(100) NOT NULL COMMENT 'MD5唯一标识',
  `agent_id` INT NOT NULL COMMENT '代理ID',
  `parent_id` INT NOT NULL COMMENT '父代理ID',
  `agent_profit` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '代理利润',
  `agent_volume` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '代理交易量',
  `equity_value` INT NOT NULL DEFAULT 0 COMMENT '净值',
  `equity_diff` INT NOT NULL DEFAULT 0 COMMENT '净值差',
  `settle_cycle` TINYINT NOT NULL DEFAULT 0 COMMENT '结算周期',
  `mt4_order_id` INT NOT NULL DEFAULT 0 COMMENT 'MT4订单ID',
  `date_range` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '日期范围',
  `settle_status` TINYINT NOT NULL DEFAULT 1 COMMENT '结算状态: 1=待结算 2=已结算',
  `fee` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '手续费',
  `swap` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '隔夜利息',
  `commission_amount` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '佣金金额',
  `returned_amount` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '返还金额',
  `deposit` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '入金',
  `real_amount` DOUBLE(12,2) NOT NULL DEFAULT 0 COMMENT '实际金额',
  `data_type` VARCHAR(20) NOT NULL DEFAULT 'mainData' COMMENT '数据类型',
  `manual_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '手动调整原因',
  `remarks` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
  `created_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '创建人',
  `updated_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `commission_records_agent_id_index` (`agent_id`),
  INDEX `commission_records_parent_id_index` (`parent_id`),
  INDEX `commission_records_settle_status_index` (`settle_status`),
  INDEX `commission_records_unique_id_index` (`unique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_configs`;
CREATE TABLE `system_configs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `key` VARCHAR(100) NOT NULL COMMENT '配置键',
  `value` TEXT COMMENT '配置值',
  `group` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT '分组',
  `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '描述',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_configs_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` VARCHAR(500) NOT NULL COMMENT '标题',
  `content` LONGTEXT NOT NULL COMMENT '内容',
  `image` VARCHAR(500) COMMENT '图片',
  `author_id` INT NOT NULL DEFAULT 0 COMMENT '作者ID',
  `author_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '作者名称',
  `is_published` TINYINT NOT NULL DEFAULT 0 COMMENT '是否发布: 0=草稿 1=已发布',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `operation_logs`;
CREATE TABLE `operation_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `admin_id` INT NOT NULL COMMENT '管理员ID',
  `admin_name` VARCHAR(100) NOT NULL COMMENT '管理员名称',
  `target_user_id` INT COMMENT '目标用户ID',
  `order_no` VARCHAR(100) COMMENT '订单号',
  `content` VARCHAR(1000) NOT NULL COMMENT '操作内容',
  `ip` VARCHAR(100) NOT NULL COMMENT 'IP',
  `action_type` TINYINT NOT NULL DEFAULT 0 COMMENT '行为类型: 0=普通 1=提现',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `operation_logs_admin_id_index` (`admin_id`),
  INDEX `operation_logs_target_user_id_index` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cancel_applies`;
CREATE TABLE `cancel_applies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `user_name` VARCHAR(100) NOT NULL COMMENT '用户名',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=通过 -1=拒绝',
  `cancel_remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Cancellation reason submitted by user',
  `reject_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '拒绝原因',
  `created_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '创建人',
  `updated_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `blacklists`;
CREATE TABLE `blacklists` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` VARCHAR(100) NOT NULL COMMENT '姓名',
  `id_card` VARCHAR(50) NOT NULL COMMENT '身份证号',
  `email` VARCHAR(100) NOT NULL COMMENT '邮箱',
  `phone` VARCHAR(30) NOT NULL COMMENT '电话',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `blacklists_id_card_index` (`id_card`),
  INDEX `blacklists_email_index` (`email`),
  INDEX `blacklists_phone_index` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_channels`;
CREATE TABLE `payment_channels` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` VARCHAR(100) NOT NULL COMMENT '名称',
  `channel_code` VARCHAR(50) NOT NULL COMMENT '渠道代码',
  `exchange_rate` DOUBLE(10,4) NOT NULL DEFAULT 0 COMMENT '汇率',
  `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `config` JSON COMMENT '配置',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `big_agents`;
CREATE TABLE `big_agents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `email` VARCHAR(200) NOT NULL COMMENT '邮箱',
  `username` VARCHAR(200) NOT NULL COMMENT '用户名',
  `password` VARCHAR(255) NOT NULL COMMENT '密码',
  `sub_agent_ids` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '下级代理ID',
  `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用',
  `jwt_token_id` VARCHAR(100) COMMENT 'JWT Token ID',
  `created_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '创建人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `big_agent_login_logs`;
CREATE TABLE `big_agent_login_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `big_agent_id` INT NOT NULL COMMENT '大代理ID',
  `login_ip` VARCHAR(100) NOT NULL COMMENT '登录IP',
  `login_at` DATETIME NOT NULL COMMENT '登录时间',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `voucher_infos`;
CREATE TABLE `voucher_infos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `images` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '凭证图片',
  `remarks` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '备注',
  `review_status` TINYINT NOT NULL DEFAULT 0 COMMENT '审核状态: 0=待处理 1=通过 2=拒绝',
  `review_message` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '审核留言',
  `created_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '创建人',
  `updated_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `voucher_infos_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `symbol_prices`;
CREATE TABLE `symbol_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `symbol` VARCHAR(16) NOT NULL COMMENT '交易品种',
  `time` DATETIME NOT NULL COMMENT '时间',
  `bid` DOUBLE NOT NULL COMMENT '买入价',
  `ask` DOUBLE NOT NULL COMMENT '卖出价',
  `low` DOUBLE NOT NULL COMMENT '最低价',
  `high` DOUBLE NOT NULL COMMENT '最高价',
  `direction` INT NOT NULL COMMENT '方向',
  `digits` INT NOT NULL COMMENT '小数位数',
  `spread` DOUBLE NOT NULL COMMENT '点差',
  `group_id` INT NOT NULL DEFAULT 0 COMMENT '分组ID: 1=贵金属 2=能源 3=外汇 4=指数 5=货币 6=股票',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  `modify_time` DATETIME NOT NULL COMMENT '修改时间',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `spread_configs`;
CREATE TABLE `spread_configs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `spread` DOUBLE NOT NULL COMMENT '点差',
  `agent_group_id` INT NOT NULL COMMENT '代理组ID',
  `spread_ratio` DOUBLE NOT NULL COMMENT '点差比例',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `deposit_imports`;
CREATE TABLE `deposit_imports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `user_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '用户名',
  `amount` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '金额',
  `remarks` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
  `mt4_order_id` INT NOT NULL DEFAULT 0 COMMENT 'MT4订单ID',
  `batch_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '批次号',
  `is_synced` TINYINT NOT NULL DEFAULT 0 COMMENT '是否同步: 0=待处理 1=成功 2=失败',
  `fail_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '失败原因',
  `created_by` INT NOT NULL DEFAULT 0 COMMENT '创建人',
  `updated_by` INT NOT NULL DEFAULT 0 COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `deposit_imports_user_id_index` (`user_id`),
  INDEX `deposit_imports_batch_no_index` (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `withdraw_imports`;
CREATE TABLE `withdraw_imports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `user_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '用户名',
  `amount` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '金额',
  `remarks` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
  `mt4_order_id` INT NOT NULL DEFAULT 0 COMMENT 'MT4订单ID',
  `batch_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '批次号',
  `is_synced` TINYINT NOT NULL DEFAULT 0 COMMENT '是否同步: 0=待处理 1=成功 2=失败',
  `fail_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '失败原因',
  `created_by` INT NOT NULL DEFAULT 0 COMMENT '创建人',
  `updated_by` INT NOT NULL DEFAULT 0 COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `withdraw_imports_user_id_index` (`user_id`),
  INDEX `withdraw_imports_batch_no_index` (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `credit_imports`;
CREATE TABLE `credit_imports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `user_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '用户名',
  `credit_type` TINYINT NOT NULL DEFAULT 1 COMMENT '信用类型: 1=临时 2=永久 3=奖励 4=其他',
  `mt4_order_id` INT NOT NULL DEFAULT 0 COMMENT 'MT4订单ID',
  `amount` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '金额',
  `batch_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '批次号',
  `is_synced` TINYINT NOT NULL DEFAULT 0 COMMENT '是否同步',
  `fail_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '失败原因',
  `remarks` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '备注',
  `created_by` INT NOT NULL DEFAULT 0 COMMENT '创建人',
  `updated_by` INT NOT NULL DEFAULT 0 COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `credit_imports_user_id_index` (`user_id`),
  INDEX `credit_imports_batch_no_index` (`batch_no`),
  INDEX `credit_imports_credit_type_index` (`credit_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_addresses`;
CREATE TABLE `user_addresses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `recipient_name` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '收件人姓名',
  `recipient_phone` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '收件人电话',
  `recipient_address` VARCHAR(5000) NOT NULL DEFAULT '' COMMENT '收件人地址',
  `is_default` TINYINT NOT NULL DEFAULT 0 COMMENT '是否默认',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `user_addresses_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gift_shipments`;
CREATE TABLE `gift_shipments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `address_id` BIGINT NOT NULL DEFAULT 0 COMMENT '地址ID',
  `recipient_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '收件人姓名',
  `recipient_phone` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '收件人电话',
  `recipient_address` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '收件人地址',
  `sender_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '发件人姓名',
  `tracking_number` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '快递单号',
  `gift_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '礼品名称',
  `gift_quantity` INT NOT NULL DEFAULT 1 COMMENT '礼品数量',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=已发货 2=运输中 3=已送达 4=异常',
  `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
  `admin_id` INT NOT NULL DEFAULT 0 COMMENT '管理员ID',
  `shipped_at` DATETIME COMMENT '发货时间',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `gift_shipments_user_id_index` (`user_id`),
  INDEX `gift_shipments_tracking_number_index` (`tracking_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mail_settings`;
CREATE TABLE `mail_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `driver` VARCHAR(50) COMMENT '驱动',
  `host` VARCHAR(255) COMMENT '主机',
  `port` VARCHAR(10) COMMENT '端口',
  `username` VARCHAR(255) COMMENT '用户名',
  `password` VARCHAR(255) COMMENT '密码',
  `encryption` VARCHAR(20) COMMENT '加密方式',
  `from_address` VARCHAR(255) COMMENT '发件人地址',
  `from_name` VARCHAR(255) COMMENT '发件人名称',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `data_operation_logs`;
CREATE TABLE `data_operation_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `model_type` VARCHAR(100) NOT NULL COMMENT '模型类型',
  `model_id` INT NOT NULL COMMENT '模型ID',
  `before_data` JSON COMMENT '修改前数据',
  `after_data` JSON COMMENT '修改后数据',
  `operator_id` INT NOT NULL COMMENT '操作人ID',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `trans_apply_logs`;
CREATE TABLE `trans_apply_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `origin_group_id` INT NOT NULL DEFAULT 0 COMMENT 'Original group ID before transfer apply',
  `group_id` INT NOT NULL COMMENT '分组ID',
  `group_name` VARCHAR(200) NOT NULL COMMENT '分组名称',
  `applicant_id` INT NOT NULL COMMENT '申请人ID',
  `applicant_name` VARCHAR(200) NOT NULL COMMENT '申请人姓名',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=通过 -1=拒绝',
  `apply_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Application reason submitted by agent',
  `reject_reason` VARCHAR(500) COMMENT '拒绝原因',
  `created_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '创建人',
  `updated_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `whs_exp_zeros`;
CREATE TABLE `whs_exp_zeros` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT NOT NULL COMMENT '用户ID',
  `user_name` VARCHAR(100) NOT NULL COMMENT '用户名',
  `balance` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '余额',
  `credit` DOUBLE(50,2) NOT NULL DEFAULT 0 COMMENT '信用额',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 1=待处理 2=已清零',
  `md5_key` VARCHAR(100) NOT NULL COMMENT 'MD5标识',
  `created_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '创建人',
  `updated_by` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '更新人',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `iso_code` VARCHAR(5) NOT NULL COMMENT 'ISO代码',
  `zone_id` INT NOT NULL DEFAULT 0 COMMENT '时区ID',
  `currency_id` INT NOT NULL DEFAULT 0 COMMENT '货币ID',
  `is_active` TINYINT NOT NULL DEFAULT 0 COMMENT '是否启用',
  `call_prefix` INT NOT NULL DEFAULT 0 COMMENT '电话前缀',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  INDEX `countries_iso_code_index` (`iso_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `country_translations`;
CREATE TABLE `country_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `country_id` INT NOT NULL COMMENT '国家ID',
  `lang_code` VARCHAR(10) NOT NULL COMMENT '语言代码',
  `name` VARCHAR(100) NOT NULL COMMENT '名称',
  `initials` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '首字母',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`),
  UNIQUE `country_translations_country_id_lang_code_unique` (`country_id`,`lang_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `languages`;
CREATE TABLE `languages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` VARCHAR(50) NOT NULL COMMENT '名称',
  `iso_code` VARCHAR(5) NOT NULL COMMENT 'ISO代码',
  `language_code` VARCHAR(10) NOT NULL COMMENT '语言代码',
  `locale` VARCHAR(10) NOT NULL COMMENT '本地化',
  `is_active` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间(10位时间戳)',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间(10位时间戳)',
  `deleted_at` INT UNSIGNED COMMENT '删除时间(10位时间戳)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '',
  `title` VARCHAR(100) NOT NULL COMMENT '菜单标题',
  `title_en` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '菜单英文标题',
  `icon` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '图标',
  `path` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '前端路由路径',
  `component` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '前端组件路径',
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级菜单ID, 0=顶级',
  `permission_id` BIGINT UNSIGNED COMMENT '绑定权限ID',
  `guard_type` VARCHAR(20) NOT NULL DEFAULT 'admin' COMMENT '守卫类型: admin/front',
  `type` TINYINT NOT NULL DEFAULT 1 COMMENT '类型: 1=目录 2=菜单 3=按钮',
  `is_visible` TINYINT NOT NULL DEFAULT 1 COMMENT '是否可见: 0=隐藏 1=显示',
  `is_external` TINYINT NOT NULL DEFAULT 0 COMMENT '是否外链: 0=否 1=是',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序值越小越前',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  INDEX `menus_parent_id_index` (`parent_id`),
  INDEX `menus_guard_type_index` (`guard_type`),
  INDEX `menus_guard_type_status_index` (`guard_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admin_logins`;
CREATE TABLE `admin_logins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `username` VARCHAR(100) NOT NULL COMMENT '用户名',
  `password` VARCHAR(100) NOT NULL COMMENT '密码',
  `role_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色ID',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 1=启用 0=禁用',
  `last_login_ip` VARCHAR(50) COMMENT '最后登录IP',
  `last_login_at` INT UNSIGNED COMMENT '最后登录时间',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE `admin_logins_username_unique` (`username`),
  INDEX `admin_logins_role_id_index` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `news_langs`;
CREATE TABLE `news_langs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `news_id` INT UNSIGNED NOT NULL COMMENT '新闻ID',
  `lang_code` VARCHAR(10) NOT NULL COMMENT '语言代码',
  `title` VARCHAR(255) NOT NULL COMMENT '标题',
  `content` TEXT COMMENT '内容',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  INDEX `news_langs_news_id_lang_code_index` (`news_id`,`lang_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `country_langs`;
CREATE TABLE `country_langs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `country_id` INT UNSIGNED NOT NULL COMMENT '国家ID',
  `lang_code` VARCHAR(10) NOT NULL COMMENT '语言代码',
  `name` VARCHAR(100) NOT NULL COMMENT '国家名称',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  INDEX `country_langs_country_id_lang_code_index` (`country_id`,`lang_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_onlines`;
CREATE TABLE `user_onlines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `last_activity` INT UNSIGNED NOT NULL COMMENT '最后活跃时间',
  `ip_address` VARCHAR(45) COMMENT 'IP地址',
  `user_agent` TEXT COMMENT '浏览器代理',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `user_onlines_user_id_index` (`user_id`),
  INDEX `user_onlines_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rights_settlements`;
CREATE TABLE `rights_settlements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `amount` DECIMAL(20,8) NOT NULL COMMENT '结算金额',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0=未处理, 1=已处理',
  `remark` VARCHAR(255) COMMENT '备注',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  INDEX `rights_settlements_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rights_settlement_temps`;
CREATE TABLE `rights_settlement_temps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `amount` DECIMAL(20,8) NOT NULL COMMENT '临时金额',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `rights_settlement_temps_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_images`;
CREATE TABLE `user_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `type` VARCHAR(50) NOT NULL COMMENT '图片类型: kyc_front, kyc_back, avatar',
  `path` VARCHAR(255) NOT NULL COMMENT '文件路径',
  `mime_type` VARCHAR(50) COMMENT 'MIME类型',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  INDEX `user_images_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `batch_fail_records`;
CREATE TABLE `batch_fail_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `batch_type` VARCHAR(50) NOT NULL COMMENT '批量操作类型',
  `batch_id` VARCHAR(100) NOT NULL COMMENT '批量操作ID',
  `data` TEXT NOT NULL COMMENT '原始数据',
  `error_msg` VARCHAR(255) NOT NULL COMMENT '错误信息',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `batch_fail_records_batch_id_index` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `app_versions`;
CREATE TABLE `app_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `platform` VARCHAR(20) NOT NULL COMMENT '平台: android, ios',
  `version` VARCHAR(20) NOT NULL COMMENT '版本号',
  `download_url` VARCHAR(255) NOT NULL COMMENT '下载地址',
  `update_logs` TEXT COMMENT '更新日志',
  `is_force` TINYINT NOT NULL DEFAULT 0 COMMENT '是否强制更新',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `offweb_feedbacks`;
CREATE TABLE `offweb_feedbacks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT UNSIGNED COMMENT '用户ID',
  `email` VARCHAR(100) NOT NULL COMMENT '联系邮箱',
  `title` VARCHAR(255) NOT NULL COMMENT '标题',
  `content` TEXT NOT NULL COMMENT '反馈内容',
  `reply` VARCHAR(255) COMMENT '回复内容',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0=未处理, 1=已回复',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  INDEX `offweb_feedbacks_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sys_dicts`;
CREATE TABLE `sys_dicts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `type` VARCHAR(50) NOT NULL COMMENT '字典类型',
  `label` VARCHAR(100) NOT NULL COMMENT '字典名称',
  `value` VARCHAR(100) NOT NULL COMMENT '字典值',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `sys_dicts_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mt4_configs`;
CREATE TABLE `mt4_configs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `server_name` VARCHAR(100) NOT NULL COMMENT '服务器名称',
  `ip` VARCHAR(50) NOT NULL COMMENT '服务器IP',
  `port` INT NOT NULL COMMENT '端口',
  `manager_login` VARCHAR(50) NOT NULL COMMENT '管理账号',
  `manager_password` VARCHAR(100) NOT NULL COMMENT '管理密码',
  `is_active` TINYINT NOT NULL DEFAULT 1 COMMENT '是否激活',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mt4_prices`;
CREATE TABLE `mt4_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `symbol` VARCHAR(50) NOT NULL COMMENT '交易品种',
  `bid` DECIMAL(20,5) NOT NULL COMMENT '卖出价',
  `ask` DECIMAL(20,5) NOT NULL COMMENT '买入价',
  `timestamp` INT UNSIGNED NOT NULL COMMENT '价格时间戳',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `mt4_prices_symbol_index` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mt4_trades`;
CREATE TABLE `mt4_trades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `ticket` INT NOT NULL COMMENT 'MT4订单号',
  `login` INT NOT NULL COMMENT 'MT4账号',
  `symbol` VARCHAR(50) NOT NULL COMMENT '品种',
  `cmd` INT NOT NULL COMMENT '类型: 0=Buy, 1=Sell',
  `volume` DOUBLE NOT NULL COMMENT '成交量',
  `open_price` DECIMAL(20,5) NOT NULL COMMENT '开仓价',
  `close_price` DECIMAL(20,5) COMMENT '平仓价',
  `commission` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT '手续费',
  `swaps` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT '库存费',
  `profit` DECIMAL(20,2) NOT NULL COMMENT '盈利',
  `open_time` INT UNSIGNED NOT NULL COMMENT '开仓时间',
  `close_time` INT UNSIGNED COMMENT '平仓时间',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mt4_trades_ticket_unique` (`ticket`),
  INDEX `mt4_trades_login_index` (`login`),
  INDEX `mt4_trades_ticket_index` (`ticket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mt4_users`;
CREATE TABLE `mt4_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `login` INT NOT NULL COMMENT 'MT4账号',
  `name` VARCHAR(100) NOT NULL COMMENT '姓名',
  `group` VARCHAR(100) NOT NULL COMMENT 'MT4分组',
  `balance` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT '余额',
  `equity` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT '净值',
  `margin` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT '保证金',
  `margin_free` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT '可用保证金',
  `leverage` INT NOT NULL DEFAULT 100 COMMENT '杠杆',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` INT UNSIGNED COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mt4_users_login_unique` (`login`),
  INDEX `mt4_users_login_index` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `id_sequences` (`type`, `current_value`, `prefix`, `step`, `created_at`, `updated_at`) VALUES 
('agent', 1000, '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('customer', 600000, '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT INTO `admins` (`id`, `role_id`, `mobile`, `email`, `username`, `password`, `login_count`, `last_login_ip`, `last_login_at`, `last_login_address`, `status`, `jwt_token_id`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '1', '', 'admin@admin.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, '', NULL, NULL, 1, NULL, 'system', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), NULL);

INSERT INTO `roles` (`id`, `name`, `guard_type`, `description`, `permissions`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'super_admin', 'admin', '超级管理员', '[]', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), NULL);

INSERT INTO `languages` (`id`, `name`, `iso_code`, `language_code`, `locale`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'English', 'en', 'en', 'en_US', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '简体中文', 'zh', 'zh-CN', 'zh_CN', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT INTO `menus` (`id`, `title`, `title_en`, `icon`, `path`, `parent_id`, `guard_type`, `type`, `sort`, `created_at`, `updated_at`) VALUES 
(1, '仪表盘', 'Dashboard', 'fa fa-tachometer-alt', '/admin/dashboard', 0, 'admin', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '用户管理', 'User Management', 'fa fa-users', '', 0, 'admin', 1, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, '代理商列表', 'Agent List', 'fa fa-user-tie', '/admin/users?type=agent', 2, 'admin', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(4, '客户列表', 'Customer List', 'fa fa-user', '/admin/users?type=customer', 2, 'admin', 2, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(5, '用户审核', 'User Review', 'fa fa-user-check', '/admin/users?status=pending', 2, 'admin', 2, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(6, '财务管理', 'Finance', 'fa fa-money-bill', '', 0, 'admin', 1, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(7, '入金记录', 'Deposits', 'fa fa-arrow-down', '/admin/deposits', 6, 'admin', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(8, '出金记录', 'Withdrawals', 'fa fa-arrow-up', '/admin/withdrawals', 6, 'admin', 2, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(9, '返佣记录', 'Commission', 'fa fa-percentage', '/admin/commissions', 6, 'admin', 2, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(10, '系统管理', 'System', 'fa fa-cog', '', 0, 'admin', 1, 4, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(11, '角色管理', 'Roles', 'fa fa-user-shield', '/admin/roles', 10, 'admin', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(12, '权限管理', 'Permissions', 'fa fa-key', '/admin/permissions', 10, 'admin', 2, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(13, '菜单管理', 'Menus', 'fa fa-bars', '/admin/menus', 10, 'admin', 2, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(14, '系统配置', 'Config', 'fa fa-sliders-h', '/admin/config', 10, 'admin', 2, 4, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(15, '仪表盘', 'Dashboard', 'fa fa-tachometer-alt', '/dashboard', 0, 'front', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(16, '我的账户', 'My Account', 'fa fa-user-circle', '', 0, 'front', 1, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(17, '个人资料', 'Profile', 'fa fa-id-card', '/profile', 16, 'front', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(18, '修改密码', 'Password', 'fa fa-lock', '/profile/password', 16, 'front', 2, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(19, '修改邮箱', 'Email', 'fa fa-envelope', '/profile/email', 16, 'front', 2, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(20, '代理中心', 'Agent Center', 'fa fa-sitemap', '', 0, 'front', 1, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(21, '我的下级代理', 'Sub Agents', 'fa fa-user-friends', '/agents', 20, 'front', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(22, '我的客户', 'Customers', 'fa fa-users', '/customers', 20, 'front', 2, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(23, '交易', 'Trading', 'fa fa-chart-line', '', 0, 'front', 1, 4, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(24, '仓位总结', 'Positions', 'fa fa-th-list', '/positions', 23, 'front', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(25, '实时返佣', 'Commission', 'fa fa-coins', '/commission', 23, 'front', 2, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

SET FOREIGN_KEY_CHECKS = 1;
