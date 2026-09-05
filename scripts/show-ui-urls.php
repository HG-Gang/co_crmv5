<?php

// 快速生成UI访问URL测试脚本

echo "====================================\n";
echo "四套UI系统访问地址\n";
echo "====================================\n\n";

$baseUrl = 'http://localhost:8000';

echo "【一、管理后台 - Layui版】\n";
echo "登录页：{$baseUrl}/admin/layui/login\n";
echo "测试账号：admin / abc123\n\n";

echo "【二、管理后台 - CRMUI版】\n";
echo "登录页：{$baseUrl}/admin/crmui/login\n";
echo "测试账号：admin / abc123\n\n";

echo "【三、前台代理 - Layui版】\n";
echo "登录页：{$baseUrl}/front/layui/login\n";
echo "测试账号：info@gmtkg.com / 123456\n";
echo "（user_id=1001，有23条交易记录，余额74.80）\n\n";

echo "【四、前台代理 - CRMUI版】\n";
echo "登录页：{$baseUrl}/front/crmui/login\n";
echo "测试账号：info@gmtkg.com / 123456\n";
echo "（user_id=1001，有23条交易记录，余额74.80）\n\n";

echo "====================================\n";
echo "测试URL列表（可直接在浏览器打开）\n";
echo "====================================\n\n";

$urls = [
    'Admin Layui' => '/admin/layui/login',
    'Admin CRMUI' => '/admin/crmui/login',
    'Front Layui' => '/front/layui/login',
    'Front CRMUI' => '/front/crmui/login',
];

foreach ($urls as $name => $path) {
    echo "{$name}:\n";
    echo "{$baseUrl}{$path}\n\n";
}

echo "====================================\n";
echo "快速验证命令\n";
echo "====================================\n\n";

foreach ($urls as $name => $path) {
    $fullUrl = $baseUrl . $path;
    echo "# 测试 {$name}\n";
    echo "Invoke-WebRequest -Uri \"{$fullUrl}\" -UseBasicParsing | Select-Object StatusCode\n\n";
}
