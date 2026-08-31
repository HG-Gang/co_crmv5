<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 00:04
 */

/**
 * 佣金转账对账 worker 测试替身脚本(commission_transfer_reconciliation_worker.php)。
 *
 * 文件功能:
 * - 作为独立子进程被测试拉起,通过 argv[1] 接收 base64 编码的 JSON payload,
 *   与父进程以文件信号量握手:先写 ready 文件,再等待 go 文件出现(10 秒超时),
 *   然后调用 CommissionTransferReconciliationService::reconcile() 执行真实对账,
 *   把结果 JSON 写入 result 文件并以退出码 0 结束。
 * - 任意异常(非法 base64/JSON、缺字段、超时、对账失败)时把 worker_error 结果写入
 *   result 文件、向 STDERR 输出错误信息并以退出码 1 结束。
 *
 * 适用场景:这是复杂测试替身,供并发/异步对账测试模拟真实服务端进程,
 * 验证父进程与 worker 之间的协调屏障与结果回传闭环。
 *
 * 入参例子:argv[1] 为 base64 编码的 JSON,必须含 ready/go/result(信号文件路径)、
 * admin_id、transfer_id、evidence、external_reference 七个字段,缺任一字段即报错退出。
 *
 * 返回值:结果 JSON 写入 result 文件,字段与 CommissionTransferReconciliationService::reconcile()
 * 的返回结构一致;退出码 0 表示成功、1 表示失败。
 *
 * 失败场景:worker 不是 testing 环境、数据库不是 co_crmv5_test、MT4 未关闭，或 result
 * 文件出现 worker_error/退出码非 0 时失败，避免并发测试连接正式库或真实 MT4。
 */

declare(strict_types=1);

use App\Models\Admin;
use App\Services\CommissionTransfer\CommissionTransferReconciliationService;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = [];
try {
    $databaseName = (string) $app->make('db')->connection()->getDatabaseName();
    $mt4Enabled = filter_var(getenv('MT4_ENABLED'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $mt4UserSyncEnabled = filter_var(
        getenv('MT4_USER_SYNC_ENABLED'),
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );
    if (!$app->environment('testing') || $databaseName !== 'co_crmv5_test') {
        throw new RuntimeException('Reconciliation worker refused a non-test database environment.');
    }
    if ($mt4Enabled !== false || $mt4UserSyncEnabled !== false) {
        throw new RuntimeException('Reconciliation worker requires MT4 integrations to be disabled.');
    }

    $encoded = (string) ($argv[1] ?? '');
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid reconciliation worker payload.');
    }
    $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
    foreach (['ready', 'go', 'result', 'admin_id', 'transfer_id', 'evidence', 'external_reference'] as $field) {
        if (!array_key_exists($field, $payload)) {
            throw new RuntimeException('Missing reconciliation worker field: ' . $field . '.');
        }
    }

    file_put_contents((string) $payload['ready'], 'ready', LOCK_EX);
    $deadline = microtime(true) + 10.0;
    while (!is_file((string) $payload['go'])) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timed out at reconciliation worker barrier.');
        }
        usleep(10000);
    }

    $admin = Admin::query()->findOrFail((int) $payload['admin_id']);
    $service = $app->make(CommissionTransferReconciliationService::class);
    $result = $service->reconcile(
        $admin,
        (int) $payload['transfer_id'],
        (array) $payload['evidence'],
        (string) $payload['external_reference'],
        '127.0.0.1'
    );
    file_put_contents(
        (string) $payload['result'],
        json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        LOCK_EX
    );
    exit(0);
} catch (Throwable $exception) {
    if (isset($payload['result'])) {
        file_put_contents(
            (string) $payload['result'],
            json_encode([
                'result' => 'worker_error',
                'message' => $exception->getMessage(),
            ], JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
