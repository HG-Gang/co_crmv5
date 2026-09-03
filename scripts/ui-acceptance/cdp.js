/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 00:20
 */

/**
 * 最小 Chrome DevTools Protocol 客户端（零依赖）。
 *
 * 文件功能：
 * - 用 Node 24 内置的 WebSocket 与 fetch 直连 Chrome 的调试端口，不引入 puppeteer 等外部依赖。
 * - 提供 send(method, params) 的请求/响应配对，以及 on(event) 的事件订阅。
 *
 * 为什么不用 puppeteer：
 * - 本项目无 package.json，引入 npm 依赖会新增供应链面与离线安装风险；
 * - 验收只需要 Emulation/Page/Runtime 三个域的少量命令，自建客户端足够且可审计。
 *
 * 失败语义：
 * - 任何 CDP 命令返回 error 时直接 reject，禁止把失败当成空结果继续跑，
 *   否则验收会在浏览器未真正渲染的情况下输出「无缺陷」的假绿。
 */

'use strict';

/**
 * 轮询 Chrome 调试端口，直到 /json/version 可用。
 *
 * @param {number} port 调试端口。
 * @param {number} timeoutMs 最长等待毫秒数。
 * @returns {Promise<object>} /json/version 的 JSON 响应。
 */
async function waitForBrowser(port, timeoutMs) {
    const deadline = Date.now() + timeoutMs;
    let lastError = null;

    while (Date.now() < deadline) {
        try {
            const response = await fetch('http://127.0.0.1:' + port + '/json/version');
            if (response.ok) {
                return await response.json();
            }
            lastError = new Error('HTTP ' + response.status);
        } catch (error) {
            lastError = error;
        }
        await new Promise((resolve) => setTimeout(resolve, 200));
    }

    throw new Error('Chrome 调试端口未就绪：' + (lastError ? lastError.message : 'timeout'));
}

/**
 * CDP 连接：一条 WebSocket 上复用请求/响应与事件。
 *
 * 命令必须带超时（取证依据，不是预防性设计）：
 * - 2026-09-01 全量矩阵在第 205 个组合永久挂死 —— 卡在 /admin-crmui 切换到 /admin
 *   的家族边界，node 进程 CPU 停在 8 秒不再增长、日志停止写入超过 10 分钟，
 *   而同一时刻 curl 该页面 0.3 秒返回 200，服务端完全正常。
 * - 根因是 send() 的 Promise 只在收到回包时 settle：Chrome 跨家族切换要换渲染进程，
 *   此刻发出的命令若丢包就永远等不到响应。调用方的 try/catch 拦不住一个
 *   永不 reject 的 Promise，导航超时（20 秒）也管不到命令层。
 * - 后果是整轮验收无法完成，拿不到基线；v2 报告里 17 次「连续失败后自愈」
 *   是同一不稳定性的较轻表现。
 */
class Cdp {
    /**
     * 单条 CDP 命令的默认超时。
     *
     * 取 30 秒的理由：正常命令是毫秒级，导航类事件另有 20 秒的 once() 超时，
     * 30 秒足以覆盖跨渲染进程切换的最慢正常情形，又能在挂死时及时暴露。
     * 调小会把慢而正常的命令误判成故障，调大则重新接近「挂死」的体验。
     *
     * @type {number}
     */
    static COMMAND_TIMEOUT_MS = 30000;

    /**
     * @param {WebSocket} socket 已 open 的 WebSocket 实例。
     */
    constructor(socket) {
        this.socket = socket;
        this.nextId = 0;
        this.pending = new Map();
        this.listeners = new Map();

        this.socket.addEventListener('message', (event) => {
            this.dispatch(JSON.parse(event.data));
        });
    }

    /**
     * 建立连接并等待 open。
     *
     * @param {string} url WebSocket 调试地址。
     * @returns {Promise<Cdp>}
     */
    static connect(url) {
        return new Promise((resolve, reject) => {
            const socket = new WebSocket(url);
            socket.addEventListener('open', () => resolve(new Cdp(socket)));
            socket.addEventListener('error', () => reject(new Error('CDP WebSocket 连接失败：' + url)));
        });
    }

