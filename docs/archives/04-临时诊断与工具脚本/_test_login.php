<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Test front login
$req = Illuminate\Http\Request::create('/api/front/login', 'POST', [
    'email' => 'agent@test.com',
    'password' => 'agent123'
]);
$req->headers->set('Accept', 'application/json');
$resp = $kernel->handle($req);
echo "=== Front Login ===\n";
echo $resp->getContent() . "\n\n";

// Test admin login
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$req2 = Illuminate\Http\Request::create('/api/admin/login', 'POST', [
    'username' => 'admin',
    'password' => 'admin123'
]);
$req2->headers->set('Accept', 'application/json');
$resp2 = $kernel->handle($req2);
echo "=== Admin Login ===\n";
echo $resp2->getContent() . "\n";
