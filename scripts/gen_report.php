<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:29
 */

/**
 * 报告占位初始化脚本（一次性）。
 *
 * 脚本用途：
 * - 创建/清空 docs/superpowers/reports/新旧项目全量业务逻辑执行链路详解.md 占位文件，
 *   供后续生成脚本写入完整链路报告。
 *
 * 运行方式：
 * - php scripts/gen_report.php（在项目根目录执行，输出 done 表示成功）。
 */

$fp = fopen(__DIR__."/docs/superpowers/reports/新旧项目全量业务逻辑执行链路详解.md", "wb");
fclose($fp);
echo "done";

