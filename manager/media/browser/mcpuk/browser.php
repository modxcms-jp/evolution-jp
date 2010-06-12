<?php

include_once('../../../includes/config.inc.php');

// $modx_base_path = realpath('../../../../');
// $modx_base_path = trim($modx_base_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
// define('MODX_BASE_PATH', $modx_base_path);

$rb = new FBROWSER();
$ph = array();
$ph['seturl_js'] = $rb->seturl_js();
$output = $rb->render_fbrowser($ph);
echo $output;

class FBROWSER
{
	function seturl_js()
	{
		$seturl_js_filename = 'seturl_js_'  . htmlspecialchars($_GET['editor']) . '.inc';
		$seturl_js_path = MODX_BASE_PATH . 'assets/plugins/';
		
		if(file_exists($seturl_js_path . $seturl_js_filename))
		{
			$result = file_get_contents($seturl_js_path . $seturl_js_filename);
		}
		else
		{
			$editor_path = htmlspecialchars($_GET['editorpath'], ENT_QUOTES);
			switch($_GET['editor'])
			{
				case 'tinymce' :
				case 'tinymce3':
					$result = file_get_contents('seturl_js_tinymce.inc');
					$result = str_replace('[+editor_path+]', $editor_path, $result);
					break;
				default:
				$result = '<script src="seturl.js" type="text/javascript"></script>' . PHP_EOL;
			}
		}
		return $result;
	}
	
	function render_fbrowser($ph)
	{
		$browser_html = file_get_contents('browser.html.inc');
		$browser_html2 = $browser_html;
		foreach($ph as $name => $value)
		{
			$name = '[+' . $name . '+]';
			$browser_html = str_replace($name, $value, $browser_html);
		}
		return $browser_html;
			}
}
