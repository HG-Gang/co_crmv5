<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 16:23
 */

/**
 * AdminNewsWriteBoundaryClosureModuleTest
 *
 * 文件功能：
 * - 验证新闻公告写边界闭环：写入白名单与默认发布态、镜像翻译同步与字节边界、异常路径回滚并隐藏内部错误、路由 id 非正整数一律拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminNewsWriteBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_uses_the_write_whitelist_current_author_and_default_publish_state(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $title = 'Whitelisted Store ' . uniqid('', true);
        $now = time();

        $response = $this->modernPost($admin, '/api/admin/createNews', [
            'id' => 2147483101,
            'title' => $title,
            'content' => 'Whitelisted content',
            'image' => '/attacker.png',
            'author_id' => 998201,
            'author_name' => 'Attacker',
            'deleted_at' => $now,
            'created_at' => 1,
            'updated_at' => 1,
        ])->assertOk()->assertJsonPath('code', ResponseCode::CREATED);

        $id = (int) $response->json('data.id');
        $news = DB::table('news')->where('id', $id)->first();
        $this->assertNotSame(2147483101, $id);
        $this->assertSame($title, $news->title);
        $this->assertSame('Whitelisted content', $news->content);
        $this->assertNull($news->image);
        $this->assertSame((int) $admin->id, (int) $news->author_id);
        $this->assertSame((string) $admin->username, (string) $news->author_name);
        $this->assertSame(0, (int) $news->is_published);
        $this->assertNull($news->deleted_at);
        $this->assertGreaterThan(1, (int) $news->created_at);
        $this->assertGreaterThan(1, (int) $news->updated_at);
    }

    public function test_update_only_changes_whitelisted_fields_and_synchronizes_exact_active_mirrors(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Café News', 'Old Content', 1);
        $otherId = $this->insertNews('Other Main', 'Other Content', 1);
        $exactId = $this->insertTranslation($targetId, 'en', 'Café News', 'Old Content', null);
        $caseVariantId = $this->insertTranslation($targetId, 'zh-CN', 'CAFÉ NEWS', 'Old Content', null);
        $accentVariantId = $this->insertTranslation($targetId, 'ja', 'Cafe News', 'Old Content', null);
        $spaceVariantId = $this->insertTranslation($targetId, 'de', 'Café News ', 'Old Content', null);
        $manualContentId = $this->insertTranslation($targetId, 'ko', 'Café News', 'OLD CONTENT', null);
        $deletedMirrorId = $this->insertTranslation($targetId, 'fr', 'Café News', 'Old Content', time());
        $translationCount = DB::table('news_langs')->where('news_id', $targetId)->count();

        $this->modernPost($admin, '/api/admin/updateNews/' . $targetId, [
            'id' => $otherId,
            'title' => 'New Main',
            'content' => 'New Content',
            'is_published' => 0,
            'image' => '/attacker.png',
            'author_id' => 998202,
            'author_name' => 'Attacker',
            'deleted_at' => time(),
            'created_at' => 1,
            'updated_at' => 1,
        ])->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);

        $target = DB::table('news')->where('id', $targetId)->first();
        $other = DB::table('news')->where('id', $otherId)->first();
        $this->assertSame('New Main', $target->title);
        $this->assertSame('New Content', $target->content);
        $this->assertSame('/original.png', $target->image);
        $this->assertSame((int) $admin->id, (int) $target->author_id);
        $this->assertSame((string) $admin->username, (string) $target->author_name);
        $this->assertSame(0, (int) $target->is_published);
        $this->assertNull($target->deleted_at);
        $this->assertSame('Other Main', $other->title);

        $this->assertTranslation($exactId, 'New Main', 'New Content');
        $this->assertTranslation($caseVariantId, 'CAFÉ NEWS', 'Old Content');
        $this->assertTranslation($accentVariantId, 'Cafe News', 'Old Content');
        $this->assertTranslation($spaceVariantId, 'Café News ', 'Old Content');
        $this->assertTranslation($manualContentId, 'Café News', 'OLD CONTENT');
        $this->assertTranslation($deletedMirrorId, 'Café News', 'Old Content');
        $this->assertSame($translationCount, DB::table('news_langs')->where('news_id', $targetId)->count());
    }

    public function test_update_preserves_publish_state_when_the_optional_field_is_missing(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Default Publish Old', 'Old Content', 1);

        $this->modernPost($admin, '/api/admin/updateNews/' . $targetId, [
            'title' => 'Default Publish New',
            'content' => 'New Content',
        ])->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame(1, (int) DB::table('news')->where('id', $targetId)->value('is_published'));
    }

    public function test_update_supports_the_news_lang_column_and_byte_limits_at_the_exact_boundary(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Boundary Old', 'Boundary Content', 1);
        $translationId = $this->insertTranslation($targetId, 'en', 'Boundary Old', 'Boundary Content', null);
        $maxTitle = str_repeat('T', 255);
        $maxContent = str_repeat('C', 65535);

        $this->modernPost($admin, '/api/admin/updateNews/' . $targetId, [
            'title' => $maxTitle,
            'content' => $maxContent,
            'is_published' => 0,
        ])->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);

        $news = DB::table('news')->where('id', $targetId)->first();
        $this->assertSame($maxTitle, $news->title);
        $this->assertSame($maxContent, $news->content);
        $this->assertTranslation($translationId, $maxTitle, $maxContent);
        $this->assertNull(DB::table('news_langs')->where('id', $translationId)->value('deleted_at'));
    }

    /**
     * @dataProvider oversizedMirrorPayloadProvider
     */
    public function test_update_soft_deletes_exact_mirrors_when_new_values_exceed_translation_storage(
        string $title,
        string $content
    ): void {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Oversized Old', 'Oversized Content', 1);
        $exactId = $this->insertTranslation($targetId, 'en', 'Oversized Old', 'Oversized Content', null);
        $manualId = $this->insertTranslation($targetId, 'ja', 'Manual Translation', 'Manual Content', null);

        $this->modernPost($admin, '/api/admin/updateNews/' . $targetId, [
            'title' => $title,
            'content' => $content,
            'is_published' => 1,
        ])->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame($title, DB::table('news')->where('id', $targetId)->value('title'));
        $this->assertSame($content, DB::table('news')->where('id', $targetId)->value('content'));
        $this->assertNotNull(DB::table('news_langs')->where('id', $exactId)->value('deleted_at'));
        $exactMirror = DB::table('news_langs')->where('id', $exactId)->first();
        $this->assertSame((int) $exactMirror->deleted_at, (int) $exactMirror->updated_at);
        $this->assertNull(DB::table('news_langs')->where('id', $manualId)->value('deleted_at'));
        $this->assertTranslation($manualId, 'Manual Translation', 'Manual Content');
    }

    public static function oversizedMirrorPayloadProvider(): array
    {
        return [
            'title over 255 characters' => [str_repeat('T', 256), 'Within content limit'],
            'content over 65535 bytes' => ['Within title limit', str_repeat('C', 65536)],
        ];
    }

    public function test_update_rolls_back_main_and_translation_when_mirror_write_throws(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Rollback Old', 'Rollback Content', 1);
        $translationId = $this->insertTranslation($targetId, 'en', 'Rollback Old', 'Rollback Content', null);
        $beforeNews = DB::table('news')->where('id', $targetId)->first();
        $beforeTranslation = DB::table('news_langs')->where('id', $translationId)->first();
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $testDispatcher = new Dispatcher($this->app);
        $testDispatcher->listen(QueryExecuted::class, static function (QueryExecuted $query): void {
            $sql = self::normalizeSql($query->sql);
            if (self::matchesMutationSql('update', $sql, 'update', 'news_langs', 'title')) {
                throw new \RuntimeException('Forced mirror sync failure');
            }
        });
        $connection->setEventDispatcher($testDispatcher);

        try {
            $response = $this->modernPost($admin, '/api/admin/updateNews/' . $targetId, [
                'title' => 'Rollback New',
                'content' => 'Changed Content',
                'is_published' => 0,
            ]);
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
        }

        $response->assertOk()->assertJsonPath('code', ResponseCode::SERVER_ERROR);
        $this->assertSame($originalDispatcher, $connection->getEventDispatcher());

        $news = DB::table('news')->where('id', $targetId)->first();
        $this->assertSame('Rollback Old', $news->title);
        $this->assertSame('Rollback Content', $news->content);
        $this->assertSame(1, (int) $news->is_published);
        $this->assertSame((int) $beforeNews->author_id, (int) $news->author_id);
        $this->assertSame((string) $beforeNews->author_name, (string) $news->author_name);
        $this->assertSame((int) $beforeNews->updated_at, (int) $news->updated_at);
        $this->assertTranslation($translationId, 'Rollback Old', 'Rollback Content');
        $translation = DB::table('news_langs')->where('id', $translationId)->first();
        $this->assertSame((int) $beforeTranslation->updated_at, (int) $translation->updated_at);
        $this->assertNull($translation->deleted_at);
    }

    /**
     * @dataProvider newsWriteExceptionProvider
     */
    public function test_each_news_write_exception_path_rolls_back_logs_context_and_hides_internal_error(
        string $operation,
        string $statement,
        string $table,
        string $keyField
    ): void {
        $admin = Admin::query()->findOrFail(1);
        $title = 'Forced ' . $operation . ' ' . uniqid('', true);
        $targetId = null;
        $translationId = null;
        $payload = [];

        if ($operation === 'store') {
            $uri = '/api/admin/createNews';
            $payload = ['title' => $title, 'content' => 'Forced store content'];
        } else {
            $targetId = $this->insertNews($title, 'Original content', 1);
            if ($operation === 'update') {
                $translationId = $this->insertTranslation($targetId, 'en', $title, 'Original content', null);
                $uri = '/api/admin/updateNews/' . $targetId;
                $payload = ['title' => 'Changed title', 'content' => 'Changed content', 'is_published' => 0];
            } elseif ($operation === 'destroy') {
                $uri = '/api/admin/deleteNews/' . $targetId;
            } else {
                $uri = '/api/admin/toggleNews/' . $targetId;
            }
        }

        $beforeCount = DB::table('news')->count();
        $beforeNews = $targetId === null
            ? null
            : (array) DB::table('news')->where('id', $targetId)->first();
        $beforeTranslation = $translationId === null
            ? null
            : (array) DB::table('news_langs')->where('id', $translationId)->first();
        $exceptionMessage = 'Forced ' . $operation . ' transaction failure';
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $testDispatcher = new Dispatcher($this->app);
        $injectedException = null;
        $testDispatcher->listen(
            QueryExecuted::class,
            static function (QueryExecuted $query) use (
                $operation,
                $statement,
                $table,
                $keyField,
                $exceptionMessage,
                &$injectedException
            ): void {
                $sql = self::normalizeSql($query->sql);
                if (self::matchesMutationSql($operation, $sql, $statement, $table, $keyField)) {
                    $injectedException = new QueryException(
                        $query->sql,
                        $query->bindings,
                        new \RuntimeException($exceptionMessage)
                    );
                    throw $injectedException;
                }
            }
        );

        $originalLogger = Log::getFacadeRoot();
        Log::spy();
        $connection->setEventDispatcher($testDispatcher);

        try {
            try {
                $response = $this->modernPost($admin, $uri, $payload);
            } finally {
                if ($originalDispatcher) {
                    $connection->setEventDispatcher($originalDispatcher);
                } else {
                    $connection->unsetEventDispatcher();
                }
            }

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::SERVER_ERROR)
                ->assertJsonPath('message', __('response.server_error'));
            $this->assertInstanceOf(QueryException::class, $injectedException);
            $sensitiveValues = array_values(array_filter(
                array_merge(
                    [
                        $title,
                        $exceptionMessage,
                        $injectedException->getMessage(),
                        $injectedException->getSql(),
                    ],
                    array_values($payload)
                ),
                'is_string'
            ));
            foreach ($sensitiveValues as $sensitiveValue) {
                $this->assertStringNotContainsString($sensitiveValue, $response->getContent());
            }
            $this->assertStringNotContainsString(QueryException::class, $response->getContent());
            $this->assertSame($originalDispatcher, $connection->getEventDispatcher());
            $this->assertSame($beforeCount, DB::table('news')->count());

            if ($targetId === null) {
                $this->assertDatabaseMissing('news', ['title' => $title]);
            } else {
                $this->assertSame(
                    $beforeNews,
                    (array) DB::table('news')->where('id', $targetId)->first()
                );
            }
            if ($translationId !== null) {
                $this->assertSame(
                    $beforeTranslation,
                    (array) DB::table('news_langs')->where('id', $translationId)->first()
                );
            }

            Log::shouldHaveReceived('error')->once()->withArgs(
                static function (string $message, array $context) use (
                    $operation,
                    $targetId,
                    $exceptionMessage,
                    $sensitiveValues
                ): bool {
                    $hasExpectedId = $targetId === null
                        ? !array_key_exists('news_id', $context)
                        : ($context['news_id'] ?? null) === $targetId;
                    $encodedContext = json_encode($context) ?: '';
                    foreach ($sensitiveValues as $sensitiveValue) {
                        if (strpos($encodedContext, $sensitiveValue) !== false) {
                            return false;
                        }
                    }

                    return $message === 'Admin news operation failed.'
                        && ($context['operation'] ?? null) === $operation
                        && $hasExpectedId
                        && ($context['exception_class'] ?? null) === QueryException::class
                        && ($context['exception_code'] ?? null) === 0
                        && !array_key_exists('exception', $context)
                        && !array_key_exists('message', $context)
                        && strpos($encodedContext, $exceptionMessage) === false;
                }
            );
        } finally {
            Log::swap($originalLogger);
        }
    }

    public static function newsWriteExceptionProvider(): array
    {
        return [
            'store' => ['store', 'insert into', 'news', 'title'],
            'update' => ['update', 'update', 'news_langs', 'title'],
            'destroy' => ['destroy', 'update', 'news', 'deleted_at'],
            'toggle' => ['togglePublish', 'update', 'news', 'is_published'],
        ];
    }

    public function test_update_locks_the_main_row_and_active_translation_candidates(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Lock Order Old', 'Lock Order Content', 1);
        $translationId = $this->insertTranslation(
            $targetId,
            'en',
            'Lock Order Old',
            'Lock Order Content',
            null
        );
        $events = [];
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $testDispatcher = new Dispatcher($this->app);
        $testDispatcher->listen(QueryExecuted::class, static function (QueryExecuted $query) use (&$events): void {
            $sql = self::normalizeSql($query->sql);
            if (strpos($sql, 'select * from news ') === 0
                || strpos($sql, 'select id, title, content from news_langs ') === 0
                || strpos($sql, 'update news set ') === 0
                || strpos($sql, 'update news_langs set ') === 0) {
                $events[] = [
                    'sql' => $sql,
                    'bindings' => $query->bindings,
                    'transactionLevel' => $query->connection->transactionLevel(),
                ];
            }
        });
        $connection->setEventDispatcher($testDispatcher);

        try {
            $response = $this->modernPost($admin, '/api/admin/updateNews/' . $targetId, [
                'title' => 'Lock Order New',
                'content' => 'Lock Order Changed',
                'is_published' => 0,
            ]);
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
        }

        $response->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);
        $this->assertSame($originalDispatcher, $connection->getEventDispatcher());

        $mainLock = $this->findQueryEvent($events, static function (array $event) use ($targetId): bool {
            return strpos($event['sql'], 'select * from news ') === 0
                && strpos($event['sql'], 'for update') !== false
                && in_array((string) $targetId, array_map('strval', $event['bindings']), true);
        });
        $translationLock = $this->findQueryEvent($events, static function (array $event) use ($targetId): bool {
            return strpos($event['sql'], 'select id, title, content from news_langs ') === 0
                && strpos($event['sql'], 'news_id = ?') !== false
                && strpos($event['sql'], 'deleted_at is null') !== false
                && strpos($event['sql'], 'for update') !== false
                && in_array((string) $targetId, array_map('strval', $event['bindings']), true);
        });
        $mainUpdate = $this->findQueryEvent($events, static function (array $event) use ($targetId): bool {
            return strpos($event['sql'], 'update news set ') === 0
                && in_array((string) $targetId, array_map('strval', $event['bindings']), true);
        });
        $translationUpdate = $this->findQueryEvent($events, static function (array $event) use ($translationId): bool {
            return strpos($event['sql'], 'update news_langs set ') === 0
                && in_array((string) $translationId, array_map('strval', $event['bindings']), true);
        });

        $this->assertNotNull($mainLock, 'main news SELECT ... FOR UPDATE');
        $this->assertNotNull($translationLock, 'active translation SELECT ... FOR UPDATE');
        $this->assertNotNull($mainUpdate, 'main news UPDATE');
        $this->assertNotNull($translationUpdate, 'translation UPDATE');
        $this->assertLessThan($translationLock, $mainLock, 'main row must be locked first');
        $this->assertLessThan($mainUpdate, $translationLock, 'translation rows must be locked before mutation');
        $this->assertLessThan($translationUpdate, $mainUpdate, 'main mutation must precede mirror mutation');
        foreach ([$mainLock, $translationLock, $mainUpdate, $translationUpdate] as $eventIndex) {
            $this->assertSame(2, $events[$eventIndex]['transactionLevel']);
        }
    }

    public function test_news_server_error_response_logs_target_id_and_hides_exception_from_client(): void
    {
        $newsId = 2147483099;
        $sensitiveTitle = 'Sensitive query title';
        $sensitiveContent = 'Sensitive query content';
        $exception = new QueryException(
            'update `news` set `title` = ?, `content` = ? where `id` = ?',
            [$sensitiveTitle, $sensitiveContent, $newsId],
            new \RuntimeException('Sensitive news write failure')
        );
        $originalLogger = Log::getFacadeRoot();
        Log::spy();

        try {
            $method = new \ReflectionMethod(NewsController::class, 'newsServerErrorResponse');
            $method->setAccessible(true);
            $response = $method->invoke(new NewsController(), $exception, 'update', $newsId);
            $payload = $response->getData(true);

            $this->assertSame(ResponseCode::SERVER_ERROR, (int) $payload['code']);
            $this->assertSame(__('response.server_error'), $payload['message']);
            $encodedPayload = json_encode($payload) ?: '';
            $this->assertStringNotContainsString($exception->getMessage(), $encodedPayload);
            $this->assertStringNotContainsString(get_class($exception), $encodedPayload);
            $this->assertStringNotContainsString($exception->getSql(), $encodedPayload);
            $this->assertStringNotContainsString($sensitiveTitle, $encodedPayload);
            $this->assertStringNotContainsString($sensitiveContent, $encodedPayload);

            Log::shouldHaveReceived('error')->once()->withArgs(
                static function (string $message, array $context) use (
                    $exception,
                    $newsId,
                    $sensitiveTitle,
                    $sensitiveContent
                ): bool {
                    $encodedContext = json_encode($context) ?: '';

                    return $message === 'Admin news operation failed.'
                        && ($context['operation'] ?? null) === 'update'
                        && ($context['news_id'] ?? null) === $newsId
                        && ($context['exception_class'] ?? null) === get_class($exception)
                        && ($context['exception_code'] ?? null) === 0
                        && !array_key_exists('exception', $context)
                        && !array_key_exists('message', $context)
                        && strpos($encodedContext, $exception->getMessage()) === false
                        && strpos($encodedContext, $exception->getSql()) === false
                        && strpos($encodedContext, $sensitiveTitle) === false
                        && strpos($encodedContext, $sensitiveContent) === false;
                }
            );
        } finally {
            Log::swap($originalLogger);
        }
    }

    public function test_zero_route_id_never_falls_back_to_a_valid_body_id_for_update_or_delete(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Route Zero Target', 'Route Zero Content', 1);

        $this->modernPost($admin, '/api/admin/updateNews/0', [
            'id' => $targetId,
            'title' => 'Must Not Update',
            'content' => 'Must Not Update Content',
            'is_published' => 0,
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->modernPost($admin, '/api/admin/deleteNews/0', [
            'id' => $targetId,
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('news', [
            'id' => $targetId,
            'title' => 'Route Zero Target',
            'content' => 'Route Zero Content',
            'is_published' => 1,
            'deleted_at' => null,
        ]);
    }

    /**
     * @dataProvider invalidPositiveRouteIdProvider
     */
    public function test_update_delete_and_toggle_reject_every_non_positive_integer_route_id(string $routeId): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Invalid Route Target ' . $routeId, 'Original Content', 1);

        $this->modernPost($admin, '/api/admin/updateNews/' . $routeId, [
            'id' => $targetId,
            'title' => 'Must Not Update',
            'content' => 'Must Not Update Content',
            'is_published' => 0,
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->modernPost($admin, '/api/admin/deleteNews/' . $routeId, ['id' => $targetId])
            ->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->modernPost($admin, '/api/admin/toggleNews/' . $routeId, ['id' => $targetId])
            ->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('news', [
            'id' => $targetId,
            'title' => 'Invalid Route Target ' . $routeId,
            'content' => 'Original Content',
            'is_published' => 1,
            'deleted_at' => null,
        ]);
    }

    public static function invalidPositiveRouteIdProvider(): array
    {
        return [
            'negative' => ['-1'],
            'decimal' => ['1.5'],
            'suffix' => ['1abc'],
        ];
    }

    /**
     * @dataProvider invalidWritePayloadProvider
     */
    public function test_store_rejects_invalid_write_payloads(array $payload): void
    {
        $admin = Admin::query()->findOrFail(1);
        $before = DB::table('news')->count();

        $this->modernPost($admin, '/api/admin/createNews', $payload)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame($before, DB::table('news')->count());
    }

    /**
     * @dataProvider invalidWritePayloadProvider
     */
    public function test_update_rejects_the_same_invalid_write_payloads_without_any_write(array $payload): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Invalid Update Target', 'Original Update Content', 1);
        $translationId = $this->insertTranslation(
            $targetId,
            'en',
            'Invalid Update Target',
            'Original Update Content',
            null
        );
        $beforeNews = (array) DB::table('news')->where('id', $targetId)->first();
        $beforeTranslation = (array) DB::table('news_langs')->where('id', $translationId)->first();

        $this->modernPost($admin, '/api/admin/updateNews/' . $targetId, $payload)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame($beforeNews, (array) DB::table('news')->where('id', $targetId)->first());
        $this->assertSame(
            $beforeTranslation,
            (array) DB::table('news_langs')->where('id', $translationId)->first()
        );
    }

    public static function invalidWritePayloadProvider(): array
    {
        return [
            'missing title' => [['content' => 'content']],
            'title array' => [['title' => ['title'], 'content' => 'content']],
            'title too long' => [['title' => str_repeat('T', 501), 'content' => 'content']],
            'content array' => [['title' => 'title', 'content' => ['content']]],
            'content object' => [['title' => 'title', 'content' => (object) ['value' => 'content']]],
            'publish out of range' => [['title' => 'title', 'content' => 'content', 'is_published' => 2]],
            'publish array' => [['title' => 'title', 'content' => 'content', 'is_published' => ['1']]],
        ];
    }

    private static function normalizeSql(string $sql): string
    {
        $withoutBackticks = str_replace('`', '', strtolower($sql));
        return preg_replace('/\s+/', ' ', trim($withoutBackticks)) ?: '';
    }

    private static function matchesMutationSql(
        string $operation,
        string $sql,
        string $statement,
        string $table,
        string $keyField
    ): bool {
        $expected = [
            'store' => ['insert into', 'news', 'title'],
            'update' => ['update', 'news_langs', 'title'],
            'destroy' => ['update', 'news', 'deleted_at'],
            'togglePublish' => ['update', 'news', 'is_published'],
        ][$operation] ?? null;

        return $expected === [$statement, $table, $keyField]
            && strpos($sql, $statement . ' ' . $table . ' ') === 0
            && strpos($sql, $keyField) !== false;
    }

    private function findQueryEvent(array $events, callable $matcher): ?int
    {
        foreach ($events as $index => $event) {
            if ($matcher($event)) {
                return $index;
            }
        }

        return null;
    }

    private function modernPost(Admin $admin, string $uri, array $payload)
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')->postJson($uri, $payload);
    }

    private function insertNews(string $title, string $content, int $isPublished): int
    {
        $now = time();

        return (int) DB::table('news')->insertGetId([
            'title' => $title,
            'content' => $content,
            'image' => '/original.png',
            'author_id' => 998299,
            'author_name' => 'Original Author',
            'is_published' => $isPublished,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertTranslation(
        int $newsId,
        string $langCode,
        string $title,
        string $content,
        ?int $deletedAt
    ): int {
        $now = time();

        return (int) DB::table('news_langs')->insertGetId([
            'news_id' => $newsId,
            'lang_code' => $langCode,
            'title' => $title,
            'content' => $content,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deletedAt,
        ]);
    }

    private function assertTranslation(int $id, string $title, string $content): void
    {
        $translation = DB::table('news_langs')->where('id', $id)->first();
        $this->assertSame($title, $translation->title);
        $this->assertSame($content, $translation->content);
    }
}
