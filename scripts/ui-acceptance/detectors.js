/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 00:35
 */

/**
 * 页内渲染缺陷检测器集合。
 *
 * 文件功能：
 * - 导出一个在浏览器页面上下文里执行的函数源码，返回结构化缺陷清单。
 * - 覆盖 docs/audits/2026-08-30-handoff-resume-here.md §5.1 列出的「静态审计做不了」项：
 *   横向溢出、元素重叠、文本截断、键盘焦点可见性、disabled/placeholder 实际对比度、表格斑马纹对比度。
 *
 * 为什么必须在真实浏览器里做：
 * - 这些缺陷都由「层叠后的计算样式 + 实际字体度量 + 实际视口宽度」共同决定，
 *   读 CSS 源码无法得出结论。§5.1 已明确要求不得把静态结论表述为浏览器验收。
 *
 * 边界（刻意不报的情况，避免噪音淹没真缺陷）：
 * - 自身声明 overflow-x: auto/scroll 的容器，横向滚动是设计意图，不算溢出缺陷；
 * - position 为 fixed/absolute/sticky 的浮层与其他元素重叠属正常层叠，不算重叠缺陷；
 * - 隐藏元素（display:none / visibility:hidden / 尺寸为 0 / opacity:0）全部跳过。
 */

'use strict';

/**
 * 在页面上下文执行的检测主体。返回值必须可 JSON 序列化。
 *
 * @returns {{findings: Array<object>, stats: object}} 缺陷清单与统计。
 */
