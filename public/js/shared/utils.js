// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/10
// Time: 15:22
/**
 * 前端轻量工具集合。
 * 只放跨页面会复用的小能力：提示、加载层和 URL 参数读取。
 */
var Utils = {
    // 优先使用 Layui layer 提示；无 Layui 的页面退回浏览器 alert。
    toast: function(message, type) {
        if (window.layui && layui.layer) {
            layui.layer.msg(message, { icon: type === 'success' ? 1 : 2 });
        } else {
            alert(message);
        }
    },
    // 打开通用加载层，复用全站 Ajax 半透明遮罩。
    showLoading: function() {
        if (window.CrmAjax && CrmAjax.showGlobalMask) {
            return CrmAjax.showGlobalMask({});
        }

        return false;
    },
    // 按开启结果关闭遮罩，避免多个异步请求互相误关。
    hideLoading: function(index) {
        if (window.CrmAjax && CrmAjax.hideGlobalMask) {
            CrmAjax.hideGlobalMask(index);
        }
    },
    // 从当前 URL 查询串中读取指定参数，兼容旧页面直接取 query 的写法。
    getQueryString: function(name) {
        var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
        var r = window.location.search.substr(1).match(reg);
        if (r != null) return unescape(r[2]);
        return null;
    }
};
