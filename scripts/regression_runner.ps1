param(
    [string]$ResultFile = 'D:\Software\PhpProject\Demo\co_crmv5\storage\logs\regression-results.csv',
    [string]$LogDir = 'D:\Software\PhpProject\Demo\co_crmv5\storage\logs\regression',
    [string]$FilterFile = ''
)

# 回归运行器：逐文件执行 PHPUnit 并记录结果。
# 用法：powershell -ExecutionPolicy Bypass -File scripts\regression_runner.ps1
# 说明：为避免并发测试实例干扰，单文件串行执行；失败文件会在结果中标记，由第二轮复跑甄别。

$ErrorActionPreference = 'Continue'
$php = 'D:\Software\PhpProject\Demo\co_crmv5\vendor\bin\phpunit'
$root = 'D:\Software\PhpProject\Demo\co_crmv5'
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

if (-not (Test-Path $ResultFile)) {
    "file,result,tests,assertions,failures,errors,exitcode" | Out-File -FilePath $ResultFile -Encoding UTF8
}
$done = @{}
if (Test-Path $ResultFile) {
    Get-Content $ResultFile -Encoding UTF8 | Select-Object -Skip 1 | ForEach-Object {
        $parts = $_ -split ','
        if ($parts.Length -gt 0) { $done[$parts[0]] = $true }
    }
}

$files = @()
$files += Get-ChildItem "$root\tests\Feature" -Filter '*Test.php' -File | Select-Object -ExpandProperty FullName
$files += Get-ChildItem "$root\tests\Unit" -Filter '*Test.php' -File | Select-Object -ExpandProperty FullName
if ($FilterFile -ne '') {
    $allowed = @{}
    Get-Content $FilterFile -Encoding UTF8 | ForEach-Object { $allowed[$_.Trim()] = $true }
    $files = $files | Where-Object { $allowed.ContainsKey((Split-Path $_ -Leaf)) }
}

foreach ($file in $files) {
    $name = Split-Path $file -Leaf
    if ($done.ContainsKey($name)) { continue }
    $out = & php $php $file --colors=never 2>&1
    $code = $LASTEXITCODE
    $text = $out -join "`n"
    $result = 'UNKNOWN'
    $tests = 0; $assertions = 0; $failures = 0; $errors = 0
    if ($text -match 'OK \((\d+) tests?, (\d+) assertions?\)') {
        $result = 'OK'; $tests = [int]$Matches[1]; $assertions = [int]$Matches[2]
    } elseif ($text -match 'Tests:\s*(\d+),\s*Assertions:\s*(\d+),\s*Failures:\s*(\d+),\s*Errors:\s*(\d+)') {
        $result = 'FAIL'; $tests = [int]$Matches[1]; $assertions = [int]$Matches[2]; $failures = [int]$Matches[3]; $errors = [int]$Matches[4]
    } elseif ($text -match 'ERRORS!\s*Tests:\s*(\d+)') {
        $result = 'FAIL'
    } elseif ($text -match 'No tests executed') {
        $result = 'EMPTY'
    } else {
        $result = 'CRASH'
    }
    $line = "$name,$result,$tests,$assertions,$failures,$errors,$code"
    $line | Out-File -FilePath $ResultFile -Append -Encoding UTF8
    $logFile = Join-Path $LogDir ($name -replace '\.php$', '.log')
    $text | Out-File -FilePath $logFile -Encoding UTF8
    Write-Host "[$result] $name ($tests tests, $failures failures, $errors errors)"
}

Write-Host 'REGRESSION_RUNNER_DONE'
