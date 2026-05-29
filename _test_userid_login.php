<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$req = Illuminate\Http\Request::create('/api/front/login', 'POST', ['user_id'=>'1001','password'=>'agent123']);
$req->headers->set('Accept','application/json');
$resp = $kernel->handle($req);
$data = json_decode($resp->getContent(), true);
echo "UserID login: code=" . $data['code'] . " msg=" . $data['message'] . "\n";
if ($data['code'] == 1000) echo "Token OK: " . substr($data['data']['access_token'],0,30) . "...\n";
