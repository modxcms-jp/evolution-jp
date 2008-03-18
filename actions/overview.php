<?php

class BlogMenu_overview
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$view =& $admin->getView();
		if ($msg) $view->assign('message', _MESSAGE.': '.$msg);
		
		$plugin =& $admin->getPlugin();
		$rank_cnf = array(
			'blogmax' => $plugin->getOption('maxblogrank'),
			'blogdef' => $plugin->getOption('defblogrank'),
			'catmax' => $plugin->getOption('maxcatrank'),
			'catdef' => $plugin->getOption('defcatrank')
		);
		
		$view->assign('rank_cnf', $rank_cnf);
		
		$allowed_modules = $plugin->getModuleList();
		$module_dir = $plugin->getDirectory() . 'modules/';
		$modules = array();
		
		$d = dir(substr($module_dir, 0, -1));
		while (false !== ($file = $d->read()))
		{
			$classname = substr($file, 0, -4);
			if (preg_match('/^BMModule_([-_a-zA-Z0-9.]+)\.php$/', $file, $m))
			{
				$modules[$classname] = array();
				$modules[$classname]['name'] = $m[1];
				if (in_array($m[1], $allowed_modules)) {
					$modules[$classname]['enable'] = 1;
				} else {
					$modules[$classname]['enable'] = 0;
				}
			}
		}
		$d->close();
		
		$view->assign('modules', $modules);
		
		$popup = array();
		$popup['module'] = $admin->createPopup('module');
		$popup['rankbasic'] = $admin->createPopup('rankbasic');
		
		$view->assign('popup', $popup);
		
		$view->display('overview.tpl.php');
	}
}

?>