<?php
/**
 * QuickManager+
 *
 * @author      Mikko Lammi, www.maagit.fi
 * @license     GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @version     1.5.5r5 updated 12/01/2011
 */

// Replace [*#tv*] with QM+ edit TV button placeholders
if (($tvbuttons == 'true') && ($modx->event->name == 'OnParseDocument'))
{
	$output = &$modx->documentOutput;
	if(strpos($output,'[*#')===false) $m = false;
	else                              $m = $modx->getTagsFromContent($output,'[*#','*]');
	if(!empty($m)) {
    	foreach($m[1] as $i=>$v) {
    		$s = $m[0][$i];
    		if(strpos($v,':')!==false) $v = substr($v,0,strpos($v,':'));
    		$output = str_replace($s,"<!-- {$tvbclass} {$v} -->{$s}", $output);
    	}
	}
}

if(class_exists('Qm')) return;

class Qm {
	var $modx;

    //_______________________________________________________
	function __construct(&$modx, $params=array())
	{
		if(isset($_GET['a']) && $_GET['a']==='83') return;
		
		$this->modx = $modx;
		if(empty($params) || count($params)==0)
		{
			$modx->documentOutput = 'QuickManagerをインストールし直してください。';
			return;
		}
		extract($params);
		
		if (isset($disabled) && $disabled  != '')
		{
			$arr = explode(',', $disabled );
			if (in_array($modx->documentIdentifier, $arr)) return;
		}
		
		// Get plugin parameters
		$this->jqpath = 'manager/media/script/jquery/jquery.min.js';
		$this->loadfrontendjq = $loadfrontendjq;
		$this->noconflictjq = $noconflictjq;
		$this->loadtb = $loadtb;
		$this->tbwidth = $tbwidth;
		$this->tbheight = $tbheight;
		$this->hidefields = $hidefields;
		$this->hidetabs = isset($hidetabs) ? $hidetabs : '';;
		$this->hidesections = isset($hidesections) ? $hidesections : '';
		$this->addbutton = $addbutton;
		$this->tpltype = $tpltype;
		$this->tplid = isset($tplid) ? $tplid : '';
		$this->custombutton = isset($custombutton)? $custombutton : '';
		$this->managerbutton = $managerbutton;
		$this->logout = $logout;
		$this->autohide = $autohide;
		$this->editbuttons = $editbuttons;
		$this->editbclass = $editbclass;
		$this->newbuttons = $newbuttons;
		$this->newbclass = $newbclass;
		$this->tvbuttons = $tvbuttons;
		$this->tvbclass = $tvbclass;
		
		if(!isset($version) || version_compare($version,'1.5.5r5','<'))
		{
			$modx->documentOutput = 'QuickManagerをアップデートしてください。';
			return;
		}
		
		// Includes
		include_once(MODX_BASE_PATH.'assets/plugins/qm/mcc.class.php');
		
		// Run plugin
		$this->Run();
	}
	
