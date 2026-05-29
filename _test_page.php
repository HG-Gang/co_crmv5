<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Test front login page
$req = Illuminate\Http\Request::create('/front/login', 'GET');
$resp = $kernel->handle($req);
echo "Front login page: HTTP " . $resp->getStatusCode() . "\n";
if ($resp->getStatusCode() !== 200) {
    $content = $resp->getContent();
    // Extract error message
    if (strpos($content, '"message"') !== false) {
        $json = json_decode($content, true);
        echo "Error: " . ($json['message'] ?? substr($content, 0, 300)) . "\n";
    } else {
        echo "Error: " . substr($content, 0, 500) . "\n";
    }
}

// Test admin login page
$app2 = require __DIR__.'/bootstrap/app.php';
$kernel2 = $app2->make(Illuminate\Contracts\Http\Kernel::class);
$req2 = Illuminate\Http\Request::create('/admin/login', 'GET');
$resp2 = $kernel2->handle($req2);
echo "\nAdmin login page: HTTP " . $resp2->getStatusCode() . "\n";
if ($resp2->getStatusCode() !== 200) {
    $content = $resp2->getContent();
    if (strpos($content, '"message"') !== false) {
        $json = json_decode($content, true);
        echo "Error: " . ($json['message'] ?? substr($content, 0, 300)) . "\n";
    } else {
        echo "Error: " . substr($content, 0, 500) . "\n";
    }
}
