if (typeof jQuery != 'undefined') {
	(function($, undefined) {

        /* handlers for Ad Management Panel - Listings page */
        $(function() {
            var panel = $('#awpcp-ad-management-panel');

            panel.admin({
                actions: {
                    remove: 'awpcp-panel-delete-ad'
                },
                ajaxurl: ajaxurl,
                base: '#ad-'
            });

            // handle Delete Selected Ads button
            panel.find('.bulk-delete-form').delegate('.trash-selected:button', 'click', function(event) {
                event.preventDefault();
                var button = $(this), form = $(this).closest('form'),
                    message;
                if (!button.hasClass('waiting')) {
                    message = 'Are you sure you want to delete the selected Ads? &nbsp;';
                    button.before($('<span class="delete-verification">' + message + '</span>'))
                        .before($('<input type="button" class="cancel button" value="Cancel" /> '))
                        .addClass('waiting').addClass('button-primary');
                } else {
                    form.get(0).submit();
                }
            }).delegate('.cancel:button', 'click', function(event) {
                event.preventDefault();
                var button = $(this), form = $(this).closest('form');
                form.find('span.delete-verification').remove();
                form.find('.trash-selected:button').removeClass('waiting')
                        .removeClass('button-primary');
                button.remove();
            });
        });
        
	})(jQuery);
}