	//_______________________________________________________
	function Run()
	{
		// Include MODx manager language file
		global $modx, $_lang;
		
		// Get manager language
		$manager_language = $this->modx->config['manager_language'];
		
		// Get event
		$e = &$this->modx->event;
		
		// Run plugin based on event
		switch ($e->name)
		{
			// Save document
			case 'OnDocFormSave':
				// Saving process for Qm only
				if(intval($_REQUEST['quickmanager']) == 1)
				{
					$id = $e->params['id'];
					$key = $id;
					
					// Normal saving document procedure stops to redirect => Before redirecting secure documents and clear cache
					
					// Secure web documents - flag as private (code from: manager/processors/save_content.processor.php)
					include_once(MODX_CORE_PATH . 'secure_web_documents.inc.php');
					secureWebDocument($key);
					
					// Secure manager documents - flag as private (code from: manager/processors/save_content.processor.php)
					include_once(MODX_CORE_PATH . 'secure_mgr_documents.inc.php');
					secureMgrDocument($key);
					
					// Clear cache
					$this->clearCache();
					
					// Different doc to be refreshed than the one we are editing?
					if (isset($_POST['qmrefresh']))
					{
						$id = intval($_POST['qmrefresh']);
					}
					
					// Redirect to clearer page which refreshes parent window and closes modal box frame
					$url = $this->modx->makeUrl($id,'','','full');
					$delim = (strpos($url,'?')!==false) ? '&' : '?';
					$this->modx->sendRedirect("{$url}{$delim}quickmanagerclose=1", 0, 'REDIRECT_HEADER', 'HTTP/1.1 301 Moved Permanently');
				}
				break;
				
			// Display page in front-end
			case 'OnWebPagePrerender':
				if($modx->directParse==1) return;
				
				if($modx->documentObject['contentType']!=='text/html') return;
				if($modx->documentObject['content_dispo']==='1') return;
				
				// Include_once the language file
				if(!isset($manager_language) || !is_file(MODX_CORE_PATH . "lang/{$manager_language}.inc.php"))
				{
					$manager_language = 'english'; // if not set, get the english language file.
				}
				// Include default language
				include_once(MODX_CORE_PATH . 'lang/english.inc.php');
				
				// Include user language
				if($manager_language!="english" && is_file(MODX_CORE_PATH . "lang/{$manager_language}.inc.php"))
				{
					include_once(MODX_CORE_PATH . "lang/{$manager_language}.inc.php");
				}
				
				// Get document id
				$docID = $this->modx->documentIdentifier;
				
				// Get page output
				$output = &$this->modx->documentOutput;
				
				// Close modal box after saving (previously close.php)
				if (isset($_GET['quickmanagerclose']))
				{
					// Set url to refresh
					$url = $this->modx->makeUrl($docID, '', '', 'full');
					$output = '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title></title>
</head>
<body onload="javascript: parent.location.href = \'' . $url . '\';">
</body>
</html>
';
					break;
				}
				
				// QM+ TV edit
				if(isset($_GET['quickmanagertv'] ) && intval($_GET['quickmanagertv'] == 1) && $_GET['tvname'] != '' && $this->tvbuttons == 'true')
				{
					$output = include_once('edit_tv.inc');
				}
			
			// QM+ with toolbar
			else
			{
				if(isset($_SESSION['mgrValidated']) && (!isset($_REQUEST['z']) || $_REQUEST['z'] != 'manprev'))
				{
					// If logout break here
					if(isset($_REQUEST['logout']))
					{
						$this->Logout();
						break;
					}
					$userID = $_SESSION['mgrInternalKey'];
					
					// Edit button
					
					$editButton = '
<li class="qmEdit">
<a class="qmButton qmEdit colorbox" href="'.$this->modx->config['site_url'].'manager/index.php?a=27&amp;id='.$docID.'&amp;quickmanager=1"><span> '.$_lang['edit_resource'].'</span></a>
</li>
';
					// Check if user has manager access to current document
					$access = $this->checkAccess();
					
					// Does user have permissions to edit document
					if(!isset($controls)) $controls = '';
					if($access) $controls .= $editButton;
					
					if ($this->addbutton == 'true' && $access)
					{
						// Add button
						$addButton = '
<li class="qmAdd">
<a class="qmButton qmAdd colorbox" href="'.$this->modx->config['site_url'].'manager/index.php?a=4&amp;pid='.$docID.'&amp;quickmanager=1"><span>'.$_lang['create_resource_here'].'</span></a>
</li>
';
						
						// Does user have permissions to add document
						if($this->modx->hasPermission('new_document')) $controls .= $addButton;
					}
				
					// Custom add buttons if not empty and enough permissions
					if ($this->custombutton != '')
					{
						$this->custombutton = $this->modx->mergeDocumentContent($this->custombutton);
						$this->custombutton = $this->modx->mergeSettingsContent($this->custombutton);
						$this->custombutton = $this->modx->mergeChunkContent($this->custombutton);
						$this->custombutton = $this->modx->evalSnippets($this->custombutton);
						// Handle [~id~] links
						$this->custombutton = $this->modx->rewriteUrls($this->custombutton);
						
						$buttons = explode("||", $this->custombutton); // Buttons are divided by "||"
						
						// Custom buttons class index
						$i = 0;
						
						// Parse buttons
						foreach($buttons as $key => $field)
						{
							$i++;
							
							$field = substr($field, 1, -1); // Trim "'" from beginning and from end
							$buttonParams = explode("','", $field); // Button params are divided by "','"
							
							$buttonTitle = $buttonParams[0];
							$buttonAction = $buttonParams[1]; // Contains URL if this is not add button
							$buttonParentId = $buttonParams[2]; // Is empty is this is not add button
							$buttonTplId = $buttonParams[3];
							
							// Button visible for all
							if ($buttonParams[4] == '')
							{
								$showButton = TRUE;
							}
							else
							{
								// Button is visible for specific user roles
								$showButton = FALSE;
								// Get user roles the button is visible for
								$buttonRoles = explode(",", $buttonParams[4]); // Roles are divided by ','
								
								// Check if user role is found
								foreach($buttonRoles as $key => $field)
								{
									if ($field == $_SESSION['mgrRole'])
									{
										$showButton = TRUE;
									}
								}
							}
						
							// Show custom button
							if ($showButton)
							{
								switch ($buttonAction)
								{
									case 'new':
										$customButton = '
<li class="qm-custom-'.$i.' qmCustom">
<a class="qmButton qmCustom colorbox" href="'.$this->modx->config['site_url'].'manager/index.php?a=4&amp;pid='.$buttonParentId.'&amp;quickmanager=1&amp;customaddtplid='.$buttonTplId.'"><span>'.$buttonTitle.'</span></a>
</li>
';
										break;
									case 'link':
										$customButton  = '
<li class="qm-custom-'.$i.' qmCustom">
<a class="qmButton qmCustom" href="'.$buttonParentId.'" ><span>'.$buttonTitle.'</span></a>
</li>
';
										break;
									case 'modal':
										$customButton  = '
<li class="qm-custom-'.$i.' qmCustom">
<a class="qmButton qmCustom colorbox" href="'.$buttonParentId.'" ><span>'.$buttonTitle.'</span></a>
</li>
';
										break;
								}
								$controls .= $customButton;
							}
						}
					}
					
					// Go to Manager button
					if ($this->managerbutton == 'true')
					{
						$managerButton  = '
<li class="qmManager">
<a class="qmButton qmManager" title="'.$_lang['manager'].'" href="'.$this->modx->config['site_url'].'manager/" ><span>'.$_lang['manager'].'</span></a>
</li>
';
						$controls .= $managerButton;
					}
					// Logout button
					$logout = $this->modx->config['site_url'].'manager/index.php?a=8&amp;quickmanager=logout&amp;logoutid='.$docID;
					$logoutButton  = '
<li class="qmLogout">
<a id="qmLogout" class="qmButton qmLogout" title="'.$_lang['logout'].'" href="'.$logout.'" ><span>'.$_lang['logout'].'</span></a>
</li>
';
					$controls .= $logoutButton;
					
					// Add action buttons
					$editor = '
<div id="qmEditorClosed"></div>

<div id="qmEditor">

<ul>
<li id="qmClose"><a class="qmButton qmClose" href="#" onclick="javascript: return false;">X</a></li>
<li><a id="qmLogoClose" class="qmClose" href="#" onclick="javascript: return false;"></a></li>
'.$controls.'
</ul>
</div>';
					$css = '
<link rel="stylesheet" type="text/css" href="'.$this->modx->config['site_url'].'assets/plugins/qm/css/style.css" />
';
			
					// Autohide toolbar? Default: true
					if ($this->autohide == 'false')
					{
						$css .= '
<style type="text/css">
#qmEditor, #qmEditorClosed { top: 0px; }
</style>
';
					}
					
					// Insert jQuery and ColorBox in head if needed
					if ($this->loadfrontendjq == 'true')
					{
						if(!isset($head)) $head = '';
						$head .= '<script src="'.$this->modx->config['site_url'].$this->jqpath.'" type="text/javascript"></script>';
					}
					if ($this->loadtb == 'true')
					{
						$head .= '
<link type="text/css" media="screen" rel="stylesheet" href="'.$this->modx->config['site_url'].'assets/plugins/qm/css/colorbox.css" />
<style type="text/css">
	.cboxIE #cboxTopLeft{background:transparent; filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='.$this->modx->config['site_url'].'assets/plugins/qm/css/images/internet_explorer/borderTopLeft.png, sizingMethod=\'scale\');}
	.cboxIE #cboxTopCenter{background:transparent; filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='.$this->modx->config['site_url'].'assets/plugins/qm/css/images/internet_explorer/borderTopCenter.png, sizingMethod=\'scale\');}
	.cboxIE #cboxTopRight{background:transparent; filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='.$this->modx->config['site_url'].'assets/plugins/qm/css/images/internet_explorer/borderTopRight.png, sizingMethod=\'scale\');}
	.cboxIE #cboxBottomLeft{background:transparent; filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='.$this->modx->config['site_url'].'assets/plugins/qm/css/images/internet_explorer/borderBottomLeft.png, sizingMethod=\'scale\');}
	.cboxIE #cboxBottomCenter{background:transparent; filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='.$this->modx->config['site_url'].'assets/plugins/qm/css/images/internet_explorer/borderBottomCenter.png, sizingMethod=\'scale\');}
	.cboxIE #cboxBottomRight{background:transparent; filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='.$this->modx->config['site_url'].'assets/plugins/qm/css/images/internet_explorer/borderBottomRight.png, sizingMethod=\'scale\');}
	.cboxIE #cboxMiddleLeft{background:transparent; filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='.$this->modx->config['site_url'].'assets/plugins/qm/css/images/internet_explorer/borderMiddleLeft.png, sizingMethod=\'scale\');}
	.cboxIE #cboxMiddleRight{background:transparent; filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='.$this->modx->config['site_url'].'assets/plugins/qm/css/images/internet_explorer/borderMiddleRight.png, sizingMethod=\'scale\');}
</style>
<script type="text/javascript" src="'.$this->modx->config['site_url'].'assets/plugins/qm/js/jquery.colorbox-min.js"></script>
';
					}
					// Insert ColorBox jQuery definitions for QuickManager+
					$head .= '<script type="text/javascript">';
			
					// jQuery in noConflict mode
					if ($this->noconflictjq == 'true')
					{
						$head .= '
						var $j = jQuery.noConflict();
						$j(function()
						';
						$jvar = 'j';
					}
					else
					{
						// jQuery in normal mode
						$head .= '$(function()';
						$jvar = '';
					}
					$head .= '
{
$'.$jvar.'("a.colorbox").colorbox({width:"'.$this->tbwidth.'", height:"'.$this->tbheight.'", iframe:true, overlayClose:false, opacity:0.5, transition:"fade", speed:150});

// Hide QM+ if cookie found
if (getCookie("hideQM") == 1)
{
	$'.$jvar.'("#qmEditor").css({"display":"none"});
	$'.$jvar.'("#qmEditorClosed").css({"display":"block"});
}

// Hide QM+
$'.$jvar.'(".qmClose").click(function ()
{
	$'.$jvar.'("#qmEditor").hide("normal");
	$'.$jvar.'("#qmEditorClosed").show("normal");
	document.cookie = "hideQM=1; path=/;";
});

// Show QM+
$'.$jvar.'("#qmEditorClosed").click(function ()
{
	{
		$'.$jvar.'("#qmEditorClosed").hide("normal");
		$'.$jvar.'("#qmEditor").show("normal");
		document.cookie = "hideQM=0; path=/;";
	}
});

});

function getCookie(cookieName)
{
	var results = document.cookie.match ( "(^|;) ?" + cookieName + "=([^;]*)(;|$)" );
	
	if (results) return (unescape(results[2]));
	else return null;
}
</script>
';
			
						// Insert QM+ css in head
						$head .= $css;
						
						// Place QM+ head information in head, just before </head> tag
						$output = preg_replace('~(</head>)~i', $head . '\1', $output);
						
						// Insert editor toolbar right after <body> tag
						$output = preg_replace('~(<body[^>]*>)~i', '\1' . $editor, $output);
						
						// Search and create edit buttons in to the content
						if ($this->editbuttons == 'true' && $access)
						{
							$output = preg_replace('/<!-- '.$this->editbclass.' ([0-9]+) ([\'|\\"])([^\\"\'\(\)<>!?]+)\\2 -->/', '<span class="'.$this->editbclass.'"><a class="colorbox" href="'.$this->modx->config['site_url'].'manager/index.php?a=27&amp;id=$1&amp;quickmanager=1&amp;qmrefresh='.$docID.'"><span>$3</span></a></span>', $output);
						}
						
						// Search and create new document buttons in to the content
						if ($this->newbuttons == 'true' && $access)
						{
							$output = preg_replace('/<!-- '.$this->newbclass.' ([0-9]+) ([0-9]+) ([\'|\\"])([^\\"\'\(\)<>!?]+)\\3 -->/', '<span class="'.$this->newbclass.'"><a class="colorbox" href="'.$this->modx->config['site_url'].'manager/index.php?a=4&amp;pid=$1&amp;quickmanager=1&amp;customaddtplid=$2"><span>$4</span></a></span>', $output);
						}
						
						// Search and create new document buttons in to the content
						if ($this->tvbuttons == 'true' && $access)
						{
							// Set and get user doc groups for TV permissions
							$this->docGroup = '';
							$mrgDocGroups = $_SESSION['mgrDocgroups'];
							if (!empty($mrgDocGroups)) $this->docGroup = implode(",", $mrgDocGroups);
							
							// Create TV buttons and check TV permissions
							$output = preg_replace_callback('/<!-- '.$this->tvbclass.' ([^\\"\'\(\)<>!?]+) -->/', array(&$this, 'createTvButtons'), $output);
						}
					}
				}
				break;
			
			// Edit document in ThickBox frame (MODx manager frame)
			case 'OnDocFormPrerender':
				// If there is Qm call, add control buttons and modify to edit document page
				if (isset($_REQUEST['quickmanager']) && intval($_REQUEST['quickmanager']) == 1)
				{
					global $docObject;
					
					// Set template for new document, action = 4
					if(intval($_GET['a']) == 4)
					{
						// Custom add button
						if (isset($_GET['customaddtplid']))
						{
							// Set template
							$docObject['template'] = intval($_GET['customaddtplid']);
						}
						else
						{
							// Normal add button
							if($this->tpltype==='config') $this->tpltype = $this->modx->config['auto_template_logic'];
							switch ($this->tpltype)
							{
								case 'parent': // Template type is parent
									// Get parent document id
									$pid = $docObject['parent'] ? $docObject['parent'] : intval($_REQUEST['pid']);
									
									// Get parent document
									$parent = $this->modx->getDocument($pid);
									
									// Set parent template
									$docObject['template'] = $parent['template'];
									break;
									
								case 'id': // Template is specific id
									$docObject['template'] = $this->tplid;
									break;
								case 'selected': // Template is inherited by Inherit Selected Template plugin
								case 'sibling':
									// Get parent document id
									$pid = $docObject['parent'] ? $docObject['parent'] : intval($_REQUEST['pid']);
									
									if ($this->modx->config['auto_template_logic'] === 'sibling') {
										// Eoler: template_autologic in Evolution 1.0.5+
										// http://tracker.modx.com/issues/9586
										$tv = array();
										$sibl = $this->modx->getDocumentChildren($pid, 1, 0, 'template', '', 'menuindex', 'ASC', 1);
										if(empty($sibl)) {
											$sibl = $this->modx->getDocumentChildren($pid, 0, 0, 'template', '', 'menuindex', 'ASC', 1);
										}
										if(!empty($sibl)) {
											$tv['value'] = $sibl[0]['template'];
										}
										else $tv['value'] = ''; // Added by yama
									}
									else
									{
										// Get inheritTpl TV
										$tv = $this->modx->getTemplateVar('inheritTpl', '', $pid);
									}

									
									// Set template to inherit
									if ($tv['value'] != '') $docObject['template'] = $tv['value'];
									else                    $docObject['template'] = $this->modx->config['default_template'];
									break;
								case 'system':
									$docObject['template'] = $this->modx->config['default_template'];
									break;
							}
						}
					}
					
					// Manager control class
					$mc = new Mcc();
					$mc->noconflictjq = 'true';
					
					// Get jQuery conflict mode
					if ($this->noconflictjq == 'true') $jq_mode = '$j';
					else                               $jq_mode = '$';
					
					// Hide default manager action buttons
					$mc->addLine($jq_mode . '("#actions").hide();');
					
					// Get MODx theme
					$qm_theme = $this->modx->config['manager_theme'];
					
					// Get doc id
					if    (isset($_REQUEST['id']))  $doc_id = (int)$_REQUEST['id'];
					elseif(isset($_REQUEST['pid'])) $doc_id = (int)$_REQUEST['pid'];
					else $doc_id = 0;
					
					// Add action buttons
					$url = $this->modx->makeUrl($doc_id,'','','full');
					$mc->addLine('var controls = "<div style=\"padding:4px 0;position:fixed;top:10px;right:-10px;z-index:1000\" id=\"qmcontrols\" class=\"actionButtons\"><ul><li class=\"primary\"><a href=\"#\" onclick=\"documentDirty=false;gotosave=true;document.mutate.save.click();return false;\"><img src=\"media/style/'.$qm_theme.'/images/icons/save.png\" />'.$_lang['save'].'</a></li><li><a href=\"#\" onclick=\"parent.location.href=\''.$url.'\'; return false;\"><img src=\"media/style/'.$qm_theme.'/images/icons/stop.png\"/>'.$_lang['cancel'].'</a></li></ul></div>";');
					
					// Modify head
					$mc->head = '<script type="text/javascript">document.body.style.display="none";</script>';
					
					// Add control button
					$mc->addLine($jq_mode . '("body").prepend(controls);');
					
				// Hide fields to from front-end editors
					if ($this->hidefields != '')
					{
						$hideFields = explode(",", $this->hidefields);
						foreach($hideFields as $key => $field)
						{
							$mc->hideField($field);
						}
					}
					// Hide tabs to from front-end editors
					if ($this->hidetabs != '')
					{
						$hideTabs = explode(",", $this->hidetabs);
						
						foreach($hideTabs as $key => $field)
						{
							$mc->hideTab($field);
						}
					}
				
					// Hide sections from front-end editors
					if ($this->hidesections != '')
					{
						$hideSections = explode(",", $this->hidesections);
						
						foreach($hideSections as $key => $field)
						{
							$mc->hideSection($field);
						}
					}
					
					// Hidden field to verify that QM+ call exists
					$hiddenFields = '<input type="hidden" name="quickmanager" value="1" />';
					
					// Different doc to be refreshed?
					if (isset($_REQUEST['qmrefresh']))
					{
						$hiddenFields .= '<input type="hidden" name="qmrefresh" value="'.intval($_REQUEST['qmrefresh']).'" />';
					}
					
					// Output
					$e->output($mc->Output().$hiddenFields);
				}
				break;
			case 'OnManagerLogout': // Where to logout
				// Only if cancel editing the document and QuickManager is in use
				if ($_REQUEST['quickmanager'] == 'logout')
				{
					// Redirect to document id
					if ($this->logout != 'manager')
					{
						$url = $this->modx->makeUrl($_REQUEST['logoutid'],'','','full');
						$this->modx->sendRedirect($url, 0, 'REDIRECT_HEADER', 'HTTP/1.1 301 Moved Permanently');
					}
				}
				break;
		}
	}

