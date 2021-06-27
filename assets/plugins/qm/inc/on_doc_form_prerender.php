<?php
// If there is Qm call, add control buttons and modify to edit document page
if ((int)anyv('quickmanager') != 1) {
    return;
}

global $docObject;

// Set template for new document, action = 4
if (getv('a') == 4) {
    // Custom add button
    if (getv('customaddtplid')) {
        $docObject['template'] = (int)getv('customaddtplid');
    } else {
        // Normal add button
        if ($this->tpltype === 'config') $this->tpltype = evo()->config['auto_template_logic'];
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
                    if (!$sibl) {
                        $sibl = evo()->getDocumentChildren($pid, 0, 0, 'template', '', 'menuindex', 'ASC', 1);
                    }
                    if (!empty($sibl)) {
                        $tv['value'] = $sibl[0]['template'];
                    } else $tv['value'] = ''; // Added by yama
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
if (anyv('id')) {
    $doc_id = (int)anyv('id');
} elseif (anyv('pid')) {
    $doc_id = (int)anyv('pid');
} else {
    $doc_id = 0;
}

// Add action buttons
if ($this->conf('prop_loadtb')) {
    $mc->addLine(
        sprintf(
            'var controls = "<div style=\\"padding:4px 0;position:fixed;top:10px;right:-10px;z-index:1000\\" id=\\"qmcontrols\\" class=\\"actionButtons\\"><ul><li class=\\"primary\\"><a href=\\"#\\" onclick=\\"documentDirty=false;gotosave=true;document.mutate.save.click();return false;\\"><img src=\\"media/style/%s/images/icons/save.png\\" />%s</a></li><li><a href=\\"#\\" id=\\"cancel\\" onclick=\\"parent.location.href=\'%s\';return false;\\"><img src=\\"media/style/%s/images/icons/stop.png\\"/>%s</a></li></ul></div>";',
            $qm_theme,
            $_lang['save'],
            evo()->makeUrl($doc_id, '', '', 'full'),
            $qm_theme,
            $_lang['cancel']
        )
    );
} else {
    $mc->addLine(
        sprintf(
            'var controls = "<div style=\\"padding:4px 0;position:fixed;top:10px;right:-10px;z-index:1000\\" id=\\"qmcontrols\\" class=\\"actionButtons\\"><ul><li class=\\"primary\\"><a href=\\"#\\" onclick=\\"documentDirty=false;gotosave=true;document.mutate.save.click();return false;\\"><img src=\\"media/style/%s/images/icons/save.png\\" />%s</a></li><li><a href=\\"#\\" id=\\"cancel\\" onclick=\\"parent.location.href=\'%s\';return false;\\"><img src=\\"media/style/%s/images/icons/stop.png\\"/>%s</a></li></ul></div>";',
            $qm_theme,
            $_lang['save'],
            evo()->makeUrl($doc_id, '', '', 'full'),
            $qm_theme,
            $_lang['cancel']
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
    foreach ($hideFields as $key => $field) {
        $mc->hideField($field);
    }
}
// Hide tabs to from front-end editors
if ($this->hidetabs != '') {
    $hideTabs = explode(",", $this->hidetabs);

    foreach ($hideTabs as $key => $field) {
        $mc->hideTab($field);
    }
}

// Hide sections from front-end editors
if ($this->hidesections != '') {
    $hideSections = explode(",", $this->hidesections);

    foreach ($hideSections as $key => $field) {
        $mc->hideSection($field);
    }
}

// Hidden field to verify that QM+ call exists
$hiddenFields = '<input type="hidden" name="quickmanager" value="1" />';

// Different doc to be refreshed?
if (anyv('qmrefresh')) {
    $hiddenFields .= '<input type="hidden" name="qmrefresh" value="' . (int)anyv('qmrefresh') . '" />';
}

// Output
$e->output($mc->Output() . $hiddenFields);
