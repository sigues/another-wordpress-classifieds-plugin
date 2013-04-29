if (typeof jQuery != 'undefined') {
	(function($, undefined) {
       
        /* handlers for Admin Panel - Listings page */
        $(function() {
            var listings = $('#manageads'),
                buttons = listings.find('.deletechekedbuttom'),
                table = listings.find('table'),
                canvas, panel = $();

            canvas = $('<div style="clear: both"></div>').insertBefore(table);

            // bulk Ad delete

            buttons.delegate('.button:first', 'click', function(event) {
                event.preventDefault();
                var button = $(this), form = $(this).closest('form'),
                    message;
                if (!button.hasClass('waiting')) {
                    message = 'Are you sure you want to delete the selected Ads? &nbsp;';
                    canvas.append($('<span class="delete-verification">' + message + '</span>'))
                          .append(button.addClass('waiting').addClass('button-primary'))
                          .append($('<input type="button" class="cancel button" value="Cancel" /> '));
                }
                return false;
            });

            canvas.delegate('.cancel', 'click', function(event) {
                event.preventDefault();
                var button = canvas.find('.waiting').removeClass('waiting')
                                                    .removeClass('button-primary');
                buttons.prepend(button);
                canvas.empty();
            });

            // single Ad delete

            table.delegate('.trash', 'click', function(event) {
                event.preventDefault();
                var link = $(this),
                    column = link.closest('td'),
                    message = 'Are you sure you want to delete this Ad? &nbsp;';
                column.append($('<br/><span class="delete-verification">' + message + '</span>'))
                      .append(link.clone().addClass('button-primary').removeClass('trash'))
                      .append($('<input type="button" class="cancel button" value="Cancel" /> '));
            }).delegate('.cancel', 'click', function(event) {
                event.preventDefault();
                var link = $(this),
                    column = link.closest('td');
                column.find('br').remove();
                column.find('.delete-verification').remove();
                column.find('.button-primary').remove();
                column.find('.cancel').remove();
            });
        });
	})(jQuery);
}