	// Check if user has manager access permissions to current document
	//_______________________________________________________
	function checkAccess()
	{
		$access = FALSE;
		
		// If user is admin (role = 1)
		if ($_SESSION['mgrRole'] == 1) $access = TRUE;
		elseif(!isset($this->modx->documentIdentifier) || empty($this->modx->documentIdentifier))
		{
			$access = FALSE;
		}
		else
		{
			$docID = $this->modx->documentIdentifier;
			
			// Database table
			$table= $this->modx->getFullTableName("document_groups");
			
			// Check if current document is assigned to one or more doc groups
			$result= $this->modx->db->select('id',$table,"document='{$docID}'");
			$rowCount= $this->modx->db->getRecordCount($result);
			
			// If document is assigned to one or more doc groups, check access
			if ($rowCount >= 1)
			{
				// Get document groups for current user
				$mrgDocGroups = $_SESSION['mgrDocgroups'];
				if (!empty($mrgDocGroups))
				{
					$docGroup = implode(",", $mrgDocGroups);
					
					// Check if user has access to current document
					$result= $this->modx->db->select('id',$table,"document = {$docID} AND document_group IN ({$docGroup})");
					$rowCount = $this->modx->db->getRecordCount($result);
					
					if ($rowCount >= 1) $access = TRUE;
				}
				else $access = FALSE;
			}
			else $access = TRUE;
		}
		return $access;
	}
	
