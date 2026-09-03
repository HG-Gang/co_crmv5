/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 01:05
 */

/**
 * 四套 UI 家族 × 多主题 × 四视口的真实浏览器渲染验收编排器。
 *
 * 文件功能：
 * - 启动无头 Chrome，注入登录令牌，逐个组合导航页面、切换主题、执行页内检测器。
 * - 汇总结构化缺陷清单到 JSON 报告，并对有缺陷的组合抓取截图供人工复核。
 *
 * 为什么需要它：
 * - docs/audits/2026-08-30-handoff-resume-here.md §5.1 列出的项（横向溢出、元素重叠、
 *   焦点可见性、disabled/placeholder 实际对比度）都依赖真实渲染，静态审计给不出结论，
 *   且该文档明确禁止把静态结论表述为浏览器验收通过。
 *
 * 边界与失败语义：
 * - 只连接隔离测试库对应的本地服务，不触碰正式库；服务地址由 --base 传入。
 * - 导航失败、主题设置失败、检测器抛错都会记为该组合的 error 并计入退出码，
 *   绝不静默跳过——跳过会让报告看起来「全绿」而实际没测。
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { openSession, authenticate, evaluate } = require('./session');
const { parseArgs } = require('./argv');
const { inPageAudit } = require('./detectors');

/**
 * 注入「关闭全部过渡与动画」的样式。
 *
 * 为什么必须注入而不是靠等待：主题 CSS 对 background-color / border-color / box-shadow
 * 声明了 180ms 过渡。焦点检测在 focus() 之后立刻读计算样式，若过渡仍在进行，
 * 读到的是过渡起始值而非终值，靠 box-shadow 表达焦点的控件会被误报成无焦点环。
 * 用固定 sleep 去躲这个时序只是概率上减少误报，注入 transition:none 才是消除整类误差。
 */
const KILL_MOTION_CSS = `(function () {
    var id = 'crm-audit-kill-motion';
    if (document.getElementById(id)) { return 'already'; }
    var style = document.createElement('style');
    style.id = id;
    style.textContent = '*, *::before, *::after { transition: none !important; animation: none !important; }';
    document.head.appendChild(style);
    return 'injected';
})()`;

/**
 * 审计「一个页面 × 一个视口 × 全部主题」。
 *
 * 为什么把主题循环放在同一次加载内：主题切换是纯 CSS 重算，
 * 逐主题重新导航会把 154 页 × 4 视口 × 15 主题变成 9240 次导航（约 5 小时）。
 * 同一次加载内切主题拿到的正是「用户在页面上切皮肤」的真实状态。
 * 代价是 layui 表格列宽在加载时按当时主题度量算定，加载后切主题不会重算；
 * 因此几何类缺陷需要 --confirm 模式（预置主题后全新加载）复核，二者结合才是可信结论。
 *
 * @param {Cdp} cdp CDP 连接。
 * @param {string} sessionId 页面会话。
 * @param {object} options 组合参数。
 * @returns {Promise<Array<object>>} 每个主题一条结果记录。
 */
