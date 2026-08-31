<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:40
 */

/**
 * Mt4ManagerServiceLegacyProtocolClosureModuleTest
 *
 * 文件功能：
 * - 验证 MT4 管理服务旧协议闭环：入金旧 act 查询帧与 tck 票号映射、旧出金动作名、注册保留回显账户身份、旧 KV 响应余额字段映射、锁户/交易资料/层级单帧更新、供应商错误码映射。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Mt4ManagerService;
use Tests\TestCase;

/**
 * Ensures new MT4 client speaks the same wire protocol as old Abstract_Service_Controller /
 * Mt4ManagerService (act/ver/key + E...QUIT + &k=v response), not the invented CMD:k=v| format.
 */
class Mt4ManagerServiceLegacyProtocolClosureModuleTest extends TestCase
{
    /**
     * 本测试注册的伪协议名。把 Mt4ManagerService 的 socket 连接指向可控 stream wrapper，
     * 用例结束后必须注销，避免污染同一进程内的其他测试。
     * @var string
     */
    private const STREAM_SCHEME = 'mt4legacyprotocol';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!in_array(self::STREAM_SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::STREAM_SCHEME, LegacyMt4ProtocolStream::class);
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
        LegacyMt4ProtocolStream::reset(self::STREAM_SCHEME);
        // 本测试使用内存流验证真实协议帧，必须显式声明已授权，且不会连接外部 MT4。
        config([
            'mt4.enabled' => true,
            'mt4.user_sync_enabled' => true,
        ]);
    }

    public function test_deposit_sends_legacy_act_query_frame_and_maps_tck_to_ok_ticket(): void
    {
        LegacyMt4ProtocolStream::queueResponse("act=deposit&ver=000005&err=0&des=OK&acc=1001&tck=3808401\r\nend\r\n");
        $service = new LegacyProtocolMt4ManagerService();

        $result = $service->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $written = LegacyMt4ProtocolStream::lastWritten();
        $this->assertStringStartsWith('Eact=deposit&ver=test-version&key=test-key&acc=1001&amt=25.00&cmt=', $written);
        $this->assertStringContainsString("\r\nQUIT\r\n", $written);
        $this->assertStringNotContainsString('USER_DEPOSIT', $written);
        $this->assertStringNotContainsString('|', explode("\r\n", $written)[0]);

        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertSame('3808401', $result['ticket'] ?? null);
        $this->assertSame('0', $result['err'] ?? null);
    }

    public function test_withdrawal_uses_legacy_withdrawal_action_name(): void
    {
        LegacyMt4ProtocolStream::queueResponse("act=withdrawal&ver=000005&err=0&des=OK&acc=1001&tck=3808402\r\nend\r\n");
        $service = new LegacyProtocolMt4ManagerService();

        $result = $service->withdrawal(1001, '10.00', 'WDR-1');

        $written = LegacyMt4ProtocolStream::lastWritten();
        $this->assertStringContainsString('act=withdrawal&', $written);
        $this->assertStringNotContainsString('USER_WITHDRAW', $written);
        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertSame('3808402', $result['ticket'] ?? null);
    }

    public function test_register_success_preserves_the_echoed_account_identity(): void
    {
        LegacyMt4ProtocolStream::queueResponse(
            "act=register&ver=000005&err=0&des=OK&acc=412300001\r\nend\r\n"
        );
        $service = new LegacyProtocolMt4ManagerService();

        $result = $service->registerUser([
            'user_id' => 412300001,
            'user_name' => 'Provisioning User',
            'password' => 'protocol-safe-password',
            'email' => 'provisioning@example.test',
            'phone' => '86-13900000001',
            'id_card' => 'PROVISIONING-ID',
            'parent_id' => 0,
            'group' => 'demo\\retail',
            'country' => 'CN',
            'leverage' => 100,
        ]);

        $this->assertStringContainsString('act=register&', LegacyMt4ProtocolStream::lastWritten());
        $this->assertStringContainsString('acc=412300001&', LegacyMt4ProtocolStream::lastWritten());
        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertSame('412300001', $result['acc'] ?? null);
    }

    public function test_accountinfo_maps_balance_fields_from_legacy_kv_response(): void
    {
        LegacyMt4ProtocolStream::queueResponse(
            "act=accountinfo&ver=000005&err=0&des=OK&acc=1001&bal=1234.56&eqy=1200.00&mrg=50.00&fre=1150.00&lvg=100\r\nend\r\n"
        );
        $service = new LegacyProtocolMt4ManagerService();

        $result = $service->getAccountInfo(1001);

        $written = LegacyMt4ProtocolStream::lastWritten();
        $this->assertStringContainsString('act=accountinfo&', $written);
        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertSame('1234.56', (string) ($result['balance'] ?? ''));
        $this->assertSame('1200.00', (string) ($result['equity'] ?? ''));
        $this->assertSame('1150.00', (string) ($result['free_margin'] ?? ''));
    }

    public function test_lock_user_uses_legacy_lock_user_action(): void
    {
        LegacyMt4ProtocolStream::queueResponse("act=lock_user&ver=000005&err=0&des=OK&acc=1001\r\nend\r\n");
        $service = new LegacyProtocolMt4ManagerService();

        $result = $service->lockUser(1001);

        $this->assertStringContainsString('act=lock_user&', LegacyMt4ProtocolStream::lastWritten());
        $this->assertSame('ok', $result['status'] ?? null);
    }

    /**
     * 验证账户类型切换使用一次 update_user 同时提交组别和杠杆。
     *
     * @return void
     */
    public function test_update_user_trading_profile_sends_group_and_leverage_in_one_frame(): void
    {
        LegacyMt4ProtocolStream::queueResponse(
            "act=update_user&ver=000005&err=0&des=OK&acc=1001&grp=GMTK-P&lvg=200\r\nend\r\n"
        );
        $service = new LegacyProtocolMt4ManagerService();

        $result = $service->updateUserTradingProfile(1001, 'GMTK-P', 200);

        $written = LegacyMt4ProtocolStream::lastWritten();
        $this->assertStringContainsString(
            'act=update_user&ver=test-version&key=test-key&acc=1001&grp=GMTK-P&lvg=200',
            $written
        );
        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertSame('0', $result['err'] ?? null);
    }

    /**
     * 验证客户上级代理变更使用一次 update_user 同时提交 zip 和 cny。
     *
     * 参数逻辑说明：
     * - zip 表示新的直属上级代理业务用户 ID。
     * - cny 表示旧 MT4 协议使用的五段代理关系码。
     *
     * @return void
     */
    public function test_update_user_hierarchy_sends_parent_and_relationship_code_in_one_frame(): void
    {
        LegacyMt4ProtocolStream::queueResponse(
            "act=update_user&ver=000005&err=0&des=OK&acc=1001&zip=2002&cny=00002002000000000000\r\nend\r\n"
        );
        $service = new LegacyProtocolMt4ManagerService();

        $result = $service->updateUserHierarchy(1001, 2002, '00002002000000000000');

        $written = LegacyMt4ProtocolStream::lastWritten();
        $this->assertStringContainsString(
            'act=update_user&ver=test-version&key=test-key&acc=1001&zip=2002&cny=00002002000000000000',
            $written
        );
        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertSame('0', $result['err'] ?? null);
    }

    public function test_provider_error_maps_to_status_error_with_provider_code(): void
    {
        LegacyMt4ProtocolStream::queueResponse("act=deposit&ver=000005&err=1003&des=not enough money&acc=1001\r\nend\r\n");
        $service = new LegacyProtocolMt4ManagerService();

        $result = $service->deposit(1001, '25.00', 'x');

        $this->assertSame('error', $result['status'] ?? null);
        $this->assertSame('1003', $result['error_code'] ?? null);
        $this->assertSame('not enough money', $result['message'] ?? null);
    }
}

