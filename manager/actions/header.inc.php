<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
global $modx, $_lang, $_style, $modx_textdir, $modx_lang_attribute;
global $action, $manager_theme, $modx_charset;
global $manager_language,$modx_version;

if($modx->config['remember_last_tab']!=='2')
{
	$tab = (isset($_GET['tab'])) ? intval($_GET['tab']) : '1';
	setcookie('webfxtab_childPane', $tab, time()+3600, MODX_BASE_URL);
}
$mxla = $modx_lang_attribute ? $modx_lang_attribute : 'en';

$bodyid = (isset($_GET['f'])) ? $_GET['f'] : 'mainpane';
$textdir = $modx_textdir==='rtl' ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?php echo  $mxla;?>" dir="<?php echo  $textdir;?>">
<head>
    <title>MODX</title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $modx->config['modx_charset']; ?>" />
    <link rel="stylesheet" type="text/css" href="media/style/<?php echo $modx->config['manager_theme']; ?>/style.css?<?php echo $modx_version;?>" />
    <link rel="stylesheet" type="text/css" href="media/script/jquery/jquery.powertip.css" />
    <link rel="stylesheet" href="media/script/jquery/jquery.alerts.css" type="text/css" />
<?php
// invoke OnManagerRegClientStartupHTMLBlock event
$evtOut = $modx->invokeEvent('OnManagerMainFrameHeaderHTMLBlock');
$onManagerMainFrameHeaderHTMLBlock = is_array($evtOut) ? implode("\n", $evtOut) : '';
?>
    <!-- OnManagerMainFrameHeaderHTMLBlock -->
    <?php echo $onManagerMainFrameHeaderHTMLBlock; ?>
    <?php echo $modx->config['manager_inline_style']; ?>
    <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
    <script src="media/script/jquery/jquery-migrate.min.js"></script>
    <script src="media/script/jquery/jquery.powertip.min.js" type="text/javascript"></script>
    <script src="media/script/jquery/jquery.alerts.js" type="text/javascript"></script>
    <script src="media/script/nanobar.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="media/script/tabpane.js"></script>
    <script src="media/script/mootools/mootools.js" type="text/javascript"></script>
    <script type="text/javascript">
		/* <![CDATA[ */
		
		var cookiepath = '<?php echo MODX_BASE_URL; ?>';
		var $j = jQuery.noConflict();
		
        function document_onload() {
            stopWorker();
            hideLoader();
<?php
	if(isset($_REQUEST['r'])) echo 'doRefresh(' . $_REQUEST['r'] . ");\n";
?>
        }
        
		$j(function(){
			var action = <?php echo $action;?>;
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
					$j('input').change(function() {documentDirty=true;});
					$j('textarea').change(function() {documentDirty=true;});
					$j('select:not(#template,#which_editor)').change(function() {documentDirty=true;});
					gotosave=false;
				break;
			}
        	document_onload();
			$j('.tooltip').powerTip({'fadeInTime':'0','placement':'e'});
		});
		
        $j.bind('beforeunload', document_onunload());
        
        function doRefresh(r) {
            try
            {
                rr = r;
                top.mainMenu.startrefresh(rr);
            }
            catch(oException)
            {
                vv = window.setTimeout('doRefresh()',500);
            }
        }
        
		function reset_path(elementName) {
	  		document.getElementById(elementName).value = document.getElementById('default_' + elementName).innerHTML;
		}

        var dontShowWorker = false;
        function document_onunload() {
            if(!dontShowWorker && top.mainMenu) top.mainMenu.work();
        }

        // set tree to default action.
        if (parent.tree) parent.tree.ca = "open";

		// call the updateMail function, updates mail notification in top navigation
		if (top.mainMenu) {
			if(top.mainMenu.updateMail) top.mainMenu.updateMail(true);
		}
		
        function stopWorker() {
            try
            {
                parent.mainMenu.stopWork();
            }
            catch(oException)
            {
                ww = window.setTimeout('stopWorker()',500);
            }
        }

        var documentDirty=false;
        var gotosave = false;

        function checkDirt(evt) {
            if(documentDirty==true && gotosave==false)
            {
				var message = "<?php echo $_lang['warning_not_saved']; ?>";
				
				if (typeof evt == 'undefined') evt = window.event;
				if (evt)                       evt.returnValue = message;
				
				return message;
  			}
        }

        function saveWait(fName) {
            document.getElementById('savingMessage').innerHTML = "<?php echo $_lang['saving']; ?>";
            for(i = 0; i < document.forms[fName].elements.length; i++)
            {
                document.forms[fName].elements[i].disabled='disabled';
            }
        }

        function hideLoader() {
            $j('#preLoader').css('display','none');
        }

        hideL = window.setTimeout("hideLoader()", 150);

        // add the 'unsaved changes' warning event handler
        if( window.addEventListener )
        {
			window.addEventListener('beforeunload',checkDirt,false);
		}
		else if ( window.attachEvent )
		{
			window.attachEvent('onbeforeunload',checkDirt);
		}
		else
		{
			window.onbeforeunload = checkDirt;
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
