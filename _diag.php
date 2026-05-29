<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=co_crmv5;charset=utf8mb4', 'root', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ADMINS ===\n";
    $s = $pdo->query("SELECT id,username,password,status,last_login_at FROM admins LIMIT 3");
    while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
        echo "id={$r['id']} user={$r['username']} status={$r['status']} last_login={$r['last_login_at']}\n";
        echo "  pass_len=" . strlen($r['password']) . " hash_check(password)=" . (password_verify('password', $r['password']) ? 'YES' : 'NO') . "\n";
    }

    echo "\n=== USER_LOGINS ===\n";
    $s = $pdo->query("SELECT id,user_id,email,password,is_enabled,is_cancelled,account_type FROM user_logins LIMIT 5");
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  NO DATA - table is empty!\n";
    } else {
        foreach ($rows as $r) {
            echo "id={$r['id']} uid={$r['user_id']} email={$r['email']} enabled={$r['is_enabled']} type={$r['account_type']}\n";
            echo "  hash_check(password)=" . (password_verify('password', $r['password']) ? 'YES' : 'NO') . "\n";
        }
    }

    echo "\n=== ADMINS TABLE STRUCTURE ===\n";
    $s = $pdo->query("DESCRIBE admins");
    while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$r['Field']} {$r['Type']} null={$r['Null']} default={$r['Default']}\n";
    }

    echo "\n=== USER_LOGINS TABLE STRUCTURE ===\n";
    $s = $pdo->query("DESCRIBE user_logins");
    while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$r['Field']} {$r['Type']} null={$r['Null']} default={$r['Default']}\n";
    }

    echo "\n=== MENUS COUNT ===\n";
    $s = $pdo->query("SELECT COUNT(*) as c FROM menus");
    echo "Total menus: " . $s->fetch(PDO::FETCH_ASSOC)['c'] . "\n";

    echo "\n=== JWT CONFIG ===\n";
    echo "secret from env: " . (getenv('JWT_SECRET') ?: '(not set)') . "\n";

    // Test bcrypt
    echo "\n=== BCRYPT TEST ===\n";
    $hash = password_hash('password', PASSWORD_BCRYPT);
    echo "New hash for 'password': {$hash}\n";
    echo "Verify: " . (password_verify('password', $hash) ? 'YES' : 'NO') . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
