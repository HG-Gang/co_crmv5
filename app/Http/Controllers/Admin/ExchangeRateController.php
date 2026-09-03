<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\OperationLog;
use App\Models\PaymentChannel;
use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台汇率配置控制器。
 *
 * 文件功能：
 * - 旧项目把入金汇率、出金汇率拆在系统配置字段中维护，新项目统一落到 system_configs 表。
 * - 页面读写沿用旧字段名 sys_deposit_rate / sys_draw_rate，同时把值同步到真正参与
 *   资金结算的生效来源，保证「后台改汇率」立即对入金与出金生效。
 * - 页面入口、查看接口和保存接口仍然由 permissions 表配置驱动，接口层由 check.permission:admin 二次鉴权。
 *
 * 汇率的三条真实链路（改动本控制器前必须先理解）：
 * - 出金结算：system_configs.withdraw_exchange_rate_cny → WithdrawalOrderService；
 * - 入金结算：payment_channels.exchange_rate → PaymentOrderService；
 * - 入金页展示：system_configs.deposit_exchange_rate_cny → Front\DepositController。
 * sys_deposit_rate / sys_draw_rate 不在任何结算链路上，只作旧字段名兼容。
 *
 * 适用场景：
 * - 后台汇率配置页面：读取/保存入金与出金换算汇率。
 * - 汇率值入库前统一保留最多 8 位小数并去除无意义尾零，避免浮点噪声写入配置。
 */
class ExchangeRateController extends AdminBaseController
{
    /**
     * 入金汇率配置 key（旧契约键）。
     *
     * 该键沿用项目1 system_config.sys_deposit_rate 的字段名，作用是让旧后台汇率表单
     * 与旧 API 调用方能按原字段名读写；它本身**不参与**入金金额换算。
     * 真正参与换算的是 payment_channels.exchange_rate，见 DEPOSIT_LINKED_CHANNEL_IDS。
     *
     * @var string
     */
    private const DEPOSIT_RATE_KEY = 'sys_deposit_rate';

    /**
     * 出金汇率配置 key（旧契约键）。
     *
     * 同 DEPOSIT_RATE_KEY，仅承载旧字段名兼容；出金金额换算实际读取
     * EFFECTIVE_DRAW_RATE_KEY，因此两者必须在同一事务内一起写。
     *
     * @var string
     */
    private const DRAW_RATE_KEY = 'sys_draw_rate';

    /**
     * 入金汇率的前台展示键。
     *
     * Front\DepositController 读取该键渲染入金页的币种汇率提示（exchange_rates.CNY）。
     * 它只影响展示文案，不参与落库金额计算，但必须与实际换算汇率同步，
     * 否则页面显示的汇率与实际到账金额不一致，属用户可见的口径矛盾。
     *
     * @var string
     */
    private const EFFECTIVE_DEPOSIT_RATE_KEY = 'deposit_exchange_rate_cny';

    /**
     * 出金汇率的唯一生效键。
     *
     * Services\Withdrawal\WithdrawalOrderService 读取该键计算 rmb_fee 与实际出金本币金额。
     * 只写 DRAW_RATE_KEY 而不写本键，会导致后台改出金汇率完全不生效。
     *
     * @var string
     */
    private const EFFECTIVE_DRAW_RATE_KEY = 'withdraw_exchange_rate_cny';

    /**
     * 跟随入金汇率联动的支付通道 ID 白名单。
     *
     * 取值依据是项目1 ExchangeRateController::whpj_rate_save()：保存一次入金汇率时，
     * sys_deposit_rate 与 sys_deposit_rate2/3/6/7/8/9/10/11 会被写成同一个值，
     * 而 sys_deposit_rate4/5 不在联动列内（项目1 seeder 将其固定为 1.0，
     * 对应加密货币与数字货币通道，金额不做本币换算）。
     *
     * 因此这里按通道编号而非「当前汇率是否为 1」判断——后者会随运维改动漂移，
     * 而旧联动清单是稳定事实源。通道 4、5 的 exchange_rate 必须保持不被覆盖。
     *
     * @var array<int, int>
     */
    private const DEPOSIT_LINKED_CHANNEL_IDS = [1, 2, 3, 6, 7, 8, 9, 10, 11];

    /**
     * 出金手续费总开关配置键。'1' 扣费、'0' 不扣。
     *
     * 与两个金额键相互独立：关闭时 WithdrawalOrderService 把固定费与费率一并按 0 计算，
     * 但原配置值仍保留在 system_configs 中，重新开启即恢复既有标准。
     * 由 2026_08_30_000001 迁移写入，缺键时各处一律按 '1' 兜底。
     *
     * @var string
     */
    private const FEE_ENABLED_KEY = 'withdrawal_fee_enabled';

