<?php

if ($modx->manager->action == 17) {
    $css_selectors = '左寄せ=justifyleft;右寄せ=justifyright';
    $params['theme'] = (empty($params['theme'])) ? 'editor' : $params['theme'];
    $ph['custom_plugins'] = $params['custom_plugins'] ?? '';
    $ph['custom_buttons1'] = $params['custom_buttons1'] ?? '';
    $ph['custom_buttons2'] = $params['custom_buttons2'] ?? '';
    $ph['custom_buttons3'] = $params['custom_buttons3'] ?? '';
    $ph['custom_buttons4'] = $params['custom_buttons4'] ?? '';
    $ph['mce_template_docs'] = $params['mce_template_docs'] ?? '';
    $ph['mce_template_chunks'] = $params['mce_template_chunks'] ?? '';
    $ph['css_selectors'] = (!isset($params['css_selectors']))
        ? $css_selectors
        : $params['css_selectors'];
    $ph['mce_entermode'] = $params['mce_entermode'] ?? 'p';
    $ph['mce_schema'] = $params['mce_schema'] ?? 'html4';
    $ph['mce_element_format'] = $params['mce_element_format'] ?? 'xhtml';
} else {
    $ph = $params;
}
