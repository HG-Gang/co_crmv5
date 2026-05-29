<?php

namespace App\Constants;

/**
 * 统一响应状态码常量 | Unified Response Status Code Constants
 * 
 * 规则说明 | Rules:
 * - 1xxx: 成功类 | Success
 * - 2xxx: 业务逻辑类 | Business logic
 * - 3xxx: 数据操作类 | Data operation
 * - 4xxx: 认证授权类 | Authentication & Authorization
 * - 5xxx: 系统错误类 | System errors
 */
class ResponseCode
{
    // ==================== 1xxx 成功类 | Success ====================
    /** 操作成功 | Operation successful */
    const SUCCESS = 1000;
    /** 创建成功 | Created successfully */
    const CREATED = 1001;
    /** 更新成功 | Updated successfully */
    const UPDATED = 1002;
    /** 删除成功 | Deleted successfully */
    const DELETED = 1003;
    /** 上传成功 | Upload successful */
    const UPLOADED = 1004;

    // ==================== 2xxx 业务逻辑类 | Business Logic ====================
    /** 注册成功 | Registration successful */
    const REGISTER_SUCCESS = 2000;
    /** 邮箱已存在 | Email already exists */
    const EMAIL_EXISTS = 2001;
    /** 手机号已存在 | Phone number already exists */
    const PHONE_EXISTS = 2002;
    /** 邀请人无效 | Invalid inviter */
    const INVALID_INVITER = 2003;
    /** 邀请人已禁用 | Inviter disabled */
    const INVITER_DISABLED = 2004;
    /** 返佣比例无效 | Invalid commission rate */
    const INVALID_COMMISSION_RATE = 2005;
    /** 组别无效 | Invalid group */
    const INVALID_GROUP = 2006;
    /** 代理级别无效 | Invalid agent level */
    const INVALID_AGENT_LEVEL = 2007;
    /** 用户不存在 | User not found */
    const USER_NOT_FOUND = 2008;
    /** 用户已禁用 | User disabled */
    const USER_DISABLED = 2009;
    /** 用户已注销 | User cancelled */
    const USER_CANCELLED = 2010;
    /** 审核状态无效 | Invalid audit status */
    const INVALID_AUDIT_STATUS = 2011;
    /** 出金不允许 | Withdrawal not allowed */
    const WITHDRAWAL_NOT_ALLOWED = 2012;
    /** 入金不允许 | Deposit not allowed */
    const DEPOSIT_NOT_ALLOWED = 2013;
    /** 金额无效 | Invalid amount */
    const INVALID_AMOUNT = 2014;
    /** 余额不足 | Insufficient balance */
    const INSUFFICIENT_BALANCE = 2015;
    /** 风险率超限 | Risk rate exceeded */
    const RISK_RATE_EXCEEDED = 2016;
    /** 注销申请已存在 | Cancel apply already exists */
    const CANCEL_APPLY_EXISTS = 2017;
    /** 黑名单用户 | Blacklisted user */
    const BLACKLISTED = 2018;
    /** 数据不存在 | Data not found */
    const DATA_NOT_FOUND = 2019;
    /** 数据已存在 | Data already exists */
    const DATA_ALREADY_EXISTS = 2020;
    /** 操作不允许 | Operation not allowed */
    const OPERATION_NOT_ALLOWED = 2021;
    /** 返佣比例不能大于上级 | Commission rate cannot exceed parent */
    const COMMISSION_EXCEEDS_PARENT = 2022;
    /** 结算记录不存在 | Settlement record not found */
    const SETTLEMENT_NOT_FOUND = 2023;
    /** 订单不存在 | Order not found */
    const ORDER_NOT_FOUND = 2024;
    /** MT4同步失败 | MT4 sync failed */
    const MT4_SYNC_FAILED = 2025;

    // ==================== 3xxx 数据操作类 | Data Operation ====================
    /** 查询成功 | Query successful */
    const QUERY_SUCCESS = 3000;
    /** 查询失败 | Query failed */
    const QUERY_FAILED = 3001;
    /** 导入成功 | Import successful */
    const IMPORT_SUCCESS = 3002;
    /** 导入失败 | Import failed */
    const IMPORT_FAILED = 3003;
    /** 导出成功 | Export successful */
    const EXPORT_SUCCESS = 3004;
    /** 批量操作成功 | Batch operation successful */
    const BATCH_SUCCESS = 3005;
    /** 批量操作部分失败 | Batch operation partially failed */
    const BATCH_PARTIAL_FAILED = 3006;

