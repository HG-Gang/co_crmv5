-- Legacy front data migration.
-- Source: hank_zl_data on the same MySQL instance.
-- Target: co_crmv5.
-- This SQL mirrors LegacyFrontReferenceSeeder and LegacyFrontBusinessDataSeeder.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET @now := UNIX_TIMESTAMP();
SET @default_plain_password_hash := '$2y$10$Esi0mGy8eLhis0PIq3XUbuWCkzyuduBj8eFRxMCJskbHbcw2a/DAS';
SET @legacy_agent_11_password_hash := '$2y$10$w6ntwM4vQ4Jc41aMqPI4OuQRlX1GVIn8h218xze7svGmjvsg3z9F6';
SET @front_test_agent_password_hash := '$2y$10$DLxlKmCrlkPlq3LRZZ0b1.RgzOgK3re6q81/AQsaGh42IfuFrFiXW';
SET @old_sql_mode := @@SESSION.sql_mode;
SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'STRICT_TRANS_TABLES', ''), 'STRICT_ALL_TABLES', '');

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- Reference data
-- ---------------------------------------------------------------------------

UPDATE co_crmv5.agent_levels t
JOIN hank_zl_data.agent_level s ON t.level_code = s.level_id
SET
    t.name = s.name,
    t.max_commission = s.max_prop,
    t.min_commission = s.min_prop,
    t.user_commission = s.user_prop,
    t.created_at = CASE WHEN s.created_at IS NULL OR s.created_at = '' OR s.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.created_at) END,
    t.updated_at = CASE WHEN s.updated_at IS NULL OR s.updated_at = '' OR s.updated_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.updated_at) END,
    t.deleted_at = CASE WHEN s.deleted_at IS NULL OR s.deleted_at = '' OR s.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(s.deleted_at) END;

INSERT INTO co_crmv5.agent_levels
    (level_code, name, max_commission, min_commission, user_commission, created_at, updated_at, deleted_at)
SELECT
    s.level_id,
    s.name,
    s.max_prop,
    s.min_prop,
    s.user_prop,
    CASE WHEN s.created_at IS NULL OR s.created_at = '' OR s.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.created_at) END,
    CASE WHEN s.updated_at IS NULL OR s.updated_at = '' OR s.updated_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.updated_at) END,
    CASE WHEN s.deleted_at IS NULL OR s.deleted_at = '' OR s.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(s.deleted_at) END
FROM hank_zl_data.agent_level s
WHERE NOT EXISTS (
    SELECT 1 FROM co_crmv5.agent_levels t WHERE t.level_code = s.level_id
);

UPDATE co_crmv5.group_configs t
JOIN hank_zl_data.group_config s ON t.pair_id = s.pair_id
SET
    t.name = s.name,
    t.radix = s.radix,
    t.category = s.category,
    t.has_commission = s.has_comm,
    t.is_enabled = s.is_enabled,
    t.is_ecn = s.is_ecn,
    t.is_default = s.is_default,
    t.created_by = COALESCE(s.created_id, 0),
    t.updated_by = COALESCE(s.updated_id, 0),
    t.created_at = CASE WHEN s.created_at IS NULL OR s.created_at = '' OR s.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.created_at) END,
    t.updated_at = CASE WHEN s.updated_at IS NULL OR s.updated_at = '' OR s.updated_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.updated_at) END,
    t.deleted_at = CASE WHEN s.deleted_at IS NULL OR s.deleted_at = '' OR s.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(s.deleted_at) END;

INSERT INTO co_crmv5.group_configs
    (pair_id, name, radix, category, has_commission, is_enabled, is_ecn, is_default, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    s.pair_id,
    s.name,
    s.radix,
    s.category,
    s.has_comm,
    s.is_enabled,
    s.is_ecn,
    s.is_default,
    COALESCE(s.created_id, 0),
    COALESCE(s.updated_id, 0),
    CASE WHEN s.created_at IS NULL OR s.created_at = '' OR s.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.created_at) END,
    CASE WHEN s.updated_at IS NULL OR s.updated_at = '' OR s.updated_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.updated_at) END,
    CASE WHEN s.deleted_at IS NULL OR s.deleted_at = '' OR s.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(s.deleted_at) END
FROM hank_zl_data.group_config s
WHERE NOT EXISTS (
    SELECT 1 FROM co_crmv5.group_configs t WHERE t.pair_id = s.pair_id
);

UPDATE co_crmv5.symbol_prices t
JOIN hank_zl_data.symbol_prices s ON t.symbol = TRIM(s.sym_symbol)
SET
    t.time = CASE WHEN s.sym_time IS NULL OR s.sym_time = '' OR s.sym_time LIKE '0000-00-00%' THEN FROM_UNIXTIME(@now) ELSE s.sym_time END,
    t.bid = s.sym_bid,
    t.ask = s.sym_ask,
    t.low = s.sym_low,
    t.high = s.sym_high,
    t.direction = s.sym_direction,
    t.digits = s.sym_digits,
    t.spread = s.sym_spread,
    t.group_id = s.sym_grp_id,
    t.status = s.voided,
    t.modify_time = CASE WHEN s.sym_modify_time IS NULL OR s.sym_modify_time = '' OR s.sym_modify_time LIKE '0000-00-00%' THEN FROM_UNIXTIME(@now) ELSE s.sym_modify_time END,
    t.updated_at = @now,
    t.deleted_at = NULL
WHERE TRIM(s.sym_symbol) <> '';

INSERT INTO co_crmv5.symbol_prices
    (symbol, time, bid, ask, low, high, direction, digits, spread, group_id, status, modify_time, created_at, updated_at, deleted_at)
SELECT
    TRIM(s.sym_symbol),
    CASE WHEN s.sym_time IS NULL OR s.sym_time = '' OR s.sym_time LIKE '0000-00-00%' THEN FROM_UNIXTIME(@now) ELSE s.sym_time END,
    s.sym_bid,
    s.sym_ask,
    s.sym_low,
    s.sym_high,
    s.sym_direction,
    s.sym_digits,
    s.sym_spread,
    s.sym_grp_id,
    s.voided,
    CASE WHEN s.sym_modify_time IS NULL OR s.sym_modify_time = '' OR s.sym_modify_time LIKE '0000-00-00%' THEN FROM_UNIXTIME(@now) ELSE s.sym_modify_time END,
    @now,
    @now,
    NULL
FROM hank_zl_data.symbol_prices s
WHERE TRIM(s.sym_symbol) <> ''
  AND NOT EXISTS (
      SELECT 1 FROM co_crmv5.symbol_prices t WHERE t.symbol = TRIM(s.sym_symbol)
  );

UPDATE co_crmv5.spread_configs t
JOIN hank_zl_data.symbol_spread s
    ON t.spread = s.spread AND t.agent_group_id = s.agent_group_id
SET
    t.spread_ratio = s.spread_ratio,
    t.status = s.voided,
    t.created_at = CASE WHEN s.rec_crt_date IS NULL OR s.rec_crt_date = '' OR s.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.rec_crt_date) END,
    t.updated_at = CASE WHEN s.rec_upd_date IS NULL OR s.rec_upd_date = '' OR s.rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.rec_upd_date) END,
    t.deleted_at = NULL;

INSERT INTO co_crmv5.spread_configs
    (spread, agent_group_id, spread_ratio, status, created_at, updated_at, deleted_at)
SELECT
    s.spread,
    s.agent_group_id,
    s.spread_ratio,
    s.voided,
    CASE WHEN s.rec_crt_date IS NULL OR s.rec_crt_date = '' OR s.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.rec_crt_date) END,
    CASE WHEN s.rec_upd_date IS NULL OR s.rec_upd_date = '' OR s.rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(s.rec_upd_date) END,
    NULL
FROM hank_zl_data.symbol_spread s
WHERE NOT EXISTS (
    SELECT 1
    FROM co_crmv5.spread_configs t
    WHERE t.spread = s.spread AND t.agent_group_id = s.agent_group_id
);

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_system_configs;
CREATE TEMPORARY TABLE tmp_legacy_system_configs AS
SELECT
    cfg_key,
    cfg_value,
    cfg_group,
    cfg_description,
    CASE WHEN rec_crt_date IS NULL OR rec_crt_date = '' OR rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_crt_date) END AS created_ts,
    CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END AS updated_ts
FROM (
    SELECT 'sys_draw_risk' cfg_key, sys_draw_risk cfg_value, 'legacy_system' cfg_group, 'Imported from hank_zl_data.system_config.sys_draw_risk' cfg_description, CAST(rec_crt_date AS CHAR) AS rec_crt_date, CAST(rec_upd_date AS CHAR) AS rec_upd_date FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_draw_perc', sys_draw_perc, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_draw_perc', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_poundage_money', sys_poundage_money, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_poundage_money', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_draw_rate', sys_draw_rate, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_draw_rate', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate', sys_deposit_rate, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate2', sys_deposit_rate2, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate2', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate3', sys_deposit_rate3, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate3', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate6', sys_deposit_rate6, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate6', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate7', sys_deposit_rate7, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate7', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate8', sys_deposit_rate8, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate8', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate9', sys_deposit_rate9, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate9', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate10', sys_deposit_rate10, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate10', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate11', sys_deposit_rate11, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_deposit_rate11', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_margin_perc', sys_margin_perc, 'legacy_system', 'Imported from hank_zl_data.system_config.sys_margin_perc', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'apply_end_date', apply_end_date, 'legacy_system', 'Imported from hank_zl_data.system_config.apply_end_date', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'trades_start', trades_start, 'legacy_system', 'Imported from hank_zl_data.system_config.trades_start', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'trades_whs_exp_zero', trades_whs_exp_zero, 'legacy_system', 'Imported from hank_zl_data.system_config.trades_whs_exp_zero', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'deposit_exchange_rate_cny', COALESCE(sys_deposit_rate, '7.0'), 'finance', 'Legacy default deposit CNY rate', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'withdraw_exchange_rate_cny', COALESCE(sys_draw_rate, '6.8'), 'finance', 'Legacy withdrawal CNY rate', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'withdraw_risk_rate_limit', COALESCE(sys_draw_risk, '100'), 'finance', 'Legacy withdrawal risk-rate limit', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'withdrawal_fixed_fee_usd', COALESCE(sys_poundage_money, '0'), 'finance', 'Legacy fixed withdrawal fee', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'withdrawal_fee_rate', '0', 'finance', 'Legacy non-OTC withdrawal fee rate', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'withdraw_percent_limit', COALESCE(sys_draw_perc, '100'), 'finance', 'Legacy withdrawal percent limit', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'margin_percent_limit', COALESCE(sys_margin_perc, '0'), 'risk', 'Legacy margin percent limit', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'withdraw_apply_end_date', COALESCE(apply_end_date, ''), 'finance', 'Legacy apply_end_date value', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate4', '1.0', 'legacy_system', 'Default legacy crypto channel 4 rate', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
    UNION ALL SELECT 'sys_deposit_rate5', '1.0', 'legacy_system', 'Default legacy crypto channel 5 rate', CAST(rec_crt_date AS CHAR), CAST(rec_upd_date AS CHAR) FROM hank_zl_data.system_config WHERE voided = '1'
) raw;

INSERT INTO co_crmv5.system_configs
    (`key`, `value`, `group`, description, created_at, updated_at, deleted_at)
SELECT cfg_key, CAST(cfg_value AS CHAR), cfg_group, cfg_description, created_ts, updated_ts, NULL
FROM tmp_legacy_system_configs
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `group` = VALUES(`group`),
    description = VALUES(description),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.system_configs
    (`key`, `value`, `group`, description, created_at, updated_at, deleted_at)
SELECT
    CONCAT('legacy_system_param_', p.para_name),
    JSON_OBJECT(
        'data0', p.para_data0,
        'data1', p.para_data1,
        'data2', p.para_data2,
        'data3', p.para_data3,
        'data4', p.para_data4,
        'data5', p.para_data5,
        'data6', p.para_data6,
        'remark', p.para_remark
    ),
    'legacy_param',
    CONCAT('Imported from hank_zl_data.system_param.', p.para_name),
    CASE WHEN p.rec_crt_date IS NULL OR p.rec_crt_date = '' OR p.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.rec_crt_date) END,
    CASE WHEN p.rec_upd_date IS NULL OR p.rec_upd_date = '' OR p.rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.rec_upd_date) END,
    NULL
FROM hank_zl_data.system_param p
WHERE p.voided = '1'
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `group` = VALUES(`group`),
    description = VALUES(description),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_mapped_configs;
CREATE TEMPORARY TABLE tmp_mapped_configs AS
SELECT 'deposit_enabled' cfg_key,
       CASE WHEN COALESCE((SELECT para_data0 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'GLOBALDEPOSITRULE' LIMIT 1), '0') = '0' THEN '1' ELSE '0' END cfg_value,
       'finance' cfg_group,
       'Mapped from GLOBALDEPOSITRULE' cfg_description
UNION ALL
SELECT 'withdrawal_enabled',
       CASE WHEN COALESCE((SELECT para_data0 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'GLOBALWITHDRAWRULE' LIMIT 1), '0') = '0' THEN '1' ELSE '0' END,
       'finance',
       'Mapped from GLOBALWITHDRAWRULE'
UNION ALL
SELECT 'deposit_start_time',
       SUBSTRING_INDEX(COALESCE(NULLIF((SELECT para_data1 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'DEPOSITRULE' LIMIT 1), ''), (SELECT para_data0 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'DEPOSITRULE' LIMIT 1), '00:00:00,23:59:59'), ',', 1),
       'finance',
       'Mapped from DEPOSITRULE weekday range'
UNION ALL
SELECT 'deposit_end_time',
       SUBSTRING_INDEX(COALESCE(NULLIF((SELECT para_data1 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'DEPOSITRULE' LIMIT 1), ''), (SELECT para_data0 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'DEPOSITRULE' LIMIT 1), '00:00:00,23:59:59'), ',', -1),
       'finance',
       'Mapped from DEPOSITRULE weekday range'
