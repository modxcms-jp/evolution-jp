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

    var toggleButtons = jQuery('.toggle-action');
    var allToggleButton = jQuery('#toggle_check_all');
    var checkboxes = jQuery('input:checkbox.toggle');
    var selectableCheckboxes = function () {
        return checkboxes.filter(':not(:disabled)');
    };
    var setActiveToggle = function (target) {
        toggleButtons.removeClass('is-active').attr('aria-pressed', 'false');
        if (target && target.length) {
            target.addClass('is-active').attr('aria-pressed', 'true');
        }
    };
    var updateActiveToggleState = function () {
        var selectable = selectableCheckboxes();
        var selectableCount = selectable.length;
        var areButtonsDisabled = selectableCount === 0;
        toggleButtons
            .prop('disabled', areButtonsDisabled)
            .attr('aria-disabled', areButtonsDisabled ? 'true' : 'false');
        if (selectableCount === 0) {
            setActiveToggle(null);
            return;
        }
        if (selectable.filter(':checked').length === selectableCount) {
            setActiveToggle(allToggleButton);
            return;
        }
        setActiveToggle(null);
    };

    jQuery('#toggle_check_all').click(function (evt) {
        evt.preventDefault();
        selectableCheckboxes().prop('checked', true);
        updateActiveToggleState();
    });
    jQuery('#toggle_check_none').click(function (evt) {
        evt.preventDefault();
        selectableCheckboxes().prop('checked', false);
        updateActiveToggleState();
    });
    jQuery('#toggle_check_toggle').click(function (evt) {
        evt.preventDefault();
        selectableCheckboxes().prop('checked', function () {
            return !jQuery(this).prop('checked');
        });
        updateActiveToggleState();
    });

    checkboxes.change(function () {
        updateActiveToggleState();
    });

    var handleSampleDataCheckbox = function () {
        var demo = jQuery('#installdata_field').prop('checked');
        jQuery('input:checkbox.toggle.demo').each(function () {
            if (demo) {
                jQuery(this).prop('checked', true).prop('disabled', true);
            } else {
                jQuery(this).prop('disabled', false);
            }
        });
        updateActiveToggleState();
    };

    jQuery('#installdata_field').click(function () {
        handleSampleDataCheckbox();
    });

    // handle state of demo content checkbox on page load
    handleSampleDataCheckbox();
</script>
