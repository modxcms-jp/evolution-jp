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
    var form = document.getElementById('install');
    document.querySelectorAll('a.prev').forEach(function (el) {
        el.addEventListener('click', function () {
            var target = (is_upgradeable == 1) ? 'mode' : 'connection';
            form.querySelector('input[name=action]').value = target;
            form.submit();
        });
    });
    document.querySelectorAll('a.next').forEach(function (el) {
        el.addEventListener('click', function () {
            form.submit();
        });
    });

    var actionButtons = document.querySelectorAll('.actions .action-button');
    var toggleCheckboxes = document.querySelectorAll('input[type=checkbox].toggle');

    // 無効になっていないチェックボックス
    var selectableCheckboxes = function () {
        return Array.from(toggleCheckboxes).filter(function (cb) {
            return !cb.disabled;
        });
    };

    var clearActionState = function () {
        actionButtons.forEach(function (btn) {
            btn.classList.remove('is-active');
        });
    };

    var setActionState = function (action) {
        clearActionState();
        if (action) {
            actionButtons.forEach(function (btn) {
                if (btn.getAttribute('data-action') === action) {
                    btn.classList.add('is-active');
                }
            });
        }
    };

    var updateActionStateFromCheckboxes = function () {
        var selectable = selectableCheckboxes();
        if (!selectable.length) {
            clearActionState();
            return;
        }

        var selectedCount = selectable.filter(function (cb) {
            return cb.checked;
        }).length;
        if (selectedCount === selectable.length) {
            setActionState('all');
            return;
        }

        if (selectedCount === 0) {
            setActionState('none');
            return;
        }

        clearActionState();
    };

    var onActionButton = function (id, handler) {
        var btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('click', function (evt) {
                evt.preventDefault();
                handler();
            });
        }
    };
    onActionButton('toggle_check_all', function () {
        selectableCheckboxes().forEach(function (cb) {
            cb.checked = true;
        });
        setActionState('all');
    });
    onActionButton('toggle_check_none', function () {
        selectableCheckboxes().forEach(function (cb) {
            cb.checked = false;
        });
        setActionState('none');
    });
    onActionButton('toggle_check_toggle', function () {
        selectableCheckboxes().forEach(function (cb) {
            cb.checked = !cb.checked;
        });
        updateActionStateFromCheckboxes();
    });

    toggleCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            clearActionState();
        });
    });

    var installDataField = document.getElementById('installdata_field');

    var handleSampleDataCheckbox = function () {
        var demo = installDataField ? installDataField.checked : false;
        document.querySelectorAll('input[type=checkbox].toggle.demo').forEach(function (cb) {
            if (demo) {
                cb.checked = true;
                cb.disabled = true;
            } else {
                cb.disabled = false;
            }
        });
    };

    if (installDataField) {
        installDataField.addEventListener('click', function () {
            clearActionState();
            handleSampleDataCheckbox();
        });
    }

    // handle state of demo content checkbox on page load
    handleSampleDataCheckbox();
    updateActionStateFromCheckboxes();
</script>
