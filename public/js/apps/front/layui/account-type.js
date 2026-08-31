// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/18
// Time: 16:42
/**
 * 前台交易账户类型切换交互。
 *
 * 文件功能：
 * - 消费 module-page.js 已加载的账户资料，不重复请求账户资料接口。
 * - 根据 is_ecn、equity 和 ecn_minimum_equity 渲染当前类型、唯一可选目标和资格状态。
 * - 提交旧兼容字段 is_enc，并把旧错误码转换为可读中文或英文提示。
 * - 成功后先更新已确认的本地视图，再发送刷新事件读取服务端最终状态。
 *
 * 入参示例：crm:module-page-loaded.detail.summary={is_ecn:0,equity:3500,ecn_minimum_equity:3000}。
 * 返回结果：成功时页面切到新的 STP/ECN 状态；失败时保留原状态并展示具体原因。
 */
(function () {
    'use strict';

    var layer = window.layui && window.layui.layer ? window.layui.layer : null;
    var formElement = document.getElementById('accountTypeSwitch');
    var moduleElement = document.getElementById('frontModulePage')
        || document.querySelector('.crmui-page[data-crmui-page="front.account_info"]');

    if (!formElement || !moduleElement) {
        return;
    }

    var currentTypeElement = document.getElementById('accountTypeCurrent');
    var equityElement = document.getElementById('accountTypeEquity');
    var minimumEquityElement = document.getElementById('accountTypeMinimumEquity');
    var statusElement = document.getElementById('accountTypeStatus');
    var statusIconElement = document.getElementById('accountTypeStatusIcon');
    var statusTextElement = document.getElementById('accountTypeStatusText');
    var submitElement = document.getElementById('accountTypeSubmit');
    var submitTextElement = document.getElementById('accountTypeSubmitText');
    var optionElements = formElement.querySelectorAll('[data-account-type]');
    var changeApi = formElement.getAttribute('data-change-api') || '';
    var changeMethod = formElement.getAttribute('data-change-method') || 'POST';
    var fallbackMinimumEquity = numberValue(formElement.getAttribute('data-minimum-ecn-equity'), 3000);
    var accountData = null;
    var currentType = 0;
    var targetType = 1;
    var equity = 0;
    var minimumEquity = fallbackMinimumEquity;
    var submitting = false;

    /**
     * 读取当前运行时语言文案。
     *
     * @param {string} key 完整语言键。
     * @return {string} 当前语言文案；语言工具不可用时返回键名。
     */
    function t(key) {
        return window.CrmLang && typeof window.CrmLang.t === 'function'
            ? window.CrmLang.t(key)
            : key;
    }

    function notify(messageKey, icon) {
        if (layer && typeof layer.msg === 'function') {
            layer.msg(t(messageKey), {icon: icon});
        }
    }

    /**
     * 把接口值安全转换为有限数字。
     *
     * @param {*} value 接口或 data 属性中的原始值。
     * @param {number} fallback 转换失败时使用的明确兜底值。
     * @return {number} 可参与净值资格比较的数字。
     */
    function numberValue(value, fallback) {
        var parsed = Number(value);

        return isFinite(parsed) ? parsed : fallback;
    }

    /**
     * 按当前语言格式化美元金额。
     *
     * @param {number} value 金额数字。
     * @return {string} 带美元币种和两位小数的展示值。
     */
    function formatMoney(value) {
        var locale = window.CrmLang && window.CrmLang.getLocale
            ? window.CrmLang.getLocale()
            : 'zh-CN';

        try {
            return new Intl.NumberFormat(locale === 'en' ? 'en-US' : locale, {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        } catch (error) {
            return '$' + numberValue(value, 0).toFixed(2);
        }
    }

    /**
     * 返回账户类型展示名。
     *
     * @param {number} type 0=STP，1=ECN。
     * @return {string} 当前语言下的账户类型名称。
     */
    function accountTypeName(type) {
        return t(type === 1 ? 'front.ecn_account' : 'front.stp_account');
    }

    /**
     * 更新资格状态条及 Lucide 图标。
     *
     * @param {string} state loading、ready 或 error。
     * @param {string} messageKey 需要展示的语言键。
     * @return {void}
     */
    function setStatus(state, messageKey) {
        var iconName = state === 'ready' ? 'circle-check' : (state === 'error' ? 'circle-alert' : 'loader-circle');

        statusElement.className = 'account-type-status is-' + state;
        statusTextElement.textContent = t(messageKey);
        statusIconElement.innerHTML = '<i data-lucide="' + iconName + '" aria-hidden="true"></i>';
        if (window.CrmIcons && typeof window.CrmIcons.refresh === 'function') {
            window.CrmIcons.refresh(statusIconElement);
        }
    }

    /**
     * 按最新资料重绘当前类型、目标类型和提交资格。
     *
     * 当前账户单选项始终禁用，另一项是唯一目标；切换 ECN 时净值低于门槛会同时禁用目标和提交。
     *
     * @return {void}
     */
    function renderAccountType() {
        var canSubmit;

        if (!accountData) {
            return;
        }

        currentType = Number(accountData.is_ecn) === 1 ? 1 : 0;
        targetType = currentType === 1 ? 0 : 1;
        equity = numberValue(accountData.equity, 0);
        minimumEquity = numberValue(accountData.ecn_minimum_equity, fallbackMinimumEquity);
        canSubmit = targetType !== 1 || equity >= minimumEquity;

        currentTypeElement.textContent = accountTypeName(currentType);
        equityElement.textContent = formatMoney(equity);
        minimumEquityElement.textContent = formatMoney(minimumEquity);

        Array.prototype.forEach.call(optionElements, function (optionElement) {
            var type = Number(optionElement.getAttribute('data-account-type')) === 1 ? 1 : 0;
            var input = optionElement.querySelector('input[name="is_enc"]');
            var isUnavailable = type === 1 && !canSubmit;

            optionElement.classList.toggle('is-current', type === currentType);
            optionElement.classList.toggle('is-target', type === targetType);
            optionElement.classList.toggle('is-selected', type === targetType);
            optionElement.classList.toggle('is-unavailable', isUnavailable);
            if (input) {
                input.checked = type === targetType;
                input.disabled = submitting || type === currentType || (type === 1 && !canSubmit);
            }
        });

        formElement.setAttribute('aria-busy', submitting ? 'true' : 'false');
        submitElement.disabled = submitting || !canSubmit;
        submitElement.classList.toggle('is-loading', submitting);
        submitTextElement.textContent = t(submitting
            ? 'front.account_type_submitting'
            : 'front.confirm_account_type_change');

        if (submitting) {
            setStatus('loading', 'front.account_type_submitting');
        } else if (!canSubmit) {
            setStatus('error', 'front.account_type_error_ecn_equity');
        } else {
            setStatus('ready', 'front.account_type_qualification_ready');
        }
    }

    /**
     * 切换提交中的只读状态，阻止重复请求和目标类型变化。
     *
     * @param {boolean} active true 表示正在提交。
     * @return {void}
     */
    function setSubmitting(active) {
        submitting = active;
        renderAccountType();
    }

    /**
     * 把旧接口错误码映射为用户可理解的失败原因。
     *
     * @param {string} errorCode 旧接口 err 字段。
     * @return {string} 前端多语言键。
     */
    function errorMessageKey(errorCode) {
        var errorMap = {
            ECNMINBALANCE: 'front.account_type_error_ecn_equity',
            ERRVOL: 'front.account_type_error_open_orders',
            MT4OHTERUPDFAIL: 'front.account_type_error_remote',
            relationGroupNotExit: 'front.account_type_error_group',
            UPDATEFAIL: 'front.account_type_error_update',
            userNotFound: 'front.account_type_error_auth'
        };

        return errorMap[String(errorCode || '')] || 'front.account_type_error_unknown';
    }

    /**
     * 展示提交失败，并恢复当前服务端已确认状态。
     *
     * @param {string} errorCode 旧接口 err 字段。
     * @return {void}
     */
    function showSubmitError(errorCode) {
        var messageKey = errorMessageKey(errorCode);

        setSubmitting(false);
        setStatus('error', messageKey);
        notify(messageKey, 2);
    }

    /**
     * 请求通用模块重新加载资料。
     *
     * @return {void}
     */
    function requestModuleReload() {
        var event;

        if (typeof window.CustomEvent === 'function') {
            moduleElement.dispatchEvent(new window.CustomEvent('crm:module-page-reload'));
            return;
        }

        event = document.createEvent('Event');
        event.initEvent('crm:module-page-reload', false, false);
        moduleElement.dispatchEvent(event);
    }

    moduleElement.addEventListener('crm:module-page-loaded', function (event) {
        var detail = event.detail || {};

        accountData = detail.summary || {};
        renderAccountType();
    });

    formElement.addEventListener('submit', function (event) {
        var csrfInput;
        var headers = {};
        var payload;

        event.preventDefault();
        if (!accountData || submitting) {
            return;
        }
        if (targetType === 1 && equity < minimumEquity) {
            showSubmitError('ECNMINBALANCE');
            return;
        }
        if (!changeApi || !window.CrmAjax || typeof window.CrmAjax.request !== 'function') {
            showSubmitError('UPDATEFAIL');
            return;
        }

        csrfInput = formElement.querySelector('input[name="_token"]');
        if (csrfInput && csrfInput.value) {
            headers['X-CSRF-TOKEN'] = csrfInput.value;
        }

        payload = String(changeMethod).toUpperCase() === 'PATCH'
            ? {is_ecn: targetType}
            : {is_enc: targetType};
        setSubmitting(true);
        window.CrmAjax.request({
            guard: 'front',
            method: changeMethod,
            url: changeApi,
            headers: headers,
            mask: false,
            data: payload,
            success: function (response) {
                if (!response || String(response.msg || '').toUpperCase() !== 'SUCCESS') {
                    showSubmitError(response && response.err);
                    return;
                }

                // 接口已确认 MT4 与本地事务成功，先更新账户类型区，再由统一加载器校准全部账户指标。
                accountData.is_ecn = targetType;
                accountData.leverage = targetType === 1 ? 200 : 100;
                submitting = false;
                renderAccountType();
                notify('front.account_type_change_success', 1);
                requestModuleReload();
            },
            error: function () {
                showSubmitError('MT4OHTERUPDFAIL');
            }
        });
    });
})();
