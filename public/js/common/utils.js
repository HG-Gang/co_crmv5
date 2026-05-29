var Utils = {
    toast: function(message, type) {
        if (window.layui && layui.layer) {
            layui.layer.msg(message, { icon: type === 'success' ? 1 : 2 });
        } else {
            alert(message);
        }
    },
    showLoading: function() {
        if (window.layui && layui.layer) {
            return layui.layer.load(2);
        }
    },
    hideLoading: function(index) {
        if (window.layui && layui.layer) {
            layui.layer.close(index);
        }
    },
    getQueryString: function(name) {
        var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
        var r = window.location.search.substr(1).match(reg);
        if (r != null) return unescape(r[2]);
        return null;
    }
};
