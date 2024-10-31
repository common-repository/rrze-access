jQuery(document).ready(function($) {
    rrze_access_none = $('#rrze-access-none').val();
    rrze_access_active = $('#rrze-access-active').val();
    rrze_access = $('#post-rrze-access-display').html();
    rrze_access_text = $('#rrze-access-text').val();

    $('.edit-rrze-access', '#rrze-access').click(function () {
        if ($('#post-rrze-access-select').is(":hidden")) {
            updateAccess();
            $('#post-rrze-access-select').slideDown('fast');
            $(this).hide();
        }
        return false;
    });

    $('.cancel-post-rrze-access', '#post-rrze-access-select').click(function () {
        $('#post-rrze-access-select').slideUp('fast');
        $('#post-rrze-access-display').html(rrze_access);
        $('#rrze-access-' + $('#rrze-access-checked').val()).prop('checked', true);
        $('.edit-rrze-access', '#rrze-access').show();
        $('#rrze-access-text').val(rrze_access_text);
        return false;
    });

    $('.save-post-rrze-access', '#post-rrze-access-select').click(function () {
        $('#post-rrze-access-select').slideUp('fast');
        $('#post-rrze-access-select').siblings('a.edit-rrze-access').show();
        updateAccess();
        return false;
    });
        
    $('input:radio', '#post-rrze-access-select').change(function() {
        updateAccess();
    });

    function updateAccess() {
        var paSelect = $('#post-rrze-access-select');
        var paDisplay = $('#post-rrze-access-display');
        if ( $('input:radio:checked', paSelect).val() != '1' ) {
            $('#rrze-access-field').show();
            paDisplay.html(rrze_access_active);
        } else {
            paDisplay.html(rrze_access_none);
            $('#rrze-access-field').hide();
        }
    }

});
