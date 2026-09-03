/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 02:40
 */

/**
 * 检测器自检：用已知答案的合成页面验证 detectors.js 的判定能力。
 *
 * 文件功能：
 * - 构造一个刻意包含各类已知缺陷、同时包含各类「不该报」对照项的页面。
 * - 跑 inPageAudit()，逐条断言「该报的报了」且「不该报的没报」。
 *
 * 为什么必须有这个自检：
 * - 全量矩阵里对比度类缺陷 0 命中。0 命中有两种截然不同的解释：
 *   真的没有缺陷，或者检测器根本不会触发。报告本身分辨不了这两者。
 * - 不证明检测器能触发，就不能拿「0 命中」当验收结论——那是最典型的假绿。
 * - 同时反向锁定两类已修复的误报（sr-only 文本、首个可交互元素焦点），
 *   防止后续改动把它们改回去。
 *
 * 失败语义：
 * - 任一断言不成立即以非 0 退出，并打印实际收到的缺陷种类，便于定位。
 *
 * 用法：
 *   node scripts/ui-acceptance/selftest.js
 */

'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');
const { waitForBrowser, Cdp } = require('./cdp');
const { inPageAudit } = require('./detectors');
const { evaluateLanding } = require('./landing');

/** 合成页面：每个区块的注释说明它验证哪一条判定。 */
const FIXTURE_HTML = `<!doctype html>
<html><head><meta charset="utf-8"><style>
  body { margin: 0; background: #ffffff; font-family: sans-serif; }
  /* 该报：#999 on #fff = 2.85:1，低于 4.5:1 */
  .bad-contrast { color: #999999; background: #ffffff; font-size: 14px; }
  /* 不该报：#000 on #fff = 21:1 */
  .good-contrast { color: #000000; background: #ffffff; font-size: 14px; }
  /* 该报：内容宽于容器且 overflow-x 可见 */
  .h-overflow { width: 80px; overflow-x: visible; white-space: nowrap; }
  /* 不该报：声明了 overflow-x auto，横向滚动是设计意图 */
  .h-scroll { width: 80px; overflow-x: auto; white-space: nowrap; }
  /* 该报：硬裁断且无 ellipsis */
  .hard-clip { width: 40px; overflow: hidden; white-space: nowrap; text-overflow: clip; }
  /* 不该报：ellipsis 是设计选择，只记 info */
  .ellipsis { width: 40px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
  /* 不该报：sr-only 视觉隐藏 */
  .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
             overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
  /* 该报：聚焦后无任何可见变化 */
  .no-focus-ring:focus { outline: none; box-shadow: none; }
  /* 不该报：聚焦后有 3px 焦点环 */
  .has-focus-ring:focus { outline: 3px solid #0b6bcb; }
  /* 该报：两个可点元素几何重叠 */
  .overlap-wrap { position: relative; height: 40px; }
  .overlap-wrap .ov { position: static; display: inline-block; width: 120px; }
  /*
   * 不该报：sticky 顶栏滚动后盖住内容是设计意图，不是误点缺陷。
   * 几何必须让两个按钮在 x 轴也相交，否则滚到底后只有 y 轴重叠，
   * 判据要求 overlapW>2 && overlapH>2，夹具会「碰巧不报」而失去锁定意义。
   * 因此两个按钮都从左边距 0 起、给足宽度，确保 x 轴必然相交。
   */
  .sticky-bar { position: sticky; top: 0; height: 44px; background: #0b6bcb; }
  .sticky-action { width: 300px; height: 40px; }
  /*
   * filler 只负责撑出滚动距离，让按钮在 scrollTop=0 时远在视口之外——
   * 这样「检测器归零滚动位置」与「不归零」两种行为才会给出不同结论。
   */
  .tall-filler { height: 1600px; }
  /*
   * 关键几何：滚到底时文档底边与视口底边对齐，所以「按钮之后的尾部高度」
   * 决定按钮落在视口的哪个 y 位置。尾部 680px + 按钮 40px = 720px 视口高，
   * 按钮因此正好落在 y≈0..40，与 sticky 顶栏的 .sticky-action(y 0..40) 完全相交。
   *
   * 为什么不能用负 margin 或 padding 去推：两者都在「文档坐标」里移动按钮，
   * 而 maxScroll 由 filler 底边决定，按钮只会离视口更远（实测 top=-824）。
   * 只有尾部占位块能在「视口坐标」里定位按钮。
   *
   * 容差：overlapH>2 的判据要求按钮 top ∈ (-38, 38)，即尾部高度有 ±37px 余量，
   * 足以吸收行盒与默认外边距的浮动，不是卡在临界值上的脆弱夹具。
   */
  .below-fold-action { display: block; width: 300px; height: 40px; }
  .tail-spacer { height: 680px; }
  /*
   * 不该报：fixed 顶栏在 scrollTop=0 时就盖住流内按钮。
   * 这个场景专门锁定 inStickyChrome：归零滚动位置对它无效
   * （fixed 元素本就不随滚动移动），只有「计算样式 position 判据」能拦住。
   * 本项目 .crm-admin-topbar 正是 fixed，属真实构造而非臆造。
   */
  .fixed-bar { position: fixed; top: 0; left: 0; width: 300px; height: 40px; background: #0b6bcb; }
  .fixed-action { width: 300px; height: 36px; }
  .covered-action { display: block; width: 300px; height: 36px; }
  /* 该报：placeholder 对比度不足 */
  .bad-placeholder { background: #ffffff; }
  .bad-placeholder::placeholder { color: #bbbbbb; }
  /* 该报：disabled 文本对比度不足（info 级） */
  .bad-disabled { color: #cccccc; background: #ffffff; }
</style></head><body>
  <div class="fixed-bar"><button class="fixed-action">固定栏按钮</button></div>
  <button class="covered-action">被固定栏盖住的流内按钮</button>
  <p class="bad-contrast">低对比度正文应被报出</p>
  <h2 class="good-contrast">高对比度标题不该被报出</h2>
  <div class="h-overflow">这段文字明显宽于八十像素的容器所以横向溢出</div>
  <div class="h-scroll">这段文字同样很宽但容器声明了可滚动因此不算缺陷</div>
  <span class="hard-clip">硬裁断文本</span>
  <span class="ellipsis">省略号文本</span>
  <label class="sr-only">屏幕阅读器专用标签</label>
  <div class="overlap-wrap">
    <button class="ov" style="margin-right:-100px">按钮甲</button>
    <button class="ov">按钮乙</button>
  </div>
  <button class="no-focus-ring">无焦点环按钮</button>
  <button class="has-focus-ring">有焦点环按钮</button>
  <input class="bad-placeholder" placeholder="占位符对比度不足">
  <button class="bad-disabled" disabled>禁用态文本</button>
  <table><tbody><tr><td class="bad-contrast">表格低对比度单元格</td></tr></tbody></table>
  <div class="sticky-bar"><button class="sticky-action">悬浮栏按钮</button></div>
  <div class="tall-filler">
    <p>撑高页面，让 sticky 顶栏在滚动后与下方按钮几何相交。</p>
  </div>
  <button class="below-fold-action">折叠线以下按钮</button>
  <div class="tail-spacer"></div>
</body></html>`;