	// Function from: manager/processors/cache_sync.class.processor.php
	//_____________________________________________________
	function getParents($id, $path = '')
	{
		// modx:returns child's parent
		global $modx;
		if(empty($this->aliases))
		{
			$qh = $modx->db->select("id, IF(alias='', id, alias) AS alias, parent",$modx->getFullTableName('site_content'));
			if ($qh && $modx->db->getRecordCount($qh) > 0)
			{
				while ($row = $modx->db->getRow($qh))
				{
					$this->aliases[$row['id']] = $row['alias'];
					$this->parents[$row['id']] = $row['parent'];
				}
			}
		}
		if (isset($this->aliases[$id]))
		{
			$path = $this->aliases[$id] . ($path != '' ? '/' : '') . $path;
			return $this->getParents($this->parents[$id], $path);
		}
		return $path;
	}

	// Create TV buttons if user has permissions to TV
	//_____________________________________________________
	function createTvButtons($matches)
	{
		$access = FALSE;
		$table = $this->modx->getFullTableName('site_tmplvar_access');
		$docID = $this->modx->documentIdentifier;
		
		// Get TV caption for button title
		$tv = $this->modx->getTemplateVar($matches[1]);
		$caption = $tv['caption'];
		
		// If caption is empty this must be a "build-in-tv-field" like pagetitle etc.
		if ($caption == '')
		{
			// Allowed for all
			$access = TRUE;
			
			// Resolve caption
			$caption = $this->getDefaultTvCaption($matches[1]);
		}
		else
		{
			// Check TV access
			$access = $this->checkTvAccess($tv['id']);
		}
		
		// Return TV button link if access
		if ($access && $caption != '')
		{
			$tvname = urlencode($matches[1]);
			return '<span class="'.$this->tvbclass.'"><a class="colorbox" href="'.$this->modx->config['site_url'].'index.php?id='.$docID.'&amp;quickmanagertv=1&amp;tvname='.$tvname.'"><span>'.$caption.'</span></a></span>';
		}
	}

