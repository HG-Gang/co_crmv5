/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 03:05
 */

/**
 * 无头浏览器会话的共用装配层。
 *
 * 文件功能：
 * - 启动隔离的无头 Chrome、建连 CDP、开一个页面会话。
 * - 提供页内求值与「注入 JWT 取得真实登录态」两个基础能力。
 * - 提供会话收尾（关连接、杀进程、清临时目录）。
 *
 * 为什么要单独一层：
 * - audit.js（全量矩阵）、probe.js（单点取证）、selftest.js（检测器自检）
 *   都需要同一套启动与登录逻辑。复制三份会让「凭据/启动参数」出现三个真相源，
 *   改一处漏两处，验收结论就会随脚本不同而漂移。
 *
 * 失败语义：
 * - 启动、连接、登录任一步失败都直接抛出。绝不降级继续：
 *   未登录状态下页面渲染成空态，跑完全程会得出「无缺陷」的假结论。
 */

'use strict';

const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { waitForBrowser, Cdp } = require('./cdp');
const { evaluateLanding } = require('./landing');

/** 默认 Chrome 路径，可用 CHROME_PATH 覆盖。 */
const DEFAULT_CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

/**
 * 启动无头 Chrome。
 *
 * 用独立临时 user-data-dir：复用默认配置目录会继承真实浏览器的扩展、缓存和已登录态，
 * 让验收结果不可复现，也可能把开发者本人的会话数据带进截图。
 *
 * @param {string} chromePath chrome.exe 路径。
 * @param {number} port 调试端口。
 * @returns {{child: object, profileDir: string}}
 */
function launchChrome(chromePath, port) {
    const profileDir = fs.mkdtempSync(path.join(os.tmpdir(), 'crmv5-ui-audit-'));
    const child = spawn(chromePath || DEFAULT_CHROME, [
        '--headless=new',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-extensions',
        // 关闭动画与过渡，避免检测器在过渡中途读到中间几何值造成偶发误报。
        '--force-prefers-reduced-motion',
        '--hide-scrollbars',
        '--remote-debugging-port=' + port,
        '--user-data-dir=' + profileDir,
        'about:blank'
    ], { stdio: 'ignore' });

    return { child, profileDir };
}

/**
 * 在页面上下文执行一段表达式并返回其值。
 *
 * awaitPromise + returnByValue 组合让调用方能直接拿到 JSON 值；
 * 页内异常会被转成 Error 抛出，不允许以 undefined 的形式被忽略。
 *
 * @param {Cdp} cdp CDP 连接。
 * @param {string} sessionId 页面会话 id。
 * @param {string} expression JS 表达式。
 * @returns {Promise<any>}
 */
async function evaluate(cdp, sessionId, expression) {
    const result = await cdp.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true
    }, sessionId);

    if (result.exceptionDetails) {
        const text = result.exceptionDetails.exception
            ? result.exceptionDetails.exception.description || result.exceptionDetails.exception.value
            : result.exceptionDetails.text;
        throw new Error('页内执行失败：' + text);
    }

    return result.result.value;
}

/**
 * 启动浏览器并开一个已启用 Page/Runtime 域的页面会话。
 *
 * @param {object} options {chromePath, port}
 * @returns {Promise<object>} 会话句柄，含 cdp / sessionId / close()。
 */
