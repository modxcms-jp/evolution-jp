<?php
if (!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

if (empty($params['output'])) {
    throw new Exception('No output specified for widget');
}

$widget_output = null;
$o = '';
/* If we are loading a file */
$params['output'] = $this->parseText($params['output'], ['value' => $value, 'tvname' => $name]);

if (substr($params['output'], 0, 5) === '<?php') {
    $params['output'] = "@EVAL:\n" . substr($params['output'], 5);
}

$modx->tvfilter = new stdClass();
$modx->tvfilter->vars['name'] = &$name;
$modx->tvfilter->vars['value'] = &$value;
$modx->tvfilter->vars['input'] = &$value;
$modx->tvfilter->vars['docid'] = &$docid;

if (substr($params['output'], 0, 5) == '@FILE') {
    $file_name = MODX_BASE_PATH . trim(substr($params['output'], 6));
    if (!is_file($file_name)) {
        throw new Exception($file_name . ' does not exist');
    }
    $widget_output = file_get_contents($file_name);
} elseif (substr($params['output'], 0, 8) == '@INCLUDE') {
    $file_name = MODX_BASE_PATH . trim(substr($params['output'], 9));
    if (!is_file($file_name)) {
        throw new Exception($file_name . ' does not exist');
    }
    ob_start();
    $return = include $file_name;
    $incOut = ob_get_clean();
    if ($widget_output === null) {
        $widget_output = $incOut ?: $return;
    }
} elseif (substr($params['output'], 0, 6) == '@CHUNK' && $value !== '') {
    $chunk_name = trim(substr($params['output'], 7));
    $widget_output = $this->getChunk($chunk_name);
} elseif (substr($params['output'], 0, 5) == '@EVAL') {
    $tvname = $name;
    $eval_str = trim(substr($params['output'], 6));
    ob_start();
    $return = eval($eval_str);
    $msg = ob_get_contents();
    ob_end_clean();
    if ($return === false) {
        $widget_output = $value;
    } else {
        $widget_output = $msg . $return;
    }
} else {
    if ($value !== '') $widget_output = $params['output'];
    else            $widget_output = '';
}

$modx->tvfilter->vars = [];

// Except @INCLUDE
if (!is_string($widget_output)) {
    return $widget_output;
}
if (strpos($widget_output, '[+') !== false) {
    $widget_output = $this->parseText($widget_output, ['value' => $value, 'tvname' => $name], '[+', '+]', false);
}

return $this->parseDocumentSource($widget_output);
