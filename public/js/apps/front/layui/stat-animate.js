// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/02
// Time: 00:06
/**
 * 统计数字滚动动画（CountUp 风格，纯原生 JS）。
 *
 * 文件功能：
 * - 监听统计数字元素的文本变化，从旧值平滑滚动到新值（easeOutCubic，600ms）。
 * - 覆盖两类统计：
 *   1. 前台工作台 front-v2-stat 的 dd（directAgentsCount / commissionRate 等）；
 *   2. 模块页 .module-stat-value（前后台 Layui 模块页统计卡）。
 * - 数字格式自适应：保留小数位、千分位、百分比、货币前缀/后缀（按新文本推断）。
 * - 尊重 prefers-reduced-motion：系统要求减弱动效时直接显示终值。
 *
 * 适用场景：
 * - 前台/后台所有统计数字的丝滑呈现，配合 crm-design-system 增强层。
 *
 * 入参例子：
 * - <dd id="directAgentsCount">0</dd> 被脚本更新为 128 -> 从 0 滚动到 128。
 * - <div class="module-stat-value">0.00</div> 被更新为 1,234.56 -> 从 0.00 滚动到 1,234.56。
 *
 * 返回值：
 * - 无。副作用：滚动过程中实时更新元素文本，动画结束后写入终值。
 *
 * 异常或失败场景：
 * - 数值无法解析时直接写入新文本（不滚动），保证数据准确优先于动效。
 */
(function (window, document) {
    'use strict';

    var DURATION = 600;
    var PREFERS_REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * 从文本中解析数值与格式信息。
     *
     * @param {string} text 原始文本，例如 "1,234.56"、"88%"、"$100.00"。
     * @return {object|null} {value, decimals, prefix, suffix}；无法解析时返回 null。
     */
    function parseNumber(text) {
        var match = /^\s*([^\d\-+.]*)\s*([-+]?[\d,]+(?:\.\d+)?)\s*([^\d]*)\s*$/.exec(text || '');
        if (!match) {
            return null;
        }
        var raw = match[2].replace(/,/g, '');
        var value = parseFloat(raw);
        if (isNaN(value)) {
            return null;
        }
        var decimals = (raw.split('.')[1] || '').length;
        return {
            value: value,
            decimals: decimals,
            prefix: match[1] || '',
            suffix: match[3] || ''
        };
    }

    /**
     * 按千分位格式化数字。
     *
     * @param {number} value 数值。
     * @param {number} decimals 小数位数。
     * @return {string} 格式化后的数字文本。
     */
    function format(value, decimals) {
        return value.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    /**
     * 执行一次滚动动画。
     *
     * @param {HTMLElement} element 目标元素。
     * @param {number} from 起始值。
     * @param {object} meta 格式信息。
     * @param {number} to 目标值。
     * @return {void}
     */
    function animate(element, from, meta, to) {
        var startTime = null;

        function frame(timestamp) {
            if (startTime === null) {
                startTime = timestamp;
            }
            var progress = Math.min((timestamp - startTime) / DURATION, 1);
            // easeOutCubic：先快后慢的丝滑减速。
            var eased = 1 - Math.pow(1 - progress, 3);
            var current = from + (to - from) * eased;
            element.textContent = meta.prefix + format(current, meta.decimals) + meta.suffix;
            if (progress < 1) {
                window.requestAnimationFrame(frame);
            } else {
                element.textContent = meta.prefix + format(to, meta.decimals) + meta.suffix;
            }
        }

        window.requestAnimationFrame(frame);
    }

    /**
     * 元素文本变化时触发滚动。
     *
     * @param {HTMLElement} element 目标元素。
     * @param {string} oldText 旧文本。
     * @return {void}
     */
    function onTextChange(element, oldText) {
        var target = parseNumber(element.textContent);
        var previous = parseNumber(oldText);

        if (!target) {
            return;
        }
        // 系统要求减弱动效或旧值不可解析时直接显示终值。
        if (PREFERS_REDUCED || !previous) {
            element.textContent = target.prefix + format(target.value, target.decimals) + target.suffix;
            return;
        }

        animate(element, previous.value, target, target.value);
    }

    /**
     * 为目标元素挂载文本监听（MutationObserver 观察子节点文本变化）。
     *
     * @param {HTMLElement} element 目标元素。
     * @return {void}
     */
    function watch(element) {
        var lastText = element.textContent || '';

        var observer = new MutationObserver(function (mutations) {
            var changed = mutations.some(function (m) {
                return m.type === 'characterData' || (m.type === 'childList' && m.addedNodes.length > 0);
            });
            if (!changed) {
                return;
            }
            var nextText = element.textContent || '';
            if (nextText === lastText) {
                return;
            }
            var previous = lastText;
            lastText = nextText;
            onTextChange(element, previous);
        });

        observer.observe(element, {childList: true, characterData: true, subtree: true});
    }

    /**
     * 初始化：收集统计元素并挂载监听（含动态插入的统计卡）。
     *
     * @return {void}
     */
    function boot() {
        var scope = document;
        var candidates = scope.querySelectorAll(
            '.front-v2-stat dd, .module-stat-value, [data-stat-animate]'
        );
        Array.prototype.forEach.call(candidates, function (el) {
            if (!el.getAttribute('data-stat-animated')) {
                el.setAttribute('data-stat-animated', '1');
                watch(el);
            }
        });

        // 动态插入的统计卡（分页/筛选后刷新）由观察器统一接管。
        if (window.MutationObserver && document.body) {
            new MutationObserver(function (mutations) {
                var added = false;
                mutations.forEach(function (m) {
                    if (m.addedNodes && m.addedNodes.length) {
                        added = true;
                    }
                });
                if (!added) {
                    return;
                }
                scope.querySelectorAll('.front-v2-stat dd, .module-stat-value, [data-stat-animate]')
                    .forEach(function (el) {
                        if (!el.getAttribute('data-stat-animated')) {
                            el.setAttribute('data-stat-animated', '1');
                            watch(el);
                        }
                    });
            }).observe(document.body, {childList: true, subtree: true});
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
}(window, document));
