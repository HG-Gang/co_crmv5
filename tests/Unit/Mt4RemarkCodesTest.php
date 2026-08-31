<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:24
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\Mt4RemarkCodes;
use PHPUnit\Framework\TestCase;

/**
 * MT4 操作备注码常量契约测试。
 *
 * 文件功能：
 * - 验证 Mt4RemarkCodes 全缀备注常量与旧项目 MY_Controller 约定一致（值、后缀 '-'）。
 * - 验证入金/出金/佣金/清零等关键备注码的常量值不被误改（资金流水与返佣分类依赖前缀匹配）。
 *
 * 适用场景：
 * - 任何改动 app/Constants/Mt4RemarkCodes.php 后回归。
 *
 * 入参例子：无（直接断言常量值）。
 *
 * 返回值：断言通过即表示备注码契约成立。
 *
 * 异常或失败场景：
 * - 常量值被误改导致与旧项目约定不一致时失败。
 */
final class Mt4RemarkCodesTest extends TestCase
{
    /**
     * 入金系列备注码必须与旧项目约定一致（DB 前缀 + '-' 后缀）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_deposit_remark_codes_match_legacy_convention(): void
    {
        $this->assertSame('DBAA-', Mt4RemarkCodes::DBAA);
        $this->assertSame('DBCN-', Mt4RemarkCodes::DBCN);
        $this->assertSame('DBCR-', Mt4RemarkCodes::DBCR);
        $this->assertSame('DBCT-', Mt4RemarkCodes::DBCT);
        $this->assertSame('DBGN-', Mt4RemarkCodes::DBGN);
        $this->assertSame('DBMN-', Mt4RemarkCodes::DBMN);
        $this->assertSame('DBPA-', Mt4RemarkCodes::DBPA);
        $this->assertSame('DBPN-', Mt4RemarkCodes::DBPN);
        $this->assertSame('DBSN-', Mt4RemarkCodes::DBSN);
        $this->assertSame('DBTN-', Mt4RemarkCodes::DBTN);
        $this->assertSame('DBUN-', Mt4RemarkCodes::DBUN);
        $this->assertSame('DBZN-', Mt4RemarkCodes::DBZN);
        $this->assertSame('DBZR-', Mt4RemarkCodes::DBZR);
        $this->assertSame('DBAD-', Mt4RemarkCodes::DBAD);
    }

    /**
     * 出金系列备注码必须与旧项目约定一致（WB 前缀 + '-' 后缀）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_withdraw_remark_codes_match_legacy_convention(): void
    {
        $this->assertSame('WBAA-', Mt4RemarkCodes::WBAA);
        $this->assertSame('WBCN-', Mt4RemarkCodes::WBCN);
        $this->assertSame('WBCR-', Mt4RemarkCodes::WBCR);
        $this->assertSame('WBCT-', Mt4RemarkCodes::WBCT);
        $this->assertSame('WBHN-', Mt4RemarkCodes::WBHN);
        $this->assertSame('WBHR-', Mt4RemarkCodes::WBHR);
        $this->assertSame('WBIN-', Mt4RemarkCodes::WBIN);
        $this->assertSame('WBIR-', Mt4RemarkCodes::WBIR);
        $this->assertSame('WBPN-', Mt4RemarkCodes::WBPN);
        $this->assertSame('WBSN-', Mt4RemarkCodes::WBSN);
        $this->assertSame('WBTN-', Mt4RemarkCodes::WBTN);
        $this->assertSame('WBAD-', Mt4RemarkCodes::WBAD);
    }

    /**
     * 佣金/客户类型与扩展备注码必须保持约定。
     *
     * @return void 断言通过不返回值。
     */
    public function test_commission_and_extended_codes(): void
    {
        $this->assertSame('GMTKAgent-', Mt4RemarkCodes::GMTK_AGENT);
        $this->assertSame('GMTKAgent0-', Mt4RemarkCodes::GMTK_AGENT_0);
        $this->assertSame('GMTK-', Mt4RemarkCodes::GMTK);
        $this->assertSame('GMTK0-', Mt4RemarkCodes::GMTK_0);
        $this->assertSame('WDUN-', Mt4RemarkCodes::WDUN);
        $this->assertSame('DBRF-', Mt4RemarkCodes::DBRF);
        $this->assertSame('WDRF-', Mt4RemarkCodes::WDRF);
        $this->assertSame('WHS_ZERO:', Mt4RemarkCodes::WHS_ZERO);
        $this->assertSame('CRM risk force close #', Mt4RemarkCodes::RISK_FORCE_CLOSE);
    }
}
