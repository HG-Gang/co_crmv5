<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

namespace App\Constants;

/**
 * 统一响应状态码常量。
 *
 * 文件功能：
 * - 1xxx 表示通用成功类响应。
 * - 2xxx 表示业务规则类响应，例如用户、资金、审核和订单状态。
 * - 3xxx 表示数据操作类响应，例如查询、导入、导出和批量操作。
 * - 4xxx 表示认证授权类响应，例如登录失败、令牌过期、权限不足和参数校验失败。
 * - 5xxx 表示系统错误类响应，例如服务器、数据库、文件上传和第三方接口错误。
 * - messageKey() 负责把状态码转换为 response.* 多语言 key，供 ApiResponse 统一翻译接口消息。
 * - 映射契约：新增错误码必须在 messageKey() 中登记对应语言 key，并在
 *   resources/lang/response.php 补齐文案；漏登记会回落到 response.unknown，
 *   前端将无法按码识别错误。通用错误码（4000 ERROR / 5000 SERVER_ERROR）已在语言包
 *   中兜底，业务层可直接复用。
 */
class ResponseCode
{
    // ==================== 1xxx 成功类 ====================
    /** 操作成功。 */
    const SUCCESS = 1000;
    /** 创建成功。 */
    const CREATED = 1001;
    /** 更新成功。 */
    const UPDATED = 1002;
    /** 删除成功。 */
    const DELETED = 1003;
    /** 上传成功。 */
    const UPLOADED = 1004;
    /** 旧前台兼容：存在收货地址时必须保留一个默认地址。 */
    const DEFAULT_ADDRESS_MUST_EXIST = 1015;

    // ==================== 2xxx 业务逻辑类 ====================
    /** 注册成功。 */
    const REGISTER_SUCCESS = 2000;
    /** 邮箱已存在。 */
    const EMAIL_EXISTS = 2001;
    /** 手机号已存在。 */
    const PHONE_EXISTS = 2002;
    /** 邀请人无效。 */
    const INVALID_INVITER = 2003;
    /** 邀请人已禁用。 */
    const INVITER_DISABLED = 2004;
    /** 返佣比例无效。 */
    const INVALID_COMMISSION_RATE = 2005;
    /** 组别无效。 */
    const INVALID_GROUP = 2006;
    /** 代理级别无效。 */
    const INVALID_AGENT_LEVEL = 2007;
    /** 用户不存在。 */
    const USER_NOT_FOUND = 2008;
    /** 用户已禁用。 */
    const USER_DISABLED = 2009;
    /** 用户已注销。 */
    const USER_CANCELLED = 2010;
    /** 审核状态无效。 */
    const INVALID_AUDIT_STATUS = 2011;
    /** 出金不允许。 */
    const WITHDRAWAL_NOT_ALLOWED = 2012;
    /** 入金不允许。 */
    const DEPOSIT_NOT_ALLOWED = 2013;
    /** 金额无效。 */
    const INVALID_AMOUNT = 2014;
    /** 余额不足。 */
    const INSUFFICIENT_BALANCE = 2015;
    /** 风险率超限。 */
    const RISK_RATE_EXCEEDED = 2016;
    /** 注销申请已存在。 */
    const CANCEL_APPLY_EXISTS = 2017;
    /** 黑名单用户。 */
    const BLACKLISTED = 2018;
    /** 数据不存在。 */
    const DATA_NOT_FOUND = 2019;
    /** 数据已存在。 */
    const DATA_ALREADY_EXISTS = 2020;
    /** 操作不允许。 */
    const OPERATION_NOT_ALLOWED = 2021;
    /** 返佣比例不能大于上级。 */
    const COMMISSION_EXCEEDS_PARENT = 2022;
    /** 结算记录不存在。 */
    const SETTLEMENT_NOT_FOUND = 2023;
    /** 订单不存在。 */
    const ORDER_NOT_FOUND = 2024;
    /** MT4 同步失败。 */
    const MT4_SYNC_FAILED = 2025;

    // ==================== 3xxx 数据操作类 ====================
    /** 查询成功。 */
    const QUERY_SUCCESS = 3000;
    /** 查询失败。 */
    const QUERY_FAILED = 3001;
    /** 导入成功。 */
    const IMPORT_SUCCESS = 3002;
    /** 导入失败。 */
    const IMPORT_FAILED = 3003;
    /** 导出成功。 */
    const EXPORT_SUCCESS = 3004;
    /** 批量操作成功。 */
    const BATCH_SUCCESS = 3005;
    /** 批量操作部分失败。 */
    const BATCH_PARTIAL_FAILED = 3006;

    // ==================== 4xxx 认证授权类 ====================
    /** 通用错误。 */
    const ERROR = 4000;
    /** 认证失败，例如密码错误或令牌无效。 */
    const AUTH_FAILED = 4001;
    /** 令牌已过期。 */
    const TOKEN_EXPIRED = 4002;
    /** 单点登录冲突，账号已在其他地方登录。 */
    const SSO_CONFLICT = 4003;
    /** 令牌缺失。 */
    const TOKEN_MISSING = 4004;
    /** 参数校验失败。 */
    const VALIDATION_FAILED = 4005;
    /** 权限不足。 */
    const PERMISSION_DENIED = 4006;
    /** 账号已锁定。 */
    const ACCOUNT_LOCKED = 4007;
    /** 旧密码不正确。 */
    const OLD_PASSWORD_WRONG = 4008;
    /** 频率限制。 */
    const RATE_LIMITED = 4009;

