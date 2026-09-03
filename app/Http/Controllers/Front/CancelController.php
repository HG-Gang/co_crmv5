<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Models\CancelApply;
use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\WithdrawRecord;
use App\Services\Mt4ManagerService;
use App\Services\UserPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

/**
 * 前台销户申请控制器。
 *
 * 文件功能：
 * - 处理当前前台用户提交销户申请、旧前台销户兼容入口和最近一次销户申请状态查询。
 * - 销户申请写入 cancel_applies 表，后台后续通过注销申请审核页面执行通过或拒绝。
 * - 现代与旧入口共用身份、验证码、密码、资金、持仓、代理关系和处理中出金校验，任何步骤均不能绕过。
 * - MT4 锁号明确成功后才在本地事务中收口交易与出金能力；本地失败时执行远端解锁补偿。
 *
 * 安全边界：
 * - 手机、邮箱、身份证、一次性验证码与登录密码必须全部匹配当前认证用户，任何一项不通过都中止申请。
 * - 验证码使用恒定时间比较并绑定邮箱/手机号；申请成功后凭据一次性消费，失败路径保留凭据供用户安全重试。
 * - 未平仓订单、非零资金（含负余额）、存在直属下级或处理中出金时禁止销户，且不能绕过。
 * - MT4 远端锁号未明确成功时本地不落库（失败关闭）；本地失败时执行远端解锁补偿，补偿失败只记录日志。
 * - 同一用户只能存在一条待审核申请：事务行锁 + 存在性检查双重拦截，重复提交明确拒绝。
 */
class CancelController extends FrontBaseController
{
    /**
     * 用户密码服务。
     *
     * @var UserPasswordService
     */
    private $passwordService;

    /**
     * MT4 管理服务。
     *
     * @var Mt4ManagerService
     */
    private $mt4Manager;

    /**
     * 注入注销状态机需要的密码和 MT4 服务。
     *
     * @param UserPasswordService $passwordService 密码校验服务，返回明确通过、明确拒绝或网络未知。
     * @param Mt4ManagerService $mt4Manager MT4 管理服务，负责远端锁号和解锁补偿。
     */
    public function __construct(UserPasswordService $passwordService, Mt4ManagerService $mt4Manager)
    {
        $this->passwordService = $passwordService;
        $this->mt4Manager = $mt4Manager;
    }

    /**
     * 提交当前前台用户的销户申请。
     *
     * 功能说明：
     * - 处理前台用户销户申请，按顺序校验身份、业务条件、敏感信息、远端锁号和本地收口，任一环节失败都拒绝申请。
     * - 销户成功后创建 cancel_applies 记录（status=0 表示待审核），并将账号置为只读状态（is_mt4_readonly=1）。
     *
     * 参数含义：
     * - reason 表示新版前台提交的销户原因，最大 500 个字符。
     * - cancel_applies 表示销户申请数据表，status=0 表示待后台审核。
     * - cancel_remark 表示用户提交的销户原因，对应新迁移后的原因字段。
     * - reject_reason 表示后台拒绝原因或旧表兼容原因字段；当 cancel_remark 字段不存在时临时承载用户原因。
     *
     * 业务边界：
     * - 重复待审销户申请会被拒绝，已通过或已拒绝的历史记录不阻止再次申请。
     * - UserTrade::open 用于判断当前用户是否仍有未平仓订单，存在未平仓订单时禁止销户。
     * - total_funds 表示当前账户总资金，equity 表示当前账户净值，任一非零都禁止销户，负余额也不能绕过。
     * - 手机、邮箱、身份证、一次性验证码和密码必须全部匹配当前认证用户。
     * - 远端锁号、本地能力收口和申请创建组成一个失败关闭链路；远端成功而本地失败时执行解锁补偿。
     *
     * @param Request $request HTTP 请求对象，承载销户原因和当前前台登录态。
     * @return JsonResponse 销户申请创建结果响应。
     */
    public function apply(Request $request): JsonResponse
    {
        $legacyResponse = (bool) $request->attributes->get('legacy_cancel_response', false);
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($legacyResponse) {
                return $this->legacyFail('cancelApplyErr');
            }

            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $this->legacyFrontUserLogin($request);
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userLogin || !$userInfo) {
            if ($legacyResponse) {
                return $this->legacyFail('userNotFound', 'userId');
            }

            return $this->legacyFrontAuthError($request);
        }

