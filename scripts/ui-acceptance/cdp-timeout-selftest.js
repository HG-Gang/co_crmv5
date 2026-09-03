/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 21:45
 */

/**
 * CDP 命令超时的红绿自检。
 *
 * 文件功能：
 * - 用「永不回包」的假 socket 复现挂死场景，断言 send() 必须超时 reject；
 * - 同时断言正常回包、命令报错、超时后迟到回包三条既有路径没有被这次修复打坏。
 *
 * 为什么必须有这个自检：
 * - 2026-09-01 全量矩阵在第 205 个组合永久挂死，根因是 send() 的 Promise
 *   在没有回包时永不 settle。修复加了超时，但未经验证的超时逻辑等于没有：
 *   若超时分支写错（例如误用 resolve 而非 reject），挂死会变成「静默返回空结果」，
 *   调用方拿着未生效的设备尺寸跑完整套检测，产出的零缺陷是假绿，比挂死更危险。
 * - 不用真浏览器：假 socket 能确定性复现「不回包」，真浏览器无法稳定复现丢包。
 *
 * 失败语义：
 * - 任一断言不成立即以非 0 退出，并打印实际收到的信息。
 *
 * 用法：
 *   node scripts/ui-acceptance/cdp-timeout-selftest.js
 */

'use strict';

const { Cdp } = require('./cdp');

/**
 * 构造只吞报文、永不回包的假 socket。
 *
 * @returns {object} 具备 send/addEventListener/close 的最小 socket 替身。
 */
function fakeSocket() {
    return {
        sent: [],
        send(payload) {
            this.sent.push(payload);
        },
        addEventListener() {},
        close() {}
    };
}

/**
 * 记录一条断言结果。
 *
 * @param {Array<object>} results 结果累加器。
 * @param {boolean} ok 是否通过。
 * @param {string} message 说明。
 * @returns {void}
 */
function check(results, ok, message) {
    results.push({ ok, message });
}

/**
 * 入口。
 *
 * @returns {Promise<void>}
 */
async function main() {
    const results = [];

    // 红：无响应必须 reject，错误信息要带方法名与超时值，否则挂在哪一步无从定位。
    const stalled = new Cdp(fakeSocket());
    const started = Date.now();
    try {
        await stalled.send('Emulation.setDeviceMetricsOverride', { width: 1440 }, 'sess-1', 300);
        check(results, false, '无响应命令必须 reject，实际却 resolve 了');
    } catch (error) {
        check(
            results,
            /300ms/.test(error.message) && /setDeviceMetricsOverride/.test(error.message),
            '无响应命令 reject 且含方法名与超时值：' + error.message
        );
        const elapsed = Date.now() - started;
        check(
            results,
            elapsed >= 250 && elapsed < 3000,
            '在设定超时附近触发，实测 ' + elapsed + 'ms'
        );
    }

    // 绿：正常回包必须 resolve —— 证明加超时没有把可用路径打坏。
    const normal = new Cdp(fakeSocket());
    const pending = normal.send('Runtime.evaluate', {}, 'sess-1', 1000);
    normal.dispatch({ id: 1, result: { value: 42 } });
    const value = await pending;
    check(results, value && value.value === 42, '正常回包 resolve，实际 ' + JSON.stringify(value));

    // 绿：命令报错仍走原 reject 路径，没被超时改写。
    const failing = new Cdp(fakeSocket());
    const rejected = failing.send('Bad.method', {}, null, 1000);
    failing.dispatch({ id: 1, error: { code: -32000, message: 'nope' } });
    try {
        await rejected;
        check(results, false, '命令报错必须 reject');
    } catch (error) {
        check(results, /nope/.test(error.message), '命令报错按原路径 reject：' + error.message);
    }

    // 绿：超时后迟到的回包不得抛错。pending 已删，dispatch 会把它当事件处理，
    // 若 listeners 查找没有守卫就会崩，让一次迟到回包搞挂整轮验收。
    const late = new Cdp(fakeSocket());
    try {
        await late.send('Late.cmd', {}, null, 100);
    } catch (error) {
        // 预期超时，此处不作断言。
    }
    try {
        late.dispatch({ id: 1, result: { value: 'late' } });
        check(results, true, '超时后迟到回包被安全忽略');
    } catch (error) {
        check(results, false, '迟到回包导致异常：' + error.message);
    }

    const failed = results.filter((result) => !result.ok);
    results.forEach((result) => {
        process.stdout.write((result.ok ? '  PASS  ' : '  FAIL  ') + result.message + '\n');
    });
    process.stdout.write(
        '\nCDP 超时自检：' + (results.length - failed.length) + '/' + results.length + ' 通过\n'
    );

    if (failed.length) {
        process.exit(1);
    }
}

main().catch((error) => {
    process.stderr.write('CDP 超时自检失败：' + (error && error.stack ? error.stack : error) + '\n');
    process.exit(1);
});
