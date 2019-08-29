<?php

function sectionContent() {
    if (doc('type') !== 'document') {
        return '';
    }
    $ph['header'] = lang('resource_content');
    $planetpl = function($content) {
        return sprintf(
            '<textarea class="phptextarea" id="ta" name="ta" style="width:100%%; height: 400px;">%s</textarea>'
            , $content
        );
    };
    if (config('use_editor') && doc('richtext')) {
        $editors = evo()->invokeEvent('OnRichTextEditorRegister');
        if($editors) {
            $ph['body'] = rteContent(doc('content|hsc'), $editors);
        } else {
            $ph['body'] = $planetpl(doc('content|hsc'));
        }
    } else {
        $ph['body'] = $planetpl(doc('content|hsc'));
    }

    return parseText(file_get_tpl('section_content.tpl'),$ph);
}

function sectionTV() {
    $ph = array();
    $ph['header'] = lang('settings_templvars');
    $ph['body'] = fieldsTV();
    return parseText(file_get_tpl('section_tv.tpl'),$ph);
}
