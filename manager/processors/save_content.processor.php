<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if($_POST['stay']==='d')
	include_once('save_draft_content.processor.php');
else
	include_once('save_resource.processor.php');
