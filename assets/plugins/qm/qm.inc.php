<?php
/**
 * QuickManager+
 *
 * @author      Mikko Lammi, www.maagit.fi
 * @license     GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @version     1.5.6 updated 12/01/2011
 */

class Qm {
    public $modx;
    public $jqpath = '';

    public function __construct() {
    }

    private function tv_buttons() {
        global $modx;
        if(!evo()->isFrontend()) {
            return;
        }
        // Replace [*#tv*] with QM+ edit TV button placeholders
        if (event()->param('tvbuttons') != 'true') {
            return;
        }
        if (event()->name !== 'OnParseDocument') {
            return;
        }
        $output = &$modx->documentOutput;
        if(strpos($output,'[*#')===false) {
            return;
        }
        
        $m = evo()->getTagsFromContent(
            $output
            , '[*#', '*]'
        );
        if(!$m) {
            return;
        }
        foreach($m[1] as $i=>$v) {
            $output = str_replace(
                $m[0][$i]
                , sprintf(
                    '<!-- %s %s -->%s'
                    , event()->param('tvbclass')
                    , (strpos($v,':')!==false) ? substr($v, 0, strpos($v, ':')) : $v
                    , $m[0][$i]
                )
                , $output
            );
        }
    }

    private function init() {
        global $modx;
        if(getv('a')==83) {
            return;
        }

        $params = event()->params;
        $this->modx = $modx;
        if(!$params) {
            $modx->documentOutput = 'QuickManagerをインストールし直してください。';
            return;
        }
        extract($params);
        if ($this->config('disabled')) {
            $arr = explode(',', $this->config('disabled') );
            if (in_array($modx->documentIdentifier, $arr)) {
                return false;
            }
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

        if(!isset($version) || version_compare($version,'1.5.5r5','<')) {
            $modx->documentOutput = 'QuickManagerをアップデートしてください。';
            return false;
        }

        // Includes
        include_once(MODX_BASE_PATH.'assets/plugins/qm/mcc.class.php');
        return true;
    }

    function Run() {

        $this->tv_buttons();

        $rs = $this->init();
        if(!$rs) {
            return;
        }
        // Include MODx manager language file
        global $modx, $_lang;

        // Get manager language
        $manager_language = evo()->config['manager_language'];

        // Get event
        $e = &evo()->event;

        // Run plugin based on event
        switch ($e->name) {
            // Save document
            case 'OnDocFormSave':
                // Saving process for Qm only
                if((int)anyv('quickmanager') == 1) {
                    $id = $e->params['id'];
                    $key = $id;
                    include_once(MODX_CORE_PATH . 'secure_web_documents.inc.php');
                    secureWebDocument($key);

                    include_once(MODX_CORE_PATH . 'secure_mgr_documents.inc.php');
                    secureMgrDocument($key);

                    // Clear cache
                    $this->clearCache();

                    // Different doc to be refreshed than the one we are editing?
                    if (isset($_POST['qmrefresh'])) {
                        $id = (int)$_POST['qmrefresh'];
                    }

                    evo()->config['xhtml_urls'] = 0;
                    $url = evo()->makeUrl($id,'','quickmanagerclose=1','full');
                    evo()->sendRedirect($url, 0, 'REDIRECT_HEADER', 'HTTP/1.1 301 Moved Permanently');
                }
                break;

            // Display page in front-end
            case 'OnWebPagePrerender':
                if($modx->directParse==1) {
                    return;
                }

                if($modx->documentObject['contentType']!=='text/html') {
                    return;
                }
                if($modx->documentObject['content_dispo']==='1') {
                    return;
                }

                // Include_once the language file
                include_once(MODX_CORE_PATH . 'lang/' . $manager_language . '.inc.php');

                // Get document id
                $docID = evo()->documentIdentifier;

                // Get page output
                $output = &evo()->documentOutput;

                // Close modal box after saving (previously close.php)
                if (getv('quickmanagerclose')) {
                    // Set url to refresh
                    $url = evo()->makeUrl($docID, '', '', 'full');
                    exit(sprintf("<script>parent.location.href='%s';</script>",$url));
                    break;
                }

                // QM+ TV edit
                if(getv('quickmanagertv') == 1 && getv('tvname') != '' && $this->tvbuttons == 'true') {
                    $output = include('edit_tv.inc');
                }

                // QM+ with toolbar
                else {
                    if(sessionv('mgrValidated') && anyv('z') !== 'manprev') {
                        // If logout break here
                        if(anyv('logout')) {
                            $this->Logout();
                            break;
                        }
                        $userID = $_SESSION['mgrInternalKey'];

                        // Edit button

                        $editButton = '
<li class="qmEdit">
<a class="qmButton qmEdit colorbox" href="'.evo()->config['site_url'].'manager/index.php?a=27&amp;id='.$docID.'&amp;quickmanager=1"><span> '.$_lang['edit_resource'].'</span></a>
</li>
';
                        // Check if user has manager access to current document
                        $access = $this->checkAccess();

                        // Does user have permissions to edit document
                        $controls = '';
                        if($access) {
                            $controls .= $editButton;
                        }

                        if ($this->addbutton == 'true' && $access) {
                            // Add button
                            $addButton = '
<li class="qmAdd">
<a class="qmButton qmAdd colorbox" href="'.evo()->config['site_url'].'manager/index.php?a=4&amp;pid='.$docID.'&amp;quickmanager=1"><span>'.$_lang['create_resource_here'].'</span></a>
</li>
';

                            // Does user have permissions to add document
                            if(evo()->hasPermission('new_document')) {
                                $controls .= $addButton;
                            }
                        }

                        // Custom add buttons if not empty and enough permissions
                        if ($this->custombutton != '') {
                            $this->custombutton = evo()->mergeDocumentContent($this->custombutton);
                            $this->custombutton = evo()->mergeSettingsContent($this->custombutton);
                            $this->custombutton = evo()->mergeChunkContent($this->custombutton);
                            $this->custombutton = evo()->evalSnippets($this->custombutton);
                            // Handle [~id~] links
                            $this->custombutton = evo()->rewriteUrls($this->custombutton);

                            $buttons = explode("||", $this->custombutton); // Buttons are divided by "||"

                            // Custom buttons class index
                            $i = 0;

                            // Parse buttons
                            foreach($buttons as $key => $field) {
                                $i++;

                                $field = substr($field, 1, -1); // Trim "'" from beginning and from end
                                $buttonParams = explode("','", $field); // Button params are divided by "','"

                                $buttonTitle = $buttonParams[0];
                                $buttonAction = $buttonParams[1]; // Contains URL if this is not add button
                                $buttonParentId = $buttonParams[2]; // Is empty is this is not add button
                                $buttonTplId = $buttonParams[3];

                                // Button visible for all
                                if ($buttonParams[4] == '') {
                                    $showButton = TRUE;
                                } else {
                                    // Button is visible for specific user roles
                                    $showButton = FALSE;
                                    // Get user roles the button is visible for
                                    $buttonRoles = explode(",", $buttonParams[4]); // Roles are divided by ','

                                    // Check if user role is found
                                    foreach($buttonRoles as $mgrRole) {
                                        if ($mgrRole != $_SESSION['mgrRole']) {
                                            continue;
                                        }
                                        $showButton = TRUE;
                                        break;
                                    }
                                }

                                // Show custom button
                                if ($showButton) {
                                    switch ($buttonAction) {
                                        case 'new':
                                            $customButton = '
<li class="qm-custom-'.$i.' qmCustom">
<a class="qmButton qmCustom colorbox" href="'.evo()->config['site_url'].'manager/index.php?a=4&amp;pid='.$buttonParentId.'&amp;quickmanager=1&amp;customaddtplid='.$buttonTplId.'"><span>'.$buttonTitle.'</span></a>
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
                        if ($this->managerbutton == 'true') {
                            $managerButton  = '
<li class="qmManager">
<a class="qmButton qmManager" title="'.$_lang['manager'].'" href="'.evo()->config['site_url'].'manager/" ><span>'.$_lang['manager'].'</span></a>
</li>
';
                            $controls .= $managerButton;
                        }
                        // Logout button
                        $logout = evo()->config['site_url'].'manager/index.php?a=8&amp;quickmanager=logout&amp;logoutid='.$docID;
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
<link rel="stylesheet" type="text/css" href="'.evo()->config['site_url'].'assets/plugins/qm/css/style.css" />
';

                        // Autohide toolbar? Default: true
                        if ($this->autohide == 'false') {
                            $css .= '
<style type="text/css">
#qmEditor, #qmEditorClosed { top: 0px; }
</style>
';
                        }

                        // Insert jQuery and ColorBox in head if needed
                        $head = '';
                        if ($this->loadfrontendjq == 'true') {
                            $head .= '<script src="'.evo()->config['site_url'].$this->jqpath.'" type="text/javascript"></script>';
                        }
                        if ($this->loadtb == 'true') {
                            $head .= '
<link type="text/css" media="screen" rel="stylesheet" href="'.evo()->config['site_url'].'assets/plugins/qm/css/colorbox.css" />
<script type="text/javascript" src="'.evo()->config['site_url'].'assets/plugins/qm/js/jquery.colorbox-min.js"></script>
';
                        }
                        // Insert ColorBox jQuery definitions for QuickManager+
                        $head .= '<script type="text/javascript">';

                        // jQuery in noConflict mode
                        if ($this->noconflictjq == 'true') {
                            $head .= '
						var $j = jQuery.noConflict();
						$j(function()
						';
                            $jvar = 'j';
                        } else {
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
                        if ($this->editbuttons == 'true' && $access) {
                            $output = preg_replace('/<!-- '.$this->editbclass.' ([0-9]+) ([\'|\\"])([^\\"\'\(\)<>!?]+)\\2 -->/', '<span class="'.$this->editbclass.'"><a class="colorbox" href="'.evo()->config['site_url'].'manager/index.php?a=27&amp;id=$1&amp;quickmanager=1&amp;qmrefresh='.$docID.'"><span>$3</span></a></span>', $output);
                        }

                        // Search and create new document buttons in to the content
                        if ($this->newbuttons == 'true' && $access) {
                            $output = preg_replace('/<!-- '.$this->newbclass.' ([0-9]+) ([0-9]+) ([\'|\\"])([^\\"\'\(\)<>!?]+)\\3 -->/', '<span class="'.$this->newbclass.'"><a class="colorbox" href="'.evo()->config['site_url'].'manager/index.php?a=4&amp;pid=$1&amp;quickmanager=1&amp;customaddtplid=$2"><span>$4</span></a></span>', $output);
                        }

                        // Search and create new document buttons in to the content
                        if ($this->tvbuttons == 'true' && $access) {
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
                if ((int)anyv('quickmanager') == 1) {
                    global $docObject;

                    // Set template for new document, action = 4
                    if(getv('a') == 4) {
                        // Custom add button
                        if (getv('customaddtplid')) {
                            $docObject['template'] = (int)getv('customaddtplid');
                        } else {
                            // Normal add button
                            if($this->tpltype==='config') $this->tpltype = evo()->config['auto_template_logic'];
                            switch ($this->tpltype) {
                                case 'parent': // Template type is parent
                                    // Get parent document id
                                    $pid = $docObject['parent'] ? $docObject['parent'] : (int)anyv('pid');

                                    // Get parent document
                                    $parent = evo()->getDocument($pid);

                                    // Set parent template
                                    $docObject['template'] = $parent['template'];
                                    break;

                                case 'id': // Template is specific id
                                    $docObject['template'] = $this->tplid;
                                    break;
                                case 'selected': // Template is inherited by Inherit Selected Template plugin
                                case 'sibling':
                                    // Get parent document id
                                    $pid = $docObject['parent'] ? $docObject['parent'] : (int)anyv('pid');

                                    if (evo()->config['auto_template_logic'] === 'sibling') {
                                        // Eoler: template_autologic in Evolution 1.0.5+
                                        // http://tracker.modx.com/issues/9586
                                        $tv = array();
                                        $sibl = evo()->getDocumentChildren($pid, 1, 0, 'template', '', 'menuindex', 'ASC', 1);
                                        if(!$sibl) {
                                            $sibl = evo()->getDocumentChildren($pid, 0, 0, 'template', '', 'menuindex', 'ASC', 1);
                                        }
                                        if(!empty($sibl)) {
                                            $tv['value'] = $sibl[0]['template'];
                                        }
                                        else $tv['value'] = ''; // Added by yama
                                    } else {
                                        // Get inheritTpl TV
                                        $tv = evo()->getTemplateVar('inheritTpl', '', $pid);
                                    }


                                    // Set template to inherit
                                    if ($tv['value'] != '') {
                                        $docObject['template'] = $tv['value'];
                                    } else {
                                        $docObject['template'] = evo()->config['default_template'];
                                    }
                                    break;
                                case 'system':
                                    $docObject['template'] = evo()->config['default_template'];
                                    break;
                            }
                        }
                    }

                    // Manager control class
                    $mc = new Mcc();
                    $mc->noconflictjq = 'true';

                    // Get jQuery conflict mode
                    if ($this->noconflictjq == 'true') {
                        $jq_mode = '$j';
                    } else {
                        $jq_mode = '$';
                    }

                    // Hide default manager action buttons
                    $mc->addLine($jq_mode . '("#actions").hide();');

                    // Get MODx theme
                    $qm_theme = evo()->config['manager_theme'];

                    // Get doc id
                    if    (anyv('id')) {
                        $doc_id = (int)anyv('id');
                    } elseif(anyv('pid')) {
                        $doc_id = (int)anyv('pid');
                    } else {
                        $doc_id = 0;
                    }

                    // Add action buttons
                    $url = evo()->makeUrl($doc_id,'','','full');
                    if($this->conf('prop_loadtb')) {
                        $mc->addLine(
                            sprintf(
                                'var controls = "<div style=\\"padding:4px 0;position:fixed;top:10px;right:-10px;z-index:1000\\" id=\\"qmcontrols\\" class=\\"actionButtons\\"><ul><li class=\\"primary\\"><a href=\\"#\\" onclick=\\"documentDirty=false;gotosave=true;document.mutate.save.click();return false;\\"><img src=\\"media/style/%s/images/icons/save.png\\" />%s</a></li><li><a href=\\"#\\" id=\\"cancel\\" onclick=\\"parent.location.href=\'%s\';return false;\\"><img src=\\"media/style/%s/images/icons/stop.png\\"/>%s</a></li></ul></div>";'
                                , $qm_theme
                                , $_lang['save']
                                , $url
                                , $qm_theme
                                , $_lang['cancel']
                            )
                        );
                    } else {
                        $mc->addLine(
                            sprintf(
                                'var controls = "<div style=\\"padding:4px 0;position:fixed;top:10px;right:-10px;z-index:1000\\" id=\\"qmcontrols\\" class=\\"actionButtons\\"><ul><li class=\\"primary\\"><a href=\\"#\\" onclick=\\"documentDirty=false;gotosave=true;document.mutate.save.click();return false;\\"><img src=\\"media/style/%s/images/icons/save.png\\" />%s</a></li><li><a href=\\"#\\" id=\\"cancel\\" onclick=\\"parent.location.href=\'%s\';return false;\\"><img src=\\"media/style/%s/images/icons/stop.png\\"/>%s</a></li></ul></div>";'
                                , $qm_theme
                                , $_lang['save']
                                , $url
                                , $qm_theme
                                , $_lang['cancel']
                            )
                        );
                    }

                    // Modify head
                    $mc->head = '<script>document.body.style.display="none";</script>';

                    // Add control button
                    $mc->addLine($jq_mode . '("body").prepend(controls);');

                    // Hide fields to from front-end editors
                    if ($this->hidefields != '') {
                        $hideFields = explode(",", $this->hidefields);
                        foreach($hideFields as $key => $field) {
                            $mc->hideField($field);
                        }
                    }
                    // Hide tabs to from front-end editors
                    if ($this->hidetabs != '') {
                        $hideTabs = explode(",", $this->hidetabs);

                        foreach($hideTabs as $key => $field) {
                            $mc->hideTab($field);
                        }
                    }

                    // Hide sections from front-end editors
                    if ($this->hidesections != '') {
                        $hideSections = explode(",", $this->hidesections);

                        foreach($hideSections as $key => $field) {
                            $mc->hideSection($field);
                        }
                    }

                    // Hidden field to verify that QM+ call exists
                    $hiddenFields = '<input type="hidden" name="quickmanager" value="1" />';

                    // Different doc to be refreshed?
                    if (anyv('qmrefresh')) {
                        $hiddenFields .= '<input type="hidden" name="qmrefresh" value="'. (int)anyv('qmrefresh') .'" />';
                    }

                    // Output
                    $e->output($mc->Output().$hiddenFields);
                }
                break;
            case 'OnManagerLogout': // Where to logout
                // Only if cancel editing the document and QuickManager is in use
                if (anyv('quickmanager') === 'logout') {
                    // Redirect to document id
                    if ($this->logout != 'manager') {
                        $url = evo()->makeUrl(anyv('logoutid'),'','','full');
                        evo()->sendRedirect($url, 0, 'REDIRECT_HEADER', 'HTTP/1.1 301 Moved Permanently');
                    }
                }
                break;
        }
    }

    private function conf($key, $default=null) {
        $conf = evo()->event->params;
        if(!isset($conf[$key])) {
            $keys = array('hidetabs', 'hidesections', 'tplid', 'custombutton');
            if(in_array($key, $keys)) {
                return '';
            }
            if ($key === 'jqpath') {
                if ($this->jqpath) {
                    return $this->jqpath;
                }
                return 'manager/media/script/jquery/jquery.min.js';
            }
            return $default;
        }
        return $conf[$key];

    }

    function checkAccess() {
        // If user is admin (role = 1)
        if (sessionv('mgrRole') == 1) {
            return true;
        }

        if (!isset(evo()->documentIdentifier) || !evo()->documentIdentifier) {
            return false;
        }

        $result= db()->select(
            'id'
            , evo()->getFullTableName('document_groups')
            , sprintf(
                "document='%s'"
                , evo()->documentIdentifier
            )
        );
        if (!db()->getRecordCount($result)) {
            return true;
        }

        $docGroup = implode(',', sessionv('mgrDocgroups', array()));
        if (!$docGroup) {
            return false;
        }

        $result = db()->select(
            'id'
            , evo()->getFullTableName('document_groups')
            , sprintf(
                'document=%s AND document_group IN (%s)'
                , evo()->documentIdentifier
                , $docGroup
            )
        );
        if (db()->getRecordCount($result)) {
            return true;
        }
        return false;
    }

    // Function from: manager/processors/cache_sync.class.processor.php
    //_____________________________________________________
    function getParents($id, $path = ''){
        global $modx;
        if(!$this->aliases) {
            $qh = $modx->db->select(
                "id, IF(alias='', id, alias) AS alias, parent"
                , $modx->getFullTableName('site_content')
            );
            if ($qh && $modx->db->getRecordCount($qh) > 0) {
                while ($row = $modx->db->getRow($qh)) {
                    $this->aliases[$row['id']] = $row['alias'];
                    $this->parents[$row['id']] = $row['parent'];
                }
            }
        }
        if (isset($this->aliases[$id])) {
            $path = $this->aliases[$id] . ($path != '' ? '/' : '') . $path;
            return $this->getParents($this->parents[$id], $path);
        }
        return $path;
    }

    // Create TV buttons if user has permissions to TV
    //_____________________________________________________
    function createTvButtons($matches) {
        $docID = evo()->documentIdentifier;

        // Get TV caption for button title
        $tv = evo()->getTemplateVar($matches[1]);
        $caption = $tv['caption'];

        // If caption is empty this must be a "build-in-tv-field" like pagetitle etc.
        if ($caption == '') {
            $access = TRUE;
            $caption = $this->getDefaultTvCaption($matches[1]);
        } else {
            $access = $this->checkTvAccess($tv['id']);
        }

        // Return TV button link if access
        if ($access && $caption != '') {
            $tvname = urlencode($matches[1]);
            return sprintf(
                '<span class="%s"><a class="colorbox" href="%sindex.php?id=%s&amp;quickmanagertv=1&amp;tvname=%s"><span>%s</span></a></span>'
                , $this->tvbclass
                , evo()->config['site_url']
                , $docID
                , $tvname
                , $caption
            );
        }
    }

    // Check user access to TV
    //_____________________________________________________
    function checkTvAccess($tvId){
        $access = FALSE;
        $table = evo()->getFullTableName('site_tmplvar_access');

        // If user is admin (role = 1)
        if ($_SESSION['mgrRole'] == 1 && !$access) {
            $access = true;
        }

        // Check permission to TV, is TV in document group?
        if (!$access) {
            $result = db()->select('id',$table, 'tmplvarid = ' . $tvId);
            $rowCount = db()->getRecordCount($result);
            // TV is not in any document group
            if ($rowCount == 0) { $access = TRUE; }
        }
        // Check permission to TV, TV is in document group
        if (!$access && $this->docGroup != '') {
            $result = db()->select(
                'id'
                , $table
                , sprintf(
                    'tmplvarid = %s AND documentgroup IN (%s)'
                    , $tvId
                    , $this->docGroup
                )
            );
            $rowCount = db()->getRecordCount($result);
            if ($rowCount >= 1) {
                $access = TRUE;
            }
        }
        return $access;
    }

    // Get default TV ("build-in" TVs) captions
    //_____________________________________________________
    function getDefaultTvCaption($name){
        global $_lang;
        $caption = '';
        switch ($name) {
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
    function checkLocked(){
        $tbl_active_users = evo()->getFullTableName('active_users');
        $pageId = evo()->documentIdentifier;
        $locked = TRUE;
        $userId = $_SESSION['mgrInternalKey'];
        $where = "(`action` = 27) AND `internalKey` != '{$userId}' AND `id` = '{$pageId}'";
        $result = db()->select('internalKey',$tbl_active_users,$where);

        if (!db()->getRecordCount($result)) {
            $locked = FALSE;
        }

        return $locked;
    }

    // Set document locked on/off
    //_____________________________________________________
    function setLocked($locked) {
        $tbl_active_users = evo()->getFullTableName('active_users');
        $pageId = evo()->documentIdentifier;
        $userId = $_SESSION['mgrInternalKey'];

        // Set document locked
        if ($locked == 1) {
            $fields['id']     = $pageId;
            $fields['action'] = 27;
        } else {
            // Set document unlocked
            $fields['id'] = 'NULL';
            $fields['action'] = 2;
        }
        $where = "internalKey = '{$userId}'";
        $result = db()->update($fields, $tbl_active_users, $where);
    }

    // Save TV
    //_____________________________________________________
    function saveTv($tvName){
        $tbl_site_tmplvar_contentvalues = evo()->getFullTableName('site_tmplvar_contentvalues');
        $tbl_site_content = evo()->getFullTableName('site_content');
        $pageId = evo()->documentIdentifier;
        $result = null;
        $time = time();
        $user = $_SESSION['mgrInternalKey'];
        $tvId = isset($_POST['tvid'])&&preg_match('@^[1-9][0-9]*$@',$_POST['tvid']) ? $_POST['tvid'] : 0;
        if($tvId) {
            $tvContent = postv('tv' . $tvId, '');
        } else {
            $tvContent = postv('tv' . $tvName, '');
        }
        $tvContentTemp = '';

        // Escape TV content
        $tvName = db()->escape($tvName);
        $tvContent = db()->escape($tvContent);

        // Invoke OnBeforeDocFormSave event
        $tmp = array('mode'=>'upd', 'id'=>$pageId);
        evo()->invokeEvent('OnBeforeDocFormSave', $tmp);

        // Handle checkboxes and other arrays, TV to be saved must be e.g. value1||value2||value3
        if (is_array($tvContent)) {
            foreach($tvContent as $key => $value) {
                $tvContentTemp .= $value . '||';
            }
            $tvContentTemp = substr($tvContentTemp, 0, -2);  // Remove last ||
            $tvContent = $tvContentTemp;
        }

        // Save TV
        if ($tvId) {
            $where = "`tmplvarid` = '{$tvId}' AND `contentid` = '{$pageId}'";
            $result = db()->select('id',$tbl_site_tmplvar_contentvalues,$where);

            // TV exists, update TV
            if(db()->getRecordCount($result)) {
                $sql = sprintf("UPDATE %s SET `value`='%s' WHERE `tmplvarid`='%s' AND `contentid`='%s'"
                    , $tbl_site_tmplvar_contentvalues
                    , $tvContent
                    , $tvId
                    , $pageId
                );
            } else {
                // TV does not exist, create new TV
                $sql = sprintf(
                    "INSERT INTO %s (tmplvarid, contentid, value) VALUES('%s', '%s', '%s')"
                    , $tbl_site_tmplvar_contentvalues
                    , $tvId
                    , $pageId
                    , $tvContent
                );
            }

            // Page edited by
            db()->update(
                array('editedon'=>$time, 'editedby'=>$user)
                , $tbl_site_content
                , 'id = "' . $pageId . '"'
            );
        } else {
            // Save default field, e.g. pagetitle
            $sql = sprintf(
                "UPDATE %s SET `%s`='%s', `editedon`='%s', `editedby`='%s' WHERE `id`='%s'"
                , $tbl_site_content
                , $tvName
                , $tvContent
                , $time
                , $user
                , $pageId
            );
        }
        // Update TV
        if($sql) {
            $result = db()->query($sql);
        }
        // Log possible errors
        if(!$result) {
            evo()->logEvent(
                0
                , 0
                , sprintf(
                    '<p>Save failed!</p><strong>SQL:</strong><pre>%s</pre>'
                    , $sql
                )
                , 'QuickManager+'
            );
        } else {
            // No errors
            // Invoke OnDocFormSave event
            $tmp = array('mode'=>'upd', 'id'=>$pageId);
            evo()->invokeEvent('OnDocFormSave', $tmp);
            // Clear cache
            $this->clearCache();
        }
    }

    // Clear cache
    //_____________________________________________________
    function clearCache(){
        // Clear cache
        evo()->clearCache();
    }

    function get_img_prev_src(){
        if ($this->noconflictjq == 'true') {
            $jq_mode = '$j';
        } else {
            $jq_mode = '$';
        }

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

    private function config($key, $default=null) {
        $conf = evo()->event->params;
        if(!isset($conf[$key])) {
            if ($key === 'jqpath') {
                if ($this->jqpath) {
                    return $this->jqpath;
                }
                return 'manager/media/script/jquery/jquery.min.js';
            }
            return $default;
        }
        if($conf[$key]==='true') {
            $conf[$key] = true;
        }
        if($conf[$key]==='false') {
            $conf[$key] = false;
        }
        return $conf[$key];

    }
}
