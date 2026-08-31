<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 06:08
 */

// 修复 FrontNewsTranslationSoftDeleteClosureModuleTest：删除文件头误插块，class 内正确插入 setUp。
$f = 'tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php';
$lines = file($f);

// 1) 删除误插的 setUp 块（在文件头 docblock 内，从 "/**\n     * 测试前置" 到 "    }"）
$out = [];
$skip = false;
foreach ($lines as $line) {
    if (strpos($line, ' * 测试前置：全量回归中其他测试可能重建 news_langs 表') !== false) {
        // 找到块起始（向上找 /**）
        $skip = true;
        array_pop($out); // 移除上一行（可能是 docblock 行）
        // 继续移除直到块结束（"    }" 且后面是 docblock 尾部）
        continue;
    }
    if ($skip) {
        // 跳过直到块结束：块以 "    }" 结束
        if (trim($line) === '}') {
            $skip = false;
        }
        continue;
    }
    $out[] = $line;
}

$c = implode('', $out);

// 2) class 内插入 setUp
$classPos = strpos($c, 'final class FrontNewsTranslationSoftDeleteClosureModuleTest extends TestCase');
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
echo "完成\n";