        // 无副作用的业务条件先检查一次，减少无效请求进入密码网关和 MT4 状态机。
        $businessFailure = $this->cancellationBusinessFailure($userInfo);
        if ($businessFailure !== null) {
            return $this->cancelFailure(
                $legacyResponse,
                $businessFailure['err'],
                $businessFailure['col'],
                $businessFailure['code'],
                $businessFailure['message']
            );
        }

        // 敏感验证（手机/邮箱/身份证/验证码/密码）通过前不触发任何 MT4 或数据库副作用。
        $verificationError = $this->sensitiveVerificationError($request, $userLogin, $userInfo);
        if ($verificationError !== null) {
            return $this->cancelFailure(
                $legacyResponse,
                $verificationError['err'],
                $verificationError['col'],
                $verificationError['code'],
                $verificationError['message']
            );
        }

        // 敏感验证后再检查待审申请，使已消费验证码的重放请求明确返回 codeErr，且仍早于任何 MT4 副作用。
        if ($this->hasPendingCancellation((int) $userInfo->user_id)) {
            $pendingFailure = $this->pendingCancellationFailure();

            return $this->cancelFailure(
                $legacyResponse,
                $pendingFailure['err'],
                $pendingFailure['col'],
                $pendingFailure['code'],
                $pendingFailure['message']
            );
        }

        $reason = trim((string) $request->input('reason', ''));
        $applyData = [
            'user_id'    => $userInfo->user_id,
            'user_name'  => $userInfo->user_name,
            'status'     => 0,
            'created_by' => $userInfo->user_name,
        ];

        if (Schema::hasColumn('cancel_applies', 'cancel_remark')) {
            $applyData['cancel_remark'] = $reason;
            $applyData['reject_reason'] = '';
        } else {
            // 兼容尚未执行 cancel_remark 迁移的数据库，临时把用户原因写入 reject_reason。
            $applyData['reject_reason'] = $reason;
        }

