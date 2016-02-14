<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('export_static'))
{
	$e->setError(3);
	$e->dumpError();
}

// figure out the base of the server, so we know where to get the documents in order to export them
?>

<h1><?php echo $_lang['export_site_html']; ?></h1>

<div id="actions">
  <ul class="actionButtons">
      <li id="Button5"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<script type="text/javascript">
tpExport = new WebFXTabPane(document.getElementById("exportPane"));
</script>

<div class="sectionBody">
<div class="tab-pane" id="exportPane">
<div class="tab-page" id="tabMain">
<h2 class="tab"><?php echo $_lang['export_site']?></h2>
<?php

if(isset($_POST['export']))
{
	$rs = include_once(MODX_MANAGER_PATH . 'processors/export_site.processor.php');
	echo $rs;
}
else
{
?>

<form action="index.php" method="post" name="exportFrm">
<input type="hidden" name="export" value="export" />
<input type="hidden" name="a" value="83" />
<style type="text/css">
table.settings {width:100%;}
table.settings td.head {white-space:nowrap;vertical-align:top;padding-right:20px;font-weight:bold;}
</style>
<table class="settings" cellspacing="0" cellpadding="2">
<?php
	$generate_mode = $modx->config['export_generate_mode'] ? $modx->config['export_generate_mode'] : 'crawl';
?>
  <tr>
    <td class="head"><?php echo $_lang['a83_mode_title']; ?></td>
    <td>
		<label><input type="radio" name="generate_mode" value="crawl"  <?php echo $generate_mode==='crawl'?'checked':'';?>><?php echo $_lang['a83_mode_crawl'];?></label>
	    <label><input type="radio" name="generate_mode" value="direct" <?php echo $generate_mode==='direct'?'checked':''; ?>><?php echo $_lang['a83_mode_direct'];?></label>
	</td>
  </tr>
<?php
	$includenoncache0 = '';
	$includenoncache1 = '';
	if($modx->config['export_includenoncache']==='1') $includenoncache1 = 'checked="checked"';
	else                                            $includenoncache0 = 'checked="checked"';
?>
<?php
	$allow_ids = $modx->getOption('export_allow_ids');
	$ignore_ids = $modx->getOption('export_ignore_ids');
?>
  <tr>
    <td class="head">出力するリソース</td>
    <td>
	<label><input name="target" type="radio" value="all" checked>全リソース</label>
	<label><input name="target" type="radio" value="allow_ids">一部のリソースを出力</label>
	<label><input name="target" type="radio" value="ignore_ids">一部のリソースを除外</label>
	<div id="allow_ids" style="display:none;">
	    <input type="text" name="allow_ids" value="<?php echo $allow_ids;?>" style="width:300px;background-color:#f2fff2;" /><br />
	    出力するリソースのIDを指定(カンマ区切りで複数指定可)
	</div>
	<div id="ignore_ids" style="display:none;">
	    <input type="text" name="ignore_ids" value="<?php echo $ignore_ids;?>" style="width:300px;background-color:#fff2f2;" /><br />
	    出力しないリソースのIDを指定(カンマ区切りで複数指定可)
	</div>
    </td>
  </tr>
  <tr>
    <td class="head"><?php echo $_lang['export_site_cacheable']; ?></td>
    <td><label><input type="radio" name="includenoncache" value="1" <?php echo $includenoncache1;?>><?php echo $_lang['yes'];?></label>
		<label><input type="radio" name="includenoncache" value="0" <?php echo $includenoncache0;?>><?php echo $_lang['no'];?></label></td>
  </tr>
<?php
$repl_before = $modx->getOption('export_repl_before',$modx->config['site_url']);
$repl_after  = $modx->getOption('export_repl_after',$modx->config['site_url']);
?>
  <tr>
    <td class="head"><?php echo $_lang['export_site.static.php4']; ?></td>
    <td><input type="text" name="repl_before" value="<?php echo $repl_before; ?>" style="width:300px;" /></td>
  </tr>
  <tr>
    <td class="head"><?php echo $_lang['export_site.static.php5']; ?></td>
    <td><input type="text" name="repl_after" value="<?php echo $repl_after; ?>" style="width:300px;" /></td>
  </tr>
  <tr>
    <td class="head"><?php echo $_lang['export_site_maxtime']; ?></td>
    <td><input type="text" name="maxtime" value="60" />
		<br />
		<?php echo $_lang['export_site_maxtime_message']; ?>
	</td>
  </tr>
</table>

<ul class="actionButtons">
	<li><a href="#" class="default" onclick="nanobar.go(20);document.exportFrm.submit();"><img src="<?php echo $_style["icons_save"] ?>" /> <?php echo $_lang["export_site_start"]; ?></a></li>
</ul>
</form>

<?php
}
?>


</div>
<div class="tab-page" id="tabHelp">
<h2 class="tab"><?php echo $_lang['help']?></h2>
<?php
	echo '<p>'.$_lang['export_site_message'].'</p>';
?>
</div>
</div>
</div>

<script>
jQuery('input[name="target"]:radio').change(function(){
	switch(jQuery('input[name="target"]:checked').val()) {
		case 'all'       :jQuery('#ignore_ids').fadeOut('fast');jQuery('#allow_ids').fadeOut('fast');break;
		case 'allow_ids' :jQuery('#ignore_ids').hide();jQuery('#allow_ids').fadeIn('normal');break;
		case 'ignore_ids':jQuery('#allow_ids').hide();jQuery('#ignore_ids').fadeIn('normal');break;
	}
});
</script>