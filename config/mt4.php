<?php
return [
    'host' => env('MT4_HOST', '127.0.0.1'),
    'port' => env('MT4_PORT', 3490),
    'api_key' => env('MT4_API_KEY', ''),
    'api_version' => env('MT4_API_VERSION', '000005'),
    'timeout' => env('MT4_TIMEOUT', 10),
    'enabled' => env('MT4_ENABLED', false),
];