UNION ALL
SELECT 'withdrawal_start_time',
       SUBSTRING_INDEX(COALESCE(NULLIF((SELECT para_data1 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'WITHDRAWRULE' LIMIT 1), ''), (SELECT para_data0 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'WITHDRAWRULE' LIMIT 1), '09:00:00,16:30:00'), ',', 1),
       'finance',
       'Mapped from WITHDRAWRULE weekday range'
UNION ALL
SELECT 'withdrawal_end_time',
       SUBSTRING_INDEX(COALESCE(NULLIF((SELECT para_data1 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'WITHDRAWRULE' LIMIT 1), ''), (SELECT para_data0 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'WITHDRAWRULE' LIMIT 1), '09:00:00,16:30:00'), ',', -1),
       'finance',
       'Mapped from WITHDRAWRULE weekday range'
UNION ALL
SELECT 'deposit_weekend_enabled',
       CASE
           WHEN EXISTS (
               SELECT 1 FROM hank_zl_data.system_param
               WHERE voided = '1'
                 AND para_name = 'DEPOSITRULE'
                 AND (
                     (COALESCE(para_data0, '') <> '' AND para_data0 <> '00:00:00,00:00:00')
                     OR (COALESCE(para_data6, '') <> '' AND para_data6 <> '00:00:00,00:00:00')
                 )
           ) THEN '1'
           WHEN NOT EXISTS (SELECT 1 FROM hank_zl_data.system_param WHERE voided = '1' AND para_name = 'DEPOSITRULE') THEN '1'
           ELSE '0'
       END,
       'finance',
       'Mapped from DEPOSITRULE weekend ranges'
UNION ALL
SELECT 'deposit_min_amount',
       COALESCE(CAST((SELECT MIN(CAST(para_data1 AS DECIMAL(20,2))) FROM hank_zl_data.system_param WHERE voided = '1' AND para_name LIKE 'PAYMENT_CHANNEL_%' AND para_data0 = '1' AND CAST(para_data1 AS DECIMAL(20,2)) > 0) AS CHAR), '10'),
       'finance',
       'Minimum enabled legacy channel amount'
UNION ALL
SELECT 'deposit_max_amount',
       COALESCE(CAST((SELECT MAX(CASE CAST(SUBSTRING(para_name, 17) AS UNSIGNED)
           WHEN 1 THEN 6800 WHEN 2 THEN 30000 WHEN 3 THEN 80000 WHEN 4 THEN 500000 WHEN 5 THEN 500000
           WHEN 6 THEN 6800 WHEN 7 THEN 6800 WHEN 8 THEN 14000 WHEN 9 THEN 80000 WHEN 10 THEN 6800 WHEN 11 THEN 6800
           ELSE 500000 END)
           FROM hank_zl_data.system_param
           WHERE voided = '1' AND para_name LIKE 'PAYMENT_CHANNEL_%' AND para_data0 = '1') AS CHAR), '500000'),
       'finance',
       'Maximum enabled legacy channel amount'
UNION ALL
SELECT 'withdraw_min_amount', '300', 'finance', 'Legacy withdrawal page minimum amount'
UNION ALL
SELECT 'withdraw_max_amount', '500000', 'finance', 'Legacy withdrawal maximum amount fallback';

INSERT INTO co_crmv5.system_configs
    (`key`, `value`, `group`, description, created_at, updated_at, deleted_at)
SELECT cfg_key, cfg_value, cfg_group, cfg_description, @now, @now, NULL
FROM tmp_mapped_configs
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `group` = VALUES(`group`),
    description = VALUES(description),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_payment_channels;
CREATE TEMPORARY TABLE tmp_legacy_payment_channels AS
SELECT
    CAST(SUBSTRING(p.para_name, 17) AS UNSIGNED) AS legacy_id,
    p.*,
    CASE CAST(SUBSTRING(p.para_name, 17) AS UNSIGNED)
        WHEN 1 THEN 'front.channel_one'
        WHEN 2 THEN 'front.channel_two'
        WHEN 3 THEN 'front.channel_three'
        WHEN 4 THEN 'front.crypto_currency'
        WHEN 5 THEN 'front.crypto_currency_two'
        WHEN 6 THEN 'front.wechat_pay_one'
        WHEN 7 THEN 'front.alipay_one'
        WHEN 8 THEN 'front.channel_five'
        WHEN 9 THEN 'front.channel_six'
        WHEN 10 THEN 'front.alipay_two'
        WHEN 11 THEN 'front.wechat_pay_two'
        ELSE 'front.payment_channel'
    END AS label_key,
    CASE CAST(SUBSTRING(p.para_name, 17) AS UNSIGNED)
        WHEN 1 THEN 6800 WHEN 2 THEN 30000 WHEN 3 THEN 80000 WHEN 4 THEN 500000 WHEN 5 THEN 500000
        WHEN 6 THEN 6800 WHEN 7 THEN 6800 WHEN 8 THEN 14000 WHEN 9 THEN 80000 WHEN 10 THEN 6800 WHEN 11 THEN 6800
        ELSE 500000
    END AS max_amount
FROM hank_zl_data.system_param p
WHERE p.voided = '1'
  AND p.para_name LIKE 'PAYMENT_CHANNEL_%'
  AND CAST(SUBSTRING(p.para_name, 17) AS UNSIGNED) BETWEEN 1 AND 11;

UPDATE co_crmv5.payment_channels t
JOIN tmp_legacy_payment_channels p ON t.channel_code = CAST(p.legacy_id AS CHAR)
CROSS JOIN (SELECT * FROM hank_zl_data.system_config WHERE voided = '1' LIMIT 1) s
SET
    t.name = CONCAT('Legacy Channel ', p.legacy_id),
    t.exchange_rate = CASE p.legacy_id
        WHEN 4 THEN 1.0
        WHEN 5 THEN 1.0
        WHEN 1 THEN COALESCE(s.sys_deposit_rate, 7.0)
        WHEN 2 THEN COALESCE(s.sys_deposit_rate2, s.sys_deposit_rate, 7.0)
        WHEN 3 THEN COALESCE(s.sys_deposit_rate3, s.sys_deposit_rate, 7.0)
        WHEN 6 THEN COALESCE(s.sys_deposit_rate6, s.sys_deposit_rate, 7.0)
        WHEN 7 THEN COALESCE(s.sys_deposit_rate7, s.sys_deposit_rate, 7.0)
        WHEN 8 THEN COALESCE(s.sys_deposit_rate8, s.sys_deposit_rate, 7.0)
        WHEN 9 THEN COALESCE(s.sys_deposit_rate9, s.sys_deposit_rate, 7.0)
        WHEN 10 THEN COALESCE(s.sys_deposit_rate10, s.sys_deposit_rate, 7.0)
        WHEN 11 THEN COALESCE(s.sys_deposit_rate11, s.sys_deposit_rate, 7.0)
        ELSE COALESCE(s.sys_deposit_rate, 7.0)
    END,
    t.is_enabled = COALESCE(p.para_data0, 0),
    t.sort = COALESCE(p.para_data6, 100 - p.legacy_id),
    t.config = JSON_OBJECT(
        'label_key', p.label_key,
        'min_amount', CAST(COALESCE(p.para_data1, 0) AS DECIMAL(20,2)),
        'max_amount', p.max_amount,
        'daily_amount', p.para_data2,
        'is_default', COALESCE(p.para_data5, 0),
        'type', IF(p.legacy_id IN (4, 5), 'crypto', 'fiat'),
        'type_label_key', IF(p.legacy_id IN (4, 5), 'front.channel_type_crypto', 'front.channel_type_fiat'),
        'legacy_para_name', p.para_name
    ),
    t.created_at = CASE WHEN p.rec_crt_date IS NULL OR p.rec_crt_date = '' OR p.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.rec_crt_date) END,
    t.updated_at = CASE WHEN p.rec_upd_date IS NULL OR p.rec_upd_date = '' OR p.rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.rec_upd_date) END,
    t.deleted_at = NULL;

INSERT INTO co_crmv5.payment_channels
    (name, channel_code, exchange_rate, is_enabled, sort, config, created_at, updated_at, deleted_at)
SELECT
    CONCAT('Legacy Channel ', p.legacy_id),
    CAST(p.legacy_id AS CHAR),
    CASE p.legacy_id
        WHEN 4 THEN 1.0
        WHEN 5 THEN 1.0
        WHEN 1 THEN COALESCE(s.sys_deposit_rate, 7.0)
        WHEN 2 THEN COALESCE(s.sys_deposit_rate2, s.sys_deposit_rate, 7.0)
        WHEN 3 THEN COALESCE(s.sys_deposit_rate3, s.sys_deposit_rate, 7.0)
        WHEN 6 THEN COALESCE(s.sys_deposit_rate6, s.sys_deposit_rate, 7.0)
        WHEN 7 THEN COALESCE(s.sys_deposit_rate7, s.sys_deposit_rate, 7.0)
        WHEN 8 THEN COALESCE(s.sys_deposit_rate8, s.sys_deposit_rate, 7.0)
        WHEN 9 THEN COALESCE(s.sys_deposit_rate9, s.sys_deposit_rate, 7.0)
        WHEN 10 THEN COALESCE(s.sys_deposit_rate10, s.sys_deposit_rate, 7.0)
        WHEN 11 THEN COALESCE(s.sys_deposit_rate11, s.sys_deposit_rate, 7.0)
        ELSE COALESCE(s.sys_deposit_rate, 7.0)
    END,
    COALESCE(p.para_data0, 0),
    COALESCE(p.para_data6, 100 - p.legacy_id),
    JSON_OBJECT(
        'label_key', p.label_key,
        'min_amount', CAST(COALESCE(p.para_data1, 0) AS DECIMAL(20,2)),
        'max_amount', p.max_amount,
        'daily_amount', p.para_data2,
        'is_default', COALESCE(p.para_data5, 0),
        'type', IF(p.legacy_id IN (4, 5), 'crypto', 'fiat'),
        'type_label_key', IF(p.legacy_id IN (4, 5), 'front.channel_type_crypto', 'front.channel_type_fiat'),
        'legacy_para_name', p.para_name
    ),
    CASE WHEN p.rec_crt_date IS NULL OR p.rec_crt_date = '' OR p.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.rec_crt_date) END,
    CASE WHEN p.rec_upd_date IS NULL OR p.rec_upd_date = '' OR p.rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.rec_upd_date) END,
    NULL
FROM tmp_legacy_payment_channels p
CROSS JOIN (SELECT * FROM hank_zl_data.system_config WHERE voided = '1' LIMIT 1) s
WHERE NOT EXISTS (
    SELECT 1 FROM co_crmv5.payment_channels t WHERE t.channel_code = CAST(p.legacy_id AS CHAR)
);

UPDATE co_crmv5.payment_channels
SET is_enabled = 0, updated_at = @now
WHERE channel_code IN ('bank_transfer', 'usdt_trc20', 'quick_pay');

-- ---------------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_email_counts;
CREATE TEMPORARY TABLE tmp_legacy_email_counts AS
SELECT LOWER(TRIM(email)) AS email_key, COUNT(*) AS total
FROM (
    SELECT email FROM hank_zl_data.agents
    UNION ALL
    SELECT email FROM hank_zl_data.`user`
) e
WHERE email IS NOT NULL AND TRIM(email) <> ''
GROUP BY LOWER(TRIM(email));
ALTER TABLE tmp_legacy_email_counts ADD INDEX idx_email_key (email_key);

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_users_raw;
CREATE TEMPORARY TABLE tmp_legacy_users_raw AS
SELECT
    1 AS account_type,
    user_id, user_name, password, email, phone, sex, user_money, group_id, parent_id, family_tree,
    used_bond_money, available_bond_money, cust_vol, cust_eqy, cust_lvg, effective_cdt, risk_rate, bond_money,
    trans_mode, player_Id, mt4_code, mt4_grp, is_enc, enc_look, original_grp, voided, CAST(rec_crt_date AS CHAR) AS rec_crt_date, CAST(rec_upd_date AS CHAR) AS rec_upd_date,
    rec_crt_user, rec_upd_user, CAST(last_logindate AS CHAR) AS last_logindate, CAST(Last_landing_time AS CHAR) AS Last_landing_time, user_status, comm_prop, bank_no, bank_no_tmp,
    bank_class, bank_class_tmp, bank_img, bank_img_tmp, bank_info, bank_info_tmp, bank_status, bank_remarks,
    IDcard_no, IDcard_status, bank_synchro, IDcard_img, IDcard_negative, IDcard_remarks, enable, enable_readonly,
    is_out_money, is_allow_money, is_confirm_agents_lvg, country, city, state, address, rights, cycle, settlement_model,
    remark, local_enable, data_source, gift_allowed
FROM hank_zl_data.agents
UNION ALL
SELECT
    2 AS account_type,
    user_id, user_name, password, email, phone, sex, user_money, group_id, parent_id, family_tree,
    used_bond_money, available_bond_money, cust_vol, cust_eqy, cust_lvg, effective_cdt, risk_rate, bond_money,
    trans_mode, player_Id, mt4_code, mt4_grp, is_enc, enc_look, original_grp, voided, CAST(rec_crt_date AS CHAR) AS rec_crt_date, CAST(rec_upd_date AS CHAR) AS rec_upd_date,
    rec_crt_user, rec_upd_user, CAST(last_logindate AS CHAR) AS last_logindate, CAST(Last_landing_time AS CHAR) AS Last_landing_time, user_status, comm_prop, bank_no, bank_no_tmp,
    bank_class, bank_class_tmp, bank_img, bank_img_tmp, bank_info, bank_info_tmp, bank_status, bank_remarks,
    IDcard_no, IDcard_status, bank_synchro, IDcard_img, IDcard_negative, IDcard_remarks, enable, enable_readonly,
    is_out_money, is_allow_money, is_confirm_agents_lvg, country, city, state, address, rights, cycle, settlement_model,
    remark, local_enable, data_source, gift_allowed
