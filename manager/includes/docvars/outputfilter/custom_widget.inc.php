<?php
if (!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

if (empty($params['output'])) {
    throw new Exception('No output specified for widget');
}

$widget_output = null;
$o = '';
/* If we are loading a file */
// 処理の種類は、文書の値を埋め込む前の出力の定義で決める。
// 定義が @FILE・@INCLUDE などなら、値を埋め込む前に展開してから決める
$raw_output = $params['output'];
if (strpos($raw_output, '@') === 0) {
    $raw_output = $this->atBind($raw_output);
    $raw_output = is_array($raw_output) ? implode('', $raw_output) : (string) $raw_output;
}
$params['output'] = $this->parseText($raw_output, ['value' => $value, 'tvname' => $name]);

if (substr($raw_output, 0, 5) === '<?php') {
    $raw_output = "@EVAL:\n" . substr($raw_output, 5);
}

$modx->tvfilter = new stdClass();
$modx->tvfilter->vars['name'] = &$name;
$modx->tvfilter->vars['value'] = &$value;
$modx->tvfilter->vars['input'] = &$value;
$modx->tvfilter->vars['docid'] = &$docid;

if (substr($raw_output, 0, 6) == '@CHUNK' && $value !== '') {
    $chunk_name = trim(substr($raw_output, 7));
    $widget_output = $this->getChunk($chunk_name);
} elseif (substr($raw_output, 0, 5) == '@EVAL') {
    $tvname = $name;
    $eval_str = trim(substr($raw_output, 6));
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

if (!is_string($widget_output)) {
    return $widget_output;
}
if (strpos($widget_output, '[+') !== false) {
    $widget_output = $this->parseText($widget_output, ['value' => $value, 'tvname' => $name], '[+', '+]', false);
}

return $this->parseDocumentSource($widget_output);
