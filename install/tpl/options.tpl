<form id="install" action="[+site_url+]install/" method="POST">
    <input type="hidden" name="action" value="summary"/>
    <input type="hidden" name="prev_action" value="options"/>
    <h2>[+optional_items+]</h2>

    [+object_list+]

    <hr/>
    [+convert_utf8mb4_option+]
    [+install_sample_site+]

    <p class="buttonlinks">
        <a class="prev" href="javascript:void(0);" title="[+btnback_value+]"><span>[+btnback_value+]</span></a>
        <a class="next" href="javascript:void(0);" title="[+btnnext_value+]"><span>[+btnnext_value+]</span></a>
    </p>

</form>
<script type="text/javascript">
    var is_upgradeable = '[+is_upgradeable+]';
    jQuery('a.prev').click(function () {
        var target = (is_upgradeable == 1) ? 'mode' : 'connection';
        jQuery('#install input[name=action]').val(target);
        jQuery('#install').submit();
    });
    jQuery('a.next').click(function () {
        jQuery('#install').submit();
    });

    var actionButtons = jQuery('.actions .action-button');
    var toggleCheckboxes = jQuery('input:checkbox.toggle');

    var clearActionState = function () {
        actionButtons.removeClass('is-active');
    };

    var setActionState = function (action) {
        clearActionState();
        if (action) {
            actionButtons.filter('[data-action="' + action + '"]').addClass('is-active');
        }
    };

    jQuery('#toggle_check_all').click(function (evt) {
        evt.preventDefault();
        jQuery('input:checkbox.toggle:not(:disabled)').prop('checked', true);
        setActionState('all');
    });
    jQuery('#toggle_check_none').click(function (evt) {
        evt.preventDefault();
        jQuery('input:checkbox.toggle:not(:disabled)').prop('checked', false);
        setActionState('none');
    });
    jQuery('#toggle_check_toggle').click(function (evt) {
        evt.preventDefault();
        jQuery('input:checkbox.toggle:not(:disabled)').prop('checked', function () {
            return !jQuery(this).prop('checked');
        });
        setActionState('toggle');
    });

    toggleCheckboxes.on('change', function () {
        clearActionState();
    });

    jQuery('#installdata_field').click(function () {
        handleSampleDataCheckbox();
    });

    var handleSampleDataCheckbox = function () {
        clearActionState();
        var demo = jQuery('#installdata_field').prop('checked');
        jQuery('input:checkbox.toggle.demo').each(function () {
            if (demo) {
                jQuery(this).prop('checked', true).prop('disabled', true);
            } else {
                jQuery(this).prop('disabled', false);
            }
        });
    };

    // handle state of demo content checkbox on page load
    handleSampleDataCheckbox();
</script>
