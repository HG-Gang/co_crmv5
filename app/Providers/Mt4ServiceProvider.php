<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

/**
 * MT4 相关服务与网关绑定提供者。
 *
 * 文件功能：
 * - register() 阶段把 Mt4ManagerService 注册为单例并绑定 'mt4.manager' 别名。
 * - 把各业务网关契约（入金结算、入金退款、信用结算、出金快照/扣款/退款、风控强平、
 *   MT4 用户建仓、交易密码、返佣转账资金与快照）全部绑定到对应 MT4 实现。
 *
 * 适用场景：
 * - 应用启动时自动加载；业务服务通过构造函数注入契约接口即可获得 MT4 实现。
 *
 * 方法功能：
 * - register()：注册 Mt4ManagerService 单例（读取 config('mt4.*') 的 host/port/api_key/api_version/timeout/retries/retry_delay），
 *   并依次注册全部网关契约的单例绑定。
 * - boot()：当前无额外启动逻辑。
 *
 * 返回值：
 * - 所有方法均无业务返回值。
 *
 * 异常或失败场景：
 * - config('mt4.*') 配置缺失时由构造函数参数校验或 MT4 请求阶段抛出异常。
 */
namespace App\Providers;

use App\Contracts\CommissionTransferAccountSnapshotGateway;
use App\Contracts\CommissionTransferFundingGateway;
use App\Contracts\DepositSettlementGateway;
use App\Contracts\DepositRefundGateway;
use App\Contracts\CreditSettlementGateway;
use App\Contracts\RiskForceCloseGateway;
use App\Contracts\TradePasswordGateway;
use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Contracts\WithdrawalFundingGateway;
use App\Contracts\WithdrawalRefundGateway;
use App\Contracts\UserMt4ProvisioningGateway;
use App\Services\Mt4ManagerService;
use App\Services\CommissionTransfer\Mt4CommissionTransferAccountSnapshotGateway;
use App\Services\CommissionTransfer\Mt4CommissionTransferFundingGateway;
use App\Services\CommissionTransfer\Mt4TradePasswordGateway;
use App\Services\Payment\Mt4CreditSettlementGateway;
use App\Services\Payment\Mt4DepositRefundGateway;
use App\Services\Payment\Mt4DepositSettlementGateway;
use App\Services\Risk\Mt4RiskForceCloseGateway;
use App\Services\Withdrawal\Mt4WithdrawalAccountSnapshotGateway;
use App\Services\Withdrawal\Mt4WithdrawalFundingGateway;
use App\Services\Withdrawal\Mt4WithdrawalRefundGateway;
use App\Services\Registration\Mt4UserProvisioningGateway;
use Illuminate\Support\ServiceProvider;

class Mt4ServiceProvider extends ServiceProvider
{
    /**
     * 注册 MT4 相关服务：
     * - 把 Mt4ManagerService 注册为单例（读取 config('mt4.*') 的连接参数），
     *   并绑定 'mt4.manager' 别名，供各网关实现共享同一个 MT4 客户端；
     * - 把全部业务网关契约绑定到对应 MT4 实现，业务层只注入契约接口，
     *   不感知实现细节。所有网关绑定均为单例，避免请求内重复创建 HTTP 连接。
     *
     * @return void 无返回值。
     */
    public function register()
    {
        // Mt4ManagerService 单例：集中管理 MT4 HTTP 连接与重试参数。
        $this->app->singleton(Mt4ManagerService::class, function ($app) {
            return new Mt4ManagerService(
                config('mt4.host'),
                config('mt4.port'),
                config('mt4.api_key'),
                config('mt4.api_version'),
                config('mt4.timeout'),
                config('mt4.retries', 3),
                config('mt4.retry_delay', 1)
            );
        });
        $this->app->alias(Mt4ManagerService::class, 'mt4.manager');
        // 入金侧契约：结算、退款、信用额度。
        $this->app->singleton(DepositSettlementGateway::class, function ($app) {
            return new Mt4DepositSettlementGateway($app->make('mt4.manager'));
        });
        $this->app->singleton(DepositRefundGateway::class, function ($app) {
            return new Mt4DepositRefundGateway($app->make('mt4.manager'));
        });
        $this->app->singleton(CreditSettlementGateway::class, function ($app) {
            return new Mt4CreditSettlementGateway($app->make('mt4.manager'));
        });
        // 出金侧契约：快照、扣款、退款。
        $this->app->singleton(WithdrawalAccountSnapshotGateway::class, function ($app) {
            return new Mt4WithdrawalAccountSnapshotGateway($app->make('mt4.manager'));
        });
        $this->app->singleton(WithdrawalFundingGateway::class, function ($app) {
            return new Mt4WithdrawalFundingGateway($app->make('mt4.manager'));
        });
        $this->app->singleton(WithdrawalRefundGateway::class, function ($app) {
            return new Mt4WithdrawalRefundGateway($app->make('mt4.manager'));
        });
        // 风控与账号管理契约：强平、建号核对、交易密码校验。
        $this->app->singleton(RiskForceCloseGateway::class, function ($app) {
            return new Mt4RiskForceCloseGateway($app->make('mt4.manager'));
        });
        $this->app->singleton(UserMt4ProvisioningGateway::class, function ($app) {
            return new Mt4UserProvisioningGateway($app->make('mt4.manager'));
        });
        $this->app->singleton(TradePasswordGateway::class, function ($app) {
            return new Mt4TradePasswordGateway($app->make('mt4.manager'));
        });
        // 佣金转账侧契约：资金划拨与转账前快照。
        $this->app->singleton(CommissionTransferFundingGateway::class, function ($app) {
            return new Mt4CommissionTransferFundingGateway($app->make('mt4.manager'));
        });
        $this->app->singleton(CommissionTransferAccountSnapshotGateway::class, function ($app) {
            return new Mt4CommissionTransferAccountSnapshotGateway($app->make('mt4.manager'));
        });
    }

    /**
     * 启动阶段：当前无额外逻辑，绑定均已在 register() 完成。
     *
     * @return void 无返回值。
     */
    public function boot()
    {
        //
    }
}
