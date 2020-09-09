jQuery(function ($) {
    function updateNotifiers() {
        $('.gdezakazy-status').each(function () {
            var $this = $(this), $textarea = $this.find('.text'), $select = $this.find('select'), $checkbox = $this.find('input[type=checkbox]');
            if ($select.val()) {
                $checkbox.prop('disabled', false).closest('label').removeClass('disabled');
            } else {
                $checkbox.prop('disabled', true).closest('label').addClass('disabled');
            }
            if ($checkbox.is(':checked') && $select.val()) {
                $textarea.show();
            } else {
                $textarea.hide();
            }
        });
    }
    updateNotifiers();
    $('.gdezakazy-status input[type=checkbox]').click(function () {
        updateNotifiers();
    });
    $('.gdezakazy-status select').change(function () {
        updateNotifiers();
    });
});