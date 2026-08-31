<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:02
 */

namespace App\Http\Controllers\Front;

use App\Models\PaymentChannel;
use App\Services\Payment\PaymentCallbackService;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\Gateways\OtcAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * 前台支付回调控制器。
 *
 * 文件功能：
 * - 兼容旧前台 legacy /user/deposit_*、legacy /user/withdraw_* 支付回调路径。
 * - 提供新前台 `POST /api/front/payment/notify/{gateway}` 异步通知入口。
 * - 提供新前台 `GET /api/front/payment/return/{gateway}` 同步返回入口。
 * - 在逐通道 adapter、验签、金额和商户绑定完成前，所有通知都失败关闭，绝不修改入金状态。
 *
 * 安全边界：
 * - 未知网关返回 404，已知但未接入 adapter 的网关返回 422，验签失败返回 400；所有失败路径均不查询、不更新订单。
 * - 防重放由 PaymentCallbackService 的行锁加状态机保证：重复回调最多推进一次订单状态，控制器不做二次幂等承诺。
 * - 日志只记录请求体哈希（payload_hash）与拒绝原因，不记录可能含签名、身份或支付数据的完整 payload，更不记录密钥。
 * - legacyCallback 只把白名单内的旧 return 路径放行到同步页，其余旧路径统一进入 notify 的失败关闭边界。
 */
class PaymentNotifyController extends FrontBaseController
{
    /**
     * 处理旧前台支付回调入口。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，包含旧支付通道回传的 query、form 和 path 信息。
     * - path：旧路由路径，用于区分入金通知、入金同步返回和出金通知。
     * - gateway 表示支付网关标识，由 legacyGatewayName 根据旧路由路径转换得到。
     * - 同步 return 只负责页面跳转；所有 notify（包括旧出金 OTC）统一进入失败关闭的 adapter 边界。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function legacyCallback(Request $request)
    {
        // 先归一化旧路径并映射统一网关标识；映射不到白名单的路径返回 legacy，仍走 notify 的失败关闭边界。
        $path = trim($request->path(), '/');
        $gateway = $this->legacyGatewayName($path);

        // 旧协议同步 return 白名单：只做浏览器跳转，不具备支付证明能力，最终结果仍以异步通知为准。
        if (in_array($path, [
            'user/deposit_return',
            'user/deposit_return2',
            'user/deposit_wppay_return',
            'user/deposit_exlink_bbreturn',
            'user/deposit_exlink_fbreturn',
            'user/deposit_btb_return',
        ], true)) {
            return $this->returnPage($request, $gateway);
        }

        // 现有 PaymentCallbackService 只处理入金。两个旧出金 gateway 只能绑定预留的
        // OtcAdapter；其他可验签入金 adapter 必须在进入入金状态机前被拒绝。
        if (in_array($gateway, ['otc_withdraw_notify', 'otc_withdraw_verify'], true)) {
            return $this->notify($request, $gateway, OtcAdapter::class);
        }

        // 其余旧入金 notify 路径与新版共用同一套 adapter 验签边界。
        return $this->notify($request, $gateway);
    }

    /**
     * 处理支付异步通知。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，payload 表示第三方支付平台回传的完整参数。
     * - gateway 表示支付网关标识，对应新路由 `{gateway}` 或旧路由映射后的统一名称。
     * - requiredAdapterClass 仅用于旧 OTC 出金回调，强制绑定预留 OtcAdapter，阻止误入入金适配器。
     * - 已知旧网关只表示路由可识别，不表示当前商户、密钥和 adapter 已配置。
     * - 未知网关返回 404；已知但未配置/未接入 adapter 的网关返回 422，且不查询或更新入金订单。
     * - 日志只保存请求体哈希和拒绝原因，不记录可能含签名、身份或支付数据的完整 payload。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param string $gateway 支付网关标识。
     * @param string|null $requiredAdapterClass 可选的适配器类型边界。
     * @return \Illuminate\Http\Response
     */
    public function notify(Request $request, string $gateway, string $requiredAdapterClass = null)
    {
        // 网关识别阶段：渠道表已启用记录或旧协议白名单之外一律按未知网关 404，避免探测性请求触达后续校验细节。
        $channel = PaymentChannel::enabled()->where('channel_code', $gateway)->first();
        $knownLegacyGateway = in_array($gateway, $this->knownLegacyGateways(), true);
        if (!$channel && !$knownLegacyGateway) {
            return response('gateway_not_found', 404);
        }

        // 适配器解析阶段：网关已知但没有可用 adapter 配置时失败关闭，不进入验签也不读取订单。
        $resolved = $channel
            ? app(PaymentGatewayRegistry::class)->resolve($channel, $gateway)
            : null;
        if ($resolved === null) {
            $this->logCallbackRejection($request, $gateway, 'callback_not_configured');

            return response('callback_not_configured', 422);
        }

        // 验签阶段：验签失败整体拒绝回调，已验签请求才允许继续解析和推进订单状态。
        $adapter = $resolved['adapter'];
        $config = $resolved['config'];
        if ($requiredAdapterClass !== null && !$adapter instanceof $requiredAdapterClass) {
            $this->logCallbackRejection($request, $gateway, 'callback_not_configured');

            return response('callback_not_configured', 422);
        }
        if (!$adapter->verifyCallback($request, $config)) {
            $this->logCallbackRejection($request, $gateway, 'invalid_signature');

            return response('invalid_signature', 400);
        }

        try {
            // 解析与幂等处理阶段：解析成功后交给 PaymentCallbackService，行锁加状态机保证重复通知最多推进一次状态。
            $callback = $adapter->parseCallback($request, $config);
            app(PaymentCallbackService::class)->handle($callback);

            // 应答阶段：订单状态已推进，按网关协议确认接收，通知网关停止重试。
            return $adapter->acknowledge($callback);
        } catch (InvalidArgumentException $exception) {
            // 业务校验失败（订单不存在、回调标识不匹配、状态转换非法）：拒绝回调，订单状态未知时不做任何补偿。
            $this->logCallbackRejection($request, $gateway, 'invalid_callback', $exception);

            return response('invalid_callback', 422);
        } catch (Throwable $exception) {
            // 处理异常：返回 500 失败关闭，绝不伪造成功应答，避免网关误认为已处理而停止重试导致回调丢失。
            $this->logCallbackRejection($request, $gateway, 'callback_processing_failed', $exception);

            return response('callback_processing_failed', 500);
        }

        // 未完成逐通道 adapter、验签、金额和商户绑定前，任何通知都不得修改订单状态。
        return response('callback_not_configured', 422);
    }

