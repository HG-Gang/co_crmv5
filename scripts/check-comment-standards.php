<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 10:30
 */

/**
 * 中文注释标准验证脚本
 *
 * 文件功能：
 * - 检查所有PHP文件是否符合中文注释标准 v0.0.3
 * - 验证文件头部标识块、类属性注释、方法注释完整性
 *
 * 使用方法：
 * php scripts/check-comment-standards.php [目录路径]
 *
 * 示例：
 * php scripts/check-comment-standards.php app/Http/Controllers
 */

/**
 * 验证规则（基于 docs/中文注释标准-v0.0.3.md）
 *
 * 1. 文件头部标识块（必须）
 * 2. 文件级注释（必须）
 * 3. 类属性和常量注释（必须包含中文说明）
 * 4. 方法注释（必须包含功能说明）
 * 5. 复杂逻辑内部注释（推荐）
 */

class CommentStandardChecker
{
    /** @var array 检查结果统计 */
    private $stats = [
        'total_files' => 0,           // 总文件数
        'passed_files' => 0,          // 通过文件数
        'failed_files' => 0,          // 失败文件数
        'missing_header' => 0,        // 缺少头部标识块
        'missing_file_doc' => 0,      // 缺少文件级注释
        'missing_property_doc' => 0,  // 缺少属性注释
        'missing_method_doc' => 0,    // 缺少方法注释
    ];

    /** @var array 问题详情列表 */
    private $issues = [];

    /**
     * 执行检查
     *
     * @param string $directory 要检查的目录路径
     * @return void
     */
    public function check($directory)
    {
        echo "====================================\n";
        echo "中文注释标准验证 v0.0.3\n";
        echo "====================================\n";
        echo "检查目录: {$directory}\n\n";

        $files = $this->getPhpFiles($directory);
        $this->stats['total_files'] = count($files);

        echo "找到 {$this->stats['total_files']} 个PHP文件\n\n";

        foreach ($files as $file) {
            $this->checkFile($file);
        }

        $this->printReport();
    }

    /**
     * 获取目录下所有PHP文件
     *
     * @param string $directory 目录路径
     * @return array 文件路径数组
     */
    private function getPhpFiles($directory)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * 检查单个文件
     *
     * @param string $filePath 文件路径
     * @return void
     */
    private function checkFile($filePath)
    {
        $content = file_get_contents($filePath);
        $relativePath = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $filePath);

        $hasIssue = false;

        // 1. 检查文件头部标识块
        if (!$this->hasHeaderBlock($content)) {
            $this->addIssue($relativePath, '缺少文件头部标识块（Created by PhpStorm）');
            $this->stats['missing_header']++;
            $hasIssue = true;
        }

        // 2. 检查文件级注释
        if (!$this->hasFileDocBlock($content)) {
            $this->addIssue($relativePath, '缺少文件级功能说明注释');
            $this->stats['missing_file_doc']++;
            $hasIssue = true;
        }

        // 3. 检查类属性注释（必须包含中文）
        $propertyIssues = $this->checkProperties($content);
        if (!empty($propertyIssues)) {
            foreach ($propertyIssues as $issue) {
                $this->addIssue($relativePath, $issue);
            }
            $this->stats['missing_property_doc'] += count($propertyIssues);
            $hasIssue = true;
        }

        // 4. 检查方法注释
        $methodIssues = $this->checkMethods($content);
        if (!empty($methodIssues)) {
            foreach ($methodIssues as $issue) {
                $this->addIssue($relativePath, $issue);
            }
            $this->stats['missing_method_doc'] += count($methodIssues);
            $hasIssue = true;
        }

