// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/05/31
// Time: 22:56
/**
 * 旧版页面兼容 API 工具。
 * 提供 CRM.lang、CRM.use 和基础 GET/POST 封装，保证迁移过程中旧脚本还能按原方式调用。
 */
var CRM = CRM || {};

(function() {
    // 按点路径读取旧语言包，例如 common.submit；找不到时返回 key 方便发现缺失项。
    CRM.lang = function(key) {
        var parts = key.split('.');
        var data = CRM.lang_data;
        for (var i = 0; i < parts.length; i++) {
            if (data[parts[i]]) {
                data = data[parts[i]];
            } else {
                return key;
            }
        }
        return data;
    };

    // 保留旧项目的模块加载入口，新项目已静态引入依赖，所以这里只做兼容回调。
    CRM.use = function(modules, callback) {
        // 简单兼容旧模块加载器，避免旧页面因缺少 loader 中断。
        if (typeof modules === 'string') modules = [modules];
        callback();
    };
})();

// 基础 Ajax 封装，自动带上 CSRF、语言和 XHR 头，供旧式页面直接调用。
var API = {
    baseUrl: '',
    // 每次请求实时读取 meta 和 html lang，确保切换语言或刷新 token 后立即生效。
    headers: function() {
        var token = $('meta[name="csrf-token"]').attr('content') || '';
        var locale = $('html').attr('lang') || 'en';
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept-Language': locale
        };
    },
    // GET 请求只负责读取数据，不做业务成功码判断，交给调用方处理。
    get: function(url) {
        return $.ajax({
            url: url,
            type: 'GET',
            headers: this.headers(),
            dataType: 'json'
        });
    },
    // POST 请求默认提交 JSON，兼容旧项目接口的 application/json 读取方式。
    post: function(url, data) {
        return $.ajax({
            url: url,
            type: 'POST',
            headers: this.headers(),
            data: JSON.stringify(data || {}),
            dataType: 'json'
        });
    }
};