	// Check user access to TV
	//_____________________________________________________
	function checkTvAccess($tvId)
	{
		$access = FALSE;
		$table = $this->modx->getFullTableName('site_tmplvar_access');
		
		// If user is admin (role = 1)
		if ($_SESSION['mgrRole'] == 1 && !$access) { $access = TRUE; }
		
		// Check permission to TV, is TV in document group?
		if (!$access)
		{
			$result = $this->modx->db->select('id',$table,"tmplvarid = {$tvId}");
			$rowCount = $this->modx->db->getRecordCount($result);
			// TV is not in any document group
			if ($rowCount == 0) { $access = TRUE; }
		}
		// Check permission to TV, TV is in document group
		if (!$access && $this->docGroup != '')
		{
			$result = $this->modx->db->select('id',$table,"tmplvarid = {$tvId} AND documentgroup IN ({$this->docGroup})");
			$rowCount = $this->modx->db->getRecordCount($result);
			if ($rowCount >= 1) { $access = TRUE; }
		}
		return $access;
	}
	
	// Get default TV ("build-in" TVs) captions
	//_____________________________________________________
	function getDefaultTvCaption($name)
	{
		global $_lang;
		$caption = '';
		switch ($name)
		{
			case 'pagetitle'    : $caption = $_lang['resource_title']; break;
			case 'longtitle'    : $caption = $_lang['long_title']; break;
			case 'description'  : $caption = $_lang['resource_description']; break;
			case 'content'      : $caption = $_lang['resource_content']; break;
			case 'menutitle'    : $caption = $_lang['resource_opt_menu_title']; break;
			case 'introtext'    : $caption = $_lang['resource_summary']; break;
		}
		return $caption;
	}
	
