<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:47
 */

namespace App\Services;

use App\Contracts\TradePasswordGateway;
use App\Facades\Mt4ManagerApi;
use App\Models\UserLogin;
use Illuminate\Support\Facades\Hash;

/**
 * 用户密码修改与敏感操作校验服务。
 *
 * 文件功能：
 * - 修改密码时先同步 MT4，再更新本地哈希，避免远端失败后本地密码先变化。
 * - 校验敏感操作密码时按运行模式选择本地哈希或 MT4 网关。
 * - 明确区分密码错误与网络结果未知，调用方不能把超时或传输异常误判成密码错误或校验成功。
 */
class UserPasswordService
{
    /**
     * 交易密码网关：MT4 运行模式下敏感操作密码校验与改密先行的远端依据。
     * 改密必须“先 MT4 成功、再写本地哈希”；它不可用或网络结果未知时本地哈希保持不变（失败关闭），
     * 防止 MT4 交易端与本地密码互相矛盾把用户锁在交易端之外。
     *
     * @var TradePasswordGateway
     */
    private $passwordGateway;

    /**
     * 构造密码服务。
     *
     * @param TradePasswordGateway $passwordGateway 交易密码网关，MT4 模式下校验远端密码状态。
     */
    public function __construct(TradePasswordGateway $passwordGateway)
    {
        $this->passwordGateway = $passwordGateway;
    }

    /**
     * 修改用户登录密码。
     *
     * @param UserLogin $login 当前登录账号。
     * @param string $newPassword 新密码明文。
     * @return bool true 表示远端与本地均更新成功；false 表示 MT4 拒绝或远端结果不可用，本地哈希保持不变。
     */
    public function change(UserLogin $login, string $newPassword): bool
    {
        if (config('mt4.enabled')) {
            $result = Mt4ManagerApi::changePassword((int) $login->user_id, $newPassword);
            if (!is_array($result) || strtolower((string) ($result['status'] ?? '')) !== 'ok') {
                return false;
            }
        }

        $login->update(['password' => Hash::make($newPassword)]);

        return true;
    }

    /**
     * 只校验 user_logins 中保存的本地密码哈希。
     *
     * 旧项目修改密码采用“两阶段校验”：本地不一致返回 localpswerr，随后 MT4
     * 明确拒绝返回 apipswerr。该方法集中本地哈希实现，避免控制器直接依赖 Hash。
     *
     * @param UserLogin $login 当前登录账号。
     * @param string $password 用户提交的当前密码明文。
     * @return bool true 表示本地哈希匹配；false 表示密码为空或本地哈希不匹配。
     */
    public function verifyLocal(UserLogin $login, string $password): bool
    {
        return $password !== '' && Hash::check($password, $login->password);
    }

    /**
     * 校验敏感操作提交的当前密码。
     *
     * 返回值含义：
     * - verified：密码已明确验证通过，可以继续敏感操作。
     * - rejected：密码被明确拒绝，应返回密码错误。
     * - network_failure：MT4 未能给出确定结果，应返回网络失败并停止操作。
     *
     * @param UserLogin $login 当前登录账号。
     * @param string $password 用户提交的当前密码明文。
     * @return string verified、rejected 或 network_failure。
     */
    public function verify(UserLogin $login, string $password): string
    {
        if (!config('mt4.enabled')) {
            return $this->verifyLocal($login, $password)
                ? 'verified'
                : 'rejected';
        }

        $status = $this->passwordGateway
            ->verify((int) $login->user_id, $password)
            ->status();

        if ($status === 'verified') {
            return 'verified';
        }

        return $status === 'rejected' ? 'rejected' : 'network_failure';
    }
}
