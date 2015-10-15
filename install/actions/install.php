<h2><?php echo $_lang['install_results']?></h2>
<?php
ob_start();
include_once("{$installer_path}instprocessor.php");
$content = ob_get_contents();
ob_end_clean();
echo $content;
session_destroy();

if ($errors == 0) {
	// check if install folder is removeable
    if ((is_writable('../install') || is_webmatrix()) && !is_iis()) { ?>
<label style="float:left;line-height:18px;"><input type="checkbox" id="rminstaller" value="1" checked /><?php echo $_lang['remove_install_folder_auto'] ?></label>
<?php 
    } else {
?>
<span style="float:left;color:#505050;line-height:18px;"><?php echo $_lang['remove_install_folder_manual']?></span>
<?php
    }
}
?>
    <p class="buttonlinks">
        <a id="closepage" title="<?php echo $_lang['btnclose_value']?>"><span><?php echo $_lang['btnclose_value']?></span></a>
    </p>
	<br />
<br />
<script type="text/javascript">
/* <![CDATA[ */
jQuery('#closepage span').click(function(){
	checked = jQuery('#rminstaller').prop('checked');
	if(checked) {
		// remove install folder and files
		window.location.href = "../manager/processors/remove_installer.processor.php?rminstall=1";
	}
	else {
		window.location.href = "../manager/";
	}
});
/* ]]> */
</script>
