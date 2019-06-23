<?php

/*
 * The Filter class contains all functions relating to filtering,
 * the removing of documents from the result set
*/

class filter {

	public function __construct() {
	}

    public function execute($docs, $filter) {
		foreach ($filter['basic'] as $current_filter) {
			if (!is_array($current_filter) || !$current_filter) {
				continue;
			}
			$param = $this->_getParams($current_filter);
			$docs = $this->_basicFilter($docs, $param);
		}
		foreach ($filter['custom'] as $current_filter) {
			$docs = array_filter($docs, $current_filter);
		}
		return $docs;
	}

	private function _basicFilter($docs, $param) {
		foreach($docs as $i=>$doc) {
			$unset = $this->isTrue($doc, $param);
			if($param['flip_mode']) {
				$unset = $unset ? 0 : 1;
			}
			if($unset) {
				unset($docs[$i]);
			}
		}
		return $docs;
	}

	private function _getParams($param) {
		global $modx;

		$rs = array();
		$rs['op'] = isset($param['mode']) ? $param['mode'] : '!=';
		if (in_array($param['value'], array('>','>=','<','<=','!=','<>','==','=~','!~') )) {
			list($param['value'], $rs['op']) = array($rs['op'], $param['value']);
		}
		
		if(strpos($param['value'], '@EVAL') === 0) {
			$eval_code = trim(substr($param['value'],6));
			$eval_code = trim($eval_code,';') . ';';
			if(strpos($eval_code,'return')===false) {
				$eval_code = 'return ' . $eval_code;
			}
			$rs['creteria'] = eval($eval_code);
		} else {
			$rs['creteria'] = $param['value'];
		}

		if(strpos($rs['creteria'],'[+') !== false) {
			$rs['creteria'] = $modx->mergePlaceholderContent($rs['creteria']);
		}

		$rs['op'] = $this->_get_operator_name($rs['op']);

		$rs['creteria'] = trim($rs['creteria']);

		if ($this->get_docfield_type($param['source'])==='datetime') {
			if (!preg_match('@^[0-9]+$@',$rs['creteria'])) {
				$rs['creteria'] = strtotime($rs['creteria']);
			}
		}

		$rs['field_name'] = $param['source'];

		if (strpos($rs['op'], '!') === 0 && substr($rs['op'],0,2)!=='!!') {
			$rs['flip_mode'] = 1;
			$rs['op'] = substr($rs['op'],1);
			if($rs['op']==='=') {
				$rs['op'] = '==';
			}
		} else {
			$rs['flip_mode'] = 0;
		}

		return $rs;
	}
	
	private function isTrue($doc, $param) {
		$field_name = $param['field_name'];
		$doc_value = isset($doc[$field_name]) ? $doc[$field_name] : false;
		$creteria = $param['creteria'];

		switch ($param['op']) {
			case '!=' : return ($doc_value != $creteria || $doc_value===false);
			case '==' : return ($doc_value == $creteria);
			case '<'  : return ($doc_value <  $creteria);
			case '>'  : return ($doc_value >  $creteria);
			case '>=' : return ($doc_value >= $creteria);
			case '<=' : return ($doc_value <= $creteria);
			case '=~' : return (strpos($doc_value, $creteria)===false);
			case '!~' : return (strpos($doc_value, $creteria)!==false);
			case 9    : return (stripos($doc_value, $creteria)===false);
			case 10   : return (stripos($doc_value, $creteria)!==false);
			case 'regex': return (preg_match($doc_value, $creteria)===false);
			case 11   :
				$firstChr = strtoupper(substr($doc_value, 0, 1));
				return ($firstChr!=$creteria);
		}
		return false;
	}

	private function _get_operator_name($operator_name) {
		if (in_array($operator_name, array(1,'<>','ne')))  return '!=';
		if (in_array($operator_name, array(2,'eq','ne')))  return '==';
		if (in_array($operator_name, array(3,'lt','ne')))  return '<';
		if (in_array($operator_name, array(6,'lte','le'))) return '<=';
		if (in_array($operator_name, array(4,'gt')))       return '>';
		if (in_array($operator_name, array(5,'gte','ge'))) return '>=';
		if ($operator_name==8)                             return '!~';
		if ($operator_name === 'preg')                     return 'regex';
		if (in_array($operator_name, array(7,'find','search','strpos'))) return '=~';
		return $operator_name;
	}

	private function get_docfield_type($field_name='') {
		if(in_array($field_name, explode(',','published,pub_date,unpub_date,createdon,editedon,publishedon,deletedon'))) {
			return 'datetime';
		};
		return false;
	}
}
