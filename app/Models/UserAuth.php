<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 16:58
 */

namespace App\Models;

/**
 * 用户实名认证模型。
 *
 * 文件功能：
 * - user_auths 表保存前台用户实名和银行卡认证资料，是后台实名认证审核、银行卡审核和前台资料展示的数据来源。
 * - user_id 表示认证资料所属业务用户 ID，对应 user_infos.user_id。
 * - bank_no 和 bank_no_tmp 表示已审核银行卡号与待审核银行卡号。
 * - bank_name 和 bank_name_tmp 表示已审核开户行名称与待审核开户行名称。
 * - bank_addr 和 bank_addr_tmp 表示已审核开户地址与待审核开户地址。
 * - bank_card_img、bank_card_back_img 及 tmp 字段表示银行卡图片和待审核图片。
 * - bank_status 表示银行卡审核状态，bank_remarks 表示银行卡审核备注。
 * - id_card_no 表示身份证号码，id_card_front 和 id_card_back 表示身份证正反面图片。
 * - id_card_status 表示身份证审核状态，id_card_remarks 表示身份证审核备注。
 * - is_bank_synced 表示银行卡信息是否已同步到后续资金或交易系统。
 */
class UserAuth extends BaseModel
{
    /**
     * 审核列表统一展示字段。
     *
     * @var array<int, string>
     */
    protected $appends = [
        'review_bank_no',
        'review_bank_name',
        'review_bank_addr',
        'review_bank_img',
        'review_bank_back_img',
    ];

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 user_auths。
     */
    protected $table = 'user_auths';

    /**
     * 允许批量写入的认证字段。
     *
     * 参数逻辑说明：
     * - $fillable 表示旧表单、后台审核接口和前台资料接口允许写入的字段白名单。
     * - 其中 real_name、id_card、status、audit_time 等字段保留旧项目兼容入口；当前数据库不存在同名字段时，调用方仍必须按真实表结构过滤后再写入。
     *
     * @var array<int, string> $fillable 表示可批量赋值字段名称列表。
     */
    protected $fillable = [
        'user_id', 'real_name', 'id_card', 'id_card_no', 'id_card_front', 'id_card_back',
        'id_card_hand', 'id_card_status', 'status', 'audit_time', 'audit_remark',
        'bank_no', 'bank_no_tmp', 'bank_name', 'bank_name_tmp', 'bank_addr', 'bank_addr_tmp',
        'bank_branch', 'bank_account', 'bank_user', 'bank_card_img', 'bank_card_back_img', 'bank_card_img_tmp', 'bank_card_back_img_tmp',
        'bank_status', 'bank_remarks', 'id_card_remarks', 'is_bank_synced'
    ];

    /**
     * 获取当前待审核或已审核记录应展示的银行卡号。
     */
    public function getReviewBankNoAttribute(): string
    {
        return $this->reviewBankValue('bank_no');
    }

    /**
     * 获取当前待审核或已审核记录应展示的开户行。
     */
    public function getReviewBankNameAttribute(): string
    {
        return $this->reviewBankValue('bank_name');
    }

    /**
     * 获取当前待审核或已审核记录应展示的开户地址。
     */
    public function getReviewBankAddrAttribute(): string
    {
        return $this->reviewBankValue('bank_addr');
    }

    /**
     * 获取当前银行卡审核应展示的正面图片。
     */
    public function getReviewBankImgAttribute(): string
    {
        return $this->reviewBankValue('bank_card_img');
    }

    /**
     * 获取当前银行卡审核应展示的反面图片。
     */
    public function getReviewBankBackImgAttribute(): string
    {
        return $this->reviewBankValue('bank_card_back_img');
    }

    /**
     * 关联认证资料所属业务用户。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 user_auths.user_id，表示认证资料属于哪个前台业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目业务用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回认证资料所属的 UserInfo 用户资料关系。
     */
    public function userInfo()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }

    /**
     * 换绑待审状态优先展示非空临时值，其余状态展示正式值。
     */
    private function reviewBankValue(string $field): string
    {
        $formalValue = (string) ($this->attributes[$field] ?? '');
        $temporaryValue = (string) ($this->attributes[$field . '_tmp'] ?? '');

        if ((int) ($this->attributes['bank_status'] ?? 0) === 3 && trim($temporaryValue) !== '') {
            return $temporaryValue;
        }

        return $formalValue;
    }
}
