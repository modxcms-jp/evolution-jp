<?php
define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', 'true');
$self = 'manager/media/browser/mcpuk/browser.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
require_once("{$base_path}index.php");
header('X-UA-Compatible: IE=EmulateIE7');
if(!isset($_SESSION['mgrValidated'])) {
	if(!isset($_SESSION['webValidated'])){
		die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
	}
}

$rb = new FBROWSER();
$ph = array();
$ph['seturl_js'] = $rb->seturl_js();
$output = $rb->render_fbrowser($ph);
echo $output;

class FBROWSER
{
	function seturl_js()
	{
		if(isset($_GET['editor']) && strpos($editor_name, '..')===false)
			$editor_name = htmlspecialchars($_GET['editor']);
		else $editor_name = '';
		if(!empty($editor_name))
		{
			$seturl_js_path = MODX_BASE_PATH . "assets/plugins/{$editor_name}/seturl_js_{$editor_name}.inc.html";
		}
		else $seturl_js_path = '';
		
		if($seturl_js_path!='' && is_file($seturl_js_path))
		{
			$result = file_get_contents($seturl_js_path);
		}
		else
		{
			switch($editor_name)
			{
				case 'tinymce' :
				case 'tinymce3':
					$result = file_get_contents('seturl_js_tinymce.inc');
					break;
				default:
				$result = '<script src="seturl.js" type="text/javascript"></script>' . "\n";
			}
		}
		if(strpos($result,'[+editor_path+]')!==false)
			$result = str_replace('[+editor_path+]', MODX_BASE_URL . "assets/plugins/{$editor_name}/", $result);
		return $result;
	}
	
	function render_fbrowser($ph)
	{
		$browser_html = file_get_contents('browser.inc.html');
		foreach($ph as $name => $value)
		{
			$name = '[+' . $name . '+]';
			$browser_html = str_replace($name, $value, $browser_html);
		}
		return $browser_html;
	}
}
