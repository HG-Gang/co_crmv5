<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

namespace App\Constants;

/**
 * MT4 操作备注码全局常量（统一全缀体系）。
 *
 * 文件功能：
 * - 对齐旧项目 MY_Controller 的 MT4 全缀备注约定：所有与 MT4 相关的入金/出金/佣金/爆仓清零
 *   等远端操作，其 order comment（备注）必须使用本类常量拼接，禁止散落硬编码字符串。
 * - 备注码按业务语义分四组：入金（DB 系列）、出金（WB 系列）、佣金/客户类型（GMTK 系列）、
 *   其他（清零/风控/测试等）。
 * - 拼接约定：常量仅为前缀，调用处按 `{CODE}{userId}-#{orderNo}`（或对应业务标识）拼接完整
 *   comment；同一业务方向只允许一个备注码，保证后台可按前缀聚合统计、对账可追溯。
 *
 * 适用场景：
 * - 入金结算（SettleDepositPayment）、出金打款（ProcessWithdrawFunding）、
 *   退款（RefundDepositPayment / RefundWithdrawFunding）、佣金结算（Legacy*CommissionSummaryService）、
 *   爆仓清零（AdminWhsExpZeroController）、佣金转账（CommissionTransferService）等写 MT4 备注的位置。
 * - 后台资金流水/实时返佣页面按备注码前缀分类统计（FundFlowController / RealtimeCommissionController）。
 *
 * 入参例子：
 * - Mt4RemarkCodes::DBUN . $userId . '-#' . $orderNo  // 正常入金：DBUN-10001-#...
 * - Mt4RemarkCodes::DBCN . $traderId . '-#' . $ticket  // 佣金正常存入：DBCN-...
 *
 * 返回值：
 * - 常量值为备注前缀字符串（均以 '-' 结尾，符合旧项目约定）。
 *
 * 异常或失败场景：
 * - 无；纯常量定义，业务代码必须使用常量而非字面量，便于统一调整与检索。
 */
final class Mt4RemarkCodes
{
    // ==================== 入金（Deposit Balance）系列 ====================

    /** 会计调整存入（Deposit balance account adjustment）。 */
    public const DBAA = 'DBAA-';

    /** 佣金正常存入（Deposit balance commission normal）：替换旧返佣标注 FY，返佣入金统一用本码。 */
    public const DBCN = 'DBCN-';

    /** 佣金正常存入退回（Deposit balance commission reverse）：返佣退回时使用。 */
    public const DBCR = 'DBCR-';

    /** 佣金正常转户（Deposit balance commission transfer）：佣金转户接收方入金使用（对应旧 ZH）。 */
    public const DBCT = 'DBCT-';

    /** 赠金余额存入（Deposit balance gift normal）。 */
    public const DBGN = 'DBGN-';

    /** 月返佣金存入（Deposit balance monthly normal）。 */
    public const DBMN = 'DBMN-';

    /** 交易赔偿调整（Deposit balance penalty adjustment）。 */
    public const DBPA = 'DBPA-';

    /** 交易赔偿正常存入（Deposit balance penalty normal）。 */
    public const DBPN = 'DBPN-';

    /** 利润分成正常存入（Deposit balance share profit normal）。 */
    public const DBSN = 'DBSN-';

    /** 余额正常转账（Deposit balance transfer normal）。 */
    public const DBTN = 'DBTN-';

    /** 正常存款（Deposit balance usdt normal）：对应用户充值的 CZ。 */
    public const DBUN = 'DBUN-';

    /** 正常清零存入（Deposit balance zero balance normal）：爆仓清零栏目清零时的入金备注。 */
    public const DBZN = 'DBZN-';

    /** 正常清零存入退回（Deposit balance zero balance reverse）：清零失败退回时的备注。 */
    public const DBZR = 'DBZR-';

    /** 会计部信用额存入（Deposit credit account adjustment）。 */
    public const DCAA = 'DCAA-';

    /** 会计部信用额存入退回（Deposit credit account reverse）。 */
    public const DCAR = 'DCAR-';

    /** 经授权信用额存入（Deposit credit internal normal）。 */
    public const DCIN = 'DCIN-';

    /** 经授权信用额存入退回（Deposit credit internal reverse）。 */
    public const DCIR = 'DCIR-';

    /** 测试存入信用额（Deposit credit testing account）。 */
    public const DCTA = 'DCTA-';

    /** 平台入金（Deposit balance account deposit）。 */
    public const DBAD = 'DBAD-';

    // ==================== 出金（Withdraw Balance）系列 ====================

    /** 会计调整扣回（Withdraw balance account adjustment）。 */
    public const WBAA = 'WBAA-';

    /** 佣金正常提款（Withdraw balance Commission normal）。 */
    public const WBCN = 'WBCN-';

    /** 佣金正常提款退回（Withdraw balance Commission reverse）。 */
    public const WBCR = 'WBCR-';

    /** 佣金转户提款（Withdraw balance Commission transfer）：佣金转户转出方扣款使用（接收方用 DBCT）。 */
    public const WBCT = 'WBCT-';

    /** 手续费扣账（Withdraw balance handling normal）。 */
    public const WBHN = 'WBHN-';

    /** 手续费扣账退回（Withdraw balance handling reverse）。 */
    public const WBHR = 'WBHR-';

    /** 正常出金（Withdraw balance internal normal）：对应用户取款出金操作。 */
    public const WBIN = 'WBIN-';

    /** 正常出金退回（Withdraw balance internal reverse）：出金失败退回时使用。 */
    public const WBIR = 'WBIR-';

    /** 炒单盈利扣取（Withdraw balance penalty normal）。 */
    public const WBPN = 'WBPN-';

    /** 分成亏损扣取（Withdraw balance share profit normal）。 */
    public const WBSN = 'WBSN-';

    /** 转账出金（Withdraw balance transfer normal）。 */
    public const WBTN = 'WBTN-';

    /** 平台出金（Withdraw balance account deposit）。 */
    public const WBAD = 'WBAD-';

    // ==================== 客户/代理类型（GMTK 系列） ====================

    /** 有佣代理（旧项目约定备注前缀）。 */
    public const GMTK_AGENT = 'GMTKAgent-';

    /** 零佣代理（旧项目约定备注前缀）。 */
    public const GMTK_AGENT_0 = 'GMTKAgent0-';

    /** 有佣客户（旧项目约定备注前缀）。 */
    public const GMTK = 'GMTK-';

    /** 零佣客户（旧项目约定备注前缀）。 */
    public const GMTK_0 = 'GMTK0-';

    // ==================== 新项目扩展备注码（与旧全缀体系对齐命名） ====================

    /** 出金订单备注（Withdraw balance user normal）：出金申请单落库备注，格式 WDUN-{userId}-#{orderNo}。 */
    public const WDUN = 'WDUN-';

    /** 入金退款备注（Deposit balance refund）：入金结算失败退款，格式 DBRF-{userId}-#{orderNo}。 */
    public const DBRF = 'DBRF-';

    /** 出金退款备注（Withdraw balance refund）：出金打款失败退款，格式 WDRF-{orderNo}。 */
    public const WDRF = 'WDRF-';

    /** 爆仓清零备注（Whs Exp Zero）：后台爆仓清零操作，格式 WHS_ZERO:{userId}。 */
    public const WHS_ZERO = 'WHS_ZERO:';

    /** 风控强平备注（Risk force close）：后台风控强制平仓，格式 CRM risk force close #{tradeId}。 */
    public const RISK_FORCE_CLOSE = 'CRM risk force close #';
}
