jQuery(function ($) {
    $('#gdezakazy_order_wrap').on('click', '#gdezakazy_add_form .button-primary', function () {
        var $this = $(this);
        $this.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            method: 'post',
            data: $('#gdezakazy_add_form input').serialize() + '&action=gdezakazy_add',
            success: function (data) {
                $('#gdezakazy_order_wrap').html(data);
            },
            error: function () {
                alert('Request error');
                $this.prop('disabled', false);
            }
        });
        return false;
    });
    $('#gdezakazy_order_wrap').on('click', '#gdezakazy_archive_btn', function () {
        var $this = $(this);
        $this.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            method: 'post',
            data: 'action=gdezakazy_archive&order_id=' + $this.data('id'),
            success: function (data) {
                $('#gdezakazy_order_wrap').html(data);
            },
            error: function () {
                alert('Request error');
                $this.prop('disabled', false);
            }
        });
        return false;
    });
});