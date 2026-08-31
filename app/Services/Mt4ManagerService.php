<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:31
 */

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OverflowException;

/**
 * MT4 Manager Socket 服务（对齐旧项目真实协议）。
 *
 * 文件功能：
 * - 本服务负责把系统内的开户注册、入金、出金、改密、锁定、组别变更等动作转换为旧项目 MT4 Manager API 命令。
 * - 旧协议查询串：act={动作}&ver={版本}&key={密钥}&acc=...&amt=...&cmt=...
 * - 旧协议帧格式：E{query}\r\nQUIT\r\n；响应为 & 分隔的 k=v，直到 end 行。
 * - 命令参数中的中文备注会从 UTF-8 转为 GBK；响应再尽量转回 UTF-8。
 * - 返回数组中的 message 可能被上层接口直接透出给用户，因此连接失败、读取超时等系统错误必须使用 Laravel 多语言文案。
 *
 * 参数含义：
 * - $host 表示 MT4 Manager API 主机地址。
 * - $port 表示 MT4 Manager API 端口。
 * - $apiKey 表示 MT4 Manager API 授权密钥。
 * - $apiVersion 表示 MT4 Manager API 协议版本。
 * - $timeout 表示 Socket 连接和读取超时时间。
 * - $retries 表示连接失败最大重试次数。
 * - $retryDelay 表示重试间隔秒数。
 * - $act 表示旧协议动作名（deposit/withdrawal/accountinfo/...）。
 * - $cmd 表示 MT4 命令名称（与 $act 同义，兼容旧注释命名）。
 * - $params 表示命令参数键值对（不含 act/ver/key）。
 * - $query 表示拼接后的 act/ver/key 查询串。
 * - $paramStr 表示转换为 MT4 协议格式后的参数片段（旧命名；现为 & 拼接的 k=v 段）。
 * - $frame / $fullCmd 表示最终写入 Socket 的完整命令字符串（E{query}\\r\\nQUIT\\r\\n）。
 * - $response 表示 MT4 Socket 返回的原始响应。
 * - $fields / $parts 表示按协议分隔后的响应字段。
 * - $status 表示响应状态，统一为 ok/error 供网关判断。
 */
class Mt4ManagerService
{
    /**
     * 单次 MT4 Socket 响应的字节上限，固定 65536（64 KiB）。
     * 读取循环累计超过该值即抛 OverflowException，防止 MT4 返回畸形超长响应耗尽内存；
     * 正常协议响应远小于该上限，调大等于放宽 DoS 防线。
     */
    protected const MAX_RESPONSE_BYTES = 65536;

    /**
     * @var string $host 表示 MT4 Manager API 主机地址，例如内网 IP 或域名。
     */
    protected $host;

    /**
     * @var int|string $port 表示 MT4 Manager API 端口，通常由 mt4 配置文件注入。
     */
    protected $port;

    /**
     * @var string $apiKey 表示 MT4 Manager API 授权密钥，写入每条命令的 key 参数。
     */
    protected $apiKey;

    /**
     * @var string $apiVersion 表示 MT4 Manager API 协议版本，写入每条命令的 ver 参数。
     */
    protected $apiVersion;

    /**
     * @var int $timeout 表示 Socket 连接和读取超时时间，单位为秒。
     */
    protected $timeout;

    /**
     * @var int $retries 表示连接失败时的最大尝试次数。
     */
    protected $retries;

    /**
     * @var int $retryDelay 表示重试间隔，单位为秒。
     */
    protected $retryDelay;

    /**
     * @var resource|null $socket 表示当前已建立的 MT4 Socket 连接，null 表示尚未连接或已经断开。
     */
    protected $socket = null;

