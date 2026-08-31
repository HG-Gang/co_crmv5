param(
    [Parameter(Mandatory = $true)][string]$Filter,
    [Parameter(Mandatory = $true)][string]$Log
)

# 逐文件 PHPUnit 可靠性运行器。
#
# 文件功能：
# - 只发现命名符合 `*Test.php` 的测试文件，并在独立 PHPUnit 进程中串行执行。
# - 同时保留每个进程的 stdout、stderr 和退出码，避免错误输出被管道吞掉。
# - 结合 PHPUnit 摘要与真实退出码区分 OK、FAIL 和 CRASH。
#
# 失败语义：
# - 退出码为零但没有成功摘要仍属于 CRASH，不能凭进程状态伪造通过。
# - 任一 FAIL、CRASH 或空匹配都会令脚本返回 1，供自动化调用方可靠阻断后续操作。

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

try {
    $phpCommand = Get-Command php -CommandType Application -ErrorAction Stop | Select-Object -First 1
    $php = $phpCommand.Source
} catch {
    Write-Error '无法从当前 PATH 解析 PHP 可执行文件。'
    exit 1
}

# 每个子进程继承同一隔离边界，防止 phpunit.xml 外的调用方式落回普通环境或触发 MT4 同步。
$env:XDEBUG_MODE = 'off'
$env:APP_ENV = 'testing'
$env:DATABASE_URL = ''
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3307'
$env:DB_SOCKET = ''
$env:DB_DATABASE = 'co_crmv5_test'
$env:MT4_ENABLED = 'false'
$env:MT4_USER_SYNC_ENABLED = 'false'
Set-Location $root

$testsRoot = Join-Path $root 'tests'
$files = @(Get-ChildItem -Path $testsRoot -Recurse -Filter '*Test.php' -File |
    Where-Object { $_.FullName -like $Filter } |
    Sort-Object FullName)

if ([System.IO.Path]::IsPathRooted($Log)) {
    $logPath = $Log
} else {
    $logPath = Join-Path $root $Log
}

$logDirectory = Split-Path -Parent $logPath
if ($logDirectory -and -not (Test-Path -LiteralPath $logDirectory)) {
    New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
}

$ok = 0
$fail = 0
$crash = 0
$total = $files.Count
$index = 0

if ($total -eq 0) {
    Add-Content -LiteralPath $logPath -Value "CRASH`tNO_MATCHING_TEST_FILES"
    Write-Output 'SUMMARY OK=0 FAIL=0 CRASH=1 TOTAL=0'
    exit 1
}

foreach ($file in $files) {
    $index++
    $stdoutPath = [System.IO.Path]::GetTempFileName()
    $stderrPath = [System.IO.Path]::GetTempFileName()

    try {
        # 临时文件让两个输出流保持独立；退出码必须在任何后续 PowerShell 命令前立即保存。
        & $php (Join-Path $root 'vendor/bin/phpunit') --colors=never $file.FullName 1> $stdoutPath 2> $stderrPath
        $testExit = $LASTEXITCODE
        $stdout = [System.IO.File]::ReadAllText($stdoutPath)
        $stderr = [System.IO.File]::ReadAllText($stderrPath)
        $combinedOutput = $stdout + "`n" + $stderr

        if ($testExit -eq 0 -and $combinedOutput -match '(?m)^OK \([0-9]+ tests?, [0-9]+ assertions?\)') {
            $status = 'OK'
            $ok++
        } elseif ($testExit -ne 0 -and $combinedOutput -match '(?m)^(FAILURES!|ERRORS!)') {
            $status = 'FAIL'
            $fail++
        } else {
            # 进程状态与 PHPUnit 摘要不一致时无法证明测试完成，统一按运行器崩溃处理。
            $status = 'CRASH'
            $crash++
        }

        Add-Content -LiteralPath $logPath -Value ("=== {0}`t{1}`tEXIT={2} ===" -f $status, $file.FullName, $testExit)
        Add-Content -LiteralPath $logPath -Value '--- STDOUT ---'
        Add-Content -LiteralPath $logPath -Value $stdout
        Add-Content -LiteralPath $logPath -Value '--- STDERR ---'
        Add-Content -LiteralPath $logPath -Value $stderr

        if ($stdout.Length -gt 0) {
            Write-Output $stdout.TrimEnd()
        }
        if ($stderr.Length -gt 0) {
            [Console]::Error.Write($stderr)
        }
    } catch {
        # PowerShell 自身无法启动或读取子进程时没有可信 PHPUnit 结果，记录为 CRASH 并继续汇总。
        $status = 'CRASH'
        $crash++
        $runnerErrorType = $_.Exception.GetType().FullName
        $runnerErrorId = $_.FullyQualifiedErrorId
        $runnerErrorMessage = $_.Exception.Message
        Add-Content -LiteralPath $logPath -Value (
            "CRASH`t{0}`tRUNNER_ERROR`tTYPE={1}`tERROR_ID={2}`tMESSAGE={3}" -f `
                $file.FullName,
                $runnerErrorType,
                $runnerErrorId,
                $runnerErrorMessage
        )
    } finally {
        # 每个测试结束后删除已读取的临时流文件，避免大套件持续占用磁盘和文件句柄。
        Remove-Item -LiteralPath $stdoutPath, $stderrPath -Force -ErrorAction SilentlyContinue
    }

    Write-Output ("[{0}/{1}] {2} {3}" -f $index, $total, $status, $file.Name)
}

Write-Output ("SUMMARY OK={0} FAIL={1} CRASH={2} TOTAL={3}" -f $ok, $fail, $crash, $total)
if (($fail + $crash) -gt 0) {
    exit 1
}

exit 0
