<?php
$custom_plugins  = 'visualblocks,inlinepopups,autosave,save,advlist,style,fullscreen,advimage,paste,advlink,media,contextmenu,table';
$custom_buttons1 = 'undo,redo,|,bold,forecolor,backcolor,strikethrough,formatselect,fontsizeselect,pastetext,pasteword,code,|,fullscreen,help';
$custom_buttons2 = 'image,media,link,unlink,anchor,|,justifyleft,justifycenter,justifyright,|,bullist,numlist,|,blockquote,outdent,indent,|,table,hr,|,visualblocks,styleprops,removeformat';
$css_selectors   = '左寄せ=justifyleft;右寄せ=justifyright';

$params['theme']       = (empty($params['theme']))          ? 'editor' : $params['theme'];
$ph['custom_plugins']  = (empty($params['custom_plugins']))  ? $custom_plugins  : $params['custom_plugins'];
$ph['custom_buttons1'] = (empty($params['custom_buttons1'])) ? $custom_buttons1 : $params['custom_buttons1'];
$ph['custom_buttons2'] = (empty($params['custom_buttons2'])) ? $custom_buttons2 : $params['custom_buttons2'];
$ph['custom_buttons3'] = $params['custom_buttons3'];
$ph['custom_buttons4'] = $params['custom_buttons4'];
$ph['css_selectors']   = (!isset($params['css_selectors']))  ? $css_selectors   : $params['css_selectors'];
$ph['mce_entermode']   = (empty($params['mce_entermode'])) ? 'p' : $params['mce_entermode'];
$ph['mce_schema']      = (empty($params['mce_schema'])) ? 'html4' : $params['mce_schema'];
$ph['mce_element_format'] = (empty($params['mce_element_format'])) ? 'xhtml' : $params['mce_element_format'];
