<?php

class BlogMenu_templatenew
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$name = postVar('tname');
		$desc = postVar('tdesc');
		
		$tpmanager =& $admin->getTpManager();
		
		if (!isValidTemplateName($name))
			$admin->error(_ERROR_BADTEMPLATENAME);
		
		if ($tpmanager->exists($name))
			$admin->error(_ERROR_DUPTEMPLATENAME);
		
		$newid = $tpmanager->createTemplate($name, $desc);
		
		$controller->forward('templateoverview');
	}
}

?>