    /**
     * 构造 MT4 Manager Socket 服务。
     *
     * @param string $host MT4 Manager API 主机地址。
     * @param int|string $port MT4 Manager API 端口。
     * @param string $apiKey MT4 Manager API 授权密钥。
     * @param string $apiVersion MT4 Manager API 协议版本。
     * @param int $timeout Socket 超时时间，单位为秒。
     * @param int $retries 连接失败重试次数。
     * @param int $retryDelay 重试间隔秒数。
     */
    public function __construct($host, $port, $apiKey, $apiVersion, $timeout, $retries = 1, $retryDelay = 0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
        $this->timeout = (int) $timeout;
        $this->retries = max(1, (int) $retries);
        $this->retryDelay = max(0, (int) $retryDelay);
    }

    /**
     * 建立 MT4 Manager Socket 连接。
     *
     * - 已持有可用连接时直接返回 true，避免重复建立。
     * - 配置 mt4.enabled 关闭时记录警告并失败返回，防止误连未启用的地址。
     * - 连接失败记录系统错误码与描述后返回 false，由 sendCommand 决定是否重试。
     *
     * @return bool true 表示连接可用；false 表示配置关闭或连接失败。
     */
    public function connect()
    {
        if ($this->socket) {
            return true;
        }

        if (!config('mt4.enabled')) {
            Log::warning('MT4 API is disabled in config.');
            return false;
        }

        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            Log::error("MT4 Connection Error: [{$errno}] {$errstr}");
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);
        return true;
    }

    /**
     * 关闭当前 Socket 连接并置空句柄。
     *
     * 每次命令收发后都会调用，避免长驻进程持续占用文件描述符；未连接时静默跳过。
     *
     * @return void
     */
    public function disconnect()
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * 发送一条旧协议 MT4 命令并规范化响应。
     *
     * 参数含义：
     * - $act 表示 MT4 命令名称（旧协议动作名，如 deposit）。
     * - $params 表示命令参数键值对。
     *
     * @param string $act 旧协议动作名。
     * @param array<string, mixed> $params 业务参数。
     * @return array<string, mixed> 含 status/message/error_code/ticket 的网关友好结果。
     */
    protected function sendCommand($act, $params = [])
    {
        // 所有旧协议命令最终都经过这里；统一门禁防止控制器或服务遗漏上层校验后建立远端连接。
        Mt4SyncGate::assertUserSyncEnabled();

        $last = [
            'status' => 'error',
            'message' => __('response.mt4_connection_failed'),
            'error_code' => 'connection_failed',
        ];

        for ($attempt = 1; $attempt <= $this->retries; $attempt++) {
            $result = $this->sendOnce((string) $act, $params);
            if (($result['status'] ?? '') === 'ok') {
                return $result;
            }
            $last = $result;
            // Only retry connection-level failures, not provider business rejections.
            if (($result['error_code'] ?? '') !== 'connection_failed') {
                return $result;
            }
            if ($attempt < $this->retries && $this->retryDelay > 0) {
                sleep($this->retryDelay);
            }
        }

        return $last;
    }

    /**
     * 单次发送旧协议命令并返回规范化结果（不做重试）。
     *
     * 每次调用都先断开旧连接再重新建立，保证命令帧在干净连接上发送；
     * 写失败、读取超时、响应超限分别返回对应 error_code 的错误结果，
     * 无论成败 finally 都会关闭连接。
     *
     * @param string $act 旧协议动作名。
     * @param array<string, mixed> $params 命令参数键值对。
     * @return array<string, mixed> 含 status/message/error_code 的规范化结果。
     */
    protected function sendOnce(string $act, array $params): array
    {
        $this->disconnect();
        try {
            $query = $this->buildQuery($act, $params);
            if (!$this->connect()) {
                return [
                    'status' => 'error',
                    'message' => __('response.mt4_connection_failed'),
                    'error_code' => 'connection_failed',
                ];
            }

            $frame = 'E' . $query . "\r\nQUIT\r\n";

            $writtenBytes = @fwrite($this->socket, $frame);
            if ($writtenBytes === false || $writtenBytes !== strlen($frame)) {
                Log::error('MT4 Write Error: command delivery is uncertain', ['act' => $act]);

                return [
                    'status' => 'error',
                    'message' => __('response.mt4_write_failed'),
                    'error_code' => 'write_failed',
                ];
            }

            try {
                $raw = $this->readUntilEnd();
            } catch (OverflowException $exception) {
                Log::error('MT4 Read Error: Response exceeds maximum size', [
                    'act' => $act,
                    'max_bytes' => self::MAX_RESPONSE_BYTES,
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Malformed MT4 response.',
                    'error_code' => 'malformed_response',
                ];
            }
            if ($raw === null) {
                Log::error('MT4 Read Error: Empty response or timeout', ['act' => $act]);

                return [
                    'status' => 'error',
                    'message' => __('response.mt4_read_timeout'),
                    'error_code' => 'read_timeout',
                ];
            }

            $fields = $this->parseLegacyResponse($raw);

            return $this->normalizeLegacyResult($fields);
        } finally {
            $this->disconnect();
        }
    }

    /**
     * 拼接旧协议查询串 act/ver/key 及业务参数。
     *
     * 顺序固定为 act -> ver -> key -> 业务字段，与旧项目完全一致；
     * 参数值必须是标量且不得包含 &、=、回车、换行等协议分隔符，
     * 否则直接抛异常拒绝发送，防止参数内容破坏帧结构；中文备注在此转为 GBK 编码。
     *
     * @param string $act 旧协议动作名。
     * @param array<string, mixed> $params 业务参数键值对。
     * @return string 拼接好的查询串。
     * @throws InvalidArgumentException 参数值非标量或包含协议分隔符时抛出。
     */
    protected function buildQuery(string $act, array $params): string
    {
        // Old order is mandatory: act -> ver -> key, then business fields.
        $parts = [
            'act=' . $act,
            'ver=' . $this->apiVersion,
            'key=' . $this->apiKey,
        ];
        foreach ($params as $key => $value) {
            if ($value === null) {
                $value = '';
            }
            if (!is_scalar($value)) {
                throw new InvalidArgumentException('MT4 command values must be scalar.');
            }
            $rawValue = (string) $value;
            if (preg_match('/[&=\r\n]/', $rawValue) === 1) {
                throw new InvalidArgumentException('MT4 command value contains a protocol delimiter.');
            }
            $encoded = mb_convert_encoding($rawValue, 'GBK', 'UTF-8');
            $parts[] = $key . '=' . $encoded;
        }

        return implode('&', $parts);
    }

    /**
     * 非阻塞读取 MT4 响应直到 end 行或墙钟超时。
     *
     * - 响应按行累积，读到 trim 后为 end 的行视为帧结束。
     * - 累计字节超过 MAX_RESPONSE_BYTES 时抛 OverflowException，防止畸形响应耗尽内存。
     * - 超时、未连接或空响应返回 null，由 sendOnce 统一转成 read_timeout。
     *
     * @return string|null 帧内响应文本（去除首尾换行）；未连接、超时或空响应时返回 null。
     * @throws OverflowException 响应累计超过 MAX_RESPONSE_BYTES 时抛出。
     */
    protected function readUntilEnd(): ?string
    {
        if (!$this->socket) {
            return null;
        }

        // Non-blocking read with wall-clock timeout, matching old client behavior.
        // Some stream wrappers used in tests do not implement stream_set_option.
        @stream_set_blocking($this->socket, false);
        $response = '';
        $deadline = microtime(true) + max(0.01, (float) $this->timeout);

        while (true) {
            $line = fgets($this->socket, 512);
            if ($line === false) {
                if (microtime(true) >= $deadline) {
                    return null;
                }
                usleep(10000);
                continue;
            }

            if (trim($line) === 'end') {
                $response = trim($response, "\r\n ");

                return $response === '' ? null : $response;
            }
            if (strlen($line) > self::MAX_RESPONSE_BYTES - strlen($response)) {
                throw new OverflowException('MT4 response exceeds the maximum size.');
            }
            $response .= $line;
            if (microtime(true) >= $deadline) {
                return null;
            }
        }
    }

    /**
     * 解析旧协议 & 分隔的 k=v 响应字段。
     *
     * 行尾多余的 & 与空字段会被忽略，字段值统一转回 UTF-8 后返回。
     *
     * @param string $raw 原始响应文本。
     * @return array<string, string> 字段名到字段值的映射。
     */
    protected function parseLegacyResponse(string $raw): array
    {
        $raw = rtrim(trim($raw, "\r\n "), '&');
        $fields = [];
        foreach (explode('&', $raw) as $part) {
            if ($part === '') {
                continue;
            }
            $kv = explode('=', $part, 2);
            $key = (string) ($kv[0] ?? '');
            $value = isset($kv[1]) ? (string) $kv[1] : '';
            if ($key === '') {
                continue;
            }
            $fields[$key] = $this->toUtf8($value);
        }

        return $fields;
    }

    /**
     * 将旧协议响应字段规范化为网关友好的统一结果。
     *
     * - _CONNECT_FAILED_ 特殊值映射为 connection_failed 错误。
     * - 无 err 字段视为畸形响应；存在 fatal_err 时优先以其为错误码。
     * - err=0 表示成功，并额外把 bal/eqy/mrg/fre/lvg 等旧别名映射为 balance/equity 等标准字段。
     *
     * @param array<string, string> $fields 解析后的响应字段。
     * @return array<string, mixed> 含 status/message/error_code 及业务字段的规范化结果。
     */
    protected function normalizeLegacyResult(array $fields): array
    {
        if (isset($fields['mt4_connect']) && $fields['mt4_connect'] === '_CONNECT_FAILED_') {
            return [
                'status' => 'error',
                'message' => __('response.mt4_connection_failed'),
                'error_code' => 'connection_failed',
                'err' => (string) ($fields['err'] ?? '9999'),
            ];
        }

        if (!array_key_exists('err', $fields) || trim((string) $fields['err']) === '') {
            $fatalError = trim((string) ($fields['fatal_err'] ?? ''));
            if ($fatalError !== '') {
                return [
                    'status' => 'error',
                    'message' => trim((string) ($fields['des'] ?? '')) ?: ('MT4 error ' . $fatalError),
                    'error_code' => $fatalError,
                    'err' => $fatalError,
                    'data' => [],
                ] + $fields;
            }

            return [
                'status' => 'error',
                'message' => 'Malformed MT4 response.',
                'error_code' => 'malformed_response',
                'err' => '',
                'data' => [],
            ] + $fields;
        }

        $err = trim((string) $fields['err']);
        $des = trim((string) ($fields['des'] ?? ''));
        $ticket = trim((string) ($fields['tck'] ?? ''));

        if ($err === '0') {
            $normalized = [
                'status' => 'ok',
                'message' => $des !== '' ? $des : 'OK',
                'err' => $err,
                'ticket' => $ticket,
                'data' => $ticket !== '' ? [$ticket] : [],
            ];

            // accountinfo field aliases used by withdrawal snapshot / balance refresh.
            if (isset($fields['bal'])) {
                $normalized['balance'] = $fields['bal'];
            }
            if (isset($fields['eqy'])) {
                $normalized['equity'] = $fields['eqy'];
            }
            if (isset($fields['mrg'])) {
                $normalized['margin'] = $fields['mrg'];
            }
            if (isset($fields['fre'])) {
                $normalized['free_margin'] = $fields['fre'];
            }
            if (isset($fields['lvg'])) {
                $normalized['leverage'] = $fields['lvg'];
            }

            // Keep original provider keys for debugging / advanced callers.
            foreach ($fields as $key => $value) {
                if (!array_key_exists($key, $normalized)) {
                    $normalized[$key] = $value;
                }
            }

            return $normalized;
        }

        return [
            'status' => 'error',
            'message' => $des !== '' ? $des : ('MT4 error ' . $err),
            'error_code' => $err,
            'err' => $err,
            'data' => [],
        ] + $fields;
    }

    /**
     * 将 MT4 响应字段值尽量转回 UTF-8。
     *
     * 旧服务返回中文备注时可能使用 GBK/BIG5 编码，先探测编码再转换；
     * 探测失败按 GBK 处理，转换失败原样返回，保证 message 可读且不抛异常。
     *
     * @param string $value 原始响应字段值。
     * @return string 转换为 UTF-8 后的值；无法识别或转换时返回原值。
     */
    protected function toUtf8(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        $encoding = mb_detect_encoding($value, ['UTF-8', 'GBK', 'BIG5', 'ASCII'], true);
        if ($encoding === false) {
            $encoding = 'GBK';
        }
        if ($encoding !== 'UTF-8') {
            $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);
            if (is_string($converted)) {
                return $converted;
            }
        }

        return $value;
    }

    /**
     * 向 MT4 发送开户注册命令。
     *
     * 兼容两套字段命名（user_id/acc、user_name/name 等），新字段优先、旧字段回退；
     * 其中 zip 传入 parent_id（直属上级），cny 传入 country，与旧协议字段含义一致。
     *
     * @param array<string, mixed> $data 业务侧开户数据，键名见各取值的回退链。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示注册成功，否则未成功。
     */
    public function registerUser($data)
    {
        return $this->sendCommand('register', [
            'acc' => $data['user_id'] ?? ($data['acc'] ?? ''),
            'nam' => $data['name'] ?? ($data['user_name'] ?? ''),
            'ctp' => $data['password'] ?? ($data['pwd'] ?? ''),
            'eml' => $data['email'] ?? '',
            'tel' => $data['phone'] ?? '',
            'idn' => $data['id_card'] ?? ($data['IDcard_no'] ?? ''),
            'zip' => $data['parent_id'] ?? ($data['zipcode'] ?? ''),
            'grp' => $data['group'] ?? ($data['user_grp_name'] ?? ''),
            'cny' => $data['country'] ?? ($data['str_rala'] ?? ''),
            'lvg' => $data['leverage'] ?? 100,
        ]);
    }

    /**
     * 向 MT4 发送入金（deposit）命令。
     *
     * @param int|string $userId MT4 登录账号，对应当前业务 user_id。
     * @param int|string|float $amount 入金金额，随 amt 字段下发，由 MT4 按账户货币解释。
     * @param string $comment 入金备注，将按 GBK 编码后随 cmt 下发。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 且 err=0 表示入金已记账。
     */
    public function deposit($userId, $amount, $comment)
    {
        return $this->sendCommand('deposit', [
            'acc' => $userId,
            'amt' => $amount,
            'cmt' => $comment,
        ]);
    }

    /**
     * 向 MT4 发送出金（withdrawal）命令。
     *
     * @param int|string $userId MT4 登录账号。
     * @param int|string|float $amount 出金金额。
     * @param string $comment 出金备注。
     * @return array<string, mixed> 规范化 MT4 响应；status=error 时上层不得扣减本地余额。
     */
    public function withdrawal($userId, $amount, $comment)
    {
        return $this->sendCommand('withdrawal', [
            'acc' => $userId,
            'amt' => $amount,
            'cmt' => $comment,
        ]);
    }

    /**
     * 校验 MT4 账号登录密码是否匹配。
     *
     * @param int|string $userId MT4 登录账号。
     * @param string $password 待校验的明文密码，仅随本次命令帧传输。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示密码校验通过。
     */
    public function verifyPassword($userId, $password)
    {
        return $this->sendCommand('verify', [
            'acc' => $userId,
            'ctp' => $password,
        ]);
    }

    /**
     * 向 MT4 发送信用赠金入账（credit-in）命令。
     *
     * @param int|string $userId MT4 登录账号。
     * @param int|string|float $amount 赠金金额。
     * @param string $comment 赠金备注。
     * @param int|string $expires 赠金有效期天数，单位天，默认 999，对应旧协议 exp 字段。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示赠金已入账。
     */
    public function creditIn($userId, $amount, $comment, $expires = 999)
    {
        return $this->sendCommand('credit-in', [
            'acc' => $userId,
            'amt' => $amount,
            'cmt' => $comment,
            'exp' => $expires,
        ]);
    }

    /**
     * 查询 MT4 账号信息并映射为网关友好结构。
     *
     * 返回字段同时兼容新命名（balance/equity/margin/free_margin/leverage/group）
     * 与旧字段（bal/eqy/mrg/fre/lvg/grp）；响应畸形或失败时原样透出错误结果。
     *
     * @param int|string $userId MT4 登录账号。
     * @return array<string, mixed> status=ok 时为含 account_id、balance、equity 等字段的账号快照；否则为错误结果。
     */
    public function getAccountInfo($userId)
    {
        $res = $this->sendCommand('accountinfo', ['acc' => $userId]);
        if (($res['status'] ?? '') === 'ok') {
            if (!array_key_exists('err', $res) || trim((string) $res['err']) !== '0') {
                return [
                    'status' => 'error',
                    'error_code' => 'malformed_response',
                    'message' => 'Malformed MT4 accountinfo response.',
                ];
            }

            return [
                'status' => 'ok',
                'account_id' => $res['acc'] ?? null,
                'balance' => $res['balance'] ?? ($res['bal'] ?? null),
                'is_enabled' => $res['is_enabled'] ?? ($res['ena'] ?? null),
                'group' => $res['group'] ?? ($res['grp'] ?? null),
                'equity' => $res['equity'] ?? ($res['eqy'] ?? 0),
                'margin' => $res['margin'] ?? ($res['mrg'] ?? 0),
                'free_margin' => $res['free_margin'] ?? ($res['fre'] ?? ($res['fmg'] ?? 0)),
                'leverage' => $res['leverage'] ?? ($res['lvg'] ?? 0),
                'err' => $res['err'] ?? '0',
                'message' => $res['message'] ?? 'OK',
            ];
        }

        return $res;
    }

    /**
     * 修改 MT4 账号密码。
     *
     * 传入旧密码时走 change_password（需校验旧密码），否则走 reset_password（直接重置）；
     * 忘记密码重置场景不要传旧密码，管理员强制重置同样走 reset_password。
     *
     * @param int|string $userId MT4 登录账号。
     * @param string $newPwd 新密码明文。
     * @param string $oldPwd 旧密码明文，为空表示直接重置。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示密码已更新。
     */
    public function changePassword($userId, $newPwd, $oldPwd = '')
    {
        if ($oldPwd !== '') {
            return $this->sendCommand('change_password', [
                'acc' => $userId,
                'ctp' => $oldPwd,
                'ntp' => $newPwd,
            ]);
        }

        return $this->sendCommand('reset_password', [
            'acc' => $userId,
            'ctp' => $newPwd,
        ]);
    }

    /**
     * 锁定 MT4 账号，锁定后用户无法登录交易。
     *
     * @param int|string $userId MT4 登录账号。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示锁定成功。
     */
    public function lockUser($userId)
    {
        return $this->sendCommand('lock_user', ['acc' => $userId]);
    }

    /**
     * 解除 MT4 账号锁定，恢复登录交易能力。
     *
     * @param int|string $userId MT4 登录账号。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示解锁成功。
     */
    public function unlockUser($userId)
    {
        return $this->sendCommand('unlock_user', ['acc' => $userId]);
    }

    /**
     * 启用 MT4 账号（允许登录，区别于解锁的锁定状态）。
     *
     * @param int|string $userId MT4 登录账号。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示启用成功。
     */
    public function enableUser($userId)
    {
        return $this->sendCommand('enable_user', ['acc' => $userId]);
    }

    /**
     * 停用 MT4 账号（禁止登录，区别于锁定的临时状态）。
     *
     * @param int|string $userId MT4 登录账号。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示停用成功。
     */
    public function disableUser($userId)
    {
        return $this->sendCommand('disable_user', ['acc' => $userId]);
    }

    /**
     * 修改 MT4 账号所属交易组。
     *
     * @param int|string $userId MT4 登录账号。
     * @param string $newGroup 目标 MT4 组名，例如 GMTK-P。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示组别已更新。
     */
    public function changeGroup($userId, $newGroup)
    {
        return $this->sendCommand('change_group', [
            'acc' => $userId,
            'grp' => $newGroup,
        ]);
    }

    /**
     * 一次更新 MT4 用户交易组和杠杆。
     *
     * 业务逻辑说明：
     * - update_user 是旧协议已经支持的幂等设置命令，同一目标值可安全重试。
     * - 组别和杠杆放在同一帧中，避免 change_group 成功但 change_leverage 失败的部分成功状态。
     * - 上层只有在 status=ok 且 err=0 时才能更新本地 user_infos 镜像。
     *
     * @param int|string $userId MT4 登录账号，对应当前业务 user_id。
     * @param string $group 目标 MT4 真实组名，例如 GMTK-P。
     * @param int $leverage 目标杠杆，例如 200。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示明确成功，error 表示未成功。
     */
    public function updateUserTradingProfile($userId, $group, $leverage)
    {
        return $this->sendCommand('update_user', [
            'acc' => $userId,
            'grp' => $group,
            'lvg' => $leverage,
        ]);
    }

    /**
     * 一次更新 MT4 用户的直属上级和五段代理关系码。
     *
     * 业务逻辑说明：
     * - zip 是旧 MT4 协议中的直属上级代理账号，0 表示平台直属客户。
     * - cny 是按代理等级位置拼接的五段关系码，未占用层级固定补 0000。
     * - acc、zip、cny 必须放在同一帧发送，避免远端只更新直属上级却保留旧关系码。
     * - 上层只有在返回 status=ok 且 err=0 后，才能提交本地 parent_id、family_tree 和闭包关系。
     *
     * @param int|string $userId MT4 登录账号，对应业务 user_infos.user_id。
     * @param int|string $parentId 新直属上级代理账号；0 表示平台根节点。
     * @param string $relationshipCode 旧协议五段代理关系码，例如 00002002000000000000。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示远端明确完成更新。
     */
    public function updateUserHierarchy($userId, $parentId, string $relationshipCode)
    {
        return $this->sendCommand('update_user', [
            'acc' => $userId,
            'zip' => $parentId,
            'cny' => $relationshipCode,
        ]);
    }

    /**
     * 更新 MT4 账号备注信息。
     *
     * 复用 update_user 的自定义字段片段（cmt），与旧项目维护备注的方式一致；
     * 只更新备注，不影响组别与杠杆。
     *
     * @param int|string $userId MT4 登录账号。
     * @param string $comment 新备注文本。
     * @return array<string, mixed> 规范化 MT4 响应；status=ok 表示备注已更新。
     */
    public function updateComment($userId, $comment)
    {
        // Old project used update_user with custom field fragments (cmt/sta/...).
        return $this->sendCommand('update_user', [
            'acc' => $userId,
            'cmt' => $comment,
        ]);
    }

    /**
     * Best-effort order close using update_user style extension if server supports it.
     * Kept for admin risk force-close path; response mapping still uses err/tck.
     */
    public function closeOrder($userId, $ticket, $comment = '')
    {
        return $this->sendCommand('ORDER_CLOSE', [
            'acc' => $userId,
            'tkt' => $ticket,
            'cmt' => $comment,
        ]);
    }

    /**
     * 对象销毁时关闭残留 Socket 连接，防止长驻进程泄漏文件描述符。
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
