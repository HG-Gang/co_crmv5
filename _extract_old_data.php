<?php
try {
    $old = new PDO('mysql:host=127.0.0.1;port=3307;dbname=hank_zl_data;charset=utf8mb4', 'root', '123456');
    $old->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to hank_zl_data\n";

    // List all tables
    echo "\n=== ALL TABLES ===\n";
    $tables = $old->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) echo "  {$t}\n";

    // Look for menu/role/permission tables
    echo "\n=== ROLE DATA ===\n";
    try {
        $roles = $old->query("SELECT * FROM role")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($roles as $r) {
            echo "  ID={$r['role_id']} name={$r['username']} acl=" . substr($r['acl'] ?? '', 0, 200) . "\n";
        }
    } catch (Exception $e) { echo "  No role table or error: {$e->getMessage()}\n"; }

    // Admin table
    echo "\n=== ADMIN DATA ===\n";
    try {
        $admins = $old->query("SELECT id, username, role_id, email, state FROM admin LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($admins as $a) echo "  id={$a['id']} user={$a['username']} role={$a['role_id']} state={$a['state']}\n";
    } catch (Exception $e) { echo "  Error: {$e->getMessage()}\n"; }

    // Agents group
    echo "\n=== AGENTS GROUP ===\n";
    try {
        $groups = $old->query("SELECT * FROM agents_group")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($groups as $g) echo "  id={$g['group_id']} name={$g['group_name']} voided={$g['voided']} comm={$g['agents_comm_prop']}\n";
    } catch (Exception $e) { echo "  Error: {$e->getMessage()}\n"; }

    // Agent level
    echo "\n=== AGENT LEVEL ===\n";
    try {
        $levels = $old->query("SELECT * FROM agent_level")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($levels as $l) echo "  id={$l['id']} level={$l['level_id']} name={$l['name']} max={$l['max_prop']} min={$l['min_prop']}\n";
    } catch (Exception $e) { echo "  Error: {$e->getMessage()}\n"; }

    // group_config
    echo "\n=== GROUP CONFIG ===\n";
    try {
        $gc = $old->query("SELECT * FROM group_config")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($gc as $g) echo "  id={$g['id']} name={$g['name']} cat={$g['category']} comm={$g['has_comm']} ecn={$g['is_ecn']} default={$g['is_default']}\n";
    } catch (Exception $e) { echo "  Error: {$e->getMessage()}\n"; }

    // user_group
    echo "\n=== USER GROUP ===\n";
    try {
        $ug = $old->query("SELECT * FROM user_group")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ug as $g) echo "  id={$g['user_group_id']} name={$g['user_group_name']} type={$g['group_type']} comm={$g['group_id']} ecn={$g['is_enc']}\n";
    } catch (Exception $e) { echo "  Error: {$e->getMessage()}\n"; }

    // Sample users
    echo "\n=== SAMPLE AGENTS (first 5) ===\n";
    try {
        $agents = $old->query("SELECT user_id, user_name, email, parent_id, family_tree, account_type, comm_prop, group_id, user_status FROM agents LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($agents as $a) echo "  uid={$a['user_id']} name={$a['user_name']} parent={$a['parent_id']} tree={$a['family_tree']} type={$a['account_type']} comm={$a['comm_prop']}\n";
    } catch (Exception $e) { echo "  Error: {$e->getMessage()}\n"; }

    echo "\n=== SAMPLE USERS (first 5) ===\n";
    try {
        $users = $old->query("SELECT user_id, user_name, email, parent_id, family_tree, account_type, comm_prop, group_id FROM user LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $u) echo "  uid={$u['user_id']} name={$u['user_name']} parent={$u['parent_id']} tree={$u['family_tree']} type={$u['account_type']}\n";
    } catch (Exception $e) { echo "  Error: {$e->getMessage()}\n"; }

    // System config
    echo "\n=== SYSTEM CONFIG ===\n";
    try {
        $sc = $old->query("SELECT * FROM system_config LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);
        if ($sc) { foreach ($sc[0] as $k => $v) echo "  {$k}={$v}\n"; }
    } catch (Exception $e) { echo "  Error: {$e->getMessage()}\n"; }

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
