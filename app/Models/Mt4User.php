<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 20:42
 */

namespace App\Models;

use App\Models\BaseModel;

/**
 * MT4 用户资金模型。
 *
 * 文件功能：
 * - mt4_users 表保存从 MT4 同步的交易账号资金快照。
 * - login 表示 MT4 登录账号，通常通过 user_infos.mt4_code 映射到业务用户。
 * - balance/equity/margin/margin_free 分别表示余额、净值、已用保证金和可用保证金。
 * - leverage 表示 MT4 账号杠杆，用于后台风险、权益和交易账号信息展示。
 * - 后台数据权限不直接依赖 MT4 账号归属，而是先映射到业务用户后再应用管理员数据范围。
 */
class Mt4User extends BaseModel
{
    /**
     * 模型对应的数据表名称。
     *
     * 参数逻辑说明：
     * - $table 指向 mt4_users 表，保存交易账号最新资金快照。
     *
     * @var string
     */
    protected $table = 'mt4_users';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - login 表示 MT4 登录账号，用于和 user_infos.mt4_code 建立业务用户映射。
     * - name 表示 MT4 侧用户名称，用于后台列表展示和模糊搜索。
     * - group 表示 MT4 交易组，用于财务、交易和风险筛选。
     * - balance/equity/margin/margin_free 表示余额、净值、已用保证金和可用保证金。
     * - leverage 表示 MT4 账号杠杆，用于风险和权益口径展示。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'login',
        'name',
        'group',
        'balance',
        'equity',
        'margin',
        'margin_free',
        'leverage',
    ];

    /**
     * 字段类型转换。
     *
     * 参数逻辑说明：
     * - login、leverage 转为整数，方便和业务账号、风险规则做精确比较。
     * - 资金字段统一转为两位小数，避免后台表格和接口响应出现不一致的小数精度。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'login' => 'integer',
        'balance' => 'decimal:2',
        'equity' => 'decimal:2',
        'margin' => 'decimal:2',
        'margin_free' => 'decimal:2',
        'leverage' => 'integer',
    ];
}