FROM hank_zl_data.`user`;
ALTER TABLE tmp_legacy_users_raw ADD INDEX idx_user_id (user_id);

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_users;
CREATE TEMPORARY TABLE tmp_legacy_users AS
SELECT
    u.*,
    CASE WHEN u.voided = '1' THEN NULL ELSE u.updated_ts END AS deleted_ts,
    CASE
        WHEN u.email_key REGEXP '^[A-Z0-9._%+\\-]+@[A-Z0-9.\\-]+\\.[A-Z]{2,}$'
             AND COALESCE(ec.total, 0) = 1
             AND other_user.id IS NULL
        THEN u.email_key
        ELSE CONCAT('legacy', u.user_id, '@legacy.local')
    END AS migrated_email,
    CASE
        WHEN COALESCE(u.password, '') LIKE '$2y$%' OR COALESCE(u.password, '') LIKE '$2a$%' OR COALESCE(u.password, '') LIKE '$argon%' THEN u.password
        WHEN u.account_type = 1 AND u.user_id = 11 THEN @legacy_agent_11_password_hash
        WHEN COALESCE(u.password, '') = '' THEN @default_plain_password_hash
        ELSE @default_plain_password_hash
    END AS migrated_password
FROM (
    SELECT
        r.*,
        LOWER(TRIM(COALESCE(r.email, ''))) AS email_key,
        CASE WHEN r.rec_crt_date IS NULL OR r.rec_crt_date = '' OR r.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.rec_crt_date) END AS created_ts,
        CASE WHEN r.rec_upd_date IS NULL OR r.rec_upd_date = '' OR r.rec_upd_date LIKE '0000-00-00%' THEN
            CASE WHEN r.rec_crt_date IS NULL OR r.rec_crt_date = '' OR r.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.rec_crt_date) END
        ELSE UNIX_TIMESTAMP(r.rec_upd_date) END AS updated_ts
    FROM tmp_legacy_users_raw r
    WHERE r.user_id > 0
) u
LEFT JOIN tmp_legacy_email_counts ec ON ec.email_key = u.email_key
LEFT JOIN co_crmv5.user_logins other_user
    ON LOWER(other_user.email) = u.email_key AND other_user.user_id <> u.user_id;
ALTER TABLE tmp_legacy_users ADD INDEX idx_user_id (user_id);

UPDATE co_crmv5.user_logins t
JOIN tmp_legacy_users u ON t.user_id = u.user_id
SET
    t.email = u.migrated_email,
    t.password = u.migrated_password,
    t.account_type = u.account_type,
    t.is_enabled = IF(COALESCE(u.enable, 1) = 1 AND u.deleted_ts IS NULL, 1, 0),
    t.is_cancelled = IF(u.deleted_ts IS NULL, 0, 1),
    t.source_type = 1,
    t.jwt_token_id = '',
    t.last_login_ip = '',
    t.last_login_at = CASE
        WHEN COALESCE(u.last_logindate, u.Last_landing_time) IS NULL OR COALESCE(u.last_logindate, u.Last_landing_time) = '' OR COALESCE(u.last_logindate, u.Last_landing_time) LIKE '0000-00-00%' THEN NULL
        ELSE COALESCE(u.last_logindate, u.Last_landing_time)
    END,
    t.created_at = u.created_ts,
    t.updated_at = u.updated_ts,
    t.deleted_at = u.deleted_ts;

INSERT INTO co_crmv5.user_logins
    (user_id, email, password, account_type, is_enabled, is_cancelled, source_type, jwt_token_id, last_login_ip, last_login_at, created_at, updated_at, deleted_at)
SELECT
    u.user_id,
    u.migrated_email,
    u.migrated_password,
    u.account_type,
    IF(COALESCE(u.enable, 1) = 1 AND u.deleted_ts IS NULL, 1, 0),
    IF(u.deleted_ts IS NULL, 0, 1),
    1,
    '',
    '',
    CASE
        WHEN COALESCE(u.last_logindate, u.Last_landing_time) IS NULL OR COALESCE(u.last_logindate, u.Last_landing_time) = '' OR COALESCE(u.last_logindate, u.Last_landing_time) LIKE '0000-00-00%' THEN NULL
        ELSE COALESCE(u.last_logindate, u.Last_landing_time)
    END,
    u.created_ts,
    u.updated_ts,
    u.deleted_ts
FROM tmp_legacy_users u
WHERE NOT EXISTS (
    SELECT 1 FROM co_crmv5.user_logins t WHERE t.user_id = u.user_id
);

UPDATE co_crmv5.user_infos t
JOIN tmp_legacy_users u ON t.user_id = u.user_id
JOIN co_crmv5.user_logins l ON l.user_id = u.user_id
LEFT JOIN co_crmv5.group_configs gc ON gc.pair_id = u.group_id
SET
    t.login_id = l.id,
    t.user_name = IF(TRIM(COALESCE(u.user_name, '')) = '', CONCAT('Legacy User ', u.user_id), TRIM(u.user_name)),
    t.phone = TRIM(COALESCE(u.phone, '')),
    t.gender = IF(LOWER(TRIM(COALESCE(u.sex, '1'))) IN ('2', 'f', 'female'), 2, 1),
    t.avatar = NULL,
    t.level_id = IF(u.account_type <> 1, 0, COALESCE((SELECT id FROM co_crmv5.agent_levels WHERE level_code = 5 LIMIT 1), (SELECT id FROM co_crmv5.agent_levels WHERE level_code = 1 LIMIT 1), (SELECT MIN(id) FROM co_crmv5.agent_levels), 0)),
    t.group_id = COALESCE(gc.id, (SELECT id FROM co_crmv5.group_configs WHERE category = u.account_type AND is_default = 1 ORDER BY id LIMIT 1), (SELECT id FROM co_crmv5.group_configs WHERE category = u.account_type ORDER BY id LIMIT 1), 0),
    t.parent_id = COALESCE(u.parent_id, 0),
    t.account_type = u.account_type,
    t.family_tree = IF(TRIM(COALESCE(u.family_tree, '')) = '', CAST(u.user_id AS CHAR), TRIM(u.family_tree)),
    t.total_funds = ROUND(COALESCE(u.user_money, 0), 2),
    t.used_margin = ROUND(COALESCE(u.used_bond_money, 0), 2),
    t.avail_margin = ROUND(COALESCE(u.available_bond_money, 0), 2),
    t.equity = ROUND(COALESCE(u.cust_eqy, 0), 2),
    t.effective_credit = ROUND(COALESCE(u.effective_cdt, 0), 2),
    t.risk_ratio = ROUND(COALESCE(u.risk_rate, 0), 2),
    t.margin_amount = ROUND(COALESCE(u.bond_money, 0), 2),
    t.leverage = CAST(ROUND(COALESCE(u.cust_lvg, 0), 2) AS SIGNED),
    t.cust_vol = TRIM(COALESCE(u.cust_vol, '0')),
    t.pay_provider_id = COALESCE(u.player_Id, 0),
    t.equity_ratio = COALESCE(u.rights, 0),
    t.comm_rate = COALESCE(u.comm_prop, 0),
    t.is_ecn = COALESCE(u.is_enc, 0),
    t.follow_parent_ecn = COALESCE(u.enc_look, 0),
    t.auth_status = IF(COALESCE(u.user_status, '0') = '1', 1, 0),
    t.is_mt4_synced = 1,
    t.is_mt4_enabled = IF(COALESCE(u.enable, 1) = 1, 1, 0),
    t.is_mt4_readonly = COALESCE(u.enable_readonly, 0),
    t.is_withdrawal_allowed = COALESCE(u.is_out_money, 0),
    t.is_deposit_allowed = COALESCE(u.is_allow_money, 0),
    t.is_agent_confirmed = COALESCE(u.is_confirm_agents_lvg, 0),
    t.original_group = TRIM(COALESCE(u.original_grp, '')),
    t.mt4_group = TRIM(COALESCE(u.mt4_grp, '')),
    t.mt4_code = COALESCE(u.mt4_code, 0),
    t.trading_mode = COALESCE(u.trans_mode, 0),
    t.settle_method = COALESCE(u.settlement_model, 0),
    t.settle_cycle = COALESCE(u.cycle, 0),
    t.country = TRIM(COALESCE(u.country, '')),
    t.city = TRIM(COALESCE(u.city, '')),
    t.state = TRIM(COALESCE(u.state, '')),
    t.address = TRIM(COALESCE(u.address, '')),
    t.is_gift_allowed = COALESCE(u.gift_allowed, 0),
    t.data_source = COALESCE(u.data_source, 1),
    t.remark = TRIM(COALESCE(u.remark, '')),
    t.created_by = 0,
    t.updated_by = 0,
    t.created_at = u.created_ts,
    t.updated_at = u.updated_ts,
    t.deleted_at = u.deleted_ts;

