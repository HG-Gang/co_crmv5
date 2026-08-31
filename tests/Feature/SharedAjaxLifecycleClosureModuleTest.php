<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 04:23
 */

/**
 * SharedAjaxLifecycleClosureModuleTest
 *
 * 文件功能：
 * - 验证共享 JS 运行器与 Ajax 生命周期契约：taskkill 失败回退、正常退出进程树清理、注册表树与退出标识解码、身份辅助器的孤儿/伪造/未来注册防护、原生探针与 createtoken 匹配等状态机。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\AssertionFailedError;
use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\TestCase;

class SharedAjaxLifecycleClosureModuleTest extends TestCase
{
    use ExecutesJavascriptScenarios;

    /** @dataProvider javascriptRunnerPrimaryTerminationProvider */
    public function test_js_runner_primary_taskkill_failure_fallback(
        bool $forcePrimaryTaskkillFailure
    ): void
    {
        if ($forcePrimaryTaskkillFailure && PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The injected primary taskkill failure is Windows-specific.');
        }
        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-runner-');
        $this->assertIsString($pidPath, 'The process marker file could not be created.');
        $this->assertTrue(unlink($pidPath), 'The empty process marker file could not be removed.');
        $pidPathJson = json_encode($pidPath, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($pidPathJson);
        $runnerTemporaryPaths = [];
        $registryContents = null;
        $previousParentOnlyEnvironment = getenv('RUNNER_PARENT_ONLY_ENV');
        $this->assertTrue(putenv('RUNNER_PARENT_ONLY_ENV=must-not-leak'));
        $temporaryFileFactory = function (string $directory, string $prefix) use (&$runnerTemporaryPaths): string {
            $path = tempnam($directory, $prefix);
            if (!is_string($path)) {
                throw new \RuntimeException('The runner temporary file could not be created.');
            }
            $runnerTemporaryPaths[] = $path;

            return $path;
        };
        $failure = null;
        $processIds = [];
        $injectedTaskkillFailures = 0;
        $windowsCommandInterceptor = function (array $command) use (
            $forcePrimaryTaskkillFailure,
            &$injectedTaskkillFailures
        ): ?array {
            if (
                $forcePrimaryTaskkillFailure
                && $injectedTaskkillFailures === 0
                && strtolower(basename($command[0])) === 'taskkill.exe'
                && in_array('/T', $command, true)
            ) {
                $injectedTaskkillFailures++;

                return [
                    'completed' => true,
                    'exitCode' => 1,
                    'stdout' => '',
                    'stderr' => 'injected primary taskkill failure',
                ];
            }

            return null;
        };
        if (PHP_OS_FAMILY === 'Windows') {
            $this->prepareWindowsProcessTreeControlForTest();
        }
        $startedAt = microtime(true);

        $temporaryFileDeleter = function (string $path) use (&$registryContents, &$runnerTemporaryPaths): bool {
            if (isset($runnerTemporaryPaths[1]) && $path === $runnerTemporaryPaths[1]) {
                $registryContents = file_get_contents($path);
            }

            return unlink($path);
        };

        try {
            try {
                // 两层 Node 子进程必须在运行超时前完成预加载与环境隔离取证；
                // 0.75 秒只扩大场景初始化窗口，总硬截止仍由下方断言约束。
                // 全量回归时整机 CPU 竞争会让 taskkill 与进程退出轮询变慢，
                // 5 秒上限仍能证明硬截止必然触发，而不是无限挂起。
                $this->executeJavascriptJson(<<<JS
'use strict';
var childProcess = require('child_process');
var fs = require('fs');
var pidPath = {$pidPathJson};
fs.writeFileSync(pidPath, 'parentPid=' + process.pid + String.fromCharCode(10));
var grandchildScript = [
    "'use strict';",
    "var fs = require('fs');",
    "fs.appendFileSync(" + JSON.stringify(pidPath)
        + ", 'guardInherited=' + (process.env.NODE_OPTIONS ? '1' : '0') + String.fromCharCode(10));",
    "fs.appendFileSync(" + JSON.stringify(pidPath)
        + ", 'customEnvPreserved=' + (process.env.RUNNER_CUSTOM_ENV === 'kept' ? '1' : '0')"
        + " + String.fromCharCode(10));",
    "fs.appendFileSync(" + JSON.stringify(pidPath)
        + ", 'parentEnvLeaked=' + (process.env.RUNNER_PARENT_ONLY_ENV ? '1' : '0')"
        + " + String.fromCharCode(10));",
    "process.on('SIGTERM', function () {});",
    "setInterval(function () {}, 1000);"
].join(String.fromCharCode(10));
var childScript = [
    "'use strict';",
    "var childProcess = require('child_process');",
    "var fs = require('fs');",
    "fs.appendFileSync(" + JSON.stringify(pidPath)
        + ", 'childCustomEnvPreserved=' + (process.env.RUNNER_CUSTOM_ENV === 'kept' ? '1' : '0')"
        + " + String.fromCharCode(10));",
    "fs.appendFileSync(" + JSON.stringify(pidPath)
        + ", 'childParentEnvLeaked=' + (process.env.RUNNER_PARENT_ONLY_ENV ? '1' : '0')"
        + " + String.fromCharCode(10));",
    "delete process.env.NODE_OPTIONS;",
    "var grandchild = childProcess.spawn(process.execPath, ['-e', " + JSON.stringify(grandchildScript) + "]);",
    "grandchild.unref();",
    "fs.appendFileSync(" + JSON.stringify(pidPath)
        + ", 'grandchildPid=' + grandchild.pid + String.fromCharCode(10));",
    "process.on('SIGTERM', function () {});",
    "setInterval(function () {}, 1000);"
].join(String.fromCharCode(10));
var child = childProcess.spawn(process.execPath, ['-e', childScript], {
    detached: process.platform === 'win32',
    env: {RUNNER_CUSTOM_ENV: 'kept'},
    stdio: 'ignore',
    windowsHide: true
});
child.unref();
fs.appendFileSync(pidPath, 'childPid=' + child.pid + String.fromCharCode(10));
if (process.platform === 'win32') {
    process.on('SIGTERM', function () {});
}
setInterval(function () {}, 1000);
JS
                , 0.75, $temporaryFileFactory, $temporaryFileDeleter, $windowsCommandInterceptor);
            } catch (AssertionFailedError $error) {
                $failure = $error;
            }

            $elapsed = microtime(true) - $startedAt;
            $this->assertInstanceOf(AssertionFailedError::class, $failure);
            $this->assertStringContainsString('timed out', $failure->getMessage());
            $this->assertLessThan(5.0, $elapsed, 'The timed-out Node process must reach a hard deadline.');
            $this->assertSame(0, $injectedTaskkillFailures);

            $pidContents = file_get_contents($pidPath);
            $this->assertIsString($pidContents, 'The Node process marker must be readable.');
            $this->assertStringContainsString('guardInherited=1', $pidContents);
            $this->assertStringContainsString('childCustomEnvPreserved=1', $pidContents);
            $this->assertStringContainsString('childParentEnvLeaked=0', $pidContents);
            $this->assertStringContainsString('customEnvPreserved=1', $pidContents);
            $this->assertStringContainsString('parentEnvLeaked=0', $pidContents);
            $decodedProcessIds = $this->parseJavascriptProcessMarker($pidContents);
            $this->assertArrayHasKey('parentPid', $decodedProcessIds);
            $this->assertArrayHasKey('childPid', $decodedProcessIds);
            $this->assertArrayHasKey('grandchildPid', $decodedProcessIds);
            $processIds = [
                (int) $decodedProcessIds['parentPid'],
                (int) $decodedProcessIds['childPid'],
                (int) $decodedProcessIds['grandchildPid'],
            ];
            $this->assertCount(3, $processIds);
            $this->assertNotContains(0, $processIds, 'The Node process IDs must be positive.');
            $this->assertIsString($registryContents, 'The guarded process registry must be readable.');
            $registeredAtByPid = [];
            $exitedAtByPid = [];
            $this->javascriptProcessTreeFromRegistry(
                $registryContents,
                $processIds[0],
                $registeredAtByPid,
                $exitedAtByPid
            );
            $this->assertSame(
                [],
                $this->waitForProcessIdentitiesToExit(
                    $processIds,
                    $registeredAtByPid,
                    $exitedAtByPid,
                    0.50
                ),
                'The timed-out Node process tree must not remain alive.'
            );

            $mainContract = $this->executeJavascriptJson(
                <<<'JS'
'use strict';
var path = require('path');
console.log(JSON.stringify({
    finished: true,
    mainModule: require.main === module && module.parent === null,
    argvPath: path.resolve(process.argv[1] || '') === __filename,
    moduleId: module.id,
    moduleLoaded: module.loaded,
    argumentCount: arguments.length,
    evalArgPresent: process.execArgv.indexOf('-e') !== -1,
    topThisIsExports: this === module.exports,
    stack: new Error('runner-main-probe').stack
}));
JS
                ,
                10.0,
                $temporaryFileFactory
            );
            $this->assertTrue($mainContract['finished']);
            $this->assertTrue($mainContract['mainModule']);
            $this->assertTrue($mainContract['argvPath']);
            $this->assertSame('.', $mainContract['moduleId']);
            $this->assertFalse($mainContract['moduleLoaded']);
            $this->assertSame(5, $mainContract['argumentCount']);
            $this->assertFalse($mainContract['evalArgPresent']);
            $this->assertTrue($mainContract['topThisIsExports']);
            $this->assertStringNotContainsString('module.exports', $mainContract['stack']);
            $this->assertStringNotContainsString('[eval]', $mainContract['stack']);

            $this->assertSame(
                ['finished' => true, 'stderrBytes' => 256 * 1024],
                $this->executeJavascriptJson(<<<'JS'
'use strict';
var stderrBytes = 256 * 1024;
process.stderr.write('x'.repeat(stderrBytes));
console.log(JSON.stringify({finished: true, stderrBytes: stderrBytes}));
JS
                    , 10.0, $temporaryFileFactory
                )
            );

            $this->assertCount(12, $runnerTemporaryPaths);
            foreach ($runnerTemporaryPaths as $runnerTemporaryPath) {
                $this->assertFileDoesNotExist(
                    $runnerTemporaryPath,
                    'The timed-out and reused runners must not leave temporary files.'
                );
            }
        } finally {
            if (is_file($pidPath)) {
                $markerContents = file_get_contents($pidPath);
                if (is_string($markerContents)) {
                    $processIds = array_values(array_unique(array_merge(
                        $processIds,
                        array_values($this->parseJavascriptProcessMarker($markerContents))
                    )));
                }
            }
            if (is_string($registryContents)) {
                $registeredAtByPid = [];
                $exitedAtByPid = [];
                $processTree = $this->javascriptProcessTreeFromRegistry(
                    $registryContents,
                    $processIds[0],
                    $registeredAtByPid,
                    $exitedAtByPid
                );
                $remainingProcessIds = $this->waitForProcessIdentitiesToExit(
                    $processIds,
                    $registeredAtByPid,
                    $exitedAtByPid,
                    0.50
                );
                if ($remainingProcessIds !== []) {
                    $this->assertNull($this->terminateWindowsJavascriptProcessTree(
                        $processTree,
                        microtime(true) + 0.50,
                        null,
                        $registeredAtByPid,
                        $exitedAtByPid
                    ));
                    $remainingProcessIds = $this->waitForProcessIdentitiesToExit(
                        $processIds,
                        $registeredAtByPid,
                        $exitedAtByPid,
                        0.15
                    );
                }
            } else {
                $remainingProcessIds = $processIds;
            }
            $this->assertSame(
                [],
                $remainingProcessIds,
                'The JavaScript runner test cleanup must not leave a recorded process alive.'
            );
            if (is_file($pidPath)) {
                $this->assertTrue(unlink($pidPath), 'The process marker file could not be deleted.');
            }
            $restoredEnvironment = $previousParentOnlyEnvironment === false
                ? putenv('RUNNER_PARENT_ONLY_ENV')
                : putenv('RUNNER_PARENT_ONLY_ENV=' . $previousParentOnlyEnvironment);
            $this->assertTrue($restoredEnvironment, 'The parent-only test environment could not be restored.');
        }
    }

    /** @return array<string, array{bool}> */
    public function javascriptRunnerPrimaryTerminationProvider(): array
    {
        return [
            'primary taskkill succeeds' => [false],
            'primary taskkill fails and recursive fallback runs' => [true],
        ];
    }

    public function test_js_runner_normal_exit_tree_cleaned(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The guarded Windows process-tree registry is Windows-specific.');
        }
        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-normal-exit-');
        $this->assertIsString($pidPath, 'The normal-exit process marker file could not be created.');
        $this->assertTrue(unlink($pidPath), 'The empty normal-exit process marker file could not be removed.');
        $pidPathJson = json_encode($pidPath, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($pidPathJson);
        $runnerTemporaryPaths = [];
        $registryContents = null;
        $processIds = [];
        $temporaryFileFactory = function (string $directory, string $prefix) use (&$runnerTemporaryPaths): string {
            $path = tempnam($directory, $prefix);
            if (!is_string($path)) {
                throw new \RuntimeException('The normal-exit runner temporary file could not be created.');
            }
            $runnerTemporaryPaths[] = $path;

            return $path;
        };
        $temporaryFileDeleter = function (string $path) use (
            &$registryContents,
            &$runnerTemporaryPaths
        ): bool {
            if (isset($runnerTemporaryPaths[1]) && $path === $runnerTemporaryPaths[1]) {
                $registryContents = file_get_contents($path);
            }

            return unlink($path);
        };

        try {
            $result = $this->executeJavascriptJson(<<<JS
'use strict';
var childProcess = require('child_process');
var fs = require('fs');
var pidPath = {$pidPathJson};
var leafScript = [
    "'use strict';",
    "var fs = require('fs');",
    "fs.appendFileSync(" + JSON.stringify(pidPath)
        + ", 'grandchildPid=' + process.pid + String.fromCharCode(10)"
        + " + 'grandchildParentPid=' + process.ppid + String.fromCharCode(10));",
    "process.on('SIGTERM', function () {});",
    "setInterval(function () {}, 1000);"
].join(String.fromCharCode(10));
var encodedLeafScript = Buffer.from(leafScript, 'utf8').toString('base64');
var middle = childProcess.spawn(process.env.ComSpec || 'cmd.exe', ['/d', '/q'], {
    stdio: ['pipe', 'ignore', 'ignore'],
    windowsHide: true
});
fs.writeFileSync(
    pidPath,
    'parentPid=' + process.pid + String.fromCharCode(10)
        + 'childPid=' + middle.pid + String.fromCharCode(10)
);
var command = 'set "NODE_OPTIONS="' + String.fromCharCode(13, 10)
    + '"' + process.execPath.replace(/"/g, '""') + '"'
    + ' -e "eval(Buffer.from(\'' + encodedLeafScript + '\',\'base64\').toString())"'
    + String.fromCharCode(13, 10)
    + 'exit'
    + String.fromCharCode(13, 10);
middle.stdin.end(command);
middle.unref();
var markerDeadline = Date.now() + 3000;
var markerTimer = setInterval(function () {
    var marker = fs.readFileSync(pidPath, 'utf8');
    var leafMatch = marker.match(/^grandchildPid=(\d+)$/m);
    var leafParentMatch = marker.match(/^grandchildParentPid=(\d+)$/m);
    if (leafMatch && leafParentMatch) {
        clearInterval(markerTimer);
        fs.writeSync(1, JSON.stringify({
            finished: true,
            parentPid: process.pid,
            childPid: middle.pid,
            grandchildPid: Number(leafMatch[1]),
            grandchildParentPid: Number(leafParentMatch[1])
        }) + String.fromCharCode(10));
        process.exit(0);
    }
    if (Date.now() >= markerDeadline) {
        clearInterval(markerTimer);
        throw new Error('The non-Node intermediary did not start its leaf process.');
    }
}, 10);
JS
                , 5.0, $temporaryFileFactory, $temporaryFileDeleter);

            $this->assertTrue($result['finished']);
            $processIds = [
                (int) $result['parentPid'],
                (int) $result['childPid'],
                (int) $result['grandchildPid'],
            ];
            $this->assertNotContains(0, $processIds, 'The normal-exit process IDs must be positive.');
            $this->assertSame(
                $processIds[1],
                (int) $result['grandchildParentPid'],
                'The unguarded leaf must remain a real child of the registered non-Node intermediary.'
            );
            $this->assertIsString($registryContents, 'The guarded process registry must be readable.');
            $this->assertSame(
                [$processIds[1], $processIds[0]],
                $this->javascriptProcessTreeFromRegistry($registryContents, $processIds[0]),
                'The preload must register the direct non-Node child before the main process exits.'
            );
            $this->assertStringNotContainsString(
                '// CRM_NODE_PROCESS ' . $processIds[2] . ' ',
                $registryContents,
                'The leaf intentionally clears NODE_OPTIONS so recursive cleanup cannot depend on Node self-registration.'
            );
            $registeredAtByPid = [];
            $exitedAtByPid = [];
            $this->javascriptProcessTreeFromRegistry(
                $registryContents,
                $processIds[0],
                $registeredAtByPid,
                $exitedAtByPid
            );
            $this->assertSame(
                [],
                $this->waitForProcessIdentitiesToExit(
                    [$processIds[0], $processIds[1]],
                    $registeredAtByPid,
                    $exitedAtByPid,
                    1.0
                ),
                sprintf(
                    'A successful runner return must not leave the normal-exit tree alive '
                    . '(parent=%d, intermediary=%d, leaf=%d).',
                    $processIds[0],
                    $processIds[1],
                    $processIds[2]
                )
            );
            $this->assertCount(4, $runnerTemporaryPaths);
            foreach ($runnerTemporaryPaths as $runnerTemporaryPath) {
                $this->assertFileDoesNotExist($runnerTemporaryPath);
            }
        } finally {
            if (is_file($pidPath)) {
                $markerContents = file_get_contents($pidPath);
                if (is_string($markerContents)) {
                    $processIds = array_values(array_unique(array_merge(
                        $processIds,
                        array_values($this->parseJavascriptProcessMarker($markerContents))
                    )));
                }
            }
            if (is_string($registryContents)) {
                $registeredAtByPid = [];
                $exitedAtByPid = [];
                $processTree = $this->javascriptProcessTreeFromRegistry(
                    $registryContents,
                    $processIds[0],
                    $registeredAtByPid,
                    $exitedAtByPid
                );
                $remainingProcessIds = $this->waitForProcessIdentitiesToExit(
                    [$processIds[0], $processIds[1]],
                    $registeredAtByPid,
                    $exitedAtByPid,
                    1.0
                );
                if ($remainingProcessIds !== []) {
                    $this->assertNull($this->terminateWindowsJavascriptProcessTree(
                        $processTree,
                        microtime(true) + 0.50,
                        null,
                        $registeredAtByPid,
                        $exitedAtByPid
                    ));
                    $remainingProcessIds = $this->waitForProcessIdentitiesToExit(
                        [$processIds[0], $processIds[1]],
                        $registeredAtByPid,
                        $exitedAtByPid,
                        0.50
                    );
                }
                $this->assertSame(
                    [],
                    $remainingProcessIds,
                    'The normal-exit regression cleanup must not leave a recorded identity alive.'
                );
            }
            if (is_file($pidPath)) {
                $this->assertTrue(unlink($pidPath), 'The normal-exit process marker file could not be deleted.');
            }
        }
    }

    public function test_js_runner_process_group_termination_state_machine(): void
    {
        $this->assertTrue(
            method_exists($this, 'terminateJavascriptProcessGroup'),
            'The JavaScript runner must expose one process-group termination state machine.'
        );

        $groupAlive = true;
        $signals = [];
        $failure = $this->terminateJavascriptProcessGroup(
            4242,
            microtime(true) + 0.01,
            microtime(true) + 0.10,
            function (int $processGroupId, int $signal) use (&$groupAlive, &$signals): bool {
                $signals[] = [$processGroupId, $signal];
                if ($signal === 9) {
                    $groupAlive = false;
                }

                return true;
            },
            function (int $processGroupId) use (&$groupAlive): bool {
                return $processGroupId === 4242 && $groupAlive;
            }
        );

        $this->assertNull($failure);
        $this->assertSame([[4242, 15], [4242, 9]], $signals);
    }

    public function test_js_runner_registry_tree_decoding(): void
    {
        $this->assertTrue(
            method_exists($this, 'javascriptProcessTreeFromRegistry'),
            'The JavaScript runner must decode its guarded PID/PPID registry.'
        );

        $registry = <<<'REGISTRY'
// CRM_NODE_PROCESS 100 10 1700000000000
// CRM_NODE_PROCESS 101 100 1700000000001
// CRM_NODE_PROCESS 102 101 1700000000002
// CRM_NODE_PROCESS 200 10 1700000000003
// CRM_NODE_PROCESS 201 200 1700000000004
REGISTRY;

        $this->assertSame(
            [102, 101, 100],
            $this->javascriptProcessTreeFromRegistry($registry, 100)
        );
    }

    public function test_js_runner_registry_exit_identity_decoding(): void
    {
        $registry = <<<'REGISTRY'
// CRM_NODE_EXIT 101 1700000000010
// CRM_NODE_PROCESS 100 10 1700000000000
// CRM_NODE_PROCESS 101 100 1700000000001
// CRM_NODE_EXIT 100 1700000000020
REGISTRY;
        $registeredAtByPid = [];
        $exitedAtByPid = [];

        $this->assertSame(
            [101, 100],
            $this->javascriptProcessTreeFromRegistry(
                $registry,
                100,
                $registeredAtByPid,
                $exitedAtByPid
            )
        );
        $this->assertSame(
            [100 => 1700000000000, 101 => 1700000000001],
            $registeredAtByPid
        );
        $this->assertSame(
            [100 => 1700000000020, 101 => 1700000000010],
            $exitedAtByPid
        );
    }

    /** @dataProvider invalidJavascriptProcessExitMarkerProvider */
    public function test_invalid_js_runner_registry_exit_markers_fail_closed(string $registry): void
    {
        $failure = null;
        try {
            $this->javascriptProcessTreeFromRegistry($registry, 100);
        } catch (\Throwable $error) {
            $failure = $error;
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertStringContainsString('guarded Windows process registry', $failure->getMessage());
    }

    /** @return array<string, array{string}> */
    public function invalidJavascriptProcessExitMarkerProvider(): array
    {
        return [
            'malformed exit timestamp' => [
                "// CRM_NODE_PROCESS 100 10 1700000000000\n"
                    . "// CRM_NODE_EXIT 100 17x\n",
            ],
            'exit for unknown process' => [
                "// CRM_NODE_PROCESS 100 10 1700000000000\n"
                    . "// CRM_NODE_EXIT 101 1700000000010\n",
            ],
            'conflicting duplicate exit' => [
                "// CRM_NODE_PROCESS 100 10 1700000000000\n"
                    . "// CRM_NODE_EXIT 100 1700000000010\n"
                    . "// CRM_NODE_EXIT 100 1700000000020\n",
            ],
            'exit before registration with an earlier timestamp' => [
                "// CRM_NODE_EXIT 100 1699999999999\n"
                    . "// CRM_NODE_PROCESS 100 10 1700000000000\n",
            ],
        ];
    }

    public function test_js_preload_registry_marker_contract(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows preload registry is Windows-specific.');
        }

        $preload = $this->javascriptPreloadFileContents();
        $this->assertStringContainsString('CRM_NODE_EXIT', $preload);
        $this->assertStringContainsString('CRM_NODE_PROCESS_TREE_HELPER', $preload);
        $this->assertStringContainsString("'--watch-exit'", $preload);
        $this->assertStringContainsString('originalSpawn', $preload);
        $this->assertStringNotContainsString("'--identity'", $preload);
        $this->assertStringNotContainsString('markerPattern', $preload);
        $this->assertStringNotContainsString('markerDeadline', $preload);
        $this->assertStringNotContainsString("process.on('exit'", $preload);
        $this->assertStringNotContainsString('process.uptime()', $preload);
    }

    public function test_js_preload_guard_structure_contract(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows preload registry is Windows-specific.');
        }

        $preload = $this->javascriptPreloadFileContents();
        $this->assertStringContainsString('registeredProcessIdentities', $preload);
        $this->assertStringContainsString("CRM_NODE_EXIT_MARKER_OWNER === 'parent'", $preload);
        $this->assertStringContainsString("var path = require('path');", $preload);
        $this->assertStringContainsString('path.isAbsolute(processTreeHelperPath)', $preload);
        $this->assertStringContainsString('fs.statSync(processTreeHelperPath).isFile()', $preload);
        $this->assertStringContainsString(
            'constrained.env.CRM_NODE_PROCESS_TREE_HELPER = processTreeHelperPath;',
            $preload
        );
        $this->assertStringContainsString('if (!processTreeHelperPath)', $preload);
        $this->assertStringContainsString('return false;', $preload);
    }

    public function test_js_runner_helper_cleanup_on_normal_exit(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process-tree helper is Windows-specific.');
        }

        $commands = [];
        $interceptor = function (array $command) use (&$commands): array {
            $commands[] = $command;

            return ['completed' => true, 'exitCode' => 0, 'stdout' => 'OK', 'stderr' => ''];
        };

        $this->assertSame(
            ['finished' => true],
                $this->executeJavascriptJson(
                "'use strict';console.log(JSON.stringify({finished:true}));",
                1.0,
                null,
                null,
                $interceptor
            )
        );
        $this->assertCount(1, $commands, 'A root-only normal exit must still invoke helper cleanup.');
        $this->assertSame(
            strtolower(basename($this->prepareWindowsProcessTreeControlForTest())),
            strtolower(basename($commands[0][0]))
        );
    }

    public function test_js_runner_ambiguous_identity_probe_retry(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process-tree helper is Windows-specific.');
        }

        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-ambiguous-probe-');
        $this->assertIsString($registryPath);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(5000000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $processId = (int) $status['pid'];
        $this->assertTrue($status['running']);
        $registeredAt = $this->readProcessCreationTokenForTest($processId);
        $registry = "// CRM_NODE_PROCESS {$processId} 1 {$registeredAt}\n";
        $this->assertSame(strlen($registry), file_put_contents($registryPath, $registry));
        $commands = [];
        $interceptor = function (array $command) use (&$commands, $process, $processId): array {
            $commands[] = $command;
            if (in_array('--probe', $command, true)) {
                return ['completed' => true, 'exitCode' => 0, 'stdout' => '[]', 'stderr' => ''];
            }

            proc_terminate($process, 9);

            return [
                'completed' => true,
                'exitCode' => 3,
                'stdout' => '',
                'stderr' => 'ambiguous:' . $processId,
            ];
        };
        $exitCode = null;

        try {
            $this->assertNull($this->terminateJavascriptProcess(
                $process,
                $processId,
                false,
                $registryPath,
                microtime(true) + 2.0,
                $exitCode,
                $interceptor
            ));
            $this->assertCount(2, $commands);
            $this->assertContains('--probe', $commands[1]);
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
            if (is_file($registryPath)) {
                $this->assertTrue(unlink($registryPath));
            }
        }
    }

    public function test_js_runner_missing_root_identity_fails_closed(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process-tree helper is Windows-specific.');
        }

        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-missing-root-registry-');
        $this->assertIsString($registryPath);
        $this->assertSame(0, file_put_contents($registryPath, ''));
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(100000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $processId = (int) $status['pid'];
        $this->assertGreaterThan(0, $processId);
        $commands = [];
        $interceptor = function (array $command) use (&$commands): array {
            $commands[] = $command;

            return ['completed' => true, 'exitCode' => 0, 'stdout' => 'OK', 'stderr' => ''];
        };
        $exitCode = null;

        try {
            do {
                usleep(10000);
                $status = proc_get_status($process);
            } while ($status['running']);

            $failure = $this->terminateJavascriptProcess(
                $process,
                $processId,
                false,
                $registryPath,
                microtime(true) + 1.0,
                $exitCode,
                $interceptor
            );
            $this->assertIsString($failure);
            $this->assertStringContainsString('registry', strtolower($failure));
            $this->assertSame([], $commands, 'Missing root identity must not reach the helper.');
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
            if (is_file($registryPath)) {
                $this->assertTrue(unlink($registryPath));
            }
        }
    }

    public function test_js_identity_helper_rejects_future_registration(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(5000000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $processId = (int) $status['pid'];
        $this->assertGreaterThan(0, $processId);
        $this->assertTrue($status['running']);

        try {
            $futureRegistration = (int) floor(microtime(true) * 1000) + 60000;
            $result = $this->runBoundedWindowsCommand(
                [$this->prepareWindowsProcessTreeControlForTest(), $processId . ':' . $futureRegistration],
                microtime(true) + 2.0
            );
            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertSame(3, $result['exitCode'], 'stdout=' . $result['stdout'] . ' stderr=' . $result['stderr']);
            $this->assertStringContainsString('stale:' . $processId, $result['stderr']);
            $this->assertTrue(proc_get_status($process)['running']);
        } finally {
            proc_terminate($process, 9);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }

    public function test_js_identity_helper_ambiguous_orphan_protection(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $markerPath = tempnam(sys_get_temp_dir(), 'pid-js-orphan-marker-');
        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-orphan-registry-');
        $this->assertIsString($markerPath);
        $this->assertIsString($registryPath);
        $this->assertTrue(unlink($markerPath));
        $encodedMarkerPath = json_encode($markerPath, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encodedMarkerPath);
        $parentScript = "'use strict';var cp=require('child_process'),fs=require('fs');"
            . "var leaf=cp.spawn(process.execPath,['-e','setInterval(function(){},1000);'],"
            . "{stdio:'ignore',windowsHide:true,detached:true});leaf.unref();"
            . "fs.writeFileSync(" . $encodedMarkerPath . ",process.pid+':'+leaf.pid);"
            . "setTimeout(function(){process.exit(0);},100);";
        $pipes = [];
        $process = proc_open(
            ['node', '-e', $parentScript],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $parentProcessId = (int) $status['pid'];
        $parentCreationToken = $this->readProcessCreationTokenForTest($parentProcessId);
        $leafProcessId = 0;
        $leafCreationToken = 0;
        $this->assertGreaterThan(0, $parentProcessId);

        try {
            $markerDeadline = microtime(true) + 2.0;
            do {
                if (is_file($markerPath)) {
                    $contents = file_get_contents($markerPath);
                    if (is_string($contents)
                        && preg_match('/\A([1-9][0-9]*):([1-9][0-9]*)\z/', trim($contents), $matches) === 1) {
                        $parentProcessId = (int) $matches[1];
                        $leafProcessId = (int) $matches[2];
                        break;
                    }
                }
                usleep(10000);
            } while (microtime(true) < $markerDeadline);
            $this->assertGreaterThan(0, $leafProcessId);
            $leafCreationToken = $this->readProcessCreationTokenForTest($leafProcessId);
            do {
                usleep(10000);
                $status = proc_get_status($process);
            } while ($status['running'] && microtime(true) < $markerDeadline);
            $this->assertFalse($status['running']);
            $registeredAt = (int) floor(microtime(true) * 1000) - 1000;
            $registry = "// CRM_NODE_PROCESS {$parentProcessId} 1 {$registeredAt}\n";
            $this->assertSame(strlen($registry), file_put_contents($registryPath, $registry));

            $result = $this->runBoundedWindowsCommand(
                [$this->prepareWindowsProcessTreeControlForTest(), $parentProcessId . ':' . $registeredAt],
                microtime(true) + 1.5
            );
            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertSame(3, $result['exitCode']);
            $this->assertStringContainsString('ambiguous:' . $parentProcessId, $result['stderr']);
            $this->assertTrue(
                $this->isProcessRunningForTest($leafProcessId),
                'A missing exit identity must not permit killing an orphan descendant.'
            );
        } finally {
            if ($leafProcessId > 0 && $leafCreationToken > 0) {
                $this->assertNull($this->terminateWindowsJavascriptProcessTree(
                    [$leafProcessId],
                    microtime(true) + 1.0,
                    null,
                    [$leafProcessId => $leafCreationToken]
                ));
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
            foreach ([$markerPath, $registryPath] as $path) {
                if (is_file($path)) {
                    $this->assertTrue(unlink($path));
                }
            }
        }
    }

    /** @dataProvider invalidJavascriptProcessRegistryProvider */
    public function test_invalid_js_runner_registry_fails_closed(
        string $registry
    ): void {
        $failure = null;
        try {
            $this->javascriptProcessTreeFromRegistry($registry, 100);
        } catch (\Throwable $error) {
            $failure = $error;
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertStringContainsString('guarded Windows process registry', $failure->getMessage());
    }

    /** @return array<string, array{string}> */
    public function invalidJavascriptProcessRegistryProvider(): array
    {
        return [
            'zero pid' => ["// CRM_NODE_PROCESS 0 10 1700000000000\n"],
            'leading-zero pid' => ["// CRM_NODE_PROCESS 010 10 1700000000000\n"],
            'pid above uint32' => ["// CRM_NODE_PROCESS 4294967296 10 1700000000000\n"],
            'negative parent' => ["// CRM_NODE_PROCESS 100 -1 1700000000000\n"],
            'missing identity' => ["// CRM_NODE_PROCESS 100 10\n"],
            'nondecimal identity' => ["// CRM_NODE_PROCESS 100 10 17x\n"],
            'extra field' => ["// CRM_NODE_PROCESS 100 10 1700000000000 extra\n"],
        ];
    }

    public function test_js_identity_helper_direct_identity_kill(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $commands = [];
        $interceptor = function (array $command) use (&$commands): array {
            $commands[] = $command;

            return ['completed' => true, 'exitCode' => 0, 'stdout' => 'OK', 'stderr' => ''];
        };

        $this->assertNull($this->terminateWindowsJavascriptProcessTree(
            [2147482702, 2147482701],
            microtime(true) + 1.0,
            $interceptor,
            [2147482702 => 1700000000002, 2147482701 => 1700000000001]
        ));
        $this->assertCount(1, $commands, 'Registry-derived PIDs must reach the identity helper directly.');
        $this->assertSame(
            [
                $this->prepareWindowsProcessTreeControlForTest(),
                '2147482702:1700000000002',
                '2147482701:1700000000001',
            ],
            $commands[0]
        );
    }

    public function test_js_runner_no_taskkill_for_registry_identity(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-no-taskkill-');
        $this->assertIsString($registryPath);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(500000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $processId = (int) $status['pid'];
        $this->assertTrue($status['running']);
        $registry = "// CRM_NODE_PROCESS {$processId} 1 1\n";
        $this->assertSame(strlen($registry), file_put_contents($registryPath, $registry));
        $commands = [];
        $interceptor = function (array $command) use (&$commands): array {
            $commands[] = strtolower(basename($command[0]));

            return ['completed' => true, 'exitCode' => 0, 'stdout' => 'OK', 'stderr' => ''];
        };
        $exitCode = null;

        try {
            $this->terminateJavascriptProcess(
                $process,
                $processId,
                false,
                $registryPath,
                microtime(true) + 0.10,
                $exitCode,
                $interceptor
            );

            $this->assertNotContains('taskkill.exe', $commands);
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
            if (is_file($registryPath)) {
                $this->assertTrue(unlink($registryPath));
            }
        }
    }

    /** @dataProvider invalidNativeTreeSeedProvider */
    public function test_js_identity_helper_invalid_seeds_rejected(string $seed): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $result = $this->runBoundedWindowsCommand(
            [$this->prepareWindowsProcessTreeControlForTest(), '--probe', $seed],
            microtime(true) + 1.0
        );

        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(2, $result['exitCode']);
        $this->assertStringContainsString('ArgumentException:', $result['stderr']);
    }

    /** @return array<string, array{string}> */
    public function invalidNativeTreeSeedProvider(): array
    {
        return [
            'pid leading whitespace' => [' 1'],
            'pid trailing whitespace' => ['1 '],
            'pid plus sign' => ['+1'],
            'pid leading zero' => ['01'],
            'pid zero' => ['0'],
            'pid above uint32' => ['4294967296'],
            'identity leading whitespace' => ['1: 1'],
            'identity trailing whitespace' => ['1:1 '],
            'identity plus sign' => ['1:+1'],
            'identity leading zero' => ['1:01'],
            'identity zero' => ['1:0'],
            'identity above int64' => ['1:9223372036854775808'],
            'multiple identity separators' => ['1:1:1:1'],
        ];
    }

    public function test_js_identity_helper_rejects_unknown_probe_seeds(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $result = $this->runBoundedWindowsCommand(
            [$this->prepareWindowsProcessTreeControlForTest(), '--probe', '1:1', '1:2'],
            microtime(true) + 1.0
        );

        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(2, $result['exitCode']);
        $this->assertStringContainsString('ArgumentException:', $result['stderr']);
    }

    public function test_js_identity_helper_accepts_uint32_identity(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $result = $this->runBoundedWindowsCommand(
            [
                $this->prepareWindowsProcessTreeControlForTest(),
                '4294967295:1700000000000:1700000000010',
            ],
            microtime(true) + 1.0
        );

        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame('OK', trim($result['stdout']));
    }

    public function test_js_native_probe_returns_matching_pid(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process probe is Windows-specific.');
        }

        $result = $this->runBoundedWindowsCommand(
            [$this->prepareWindowsProcessTreeControlForTest(), '--probe', '4'],
            microtime(true) + 1.0
        );

        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame([4], json_decode(trim($result['stdout']), true));
    }

    public function test_js_native_probe_creation_token_mismatch_empty(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process probe is Windows-specific.');
        }

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(500000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $processId = (int) $status['pid'];
        $this->assertTrue($status['running']);

        try {
            $creationToken = $this->readProcessCreationTokenForTest($processId);
            $result = $this->runBoundedWindowsCommand(
                [
                    $this->prepareWindowsProcessTreeControlForTest(),
                    '--probe',
                    $processId . ':' . ($creationToken + 1),
                ],
                microtime(true) + 1.0
            );

            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertSame(0, $result['exitCode'], $result['stderr']);
            $this->assertSame([], json_decode(trim($result['stdout']), true));
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
        }
    }

    public function test_js_identity_helper_returns_current_process_identity(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $result = $this->runBoundedWindowsCommand(
            [$this->prepareWindowsProcessTreeControlForTest(), '--identity', (string) getmypid()],
            microtime(true) + 1.0
        );

        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertMatchesRegularExpression('/\A[1-9][0-9]*\z/', trim($result['stdout']));
    }

    public function test_js_native_probe_matches_only_creation_token(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process probe is Windows-specific.');
        }

        $registeredAt = $this->readProcessCreationTokenForTest(getmypid());
        $result = $this->runBoundedWindowsCommand(
            [
                $this->prepareWindowsProcessTreeControlForTest(),
                '--probe',
                getmypid() . ':' . $registeredAt . ':' . ($registeredAt + 1),
            ],
            microtime(true) + 1.0
        );

        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame([], json_decode(trim($result['stdout']), true));
    }

    public function test_js_runner_wait_for_exit_no_poll_with_zero_deadline(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process probe is Windows-specific.');
        }

        $commands = [];
        $processId = 2147482703;
        $interceptor = function (array $command) use (&$commands): array {
            $commands[] = $command;

            return ['completed' => true, 'exitCode' => 0, 'stdout' => '[]', 'stderr' => ''];
        };

        $this->assertSame(
            [$processId],
            $this->waitForProcessIdentitiesToExit(
                [$processId],
                [$processId => 1700000000000],
                [],
                0.04,
                $interceptor
            )
        );
        $this->assertSame([], $commands);
    }

    public function test_js_native_query_rejects_unknown_seed_format(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $result = $this->runBoundedWindowsCommand(
            [$this->prepareWindowsProcessTreeControlForTest(), '4:1'],
            microtime(true) + 1.0
        );

        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(3, $result['exitCode']);
        $this->assertStringContainsString('query:4:', $result['stderr']);
    }

    public function test_js_native_exit_watcher_records_exit(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process exit watcher is Windows-specific.');
        }

        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-watcher-registry-');
        $this->assertIsString($registryPath);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(400000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $processId = (int) $status['pid'];
        $registeredAt = $this->readProcessCreationTokenForTest($processId);
        $this->assertGreaterThan(0, $processId);
        $this->assertTrue($status['running']);
        $registryPrefix = "'use strict';";
        $this->assertSame(strlen($registryPrefix), file_put_contents($registryPath, $registryPrefix));

        try {
            $result = $this->runBoundedWindowsCommand(
                [
                    $this->prepareWindowsProcessTreeControlForTest(),
                    '--watch-exit',
                    (string) $processId,
                    (string) getmypid(),
                    $registryPath,
                ],
                microtime(true) + 2.0
            );
            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertSame(0, $result['exitCode'], $result['stderr']);
            $this->assertSame('OK', trim($result['stdout']));
            $contents = file_get_contents($registryPath);
            $this->assertIsString($contents);
            $this->assertMatchesRegularExpression(
                '/\A' . preg_quote($registryPrefix, '/')
                    . '\r?\n\/\/ CRM_NODE_PROCESS ' . $processId . ' ' . getmypid() . ' ' . $registeredAt
                    . '\r?\n\/\/ CRM_NODE_EXIT ' . $processId . ' ([1-9][0-9]*)\r?\n\z/D',
                $contents
            );
            $this->assertSame(
                1,
                preg_match('/^\/\/ CRM_NODE_EXIT ' . $processId . ' ([1-9][0-9]*)\r?$/m', $contents, $matches)
            );
            $this->assertGreaterThan(
                $registeredAt,
                (int) $matches[1],
                'The native watcher must record the kernel exit FILETIME, not reuse process creation time.'
            );
        } finally {
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
            if (is_file($registryPath)) {
                $this->assertTrue(unlink($registryPath));
            }
        }
    }

    /** @dataProvider invalidNativeExitWatcherProvider */
    public function test_invalid_native_exit_watcher_fails_closed(
        string $processId,
        string $parentProcessId,
        string $expectedError
    ): void {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process exit watcher is Windows-specific.');
        }

        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-watcher-failure-');
        $this->assertIsString($registryPath);
        $this->assertSame(0, file_put_contents($registryPath, ''));

        try {
            $result = $this->runBoundedWindowsCommand(
                [
                    $this->prepareWindowsProcessTreeControlForTest(),
                    '--watch-exit',
                    $processId,
                    $parentProcessId,
                    $registryPath,
                ],
                microtime(true) + 1.0
            );
            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertNotSame(0, $result['exitCode']);
            $this->assertStringContainsString($expectedError, $result['stderr']);
            $this->assertSame('', file_get_contents($registryPath));
        } finally {
            if (is_file($registryPath)) {
                $this->assertTrue(unlink($registryPath));
            }
        }
    }

    /** @return array<string, array{string, string, string}> */
    public function invalidNativeExitWatcherProvider(): array
    {
        return [
            'nonexistent pid' => ['4294967295', '1', 'watch-open:4294967295:'],
            'noncanonical pid' => ['01', '1', 'ArgumentException:'],
        ];
    }

    public function test_js_native_exit_watcher_rejects_zero_parent(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process exit watcher is Windows-specific.');
        }

        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-watcher-stale-');
        $this->assertIsString($registryPath);
        $this->assertSame(0, file_put_contents($registryPath, ''));
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(5000000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $processId = (int) $status['pid'];
        $this->assertGreaterThan(0, $processId);

        try {
            $result = $this->runBoundedWindowsCommand(
                [
                    $this->prepareWindowsProcessTreeControlForTest(),
                    '--watch-exit',
                    (string) $processId,
                    '0',
                    $registryPath,
                ],
                microtime(true) + 2.0
            );
            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertSame(2, $result['exitCode']);
            $this->assertStringContainsString('ArgumentException:', $result['stderr']);
            $this->assertSame('', file_get_contents($registryPath));
            $this->assertTrue(proc_get_status($process)['running']);
        } finally {
            proc_terminate($process, 9);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
            if (is_file($registryPath)) {
                $this->assertTrue(unlink($registryPath));
            }
        }
    }

    public function test_js_native_probe_dedupes_seeds(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(5000000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $childProcessId = (int) $status['pid'];
        $currentProcessId = getmypid();
        $this->assertGreaterThan(0, $childProcessId);
        $this->assertIsInt($currentProcessId);

        try {
            $childCreationToken = $this->readProcessCreationTokenForTest($childProcessId);
            $currentCreationToken = $this->readProcessCreationTokenForTest($currentProcessId);
            $result = $this->runBoundedWindowsCommand(
                [
                    $this->prepareWindowsProcessTreeControlForTest(),
                    '--probe',
                    $childProcessId . ':' . $childCreationToken,
                    $currentProcessId . ':' . $currentCreationToken,
                    $childProcessId . ':' . $childCreationToken,
                    $currentProcessId . ':' . $currentCreationToken,
                ],
                microtime(true) + 1.0
            );

            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertSame(0, $result['exitCode'], $result['stderr']);
            $this->assertSame(
                [$childProcessId, $currentProcessId],
                json_decode(trim($result['stdout']), true),
                'Duplicate seeds must retain the first occurrence order.'
            );
        } finally {
            proc_terminate($process, 9);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }

    public function test_js_runner_requires_64bit_php_on_windows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows JavaScript runner integer-width guard is Windows-specific.');
        }

        $startedMarkerPath = tempnam(sys_get_temp_dir(), 'pid-js-32-bit-started-');
        $this->assertIsString($startedMarkerPath);
        $this->assertTrue(unlink($startedMarkerPath));
        $encodedMarkerPath = json_encode($startedMarkerPath, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encodedMarkerPath);
        $temporaryFileCalls = 0;
        $temporaryFileFactory = function (string $directory, string $prefix) use (&$temporaryFileCalls): string {
            $temporaryFileCalls++;
            $path = tempnam($directory, $prefix);
            if (!is_string($path)) {
                throw new \RuntimeException('The test temporary file could not be created.');
            }

            return $path;
        };
        $failure = null;

        try {
            $this->executeJavascriptJson(
                "'use strict';require('fs').writeFileSync(" . $encodedMarkerPath . ",'started');"
                    . "console.log(JSON.stringify({finished:true}));",
                1.0,
                $temporaryFileFactory,
                null,
                null,
                4
            );
        } catch (\RuntimeException $error) {
            $failure = $error;
        }

        $scriptStarted = is_file($startedMarkerPath);
        if ($scriptStarted) {
            $this->assertTrue(unlink($startedMarkerPath));
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame('The Windows JavaScript runner requires 64-bit PHP.', $failure->getMessage());
        $this->assertSame(0, $temporaryFileCalls, 'The integer-width guard must run before temporary preparation.');
        $this->assertFalse($scriptStarted, 'The integer-width guard must run before Node is started.');
    }

    public function test_js_native_stale_seed_rejected(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(5000000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $processId = (int) $status['pid'];
        $this->assertGreaterThan(0, $processId);

        try {
            $result = $this->runBoundedWindowsCommand(
                [$this->prepareWindowsProcessTreeControlForTest(), $processId . ':1'],
                microtime(true) + 1.0
            );
            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertSame(3, $result['exitCode']);
            $this->assertStringContainsString('stale:' . $processId, $result['stderr']);
            $this->assertTrue(proc_get_status($process)['running']);
        } finally {
            proc_terminate($process, 9);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }

    public function test_js_native_stale_seed_rejected_before_descendant_expansion(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows process identity helper is Windows-specific.');
        }

        $leafMarkerPath = tempnam(sys_get_temp_dir(), 'pid-js-stale-leaf-');
        $this->assertIsString($leafMarkerPath);
        $this->assertTrue(unlink($leafMarkerPath));
        $encodedMarkerPath = json_encode($leafMarkerPath, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encodedMarkerPath);
        $parentScript = "'use strict';var cp=require('child_process'),fs=require('fs');"
            . "var leaf=cp.spawn(process.execPath,['-e','setInterval(function(){},1000);'],"
            . "{stdio:'ignore',windowsHide:true});"
            . "fs.writeFileSync(" . $encodedMarkerPath . ",String(leaf.pid));"
            . "setInterval(function(){},1000);";
        $pipes = [];
        $process = proc_open(
            ['node', '-e', $parentScript],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $parentProcessId = (int) $status['pid'];
        $parentCreationToken = $this->readProcessCreationTokenForTest($parentProcessId);
        $this->assertGreaterThan(0, $parentProcessId);
        $leafProcessId = 0;
        $leafCreationToken = 0;

        try {
            $markerDeadline = microtime(true) + 2.0;
            do {
                if (is_file($leafMarkerPath)) {
                    $contents = file_get_contents($leafMarkerPath);
                    if (is_string($contents) && preg_match('/\A[1-9][0-9]*\z/', $contents) === 1) {
                        $leafProcessId = (int) $contents;
                        break;
                    }
                }
                usleep(10000);
            } while (microtime(true) < $markerDeadline);
            $this->assertGreaterThan(0, $leafProcessId);
            $leafCreationToken = $this->readProcessCreationTokenForTest($leafProcessId);
            $this->assertTrue($this->isProcessRunningForTest($leafProcessId));

            $result = $this->runBoundedWindowsCommand(
                [$this->prepareWindowsProcessTreeControlForTest(), $parentProcessId . ':1'],
                microtime(true) + 1.5
            );

            $this->assertTrue($result['completed'], $result['stderr']);
            $this->assertSame(3, $result['exitCode']);
            $this->assertStringContainsString('stale:' . $parentProcessId, $result['stderr']);
            $this->assertTrue(proc_get_status($process)['running'], 'The stale seed must remain alive.');
            $this->assertTrue(
                $this->isProcessRunningForTest($leafProcessId),
                'A stale seed must be rejected before its descendants are expanded or terminated.'
            );
        } finally {
            if ($parentProcessId > 0 && $parentCreationToken > 0) {
                $registeredAtByPid = [$parentProcessId => $parentCreationToken];
                $processTree = [$parentProcessId];
                if ($leafProcessId > 0 && $leafCreationToken > 0) {
                    $registeredAtByPid[$leafProcessId] = $leafCreationToken;
                    array_unshift($processTree, $leafProcessId);
                }
                $this->assertNull($this->terminateWindowsJavascriptProcessTree(
                    $processTree,
                    microtime(true) + 1.0,
                    null,
                    $registeredAtByPid
                ));
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
            if (is_file($leafMarkerPath)) {
                $this->assertTrue(unlink($leafMarkerPath));
            }
            if ($leafProcessId > 0) {
                $this->assertSame(
                    [],
                    $this->waitForProcessIdsToExit([$parentProcessId, $leafProcessId], 1.0),
                    'The stale-seed regression test leaked its process tree.'
                );
            }
        }
    }

    public function test_js_runner_taskkill_success_triggers_verification(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows taskkill verification path is Windows-specific.');
        }

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', '-r', 'usleep(100000);'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $this->assertTrue($status['running']);
        $rootProcessId = (int) $status['pid'];
        $childProcessId = 2147482701;
        $registeredAt = $this->readProcessCreationTokenForTest($rootProcessId);
        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-registry-');
        $this->assertIsString($registryPath);
        $this->assertSame(
            strlen("// CRM_NODE_PROCESS {$rootProcessId} 1 {$registeredAt}\n"
                . "// CRM_NODE_PROCESS {$childProcessId} {$rootProcessId} {$registeredAt}\n"),
            file_put_contents(
                $registryPath,
                "// CRM_NODE_PROCESS {$rootProcessId} 1 {$registeredAt}\n"
                    . "// CRM_NODE_PROCESS {$childProcessId} {$rootProcessId} {$registeredAt}\n"
            )
        );
        $commands = [];
        $interceptor = function (array $command, float $deadline) use (&$commands): array {
            $commands[] = strtolower(basename($command[0]));

            return strtolower(basename($command[0])) === 'taskkill.exe'
                ? ['completed' => true, 'exitCode' => 0, 'stdout' => 'SUCCESS', 'stderr' => '']
                : ['completed' => true, 'exitCode' => 0, 'stdout' => 'OK', 'stderr' => ''];
        };
        $exitCode = null;

        try {
            $this->assertNull($this->terminateJavascriptProcess(
                $process,
                $rootProcessId,
                false,
                $registryPath,
                microtime(true) + 1.5,
                $exitCode,
                $interceptor
            ));
            $this->assertContains(
                strtolower(basename($this->prepareWindowsProcessTreeControlForTest())),
                $commands,
                'A successful taskkill must still trigger per-PID verification.'
            );
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
            if (is_file($registryPath)) {
                $this->assertTrue(unlink($registryPath));
            }
        }
    }

    public function test_js_runner_descendant_fallback_terminates_detached_leaf(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows descendant fallback is Windows-specific.');
        }

        $leafMarkerPath = tempnam(sys_get_temp_dir(), 'pid-js-live-leaf-');
        $registryPath = tempnam(sys_get_temp_dir(), 'pid-js-live-registry-');
        $this->assertIsString($leafMarkerPath);
        $this->assertIsString($registryPath);
        $this->assertTrue(unlink($leafMarkerPath));
        $encodedMarkerPath = json_encode($leafMarkerPath, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encodedMarkerPath);
        $rootScript = "'use strict'; var cp=require('child_process'),fs=require('fs');"
            . "var leaf=cp.spawn(process.execPath,['-e','setInterval(function(){},1000);'],"
            . "{env:{},stdio:'ignore',windowsHide:true,detached:true});leaf.unref();"
            . "fs.writeFileSync(" . $encodedMarkerPath . ",String(leaf.pid));"
            . "setInterval(function(){},1000);";
        $pipes = [];
        $process = proc_open(
            ['node', '-e', $rootScript],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $rootProcessId = (int) $status['pid'];
        $this->assertGreaterThan(0, $rootProcessId);
        $leafProcessId = 0;
        $markerDeadline = microtime(true) + 2.0;
        do {
            if (is_file($leafMarkerPath)) {
                $contents = file_get_contents($leafMarkerPath);
                if (is_string($contents) && preg_match('/\A[1-9][0-9]*\z/', $contents) === 1) {
                    $leafProcessId = (int) $contents;
                    break;
                }
            }
            usleep(10000);
        } while (microtime(true) < $markerDeadline);
        $this->assertGreaterThan(0, $leafProcessId);
        $registeredAt = $this->readProcessCreationTokenForTest($rootProcessId);
        $this->assertSame(
            strlen("// CRM_NODE_PROCESS {$rootProcessId} 1 {$registeredAt}\n"),
            file_put_contents(
                $registryPath,
                "// CRM_NODE_PROCESS {$rootProcessId} 1 {$registeredAt}\n"
            )
        );
        $interceptor = function (array $command, float $deadline): ?array {
            if (strtolower(basename($command[0])) !== 'taskkill.exe') {
                return null;
            }

            return [
                'completed' => true,
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'injected taskkill access denial',
            ];
        };
        $exitCode = null;

        try {
            $this->assertNull($this->terminateJavascriptProcess(
                $process,
                $rootProcessId,
                false,
                $registryPath,
                microtime(true) + 4.0,
                $exitCode,
                $interceptor
            ));
            $this->assertSame(
                [],
                $this->waitForProcessIdsToExit([$rootProcessId, $leafProcessId], 0.3),
                'A taskkill failure must not leave an unregistered live descendant.'
            );
        } finally {
            foreach ([$leafProcessId, $rootProcessId] as $processId) {
                if ($processId > 0) {
                    $this->runBoundedWindowsCommand(
                        ['node', '-e', "try{process.kill(" . $processId . ",'SIGKILL')}catch(e){}"],
                        microtime(true) + 1.0
                    );
                }
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
            foreach ([$leafMarkerPath, $registryPath] as $path) {
                if (is_file($path)) {
                    $this->assertTrue(unlink($path));
                }
            }
        }
    }

    public function test_bounded_windows_command_skips_interceptor_when_deadline_expired(): void
    {
        $interceptorCalls = 0;
        $interceptor = function (array $command, float $deadline) use (&$interceptorCalls): array {
            $interceptorCalls++;

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => 'must not be returned',
                'stderr' => '',
            ];
        };

        $this->assertSame(
            [
                'completed' => false,
                'exitCode' => null,
                'stdout' => '',
                'stderr' => 'Windows command deadline expired before execution.',
            ],
            $this->runBoundedWindowsCommand(
                ['interceptor-must-not-run.exe'],
                microtime(true) - 1.0,
                $interceptor
            )
        );
        $this->assertSame(0, $interceptorCalls);
    }

    public function test_bounded_windows_command_interceptor_blocks_proc_open(): void
    {
        $interceptorCalls = 0;
        $deadline = microtime(true) + 0.02;
        $interceptor = function (array $command, float $commandDeadline) use (&$interceptorCalls): ?array {
            $interceptorCalls++;
            while (microtime(true) <= $commandDeadline) {
                usleep(1000);
            }

            return null;
        };

        $this->assertSame(
            [
                'completed' => false,
                'exitCode' => null,
                'stdout' => '',
                'stderr' => 'Windows command deadline expired before execution.',
            ],
            $this->runBoundedWindowsCommand(
                ['d1-command-must-not-reach-proc-open.exe'],
                $deadline,
                $interceptor
            )
        );
        $this->assertSame(1, $interceptorCalls);
    }

    public function test_bounded_windows_command_streams_large_output(): void
    {
        $chunkCount = 64;
        $chunkBytes = 4096;
        $script = 'for ($i = 0; $i < ' . $chunkCount . '; $i++) {'
            . ' fwrite(STDOUT, str_repeat("o", ' . $chunkBytes . '));'
            . ' fwrite(STDERR, str_repeat("e", ' . $chunkBytes . '));'
            . ' }';

        $result = $this->runBoundedWindowsCommand(
            [PHP_BINARY, '-n', '-r', $script],
            microtime(true) + 3.0
        );

        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame($chunkCount * $chunkBytes, strlen($result['stdout']));
        $this->assertSame($chunkCount * $chunkBytes, strlen($result['stderr']));
    }

    public function test_bounded_windows_command_limits_output_size(): void
    {
        $result = $this->runBoundedWindowsCommand(
            [PHP_BINARY, '-n', '-r', 'fwrite(STDOUT, str_repeat("x", 5 * 1024 * 1024));'],
            microtime(true) + 3.0
        );

        $this->assertFalse($result['completed']);
        $this->assertStringContainsString('output exceeded the 4194304-byte limit', $result['stderr']);
        $this->assertLessThanOrEqual(4 * 1024 * 1024, strlen($result['stdout']) + strlen($result['stderr']));
    }

    public function test_process_probe_fails_closed_on_tasklist_timeout(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The bounded tasklist probe is Windows-specific.');
        }

        $commands = [];
        $failure = null;
        $interceptor = function (array $command, float $deadline) use (&$commands): array {
            $commands[] = $command;

            return [
                'completed' => false,
                'exitCode' => null,
                'stdout' => '',
                'stderr' => 'injected tasklist timeout',
            ];
        };
        $startedAt = microtime(true);

        try {
            $this->isProcessRunningForTest(2147483000, microtime(true) + 0.10, $interceptor);
        } catch (AssertionFailedError $error) {
            $failure = $error;
        }

        $elapsed = microtime(true) - $startedAt;
        $this->assertInstanceOf(AssertionFailedError::class, $failure);
        $this->assertStringContainsString('tasklist did not complete', $failure->getMessage());
        $this->assertLessThan(0.25, $elapsed, 'The process probe must respect its hard deadline.');
        $this->assertCount(1, $commands, 'The process probe must use the injected tasklist command path.');
        $this->assertSame('tasklist.exe', strtolower(basename($commands[0][0])));
    }

    public function test_wait_for_process_ids_skips_probe_when_deadline_expired(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The bounded tasklist wait is Windows-specific.');
        }

        $processIds = [2147482997, 2147482996];
        $commands = [];
        $interceptor = function (array $command, float $deadline) use (&$commands): array {
            $commands[] = $command;

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        };

        $this->assertSame(
            $processIds,
            $this->waitForProcessIdsToExit($processIds, 0.0, $interceptor)
        );
        $this->assertSame([], $commands, 'An expired wait must not start a process probe.');
    }

    public function test_wait_for_process_ids_respects_shared_deadline(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The bounded tasklist wait is Windows-specific.');
        }

        $processIds = [2147482995, 2147482994];
        $commands = [];
        $interceptor = function (array $command, float $deadline) use (
            &$commands,
            $processIds
        ): array {
            $commands[] = $command;
            if (count($commands) === 1) {
                while (microtime(true) <= $deadline) {
                    usleep(1000);
                }

                return [
                    'completed' => true,
                    'exitCode' => 0,
                    'stdout' => '',
                    'stderr' => '',
                ];
            }

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => '"php.exe","' . $processIds[1] . '","Console","1","1 K"',
                'stderr' => '',
            ];
        };

        $startedAt = microtime(true);
        $runningProcessIds = $this->waitForProcessIdsToExit($processIds, 0.02, $interceptor);
        $elapsed = microtime(true) - $startedAt;

        $this->assertSame([$processIds[1]], $runningProcessIds);
        $this->assertCount(1, $commands, 'No second PID may be probed after the shared deadline.');
        $this->assertLessThan(
            0.25,
            $elapsed,
            'The multi-PID wait must stay within its hard total-time bound.'
        );
    }

    public function test_force_kill_fails_closed_when_deadline_expired(): void
    {
        $processId = 2147482993;
        $commands = [];
        $failure = null;
        $interceptor = function (array $command, float $deadline) use (&$commands): array {
            $commands[] = $command;

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        };

        try {
            $this->forceKillProcessTreeForTest($processId, microtime(true) - 1.0, $interceptor);
        } catch (AssertionFailedError $error) {
            $failure = $error;
        }

        $this->assertInstanceOf(AssertionFailedError::class, $failure);
        $this->assertStringContainsString(
            'Cleanup deadline expired before probing PID ' . $processId . '.',
            $failure->getMessage()
        );
        $this->assertSame([], $commands);
    }

    public function test_force_kill_fails_closed_on_tasklist_timeout(): void
    {
        $processId = 2147482992;
        $commands = [];
        $failure = null;
        $interceptor = function (array $command, float $deadline) use (&$commands, $processId): array {
            $commands[] = $command;
            while (microtime(true) <= $deadline) {
                usleep(1000);
            }

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => '"php.exe","' . $processId . '","Console","1","1 K"',
                'stderr' => '',
            ];
        };

        try {
            $this->forceKillProcessTreeForTest(
                $processId,
                microtime(true) + 0.02,
                $interceptor
            );
        } catch (AssertionFailedError $error) {
            $failure = $error;
        }

        $this->assertInstanceOf(AssertionFailedError::class, $failure);
        $this->assertStringContainsString(
            'Cleanup deadline expired before taskkill for PID ' . $processId . '.',
            $failure->getMessage()
        );
        $this->assertSame(
            ['tasklist.exe'],
            array_map(function (array $command): string {
                return strtolower(basename($command[0]));
            }, $commands)
        );
    }

    public function test_force_kill_fails_closed_on_taskkill_timeout(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The bounded taskkill path is Windows-specific.');
        }

        $processId = 2147482999;
        $commands = [];
        $failure = null;
        $interceptor = function (array $command, float $deadline) use (&$commands, $processId): array {
            $commands[] = $command;
            if (strtolower(basename($command[0])) === 'tasklist.exe') {
                return [
                    'completed' => true,
                    'exitCode' => 0,
                    'stdout' => '"php.exe","' . $processId . '","Console","1","1 K"',
                    'stderr' => '',
                ];
            }

            return [
                'completed' => false,
                'exitCode' => null,
                'stdout' => '',
                'stderr' => 'injected taskkill timeout',
            ];
        };
        $startedAt = microtime(true);

        try {
            $this->forceKillProcessTreeForTest($processId, microtime(true) + 0.10, $interceptor);
        } catch (AssertionFailedError $error) {
            $failure = $error;
        }

        $elapsed = microtime(true) - $startedAt;
        $this->assertInstanceOf(AssertionFailedError::class, $failure);
        $this->assertStringContainsString('taskkill did not complete', $failure->getMessage());
        $this->assertLessThan(0.25, $elapsed, 'The forced cleanup must respect its hard deadline.');
        $this->assertSame(
            ['tasklist.exe', 'taskkill.exe'],
            array_map(function (array $command): string {
                return strtolower(basename($command[0]));
            }, $commands),
            'The forced cleanup must attempt taskkill after detecting a live PID.'
        );
        $this->assertContains((string) $processId, $commands[1]);
    }

    public function test_force_kill_verifies_taskkill_success(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The bounded taskkill path is Windows-specific.');
        }

        $processId = 2147482998;
        $commands = [];
        $tasklistCalls = 0;
        $interceptor = function (array $command, float $deadline) use (
            &$commands,
            &$tasklistCalls,
            $processId
        ): array {
            $commands[] = $command;
            if (strtolower(basename($command[0])) === 'taskkill.exe') {
                return [
                    'completed' => true,
                    'exitCode' => 0,
                    'stdout' => 'SUCCESS',
                    'stderr' => '',
                ];
            }

            $tasklistCalls++;

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => $tasklistCalls === 1
                    ? '"php.exe","' . $processId . '","Console","1","1 K"'
                    : '',
                'stderr' => '',
            ];
        };

        $this->forceKillProcessTreeForTest($processId, microtime(true) + 0.10, $interceptor);

        $this->assertSame(
            ['tasklist.exe', 'taskkill.exe', 'tasklist.exe'],
            array_map(function (array $command): string {
                return strtolower(basename($command[0]));
            }, $commands)
        );
        $this->assertSame(2, $tasklistCalls);
    }

    /** @dataProvider javascriptControlTerminationCleanupProvider */
    public function test_js_runner_control_process_cleanup_recovery(
        bool $failBeforePidRead
    ): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The bounded Windows control-process path is Windows-specific.');
        }

        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-control-');
        $this->assertIsString($pidPath);
        $this->assertTrue(unlink($pidPath));
        $terminationAttempts = 0;
        $processIds = [];
        $recoveredProcessIds = [];
        $earlyFailure = null;
        $primaryFailure = null;
        $tasklistDeadlines = [];
        $controlTerminator = function ($process) use (&$terminationAttempts): bool {
            $terminationAttempts++;
            if ($terminationAttempts === 1) {
                return false;
            }

            return proc_terminate($process, 9);
        };
        $controlScript = 'file_put_contents(' . var_export($pidPath, true) . ', (string) getmypid()); '
            . 'while (true) { usleep(100000); }';
        $cleanupWindowsCommandInterceptor = function (array $command, float $deadline) use (
            &$tasklistDeadlines
        ): ?array {
            if (strtolower(basename($command[0])) !== 'tasklist.exe') {
                return null;
            }
            $tasklistDeadlines[] = $deadline;

            $filterIndex = array_search('/FI', $command, true);
            $filter = is_int($filterIndex) && isset($command[$filterIndex + 1])
                ? $command[$filterIndex + 1]
                : '';
            if (preg_match('/^PID eq ([1-9]\d*)$/', $filter, $matches) !== 1) {
                return [
                    'completed' => true,
                    'exitCode' => 2,
                    'stdout' => '',
                    'stderr' => 'The tasklist PID filter could not be decoded by the test interceptor.',
                ];
            }

            $processId = (int) $matches[1];
            $probe = $this->runBoundedWindowsCommand(
                [
                    'powershell.exe',
                    '-NoLogo',
                    '-NoProfile',
                    '-NonInteractive',
                    '-Command',
                    '$target = Get-Process -Id ' . $processId
                        . ' -ErrorAction SilentlyContinue; if ($null -ne $target) { exit 0 }; exit 1',
                ],
                $deadline
            );
            if (!$probe['completed'] || !in_array($probe['exitCode'], [0, 1], true)) {
                return $probe;
            }

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => $probe['exitCode'] === 0
                    ? '"php.exe","' . $processId . '","Console","1","1 K"'
                    : '',
                'stderr' => '',
            ];
        };
        $scenarioStartedAt = microtime(true);
        $cleanupDeadline = $scenarioStartedAt + 7.0;

        try {
            $startedAt = microtime(true);
            $result = $this->runBoundedWindowsCommand(
                [PHP_BINARY, '-r', $controlScript],
                microtime(true) + 0.60,
                null,
                $controlTerminator
            );
            $elapsed = microtime(true) - $startedAt;

            $this->assertFalse($result['completed']);
            $this->assertSame(2, $terminationAttempts);
            $this->assertLessThan(0.75, $elapsed);
            $this->assertFileExists($pidPath);
            $processIds[] = $this->readControlProcessIdMarkerForTest($pidPath);
            $recoveredProcessIds = $processIds;
            $this->assertNotContains(0, $processIds);
            $this->assertTrue(unlink($pidPath));
            $processIds = [];
            if ($failBeforePidRead) {
                throw new \RuntimeException('Injected failure after safe control PID verification.');
            }
        } catch (\Throwable $error) {
            $primaryFailure = $error;
        } finally {
            $this->cleanupControlProcessesForTest(
                $primaryFailure,
                $pidPath,
                $processIds,
                $cleanupDeadline,
                $cleanupWindowsCommandInterceptor
            );
        }

        if ($primaryFailure !== null) {
            if (!$failBeforePidRead
                || $primaryFailure->getMessage() !== 'Injected failure after safe control PID verification.') {
                throw $primaryFailure;
            }
            $earlyFailure = $primaryFailure;
        }

        if ($tasklistDeadlines !== []) {
            $firstTasklistDeadline = $tasklistDeadlines[0];
            foreach (array_slice($tasklistDeadlines, 1) as $tasklistDeadline) {
                $this->assertEqualsWithDelta(
                    $firstTasklistDeadline,
                    $tasklistDeadline,
                    0.005,
                    'Every control-process probe must share one absolute cleanup deadline.'
                );
            }
        }
        $this->assertLessThan(
            7.5,
            microtime(true) - $scenarioStartedAt,
            'The control scenario and cleanup must stay within one seven-second absolute budget.'
        );

        $this->assertSame(
            $failBeforePidRead ? 'Injected failure after safe control PID verification.' : null,
            $earlyFailure instanceof \RuntimeException ? $earlyFailure->getMessage() : null
        );
        $this->assertCount(
            1,
            $recoveredProcessIds,
            'The control PID must be recovered even when the try block fails early.'
        );
    }

    /** @return array<string, array{bool}> */
    public function javascriptControlTerminationCleanupProvider(): array
    {
        return [
            'normal control PID read' => [false],
            'failure before control PID read' => [true],
        ];
    }

    /** @dataProvider invalidControlProcessIdMarkerProvider */
    public function test_invalid_control_process_id_marker_rejected(string $markerContents): void
    {
        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-invalid-');
        $this->assertIsString($pidPath);
        $failure = null;

        try {
            $this->assertSame(strlen($markerContents), file_put_contents($pidPath, $markerContents));
            try {
                $this->readControlProcessIdMarkerForTest($pidPath);
            } catch (\Throwable $error) {
                $failure = $error;
            }

            $this->assertInstanceOf(\RuntimeException::class, $failure);
            $this->assertStringContainsString(
                'Control PID marker must contain exactly one decimal PID between 1 and',
                $failure->getMessage()
            );
        } finally {
            if (is_file($pidPath)) {
                $this->assertTrue(unlink($pidPath));
            }
        }
    }

    public function test_control_process_id_marker_accepts_uint32(): void
    {
        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-valid-');
        $this->assertIsString($pidPath);

        try {
            $this->assertSame(11, file_put_contents($pidPath, "4294967295\n"));
            $this->assertSame(4294967295, $this->readControlProcessIdMarkerForTest($pidPath));
        } finally {
            if (is_file($pidPath)) {
                $this->assertTrue(unlink($pidPath));
            }
        }
    }

    public function test_normalize_control_process_ids_dedupes_and_validates(): void
    {
        $this->assertSame(
            [2147482801, 2147482802, 2147482803],
            $this->normalizeControlProcessIdsForTest([
                2147482801,
                2147482802,
                2147482801,
                2147482803,
            ])
        );

        foreach ([
            ['numeric string', [2147482801, '2147482802']],
            ['float', [2147482801, 2147482802.0]],
            ['boolean', [true]],
            ['null', [null]],
            ['zero', [0]],
            ['negative', [-1]],
            ['above uint32', [4294967296]],
        ] as [$label, $values]) {
            try {
                $this->normalizeControlProcessIdsForTest($values);
                $failure = null;
            } catch (\Throwable $error) {
                $failure = $error;
            }

            $this->assertInstanceOf(\RuntimeException::class, $failure, $label);
            $this->assertStringContainsString('native integers between 1 and 4294967295', $failure->getMessage());
        }
    }

    public function test_normalize_control_process_ids_requires_64bit_php(): void
    {
        try {
            $this->normalizeControlProcessIdsForTest([2147483647], 4);
            $failure = null;
        } catch (\Throwable $error) {
            $failure = $error;
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertStringContainsString('32-bit PHP', $failure->getMessage());
    }

    public function test_control_process_cleanup_replaces_toctou_pid(): void
    {
        $firstProcessId = 2147482804;
        $lateProcessId = 2147482805;
        $replacementProcessId = 2147482806;
        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-toctou-');
        $this->assertIsString($pidPath);
        $this->assertTrue(unlink($pidPath));
        $commands = [];
        $terminatedProcessIds = [];
        $firstMarkerWritten = false;
        $deleteAttempts = [];
        $interceptor = function (array $command, float $deadline) use (
            &$commands,
            &$terminatedProcessIds,
            &$firstMarkerWritten,
            $pidPath,
            $firstProcessId,
            $lateProcessId
        ): array {
            $commandName = strtolower(basename($command[0]));
            $pidIndex = array_search($commandName === 'taskkill.exe' ? '/PID' : '/FI', $command, true);
            $argument = is_int($pidIndex) && isset($command[$pidIndex + 1])
                ? $command[$pidIndex + 1]
                : '';
            $processId = $commandName === 'taskkill.exe'
                ? (int) $argument
                : (int) preg_replace('/^PID eq /', '', $argument);
            $commands[] = $commandName . ':' . $processId;

            if ($commandName === 'tasklist.exe' && $processId === $firstProcessId && !$firstMarkerWritten) {
                $firstMarkerWritten = true;
                file_put_contents($pidPath, $lateProcessId . "\n");
            }

            if ($commandName === 'taskkill.exe') {
                $terminatedProcessIds[$processId] = true;

                return [
                    'completed' => true,
                    'exitCode' => 0,
                    'stdout' => 'SUCCESS',
                    'stderr' => '',
                ];
            }

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => isset($terminatedProcessIds[$processId])
                    ? ''
                    : '"php.exe","' . $processId . '","Console","1","1 K"',
                'stderr' => '',
            ];
        };
        $deleter = function (string $claimedPath) use (
            &$deleteAttempts,
            $pidPath,
            $replacementProcessId
        ): bool {
            $deleteAttempts[] = $claimedPath;
            if (count($deleteAttempts) === 1) {
                file_put_contents($pidPath, $replacementProcessId . "\n");
            }

            return unlink($claimedPath);
        };
        $processIds = [$firstProcessId];

        try {
            $this->cleanupControlProcessesForTest(
                null,
                $pidPath,
                $processIds,
                microtime(true) + 2.0,
                $interceptor,
                $deleter
            );

            $this->assertSame([$firstProcessId, $lateProcessId, $replacementProcessId], $processIds);
            $this->assertSame([], $this->waitForProcessIdsToExit($processIds, 0.1, $interceptor));
            $this->assertGreaterThanOrEqual(2, count($deleteAttempts));
            $this->assertStringContainsString('taskkill.exe:' . $lateProcessId, implode('|', $commands));
            $this->assertStringContainsString('taskkill.exe:' . $replacementProcessId, implode('|', $commands));
            $this->assertFileDoesNotExist($pidPath);
        } finally {
            if (is_file($pidPath)) {
                $this->assertTrue(unlink($pidPath));
            }
            foreach ($deleteAttempts as $claimedPath) {
                if (is_file($claimedPath)) {
                    unlink($claimedPath);
                }
            }
        }
    }

    public function test_control_process_cleanup_sanitizes_diagnostic_message(): void
    {
        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-diagnostic-');
        $this->assertIsString($pidPath);
        $this->assertSame(11, file_put_contents($pidPath, "2147482807\n"));
        $processIds = [];
        $failure = null;
        $primaryFailure = new \RuntimeException(
            $pidPath . "\tprimary\r\n" . str_repeat('p', 700)
        );
        $interceptor = function (array $command, float $deadline): array {
            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        };
        $deleter = function (string $claimedPath) use ($pidPath): bool {
            throw new \RuntimeException($pidPath . "\tsecret\r\n" . str_repeat('x', 700));
        };

        try {
            try {
                $this->cleanupControlProcessesForTest(
                    $primaryFailure,
                    $pidPath,
                    $processIds,
                    microtime(true) + 1.0,
                    $interceptor,
                    $deleter
                );
            } catch (\Throwable $error) {
                $failure = $error;
            }

            $this->assertInstanceOf(AssertionFailedError::class, $failure);
            $message = $failure->getMessage();
            $this->assertStringNotContainsString($pidPath, $message);
            $this->assertStringNotContainsString("\t", $message);
            $this->assertStringNotContainsString("\r", $message);
            $this->assertStringNotContainsString("\0", $message);
            $this->assertLessThan(700, strlen($message));
            $this->assertSame($primaryFailure, $failure->getPrevious());
        } finally {
            if (is_file($pidPath)) {
                $this->assertTrue(unlink($pidPath));
            }
        }
    }

    public function test_control_process_cleanup_collects_all_failures(): void
    {
        $firstProcessId = 2147482901;
        $secondProcessId = 2147482902;
        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-cleanup-');
        $this->assertIsString($pidPath);
        $this->assertSame(10, file_put_contents($pidPath, (string) $secondProcessId));
        $processIds = [$firstProcessId, $secondProcessId];
        $commandRecords = [];
        $tasklistCalls = [];
        $terminatedProcessIds = [];
        $deleteAttempts = [];
        $primaryFailure = new \RuntimeException('Injected primary scenario failure.');
        $failure = null;
        $interceptor = function (array $command, float $deadline) use (
            &$commandRecords,
            &$tasklistCalls,
            &$terminatedProcessIds,
            $firstProcessId
        ): array {
            $commandName = strtolower(basename($command[0]));
            if ($commandName === 'tasklist.exe') {
                $filterIndex = array_search('/FI', $command, true);
                $filter = is_int($filterIndex) && isset($command[$filterIndex + 1])
                    ? $command[$filterIndex + 1]
                    : '';
                if (preg_match('/^PID eq ([1-9][0-9]*)$/', $filter, $matches) !== 1) {
                    throw new \RuntimeException('The injected tasklist command did not contain a valid PID filter.');
                }
                $processId = (int) $matches[1];
                $commandRecords[] = $commandName . ':' . $processId;
                $tasklistCalls[$processId] = ($tasklistCalls[$processId] ?? 0) + 1;
                if ($processId === $firstProcessId && $tasklistCalls[$processId] === 2) {
                    throw new \RuntimeException('Injected final verification failure for PID ' . $processId . '.');
                }

                return [
                    'completed' => true,
                    'exitCode' => 0,
                    'stdout' => isset($terminatedProcessIds[$processId])
                        ? ''
                        : '"php.exe","' . $processId . '","Console","1","1 K"',
                    'stderr' => '',
                ];
            }

            if ($commandName !== 'taskkill.exe') {
                throw new \RuntimeException('Unexpected injected cleanup command: ' . $commandName . '.');
            }
            $pidIndex = array_search('/PID', $command, true);
            if (!is_int($pidIndex) || !isset($command[$pidIndex + 1])) {
                throw new \RuntimeException('The injected taskkill command did not contain a PID.');
            }
            $processId = (int) $command[$pidIndex + 1];
            $commandRecords[] = $commandName . ':' . $processId;
            if ($processId === $firstProcessId) {
                return [
                    'completed' => false,
                    'exitCode' => null,
                    'stdout' => '',
                    'stderr' => 'Injected first PID taskkill timeout.',
                ];
            }
            $terminatedProcessIds[$processId] = true;

            return [
                'completed' => true,
                'exitCode' => 0,
                'stdout' => 'SUCCESS',
                'stderr' => '',
            ];
        };
        $deleter = function (string $path) use (&$deleteAttempts): bool {
            $deleteAttempts[] = $path;
            throw new \RuntimeException('Injected control PID marker deletion failure.');
        };

        try {
            try {
                $this->cleanupControlProcessesForTest(
                    $primaryFailure,
                    $pidPath,
                    $processIds,
                    microtime(true) + 1.0,
                    $interceptor,
                    $deleter
                );
            } catch (\Throwable $error) {
                $failure = $error;
            }

            $this->assertInstanceOf(AssertionFailedError::class, $failure);
            $this->assertStringStartsWith($primaryFailure->getMessage(), $failure->getMessage());
            $this->assertStringContainsString('Injected first PID taskkill timeout.', $failure->getMessage());
            $this->assertStringContainsString(
                'Injected final verification failure for PID ' . $firstProcessId . '.',
                $failure->getMessage()
            );
            $this->assertStringContainsString(
                'Injected control PID marker deletion failure.',
                $failure->getMessage()
            );
            $this->assertSame($primaryFailure, $failure->getPrevious());
            $this->assertSame([$firstProcessId, $secondProcessId], $processIds);
            $this->assertSame(
                [
                    'tasklist.exe:' . $firstProcessId,
                    'taskkill.exe:' . $firstProcessId,
                    'tasklist.exe:' . $secondProcessId,
                    'taskkill.exe:' . $secondProcessId,
                    'tasklist.exe:' . $secondProcessId,
                    'tasklist.exe:' . $firstProcessId,
                    'tasklist.exe:' . $secondProcessId,
                ],
                $commandRecords
            );
            $this->assertCount(1, $deleteAttempts);
            $this->assertStringStartsWith($pidPath . '.cleanup-', $deleteAttempts[0]);
        } finally {
            if (is_file($pidPath)) {
                $this->assertTrue(unlink($pidPath));
            }
        }
    }

    public function test_control_process_cleanup_fails_closed_on_expired_deadline(): void
    {
        $processIds = [2147482903, 2147482904];
        $pidPath = tempnam(sys_get_temp_dir(), 'pid-js-cleanup-');
        $this->assertIsString($pidPath);
        $this->assertSame(3, file_put_contents($pidPath, '12x'));
        $commandRecords = [];
        $deleteAttempts = [];
        $failure = null;
        $interceptor = function (array $command, float $deadline) use (&$commandRecords): array {
            $commandName = strtolower(basename($command[0]));
            $filterIndex = array_search('/FI', $command, true);
            $filter = is_int($filterIndex) && isset($command[$filterIndex + 1])
                ? $command[$filterIndex + 1]
                : '';
            if ($commandName !== 'tasklist.exe'
                || preg_match('/^PID eq ([1-9][0-9]*)$/', $filter, $matches) !== 1) {
                throw new \RuntimeException('Unexpected command in the expired-deadline cleanup test.');
            }
            $commandRecords[] = $commandName . ':' . $matches[1];

            return [
                'completed' => false,
                'exitCode' => null,
                'stdout' => '',
                'stderr' => 'Injected expired cleanup probe.',
            ];
        };
        $deleter = function (string $path) use (&$deleteAttempts): bool {
            $deleteAttempts[] = $path;

            return unlink($path);
        };

        try {
            try {
                $this->cleanupControlProcessesForTest(
                    null,
                    $pidPath,
                    $processIds,
                    microtime(true) - 1.0,
                    $interceptor,
                    $deleter
                );
            } catch (\Throwable $error) {
                $failure = $error;
            }

            $this->assertInstanceOf(AssertionFailedError::class, $failure);
            $this->assertStringStartsWith('Control-process cleanup failed.', $failure->getMessage());
            $this->assertStringContainsString(
                'Control PID marker must contain exactly one decimal PID between 1 and 4294967295.',
                $failure->getMessage()
            );
            foreach ($processIds as $processId) {
                $this->assertStringContainsString(
                    'PID ' . $processId . ' could not be killed before the cleanup deadline.',
                    $failure->getMessage()
                );
                $this->assertStringContainsString(
                    'PID ' . $processId . ' could not be verified before the cleanup deadline.',
                    $failure->getMessage()
                );
            }
            $this->assertNull($failure->getPrevious());
            $this->assertSame([], $commandRecords);
            $this->assertSame([], $deleteAttempts);
            $this->assertFileExists($pidPath);
        } finally {
            if (is_file($pidPath)) {
                $this->assertTrue(unlink($pidPath));
            }
        }
    }

    /** @return array<string, array{string}> */
    public function invalidControlProcessIdMarkerProvider(): array
    {
        return [
            'empty marker' => [''],
            'partially written marker' => ['12x'],
            'zero PID' => ['0'],
            'negative PID' => ['-12'],
            'leading whitespace' => [' 12\n'],
            'trailing whitespace' => ['12 \n'],
            'tab suffix' => ["12\t\n"],
            'nul byte' => ["12\0\n"],
            'double line ending' => ["12\n\n"],
            'lone carriage return' => ["12\r"],
            'leading zero' => ["012\n"],
            'above Windows uint32 range' => ['4294967296'],
            'above PHP integer range' => [str_repeat('9', strlen((string) PHP_INT_MAX) + 1)],
        ];
    }

    /**
     * @param array<int|string, mixed> $processIds
     * @return array<int, int>
     */
    private function normalizeControlProcessIdsForTest(array $processIds, int $phpIntegerSize = null): array
    {
        $phpIntegerSize = $phpIntegerSize ?? PHP_INT_SIZE;
        if ($phpIntegerSize < 8) {
            throw new \RuntimeException(
                '32-bit PHP cannot safely represent the complete Windows uint32 PID range.'
            );
        }

        $normalized = [];
        $seen = [];
        $position = 0;
        foreach ($processIds as $processId) {
            $position++;
            if (!is_int($processId) || $processId < 1 || $processId > 4294967295) {
                throw new \RuntimeException(
                    sprintf(
                        'Control process IDs must be native integers between 1 and 4294967295 '
                        . '(invalid position %d).',
                        $position
                    )
                );
            }

            if (isset($seen[$processId])) {
                continue;
            }
            $seen[$processId] = true;
            $normalized[] = $processId;
        }

        return $normalized;
    }

    private function readControlProcessIdMarkerForTest(string $pidPath): int
    {
        $contents = @file_get_contents($pidPath);
        if (!is_string($contents)) {
            throw new \RuntimeException('Control PID marker could not be read.');
        }

        return $this->parseControlProcessIdMarkerContentsForTest($contents);
    }

    private function parseControlProcessIdMarkerContentsForTest(string $contents): int
    {
        if (preg_match('/\A([1-9][0-9]*)(?:\r\n|\n)?\z/D', $contents, $matches) !== 1) {
            throw new \RuntimeException(
                'Control PID marker must contain exactly one decimal PID between 1 and 4294967295. '
                . 'Only an optional LF or CRLF line ending is allowed.'
            );
        }

        $processId = $matches[1];
        $maximumProcessId = '4294967295';
        $exceedsMaximum = strlen($processId) > strlen($maximumProcessId)
            || (strlen($processId) === strlen($maximumProcessId)
                && strcmp($processId, $maximumProcessId) > 0);
        if ($exceedsMaximum) {
            throw new \RuntimeException(
                'Control PID marker must contain exactly one decimal PID between 1 and 4294967295. '
                . 'Only an optional LF or CRLF line ending is allowed.'
            );
        }

        if (PHP_INT_SIZE < 8 && (strlen($processId) > strlen((string) PHP_INT_MAX)
            || (strlen($processId) === strlen((string) PHP_INT_MAX)
                && strcmp($processId, (string) PHP_INT_MAX) > 0))) {
            throw new \RuntimeException(
                'Control PID marker cannot be represented safely by 32-bit PHP.'
            );
        }

        return (int) $processId;
    }

    private function sanitizeControlDiagnosticForTest(string $message): string
    {
        return $this->sanitizeControlCleanupMessage($message);
    }

    private function sanitizeControlCleanupMessage(string $message): string
    {
        $message = preg_replace('/[A-Za-z]:[\\\\\/][^\r\n\t ]+/', '[redacted-path]', $message) ?? '';
        $message = preg_replace('/(?<![A-Za-z0-9])[\\\\\/](?:[^\r\n\t ]+?[\\\\\/])*[^\r\n\t ]+/', '[redacted-path]', $message)
            ?? $message;
        $message = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/', ' ', $message) ?? $message;
        $message = preg_replace('/[ ]{2,}/', ' ', $message) ?? $message;
        $message = trim($message);
        if (strlen($message) > 240) {
            $message = substr($message, 0, 237) . '...';
        }

        return $message;
    }

    /** @return array{pid:int, fingerprint:string}|null */
    private function observeControlPidMarkerForTest(string $pidPath): ?array
    {
        $stream = @fopen($pidPath, 'rb');
        if ($stream === false) {
            if (!file_exists($pidPath)) {
                return null;
            }
            throw new \RuntimeException('Control PID marker could not be read.');
        }

        try {
            $contents = stream_get_contents($stream, 64);
            $extra = fread($stream, 1);
            if (!is_string($contents) || ($extra !== false && $extra !== '')) {
                $contents = is_string($contents) ? $contents . (is_string($extra) ? $extra : '') : '';
            }
        } finally {
            fclose($stream);
        }

        $pid = $this->parseControlProcessIdMarkerContentsForTest($contents);

        return [
            'pid' => $pid,
            'fingerprint' => hash('sha256', $contents),
        ];
    }

    /**
     * @return array{status:'deleted'|'missing'|'changed', pid?:int, fingerprint?:string}
     */
    private function claimControlPidMarkerForTest(
        string $pidPath,
        int $expectedPid,
        string $expectedFingerprint,
        callable $pidMarkerDeleter = null
    ): array {
        try {
            $claimSuffix = bin2hex(random_bytes(8));
        } catch (\Throwable $e) { // PHP 7.4 兼容：异常捕获必须携带变量。
            $claimSuffix = str_replace('.', '', uniqid('', true));
        }
        $claimPath = $pidPath . '.cleanup-' . $claimSuffix;

        if (!@rename($pidPath, $claimPath)) {
            if (!file_exists($pidPath)) {
                return ['status' => 'missing'];
            }
            throw new \RuntimeException('Control PID marker could not be claimed atomically.');
        }

        $restoreClaim = true;
        try {
            $claimed = $this->observeControlPidMarkerForTest($claimPath);
            if ($claimed === null) {
                throw new \RuntimeException('Control PID marker claim could not be read.');
            }
            if ($claimed['pid'] !== $expectedPid || $claimed['fingerprint'] !== $expectedFingerprint) {
                return [
                    'status' => 'changed',
                    'pid' => $claimed['pid'],
                    'fingerprint' => $claimed['fingerprint'],
                ];
            }

            try {
                $deleted = $pidMarkerDeleter === null
                    ? @unlink($claimPath)
                    : $pidMarkerDeleter($claimPath);
                if (!$deleted) {
                    throw new \RuntimeException('Control PID marker deletion returned false.');
                }
                $restoreClaim = false;

                return ['status' => 'deleted'];
            } catch (\Throwable $error) {
                throw new \RuntimeException(
                    $this->sanitizeControlDiagnosticForTest($error->getMessage()),
                    0,
                    $error
                );
            }
        } finally {
            if ($restoreClaim && is_file($claimPath) && !file_exists($pidPath)) {
                @rename($claimPath, $pidPath);
            } elseif ($restoreClaim && is_file($claimPath) && file_exists($pidPath)) {
                @unlink($claimPath);
            }
        }
    }

    /**
     * @param array<int, int> $processIds
     * @param (callable(array<int, string>, float): (array{
     *     completed: bool,
     *     exitCode: ?int,
     *     stdout: string,
     *     stderr: string
     * }|null))|null $windowsCommandInterceptor
     * @param (callable(string): bool)|null $pidMarkerDeleter
     */
    private function cleanupControlProcessesForTest(
        \Throwable $primaryFailure = null,
        string $pidPath,
        array &$processIds,
        float $cleanupDeadline,
        callable $windowsCommandInterceptor = null,
        callable $pidMarkerDeleter = null
    ): void {
        $cleanupFailures = [];
        try {
            $processIds = $this->normalizeControlProcessIdsForTest($processIds);
        } catch (\Throwable $error) {
            $cleanupFailures[] = 'Control process ID validation failed: '
                . $this->sanitizeControlDiagnosticForTest($error->getMessage());
            $validProcessIds = [];
            if (PHP_INT_SIZE >= 8) {
                foreach ($processIds as $processId) {
                    if (is_int($processId) && $processId >= 1 && $processId <= 4294967295) {
                        $validProcessIds[] = $processId;
                    }
                }
            }
            $processIds = PHP_INT_SIZE >= 8
                ? $this->normalizeControlProcessIdsForTest($validProcessIds)
                : [];
        }

        $processedProcessIds = [];
        $verifiedProcessIds = [];
        $markerSnapshot = null;
        $markerBlocked = false;
        $maxPasses = 12;
        $pass = 0;

        for (; $pass < $maxPasses; $pass++) {
            if (!$markerBlocked) {
                try {
                    $observedMarker = $this->observeControlPidMarkerForTest($pidPath);
                    if ($observedMarker !== null) {
                        $markerSnapshot = $observedMarker;
                        if (!in_array($observedMarker['pid'], $processIds, true)) {
                            $processIds[] = $observedMarker['pid'];
                        }
                    }
                } catch (\Throwable $error) {
                    $cleanupFailures[] = 'Control PID marker read failed: '
                        . $this->sanitizeControlDiagnosticForTest($error->getMessage());
                    $markerBlocked = true;
                }
            }

            foreach ($processIds as $processId) {
                if (isset($processedProcessIds[$processId])) {
                    continue;
                }
                if (microtime(true) >= $cleanupDeadline) {
                    $cleanupFailures[] = sprintf(
                        'PID %d could not be killed before the cleanup deadline.',
                        $processId
                    );
                    continue;
                }

                try {
                    $this->forceKillProcessTreeForTest(
                        $processId,
                        $cleanupDeadline,
                        $windowsCommandInterceptor
                    );
                    $processedProcessIds[$processId] = true;
                } catch (\Throwable $error) {
                    $cleanupFailures[] = sprintf(
                        'PID %d kill failed: %s',
                        $processId,
                        $this->sanitizeControlDiagnosticForTest($error->getMessage())
                    );
                }
            }

            foreach ($processIds as $processId) {
                if (isset($verifiedProcessIds[$processId])) {
                    continue;
                }
                if (microtime(true) >= $cleanupDeadline) {
                    $cleanupFailures[] = sprintf(
                        'PID %d could not be verified before the cleanup deadline.',
                        $processId
                    );
                    continue;
                }

                try {
                    if ($this->isProcessRunningForTest(
                        $processId,
                        $cleanupDeadline,
                        $windowsCommandInterceptor
                    )) {
                        $cleanupFailures[] = sprintf('PID %d remained alive after cleanup.', $processId);
                    } else {
                        $verifiedProcessIds[$processId] = true;
                    }
                } catch (\Throwable $error) {
                    $cleanupFailures[] = sprintf(
                        'PID %d verification failed: %s',
                        $processId,
                        $this->sanitizeControlDiagnosticForTest($error->getMessage())
                    );
                }
            }

            if ($markerBlocked) {
                break;
            }

            try {
                $latestMarker = $this->observeControlPidMarkerForTest($pidPath);
            } catch (\Throwable $error) {
                $cleanupFailures[] = 'Control PID marker read failed: '
                    . $this->sanitizeControlDiagnosticForTest($error->getMessage());
                break;
            }

            if ($latestMarker === null) {
                break;
            }

            if ($markerSnapshot === null
                || $latestMarker['pid'] !== $markerSnapshot['pid']
                || $latestMarker['fingerprint'] !== $markerSnapshot['fingerprint']) {
                $markerSnapshot = $latestMarker;
                if (!in_array($latestMarker['pid'], $processIds, true)) {
                    $processIds[] = $latestMarker['pid'];
                }
                continue;
            }

            $markerPid = $markerSnapshot['pid'];
            if (!isset($processedProcessIds[$markerPid]) || !isset($verifiedProcessIds[$markerPid])) {
                continue;
            }

            try {
                $claimResult = $this->claimControlPidMarkerForTest(
                    $pidPath,
                    $markerSnapshot['pid'],
                    $markerSnapshot['fingerprint'],
                    $pidMarkerDeleter
                );
            } catch (\Throwable $error) {
                $cleanupFailures[] = 'Control PID marker deletion failed: '
                    . $this->sanitizeControlDiagnosticForTest($error->getMessage());
                break;
            }

            if ($claimResult['status'] === 'changed') {
                $markerSnapshot = [
                    'pid' => $claimResult['pid'],
                    'fingerprint' => $claimResult['fingerprint'],
                ];
                if (!in_array($claimResult['pid'], $processIds, true)) {
                    $processIds[] = $claimResult['pid'];
                }
                continue;
            }

            $markerSnapshot = null;
        }

        if ($pass >= $maxPasses && !$markerBlocked) {
            try {
                if ($this->observeControlPidMarkerForTest($pidPath) !== null) {
                    $cleanupFailures[] = 'Control PID marker remained after bounded cleanup passes.';
                }
            } catch (\Throwable $error) {
                $cleanupFailures[] = 'Control PID marker final read failed: '
                    . $this->sanitizeControlDiagnosticForTest($error->getMessage());
            }
        }

        if ($cleanupFailures === []) {
            return;
        }

        $cleanupDiagnostic = implode("\n", array_map(
            function (string $failure): string {
                return '- ' . $failure;
            },
            $cleanupFailures
        ));
        $primaryDiagnostic = $primaryFailure === null
            ? null
            : $this->sanitizeControlDiagnosticForTest($primaryFailure->getMessage());
        $message = $primaryDiagnostic === null
            ? "Control-process cleanup failed.\n" . $cleanupDiagnostic
            : $primaryDiagnostic
                . "\nControl-process cleanup also failed.\n"
                . $cleanupDiagnostic;

        throw new AssertionFailedError($message, 0, $primaryFailure);
    }

    /** @return array<string, int> */
    private function parseJavascriptProcessMarker(string $contents): array
    {
        return $this->parsePidMarkerLabelsForTest($contents);
    }

    /** @return array<string, int> */
    private function parsePidMarkerLabelsForTest(string $contents): array
    {
        preg_match_all('/^(parentPid|childPid|grandchildPid)=(\d+)$/m', str_replace("\r", '', $contents), $matches);
        $processIds = [];
        foreach ($matches[1] as $index => $label) {
            $processIds[$label] = (int) $matches[2][$index];
        }

        return $processIds;
    }

    /** @dataProvider javascriptTemporaryFileCreationFailureProvider */
    public function test_javascript_temporary_file_creation_failure_cleans_up(
        int $failureCall
    ): void {
        $factoryCalls = 0;
        $createdPaths = [];
        $failure = null;
        $temporaryFileFactory = function (string $directory, string $prefix) use (
            &$factoryCalls,
            &$createdPaths,
            $failureCall
        ): string {
            $factoryCalls++;
            if ($factoryCalls === $failureCall) {
                throw new \RuntimeException('Temporary file creation failed at call ' . $factoryCalls . '.');
            }

            $path = tempnam($directory, $prefix);
            if (!is_string($path)) {
                throw new \RuntimeException('The test temporary file could not be created.');
            }
            $createdPaths[] = $path;

            return $path;
        };

        try {
            $this->executeJavascriptJson(
                "'use strict';\nconsole.log(JSON.stringify({finished: true}));",
                1.0,
                $temporaryFileFactory
            );
        } catch (\RuntimeException $error) {
            $failure = $error;
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame('Temporary file creation failed at call ' . $failureCall . '.', $failure->getMessage());
        $this->assertSame($failureCall, $factoryCalls);
        $this->assertCount($failureCall - 1, $createdPaths);
        foreach ($createdPaths as $createdPath) {
            $this->assertFileDoesNotExist($createdPath, 'A partially created runner file was leaked.');
        }
        $this->assertSame(
            ['finished' => true],
            $this->executeJavascriptJson("'use strict';\nconsole.log(JSON.stringify({finished: true}));")
        );
    }

    /** @return array<string, array{int}> */
    public function javascriptTemporaryFileCreationFailureProvider(): array
    {
        return [
            'first file creation fails' => [1],
            'second file creation fails' => [2],
            'third file creation fails' => [3],
            'fourth file creation fails' => [4],
        ];
    }

    public function test_javascript_temporary_file_deletion_failure_asserts(): void
    {
        $createdPaths = [];
        $deleteAttempts = [];
        $failure = null;
        $temporaryFileFactory = function (string $directory, string $prefix) use (&$createdPaths): string {
            $path = tempnam($directory, $prefix);
            if (!is_string($path)) {
                throw new \RuntimeException('The test temporary file could not be created.');
            }
            $createdPaths[] = $path;

            return $path;
        };
        $temporaryFileDeleter = function (string $path) use (&$deleteAttempts): bool {
            $deleteAttempts[] = $path;
            $deleted = unlink($path);
            if (count($deleteAttempts) === 1) {
                throw new \RuntimeException('The first test deletion failed after removing the file.');
            }
            if (count($deleteAttempts) === 2) {
                return false;
            }

            return $deleted;
        };

        try {
            $this->executeJavascriptJson(
                "'use strict';\nconsole.log(JSON.stringify({finished: true}));",
                1.0,
                $temporaryFileFactory,
                $temporaryFileDeleter
            );
        } catch (AssertionFailedError $error) {
            $failure = $error;
        }

        $this->assertInstanceOf(AssertionFailedError::class, $failure);
        $this->assertStringContainsString('The first test deletion failed after removing the file.', $failure->getMessage());
        $this->assertStringContainsString('could not be deleted: ' . $createdPaths[1], $failure->getMessage());
        $this->assertCount(4, $createdPaths);
        $this->assertSame($createdPaths, $deleteAttempts);
        foreach ($createdPaths as $createdPath) {
            $this->assertFileDoesNotExist($createdPath);
        }
        $this->assertSame(
            ['finished' => true],
            $this->executeJavascriptJson("'use strict';\nconsole.log(JSON.stringify({finished: true}));")
        );
    }

    /**
     * @param array<int, int> $processIds
     * @param (callable(array<int, string>, float): (array{
     *     completed: bool,
     *     exitCode: ?int,
     *     stdout: string,
     *     stderr: string
     * }|null))|null $windowsCommandInterceptor
     * @return array<int, int>
     */
    private function waitForProcessIdsToExit(
        array $processIds,
        float $timeoutSeconds,
        callable $windowsCommandInterceptor = null
    ): array {
        $processIds = $this->normalizeControlProcessIdsForTest($processIds);
        $deadline = microtime(true) + $timeoutSeconds;
        if ($windowsCommandInterceptor === null && PHP_OS_FAMILY === 'Windows') {
            if ($timeoutSeconds <= 0.0) {
                return $processIds;
            }
            $pendingProcessIds = $processIds;
            do {
                $probeCommand = array_merge(
                    [$this->prepareWindowsProcessTreeControlForTest(), '--probe'],
                    array_map('strval', $pendingProcessIds)
                );
                $result = $this->runBoundedWindowsCommand(
                    $probeCommand,
                    $deadline
                );
                if (!$result['completed']) {
                    return $pendingProcessIds;
                }
                $this->assertTrue($result['completed'], trim($result['stderr']) ?: 'Node PID batch probe timed out.');
                $this->assertSame(0, $result['exitCode'], trim($result['stderr']) ?: 'Native PID batch probe failed.');
                $decoded = json_decode(trim($result['stdout']), true);
                $this->assertIsArray($decoded, 'Native PID batch probe returned invalid JSON.');
                $pendingProcessIds = $this->normalizeControlProcessIdsForTest($decoded);
                if ($pendingProcessIds === []) {
                    return [];
                }
                $remainingMicroseconds = (int) (($deadline - microtime(true)) * 1000000);
                if ($remainingMicroseconds <= 0) {
                    return $pendingProcessIds;
                }
                usleep(min(10000, $remainingMicroseconds));
            } while (microtime(true) < $deadline);

            return $pendingProcessIds;
        }
        $pendingProcessIds = array_values($processIds);

        while ($pendingProcessIds !== []) {
            $runningProcessIds = [];
            foreach ($pendingProcessIds as $index => $processId) {
                if (microtime(true) >= $deadline) {
                    return array_values(array_merge(
                        $runningProcessIds,
                        array_slice($pendingProcessIds, $index)
                    ));
                }

                if ($this->isProcessRunningForTest($processId, $deadline, $windowsCommandInterceptor)) {
                    $runningProcessIds[] = $processId;
                }
            }
            if ($runningProcessIds === []) {
                return [];
            }

            $remainingMicroseconds = (int) (($deadline - microtime(true)) * 1000000);
            if ($remainingMicroseconds <= 0) {
                return $runningProcessIds;
            }
            usleep(min(10000, $remainingMicroseconds));
            $pendingProcessIds = $runningProcessIds;
        }

        return [];
    }

    /**
     * @param array<int, int> $processIds
     * @param array<int, int> $registeredAtByPid
     * @return array<int, int>
     */
    private function waitForProcessIdentitiesToExit(
        array $processIds,
        array $registeredAtByPid,
        array $exitedAtByPid,
        float $timeoutSeconds,
        callable $windowsCommandInterceptor = null
    ): array {
        $deadline = microtime(true) + $timeoutSeconds;
        $pendingProcessIds = array_values(array_intersect($processIds, array_keys($registeredAtByPid)));
        while ($pendingProcessIds !== [] && microtime(true) < $deadline) {
            if ($deadline - microtime(true) <= 0.05) {
                return $pendingProcessIds;
            }
            $probeCommand = array_merge(
                [$this->prepareWindowsProcessTreeControlForTest(), '--probe'],
                array_map(
                    function (int $processId) use ($registeredAtByPid, $exitedAtByPid): string {
                        $identity = $processId . ':' . $registeredAtByPid[$processId];

                        return isset($exitedAtByPid[$processId])
                            ? $identity . ':' . $exitedAtByPid[$processId]
                            : $identity;
                    },
                    $pendingProcessIds
                )
            );
            $result = $this->runBoundedWindowsCommand($probeCommand, $deadline, $windowsCommandInterceptor);
            $this->assertTrue(
                $result['completed'],
                trim($result['stderr']) ?: 'Identity-aware native PID probe timed out.'
            );
            $this->assertSame(
                0,
                $result['exitCode'],
                trim($result['stderr']) ?: 'Identity-aware native PID probe failed.'
            );
            $decoded = json_decode(trim($result['stdout']), true);
            $this->assertIsArray($decoded, 'Identity-aware native PID probe returned invalid JSON.');
            $pendingProcessIds = array_map('intval', $decoded);
            if ($pendingProcessIds !== []) {
                usleep(min(10000, max(1, (int) (($deadline - microtime(true)) * 1000000))));
            }
        }

        return $pendingProcessIds;
    }

    private function readProcessCreationTokenForTest(int $processId): int
    {
        $result = $this->runBoundedWindowsCommand(
            [$this->prepareWindowsProcessTreeControlForTest(), '--identity', (string) $processId],
            microtime(true) + 1.0
        );
        $this->assertTrue($result['completed'], $result['stderr']);
        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $token = trim($result['stdout']);
        $this->assertMatchesRegularExpression('/\A[1-9][0-9]*\z/', $token);

        return (int) $token;
    }


    /**
     * @param (callable(array<int, string>, float): (array{
     *     completed: bool,
     *     exitCode: ?int,
     *     stdout: string,
     *     stderr: string
     * }|null))|null $windowsCommandInterceptor
     */
    private function isProcessRunningForTest(
        int $processId,
        float $deadline = null,
        callable $windowsCommandInterceptor = null
    ): bool
    {
        if ($windowsCommandInterceptor === null
            && PHP_OS_FAMILY !== 'Windows'
            && function_exists('posix_kill')) {
            return @posix_kill($processId, 0);
        }

        if ($windowsCommandInterceptor === null && PHP_OS_FAMILY === 'Windows') {
            $result = $this->runBoundedWindowsCommand(
                [$this->prepareWindowsProcessTreeControlForTest(), '--probe', (string) $processId],
                $deadline ?? microtime(true) + 0.50
            );
            $this->assertTrue($result['completed'], trim($result['stderr']) ?: 'Native PID probe timed out.');
            $this->assertSame(0, $result['exitCode'], trim($result['stderr']) ?: 'Native PID probe failed.');
            $probe = json_decode(trim($result['stdout']), true);
            $this->assertIsArray($probe, 'Native PID probe returned invalid JSON.');

            return in_array($processId, $probe, true);
        }

        $result = $this->runBoundedWindowsCommand(
            ['tasklist.exe', '/FI', 'PID eq ' . $processId, '/FO', 'CSV', '/NH'],
            $deadline ?? microtime(true) + 0.50,
            $windowsCommandInterceptor
        );
        if ($windowsCommandInterceptor === null
            && PHP_OS_FAMILY === 'Windows'
            && (!$result['completed'] || $result['exitCode'] !== 0)) {
            $result = $this->runBoundedWindowsCommand(
                [
                    'node',
                    '-e',
                    "try { process.kill(" . $processId . ", 0); process.stdout.write('ALIVE'); } "
                        . "catch (error) { if (error && error.code === 'ESRCH') "
                        . "{ process.stdout.write('DEAD'); } else { throw error; } }",
                ],
                $deadline ?? microtime(true) + 0.50
            );
            $this->assertTrue($result['completed'], trim($result['stderr']) ?: 'PowerShell PID probe timed out.');
            $this->assertSame(0, $result['exitCode'], trim($result['stderr']) ?: 'PowerShell PID probe failed.');
            $probe = trim($result['stdout']);
            $this->assertContains($probe, ['ALIVE', 'DEAD'], 'PowerShell PID probe returned an invalid result.');

            return $probe === 'ALIVE';
        }
        $this->assertTrue(
            $result['completed'],
            sprintf(
                'tasklist did not complete before the process-probe deadline (exit=%s, stderr=%s).',
                $result['exitCode'] === null ? 'unknown' : (string) $result['exitCode'],
                trim($result['stderr']) ?: 'none'
            )
        );
        $this->assertSame(0, $result['exitCode'], trim($result['stderr']) ?: 'tasklist failed.');

        return preg_match('/"[^\"]+","' . $processId . '",/', $result['stdout']) === 1;
    }

    /**
     * @param (callable(array<int, string>, float): (array{
     *     completed: bool,
     *     exitCode: ?int,
     *     stdout: string,
     *     stderr: string
     * }|null))|null $windowsCommandInterceptor
     */
    private function forceKillProcessTreeForTest(
        int $processId,
        float $deadline = null,
        callable $windowsCommandInterceptor = null
    ): void
    {
        $deadline = $deadline ?? microtime(true) + 0.75;
        if (microtime(true) >= $deadline) {
            $this->fail(sprintf(
                'Cleanup deadline expired before probing PID %d.',
                $processId
            ));
        }
        if (!$this->isProcessRunningForTest($processId, $deadline, $windowsCommandInterceptor)) {
            return;
        }
        if (microtime(true) >= $deadline) {
            $this->fail(sprintf(
                'Cleanup deadline expired before taskkill for PID %d.',
                $processId
            ));
        }

        if ($windowsCommandInterceptor === null
            && PHP_OS_FAMILY !== 'Windows'
            && function_exists('posix_kill')) {
            @posix_kill($processId, 9);

            return;
        }

        if ($windowsCommandInterceptor === null && PHP_OS_FAMILY === 'Windows') {
            $this->fail(sprintf(
                'Identity-aware Windows cleanup requires a creation token for PID %d.',
                $processId
            ));
        }

        $result = $this->runBoundedWindowsCommand(
            ['taskkill.exe', '/PID', (string) $processId, '/T', '/F'],
            $deadline,
            $windowsCommandInterceptor
        );
        if ($windowsCommandInterceptor === null
            && PHP_OS_FAMILY === 'Windows'
            && (!$result['completed'] || $result['exitCode'] !== 0)) {
            $result = $this->runBoundedWindowsCommand(
                [$this->prepareWindowsProcessTreeControlForTest(), (string) $processId],
                $deadline
            );
        }
        $this->assertTrue(
            $result['completed'],
            sprintf(
                'taskkill did not complete before the test-cleanup deadline (exit=%s, stderr=%s).',
                $result['exitCode'] === null ? 'unknown' : (string) $result['exitCode'],
                trim($result['stderr']) ?: 'none'
            )
        );
        $this->assertSame(0, $result['exitCode'], trim($result['stderr']) ?: 'taskkill failed.');

        do {
            $this->assertLessThan(
                $deadline,
                microtime(true),
                sprintf(
                    'Cleanup deadline expired before verifying PID %d after taskkill.',
                    $processId
                )
            );
            if (!$this->isProcessRunningForTest($processId, $deadline, $windowsCommandInterceptor)) {
                return;
            }

            $remainingMicroseconds = (int) (($deadline - microtime(true)) * 1000000);
            if ($remainingMicroseconds > 0) {
                usleep(min(10000, $remainingMicroseconds));
            }
        } while (microtime(true) < $deadline);

        $this->fail(sprintf(
            'taskkill reported success but process %d remained alive until the test-cleanup deadline.',
            $processId
        ));
    }

    /** @dataProvider sharedAjaxMethodProvider */
    public function test_shared_ajax_scenario_headers_contract(
        string $method
    ): void
    {
        $customHeaders = [
            'Idempotency-Key' => 'idempotency-key-1',
            'X-Custom' => 'custom-value',
        ];
        $internal = $this->executeSharedAjaxScenario(
            $method,
            'done',
            1000,
            true,
            false,
            false,
            false,
            false,
            '/api/front/test',
            $customHeaders
        );

        $this->assertSame(1, $internal['ajaxCount']);
        $this->assertSame('idempotency-key-1', $internal['ajaxOptions'][0]['headers']['Idempotency-Key']);
        $this->assertSame('custom-value', $internal['ajaxOptions'][0]['headers']['X-Custom']);
        $this->assertSame('application/json', $internal['ajaxOptions'][0]['headers']['Accept']);
        $this->assertSame('zh-CN', $internal['ajaxOptions'][0]['headers']['X-Locale']);
        $this->assertSame('Bearer front-token', $internal['ajaxOptions'][0]['headers']['Authorization']);

        $external = $this->executeSharedAjaxScenario(
            $method,
            'done',
            1000,
            true,
            false,
            false,
            false,
            false,
            'https://external.example/api',
            $customHeaders
        );

        $this->assertSame(0, $external['ajaxCount']);
        $this->assertSame([5000], $external['errorCodes']);
        $this->assertSame(1, $external['errorCount']);
        $this->assertSame(0, $external['successCount']);
        $this->assertFalse($external['maskVisible']);
    }

    public function sharedAjaxMethodProvider(): array
    {
        return [
            'request' => ['request'],
            'upload' => ['upload'],
        ];
    }

    /** @dataProvider sharedAjaxAuthenticationFailureProvider */
    public function test_shared_ajax_authentication_failure_redirects(
        string $method,
        string $transport,
        int $code,
        bool $authRedirect,
        string $expectedLocation
    ): void {
        $result = $this->executeSharedAjaxAuthenticationScenario(
            $method,
            $transport,
            $code,
            $authRedirect
        );

        $this->assertNull($result['frontToken']);
        $this->assertNull($result['frontJwtToken']);
        $this->assertSame($expectedLocation, $result['location']);
        $this->assertSame(1, $result['errorCount']);
        $this->assertSame(0, $result['successCount']);
        $this->assertSame($code === 4003 && $authRedirect ? 1 : 0, $result['alertCount']);
    }

    public function sharedAjaxAuthenticationFailureProvider(): array
    {
        return [
            'request done auth failed redirects' => ['request', 'done', 4001, true, '/front/login'],
            'request fail token expired redirects' => ['request', 'fail', 4002, true, '/front/login'],
            'request done SSO redirects once' => ['request', 'done', 4003, true, '/front/login'],
            'request fail missing token redirects' => ['request', 'fail', 4004, true, '/front/login'],
            'request explicit opt-out does not redirect' => ['request', 'done', 4001, false, '/current'],
            'upload done auth failed redirects' => ['upload', 'done', 4001, true, '/front/login'],
            'upload fail token expired redirects' => ['upload', 'fail', 4002, true, '/front/login'],
            'upload explicit opt-out does not redirect' => ['upload', 'fail', 4004, false, '/current'],
        ];
    }

    /** @dataProvider sharedAjaxThrowingAuthenticationCallbackProvider */
    public function test_shared_ajax_throwing_auth_callback_surfaces_error(
        string $method,
        string $transport,
        int $code
    ): void {
        $result = $this->executeSharedAjaxScenario(
            $method,
            $transport,
            $code,
            true,
            false,
            true
        );

        $this->assertSame('/front/login', $result['location']);
        $this->assertFalse($result['maskVisible']);
        $this->assertSame(1, $result['completeCount']);
        $this->assertSame([], $result['synchronousErrors']);
        $this->assertSame(['business error callback failed'], $result['surfacedErrors']);
    }

    public function sharedAjaxThrowingAuthenticationCallbackProvider(): array
    {
        return [
            'request done' => ['request', 'done', 4001],
            'request fail' => ['request', 'fail', 4002],
            'upload done' => ['upload', 'done', 4001],
            'upload fail' => ['upload', 'fail', 4002],
        ];
    }

    /** @dataProvider sharedAjaxThrowingBusinessCallbackProvider */
    public function test_shared_ajax_throwing_business_callback_surfaces_error(
        string $method,
        string $transport
    ): void {
        $result = $this->executeSharedAjaxScenario(
            $method,
            $transport,
            $transport === 'done' ? 1000 : 5000,
            true,
            $transport === 'done',
            $transport === 'fail'
        );

        $this->assertSame('/current', $result['location']);
        $this->assertFalse($result['maskVisible']);
        $this->assertSame(1, $result['completeCount']);
        $this->assertSame([], $result['synchronousErrors']);
        $this->assertSame([
            $transport === 'done' ? 'business success callback failed' : 'business error callback failed',
        ], $result['surfacedErrors']);
    }

    public function sharedAjaxThrowingBusinessCallbackProvider(): array
    {
        return [
            'request done' => ['request', 'done'],
            'request fail' => ['request', 'fail'],
            'upload done' => ['upload', 'done'],
            'upload fail' => ['upload', 'fail'],
        ];
    }

    public function test_shared_ajax_sso_alert_failure_surfaces_error(): void
    {
        $result = $this->executeSharedAjaxScenario(
            'request',
            'done',
            4003,
            true,
            false,
            false,
            true
        );

        $this->assertSame('/front/login', $result['location']);
        $this->assertFalse($result['maskVisible']);
        $this->assertSame(1, $result['completeCount']);
        $this->assertSame([], $result['synchronousErrors']);
        $this->assertSame(['SSO alert failed'], $result['surfacedErrors']);
    }

    public function test_shared_ajax_complete_callback_failure_surfaces_error(): void
    {
        $result = $this->executeSharedAjaxScenario(
            'upload',
            'done',
            1000,
            true,
            false,
            false,
            false,
            true
        );

        $this->assertFalse($result['maskVisible']);
        $this->assertSame(1, $result['successCount']);
        $this->assertSame(1, $result['completeCount']);
        $this->assertSame([], $result['synchronousErrors']);
        $this->assertSame(['complete callback failed'], $result['surfacedErrors']);
    }

    /** @return array<string, mixed> */
    private function executeSharedAjaxAuthenticationScenario(
        string $method,
        string $transport,
        int $code,
        bool $authRedirect
    ): array {
        return $this->executeSharedAjaxScenario($method, $transport, $code, $authRedirect);
    }

    /** @return array<string, mixed> */
    private function executeSharedAjaxScenario(
        string $method,
        string $transport,
        int $code,
        bool $authRedirect,
        bool $throwSuccess = false,
        bool $throwError = false,
        bool $throwAlert = false,
        bool $throwComplete = false,
        string $url = '/api/front/test',
        array $customHeaders = []
    ): array {
        $source = file_get_contents(public_path('js/shared/ajax.js'));
        $this->assertIsString($source, 'The shared Ajax source must be readable.');
        $methodJson = json_encode($method);
        $transportJson = json_encode($transport);
        $codeJson = json_encode($code);
        $authRedirectJson = json_encode($authRedirect);
        $throwSuccessJson = json_encode($throwSuccess);
        $throwErrorJson = json_encode($throwError);
        $throwAlertJson = json_encode($throwAlert);
        $throwCompleteJson = json_encode($throwComplete);
        $urlJson = json_encode($url, JSON_UNESCAPED_SLASHES);
        $customHeadersJson = json_encode($customHeaders, JSON_UNESCAPED_SLASHES);

        return $this->executeJavascriptJson(<<<JS
'use strict';
var method = {$methodJson};
var transport = {$transportJson};
var response = {code: {$codeJson}, message: 'scenario response', data: {}};
var authRedirect = {$authRedirectJson};
var throwSuccess = {$throwSuccessJson};
var throwError = {$throwErrorJson};
var throwAlert = {$throwAlertJson};
var throwComplete = {$throwCompleteJson};
var requestUrl = {$urlJson};
var customHeaders = {$customHeadersJson};
var successCount = 0;
var errorCount = 0;
var errorCodes = [];
var alertCount = 0;
var completeCount = 0;
var ajaxOptions = [];
var maskNode = null;
var pendingRequest = null;
var scheduledCallbacks = [];
var synchronousErrors = [];
var surfacedErrors = [];
var storage = {front_token: 'front-token', front_jwt_token: 'front-jwt-token'};
var localStorage = {
    getItem: function (key) { return Object.prototype.hasOwnProperty.call(storage, key) ? storage[key] : null; },
    setItem: function (key, value) { storage[key] = value; },
    removeItem: function (key) { delete storage[key]; }
};
var window = {
    location: {href: '/current', origin: 'http://localhost'},
    CrmLang: null,
    CrmAjaxMaskConfig: {},
    jQuery: null,
    setTimeout: function (callback) { scheduledCallbacks.push(callback); return scheduledCallbacks.length; }
};
function createMaskElement() {
    var element = {
        className: '',
        innerHTML: '',
        classList: {
            contains: function (name) { return element.className.split(/\s+/).indexOf(name) !== -1; },
            add: function (name) {
                if (!this.contains(name)) { element.className = (element.className + ' ' + name).trim(); }
            },
            remove: function (name) {
                element.className = element.className.split(/\s+/).filter(function (item) { return item && item !== name; }).join(' ');
            }
        },
        querySelector: function () {
            return {textContent: '', src: '', setAttribute: function () {}};
        }
    };
    return element;
}
function createAnchorElement() {
    var anchor = {origin: window.location.origin};
    var href = '';
    Object.defineProperty(anchor, 'href', {
        get: function () { return href; },
        set: function (value) {
            href = String(value);
            anchor.origin = new URL(href, window.location.origin).origin;
        }
    });
    return anchor;
}
var document = {
    createElement: function (tag) {
        if (tag === 'a') { return createAnchorElement(); }
        return createMaskElement();
    },
    body: {appendChild: function (node) { maskNode = node; }}
};
function confirm() {}
function alert() {
    alertCount++;
    if (throwAlert) { throw new Error('SSO alert failed'); }
}
function $() { return {}; }
$.extend = function () {
    var result = {};
    Array.prototype.slice.call(arguments, 1).forEach(function (item) {
        Object.keys(item || {}).forEach(function (key) { result[key] = item[key]; });
    });
    return result;
};
$.ajax = function (options) {
    var doneCallbacks = [];
    var failCallbacks = [];
    ajaxOptions.push(options);
    var chain = {
        done: function (callback) { doneCallbacks.push(callback); return chain; },
        fail: function (callback) { failCallbacks.push(callback); return chain; },
        always: function (callback) {
            doneCallbacks.push(callback);
            failCallbacks.push(callback);
            return chain;
        }
    };
    pendingRequest = {
        settle: function () {
            var callbacks = transport === 'done' ? doneCallbacks : failCallbacks;
            var args = transport === 'done' ? [response] : [{responseJSON: response}];
            callbacks.forEach(function (callback) { callback.apply(null, args); });
        }
    };
    return chain;
};
{$source}
var options = {
    guard: 'front',
    url: requestUrl,
    headers: customHeaders,
    authRedirect: authRedirect,
    success: function () {
        successCount++;
        if (throwSuccess) { throw new Error('business success callback failed'); }
    },
    error: function (res) {
        errorCount++;
        errorCodes.push(res && res.code);
        if (throwError) { throw new Error('business error callback failed'); }
    },
    complete: function () {
        completeCount++;
        if (throwComplete) { throw new Error('complete callback failed'); }
    }
};
if (method === 'upload') {
    options.formData = new FormData();
    CrmAjax.upload(options);
} else {
    CrmAjax.request(options);
}
if (pendingRequest) {
    try {
        pendingRequest.settle();
    } catch (error) {
        synchronousErrors.push(error.message);
    }
}
scheduledCallbacks.forEach(function (callback) {
    try { callback(); } catch (error) { surfacedErrors.push(error.message); }
});
console.log(JSON.stringify({
    frontToken: localStorage.getItem('front_token'),
    frontJwtToken: localStorage.getItem('front_jwt_token'),
    location: window.location.href,
    successCount: successCount,
    errorCount: errorCount,
    errorCodes: errorCodes,
    alertCount: alertCount,
    completeCount: completeCount,
    ajaxCount: ajaxOptions.length,
    ajaxOptions: ajaxOptions,
    maskVisible: !!(maskNode && maskNode.classList.contains('is-visible')),
    synchronousErrors: synchronousErrors,
    surfacedErrors: surfacedErrors
}));
JS
        );
    }

}
