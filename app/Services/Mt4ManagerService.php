<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class Mt4ManagerService
{
    protected $host;
    protected $port;
    protected $apiKey;
    protected $apiVersion;
    protected $timeout;
    protected $socket = null;

    public function __construct($host, $port, $apiKey, $apiVersion, $timeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
        $this->timeout = $timeout;
    }

    /**
     * Establish socket connection
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
     * Close socket
     */
    public function disconnect()
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send command and read response
     */
    protected function sendCommand($cmd, $params = [])
    {
        if (!$this->connect()) {
            return ['status' => 'error', 'message' => 'Connection failed'];
        }

        // Add API Key and Version to params
        $params['key'] = $this->apiKey;
        $params['ver'] = $this->apiVersion;

        // Build command string: CMD:param1=val1|param2=val2...
        $paramStr = [];
        foreach ($params as $k => $v) {
            // Encode to GBK for MT4
            $val = mb_convert_encoding($v, 'GBK', 'UTF-8');
            $paramStr[] = "{$k}={$val}";
        }
        $fullCmd = "{$cmd}:" . implode('|', $paramStr) . "\n";

        fwrite($this->socket, $fullCmd);

        $response = fgets($this->socket, 4096);
        if ($response === false) {
            Log::error("MT4 Read Error: Empty response or timeout");
            return ['status' => 'error', 'message' => 'Read timeout or empty response'];
        }

        // Decode from GBK to UTF-8
        $response = mb_convert_encoding(trim($response), 'UTF-8', 'GBK');
        
        // Parse response: STATUS|MSG|DATA...
        $parts = explode('|', $response);
        $status = strtolower($parts[0] ?? 'error');
        
        return [
            'status'  => $status,
            'message' => $parts[1] ?? '',
            'data'    => array_slice($parts, 2),
        ];
    }

    /**
     * Register user on MT4
     */
    public function registerUser($data)
    {
        $params = [
            'nam' => $data['name'] ?? '',
            'grp' => $data['group'] ?? '',
            'pwd' => $data['password'] ?? '',
            'cny' => $data['country'] ?? '',
            'lvg' => $data['leverage'] ?? 100,
            'eml' => $data['email'] ?? '',
            'tel' => $data['phone'] ?? '',
            'cty' => $data['city'] ?? '',
            'sta' => $data['state'] ?? '',
            'zip' => $data['zipcode'] ?? '',
            'adr' => $data['address'] ?? '',
            'id'  => $data['id_card'] ?? '',
            'phs' => $data['phone_pwd'] ?? '',
        ];

        return $this->sendCommand('USER_RECORD_NEW', $params);
    }

    /**
     * Deposit operation
     */
    public function deposit($userId, $amount, $comment)
    {
        return $this->sendCommand('USER_DEPOSIT', [
            'acc' => $userId,
            'amt' => $amount,
            'cmt' => $comment,
        ]);
    }

    /**
     * Withdrawal operation
     */
    public function withdrawal($userId, $amount, $comment)
    {
        return $this->sendCommand('USER_WITHDRAW', [
            'acc' => $userId,
            'amt' => $amount,
            'cmt' => $comment,
        ]);
    }

    /**
     * Get account info (balance, equity, margin, leverage)
     */
    public function getAccountInfo($userId)
    {
        $res = $this->sendCommand('USER_INFO_GET', ['acc' => $userId]);
        if ($res['status'] === 'ok' && !empty($res['data'])) {
            // Assume data order: balance, equity, margin, free_margin, leverage
            $d = $res['data'];
            return [
                'status'      => 'ok',
                'balance'     => $d[0] ?? 0,
                'equity'      => $d[1] ?? 0,
                'margin'      => $d[2] ?? 0,
                'free_margin' => $d[3] ?? 0,
                'leverage'    => $d[4] ?? 0,
            ];
        }
        return $res;
    }

    /**
     * Change MT4 password
     */
    public function changePassword($userId, $newPwd)
    {
        return $this->sendCommand('USER_PASS_CHANGE', [
            'acc' => $userId,
            'pwd' => $newPwd,
        ]);
    }

    /**
     * Disable trading
     */
    public function lockUser($userId)
    {
        return $this->sendCommand('USER_LOCK', [
            'acc' => $userId,
            'flg' => 1,
        ]);
    }

    /**
     * Enable trading
     */
    public function unlockUser($userId)
    {
        return $this->sendCommand('USER_LOCK', [
            'acc' => $userId,
            'flg' => 0,
        ]);
    }

    /**
     * Change MT4 group
     */
    public function changeGroup($userId, $newGroup)
    {
        return $this->sendCommand('USER_GROUP_CHANGE', [
            'acc' => $userId,
            'grp' => $newGroup,
        ]);
    }

    /**
     * Update comment field
     */
    public function updateComment($userId, $comment)
    {
        return $this->sendCommand('USER_COMMENT_UPDATE', [
            'acc' => $userId,
            'cmt' => $comment,
        ]);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