    /**
     * 出金固定手续费配置键，单位 USD。参与 fee = 固定费 + 申请金额 × 费率 / 100。
     *
     * @var string
     */
    private const FIXED_FEE_KEY = 'withdrawal_fixed_fee_usd';

    /**
     * 出金比例手续费配置键，单位为百分数（0..100），服务层计算时会除以 100。
     *
     * 语义必须是百分数而非分数：WithdrawalOrderService::settlementSnapshot() 里
     * `bcdiv(bcmul($amount, $feeRate, 10), '100', 3)` 已做除 100 处理，
     * 若这里按分数存 0.05，实扣会变成万分之五而不是百分之五。
     *
     * @var string
     */
    private const FEE_RATE_KEY = 'withdrawal_fee_rate';

    /**
     * 获取当前汇率配置。
     *
     * 参数逻辑说明：
     * - 本接口当前不需要业务入参，登录管理员身份和接口权限由中间件处理。
     * - 返回值中的 sys_deposit_rate 表示入金换算汇率，sys_draw_rate 表示出金/取款换算汇率。
     *
     * @param Request $request 请求对象；保留参数用于后续审计、日志或扩展筛选。
     * @return \Illuminate\Http\JsonResponse
     */
    public function info(Request $request)
    {
        return $this->success([
            self::DEPOSIT_RATE_KEY => $this->getRateValue(self::DEPOSIT_RATE_KEY),
            self::DRAW_RATE_KEY => $this->getRateValue(self::DRAW_RATE_KEY),
            // 出金手续费三项：开关 + 固定费 + 比例费。
            // 开关缺键时回显 '1'，与 WithdrawalOrderService::loadConfiguration() 的可选键兜底
            // 保持同一口径，避免页面显示「不扣」而服务层实际扣费。
            self::FEE_ENABLED_KEY => $this->getRateValue(self::FEE_ENABLED_KEY) === '0' ? '0' : '1',
            self::FIXED_FEE_KEY => $this->getRateValue(self::FIXED_FEE_KEY),
            self::FEE_RATE_KEY => $this->getRateValue(self::FEE_RATE_KEY),
        ], __('admin.exchange_rate_info_fetched'));
    }