        try {
            $remoteLocked = false;
            // 事务内串行创建申请：远端锁号、本地能力收口与申请落库任一失败都整体回滚。
            $transactionResult = DB::transaction(function () use ($userInfo, $applyData, &$remoteLocked) {
                // 同一用户的销户创建必须串行；第二个请求等待后会看到第一条待审申请。
                $lockedUserInfo = UserInfo::where('user_id', $userInfo->user_id)
                    ->lockForUpdate()
                    ->first();
                if (!$lockedUserInfo) {
                    throw new RuntimeException('Cancellation user disappeared while acquiring row lock.');
                }

                $lateBusinessFailure = $this->cancellationBusinessFailure($lockedUserInfo);
                if ($lateBusinessFailure !== null) {
                    return ['failure' => $lateBusinessFailure];
                }
                if ($this->hasPendingCancellation((int) $lockedUserInfo->user_id)) {
                    return ['failure' => $this->pendingCancellationFailure()];
                }

                if (config('mt4.enabled')) {
                    // 远端锁号必须给出明确成功；传输异常或状态未知一律按失败处理，不继续本地收口。
                    try {
                        $mt4Result = $this->mt4Manager->lockUser((int) $lockedUserInfo->user_id);
                    } catch (Throwable $exception) {
                        Log::error('前台注销 MT4 锁号发生异常。', [
                            'user_id' => (int) $lockedUserInfo->user_id,
                            'exception_class' => get_class($exception),
                            'message' => $exception->getMessage(),
                        ]);
                        $mt4Result = ['status' => 'error', 'error_code' => 'transport_exception'];
                    }

                    if (!$this->isMt4Success($mt4Result)) {
                        return ['failure' => [
                            'err' => 'MT4SYNCUPDATAFAIL',
                            'col' => 'NOCOL',
                            'code' => ResponseCode::MT4_SYNC_FAILED,
                            'message' => 'response.mt4_sync_failed',
                            'context' => [
                                'error_code' => (string) ($mt4Result['error_code'] ?? $mt4Result['err'] ?? 'provider_rejected'),
                            ],
                        ]];
                    }
                    $remoteLocked = true;

                    // 锁号调用期间若已有竞争申请落库，保留唯一待审记录并停止本次创建。
                    if ($this->hasPendingCancellation((int) $lockedUserInfo->user_id)) {
                        return ['failure' => $this->pendingCancellationFailure()];
                    }
                }

                // 远端锁号成功后，本地将账号置为只读并创建待审申请，二者必须同事务提交。
                $updated = $lockedUserInfo->update([
                    'is_mt4_enabled' => 0,
                    'is_mt4_readonly' => 1,
                    'is_withdrawal_allowed' => 1,
                ]);
                if (!$updated) {
                    throw new RuntimeException('Failed to persist local cancellation restrictions.');
                }

                return ['apply' => CancelApply::create($applyData)];
            });
        } catch (Throwable $exception) {
            // 本地事务失败时远端可能已锁号，必须补偿解锁；响应统一按失败返回，不伪造成功。
            Log::error('前台注销本地事务失败。', [
                'user_id' => (int) $userInfo->user_id,
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            if ($remoteLocked) {
                $this->compensateRemoteLock((int) $userInfo->user_id);
            }

            return $this->cancelFailure(
                $legacyResponse,
                'cancelApplyErr',
                'NOCOL',
                ResponseCode::DB_ERROR,
                'response.db_error'
            );
        }

        if (isset($transactionResult['failure'])) {
            $failure = $transactionResult['failure'];

            return $this->cancelFailure(
                $legacyResponse,
                $failure['err'],
                $failure['col'],
                $failure['code'],
                $failure['message'],
                $failure['context'] ?? []
            );
        }

        /** @var CancelApply $apply */
        $apply = $transactionResult['apply'];

        // 只有远端和本地状态全部成功后才消费验证码，失败路径保留凭据供用户安全重试。
        $this->consumeCancelVerification($request, (int) $userInfo->user_id);

        if ($legacyResponse) {
            return $this->legacySuccess();
        }

        return $this->success($apply, __('response.success'), ResponseCode::SUCCESS);
    }

    /**
     * ajaxCancelAccount 用于兼容旧前台销户提交入口。
     *
     * 参数含义：
     * - reason 表示新版前台提交的销户原因。
     * - cancelRemark 表示旧前台提交的销户原因字段。
     * - remark 表示旧模板可能提交的原因字段别名。
     *
     * @param Request $request HTTP 请求对象，承载新旧前台销户原因字段。
     * @return JsonResponse 复用 apply 的销户申请响应。
     */
    public function ajaxCancelAccount(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);
        if (!$userInfo) {
            return $this->legacyFail('userNotFound', 'userId');
        }

        $request->merge([
            'reason' => $request->input('reason', $request->input('cancelRemark', $request->input('remark', ''))),
        ]);
        $request->attributes->set('legacy_cancel_response', true);

        return $this->apply($request);
    }

    /**
     * status 用于返回当前前台用户最近一次销户申请。
     *
     * 逻辑说明：
     * - 查询最新一条 cancel_applies 记录，供 Layui/Naive 页面展示审核状态、申请原因、拒绝原因和更新时间。
     * - 只读取当前登录用户自己的申请记录，不允许通过请求参数查看其他用户销户状态。
     *
     * @param Request $request HTTP 请求对象，承载当前前台登录态。
     * @return JsonResponse 最近一次销户申请状态响应。
     */
    public function status(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        $apply = CancelApply::where('user_id', $userInfo->user_id)
            ->orderBy('id', 'desc')
            ->first();

        return $this->success($apply, __('response.success'), ResponseCode::SUCCESS);
    }

    /**
     * 检查销户前不得变化的本地业务条件。
     *
     * 执行结果：
     * - ERRVOL 表示仍有未平仓订单。
     * - ERRBALANCE 表示 total_funds 或 equity 未清零，正负余额都禁止销户。
     * - existSubUser 表示代理仍有直属下级。
     * - UnfinishedOrder 表示存在 status=0/1 的待处理或处理中出金。
     *
     * @param UserInfo $userInfo 当前用户资料；事务内调用时应为已加行锁的最新记录。
     * @return array<string, mixed>|null 返回失败定义；null 表示四类条件全部满足。
     */
    private function cancellationBusinessFailure(UserInfo $userInfo): ?array
    {
        if (UserTrade::where('user_id', $userInfo->user_id)->open()->exists()) {
            return [
                'err' => 'ERRVOL',
                'col' => 'NOCOL',
                'code' => ResponseCode::RISK_RATE_EXCEEDED,
                'message' => 'response.risk_rate_exceeded',
            ];
        }

        if ((float) $userInfo->total_funds !== 0.0 || (float) $userInfo->equity !== 0.0) {
            return [
                'err' => 'ERRBALANCE',
                'col' => 'NOCOL',
                'code' => ResponseCode::OPERATION_NOT_ALLOWED,
                'message' => 'response.operation_not_allowed',
            ];
        }

        if (UserInfo::where('parent_id', $userInfo->user_id)->exists()) {
            return [
                'err' => 'existSubUser',
                'col' => 'userId',
                'code' => ResponseCode::OPERATION_NOT_ALLOWED,
                'message' => 'response.operation_not_allowed',
            ];
        }

        if (WithdrawRecord::where('user_id', $userInfo->user_id)->whereIn('status', [0, 1])->exists()) {
            return [
                'err' => 'UnfinishedOrder',
                'col' => 'NOCOL',
                'code' => ResponseCode::OPERATION_NOT_ALLOWED,
                'message' => 'response.operation_not_allowed',
            ];
        }

        return null;
    }

