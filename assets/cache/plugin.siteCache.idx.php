<?php
return array (
  'Forgot Manager Login' => 'include_once($modx->config[\'base_path\'] . \'assets/plugins/fmp/fmp.class.inc.php\');
$forgot = new ForgotManagerPassword();
$forgot->run();
',
  'ManagerManager' => '// You can put your ManagerManager rules EITHER in a chunk OR in an external file - whichever suits your development style the best

// To use an external file, put your rules in /assets/plugins/managermanager/mm_rules.inc.php
// (you can rename default.mm_rules.inc.php and use it as an example)
// The chunk SHOULD have php opening tags at the beginning and end

// If you want to put your rules in a chunk (so you can edit them through the Manager),
// create the chunk, and enter its name in the configuration tab.
// The chunk should NOT have php tags at the beginning or end

// ManagerManager requires jQuery 1.7+
// The URL to the jQuery library. Choose from the configuration tab whether you want to use
// a local copy (which defaults to the jQuery library distributed with ModX 1.0.1)
// a remote copy (which defaults to the Google Code hosted version)
// or specify a URL to a custom location.

// You don\'t need to change anything else from here onwards
//-------------------------------------------------------

// Run the main code
$mm_path = $modx->config[\'base_path\'] . \'assets/plugins/managermanager/mm.inc.php\';
include_once($mm_path);
$mm = new MANAGERMANAGER();
$mm->run();
',
  'ManagerManagerProps' => '&config_chunk=Configuration Chunk;text;mm_rules; ',
  'TinyMCE Rich Text Editor' => 'require MODX_BASE_PATH.\'assets/plugins/tinymce/plugin.tinymce.php\';',
  'TinyMCE Rich Text EditorProps' => '&customparams=Custom Parameters;textarea;valid_elements : "*[*]", &mce_formats=Block Formats;text;p,h1,h2,h3,h4,h5,h6,div,blockquote,code,pre &entity_encoding=Entity Encoding;list;named,numeric,raw;named &entities=Entities;text; &mce_path_options=Path Options;list;Site config,Absolute path,Root relative,URL,No convert;Site config &mce_resizing=Advanced Resizing;list;true,false;true &disabledButtons=Disabled Buttons;text; &link_list=Link List;list;enabled,disabled;enabled &webtheme=Web Theme;list;simple,editor,creative,custom;simple &webPlugins=Web Plugins;text;style,advimage,advlink,searchreplace,contextmenu,paste,fullscreen,xhtmlxtras,media &webButtons1=Web Buttons 1;text;undo,redo,selectall,|,pastetext,pasteword,|,search,replace,|,hr,charmap,|,image,link,unlink,anchor,media,|,cleanup,removeformat,|,fullscreen,code,help &webButtons2=Web Buttons 2;text;bold,italic,underline,strikethrough,sub,sup,|,|,blockquote,bullist,numlist,outdent,indent,|,justifyleft,justifycenter,justifyright,justifyfull,|,styleselect,formatselect,|,styleprops &webButtons3=Web Buttons 3;text; &webButtons4=Web Buttons 4;text; &webAlign=Web Toolbar Alignment;list;ltr,rtl;ltr &width=Width;text;95% &height=Height;text;500 ',
);