    /**
     * 保存汇率与手续费配置。
     *
     * 参数逻辑说明：
     * - sys_deposit_rate：入金汇率，要求为大于 0.000001 的数字，写入 system_configs.key=sys_deposit_rate。
     * - sys_draw_rate：出金汇率，要求为大于 0.000001 的数字，写入 system_configs.key=sys_draw_rate。
     * - withdrawal_fee_enabled：出金手续费开关，可选，接受 0/1 或 true/false。
     * - withdrawal_fixed_fee_usd：出金固定手续费（USD），可选，范围 0~100000。
     * - withdrawal_fee_rate：出金比例手续费（百分数 0~100），可选，服务层计算时会除以 100。
     * - 两个汇率字段入库前统一规范化为最多 8 位小数并去除无意义尾零。
     *
     * 为什么一次保存要写四个 system_configs 键并批量更新 payment_channels：
     * - sys_deposit_rate / sys_draw_rate 只是旧字段名兼容层，业务代码从不读它们做换算。
     * - 出金换算读 withdraw_exchange_rate_cny（WithdrawalOrderService），
     *   入金换算读 payment_channels.exchange_rate（PaymentOrderService），
     *   入金页展示读 deposit_exchange_rate_cny（Front\DepositController）。
     * - 只写旧键会让后台改了汇率但资金仍按旧汇率结算，且页面看不出异常，
     *   属于最难察觉的资金口径缺陷，因此必须在同一事务内全部同步。
     *
     * 入金通道联动范围复刻项目1 whpj_rate_save()：法币通道（1/2/3/6/7/8/9/10/11）全部跟随同一汇率，
     * 加密货币/数字货币通道（4/5）不参与联动，保持其 exchange_rate 原值。
     *
     * @param Request $request 请求对象，承载页面提交的汇率与手续费字段。
     * @return \Illuminate\Http\JsonResponse 保存成功返回最新生效值；校验失败返回错误响应。
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            self::DEPOSIT_RATE_KEY => 'required|numeric|min:0.000001',
            self::DRAW_RATE_KEY => 'required|numeric|min:0.000001',
            // 手续费三项均为可选：本接口既服务于「只改汇率」的旧调用方，也服务于新增的手续费区块。
            // 未提交的字段保持库内原值不动，不会被当成 0 覆盖。
            // 开关接受 0/1 与 true/false 两种写法：Layui switch 提交 'on'/缺省，
            // 现代表单可能提交布尔，统一由 normalizeSwitch() 归一后再入库。
            self::FEE_ENABLED_KEY => 'sometimes|in:0,1,true,false,on,off',
            // 固定费上限 100000：远高于任何合理手续费，用于挡住误把「申请金额」填进手续费框的输入。
            self::FIXED_FEE_KEY => 'sometimes|numeric|min:0|max:100000',
            // 比例费是百分数 0..100，与服务层 /100 的算法口径一致；填 5 表示 5%。
            self::FEE_RATE_KEY => 'sometimes|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        // 规范化入库值并留存变更前值，供操作日志审计汇率调整过程。
        $depositRate = $this->normalizeRate($request->input(self::DEPOSIT_RATE_KEY));
        $drawRate = $this->normalizeRate($request->input(self::DRAW_RATE_KEY));
        $beforeDepositRate = $this->getRateValue(self::DEPOSIT_RATE_KEY);
        $beforeDrawRate = $this->getRateValue(self::DRAW_RATE_KEY);

        // 手续费三项：只处理本次实际提交的字段，未提交者保留原值。
        // 用 has()（判断「键是否提交」）而非 filled()（判断「值是否非空」）：
        // 二者语义不同，这里要表达的是前者 —— 未提交的字段必须保留库内原值，
        // 而不是被当成 0 覆盖，这是「关闭开关不丢失原费率」所依赖的行为。
        // 注：Laravel 的 blank('0') 为 false，故 filled('0') 为 true，
        // 关闭动作本身两种写法都能收到；此处选 has() 是因为语义精确，
        // 不要误以为 filled() 会把 '0' 当空值漏掉（那是 PHP empty('0') 的语义）。
        $feeUpdates = [];
        if ($request->has(self::FEE_ENABLED_KEY)) {
            $feeUpdates[self::FEE_ENABLED_KEY] = $this->normalizeSwitch($request->input(self::FEE_ENABLED_KEY));
        }
        if ($request->has(self::FIXED_FEE_KEY)) {
            $feeUpdates[self::FIXED_FEE_KEY] = $this->normalizeRate($request->input(self::FIXED_FEE_KEY));
        }
        if ($request->has(self::FEE_RATE_KEY)) {
            $feeUpdates[self::FEE_RATE_KEY] = $this->normalizeRate($request->input(self::FEE_RATE_KEY));
        }

        // 旧键与生效键必须同时成功或同时回滚：任何一半写入都会让展示汇率与结算汇率脱节。
        DB::transaction(function () use ($depositRate, $drawRate, $feeUpdates): void {
            SystemConfig::updateOrCreate(
                ['key' => self::DEPOSIT_RATE_KEY],
                [
                    'value' => $depositRate,
                    'group' => 'exchange_rate',
                    'description' => __('admin.sys_deposit_rate'),
                ]
            );

            SystemConfig::updateOrCreate(
                ['key' => self::DRAW_RATE_KEY],
                [
                    'value' => $drawRate,
                    'group' => 'exchange_rate',
                    'description' => __('admin.sys_draw_rate'),
                ]
            );

            // 生效键：出金结算与入金页展示分别依赖这两个键，group 保持 finance 与既有 seeder/迁移一致，
            // 避免把 required 配置移出 finance 分组后被必填配置校验判为缺失。
            SystemConfig::updateOrCreate(
                ['key' => self::EFFECTIVE_DEPOSIT_RATE_KEY],
                [
                    'value' => $depositRate,
                    'group' => 'finance',
                    'description' => __('admin.sys_deposit_rate'),
                ]
            );

            SystemConfig::updateOrCreate(
                ['key' => self::EFFECTIVE_DRAW_RATE_KEY],
                [
                    'value' => $drawRate,
                    'group' => 'finance',
                    'description' => __('admin.sys_draw_rate'),
                ]
            );

            // 入金金额换算的权威来源：按旧联动清单批量刷新法币通道汇率。
            // 这里是无条件覆盖，与项目1 whpj_rate_save() 的行为一致——旧逻辑同样不保留
            // 各通道的差异化汇率，保存一次即把所有联动通道拉平为同一值。
            PaymentChannel::query()
                ->whereIn('id', self::DEPOSIT_LINKED_CHANNEL_IDS)
                ->update(['exchange_rate' => $depositRate]);

            // 手续费三项与汇率同事务写入：开关与金额若分两次提交，
            // 中间窗口可能出现「开关已开但金额还是旧值」的短暂错扣。
            foreach ($feeUpdates as $key => $value) {
                SystemConfig::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'group' => 'finance',
                        'description' => __('admin.' . $key),
                    ]
                );
            }
        });

        // 落库后写入审计日志，记录变更前后汇率，便于追溯汇率调整。
        $this->writeExchangeRateOperationLog($request, $beforeDepositRate, $beforeDrawRate, $depositRate, $drawRate);

        // 回显本次生效值：手续费三项以库内最终状态为准（本次未提交的字段读原值），
        // 便于页面保存后直接用响应刷新表单，而不必再发一次 info 请求。
        return $this->success([
            self::DEPOSIT_RATE_KEY => $depositRate,
            self::DRAW_RATE_KEY => $drawRate,
            self::FEE_ENABLED_KEY => $this->getRateValue(self::FEE_ENABLED_KEY) === '0' ? '0' : '1',
            self::FIXED_FEE_KEY => $this->getRateValue(self::FIXED_FEE_KEY),
            self::FEE_RATE_KEY => $this->getRateValue(self::FEE_RATE_KEY),
        ], __('admin.exchange_rate_updated'), ResponseCode::UPDATED);
    }

    /**
     * 读取指定汇率配置值。
     *
     * @param string $key system_configs.key，当前只会传入 sys_deposit_rate 或 sys_draw_rate。
     * @return string 配置值；配置不存在时返回空字符串，交给页面展示为空输入框。
     */
    private function getRateValue(string $key): string
    {
        return (string) SystemConfig::where('key', $key)->value('value');
    }