    /**
     * 判断当前用户是否已有待后台审核的销户申请。
     *
     * @param int $userId 当前业务用户 ID。
     * @return bool true 表示 status=0 的申请已经存在。
     */
    private function hasPendingCancellation(int $userId): bool
    {
        return CancelApply::where('user_id', $userId)
            ->where('status', 0)
            ->exists();
    }

    /**
     * 构造重复待审申请的统一失败定义。
     *
     * @return array<string, mixed> 旧入口返回 cancelApplyErr，现代接口返回 CANCEL_APPLY_EXISTS。
     */
    private function pendingCancellationFailure(): array
    {
        return [
            'err' => 'cancelApplyErr',
            'col' => 'NOCOL',
            'code' => ResponseCode::CANCEL_APPLY_EXISTS,
            'message' => 'response.cancel_apply_exists',
        ];
    }

    /**
     * 校验现代与旧前台销户所需的身份证、手机号、邮箱、验证码和密码。
     *
     * @param Request $request HTTP 请求对象。
     * @param mixed $userLogin 当前前台登录账号。
     * @param UserInfo $userInfo 当前前台业务用户资料。
     * @return array<string, mixed>|null 返回统一错误语义；null 表示五项敏感验证全部通过。
     */
    private function sensitiveVerificationError(Request $request, $userLogin, UserInfo $userInfo): ?array
    {
        if (!$userLogin || !$userInfo) {
            return $this->verificationError('userNotFound', 'userId');
        }

        $submittedPhone = trim((string) $request->input('userphoneNo', $request->input('phone', '')));
        $submittedEmail = strtolower(trim((string) $request->input('useremail', $request->input('email', ''))));
        $submittedIdCard = trim((string) $request->input('userIdcardNo', $request->input('id_card_no', '')));
        $submittedCode = trim((string) $request->input('userverfcode', $request->input('email_code', '')));
        $password = (string) $request->input('password', '');
        $auth = UserAuth::where('user_id', $userInfo->user_id)->first();
        $idCardNo = trim((string) ($auth->id_card_no ?? $auth->id_card ?? ''));

        if (!$this->phoneMatches($submittedPhone, (string) $userInfo->phone)) {
            return $this->verificationError('phoneErr', 'userphoneNo');
        }
        if ($submittedEmail === '' || strtolower((string) $userLogin->email) !== $submittedEmail) {
            return $this->verificationError('emailErr', 'useremail');
        }
        if ($idCardNo === '' || $submittedIdCard !== $idCardNo) {
            return $this->verificationError('IDcardnoErr', 'IDcard_no');
        }

        if (!$this->cancelCodeMatches($request, (int) $userInfo->user_id, $submittedCode, $submittedEmail, $submittedPhone)) {
            return $this->verificationError('codeErr', 'userverfcode');
        }

        try {
            $passwordStatus = $this->passwordService->verify($userLogin, $password);
        } catch (Throwable $exception) {
            Log::error('前台注销密码校验发生异常。', [
                'user_id' => (int) $userInfo->user_id,
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            $passwordStatus = 'network_failure';
        }
        if ($passwordStatus === 'rejected') {
            return $this->verificationError('passwordErr', 'password');
        }
        if ($passwordStatus !== 'verified') {
            return [
                'err' => 'NETWORKFAIL',
                'col' => 'FATALCANOTCONNECT',
                'code' => ResponseCode::THIRD_PARTY_ERROR,
                'message' => 'response.third_party_error',
            ];
        }

        return null;
    }

    /**
     * 校验销户验证码及其发码邮箱、手机号绑定；兼容 Cache 和旧 session 凭据。
     *
     * @param Request $request HTTP 请求对象。
     * @param int $userId 当前业务用户 ID。
     * @param string $submittedCode 用户提交的一次性验证码。
     * @param string $submittedEmail 用户提交的当前邮箱。
     * @param string $submittedPhone 用户提交的当前手机号。
     * @return bool true 表示验证码、用户归属和已保存目标均匹配。
     */
    private function cancelCodeMatches(
        Request $request,
        int $userId,
        string $submittedCode,
        string $submittedEmail,
        string $submittedPhone
    ): bool
    {
        $cached = Cache::get('front_profile_cancel_code:' . $userId);
        if (is_array($cached) && !empty($cached['code'])) {
            $cachedEmail = strtolower(trim((string) ($cached['email'] ?? '')));
            $cachedPhone = trim((string) ($cached['phone'] ?? ''));

            return $this->codesMatch($submittedCode, (string) $cached['code'])
                && ($cachedEmail === '' || $cachedEmail === $submittedEmail)
                && ($cachedPhone === '' || $this->phoneMatches($submittedPhone, $cachedPhone));
        }

        $sessionCode = '';
        $sessionPhone = '';
        $sessionEmail = '';
        if ($request->hasSession()) {
            $sessionCode = (string) $request->session()->get('cancelCode', '');
            $sessionPhone = (string) $request->session()->get('cancelverifyphoneNo', '');
            $sessionEmail = strtolower(trim((string) $request->session()->get('cancelverifyuseremail', '')));
        } else {
            try {
                $sessionCode = (string) app('session')->get('cancelCode', '');
                $sessionPhone = (string) app('session')->get('cancelverifyphoneNo', '');
                $sessionEmail = strtolower(trim((string) app('session')->get('cancelverifyuseremail', '')));
            } catch (Throwable $exception) {
                return false;
            }
        }

        return $this->codesMatch($submittedCode, $sessionCode)
            && ($sessionEmail === '' || $sessionEmail === $submittedEmail)
            && ($sessionPhone === '' || $this->phoneMatches($submittedPhone, $sessionPhone));
    }

    /**
     * 使用恒定时间函数比较验证码，空值永远不通过。
     *
     * @param string $submitted 用户提交验证码。
     * @param string $expected 服务端保存验证码。
     * @return bool true 表示两个非空验证码完全一致。
     */
    private function codesMatch(string $submitted, string $expected): bool
    {
        return $submitted !== '' && $expected !== '' && hash_equals($expected, $submitted);
    }

    /**
     * 构造敏感验证的常规参数错误结构。
     *
     * @param string $err 旧前台可识别的错误码。
     * @param string $col 旧前台应定位的字段名。
     * @return array<string, mixed> 现代接口使用 VALIDATION_FAILED，旧接口复用 err 和 col。
     */
    private function verificationError(string $err, string $col): array
    {
        return [
            'err' => $err,
            'col' => $col,
            'code' => ResponseCode::VALIDATION_FAILED,
            'message' => 'response.validation_failed',
        ];
    }

    /**
     * 按入口类型返回销户失败响应，并保留旧页面所需的 err/col 语义。
     *
     * @param bool $legacyResponse true 表示旧 web 兼容入口。
     * @param string $err 旧前台错误码。
     * @param string $col 旧前台字段定位标识。
     * @param int $code 现代接口统一响应码。
     * @param string $message 现代接口多语言消息 key。
     * @param array<string, mixed> $context 额外错误上下文，例如第三方 error_code。
     * @return JsonResponse 旧入口返回 FAIL 结构，现代入口返回统一 error 结构。
     */
    private function cancelFailure(
        bool $legacyResponse,
        string $err,
        string $col,
        int $code,
        string $message,
        array $context = []
    ): JsonResponse {
        if ($legacyResponse) {
            return $this->legacyFail($err, $col);
        }

        return $this->error($message, $code, array_merge([
            'err' => $err,
            'col' => $col,
        ], $context));
    }

    /**
     * 判断 MT4 命令是否给出明确成功结果。
     *
     * 功能说明：
     * - 校验 MT4 管理服务返回的结果是否表示操作成功。
     * - 只有返回结构为数组、status 为 'ok' 且 err 字段为空或为 '0' 时才视为成功。
     * - 其他任何情况（包括传输异常、status 非 ok、err 存在且非零）都视为失败。
     *
     * @param mixed $result MT4 管理服务返回值。
     * @return bool 仅数组且 status=ok、err 为空或 0 时返回 true。
     */
    private function isMt4Success($result): bool
    {
        if (!is_array($result) || strtolower(trim((string) ($result['status'] ?? ''))) !== 'ok') {
            return false;
        }

        return !array_key_exists('err', $result) || trim((string) $result['err']) === '0';
    }

    /**
     * 本地事务失败后解锁已锁定的远端账号。
     *
     * 功能说明：
     * - 当销户流程的 MT4 锁号成功但本地事务失败时，调用此方法补偿解锁远端账号，避免账号处于不一致状态（远端锁定但本地未创建销户申请）。
     * - 补偿解锁失败时记录 critical 级别日志供运维追踪，但绝不将失败改写为成功响应。
     *
     * 安全边界：
     * - 补偿失败不抛出异常，不影响主流程的失败响应返回给用户。
     * - 记录日志包含用户 ID 和第三方错误码，便于运维手动干预修复数据不一致。
     *
     * @param int $userId 当前业务用户 ID。
     * @return void 补偿成功无返回值；失败记录错误供运维追踪。
     */
    private function compensateRemoteLock(int $userId): void
    {
        try {
            $result = $this->mt4Manager->unlockUser($userId);
            if (!$this->isMt4Success($result)) {
                Log::critical('前台注销本地失败后的 MT4 解锁补偿未成功。', [
                    'user_id' => $userId,
                    'error_code' => (string) ($result['error_code'] ?? $result['err'] ?? 'provider_rejected'),
                ]);
            }
        } catch (Throwable $exception) {
            Log::critical('前台注销本地失败后的 MT4 解锁补偿发生异常。', [
                'user_id' => $userId,
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 消费当前用户已完成注销验证的一次性凭据。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param int $userId 当前业务用户 ID，用于删除独立 Cache 键。
     * @return void Cache 与旧 session 凭据均删除，不影响其他用途验证码。
     */
    private function consumeCancelVerification(Request $request, int $userId): void
    {
        Cache::forget('front_profile_cancel_code:' . $userId);

        if ($request->hasSession()) {
            $request->session()->forget([
                'cancelCode',
                'cancelverifyphoneNo',
                'cancelverifyuseremail',
            ]);
        }
    }

    /**
     * 比较用户输入手机号和数据库手机号，兼容旧项目 86- 前缀格式。
     *
     * @param string $submitted 用户输入手机号。
     * @param string $stored 数据库存储手机号。
     * @return bool true 表示匹配。
     */
    private function phoneMatches(string $submitted, string $stored): bool
    {
        $submitted = preg_replace('/\D+/', '', $submitted) ?: '';
        $stored = preg_replace('/\D+/', '', $stored) ?: '';

        if ($stored !== '' && strlen($stored) > 11 && substr($stored, 0, 2) === '86') {
            $stored = substr($stored, 2);
        }
        if ($submitted !== '' && strlen($submitted) > 11 && substr($submitted, 0, 2) === '86') {
            $submitted = substr($submitted, 2);
        }

        return $submitted !== '' && $submitted === $stored;
    }

    /**
     * 返回旧前台成功响应结构。
     *
     * @param string $msg 旧页面主提示码。
     * @param string $err 旧页面错误码。
     * @param string $col 旧页面字段定位标识。
     * @return JsonResponse 旧前台兼容成功响应。
     */
    private function legacySuccess(string $msg = 'SUC', string $err = 'NOErr', string $col = 'NOCOL'): JsonResponse
    {
        return response()->json([
            'msg' => $msg,
            'err' => $err,
            'col' => $col,
        ]);
    }

    /**
     * 返回旧前台失败响应结构。
     *
     * @param string $err 旧页面错误码。
     * @param string $col 旧页面字段定位标识。
     * @return JsonResponse 旧前台兼容失败响应。
     */
    private function legacyFail(string $err, string $col = 'NOCOL'): JsonResponse
    {
        return response()->json([
            'msg' => 'FAIL',
            'err' => $err,
            'col' => $col,
        ]);
    }
}
