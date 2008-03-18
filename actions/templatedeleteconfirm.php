<?php

class BlogMenu_templatedeleteconfirm
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$tid = intRequestVar('tid');
		if (!$tid) $admin->error(_ERROR_BADACTION);
		
		$tpmanager =& $admin->getTpManager();
		$tpmanager->deleteTemplate($tid);
		
		$controller->forward('templateoverview');
	}
}

?>