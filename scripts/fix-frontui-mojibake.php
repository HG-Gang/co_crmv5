<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:38
 */

/**
 * 修复 FrontUiRegressionTest 断言消息乱码的历史一次性脚本。
 *
 * 文件功能：
 * - 将 4 处 GBK 误转码的断言消息替换为正确 UTF-8 字面量。
 *
 * 适用场景：
 * - 仅历史修复用，当前代码无需再执行。
 */

// 修复 FrontUiRegressionTest 4 处断言消息 mojibake（直接 UTF-8 字面量替换）。
$f = 'tests/Feature/FrontUiRegressionTest.php';
$c = file_get_contents($f);

$replacements = [
    ["鍓嶇涓嶅簲鍐嶅嚭鐜?crm-data-head 鎴?crm-row-detail 杩欑被鏃?DOM 娈嬬暀銆?", "前端不应再出现 crm-data-head 或 crm-row-detail 这类 DOM 残留。"],
    ["璇█鍖呯己灏?Blade 棣栧睆缈昏瘧 key锛岄〉闈細鐩存帴鏄剧ず key銆?", "语言包缺少 Blade 首屏翻译 key，页面会直接显示 key。"],
    ["璇█鍖呯己灏戝墠绔?JS 闈欐€佺炕璇?key锛岄〉闈細鏄剧ず鑻辨枃鍏滃簳鎴?key銆?", "语言包缺少前台 JS 静态翻译 key，页面会显示英文兜底或 key。"],
    ["鍛藉悕鎺ュ彛濡?front_api_userDetail 涓嶈兘浣滀负鐩稿 URL 鍙戦€侊紝鍚﹀垯浼氳姹傚埌 /front/agent/front_api_userDetail銆?", "命名接口如 front_api_userDetail 不能作为相对 URL 发送，否则会请求到 /front/agent/front_api_userDetail。"],
];

$changed = 0;
foreach ($replacements as [$old, $new]) {
    if (strpos($c, $old) !== false) {
        $c = str_replace($old, $new, $c);
        $changed++;
    } else {
        echo "NOT FOUND: " . mb_substr($old, 0, 30) . "\n";
    }
}
file_put_contents($f, $c);
echo "FrontUiRegressionTest 修复: $changed 处\n";
