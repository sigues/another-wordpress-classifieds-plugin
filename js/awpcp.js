/*
    jQuery pub/sub plugin by Peter Higgins (dante@dojotoolkit.org)

    Loosely based on Dojo publish/subscribe API, limited in scope. Rewritten blindly.

    Original is (c) Dojo Foundation 2004-2010. Released under either AFL or new BSD, see:
    http://dojofoundation.org/license for more information.

    https://github.com/phiggins42/bloody-jquery-plugins/blob/master/pubsub.js
*/
;(function(d){

    var cache = {};

    d.publish = function(/* String */topic, /* Array? */args){
        if (cache[topic]) {
            d.each(cache[topic], function(){
                this.apply(d, args || []);
            });
        }
    };

    d.subscribe = function(/* String */topic, /* Function */callback){
        if(!cache[topic]){
            cache[topic] = [];
        }
        cache[topic].push(callback);
        return [topic, callback]; // Array
    };

    d.unsubscribe = function(/* Array */handle){
        var t = handle[0];
        if (cache[t]) {
            d.each(cache[t], function(idx){
                if(this == handle[1]){
                    cache[t].splice(idx, 1);
                }
            });
        }
    };

})(jQuery);


(function($) {
    var AWPCP = function() {
        if (typeof __awpcp_js_data == 'object') {
            this.options = __awpcp_js_data;
        } else {
            this.options = {};
        }

        if (this.get('ajaxurl') === null) {
            if (typeof AWPCP !== 'undefined' && AWPCP.ajaxurl) {
                this.set('ajaxurl', AWPCP.ajaxurl);
            } else if (ajaxurl) {
                this.set('ajaxurl', ajaxurl);
            } else {
                this.set('ajaxurl', '/wp-admin/admin-ajax.php');
            }
        }
    };

    $.extend(AWPCP.prototype, {
        set: function(name, value) {
            this.options[name] = value;
            return this;
        },

        get: function(name) {
            return this.options[name] ? this.options[name] : null;
        }
    });

    $.AWPCP = new AWPCP();
})(jQuery);
