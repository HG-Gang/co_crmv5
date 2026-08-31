<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 23:51
 */

/**
 * 项目根目录杂项文件归档脚本（一次性）。
 *
 * 功能：将根目录的日志/报告/脚本/数据快照/构建产物按功能移动到 docs/archives/ 下
 *       6 个分类目录，并为每个目录写入 README.md 说明用途。
 *
 * 说明：不移动任何代码文件（app/routes/config/resources/tests/public/database/scripts 等），
 *       不移动 Laravel 根文件（artisan/composer/phpunit.xml/.env 等）。
 */

$root = __DIR__ . '/..';
$archives = $root . '/docs/archives';
$root = realpath($root);

// 分类定义：目录名 => 文件清单（支持 glob 模式与目录）
$categories = [
    '01-迁移与重构报告' => [
        'files' => [
            'Controller迁移100%完成报告.md', 'Controller迁移完成报告.md',
            'Controller迁移最终总结报告.md', 'Controller迁移最终进度报告.md',
            'Controller迁移进度报告.md', 'REFACTOR_ANALYSIS_2026-05-14.md',
            'REFACTOR_REPORT.md', '新旧项目迁移总结文档.md', '业务逻辑对比分析.md',
            '项目Controller对比清单.md', '数据来源验证报告.md', '_exploration_report.md',
            '_old_project_report.md', '_old_registration_logic.md', '_old_source_dump.md',
        ],
        'desc' => '旧项目迁移、Controller 迁移进度与重构分析报告，以及旧项目源码逻辑提取快照。',
    ],
    '02-需求与设计文档' => [
        'files' => [
            'CRM权限系统实施文档.md', 'CRM权限系统技术方案.md', '权限系统设计方案.md',
            'UI改进建议.md', 'co需求文档-260330.txt', 'PRODUCT.md', 'FRONTEND_CHANGES_LIST.md',
            '未完成功能清单.md', 'WEB_DEBUG_PAGES_ANALYSIS_2026-05-14.md',
            'AZURE_AND_EVENTS_REFACTOR_2026-05-15.md', 'CODE_COMMENTARY_2026-05-14.md',
            'FUNCTION_CALL_AND_INTERRUPT_REFACTOR_2026-05-15.md',
            'GATEWAY_EVENTS_MIGRATION_2026-05-14.md', 'OPENAI_RESPONSES_AZURE_PLAN_2026-05-15.md',
            'PROJECT_COMPLETION_SUMMARY.md', 'PROJECT_COMPLIANCE_CHECK_2026-05-15.md',
            'QUICK_START_GUIDE.md', 'SETUP.md', 'DATABASE_CONFIG_GUIDE.md',
            'DATABASE_MIGRATION_GUIDE.md', '安装说明.md', '技能安装完成总结.md',
            'TEST_ACCOUNTS.md', '今日工作完成进度报告.md', '最终完成进度报告.md',
            '项目工作总结.md', '20260327155339_change.md', '20260330232231_chang.md',
        ],
        'dirs' => ['design-system', 'register_logic'],
        'desc' => '需求文档、技术方案、设计指南、历史进度总结与设计系统/注册逻辑设计资料。',
    ],
    '03-测试运行日志与过程数据' => [
        'files' => [
            '_batch2_verify.log', '_cleanup.log', '_fullrun_20260801.log',
            '_fullrun_20260801_2.err.log', '_fullrun_20260801_3.log', '_fullrun_final.log',
            '_fullrun_verify.log', '_gap_report_latest.txt', '_killed_files.txt',
            '_killed_remaining.txt', '_list_tests.txt', '_migrate_verify.log',
            '_perfile_results.csv', '_phpunit.result.cache.bak-20260801', '_phpunit_full.log',
            '_phpunit_run.err.log', '_phpunit_run.log', '_probe.log', '_route_list_check.json',
            '_seed_debug.log', '_seed_verify.log', '_unit_run.log', '_verify_new_tests.log',
            '_verify_new_tests_h1.log', '_verify_orderrisk.log',
        ],
        'globs' => ['_batch_*.txt', '_shard_*.log', '_s_4b2*.log', '_v_*.log', '_v_saga*.log', '_v_trademt4.log', '_v_wd*.log'],
        'desc' => '历次全量回归/分片执行/单测验证的运行日志、过程数据与测试清单（仅供追溯，不再参与运行）。',
    ],
    '04-临时诊断与工具脚本' => [
        'files' => [
            '_check_cols.php', '_check_db.php', '_check_locks.php', '_check_schema.php',
            '_check_testdb.php', '_create_db.php', '_diag.php', '_download_assets.php',
            '_exec_sql.php', '_extract_old_data.php', '_seed.php', '_seed_menus.php',
            '_test_db.php', '_test_full.php', '_test_login.php', '_test_page.php',
            '_test_userid_login.php', 'check_migration_status.php',
        ],
        'desc' => '开发期间的数据库/迁移/种子/登录诊断脚本（一次性用途，归档保留）。',
    ],
    '05-历史数据快照' => [
        'files' => [
            'admin_agent_bindings-dev.sql', 'admin_login_logs-dev.sql', 'admin_login_logs.sql',
        ],
        'desc' => '开发库数据导出快照（权限绑定/管理员登录日志），仅供数据追溯，不参与运行。',
    ],
    '06-构建产物与本地工具' => [
        'files' => [
            'server.exe', 'server.exe~', 'server_debug.exe', 'server_debug.exe~',
            'proxy-toggle.bat', 'sf_proc_00.err', 'sf_proc_00.err.lock',
            'sf_proc_00.out', 'sf_proc_00.out.lock',
        ],
        'desc' => '本地开发用构建产物（server 调试版）、代理切换脚本与旧进程输出文件。',
    ],
];

