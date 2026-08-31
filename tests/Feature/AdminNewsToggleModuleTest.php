<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 16:21
 */

/**
 * 新闻发布状态切换接口的功能测试。
 *
 * 文件功能：
 * - 验证 admin_api_toggleNews 路由的注册与 URI 格式。
 * - 验证切换接口只翻转 is_published 发布状态、不改动其他字段。
 *
 * 适用场景：
 * - 后台新闻列表的“发布/下架”一键切换按钮。
 *
 * 入参例子：
 * - POST /api/admin/toggleNews/{id}（id 为 news 表主键）。
 *
 * 返回值：
 * - 切换成功返回 code=1000。
 *
 * 异常或失败场景：
 * - 记录不存在或删除时接口无法切换，测试数据需先写入 news 表。
 */

namespace Tests\Feature;

use App\Http\Controllers\Admin\NewsController;
use App\Constants\ResponseCode;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminNewsToggleModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 校验切换路由并验证连续两次切换后发布状态在 1 与 0 之间翻转、其余字段保持不变。
    public function test_news_toggle_route_flips_publish_status_only(): void
    {
        $route = Route::getRoutes()->getByName('admin_api_toggleNews');

        $this->assertNotNull($route, 'admin_api_toggleNews route is missing.');
        $this->assertSame('api/admin/toggleNews/{id}', $route->uri());
        $this->assertSame(NewsController::class . '@togglePublish', $route->getActionName());

        $id = DB::table('news')->insertGetId([
            'title' => 'Toggle Test News',
            'content' => 'Original news content',
            'image' => '/uploads/news/toggle-test.png',
            'author_id' => 7,
            'author_name' => 'Admin Tester',
            'is_published' => 1,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $response = (new NewsController())->togglePublish($id);
        $payload = $response->getData(true);

        $this->assertSame(1000, (int) $payload['code']);
        $this->assertDatabaseHas('news', [
            'id' => $id,
            'title' => 'Toggle Test News',
            'content' => 'Original news content',
            'image' => '/uploads/news/toggle-test.png',
            'author_id' => 7,
            'author_name' => 'Admin Tester',
            'is_published' => 0,
        ]);

        (new NewsController())->togglePublish($id);

        $this->assertDatabaseHas('news', [
            'id' => $id,
            'is_published' => 1,
        ]);
    }

    /**
     * @dataProvider mutationLockProvider
     */
    public function test_delete_and_toggle_lock_the_target_before_mutation_inside_nested_transactions(
        string $operation
    ): void
    {
        $id = DB::table('news')->insertGetId([
            'title' => 'Lock target ' . $operation,
            'content' => 'Lock target content',
            'image' => null,
            'author_id' => 7,
            'author_name' => 'Admin Tester',
            'is_published' => 1,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $events = [];
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $testDispatcher = new Dispatcher($this->app);
        $testDispatcher->listen(QueryExecuted::class, static function (QueryExecuted $query) use (&$events): void {
            $sql = self::normalizeSql($query->sql);
            if (strpos($sql, 'select * from news') !== false
                || strpos($sql, 'update news set deleted_at') !== false
                || strpos($sql, 'update news set is_published') !== false) {
                $events[] = [
                    'sql' => $sql,
                    'bindings' => $query->bindings,
                    'transactionLevel' => $query->connection->transactionLevel(),
                ];
            }
        });
        $connection->setEventDispatcher($testDispatcher);

        try {
            if ($operation === 'destroy') {
                $response = (new NewsController())->destroy(Request::create('/api/admin/deleteNews/' . $id, 'POST'), $id);
            } else {
                $response = (new NewsController())->togglePublish($id);
            }
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
        }

        $payload = $response->getData(true);
        $expectedCode = $operation === 'destroy' ? ResponseCode::DELETED : 1000;
        $this->assertSame($expectedCode, (int) $payload['code']);

        $lockIndex = null;
        $mutationIndex = null;
        foreach ($events as $index => $event) {
            if (strpos($event['sql'], 'select * from news') !== false
                && strpos($event['sql'], 'for update') !== false
                && in_array((string) $id, array_map('strval', $event['bindings']), true)) {
                $lockIndex = $index;
            }
            $mutationNeedle = $operation === 'destroy'
                ? 'update news set deleted_at'
                : 'update news set is_published';
            if (strpos($event['sql'], $mutationNeedle) !== false
                && in_array((string) $id, array_map('strval', $event['bindings']), true)) {
                $mutationIndex = $index;
            }
        }

        $this->assertNotNull($lockIndex, $operation . ' target lock query');
        $this->assertNotNull($mutationIndex, $operation . ' mutation query');
        $this->assertLessThan($mutationIndex, $lockIndex, $operation . ' lock must precede mutation');
        $this->assertSame(2, $events[$lockIndex]['transactionLevel'], $operation . ' lock transaction level');
        $this->assertSame(2, $events[$mutationIndex]['transactionLevel'], $operation . ' mutation transaction level');
    }

    public static function mutationLockProvider(): array
    {
        return [
            'soft delete' => ['destroy'],
            'publish toggle' => ['togglePublish'],
        ];
    }

    private static function normalizeSql(string $sql): string
    {
        $withoutBackticks = str_replace('`', '', strtolower($sql));
        return preg_replace('/\s+/', ' ', trim($withoutBackticks)) ?: '';
    }
}
