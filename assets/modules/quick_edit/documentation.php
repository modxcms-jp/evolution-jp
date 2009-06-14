<?php

// Automatically get the module ID (thanks Travis)
$id = (!empty($_REQUEST["id"])) ? (int)$_REQUEST["id"] : "[QuickEditModuleId]";
$manager_language = $modx->config['manager_language'];
include_once $basePath.'assets/modules/quick_edit/lang/documents/english.inc.php';
if($manager_language!="english")
{
	if (file_exists($basePath.'assets/modules/quick_edit/lang/documents/'.$manager_language.'.inc.php'))
	{
		include_once $basePath.'assets/modules/quick_edit/lang/documents/'.$manager_language.'.inc.php';
	}
}
echo $QE_doc_lang['QE_doc_content'];
?>