    /**
     * 规范化汇率数字字符串。
     *
     * @param mixed $value 页面提交的汇率值，可能是字符串或数字。
     * @return string 去除无意义尾零后的汇率字符串，便于写入 key/value 配置表。
     */
    /**
     * 把开关类输入归一为 '1' 或 '0' 字符串。
     *
     * 为什么不用 (bool) 或 filter_var 直接转：system_configs 是 key/value 文本表，
     * 而不同前端提交的「开」有多种形态——Layui switch 提交 'on'，
     * 现代表单可能提交布尔 true，旧调用方可能提交 '1'。若用 (bool) 强转，
     * 字符串 'off' 和 'false' 都会被判成 true，导致管理员点了关闭却仍在扣费。
     * 因此这里显式白名单化：只有明确的关闭形态才返回 '0'，其余一律 '1'。
     *
     * @param mixed $value 页面提交的开关值。
     * @return string '1' 表示开启，'0' 表示关闭。
     */
    private function normalizeSwitch($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['0', 'false', 'off', 'no', ''], true) ? '0' : '1';
    }

    private function normalizeRate($value): string
    {
        // 固定 8 位小数格式化后逐层去除尾零与小数点，避免浮点表示噪声写入配置；全零时归一为 '0'。
        $normalized = rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');

        return $normalized === '' ? '0' : $normalized;
    }

    /**
     * 写入汇率变更操作日志。
     *
     * @param Request $request 当前请求对象，用于读取管理员与来源 IP。
     * @param string $beforeDepositRate 变更前入金汇率。
     * @param string $beforeDrawRate 变更前出金汇率。
     * @param string $depositRate 变更后入金汇率。
     * @param string $drawRate 变更后出金汇率。
     * @return void
     */
    private function writeExchangeRateOperationLog(
        Request $request,
        string $beforeDepositRate,
        string $beforeDrawRate,
        string $depositRate,
        string $drawRate
    ): void {
        $admin = $request->user('admin');

        OperationLog::create([
            'admin_id' => $admin ? (int) $admin->id : 0,
            'admin_name' => $admin ? (string) $admin->username : '',
            'target_user_id' => null,
            'order_no' => 'exchange_rate',
            'content' => sprintf(
                'Update exchange rate %s:%s->%s; %s:%s->%s',
                self::DEPOSIT_RATE_KEY,
                $beforeDepositRate,
                $depositRate,
                self::DRAW_RATE_KEY,
                $beforeDrawRate,
                $drawRate
            ),
            'ip' => $request->ip() ?: '',
            'action_type' => 0,
        ]);
    }
}
