/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 03:25
 */

/**
 * 命令行参数解析（验收脚本共用）。
 *
 * 文件功能：
 * - 把 --key value 与 --key=value 两种写法统一解析成键值字典。
 *
 * 为什么单独成文件：
 * - audit.js 与 probe.js 都要解析同一套参数。留两份实现会让
 *   「--base 默认值」这类约定出现两个真相源，改一处漏一处。
 */

'use strict';

/**
 * 解析命令行参数为键值对。--key value 与 --key=value 两种写法都支持。
 *
 * 传入 allowedKeys 时，出现未声明的参数名立即抛错。原因是拼错或记错参数名
 * 会被静默忽略、回落到默认值：曾把 --viewport 传给只认 --width/--height 的
 * probe.js，脚本照默认 1440x900 跑完并打印出一份「看起来正常」的报告，
 * 导致按 390 视口解读了 1440 的数据。让这类错误当场失败，比事后对不上账便宜。
 *
 * @param {Array<string>} argv process.argv.slice(2)。
 * @param {Array<string>} [allowedKeys] 允许的参数名；省略则不校验。
 * @returns {object} 参数字典。
 */
function parseArgs(argv, allowedKeys) {
    const args = {};
    const allowed = allowedKeys ? new Set(allowedKeys) : null;
    for (let i = 0; i < argv.length; i += 1) {
        const token = argv[i];
        if (!token.startsWith('--')) {
            continue;
        }
        let key;
        const eq = token.indexOf('=');
        if (eq > -1) {
            key = token.slice(2, eq);
            args[key] = token.slice(eq + 1);
        } else {
            key = token.slice(2);
            const next = argv[i + 1];
            if (next && !next.startsWith('--')) {
                args[key] = next;
                i += 1;
            } else {
                args[key] = 'true';
            }
        }
        if (allowed && !allowed.has(key)) {
            throw new Error('未知参数 --' + key + '，可用参数：'
                + allowedKeys.slice().sort().map((k) => '--' + k).join(' '));
        }
    }
    return args;
}

module.exports = { parseArgs };
