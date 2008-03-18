<?php

class BlogMenu_templateoverview
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$view =& $admin->getView();
		
		if ($msg) $view->assign('message', _MESSAGE. ': ' .$msg);
		
		$tpmanager =& $admin->getTpManager();
		$templates = $tpmanager->getTemplateList();
		
		$view->assign('templates', $templates);
		
		
		$popup = array();
		$popup['name'] = $admin->createPopup('name');
		
		$view->assign('popup', $popup);
		
		$view->display('templateoverview.tpl.php');
	}
	
}

?>