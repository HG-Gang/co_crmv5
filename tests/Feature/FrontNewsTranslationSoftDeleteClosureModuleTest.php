<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 13:17
 */

/**
 * 前台新闻多语言软删除闭环测试。
 *
 * 文件功能：
 * - 验证现代新闻列表 /api/front/news 忽略已软删除的翻译，优先使用未删除翻译，
 *   缺失翻译时回退主表标题/正文。
 * - 验证已删除翻译标题不能命中现代与遗留搜索。
 * - 验证遗留详情 /user/news/news_detail/{id}、热点 /user/main/hot/news 与
 *   /user/main/hot/newsV2 均忽略已删除翻译。
 * - 验证前台仪表盘 /api/front/dashboard 忽略已删除翻译。
 * - 验证数据库层拒绝同一新闻同一语言存在两条未删除翻译。
 *
 * 适用场景：
 * - 前台新闻多语言数据的回归测试，防止软删除翻译被误展示。
 *
 * 入参例子：
 * - GET /api/front/news?per_page=100（携带 X-Locale: zh-CN）。
 * - GET /user/news/news_detail/{id}、POST /user/main/hot/news、/user/main/hot/newsV2。
 *
 * 返回值：
 * - 已删除翻译被忽略，回退或使用未删除翻译；搜索不命中已删除标题。
 * - 插入重复活动翻译时抛出 QueryException。
 *
 * 异常或失败场景：
 * - 数据库唯一约束拒绝同新闻同语言的多条未删除翻译（QueryException）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Tests\Support\CreatesLegacyFrontUserFixture;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlTableFingerprint;

class FrontNewsTranslationSoftDeleteClosureModuleTest extends TestCase
{
    use CreatesLegacyFrontUserFixture;

    /**
     * 本用例涉及的数据表清单（news、news_langs 等）。tearDown 捕获并比对行指纹，
     * 任何差异都说明软删除用例泄漏了数据。
     * @var array<int, string>
     */
    private const FINGERPRINT_TABLES = [
        'news',
        'news_langs',
        'user_logins',
        'user_infos',
    ];

    private ?MySqlFixtureMutex $fixtureMutex = null;

    private ?MySqlAutoIncrementSnapshot $autoIncrementSnapshot = null;

    private int $deletedOnlyNewsId = 0;

    private int $mixedNewsId = 0;

    private int $userId = 0;

    /** @var array<string, array<string, int|string>> */
    private array $tableFingerprintBefore = [];

    /**
     * 测试前置：验证 migration 已安装唯一约束，并在 MySQL 建议锁内创建共享新闻夹具。
     *
     * @return void schema 完整且夹具创建成功时无返回值。
     *
     * @throws RuntimeException migration 未完整安装或互斥锁无法获取时抛出。
     * @throws \Throwable 夹具创建失败时完成清理和解锁后原样抛出。
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 测试只验证已安装结构；缺失时明确要求重跑 migrations，禁止在事务生命周期内执行 DDL 自愈。
        $this->assertNewsTranslationSchemaInstalled();
        $this->fixtureMutex = new MySqlFixtureMutex();
        $this->fixtureMutex->acquire();

        try {
            // 互斥锁覆盖完整快照窗口，确保前后差异只能来自当前测试夹具。
            $this->tableFingerprintBefore = MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES);
            $this->autoIncrementSnapshot = MySqlAutoIncrementSnapshot::capture(self::FINGERPRINT_TABLES);

            // 夹具创建：前台用户 + 两条新闻（一条仅主表可回退、一条含活动翻译）+ 软删除翻译。
            $this->userId = random_int(260000000, 269999999);
            $this->createLegacyFrontUserFixture($this->userId);

            $this->deletedOnlyNewsId = random_int(230000000, 239999999);
            $this->mixedNewsId = random_int(240000000, 249999999);
            $now = time();

            DB::table('news')->insert([
                [
                    'id' => $this->deletedOnlyNewsId,
                    'title' => 'Main fallback title',
                    'content' => '<p>Main fallback content</p>',
                    'image' => '',
                    'author_id' => 0,
                    'author_name' => 'News Admin',
                    'is_published' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
                [
                    'id' => $this->mixedNewsId,
                    'title' => 'Main mixed title',
                    'content' => '<p>Main mixed content</p>',
                    'image' => '',
                    'author_id' => 0,
                    'author_name' => 'News Admin',
                    'is_published' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
            ]);

            DB::table('news_langs')->insert([
                [
                    'news_id' => $this->deletedOnlyNewsId,
                    'lang_code' => 'zh-CN',
                    'title' => 'Deleted only translated title',
                    'content' => '<p>Deleted translated content</p>',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ],
                [
                    'news_id' => $this->mixedNewsId,
                    'lang_code' => 'zh-CN',
                    'title' => 'Active mixed translated title',
                    'content' => '<p>Active mixed translated content</p>',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
            ]);
        } catch (\Throwable $exception) {
            try {
                $this->restoreFixtureState();
            } finally {
                $this->fixtureMutex->releaseWithDisconnectFallback();
                $this->fixtureMutex = null;
            }

            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreFixtureState();
        } finally {
            try {
                if ($this->fixtureMutex !== null) {
                    $this->fixtureMutex->releaseWithDisconnectFallback();
                    $this->fixtureMutex = null;
                }
            } finally {
                parent::tearDown();
            }
        }
    }

    /**
     * 删除当前测试拥有的全部夹具行。
     *
     * 子表 news_langs 必须先于 news 物理删除，用户夹具同样按子表到父表顺序清理；
     * 任一删除失败都原样向上抛出，禁止把残留数据伪装成清理成功。
     *
     * @return void 全部夹具行删除成功时无返回值。
     */
    private function cleanupFixtureRows(): void
    {
        $newsIds = array_values(array_filter([$this->deletedOnlyNewsId, $this->mixedNewsId]));
        if ($newsIds !== []) {
            DB::table('news_langs')->whereIn('news_id', $newsIds)->delete();
            DB::table('news')->whereIn('id', $newsIds)->delete();
        }

        if ($this->userId > 0) {
            $this->cleanupLegacyFrontUserFixtures();
        }
    }

    /**
     * 清理夹具并恢复测试前的表数据、结构及 AUTO_INCREMENT。
     *
     * @return void 四张共享表与测试前指纹完全一致时无返回值。
     *
     * @throws \Throwable 清理、自增值恢复或指纹比对任一步失败时原样抛出。
     */
    private function restoreFixtureState(): void
    {
        try {
            $this->cleanupFixtureRows();
        } finally {
            // 即使行清理失败也尝试恢复元数据；若仍有高位夹具行，快照工具会失败关闭而不会强行降低自增值。
            if ($this->autoIncrementSnapshot !== null) {
                $this->autoIncrementSnapshot->restore();
                $this->autoIncrementSnapshot = null;
            }
        }

        if ($this->tableFingerprintBefore === []) {
            return;
        }

        $this->assertSame(
            $this->tableFingerprintBefore,
            MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES),
            '新闻测试夹具必须完整恢复表数据、结构与 AUTO_INCREMENT。'
        );
        $this->tableFingerprintBefore = [];
    }

    /**
     * 只读验证活跃翻译唯一约束已经由最新 migration 安装。
     *
     * @return void 生成列和唯一索引均符合约定时无返回值。
     *
     * @throws RuntimeException 生成列或唯一索引缺失时抛出，并提示重建测试 schema。
     */
    private function assertNewsTranslationSchemaInstalled(): void
    {
        $column = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            ['news_langs', 'active_translation_key'],
            false
        );
        $columnValues = $column ? (array) $column : [];
        $extra = (string) ($columnValues['EXTRA'] ?? $columnValues['extra'] ?? '');

        $indexRows = DB::select(
            'SELECT NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? '
            . 'ORDER BY SEQ_IN_INDEX',
            ['news_langs', 'news_langs_active_translation_unique'],
            false
        );
        $indexValues = array_map(
            static fn ($row): array => array_change_key_case((array) $row, CASE_LOWER),
            $indexRows
        );
        $indexColumns = array_map(
            static fn (array $row): string => (string) ($row['column_name'] ?? ''),
            $indexValues
        );

        $generatedColumnInstalled = stripos($extra, 'generated') !== false;
        $uniqueIndexInstalled = $indexValues !== []
            && count(array_filter(
                $indexValues,
                static fn (array $row): bool => (int) ($row['non_unique'] ?? 1) !== 0
            )) === 0
            && $indexColumns === ['active_translation_key'];
        if (!$generatedColumnInstalled || !$uniqueIndexInstalled) {
            throw new RuntimeException(
                '测试数据库 migrations 未完整安装：news_langs 活跃翻译生成列或唯一索引缺失。'
            );
        }
    }

    // 验证现代新闻列表忽略已删除翻译并回退到活动翻译或主表内容。
    public function test_modern_news_list_ignores_deleted_translations_and_uses_active_translation(): void
    {
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withHeader('X-Locale', 'zh-CN')
            ->getJson('/api/front/news?per_page=100');

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $rows = collect($response->json('data.news.data'))->keyBy('news_id');
        $this->assertSame('Main fallback title', $rows[$this->deletedOnlyNewsId]['title'] ?? null);
        $this->assertSame('<p>Main fallback content</p>', $rows[$this->deletedOnlyNewsId]['content'] ?? null);
        $this->assertSame('Active mixed translated title', $rows[$this->mixedNewsId]['title'] ?? null);
        $this->assertSame('<p>Active mixed translated content</p>', $rows[$this->mixedNewsId]['content'] ?? null);
    }

    public function test_modern_news_list_batches_active_translation_queries(): void
    {
        $translationQueries = 0;
        DB::listen(static function ($query) use (&$translationQueries): void {
            if (stripos((string) $query->sql, 'news_langs') !== false) {
                ++$translationQueries;
            }
        });

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withHeader('X-Locale', 'zh-CN')
            ->getJson('/api/front/news?title=Main%20&per_page=100');

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertLessThanOrEqual(2, $translationQueries);

        $source = file_get_contents(app_path('Http/Controllers/Front/NewsController.php')) ?: '';
        $start = strpos($source, 'private function newsRow(');
        $method = substr($source, (int) $start);
        $this->assertStringNotContainsString("DB::table('news_langs')", $method);
        $this->assertStringContainsString('newsTranslationsFor', $source);
    }

    // 验证已删除翻译标题不能命中现代或遗留搜索。
    public function test_deleted_translation_title_cannot_match_modern_or_legacy_search(): void
    {
        $modern = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withHeader('X-Locale', 'zh-CN')
            ->getJson('/api/front/news?title=Deleted%20only%20translated%20title&per_page=100');
        $modern->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertNotContains($this->deletedOnlyNewsId, collect($modern->json('data.news.data'))->pluck('news_id')->map(fn ($id) => (int) $id)->all());

        $legacy = $this->withHeader('X-Locale', 'zh-CN')
            ->postJson('/user/newsListSearch', [
                'title' => 'Deleted only translated title',
                'per_page' => 100,
            ]);
        $legacy->assertOk();
        $this->assertNotContains($this->deletedOnlyNewsId, collect($legacy->json('rows'))->pluck('news_id')->map(fn ($id) => (int) $id)->all());
    }

    // 验证遗留详情与热点新闻均忽略已删除翻译。
    public function test_legacy_detail_and_hot_news_ignore_deleted_translation(): void
    {
        $detail = $this->withHeader('X-Locale', 'zh-CN')
            ->withSession(['suser' => ['user_id' => $this->userId]])
            ->get('/user/news/news_detail/' . $this->deletedOnlyNewsId);
        $detail->assertOk()
            ->assertSeeText('Main fallback title')
            ->assertSee('Main fallback content', false)
            ->assertDontSeeText('Deleted only translated title');

        $hot = $this->withHeader('X-Locale', 'zh-CN')->postJson('/user/main/hot/news', ['page' => 1]);
        $hot->assertOk()->assertJsonPath('code', 0);
        $this->assertStringContainsString('Main fallback title', (string) $hot->json('dataHtml'));
        $this->assertStringNotContainsString('Deleted only translated title', (string) $hot->json('dataHtml'));

        $hotV2 = $this->withSession(['suser' => ['user_id' => $this->userId]])
            ->postJson('/user/main/hot/newsV2', ['page' => 1, 'lang_id' => 1]);
        $hotV2->assertOk()->assertJsonPath('code', 200);
        $row = collect($hotV2->json('data'))->firstWhere('aid', $this->deletedOnlyNewsId);
        $this->assertSame('Main fallback title', $row['title'] ?? null);
    }

    // 验证前台仪表盘忽略已删除翻译。
    public function test_dashboard_ignores_deleted_translation(): void
    {
        $login = $this->dashboardUser();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('X-Locale', 'zh-CN')
            ->getJson('/api/front/dashboard');

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $row = collect($response->json('data.news'))->firstWhere('id', $this->deletedOnlyNewsId);
        $this->assertSame('Main fallback title', $row['title'] ?? null);
        $this->assertSame('<p>Main fallback content</p>', $row['content'] ?? null);
    }

    // 验证数据库拒绝同一新闻同一语言的第二条未删除翻译。
    public function test_database_rejects_two_active_translations_for_the_same_news_and_language(): void
    {
        $this->expectException(QueryException::class);

        DB::table('news_langs')->insert([
            'news_id' => $this->mixedNewsId,
            'lang_code' => 'zh-CN',
            'title' => 'Second active translation must be rejected',
            'content' => '<p>Duplicate active content</p>',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function dashboardUser(): UserLogin
    {
        return UserLogin::where('user_id', $this->userId)->firstOrFail();
    }
}
