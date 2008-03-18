<?php

class BlogMenu_pluginhelp
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		
		$helpfile = $admin->getHelpPath();
		
		$view =& $admin->getView();
		$view->assign('helpfile', $helpfile);
		
		$view->display('pluginhelp.tpl.php');
	}
}

?>