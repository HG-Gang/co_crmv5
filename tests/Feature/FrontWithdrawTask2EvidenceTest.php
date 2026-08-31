<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:18
 */

/**
 * FrontWithdrawTask2EvidenceTest
 *
 * 文件功能：
 * - 验证前台出金任务 2 证据：两个真实 PHP worker 竞争同一余额仅一个提交 outbox、真实 Layui 提交回调防双发并复用幂等键、出金路由冒烟覆盖现代页面与旧契约。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Services\Withdrawal\WithdrawalAccountSnapshot;
use App\Services\Withdrawal\WithdrawalOrderService;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\Feature\Concerns\ManagesSharedSystemConfigFixtures;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

final class FrontWithdrawTask2EvidenceTest extends TestCase
{
    use ExecutesJavascriptScenarios;
    use ManagesSharedSystemConfigFixtures;
    use ReadsAggregatedLayuiScripts;

    /**
     * 出金任务 2 证据用例的固定业务用户 ID（412372009，与结算夹具同段错开）。
     * 脚本证据与接口断言都围绕该用户生成。
     * @var int
     */
    private const USER_ID = 412372009;

    /**
     * 改写前的出金相关 system_configs 行快照。tearDown 据此恢复，防止配置改写泄漏到共享库。
     * @var array<int, array<string, mixed>>
     */
    private $configSnapshot = [];

    /**
     * 本夹具接管的出金配置键清单（开关、时段、限额、风控）。
     * 快照、恢复与"夹具拥有行"登记都按它圈定范围。
     * @var array<int, string>
     */
    private $configKeys = [
        'withdrawal_enabled', 'withdrawal_weekend_enabled', 'withdrawal_start_time', 'withdrawal_end_time',
        'withdraw_min_amount', 'withdraw_max_amount', 'withdraw_risk_rate_limit', 'withdraw_check_open',
        'withdrawal_fee_rate', 'withdrawal_fixed_fee_usd', 'withdraw_exchange_rate_cny',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->acquireSharedSystemConfigFixtureLock();
        $this->configSnapshot = DB::table('system_configs')->useWritePdo()
            ->whereIn('key', $this->configKeys)->orderBy('key')->get()
            ->map(static function ($row): array { return (array) $row; })->all();
        $this->captureSharedSystemConfigFixtureOwnedState($this->configKeys, $this->configSnapshot);
        $this->cleanupFixture();
        $this->configureWithdrawals();
        $this->insertFixtureUser();
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixture();
            $this->restoreSharedSystemConfigSnapshot($this->configKeys, $this->configSnapshot);
        } finally {
            $this->releaseSharedSystemConfigFixtureLock();
        }
        parent::tearDown();
    }

    public function test_two_real_php_workers_race_one_balance_and_commit_one_outbox(): void
    {
        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'withdraw-race-' . bin2hex(random_bytes(6));
        mkdir($temp, 0700, true);
        $worker = $temp . DIRECTORY_SEPARATOR . 'worker.php';
        $script = <<<'PHP'
<?php
require getenv('COMPOSER_AUTOLOAD');
$app = require getenv('LARAVEL_BOOTSTRAP');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$args = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$ready = $args['ready'];
$go = $args['go'];
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 15;
while (!is_file($go) && microtime(true) < $deadline) { usleep(10000); }
$result = [];
try {
    $app->instance(App\Contracts\WithdrawalAccountSnapshotGateway::class, new class implements App\Contracts\WithdrawalAccountSnapshotGateway {
        public function snapshot(int $userId): App\Services\Withdrawal\WithdrawalAccountSnapshot
        {
            return new App\Services\Withdrawal\WithdrawalAccountSnapshot('100.00', '100.00');
        }
    });
    $user = App\Models\UserInfo::query()->where('user_id', (int) $args['user_id'])->firstOrFail();
    $created = (new App\Services\Withdrawal\WithdrawalOrderService(
        app(App\Contracts\WithdrawalAccountSnapshotGateway::class)
    ))->createOrRetrieve(
        $user,
        App\Support\Money::fromDecimalString('100.00', '10.00', '500000.00'),
        $args['key']
    );
    $result = ['status' => 'created', 'created' => (bool) $created['created']];
} catch (Throwable $e) {
    $result = ['status' => $e instanceof DomainException ? $e->getMessage() : get_class($e) . ':' . $e->getMessage()];
}
file_put_contents($args['result'], json_encode($result, JSON_THROW_ON_ERROR));
PHP;
        file_put_contents($worker, $script);
        $processes = [];
        $results = [];
        try {
            $go = $temp . DIRECTORY_SEPARATOR . 'go';
            foreach (['race-a', 'race-b'] as $key) {
                $payload = base64_encode(json_encode([
                    'user_id' => self::USER_ID,
                    'key' => $key,
                    'ready' => $temp . DIRECTORY_SEPARATOR . $key . '.ready',
                    'result' => $temp . DIRECTORY_SEPARATOR . $key . '.result',
                    'go' => $go,
                ], JSON_THROW_ON_ERROR));
                $pipes = [];
                $processes[$key] = proc_open(
                    [PHP_BINARY, $worker, $payload],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    base_path(),
                    array_merge(getenv(), [
                        'COMPOSER_AUTOLOAD' => base_path('vendor/autoload.php'),
                        'LARAVEL_BOOTSTRAP' => base_path('bootstrap/app.php'),
                        'APP_ENV' => (string) env('APP_ENV', 'testing'),
                        'DB_CONNECTION' => (string) env('DB_CONNECTION', 'mysql'),
                        'DB_HOST' => (string) env('DB_HOST'),
                        'DB_PORT' => (string) env('DB_PORT'),
                        'DB_DATABASE' => (string) env('DB_DATABASE'),
                        'DB_USERNAME' => (string) env('DB_USERNAME'),
                        'DB_PASSWORD' => (string) env('DB_PASSWORD'),
                    ])
                );
                $this->assertIsResource($processes[$key]);
            }
            $deadline = microtime(true) + 10;
            while ((!is_file($temp . DIRECTORY_SEPARATOR . 'race-a.ready') || !is_file($temp . DIRECTORY_SEPARATOR . 'race-b.ready'))
                && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($temp . DIRECTORY_SEPARATOR . 'race-a.ready');
            $this->assertFileExists($temp . DIRECTORY_SEPARATOR . 'race-b.ready');
            touch($go);
            foreach ($processes as $key => $process) {
                $exit = proc_close($process);
                $this->assertSame(0, $exit, $key . ' worker failed.');
                $resultPath = $temp . DIRECTORY_SEPARATOR . $key . '.result';
                $this->assertFileExists($resultPath);
                $results[] = json_decode((string) file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            foreach (glob($temp . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($temp);
        }

        $this->assertSame(1, DB::table('withdraw_records')->where('user_id', self::USER_ID)->count());
        $this->assertSame(1, DB::table('withdraw_settlement_outbox as outbox')
            ->join('withdraw_records as withdraw', 'withdraw.id', '=', 'outbox.withdraw_record_id')
            ->where('withdraw.user_id', self::USER_ID)->count());
        $statuses = array_column($results, 'status');
        $this->assertContains('created', $statuses);
        $this->assertTrue((bool) array_filter($statuses, static function (string $status): bool {
            return in_array($status, ['insufficient_balance', 'reservation_lock_unavailable'], true);
        }));
    }

    public function test_real_layui_withdraw_submit_callback_blocks_double_send_and_reuses_key(): void
    {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $functions = [];
        foreach (['normalizeWithdrawAmount', 'createWithdrawIdempotencyKey', 'withdrawIdempotencyStorageKey', 'restoreWithdrawIdempotencyState', 'persistWithdrawIdempotencyState', 'prepareWithdrawIdempotencyKey', 'currentWithdrawIdempotencyKey', 'clearWithdrawIdempotencyKey', 'setWithdrawSubmitting', 'isSuccess', 'validateSubmit', 'submitWithdraw'] as $name) {
            $functions[$name] = $this->javascriptFunctionSource($source, $name);
        }
        $callbackStart = strpos($source, "form.on('submit(withdrawSubmit)', function (data) {");
        $this->assertNotFalse($callbackStart);
        $callbackEnd = strpos($source, "\n            });", $callbackStart);
        $this->assertNotFalse($callbackEnd);
        $callback = substr($source, $callbackStart, $callbackEnd - $callbackStart + strlen("\n            });"));
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var pageData={isAllowed:true,min:10,max:500,availableAmount:100,feeRate:0,fixedFee:0};
var withdrawIdempotencyKey=null,withdrawIdempotencyAmount=null,withdrawIdempotencyUserId=null,withdrawIdempotencyStorageReady=true,withdrawIdempotencyFailureReason=null,withdrawSubmitting=false;
var storage={},uuid=0,requests=[],pending=[]; var window={localStorage:{getItem:function(k){return storage[k]||null;},setItem:function(k,v){storage[k]=String(v);},removeItem:function(k){delete storage[k];}},crypto:{randomUUID:function(){uuid++;return 'race-key-'+uuid;}}};
var layer={msg:function(){}}; var form={on:function(name,cb){if(name==='submit(withdrawSubmit)'){form.callback=cb;}},render:function(){}};
var CrmAjax={request:function(options){requests.push({headers:options.headers,data:options.data});pending.push(options);}};
function t(k){return k;} function renderAllowedState(){} function clearWithdrawEditableFields(){} function loadPageConfig(){} function renderHistoryTable(){} function calculateAmount(){}
function $(selector){return {val:function(){return selector==='#withdrawUserId'?'412372009':'';},is:function(){return true;},prop:function(){return this;},toggleClass:function(){return this;},text:function(){return this;},addClass:function(){return this;},removeClass:function(){return this;}};}
{$functions['normalizeWithdrawAmount']}
{$functions['createWithdrawIdempotencyKey']}
{$functions['withdrawIdempotencyStorageKey']}
{$functions['restoreWithdrawIdempotencyState']}
{$functions['persistWithdrawIdempotencyState']}
{$functions['prepareWithdrawIdempotencyKey']}
{$functions['currentWithdrawIdempotencyKey']}
{$functions['clearWithdrawIdempotencyKey']}
{$functions['setWithdrawSubmitting']}
{$functions['isSuccess']}
{$functions['validateSubmit']}
{$functions['submitWithdraw']}
{$callback}
form.callback({field:{amount:'100.0',password:'password'}});
form.callback({field:{amount:'100.00',password:'password'}});
pending[0].error({code:5000,message:'transport uncertain'});
form.callback({field:{amount:'100.00',password:'password'}});
pending[1].success({code:1001,message:'created'});
console.log(JSON.stringify({requestCount:requests.length,keys:requests.map(function(r){return r.headers['Idempotency-Key'];}),amounts:requests.map(function(r){return r.data.amount;}),uuid:uuid,key:withdrawIdempotencyKey}));
JS
        );
        $this->assertSame(2, $result['requestCount']);
        $this->assertSame($result['keys'][0], $result['keys'][1]);
        $this->assertSame(['100.00', '100.00'], $result['amounts']);
        $this->assertSame(1, $result['uuid']);
        $this->assertNull($result['key']);
    }

    public function test_withdraw_route_smoke_covers_modern_page_and_legacy_contracts(): void
    {
        $routes = collect(app('router')->getRoutes())->mapWithKeys(static function ($route): array {
            return [$route->uri() => $route];
        });
        foreach (['api/front/withdrawals/form-options', 'api/front/withdrawals/submissions', 'api/front/withdrawals/history', 'front/withdraw', 'user/withdraw_request', 'user/withdraw_request_OTC'] as $uri) {
            $this->assertTrue($routes->has($uri), 'Missing route ' . $uri);
        }
        $actions = [
            'api/front/withdrawals/form-options' => ['front_api_withdrawals_form_options', '@withdrawPage'],
            'api/front/withdrawals/submissions' => ['front_api_withdrawals_submissions', '@submitWithdraw'],
            'api/front/withdrawals/history' => ['front_api_withdrawals_history', '@withdrawHistory'],
            'front/withdraw' => ['front_page_withdraw', 'Closure'],
            'user/withdraw_request' => ['legacy_user_withdraw_request', '@withdraw_request'],
            'user/withdraw_request_OTC' => ['legacy_user_withdraw_request_otc', '@withdraw_request_OTC'],
        ];
        foreach ($actions as $uri => [$name, $actionSuffix]) {
            $this->assertSame($name, $routes[$uri]->getName());
            $this->assertStringEndsWith($actionSuffix, $routes[$uri]->getActionName());
        }
        $this->assertSame(['GET', 'HEAD'], $routes['api/front/withdrawals/form-options']->methods());
        $this->assertSame(['POST'], $routes['api/front/withdrawals/submissions']->methods());
        $this->assertSame(['GET', 'HEAD'], $routes['api/front/withdrawals/history']->methods());
        $this->assertSame(['GET', 'HEAD'], $routes['front/withdraw']->methods());
        $this->assertSame(['POST'], $routes['user/withdraw_request']->methods());
        $this->assertSame(['POST'], $routes['user/withdraw_request_OTC']->methods());
        foreach (['api/front/withdrawals/form-options', 'api/front/withdrawals/submissions', 'api/front/withdrawals/history'] as $uri) {
            $middleware = $routes[$uri]->gatherMiddleware();
            $this->assertNotEmpty($middleware);
            $middlewareText = implode('|', array_map('strval', $middleware));
            $this->assertTrue(
                strpos($middlewareText, 'JwtAuthMiddleware') !== false
                    || strpos($middlewareText, 'jwt') !== false
                    || strpos($middlewareText, 'auth') !== false,
                'Modern withdrawal route is missing authentication middleware.'
            );
        }
        $before = DB::table('withdraw_records')->count();
        foreach (['/api/front/withdrawals/form-options', '/api/front/withdrawals/history'] as $uri) {
            $code = (int) $this->getJson($uri)->assertOk()->json('code');
            $this->assertContains($code, [ResponseCode::TOKEN_MISSING, ResponseCode::AUTH_FAILED]);
        }
        foreach (['/api/front/withdrawals/submissions', '/user/withdraw_request', '/user/withdraw_request_OTC'] as $uri) {
            $code = (int) $this->postJson($uri, ['amount' => '100.00'])->assertOk()->json('code');
            $this->assertContains($code, [ResponseCode::TOKEN_MISSING, ResponseCode::AUTH_FAILED]);
        }
        $this->get('/front/withdraw')->assertOk();
        $this->assertSame($before, DB::table('withdraw_records')->count());
    }

    private function insertFixtureUser(): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => self::USER_ID, 'email' => 'withdraw-task2-evidence@example.test',
            'password' => Hash::make('password'), 'account_type' => 2, 'role_id' => 0,
            'is_enabled' => 1, 'is_cancelled' => 0, 'source_type' => 0, 'jwt_token_id' => '',
            'last_login_ip' => '', 'last_login_at' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => self::USER_ID, 'login_id' => $loginId, 'user_name' => 'withdraw-task2-evidence',
            'phone' => '13937200009', 'gender' => 1, 'account_type' => 2, 'parent_id' => 0, 'family_tree' => '',
            'group_id' => 0, 'level_id' => 0, 'comm_rate' => 0, 'auth_status' => 1, 'total_funds' => '100.00',
            'used_margin' => '0.00', 'avail_margin' => '100.00', 'equity' => '100.00', 'effective_credit' => '0.00',
            'risk_ratio' => '0.00', 'leverage' => 100, 'is_ecn' => 0, 'is_withdrawal_allowed' => 0, 'is_deposit_allowed' => 0,
            'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
        DB::table('user_auths')->insert([
            'user_id' => self::USER_ID, 'bank_no' => 'TASK2-EVIDENCE-BANK', 'bank_name' => 'Task2 Bank', 'bank_card_img' => '',
            'bank_card_img_tmp' => '', 'bank_addr' => 'Task2 Branch', 'bank_addr_tmp' => '', 'bank_status' => 2, 'bank_remarks' => '',
            'id_card_no' => 'ID' . self::USER_ID, 'id_card_status' => 2, 'id_card_front' => '', 'id_card_back' => '',
            'id_card_remarks' => '', 'is_bank_synced' => 0, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
    }

    private function configureWithdrawals(): void
    {
        $values = [
            'withdrawal_enabled' => '1', 'withdrawal_weekend_enabled' => '1',
            'withdrawal_start_time' => '', 'withdrawal_end_time' => '',
            'withdraw_min_amount' => '10.00', 'withdraw_max_amount' => '500000.00',
            'withdraw_risk_rate_limit' => '0.00000000', 'withdraw_check_open' => '0',
            'withdrawal_fee_rate' => '0.00000000', 'withdrawal_fixed_fee_usd' => '0.00',
            'withdraw_exchange_rate_cny' => '7.20000000',
        ];
        $now = time();
        foreach ($values as $key => $value) {
            $row = DB::table('system_configs')->useWritePdo()->where('key', $key)->first();
            if ($row === null) {
                DB::table('system_configs')->insert([
                    'key' => $key,
                    'value' => $value,
                    'group' => 'withdraw',
                    'description' => 'Withdrawal Task 2 evidence fixture',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);

                continue;
            }

            DB::table('system_configs')
                ->where('id', $row->id)
                ->where('key', $key)
                ->update(['value' => $value, 'updated_at' => $now]);
        }
        $owned = DB::table('system_configs')->whereIn('key', $this->configKeys)->orderBy('key')->get()
            ->map(static function ($row): array { return (array) $row; })->all();
        $this->assertCount(count($this->configKeys), $owned);
        $this->captureSharedSystemConfigFixtureOwnedState($this->configKeys, $owned);
    }

    private function javascriptFunctionSource(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertNotFalse($start, 'Missing JavaScript function ' . $name);
        $open = strpos($source, '{', $start);
        $this->assertNotFalse($open);
        $depth = 0;
        $length = strlen($source);
        for ($offset = $open; $offset < $length; $offset++) {
            if ($source[$offset] === '{') {
                $depth++;
            } elseif ($source[$offset] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $offset - $start + 1);
                }
            }
        }
        $this->fail('Unclosed JavaScript function ' . $name);
    }

    private function cleanupFixture(): void
    {
        DB::table('withdraw_settlement_outbox')->whereIn('withdraw_record_id', function ($query): void {
            $query->select('id')->from('withdraw_records')->where('user_id', self::USER_ID);
        })->delete();
        DB::table('withdraw_records')->where('user_id', self::USER_ID)->delete();
        DB::table('user_auths')->where('user_id', self::USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::USER_ID)->delete();
        DB::table('user_logins')->where('user_id', self::USER_ID)->delete();
    }
}
