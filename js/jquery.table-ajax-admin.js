/**
 * Copyright (c) 2011 Willington Vega <wvega@wvega.com>
 */

if (typeof jQuery != 'undefined') {

(function($, undefined) {

    $.WordPressAjaxAdmin = function(element, options) {
        var self = this, block = self.block = $(element);

        self.options = $.extend({}, $.WordPressAjaxAdmin.defaults, options);

        block.delegate('.row-actions a', 'click', function(event) {
            var link, parent, row;

            link = self.link = $(this);
            parent = self.parent = link.closest('span');
            row = self.row = parent.closest('tr');

            if (link.attr('target') == '_blank' || parent.length === 0) {
                return;
            } else if (!parent.hasClass('view') && $.inArray(parent.attr('class'), self.options.ignore) < 0) {
                event.preventDefault();
            }

            if (parent.hasClass('edit')) {
                self.edit();
            } else if (parent.hasClass('top') || parent.hasClass('up') ||
                       parent.hasClass('down') || parent.hasClass('bottom')) {
                self.move();
            } else if (parent.hasClass('trash')) {
                self.trash();
            }
        });

        block.delegate('a.add', 'click', function(event) {
            event.preventDefault();
            self.link = $(this);
            self.add();
        });
    };

    $.WordPressAjaxAdmin.defaults = {
        ignore: []
    };

    $.WordPressAjaxAdmin.prototype = {

        add: function() {
            var options = this.options,
                link = this.link,
                parent = link.closest('div'),
                table = parent.find('table tbody'),
                first = table.find('tr:first'), inline;
                
            $.post(options.ajaxurl, $.extend({}, options.data, {
                'action': options.actions.add
            }), function(response, status, xhr) {
                inline = $(response.html).insertBefore(first);

                // handle save and cancel buttons
                inline.find('a.cancel').click(function(){
                    first.show();inline.remove();
                });
                inline.find('a.save').click(function(){
                    var waiting = inline.find('img.waiting').show();
                    inline.find('div.error').remove();
                    inline.find('form').ajaxSubmit({
                        data: {'save': true},
                        dataType: 'json',
                        success: function(response, status, xhr) {
                            if (response.status === 'success') {
                                inline.remove();
                                table.append(response.html);
                                if (first.hasClass('empty-row')) {
                                    first.remove();
                                }
                            } else {
                                waiting.hide();
                                var errors = $('<div class="error">');
                                $.each(response.errors, function(k,v) {
                                    errors.append(v + '</br>');
                                });
                                inline.find('p.submit').after(errors);
                            }
                        }
                    });
                });
                
                if ($.isFunction(options.onFormReady)) {
                    options.onFormReady.apply(this, [options.actions.add, inline]);
                }

                if (first.hasClass('empty-row')) {
                    first.hide();
                }
            });
        },

        edit: function() {
            var options = this.options,
                link = this.link,
                parent = this.parent,
                row = this.row;

            $.post(options.ajaxurl, $.extend({}, options.data, {
                'action': options.actions.edit,
                'id': row.data('id')
            }), function(response, status, xhr) {
                inline = $(response.html).insertAfter(row);
                inline.find('a.cancel').click(function() {
                    row.show();inline.remove();
                });
                inline.find('a.save').click(function() {
                    var waiting = inline.find('img.waiting').show();
                    inline.find('div.error').remove();
                    inline.find('form').ajaxSubmit({
                        data: $.extend({}, options.data, {save: true}),
                        dataType: 'json',
                        success: function(response, status, xhr) {
                            if (response.status === 'success') {
                                row.replaceWith(response.html);
                                inline.remove();
                            } else {
                                waiting.hide();
                                var errors = $('<div class="error">');
                                $.each(response.errors, function(k,v) {
                                    errors.append(v + '</br>');
                                });
                                inline.find('p.submit').after(errors);
                            }
                        }
                    });
                });
            
                if ($.isFunction(options.onFormReady)) {
                    options.onFormReady.apply(this, [options.actions.edit, inline]);
                }

                row.hide();
            });
        },

        move: function() {
            var options = this.options,
                link = this.link,
                parent = this.parent,
                row = this.row;

            $.post(options.ajaxurl, {
                action: options.actions.move,
                target: parent.attr('class'),
                id: row.data('id')
            }, function(response, status, xhr) {
                if (response.id == response.other) { return; }
                var other = $(options.base + response.other);
                if (response.target == 'top' || response.target == 'up') {
                    other.before(row);
                } else if (response.target == 'down' || response.target == 'bottom') {
                    other.after(row);
                }
            });
        },

        trash: function() {
            var options = this.options,
                link = this.link,
                parent = this.parent,
                row = this.row;

            $.post(options.ajaxurl, $.extend({}, options.data, {
                'action': options.actions.remove,
                'id': row.data('id')
            }), function(response, status, xhr) {
                inline = $(response.html).insertAfter(row);
                inline.find('a.cancel').click(function() {
                    row.show(); inline.remove();
                });
                
                var form = inline.find('form'),
                    legend = form.find('label span');
                
                inline.delegate('a.delete', 'click', function() {
                    var buttons = inline.find('a.delete'),
                        option = $(this).data('option'),
                        waiting = inline.find('img.waiting').show();

                    form.ajaxSubmit({
                        data: { 'remove': true, 'option': option },
                        dataType: 'json',
                        success: function(response, status, xhr) {
                            var link = null;
                            
                            // mission acomplished!
                            if (response.status === 'success') {
                                row.remove(); inline.remove();
                            
                            // we need to ask something else to the user
                            } else if (response.status === 'confirm') {
                                waiting.hide();

                                // create a set of options
                                $.each(response.options, function(value, label) {
                                    link = $('<a></a>').attr({
                                         href: '#inline-edit' ,
                                         title: label,
                                         "class": 'button-primary delete alignright'
                                     }).text(label).data('option', value);
                                     buttons.eq(0).after(link);
                                });
                                buttons.remove();

                                // update the form legend
                                legend.text(response.message);

                            // ¬_¬
                            } else {
                                waiting.hide();
                                form.find('div.error').remove();
                                form.append('<div class="error"><p>' + response.message + '</p></div>');

                                // create default Delete button
                                link = $('<a></a>').attr({
                                    href: '#inline-edit' ,
                                    title: label,
                                    "class": 'button-primary delete alignright'
                                }).text(label).data('option', value);
                                buttons.eq(0).after(link);
                                buttons.remove();
                            }
                        }
                    });
                });
                row.hide();
            });
        }
    };

    /**
     * Plugin to handle Model add, edit and delete actions
     */
    $.fn.admin = function(options) {
        return this.each(function() {
            new $.WordPressAjaxAdmin($(this), options);
        });
    };

})(jQuery);

}