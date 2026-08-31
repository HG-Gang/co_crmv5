<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:11
 */

namespace App\Http\Controllers\Front;

use App\Mail\FrontFeedbackNotification;
use App\Models\UserAuth;
use App\Services\Legacy\LegacyFormIntentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 旧前台页面控制器。
 *
 * 文件功能：
 * - 负责承接旧项目 legacy user/* 页面入口，并映射到当前 Laravel Blade 模板。
 * - 当前页面全部使用 front_layui:: 命名空间渲染，实际文件位于 resources/front/layui。
 * - 本控制器只维护旧页面入口和少量旧页面参数透传，业务数据仍由对应前台 API 控制器返回。
 *
 * 桥接语义：
 * - 页面身份一律来自 Web Session（legacyFrontUserLogin/legacyFrontUserInfo），查询参数只做展示透传，不能覆盖当前登录用户。
 * - 入金、出金、返佣转账等资金页面在渲染时签发一次性 legacyFormIntentNonce，旧表单提交由后端 LegacyFormIntentService 校验防重放。
 * - 旧资料操作表单统一组装到 legacy-action 页面，提交端点仍指向旧协议 JSON 接口，成功/失败语义由 ProfileController 等后端失败关闭。
 */
class LegacyPageController extends FrontBaseController
{
    /**
     * 渲染旧前台控制台页面。
     *
     * 页面映射：front_layui::dashboard.index_v2。
     * 执行逻辑：GET 入口继续以当前地址加载 iframe；POST /user/indexreg 则改用可 GET 的
     * /user/index?frame=1，避免浏览器加载 iframe 时因请求方法变为 GET 而返回 405。
     *
     * @param Request $request 当前仪表盘页面请求，用于判断旧入口的 HTTP 方法并保留查询参数。
     * @return \Illuminate\Contracts\View\View 返回包含可访问 iframe 地址的服务端 Blade 页面。
     */
    public function dashboard(Request $request)
    {
        $frameSrc = $request->isMethod('get')
            ? $request->fullUrlWithQuery(['frame' => 1])
            : route('legacy_user_index_page', array_merge($request->query(), ['frame' => 1]));

        return view('front_layui::dashboard.index_v2', [
            'frameSrc' => $frameSrc,
        ]);
    }

    /**
     * 渲染旧前台个人资料页面。
     *
     * 页面映射：front_layui::profile.index_v2。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function profile()
    {
        return view('front_layui::profile.index_v2');
    }

    /**
     * 渲染旧前台实名认证上传表单。
     *
     * 入参和返回结果：
     * - 当前 Web Session 用于读取姓名等展示资料，不能由查询参数替换用户身份。
     * - 页面提交到旧 POST `/user/center/uploadIdCard`，成功或失败均由 ProfileController 返回旧协议 JSON。
     *
     * @param Request $request 当前旧前台页面请求。
     * @return \Illuminate\Contracts\View\View 返回仅包含实名认证操作的 Blade 页面。
     */
    public function profileIdentity(Request $request)
    {
        return $this->legacyProfileActionView(
            $request,
            'identity',
            '/user/center/uploadIdCard'
        );
    }

    /**
     * 渲染旧前台银行卡认证上传表单。
     *
     * @param Request $request 当前旧前台页面请求。
     * @return \Illuminate\Contracts\View\View 返回银行卡首次认证的 Session 表单。
     */
    public function profileBank(Request $request)
    {
        return $this->legacyProfileActionView(
            $request,
            'bank',
            '/user/center/uploadBankCard'
        );
    }

    /**
     * 渲染旧前台银行卡换绑表单。
     *
     * 参数和失败场景：
     * - `$type` 原样写入 uploadType，用于兼容旧页面打开方式，不能用于覆盖当前登录用户。
     * - 邮箱前置校验和发码分别走旧 Session 端点；验证码、密码、银行卡审核状态等最终仍由后端失败关闭。
     *
     * @param Request $request 当前旧前台页面请求。
     * @param string $type 旧路由传入的换绑动作类型。
     * @return \Illuminate\Contracts\View\View 返回银行卡换绑及验证码操作页面。
     */
    public function profileBankChange(Request $request, string $type)
    {
        return $this->legacyProfileActionView(
            $request,
            'bank-change',
            '/user/center/uploadChangeBankCard',
            '/user/center/changeBankCardVerifyCode',
            '/user/center/changeBankCardSendCode',
            $type
        );
    }