final class LegacyProtocolMt4ManagerService extends Mt4ManagerService
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
        $this->socket = LegacyMt4ProtocolStream::open();

        return is_resource($this->socket);
    }
}

final class LegacyMt4ProtocolStream
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
    private static $scheme = '';
    /**
     * 下一个流的编号。每次 open 自增，为每个连接分配可区分的唯一 id。
     * @var int
     */
    private static $nextId = 1;
    /**
     * queueResponse() 排入的响应队列。stream_open 时按连接顺序弹出作为该流的响应体，
     * 驱动 legacy 报文的成功/失败形态。
     * @var array<int, string>
     */
    private static $responses = [];
    /**
     * 每条流上实际写入的报文记录。lastWritten() 供用例断言发给 MT4 的原始协议串字节级正确。
     * @var array<int, string>
     */
    private static $written = [];
    /**
     * 当前流实例的 id（从 wrapper URL 的 host 段解析），用于在静态表中定位本流。
     * @var string
     */
    private $id = '';
    /**
     * 当前流剩余的响应缓冲。stream_read 按请求字节数分片返回，读空后 stream_eof 返回 true。
     * @var string
     */
    private $buffer = '';

    public static function reset(string $scheme): void
    {
        self::$scheme = $scheme;
        self::$nextId = 1;
        self::$responses = [];
        self::$written = [];
    }

    public static function queueResponse(string $response): void
    {
        self::$responses[] = $response;
    }

    public static function lastWritten(): string
    {
        return (string) end(self::$written);
    }

    /** @return resource */
    public static function open()
    {
        $id = 'legacy-' . self::$nextId++;
        $stream = fopen(self::$scheme . '://' . $id, 'r+');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to open legacy MT4 protocol stream.');
        }

        return $stream;
    }

    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        $this->id = (string) parse_url($path, PHP_URL_HOST);
        $this->buffer = (string) array_shift(self::$responses);

        return true;
    }

    public function stream_write($data): int
    {
        self::$written[] = (string) $data;

        return strlen((string) $data);
    }

    public function stream_read($count)
    {
        if ($this->buffer === '') {
            return false;
        }
        $chunk = substr($this->buffer, 0, $count);
        $this->buffer = (string) substr($this->buffer, strlen($chunk));

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->buffer === '';
    }

    public function stream_stat(): array
    {
        return [];
    }

    public function stream_close(): void
    {
    }

    public function stream_set_option($option, $arg1, $arg2): bool
    {
        return true;
    }
}
