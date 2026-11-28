<?= userFormScript() ?>
<script type="text/javascript">
    function deleteuser() {
        <?php if($userid === (int) evo()->getLoginUserID()) { ?>
        alert(<?= js_literal(stripcslashes(lang('alert_delete_self'))) ?>);
        <?php } else { ?>
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_user'))) ?>) == true) {
            EvoCsrf.post("index.php?id=" + document.userform.userid.value + "&a=33");
        }
        <?php } ?>
    }
</script>
