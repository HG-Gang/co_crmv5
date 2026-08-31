<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:04
 */

/**
 * FrontLegacyDataDateAliasTest
 *
 * 文件功能：
 * - 验证前台旧数据日期别名兼容：空的现代日期别名回退到旧别名。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class FrontLegacyDataDateAliasTest extends TestCase
{
    public function test_empty_modern_date_aliases_fall_back_to_legacy_aliases(): void
    {
        $request = Request::create('/', 'GET', [
            'date_from' => '',
            'startdate' => '2026-08-16',
            'date_to' => '   ',
            'enddate' => '2026-08-17',
        ]);

        $this->assertSame('2026-08-16', FrontLegacyData::dateFrom($request));
        $this->assertSame('2026-08-17', FrontLegacyData::dateTo($request));
    }
}
