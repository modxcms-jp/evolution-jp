<?php

class BlogMenu_templateedit
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$tid = intRequestVar('tid');
		if (!$tid) $admin->error(_ERROR_BADACTION);
		
		$tpmanager =& $admin->getTpManager();
		if (!$tpmanager->existsID($tid)) {
			$admin->error('No such id exists.');
		}
		
		$view =& $admin->getView();
		if ($msg) $view->assign('message', $msg);
		
		$data = $tpmanager->readFromID($tid);
		$view->assign('data', $data);
		
		$popup = array();
		$popup['bloglist'] = $admin->createPopup('bloglist');
		$popup['aliases'] = $admin->createPopup('aliases');
		$popup['categorylist'] = $admin->createPopup('categorylist');
		
		$view->assign('popup', $popup);
		
		$view->display('templateedit.tpl.php');

	}
}

?>