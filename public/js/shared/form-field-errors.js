// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/28
// Time: 00:38
/**
 * 共享表单字段级校验提示组件。
 *
 * 文件职责：
 * - 让每个提交按钮只校验它自己所属 form 内的必填字段，不跨表单误报。
 * - 把失败原因写在出错字段旁边的行内提示位，而不是页面中央的全局 toast。
 * - 同步 aria-invalid、aria-describedby、focus 与滚动到可视区域，保证键盘和读屏用户能定位到真正出错的输入框。
 *
 * 依赖说明：
 * - 纯原生 ES5 实现，不依赖构建步骤；jQuery 存在时可直接传入 jQuery 对象，内部会取原生节点。
 * - 文案统一走 CrmLang.t()，缺失语言包时回退到调用方给出的兜底串，不在本文件硬编码中英文业务文案。
 */
(function (window, document) {
    'use strict';

    var ERROR_SLOT_SELECTOR = '[data-error-for]';
    var ERROR_CLASS = 'crm-field-error';
    var INVALID_CLASS = 'layui-form-danger';
    var CONTROL_SELECTOR = 'input[name], select[name], textarea[name]';
    var BOUND_FLAG = 'data-crm-field-errors-bound';

    /**
     * 取原生 DOM 节点，允许传入选择器、jQuery 对象或原生节点。
     *
     * @param {(string|Element|Object)} target 目标表单或节点。
     * @return {?Element} 原生节点；解析失败返回 null。
     */
    function toElement(target) {
        if (!target) {
            return null;
        }
        if (typeof target === 'string') {
            return document.querySelector(target);
        }
        if (target.nodeType === 1) {
            return target;
        }
        if (target.length && target[0] && target[0].nodeType === 1) {
            return target[0];
        }

        return null;
    }

    /**
     * 翻译文案并保留兜底串，避免语言包缺键时把 key 直接暴露给用户。
     *
     * @param {string} key 语言键。
     * @param {string} fallback 语言包缺失时的兜底文案。
     * @return {string} 最终展示文案。
     */
    function translate(key, fallback) {
        var value;

        if (!key) {
            return fallback || '';
        }
        if (window.CrmLang && typeof window.CrmLang.t === 'function') {
            value = window.CrmLang.t(key);
            if (value && value !== key) {
                return value;
            }
        }

        return fallback || '';
    }

    /**
     * 读取字段的可读标签，优先取同一个表单项内的 label 文本。
     *
     * @param {Element} control 表单控件。
     * @return {string} 字段标签；无标签时回退到 name。
     */
    function fieldLabel(control) {
        var item = closest(control, '.layui-form-item');
        var label = item ? item.querySelector('label') : null;
        var text = label ? String(label.textContent || '').replace(/\s+/g, ' ').trim() : '';

        return text || String(control.getAttribute('name') || '');
    }

    /**
     * 向上查找最近的匹配祖先，兼容不支持 Element.closest 的旧内核。
     *
     * @param {Element} node 起始节点。
     * @param {string} selector CSS 选择器。
     * @return {?Element} 命中的祖先节点；未命中返回 null。
     */
    function closest(node, selector) {
        var current = node;

        if (current && current.closest) {
            return current.closest(selector);
        }
        while (current && current.nodeType === 1) {
            if (matches(current, selector)) {
                return current;
            }
            current = current.parentNode;
        }

        return null;
    }

    /**
     * 选择器匹配，兼容旧前缀实现。
     *
     * @param {Element} node 待匹配节点。
     * @param {string} selector CSS 选择器。
     * @return {boolean} 匹配结果。
     */
    function matches(node, selector) {
        var fn = node.matches || node.msMatchesSelector || node.webkitMatchesSelector;

        return fn ? fn.call(node, selector) : false;
    }

    /**
     * 判断控件是否参与本次必填校验。
     *
     * 只有 required 或 lay-verify 中声明了 required 语义的可见控件才会被校验，
     * disabled、readonly、hidden 与 layui-hide 控件一律跳过，避免只读展示字段被误判为必填。
     *
     * @param {Element} control 表单控件。
     * @return {boolean} true 表示需要校验。
     */
    function isRequiredControl(control) {
        var verify = String(control.getAttribute('lay-verify') || '');
        var declared = control.hasAttribute('required') || /(^|\|)(required|profileRequired)(\||$)/.test(verify);

        if (!declared || control.disabled || control.readOnly) {
            return false;
        }
        if (control.type === 'hidden' || closest(control, '.layui-hide')) {
            return false;
        }

        return !!(control.offsetParent || control.offsetWidth || control.offsetHeight);
    }

    /**
     * 取控件当前值的去空格结果，radio/checkbox 组按“是否有勾选”判断。
     *
     * @param {Element} form 所属表单。
     * @param {Element} control 表单控件。
     * @return {string} 有值返回非空字符串。
     */
    function controlValue(form, control) {
        var name;
        var group;
        var i;

        if (control.type === 'radio' || control.type === 'checkbox') {
            name = String(control.getAttribute('name') || '');
            group = name ? form.querySelectorAll('[name="' + name + '"]') : [];
            for (i = 0; i < group.length; i++) {
                if (group[i].checked) {
                    return String(group[i].value || 'on');
                }
            }

            return '';
        }

        return String(control.value == null ? '' : control.value).trim();
    }

    /**
     * 为字段准备行内错误提示位，并建立无障碍关联。
     *
     * 提示位优先复用页面已声明的 [data-error-for]；没有声明时在字段容器末尾动态补一个，
     * 这样既兼容已有 Blade，又不要求每个表单都手写提示节点。
     *
     * @param {Element} form 所属表单。
     * @param {string} field 字段 name。
     * @return {?Element} 错误提示节点。
     */
    function ensureSlot(form, field) {
        var slot = form.querySelector('[data-error-for="' + field + '"]');
        var control = form.querySelector('[name="' + field + '"]');
        var host;
        var slotId;

        if (!slot) {
            if (!control) {
                return null;
            }
            host = closest(control, '.layui-input-block')
                || closest(control, '.layui-form-item')
                || control.parentNode;
            if (!host) {
                return null;
            }
            slot = document.createElement('p');
            slot.setAttribute('data-error-for', field);
            slot.className = ERROR_CLASS;
            host.appendChild(slot);
        }

        slot.classList.add(ERROR_CLASS);
        slotId = slot.getAttribute('id') || ('crm-field-error-' + field + '-' + Math.random().toString(36).slice(2, 8));
        slot.setAttribute('id', slotId);
        slot.setAttribute('role', 'alert');
        slot.setAttribute('aria-live', 'assertive');
        if (control) {
            control.setAttribute('aria-describedby', slotId);
        }

        return slot;
    }

    /**
     * 清空指定表单的全部字段级错误状态。
     *
     * @param {(string|Element|Object)} target 表单选择器或节点。
     * @return {void}
     */
    function clear(target) {
        var form = toElement(target);
        var slots;
        var invalids;
        var i;

        if (!form) {
            return;
        }
        slots = form.querySelectorAll(ERROR_SLOT_SELECTOR);
        for (i = 0; i < slots.length; i++) {
            slots[i].textContent = '';
            slots[i].classList.remove('is-visible');
        }
        invalids = form.querySelectorAll('[aria-invalid="true"]');
        for (i = 0; i < invalids.length; i++) {
            invalids[i].removeAttribute('aria-invalid');
            invalids[i].classList.remove(INVALID_CLASS);
        }
    }

    /**
     * 清空单个字段的错误状态，供用户重新输入时即时消除旧提示。
     *
     * @param {(string|Element|Object)} target 表单选择器或节点。
     * @param {string} field 字段 name。
     * @return {void}
     */
    function clearField(target, field) {
        var form = toElement(target);
        var slot;
        var control;

        if (!form || !field) {
            return;
        }
        slot = form.querySelector('[data-error-for="' + field + '"]');
        control = form.querySelector('[name="' + field + '"]');
        if (slot) {
            slot.textContent = '';
            slot.classList.remove('is-visible');
        }
        if (control) {
            control.removeAttribute('aria-invalid');
            control.classList.remove(INVALID_CLASS);
        }
    }

    /**
     * 把错误锚定到具体字段：行内文案 + aria-invalid + 聚焦 + 滚动到可视区域。
     *
     * @param {(string|Element|Object)} target 表单选择器或节点。
     * @param {string} field 字段 name 或上传字段标识。
     * @param {string} message 已翻译的错误文案。
     * @return {boolean} true 表示已成功锚定到字段。
     */
    function show(target, field, message) {
        var form = toElement(target);
        var slot;
        var control;
        var anchor;

        if (!form || !field || !message) {
            return false;
        }
        slot = ensureSlot(form, field);
        control = form.querySelector('[name="' + field + '"]');
        if (!slot) {
            return false;
        }

        slot.textContent = message;
        slot.classList.add('is-visible');
        if (control) {
            control.setAttribute('aria-invalid', 'true');
            control.classList.add(INVALID_CLASS);
        }

        anchor = control || slot;
        scrollIntoView(anchor);
        focusControl(control);

        return true;
    }

    /**
     * 把错误锚定到上传控件：复用 [data-upload-field] 容器内的触发按钮作为聚焦目标。
     *
     * @param {(string|Element|Object)} target 表单选择器或节点。
     * @param {string} field 上传字段标识，对应 data-upload-field。
     * @param {string} message 已翻译的错误文案。
     * @return {boolean} true 表示已成功锚定到上传块。
     */
    function showUpload(target, field, message) {
        var form = toElement(target);
        var block;
        var slot;
        var trigger;

        if (!form || !field || !message) {
            return false;
        }
        block = form.querySelector('[data-upload-field="' + field + '"]');
        if (!block) {
            return show(form, field, message);
        }

        slot = block.querySelector('[data-error-for="' + field + '"]');
        if (!slot) {
            slot = document.createElement('p');
            slot.setAttribute('data-error-for', field);
            block.appendChild(slot);
        }
        slot.className = ERROR_CLASS + ' is-visible';
        slot.setAttribute('role', 'alert');
        slot.setAttribute('aria-live', 'assertive');
        slot.textContent = message;

        block.setAttribute('aria-invalid', 'true');
        trigger = block.querySelector('.layui-upload-drag, button, [tabindex]');
        scrollIntoView(trigger || block);
        focusControl(trigger);

        return true;
    }

    /**
     * 滚动到出错节点，优先使用平滑滚动并保持节点居中。
     *
     * @param {?Element} node 目标节点。
     * @return {void}
     */
    function scrollIntoView(node) {
        if (!node || typeof node.scrollIntoView !== 'function') {
            return;
        }
        try {
            node.scrollIntoView({behavior: 'smooth', block: 'center', inline: 'nearest'});
        } catch (error) {
            node.scrollIntoView();
        }
    }

    /**
     * 聚焦出错控件；隐藏原生控件（如 layui 美化后的 select/radio）时聚焦其可见替身。
     *
     * @param {?Element} control 表单控件。
     * @return {void}
     */
    function focusControl(control) {
        var visible;

        if (!control) {
            return;
        }
        visible = control;
        if (control.tagName === 'SELECT' || control.type === 'radio' || control.type === 'checkbox') {
            visible = (control.nextElementSibling && control.nextElementSibling.querySelector('input'))
                || control.nextElementSibling
                || control;
        }
        window.setTimeout(function () {
            try {
                visible.focus({preventScroll: true});
            } catch (error) {
                visible.focus();
            }
        }, 10);
    }

    /**
     * 只校验指定表单自身的必填字段与必传上传项，并把第一个错误锚定到对应控件。
     *
     * 校验按 DOM 顺序推进，因此提示总是落在用户视觉上第一个未填写的字段，
     * 与点击的提交按钮所属表单严格一一对应，不会把其他卡片的字段算进来。
     *
     * @param {(string|Element|Object)} target 表单选择器或节点。
     * @param {Object=} options 可选项：uploads 为 {字段: 语言键} 映射，hasUpload 为判断上传是否已选文件的回调，
     *                          messageFor 为自定义文案生成器 (label, control) => string。
     * @return {boolean} true 表示该表单全部必填项已满足。
     */
    function validateRequired(target, options) {
        var form = toElement(target);
        var settings = options || {};
        var controls;
        var control;
        var seen = {};
        var name;
        var i;
        var uploadField;

        if (!form) {
            return true;
        }
        clear(form);

        controls = form.querySelectorAll(CONTROL_SELECTOR);
        for (i = 0; i < controls.length; i++) {
            control = controls[i];
            name = String(control.getAttribute('name') || '');
            if (!name || seen[name] || !isRequiredControl(control)) {
                continue;
            }
            seen[name] = true;
            if (controlValue(form, control) === '') {
                show(form, name, requiredMessage(form, control, settings));

                return false;
            }
        }

        for (uploadField in (settings.uploads || {})) {
            if (!Object.prototype.hasOwnProperty.call(settings.uploads, uploadField)) {
                continue;
            }
            if (typeof settings.hasUpload === 'function' && settings.hasUpload(uploadField)) {
                continue;
            }
            showUpload(form, uploadField, uploadMessage(form, settings.uploads[uploadField], settings));

            return false;
        }

        return true;
    }

    /**
     * 生成必填字段的提示文案。
     *
     * @param {Element} form 所属表单。
     * @param {Element} control 出错控件。
     * @param {Object} settings validateRequired 的可选项。
     * @return {string} 提示文案。
     */
    function requiredMessage(form, control, settings) {
        var label = fieldLabel(control);

        if (typeof settings.messageFor === 'function') {
            return settings.messageFor(label, control);
        }

        return translate('front.field_required_message', label + ' is required').replace('{field}', label);
    }

    /**
     * 生成必传上传项的提示文案。
     *
     * @param {Element} form 所属表单。
     * @param {string} labelKey 上传项语言键。
     * @param {Object} settings validateRequired 的可选项。
     * @return {string} 提示文案。
     */
    function uploadMessage(form, labelKey, settings) {
        var label = translate(labelKey, labelKey);

        if (typeof settings.uploadMessageFor === 'function') {
            return settings.uploadMessageFor(label, labelKey);
        }

        return translate('front.upload_required_message', 'Please upload: {field}').replace('{field}', label);
    }

    /**
     * 绑定输入即清错：用户开始修正时立刻移除旧提示，避免过期错误停留在界面上。
     *
     * @param {(string|Element|Object)} target 表单选择器或节点。
     * @return {void}
     */
    function bindAutoClear(target) {
        var form = toElement(target);

        if (!form || form.getAttribute(BOUND_FLAG) === '1') {
            return;
        }
        form.setAttribute(BOUND_FLAG, '1');
        ['input', 'change'].forEach(function (eventName) {
            form.addEventListener(eventName, function (event) {
                var control = event.target;
                var name = control && control.getAttribute ? control.getAttribute('name') : '';

                if (name) {
                    clearField(form, name);
                }
            }, true);
        });
    }

    /**
     * 清空上传块的错误状态，供重新选择文件后调用。
     *
     * @param {(string|Element|Object)} target 表单或页面根节点。
     * @param {string} field 上传字段标识。
     * @return {void}
     */
    function clearUpload(target, field) {
        var root = toElement(target) || document;
        var block = root.querySelector ? root.querySelector('[data-upload-field="' + field + '"]') : null;
        var slot;

        if (!block) {
            return;
        }
        block.removeAttribute('aria-invalid');
        slot = block.querySelector('[data-error-for="' + field + '"]');
        if (slot) {
            slot.textContent = '';
            slot.classList.remove('is-visible');
        }
    }

    window.CrmFieldErrors = {
        clear: clear,
        clearField: clearField,
        clearUpload: clearUpload,
        show: show,
        showUpload: showUpload,
        validateRequired: validateRequired,
        bindAutoClear: bindAutoClear,
        ensureSlot: ensureSlot
    };
}(window, document));
