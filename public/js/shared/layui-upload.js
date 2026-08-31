// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/28
// Time: 00:54
/**
 * 共享 Layui 2.13.5 上传组件。
 *
 * 文件职责：
 * - 为前台和后台的所有上传入口提供同一套交互：拖拽区、缩略图预览、上传进度条、移除/重选按钮和友好错误文案。
 * - 只替换交互与视觉，不改变任何后端契约：上传地址、表单字段名、accept 类型和体积上限全部由调用方或 data-* 属性提供。
 * - 支持两种模式：auto 模式直接调用 layui.upload 上传；deferred 模式只做本地预览并缓存 File，由页面自己组装 FormData 提交。
 *
 * 依赖说明：
 * - 需要 layui 2.13.5 的 upload 与 jquery 模块，无构建步骤。
 * - 文案统一走 CrmLang.t()，本文件不硬编码任何中英文业务文案。
 */
(function (window, document) {
    'use strict';

    var instances = {};
    var cachedFiles = {};
    var INITED_FLAG = 'data-crm-upload-inited';

    /**
     * 翻译文案并保留兜底串，避免语言包缺键时把 key 暴露给用户。
     *
     * @param {string} key 语言键。
     * @param {string} fallback 兜底文案。
     * @return {string} 展示文案。
     */
    function t(key, fallback) {
        var value;

        if (window.CrmLang && typeof window.CrmLang.t === 'function') {
            value = window.CrmLang.t(key);
            if (value && value !== key) {
                return value;
            }
        }

        return fallback || key || '';
    }

    /**
     * 把字节数格式化为 KB 或 MB，让用户在提交前判断文件是否过大。
     *
     * @param {number} size 字节数。
     * @return {string} 带单位的体积文案。
     */
    function formatSize(size) {
        if (!size) {
            return '0 KB';
        }
        if (size < 1024 * 1024) {
            return (size / 1024).toFixed(1) + ' KB';
        }

        return (size / 1024 / 1024).toFixed(2) + ' MB';
    }

    /**
     * 读取 data-* 配置并归一化，缺省值与 Layui 默认行为保持一致。
     *
     * @param {Element} block 上传块根节点。
     * @return {Object} 归一化后的配置。
     */
    function readConfig(block) {
        // 体积上限用 data-upload-max-size，避免与既有 Blade 中表示“体积文案节点”的 data-upload-size 冲突。
        var size = parseInt(block.getAttribute('data-upload-max-size') || '0', 10);

        return {
            field: String(block.getAttribute('data-upload-field') || block.getAttribute('data-crm-upload') || ''),
            url: String(block.getAttribute('data-upload-url') || ''),
            name: String(block.getAttribute('data-upload-name-field') || ''),
            accept: String(block.getAttribute('data-upload-accept') || 'images'),
            exts: String(block.getAttribute('data-upload-exts') || ''),
            size: isNaN(size) ? 0 : size,
            multiple: block.getAttribute('data-upload-multiple') === '1',
            auto: block.getAttribute('data-upload-auto') === '1',
            guard: String(block.getAttribute('data-upload-guard') || ''),
            previewOnly: block.getAttribute('data-upload-preview-only') === '1'
        };
    }

    /**
     * 生成友好错误文案：把 Layui 的技术性拒绝原因翻译成用户能直接处理的说明。
     *
     * @param {string} reason 错误类型：exts、size、network、unknown。
     * @param {Object} config 上传配置。
     * @return {string} 错误文案。
     */
    function errorText(reason, config) {
        if (reason === 'exts') {
            return t('front.upload_error_type', 'Unsupported file type: {exts}')
                .replace('{exts}', config.exts || config.accept);
        }
        if (reason === 'size') {
            return t('front.upload_error_size', 'Each file must not exceed {size}')
                .replace('{size}', formatSize((config.size || 0) * 1024));
        }
        if (reason === 'network') {
            return t('front.upload_error_network', 'Upload failed. Please check the network and try again.');
        }

        return t('front.upload_error_unknown', 'Upload failed. Please choose the file again.');
    }

    /**
     * 查询上传块内的子节点，字段限定保证多个上传块互不串状态。
     *
     * @param {Element} block 上传块根节点。
     * @param {string} selector 子节点选择器。
     * @return {?Element} 命中的节点。
     */
    function part(block, selector) {
        return block.querySelector(selector);
    }

    /**
     * 找到展示文件体积的节点。
     *
     * 新组件优先使用 data-upload-size-text；既有 Blade 使用 data-upload-size 表示同一语义，
     * 这里同时兼容两者，保证升级过程中不需要一次性改完所有页面。
     *
     * @param {Element} block 上传块根节点。
     * @param {string} field 上传字段标识。
     * @return {?Element} 体积文案节点。
     */
    function sizeTextNode(block, field) {
        return part(block, '[data-upload-size-text="' + field + '"]')
            || part(block, '[data-upload-size-text]')
            || part(block, '[data-upload-size="' + field + '"]');
    }

    /**
     * 更新进度条显示，percent 为 0-100 的整数。
     *
     * @param {Element} block 上传块根节点。
     * @param {number} percent 进度百分比。
     * @param {boolean} visible 是否显示进度条。
     * @return {void}
     */
    function setProgress(block, percent, visible) {
        var wrap = part(block, '[data-upload-progress]');
        var bar = part(block, '[data-upload-progress-bar]');
        var value = Math.max(0, Math.min(100, Math.round(percent || 0)));

        if (!wrap) {
            return;
        }
        wrap.classList.toggle('is-visible', !!visible);
        wrap.setAttribute('aria-valuenow', String(value));
        if (bar) {
            bar.style.width = value + '%';
        }
    }

    /**
     * 写入状态文案，state 用于区分空态、已选、上传中、成功和失败的视觉样式。
     *
     * @param {Element} block 上传块根节点。
     * @param {string} message 状态文案。
     * @param {string} state 状态标识：empty、chosen、uploading、done、error。
     * @return {void}
     */
    function setStatus(block, message, state) {
        var status = part(block, '[data-upload-status="' + readConfig(block).field + '"]')
            || part(block, '[data-upload-status]');

        if (!status) {
            return;
        }
        status.textContent = message;
        status.classList.remove('has-file', 'is-uploading', 'is-error');
        if (state === 'chosen' || state === 'done') {
            status.classList.add('has-file');
        }
        if (state === 'uploading') {
            status.classList.add('is-uploading');
        }
        if (state === 'error') {
            status.classList.add('is-error');
        }
        if (state === 'empty') {
            status.setAttribute('data-translate', 'front.no_file_selected');
        } else {
            status.removeAttribute('data-translate');
        }
    }

    /**
     * 展示选中文件的名称、体积和缩略图，让用户在提交前确认选对了文件。
     *
     * @param {Element} block 上传块根节点。
     * @param {File} file 选中的文件。
     * @param {string} previewUrl 本地预览地址或服务端返回地址。
     * @return {void}
     */
    function showFile(block, file, previewUrl) {
        var config = readConfig(block);
        var nameNode = part(block, '[data-upload-name="' + config.field + '"]') || part(block, '[data-upload-name]');
        var sizeNode = sizeTextNode(block, config.field);
        var preview = part(block, '[data-upload-preview="' + config.field + '"]') || part(block, '[data-upload-preview]');
        var clearBtn = part(block, '[data-upload-clear="' + config.field + '"]') || part(block, '[data-upload-clear]');

        if (nameNode) {
            nameNode.textContent = (file && file.name) || '-';
        }
        if (sizeNode) {
            sizeNode.textContent = file ? formatSize(file.size || 0) : '-';
        }
        if (preview && previewUrl) {
            preview.setAttribute('src', previewUrl);
            preview.setAttribute('data-image-preview', previewUrl);
            preview.classList.add('is-visible');
            preview.style.display = '';
        }
        if (clearBtn) {
            clearBtn.classList.add('is-visible');
        }
        block.classList.add('has-file');
        setStatus(block, t('front.selected_files', '{count} file(s) selected').replace('{count}', '1'), 'chosen');
        if (window.CrmFieldErrors && window.CrmFieldErrors.clearUpload) {
            window.CrmFieldErrors.clearUpload(document, config.field);
        }
    }

    /**
     * 复位上传块到空态，keepPreview 为 true 时保留当前已保存的图片（例如头像）。
     *
     * @param {Element} block 上传块根节点。
     * @param {boolean=} keepPreview 是否保留缩略图。
     * @return {void}
     */
    function reset(block, keepPreview) {
        var config = readConfig(block);
        var nameNode = part(block, '[data-upload-name="' + config.field + '"]') || part(block, '[data-upload-name]');
        var sizeNode = sizeTextNode(block, config.field);
        var preview = part(block, '[data-upload-preview="' + config.field + '"]') || part(block, '[data-upload-preview]');
        var clearBtn = part(block, '[data-upload-clear="' + config.field + '"]') || part(block, '[data-upload-clear]');

        delete cachedFiles[config.field];
        if (nameNode) {
            nameNode.textContent = '-';
        }
        if (sizeNode) {
            sizeNode.textContent = '-';
        }
        if (clearBtn) {
            clearBtn.classList.remove('is-visible');
        }
        if (preview && !keepPreview) {
            preview.removeAttribute('data-image-preview');
            preview.setAttribute('src', '');
            preview.classList.remove('is-visible');
            preview.style.display = 'none';
        }
        block.classList.remove('has-file');
        setProgress(block, 0, false);
        setStatus(block, t('front.no_file_selected', 'No file selected'), 'empty');
        if (window.CrmFieldErrors && window.CrmFieldErrors.clearUpload) {
            window.CrmFieldErrors.clearUpload(document, config.field);
        }
    }

    /**
     * 展示上传失败：状态文案 + 行内错误 + 清空已缓存文件，避免错误文件被继续提交。
     *
     * @param {Element} block 上传块根节点。
     * @param {string} message 错误文案。
     * @return {void}
     */
    function fail(block, message) {
        var config = readConfig(block);

        reset(block, true);
        setStatus(block, message, 'error');
        if (window.CrmFieldErrors && window.CrmFieldErrors.showUpload) {
            window.CrmFieldErrors.showUpload(closestForm(block) || document.body, config.field, message);
        }
    }

    /**
     * 取上传块所属 form，兼容不支持 Element.closest 的旧内核。
     *
     * @param {Element} node 起始节点。
     * @return {?Element} 所属 form；不在表单内返回 null。
     */
    function closestForm(node) {
        var current = node;

        if (current && current.closest) {
            return current.closest('form');
        }
        while (current && current.nodeType === 1) {
            if (current.tagName === 'FORM') {
                return current;
            }
            current = current.parentNode;
        }

        return null;
    }

    /**
     * 初始化单个上传块：绑定 layui.upload、拖拽区、移除按钮和键盘可达性。
     *
     * @param {Element} block 上传块根节点。
     * @param {Object=} overrides 运行时覆盖项，可提供 onChoose/onDone/onError 回调。
     * @return {void}
     */
    function initBlock(block, overrides) {
        var config = readConfig(block);
        var options = overrides || {};
        var upload = window.layui && window.layui.upload;
        var trigger = part(block, '[data-upload-trigger]')
            || part(block, '.layui-upload-drag')
            || part(block, '.crm-upload-action');
        var clearBtn = part(block, '[data-upload-clear="' + config.field + '"]') || part(block, '[data-upload-clear]');
        var renderOptions;

        if (!upload || !trigger || !config.field || block.getAttribute(INITED_FLAG) === '1') {
            return;
        }
        block.setAttribute(INITED_FLAG, '1');
        reset(block, true);

        renderOptions = {
            elem: trigger,
            // auto=false 时只做本地预览并缓存文件，页面自行组装 FormData，保证原有接口字段名不变。
            auto: config.auto,
            url: config.url || undefined,
            field: config.name || config.field,
            accept: config.accept,
            exts: config.exts || undefined,
            size: config.size || undefined,
            multiple: config.multiple,
            drag: true,
            choose: function (obj) {
                handleChoose(block, config, obj, options);
            },
            before: function () {
                setProgress(block, 0, true);
                setStatus(block, t('front.upload_uploading', 'Uploading...'), 'uploading');
            },
            progress: function (percent) {
                setProgress(block, percent, true);
            },
            done: function (res) {
                setProgress(block, 100, false);
                setStatus(block, t('front.upload_done', 'Upload complete'), 'done');
                if (typeof options.onDone === 'function') {
                    options.onDone(res, block, config);
                }
            },
            error: function () {
                fail(block, errorText('network', config));
                if (typeof options.onError === 'function') {
                    options.onError(block, config);
                }
            },
            allDone: function (obj) {
                if (typeof options.onAllDone === 'function') {
                    options.onAllDone(obj, block, config);
                }
            }
        };

        instances[config.field] = upload.render(renderOptions);

        if (clearBtn) {
            clearBtn.addEventListener('click', function (event) {
                event.preventDefault();
                reset(block, false);
                if (typeof options.onClear === 'function') {
                    options.onClear(block, config);
                }
            });
        }
        trigger.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                trigger.click();
            }
        });
    }

    /**
     * 处理选择文件：只保留最后一次选择的文件，缓存 File 并生成本地预览。
     *
     * @param {Element} block 上传块根节点。
     * @param {Object} config 上传配置。
     * @param {Object} obj layui.upload 的 choose 回调对象。
     * @param {Object} options 运行时覆盖项。
     * @return {void}
     */
    function handleChoose(block, config, obj, options) {
        var files = obj.pushFile();
        var keys = Object.keys(files);
        var latestKey = keys.length ? keys[keys.length - 1] : '';
        var file = latestKey ? files[latestKey] : null;
        var validation;

        if (!file) {
            return;
        }
        if (!config.multiple) {
            keys.forEach(function (key) {
                if (key !== latestKey) {
                    delete files[key];
                }
            });
        }

        validation = validateFile(file, config);
        if (validation) {
            delete files[latestKey];
            fail(block, validation);

            return;
        }

        cachedFiles[config.field] = file;
        obj.preview(function (_index, selectedFile, result) {
            showFile(block, selectedFile || file, result);
            if (typeof options.onChoose === 'function') {
                options.onChoose(selectedFile || file, block, config, obj);
            }
        });
    }

    /**
     * 本地二次校验扩展名与体积，保证 auto=false 时也能给出与后端一致的拒绝理由。
     *
     * @param {File} file 选中的文件。
     * @param {Object} config 上传配置。
     * @return {string} 空字符串表示通过。
     */
    function validateFile(file, config) {
        var name = String((file && file.name) || '');
        var extension = name.indexOf('.') >= 0 ? name.split('.').pop().toLowerCase() : '';
        var allowed = config.exts ? config.exts.toLowerCase().split('|') : [];

        if (allowed.length && allowed.indexOf(extension) < 0) {
            return errorText('exts', config);
        }
        if (config.size && file.size > config.size * 1024) {
            return errorText('size', config);
        }

        return '';
    }

    /**
     * 批量初始化页面内所有共享上传块。
     *
     * @param {(string|Element|Document)=} scope 查找范围，默认整个文档。
     * @param {Object=} overrides 运行时覆盖项。
     * @return {void}
     */
    function init(scope, overrides) {
        var root = scope || document;
        var blocks;
        var i;

        if (typeof root === 'string') {
            root = document.querySelector(root) || document;
        }
        blocks = root.querySelectorAll('[data-crm-upload]');
        for (i = 0; i < blocks.length; i++) {
            initBlock(blocks[i], overrides);
        }
    }

    /**
     * 读取某个字段当前缓存的 File，供页面组装 FormData 时使用。
     *
     * @param {string} field 上传字段标识。
     * @return {?File} 已选文件；未选择返回 null。
     */
    function file(field) {
        return cachedFiles[field] || null;
    }

    /**
     * 判断字段是否已经选择文件。
     *
     * @param {string} field 上传字段标识。
     * @return {boolean} true 表示已选文件。
     */
    function has(field) {
        return !!cachedFiles[field];
    }

    /**
     * 手动写入缓存文件，供页面复用已有选择逻辑时同步状态。
     *
     * @param {string} field 上传字段标识。
     * @param {?File} value 文件对象；传 null 表示清除。
     * @return {void}
     */
    function setFile(field, value) {
        if (value) {
            cachedFiles[field] = value;

            return;
        }
        delete cachedFiles[field];
    }

    /**
     * 按字段复位上传块，供表单提交成功后清理界面与缓存。
     *
     * @param {(string|Array<string>)} fields 单个或多个上传字段标识。
     * @param {boolean=} keepPreview 是否保留缩略图。
     * @return {void}
     */
    function clear(fields, keepPreview) {
        var list = Object.prototype.toString.call(fields) === '[object Array]' ? fields : [fields];

        list.forEach(function (field) {
            var block = document.querySelector('[data-crm-upload="' + field + '"]')
                || document.querySelector('[data-upload-field="' + field + '"]');

            delete cachedFiles[field];
            if (block) {
                reset(block, keepPreview);
            }
        });
    }

    window.CrmUpload = {
        init: init,
        initBlock: initBlock,
        file: file,
        has: has,
        setFile: setFile,
        clear: clear,
        reset: reset,
        fail: fail,
        formatSize: formatSize,
        setProgress: setProgress,
        setStatus: setStatus,
        showFile: showFile,
        instances: instances
    };
}(window, document));