async function openSession(options) {
    const port = options.port || 9333;
    const { child, profileDir } = launchChrome(options.chromePath, port);

    const version = await waitForBrowser(port, 20000);
    const cdp = await Cdp.connect(version.webSocketDebuggerUrl);

    const target = await cdp.send('Target.createTarget', { url: 'about:blank' });
    const attached = await cdp.send('Target.attachToTarget', {
        targetId: target.targetId,
        flatten: true
    });
    const sessionId = attached.sessionId;

    await cdp.send('Page.enable', {}, sessionId);
    await cdp.send('Runtime.enable', {}, sessionId);

    // 主文档落地校验：Page.loadEventFired 只说明「某个文档加载完了」，
    // 不说明那是不是目标页面。两类情况都会被它放过：
    // 1) 错误页（500/419/429）同样触发 load；
    // 2) 重定向 —— 会话过期跳登录页、无权限跳 dashboard，终点都是 200。
    // 实测取证：导航到不存在的 /admin/__no_such_route__ 实际落到 /admin/dashboard 且 200。
    // 若不校验落地位置，检测器会把登录页或 dashboard 的结果记在目标 URI 名下，
    // 得到的「零缺陷」根本不是那个页面的结论。
    // 因此判据是「最终主文档 URL == 目标 URL 且状态码 200」，二者缺一不可。
    // 只记录 type === 'Document'：子资源（XHR/图片）失败不代表页面不可用。
    let lastDocument = null;
    cdp.on('Network.responseReceived', (params, incomingSession) => {
        if (incomingSession !== sessionId || params.type !== 'Document') {
            return;
        }
        lastDocument = { url: params.response.url, status: params.response.status };
    });
    await cdp.send('Network.enable', {}, sessionId);

    return {
        cdp,
        sessionId,
        /**
         * 判定本次导航是否真的落在目标页面上。
         *
         * @param {string} url 导航目标 URL。
         * @returns {{ok: boolean, reason: string}} ok=true 表示落地正确；
         *   否则 reason 给出可直接写进报告的中文原因。
         */
        verifyLanding(url) {
            return evaluateLanding(lastDocument, url);
        },
        /**
         * 清空上一次导航的主文档记录。
         *
         * 每次导航前调用：不清的话，同 URL 重复导航或导航失败时，
         * 会把上一次的成功记录当成本次结果。
         *
         * @returns {void}
         */
        resetDocumentStatus() {
            lastDocument = null;
        },
        /**
         * 收尾：关连接、杀进程、清临时目录。
         *
         * @returns {void}
         */
        close() {
            cdp.close();
            child.kill();
            try {
                fs.rmSync(profileDir, { recursive: true, force: true });
            } catch (error) {
                // 临时目录清理失败不影响验收结论，不掩盖真实结果。
            }
        }
    };
}

/**
 * 登录并把令牌写入 localStorage。
 *
 * 后台 Blade 页面本身只挂 web 中间件、不做鉴权，但页面数据全部来自
 * JWT 保护的 /api/* 接口。不注入令牌，表格会渲染成空态或错误态，
 * 那样验收到的就不是真实业务页面，长金额/长用户名撑破单元格等缺陷根本不会出现。
 *
 * @param {Cdp} cdp CDP 连接。
 * @param {string} sessionId 页面会话。
 * @param {string} base 服务基地址。
 * @returns {Promise<{admin: boolean, front: boolean}>} 两侧登录是否成功。
 */
async function authenticate(cdp, sessionId, base) {
    await cdp.send('Page.navigate', { url: base + '/admin/login' }, sessionId);
    await cdp.once('Page.loadEventFired', 15000, sessionId);

    const script = `(async () => {
        const out = { admin: false, front: false, adminMsg: '', frontMsg: '' };
        try {
            const r = await fetch('/api/admin/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                // 账号必须是 superadmin 而不是 admin：/api/admin/login 查的是 admins 表
                // （AuthController::login 用 Admin 模型 where username orWhere email），
                // InitialDataSeeder 往 admins 写的是 username=superadmin / admin@crmv5.com；
                // username=admin 只存在于 admin_logins 这张旧表，用它登录必然 4001。
                body: JSON.stringify({ username: 'superadmin', password: 'abc123' })
            });
            const j = await r.json();
            const token = j && j.data && j.data.access_token;
            if (token) {
                localStorage.setItem('admin_token', token);
                localStorage.setItem('admin_jwt_token', token);
                out.admin = true;
            } else {
                out.adminMsg = JSON.stringify(j).slice(0, 200);
            }
        } catch (e) { out.adminMsg = String(e); }
        try {
            const r = await fetch('/api/front/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ account: 'agent@test.com', password: 'abc123' })
            });
            const j = await r.json();
            const token = j && j.data && j.data.access_token;
            if (token) {
                localStorage.setItem('front_token', token);
                localStorage.setItem('front_jwt_token', token);
                out.front = true;
            } else {
                out.frontMsg = JSON.stringify(j).slice(0, 200);
            }
        } catch (e) { out.frontMsg = String(e); }
        return out;
    })()`;

    const auth = await evaluate(cdp, sessionId, script);

    // 登录失败必须显式失败退出：带着空态页面跑完全程会得出「无缺陷」的假结论。
    if (!auth.admin || !auth.front) {
        throw new Error('登录失败，无法验收真实业务页面：' + JSON.stringify(auth));
    }

    return auth;
}

module.exports = { DEFAULT_CHROME, launchChrome, evaluate, openSession, authenticate };
