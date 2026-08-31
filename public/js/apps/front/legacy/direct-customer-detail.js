// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/07/26
// Time: 11:27
/**
 * 旧前台直属客户详情登录历史模块。
 *
 * 业务边界：
 * - 接口地址与 CSRF 令牌只从 Blade 输出的 data 属性读取，脚本不拼接用户 ID。
 * - 请求继续发送旧 WidgetPage 的 page、rows 参数，并消费 rows、total 响应字段。
 * - 请求失败时明确展示中文失败状态，不吞掉网络或响应格式异常。
 *
 * 执行结果：
 * - 成功：安全渲染当前页登录记录并更新上一页、下一页可用状态。
 * - 空数据：显示“暂无登录历史记录”，总数归零且禁用翻页按钮。
 * - 失败：显示“登录历史加载失败”，避免用户把空白表格误认为真实空数据。
 */
(function () {
    'use strict';

    /**
     * 初始化单个登录历史区域。
     *
     * @param {HTMLElement} root Blade 输出的登录历史根节点。
     * @returns {void} 初始化成功后立即加载第一页；节点或配置缺失时保持现有静态状态。
     */
    function initializeHistory(root) {
        if (!root || root.dataset.loginHistoryInitialized === '1') {
            return;
        }

        var endpoint = root.dataset.loginHistoryUrl || '';
        var token = root.dataset.csrfToken || '';
        var tbody = root.querySelector('#login_history_rows');
        var previous = root.querySelector('#login_history_previous');
        var next = root.querySelector('#login_history_next');
        var pageLabel = root.querySelector('#login_history_page');

        if (!endpoint || !tbody || !previous || !next || !pageLabel) {
            return;
        }

        root.dataset.loginHistoryInitialized = '1';

        var currentPage = 1;
        var pageSize = 20;
        var total = 0;

        /**
         * 根据总记录数同步分页按钮和中文页码说明。
         *
         * @returns {void} 页面状态直接写回当前登录历史区域。
         */
        function updatePager() {
            var totalPages = Math.max(1, Math.ceil(total / pageSize));

            previous.disabled = currentPage <= 1;
            next.disabled = currentPage >= totalPages;
            pageLabel.textContent = '第 ' + currentPage + ' 页，共 ' + total + ' 条';
        }

        /**
         * 使用 textContent 安全渲染旧接口返回的登录历史行。
         *
         * @param {Array<Object>} rows 旧接口 rows 数组，每行包含账号、IP 归属地、IP 和时间。
         * @returns {void} 有数据时生成表格行，无数据时生成明确空状态。
         */
        function renderRows(rows) {
            tbody.innerHTML = '';

            if (!rows.length) {
                var emptyRow = document.createElement('tr');
                var emptyCell = document.createElement('td');

                emptyCell.colSpan = 4;
                emptyCell.className = 'legacy-customer-history-empty';
                emptyCell.textContent = '暂无登录历史记录';
                emptyRow.appendChild(emptyCell);
                tbody.appendChild(emptyRow);
                return;
            }

            rows.forEach(function (row) {
                var tr = document.createElement('tr');

                [row.login_id, row.login_id_desc, row.login_ip, row.login_date].forEach(function (value) {
                    var td = document.createElement('td');

                    td.textContent = value || '';
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
        }

        /**
         * 请求指定页并按旧 rows、total 协议更新页面。
         *
         * @param {number} page 需要加载的正整数页码。
         * @returns {void} 异步结果通过表格和分页控件反馈给用户。
         */
        function loadHistory(page) {
            currentPage = page;
            tbody.innerHTML = '<tr><td class="legacy-customer-history-empty" colspan="4">正在加载</td></tr>';
            updatePager();

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': token
                },
                body: 'page=' + encodeURIComponent(currentPage) + '&rows=' + encodeURIComponent(pageSize)
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('login_history_request_failed');
                }

                return response.json();
            }).then(function (payload) {
                total = Number(payload.total || 0);
                renderRows(Array.isArray(payload.rows) ? payload.rows : []);
                updatePager();
            }).catch(function () {
                total = 0;
                tbody.innerHTML = '<tr><td class="legacy-customer-history-empty" colspan="4">登录历史加载失败</td></tr>';
                updatePager();
            });
        }

        previous.addEventListener('click', function () {
            if (currentPage > 1) {
                loadHistory(currentPage - 1);
            }
        });

        next.addEventListener('click', function () {
            if (currentPage * pageSize < total) {
                loadHistory(currentPage + 1);
            }
        });

        loadHistory(currentPage);
    }

    /**
     * 扫描当前文档并初始化所有尚未绑定的登录历史区域。
     *
     * @returns {void} 每个根节点最多初始化一次，防止重复绑定分页事件。
     */
    function initializeAll() {
        document.querySelectorAll('[data-login-history-root]').forEach(initializeHistory);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAll);
        return;
    }

    initializeAll();
}());