INSERT INTO co_crmv5.user_infos
    (user_id, login_id, user_name, phone, gender, avatar, level_id, group_id, parent_id, account_type, family_tree,
     total_funds, used_margin, avail_margin, equity, effective_credit, risk_ratio, margin_amount, leverage, cust_vol,
     pay_provider_id, equity_ratio, comm_rate, is_ecn, follow_parent_ecn, auth_status, is_mt4_synced, is_mt4_enabled,
     is_mt4_readonly, is_withdrawal_allowed, is_deposit_allowed, is_agent_confirmed, original_group, mt4_group, mt4_code,
     trading_mode, settle_method, settle_cycle, country, city, state, address, is_gift_allowed, data_source, remark,
     created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    u.user_id,
    l.id,
    IF(TRIM(COALESCE(u.user_name, '')) = '', CONCAT('Legacy User ', u.user_id), TRIM(u.user_name)),
    TRIM(COALESCE(u.phone, '')),
    IF(LOWER(TRIM(COALESCE(u.sex, '1'))) IN ('2', 'f', 'female'), 2, 1),
    NULL,
    IF(u.account_type <> 1, 0, COALESCE((SELECT id FROM co_crmv5.agent_levels WHERE level_code = 5 LIMIT 1), (SELECT id FROM co_crmv5.agent_levels WHERE level_code = 1 LIMIT 1), (SELECT MIN(id) FROM co_crmv5.agent_levels), 0)),
    COALESCE(gc.id, (SELECT id FROM co_crmv5.group_configs WHERE category = u.account_type AND is_default = 1 ORDER BY id LIMIT 1), (SELECT id FROM co_crmv5.group_configs WHERE category = u.account_type ORDER BY id LIMIT 1), 0),
    COALESCE(u.parent_id, 0),
    u.account_type,
    IF(TRIM(COALESCE(u.family_tree, '')) = '', CAST(u.user_id AS CHAR), TRIM(u.family_tree)),
    ROUND(COALESCE(u.user_money, 0), 2),
    ROUND(COALESCE(u.used_bond_money, 0), 2),
    ROUND(COALESCE(u.available_bond_money, 0), 2),
    ROUND(COALESCE(u.cust_eqy, 0), 2),
    ROUND(COALESCE(u.effective_cdt, 0), 2),
    ROUND(COALESCE(u.risk_rate, 0), 2),
    ROUND(COALESCE(u.bond_money, 0), 2),
    CAST(ROUND(COALESCE(u.cust_lvg, 0), 2) AS SIGNED),
    TRIM(COALESCE(u.cust_vol, '0')),
    COALESCE(u.player_Id, 0),
    COALESCE(u.rights, 0),
    COALESCE(u.comm_prop, 0),
    COALESCE(u.is_enc, 0),
    COALESCE(u.enc_look, 0),
    IF(COALESCE(u.user_status, '0') = '1', 1, 0),
    1,
    IF(COALESCE(u.enable, 1) = 1, 1, 0),
    COALESCE(u.enable_readonly, 0),
    COALESCE(u.is_out_money, 0),
    COALESCE(u.is_allow_money, 0),
    COALESCE(u.is_confirm_agents_lvg, 0),
    TRIM(COALESCE(u.original_grp, '')),
    TRIM(COALESCE(u.mt4_grp, '')),
    COALESCE(u.mt4_code, 0),
    COALESCE(u.trans_mode, 0),
    COALESCE(u.settlement_model, 0),
    COALESCE(u.cycle, 0),
    TRIM(COALESCE(u.country, '')),
    TRIM(COALESCE(u.city, '')),
    TRIM(COALESCE(u.state, '')),
    TRIM(COALESCE(u.address, '')),
    COALESCE(u.gift_allowed, 0),
    COALESCE(u.data_source, 1),
    TRIM(COALESCE(u.remark, '')),
    0,
    0,
    u.created_ts,
    u.updated_ts,
    u.deleted_ts
FROM tmp_legacy_users u
JOIN co_crmv5.user_logins l ON l.user_id = u.user_id
LEFT JOIN co_crmv5.group_configs gc ON gc.pair_id = u.group_id
WHERE NOT EXISTS (
    SELECT 1 FROM co_crmv5.user_infos t WHERE t.user_id = u.user_id
);

UPDATE co_crmv5.user_auths t
JOIN tmp_legacy_users u ON t.user_id = u.user_id
SET
    t.bank_no = TRIM(COALESCE(u.bank_no, '')),
    t.bank_no_tmp = TRIM(COALESCE(u.bank_no_tmp, '')),
    t.bank_name = TRIM(COALESCE(u.bank_class, '')),
    t.bank_name_tmp = TRIM(COALESCE(u.bank_class_tmp, '')),
    t.bank_card_img = TRIM(COALESCE(u.bank_img, '')),
    t.bank_card_img_tmp = TRIM(COALESCE(u.bank_img_tmp, '')),
    t.bank_addr = TRIM(COALESCE(u.bank_info, '')),
    t.bank_addr_tmp = TRIM(COALESCE(u.bank_info_tmp, '')),
    t.bank_status = COALESCE(u.bank_status, 0),
    t.bank_remarks = TRIM(COALESCE(u.bank_remarks, '')),
    t.id_card_no = TRIM(COALESCE(u.IDcard_no, '')),
    t.id_card_status = COALESCE(u.IDcard_status, 0),
    t.id_card_front = TRIM(COALESCE(u.IDcard_img, '')),
    t.id_card_back = TRIM(COALESCE(u.IDcard_negative, '')),
    t.id_card_remarks = TRIM(COALESCE(u.IDcard_remarks, '')),
    t.is_bank_synced = COALESCE(u.bank_synchro, 0),
    t.created_at = u.created_ts,
    t.updated_at = u.updated_ts,
    t.deleted_at = u.deleted_ts;

INSERT INTO co_crmv5.user_auths
    (user_id, bank_no, bank_no_tmp, bank_name, bank_name_tmp, bank_card_img, bank_card_img_tmp, bank_addr, bank_addr_tmp,
     bank_status, bank_remarks, id_card_no, id_card_status, id_card_front, id_card_back, id_card_remarks, is_bank_synced,
     created_at, updated_at, deleted_at)
SELECT
    u.user_id,
    TRIM(COALESCE(u.bank_no, '')),
    TRIM(COALESCE(u.bank_no_tmp, '')),
    TRIM(COALESCE(u.bank_class, '')),
    TRIM(COALESCE(u.bank_class_tmp, '')),
    TRIM(COALESCE(u.bank_img, '')),
    TRIM(COALESCE(u.bank_img_tmp, '')),
    TRIM(COALESCE(u.bank_info, '')),
    TRIM(COALESCE(u.bank_info_tmp, '')),
    COALESCE(u.bank_status, 0),
    TRIM(COALESCE(u.bank_remarks, '')),
    TRIM(COALESCE(u.IDcard_no, '')),
    COALESCE(u.IDcard_status, 0),
    TRIM(COALESCE(u.IDcard_img, '')),
    TRIM(COALESCE(u.IDcard_negative, '')),
    TRIM(COALESCE(u.IDcard_remarks, '')),
    COALESCE(u.bank_synchro, 0),
    u.created_ts,
    u.updated_ts,
    u.deleted_ts
FROM tmp_legacy_users u
WHERE NOT EXISTS (
    SELECT 1 FROM co_crmv5.user_auths t WHERE t.user_id = u.user_id
);

-- ---------------------------------------------------------------------------
-- Agent descendants
-- ---------------------------------------------------------------------------

INSERT INTO co_crmv5.agent_descendants
    (agent_id, descendant_id, descendant_type, is_direct, depth, created_at, updated_at, deleted_at)
SELECT
    ar.parent_id,
    ar.child_id,
    d.account_type,
    IF(d.parent_id = ar.parent_id, 1, 0),
    CASE
        WHEN FIND_IN_SET(ar.parent_id, d.family_tree) > 0 AND FIND_IN_SET(ar.child_id, d.family_tree) > FIND_IN_SET(ar.parent_id, d.family_tree)
        THEN FIND_IN_SET(ar.child_id, d.family_tree) - FIND_IN_SET(ar.parent_id, d.family_tree)
        WHEN d.parent_id = ar.parent_id THEN 1
        ELSE 2
    END,
    @now,
    @now,
    NULL
FROM hank_zl_data.agent_relations ar
JOIN co_crmv5.user_infos a ON a.user_id = ar.parent_id AND a.account_type = 1
JOIN co_crmv5.user_infos d ON d.user_id = ar.child_id
WHERE ar.parent_id > 0
  AND ar.child_id > 0
  AND ar.parent_id <> ar.child_id
ON DUPLICATE KEY UPDATE
    descendant_type = VALUES(descendant_type),
    is_direct = VALUES(is_direct),
    depth = VALUES(depth),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.agent_descendants
    (agent_id, descendant_id, descendant_type, is_direct, depth, created_at, updated_at, deleted_at)
SELECT
    jt.agent_id,
    d.user_id,
    d.account_type,
    IF(d.parent_id = jt.agent_id, 1, 0),
    GREATEST(1, self_pos.self_ordinal - jt.agent_ordinal),
    @now,
    @now,
    NULL
FROM co_crmv5.user_infos d
JOIN JSON_TABLE(
    CASE
        WHEN TRIM(COALESCE(d.family_tree, '')) = '' THEN CONCAT('[', IF(d.parent_id > 0, CONCAT(d.parent_id, ','), ''), d.user_id, ']')
        ELSE CONCAT('[', TRIM(BOTH ',' FROM d.family_tree), ']')
    END,
    '$[*]' COLUMNS (
        agent_ordinal FOR ORDINALITY,
        agent_id INT PATH '$'
    )
) jt
JOIN JSON_TABLE(
    CASE
        WHEN TRIM(COALESCE(d.family_tree, '')) = '' THEN CONCAT('[', IF(d.parent_id > 0, CONCAT(d.parent_id, ','), ''), d.user_id, ']')
        ELSE CONCAT('[', TRIM(BOTH ',' FROM d.family_tree), ']')
    END,
    '$[*]' COLUMNS (
        self_ordinal FOR ORDINALITY,
        node_id INT PATH '$'
    )
) self_pos ON self_pos.node_id = d.user_id
JOIN co_crmv5.user_infos a ON a.user_id = jt.agent_id AND a.account_type = 1
WHERE jt.agent_id > 0
  AND jt.agent_id <> d.user_id
  AND self_pos.self_ordinal > jt.agent_ordinal
ON DUPLICATE KEY UPDATE
    descendant_type = VALUES(descendant_type),
    is_direct = VALUES(is_direct),
    depth = VALUES(depth),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

-- ---------------------------------------------------------------------------
-- Business records
-- ---------------------------------------------------------------------------

INSERT INTO co_crmv5.deposit_records
    (id, user_id, user_name, mt4_ticket, amount, actual_amount, exchange_rate, channel_name, channel_order_no,
     local_order_no, status, payment_time, remarks, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    d.dep_id,
    CAST(COALESCE(REGEXP_SUBSTR(d.dep_body, '[0-9]+'), '0') AS UNSIGNED),
    COALESCE(ui.user_name, ''),
    COALESCE(d.dep_mt4_id, 0),
    ROUND(COALESCE(d.dep_act_amount, 0), 2),
    ROUND(COALESCE(d.dep_amount, 0), 2),
    ROUND(COALESCE(d.dep_amt_rate, 0), 2),
    TRIM(COALESCE(d.dep_channel, '')),
    TRIM(COALESCE(d.dep_channel_no, '')),
    IF(TRIM(COALESCE(d.dep_outTrande, '')) = '', CONCAT('LEGACY-DEP-', d.dep_id), TRIM(d.dep_outTrande)),
    IF(TRIM(COALESCE(d.dep_status, '')) = '', '01', TRIM(d.dep_status)),
    CASE WHEN d.rec_upd_date IS NULL OR d.rec_upd_date = '' OR d.rec_upd_date LIKE '0000-00-00%' THEN NULL ELSE d.rec_upd_date END,
    TRIM(COALESCE(d.dep_body, '')),
    TRIM(COALESCE(d.rec_crt_user, '')),
    TRIM(COALESCE(d.rec_upd_user, '')),
    CASE WHEN d.rec_crt_date IS NULL OR d.rec_crt_date = '' OR d.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(d.rec_crt_date) END,
    CASE WHEN d.rec_upd_date IS NULL OR d.rec_upd_date = '' OR d.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN d.rec_crt_date IS NULL OR d.rec_crt_date = '' OR d.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(d.rec_crt_date) END ELSE UNIX_TIMESTAMP(d.rec_upd_date) END,
    NULL
FROM hank_zl_data.deposit_record_log d
LEFT JOIN co_crmv5.user_infos ui ON ui.user_id = CAST(COALESCE(REGEXP_SUBSTR(d.dep_body, '[0-9]+'), '0') AS UNSIGNED)
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    user_name = VALUES(user_name),
    mt4_ticket = VALUES(mt4_ticket),
    amount = VALUES(amount),
    actual_amount = VALUES(actual_amount),
    exchange_rate = VALUES(exchange_rate),
    channel_name = VALUES(channel_name),
    channel_order_no = VALUES(channel_order_no),
    local_order_no = VALUES(local_order_no),
    status = VALUES(status),
    payment_time = VALUES(payment_time),
    remarks = VALUES(remarks),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.withdraw_records
    (id, user_id, user_name, mt4_ticket, apply_amount, actual_amount, fee, exchange_rate, rmb_fee, bank_no, bank_name,
     bank_addr, status, local_order_no, third_order_no, reject_reason, mt4_return_status, created_by, updated_by,
     created_at, updated_at, deleted_at)
SELECT
    w.record_id,
    COALESCE(w.user_id, 0),
    TRIM(COALESCE(w.user_name, '')),
    TRIM(COALESCE(w.mt4_trades_no, '')),
    ROUND(COALESCE(w.apply_amount, 0), 2),
    ROUND(COALESCE(w.act_apply_amount, w.apply_amount, 0), 2),
    ROUND(COALESCE(w.draw_poundage, 0), 2),
    ROUND(COALESCE(w.draw_rate, 0), 2),
    ROUND(COALESCE(w.act_pdg_rmb, 0), 2),
    TRIM(COALESCE(w.draw_bank_no, '')),
    TRIM(COALESCE(w.draw_bank_class, '')),
    TRIM(COALESCE(w.draw_bank_info, '')),
    COALESCE(w.apply_status, 0),
    IF(TRIM(COALESCE(w.orderId_LOC, '')) = '', CONCAT('LEGACY-WDR-', w.record_id), TRIM(w.orderId_LOC)),
    TRIM(COALESCE(w.orderId_OTC, '')),
    TRIM(COALESCE(w.apply_remark, '')),
    TRIM(COALESCE(w.mt4_return_status, '')),
    TRIM(COALESCE(w.rec_crt_user, '')),
    TRIM(COALESCE(w.rec_upd_user, '')),
    CASE WHEN w.rec_crt_date IS NULL OR w.rec_crt_date = '' OR w.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(w.rec_crt_date) END,
    CASE WHEN w.rec_upd_date IS NULL OR w.rec_upd_date = '' OR w.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN w.rec_crt_date IS NULL OR w.rec_crt_date = '' OR w.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(w.rec_crt_date) END ELSE UNIX_TIMESTAMP(w.rec_upd_date) END,
    NULL
FROM hank_zl_data.draw_record_log w
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    user_name = VALUES(user_name),
    mt4_ticket = VALUES(mt4_ticket),
    apply_amount = VALUES(apply_amount),
    actual_amount = VALUES(actual_amount),
    fee = VALUES(fee),
    exchange_rate = VALUES(exchange_rate),
    rmb_fee = VALUES(rmb_fee),
    bank_no = VALUES(bank_no),
    bank_name = VALUES(bank_name),
    bank_addr = VALUES(bank_addr),
    status = VALUES(status),
    local_order_no = VALUES(local_order_no),
    third_order_no = VALUES(third_order_no),
    reject_reason = VALUES(reject_reason),
    mt4_return_status = VALUES(mt4_return_status),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.user_trades
    (id, user_id, ticket, symbol, digits, cmd, volume, open_time, open_price, stop_loss, take_profit, close_time,
     expiration, reason, conv_rate1, conv_rate2, commission, commission_agent, swaps, close_price, profit, taxes,
     comment, internal_id, margin_rate, timestamp_val, magic, gw_volume, gw_open_price, gw_close_price, modify_time,
     settlement_status, settled_at, created_at, updated_at, deleted_at)
SELECT
    t.trades_id,
    COALESCE(t.user_id, 0),
    COALESCE(t.ticket, 0),
    TRIM(COALESCE(t.symbol, '')),
    COALESCE(t.digits, 0),
    COALESCE(t.cmd, 0),
    COALESCE(t.volume, 0),
    CASE WHEN t.open_time IS NULL OR t.open_time = '' OR t.open_time LIKE '0000-00-00%' THEN FROM_UNIXTIME(@now) ELSE t.open_time END,
    ROUND(COALESCE(t.open_price, 0), 2),
    ROUND(COALESCE(t.stop_loss, 0), 2),
    ROUND(COALESCE(t.take_profit, 0), 2),
    CASE WHEN t.close_time IS NULL OR t.close_time = '' OR t.close_time LIKE '0000-00-00%' THEN '1970-01-01 00:00:00' ELSE t.close_time END,
    CASE WHEN t.expiration IS NULL OR t.expiration = '' OR t.expiration LIKE '0000-00-00%' THEN NULL ELSE t.expiration END,
    COALESCE(t.reason, 0),
    ROUND(COALESCE(t.conv_rate1, 0), 2),
    ROUND(COALESCE(t.conv_rate2, 0), 2),
    ROUND(COALESCE(t.commission, 0), 2),
    ROUND(COALESCE(t.commission_agent, 0), 2),
    ROUND(COALESCE(t.swaps, 0), 2),
    ROUND(COALESCE(t.close_price, 0), 2),
    ROUND(COALESCE(t.profit, 0), 2),
    ROUND(COALESCE(t.taxes, 0), 2),
    TRIM(COALESCE(t.comment, '')),
    COALESCE(t.internal_id, 0),
    ROUND(COALESCE(t.margin_rate, 0), 2),
    COALESCE(t.`timestamp`, 0),
    COALESCE(t.magic, 0),
    COALESCE(t.gw_volume, 0),
    COALESCE(t.gw_open_price, 0),
    COALESCE(t.gw_close_price, 0),
    CASE WHEN t.modify_time IS NULL OR t.modify_time = '' OR t.modify_time LIKE '0000-00-00%' THEN CASE WHEN t.open_time IS NULL OR t.open_time = '' OR t.open_time LIKE '0000-00-00%' THEN FROM_UNIXTIME(@now) ELSE t.open_time END ELSE t.modify_time END,
    IF(CASE WHEN t.close_time IS NULL OR t.close_time = '' OR t.close_time LIKE '0000-00-00%' THEN '1970-01-01 00:00:00' ELSE t.close_time END = '1970-01-01 00:00:00', 0, 1),
    CASE WHEN t.rec_comp_date IS NULL OR t.rec_comp_date = '' OR t.rec_comp_date LIKE '0000-00-00%' THEN NULL ELSE t.rec_comp_date END,
    CASE WHEN t.open_time IS NULL OR t.open_time = '' OR t.open_time LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.open_time) END,
    CASE WHEN t.modify_time IS NULL OR t.modify_time = '' OR t.modify_time LIKE '0000-00-00%' THEN CASE WHEN t.open_time IS NULL OR t.open_time = '' OR t.open_time LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.open_time) END ELSE UNIX_TIMESTAMP(t.modify_time) END,
    CASE WHEN t.voided = '1' THEN NULL ELSE CASE WHEN t.modify_time IS NULL OR t.modify_time = '' OR t.modify_time LIKE '0000-00-00%' THEN CASE WHEN t.open_time IS NULL OR t.open_time = '' OR t.open_time LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.open_time) END ELSE UNIX_TIMESTAMP(t.modify_time) END END
