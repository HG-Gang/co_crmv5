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
    // 打开通用加载层，返回 layer 索引用于后续关闭。
    showLoading: function() {
        if (window.layui && layui.layer) {
            return layui.layer.load(2);
        }
    },
    // 按索引关闭加载层，避免多个异步请求互相误关。
    hideLoading: function(index) {
        if (window.layui && layui.layer) {
            layui.layer.close(index);
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
