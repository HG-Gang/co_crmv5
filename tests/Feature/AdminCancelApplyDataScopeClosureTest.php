<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 22:36
 */

/**
 * 文件功能：验证受限管理员对销户申请（cancel_applies）的数据范围隔离：
 *           列表、审核通过、审核拒绝均不能触及范围外数据，且越权在 MT4 调用前失败。
 *
 * 适用场景：后台 /api/admin/cancelApplyList、cancelApplyApprove/{id}、
 *           cancelApplyReject/{id} 接口的数据范围回归测试。
 *
 * 入参例子：
 * - POST /api/admin/cancelApplyList：{per_page}
 * - POST /api/admin/cancelApplyApprove/{id}：无请求体
 * - POST /api/admin/cancelApplyReject/{id}：{reason}
 *
 * 返回值：
 * - 范围外数据列表返回 data.total=0；
 * - 越权审核返回 code=PERMISSION_DENIED，申请状态保持不变且不触发 MT4 调用。
 *
 * 异常或失败场景：
 * - AdminDataScopeService 判定记录所有权无权限（canAccessRecord=false）时审核接口拒绝执行。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AdminCancelApplyDataScopeClosureTest extends TestCase
{
    use DatabaseTransactions;

    // 受限管理员不能列出、审核通过或拒绝数据范围外的销户申请，且越权不得触发 MT4 调用。
    public function test_restricted_admin_cannot_list_approve_or_reject_out_of_scope_cancel_apply(): void
    {
        $admin = new Admin();
        $admin->id = 880201;
        $admin->username = 'restricted-cancel-admin';
        $admin->status = 1;

        $firstId = $this->insertApply(88020101);
        $secondId = $this->insertApply(88020102);

        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('apply')
            ->once()
            ->andReturnUsing(static function ($query) {
                return $query->whereRaw('1 = 0');
            });
        $scope->shouldReceive('canAccessRecord')
            ->twice()
            ->withArgs(static function ($candidateAdmin, $userId, $createdBy, $accountType) use ($admin): bool {
                return $candidateAdmin === $admin
                    && in_array((int) $userId, [88020101, 88020102], true)
                    && in_array((string) $createdBy, ['88020101', '88020102'], true)
                    && $accountType === 'user';
            })
            ->andReturnFalse();
        $this->app->instance(AdminDataScopeService::class, $scope);

        $mt4Calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($mt4Calls) extends Mt4ManagerService {
            /**
             * MT4 lockUser 替身的调用捕获表。记录被锁定的 userId，断言数据范围外的销单申请不会触发锁定。
             * @var array<int, int>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function lockUser($userId)
            {
                $this->calls[] = (int) $userId;

                return ['status' => 'ok'];
            }
        });

        $client = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');

        $client->postJson('/api/admin/cancelApplyList')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
        $client->postJson('/api/admin/cancelApplyApprove/' . $firstId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $client->postJson('/api/admin/cancelApplyReject/' . $secondId, ['reason' => 'out of scope'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame([], $mt4Calls, '越权审核必须在 MT4 调用前失败。');
        $this->assertSame(0, (int) DB::table('cancel_applies')->where('id', $firstId)->value('status'));
        $this->assertSame(0, (int) DB::table('cancel_applies')->where('id', $secondId)->value('status'));
    }

    private function insertApply(int $userId): int
    {
        $now = time();

        return (int) DB::table('cancel_applies')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'scope-user-' . $userId,
            'status' => 0,
            'cancel_remark' => 'scope test',
            'reject_reason' => '',
            'created_by' => (string) $userId,
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
