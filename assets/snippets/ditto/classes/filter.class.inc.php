<?php

/*
 * Title: Filter Class
 * Purpose:
 *  	The Filter class contains all functions relating to filtering,
 * 		the removing of documents from the result set
*/

class filter {
	var $array_key, $filtertype, $filterValue, $filterArgs;

// ---------------------------------------------------
// Function: execute
// Filter documents via either a custom filter or basic filter
// ---------------------------------------------------
	function execute($resource, $filter)
	{
		global $modx;
		foreach ($filter['basic'] AS $currentFilter)
		{
			if (is_array($currentFilter) && count($currentFilter) > 0)
			{
				$this->array_key = $currentFilter['source'];
				
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
				
				$resource = array_filter($resource, array($this, 'basicFilter'));
			}
		}
		foreach ($filter['custom'] AS $currentFilter)
		{
			$resource = array_filter($resource, $currentFilter);
		}
		return $resource;
	}
	
// ---------------------------------------------------
// Function: basicFilter
// Do basic comparison filtering
// ---------------------------------------------------
	
	function basicFilter ($value) {
			$unset = 1;
			switch ($this->filtertype) {
				case '!=' :
				case '<>' :
				case 'ne' :
				case 1 :
					if (!isset ($value[$this->array_key]) || $value[$this->array_key] != $this->filterValue)
						$unset = 0;
					break;
				case '==' :
				case 'eq' :
				case 2 :
					if ($value[$this->array_key] == $this->filterValue)
						$unset = 0;
					break;
				case '<' :
				case 'lt' :
				case 3 :
					if ($value[$this->array_key] < $this->filterValue)
						$unset = 0;
					break;
				case '>' :
				case 'gt' :
				case 4 :
					if ($value[$this->array_key] > $this->filterValue)
						$unset = 0;
					break;
				case '<=' :
				case 'lte' :
				case 'le' :
				case 5 :
					if (!($value[$this->array_key] < $this->filterValue))
						$unset = 0;
					break;
				case '>=' :
				case 'gte' :
				case 'ge' :
				case 6 :
					if (!($value[$this->array_key] > $this->filterValue))
						$unset = 0;
					break;
					
				// Cases 7 & 8 created by MODx Testing Team Member ZAP
				case '=~':
					if (strpos($value[$this->array_key], $this->filterValue)!==FALSE)
						$unset = 0;
					break;
				case '!~':
				case 7 :
					if (strpos($value[$this->array_key], $this->filterValue)===FALSE)
						$unset = 0;
					break;
				case 8 :
					if (strpos($value[$this->array_key], $this->filterValue)!==FALSE)
						$unset = 0;
					break;	
				
				// Cases 9-11 created by highlander
				case 9 : // case insenstive version of #7 - exclude records that do not contain the text of the criterion
					if (strpos(strtolower($value[$this->array_key]), strtolower($this->filterValue))===FALSE)
						$unset = 0;
					break;
				case 10 : // case insenstive version of #8 - exclude records that do contain the text of the criterion
					if (strpos(strtolower($value[$this->array_key]), strtolower($this->filterValue))!==FALSE)
						$unset = 0;
					break;
				case 11 : // checks leading character of the field
					$firstChr = strtoupper(substr($value[$this->array_key], 0, 1));
					if ($firstChr!=$this->filterValue)
						$unset = 0;
					break;				
		}
			return $unset;
	}
	
}
?>