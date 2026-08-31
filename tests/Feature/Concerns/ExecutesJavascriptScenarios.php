<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:48
 */

/**
 * ExecutesJavascriptScenarios
 *
 * 文件功能：
 * - 为 Feature 测试提供执行 Node JS 场景脚本的公共 trait：受控超时、临时脚本文件生命周期、Windows 进程树管控与命令拦截钩子，返回脚本 JSON 结果供断言。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature\Concerns;

trait ExecutesJavascriptScenarios
{
    /**
     * @param null|callable(string, string): mixed $temporaryFileFactory
     * @param null|callable(string): bool $temporaryFileDeleter
     * @param null|callable(array<int, string>, float): ?array<string, mixed> $windowsCommandInterceptor
     * @param int|null $phpIntegerSize
     * @return array<string, mixed>
     */
    private function executeJavascriptJson(
        string $script,
        // The full Laravel suite can temporarily consume most of the worker's
        // memory. Keep this bounded, while allowing Node to start and flush
        // its guarded process-exit marker under high Windows memory pressure.
        float $timeoutSeconds = 40.0,
        callable $temporaryFileFactory = null,
        callable $temporaryFileDeleter = null,
        callable $windowsCommandInterceptor = null,
        int $phpIntegerSize = null
    ): array {
        if (PHP_OS_FAMILY === 'Windows' && ($phpIntegerSize ?? PHP_INT_SIZE) < 8) {
            throw new \RuntimeException('The Windows JavaScript runner requires 64-bit PHP.');
        }

        $this->assertGreaterThan(0.0, $timeoutSeconds, 'JavaScript timeout must be greater than zero.');
        if (PHP_OS_FAMILY === 'Windows') {
            $this->prepareWindowsProcessTreeControlForTest();
        }
        $scriptPath = null;
        $preloadPath = null;
        $stdoutPath = null;
        $stderrPath = null;

        try {
            $scriptPath = $this->createJavascriptTemporaryFile('crm-js-script-', $temporaryFileFactory);
            $this->assertIsString($scriptPath, 'JavaScript source file could not be created.');
            $preloadPath = $this->createJavascriptTemporaryFile('crm-js-preload-', $temporaryFileFactory);
            $this->assertIsString($preloadPath, 'JavaScript preload file could not be created.');
            $stdoutPath = $this->createJavascriptTemporaryFile('crm-js-stdout-', $temporaryFileFactory);
            $this->assertIsString($stdoutPath, 'JavaScript stdout file could not be created.');
            $stderrPath = $this->createJavascriptTemporaryFile('crm-js-stderr-', $temporaryFileFactory);
            $this->assertIsString($stderrPath, 'JavaScript stderr file could not be created.');
            $this->assertSame(
                strlen($script),
                file_put_contents($scriptPath, $script),
                'JavaScript source file could not be written completely.'
            );
            $preloadFileContents = $this->javascriptPreloadFileContents();
            $this->assertSame(
                strlen($preloadFileContents),
                file_put_contents($preloadPath, $preloadFileContents),
                'JavaScript preload file could not be written completely.'
            );

            $processPipes = [];
            $commandConfiguration = $this->javascriptNodeCommand($scriptPath, $preloadPath);
            $process = proc_open(
                $commandConfiguration['command'],
                [
                    1 => ['file', $stdoutPath, 'w'],
                    2 => ['file', $stderrPath, 'w'],
                ],
                $processPipes,
                null,
                $commandConfiguration['environment'],
                ['bypass_shell' => true]
            );
            $this->assertIsResource($process);
            $processId = 0;
            $exitCode = null;
            $closeExitCode = null;
            $timedOut = false;
            $terminationFailure = null;
            $processStopped = false;
            $deadline = microtime(true) + $timeoutSeconds;

            try {
                $initialStatus = proc_get_status($process);
                $this->assertIsArray($initialStatus, 'The Node process status could not be read.');
                $processId = (int) $initialStatus['pid'];
                $this->assertGreaterThan(0, $processId, 'The Node process ID must be positive.');

                while (true) {
                    $status = proc_get_status($process);

                    if (!$status['running']) {
                        if ($status['exitcode'] >= 0) {
                            $exitCode = $status['exitcode'];
                        }
                        break;
                    }

                    if (microtime(true) >= $deadline) {
                        $timedOut = true;
                        break;
                    }

                    usleep(10000);
                }
            } finally {
                $status = proc_get_status($process);
                $shouldTerminate = $status['running'];
                if (!$shouldTerminate && $processId > 0) {
                    if (PHP_OS_FAMILY === 'Windows') {
                        $shouldTerminate = true;
                    } elseif ($commandConfiguration['usesProcessGroup']) {
                        $shouldTerminate = true;
                    }
                }

                if ($shouldTerminate) {
                    // Native Windows watchers write the exit identity from a
                    // separate process.  A three-second bounded window avoids
                    // treating a late marker as a reused/ambiguous PID while
                    // still guaranteeing cleanup before the next scenario.
                    $cleanupDeadline = microtime(true) + 3.0;
                    if ($processId > 0) {
                        $terminationFailure = $this->terminateJavascriptProcess(
                            $process,
                            $processId,
                            $commandConfiguration['usesProcessGroup'],
                            $preloadPath,
                            max(microtime(true), $cleanupDeadline - 0.15),
                            $exitCode,
                            $windowsCommandInterceptor
                        );
                    } else {
                        $terminationRequested = proc_terminate($process, 9);
                        if (!$this->waitForJavascriptProcessExit($process, $cleanupDeadline, $exitCode)) {
                            $terminationFailure = 'The Node process PID was unavailable and forceful termination '
                                . ($terminationRequested ? 'did not stop it.' : 'could not be requested.');
                        }
                    }

                    $status = proc_get_status($process);
                    if ($status['running']) {
                        $terminationRequested = proc_terminate($process, 9);
                        if (!$this->waitForJavascriptProcessExit($process, $cleanupDeadline, $exitCode)) {
                            $terminationFailure = ($terminationFailure === null ? '' : $terminationFailure . ' ')
                                . 'The Node process remained alive after the final direct force-stop '
                                . ($terminationRequested ? 'request.' : 'attempt.');
                        }
                    }
                }

                $status = proc_get_status($process);
                $processStopped = !$status['running'];
                if ($processStopped) {
                    if ($exitCode === null && $status['exitcode'] >= 0) {
                        $exitCode = $status['exitcode'];
                    }
                    $closeExitCode = proc_close($process);
                } else {
                    $terminationFailure = $terminationFailure
                        ?: 'The Node process was still running at the cleanup deadline.';
                }
            }

            if (!$processStopped) {
                $this->fail($terminationFailure ?: 'The Node process lifecycle could not be closed.');
            }

            $stdout = $this->readJavascriptOutputFile($stdoutPath, 'stdout');
            $stderr = $this->readJavascriptOutputFile($stderrPath, 'stderr');

            if ($terminationFailure !== null) {
                $this->fail($terminationFailure);
            }

            if ($timedOut) {
                $this->fail(sprintf('JavaScript execution timed out after %.3f seconds.', $timeoutSeconds));
            }

            if ($exitCode === null && $closeExitCode >= 0) {
                $exitCode = $closeExitCode;
            }
            $this->assertSame(0, $exitCode, $stderr ?: 'JavaScript execution failed.');
            $decoded = json_decode(trim($stdout), true);
            $this->assertIsArray($decoded, 'JavaScript must print one JSON object.');

            return $decoded;
        } finally {
            $cleanupFailures = [];
            foreach ([$scriptPath, $preloadPath, $stdoutPath, $stderrPath] as $path) {
                if (!is_string($path)) {
                    continue;
                }

                try {
                    $deleted = $temporaryFileDeleter === null
                        ? unlink($path)
                        : $temporaryFileDeleter($path);
                    if (!$deleted) {
                        $cleanupFailures[] = 'JavaScript temporary file could not be deleted: ' . $path;
                    }
                } catch (\Throwable $error) {
                    $cleanupFailures[] = 'JavaScript temporary file could not be deleted: '
                        . $path
                        . ' ('
                        . $error->getMessage()
                        . ')';
                }
            }

            if ($cleanupFailures !== []) {
                $this->fail("JavaScript temporary file cleanup failed:\n- " . implode("\n- ", $cleanupFailures));
            }
        }
    }

    /** @param null|callable(string, string): mixed $temporaryFileFactory
     *  @return mixed
     */
    private function createJavascriptTemporaryFile(string $prefix, callable $temporaryFileFactory = null)
    {
        if ($temporaryFileFactory !== null) {
            return $temporaryFileFactory(sys_get_temp_dir(), $prefix);
        }

        return tempnam(sys_get_temp_dir(), $prefix);
    }

    private function prepareWindowsProcessTreeControlForTest(): string
    {
        static $helperPath = null;
        if (is_string($helperPath) && is_file($helperPath)) {
            return $helperPath;
        }

        $sourcePath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'Support'
            . DIRECTORY_SEPARATOR . 'WindowsProcessTreeControl.cs';
        $sourceHash = is_file($sourcePath) ? hash_file('sha256', $sourcePath) : false;
        $this->assertIsString($sourceHash, 'Windows process-tree helper source could not be hashed.');
        $helperPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'crm-process-tree-'
            . substr($sourceHash, 0, 16)
            . '-'
            . getmypid()
            . '.exe';
        if (!is_file($helperPath)) {
            $compilerCandidates = [
                'C:\\Windows\\Microsoft.NET\\Framework64\\v4.0.30319\\csc.exe',
                'C:\\Windows\\Microsoft.NET\\Framework\\v4.0.30319\\csc.exe',
            ];
            $compilerPath = null;
            foreach ($compilerCandidates as $candidate) {
                if (is_file($candidate)) {
                    $compilerPath = $candidate;
                    break;
                }
            }
            $this->assertIsString($compilerPath, 'Windows C# compiler is unavailable.');
            $compileResult = $this->runBoundedWindowsCommand(
                [
                    $compilerPath,
                    '/nologo',
                    '/optimize+',
                    '/target:exe',
                    '/out:' . $helperPath,
                    $sourcePath,
                ],
                microtime(true) + 3.0
            );
            $this->assertTrue($compileResult['completed'], $compileResult['stderr']);
            $this->assertSame(0, $compileResult['exitCode'], $compileResult['stderr']);
            $this->assertFileExists($helperPath);
            register_shutdown_function(static function () use ($helperPath): void {
                if (is_file($helperPath)) {
                    @unlink($helperPath);
                }
            });
        }

        return $helperPath;
    }

    private function readJavascriptOutputFile(string $path, string $label): string
    {
        $stream = fopen($path, 'rb');
        $this->assertIsResource($stream, 'JavaScript ' . $label . ' file could not be opened.');

        try {
            $contents = stream_get_contents($stream);
            $this->assertIsString($contents, 'JavaScript ' . $label . ' file could not be read.');

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{
     *     command: array<int, string>,
     *     usesProcessGroup: bool,
     *     environment: null|array<string, string>
     * }
     */
    private function javascriptNodeCommand(string $scriptPath, string $preloadPath): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $modulePath = str_replace('\\', '/', $preloadPath);
            $environment = getenv();
            $this->assertIsArray($environment, 'The Node process environment could not be read.');
            $existingNodeOptions = isset($environment['NODE_OPTIONS'])
                ? trim((string) $environment['NODE_OPTIONS'])
                : '';
            $requireOption = '--require="' . str_replace('"', '\\"', $modulePath) . '"';
            $environment['NODE_OPTIONS'] = trim($existingNodeOptions . ' ' . $requireOption);
            $environment['CRM_NODE_PROCESS_TREE_HELPER'] = $this->prepareWindowsProcessTreeControlForTest();

            return [
                'command' => ['node', $scriptPath],
                'usesProcessGroup' => false,
                'environment' => $environment,
            ];
        }

        foreach (['/usr/bin/setsid', '/bin/setsid'] as $setsidPath) {
            if (is_executable($setsidPath)) {
                return [
                    'command' => [$setsidPath, 'node', $scriptPath],
                    'usesProcessGroup' => true,
                    'environment' => null,
                ];
            }
        }

        return [
            'command' => ['node', $scriptPath],
            'usesProcessGroup' => false,
            'environment' => null,
        ];
    }

    private function javascriptPreloadFileContents(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return '';
        }

        return <<<'JS'
