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
        if (currval == 1) {
            jQuery('#passwordBlock').show(100);
        } else {
            jQuery('#passwordBlock').hide(100);
        }
    }

    function changeblockstate(element, checkelement) {
        currval = eval(element).value;
        if (currval == 1) {
            if (confirm("<?= $_lang['confirm_unblock'] ?>") == true) {
                document.userform.blocked.value = 0;
                document.userform.blockeduntil.value = "";
                document.userform.blockedafter.value = "";
                document.userform.failedlogincount.value = 0;
                blocked.innerHTML = "<b><?= $_lang['unblock_message'] ?></b>";
                blocked.className = "TD";
                eval(element).value = 0;
            } else {
                eval(checkelement).checked = true;
            }
        } else {
            if (confirm("<?= $_lang['confirm_block'] ?>") == true) {
                document.userform.blocked.value = 1;
                blocked.innerHTML = "<b><?= $_lang['block_message'] ?></b>";
                blocked.className = "warning";
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
        <?php if(getv('id') == evo()->getLoginUserID()) { ?>
        alert("<?= $_lang['alert_delete_self'] ?>");
        <?php } else { ?>
        if (confirm("<?= $_lang['confirm_delete_user'] ?>") == true) {
            document.location.href = "index.php?id=" + document.userform.userid.value + "&a=33";
        }
        <?php } ?>
    }

    function OpenServerBrowser(url, width, height) {
        let iLeft = (screen.width - width) / 2;
        let iTop = (screen.height - height) / 2;

        let sOptions = "toolbar=no,status=no,resizable=yes,dependent=yes";
        sOptions += ",width=" + width;
        sOptions += ",height=" + height;
        sOptions += ",left=" + iLeft;
        sOptions += ",top=" + iTop;

        let oWindow = window.open(url, "FCKBrowseWindow", sOptions);
    }

    function BrowseServer() {
        let w = screen.width * 0.7;
        let h = screen.height * 0.7;
        OpenServerBrowser("<?= $base_url ?>manager/media/browser/mcpuk/browser.php?Type=images", w, h);
    }

    function SetUrl(url, width, height, alt) {
        document.userform.photo.value = url;
        document.images['iphoto'].src = "<?= $base_url ?>" + url;
    }

    tpUser = new WebFXTabPane(document.getElementById("userPane"), <?= (config('remember_last_tab') == 2 || getv('stay') == 2) ? 'true' : 'false' ?> );
</script>