FROM hank_zl_data.user_trades t
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    ticket = VALUES(ticket),
    symbol = VALUES(symbol),
    digits = VALUES(digits),
    cmd = VALUES(cmd),
    volume = VALUES(volume),
    open_time = VALUES(open_time),
    open_price = VALUES(open_price),
    stop_loss = VALUES(stop_loss),
    take_profit = VALUES(take_profit),
    close_time = VALUES(close_time),
    expiration = VALUES(expiration),
    reason = VALUES(reason),
    conv_rate1 = VALUES(conv_rate1),
    conv_rate2 = VALUES(conv_rate2),
    commission = VALUES(commission),
    commission_agent = VALUES(commission_agent),
    swaps = VALUES(swaps),
    close_price = VALUES(close_price),
    profit = VALUES(profit),
    taxes = VALUES(taxes),
    comment = VALUES(comment),
    internal_id = VALUES(internal_id),
    margin_rate = VALUES(margin_rate),
    timestamp_val = VALUES(timestamp_val),
    magic = VALUES(magic),
    gw_volume = VALUES(gw_volume),
    gw_open_price = VALUES(gw_open_price),
    gw_close_price = VALUES(gw_close_price),
    modify_time = VALUES(modify_time),
    settlement_status = VALUES(settlement_status),
    settled_at = VALUES(settled_at),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.voucher_infos
    (id, user_id, images, remarks, review_status, review_message, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    v.id,
    COALESCE(v.user_id, 0),
    TRIM(COALESCE(v.imgs, '')),
    TRIM(COALESCE(v.remarks, '')),
    COALESCE(v.review_status, 0),
    TRIM(COALESCE(v.review_msg, '')),
    TRIM(COALESCE(v.rec_crt_user, '')),
    TRIM(COALESCE(v.rec_upd_user, '')),
    CASE WHEN v.rec_crt_date IS NULL OR v.rec_crt_date = '' OR v.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(v.rec_crt_date) END,
    CASE WHEN v.rec_upd_date IS NULL OR v.rec_upd_date = '' OR v.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN v.rec_crt_date IS NULL OR v.rec_crt_date = '' OR v.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(v.rec_crt_date) END ELSE UNIX_TIMESTAMP(v.rec_upd_date) END,
    NULL
FROM hank_zl_data.voucher_info v
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    images = VALUES(images),
    remarks = VALUES(remarks),
    review_status = VALUES(review_status),
    review_message = VALUES(review_message),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.user_addresses
    (id, user_id, recipient_name, recipient_phone, recipient_address, is_default, created_at, updated_at, deleted_at)
SELECT
    a.id,
    COALESCE(a.user_id, 0),
    TRIM(COALESCE(a.recipient_name, '')),
    TRIM(COALESCE(a.recipient_phone, '')),
    TRIM(COALESCE(a.recipient_address, '')),
    COALESCE(a.is_default, 0),
    CASE WHEN a.created_at IS NULL OR a.created_at = '' OR a.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(a.created_at) END,
    CASE WHEN a.updated_at IS NULL OR a.updated_at = '' OR a.updated_at LIKE '0000-00-00%' THEN CASE WHEN a.created_at IS NULL OR a.created_at = '' OR a.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(a.created_at) END ELSE UNIX_TIMESTAMP(a.updated_at) END,
    CASE WHEN a.deleted_at IS NULL OR a.deleted_at = '' OR a.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(a.deleted_at) END
FROM hank_zl_data.user_addresses a
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    recipient_name = VALUES(recipient_name),
    recipient_phone = VALUES(recipient_phone),
    recipient_address = VALUES(recipient_address),
    is_default = VALUES(is_default),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.gift_shipments
    (id, user_id, address_id, recipient_name, recipient_phone, recipient_address, sender_name, tracking_number, gift_name,
     gift_quantity, status, remark, admin_id, shipped_at, created_at, updated_at, deleted_at)
SELECT
    g.id,
    COALESCE(g.user_id, 0),
    COALESCE(g.address_id, 0),
    TRIM(COALESCE(g.recipient_name, '')),
    TRIM(COALESCE(g.recipient_phone, '')),
    TRIM(COALESCE(g.recipient_address, '')),
    TRIM(COALESCE(g.sender_name, '')),
    TRIM(COALESCE(g.tracking_number, '')),
    TRIM(COALESCE(g.gift_name, '')),
    COALESCE(g.gift_quantity, 0),
    COALESCE(g.status, 0),
    TRIM(COALESCE(g.remark, '')),
    COALESCE(g.admin_id, 0),
    CASE WHEN g.shipped_at IS NULL OR g.shipped_at = '' OR g.shipped_at LIKE '0000-00-00%' THEN NULL ELSE g.shipped_at END,
    CASE WHEN g.created_at IS NULL OR g.created_at = '' OR g.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(g.created_at) END,
    CASE WHEN g.updated_at IS NULL OR g.updated_at = '' OR g.updated_at LIKE '0000-00-00%' THEN CASE WHEN g.created_at IS NULL OR g.created_at = '' OR g.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(g.created_at) END ELSE UNIX_TIMESTAMP(g.updated_at) END,
    CASE WHEN g.deleted_at IS NULL OR g.deleted_at = '' OR g.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(g.deleted_at) END
FROM hank_zl_data.gift_shipments g
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    address_id = VALUES(address_id),
    recipient_name = VALUES(recipient_name),
    recipient_phone = VALUES(recipient_phone),
    recipient_address = VALUES(recipient_address),
    sender_name = VALUES(sender_name),
    tracking_number = VALUES(tracking_number),
    gift_name = VALUES(gift_name),
    gift_quantity = VALUES(gift_quantity),
    status = VALUES(status),
    remark = VALUES(remark),
    admin_id = VALUES(admin_id),
    shipped_at = VALUES(shipped_at),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.trans_apply_logs
    (id, user_id, origin_group_id, group_id, group_name, applicant_id, applicant_name, status, apply_reason,
     reject_reason, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    t.trans_id,
    COALESCE(t.trans_uid, 0),
    0,
    COALESCE(t.trans_type_gid, 0),
    TRIM(COALESCE(t.trans_type_name, '')),
    COALESCE(t.trans_apply_uid, 0),
    TRIM(COALESCE(t.trans_apply_uname, '')),
    COALESCE(t.trans_apply_status, 0),
    '',
    TRIM(COALESCE(t.trans_apply_reason, '')),
    TRIM(COALESCE(t.rec_crt_user, '')),
    TRIM(COALESCE(t.rec_upd_user, '')),
    CASE WHEN t.rec_crt_date IS NULL OR t.rec_crt_date = '' OR t.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.rec_crt_date) END,
    CASE WHEN t.rec_upd_date IS NULL OR t.rec_upd_date = '' OR t.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN t.rec_crt_date IS NULL OR t.rec_crt_date = '' OR t.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.rec_crt_date) END ELSE UNIX_TIMESTAMP(t.rec_upd_date) END,
    CASE WHEN t.voided = '1' THEN NULL ELSE CASE WHEN t.rec_upd_date IS NULL OR t.rec_upd_date = '' OR t.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN t.rec_crt_date IS NULL OR t.rec_crt_date = '' OR t.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.rec_crt_date) END ELSE UNIX_TIMESTAMP(t.rec_upd_date) END END
FROM hank_zl_data.trans_apply_log t
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    origin_group_id = VALUES(origin_group_id),
    group_id = VALUES(group_id),
    group_name = VALUES(group_name),
    applicant_id = VALUES(applicant_id),
    applicant_name = VALUES(applicant_name),
    status = VALUES(status),
    apply_reason = VALUES(apply_reason),
    reject_reason = VALUES(reject_reason),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.news
    (id, title, content, image, author_id, author_name, is_published, created_at, updated_at, deleted_at)
SELECT
    n.news_id,
    TRIM(COALESCE(n.news_title, '')),
    COALESCE(n.news_content, ''),
    NULL,
    0,
    TRIM(COALESCE(NULLIF(n.news_user, ''), n.rec_crt_user, '')),
    1,
    CASE WHEN n.rec_crt_date IS NULL OR n.rec_crt_date = '' OR n.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(n.rec_crt_date) END,
    CASE WHEN n.rec_upd_date IS NULL OR n.rec_upd_date = '' OR n.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN n.rec_crt_date IS NULL OR n.rec_crt_date = '' OR n.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(n.rec_crt_date) END ELSE UNIX_TIMESTAMP(n.rec_upd_date) END,
    NULL
FROM hank_zl_data.newslist n
WHERE n.voided = '1'
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content = VALUES(content),
    image = VALUES(image),
    author_id = VALUES(author_id),
    author_name = VALUES(author_name),
    is_published = VALUES(is_published),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

UPDATE co_crmv5.news_langs nl
JOIN hank_zl_data.newslist n ON n.news_id = nl.news_id
SET
    nl.title = TRIM(COALESCE(n.news_title, '')),
    nl.content = COALESCE(n.news_content, ''),
    nl.created_at = CASE WHEN n.rec_crt_date IS NULL OR n.rec_crt_date = '' OR n.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(n.rec_crt_date) END,
    nl.updated_at = CASE WHEN n.rec_upd_date IS NULL OR n.rec_upd_date = '' OR n.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN n.rec_crt_date IS NULL OR n.rec_crt_date = '' OR n.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(n.rec_crt_date) END ELSE UNIX_TIMESTAMP(n.rec_upd_date) END,
    nl.deleted_at = NULL
WHERE n.voided = '1'
  AND nl.lang_code IN ('zh-CN', 'zh_CN', 'en');

INSERT INTO co_crmv5.news_langs
    (news_id, lang_code, title, content, created_at, updated_at, deleted_at)
SELECT
    n.news_id,
    locales.lang_code,
    TRIM(COALESCE(n.news_title, '')),
    COALESCE(n.news_content, ''),
    CASE WHEN n.rec_crt_date IS NULL OR n.rec_crt_date = '' OR n.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(n.rec_crt_date) END,
    CASE WHEN n.rec_upd_date IS NULL OR n.rec_upd_date = '' OR n.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN n.rec_crt_date IS NULL OR n.rec_crt_date = '' OR n.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(n.rec_crt_date) END ELSE UNIX_TIMESTAMP(n.rec_upd_date) END,
    NULL
FROM hank_zl_data.newslist n
JOIN (
    SELECT 'zh-CN' AS lang_code
    UNION ALL SELECT 'zh_CN'
    UNION ALL SELECT 'en'
) locales
WHERE n.voided = '1'
  AND NOT EXISTS (
      SELECT 1
      FROM co_crmv5.news_langs nl
      WHERE nl.news_id = n.news_id AND nl.lang_code = locales.lang_code
  );

-- Remove obsolete external version/download probes.
UPDATE co_crmv5.system_configs
SET `value` = '#', updated_at = @now
WHERE `key` IN (
    'download_pc_url',
    'pc_download_url',
    'client_pc_download_url',
    'download_mobile_url',
    'mobile_download_url',
    'app_download_url'
)
AND (`value` LIKE '%xapi.yhchj.com/version%' OR `value` LIKE '%/version%');

-- ---------------------------------------------------------------------------
-- Additional legacy tables with direct co_crmv5 targets
-- ---------------------------------------------------------------------------

INSERT INTO co_crmv5.roles
    (id, name, guard_type, description, permissions, status, created_at, updated_at, deleted_at)
SELECT
    r.role_id,
    TRIM(COALESCE(r.username, CONCAT('legacy_role_', r.role_id))),
    'admin',
    COALESCE(r.`desc`, ''),
    CASE WHEN JSON_VALID(r.acl) THEN r.acl ELSE JSON_ARRAY() END,
    1,
    CASE WHEN r.created_at IS NULL OR r.created_at = '' OR r.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.created_at) END,
    CASE WHEN r.updated_at IS NULL OR r.updated_at = '' OR r.updated_at LIKE '0000-00-00%' THEN CASE WHEN r.created_at IS NULL OR r.created_at = '' OR r.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.created_at) END ELSE UNIX_TIMESTAMP(r.updated_at) END,
    NULL
FROM hank_zl_data.role r
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    guard_type = VALUES(guard_type),
    description = VALUES(description),
    permissions = VALUES(permissions),
    status = VALUES(status),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.admins
    (id, role_id, mobile, email, username, password, login_count, last_login_ip, last_login_at, last_login_address,
     status, jwt_token_id, created_by, created_at, updated_at, deleted_at)