    // ==================== 5xxx 系统错误类 ====================
    /** 服务器内部错误。 */
    const SERVER_ERROR = 5000;
    /** 数据库错误。 */
    const DB_ERROR = 5001;
    /** 文件上传失败。 */
    const FILE_UPLOAD_FAILED = 5002;
    /** 邮件发送失败。 */
    const EMAIL_SEND_FAILED = 5003;
    /** 第三方接口错误。 */
    const THIRD_PARTY_ERROR = 5004;

    // ==================== 别名 ====================
    /** 参数校验失败别名，兼容旧代码中的 VALIDATION_ERROR。 */
    const VALIDATION_ERROR = self::VALIDATION_FAILED;
    /** 认证失败别名，兼容旧代码中的 INVALID_CREDENTIALS。 */
    const INVALID_CREDENTIALS = self::AUTH_FAILED;
    /** 服务器错误别名，兼容旧代码中的 INTERNAL_ERROR。 */
    const INTERNAL_ERROR = self::SERVER_ERROR;

    /**
     * 获取状态码对应的多语言消息 key。
     *
     * 参数逻辑说明：
     * - $code 表示业务响应状态码，通常来自本类常量。
     * - 返回值必须是 response.* 语言包 key，ApiResponse 会继续调用 __() 翻译成当前语言。
     * - 未声明的状态码统一返回 response.unknown，便于测试及时发现映射缺口。
     *
     * @param int $code 业务响应状态码。
     * @return string response.php 语言包 key。
     */
    public static function messageKey(int $code): string
    {
        $map = [
            self::SUCCESS => 'response.success',
            self::CREATED => 'response.created',
            self::UPDATED => 'response.updated',
            self::DELETED => 'response.deleted',
            self::UPLOADED => 'response.uploaded',
            self::DEFAULT_ADDRESS_MUST_EXIST => 'response.default_address_must_exist',
            self::REGISTER_SUCCESS => 'response.register_success',
            self::EMAIL_EXISTS => 'response.email_exists',
            self::PHONE_EXISTS => 'response.phone_exists',
            self::INVALID_INVITER => 'response.invalid_inviter',
            self::INVITER_DISABLED => 'response.inviter_disabled',
            self::INVALID_COMMISSION_RATE => 'response.invalid_commission_rate',
            self::INVALID_GROUP => 'response.invalid_group',
            self::INVALID_AGENT_LEVEL => 'response.invalid_agent_level',
            self::USER_NOT_FOUND => 'response.user_not_found',
            self::USER_DISABLED => 'response.user_disabled',
            self::USER_CANCELLED => 'response.user_cancelled',
            self::INVALID_AUDIT_STATUS => 'response.invalid_audit_status',
            self::WITHDRAWAL_NOT_ALLOWED => 'response.withdrawal_not_allowed',
            self::DEPOSIT_NOT_ALLOWED => 'response.deposit_not_allowed',
            self::INVALID_AMOUNT => 'response.invalid_amount',
            self::INSUFFICIENT_BALANCE => 'response.insufficient_balance',
            self::RISK_RATE_EXCEEDED => 'response.risk_rate_exceeded',
            self::CANCEL_APPLY_EXISTS => 'response.cancel_apply_exists',
            self::BLACKLISTED => 'response.blacklisted',
            self::DATA_NOT_FOUND => 'response.data_not_found',
            self::DATA_ALREADY_EXISTS => 'response.data_already_exists',
            self::OPERATION_NOT_ALLOWED => 'response.operation_not_allowed',
            self::COMMISSION_EXCEEDS_PARENT => 'response.commission_exceeds_parent',
            self::SETTLEMENT_NOT_FOUND => 'response.settlement_not_found',
            self::ORDER_NOT_FOUND => 'response.order_not_found',
            self::MT4_SYNC_FAILED => 'response.mt4_sync_failed',
            self::QUERY_SUCCESS => 'response.query_success',
            self::QUERY_FAILED => 'response.query_failed',
            self::IMPORT_SUCCESS => 'response.import_success',
            self::IMPORT_FAILED => 'response.import_failed',
            self::EXPORT_SUCCESS => 'response.export_success',
            self::BATCH_SUCCESS => 'response.batch_success',
            self::BATCH_PARTIAL_FAILED => 'response.batch_partial_failed',
            self::ERROR => 'response.error',
            self::AUTH_FAILED => 'response.auth_failed',
            self::TOKEN_EXPIRED => 'response.token_expired',
            self::SSO_CONFLICT => 'response.sso_conflict',
            self::TOKEN_MISSING => 'response.token_missing',
            self::VALIDATION_FAILED => 'response.validation_failed',
            self::PERMISSION_DENIED => 'response.permission_denied',
            self::ACCOUNT_LOCKED => 'response.account_locked',
            self::OLD_PASSWORD_WRONG => 'response.old_password_wrong',
            self::RATE_LIMITED => 'response.rate_limited',
            self::SERVER_ERROR => 'response.server_error',
            self::DB_ERROR => 'response.db_error',
            self::FILE_UPLOAD_FAILED => 'response.file_upload_failed',
            self::EMAIL_SEND_FAILED => 'response.email_send_failed',
            self::THIRD_PARTY_ERROR => 'response.third_party_error',
        ];

        return $map[$code] ?? 'response.unknown';
    }
}
