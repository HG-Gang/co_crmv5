var CRM = CRM || {};

(function() {
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

    CRM.use = function(modules, callback) {
        // Simple module loader mock for compatibility
        if (typeof modules === 'string') modules = [modules];
        callback();
    };
})();

var API = {
    baseUrl: '',
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
    get: function(url) {
        return $.ajax({
            url: url,
            type: 'GET',
            headers: this.headers(),
            dataType: 'json'
        });
    },
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