SELECT
    a.id,
    TRIM(COALESCE(a.role_id, '0')),
    a.mobile,
    a.email,
    TRIM(COALESCE(NULLIF(a.username, ''), CONCAT('legacy_admin_', a.id))),
    CASE WHEN a.password IS NULL OR a.password = '' THEN @default_plain_password_hash ELSE a.password END,
    COALESCE(a.login_mnu, 0),
    a.ip,
    CASE WHEN a.login_time IS NULL OR a.login_time = 0 THEN NULL ELSE FROM_UNIXTIME(a.login_time) END,
    a.login_address,
    COALESCE(a.state, 1),
    '',
    COALESCE(a.created_name, ''),
    CASE WHEN a.created_at IS NULL OR a.created_at = '' OR a.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(a.created_at) END,
    CASE WHEN a.updated_at IS NULL OR a.updated_at = '' OR a.updated_at LIKE '0000-00-00%' THEN CASE WHEN a.created_at IS NULL OR a.created_at = '' OR a.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(a.created_at) END ELSE UNIX_TIMESTAMP(a.updated_at) END,
    NULL
FROM hank_zl_data.admin a
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    mobile = VALUES(mobile),
    email = VALUES(email),
    username = VALUES(username),
    password = VALUES(password),
    login_count = VALUES(login_count),
    last_login_ip = VALUES(last_login_ip),
    last_login_at = VALUES(last_login_at),
    last_login_address = VALUES(last_login_address),
    status = VALUES(status),
    jwt_token_id = VALUES(jwt_token_id),
    created_by = VALUES(created_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.admin_logins
    (id, username, password, role_id, status, last_login_ip, last_login_at, created_at, updated_at, deleted_at)
SELECT
    a.id,
    CASE
        WHEN EXISTS (
            SELECT 1 FROM co_crmv5.admin_logins al
            WHERE al.username = TRIM(COALESCE(NULLIF(a.username, ''), CONCAT('legacy_admin_', a.id)))
              AND al.id <> a.id
        )
        THEN CONCAT(TRIM(COALESCE(NULLIF(a.username, ''), 'legacy_admin')), '_', a.id)
        ELSE TRIM(COALESCE(NULLIF(a.username, ''), CONCAT('legacy_admin_', a.id)))
    END,
    CASE WHEN a.password IS NULL OR a.password = '' THEN @default_plain_password_hash ELSE a.password END,
    COALESCE(CAST(NULLIF(a.role_id, '') AS UNSIGNED), 0),
    COALESCE(a.state, 1),
    a.ip,
    CASE WHEN a.login_time IS NULL OR a.login_time = 0 THEN NULL ELSE a.login_time END,
    CASE WHEN a.created_at IS NULL OR a.created_at = '' OR a.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(a.created_at) END,
    CASE WHEN a.updated_at IS NULL OR a.updated_at = '' OR a.updated_at LIKE '0000-00-00%' THEN CASE WHEN a.created_at IS NULL OR a.created_at = '' OR a.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(a.created_at) END ELSE UNIX_TIMESTAMP(a.updated_at) END,
    NULL
FROM hank_zl_data.admin a
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password = VALUES(password),
    role_id = VALUES(role_id),
    status = VALUES(status),
    last_login_ip = VALUES(last_login_ip),
    last_login_at = VALUES(last_login_at),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.admin_login_logs
    (id, admin_id, login_ip, ip_address, user_agent, created_at, updated_at, deleted_at)
SELECT
    l.sys_id,
    l.login_id,
    TRIM(COALESCE(l.login_ip, '')),
    '',
    TRIM(COALESCE(l.login_id_desc, '')),
    CASE WHEN l.login_date IS NULL OR l.login_date = '' OR l.login_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(l.login_date) END,
    CASE WHEN l.login_date IS NULL OR l.login_date = '' OR l.login_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(l.login_date) END,
    CASE WHEN l.voided = '1' THEN NULL ELSE CASE WHEN l.login_date IS NULL OR l.login_date = '' OR l.login_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(l.login_date) END END
FROM hank_zl_data.system_login_log l
ON DUPLICATE KEY UPDATE
    admin_id = VALUES(admin_id),
    login_ip = VALUES(login_ip),
    ip_address = VALUES(ip_address),
    user_agent = VALUES(user_agent),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.big_agents
    (id, email, username, password, sub_agent_ids, is_enabled, jwt_token_id, created_by, created_at, updated_at, deleted_at)
SELECT
    b.id,
    TRIM(COALESCE(b.email, '')),
    TRIM(COALESCE(b.username, CONCAT('legacy_big_agent_', b.id))),
    CASE WHEN b.password IS NULL OR b.password = '' THEN @default_plain_password_hash ELSE b.password END,
    TRIM(COALESCE(b.sub_agent_ids, '')),
    COALESCE(b.is_enable, 0),
    '',
    TRIM(COALESCE(b.created_name, '')),
    CASE WHEN b.created_at IS NULL OR b.created_at = '' OR b.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(b.created_at) END,
    CASE WHEN b.updated_at IS NULL OR b.updated_at = '' OR b.updated_at LIKE '0000-00-00%' THEN CASE WHEN b.created_at IS NULL OR b.created_at = '' OR b.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(b.created_at) END ELSE UNIX_TIMESTAMP(b.updated_at) END,
    CASE WHEN b.deleted_at IS NULL OR b.deleted_at = '' OR b.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(b.deleted_at) END
FROM hank_zl_data.big_agents b
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    username = VALUES(username),
    password = VALUES(password),
    sub_agent_ids = VALUES(sub_agent_ids),
    is_enabled = VALUES(is_enabled),
    jwt_token_id = VALUES(jwt_token_id),
    created_by = VALUES(created_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.big_agent_login_logs
    (id, big_agent_id, login_ip, login_at, created_at, updated_at, deleted_at)
SELECT
    l.id,
    l.login_id,
    TRIM(COALESCE(l.login_ip, '')),
    CASE WHEN l.login_date IS NULL OR l.login_date = '' OR l.login_date LIKE '0000-00-00%' THEN FROM_UNIXTIME(@now) ELSE l.login_date END,
    CASE WHEN l.login_date IS NULL OR l.login_date = '' OR l.login_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(l.login_date) END,
    CASE WHEN l.login_date IS NULL OR l.login_date = '' OR l.login_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(l.login_date) END,
    CASE WHEN l.voided = '1' THEN NULL ELSE CASE WHEN l.login_date IS NULL OR l.login_date = '' OR l.login_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(l.login_date) END END
FROM hank_zl_data.big_agents_login_log l
ON DUPLICATE KEY UPDATE
    big_agent_id = VALUES(big_agent_id),
    login_ip = VALUES(login_ip),
    login_at = VALUES(login_at),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.blacklists
    (id, name, id_card, email, phone, created_at, updated_at, deleted_at)
SELECT
    b.id,
    TRIM(COALESCE(b.name, '')),
    TRIM(COALESCE(b.id_card, '')),
    TRIM(COALESCE(b.email, '')),
    TRIM(COALESCE(b.phone, '')),
    @now,
    @now,
    NULL
FROM hank_zl_data.blacklist b
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    id_card = VALUES(id_card),
    email = VALUES(email),
    phone = VALUES(phone),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.cancel_applies
    (id, user_id, user_name, status, cancel_remark, reject_reason, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    c.cancel_id,
    c.cancel_userid,
    TRIM(COALESCE(c.cancel_username, '')),
    COALESCE(CAST(c.cancel_status AS SIGNED), 0),
    TRIM(COALESCE(c.cancel_remark, '')),
    CASE WHEN COALESCE(CAST(c.cancel_status AS SIGNED), 0) < 0 THEN TRIM(COALESCE(c.cancel_remark, '')) ELSE '' END,
    TRIM(COALESCE(c.rec_crt_user, '')),
    TRIM(COALESCE(c.rec_upd_user, '')),
    CASE WHEN c.rec_crt_date IS NULL OR c.rec_crt_date = '' OR c.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(c.rec_crt_date) END,
    CASE WHEN c.rec_upd_date IS NULL OR c.rec_upd_date = '' OR c.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN c.rec_crt_date IS NULL OR c.rec_crt_date = '' OR c.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(c.rec_crt_date) END ELSE UNIX_TIMESTAMP(c.rec_upd_date) END,
    CASE WHEN c.voided = '1' THEN NULL ELSE CASE WHEN c.rec_upd_date IS NULL OR c.rec_upd_date = '' OR c.rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(c.rec_upd_date) END END
FROM hank_zl_data.cancel_apply c
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    user_name = VALUES(user_name),
    status = VALUES(status),
    cancel_remark = VALUES(cancel_remark),
    reject_reason = VALUES(reject_reason),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.deposit_imports
    (id, user_id, user_name, amount, remarks, mt4_order_id, batch_no, is_synced, fail_reason, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    d.id,
    d.user_id,
    TRIM(COALESCE(d.user_name, '')),
    TRIM(COALESCE(d.amount, '')),
    TRIM(COALESCE(d.remarks, '')),
    COALESCE(d.mt4_order_id, 0),
    TRIM(COALESCE(d.batch_no, '')),
    COALESCE(d.is_sync_succ, 0),
    TRIM(COALESCE(d.fail_reason, '')),
    COALESCE(d.create_id, 0),
    COALESCE(d.update_id, 0),
    CASE WHEN d.created_at IS NULL OR d.created_at = '' OR d.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(d.created_at) END,
    CASE WHEN d.updated_at IS NULL OR d.updated_at = '' OR d.updated_at LIKE '0000-00-00%' THEN CASE WHEN d.created_at IS NULL OR d.created_at = '' OR d.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(d.created_at) END ELSE UNIX_TIMESTAMP(d.updated_at) END,
    CASE WHEN d.deleted_at IS NULL OR d.deleted_at = '' OR d.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(d.deleted_at) END
FROM hank_zl_data.deposit_import d
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    user_name = VALUES(user_name),
    amount = VALUES(amount),
    remarks = VALUES(remarks),
    mt4_order_id = VALUES(mt4_order_id),
    batch_no = VALUES(batch_no),
    is_synced = VALUES(is_synced),
    fail_reason = VALUES(fail_reason),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.withdraw_imports
    (id, user_id, user_name, amount, remarks, mt4_order_id, batch_no, is_synced, fail_reason, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    w.id,
    w.user_id,
    TRIM(COALESCE(w.user_name, '')),
    TRIM(COALESCE(w.amount, '')),
    TRIM(COALESCE(w.remarks, '')),
    COALESCE(w.mt4_order_id, 0),
    TRIM(COALESCE(w.batch_no, '')),
    COALESCE(w.is_sync_succ, 0),
    TRIM(COALESCE(w.fail_reason, '')),
    COALESCE(w.create_id, 0),
    COALESCE(w.update_id, 0),
    CASE WHEN w.created_at IS NULL OR w.created_at = '' OR w.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(w.created_at) END,
    CASE WHEN w.updated_at IS NULL OR w.updated_at = '' OR w.updated_at LIKE '0000-00-00%' THEN CASE WHEN w.created_at IS NULL OR w.created_at = '' OR w.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(w.created_at) END ELSE UNIX_TIMESTAMP(w.updated_at) END,
    CASE WHEN w.deleted_at IS NULL OR w.deleted_at = '' OR w.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(w.deleted_at) END
FROM hank_zl_data.withdraw_import w
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    user_name = VALUES(user_name),
    amount = VALUES(amount),
    remarks = VALUES(remarks),
    mt4_order_id = VALUES(mt4_order_id),
    batch_no = VALUES(batch_no),
    is_synced = VALUES(is_synced),
    fail_reason = VALUES(fail_reason),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.credit_imports
    (id, user_id, user_name, credit_type, mt4_order_id, amount, batch_no, is_synced, fail_reason, remarks, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    c.id,
    c.user_id,
    TRIM(COALESCE(c.user_name, '')),
    COALESCE(c.credit_type, 1),
    COALESCE(c.mt4_order_id, 0),
    TRIM(COALESCE(c.amount, '')),
    TRIM(COALESCE(c.batch_no, '')),
    COALESCE(c.is_sync_succ, 0),
    TRIM(COALESCE(c.fail_reason, '')),
    TRIM(COALESCE(c.remarks, '')),
    COALESCE(c.create_id, 0),
    COALESCE(c.update_id, 0),
    CASE WHEN c.created_at IS NULL OR c.created_at = '' OR c.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(c.created_at) END,
    CASE WHEN c.updated_at IS NULL OR c.updated_at = '' OR c.updated_at LIKE '0000-00-00%' THEN CASE WHEN c.created_at IS NULL OR c.created_at = '' OR c.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(c.created_at) END ELSE UNIX_TIMESTAMP(c.updated_at) END,
    CASE WHEN c.deleted_at IS NULL OR c.deleted_at = '' OR c.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(c.deleted_at) END
FROM hank_zl_data.credit_import c
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    user_name = VALUES(user_name),
    credit_type = VALUES(credit_type),
    mt4_order_id = VALUES(mt4_order_id),
    amount = VALUES(amount),
    batch_no = VALUES(batch_no),
    is_synced = VALUES(is_synced),
    fail_reason = VALUES(fail_reason),
    remarks = VALUES(remarks),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.data_operation_logs
    (id, model_type, model_id, before_data, after_data, operator_id, created_at, updated_at, deleted_at)
SELECT
    d.id,
    TRIM(COALESCE(d.model_type, 'legacy')),
    d.model_id,
    CASE WHEN JSON_VALID(d.before_data) THEN d.before_data ELSE JSON_OBJECT('legacy_text', COALESCE(d.before_data, '')) END,
    CASE WHEN JSON_VALID(d.after_data) THEN d.after_data ELSE JSON_OBJECT('legacy_text', COALESCE(d.after_data, '')) END,
    d.user_id,
    CASE WHEN d.created_at IS NULL OR d.created_at = '' OR d.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(d.created_at) END,
    CASE WHEN d.updated_at IS NULL OR d.updated_at = '' OR d.updated_at LIKE '0000-00-00%' THEN CASE WHEN d.created_at IS NULL OR d.created_at = '' OR d.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(d.created_at) END ELSE UNIX_TIMESTAMP(d.updated_at) END,
    CASE WHEN d.deleted_at IS NULL OR d.deleted_at = '' OR d.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(d.deleted_at) END
FROM hank_zl_data.data_operation_log d
ON DUPLICATE KEY UPDATE
    model_type = VALUES(model_type),
    model_id = VALUES(model_id),
    before_data = VALUES(before_data),
    after_data = VALUES(after_data),
    operator_id = VALUES(operator_id),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.mail_settings
    (id, driver, host, port, username, password, encryption, from_address, from_name, created_at, updated_at, deleted_at)
SELECT
    m.id,
    m.driver,
    m.host,
    m.port,
    m.username,
    m.password,
    m.encryption,
    m.from_address,
    m.from_name,
    CASE WHEN m.created_at IS NULL OR m.created_at = '' OR m.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(m.created_at) END,
    CASE WHEN m.updated_at IS NULL OR m.updated_at = '' OR m.updated_at LIKE '0000-00-00%' THEN CASE WHEN m.created_at IS NULL OR m.created_at = '' OR m.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(m.created_at) END ELSE UNIX_TIMESTAMP(m.updated_at) END,
    CASE WHEN m.deleted_at IS NULL OR m.deleted_at = '' OR m.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(m.deleted_at) END
FROM hank_zl_data.mail_setting m
ON DUPLICATE KEY UPDATE
    driver = VALUES(driver),
    host = VALUES(host),
    port = VALUES(port),
    username = VALUES(username),
    password = VALUES(password),
    encryption = VALUES(encryption),
    from_address = VALUES(from_address),
    from_name = VALUES(from_name),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.mt4_configs
    (id, server_name, ip, port, manager_login, manager_password, is_active, created_at, updated_at)
SELECT
    c.CONFIG + 1,
    CONCAT('legacy_config_', c.CONFIG),
    COALESCE(NULLIF(c.VALUE_STR, ''), '0.0.0.0'),
    COALESCE(c.VALUE_INT, 0),
    CAST(c.CONFIG AS CHAR),
    COALESCE(c.VALUE_STR, ''),
    1,
    @now,
    @now
FROM hank_zl_data.mt4_config c
ON DUPLICATE KEY UPDATE
    server_name = VALUES(server_name),
    ip = VALUES(ip),
    port = VALUES(port),
    manager_login = VALUES(manager_login),
    manager_password = VALUES(manager_password),
    is_active = VALUES(is_active),
    updated_at = VALUES(updated_at);

INSERT INTO co_crmv5.system_configs
    (`key`, `value`, `group`, description, created_at, updated_at, deleted_at)
SELECT
    CONCAT('legacy_mt4_config_', c.CONFIG),
    JSON_OBJECT('CONFIG', c.CONFIG, 'VALUE_INT', c.VALUE_INT, 'VALUE_STR', c.VALUE_STR),
    'legacy_mt4',
    CONCAT('Imported from hank_zl_data.mt4_config.', c.CONFIG),
    @now,
    @now,
    NULL
FROM hank_zl_data.mt4_config c
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `group` = VALUES(`group`),
    description = VALUES(description),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.mt4_prices
    (id, symbol, bid, ask, `timestamp`, created_at, updated_at)
SELECT
    CRC32(CONCAT('mt4_prices:', p.SYMBOL)),
    TRIM(p.SYMBOL),
    ROUND(COALESCE(p.BID, 0), 5),
    ROUND(COALESCE(p.ASK, 0), 5),
    CASE WHEN p.`TIME` IS NULL OR p.`TIME` = '' OR p.`TIME` LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.`TIME`) END,
    CASE WHEN p.`TIME` IS NULL OR p.`TIME` = '' OR p.`TIME` LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.`TIME`) END,
    CASE WHEN p.MODIFY_TIME IS NULL OR p.MODIFY_TIME = '' OR p.MODIFY_TIME LIKE '0000-00-00%' THEN CASE WHEN p.`TIME` IS NULL OR p.`TIME` = '' OR p.`TIME` LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(p.`TIME`) END ELSE UNIX_TIMESTAMP(p.MODIFY_TIME) END
FROM hank_zl_data.mt4_prices p
WHERE TRIM(p.SYMBOL) <> ''
ON DUPLICATE KEY UPDATE
    symbol = VALUES(symbol),
    bid = VALUES(bid),
    ask = VALUES(ask),
    `timestamp` = VALUES(`timestamp`),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at);

INSERT INTO co_crmv5.mt4_trades
    (id, ticket, login, symbol, cmd, volume, open_price, close_price, commission, swaps, profit, open_time, close_time, created_at, updated_at)
SELECT
    t.TICKET,
    t.TICKET,
    t.LOGIN,
    TRIM(COALESCE(t.SYMBOL, '')),
    COALESCE(t.CMD, 0),
    COALESCE(t.VOLUME, 0),
    ROUND(COALESCE(t.OPEN_PRICE, 0), 5),
    ROUND(COALESCE(t.CLOSE_PRICE, 0), 5),
    ROUND(COALESCE(t.COMMISSION, 0), 2),
    ROUND(COALESCE(t.SWAPS, 0), 2),
    ROUND(COALESCE(t.PROFIT, 0), 2),
    CASE WHEN t.OPEN_TIME IS NULL OR t.OPEN_TIME = '' OR t.OPEN_TIME LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.OPEN_TIME) END,
    CASE WHEN t.CLOSE_TIME IS NULL OR t.CLOSE_TIME = '' OR t.CLOSE_TIME LIKE '0000-00-00%' OR t.CLOSE_TIME = '1970-01-01 00:00:00' THEN NULL ELSE UNIX_TIMESTAMP(t.CLOSE_TIME) END,
    CASE WHEN t.OPEN_TIME IS NULL OR t.OPEN_TIME = '' OR t.OPEN_TIME LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.OPEN_TIME) END,
    CASE WHEN t.MODIFY_TIME IS NULL OR t.MODIFY_TIME = '' OR t.MODIFY_TIME LIKE '0000-00-00%' THEN CASE WHEN t.OPEN_TIME IS NULL OR t.OPEN_TIME = '' OR t.OPEN_TIME LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(t.OPEN_TIME) END ELSE UNIX_TIMESTAMP(t.MODIFY_TIME) END
FROM hank_zl_data.mt4_trades t
ON DUPLICATE KEY UPDATE
    ticket = VALUES(ticket),
    login = VALUES(login),
    symbol = VALUES(symbol),
    cmd = VALUES(cmd),
    volume = VALUES(volume),
    open_price = VALUES(open_price),
    close_price = VALUES(close_price),
    commission = VALUES(commission),
    swaps = VALUES(swaps),
    profit = VALUES(profit),
    open_time = VALUES(open_time),
    close_time = VALUES(close_time),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at);

