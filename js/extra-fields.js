if (typeof jQuery != 'undefined') {
    (function($, undefined) {

        // handler to show Extra Fields when a particular category is selected
        // it works both in Place Ad page and Search Ads page
        $(function() {
            $.fn.disabled = function(disabled) {
                $(this).each(function() {
                    var element = $(this),
                        method = element.prop ? 'prop' : 'attr';
                        
                    if (disabled && method == 'attr') {
                        element.attr('disabled', 'disabled');
                    } else if (!disabled && method == 'attr') {
                        element.removeAttr('disabled');

                    } else if (disabled) {
                        element.prop('disabled', true);
                    } else {
                        element.prop('disabled', false);
                    }
                });
            };

            var category = $('select[name="searchcategory"], select[name="adcategory"], input[name="adcategory"]');

            category.bind('change', function() {
                var select = $(this),
                    form = select.closest('form'),
                    selected = parseInt(select.val(), 10),
                    fields = form.find('.awpcp-extra-field').filter(':not(.awpcp-extra-field-category-root)');
                // hide and disable all fields
                fields.hide().find('input,select,textarea').disabled(true);
                if (selected > 0) {
                    // hide and enable visible fields
                    $('.awpcp-extra-field-category-' + selected).show()
                        .find('input,select,textarea').disabled(false);
                }
            });

            // disable all fields that are hidden
            category.trigger('change');
        });

    })(jQuery);
}