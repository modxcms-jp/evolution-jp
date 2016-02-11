<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
global $modx, $_lang, $_style, $modx_textdir, $modx_lang_attribute;
global $manager_theme, $modx_charset;
global $manager_language,$modx_version;

if($modx->config['remember_last_tab']!=='2')
{
	$tab = (isset($_GET['tab'])) ? intval($_GET['tab']) : '1';
	setcookie('webfxtab_childPane', $tab, time()+3600, MODX_BASE_URL);
}
$mxla = $modx_lang_attribute ? $modx_lang_attribute : 'en';

$bodyid = (isset($_GET['f'])) ? $_GET['f'] : 'mainpane';
$textdir = $modx_textdir==='rtl' ? 'rtl' : 'ltr';

// invoke OnManagerRegClientStartupHTMLBlock event
$evtOut = $modx->invokeEvent('OnManagerMainFrameHeaderHTMLBlock');
if(!isset($modx->config['tree_pane_open_default'])) $modx->config['tree_pane_open_default'] = 1;
?>
<!DOCTYPE html>
<html lang="<?php echo  $mxla;?>" dir="<?php echo  $textdir;?>">
<head>
    <title>MODX</title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $modx->config['modx_charset']; ?>" />
    <link rel="stylesheet" type="text/css" href="media/style/<?php echo $modx->config['manager_theme']; ?>/style.css?<?php echo $modx_version;?>" />
    <link rel="stylesheet" type="text/css" href="media/script/jquery/jquery.powertip.css" />
    <link rel="stylesheet" href="media/script/jquery/jquery.alerts.css" type="text/css" />
    <!-- OnManagerMainFrameHeaderHTMLBlock -->
    <?php if(is_array($evtOut)) echo implode("\n", $evtOut); ?>
    <?php echo $modx->config['manager_inline_style']; ?>
    <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
    <script src="media/script/jquery/jquery.powertip.min.js" type="text/javascript"></script>
    <script src="media/script/jquery/jquery.alerts.js" type="text/javascript"></script>
    <script src="media/script/nanobar.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="media/script/tabpane.js"></script>
    <script src="media/script/mootools/mootools.js" type="text/javascript"></script>
    <script type="text/javascript">
		/* <![CDATA[ */
		
		var treeopen = <?php echo $modx->config['tree_pane_open_default'];?>;
		if(treeopen==0) top.mainMenu.hideTreeFrame();
		
		var documentDirty=false;
		var dontShowWorker = false;
		var baseurl = '<?php echo MODX_BASE_URL; ?>';
		var $j = jQuery.noConflict();
		
        // set tree to default action.
        if (parent.tree) parent.tree.ca = "open";

		// call the updateMail function, updates mail notification in top navigation
		if (top.mainMenu && top.mainMenu.updateMail) top.mainMenu.updateMail(true);
		
		jQuery(function(){
			var action = <?php echo $modx->manager->action;?>;
			switch(action)
			{
				case 27:
				case 17:
				case 4:
				case 87:
				case 88:
				case 11:
				case 12:
				case 28:
				case 38:
				case 35:
				case 16:
				case 19:
				case 22:
				case 23:
				case 77:
				case 78:
				case 107:
				case 108:
				case 113:
				case 100:
				case 101:
				case 102:
				case 300:
				case 301:
					jQuery('input,textarea,select:not(#template,#which_editor,#stay)').change(function() {documentDirty=true;});
					gotosave=false;
				break;
			}
            stopWorker();
            jQuery('#preLoader').hide();
            <?php if(isset($_REQUEST['r'])) echo sprintf("doRefresh(%s);\n",$_REQUEST['r']); ?>
			jQuery('.tooltip').powerTip({'fadeInTime':'0','placement':'e'});
		});
		
		jQuery(window).on('beforeunload', function(){
			if(documentDirty) return '<?php echo $_lang['warning_not_saved'];?>';
			if(!dontShowWorker && top.mainMenu) top.mainMenu.work();
		});
        
        function doRefresh(r) {
            try{
                rr = r;
                top.mainMenu.reloadPane(rr);
            } catch(oException) {
                vv = window.setTimeout('doRefresh(' + r + ')',200);
            }
        }
        
        function stopWorker() {
            try {
                parent.mainMenu.stopWork();
            } catch(oException) {
                ww = window.setTimeout('stopWorker()',200);
            }
        }

		/* ]]> */
    </script>
</head>
<body id="<?php echo $bodyid;?>" ondragstart="return false"<?php echo $modx_textdir==='rtl' ? ' class="rtl"':''?>>
<script>
var nanobar = new Nanobar({id: 'loadingBar'});
nanobar.go(50);
</script>
<div id="preLoader"><table width="100%" border="0" cellpadding="0"><tr><td align="center"><div class="preLoaderText"><?php echo $_style['ajax_loader']; ?></div></td></tr></table></div>