INSERT INTO co_crmv5.mt4_users
    (id, login, name, `group`, balance, equity, margin, margin_free, leverage, created_at, updated_at, deleted_at)
SELECT
    u.LOGIN,
    u.LOGIN,
    TRIM(COALESCE(u.NAME, CONCAT('MT4 ', u.LOGIN))),
    TRIM(COALESCE(u.`GROUP`, '')),
    ROUND(COALESCE(u.BALANCE, 0), 2),
    ROUND(COALESCE(u.EQUITY, 0), 2),
    ROUND(COALESCE(u.MARGIN, 0), 2),
    ROUND(COALESCE(u.MARGIN_FREE, 0), 2),
    COALESCE(u.LEVERAGE, 100),
    CASE WHEN u.REGDATE IS NULL OR u.REGDATE = '' OR u.REGDATE LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(u.REGDATE) END,
    CASE WHEN u.MODIFY_TIME IS NULL OR u.MODIFY_TIME = '' OR u.MODIFY_TIME LIKE '0000-00-00%' THEN CASE WHEN u.LASTDATE IS NULL OR u.LASTDATE = '' OR u.LASTDATE LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(u.LASTDATE) END ELSE UNIX_TIMESTAMP(u.MODIFY_TIME) END,
    NULL
FROM hank_zl_data.mt4_users u
ON DUPLICATE KEY UPDATE
    login = VALUES(login),
    name = VALUES(name),
    `group` = VALUES(`group`),
    balance = VALUES(balance),
    equity = VALUES(equity),
    margin = VALUES(margin),
    margin_free = VALUES(margin_free),
    leverage = VALUES(leverage),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.operation_logs
    (id, admin_id, admin_name, target_user_id, order_no, content, ip, action_type, created_at, updated_at, deleted_at)
SELECT
    o.id,
    0,
    TRIM(COALESCE(o.name, '')),
    o.user_id,
    CAST(o.order_number AS CHAR),
    TRIM(COALESCE(o.content, '')),
    TRIM(COALESCE(o.handle_ip, '')),
    COALESCE(o.type, 0),
    CASE WHEN o.created_on IS NULL OR o.created_on = 0 THEN @now ELSE o.created_on END,
    CASE WHEN o.created_on IS NULL OR o.created_on = 0 THEN @now ELSE o.created_on END,
    NULL
FROM hank_zl_data.operation_log o
ON DUPLICATE KEY UPDATE
    admin_id = VALUES(admin_id),
    admin_name = VALUES(admin_name),
    target_user_id = VALUES(target_user_id),
    order_no = VALUES(order_no),
    content = VALUES(content),
    ip = VALUES(ip),
    action_type = VALUES(action_type),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.countries
    (id, iso_code, zone_id, currency_id, is_active, call_prefix, created_at, updated_at, deleted_at)
SELECT
    c.id,
    TRIM(COALESCE(c.iso_code, '')),
    COALESCE(c.zone_id, 0),
    COALESCE(c.currency_id, 0),
    COALESCE(c.active, 0),
    COALESCE(c.call_prefix, 0),
    @now,
    @now,
    NULL
FROM hank_zl_data.ps_countries c
ON DUPLICATE KEY UPDATE
    iso_code = VALUES(iso_code),
    zone_id = VALUES(zone_id),
    currency_id = VALUES(currency_id),
    is_active = VALUES(is_active),
    call_prefix = VALUES(call_prefix),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.country_langs
    (id, country_id, lang_code, name, created_at, updated_at, deleted_at)
SELECT
    cl.country_id * 100 + cl.lang_id,
    cl.country_id,
    CASE cl.lang_id WHEN 1 THEN 'en' WHEN 3 THEN 'zh-CN' WHEN 4 THEN 'zh-TW' ELSE CONCAT('lang-', cl.lang_id) END,
    TRIM(COALESCE(cl.name, '')),
    @now,
    @now,
    NULL
FROM hank_zl_data.ps_country_langs cl
ON DUPLICATE KEY UPDATE
    country_id = VALUES(country_id),
    lang_code = VALUES(lang_code),
    name = VALUES(name),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.country_translations
    (id, country_id, lang_code, name, initials, created_at, updated_at, deleted_at)
SELECT
    cl.country_id * 100 + cl.lang_id,
    cl.country_id,
    CASE cl.lang_id WHEN 1 THEN 'en' WHEN 3 THEN 'zh-CN' WHEN 4 THEN 'zh-TW' ELSE CONCAT('lang-', cl.lang_id) END,
    TRIM(COALESCE(cl.name, '')),
    TRIM(COALESCE(cl.initials, '')),
    @now,
    @now,
    NULL
FROM hank_zl_data.ps_country_langs cl
ON DUPLICATE KEY UPDATE
    country_id = VALUES(country_id),
    lang_code = VALUES(lang_code),
    name = VALUES(name),
    initials = VALUES(initials),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.languages
    (id, name, iso_code, language_code, locale, is_active, created_at, updated_at, deleted_at)
SELECT
    l.id,
    TRIM(COALESCE(l.name, CONCAT('legacy_lang_', l.id))),
    TRIM(COALESCE(l.iso_code, '')),
    TRIM(COALESCE(l.language_code, '')),
    TRIM(COALESCE(l.locale, l.language_code, l.iso_code, '')),
    COALESCE(l.active, 1),
    CASE WHEN l.create_time IS NULL OR l.create_time = 0 THEN @now ELSE l.create_time END,
    CASE WHEN l.update_time IS NULL OR l.update_time = 0 THEN CASE WHEN l.create_time IS NULL OR l.create_time = 0 THEN @now ELSE l.create_time END ELSE l.update_time END,
    NULL
FROM hank_zl_data.t_sys_langs l
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    iso_code = VALUES(iso_code),
    language_code = VALUES(language_code),
    locale = VALUES(locale),
    is_active = VALUES(is_active),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.app_versions
    (id, platform, version, download_url, update_logs, is_force, created_at, updated_at, deleted_at)
SELECT
    a.id,
    TRIM(COALESCE(NULLIF(a.download_platform, ''), CASE a.app_platform_type WHEN 1 THEN 'ios' WHEN 2 THEN 'android' ELSE 'unknown' END)),
    TRIM(COALESCE(a.app_version_num, '')),
    TRIM(COALESCE(a.app_url, '')),
    CONCAT_WS('\n', NULLIF(a.update_desc, ''), NULLIF(a.app_desc, ''), CONCAT('app_name: ', COALESCE(a.app_name, '')), CONCAT('app_code: ', COALESCE(a.app_code, '')), CONCAT('build_code: ', COALESCE(a.build_code, ''))),
    COALESCE(a.is_force_update, 0),
    CASE WHEN a.created_at IS NULL OR a.created_at = '' OR a.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(a.created_at) END,
    CASE WHEN a.updated_at IS NULL OR a.updated_at = '' OR a.updated_at LIKE '0000-00-00%' THEN CASE WHEN a.created_at IS NULL OR a.created_at = '' OR a.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(a.created_at) END ELSE UNIX_TIMESTAMP(a.updated_at) END,
    CASE WHEN a.deleted_at IS NULL OR a.deleted_at = '' OR a.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(a.deleted_at) END
FROM hank_zl_data.ps_woer_app_versionv2s a
ON DUPLICATE KEY UPDATE
    platform = VALUES(platform),
    version = VALUES(version),
    download_url = VALUES(download_url),
    update_logs = VALUES(update_logs),
    is_force = VALUES(is_force),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.offweb_feedbacks
    (id, user_id, email, title, content, reply, status, created_at, updated_at, deleted_at)