    /**
     * 分发一条 CDP 报文：带 id 的是命令响应，否则是事件。
     *
     * @param {object} message 解析后的报文。
     * @returns {void}
     */
    dispatch(message) {
        if (message.id !== undefined && this.pending.has(message.id)) {
            const { resolve, reject } = this.pending.get(message.id);
            this.pending.delete(message.id);
            // 失败必须显式抛出：静默返回 undefined 会让上层把「命令没执行」误判为「页面没问题」。
            if (message.error) {
                reject(new Error('CDP ' + JSON.stringify(message.error)));
                return;
            }
            resolve(message.result);
            return;
        }

        const handlers = this.listeners.get(message.method);
        if (handlers) {
            handlers.forEach((handler) => handler(message.params, message.sessionId));
        }
    }

    /**
     * 发送一条 CDP 命令。
     *
     * 超时即 reject（见类文档记录的挂死取证），调用方据此把该组合记为 error。
     *
     * @param {string} method 域方法名，如 'Page.navigate'。
     * @param {object} [params] 参数对象。
     * @param {string} [sessionId] 目标会话 id；操作页面时必填。
     * @param {number} [timeoutMs] 覆盖默认超时，缺省用 COMMAND_TIMEOUT_MS。
     * @returns {Promise<object>} 命令结果。
     */
    send(method, params, sessionId, timeoutMs) {
        this.nextId += 1;
        const id = this.nextId;
        const payload = { id, method, params: params || {} };
        if (sessionId) {
            payload.sessionId = sessionId;
        }
        const limit = timeoutMs || Cdp.COMMAND_TIMEOUT_MS;

        return new Promise((resolve, reject) => {
            // 超时必须 reject 而不是 resolve：命令没有回包意味着这一步没做成，
            // 返回空结果会让调用方拿着「设备尺寸未生效」的页面继续跑完整套检测，
            // 产出的零缺陷是假绿。reject 交由 auditPageViewport() 的 try/catch
            // 记成该组合的 error，如实计入退出码。
            const timer = setTimeout(() => {
                if (this.pending.delete(id)) {
                    reject(new Error(
                        'CDP 命令超时 ' + limit + 'ms：' + method
                        + (sessionId ? '（会话 ' + sessionId + '）' : '')
                    ));
                }
            }, limit);

            const settle = (handler) => (value) => {
                clearTimeout(timer);
                handler(value);
            };

            this.pending.set(id, { resolve: settle(resolve), reject: settle(reject) });
            this.socket.send(JSON.stringify(payload));
        });
    }

    /**
     * 订阅一个 CDP 事件。
     *
     * @param {string} event 事件名，如 'Page.loadEventFired'。
     * @param {Function} handler 回调，收到 (params, sessionId)。
     * @returns {void}
     */
    on(event, handler) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(handler);
    }

    /**
     * 等待某个事件出现一次，带超时。
     *
     * 超时不抛错而是返回 false：页面可能因为没有网络请求而永不触发 networkIdle，
     * 这属于正常情况；由调用方决定是否继续。真正的失败（命令报错）走 send() 的 reject。
     *
     * @param {string} event 事件名。
     * @param {number} timeoutMs 超时毫秒。
     * @param {string} [sessionId] 只接受该会话的事件。
     * @returns {Promise<boolean>} true=等到了，false=超时。
     */
    once(event, timeoutMs, sessionId) {
        return new Promise((resolve) => {
            let settled = false;
            const timer = setTimeout(() => {
                if (!settled) {
                    settled = true;
                    resolve(false);
                }
            }, timeoutMs);

            this.on(event, (_params, incomingSession) => {
                if (settled || (sessionId && incomingSession !== sessionId)) {
                    return;
                }
                settled = true;
                clearTimeout(timer);
                resolve(true);
            });
        });
    }

    /**
     * 关闭连接。
     *
     * @returns {void}
     */
    close() {
        this.socket.close();
    }
}

module.exports = { waitForBrowser, Cdp };
