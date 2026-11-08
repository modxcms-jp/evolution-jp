<script type="text/javascript">

    function changestate(element) {
        documentDirty = true;
        currval = eval(element).value;
        if (currval == 1) {
            eval(element).value = 0;
        } else {
            eval(element).value = 1;
        }
    }

    function changePasswordState(element) {
        currval = eval(element).value;
        var passwordBlock = document.getElementById('passwordBlock');
        if (passwordBlock) {
            passwordBlock.style.display = currval == 1 ? '' : 'none';
        }
    }

    function changeblockstate(element, checkelement) {
        var blocked = document.getElementById('blocked');
        currval = eval(element).value;
        if (currval == 1) {
            if (confirm(<?= js_literal(stripcslashes(lang('confirm_unblock'))) ?>) == true) {
                document.userform.blocked.value = 0;
                document.userform.blockeduntil.value = "";
                document.userform.blockedafter.value = "";
                document.userform.failedlogincount.value = 0;
                if (blocked) {
                    blocked.replaceChildren(Object.assign(document.createElement("b"), {textContent: <?= js_literal(stripcslashes(lang('unblock_message'))) ?>}));
                    blocked.className = "TD";
                }
                eval(element).value = 0;
            } else {
                eval(checkelement).checked = true;
            }
        } else {
            if (confirm(<?= js_literal(stripcslashes(lang('confirm_block'))) ?>) == true) {
                document.userform.blocked.value = 1;
                if (blocked) {
                    blocked.replaceChildren(Object.assign(document.createElement("b"), {textContent: <?= js_literal(stripcslashes(lang('block_message'))) ?>}));
                    blocked.className = "warning";
                }
                eval(element).value = 1;
            } else {
                eval(checkelement).checked = false;
            }
        }
    }

    function resetFailed() {
        document.userform.failedlogincount.value = 0;
        document.getElementById("failed").innerHTML = "0";
    }

    function deleteuser() {
        <?php if($userid === (int) evo()->getLoginUserID()) { ?>
        alert(<?= js_literal(stripcslashes(lang('alert_delete_self'))) ?>);
        <?php } else { ?>
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_user'))) ?>) == true) {
            EvoCsrf.post("index.php?id=" + document.userform.userid.value + "&a=33");
        }
        <?php } ?>
    }

    function BrowseServer() {
        window.evoOpenFilePicker(<?= js_literal(config('base_url') . 'manager/media/browser/evo/browser.php?type=images') ?>);
    }

    function SetUrl(url, width, height, alt) {
        document.userform.photo.value = url;
        document.images['iphoto'].src = <?= js_literal(config('base_url')) ?> + url;
    }

    tpUser = new WebFXTabPane(document.getElementById("userPane"), <?= (config('remember_last_tab') == 2 || get_strvar('save_action') === 'stay') ? 'true' : 'false' ?> );
</script>