    // ==================== 4xxx 认证授权类 | Auth ====================
    /** 通用错误 | General error */
    const ERROR = 4000;
    /** 认证失败（密码错误、令牌无效） | Authentication failed */
    const AUTH_FAILED = 4001;
    /** 令牌已过期 | Token expired */
    const TOKEN_EXPIRED = 4002;
    /** 被踢出（SSO单点登录冲突） | SSO conflict, logged in elsewhere */
    const SSO_CONFLICT = 4003;
    /** 令牌缺失 | Token missing */
    const TOKEN_MISSING = 4004;
    /** 参数验证失败 | Validation failed */
    const VALIDATION_FAILED = 4005;
    /** 权限不足 | Permission denied */
    const PERMISSION_DENIED = 4006;
    /** 账户已锁定 | Account locked */
    const ACCOUNT_LOCKED = 4007;
    /** 旧密码不正确 | Old password incorrect */
    const OLD_PASSWORD_WRONG = 4008;
    /** 频率限制 | Rate limited */
    const RATE_LIMITED = 4009;

    // ==================== 5xxx 系统错误类 | System ====================
    /** 服务器内部错误 | Internal server error */
    const SERVER_ERROR = 5000;
    /** 数据库错误 | Database error */
    const DB_ERROR = 5001;
    /** 文件上传失败 | File upload failed */
    const FILE_UPLOAD_FAILED = 5002;
    /** 邮件发送失败 | Email send failed */
    const EMAIL_SEND_FAILED = 5003;
    /** 第三方接口错误 | Third-party API error */
    const THIRD_PARTY_ERROR = 5004;

    // ==================== 别名 | Aliases ====================
    /** 参数验证失败 (别名) | Validation failed (alias) */
    const VALIDATION_ERROR = self::VALIDATION_FAILED;
    /** 认证失败 (别名) | Auth failed (alias) */
    const INVALID_CREDENTIALS = self::AUTH_FAILED;
    /** 服务器错误 (别名) | Server error (alias) */
    const INTERNAL_ERROR = self::SERVER_ERROR;

    /**
     * 获取状态码对应的多语言消息键
     * Get the i18n message key for a status code
     *
     * @param int $code 状态码 | Status code
     * @return string 多语言键 | i18n key
     */
    public static function messageKey(int $code): string
    {
        $map = [
            self::SUCCESS => 'response.success',
            self::CREATED => 'response.created',
            self::UPDATED => 'response.updated',
            self::DELETED => 'response.deleted',
            self::UPLOADED => 'response.uploaded',
            self::REGISTER_SUCCESS => 'response.register_success',
            self::EMAIL_EXISTS => 'response.email_exists',
            self::PHONE_EXISTS => 'response.phone_exists',
            self::INVALID_INVITER => 'response.invalid_inviter',
            self::INVITER_DISABLED => 'response.inviter_disabled',
            self::INVALID_COMMISSION_RATE => 'response.invalid_commission_rate',
            self::INVALID_GROUP => 'response.invalid_group',
            self::USER_NOT_FOUND => 'response.user_not_found',
            self::USER_DISABLED => 'response.user_disabled',
            self::USER_CANCELLED => 'response.user_cancelled',
            self::DATA_NOT_FOUND => 'response.data_not_found',
            self::OPERATION_NOT_ALLOWED => 'response.operation_not_allowed',
            self::COMMISSION_EXCEEDS_PARENT => 'response.commission_exceeds_parent',
            self::ERROR => 'response.error',
            self::AUTH_FAILED => 'response.auth_failed',
            self::TOKEN_EXPIRED => 'response.token_expired',
            self::SSO_CONFLICT => 'response.sso_conflict',
            self::TOKEN_MISSING => 'response.token_missing',
            self::VALIDATION_FAILED => 'response.validation_failed',
            self::PERMISSION_DENIED => 'response.permission_denied',
            self::OLD_PASSWORD_WRONG => 'response.old_password_wrong',
            self::SERVER_ERROR => 'response.server_error',
            self::DB_ERROR => 'response.db_error',
            self::FILE_UPLOAD_FAILED => 'response.file_upload_failed',
        ];

        return $map[$code] ?? 'response.unknown';
    }
}