    /**
     * 渲染旧前台头像上传表单。
     *
     * @param Request $request 当前旧前台页面请求。
     * @return \Illuminate\Contracts\View\View 返回头像选择、预览和提交页面。
     */
    public function profileAvatar(Request $request)
    {
        return $this->legacyProfileActionView(
            $request,
            'avatar',
            '/user/center/uploadHeadImg'
        );
    }

    /**
     * 渲染旧前台手机号或邮箱修改表单。
     *
     * 参数和返回结果：
     * - `$type=phone` 返回当前手机号、新手机号和密码校验表单。
     * - `$type=email` 额外返回邮箱唯一性校验及验证码发送端点。
     * - 其他类型直接返回 404，避免构造未知动作进入共用提交接口。
     *
     * @param Request $request 当前旧前台页面请求。
     * @param string $type 联系方式类型，仅允许 phone 或 email。
     * @return \Illuminate\Contracts\View\View 返回对应联系方式修改页面。
     */
    public function profileContact(Request $request, string $type)
    {
        if (!in_array($type, ['phone', 'email'], true)) {
            abort(404);
        }

        return $this->legacyProfileActionView(
            $request,
            'contact-' . $type,
            '/user/center/updatePhoneEmailInfo',
            $type === 'email' ? '/user/center/updateVerifyInfo' : '',
            $type === 'email' ? '/user/center/updVerifyPassSendCode' : '',
            $type
        );
    }

    /**
     * 渲染旧前台登录密码修改表单。
     *
     * @param Request $request 当前旧前台页面请求。
     * @return \Illuminate\Contracts\View\View 返回旧字段名密码表单；修改成功后后端会注销当前会话。
     */
    public function profilePassword(Request $request)
    {
        return $this->legacyProfileActionView(
            $request,
            'password',
            '/user/editpsw_save'
        );
    }

    /**
     * 组装旧资料操作页的可信服务端上下文。
     *
     * 参数和返回值：
     * - `$action` 决定 Blade 只渲染哪一种表单，值只由上方固定控制器方法传入。
     * - `$submitUrl`、`$verifyUrl`、`$codeUrl` 是旧 Web Session 协议端点。
     * - 当前用户缺少登录、资料或认证记录时以空值渲染，后续 POST 会返回明确业务错误而不会伪造成功。
     *
     * @param Request $request 当前旧前台页面请求。
     * @param string $action 固定页面动作标识。
     * @param string $submitUrl 表单提交地址。
     * @param string $verifyUrl 可选的发码前置校验地址。
     * @param string $codeUrl 可选的验证码发送地址。
     * @param string $legacyType 旧路由传入的动作类型。
     * @return \Illuminate\Contracts\View\View 返回绑定当前用户展示数据和旧端点的统一 Blade 页面。
     */
    private function legacyProfileActionView(
        Request $request,
        string $action,
        string $submitUrl,
        string $verifyUrl = '',
        string $codeUrl = '',
        string $legacyType = ''
    ) {
        $userLogin = $this->legacyFrontUserLogin($request);
        $userInfo = $this->legacyFrontUserInfo($request);
        $userAuth = $userInfo
            ? UserAuth::where('user_id', $userInfo->user_id)->first()
            : null;
        $phone = trim((string) ($userInfo->phone ?? ''));
        $email = trim((string) ($userLogin->email ?? ''));
        $pageMeta = [
            'identity' => ['title' => '实名认证', 'icon' => 'id-card'],
            'bank' => ['title' => '银行卡认证', 'icon' => 'landmark'],
            'bank-change' => ['title' => '更换银行卡', 'icon' => 'refresh-cw'],
            'avatar' => ['title' => '头像设置', 'icon' => 'user-round'],
            'contact-phone' => ['title' => '修改手机号', 'icon' => 'smartphone'],
            'contact-email' => ['title' => '修改邮箱', 'icon' => 'mail'],
            'password' => ['title' => '修改密码', 'icon' => 'key-round'],
        ];

        return view('front_layui::profile.legacy-action', [
            'legacyProfileAction' => $action,
            'legacyProfileSubmitUrl' => $submitUrl,
            'legacyProfileVerifyUrl' => $verifyUrl,
            'legacyProfileCodeUrl' => $codeUrl,
            'legacyProfileType' => $legacyType,
            'legacyProfileTitle' => $pageMeta[$action]['title'],
            'legacyProfileIcon' => $pageMeta[$action]['icon'],
            'legacyProfileUserName' => trim((string) ($userInfo->user_name ?? '')),
            'legacyProfilePhone' => $phone,
            'legacyProfilePhoneMasked' => $this->maskLegacyProfileValue($phone),
            'legacyProfileEmail' => $email,
            'legacyProfileEmailMasked' => $this->maskLegacyProfileEmail($email),
            'legacyProfileAuth' => $userAuth,
        ]);
    }

