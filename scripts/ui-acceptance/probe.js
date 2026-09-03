/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 03:20
 */

/**
 * 单点取证工具：在真实登录态的指定页面上执行一段表达式并打印结果。
 *
 * 文件功能：
 * - 复用 session.js 启动浏览器、注入令牌、导航到目标页。
 * - 执行 --expr 或 --exprFile 给出的页内表达式，把返回值按 JSON 打印。
 *
 * 为什么需要它：
 * - 全量矩阵只回答「哪里有缺陷」，定性还需要看具体元素的标记、层叠与计算样式。
 * - 用 PowerShell 内联 JS 传表达式会被外层 shell 的引号规则改写，
 *   曾导致取证脚本直接语法失败；从文件读表达式可以彻底避开这层转义。
 *
 * 边界：
 * - 只连本地隔离服务，不触碰正式库。
 * - 表达式里的异常会显式抛出，不静默返回 undefined。
 *
 * 用法：
 *   node scripts/ui-acceptance/probe.js --uri /admin/withdrawals --exprFile tmp/expr.js
 *   node scripts/ui-acceptance/probe.js --uri /admin/withdrawals --expr "document.title"
 *   node scripts/ui-acceptance/probe.js --uri /admin/channels --viewport 390x844 --theme dark
 *
 * 参数：--base --uri --settle --theme --viewport(WxH) --width --height
 *      --expr --exprFile --chrome --port；传其它参数会直接报错而非静默忽略。
 */

'use strict';

const fs = require('fs');
const { openSession, authenticate, evaluate } = require('./session');
const { parseArgs } = require('./argv');

/**
 * 入口。
 *
 * @returns {Promise<void>}
 */
async function main() {
    const args = parseArgs(process.argv.slice(2), [
        'base', 'uri', 'settle', 'theme', 'viewport', 'width', 'height',
        'expr', 'exprFile', 'chrome', 'port'
    ]);
    const base = args.base || 'http://127.0.0.1:8099';
    const uri = args.uri || '/admin/dashboard';
    const settleMs = parseInt(args.settle || '2500', 10);
    const theme = args.theme || '';

    // --viewport WxH 与 audit.js 的 --viewports 写法对齐；--width/--height 保留兼容。
    let width = parseInt(args.width || '1440', 10);
    let height = parseInt(args.height || '900', 10);
    if (args.viewport) {
        const wh = /^(\d+)x(\d+)$/.exec(args.viewport.trim());
        if (!wh) {
            throw new Error('--viewport 需形如 390x844，实际收到 ' + args.viewport);
        }
        width = parseInt(wh[1], 10);
        height = parseInt(wh[2], 10);
    }

    const expression = args.exprFile
        ? fs.readFileSync(args.exprFile, 'utf8')
        : args.expr;
    if (!expression) {
        throw new Error('必须提供 --expr 或 --exprFile');
    }

    const session = await openSession({
        chromePath: args.chrome,
        port: parseInt(args.port || '9412', 10)
    });

    try {
        const { cdp, sessionId } = session;
        await authenticate(cdp, sessionId, base);

        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width, height, deviceScaleFactor: 1, mobile: false
        }, sessionId);

        await cdp.send('Page.navigate', { url: base + uri }, sessionId);
        const loaded = await cdp.once('Page.loadEventFired', 20000, sessionId);
        if (!loaded) {
            throw new Error('页面 20 秒内未触发 load：' + uri);
        }

        await new Promise((resolve) => setTimeout(resolve, settleMs));

        if (theme) {
            const applied = await evaluate(cdp, sessionId, `(function () {
                if (!window.CrmTheme || typeof window.CrmTheme.set !== 'function') {
                    return 'no-CrmTheme';
                }
                window.CrmTheme.set(${JSON.stringify(theme)});
                return document.documentElement.getAttribute('data-crm-theme');
            })()`);
            if (applied !== theme) {
                throw new Error('主题未生效，期望 ' + theme + ' 实际 ' + applied);
            }
        }

        const value = await evaluate(cdp, sessionId, expression);
        process.stdout.write(JSON.stringify(value, null, 2) + '\n');
    } finally {
        session.close();
    }
}

main().catch((error) => {
    process.stderr.write('取证失败：' + (error && error.stack ? error.stack : error) + '\n');
    process.exit(1);
});
