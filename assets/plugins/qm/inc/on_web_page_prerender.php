<?php
if (evo()->documentObject['contentType'] !== 'text/html') {
    return;
}
if (evo()->documentObject['content_dispo'] == 1) {
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
    exit(sprintf("<script>parent.location.href='%s';</script>", $url));
}

// QM+ TV edit
if (getv('quickmanagertv') == 1 && getv('tvname') != '' && $this->tvbuttons == 'true') {
    $output = include 'edit_tv.inc.php';
} // QM+ with toolbar
else {
    if (sessionv('mgrValidated') && anyv('z') !== 'manprev') {
        if (anyv('logout')) {
            $this->Logout();
            return;
        }
        $userID = $_SESSION['mgrInternalKey'];

        // Edit button

        $editButton = '
<li class="qmEdit">
<a class="qmButton qmEdit colorbox" href="' . MODX_SITE_URL . 'manager/index.php?a=27&amp;id=' . $docID . '&amp;quickmanager=1"><span> ' . $_lang['edit_resource'] . '</span></a>
</li>
';
        // Check if user has manager access to current document
        $access = $this->checkAccess();

        // Does user have permissions to edit document
        $controls = '';
        if ($access) {
            $controls .= $editButton;
        }

        if ($this->addbutton == 'true' && $access) {
            // Add button
            $addButton = '
<li class="qmAdd">
<a class="qmButton qmAdd colorbox" href="' . MODX_SITE_URL . 'manager/index.php?a=4&amp;pid=' . $docID . '&amp;quickmanager=1"><span>' . $_lang['create_resource_here'] . '</span></a>
</li>
';

            // Does user have permissions to add document
            if (evo()->hasPermission('new_document')) {
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
            foreach ($buttons as $key => $field) {
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
                    foreach ($buttonRoles as $mgrRole) {
                        if ($mgrRole != $_SESSION['mgrRole']) {
                            continue;
                        }
                        $showButton = TRUE;
                        return;
                    }
                }

                // Show custom button
                if ($showButton) {
                    switch ($buttonAction) {
                        case 'new':
                            $customButton = '
<li class="qm-custom-' . $i . ' qmCustom">
<a class="qmButton qmCustom colorbox" href="' . MODX_SITE_URL . 'manager/index.php?a=4&amp;pid=' . $buttonParentId . '&amp;quickmanager=1&amp;customaddtplid=' . $buttonTplId . '"><span>' . $buttonTitle . '</span></a>
</li>
';
                            return;
                        case 'link':
                            $customButton = '
<li class="qm-custom-' . $i . ' qmCustom">
<a class="qmButton qmCustom" href="' . $buttonParentId . '" ><span>' . $buttonTitle . '</span></a>
</li>
';
                            return;
                        case 'modal':
                            $customButton = '
<li class="qm-custom-' . $i . ' qmCustom">
<a class="qmButton qmCustom colorbox" href="' . $buttonParentId . '" ><span>' . $buttonTitle . '</span></a>
</li>
';
                            return;
                    }
                    $controls .= $customButton;
                }
            }
        }

        // Go to Manager button
        if ($this->managerbutton == 'true') {
            $managerButton = '
<li class="qmManager">
<a class="qmButton qmManager" title="' . $_lang['manager'] . '" href="' . MODX_SITE_URL . 'manager/" ><span>' . $_lang['manager'] . '</span></a>
</li>
';
            $controls .= $managerButton;
        }
        // Logout button
        $logout = MODX_SITE_URL . 'manager/index.php?a=8&amp;quickmanager=logout&amp;logoutid=' . $docID;
        $logoutButton = '
<li class="qmLogout">
<a id="qmLogout" class="qmButton qmLogout" title="' . $_lang['logout'] . '" href="' . $logout . '" ><span>' . $_lang['logout'] . '</span></a>
</li>
';
        $controls .= $logoutButton;

        // Add action buttons
        $editor = '
<div id="qmEditorClosed"></div>

<div id="qmEditor">

<ul>
<li id="qmClose"><a class="qmButton qmClose" href="#" onclick="return false;">X</a></li>
<li><a id="qmLogoClose" class="qmClose" href="#" onclick="return false;"></a></li>
' . $controls . '
</ul>
</div>';
        $css = '
<link rel="stylesheet" type="text/css" href="' . MODX_SITE_URL . 'assets/plugins/qm/css/style.css" />
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
            $head .= '<script src="' . MODX_SITE_URL . $this->jqpath . '" type="text/javascript"></script>';
        }
        if ($this->loadtb == 'true') {
            $head .= '
<link type="text/css" media="screen" rel="stylesheet" href="' . MODX_SITE_URL . 'assets/plugins/qm/css/colorbox.css" />
<script type="text/javascript" src="' . MODX_SITE_URL . 'assets/plugins/qm/js/jquery.colorbox-min.js"></script>
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
$' . $jvar . '("a.colorbox").colorbox({width:"' . $this->tbwidth . '", height:"' . $this->tbheight . '", iframe:true, overlayClose:false, opacity:0.5, transition:"fade", speed:150});

// Hide QM+ if cookie found
if (getCookie("hideQM") == 1)
{
	$' . $jvar . '("#qmEditor").css({"display":"none"});
	$' . $jvar . '("#qmEditorClosed").css({"display":"block"});
}

// Hide QM+
$' . $jvar . '(".qmClose").click(function ()
{
	$' . $jvar . '("#qmEditor").hide("normal");
	$' . $jvar . '("#qmEditorClosed").show("normal");
	document.cookie = "hideQM=1; path=/;";
});

// Show QM+
$' . $jvar . '("#qmEditorClosed").click(function ()
{
	{
		$' . $jvar . '("#qmEditorClosed").hide("normal");
		$' . $jvar . '("#qmEditor").show("normal");
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
            $output = preg_replace('/<!-- ' . $this->editbclass . ' ([0-9]+) ([\'|\\"])([^\\"\'\(\)<>!?]+)\\2 -->/', '<span class="' . $this->editbclass . '"><a class="colorbox" href="' . MODX_SITE_URL . 'manager/index.php?a=27&amp;id=$1&amp;quickmanager=1&amp;qmrefresh=' . $docID . '"><span>$3</span></a></span>', $output);
        }

        // Search and create new document buttons in to the content
        if ($this->newbuttons == 'true' && $access) {
            $output = preg_replace('/<!-- ' . $this->newbclass . ' ([0-9]+) ([0-9]+) ([\'|\\"])([^\\"\'\(\)<>!?]+)\\3 -->/', '<span class="' . $this->newbclass . '"><a class="colorbox" href="' . MODX_SITE_URL . 'manager/index.php?a=4&amp;pid=$1&amp;quickmanager=1&amp;customaddtplid=$2"><span>$4</span></a></span>', $output);
        }

        // Search and create new document buttons in to the content
        if ($this->tvbuttons == 'true' && $access) {
            // Set and get user doc groups for TV permissions
            $this->docGroup = '';
            $mrgDocGroups = $_SESSION['mgrDocgroups'];
            if (!empty($mrgDocGroups)) $this->docGroup = implode(",", $mrgDocGroups);

            // Create TV buttons and check TV permissions
            $output = preg_replace_callback('/<!-- ' . $this->tvbclass . ' ([^\\"\'\(\)<>!?]+) -->/', [&$this, 'createTvButtons'], $output);
        }
    }
}