/** 期望出现的缺陷种类；缺任意一项说明该判据失效。 */
const MUST_REPORT = [
    'text-contrast',
    'table-cell-contrast',
    'element-h-overflow',
    'text-hard-clip',
    'interactive-overlap',
    'focus-not-visible',
    'placeholder-contrast',
    'disabled-contrast'
];

/** 期望不出现的缺陷种类；出现任意一项说明存在误报。 */
const MUST_NOT_REPORT = ['document-h-overflow'];

/**
 * 断言辅助：记录一条结果。
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
    // 落地判据先自检：它不依赖浏览器，放在启动前跑，失败时立刻可见。
    // 自检夹具走 file:// 协议，无法复现服务端重定向，所以这道判据只能在
    // 函数级验证 —— 但它必须被验证：v2 全量矩阵 255 个错误组合是靠主题哨兵
    // 偶然拦下的（重定向终点恰好缺 theme-assets），换个带主题的终点就会全部记成正常数据。
    const landingResults = [];
    check(
        landingResults,
        evaluateLanding({ url: 'http://h/admin/agents', status: 200 }, 'http://h/admin/agents').ok,
        '落地判据：URL 与状态码都对时必须放行'
    );
    check(
        landingResults,
        evaluateLanding({ url: 'http://h/admin/agents/', status: 200 }, 'http://h/admin/agents').ok,
        '落地判据：末尾斜杠差异不得判成重定向'
    );
    check(
        landingResults,
        evaluateLanding({ url: 'http://h/admin/agents?page=1', status: 200 }, 'http://h/admin/agents').ok,
        '落地判据：查询串差异不得判成重定向'
    );
    check(
        landingResults,
        !evaluateLanding({ url: 'http://h/admin/dashboard', status: 200 }, 'http://h/admin/agents').ok,
        '落地判据：重定向到别的页面必须报错（状态 200 也不放行）'
    );
    check(
        landingResults,
        !evaluateLanding({ url: 'http://h/admin/agents', status: 500 }, 'http://h/admin/agents').ok,
        '落地判据：URL 相同但 HTTP 500 必须报错'
    );
    check(
        landingResults,
        !evaluateLanding(null, 'http://h/admin/agents').ok,
        '落地判据：未捕获主文档响应必须报错，不得默认放行'
    );

    const chromePath = process.env.CHROME_PATH
        || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    const port = 9401;
    const profileDir = fs.mkdtempSync(path.join(os.tmpdir(), 'crmv5-selftest-'));
    const fixturePath = path.join(profileDir, 'fixture.html');
    fs.writeFileSync(fixturePath, FIXTURE_HTML, 'utf8');

    const child = spawn(chromePath, [
        '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--disable-extensions', '--hide-scrollbars',
        '--remote-debugging-port=' + port, '--user-data-dir=' + profileDir, 'about:blank'
    ], { stdio: 'ignore' });

    let cdp = null;
    const results = landingResults.slice();

    try {
        const version = await waitForBrowser(port, 20000);
        cdp = await Cdp.connect(version.webSocketDebuggerUrl);
        const target = await cdp.send('Target.createTarget', { url: 'about:blank' });
        const attached = await cdp.send('Target.attachToTarget', {
            targetId: target.targetId, flatten: true
        });
        const sessionId = attached.sessionId;

        await cdp.send('Page.enable', {}, sessionId);
        await cdp.send('Runtime.enable', {}, sessionId);
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: 1280, height: 720, deviceScaleFactor: 1, mobile: false
        }, sessionId);

        await cdp.send('Page.navigate', {
            url: 'file:///' + fixturePath.replace(/\\/g, '/')
        }, sessionId);
        const loaded = await cdp.once('Page.loadEventFired', 15000, sessionId);
        if (!loaded) {
            throw new Error('自检夹具页面未触发 load');
        }

        // 与正式验收一致：先发真实 Tab 打开键盘模态，否则焦点判据会全量误报。
        await cdp.send('Input.dispatchKeyEvent', {
            type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9
        }, sessionId);
        await cdp.send('Input.dispatchKeyEvent', {
            type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9
        }, sessionId);

        // 刻意先把页面滚到底，复现「上一个检测器留下的滚动位置」这个真实场景。
        // 编排器在同一次加载内循环 15 个主题，对比度检测的 scrollIntoView
        // 会把页面滚到底且不复位，于是从第 2 个主题起重叠检测都在这个位置上执行。
        // 检测器必须自己归零滚动位置，否则 sticky 顶栏会被判成高危重叠——
        // 全量报告曾因此产出 17295 条假阳性。
        const scrolled = await cdp.send('Runtime.evaluate', {
            expression: `(function () {
                var scroller = document.scrollingElement || document.documentElement;
                scroller.scrollTop = scroller.scrollHeight;
                return scroller.scrollTop;
            })()`,
            returnByValue: true
        }, sessionId);
        if (!scrolled.result.value) {
            throw new Error('自检夹具未能滚动，无法复现滚动残留场景');
        }

        const evaluated = await cdp.send('Runtime.evaluate', {
            expression: '(' + inPageAudit.toString() + ')()',
            awaitPromise: true,
            returnByValue: true
        }, sessionId);
        if (evaluated.exceptionDetails) {
            throw new Error('检测器执行异常：' + JSON.stringify(evaluated.exceptionDetails));
        }

        const audit = evaluated.result.value;
        const kinds = Object.keys(audit.kindCounts || {});
        process.stdout.write('实际命中种类：' + (kinds.join(', ') || '（无）') + '\n\n');

        MUST_REPORT.forEach((kind) => {
            check(results, kinds.indexOf(kind) > -1, '应报出 ' + kind);
        });
        MUST_NOT_REPORT.forEach((kind) => {
            check(results, kinds.indexOf(kind) === -1, '不应报出 ' + kind);
        });

        // sr-only 与「有焦点环」两条是已修复误报的反向锁定：
        // 一旦有人改回旧判据，这两条会立刻失败。
        const clipped = audit.findings.filter((finding) => finding.kind === 'text-hard-clip');
        check(
            results,
            clipped.every((finding) => finding.selector.indexOf('sr-only') === -1),
            'sr-only 标签不得被判成 text-hard-clip'
        );
        const focusMissed = audit.findings.filter((finding) => finding.kind === 'focus-not-visible');
        check(
            results,
            focusMissed.every((finding) => finding.selector.indexOf('has-focus-ring') === -1),
            '有 3px 焦点环的按钮不得被判成 focus-not-visible'
        );
        check(
            results,
            focusMissed.some((finding) => finding.selector.indexOf('no-focus-ring') > -1),
            '无焦点环的按钮必须被判成 focus-not-visible'
        );
        // ellipsis 属设计选择，只能是 info，不能升级成高危。
        const ellipsis = audit.findings.filter((finding) => finding.kind === 'text-ellipsis');
        check(
            results,
            ellipsis.every((finding) => finding.severity === 'info'),
            'text-ellipsis 只能记为 info'
        );

        // sticky 悬浮栏的反向锁定。夹具已滚到底，若检测器不排除悬浮骨架、
        // 也不归零滚动位置，这条必然失败——正是全量报告 17295 条假阳性的成因。
        const overlaps = audit.findings.filter((finding) => finding.kind === 'interactive-overlap');
        check(
            results,
            overlaps.every((finding) => finding.selector.indexOf('sticky-action') === -1),
            'sticky 悬浮栏内的按钮不得被判成 interactive-overlap'
        );
        check(
            results,
            overlaps.every((finding) => finding.selector.indexOf('below-fold-action') === -1),
            '被悬浮栏盖住的折叠线下按钮不得被判成 interactive-overlap'
        );
        // fixed 顶栏场景独立锁定 inStickyChrome：fixed 不随滚动移动，
        // 归零滚动位置对它无效，这两条只能靠 position 判据通过。
        check(
            results,
            overlaps.every((finding) => finding.selector.indexOf('fixed-action') === -1),
            'fixed 固定栏内的按钮不得被判成 interactive-overlap'
        );
        check(
            results,
            overlaps.every((finding) => finding.selector.indexOf('covered-action') === -1),
            '被 fixed 固定栏盖住的流内按钮不得被判成 interactive-overlap'
        );
        // 同时确认真重叠仍报得出来：若上面两条靠「整类失效」通过，这条会失败。
        check(
            results,
            overlaps.some((finding) => finding.selector.indexOf('ov') > -1),
            '流内两个真重叠按钮仍必须被判成 interactive-overlap'
        );
    } finally {
        if (cdp) {
            cdp.close();
        }
        child.kill();
        try {
            fs.rmSync(profileDir, { recursive: true, force: true });
        } catch (error) {
            // 临时目录清理失败不影响自检结论。
        }
    }

    const failed = results.filter((result) => !result.ok);
    results.forEach((result) => {
        process.stdout.write((result.ok ? '  PASS  ' : '  FAIL  ') + result.message + '\n');
    });
    process.stdout.write(
        '\n自检：' + (results.length - failed.length) + '/' + results.length + ' 通过\n'
    );

    if (failed.length) {
        process.exit(1);
    }
}

main().catch((error) => {
    process.stderr.write('自检失败：' + (error && error.stack ? error.stack : error) + '\n');
    process.exit(1);
});
