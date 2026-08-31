<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 00:57
 */

/**
 * AdminSystemConfigUpdateControllerTest
 *
 * 文件功能：
 * - 验证后台系统配置更新控制器：接受单行载荷，同时保留旧版批量 configs 格式（键名对应 system_configs.key）。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Controllers\Admin\SystemConfigController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台系统配置更新接口测试。
 *
 * 测试目标：
 * - 页面单行编辑提交 id/value/group/description 时，控制器必须更新 system_configs 真实字段。
 * - 旧的 configs[key]=value 批量更新格式仍需兼容，避免影响已有调用方。
 */
class AdminSystemConfigUpdateControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 单行编辑必须按 id 更新真实 value/group/description 字段。
     *
     * @return void
     */
    public function test_system_config_update_accepts_single_row_payload(): void
    {
        $key = 'unit_test_single_config_' . str_replace('.', '', uniqid('', true));

        $id = DB::table('system_configs')->insertGetId([
            'key' => $key,
            'value' => 'old',
            'group' => 'general',
            'description' => 'old description',
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $request = Request::create('/api/admin/updateSystemConfig', 'POST', [
            'id' => $id,
            'value' => 'new',
            'group' => 'risk',
            'description' => 'new description',
        ]);

        app(SystemConfigController::class)->update($request);

        $record = DB::table('system_configs')->where('id', $id)->first();

        $this->assertSame('new', $record->value);
        $this->assertSame('risk', $record->group);
        $this->assertSame('new description', $record->description);
    }

    /**
     * 旧版批量格式必须继续可用，configs 的键名对应 system_configs.key。
     *
     * @return void
     */
    public function test_system_config_update_keeps_batch_configs_payload(): void
    {
        $key = 'unit_test_batch_config_' . str_replace('.', '', uniqid('', true));

        DB::table('system_configs')->insert([
            'key' => $key,
            'value' => 'old',
            'group' => 'general',
            'description' => 'batch description',
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $request = Request::create('/api/admin/updateSystemConfig', 'POST', [
            'configs' => [
                $key => 'new',
            ],
        ]);

        app(SystemConfigController::class)->update($request);

        $value = DB::table('system_configs')->where('key', $key)->value('value');

        $this->assertSame('new', $value);
    }
}
