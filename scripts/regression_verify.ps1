param(
    [string]$ResultFile = 'D:\Software\PhpProject\Demo\co_crmv5\storage\logs\regression-verify.csv',
    [string]$LogDir = 'D:\Software\PhpProject\Demo\co_crmv5\storage\logs\regression-verify',
    [string]$Suffix = '_verify_batch'
)

# 验证库逐文件回归包装：设置隔离库与独立锁后缀后调用通用运行器。
# 用途：避开主库并发进程与共享互斥锁，产出干净的逐文件回归证据。

$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3307'
$env:DB_DATABASE = 'co_crmv5_verify'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = '123456'
$env:PHPUNIT_LOCK_SUFFIX = $Suffix

& powershell -NoProfile -ExecutionPolicy Bypass -File `
    'D:\Software\PhpProject\Demo\co_crmv5\scripts\regression_runner.ps1' `
    -ResultFile $ResultFile `
    -LogDir $LogDir

Write-Host 'REGRESSION_VERIFY_DONE'