	// Check that a document isn't locked for editing
	//_____________________________________________________
	function checkLocked()
	{
		$tbl_active_users = $this->modx->getFullTableName('active_users');
		$pageId = $this->modx->documentIdentifier;
		$locked = TRUE;
		$userId = $_SESSION['mgrInternalKey'];
		$where = "(`action` = 27) AND `internalKey` != '{$userId}' AND `id` = '{$pageId}'";
		$result = $this->modx->db->select('internalKey',$tbl_active_users,$where);
		
		if ($this->modx->db->getRecordCount($result) === 0)
		{
			$locked = FALSE;
		}
		
		return $locked;
	}
	
	// Set document locked on/off
	//_____________________________________________________
	function setLocked($locked)
	{
		$tbl_active_users = $this->modx->getFullTableName('active_users');
		$pageId = $this->modx->documentIdentifier;
		$userId = $_SESSION['mgrInternalKey'];
		
		// Set document locked
		if ($locked == 1)
		{
			$fields['id']     = $pageId;
			$fields['action'] = 27;
		}
		else
		{
			// Set document unlocked
			$fields['id'] = 'NULL';
			$fields['action'] = 2;
		}
		$where = "internalKey = '{$userId}'";
		$result = $this->modx->db->update($fields, $tbl_active_users, $where);
	}
	