    /**
     * 对旧资料页展示的手机号或证件类字符串做中段脱敏。
     *
     * @param string $value 原始资料值。
     * @return string 长度大于六位时保留首尾三位；空值或短值返回等长星号。
     */
    private function maskLegacyProfileValue(string $value): string
    {
        $length = strlen($value);
        if ($length === 0) {
            return '';
        }
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 3) . str_repeat('*', $length - 6) . substr($value, -3);
    }

    /**
     * 对旧资料页展示邮箱做本地部分脱敏，同时保留域名供用户核对。
     *
     * @param string $email 当前登录邮箱。
     * @return string 脱敏邮箱；格式无效时复用普通中段脱敏结果。
     */
    private function maskLegacyProfileEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $this->maskLegacyProfileValue($email);
        }

        $local = $parts[0];
        $visibleLength = min(2, strlen($local));

        return substr($local, 0, $visibleLength)
            . str_repeat('*', max(3, strlen($local) - $visibleLength))
            . '@'
            . $parts[1];
    }

    /**
     * 渲染旧前台账户综合页面。
     *
     * 页面映射：front_layui::account.info。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function account()
    {
        return view('front_layui::account.info');
    }

    /**
     * 渲染旧前台凭证提交页面。
     *
     * 页面映射：front_layui::account.voucher。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function voucher()
    {
        return view('front_layui::account.voucher');
    }

    /**
     * 渲染旧前台账户注销页面。
     *
     * 页面映射：front_layui::account.cancel。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function cancelAccount()
    {
        return view('front_layui::account.cancel');
    }

    /**
     * 渲染旧前台入金页面。
     *
     * 页面映射：front_layui::deposit.index_v2。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function deposit(Request $request)
    {
        $nonce = app(LegacyFormIntentService::class)->issue(
            $request,
            'deposit',
            $this->legacyFrontUserId($request)
        );

        return view('front_layui::deposit.index_v2', [
            'legacyFormIntentNonce' => $nonce,
        ]);
    }

    /**
     * 渲染旧前台出金页面。
     *
     * 页面映射：front_layui::withdraw.index。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function withdraw(Request $request)
    {
        $nonce = app(LegacyFormIntentService::class)->issue(
            $request,
            'withdraw',
            $this->legacyFrontUserId($request)
        );

        return view('front_layui::withdraw.index', [
            'legacyFormIntentNonce' => $nonce,
        ]);
    }

    /**
     * 渲染旧前台账户流水页面。
     *
     * 页面映射：front_layui::flow.index。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function flow()
    {
        return view('front_layui::flow.index');
    }

    /**
     * 渲染旧前台下级代理列表页面。
     *
     * 页面映射：front_layui::agent.sub。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function proxyList()
    {
        return view('front_layui::agent.sub');
    }

    /**
     * 渲染旧前台代理等级确认页面。
     *
     * 页面映射：front_layui::agent.confirm-level。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function proxyConfirm()
    {
        return view('front_layui::agent.confirm-level');
    }

    /**
     * 渲染旧前台直属客户详情页面。
     *
     * 参数含义：
     * - $puid：旧路由中的上级代理业务用户 ID。
     * - legacyParentUserId 表示旧直属客户页面传入的上级代理用户 ID。
     *
     * @param int|string $puid 旧路由传入的上级代理用户 ID。
     * @return \Illuminate\Contracts\View\View
     */
    public function proxyDirectCustomerDetail($puid)
    {
        return view('front_layui::agent.customers', ['legacyParentUserId' => (int) $puid]);
    }

    /**
     * 渲染旧前台返佣转账页面。
     *
     * 参数含义：
     * - $uid：旧路由中的目标用户 ID，可为空。
     * - legacyTargetUserId 表示旧返佣转账或组别变更页面传入的目标用户 ID。
     *
     * @param int|string|null $uid 旧路由传入的目标用户 ID。
     * @return \Illuminate\Contracts\View\View
     */
    public function commissionTransfer(Request $request, $uid = null)
    {
        $nonce = app(LegacyFormIntentService::class)->issue(
            $request,
            'commission_transfer',
            $this->legacyFrontUserId($request)
        );

        return view('front_layui::commission.transfer', [
            'legacyTargetUserId' => $uid ? (int) $uid : null,
            'legacyFormIntentNonce' => $nonce,
        ]);
    }

    /**
     * 渲染旧前台代理持仓汇总页面，并兼容详情路由预选代理。
     *
     * 参数和页面行为：
     * - `$id` 来自旧 `user/position/summary/deatil/{id}` 路由；为空时渲染当前代理根汇总页。
     * - 非空 ID 通过 legacyTargetUserId 传给 Blade，Blade 再写入模块的初始 userId 查询条件。
     * - 后续实际数据仍由 PositionController 按代理树权限校验，页面参数本身不能绕过授权。
     *
     * @param int|null $id 旧详情路由中的目标代理业务用户 ID，可选。
     * @return \Illuminate\Contracts\View\View 返回绑定代理持仓汇总 API 的 Blade 页面。
     */
    public function positionSummary(int $id = null)
    {
        return view('front_layui::position.summary', [
            'legacyTargetUserId' => $id && $id > 0 ? $id : null,
        ]);
    }

    /**
     * 渲染旧前台本人交易汇总页面。
     *
     * 业务边界：
     * - summary2 只统计当前登录用户自己的 MT4 交易、余额变动和品种手数。
     * - 它不能复用代理树的 positionSummary 页面，否则会错误展示下级用户筛选和钻取能力。
     *
     * @return \Illuminate\Contracts\View\View 返回绑定 positionSummary2Search 专用接口的 Blade 页面。
     */
    public function positionSummary2()
    {
        return view('front_layui::position.summary2');
    }

    /**
     * 渲染旧前台未平仓订单页面。
     *
     * 页面映射：front_layui::order.open。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function orderOpen()
    {
        return view('front_layui::order.open');
    }

    /**
     * 渲染旧前台已平仓订单页面。
     *
     * 页面映射：front_layui::order.closed。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function orderClosed()
    {
        return view('front_layui::order.closed');
    }

    /**
     * 渲染旧前台实时返佣页面。
     *
     * 页面映射：front_layui::commission.realtime。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function realtimeRebate()
    {
        return view('front_layui::commission.realtime');
    }

    /**
     * 渲染旧前台客户列表页面。
     *
     * 页面映射：front_layui::agent.customers。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function customerList()
    {
        return view('front_layui::agent.customers');
    }

    /**
     * 渲染旧前台客户组别变更页面。
     *
     * 参数含义：
     * - $uid：旧路由中的目标用户 ID，可为空。
     * - legacyTargetUserId 表示旧返佣转账或组别变更页面传入的目标用户 ID。
     *
     * @param int|string|null $uid 旧路由传入的目标用户 ID。
     * @return \Illuminate\Contracts\View\View
     */
    public function groupChange($uid = null)
    {
        return view('front_layui::agent.group-change', ['legacyTargetUserId' => $uid ? (int) $uid : null]);
    }

    /**
     * 渲染旧前台礼品地址页面。
     *
     * 参数含义：
     * - $recId：旧路由中的地址记录 ID，可为空。
     * - legacyAddressId 表示旧地址编辑页面传入的地址记录 ID。
     *
     * @param int|string|null $recId 旧路由传入的地址记录 ID。
     * @return \Illuminate\Contracts\View\View
     */
    public function address($recId = null)
    {
        return view('front_layui::gift.address', ['legacyAddressId' => $recId ? (int) $recId : 0]);
    }

    /**
     * 渲染旧前台礼品列表页面。
     *
     * 页面映射：front_layui::gift.list。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function gift()
    {
        return view('front_layui::gift.list');
    }

    /**
     * 渲染旧前台新闻公告页面。
     *
     * 页面映射：front_layui::news.index。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function news()
    {
        return view('front_layui::news.index');
    }

    /**
     * 保存旧前台离线意见反馈。
     *
     * 参数含义：
     * - email 表示旧意见反馈提交的联系邮箱，必须是有效邮箱地址。
     * - username 表示旧意见反馈提交的称呼，会写入 offweb_feedbacks.title。
     * - phone 表示旧意见反馈提交的联系电话，会追加到反馈内容。
     * - remarks 表示旧意见反馈提交的反馈内容，兼容 content 参数。
     * - offweb_feedbacks 表用于保存旧前台离线反馈记录。
     * - mail.feedback_to 表示业务收件邮箱；落库成功后才发送 FrontFeedbackNotification。
     *
     * 返回结果：
     * - code=0 表示反馈记录保存且业务邮件发送成功。
     * - code=1 表示参数、数据库或邮件阶段失败；邮件失败时数据库记录仍保留供后台处理。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function feedback(Request $request)
    {
        // 参数桥接：旧前端 content 字段并入 remarks，随后统一做字段校验，校验失败不落库。
        $request->merge([
            'remarks' => $request->input('remarks', $request->input('content')),
        ]);
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:50',
            'remarks' => 'nullable|string|max:5000',
        ]);
        if ($validator->fails()) {
            return $this->feedbackFailure('response.validation_failed', [
                'errors' => $validator->errors()->toArray(),
            ]);
        }

        $email = trim((string) $request->input('email'));
        $username = trim((string) $request->input('username'));
        $phone = trim((string) $request->input('phone'));
        $remarks = trim((string) $request->input('remarks', ''));
        $userId = $this->legacyFrontUserId($request);
        $content = trim($remarks . "\n联系电话：" . $phone);
        $now = time();

        // 落库阶段：反馈先写入 offweb_feedbacks（未登录用户 user_id 记 null），数据库失败不伪造成功。
        try {
            $feedbackId = (int) DB::table('offweb_feedbacks')->insertGetId([
                'user_id' => $userId > 0 ? $userId : null,
                'email' => $email,
                'title' => $username,
                'content' => $content,
                'status' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $exception) {
            Log::error('旧前台意见反馈写入数据库失败。', [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->feedbackFailure('response.db_error');
        }

        // 收件人阶段：业务收件邮箱配置无效时不再发信，但已落库记录保留供后台处理。
        $recipient = trim((string) config('mail.feedback_to', ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::error('旧前台意见反馈业务收件邮箱配置无效。', [
                'feedback_id' => $feedbackId,
                'recipient' => $recipient,
            ]);

            return $this->feedbackFailure('response.email_send_failed', [
                'feedback_id' => $feedbackId,
            ]);
        }

        $feedback = [
            'id' => $feedbackId,
            'user_id' => $userId > 0 ? $userId : null,
            'email' => $email,
            'username' => $username,
            'phone' => $phone,
            'remarks' => $remarks,
        ];

        // 通知阶段：邮件发送失败返回 code=1，数据库记录仍保留，由后台人工处理。
        try {
            Mail::to($recipient)->send(new FrontFeedbackNotification($feedback));
        } catch (Throwable $exception) {
            Log::error('旧前台意见反馈业务邮件发送失败。', [
                'feedback_id' => $feedbackId,
                'recipient' => $recipient,
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->feedbackFailure('response.email_send_failed', [
                'feedback_id' => $feedbackId,
            ]);
        }

        return response()->json([
            'code' => 0,
            'msg' => __('response.success'),
            'data' => ['feedback_id' => $feedbackId],
        ]);
    }

    /**
     * 返回旧意见反馈统一失败结构。
     *
     * @param string $messageKey response 多语言消息 key。
     * @param array<string, mixed> $data 参数错误或已保存记录 ID 等失败上下文。
     * @return \Illuminate\Http\JsonResponse code=1 表示本次反馈链路没有全部完成。
     */
    private function feedbackFailure(string $messageKey, array $data = [])
    {
        return response()->json([
            'code' => 1,
            'msg' => __($messageKey),
            'data' => $data,
        ]);
    }

    /**
     * 退出旧前台登录。
     *
     * logout 用于清理新 user guard 和旧 session suser 登录态。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Auth::guard('user')->logout();
        $request->session()->forget('suser');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('front_page_login');
    }
}