    /**
     * 记录回调拒绝审计日志。
     *
     * 只保存网关、路径、请求体哈希和拒绝原因；不保存完整 payload，避免签名、身份或支付数据落入日志。
     *
     * @param Request $request 当前 HTTP 请求对象，用于读取路径与原始请求体。
     * @param string $gateway 支付网关标识。
     * @param string $reason 拒绝原因标识，例如 invalid_signature、callback_not_configured。
     * @param Throwable|null $exception 可选异常，存在时只记录异常类名，不记录消息堆栈中的敏感内容。
     * @return void
     */
    private function logCallbackRejection(
        Request $request,
        string $gateway,
        string $reason,
        Throwable $exception = null
    ): void {
        $context = [
            'gateway' => $gateway,
            'path' => $request->path(),
            'payload_hash' => hash('sha256', (string) $request->getContent()),
            'reason' => $reason,
        ];
        if ($exception !== null) {
            $context['exception_class'] = get_class($exception);
        }

        Log::warning('front.payment.callback_rejected', $context);
    }

    /**
     * 处理支付同步返回页面跳转。
     *
     * 同步 return 只负责把浏览器带回入金页，不具备支付证明能力：固定 status=pending，
     * 最终支付结果只接受已验签的异步通知，避免前端以同步返回误判支付成功。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param string $gateway 支付网关标识。
     * @return \Illuminate\Http\RedirectResponse
     */
    public function returnPage(Request $request, string $gateway)
    {
        return redirect()->route('front_page_deposit', [
            'gateway' => $gateway,
            // 同步浏览器跳转不具备支付证明能力，展示态固定 pending，最终状态只接受已验签异步通知。
            'status' => 'pending',
        ]);
    }

    /**
     * legacyGatewayName 用于把旧路由路径映射为统一网关标识。
     *
     * 参数含义：
     * - $path：去掉首尾斜杠后的旧前台支付回调路径。
     * - $map：旧路由到统一网关标识的映射表，覆盖默认入金、虎付、wppay、exlink、btb、passto、switch 和 OTC 等历史路径。
     * - 返回 legacy 表示未知旧路径，仍保留日志记录能力，方便后续补充真实通道映射。
     *
     * @param string $path 旧前台回调路径。
     * @return string 统一支付网关标识。
     */
    private function legacyGatewayName(string $path): string
    {
        $map = [
            'user/deposit_notfiy' => 'legacy_default',
            'user/deposit_notfiy2' => 'legacy_default_2',
            'user/deposit_tigerpay_notify' => 'tigerpay',
            'user/deposit_wppay_notify' => 'wppay',
            'user/deposit_wppay_return' => 'wppay',
            'user/deposit_exlink_bbnotify' => 'exlink_bb',
            'user/deposit_exlink_bbreturn' => 'exlink_bb',
            'user/deposit_exlink_fbnotify' => 'exlink_fb',
            'user/deposit_exlink_fbreturn' => 'exlink_fb',
            'user/deposit_btb_notify' => 'btb',
            'user/deposit_btb_return' => 'btb',
            'user/deposit_passto_notify' => 'passto',
            'user/deposit_switch_notify' => 'switch',
            'user/deposit_notfiy_otc' => 'otc_deposit',
            'user/withdraw_notfiy_otc' => 'otc_withdraw_notify',
            'user/withdraw_verify_otc' => 'otc_withdraw_verify',
            'user/deposit_return' => 'legacy_default',
            'user/deposit_return2' => 'legacy_default_2',
        ];

        return $map[$path] ?? 'legacy';
    }

    /**
     * 返回旧路由能够映射的网关白名单；存在路由不代表已具备可用签名配置。
     *
     * @return array<int, string>
     */
    private function knownLegacyGateways(): array
    {
        return [
            'legacy_default', 'legacy_default_2', 'tigerpay', 'wppay',
            'exlink_bb', 'exlink_fb', 'btb', 'passto', 'switch',
            'otc_deposit', 'otc_withdraw_notify', 'otc_withdraw_verify',
        ];
    }
}
