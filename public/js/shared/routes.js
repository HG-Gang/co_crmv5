// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/07/04
// Time: 18:01
/**
 * 前端全局路由助手。
 *
 * 路由清单由 PHP 从 Laravel 已注册路由表导出到 window.CRM_ROUTES。
 * 页面跳转可以通过路由名称取地址；后端 API 必须在业务 JS 中保留清晰的 /api/... 字符串。
 */
(function (window) {
    'use strict';

    var routes = window.CRM_ROUTES || readRoutesManifest() || {};
    var uriIndex = {};
    var patternIndex = [];

    function readRoutesManifest() {
        var script = document.getElementById('crm-routes-manifest');

        if (!script) {
            return {};
        }

        try {
            return JSON.parse(script.textContent || '{}');
        } catch (error) {
            return {};
        }
    }

    /**
     * 建立 URI 到路由名称的反查索引，兼容旧代码暂时只传 url 的情况。
     * 静态 URI 走精确匹配；带 {id} 这类占位符的 URI 会生成正则，用于从旧 URL 中还原参数。
     */
    Object.keys(routes).forEach(function (name) {
        var uri = routeTemplate(name);
        var pattern;

        if (uri) {
            uriIndex[normalizePath(uri)] = name;
            pattern = routePattern(name, uri);
            if (pattern) {
                patternIndex.push(pattern);
            }
        }
    });

    // 参数路由按“具体路由优先、兜底 path 路由最后”的顺序匹配。
    // 例如 /admin/users/{id} 必须先于 /admin/{path} 命中，否则详情页会被兜底页抢走。
    patternIndex.sort(function (left, right) {
        if (left.hasPathWildcard !== right.hasPathWildcard) {
            return left.hasPathWildcard ? 1 : -1;
        }
        if (left.staticCount !== right.staticCount) {
            return right.staticCount - left.staticCount;
        }
        if (left.segmentCount !== right.segmentCount) {
            return right.segmentCount - left.segmentCount;
        }

        return left.name > right.name ? 1 : (left.name < right.name ? -1 : 0);
    });

    /**
     * 读取单条路由的 URI 模板。
     * 兼容字符串和 { uri: "...", methods: [] } 两种结构，方便后续扩展。
     */
    function routeTemplate(name) {
        var entry = routes[name];

        if (!entry) {
            return '';
        }
        if (typeof entry === 'string') {
            return entry;
        }

        return entry.uri || '';
    }

    /**
     * 路由参数只能替换路径占位符；每段单独编码，保留 path 参数里的斜杠层级。
     */
    function encodePathValue(value) {
        return String(value).split('/').map(function (part) {
            return encodeURIComponent(part);
        }).join('/');
    }

    /**
     * 把 _query 对象追加为查询字符串，避免调用方手写 ? 和 &。
     */
    function appendQuery(url, query) {
        var pairs = [];

        if (!query || typeof query !== 'object') {
            return url;
        }

        Object.keys(query).forEach(function (key) {
            var value = query[key];

            if (value === undefined || value === null || value === '') {
                return;
            }
            pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
        });

        if (!pairs.length) {
            return url;
        }

        return url + (url.indexOf('?') === -1 ? '?' : '&') + pairs.join('&');
    }

    /**
     * 解析 URL 的路径、查询串和 hash，确保反查时只用 pathname，比对后再保留原 query/hash。
     */
    function parseUrl(url) {
        var link;
        var raw = String(url || '');

        if (!raw) {
            return {path: '', search: '', hash: ''};
        }
        if (raw.indexOf('{') !== -1) {
            return {
                path: raw.split('?')[0].split('#')[0],
                search: raw.indexOf('?') === -1 ? '' : '?' + raw.split('?')[1].split('#')[0],
                hash: raw.indexOf('#') === -1 ? '' : '#' + raw.split('#')[1]
            };
        }
        link = document.createElement('a');
        link.href = raw;

        return {
            path: link.pathname || raw.split('?')[0].split('#')[0] || '',
            search: link.search || '',
            hash: link.hash || ''
        };
    }

    /**
     * 统一路径格式：只比较 pathname，不比较 query/hash。
     */
    function normalizePath(url) {
        var parsed = parseUrl(url);

        return (parsed.path || '').replace(/\/+$/, '') || '/';
    }

    /**
     * 正则特殊字符需要转义，避免路由里的点号、加号等被当成正则语义。
     */
    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * 为带参数的 URI 模板生成匹配器，例如 /admin/users/{id}。
     * path 参数通常承载多级路径，位于末段时允许包含斜杠。
     */
    function routePattern(name, uri) {
        var path = normalizePath(uri);
        var parts;
        var keys = [];
        var patternParts;

        if (path.indexOf('{') === -1) {
            return null;
        }

        parts = path.split('/');
        patternParts = parts.map(function (part, index) {
            var match = part.match(/^\{([^}]+)\}$/);
            var key;

            if (!match) {
                return escapeRegExp(part);
            }

            key = String(match[1]).replace(/\?$/, '');
            keys.push(key);

            return key === 'path' && index === parts.length - 1 ? '(.+)' : '([^/]+)';
        });

        return {
            name: name,
            keys: keys,
            regex: new RegExp('^' + patternParts.join('/') + '$'),
            hasPathWildcard: keys.indexOf('path') !== -1,
            staticCount: parts.length - keys.length,
            segmentCount: parts.length
        };
    }

    /**
     * decodeURIComponent 失败时保留原值，让异常路径不会中断整页脚本。
     */
    function decodePathValue(value) {
        try {
            return decodeURIComponent(value);
        } catch (e) {
            return value;
        }
    }

    function isBackendApiPath(path) {
        return /^\/api\/(?:front|admin)(?:\/|$)/.test(path);
    }

    /**
     * 从旧 URL 中解析命名路由和参数，精确 URI 优先，参数 URI 次之。
     */
    function routeMatchFromUrl(url) {
        var path = normalizePath(url);
        var i;
        var pattern;
        var matched;
        var params;

        if (isBackendApiPath(path)) {
            return {name: '', params: {}};
        }

        if (uriIndex[path]) {
            return {name: uriIndex[path], params: {}};
        }

        for (i = 0; i < patternIndex.length; i++) {
            pattern = patternIndex[i];
            matched = path.match(pattern.regex);
            if (!matched) {
                continue;
            }

            params = {};
            pattern.keys.forEach(function (key, index) {
                params[key] = decodePathValue(matched[index + 1] || '');
            });

            return {name: pattern.name, params: params};
        }

        return {name: '', params: {}};
    }

    /**
     * 尝试从旧 URL 推导命名路由。
     * 仅用于页面 URL；后端 API URL 保持原始字符串，方便搜索、阅读和调试。
     */
    function routeNameFromUrl(url) {
        return routeMatchFromUrl(url).name;
    }

    /**
     * 旧 URL 里带 query/hash 时，反查到新路由后继续保留这些附加信息。
     */
    function appendOriginalSuffix(url, sourceUrl) {
        var parsed = parseUrl(sourceUrl);
        var result = url || '';

        if (parsed.search && result.indexOf('?') === -1) {
            result += parsed.search;
        }
        if (parsed.hash && result.indexOf('#') === -1) {
            result += parsed.hash;
        }

        return result;
    }

    /**
     * 按 Laravel 路由名称生成 URL。
     * 缺少路由时返回 fallback，缺少参数时删除对应占位段，兼容可选参数页面。
     */
    function crmRoute(name, params, fallback) {
        var url = routeTemplate(name);
        var used = {};

        params = params && typeof params === 'object' ? params : {};
        fallback = fallback || '';

        if (!url) {
            if (window.console && window.console.warn) {
                window.console.warn('CRM 路由未找到：' + name);
            }
            return fallback;
        }

        url = url.replace(/\/?\{([^}]+)\}/g, function (match, key) {
            var cleanKey = String(key).replace(/\?$/, '');
            var value = params[cleanKey];

            if (value === undefined || value === null || value === '') {
                return '';
            }

            used[cleanKey] = true;
            return (match.charAt(0) === '/' ? '/' : '') + encodePathValue(value);
        });

        return appendQuery(url, params._query);
    }

    window.CRM_ROUTES = routes;
    window.crmRoute = crmRoute;
    window.CRM_ROUTE = crmRoute;
    window.crmRouteEntry = function (name) {
        return routes[name] || null;
    };
    window.crmRouteNameFromUrl = routeNameFromUrl;
    window.crmRouteMatchFromUrl = routeMatchFromUrl;
    window.crmRouteFromUrl = function (url, fallback) {
        var match = routeMatchFromUrl(url);
        var resolved;

        if (!match.name) {
            return fallback || url || '';
        }

        resolved = crmRoute(match.name, match.params || {}, fallback || url || '');
        return appendOriginalSuffix(resolved, url);
    };
})(window);
