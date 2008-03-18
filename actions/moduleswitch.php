<?php

class BlogMenu_moduleswitch
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$name = requestVar('name');
		if (!preg_match('/^[-_a-zA-Z0-9.]+$/', $name)) {
			$admin->error(_ERROR_BADACTION);
		}
		
		$plugin =& $admin->getPlugin();
		$allowed_modules = $plugin->getModuleList();
		
		$key = array_search($name, $allowed_modules);
		if ($key !== false) {
			unset($allowed_modules[$key]);
		} else {
			array_push($allowed_modules, $name);
		}
		
		$plugin->setOption('modules', implode(',',$allowed_modules));
		$plugin->plugin_options = 0;
		
		$controller->forward('overview', __NP_MSG_MODULE_UPDATED);
	}
	
}

?>