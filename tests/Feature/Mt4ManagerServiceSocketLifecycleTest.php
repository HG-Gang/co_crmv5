<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:40
 */

/**
 * Mt4ManagerServiceSocketLifecycleTest
 *
 * 文件功能：
 * - 验证 MT4 管理服务 socket 生命周期：写入失败/读取超时立即断开且下一条命令重连、超大响应视为畸形断开、非法协议值连接前拒绝、finally 断开异常 socket。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Mt4ManagerService;
use InvalidArgumentException;
use Tests\TestCase;

class Mt4ManagerServiceSocketLifecycleTest extends TestCase
{
    /**
     * 本测试注册的伪协议名。把 Mt4ManagerService 的 socket 连接指向可控 stream wrapper，
     * 用例结束后必须注销，避免污染同一进程内的其他测试。
     * @var string
     */
    private const STREAM_SCHEME = 'withdrawmt4socket';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!in_array(self::STREAM_SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::STREAM_SCHEME, ControllableMt4SocketStream::class);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (in_array(self::STREAM_SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::STREAM_SCHEME);
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        ControllableMt4SocketStream::reset(self::STREAM_SCHEME);
        // 生命周期测试只访问注册的内存 Stream Wrapper，显式开启测试进程内门禁，不修改环境配置。
        config([
            'mt4.enabled' => true,
            'mt4.user_sync_enabled' => true,
        ]);
    }

    public function test_write_failure_disconnects_immediately_and_the_next_command_reconnects(): void
    {
        $this->assertTransportFailureDisconnectsAndReconnects('write_failure', 'write_failed');
    }

    public function test_read_timeout_disconnects_immediately_and_the_next_command_reconnects(): void
    {
        $this->assertTransportFailureDisconnectsAndReconnects('read_timeout', 'read_timeout');
    }

    public function test_oversized_response_is_malformed_and_disconnects_immediately(): void
    {
        $service = new ControllableMt4ManagerService(['oversized_response']);

        $result = $service->sendForTest('USER_INFO_GET', ['acc' => 412372001]);

        $this->assertSame('error', $result['status'] ?? null);
        $this->assertSame('malformed_response', $result['error_code'] ?? null);
        $this->assertTrue($service->socketIsNull());
        $this->assertSame(1, ControllableMt4SocketStream::closedCount());
    }

    public function test_invalid_protocol_value_is_rejected_before_connecting(): void
    {
        $service = new class extends Mt4ManagerService {
            /**
             * connect() 被调用计数。断言协议字段非法时服务在连接发生前就失败关闭（计数为 0）。
             * @var int
             */
            public $connectAttempts = 0;

            public function __construct()
            {
                parent::__construct('socket.test', 1, 'test-key', 'test-version', 1);
            }

            public function connect()
            {
                $this->connectAttempts++;

                return true;
            }
        };

        try {
            $service->registerUser([
                'user_id' => 412300001,
                'password' => 'unsafe&grp=evil',
            ]);
            $this->fail('MT4 protocol delimiters must be rejected before connecting.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(0, $service->connectAttempts);
        }
    }

    public function test_unexpected_read_throwable_disconnects_socket_in_finally(): void
    {
        $service = new ThrowingReadMt4ManagerService();
        $failure = null;
        try {
            $service->sendForTest('USER_INFO_GET', ['acc' => 412372001]);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        try {
            $this->assertInstanceOf(\Error::class, $failure);
            $this->assertTrue($service->socketIsNull());
            $this->assertSame(1, ControllableMt4SocketStream::closedCount());
        } finally {
            $service->disconnect();
        }
    }

    private function assertTransportFailureDisconnectsAndReconnects(
        string $failureMode,
        string $expectedErrorCode
    ): void {
        $service = new ControllableMt4ManagerService([$failureMode, 'success']);

        $first = $service->sendForTest('USER_INFO_GET', ['acc' => 412372001]);
        $socketWasNullAfterFailure = $service->socketIsNull();
        $closedAfterFailure = ControllableMt4SocketStream::closedCount();
        $second = $service->sendForTest('USER_INFO_GET', ['acc' => 412372001]);

        $this->assertSame([
            'first_status' => 'error',
            'first_error_code' => $expectedErrorCode,
            'socket_null_after_failure' => true,
            'closed_after_failure' => 1,
            'second_status' => 'ok',
            'second_message' => 'accepted',
            'second_data' => ['42'],
            'connect_attempts' => 2,
        ], [
            'first_status' => $first['status'] ?? null,
            'first_error_code' => $first['error_code'] ?? null,
            'socket_null_after_failure' => $socketWasNullAfterFailure,
            'closed_after_failure' => $closedAfterFailure,
            'second_status' => $second['status'] ?? null,
            'second_message' => $second['message'] ?? null,
            'second_data' => $second['data'] ?? null,
            'connect_attempts' => $service->connectAttempts(),
        ]);

        $service->disconnect();
    }
}

final class ControllableMt4ManagerService extends Mt4ManagerService
{
    /**
     * 按连接顺序预设的每个 socket 场景模式（success / write_failure / read_timeout / oversized_response）。
     * 构造函数注入后按 open 顺序逐个消费，驱动各种 I/O 失败路径。
     * @var array<int, string>
     */
    private $socketModes;

    /**
     * connect() 被调用计数。断言生命周期中的连接次数符合预期（如失败后不无限重连）。
     * @var int
     */
    private $connectAttempts = 0;

    /** @param array<int, string> $socketModes */
    public function __construct(array $socketModes)
    {
        parent::__construct('socket.test', 1, 'test-key', 'test-version', 1);

        $this->socketModes = $socketModes;
    }

    public function connect()
    {
        if ($this->socket) {
            return true;
        }

        $this->connectAttempts++;
        $mode = array_shift($this->socketModes);
        if ($mode === null) {
            return false;
        }

        $this->socket = ControllableMt4SocketStream::open($mode);

        return is_resource($this->socket);
    }

    /** @param array<string, mixed> $params */
    public function sendForTest(string $command, array $params): array
    {
        return $this->sendCommand($command, $params);
    }

    public function socketIsNull(): bool
    {
        return $this->socket === null;
    }

    public function connectAttempts(): int
    {
        return $this->connectAttempts;
    }
}

final class ThrowingReadMt4ManagerService extends Mt4ManagerService
{
    public function __construct()
    {
        parent::__construct('socket.test', 1, 'test-key', 'test-version', 1);
    }

    public function connect()
    {
        if ($this->socket) {
            return true;
        }
        $this->socket = ControllableMt4SocketStream::open('success');

        return true;
    }

    /** @param array<string, mixed> $params */
    public function sendForTest(string $command, array $params): array
    {
        return $this->sendCommand($command, $params);
    }

    public function socketIsNull(): bool
    {
        return $this->socket === null;
    }

    protected function readUntilEnd(): ?string
    {
        throw new \Error('unexpected read failure');
    }
}

final class ControllableMt4SocketStream
{
    /**
     * PHP stream wrapper 约定的上下文资源属性；替身流不使用真实上下文，保持 null 即可。
     * @var resource|null
     */
    public $context;

    /**
     * reset() 记录的当前伪协议名。open() 用它拼出 wrapper URL，让 fopen 落到本替身上。
     * @var string
     */
    private static $scheme;

    /**
     * 下一个流的场景编号。每次 open 自增，为每个连接分配可区分的唯一 id。
     * @var int
     */
    private static $nextId = 1;

    /**
     * 流 id => 场景模式 的映射。stream_open 时按 id 取出该连接应模拟的行为。
     * @var array<string, string>
     */
    private static $modes = [];

    /**
     * 流 id => 是否已 fclose 的映射。closedCount() 断言每个 socket 都被显式关闭，防止资源泄漏。
     * @var array<string, bool>
     */
    private static $closed = [];

    /**
     * 当前流实例的 id（从 wrapper URL 的 host 段解析），用于关联场景模式与关闭状态。
     * @var string
     */
    private $id = '';

    /**
     * 当前流实例被指派的场景模式，决定写入、读取、超时等行为分支。
     * @var string
     */
    private $mode = '';

    /**
     * 预设的 MT4 报文响应体（legacy &k=v 格式）。stream_read 按请求字节数分片返回，模拟真实 socket 读取。
     * @var string
     */
    private $response = '';

    public static function reset(string $scheme): void
    {
        self::$scheme = $scheme;
        self::$nextId = 1;
        self::$modes = [];
        self::$closed = [];
    }

    /** @return resource */
    public static function open(string $mode)
    {
        $id = 'scenario-' . self::$nextId++;
        self::$modes[$id] = $mode;
        self::$closed[$id] = false;
        $stream = fopen(self::$scheme . '://' . $id, 'r+');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to open the controllable MT4 test stream.');
        }

        return $stream;
    }

    public static function closedCount(): int
    {
        return count(array_filter(self::$closed));
    }

    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        $this->id = (string) parse_url($path, PHP_URL_HOST);
        $this->mode = self::$modes[$this->id] ?? '';

        return $this->mode !== '';
    }

    public function stream_write($data): int
    {
        if ($this->mode === 'write_failure') {
            return 0;
        }

        if ($this->mode === 'success') {
            // Legacy wire response: &k=v until end line.
            $this->response = "act=accountinfo&ver=000005&err=0&des=accepted&tck=42\r\nend\r\n";
        }

        if ($this->mode === 'oversized_response') {
            $this->response = str_repeat('x', 65537);
        }

        return strlen($data);
    }

    /** @return string|false */
    public function stream_read($count)
    {
        if ($this->mode === 'read_timeout') {
            return false;
        }

        $chunk = substr($this->response, 0, $count);
        $this->response = (string) substr($this->response, strlen($chunk));

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->mode !== 'read_timeout' && $this->response === '';
    }

    /** @return array<string, mixed> */
    public function stream_stat(): array
    {
        return [];
    }

    public function stream_close(): void
    {
        self::$closed[$this->id] = true;
    }

    public function stream_set_option($option, $arg1, $arg2): bool
    {
        return true;
    }
}