'use strict';
var childProcess = require('child_process');
var fs = require('fs');
var path = require('path');
var inheritedNodeOptions = process.env.NODE_OPTIONS || '';
var processTreeHelperPath = process.env.CRM_NODE_PROCESS_TREE_HELPER || '';
var originalSpawn = childProcess.spawn;
var registeredProcessIdentities = Object.create(null);
var registeredExitIdentities = Object.create(null);

function registerGuardedProcessIdentity(processId, parentProcessId, creationToken) {
    if (!(processId > 0)) {
        return false;
    }
    var normalizedParentProcessId = Math.floor(parentProcessId);
    var existingIdentity = registeredProcessIdentities[processId];
    if (existingIdentity) {
        if (existingIdentity.parentProcessId !== normalizedParentProcessId) {
            throw new Error('Conflicting guarded process parent for PID ' + processId + '.');
        }

        return false;
    }
    fs.appendFileSync(
        __filename,
        String.fromCharCode(10) + '// CRM_NODE_PROCESS ' + processId + ' ' + normalizedParentProcessId
            + ' ' + creationToken
            + String.fromCharCode(10)
    );
    registeredProcessIdentities[processId] = {
        parentProcessId: normalizedParentProcessId,
        creationToken: creationToken
    };

    return true;
}

function registerGuardedExitIdentity(processId, exitToken) {
    if (!(processId > 0) || registeredExitIdentities[processId]) {
        return;
    }
    try {
        fs.appendFileSync(
            __filename,
            String.fromCharCode(10) + '// CRM_NODE_EXIT ' + processId + ' '
                + exitToken
                + String.fromCharCode(10)
        );
        registeredExitIdentities[processId] = exitToken;
    } catch (error) {
        // A missing exit marker makes cleanup fail closed in the parent.
    }
}

