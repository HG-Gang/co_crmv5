# 全量 PHPUnit 串行运行器。
#
# 文件功能：
# - 从脚本目录解析项目根，并使用当前命令行环境中的 PHP 可执行文件。
# - 固定隔离测试环境，依次准备数据库、重建结构与种子数据、执行完整测试。
# - 将标准输出、标准错误和最终退出码分别写入带时间戳的日志文件。
#
# 失败语义：
# - 任一准备阶段失败都会立即停止，后续阶段不会在不完整数据库上继续运行。
# - 最终退出码原样传递最后执行的子进程状态，禁止把失败包装成成功。

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$logDir = Join-Path $root 'storage\logs'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$stdoutLog = Join-Path $logDir ("full-serial-" + $stamp + ".out")
$stderrLog = Join-Path $logDir ("full-serial-" + $stamp + ".err")
$exitFile = Join-Path $logDir ("full-serial-" + $stamp + ".exit")

New-Item -ItemType Directory -Path $logDir -Force | Out-Null

try {
    $phpCommand = Get-Command php -CommandType Application -ErrorAction Stop | Select-Object -First 1
    $php = $phpCommand.Source
} catch {
    Add-Content -LiteralPath $stderrLog -Value '无法从当前 PATH 解析 PHP 可执行文件。'
    1 | Out-File -FilePath $exitFile -Encoding UTF8
    exit 1
}

# 环境变量覆盖项目配置，确保迁移和测试只能使用隔离库，并且不会建立 MT4 外部连接。
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

# 先确认 MySQL 服务可连接且白名单数据库存在；失败时禁止执行任何破坏性迁移。
& $php (Join-Path $root 'scripts\prepare-test-database.php') 1>> $stdoutLog 2>> $stderrLog
$prepareExit = $LASTEXITCODE
Add-Content -LiteralPath $stdoutLog -Value ("PREPARE_EXIT=" + $prepareExit)
if ($prepareExit -ne 0) {
    $prepareExit | Out-File -FilePath $exitFile -Encoding UTF8
    exit $prepareExit
}

# 数据库目标已由准备器和环境变量双重固定，此处才允许重建测试结构与种子数据。
& $php (Join-Path $root 'artisan') migrate:fresh --seed --force 1>> $stdoutLog 2>> $stderrLog
$migrationExit = $LASTEXITCODE
Add-Content -LiteralPath $stdoutLog -Value ("MIGRATION_EXIT=" + $migrationExit)
if ($migrationExit -ne 0) {
    $migrationExit | Out-File -FilePath $exitFile -Encoding UTF8
    exit $migrationExit
}

# 只有完整测试基线安装成功后才启动 PHPUnit，测试进程状态即为本次运行的最终状态。
# PHPUnit 及其子进程可能把非致命图像/浏览器警告写到 stderr；这里已经将 stderr
# 重定向到独立日志，因此不能让 PowerShell 的 Stop 策略把可记录警告升级为脚本异常。
$previousErrorActionPreference = $ErrorActionPreference
try {
    $ErrorActionPreference = 'Continue'
    & $php (Join-Path $root 'vendor/bin/phpunit') --colors=never 1>> $stdoutLog 2>> $stderrLog
    $testExit = $LASTEXITCODE
} finally {
    $ErrorActionPreference = $previousErrorActionPreference
}
Add-Content -LiteralPath $stdoutLog -Value ("PHPUNIT_EXIT=" + $testExit)
$testExit | Out-File -FilePath $exitFile -Encoding UTF8
exit $testExit
