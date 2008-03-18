<?php

class BlogMenu_templateclone
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$tid = intRequestVar('tid');
		if (!$tid) $admin->error(_ERROR_BADACTION);
		
		$tpmanager =& $admin->getTpManager();
		
		// 1. read old template
		$basename = $tpmanager->getNameFromID($tid);

		// 2. create desc thing
		$newname = "cloned" . $basename;
		
		// if a template with that name already exists:
		if ($tpmanager->exists($newname)) {
			$i = 1;
			while ($tpmanager->exists($newname . $i))
				$i++;
			$newname .= $i;
		}		
		
		$newid = $tpmanager->createTemplate($newname);

		// 3. create clone
		// go through parts of old template and add them to the new one
		$tpl = $tpmanager->read($basename);
		
		$tpl['tid'] = $newid;
		$tpl['tname'] = $newname;

		$tpmanager->updateTemplate($newid,$tpl);
		
		
		$controller->forward('templateoverview');
	}
	
}

?>