define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/prompt',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, confirm, prompt, alert) {
    'use strict';

    return function (config, element) {
        var $element = $(element);

        $element.find('.action-approve').on('click', function (e) {
            e.preventDefault();
            var form = $(this).closest('form');
            confirm({
                content: $.mage.__('Are you sure you want to approve this purchase order?'),
                actions: {
                    confirm: function () {
                        form.submit();
                    }
                }
            });
        });

        $element.find('.action-reject').on('click', function () {
            var $button = $(this);
            var url = $button.data('url');
            var poId = $button.data('id');
            var formKey = $button.data('form-key');
            
            prompt({
                content: $.mage.__('Please enter a rejection reason for PO #') + poId + ':',
                actions: {
                    confirm: function (comment) {
                        if (!comment || comment.trim() === '') {
                            alert({
                                content: $.mage.__('A rejection reason is required.')
                            });
                            return false;
                        }
                        var form = $('<form>', {
                            'method': 'post',
                            'action': url
                        });
                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': 'form_key',
                            'value': formKey
                        }));
                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': 'comment',
                            'value': comment
                        }));
                        $('body').append(form);
                        form.submit();
                    }
                }
            });
        });

        $element.find('.action-cancel').on('click', function (e) {
            e.preventDefault();
            var form = $(this).closest('form');
            confirm({
                content: $.mage.__('Are you sure you want to cancel this purchase order? This action cannot be undone.'),
                actions: {
                    confirm: function () {
                        form.submit();
                    }
                }
            });
        });
    };
});