	// Save TV
	//_____________________________________________________
	function saveTv($tvName)
	{
		$tbl_site_tmplvar_contentvalues = $this->modx->getFullTableName('site_tmplvar_contentvalues');
		$tbl_site_content = $this->modx->getFullTableName('site_content');
		$pageId = $this->modx->documentIdentifier;
		$result = null;
		$time = time();
		$user = $_SESSION['mgrInternalKey'];
		$tvId = isset($_POST['tvid'])&&preg_match('@^[1-9][0-9]*$@',$_POST['tvid']) ? $_POST['tvid'] : 0;
		if($tvId) $tvContent = isset($_POST['tv'.$tvId])   ? $_POST['tv'.$tvId]   : '';
		else      $tvContent = isset($_POST['tv'.$tvName]) ? $_POST['tv'.$tvName] : '';
		$tvContentTemp = '';
		
		// Escape TV content
		$tvName = $this->modx->db->escape($tvName);
		$tvContent = $this->modx->db->escape($tvContent);
		
		// Invoke OnBeforeDocFormSave event
        $tmp = array('mode'=>'upd', 'id'=>$pageId);
		$this->modx->invokeEvent('OnBeforeDocFormSave', $tmp);
		
		// Handle checkboxes and other arrays, TV to be saved must be e.g. value1||value2||value3
		if (is_array($tvContent))
		{
			foreach($tvContent as $key => $value)
			{
				$tvContentTemp .= $value . '||';
			}
			$tvContentTemp = substr($tvContentTemp, 0, -2);  // Remove last ||
			$tvContent = $tvContentTemp;
		}
	
		// Save TV
		if ($tvId)
		{
			$where = "`tmplvarid` = '{$tvId}' AND `contentid` = '{$pageId}'";
			$result = $this->modx->db->select('id',$tbl_site_tmplvar_contentvalues,$where);
			
			// TV exists, update TV
			if($this->modx->db->getRecordCount($result))
			{
				$sql = "UPDATE {$tbl_site_tmplvar_contentvalues}
				SET `value` = '{$tvContent}'
				WHERE `tmplvarid` = '{$tvId}'
				AND `contentid` = '{$pageId}';";
			}
			else
			{
				// TV does not exist, create new TV
				$sql = "INSERT INTO {$tbl_site_tmplvar_contentvalues} (tmplvarid, contentid, value)
				VALUES('{$tvId}', '{$pageId}', '{$tvContent}');";
			}
			
			// Page edited by
			$this->modx->db->update(array('editedon'=>$time, 'editedby'=>$user), $tbl_site_content, 'id = "' . $pageId . '"');
		}
		else
		{
			// Save default field, e.g. pagetitle
			$sql = "UPDATE {$tbl_site_content}
			SET
			`{$tvName}` = '{$tvContent}',
			`editedon` = '{$time}',
			`editedby` = '{$user}'
			WHERE `id` = '{$pageId}';";
		}
		// Update TV
		if($sql) { $result = $this->modx->db->query($sql); }
		// Log possible errors
		if(!$result)
		{
			$this->modx->logEvent(0, 0, "<p>Save failed!</p><strong>SQL:</strong><pre>{$sql}</pre>", 'QuickManager+');
		}
		else
		{
			// No errors
			// Invoke OnDocFormSave event
      $tmp = array('mode'=>'upd', 'id'=>$pageId);
			$this->modx->invokeEvent('OnDocFormSave', $tmp);
			// Clear cache
			$this->clearCache();
		}
	}

