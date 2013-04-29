(function($, undefined) {

    $.AWPCP = typeof $.AWPCP !== 'undefined' ? $.AWPCP : {};

    $.AWPCP.PlaceAdForm = function(id, selectors) {
        this.id = id;
        this.form = $(id);
        this.selectors = $.extend({ users:'', terms:'', methods:'' }, selectors);
        this.find_elements();
    };

    $.extend($.AWPCP.PlaceAdForm.prototype, {
        find_elements: function() {
            var selectors = this.selectors;
            this.users = this.form.find(selectors.users);
            this.terms = this.form.find(selectors.terms);
            this.methods = this.form.find(selectors.methods);
            this.categories = this.form.find(selectors.categories);

            this.name = this.form.find(selectors.name);
            this.email = this.form.find(selectors.email);
            this.website = this.form.find(selectors.website);
            this.phone = this.form.find(selectors.phone);
            this.state = this.form.find(selectors.state);
            this.city = this.form.find(selectors.city);
        },

        get_user_data: function(user_id) {
            var user = null;
            $.each(AWPCP_Users, function(k, entry) {
                if (entry.ID == user_id) {
                    user = entry;
                    return false;
                }
            });
            return user;
        },

        get_category_terms: function(category, terms) {
            var categories;

            terms = terms || this.terms;

            if (category.length < 0) {
                return terms;
            }

            return terms.filter(function() {
                categories = $.parseJSON($(this).attr('data-categories'));
                return $.inArray(category, categories) > -1 || categories.length === 0;
            });
        },

        update_payment_methods: function(price) {
            this.methods.closest('fieldset, p')[price > 0 ? 'show' : 'hide']();
        },

        update_payment_terms: function(user_id, category) {
            var item = null, items, select, selected, total = 0;
            var user, selector, terms;

            // Payment Terms wrapper my be hidden, let's change that.
            this.terms.closest('p').show();


            // find Payment Terms allowed for selected user
            user_id = parseInt(user_id, 10);

            if (isNaN(user_id) || user_id === 0) {
                user = null;
                selector = '[data-categories]';
            } else {
                user = this.users.find('[value=' + user_id + ']');
                terms = user.attr('data-payment-terms') || [];
                selector = '#payment-term-default';
                if (terms.length > 0) {
                    selector += ', #payment-term-' + terms.split(',').join(', #payment-term-');
                }
            }
            items = this.terms.hide().filter(selector);


            // filter Payment Terms by category
            items = this.get_category_terms(category, items);

            // two items: the default and one actual payment term
            if (items.length === 2) {
                item = items.filter(':not(#payment-term-default)').show();
            } else {
                items.show();
            }


            // find current selected Payment Term and update form values
            // if necessary.
            // Payment Terms are either <option> or <tr> elements
            if (this.terms.length > 0 && this.terms.get(0).tagName.toLowerCase() == 'option') {
                selected = this.terms.find(':selected');
                select = this.terms.closest('select');
                if (!selected.is(':visible') && item === null) {
                    select.val('');
                } else if (item !== null) {
                    select.val(item.attr('value'));
                }
                select.change();
            } else {
                selected = this.terms.find('input').filter(':checked').filter(':visible').closest('tr');
            }


            // calculate total amount to paid and update Payment Methods
            if (selected.length === 0) {
                selected = this.terms.filter(':visible');
            }

            selected.map(function() {
                total += parseFloat($(this).attr('data-price'));
            });

            this.update_payment_methods(total);
        },

        set_user_info: function(user, overwrite) {
            overwrite = overwrite || false;

            var self = this, current, passed, updated = {};

            // quick fix to find the right City, State fields,
            // the ones that are visible at this moment
            this.find_elements();

            current = {
                name: this.name.val(),
                email: this.email.val(),
                website: this.website.val(),
                phone: this.phone.val(),
                state: this.state.val(),
                city: this.city.val()
            };
            passed = {
                name: user.first_name + ' ' + user.last_name,
                email: user.user_email,
                website: user.user_url,
                phone: user.phone,
                state: user.state,
                city: user.city
            };

            $.each(current, function(field) {
                if (current[field] && current[field].length > 0 && !overwrite) {
                    updated[field] = current[field];
                } else {
                    updated[field] = passed[field] ? passed[field] : '';
                }
            });

            this.name.val(updated.name);
            this.email.val(updated.email);
            this.website.val(updated.website);
            this.phone.val(updated.phone);

            // var field = this.state.filter(':visible');
            // if (field.length > 0 && field[0].tagName.toLowerCase() == 'select') {
            //     this.city.one('awpcp-update-region-options-completed', function(event) {
            //         this.city.val(updated.city).change();
            //     });
            //     this.state.val(updated.state).change();
            // } else {
            //     this.state.val(updated.state).change();
            //     this.city.val(updated.city);
            // }
            this.city.one('awpcp-update-region-options-completed', function() {
                self.city.val(updated.city).change();
            });
            this.state.val(updated.state).change();
        },

        clean_user_info: function() {
            this.name.val('');
            this.email.val('');
            this.website.val('');
            this.phone.val('');
            this.state.val('');
            this.city.val('');
        }
    });

    // Show/Hide Payment Terms when a Category is selected
    // Show/Hide Payment Methods when a Payment Term is selected
    $(function() {
        var form = new $.AWPCP.PlaceAdForm('#awpcp-place-ad-payment-step-form', {
            terms: '.js-awpcp-payment-term',
            methods: '.js-awpcp-payment-method'
        });

        form.form.find('#place-ad-category').change(function() {
            var category = $(this).val();
            form.update_payment_terms(null, category);
        }).change();

        var fn = function() {
            var radio = $(this);
            if (radio.attr('checked')) {
                form.update_payment_methods(radio.closest('tr').attr('data-price'));
            }
        };
        form.terms.find(':radio').click(fn).each(fn);
    });


    // Update Ad Details fields related to user information everytime an
    // user is selected in the users dropdown (available to administrators)
    $(function() {
        var form = new $.AWPCP.PlaceAdForm('#adpostform', {
            categories: '[name=adcategory]',
            users: '#place-ad-user-id',
            terms: '#place-ad-user-payment-terms option',
            name: 'input[name=adcontact_name]',
            email: 'input[name=adcontact_email]',
            state: 'input[name=adcontact_state], select[name=adcontact_state]',
            city: 'input[name=adcontact_city], select[name=adcontact_city]',
            phone: 'input[name=adcontact_phone]',
            website: 'input[name=websiteurl]'
        });

        form.terms.closest('p').hide();

        // handle per Fee characters allowed setting
        form.terms.closest('select').change(function() {
            var term = $('#payment-term-' + $(this).val()),
                limit, event;

            if (term.length <= 0) { return; }

            limit = parseInt(term.attr('data-characters-allowed'), 10);
            form.form.find('[name=characters_allowed]').val(limit);

            event = jQuery.Event('keydown');
            form.form.find('[name=addetails]').trigger(event);
        });

        form.categories.change(function() {
            if (form.users.length <= 0) { return; }
            form.update_payment_terms(form.users.val(), $(this).val());
        });

        form.users.bind('change awpcp-start', function(event) {
            var user,
                user_id = parseInt(form.users.val(), 10),
                overwrite = event.type !== 'awpcp-start',
                updated;

            if (user_id === 0) {
                form.terms.closest('p').hide();
                return;
            }

            // attempt to update form fields with user's profile data
            user = form.get_user_data(user_id);

            if (user === null) {
                form.clean_user_info();
                return;
            }

            updated = form.set_user_info(user, overwrite);


            // show message about empty fields
            var fields = [{name: 'First Name', value: user.first_name},
                           {name: 'Last Name', value: user.last_name},
                           {name: 'Email', value: user.user_email},
                           {name: 'Website', value: user.user_url},
                           {name: 'Phone Number', value: user.phone},
                           {name: 'State', value: user.state},
                           {name: 'City', value: user.city}],
                empty_fields = [],
                message = $('<span class="error message"></span>');

            $.each(fields, function(k, _field) {
                if (_field.value.length === 0) {
                    empty_fields.push(_field.name);
                }
            });

            form.users.nextAll('br, span.message').remove();
            if (empty_fields.length > 0) {
                message.text('This user has empty profile fields for ' + empty_fields.join(', ') + '.');
                form.users.closest('.awpcp-form-spacer').append('<br/>').append(message);
            }

            // update Payment Terms after a new user has been selected
            form.update_payment_terms(user_id, form.categories.val());
        });

        if (form.users.length > 0) {
            form.users.trigger('awpcp-start');
        }
    });
})(jQuery);