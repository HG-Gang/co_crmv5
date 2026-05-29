<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// 1. Front login
$req = Illuminate\Http\Request::create('/api/front/login', 'POST', ['email'=>'agent@test.com','password'=>'agent123']);
$req->headers->set('Accept','application/json');
$resp = $kernel->handle($req);
$data = json_decode($resp->getContent(), true);
echo "1. Front login: code=" . $data['code'] . "\n";
$token = $data['data']['access_token'] ?? '';
echo "   Token: " . substr($token, 0, 50) . "...\n";

// 2. Front menus with token
$app2 = require __DIR__.'/bootstrap/app.php';
$kernel2 = $app2->make(Illuminate\Contracts\Http\Kernel::class);
$req2 = Illuminate\Http\Request::create('/api/front/menus', 'POST');
$req2->headers->set('Accept','application/json');
$req2->headers->set('Authorization','Bearer '.$token);
$resp2 = $kernel2->handle($req2);
$data2 = json_decode($resp2->getContent(), true);
echo "\n2. Front menus: code=" . $data2['code'] . "\n";
if ($data2['code'] == 1000) {
    $menus = $data2['data']['menus'] ?? $data2['data'];
    if (is_array($menus)) {
        echo "   Menu count: " . count($menus) . "\n";
        foreach ($menus as $m) {
            echo "   - " . ($m['name'] ?? $m['slug'] ?? '?') . " (children: " . count($m['children'] ?? []) . ")\n";
        }
    }
} else {
    echo "   Error: " . ($data2['message'] ?? 'unknown') . "\n";
}

// 3. Admin login
$app3 = require __DIR__.'/bootstrap/app.php';
$kernel3 = $app3->make(Illuminate\Contracts\Http\Kernel::class);
$req3 = Illuminate\Http\Request::create('/api/admin/login', 'POST', ['username'=>'admin','password'=>'admin123']);
$req3->headers->set('Accept','application/json');
$resp3 = $kernel3->handle($req3);
$data3 = json_decode($resp3->getContent(), true);
echo "\n3. Admin login: code=" . $data3['code'] . "\n";
$adminToken = $data3['data']['access_token'] ?? '';

// 4. Admin menus
$app4 = require __DIR__.'/bootstrap/app.php';
$kernel4 = $app4->make(Illuminate\Contracts\Http\Kernel::class);
$req4 = Illuminate\Http\Request::create('/api/admin/menus', 'POST');
$req4->headers->set('Accept','application/json');
$req4->headers->set('Authorization','Bearer '.$adminToken);
$resp4 = $kernel4->handle($req4);
$data4 = json_decode($resp4->getContent(), true);
echo "\n4. Admin menus: code=" . $data4['code'] . "\n";
if ($data4['code'] == 1000) {
    $menus = $data4['data']['menus'] ?? $data4['data'];
    if (is_array($menus)) {
        echo "   Menu count: " . count($menus) . "\n";
        foreach ($menus as $m) {
            echo "   - " . ($m['name'] ?? $m['slug'] ?? '?') . " (children: " . count($m['children'] ?? []) . ")\n";
        }
    }
} else {
    echo "   Error: " . ($data4['message'] ?? 'unknown') . "\n";
}

// 5. Front profile
$app5 = require __DIR__.'/bootstrap/app.php';
$kernel5 = $app5->make(Illuminate\Contracts\Http\Kernel::class);
$req5 = Illuminate\Http\Request::create('/api/front/profileInfo', 'POST');
$req5->headers->set('Accept','application/json');
$req5->headers->set('Authorization','Bearer '.$token);
$resp5 = $kernel5->handle($req5);
$data5 = json_decode($resp5->getContent(), true);
echo "\n5. Front profile: code=" . $data5['code'] . "\n";
if ($data5['code'] == 1000) {
    $info = $data5['data']['info'] ?? $data5['data'];
    echo "   user_name: " . ($info['user_name'] ?? '?') . "\n";
    echo "   user_id: " . ($info['user_id'] ?? '?') . "\n";
}

echo "\n=== All tests completed ===\n";