SELECT
    f.id,
    NULL,
    TRIM(COALESCE(f.email, '')),
    TRIM(COALESCE(NULLIF(f.user_name, ''), NULLIF(f.to_email, ''), CONCAT('legacy_feedback_', f.id))),
    TRIM(COALESCE(f.remarks, '')),
    NULL,
    0,
    CASE WHEN f.created_at IS NULL OR f.created_at = '' OR f.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(f.created_at) END,
    CASE WHEN f.updated_at IS NULL OR f.updated_at = '' OR f.updated_at LIKE '0000-00-00%' THEN CASE WHEN f.created_at IS NULL OR f.created_at = '' OR f.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(f.created_at) END ELSE UNIX_TIMESTAMP(f.updated_at) END,
    CASE WHEN f.deleted_at IS NULL OR f.deleted_at = '' OR f.deleted_at LIKE '0000-00-00%' THEN NULL ELSE UNIX_TIMESTAMP(f.deleted_at) END
FROM hank_zl_data.offweb_feedback f
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    email = VALUES(email),
    title = VALUES(title),
    content = VALUES(content),
    reply = VALUES(reply),
    status = VALUES(status),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.batch_fail_records
    (id, batch_type, batch_id, data, error_msg, created_at, updated_at)
SELECT
    b.batch_id,
    TRIM(COALESCE(b.batch_type, 'legacy')),
    TRIM(COALESCE(b.batch_no, b.batch_id)),
    JSON_OBJECT('user_id', b.batch_user_id, 'user_name', b.batch_user_name, 'ticket', b.batch_ticket, 'voided', b.voided, 'created_by', b.rec_crt_user, 'updated_by', b.rec_upd_user),
    '',
    CASE WHEN b.rec_crt_date IS NULL OR b.rec_crt_date = '' OR b.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(b.rec_crt_date) END,
    CASE WHEN b.rec_upd_date IS NULL OR b.rec_upd_date = '' OR b.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN b.rec_crt_date IS NULL OR b.rec_crt_date = '' OR b.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(b.rec_crt_date) END ELSE UNIX_TIMESTAMP(b.rec_upd_date) END
FROM hank_zl_data.batch_fail_record b
ON DUPLICATE KEY UPDATE
    batch_type = VALUES(batch_type),
    batch_id = VALUES(batch_id),
    data = VALUES(data),
    error_msg = VALUES(error_msg),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at);

INSERT INTO co_crmv5.user_images
    (id, user_id, type, path, mime_type, created_at, updated_at, deleted_at)
SELECT img_id * 10 + 1, user_id, 'id_card_front', TRIM(img_idcard01_path), NULL,
       CASE WHEN rec_crt_date IS NULL OR rec_crt_date = '' OR rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_crt_date) END,
       CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END,
       CASE WHEN voided = '1' THEN NULL ELSE CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END END
FROM hank_zl_data.user_img
WHERE TRIM(COALESCE(img_idcard01_path, '')) <> ''
UNION ALL
SELECT img_id * 10 + 2, user_id, 'id_card_back', TRIM(img_idcard02_path), NULL,
       CASE WHEN rec_crt_date IS NULL OR rec_crt_date = '' OR rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_crt_date) END,
       CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END,
       CASE WHEN voided = '1' THEN NULL ELSE CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END END
FROM hank_zl_data.user_img
WHERE TRIM(COALESCE(img_idcard02_path, '')) <> ''
UNION ALL
SELECT img_id * 10 + 3, user_id, 'avatar', TRIM(img_header_path), NULL,
       CASE WHEN rec_crt_date IS NULL OR rec_crt_date = '' OR rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_crt_date) END,
       CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END,
       CASE WHEN voided = '1' THEN NULL ELSE CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END END
FROM hank_zl_data.user_img
WHERE TRIM(COALESCE(img_header_path, '')) <> ''
UNION ALL
SELECT img_id * 10 + 4, user_id, 'bank_card', TRIM(img_bank_path), NULL,
       CASE WHEN rec_crt_date IS NULL OR rec_crt_date = '' OR rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_crt_date) END,
       CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END,
       CASE WHEN voided = '1' THEN NULL ELSE CASE WHEN rec_upd_date IS NULL OR rec_upd_date = '' OR rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(rec_upd_date) END END
FROM hank_zl_data.user_img
WHERE TRIM(COALESCE(img_bank_path, '')) <> ''
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    type = VALUES(type),
    path = VALUES(path),
    mime_type = VALUES(mime_type),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.user_onlines
    (id, user_id, last_activity, ip_address, user_agent, created_at, updated_at)
SELECT
    u.id,
    u.user_id,
    COALESCE(NULLIF(u.last_active, 0), CASE WHEN u.updated_at IS NULL OR u.updated_at = '' OR u.updated_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(u.updated_at) END),
    TRIM(COALESCE(NULLIF(u.ip, ''), u.ip_addr, '')),
    JSON_OBJECT('user_name', u.user_name, 'account_type', u.account_type, 'status', u.status, 'date', u.date, 'req_url', u.req_url, 'req_params', u.req_params, 'session_key', u.session_key, 'operation_from', u.operation_from),
    CASE WHEN u.created_at IS NULL OR u.created_at = '' OR u.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(u.created_at) END,
    CASE WHEN u.updated_at IS NULL OR u.updated_at = '' OR u.updated_at LIKE '0000-00-00%' THEN CASE WHEN u.created_at IS NULL OR u.created_at = '' OR u.created_at LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(u.created_at) END ELSE UNIX_TIMESTAMP(u.updated_at) END
FROM hank_zl_data.user_online u
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    last_activity = VALUES(last_activity),
    ip_address = VALUES(ip_address),
    user_agent = VALUES(user_agent),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at);

INSERT INTO co_crmv5.rights_settlements
    (id, user_id, amount, status, remark, created_at, updated_at, deleted_at)
SELECT
    r.rights_id,
    r.rights_user_id,
    COALESCE(r.rights_sum_money, 0),
    COALESCE(CAST(r.rights_sum_status AS SIGNED), 0),
    LEFT(CONCAT('order:', COALESCE(r.rights_mt4_orderId, ''), '; ident:', COALESCE(r.rights_user_ident, ''), '; ', COALESCE(r.rights_sum_remarks, '')), 255),
    CASE WHEN r.rec_crt_date IS NULL OR r.rec_crt_date = '' OR r.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.rec_crt_date) END,
    CASE WHEN r.rec_upd_date IS NULL OR r.rec_upd_date = '' OR r.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN r.rec_crt_date IS NULL OR r.rec_crt_date = '' OR r.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.rec_crt_date) END ELSE UNIX_TIMESTAMP(r.rec_upd_date) END,
    CASE WHEN r.voided = '1' THEN NULL ELSE CASE WHEN r.rec_upd_date IS NULL OR r.rec_upd_date = '' OR r.rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.rec_upd_date) END END
FROM hank_zl_data.rights_sum r
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    amount = VALUES(amount),
    status = VALUES(status),
    remark = VALUES(remark),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.rights_settlement_temps
    (id, user_id, amount, created_at, updated_at)
SELECT
    r.rights_sum_tmpid,
    r.rights_sum_tmpuserId,
    COALESCE(CAST(NULLIF(r.rights_sum_tmpcomm, '') AS DECIMAL(20,8)), 0),
    CASE WHEN r.rec_crt_date IS NULL OR r.rec_crt_date = '' OR r.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.rec_crt_date) END,
    CASE WHEN r.rec_upd_date IS NULL OR r.rec_upd_date = '' OR r.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN r.rec_crt_date IS NULL OR r.rec_crt_date = '' OR r.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(r.rec_crt_date) END ELSE UNIX_TIMESTAMP(r.rec_upd_date) END
FROM hank_zl_data.rights_sum_tmp r
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    amount = VALUES(amount),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at);

INSERT INTO co_crmv5.whs_exp_zeros
    (id, user_id, user_name, balance, credit, status, md5_key, created_by, updated_by, created_at, updated_at, deleted_at)
SELECT
    w.wez_id,
    w.wez_userid,
    TRIM(COALESCE(w.wez_username, '')),
    COALESCE(w.wez_userbal, 0),
    COALESCE(w.wez_usercrt, 0),
    COALESCE(CAST(w.wez_status AS SIGNED), 0),
    TRIM(COALESCE(w.wez_idmd5, '')),
    TRIM(COALESCE(w.rec_crt_user, '')),
    TRIM(COALESCE(w.rec_upd_user, '')),
    CASE WHEN w.rec_crt_date IS NULL OR w.rec_crt_date = '' OR w.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(w.rec_crt_date) END,
    CASE WHEN w.rec_upd_date IS NULL OR w.rec_upd_date = '' OR w.rec_upd_date LIKE '0000-00-00%' THEN CASE WHEN w.rec_crt_date IS NULL OR w.rec_crt_date = '' OR w.rec_crt_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(w.rec_crt_date) END ELSE UNIX_TIMESTAMP(w.rec_upd_date) END,
    CASE WHEN w.voided = '1' THEN NULL ELSE CASE WHEN w.rec_upd_date IS NULL OR w.rec_upd_date = '' OR w.rec_upd_date LIKE '0000-00-00%' THEN @now ELSE UNIX_TIMESTAMP(w.rec_upd_date) END END
FROM hank_zl_data.whs_exp_zero w
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    user_name = VALUES(user_name),
    balance = VALUES(balance),
    credit = VALUES(credit),
    status = VALUES(status),
    md5_key = VALUES(md5_key),
    created_by = VALUES(created_by),
    updated_by = VALUES(updated_by),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO co_crmv5.sys_dicts
    (id, type, label, value, sort, status, created_at, updated_at)
SELECT
    d.id,
    TRIM(COALESCE(d.type, 'legacy')),
    TRIM(COALESCE(NULLIF(d.value, ''), d.name, d.code, '')),
    TRIM(COALESCE(d.code, '')),
    COALESCE(d.order_num, 0),
    CASE WHEN COALESCE(d.del_flag, 0) = 0 THEN 1 ELSE 0 END,
    @now,
    @now
FROM hank_zl_data.t_sys_dict d
ON DUPLICATE KEY UPDATE
    type = VALUES(type),
    label = VALUES(label),
    value = VALUES(value),
    sort = VALUES(sort),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

-- Permanently guarantee the front test agent after legacy data import.
INSERT INTO co_crmv5.user_logins
    (user_id, email, password, account_type, is_enabled, is_cancelled, source_type, jwt_token_id,
     last_login_ip, last_login_at, created_at, updated_at, deleted_at)
VALUES
    (1001, 'agent@test.com', @front_test_agent_password_hash, 1, 1, 0, 0, '', '', NULL, @now, @now, NULL)
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    password = VALUES(password),
    account_type = VALUES(account_type),
    is_enabled = VALUES(is_enabled),
    is_cancelled = VALUES(is_cancelled),
    source_type = VALUES(source_type),
    jwt_token_id = VALUES(jwt_token_id),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

SET @front_test_agent_login_id := (SELECT id FROM co_crmv5.user_logins WHERE email = 'agent@test.com' LIMIT 1);
SET @front_test_agent_level_id := COALESCE(
    (SELECT id FROM co_crmv5.agent_levels WHERE level_code = 1 LIMIT 1),
    (SELECT id FROM co_crmv5.agent_levels LIMIT 1),
    0
);
SET @front_test_agent_group_id := COALESCE(
    (SELECT id FROM co_crmv5.group_configs WHERE category = 1 AND is_default = 1 LIMIT 1),
    (SELECT id FROM co_crmv5.group_configs WHERE category = 1 LIMIT 1),
    0
);

INSERT INTO co_crmv5.user_infos
    (user_id, login_id, user_name, level_id, group_id, parent_id, account_type, family_tree,
     auth_status, is_mt4_synced, is_mt4_enabled, is_withdrawal_allowed, is_deposit_allowed,
     is_agent_confirmed, mt4_group, data_source, remark, created_at, updated_at, deleted_at)
VALUES
    (1001, @front_test_agent_login_id, 'Demo Root Agent', @front_test_agent_level_id,
     @front_test_agent_group_id, 0, 1, '1001', 1, 1, 1, 0, 0, 1, 'demo-agent', 0,
     'Permanent front test agent login', @now, @now, NULL)
ON DUPLICATE KEY UPDATE
    login_id = VALUES(login_id),
    user_name = VALUES(user_name),
    level_id = VALUES(level_id),
    group_id = VALUES(group_id),
    parent_id = VALUES(parent_id),
    account_type = VALUES(account_type),
    family_tree = VALUES(family_tree),
    auth_status = VALUES(auth_status),
    is_mt4_synced = VALUES(is_mt4_synced),
    is_mt4_enabled = VALUES(is_mt4_enabled),
    is_withdrawal_allowed = VALUES(is_withdrawal_allowed),
    is_deposit_allowed = VALUES(is_deposit_allowed),
    is_agent_confirmed = VALUES(is_agent_confirmed),
    mt4_group = VALUES(mt4_group),
    data_source = VALUES(data_source),
    remark = VALUES(remark),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.user_auths (user_id, created_at, updated_at, deleted_at)
VALUES (1001, @now, @now, NULL)
ON DUPLICATE KEY UPDATE
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

INSERT INTO co_crmv5.id_sequences (`type`, current_value, prefix, step, created_at, updated_at, deleted_at)
VALUES
    ('agent', COALESCE((SELECT MAX(user_id) FROM co_crmv5.user_infos WHERE account_type = 1), 0), '', 1, @now, @now, NULL),
    ('customer', COALESCE((SELECT MAX(user_id) FROM co_crmv5.user_infos WHERE account_type = 2), 0), '', 1, @now, @now, NULL)
ON DUPLICATE KEY UPDATE
    current_value = VALUES(current_value),
    prefix = VALUES(prefix),
    step = VALUES(step),
    updated_at = VALUES(updated_at),
    deleted_at = NULL;

COMMIT;
SET SESSION sql_mode = @old_sql_mode;
SET FOREIGN_KEY_CHECKS = 1;
