<?php
/**
* get_lang_keys
*
* @return array of keys from a language file
*/
function get_lang_keys($filename)
{
	$file = MODX_CORE_PATH . "lang/{$filename}";
	if(is_file($file) && is_readable($file))
	{
		include($file);
		return array_keys($_lang);
	}
	else
	{
		return array();
	}
}
/**
* get_langs_by_key
*
* @return array of languages that define the key in their file
*/
function get_langs_by_key($key)
{
	global $lang_keys;
	$lang_return = array();
	foreach($lang_keys as $lang=>$keys)
	{
		if(in_array($key, $keys))
		{
			$lang_return[] = $lang;
		}
	}
	return $lang_return;
}

/**
* get_lang_options
*
* returns html option list of languages
*
* @param string $key specify language key to return options of langauges that override it, default return all languages
* @param string $selected_lang specify language to select in option list, default none
* @return html option list
*/
function get_lang_options($key=null, $selected_lang=null)
{
	global $lang_keys, $_lang;
	$lang_options = '';
	if($key)
	{
		$languages = get_langs_by_key($key);
		sort($languages);
		$lang_options .= '<option value="">'.$_lang['language_title'].'</option>';
		foreach($languages as $language_name)
		{
			$uclanguage_name = ucwords(str_replace("_", " ", $language_name));
			$lang_options .= '<option value="'.$language_name.'">'.$uclanguage_name.'</option>';
		}
		return $lang_options;
	}
	else
	{
		$languages = array_keys($lang_keys);
		sort($languages);
		foreach($languages as $language_name)
		{
			$uclanguage_name = ucwords(str_replace("_", " ", $language_name));
			$sel = $language_name == $selected_lang ? ' selected="selected"' : '';
			$lang_options .= '<option value="'.$language_name.'" '.$sel.'>'.$uclanguage_name.'</option>';
		}
		return $lang_options;
	}
}

function form_text($name,$maxlength='255',$add='',$readonly=false)
{
	global $settings;
	
	$value = isset($settings[$name]) ? $settings[$name] : '';
	
	if($readonly) $readonly = ' disabled';
	if($add)      $add = ' ' . $add;
	if(empty($maxlength)) $maxlength = '255';
	if($maxlength<=10) $maxlength = 'maxlength="' . $maxlength . '" style="width:' . $maxlength . 'em;"';
	else               $maxlength = 'maxlength="' . $maxlength . '"';
	return '<input type="text" ' . $maxlength . ' name="' . $name . '" value="' . $value . '"' . $readonly . $add . ' />';
}

function form_radio($name,$value,$checked=false,$add='',$disabled=false) {
	if($checked)  $checked  = ' checked="checked"';
	if($disabled) $disabled = ' disabled';
	if($add)     $add = ' ' . $add;
	return '<input type="radio" name="' . $name . '" value="' . $value . '"' . $checked . $disabled . $add . ' />';
}

function wrap_label($str='',$object) {
	return "<label>{$object}\n{$str}</label>";
}

function get_role_list()
{
	global $modx, $default_role;
	
	$rs = $modx->db->select('id,name', '[+prefix+]user_roles', 'id!=1', 'save_role DESC,new_role DESC,id ASC');
	$tpl = '<option value="[+id+]" [+selected+]>[+name+]</option>';
	$options = "\n";
	while($ph=$modx->db->getRow($rs))
	{
		$ph['selected'] = ($default_role == $ph['id']) ? ' selected' : '';
		$options .= $modx->parseText($tpl,$ph);
	}
	return $options;
}

function checkConfig($key) {
	global $settings,$default_config;
	if(substr($settings[$key],0,2)==='* ')
		$settings[$key] = trim($settings[$key],'* ');
	else
		$settings[$key] = $default_config[$key];
}
