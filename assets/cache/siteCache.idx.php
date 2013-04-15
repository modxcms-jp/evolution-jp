<?php
if(!defined('MODX_BASE_PATH') || strpos(str_replace('\\','/',__FILE__), MODX_BASE_PATH)!==0) exit;
$c = &$this->contentTypes;
$e = &$this->pluginEvent;
$e['OnBeforeDocFormSave'] = array('ManagerManager');
$e['OnBeforeManagerLogin'] = array('Forgot Manager Login');
$e['OnDocFormPrerender'] = array('ManagerManager');
$e['OnDocFormRender'] = array('ManagerManager');
$e['OnInterfaceSettingsRender'] = array('TinyMCE Rich Text Editor');
$e['OnManagerAuthentication'] = array('Forgot Manager Login');
$e['OnManagerChangePassword'] = array('Forgot Manager Login');
$e['OnManagerLoginFormPrerender'] = array('Forgot Manager Login');
$e['OnManagerLoginFormRender'] = array('Forgot Manager Login');
$e['OnManagerMainFrameHeaderHTMLBlock'] = array('ManagerManager');
$e['OnPluginFormRender'] = array('ManagerManager');
$e['OnRichTextEditorInit'] = array('TinyMCE Rich Text Editor');
$e['OnRichTextEditorRegister'] = array('TinyMCE Rich Text Editor');

