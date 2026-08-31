<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:39
 */

declare(strict_types=1);

namespace App\Models;

/**
 * MT4 用户建仓出队记录模型。
 *
 * 文件功能：
 * - 映射 user_mt4_provisioning_outbox 表，记录新用户创建后在 MT4 端建仓的出队事件。
 * - 支持异步建仓与对账重试机制（reconciliation_attempts）。
 *
 * 适用场景：
 * - 用户注册成功后，DispatchPendingUserMt4Provisioning 定时任务消费出队记录。
 * - MT4 建仓包括：创建交易账户、设置杠杆、分配交易组。
 *
 * 主要字段：
 * - user_login_id：关联的 user_logins 记录 ID。
 * - user_info_id：关联的 user_infos 记录 ID。
 * - user_id：业务用户 ID。
 * - status：出队状态（pending/retryable/processing/processed/rejected/unknown/manual_reconcile_required）。
 * - attempts：已重试次数（建仓重试）。
 * - reconciliation_attempts：对账重试次数（建仓成功后验证）。
 * - payload_ciphertext：加密的建仓参数载荷。
 *
 * 消费语义：
 * - 注册事务先写入 pending；远端同步开启时注册服务可立即尝试处理，定时扫描器负责重试与陈旧任务兜底。
 * - 远端同步关闭时注册只完成本地业务，记录保持 pending，扫描命令零派发。
 * - 处理器以 status=processing 加锁声明任务，基于 payload_hash 与对账重试避免重复建仓；pending/retryable/unknown 到期记录与锁超 5 分钟的陈旧 processing 记录都会被重新派发。
 * - payload_ciphertext 与 payload_hash 已列入 $hidden，序列化时不输出，避免敏感载荷进入接口响应或日志。
 *
 * 关联关系：
 * - userLogin()：关联的 UserLogin。
 * - userInfo()：关联的 UserInfo。
 */

class UserMt4ProvisioningOutbox extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 user_mt4_provisioning_outbox。
     */
    protected $table = 'user_mt4_provisioning_outbox';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - user_login_id：关联的 user_logins 记录 ID。
     * - user_info_id：关联的 user_infos 记录 ID。
     * - user_id：业务用户 ID，唯一索引保证每个用户只有一条在途建仓记录。
     * - status：出队状态，业务侧通常只写入 pending，后续推进由消费方负责。
     * - attempts：建仓重试次数。
     * - reconciliation_attempts：对账重试次数（建仓成功后验证 MT4 侧结果）。
     * - payload_ciphertext：加密的建仓参数载荷。
     * - payload_hash：载荷指纹，用于对账防重。
     * - available_at：最早可消费时间（Unix 秒，空表示立即可消费）。
     * - locked_at：领取锁时间，用于陈旧处理记录回收判断。
     * - processed_at：处理完成时间。
     * - provider_reference：MT4 返回的账户或票据引用号。
     * - last_error_code：最近一次失败的错误码。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_login_id',
        'user_info_id',
        'user_id',
        'status',
        'attempts',
        'reconciliation_attempts',
        'payload_ciphertext',
        'payload_hash',
        'available_at',
        'locked_at',
        'processed_at',
        'provider_reference',
        'last_error_code',
    ];

    /**
     * 序列化隐藏字段。
     *
     * 逻辑说明：
     * - payload_ciphertext 是加密的建仓参数，payload_hash 是载荷指纹；两者都不应进入接口响应或日志。
     *
     * @var array<int, string>
     */
    protected $hidden = ['payload_ciphertext', 'payload_hash'];

    /**
     * 字段类型转换。
     *
     * 逻辑说明：
     * - user_login_id/user_info_id/user_id 与重试计数转为整数，便于精确比较。
     * - available_at/locked_at/processed_at 按日期对象处理，序列化输出统一格式（见 BaseModel::serializeDate）。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_login_id' => 'integer',
        'user_info_id' => 'integer',
        'user_id' => 'integer',
        'attempts' => 'integer',
        'reconciliation_attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * 关联出队记录对应的前台登录账号。
     *
     * 逻辑说明：
     * - user_mt4_provisioning_outbox.user_login_id 对应 user_logins.id。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 对应的前台登录账号。
     */
    public function userLogin()
    {
        return $this->belongsTo(UserLogin::class, 'user_login_id');
    }

    /**
     * 关联出队记录对应的用户业务资料。
     *
     * 逻辑说明：
     * - user_mt4_provisioning_outbox.user_info_id 对应 user_infos.id。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 对应的用户业务资料。
     */
    public function userInfo()
    {
        return $this->belongsTo(UserInfo::class, 'user_info_id');
    }
}
