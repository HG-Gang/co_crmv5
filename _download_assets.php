<?php
$downloads = [
    'public/vendor/jquery/jquery.min.js' => 'https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js',
    'public/vendor/bootstrap/css/bootstrap.min.css' => 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css',
    'public/vendor/bootstrap/js/bootstrap.bundle.min.js' => 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js',
    'public/vendor/adminlte/css/adminlte.min.css' => 'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css',
    'public/vendor/adminlte/js/adminlte.min.js' => 'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js',
    'public/vendor/fontawesome/css/all.min.css' => 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css',
];

foreach ($downloads as $path => $url) {
    @mkdir(dirname($path), 0777, true);
    $ctx = stream_context_create(['http' => ['timeout' => 30]]);
    $content = @file_get_contents($url, false, $ctx);
    if ($content && strlen($content) > 100) {
        file_put_contents($path, $content);
        echo "OK: {$path} (" . strlen($content) . " bytes)\n";
    } else {
        echo "FAIL: {$path} - will use CDN fallback\n";
    }
}

// Copy layui from existing local path
$layuiSrc = 'public/js/common/layui-v2.13.5/layui';
if (is_dir($layuiSrc)) {
    @mkdir('public/vendor/layui', 0777, true);
    $srcJs = $layuiSrc . '/layui.js';
    $srcCss = $layuiSrc . '/css/layui.css';
    if (file_exists($srcJs)) {
        copy($srcJs, 'public/vendor/layui/layui.js');
        echo "OK: Copied layui.js\n";
    }
    if (file_exists($srcCss)) {
        @mkdir('public/vendor/layui/css', 0777, true);
        copy($srcCss, 'public/vendor/layui/css/layui.css');
        echo "OK: Copied layui.css\n";
    }
}

echo "Done.\n";
