# 后台全量回归运行器：供 Codex 以隐藏窗口启动，输出统一写入项目根目录日志。
# 设置说明：
# - XDEBUG_MODE=off 关闭 Xdebug 提升测试速度。
# - *> 将 PHPUnit 全部输出流合并写入日志文件。
$env:XDEBUG_MODE = 'off'
Set-Location 'D:\Software\PhpProject\Demo\co_crmv5'
& php vendor\bin\phpunit --colors=never *> '_fullrun_20260801.log'
