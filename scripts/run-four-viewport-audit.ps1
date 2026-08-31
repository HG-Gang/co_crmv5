# 四视口浏览器验收脚本（Phase 2 Task 10）
#
# 文件功能：
# - 在本机以隔离测试库启动 Laravel 服务（127.0.0.1:8098，APP_ENV=testing、DB_DATABASE=co_crmv5_test、MT4_ENABLED=false）。
# - 使用本机 Edge/Chrome 的无头截图能力，对四套 UI 家族登录页执行 1440x900 / 1280x720 / 768x1024 / 390x844 四视口截图。
# - 截图输出到 docs/audits/four-viewport-<日期>/，供 Phase 2 Task 10 视觉验收归档。
#
# 安全边界：
# - 只连接 co_crmv5_test 测试库，不写正式库。
# - 无头 CLI 截图无法覆盖需登录态的 Dashboard 与交互（菜单/焦点/触控），这些仍需人工或 Playwright 授权后补充；
#   本脚本结果不得被表述为完整四视口验收通过，只作为公开页面视觉冒烟证据。
#
# 注意：本文件必须保存为 UTF-8 with BOM，否则 Windows PowerShell 5.1 会按 ANSI 解析中文注释导致语法错误。

param(
    [int]$Port = 8098,
    [string[]]$Paths = @('/admin/login', '/front/login', '/admin-crmui/login', '/front-crmui/login'),
    [string[]]$Viewports = @('1440x900', '1280x720', '768x1024', '390x844')
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

# 1. 定位无头浏览器。
$browserCandidates = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe"
)
$browser = $browserCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $browser) {
    Write-Error '未找到 Edge 或 Chrome，请安装后重试。'
    exit 1
}
Write-Output "浏览器：$browser"

# 2. 准备隔离测试库并启动服务。
php scripts\prepare-test-database.php
if ($LASTEXITCODE -ne 0) { Write-Error '测试库准备失败。'; exit 1 }

$env:APP_ENV = 'testing'
$env:DB_DATABASE = 'co_crmv5_test'
$env:MT4_ENABLED = 'false'
$server = Start-Process -FilePath 'php' -ArgumentList 'artisan', 'serve', "--host=127.0.0.1", "--port=$Port" -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru

function Stop-ServerProcessTree {
    # artisan serve 会派生 PHP worker 子进程；必须清理整棵进程树，避免孤儿进程继续占用端口。
    if ($server -and -not $server.HasExited) {
        & taskkill.exe /PID $server.Id /T /F 2>$null | Out-Null
    }
}

try {
    # 3. 等待服务就绪。
    $ready = $false
    foreach ($i in 1..20) {
        Start-Sleep -Seconds 1
        try {
            $probe = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/admin/login" -UseBasicParsing -TimeoutSec 5
            if ($probe.StatusCode -eq 200) { $ready = $true; break }
        } catch { }
    }
    if (-not $ready) { Write-Error '隔离服务未在 20 秒内就绪。'; exit 1 }
    Write-Output "隔离服务就绪：http://127.0.0.1:$Port"

    # 4. 四视口 × 页面 截图。
    $outDir = Join-Path $projectRoot ("docs\audits\four-viewport-" + (Get-Date -Format 'yyyyMMdd-HHmmss'))
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null

    foreach ($viewport in $Viewports) {
        foreach ($path in $Paths) {
            $slug = ($path -replace '^/', '' -replace '/', '-')
            $file = Join-Path $outDir ("{0}__{1}.png" -f $viewport, $slug)
            # 无头浏览器会向 stderr 输出无害噪音（如 LoadEnclaveImageW 577），
            # 临时降级 ErrorActionPreference 防止 NativeCommandError 提前终止整个脚本。
            $previousPreference = $ErrorActionPreference
            $ErrorActionPreference = 'Continue'
            try {
                & $browser --headless=new --disable-gpu --hide-scrollbars `
                    "--window-size=$viewport" "--screenshot=$file" `
                    "--virtual-time-budget=10000" "http://127.0.0.1:$Port$path" 2>$null | Out-Null
            } finally {
                $ErrorActionPreference = $previousPreference
            }
            if ((Test-Path $file) -and ((Get-Item $file).Length -gt 10KB)) {
                Write-Output ("OK  " + (Get-Item $file).Name + "  " + [math]::Round((Get-Item $file).Length / 1KB) + " KB")
            } else {
                Write-Warning ("FAIL " + $viewport + ' ' + $path)
            }
        }
    }

    Write-Output ("截图目录：" + $outDir)
    Write-Output '注意：本脚本仅产生公开页面视觉冒烟证据；登录态页面、控制台错误、交互与触控验收仍需按 Phase 2 Task 10 清单人工/授权执行。'
}
finally {
    Stop-ServerProcessTree
    Write-Output '隔离服务已停止（含 worker 子进程）。'
}