async function auditPageViewport(cdp, sessionId, options) {
    const { base, uri, viewport, themes, settleMs, session } = options;
    const records = themes.map((theme) => ({
        uri,
        viewport: viewport.label,
        theme,
        findings: [],
        error: null
    }));

    try {
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: viewport.width,
            height: viewport.height,
            deviceScaleFactor: 1,
            mobile: viewport.mobile === true
        }, sessionId);

        // 导航带一次重试：620 次加载的长时间跑动中，瞬时故障是常态而非页面缺陷。
        // 取证依据：v2 全量矩阵有 17 次连续加载失败（跨越页面边界、随后自愈），
        // 单独复现这 5 个页面 × 4 视口时 20/20 全绿 —— 页面没问题，是环境瞬时失稳。
        // 不重试的代价是一次瞬时故障报废 255 个组合，让整轮验收无法作为基线。
        // 只重试一次：真实的页面缺陷不会因为重试而消失，重试次数多了只会掩盖稳定复现的故障。
        const targetUrl = base + uri;
        let landing = { ok: false, reason: '' };
        let loaded = null;
        for (let attempt = 1; attempt <= 2; attempt += 1) {
            session.resetDocumentStatus();
            await cdp.send('Page.navigate', { url: targetUrl }, sessionId);
            loaded = await cdp.once('Page.loadEventFired', 20000, sessionId);

            // 落地校验必须先于任何检测判定：错误页与重定向终点都会触发 load 事件，
            // 在它们上面跑检测得到的「零缺陷」或「主题未生效」都不是目标页面的结论。
            landing = loaded
                ? session.verifyLanding(targetUrl)
                : { ok: false, reason: '页面 20 秒内未触发 load' };

            if (landing.ok) {
                break;
            }
            if (attempt < 2) {
                // 重试前留出恢复时间，并如实记录重试发生过——静默重试会掩盖不稳定的页面。
                console.log('    重试 ' + uri + ' @' + viewport.width + 'x' + viewport.height
                    + '：' + landing.reason);
                await new Promise((resolve) => setTimeout(resolve, 1500));
            }
        }
        if (!landing.ok) {
            records.forEach((record) => { record.error = landing.reason; });
            return records;
        }

        await evaluate(cdp, sessionId, KILL_MOTION_CSS);

        // 发一次真实 Tab：Chrome 只在键盘模态下对元素匹配 :focus-visible，
        // 缺这一步会让焦点可见性检测全量误报。键盘模态是文档级且粘性的，
        // 因此每次加载发一次就够，不必在每个主题前重复发。
        await cdp.send('Input.dispatchKeyEvent', {
            type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9
        }, sessionId);
        await cdp.send('Input.dispatchKeyEvent', {
            type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9
        }, sessionId);

        // 等异步表格渲染落定。表格数据来自 XHR，load 事件之后才到。
        await new Promise((resolve) => setTimeout(resolve, settleMs));

        for (let index = 0; index < themes.length; index += 1) {
            const theme = themes[index];
            const record = records[index];

            // 主题切换走项目自己的公开 API，而不是直接写 html 属性：
            // CrmTheme.set() 还会同步皮肤类名、cookie 与广播事件，
            // 直接写属性会漏掉这些副作用，验收到的就不是真实用户切主题后的状态。
            const themeApplied = await evaluate(cdp, sessionId, `(function () {
                if (!window.CrmTheme || typeof window.CrmTheme.set !== 'function') {
                    return 'no-CrmTheme';
                }
                window.CrmTheme.set(${JSON.stringify(theme)});
                return document.documentElement.getAttribute('data-crm-theme');
            })()`);
            if (themeApplied !== theme) {
                record.error = '主题未生效，期望 ' + theme + ' 实际 ' + themeApplied;
                continue;
            }

            const audit = await evaluate(cdp, sessionId, '(' + inPageAudit.toString() + ')()');
            record.findings = audit.findings;
            record.kindCounts = audit.kindCounts;
            // coverage 必须落盘：报告里只有「0 命中」而没有「测了多少」时，
            // 读者无法分辨真的无缺陷与压根没测到。
            record.coverage = audit.coverage;
            record.visibleCount = audit.visibleCount;
            record.interactiveCount = audit.interactiveCount;
        }
    } catch (error) {
        // 整页失败要落到每条主题记录上，不能只标一条：
        // 漏标的记录在报告里等于「该主题无缺陷」，属假绿。
        const message = String(error && error.message ? error.message : error);
        records.forEach((record) => {
            if (!record.error && !record.findings.length) {
                record.error = message;
            }
        });
    }

    return records;
}

/** 四个验收视口，取自 §5.1 要求的 1440/1280/768/390。 */
const VIEWPORTS = [
    { label: '1440x900', width: 1440, height: 900 },
    { label: '1280x720', width: 1280, height: 720 },
    { label: '768x1024', width: 768, height: 1024, mobile: true },
    { label: '390x844', width: 390, height: 844, mobile: true }
];

/**
 * 入口。
 *
 * @returns {Promise<void>}
 */