function inPageAudit() {
    var findings = [];
    var MAX_PER_KIND = 12;

    /**
     * 记录一条缺陷，同类超过上限后只累计计数，避免单页产出上万条噪音。
     */
    var kindCounts = {};
    function report(kind, severity, selector, detail) {
        kindCounts[kind] = (kindCounts[kind] || 0) + 1;
        if (kindCounts[kind] > MAX_PER_KIND) {
            return;
        }
        findings.push({ kind: kind, severity: severity, selector: selector, detail: detail });
    }

    /**
     * 生成一个足够定位元素的选择器：优先 id，其次 data 属性，再退化为标签+类名+序号。
     */
    function describe(el) {
        if (!el || !el.tagName) {
            return '<unknown>';
        }
        if (el.id) {
            return '#' + el.id;
        }
        var tag = el.tagName.toLowerCase();
        var cls = typeof el.className === 'string' && el.className
            ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.')
            : '';
        var text = (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 24);
        return tag + cls + (text ? ' «' + text + '»' : '');
    }

    /**
     * 元素是否真正可见。不可见元素的几何值无意义，必须先排除，
     * 否则 display:none 的弹窗会被误报成横向溢出与重叠。
     */
    function isVisible(el) {
        var style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0) {
            return false;
        }
        var rect = el.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) {
            return false;
        }
        return !isScreenReaderOnly(el, style, rect);
    }

    /**
     * 元素是否为「屏幕阅读器专用」的视觉隐藏元素。
     *
     * 为什么必须单独排除：这类元素（本项目的 .crm-sr-only）用
     * width:1px + height:1px + overflow:hidden + clip:rect(0,0,0,0) 把自己压成 1px 盒，
     * display 与 visibility 都是正常值，所以躲过了常规可见性判断，
     * 但它的文本本来就不打算给眼睛看——只在无障碍树里存在。
     * 不排除的话，每个页面的每个 sr-only 标签都会被判成「文本被硬裁断」高危缺陷，
     * 噪音会把真缺陷淹掉（冒烟验证里 11 条 text-hard-clip 全部出自这里）。
     *
     * 判据不绑类名而看渲染特征：1px 级的盒子放不下任何文字，
     * 因此无论类名叫什么，此类元素的几何值都不该参与视觉缺陷判定。
     */
    function isScreenReaderOnly(el, style, rect) {
        if (rect.width <= 1 || rect.height <= 1) {
            return true;
        }
        var clip = (style.clip || '').replace(/\s+/g, '');
        if (clip === 'rect(0px,0px,0px,0px)') {
            return true;
        }
        var clipPath = style.clipPath || '';
        return clipPath.indexOf('inset(50%') === 0 || clipPath === 'inset(100%)';
    }

    var all = Array.prototype.slice.call(document.querySelectorAll('body *'));
    var visible = all.filter(isVisible);

    // ---- 1. 文档级横向溢出 ----
    // 判据用 documentElement.scrollWidth 与 innerWidth 比较：出现横向滚动条即为缺陷。
    // 容差 1px 用于吸收亚像素舍入（缩放/边框半像素），不放过真实溢出。
    var docOverflow = document.documentElement.scrollWidth - window.innerWidth;
    if (docOverflow > 1) {
        report('document-h-overflow', 'high', 'html', {
            scrollWidth: document.documentElement.scrollWidth,
            innerWidth: window.innerWidth,
            overflowPx: docOverflow
        });

        // 定位真正把文档撑宽的元素：右边界超出视口最多者最可疑。
        var widest = null;
        var widestRight = window.innerWidth + 1;
        visible.forEach(function (el) {
            var rect = el.getBoundingClientRect();
            if (rect.right > widestRight) {
                widestRight = rect.right;
                widest = el;
            }
        });
        if (widest) {
            report('document-h-overflow-culprit', 'high', describe(widest), {
                right: Math.round(widestRight),
                innerWidth: window.innerWidth
            });
        }
    }

    // ---- 2. 元素级横向溢出（排除刻意可滚动容器）----
    visible.forEach(function (el) {
        var style = window.getComputedStyle(el);
        // overflow-x 为 auto/scroll 时横向滚动是设计意图（表格容器普遍如此），不算缺陷。
        if (style.overflowX === 'auto' || style.overflowX === 'scroll') {
            return;
        }
        // overflow 为 hidden 时内容被裁掉，属「截断」而非「溢出」，由 3 号检测器负责。
        if (style.overflowX === 'hidden') {
            return;
        }
        if (el.scrollWidth - el.clientWidth > 1 && el.clientWidth > 0) {
            var clipX = el.scrollWidth - el.clientWidth;

            // RC-4 去噪：≤3px 的溢出多为亚像素累积（浏览器 scrollWidth 测量的舍入噪声），
            // 如 #permissionTree 在 390 下的 3px（213 个节点的图标+缩进累积），视觉上无溢出。
            if (clipX <= 3) {
                return;
            }

            // RC-7 去噪：input[type="file"] 的 scrollWidth 包含浏览器原生控件的内部结构
            // （"选择文件"按钮 + 隐藏文件名缓冲区），不受 CSS width 约束，scrollWidth 是测量假象。
            if (el.tagName.toLowerCase() === 'input' && el.type === 'file') {
                return;
            }

            // RC-6 去噪：layui 栅格系统 .layui-col-space* 用负边距抵消列间距，
            // 容器 overflow:visible 允许其溢出，这是设计权衡而非缺陷（如 #exchangeRateForm 8px）。
            if (el.classList && (
                (el.classList.contains('layui-row') && Array.prototype.some.call(el.classList, function (c) {
                    return /^layui-col-space\d+$/.test(c);
                }))
            )) {
                return;
            }

            // RC-3 去噪：祖先有 overflow:hidden 已裁剪，溢出不可见，检测器报告属连带误报。
            var ancestor = el.parentElement;
            var clippedByAncestor = false;
            while (ancestor && ancestor !== document.body) {
                var ancestorStyle = window.getComputedStyle(ancestor);
                if (ancestorStyle.overflowX === 'hidden') {
                    clippedByAncestor = true;
                    break;
                }
                ancestor = ancestor.parentElement;
            }
            if (clippedByAncestor) {
                return;
            }

            // RC-1 去噪：overflow:visible 元素的溢出若由绝对定位后代超出边界导致（如 layui-table-mend），
            // scrollWidth 虽大但不产生视觉裁剪，属测量假象。
            if (style.overflowX === 'visible') {
                var rect = el.getBoundingClientRect();
                var rightEdge = rect.right;
                var hasAbsExceeder = Array.prototype.slice.call(el.querySelectorAll('*')).some(function (desc) {
                    if (window.getComputedStyle(desc).position !== 'absolute') {
                        return false;
                    }
                    var descRect = desc.getBoundingClientRect();
                    return descRect.right > rightEdge + 1;
                });
                if (hasAbsExceeder) {
                    return;
                }
            }
            report('element-h-overflow', 'medium', describe(el), {
                scrollWidth: el.scrollWidth,
                clientWidth: el.clientWidth,
                overflowPx: el.scrollWidth - el.clientWidth
            });
        }
    });

    // ---- 3. 文本被裁断（长用户名 / 长金额 / 长币种撑破单元格）----
    // 只看叶子文本节点容器：父容器的 scrollWidth 受子元素影响，会产生连带误报。
    // ellipsis 不算缺陷（是设计选择），但「被硬裁断且无 ellipsis」用户会读到半截数字，属缺陷。
    visible.forEach(function (el) {
        if (el.children.length > 0) {
            return;
        }
        var text = (el.textContent || '').trim();
        if (!text) {
            return;
        }
        var style = window.getComputedStyle(el);
        var clipped = el.scrollWidth - el.clientWidth > 1;
        if (!clipped) {
            return;
        }
        var hasEllipsis = style.textOverflow === 'ellipsis' && style.overflowX !== 'visible';
        report(hasEllipsis ? 'text-ellipsis' : 'text-hard-clip', hasEllipsis ? 'info' : 'high', describe(el), {
            text: text.slice(0, 40),
            scrollWidth: el.scrollWidth,
            clientWidth: el.clientWidth,
            textOverflow: style.textOverflow
        });
    });

    // ---- 4. 交互元素重叠 ----
    // 只比对交互元素：普通文本块的视觉重叠多为背景层叠，不影响可用性；
    // 而两个可点元素重叠会直接导致误点，属高危。
    var interactiveSelector = 'a[href], button, input, select, textarea, [role="button"], [onclick], [lay-event]';

    /**
     * 元素是否处于「浮层容器」内（含自身）。
     *
     * 为什么按容器类名判定而不是按祖先 position：layui 的固定列（cols 里的 fixed: 'right'）
     * 会把整个单元格连同按钮在 DOM 里复制一份，放进 position:absolute 的
     * .layui-table-fixed-r 容器。复制出来的按钮**自身仍是 position:static**，
     * 只有祖先是 absolute，所以只检查元素自身会把主体表格里的原按钮与浮层里的
     * 副本判成「编辑 ∩ 编辑」这类自我重叠——纯假阳性。
     *
     * 但「祖先链上出现任一定位元素就跳过」这个判据过宽，会把整页交互元素全部滤掉：
     * 本项目后台是 layui 固定布局，.layui-header.crm-admin-topbar 是 fixed、
     * .layui-nav 是 absolute、侧栏同理，页面骨架本身就是定位元素。
     * 实测 /admin/data-scopes 的 130 个可见交互元素被这条规则清零，
     * 于是 interactive-overlap 整类检测在所有后台页面上静默失效——
     * 报告显示「0 重叠」不是无缺陷，而是压根没比对。
     *
     * 因此改为只认真正的浮层容器：layui 复制体（固定列）、下拉面板、弹层。
     * 布局骨架（header/nav/side/body）不在此列，其中的按钮要正常参与重叠比对。
     *
     * @param {Element} el 目标元素。
     * @returns {boolean} 处于浮层容器内返回 true。
     */
    var OVERLAY_CLASS_PATTERN = /^(layui-table-fixed|layui-table-fixed-l|layui-table-fixed-r|layui-table-tool-panel|layui-form-select|layui-anim|layui-layer|layui-colorpicker|layui-laydate|layui-menu|crm-preference-panel|crm-dropdown-panel)$/;

    function inPositionedOverlay(el) {
        var node = el;
        while (node && node.nodeType === 1 && node !== document.body) {
            if (node.classList && Array.prototype.some.call(node.classList, function (c) {
                return OVERLAY_CLASS_PATTERN.test(c);
            })) {
                return true;
            }
            node = node.parentElement;
        }
        return false;
    }

    /**
     * 元素是否位于「随滚动悬浮」的骨架内（含自身）。
     *
     * 为什么必须排除：sticky/fixed 顶栏与侧栏的职责就是脱离文档流悬停，
     * 页面一旦滚动，它盖住下方内容是**设计意图**而非缺陷。
     * 本项目 .crmui-topbar 为 sticky、.crm-admin-topbar 为 fixed，
     * 滚动后必然与表格行按钮几何相交。
     *
     * 这条判据修掉的是一个成规模的假阳性：编排器为节省导航开销，
     * 在同一次页面加载内循环 15 个主题，而 5 号检测器（对比度）会
     * scrollIntoView 把页面滚到底部且不复位。于是第 2 个主题起，
     * 本检测都在「已滚到底」的残留位置上比对，把悬浮顶栏与表格按钮
     * 判成高危重叠。实测 /admin-crmui/agents：滚动前 0 条、滚到底 5 条，
     * 全量报告因此产出 17295 条 high，其中 light（首个主题，滚动位置 0）
     * 只有 144 条——8 倍落差正是这个 bug 的指纹，而不是主题差异。
     *
     * 与 inPositionedOverlay 分开而不合并进同一个正则：那个函数排除的是
     * 「DOM 副本与浮层面板」，判据是类名白名单；这里排除的是「悬浮骨架」，
     * 判据是计算样式 position。语义不同，合并会让任一侧的边界无法单独调整。
     *
     * @param {Element} el 目标元素。
     * @returns {boolean} 位于悬浮骨架内返回 true。
     */
    function inStickyChrome(el) {
        var node = el;
        while (node && node.nodeType === 1 && node !== document.body) {
            var position = window.getComputedStyle(node).position;
            if (position === 'sticky' || position === 'fixed') {
                return true;
            }
            node = node.parentElement;
        }
        return false;
    }

    // 归零滚动位置后再比对几何。
    // 不归零则本检测的结论取决于「上一个检测器把页面滚到了哪」，
    // 同一页面同一 DOM 会因执行顺序给出不同结果，属不可复现的结论。
    var scroller = document.scrollingElement || document.documentElement;
    var scrollTopBefore = scroller.scrollTop;
    var scrollLeftBefore = scroller.scrollLeft;
    scroller.scrollTop = 0;
    scroller.scrollLeft = 0;

    // 先过滤再两两比对：若放到内层循环里判定，复杂度会是 O(n² × 祖先深度)。
    var interactive = Array.prototype.slice.call(document.querySelectorAll(interactiveSelector))
        .filter(isVisible)
        .filter(function (el) {
            return !inPositionedOverlay(el) && !inStickyChrome(el);
        });
    for (var i = 0; i < interactive.length; i += 1) {
        var a = interactive[i];
        var rectA = a.getBoundingClientRect();
        for (var j = i + 1; j < interactive.length; j += 1) {
            var b = interactive[j];
            // 祖先/后代关系天然「重叠」（如 <a> 内含 <button>），不是缺陷。
            if (a.contains(b) || b.contains(a)) {
                continue;
            }
            var rectB = b.getBoundingClientRect();
            var overlapW = Math.min(rectA.right, rectB.right) - Math.max(rectA.left, rectB.left);
            var overlapH = Math.min(rectA.bottom, rectB.bottom) - Math.max(rectA.top, rectB.top);
            // 阈值 2px：相邻元素常见 1px 边框相接，不算重叠。
            if (overlapW > 2 && overlapH > 2) {
                report('interactive-overlap', 'high', describe(a) + ' ∩ ' + describe(b), {
                    overlapW: Math.round(overlapW),
                    overlapH: Math.round(overlapH)
                });
            }
        }
    }

    // 复原滚动位置：焦点检测与截图都在本检测之后执行，
    // 让页面停在被本检测改过的位置会改变它们看到的状态。
    scroller.scrollTop = scrollTopBefore;
    scroller.scrollLeft = scrollLeftBefore;

    // ---- 5. 实际渲染对比度 ----
    /**
     * 解析计算样式里的颜色为 [r,g,b,a]。计算样式一定是 rgb()/rgba() 形式，
     * 不会出现 hex 或颜色关键字，因此只需处理这两种。
     */
    function parseColor(value) {
        var match = /rgba?\(([^)]+)\)/.exec(value || '');
        if (!match) {
            return null;
        }
        var parts = match[1].split(/[,\s/]+/).filter(function (piece) { return piece !== ''; });
        return [
            parseFloat(parts[0]),
            parseFloat(parts[1]),
            parseFloat(parts[2]),
            parts.length > 3 ? parseFloat(parts[3]) : 1
        ];
    }

    /**
     * 把若干背景层按「自下而上」合成为不透明颜色。
     *
     * @param {Array<Array<number>>} stack 自上而下的背景层（索引 0 最靠上）。
     * @returns {Array<number>} 合成后的 [r,g,b]。
     */
    function compositeLayers(stack) {
        // 兜底为白色：页面根若未声明背景，浏览器按白色渲染。
        var result = [255, 255, 255];
        for (var k = stack.length - 1; k >= 0; k -= 1) {
            var layer = stack[k];
            result = [
                layer[0] * layer[3] + result[0] * (1 - layer[3]),
                layer[1] * layer[3] + result[1] * (1 - layer[3]),
                layer[2] * layer[3] + result[2] * (1 - layer[3])
            ];
        }
        return result;
    }

    /**
     * 从 background-image 里抽出渐变的色标颜色。
     *
     * 为什么必须处理渐变：`getComputedStyle().backgroundColor` 只反映纯色层。
     * CSS 的 `background` 简写一旦写成 `linear-gradient(...)`，background-color
     * 会被重置为 transparent，于是「肉眼看得见的彩色底」在检测器眼里是全透明的，
     * 会被穿透到更下层（通常是白色页面底），把「白字压在蓝色渐变上」
     * 误判成「白字压在白底上」，对比度算成 1.12:1 的高危缺陷。
     * 本项目 7 个 CSS 文件共 80 处渐变声明，不处理会产生成规模的假阳性。
     *
     * 只抽色标、不做插值：渐变各处的颜色都落在色标之间，
     * 由调用方按最坏情况判定（见 worstContrastRatio），
     * 比在这里猜「文字正下方那一点是什么颜色」更严谨也更可复现。
     *
     * @param {string} value background-image 的计算值。
     * @returns {Array<Array<number>>} 色标颜色 [r,g,b,a] 数组；无渐变返回空数组。
     */
    function extractGradientStops(value) {
        if (!value || value === 'none' || value.indexOf('gradient') === -1) {
            return [];
        }
        var stops = [];
        var pattern = /rgba?\([^)]+\)/g;
        var match = pattern.exec(value);
        while (match) {
            var color = parseColor(match[0]);
            if (color) {
                stops.push(color);
            }
            match = pattern.exec(value);
        }
        return stops;
    }

    /**
     * 沿祖先链求背景色（兜底路径）。
     * 元素自身背景常是 transparent，必须逐层上溯才能拿到真正着色的那一层。
     */
    function ancestorBackground(el) {
        var node = el;
        var stack = [];
        while (node && node.nodeType === 1) {
            var bg = parseColor(window.getComputedStyle(node).backgroundColor);
            if (bg && bg[3] > 0) {
                stack.push(bg);
                if (bg[3] >= 1) {
                    break;
                }
            }
            node = node.parentElement;
        }
        return compositeLayers(stack);
    }

    /**
     * 求文字实际压在什么颜色上。
     *
     * 为什么不能只沿祖先链：真正着色的那一层未必是祖先。
     * layui 分页当前页就是 <span><em class=layui-laypage-em(absolute,蓝底)></em><em>1</em></span>，
     * 白色页码压在「绝对定位的兄弟元素」的蓝底上。只上溯祖先会算成
     * 白底上的白字（1.12:1），把合规的 5.21:1 误报成高危缺陷。
     *
     * 改用 elementsFromPoint：它按浏览器真实绘制层序返回该点上的所有元素，
     * 与实际呈现一致，兄弟色块、遮罩、伪层叠都能覆盖到。
     *
     * 边界（已知且刻意接受）：
     * - elementsFromPoint 会排除 pointer-events:none 的元素。纯装饰底衬若设了该属性会被漏掉，
     *   此时退回祖先链结果。漏掉底衬只会让对比度被低估、从而多报一条待人工复核，
     *   不会漏掉真缺陷，方向上是安全的。
     * - 点在视口外时该 API 返回空，故调用方需先把元素滚进视口。
     */
    function backgroundCandidates(el) {
        var rect = el.getBoundingClientRect();
        var x = rect.left + rect.width / 2;
        var y = rect.top + rect.height / 2;

        if (x < 0 || y < 0 || x > window.innerWidth || y > window.innerHeight) {
            return [ancestorBackground(el)];
        }

        var painted = document.elementsFromPoint(x, y);
        if (!painted || !painted.length) {
            return [ancestorBackground(el)];
        }

        // 只有画在本元素之下的层才是它的底色；本元素自身背景也算一层。
        var from = painted.indexOf(el);
        if (from === -1) {
            // 元素不在命中栈里，说明它在该点被别的元素完全遮挡。
            // layui 的固定列（fixed: 'right'）会把整个单元格在 DOM 里复制一份，
            // 压在下面那份对自己中心点做 hit-test 就拿不到自己。
            // 此时若沿整个命中栈从顶往下取，会取到「遮挡层」的底色，
            // 并且完全跳过元素自身的背景——红色 danger 按钮会被算成
            // 白字压近白底（1.05:1）这种成规模的假阳性。
            // 遮挡状态下无法用命中测试还原真实底色，退回祖先链（含元素自身）。
            return [ancestorBackground(el)];
        }
        var layers = painted.slice(from);

        // above 收集「渐变层之上」的半透明纯色层，索引 0 最靠上，供合成时叠加。
        var above = [];
        for (var i = 0; i < layers.length; i += 1) {
            var layerStyle = window.getComputedStyle(layers[i]);

            // 渐变层优先判定：它虽然 backgroundColor 为 transparent，却是真实可见的底色。
            // 只取不透明色标作为候选基色——半透明色标还要与更下层合成，
            // 情况组合过多且本项目不存在这种写法，宁可漏取后退回纯色路径（结论偏保守），
            // 也不引入一条无法复现的近似算法。
            var opaqueStops = extractGradientStops(layerStyle.backgroundImage)
                .filter(function (stop) { return stop[3] >= 0.99; });
            if (opaqueStops.length) {
                return opaqueStops.map(function (stop) {
                    return compositeLayers(above.concat([stop]));
                });
            }

            var bg = parseColor(layerStyle.backgroundColor);
            if (bg && bg[3] > 0) {
                above.push(bg);
                if (bg[3] >= 1) {
                    return [compositeLayers(above)];
                }
            }
        }

        // 命中栈里全透明时退回祖先链：可能是底衬设了 pointer-events:none。
        return [above.length ? compositeLayers(above) : ancestorBackground(el)];
    }

    /**
     * 求单一代表底色。渐变底返回首个色标的合成色，仅用于报告里回显背景值；
     * 判定必须走 backgroundCandidates + worstContrastRatio，否则会漏掉渐变另一端。
     *
     * @param {Element} el 目标元素。
     * @returns {Array<number>} 合成后的 [r,g,b]。
     */
    function effectiveBackground(el) {
        return backgroundCandidates(el)[0];
    }

    /** WCAG 相对亮度。 */
    function luminance(rgb) {
        var channels = rgb.slice(0, 3).map(function (component) {
            var ratio = component / 255;
            return ratio <= 0.03928 ? ratio / 12.92 : Math.pow((ratio + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
    }

    /** WCAG 对比度，返回 1..21。 */
    function contrastRatio(fg, bg) {
        var lighter = Math.max(luminance(fg), luminance(bg));
        var darker = Math.min(luminance(fg), luminance(bg));
        return (lighter + 0.05) / (darker + 0.05);
    }

    /**
     * 在多个候选底色中求最坏对比度。
     *
     * 为什么需要它：渐变底不是单一颜色。backgroundCandidates 返回的是渐变的
     * 各个不透明色标，文字实际压在色标之间的某一点上。只测首个色标会漏掉
     * 「一端合规、另一端不合规」这种真实缺陷。
     *
     * 关键推论：若前景亮度落在相邻两色标亮度之间，则渐变必然经过某一点
     * 使背景亮度等于前景亮度，该点对比度恰为 1.0（完全不可读）。
     * 这不是近似估计，是连续函数的介值定理——渐变亮度沿路径连续变化，
     * 两端分别高于与低于前景亮度，中间必然穿过相等点。
     * 因此这种情况直接判 1.0，而不是取两端的较小值（那会严重低估缺陷）。
     *
     * @param {Array<number>} fg         前景色 [r,g,b]。
     * @param {Array<Array<number>>} candidates 候选底色列表。
     * @returns {number} 最坏对比度（1..21）。
     */
    function worstContrastRatio(fg, candidates) {
        if (!candidates || candidates.length === 0) {
            return 21;
        }
        if (candidates.length === 1) {
            return contrastRatio(fg, candidates[0]);
        }

        var fgLuminance = luminance(fg);
        var luminances = candidates.map(luminance);
        var minLuminance = Math.min.apply(null, luminances);
        var maxLuminance = Math.max.apply(null, luminances);

        // 前景亮度被色标区间夹住 => 渐变途中必有一点与前景等亮度 => 对比度 1.0。
        if (fgLuminance >= minLuminance && fgLuminance <= maxLuminance) {
            return 1;
        }

        var worst = 21;
        for (var i = 0; i < candidates.length; i += 1) {
            var ratio = contrastRatio(fg, candidates[i]);
            if (ratio < worst) {
                worst = ratio;
            }
        }
        return worst;
    }

    /**
     * 对一个承载文字的元素做对比度判定。
     * 大字号阈值 3:1、常规 4.5:1，与 WCAG AA 一致。
     * disabled 元素按 WCAG 可豁免，但本项目 §5.1 明确要求核查其可读性，
     * 因此单独归类为 info 级别上报，供人工决定，不与真缺陷混在一起。
     */
    function checkTextContrast(el, kindPrefix, forceInfo) {
        var style = window.getComputedStyle(el);
        var fg = parseColor(style.color);
        if (!fg || fg[3] === 0) {
            return;
        }
        // candidates 是全部候选底色（渐变有多个色标），bg 只是其中的代表值，
        // 用于报告回显；判定必须走 candidates，否则漏掉渐变另一端。
        var candidates = backgroundCandidates(el);
        var bg = candidates[0];
        // 前景半透明时同样要与背景合成，否则会低估实际对比度差距。
        if (fg[3] < 1) {
            fg = [
                fg[0] * fg[3] + bg[0] * (1 - fg[3]),
                fg[1] * fg[3] + bg[1] * (1 - fg[3]),
                fg[2] * fg[3] + bg[2] * (1 - fg[3])
            ];
        }
        var size = parseFloat(style.fontSize);
        var weight = parseInt(style.fontWeight, 10) || 400;
        var isLarge = size >= 24 || (size >= 18.66 && weight >= 700);
        var required = isLarge ? 3 : 4.5;
        var ratio = worstContrastRatio(fg, candidates);
        if (ratio + 0.01 < required) {
            report(kindPrefix, forceInfo ? 'info' : 'high', describe(el), {
                ratio: Math.round(ratio * 100) / 100,
                required: required,
                color: style.color,
                background: 'rgb(' + bg.map(Math.round).join(',') + ')',
                fontSize: style.fontSize
            });
        }
    }

    /**
     * 元素是否自己拥有非空文本（而不是仅由子元素提供文本）。
     *
     * 判据取「直接子文本节点」：只有拥有文本节点的元素，它的 color 才真正
     * 作用在可见文字上。若按 textContent 判定，容器会连带被测一遍，
     * 得到的是容器自身的 color 而不是文字实际颜色，结论会失真。
     */
    function ownsText(el) {
        for (var i = 0; i < el.childNodes.length; i += 1) {
            var node = el.childNodes[i];
            if (node.nodeType === 3 && node.nodeValue && node.nodeValue.trim() !== '') {
                return true;
            }
        }
        return false;
    }

    // 覆盖率统计：截断必须在报告里可见。
    // 只写「0 命中」而不写「测了多少」，读报告的人无法分辨
    // 「真的没缺陷」与「压根没测到」——后者是最典型的假绿。
    var coverage = {};

    /**
     * 对一批元素做对比度判定，并记录实测量与总量。
     *
     * @param {Array<Element>} elements 候选元素。
     * @param {string} kind 上报种类。
     * @param {boolean} forceInfo 是否强制 info 级。
     * @param {number} cap 单批上限，防止超大表格拖慢单次检测。
     */
    function checkBatch(elements, kind, forceInfo, cap) {
        coverage[kind] = { total: elements.length, measured: Math.min(elements.length, cap) };
        elements.slice(0, cap).forEach(function (el) {
            // 必须先把元素滚进视口再测底色：effectiveBackground 依赖
            // elementsFromPoint 取真实绘制层序，而该 API 对视口外的点返回空，
            // 那时只能退回祖先链，遇到「定位兄弟色块」的写法就会误判。
            // 折叠线以下的元素占多数，不滚动会让这类误判成为常态而非例外。
            var rect = el.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > window.innerHeight
                || rect.right < 0 || rect.left > window.innerWidth) {
                el.scrollIntoView({ block: 'center', inline: 'center' });
            }
            checkTextContrast(el, kind, forceInfo);
        });
    }

    // 5a. disabled 与 aria-disabled 文本
    checkBatch(
        Array.prototype.slice.call(document.querySelectorAll(':disabled, [aria-disabled="true"]'))
            .filter(isVisible),
        'disabled-contrast',
        true,
        80
    );

    // 5b. 表格正文单元格（覆盖斑马纹：奇偶行背景不同，逐个单元格实测即可同时覆盖两种底色）
    checkBatch(
        Array.prototype.slice.call(
            document.querySelectorAll('table tbody td, .layui-table tbody td, .crmui-table tbody td')
        ).filter(isVisible),
        'table-cell-contrast',
        false,
        200
    );

    // 5c. 全部自带文本的可见元素。
    // 这里刻意不用「h1,h2,h3,label,th,…」这类手挑选择器：
    // 手挑名单漏掉了 p / div / span / a / button 等承载绝大多数文字的元素，
    // 导致「对比度 0 命中」这个结论其实建立在「大部分文字没被测量」之上。
    // 改为按渲染特征遍历，覆盖范围才与「正式上线无瑕疵」的判定目标一致。
    checkBatch(
        visible.filter(ownsText),
        'text-contrast',
        false,
        400
    );

    // ---- 6. placeholder 对比度 ----
    // placeholder 拿不到独立的 getComputedStyle，必须读伪元素样式。
    var placeholderTargets = Array.prototype.slice.call(
        document.querySelectorAll('input[placeholder], textarea[placeholder]')
    ).filter(isVisible);
    coverage['placeholder-contrast'] = {
        total: placeholderTargets.length,
        measured: Math.min(placeholderTargets.length, 80)
    };
    placeholderTargets
        .slice(0, 80)
        .forEach(function (el) {
            var pseudo = window.getComputedStyle(el, '::placeholder');
            var fg = parseColor(pseudo.color);
            if (!fg || fg[3] === 0) {
                return;
            }
            // 与正文对比度同一口径：渐变底要按最坏色标判定，bg 仅用于报告回显。
            var candidates = backgroundCandidates(el);
            var bg = candidates[0];
            if (fg[3] < 1) {
                fg = [
                    fg[0] * fg[3] + bg[0] * (1 - fg[3]),
                    fg[1] * fg[3] + bg[1] * (1 - fg[3]),
                    fg[2] * fg[3] + bg[2] * (1 - fg[3])
                ];
            }
            var ratio = worstContrastRatio(fg, candidates);
            if (ratio + 0.01 < 4.5) {
                report('placeholder-contrast', 'medium', describe(el), {
                    ratio: Math.round(ratio * 100) / 100,
                    required: 4.5,
                    color: pseudo.color,
                    background: 'rgb(' + bg.map(Math.round).join(',') + ')'
                });
            }
        });


    // ---- 7. 键盘焦点可见性 ----
    // 前置条件：调用方必须先通过 CDP 发一次真实 Tab 键，把浏览器切到键盘模态，
    // 否则 Chrome 不会对编程式 focus() 匹配 :focus-visible，本检测会全量误报。
    // 判据：聚焦后 outline / box-shadow / border / background 至少一项发生可见变化。
    var previousActive = document.activeElement;
    // 采样上限 300：焦点可见性是键盘可达性的硬判据，采样过少会让「0 命中」
    // 变成没测出来而不是没缺陷。实测 /admin-crmui/agents 单页 interactive=100，
    // 旧上限 25 只覆盖 25%，四分之三的控件从未被聚焦过。
    // 300 的依据：实测 /admin/menus（本项目控件最密的表格页）为 251 个，
    // 300 可全覆盖并留出余量；而单元素成本是「一次 focus + 两次读计算样式」，
    // 远低于对比度检测（text-contrast cap 400，且每个元素都要 scrollIntoView
    // 加 elementsFromPoint 取层序），因此不构成时长风险。
    // 仍保留上限而不是全量：极端超大表格下 O(n) 次 focus 会触发大量样式重算，
    // 留一个明确的天花板比让单页无界增长更可控。
    var focusSample = interactive.slice(0, 300);
    focusSample.forEach(function (el) {
        // 取「聚焦前」快照之前必须先让元素失焦。
        // 编排器为了打开浏览器键盘模态，会在调用本检测器前发一次真实 Tab，
        // 而那次 Tab 恰好聚焦的就是第一个可交互元素。若不先 blur，
        // 该元素的 before 快照本身已是聚焦态，随后 focus() 自然「无变化」，
        // 就会把明明有 2px 焦点环的元素误报成 focus-not-visible——
        // 冒烟验证里 5 个页面各 1 条该误报，全部源于此。
        // blur() 不会退出键盘模态：Chrome 的 :focus-visible 启发式在真实按键后保持粘性，
        // 因此先 blur 再 focus 仍能拿到真实的键盘焦点样式。
        if (document.activeElement === el && typeof el.blur === 'function') {
            el.blur();
        }

        var before = window.getComputedStyle(el);
        var snapshot = {
            outlineWidth: before.outlineWidth,
            outlineStyle: before.outlineStyle,
            outlineColor: before.outlineColor,
            boxShadow: before.boxShadow,
            borderColor: before.borderColor,
            backgroundColor: before.backgroundColor
        };

        try {
            el.focus({ preventScroll: true });
        } catch (error) {
            return;
        }
        if (document.activeElement !== el) {
            return;
        }

        var after = window.getComputedStyle(el);
        var hasOutline = after.outlineStyle !== 'none'
            && parseFloat(after.outlineWidth) > 0
            && (after.outlineWidth !== snapshot.outlineWidth
                || after.outlineStyle !== snapshot.outlineStyle
                || after.outlineColor !== snapshot.outlineColor);
        var changed = hasOutline
            || after.boxShadow !== snapshot.boxShadow
            || after.borderColor !== snapshot.borderColor
            || after.backgroundColor !== snapshot.backgroundColor;

        if (!changed) {
            report('focus-not-visible', 'high', describe(el), {
                outline: after.outline,
                boxShadow: after.boxShadow
            });
        }
    });

    // 复原焦点，避免焦点残留影响后续同页其他检测或截图观感。
    try {
        if (previousActive && previousActive.focus) {
            previousActive.focus({ preventScroll: true });
        } else if (document.activeElement && document.activeElement.blur) {
            document.activeElement.blur();
        }
    } catch (error) {
        // 复原失败不影响已收集的结论，故不上报。
    }

    coverage['focus-not-visible'] = {
        total: interactive.length,
        measured: focusSample.length
    };

    return {
        findings: findings,
        kindCounts: kindCounts,
        coverage: coverage,
        visibleCount: visible.length,
        interactiveCount: interactive.length,
        focusSampled: focusSample.length
    };
}

module.exports = { inPageAudit };
