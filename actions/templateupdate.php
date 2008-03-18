<?php

class BlogMenu_templateupdate
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$tid = intRequestVar('tid');
		if (!$tid) $admin->error(_ERROR_BADACTION);
		$name = postVar('tname');
		
		$tpmanager =& $admin->getTpManager();
		if (!$tpmanager->existsID($tid)) {
			$admin->error('No such id exists.');
		}
		
		if (($tpmanager->getNameFromID($tid) != $name)
				&& $tpmanager->exists($name))
		{
			$this->error(_ERROR_DUPTEMPLATENAME);
		}
		
		$tpparts = array(
			'tname',
			'tdesc',
			'blogheader',
			'bloglist',
			'blogflag',
			'aliases',
			'catheader',
			'catlist',
			'catfooter',
			'catflag'
		);
		$template = array();
		
		foreach ($tpparts as $v) {
			$template[$v] = postVar($v);
		}
		$template['aliases'] = str_replace("\r\n", "\n", $template['aliases']);
		$template['aliases'] = str_replace("\r", "\n", $template['aliases']);
		
		$tpmanager->updateTemplate($tid, $template);
		
		$controller->forward('templateedit', __NP_MSG_TEMPLATE_UPDATED);
	}
}

?>