$moved = [];
$missing = [];
$errors = [];

foreach ($categories as $cat => $conf) {
    $dir = $archives . '/' . $cat;
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    // 移动单文件
    foreach ($conf['files'] as $name) {
        $src = $root . '/' . $name;
        if (! file_exists($src)) {
            $missing[] = $name;
            continue;
        }
        $dst = $dir . '/' . $name;
        if (file_exists($dst)) {
            $errors[] = "目标已存在: $name";
            continue;
        }
        if (! rename($src, $dst)) {
            $errors[] = "移动失败: $name";
            continue;
        }
        $moved[] = "$cat/$name";
    }

    // 移动 glob 文件
    foreach ($conf['globs'] ?? [] as $glob) {
        foreach (glob($root . '/' . $glob) as $src) {
            $name = basename($src);
            $dst = $dir . '/' . $name;
            if (file_exists($dst)) {
                $errors[] = "目标已存在: $name";
                continue;
            }
            rename($src, $dst);
            $moved[] = "$cat/$name";
        }
    }

    // 移动整目录
    foreach ($conf['dirs'] ?? [] as $d) {
        $src = $root . '/' . $d;
        if (! is_dir($src)) {
            $missing[] = $d . '/';
            continue;
        }
        $dst = $dir . '/' . $d;
        if (file_exists($dst)) {
            $errors[] = "目标已存在: $d/";
            continue;
        }
        rename($src, $dst);
        $moved[] = "$cat/$d/";
    }

    // 写入目录 README
    $readme = "# {$cat}\n\n## 用途\n\n{$conf['desc']}\n\n## 内容清单\n\n";
    $items = glob($dir . '/*');
    sort($items);
    foreach ($items as $item) {
        $readme .= "- " . basename($item) . ($item[strlen($item) - 1] === DIRECTORY_SEPARATOR ? '/' : '') . "\n";
    }
    $readme .= "\n> 本目录由 2026-08-01 根目录整理归档脚本生成；内容仅供追溯，不参与项目运行。\n";
    file_put_contents($dir . '/README.md', $readme);
}

echo "移动成功: " . count($moved) . "\n";
foreach ($moved as $m) echo "  + $m\n";
echo "未找到(跳过): " . count($missing) . "\n";
foreach ($missing as $m) echo "  - $m\n";
echo "错误: " . count($errors) . "\n";
foreach ($errors as $e) echo "  ! $e\n";
