<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 15:00
 */

/**
 * 批量为属性添加中文注释
 *
 * 文件功能：
 * - 扫描控制器文件，为缺少中文注释的常见服务属性自动添加注释
 * - 识别常见的服务注入属性并添加标准注释
 */

// 常见属性的中文注释映射
$propertyComments = [
    'adminDataScopeService' => '后台数据范围服务',
    'mt4Manager' => 'MT4 管理器',
    'adminAuthReviewProcessor' => '后台认证审核处理器',
    'jwtService' => 'JWT 令牌服务',
    'depositSettlementGateway' => '入金结算网关',
    'depositRefundGateway' => '入金退款网关',
    'creditSettlementGateway' => '信用额度结算网关',
    'cancelApplyQueryService' => '销户申请查询服务',
    'commissionTransferReconciliationService' => '佣金转账对账服务',
    'menuService' => '菜单服务',
    'indexedRebateColumnsAvailable' => '可用的返佣列索引',
    'legacyRiskQueryService' => '旧版风控查询服务',
    'withdrawRecordQueryService' => '出金记录查询服务',
    'commissionTransferService' => '佣金转账服务',
    'registrationService' => '注册服务',
    'passwordService' => '密码服务',
    'commissionService' => '佣金服务',
    'familyTreeService' => '家族树服务',
];

$directory = $argv[1] ?? 'app/Http/Controllers';
$baseDir = dirname(__DIR__);
$targetDir = $baseDir . DIRECTORY_SEPARATOR . $directory;

$fixedCount = 0;
$processedFiles = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($targetDir)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $filePath = $file->getPathname();
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $fileFixed = false;

    foreach ($propertyComments as $propertyName => $comment) {
        // 查找没有中文注释的属性定义
        // 匹配模式：属性声明前200字符内没有中文字符
        $pattern = '/([\s\S]{0,200}?)((?:public|protected|private)\s+(?:static\s+)?\$' . preg_quote($propertyName, '/') . '(?:\s|;))/';

        if (preg_match($pattern, $content, $matches)) {
            $beforeProperty = $matches[1];
            $propertyDeclaration = $matches[2];

            // 检查前面是否已有中文注释
            if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $beforeProperty)) {
                // 找到属性声明的位置
                $propertyPos = strpos($content, $propertyDeclaration, max(0, strlen($beforeProperty) - 200));

                if ($propertyPos !== false) {
                    // 获取属性声明前的内容，找到合适的插入位置
                    $beforeText = substr($content, 0, $propertyPos);

                    // 查找属性前最近的注释或类定义
                    if (preg_match('/(\n\s*)$/', $beforeText, $spaceMatch)) {
                        $indent = $spaceMatch[1];
                    } else {
                        $indent = "\n    ";
                    }

                    // 构造注释
                    $newComment = "{$indent}/**\n{$indent} * {$comment}\n{$indent} *\n{$indent} * @var mixed\n{$indent} */\n";

                    // 插入注释
                    $content = substr_replace($content, $newComment, $propertyPos, 0);
                    $fileFixed = true;
                }
            }
        }
    }

    if ($fileFixed && $content !== $originalContent) {
        file_put_contents($filePath, $content);
        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
        echo "✅ 已修复: {$relativePath}\n";
        $fixedCount++;
        $processedFiles[] = $relativePath;
    }
}

echo "\n====================================\n";
echo "修复完成\n";
echo "====================================\n";
echo "已修复: {$fixedCount} 个文件\n";
echo "\n已处理的文件：\n";
foreach ($processedFiles as $file) {
    echo "  - {$file}\n";
}
