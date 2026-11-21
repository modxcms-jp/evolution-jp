<?php
// If there is Qm call, add control buttons and modify to edit document page
if (request_intvar('quickmanager') !== 1) {
    return;
}

global $docObject;

// Set template for new document, action = 4
if (get_intvar('a') === 4) {
    // Custom add button
    if (get_intvar('customaddtplid')) {
        $docObject['template'] = get_intvar('customaddtplid');
    } else {
        // Normal add button
        if ($this->tpltype === 'config') $this->tpltype = config('auto_template_logic');
        switch ($this->tpltype) {
            case 'parent': // Template type is parent
                // Get parent document id
                $pid = $docObject['parent'] ? $docObject['parent'] : request_intvar('pid');

                // Get parent document (unpublished parents too)
                $parent = evo()->getDocument($pid, 'template', null);

                // Set parent template
                $docObject['template'] = $parent['template'] ?? config('default_template');
                break;

            case 'id': // Template is specific id
                $docObject['template'] = $this->tplid;
                break;
            case 'selected': // Template is inherited by Inherit Selected Template plugin
            case 'sibling':
                // Get parent document id
                $pid = $docObject['parent'] ? $docObject['parent'] : request_intvar('pid');

                if (config('auto_template_logic') === 'sibling') {
                    // Eoler: template_autologic in Evolution 1.0.5+
                    // http://tracker.modx.com/issues/9586
                    $tv = [];
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
                if (($tv['value'] ?? '') != '') {
                    $docObject['template'] = $tv['value'];
                } else {
                    $docObject['template'] = config('default_template');
                }
                break;
            case 'system':
                $docObject['template'] = config('default_template');
                break;
        }
    }
}

// Manager control class
$mc = new Mcc();

// Get doc id
$doc_id = request_intvar('id') ?: request_intvar('pid');

// 本体の操作ボタンの帯（#actions）の中身を、QuickManager の保存・キャンセルに入れ替える（assets/plugins/qm/js/qm-edit.js）。
// makeUrl() は設定（xhtml_urls）で & を &amp; にして返すので、JS の値にする前に元へ戻す
$mc->controls = [
    'save' => lang('save'),
    'cancel' => lang('cancel'),
    'cancelUrl' => html_entity_decode(evo()->makeUrl($doc_id, '', '', 'full'), ENT_QUOTES | ENT_HTML401, 'UTF-8'),
];

// Modify head
$mc->head = '<script>document.body.style.display="none";</script>';

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
if (request_intvar('qmrefresh')) {
    $hiddenFields .= '<input type="hidden" name="qmrefresh" value="' . request_intvar('qmrefresh') . '" />';
}

// Output
$e->output($mc->Output() . $hiddenFields);
