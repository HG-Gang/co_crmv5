<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:42
 */

// 字符级 mojibake 修复：识别 GBK 误解码字符并逆转，恢复正常中文。
$targets = [
    'scripts/generate-full-route-execution-chain-report.php',
    'tests/Feature/FrontUiRegressionTest.php',
];

/**
 * 尝试把单个字符从 mojibake 逆转回正常中文。
 *
 * @param string $char 单字符。
 * @return string 修复后的字符（无法逆转时原样返回）。
 */
function fixChar($char)
{
    $gbk = @iconv('UTF-8', 'GBK', $char);
    if ($gbk !== false && strlen($gbk) === 2) {
        $b0 = ord($gbk[0]);
        $b1 = ord($gbk[1]);
        // GBK 双字节中文：0x81-0xFE 高位 + 0x40-0xFE 低位（排除 0x7F）
        if ($b0 >= 0x81 && $b0 <= 0xFE && $b1 >= 0x40 && $b1 <= 0xFE && $b1 !== 0x7F) {
            $fixed = @iconv('GBK', 'UTF-8', $gbk);
            if ($fixed !== false && $fixed !== $char && preg_match('/[\x{4e00}-\x{9fff}]/u', $fixed)) {
                return $fixed;
            }
        }
    }
    return $char;
}

foreach ($targets as $f) {
    if (! is_file($f)) {
        echo "MISS $f\n";
        continue;
    }
    $c = file_get_contents($f);
    $chars = preg_split('//u', $c, -1, PREG_SPLIT_NO_EMPTY);
    $fixedChars = [];
    $changed = 0;
    foreach ($chars as $ch) {
        $fx = fixChar($ch);
        if ($fx !== $ch) {
            $changed++;
        }
        $fixedChars[] = $fx;
    }
    file_put_contents($f, implode('', $fixedChars));
    echo "$f: 修复字符 $changed 个\n";
}
