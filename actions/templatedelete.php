<?php

class BlogMenu_templatedelete
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$view =& $admin->getView();
		$tpmanager =& $admin->getTpManager();
		
		$tpl = array();
		$tpl['id'] = intRequestVar('tid');
		$tpl['name'] = $tpmanager->getNameFromID($tpl['id']);
		
		if (!$tpl['name']) {
			$admin->error(_ERROR_BADACTION);
		}
		
		$view->assign('template', $tpl);
		
		$view->display('templatedelete.tpl.php');
	}
}

?>