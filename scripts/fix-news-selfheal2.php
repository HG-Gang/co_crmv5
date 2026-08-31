<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 06:08
 */

// 在 FrontNewsTranslationSoftDeleteClosureModuleTest class 内插入 setUp 自愈。
$f = 'tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php';
$c = file_get_contents($f);
$classPos = strpos($c, 'class FrontNewsTranslationSoftDeleteClosureModuleTest extends TestCase');
if ($classPos === false) {
    echo "class 未找到\n";
    exit(1);
}
$brace = strpos($c, '{', $classPos);
$setUp = '
    /**
     * 测试前置：全量回归中其他测试可能重建 news_langs 表导致生成列 active_translation_key 丢失，
     * 此处自愈重建（唯一索引依赖该生成列，缺失时重复 active 翻译不会被数据库拒绝）。
     *
     * @return void 无返回值。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();
        $cols = $pdo->query("SELECT EXTRA FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'news_langs\' AND column_name = \'active_translation_key\'")->fetchAll();
        $isGenerated = false;
        foreach ($cols as $row) {
            if (stripos((string) ($row[\'EXTRA\'] ?? \'\'), \'generated\') !== false) {
                $isGenerated = true;
            }
        }
        if (! $isGenerated) {
            try {
                $pdo->exec(\'ALTER TABLE `news_langs` DROP INDEX `news_langs_active_translation_unique`\');
            } catch (\Throwable $e) {
                // 索引不存在时忽略。
            }
            $pdo->exec("ALTER TABLE `news_langs` MODIFY COLUMN `active_translation_key` VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN `deleted_at` IS NULL THEN CONCAT(`news_id`, \':\', `lang_code`) ELSE NULL END) STORED");
            $pdo->exec(\'ALTER TABLE `news_langs` ADD UNIQUE INDEX `news_langs_active_translation_unique` (`active_translation_key`)\');
        }
    }
';
$c = substr($c, 0, $brace + 1) . "\n" . $setUp . substr($c, $brace + 1);
file_put_contents($f, $c);
echo "setUp 插入完成\n";
