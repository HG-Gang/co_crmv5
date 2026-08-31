<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户分组控制器新旧字段兼容性的功能测试。
 *
 * 文件功能：
 * - 验证 UserGroupController::index 列表返回旧版字段名（user_group_name、group_type、group_id、group_enable、is_enc）。
 * - 验证 UserGroupController::store 创建接口能按旧版字段名（group_name、group_type、group_enable、is_enc）落库。
 *
 * 适用场景：
 * - 旧版前端页面仍使用旧字段名调用用户分组接口时的兼容保障。
 *
 * 入参例子：
 * - POST /api/admin/userGroupList，body：{"group_type": 2, "is_enabled": 1, "per_page": 20}。
 * - POST /api/admin/createUserGroup，body：{"group_name": "...", "group_type": 1, "group_id": 1, "group_enable": 1, "is_enc": 0}。
 *
 * 返回值：
 * - 列表成功返回 code=ResponseCode::SUCCESS；创建成功返回 code=ResponseCode::CREATED。
 *
 * 异常或失败场景：
 * - 兼容字段映射缺失时旧版页面无法读取分组数据或创建失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\UserGroupController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserGroupCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    // 验证用户分组列表返回旧版字段名并能按旧字段定位到测试分组。
    public function test_user_group_controller_lists_group_configs_with_legacy_field_names(): void
    {
        $groupName = 'compat-user-group-' . uniqid();

        DB::table('group_configs')->insert([
            'name' => $groupName,
            'radix' => 35,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 1,
            'is_default' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $request = Request::create('/api/admin/userGroupList', 'POST', [
            'group_type' => 2,
            'is_enabled' => 1,
            'per_page' => 20,
        ]);
        $payload = app(UserGroupController::class)->index($request)->getData(true);

        $this->assertSame(ResponseCode::SUCCESS, $payload['code']);

        $row = collect($payload['data']['data'])->firstWhere('user_group_name', $groupName);

        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['group_type']);
        $this->assertSame(0, (int) $row['group_id']);
        $this->assertSame(1, (int) $row['group_enable']);
        $this->assertSame(1, (int) $row['is_enc']);
    }

    // 验证按旧版字段名创建用户分组能正确映射并落库。
    public function test_user_group_controller_creates_group_configs_from_legacy_payload(): void
    {
        $groupName = 'compat-created-group-' . uniqid();

        $request = Request::create('/api/admin/createUserGroup', 'POST', [
            'group_name' => $groupName,
            'group_type' => 1,
            'group_id' => 1,
            'group_enable' => 1,
            'is_default' => 0,
            'is_enc' => 0,
        ]);
        $payload = app(UserGroupController::class)->store($request)->getData(true);

        $this->assertSame(ResponseCode::CREATED, $payload['code']);
        $this->assertDatabaseHas('group_configs', [
            'name' => $groupName,
            'category' => 1,
            'has_commission' => 1,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
        ]);
    }
}
