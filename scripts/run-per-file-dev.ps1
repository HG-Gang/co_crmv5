# Per-file serial full suite runner (original php.exe).
# Purpose: run every Unit/Feature test file one by one so each process stays
# short and survives external process reaping; resume from CSV; retry crashes.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\run-per-file-dev.ps1 [-Database co_crmv5_qa]
param(
    [string]$Database = 'co_crmv5'
)
$ErrorActionPreference = 'Continue'
$php = 'D:\Ruanjian\phpStudy_64\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe'
$ini = 'D:\Ruanjian\phpStudy_64\phpstudy_pro\Extensions\php\php8.0.2nts\php.ini'
$phpunit = 'D:\Software\PhpProject\Demo\co_crmv5\vendor\bin\phpunit'
$root = 'D:\Software\PhpProject\Demo\co_crmv5'
$logDir = 'D:\Software\PhpProject\Demo\co_crmv5\storage\logs'
$log = Join-Path $logDir 'perfile-dev-run.log'
$csv = Join-Path $logDir ("perfile-" + $Database + "-results.csv")
$log = Join-Path $logDir ("perfile-" + $Database + "-run.log")
$env:DB_DATABASE = $Database
$env:XDEBUG_MODE = 'off'
Set-Location $root

if (-not (Test-Path -LiteralPath $csv)) {
    "file,status,tests,assertions,failures,errors,exitcode" | Out-File -FilePath $csv -Encoding UTF8
}
$done = @{}
Get-Content -LiteralPath $csv -Encoding UTF8 | Select-Object -Skip 1 | ForEach-Object {
    $parts = $_ -split ','
    if ($parts.Length -gt 0) { $done[$parts[0]] = $true }
}

$files = @()
$files += Get-ChildItem -LiteralPath "$root\tests\Unit" -Filter '*Test.php' -File | Select-Object -ExpandProperty FullName
$files += Get-ChildItem -LiteralPath "$root\tests\Feature" -Filter '*Test.php' -File | Select-Object -ExpandProperty FullName
$files = $files | Sort-Object

Add-Content -LiteralPath $log -Value ("RUN_START files=" + $files.Count + " done=" + $done.Count + " " + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))

$pass = 0
$fail = 0
foreach ($file in $files) {
    $name = Split-Path $file -Leaf
    if ($done.ContainsKey($name)) { continue }
    $final = 'CRASH'
    $tests = 0; $assertions = 0; $failures = 0; $errors = 0; $exitCode = -1
    for ($attempt = 1; $attempt -le 5; $attempt++) {
        $out = & $php -c $ini $phpunit $file --colors=never 2>&1 | Out-String
        $exitCode = $LASTEXITCODE
        if ($out -match 'OK \((\d+) tests?, (\d+) assertions?\)') {
            $final = 'OK'; $tests = [int]$Matches[1]; $assertions = [int]$Matches[2]
            break
        }
        if ($out -match 'Tests:\s*(\d+),\s*Assertions:\s*(\d+),\s*Failures:\s*(\d+),\s*Errors:\s*(\d+)') {
            $final = 'FAIL'; $tests = [int]$Matches[1]; $assertions = [int]$Matches[2]
            $failures = [int]$Matches[3]; $errors = [int]$Matches[4]
            break
        }
        if ($out -match 'No tests executed') {
            $final = 'EMPTY'; $tests = 0; $assertions = 0
            break
        }
        Start-Sleep -Seconds 3
    }
    if ($final -eq 'OK') { $pass++ } elseif ($final -eq 'FAIL') { $fail++ }
    $line = "$name,$final,$tests,$assertions,$failures,$errors,$exitCode"
    Add-Content -LiteralPath $csv -Value $line
    Add-Content -LiteralPath $log -Value ("[" + $final + "] " + $name + " (t=" + $tests + " a=" + $assertions + " f=" + $failures + " e=" + $errors + ")")
}

Add-Content -LiteralPath $log -Value ("=== DONE pass=" + $pass + " fail=" + $fail + " total=" + $files.Count + " ===")
