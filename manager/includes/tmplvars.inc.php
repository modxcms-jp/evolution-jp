<?php
function renderFormElement(
    $field_type,
    $field_id,
    $default_text,
    $field_elements,
    $field_value,
    $field_style = '',
    $row = []
)
{
    global $modx;
    evo()->loadExtension('SubParser');
    return $modx->sub->renderFormElement($field_type, $field_id, $default_text, $field_elements, $field_value,
        $field_style, $row);
}

function ParseInputOptions($v)
{
    global $modx;
    evo()->loadExtension('SubParser');
    return $modx->sub->ParseInputOptions($v);
}

function splitOption($value)
{
    global $modx;
    evo()->loadExtension('SubParser');
    return $modx->sub->splitOption($value);
}

function isSelected($label, $value, $item, $field_value)
{
    global $modx;
    evo()->loadExtension('SubParser');
    return $modx->sub->isSelected($label, $value, $item, $field_value);
}
