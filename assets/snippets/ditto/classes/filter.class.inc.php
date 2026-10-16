<?php

/*
 * The Filter class contains all functions relating to filtering,
 * the removing of documents from the result set
*/

class filter
{

    public function __construct()
    {
    }

    public function execute($docs, $filter)
    {
        foreach ($filter['basic'] as $current_filter) {
            if (!is_array($current_filter) || !$current_filter) {
                continue;
            }
            $docs = $this->_basicFilter($docs, $this->_getParams($current_filter));
        }
        foreach ($filter['custom'] as $current_filter) {
            $docs = array_filter($docs, $current_filter);
        }
        return $docs;
    }

    private function _basicFilter($docs, $param)
    {
        foreach ($docs as $i => $doc) {
            if ($this->isTrue($doc, $param)) {
                unset($docs[$i]);
            }
        }
        return $docs;
    }

    private function _getParams($param)
    {
        global $modx;

        $rs = [];
        $op = $this->_get_operator_name($param['mode']);
        if (!$op && $param['mode'] === null) {
            // mode omitted: case-insensitive "contains" (mode 9), whatever the value is
            $op = 'ci_find';
        } elseif (!$op) {
            // source:operator:value is accepted as well
            $op = $this->_get_operator_name($param['value']);
            if ($op) {
                $param['value'] = $param['mode'];
            } else {
                // unknown mode: case-insensitive "contains" (mode 9)
                $op = 'ci_find';
            }
        }
        $rs['op'] = $op;

        // @EVAL は、バインディングが有効のときだけ実行する。無効のときは、ふつうの値として扱う
        if (stripos($param['value'], '@EVAL') === 0 && evo()->config('enable_bindings') == 1) {
            $eval_code = trim(
                    substr($param['value'], 6),
                    ';'
                ) . ';';
            if (strpos($eval_code, 'return') === false) {
                $eval_code = 'return ' . $eval_code;
            }
            $rs['creteria'] = eval($eval_code);
        } else {
            $rs['creteria'] = $param['value'];
        }

        if (strpos($rs['creteria'], '[+') !== false) {
            $rs['creteria'] = $modx->mergePlaceholderContent($rs['creteria']);
        }

        $rs['creteria'] = trim($rs['creteria']);

        if ($this->is_datetime_field($param['source'])) {
            if (!preg_match('@^[0-9]+$@', $rs['creteria'])) {
                $rs['creteria'] = strtotime($rs['creteria']);
            }
        }

        $rs['field_name'] = $param['source'];

        return $rs;
    }

    // Returns true when the document is to be removed from the result
    private function isTrue($doc, $param)
    {
        $field_name = $param['field_name'];
        $doc_value = isset($doc[$field_name]) ? $doc[$field_name] : false;
        $creteria = $param['creteria'];

        switch ($param['op']) {
            case '!=' : // keep the documents equal to the value (a missing field compares as false)
                return !($doc_value == $creteria);
            case '==' :
                return ($doc_value == $creteria);
            case '<'  :
                return ($doc_value < $creteria);
            case '>'  :
                return ($doc_value > $creteria);
            case '>=' :
                return ($doc_value >= $creteria);
            case '<=' :
                return ($doc_value <= $creteria);
            case '=~' : // keep the documents containing the value
                return (strpos((string)$doc_value, (string)$creteria) === false);
            case '!~' : // remove the documents containing the value
                return (strpos((string)$doc_value, (string)$creteria) !== false);
            case 'ci_find' : // keep the documents containing the value (case-insensitive)
                return (stripos((string)$doc_value, (string)$creteria) === false);
            case 'ci_not_find' :
                return (stripos((string)$doc_value, (string)$creteria) !== false);
            case 'regex': // keep the documents matching the pattern
                return (preg_match((string)$creteria, (string)$doc_value) !== 1);
            case 'first_char' : // keep the documents beginning with the letter
                return (strtoupper(substr((string)$doc_value, 0, 1)) != $creteria);
        }
        return false;
    }

    // mode number or operator name => operator used in isTrue()
    private const OPERATORS = [
        '1' => '!=', '!=' => '!=', '<>' => '!=', 'ne' => '!=',
        '2' => '==', '==' => '==', 'eq' => '==',
        '3' => '<', '<' => '<', 'lt' => '<',
        '4' => '>', '>' => '>', 'gt' => '>',
        '5' => '>=', '>=' => '>=', 'gte' => '>=', 'ge' => '>=',
        '6' => '<=', '<=' => '<=', 'lte' => '<=', 'le' => '<=',
        '7' => '=~', '=~' => '=~', 'find' => '=~', 'search' => '=~', 'strpos' => '=~',
        '8' => '!~', '!=~' => '!~', '!~' => '!~', '!find' => '!~', '!search' => '!~', '!strpos' => '!~',
        '9' => 'ci_find',
        '10' => 'ci_not_find',
        '11' => 'first_char',
        'regex' => 'regex', 'preg' => 'regex',
    ];

    private function _get_operator_name($op)
    {
        // null: mode omitted in &filter
        if ($op === null || is_array($op)) {
            return false;
        }
        // spaces around the mode are allowed (e.g. "field,value, 2")
        return self::OPERATORS[trim((string)$op)] ?? false;
    }

    private function is_datetime_field($field_name = '')
    {
        if (in_array($field_name, explode(',', 'pub_date,unpub_date'))) {
            return true;
        }
        if (in_array($field_name, explode(',', 'published,createdon,editedon,publishedon,deletedon'))) {
            return true;
        }
        return false;
    }
}
