/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 16:20
 */

/**
 * 主文档落地判据。
 *
 * 文件功能：
 * - 判定一次导航是否真的停在目标页面上，而不是错误页或重定向终点。
 *
 * 为什么需要它：
 * - Page.loadEventFired 只说明「某个文档加载完了」，不说明那是目标页面。
 *   错误页（500/419/429）会触发 load；会话过期跳登录页、无权限跳 dashboard
 *   的终点都是 200。两种情况下检测器都在别的页面上取样，
 *   却把结果记在目标 URI 名下，报告里就出现「从未访问过的页面通过了验收」。
 * - 实测取证：导航到不存在的 /admin/__no_such_route__ 实际落到
 *   /admin/dashboard 且状态 200，跳过本判据时该组合被记为 0 错误、
 *   并带上 dashboard 的 6 条 text-contrast。
 *
 * 适用场景：
 * - audit.js 每次导航后调用；selftest.js 直接对判据做红绿断言
 *   （自检夹具走 file:// 协议，无法复现服务端重定向，因此判据必须可独立验证）。
 *
 * 方法功能：
 * - normalizeUrl()：把 URL 归一化为「协议+主机+路径」并去掉末尾斜杠。
 * - evaluateLanding()：比对实际主文档与目标 URL，给出通过与否及中文原因。
 *
 * 返回值：
 * - evaluateLanding() 返回 {ok, reason}；ok=true 时 reason 为空串。
 *
 * 异常或失败场景：
 * - URL 解析失败时按原字符串比较，不抛错（判据不应因为畸形 URL 而中断整轮验收）。
 */

'use strict';

/**
 * URL 归一化：只保留协议、主机与路径，并去掉末尾斜杠。
 *
 * 查询串与 hash 不参与比较：验收清单里的 URI 不带查询串，
 * 而框架可能追加 ?page=1 之类参数，那仍然是同一个页面。
 *
 * @param {string} url 待归一化的 URL。
 * @returns {string} 归一化结果；解析失败时返回去掉末尾斜杠的原串。
 */
function normalizeUrl(url) {
    try {
        const parsed = new URL(url);
        return (parsed.origin + parsed.pathname).replace(/\/+$/, '');
    } catch (error) {
        return String(url).replace(/\/+$/, '');
    }
}

/**
 * 判定导航是否落在目标页面。
 *
 * 三个判据顺序固定：先确认拿到了主文档响应，再看状态码，最后比对落地 URL。
 * 顺序反了会把「未捕获」误报成「被重定向到 undefined」。
 *
 * @param {{url: string, status: number}|null} actual 实际主文档响应；null 表示未捕获。
 * @param {string} targetUrl 导航目标 URL。
 * @returns {{ok: boolean, reason: string}} 判定结果与中文原因。
 */
function evaluateLanding(actual, targetUrl) {
    if (!actual) {
        return { ok: false, reason: '未捕获主文档响应，无法确认页面已加载' };
    }
    if (actual.status !== 200) {
        return { ok: false, reason: '主文档 HTTP ' + actual.status + '，未渲染目标页面' };
    }
    if (normalizeUrl(actual.url) !== normalizeUrl(targetUrl)) {
        return { ok: false, reason: '被重定向到 ' + actual.url + '，未渲染目标页面' };
    }
    return { ok: true, reason: '' };
}

module.exports = { normalizeUrl, evaluateLanding };