if (process.env.CRM_NODE_EXIT_MARKER_OWNER === 'parent') {
    // The spawning process owns this process's registry identity and exit watcher.
} else {
    startNativeExitWatcher(process.pid, process.ppid);
}

function isChildProcessOptions(value) {
    return !!value && typeof value === 'object' && !Array.isArray(value);
}

function constrainChildProcessOptions(options) {
    var constrained = Object.assign({}, options || {});
    var hasExplicitEnvironment = options
        && Object.prototype.hasOwnProperty.call(options, 'env')
        && options.env !== null
        && options.env !== undefined;
    var environment = hasExplicitEnvironment ? options.env : process.env;
    constrained.detached = false;
    constrained.env = Object.assign({}, environment);
    constrained.env.NODE_OPTIONS = inheritedNodeOptions;
    constrained.env.CRM_NODE_PROCESS_TREE_HELPER = processTreeHelperPath;
    constrained.env.CRM_NODE_EXIT_MARKER_OWNER = 'parent';

    return constrained;
}

function startNativeExitWatcher(processId, parentProcessId) {
    if (!(processId > 0)) {
        return false;
    }
    if (!processTreeHelperPath) {
        return false;
    }
    if (!path.isAbsolute(processTreeHelperPath)
        || !fs.statSync(processTreeHelperPath).isFile()) {
        throw new Error('CRM_NODE_PROCESS_TREE_HELPER must reference an absolute file path.');
    }
    var watcherEnvironment = Object.assign({}, process.env);
    watcherEnvironment.NODE_OPTIONS = '';
    watcherEnvironment.CRM_NODE_PROCESS_TREE_HELPER = '';
    var watcher = originalSpawn(
        processTreeHelperPath,
        ['--watch-exit', String(processId), String(parentProcessId), __filename],
        {
            detached: true,
            windowsHide: true,
            stdio: 'ignore',
            env: watcherEnvironment
        }
    );
    if (!watcher || !(watcher.pid > 0)) {
        throw new Error('The native process exit watcher could not be started.');
    }
    watcher.once('error', function error(error) {
        throw error;
    });
    if (watcher && typeof watcher.unref === 'function') {
        watcher.unref();
    }

    return true;
}

