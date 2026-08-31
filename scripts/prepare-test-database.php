<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 11:56
 */

declare(strict_types=1);

use Dotenv\Dotenv;

/**
 * 隔离测试数据库准备器。
 *
 * 文件功能：
 * - 从项目 `.env` 读取 MySQL 服务地址和凭据。
 * - 在不选择业务数据库的连接上创建唯一白名单测试库。
 * - 以进程退出码向调用脚本报告准备结果。
 *
 * 安全边界：
 * - 数据库目标不接受命令行或环境变量覆盖，避免外部输入扩大建库范围。
 * - 连接或建库失败时不输出 PDO 异常和密码，只返回通用错误与非零退出码。
 */

$projectRoot = dirname(__DIR__);
$autoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "测试数据库准备失败：项目依赖未安装。\n");
    exit(1);
}

require $autoloadPath;

try {
    // 使用项目自己的环境文件确定 MySQL 服务位置，但数据库目标始终由下方白名单控制。
    Dotenv::createImmutable($projectRoot)->safeLoad();

    $allowedDatabases = ['co_crmv5_test'];
    $targetDatabase = 'co_crmv5_test';
    if (!in_array($targetDatabase, $allowedDatabases, true)) {
        fwrite(STDERR, "测试数据库准备失败：目标不在白名单内。\n");
        exit(1);
    }

    /**
     * 读取已加载的连接参数，并拒绝数组等非标量输入。
     *
     * @param string $key `.env` 中的连接参数名。
     * @param string $default 参数缺失时使用的本地开发默认值。
     * @return string 可用于 PDO 连接的字符串值。
     *
     * @throws RuntimeException 参数不是字符串或数值时抛出，禁止隐式转换未知结构。
     */
    $readEnvironment = static function (string $key, string $default): string {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        if (!is_string($value) && !is_int($value)) {
            throw new RuntimeException('Invalid database connection parameter.');
        }

        return (string) $value;
    };

    $host = trim($readEnvironment('DB_HOST', '127.0.0.1'));
    $port = trim($readEnvironment('DB_PORT', '3307'));
    $username = $readEnvironment('DB_USERNAME', 'root');
    $password = $readEnvironment('DB_PASSWORD', '');

    // 建库能力只允许落在用户确认的本地 MySQL 实例，拒绝环境文件把脚本引向其他服务器。
    if ($host !== '127.0.0.1' || $port !== '3307') {
        throw new RuntimeException('Unexpected test database server identity.');
    }

    // 空主机、空用户名或非法端口都代表连接边界不完整，必须在发起 PDO 请求前失败关闭。
    if ($host === '' || $username === '' || !ctype_digit($port)) {
        throw new RuntimeException('Invalid database connection configuration.');
    }

    $portNumber = (int) $port;
    if ($portNumber < 1 || $portNumber > 65535) {
        throw new RuntimeException('Invalid database port.');
    }

    // DSN 故意不包含 dbname，确保目标库尚不存在时仍能连接 MySQL 服务并执行建库。
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $portNumber);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // 标识符不能使用 PDO 参数绑定；精确白名单在拼接前已完成验证，因此不存在外部注入入口。
    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $targetDatabase
    ));

    fwrite(STDOUT, "隔离测试数据库已准备。\n");
    exit(0);
} catch (Throwable $exception) {
    // 失败详情可能包含主机或驱动上下文；调用方只需要失败语义，敏感连接信息不得进入日志。
    fwrite(STDERR, "测试数据库准备失败。\n");
    exit(1);
}
