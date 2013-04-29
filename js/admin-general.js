/*global AWPCPAjaxOptions:true */

if (jQuery !== undefined) {
    (function($, undefined) {

        $(function() {
            var guide = $('#quick-start-guide-notice'),
                cancel = guide.find('.button'),
                submit = guide.find('.button-primary');

            var onSuccess = function() {
                $.ajax({
                    url: AWPCPAjaxOptions.ajaxurl,
                    type: 'POST',
                    data: {
                        'action': 'disable-quick-start-guide-notice'
                    },
                    success: function() {
                        guide.closest('.update-nag').fadeOut(function() {
                            $(this).remove();
                        });
                    }
                });
            };

            submit.click(function() {
                onSuccess();
            });

            cancel.click(function() {
                onSuccess();
            });
        });

    })(jQuery);
}