    // Clear cache
	//_____________________________________________________
	function clearCache()
	{
		// Clear cache
		$this->modx->clearCache();
	}
	
	function get_img_prev_src()
	{
		if ($this->noconflictjq == 'true') $jq_mode = '$j';
		else                               $jq_mode = '$';
		
		$src = <<< EOT
<div id="qm-tv-image-preview"><img class="qm-tv-image-preview-drskip qm-tv-image-preview-skip" src="[+site_url+][tv_value+]" alt="" /></div>
<script type="text/javascript" charset="UTF-8">
{$jq_mode}(function()
{
	var previewImage = "#tv[+tv_name+]";
	var siteUrl = "[+site_url+]";
	
	OriginalSetUrl = SetUrl; // Copy the existing Image browser SetUrl function
	SetUrl = function(url, width, height, alt)
	{	// Redefine it to also tell the preview to update
		OriginalSetUrl(url, width, height, alt);
		{$jq_mode}(previewImage).trigger("change");
	}
	{$jq_mode}(previewImage).change(function()
	{
		{$jq_mode}("#qm-tv-image-preview").empty();
		if ({$jq_mode}(previewImage).val()!="" )
		{
			{$jq_mode}("#qm-tv-image-preview").append('<img class="qm-tv-image-preview-drskip qm-tv-image-preview-skip" src="' + siteUrl + {$jq_mode}(previewImage).val()  + '" alt="" />');
		}
		else
		{
			{$jq_mode}("#qm-tv-image-preview").append("");
		}
	});
});
</script>
EOT;
		return $src;
	}
}
