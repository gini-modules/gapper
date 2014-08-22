define('gapper/client/utils/data', function() {
    var data = {};
    var handler = {
        set: function(key, value) {
            data[key] = value;
        }
        ,get: function(key) {
            return data[key];
        }
    };
    return handler;
});