        if ($hasIssue) {
            $this->stats['failed_files']++;
        } else {
            $this->stats['passed_files']++;
        }
    }

    /**
     * 检查是否有文件头部标识块
     *
     * @param string $content 文件内容
     * @return bool
     */
    private function hasHeaderBlock($content)
    {
        // 匹配 "Created by PhpStorm" 标识
        return preg_match('/\/\*\*\s*\*\s*Created by PhpStorm\./s', $content) === 1;
    }

    /**
     * 检查是否有文件级注释
     *
     * @param string $content 文件内容
     * @return bool
     */
    private function hasFileDocBlock($content)
    {
        // 匹配包含"文件功能"的注释块
        // 使用 .*? 非贪婪匹配任意内容，直到遇到 */ 结束符
        return preg_match('/\/\*\*.*?文件功能[：:]/su', $content) === 1;
    }

    /**
     * 检查类属性注释
     *
     * @param string $content 文件内容
     * @return array 问题列表
     */
    private function checkProperties($content)
    {
        $issues = [];

        // 匹配 public/protected/private 属性
        preg_match_all(
            '/(public|protected|private)\s+(static\s+)?\$(\w+)/m',
            $content,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $match) {
            $propertyName = $match[3][0];
            $position = $match[0][1];

            // 检查属性上方是否有包含中文的注释（回溯 800 字节以支持长注释，并向前调整以避免 UTF-8 截断）
            $startPos = max(0, $position - 800);
            $beforeContent = substr($content, $startPos, $position - $startPos);

            // 如果回溯内容以无效 UTF-8 字节开头，向前调整起始位置
            while ($startPos > 0 && !mb_check_encoding($beforeContent, 'UTF-8')) {
                $startPos = max(0, $startPos - 1);
                $beforeContent = substr($content, $startPos, $position - $startPos);
                // 最多向前调整 10 字节
                if ($position - $startPos > 810) {
                    break;
                }
            }

            // 匹配 /** ... */ 或 // 注释，且包含中文字符
            // 使用 .*? 非贪婪匹配任意内容（包括 /）
            if (!preg_match('/\/\*\*.*?[\x{4e00}-\x{9fa5}]/su', $beforeContent) &&
                !preg_match('/\/\/.*[\x{4e00}-\x{9fa5}]/u', $beforeContent)) {
                $issues[] = "属性 \${$propertyName} 缺少中文注释";
            }
        }

        return $issues;
    }

    /**
     * 检查方法注释
     *
     * @param string $content 文件内容
     * @return array 问题列表
     */
    private function checkMethods($content)
    {
        $issues = [];

        // 匹配 public/protected/private 方法
        preg_match_all(
            '/(public|protected|private)\s+(static\s+)?function\s+(\w+)\s*\(/m',
            $content,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $match) {
            $methodName = $match[3][0];
            $position = $match[0][1];

            // 跳过魔术方法（__construct, __toString等）的严格检查
            if (strpos($methodName, '__') === 0) {
                continue;
            }

            // 检查方法上方是否有文档注释（回溯 15000 字符以支持超长注释）
            $beforeContent = substr($content, max(0, $position - 15000), 15000);

            if (!preg_match('/\/\*\*.*?\*\//s', $beforeContent)) {
                $issues[] = "方法 {$methodName}() 缺少文档注释";
            }
        }

        return $issues;
    }

    /**
     * 添加问题记录
     *
     * @param string $file 文件路径
     * @param string $issue 问题描述
     * @return void
     */
    private function addIssue($file, $issue)
    {
        if (!isset($this->issues[$file])) {
            $this->issues[$file] = [];
        }
        $this->issues[$file][] = $issue;
    }

    /**
     * 打印检查报告
     *
     * @return void
     */
    private function printReport()
    {
        echo "\n====================================\n";
        echo "检查结果统计\n";
        echo "====================================\n";
        echo "总文件数: {$this->stats['total_files']}\n";
        echo "✅ 通过: {$this->stats['passed_files']}\n";
        echo "❌ 失败: {$this->stats['failed_files']}\n";
        echo "\n";
        echo "问题分类:\n";
        echo "  - 缺少头部标识块: {$this->stats['missing_header']}\n";
        echo "  - 缺少文件级注释: {$this->stats['missing_file_doc']}\n";
        echo "  - 缺少属性注释: {$this->stats['missing_property_doc']}\n";
        echo "  - 缺少方法注释: {$this->stats['missing_method_doc']}\n";

        if (!empty($this->issues)) {
            echo "\n====================================\n";
            echo "问题详情\n";
            echo "====================================\n";

            foreach ($this->issues as $file => $fileIssues) {
                echo "\n📄 {$file}\n";
                foreach ($fileIssues as $issue) {
                    echo "   ❌ {$issue}\n";
                }
            }
        }

        echo "\n====================================\n";
        if ($this->stats['failed_files'] === 0) {
            echo "✅ 所有文件都符合中文注释标准！\n";
        } else {
            echo "❌ 发现 {$this->stats['failed_files']} 个文件不符合标准\n";
            echo "请根据上述问题详情进行修复\n";
        }
        echo "====================================\n";
    }
}

// 执行检查
$directory = $argv[1] ?? 'app/Http/Controllers';
$basePath = dirname(__DIR__);
$fullPath = $basePath . DIRECTORY_SEPARATOR . $directory;

if (!is_dir($fullPath)) {
    echo "错误: 目录不存在: {$fullPath}\n";
    exit(1);
}

$checker = new CommentStandardChecker();
$checker->check($fullPath);
