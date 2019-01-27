<?php

/*
 * Title: Filter Class
 * Purpose:
 *  	The Filter class contains all functions relating to filtering,
 * 		the removing of documents from the result set
*/

class filter {
	var $array_key, $filtertype, $filterValue, $flip_mode, $filterArgs;

// ---------------------------------------------------
// Function: execute
// Filter documents via either a custom filter or basic filter
// ---------------------------------------------------
	public function execute($doc, $filter)
	{
		global $modx;
		foreach ($filter['basic'] as $currentFilter)
		{
			$this->flip_mode = 0;

			if (!is_array($currentFilter) || !$currentFilter) {
				continue;
			}

			$this->array_key = $currentFilter['source'];
			
			if (substr($currentFilter['mode'],0,1)==='!' && substr($currentFilter['mode'],0,2)!=='!!') {
				$this->flip_mode = 1;
				$currentFilter['mode'] = substr($currentFilter['mode'],1);
			}
			
			switch($currentFilter['value'])
			{
				case '>':
				case '>=':
				case '<':
				case '<=':
				case '!=':
				case '<>':
				case '==':
				case '=~':
				case '!~':
					$t = $currentFilter['value'];
					$currentFilter['value'] = $currentFilter['mode'];
					$currentFilter['mode'] = $t;
					unset($t);
					break;
			}
			
			if(substr($currentFilter['value'],0,5) === '@EVAL')
			{
				$eval_code = trim(substr($currentFilter['value'],6));
				$eval_code = trim($eval_code,';') . ';';
				if(strpos($eval_code,'return')===false)
				{
					$eval_code = 'return ' . $eval_code;
				}
				$this->filterValue = eval($eval_code);
			}
			else
			{
				$this->filterValue = $currentFilter['value'];
			}
			if(strpos($this->filterValue,'[+') !== false)
			{
				$this->filterValue = $modx->mergePlaceholderContent($this->filterValue);
			}
			$this->filtertype = (isset($currentFilter['mode'])) ? $currentFilter['mode'] : 1;
			
			$this->filterValue = trim($this->filterValue);
			if ($modx->get_docfield_type($this->array_key)==='datetime') {
				if (!preg_match('@^[0-9]+$@',$this->filterValue)) {
					$this->filterValue = strtotime($this->filterValue);
				}
			}

			$doc = array_filter($doc, array($this, 'basicFilter'));
		}

		foreach ($filter['custom'] as $currentFilter)
		{
			$doc = array_filter($doc, $currentFilter);
		}

		return $doc;
	}
	
// ---------------------------------------------------
// Function: basicFilter
// Do basic comparison filtering
// ---------------------------------------------------
	
	private function basicFilter ($doc) {
		global $modx;

		$unset = 1;

		$this->filtertype = $this->get_operator_name($this->filtertype);

		switch ($this->filtertype) {
			case '!=' :
				if (!isset ($doc[$this->array_key]))
					$unset = 0;
				elseif($doc[$this->array_key] != $this->filterValue)
					$unset = 0;
				break;
			case '==' :
				if ($doc[$this->array_key] == $this->filterValue)
					$unset = 0;
				break;
			case '<' :
				if ($doc[$this->array_key] < $this->filterValue)
					$unset = 0;
				break;
			case '>' :
				if ($doc[$this->array_key] > $this->filterValue)
					$unset = 0;
				break;
			case '>=' :
				if ($doc[$this->array_key] >= $this->filterValue)
					$unset = 0;
				break;
			case '<=' :
				if ($doc[$this->array_key] <= $this->filterValue)
					$unset = 0;
				break;
				
			// Cases 7 & 8 created by MODx Testing Team Member ZAP
			case '=~':
				if (strpos($doc[$this->array_key], $this->filterValue)===false)
					$unset = 0;
				break;
			case '!~':
				if (strpos($doc[$this->array_key], $this->filterValue)!==false)
					$unset = 0;
				break;
			
			// Cases 9-11 created by highlander
			// case insenstive version of #7 - exclude records that do not contain the text of the criterion
			case 9 :
				if (stripos($doc[$this->array_key], $this->filterValue)===false)
					$unset = 0;
				break;
			case 10 : // case insenstive version of #8 - exclude records that do contain the text of the criterion
				if (stripos($doc[$this->array_key], $this->filterValue)!==false)
					$unset = 0;
				break;
			case 11 : // checks leading character of the field
				$firstChr = strtoupper(substr($doc[$this->array_key], 0, 1));
				if ($firstChr!=$this->filterValue)
					$unset = 0;
				break;
			case 'regex':
				if (preg_match($doc[$this->array_key], $this->filterValue)===false)
					$unset = 0;
				break;
		}
		if($this->flip_mode) $unset = $unset ? 0 : 1;
		return $unset;
	}

	private function get_operator_name($operator_name) {
		if (in_array($operator_name, array(1,'<>','ne'))) {
			return '!=';
		}
		if (in_array($operator_name, array(2,'eq','ne'))) {
			return '==';
		}
		if (in_array($operator_name, array(3,'lt','ne'))) {
			return '<';
		}
		if (in_array($operator_name, array(4,'gt'))) {
			return '>';
		}
		if (in_array($operator_name, array(5,'gte','ge'))) {
			return '>=';
		}
		if (in_array($operator_name, array(6,'lte','le'))) {
			return '<=';
		}
		if (in_array($operator_name, array(7,'find','search','strpos'))) {
			return '=~';
		}
		if ($operator_name==8) {
			return '!~';
		}
		if ($operator_name=='preg') {
			return 'regex';
		}
		return $operator_name;
	}
}
