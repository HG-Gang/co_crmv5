<?php
/**
 * 浏览器数据库诊断脚本。
 *
 * 文件功能：
 * - 检查本地数据库连接、初始化账号密码、表结构和静态资源是否可用。
 * - 以 JSON 返回诊断结果，供本地开发环境排查安装问题。
 */
header('Content-Type: application/json; charset=utf-8');

$result = ['timestamp' => date('Y-m-d H:i:s'), 'php_version' => PHP_VERSION];

// 1. DB Connection
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=co_crmv5;charset=utf8mb4', 'root', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $result['db'] = 'CONNECTED';

    // 管理员诊断只返回非敏感标识和哈希长度，不输出密码哈希原文。
    $s = $pdo->query("SELECT id,username,password,status FROM admins LIMIT 1");
    $admin = $s->fetch(PDO::FETCH_ASSOC);
    $result['admin'] = $admin ? [
        'id' => $admin['id'],
        'username' => $admin['username'],
        'status' => $admin['status'],
        'pass_len' => strlen($admin['password']),
        'verify_abc123' => password_verify('abc123', $admin['password']),
    ] : 'NO ADMIN DATA';

    // 前台账号同样只验证统一初始密码，不返回数据库中的密码哈希。
    $s = $pdo->query("SELECT id,user_id,email,password,is_enabled,is_cancelled,account_type FROM user_logins LIMIT 5");
    $users = $s->fetchAll(PDO::FETCH_ASSOC);
    $result['user_logins_count'] = count($users);
    $result['user_logins'] = [];
    foreach ($users as $u) {
        $result['user_logins'][] = [
            'id' => $u['id'], 'user_id' => $u['user_id'], 'email' => $u['email'],
            'is_enabled' => $u['is_enabled'], 'account_type' => $u['account_type'],
            'verify_abc123' => password_verify('abc123', $u['password']),
        ];
    }

    // Table structures
    $s = $pdo->query("DESCRIBE admins");
    $result['admins_columns'] = [];
    while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
        $result['admins_columns'][] = $r['Field'] . ' ' . $r['Type'];
    }

    $s = $pdo->query("DESCRIBE user_logins");
    $result['user_logins_columns'] = [];
    while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
        $result['user_logins_columns'][] = $r['Field'] . ' ' . $r['Type'];
    }

    // Menus
    $s = $pdo->query("SELECT COUNT(*) as c FROM menus");
    $result['menus_count'] = $s->fetch(PDO::FETCH_ASSOC)['c'];

    // Check last_login_at column type
    $s = $pdo->query("SHOW COLUMNS FROM admins LIKE 'last_login_at'");
    $col = $s->fetch(PDO::FETCH_ASSOC);
    $result['last_login_at_type'] = $col ? $col['Type'] : 'NOT FOUND';

} catch (PDOException $e) {
    $result['db'] = 'FAILED: ' . $e->getMessage();
}

// 2. Config check
$result['env_db_port'] = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 'NOT SET';
$result['env_jwt_secret'] = (($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET')) ? 'SET' : 'NOT SET');

// 3. File check
$result['files'] = [
    'jwt_config' => file_exists(__DIR__ . '/../config/jwt.php'),
    'auth_config' => file_exists(__DIR__ . '/../config/auth.php'),
    'admin_auth_ctrl' => file_exists(__DIR__ . '/../app/Http/Controllers/Admin/AuthController.php'),
    'front_auth_ctrl' => file_exists(__DIR__ . '/../app/Http/Controllers/Front/AuthController.php'),
    'jwt_service' => file_exists(__DIR__ . '/../app/Services/JwtService.php'),
    'layui_js' => file_exists(__DIR__ . '/js/vendor/layui-v2.13.5/layui/layui.js'),
    'i18n_js' => file_exists(__DIR__ . '/js/shared/i18n.js'),
    'ajax_js' => file_exists(__DIR__ . '/js/shared/ajax.js'),
    'lang_zh' => file_exists(__DIR__ . '/js/shared/lang/common/zh-CN.js'),
    'lang_en' => file_exists(__DIR__ . '/js/shared/lang/common/en.js'),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