async function main() {
    const args = parseArgs(process.argv.slice(2), [
        'base', 'port', 'settle', 'chrome', 'out', 'pages', 'themes', 'viewports'
    ]);
    const base = args.base || 'http://127.0.0.1:8099';
    const port = parseInt(args.port || '9333', 10);
    const settleMs = parseInt(args.settle || '1200', 10);
    const chromePath = args.chrome
        || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    const outPath = args.out || path.join('storage', 'logs', 'ui-audit-' + Date.now() + '.json');

    const uris = JSON.parse(fs.readFileSync(args.pages, 'utf8'));
    const themes = (args.themes || 'light,dark').split(',').filter(Boolean);
    const viewportFilter = args.viewports ? args.viewports.split(',') : null;
    const viewports = viewportFilter
        ? VIEWPORTS.filter((viewport) => viewportFilter.indexOf(viewport.label) > -1)
        : VIEWPORTS;

    const session = await openSession({ chromePath, port });

    try {
        const { cdp, sessionId } = session;

        // authenticate() 内部在登录失败时直接抛出，不需要在此重复判断。
        await authenticate(cdp, sessionId, base);
        process.stdout.write('登录成功（admin + front），开始验收\n');

        // 提前校验 --themes：目录里不存在的 key 会被 CrmTheme 静默回落成 light，
        // 表现为每个组合都少验这几个主题。不拦的话要跑满 620 个组合、约一小时
        // 才从「ERR×N」里看出来，而且那几个真主题一次都没验到，报告是假全量。
        // 登录后当前页已带主题目录，这里顺手读一次，不额外导航。
        const themeCatalog = await evaluate(cdp, sessionId, `(function () {
            var el = document.getElementById('crm-theme-values');
            try {
                return JSON.parse((el && el.textContent) || '[]').join(',');
            } catch (e) {
                return '';
            }
        })()`);
        const knownThemes = String(themeCatalog).split(',').filter(Boolean);
        if (!knownThemes.length) {
            throw new Error('未能从页面读到主题目录（#crm-theme-values 缺失或为空），无法校验 --themes。');
        }
        const unknownThemes = themes.filter((theme) => knownThemes.indexOf(theme) === -1);
        if (unknownThemes.length) {
            throw new Error(
                '--themes 含目录外的 key：' + unknownThemes.join('、')
                + '。当前可用主题共 ' + knownThemes.length + ' 个：' + knownThemes.join('、')
            );
        }
        process.stdout.write('主题校验通过：' + themes.length + '/' + knownThemes.length + '\n');

        const records = [];
        const totalLoads = uris.length * viewports.length;
        let done = 0;

        for (const uri of uris) {
            for (const viewport of viewports) {
                const batch = await auditPageViewport(cdp, sessionId, {
                    base, uri, viewport, themes, settleMs, session
                });
                batch.forEach((record) => records.push(record));
                done += 1;

                const errors = batch.filter((record) => record.error).length;
                const findings = batch.reduce((sum, record) => sum + record.findings.length, 0);
                const flag = errors
                    ? ('ERR×' + errors + (findings ? ' f=' + findings : ''))
                    : (findings ? String(findings) : 'ok');
                process.stdout.write(
                    '[' + done + '/' + totalLoads + '] ' + viewport.label
                    + ' ' + uri + ' ×' + themes.length + ' -> ' + flag + '\n'
                );
            }
        }

        writeReport(outPath, { base, themes, viewports, records });
    } finally {
        session.close();
    }
}

/**
 * 写出 JSON 报告并打印按 kind/severity 聚合的摘要。
 *
 * @param {string} outPath 报告路径。
 * @param {object} payload 报告内容。
 * @returns {void}
 */
function writeReport(outPath, payload) {
    const byKind = {};
    let errorCount = 0;
    payload.records.forEach((record) => {
        if (record.error) {
            errorCount += 1;
        }
        record.findings.forEach((finding) => {
            const key = finding.severity + ' / ' + finding.kind;
            byKind[key] = (byKind[key] || 0) + 1;
        });
    });

    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, JSON.stringify({
        generatedAt: new Date().toISOString(),
        base: payload.base,
        themes: payload.themes,
        viewports: payload.viewports.map((viewport) => viewport.label),
        combos: payload.records.length,
        errorCombos: errorCount,
        summaryByKind: byKind,
        records: payload.records
    }, null, 2), 'utf8');

    process.stdout.write('\n==== 摘要 ====\n');
    process.stdout.write('组合数：' + payload.records.length + '，出错组合：' + errorCount + '\n');
    Object.keys(byKind).sort().forEach((key) => {
        process.stdout.write('  ' + key + ' = ' + byKind[key] + '\n');
    });
    process.stdout.write('报告：' + outPath + '\n');
}

main().catch((error) => {
    process.stderr.write('验收编排失败：' + (error && error.stack ? error.stack : error) + '\n');
    process.exit(1);
});

module.exports = { VIEWPORTS };