function optionalArgumentsOptionsIndex(args) {
    if (Array.isArray(args[1])) {
        return 2;
    }
    if (args[1] === undefined && isChildProcessOptions(args[2])) {
        return 2;
    }

    return 1;
}

function constrainChildProcessMethod(method, optionsIndex) {
    var original = childProcess[method];
    childProcess[method] = function () {
        var args = Array.prototype.slice.call(arguments);
        var index = typeof optionsIndex === 'function' ? optionsIndex(args) : optionsIndex;
        var candidate = args[index];
        var constrained = constrainChildProcessOptions(
            isChildProcessOptions(candidate) ? candidate : null
        );
        if (typeof candidate === 'function') {
            args.splice(index, 0, constrained);
        } else {
            args[index] = constrained;
        }

        var spawnedProcess = original.apply(this, args);
        if (spawnedProcess && spawnedProcess.pid > 0) {
            var guardedProcessId = spawnedProcess.pid;
            startNativeExitWatcher(guardedProcessId, process.pid);
        }

        return spawnedProcess;
    };
}

constrainChildProcessMethod('exec', 1);
constrainChildProcessMethod('execFile', optionalArgumentsOptionsIndex);
constrainChildProcessMethod('fork', optionalArgumentsOptionsIndex);
constrainChildProcessMethod('spawn', optionalArgumentsOptionsIndex);
JS;
    }

    /** @param resource $process */
    private function terminateJavascriptProcess(
        $process,
        int $processId,
        bool $usesProcessGroup,
        string $processRegistryPath,
        float $hardDeadline,
        ?int &$exitCode,
        callable $windowsCommandInterceptor = null
    ): ?string {
        $attempts = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $registryContents = file_get_contents($processRegistryPath);
            $registeredAtByPid = [];
            $exitedAtByPid = [];
            $registryValidationDeadline = $hardDeadline;
            do {
                try {
                    $processTree = $this->javascriptProcessTreeFromRegistry(
                        is_string($registryContents) ? $registryContents : '',
                        $processId,
                        $registeredAtByPid,
                        $exitedAtByPid
                    );
                    $registryError = null;
                    break;
                } catch (\Throwable $error) {
                    $registryError = $error;
                }
                if (microtime(true) >= $registryValidationDeadline) {
                    break;
                }
                usleep(10000);
                $registryContents = file_get_contents($processRegistryPath);
            } while (microtime(true) < $registryValidationDeadline);
            if ($registryError !== null) {
                return 'The guarded Windows process registry validation failed: '
                    . $registryError->getMessage();
            }
            $parentStatus = proc_get_status($process);
            if (!$parentStatus['running']) {
                $registryRefreshDeadline = $hardDeadline;
                do {
                    $missingExitMarkers = array_diff(
                        $processTree,
                        array_map('intval', array_keys($exitedAtByPid))
                    );
                    if ($missingExitMarkers === [] || microtime(true) >= $registryRefreshDeadline) {
                        break;
                    }
                    usleep(10000);
                    $registryContents = file_get_contents($processRegistryPath);
                    if (!is_string($registryContents)) {
                        return 'The guarded Windows process registry could not be reread.';
                    }
                    try {
                        $processTree = $this->javascriptProcessTreeFromRegistry(
                            $registryContents,
                            $processId,
                            $registeredAtByPid,
                            $exitedAtByPid
                        );
                    } catch (\Throwable $error) {
                        return 'The guarded Windows process registry validation failed: ' . $error->getMessage();
                    }
                } while (microtime(true) < $registryRefreshDeadline);
            }
            // 父进程仍在运行时不会产生退出标记；创建身份已经由上面的注册表解析完成，
            // 此处应立即按身份终止进程树，避免每次超时都无条件消耗一秒并突破硬截止时间。

            $treeFailure = $this->terminateWindowsJavascriptProcessTree(
                $processTree,
                $hardDeadline,
                $windowsCommandInterceptor,
                $registeredAtByPid,
                $exitedAtByPid
            );
            if ($treeFailure !== null
                && str_contains($treeFailure, 'ambiguous:')
                && microtime(true) < $hardDeadline) {
                // An exit watcher can lose the race with a very short-lived
                // Node process. Re-probe every registered creation identity:
                // an empty result proves no original process is still alive,
                // while a live or unqueryable identity keeps the failure.
                $probeCommand = [$this->prepareWindowsProcessTreeControlForTest(), '--probe'];
                foreach ($processTree as $treeProcessId) {
                    if (!isset($registeredAtByPid[$treeProcessId])) {
                        continue;
                    }
                    $probeIdentity = $treeProcessId . ':' . $registeredAtByPid[$treeProcessId];
                    if (isset($exitedAtByPid[$treeProcessId])) {
                        $probeIdentity .= ':' . $exitedAtByPid[$treeProcessId];
                    }
                    $probeCommand[] = $probeIdentity;
                }
                $probeResult = $this->runBoundedWindowsCommand(
                    $probeCommand,
                    $hardDeadline,
                    $windowsCommandInterceptor
                );
                $runningIdentities = $probeResult['completed'] && $probeResult['exitCode'] === 0
                    ? json_decode(trim($probeResult['stdout']), true)
                    : null;
                if ($runningIdentities === []) {
                    $treeFailure = null;
                    $attempts[] = 'identity probe=all exited';
                }
            }
            $attempts[] = 'recursive tree fallback=' . ($treeFailure === null ? 'success' : 'failed');
        } else {
            if ($usesProcessGroup && function_exists('posix_kill')) {
                $gracefulDeadline = min($hardDeadline, microtime(true) + 0.15);
                $treeFailure = $this->terminateJavascriptProcessGroup(
                    $processId,
                    $gracefulDeadline,
                    $hardDeadline,
                    function (int $processGroupId, int $signal): bool {
                        return @posix_kill(-$processGroupId, $signal);
                    },
                    function (int $processGroupId): bool {
                        if (@posix_kill(-$processGroupId, 0)) {
                            return true;
                        }

                        return posix_get_last_error() !== 3;
                    }
                );
                $attempts[] = 'process group TERM/KILL=' . ($treeFailure === null ? 'success' : 'failed');
            } else {
                $treeFailure = 'JavaScript process-group containment is unavailable; descendant cleanup '
                    . 'cannot be verified.';
                $attempts[] = 'process group TERM/KILL=unavailable';
            }

            if ($treeFailure !== null) {
                $forceSent = proc_terminate($process, 9);
                $attempts[] = 'parent SIGKILL=' . ($forceSent ? 'success' : 'failed');
            }
        }

        if ($this->waitForJavascriptProcessExit($process, $hardDeadline, $exitCode)) {
            return isset($treeFailure) ? $treeFailure : null;
        }

        return 'JavaScript execution timed out and the Node process could not be terminated '
            . 'before the hard cleanup deadline ('
            . implode(', ', $attempts)
            . ').';
    }

    private function terminateJavascriptProcessGroup(
        int $processGroupId,
        float $gracefulDeadline,
        float $hardDeadline,
        callable $signalGroup,
        callable $groupIsRunning
    ): ?string {
        $termSent = $signalGroup($processGroupId, 15);
        while ($groupIsRunning($processGroupId) && microtime(true) < $gracefulDeadline) {
            usleep(10000);
        }
        if (!$groupIsRunning($processGroupId)) {
            return null;
        }

        $killSent = $signalGroup($processGroupId, 9);
        while ($groupIsRunning($processGroupId) && microtime(true) < $hardDeadline) {
            usleep(10000);
        }
        if (!$groupIsRunning($processGroupId)) {
            return null;
        }

        return 'The JavaScript process group remained alive after TERM/KILL '
            . '(TERM=' . ($termSent ? 'sent' : 'failed')
            . ', KILL=' . ($killSent ? 'sent' : 'failed') . ').';
    }

    /** @param resource $process */
    private function waitForJavascriptProcessExit($process, float $deadline, ?int &$exitCode): bool
    {
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                if ($exitCode === null && $status['exitcode'] >= 0) {
                    $exitCode = $status['exitcode'];
                }

                return true;
            }

            $remainingMicroseconds = (int) (($deadline - microtime(true)) * 1000000);
            if ($remainingMicroseconds <= 0) {
                return false;
            }
            usleep(min(10000, $remainingMicroseconds));
        } while (true);
    }

    /**
     * @param array<int, int>|null $registeredAtByPid
     * @param array<int, int>|null $exitedAtByPid
     * @return array<int, int>
     */
    private function javascriptProcessTreeFromRegistry(
        string $registryContents,
        int $rootProcessId,
        ?array &$registeredAtByPid = null,
        ?array &$exitedAtByPid = null
    ): array {
        if (PHP_INT_SIZE < 8) {
            throw new \RuntimeException('The guarded Windows process registry requires 64-bit PHP.');
        }

        $records = [];
        $exitRecords = [];
        $lines = preg_split('/\r\n|\n|\r/', $registryContents);
        if (!is_array($lines)) {
            throw new \RuntimeException('The guarded Windows process registry could not be split into lines.');
        }
        foreach ($lines as $line) {
            if (str_starts_with($line, '// CRM_NODE_PROCESS')) {
                if (preg_match(
                    '/\A\/\/ CRM_NODE_PROCESS ([1-9][0-9]*) (0|[1-9][0-9]*) ([1-9][0-9]*)\z/D',
                    $line,
                    $matches
                ) !== 1) {
                    throw new \RuntimeException('The guarded Windows process registry contains an invalid record.');
                }
                foreach ([$matches[1], $matches[2]] as $pidValue) {
                    if (strlen($pidValue) > 10
                        || (strlen($pidValue) === 10 && strcmp($pidValue, '4294967295') > 0)) {
                        throw new \RuntimeException('The guarded Windows process registry PID is outside uint32.');
                    }
                }
                if (strlen($matches[3]) > strlen((string) PHP_INT_MAX)
                    || (strlen($matches[3]) === strlen((string) PHP_INT_MAX)
                        && strcmp($matches[3], (string) PHP_INT_MAX) > 0)) {
                    throw new \RuntimeException('The guarded Windows process registry identity is outside PHP int.');
                }
                $processId = (int) $matches[1];
                $record = [
                    'parentPid' => (int) $matches[2],
                    'registeredAt' => (int) $matches[3],
                ];
                if (isset($records[$processId]) && $records[$processId] !== $record) {
                    throw new \RuntimeException(
                        'The guarded Windows process registry contains conflicting process identities.'
                    );
                }
                $records[$processId] = $record;
                continue;
            }

            if (str_starts_with($line, '// CRM_NODE_EXIT')) {
                if (preg_match(
                    '/\A\/\/ CRM_NODE_EXIT ([1-9][0-9]*) ([1-9][0-9]*)\z/D',
                    $line,
                    $matches
                ) !== 1) {
                    throw new \RuntimeException('The guarded Windows process registry contains an invalid exit record.');
                }
                $processId = (int) $matches[1];
                if (strlen($matches[1]) > 10
                    || (strlen($matches[1]) === 10 && strcmp($matches[1], '4294967295') > 0)) {
                    throw new \RuntimeException('The guarded Windows process registry PID is outside uint32.');
                }
                if (strlen($matches[2]) > strlen((string) PHP_INT_MAX)
                    || (strlen($matches[2]) === strlen((string) PHP_INT_MAX)
                        && strcmp($matches[2], (string) PHP_INT_MAX) > 0)) {
                    throw new \RuntimeException('The guarded Windows process registry identity is outside PHP int.');
                }
                $exitAt = (int) $matches[2];
                if (isset($exitRecords[$processId]) && $exitRecords[$processId] !== $exitAt) {
                    throw new \RuntimeException(
                        'The guarded Windows process registry contains conflicting exit identities.'
                    );
                }
                $exitRecords[$processId] = $exitAt;
            }
        }

        foreach ($exitRecords as $processId => $exitAt) {
            if (!isset($records[$processId]) || $exitAt < $records[$processId]['registeredAt']) {
                throw new \RuntimeException(
                    'The guarded Windows process registry contains an invalid exit identity.'
                );
            }
        }
        if (!isset($records[$rootProcessId])) {
            throw new \RuntimeException(
                'The guarded Windows process registry is missing the root process identity.'
            );
        }

        $childrenByParent = [];
        $registeredAtByPid = [];
        $exitedAtByPid = [];
        foreach ($records as $processId => $record) {
            $childrenByParent[$record['parentPid']][] = $processId;
            $registeredAtByPid[$processId] = $record['registeredAt'];
            if (isset($exitRecords[$processId])) {
                $exitedAtByPid[$processId] = $exitRecords[$processId];
            }
        }

        $tree = [$rootProcessId];
        for ($index = 0; $index < count($tree); $index++) {
            foreach ($childrenByParent[$tree[$index]] ?? [] as $childProcessId) {
                if (!in_array($childProcessId, $tree, true)) {
                    $tree[] = $childProcessId;
                }
            }
        }

        return array_reverse($tree);
    }

    /**
     * @param array<int, int> $processTree
     * @param array<int, int> $registeredAtByPid
     * @param array<int, int> $exitedAtByPid
     */
    private function terminateWindowsJavascriptProcessTree(
        array $processTree,
        float $deadline,
        callable $windowsCommandInterceptor = null,
        array $registeredAtByPid = [],
        array $exitedAtByPid = []
    ): ?string
    {
        $helperArguments = [];
        foreach ($processTree as $treeProcessId) {
            if (!isset($registeredAtByPid[$treeProcessId])) {
                return 'The guarded Windows process registry is missing a seed identity.';
            }
            $helperArgument = $treeProcessId . ':' . $registeredAtByPid[$treeProcessId];
            if (isset($exitedAtByPid[$treeProcessId])) {
                $helperArgument .= ':' . $exitedAtByPid[$treeProcessId];
            }
            $helperArguments[] = $helperArgument;
        }
        $fallbackCommand = array_merge(
            [$this->prepareWindowsProcessTreeControlForTest()],
            $helperArguments
        );
        $result = $this->runBoundedWindowsCommand(
            $fallbackCommand,
            $deadline,
            $windowsCommandInterceptor
        );
        if (!$result['completed'] || $result['exitCode'] !== 0) {
            return sprintf(
                'JavaScript execution timed out and the Node process tree termination failed '
                . '(completed=%s, exit=%s, stderr=%s).',
                $result['completed'] ? 'yes' : 'no',
                $result['exitCode'] === null ? 'unknown' : (string) $result['exitCode'],
                trim($result['stderr']) ?: 'none'
            );
        }

        if (trim($result['stdout']) !== 'OK') {
            return 'JavaScript execution timed out and Windows returned an invalid process-tree termination result.';
        }

        return null;
    }

    /**
     * @param array<int, string> $command
     * @return array{completed: bool, exitCode: ?int, stdout: string, stderr: string}
     */
    private function runBoundedWindowsCommand(
        array $command,
        float $deadline,
        callable $windowsCommandInterceptor = null,
        callable $controlProcessTerminator = null
    ): array
    {
        $expiredDeadlineResult = [
            'completed' => false,
            'exitCode' => null,
            'stdout' => '',
            'stderr' => 'Windows command deadline expired before execution.',
        ];
        if (microtime(true) >= $deadline) {
            return $expiredDeadlineResult;
        }

        if ($windowsCommandInterceptor !== null) {
            $intercepted = $windowsCommandInterceptor($command, $deadline);
            if (is_array($intercepted)) {
                return $intercepted;
            }
            if (microtime(true) >= $deadline) {
                return $expiredDeadlineResult;
            }
        }

        if (microtime(true) >= $deadline) {
            return $expiredDeadlineResult;
        }

        $stdoutPath = tempnam(sys_get_temp_dir(), 'crm-win-out-');
        $stderrPath = tempnam(sys_get_temp_dir(), 'crm-win-err-');
        if (!is_string($stdoutPath) || !is_string($stderrPath)) {
            if (is_string($stdoutPath) && is_file($stdoutPath)) {
                @unlink($stdoutPath);
            }
            if (is_string($stderrPath) && is_file($stderrPath)) {
                @unlink($stderrPath);
            }

            return [
                'completed' => false,
                'exitCode' => null,
                'stdout' => '',
                'stderr' => 'Windows command output files could not be created.',
            ];
        }

        try {
            $pipes = [];
            $controlProcess = proc_open(
                $command,
                [
                    1 => ['file', $stdoutPath, 'w'],
                    2 => ['file', $stderrPath, 'w'],
                ],
                $pipes,
                null,
                null,
                ['bypass_shell' => true]
            );
            if (!is_resource($controlProcess)) {
                return [
                    'completed' => false,
                    'exitCode' => null,
                    'stdout' => '',
                    'stderr' => 'Windows command process could not be opened.',
                ];
            }

            $exitCode = null;
            $timedOut = false;
            $outputLimitExceeded = false;
            $maximumOutputBytes = 4 * 1024 * 1024;
            $commandDeadline = max(microtime(true), $deadline - 0.05);
            while (true) {
                $status = proc_get_status($controlProcess);
                if (!$status['running']) {
                    if ($status['exitcode'] >= 0) {
                        $exitCode = $status['exitcode'];
                    }
                    break;
                }

                clearstatcache(true, $stdoutPath);
                clearstatcache(true, $stderrPath);
                $stdoutBytes = filesize($stdoutPath);
                $stderrBytes = filesize($stderrPath);
                if ((is_int($stdoutBytes) ? $stdoutBytes : 0)
                    + (is_int($stderrBytes) ? $stderrBytes : 0) > $maximumOutputBytes) {
                    $outputLimitExceeded = true;
                    break;
                }
                if (microtime(true) >= $commandDeadline) {
                    $timedOut = true;
                    break;
                }
                $remainingMicroseconds = max(1, (int) (($commandDeadline - microtime(true)) * 1000000));
                usleep(min(5000, $remainingMicroseconds));
            }

            if ($timedOut || $outputLimitExceeded) {
                $terminate = $controlProcessTerminator ?? function ($process): bool {
                    return proc_terminate($process, 9);
                };
                for ($attempt = 0; $attempt < 2; $attempt++) {
                    $terminate($controlProcess);
                    if ($this->waitForJavascriptProcessExit(
                        $controlProcess,
                        min($deadline, microtime(true) + 0.08),
                        $exitCode
                    )) {
                        break;
                    }
                }
            }

            $status = proc_get_status($controlProcess);
            if ($status['running']) {
                return [
                    'completed' => false,
                    'exitCode' => null,
                    'stdout' => '',
                    'stderr' => 'Windows command process remained alive after bounded termination.',
                ];
            }
            if ($exitCode === null && $status['exitcode'] >= 0) {
                $exitCode = $status['exitcode'];
            }
            $closeExitCode = proc_close($controlProcess);
            if ($exitCode === null && $closeExitCode >= 0) {
                $exitCode = $closeExitCode;
            }

            clearstatcache(true, $stdoutPath);
            clearstatcache(true, $stderrPath);
            $stdoutBytes = filesize($stdoutPath);
            $stderrBytes = filesize($stderrPath);
            $stdoutBytes = is_int($stdoutBytes) ? $stdoutBytes : 0;
            $stderrBytes = is_int($stderrBytes) ? $stderrBytes : 0;
            if ($stdoutBytes + $stderrBytes > $maximumOutputBytes) {
                $outputLimitExceeded = true;
            }

            $limitDiagnostic = "Windows command output exceeded the 4194304-byte limit.";
            if ($outputLimitExceeded) {
                $diagnosticSuffix = "\n" . $limitDiagnostic;
                $payloadBudget = max(0, $maximumOutputBytes - strlen($diagnosticSuffix));
                $stdoutLimit = min($stdoutBytes, $payloadBudget);
                $stderrLimit = min($stderrBytes, $payloadBudget - $stdoutLimit);
                $stdout = $stdoutLimit > 0
                    ? file_get_contents($stdoutPath, false, null, 0, $stdoutLimit)
                    : '';
                $stderr = $stderrLimit > 0
                    ? file_get_contents($stderrPath, false, null, 0, $stderrLimit)
                    : '';
                $stdout = is_string($stdout) ? $stdout : '';
                $stderr = (is_string($stderr) ? rtrim($stderr) : '') . $diagnosticSuffix;
            } else {
                $stdout = file_get_contents($stdoutPath);
                $stderr = file_get_contents($stderrPath);
                $stdout = is_string($stdout) ? $stdout : '';
                $stderr = is_string($stderr) ? $stderr : '';
            }

            return [
                'completed' => !$timedOut && !$outputLimitExceeded,
                'exitCode' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
            ];
        } finally {
            if (is_file($stdoutPath)) {
                @unlink($stdoutPath);
            }
            if (is_file($stderrPath)) {
                @unlink($stderrPath);
            }
        }
    }

}
