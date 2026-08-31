<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 18:11
 */

declare(strict_types=1);

namespace App\Models;

/**
 * 管理员实名审核出箱模型。
 *
 * 文件功能：
 * - 持久化管理员实名审核意图与 MT4 交付状态（admin_auth_review_outboxes）：加密审核载荷、
 *   状态机（pending/processing/retryable/...）、重试计数、认领锁与完成时间都由该表承载，
 *   由 DispatchPendingAdminAuthReviews 投递、ProcessAdminAuthReview 消费。
 * - 明确不负责：审核决定的业务规则映射（AuthReviewTransition）与 MT4 侧写入（Mt4ManagerService）。
 */
class AdminAuthReviewOutbox extends BaseModel
{
    /**
     * 显式绑定 outbox 表名：表名由迁移固定为 admin_auth_review_outboxes，
     * 不依赖 Laravel 的类名复数推断，避免类改名后指向错误表。
     *
     * @var string
     */
    protected $table = 'admin_auth_review_outboxes';

    /**
     * 允许批量赋值的 outbox 字段白名单。
     * - 归属字段：user_id（业务用户）、active_user_id（生效用户）、admin_id/admin_name/request_ip（审核人上下文）；
     * - 状态机字段：status（pending/processing/retryable/...）、attempts（重试次数）、available_at（可投递时间）、locked_at（认领锁）、processed_at（完成时间）；
     * - 载荷字段：payload_ciphertext（加密审核载荷）、payload_hash/auth_snapshot_hash（防篡改摘要）；
     * - last_error_code 记录最近一次失败原因，供排查。
     * 白名单外字段（如 id）禁止批量赋值，防止越权篡改状态机。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'active_user_id',
        'admin_id',
        'admin_name',
        'request_ip',
        'status',
        'attempts',
        'payload_ciphertext',
        'payload_hash',
        'auth_snapshot_hash',
        'available_at',
        'locked_at',
        'processed_at',
        'last_error_code',
    ];

    /**
     * 序列化时隐藏的敏感字段：加密审核载荷与两个防篡改摘要。
     * 即使是密文也不随模型输出（日志/调试/JSON 响应），保证审核原始材料只在处理器内部解密使用。
     *
     * @var array<int, string>
     */
    protected $hidden = ['payload_ciphertext', 'payload_hash', 'auth_snapshot_hash'];

    /**
     * 字段类型转换：归属与 attempts 转 int 保证锁比较与重试计数是数值语义；
     * available_at/locked_at/processed_at 转 datetime，供“到期可投递”“认领是否过期（5 分钟陈旧阈值）”的时间比较直接可用。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'active_user_id' => 'integer',
        'admin_id' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
