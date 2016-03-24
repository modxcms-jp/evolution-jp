<?php

/**
 * A utility class for presenting a provided array as a table view.  Includes
 * support for pagination, sorting by any column, providing optional header arrays,
 * providing classes for styling the table, rows, and cells (including alternate
 * row styling), as well as adding form controls to each row.
 * 
 * @author Jason Coward <jason@opengeek.com> (MODx)
 */
class MakeTable {
	var $actionField;
	var $cellAction;
	var $linkAction;
	var $tableWidth;
	var $tableClass;
	var $tableID;
	var $thClass;
	var $rowHeaderClass;
	var $columnHeaderClass;
	var $rowDefaultClass;
	var $rowAlternateClass;
	var $formName;
	var $formAction;
	var $formElementType;
	var $formElementName;
	var $rowAlternatingScheme;
	var $excludeFields;
	var $allOption;
	var $pageNav;
	var $columnWidths;
	var $selectedValues;
	var $pageLimit;
	
	function __construct() {
		global $modx;
		
		$this->fieldHeaders         = array();
		$this->excludeFields        = array();
		$this->actionField          = '';
		$this->cellAction           = '';
		$this->linkAction           = '';
		$this->tableWidth           = '';
		$this->tableClass           = '';
		$this->rowHeaderClass       = '';
		$this->columnHeaderClass    = '';
		$this->rowDefaultClass      = '';
		$this->rowAlternateClass    = 'alt';
		$this->formName             = 'tableForm';
		$this->formAction           = '[~[*id*]~]';
		$this->formElementName      = '';
		$this->formElementType      = '';
		$this->rowAlternatingScheme = 'EVEN';
		$this->allOption            = 0;
		$this->selectedValues       = array();
		$this->extra                = '';
		$this->pageLimit            = $modx->config['number_of_results'];
	}
	
	/**
	 * Retrieves the width of a specific table column by index position.
	 * 
	 * @param $columnPosition The index of the column to get the width for.
	 */
	function getColumnWidth($columnPosition) {
		
		if(!is_array($this->columnWidths) || !$this->columnWidths[$columnPosition])
			return '';
		else
			return sprintf(' width="%s" ', $this->columnWidths[$columnPosition]);
	}
	
	/**
	 * Determines what class the current row should have applied.
	 * 
	 * @param $value The position of the current row being rendered.
	 */
	function determineRowClass($pos) {
		if($pos%2==0 && $this->rowAlternatingScheme==='EVEN') $class = $this->rowAlternateClass;
		else                                                  $class = $this->rowDefaultClass;
		
		return sprintf('class="%s"', $class);
	}
	
	/**
	 * Generates an onclick action applied to the current cell, to execute 
	 * any specified cell actions.
	 * 
	 * @param $value Indicates the INPUT form element type attribute.
	 */
	function getCellAction($currentActionFieldValue) {
		if (!$this->cellAction) return '';
		$action = $this->cellAction.$this->actionField;
		$value  = urlencode($currentActionFieldValue);
		return sprintf(' onclick="javascript:window.location=\'%s=%s\'" ', $action, $value);
	}
	
	/**
	 * Generates the cell content, including any specified action fields values.
	 * 
	 * @param $currentActionFieldValue The value to be applied to the link action.
	 * @param $value The value of the cell.
	 */
	function createCellText($currentActionFieldValue, $cellText) {
		if(!$this->linkAction) return $cellText;
		
		$action = $this->linkAction.$this->actionField;
		$value  = urlencode($currentActionFieldValue);
		return sprintf('<a href="%s=%s">%s</a>',$action, $value, $cellText);
	}
	
	/**
	 * Function to prepare a link generated in the table cell/link actions.
	 * 
	 * @param $value Indicates the INPUT form element type attribute.
	 */
	function prepareLink($path) {
		if (strpos($path, '?')!==false) return "{$path}&";
		else                            return "{$path}?";
	}
	
	/**
	 * Generates the table content.
	 * 
	 * @param $fieldsArray The associative array representing the table rows 
	 * and columns.
	 * @param $fieldHeadersArray An optional array of values for providing
	 * alternative field headers; this is an associative arrays of keys from
	 * the $fieldsArray where the values represent the alt heading content
	 * for each column.
	 */
	
	function create($fieldsArray, $fieldHeadersArray=array()) {return  $this->renderTable($fieldsArray, $fieldHeadersArray);}
	function renderTable($fieldsArray, $fieldHeadersArray=array())
	{
		if (!is_array($fieldsArray)) return '';
		
		if($this->formElementType)
			return $this->_renderWithForm($fieldsArray, $fieldHeadersArray);
		else
			return $this->_render($fieldsArray, $fieldHeadersArray);
	}
	
	function _render($fieldsArray, $fieldHeadersArray=array())
	{
		$i= 0;
		$table  = '';
		$header = '';
		$tr= array();
		foreach ($fieldsArray as $fieldName => $fieldValue)
		{
			$colPosition= 0;
			$_ = array();
			foreach ($fieldValue as $key => $value)
			{
				if (!in_array($key, $this->excludeFields))
				{
					if ($i == 0)
					{
						$width      = $this->getColumnWidth($colPosition);
						$class      = $this->thClass ? sprintf('class="%s"',$this->thClass) : '';
						$headerText = isset($fieldHeadersArray[$key]) ? $fieldHeadersArray[$key] : $key;
						$header .= sprintf('<th %s %s>%s</th>', $width, $class, $headerText);
					}
					$cellText = $this->createCellText($fieldValue[$this->actionField], $value);
					$_[] = sprintf('<td>%s</td>', $cellText);
					$colPosition ++;
				}
			}
			$i ++;
			$tr[] = sprintf("<tr %s>\n%s</tr>", $this->determineRowClass($i), join('',$_));
		}
		$_ = array();
		if($this->tableWidth) $_[] = sprintf('width="%s"', $this->tableWidth);
		if($this->tableClass) $_[] = sprintf('class="%s"', $this->tableClass);
		if($this->tableID)    $_[] = sprintf('id="%s"',    $this->tableID);
		$args = join(' ', $_);
		$vs = array($args, $this->rowHeaderClass, $header, join("\n",$tr));
		$table= vsprintf('<table %s><thead><tr class="%s">%s</tr></thead>%s</table>',$vs);
		$table = str_replace(array('\t','\n'),array("\t","\n"),$table);
		return $table;
	}
	
	function _renderWithForm($fieldsArray, $fieldHeadersArray=array())
	{
		$i= 0;
		$table = '';
		$header = '';
		foreach ($fieldsArray as $fieldName => $fieldValue)
		{
			$table .= sprintf('<tr %s>', $this->determineRowClass($i));
			$currentActionFieldValue= $fieldValue[$this->actionField];
			if (is_array($this->selectedValues))
				$isChecked= array_search($currentActionFieldValue, $this->selectedValues)===false ? 0 : 1;
			else
				$isChecked= false;
			
			$table .= $this->addFormField($currentActionFieldValue, $isChecked);
			
			$colPosition= 0;
			foreach ($fieldValue as $key => $value)
			{
				if (!in_array($key, $this->excludeFields))
				{
					if ($i == 0)
					{
						$class = $this->thClass ? 'class="'.$this->thClass.'"' : '';
						if (empty ($header))
						{
							$allOption = $this->allOption ? '<a href="javascript:clickAll()">all</a>' : '';
							$header .= sprintf('<th style="width:32px" %s>%s</th>', $class, $allOption);
						}
						$headerText = isset($fieldHeadersArray[$key]) ? $fieldHeadersArray[$key] : $key;
						$header .= sprintf('<th %s %s>%s</th>', $this->getColumnWidth($colPosition), $class, $headerText);
					}
					$onclick = $this->getCellAction($currentActionFieldValue);
					$cellText = $this->createCellText($currentActionFieldValue, $value);
					$table .= sprintf('<td %s>%s</td>', $onclick, $cellText);
					$colPosition ++;
				}
			}
			$i ++;
			$table .= '</tr>';
		}
		$_ = array();
		if($this->tableWidth) $_[] = sprintf('width="%s"', $this->tableWidth);
		if($this->tableClass) $_[] = sprintf('class="%s"', $this->tableClass);
		if($this->tableID)    $_[] = sprintf('id="%s"',    $this->tableID);
		$args = join(' ', $_);
		$vs = array($args,$this->rowHeaderClass,$header,$table);
		$table= vsprintf('\n<table %s>\n\t<thead>\n\t<tr class="%s">\n%s\t</tr>\n\t</thead>\n%s</table>\n',$vs);
		$table = str_replace(array('\t','\n'),array("\t","\n"),$table);
		
		if ($this->allOption) $table .= $this->_getClickAllScript();
		if ($this->extra)     $table .= "\n".$this->extra."\n";
		
		return sprintf('<form id="%s" name="%s" action="%s" method="POST">%s</form>', $this->formName,$this->formName,$this->formAction,$table);
	}
	
	function _getClickAllScript() {
		return <<< EOT
<script language="javascript">
toggled = 0;
function clickAll() {
	myform = document.getElementById("'.$this->formName.'");
	for(i=0;i<myform.length;i++) {
		if(myform.elements[i].type==\'checkbox\') {
			myform.elements[i].checked=(toggled?false:true);
		}
	}
	toggled = (toggled?0:1);
}
</script>';
EOT;
	}
	
	/**
	 * Generates optional paging navigation controls for the table.
	 * 
	 * @param $totalRecords The number of records to show per page.
	 * @param $base_url An optional query string to be appended to the paging links
	 */
	function createPagingNavigation($totalRecords, $base_url='') {return $this->renderPagingNavigation($totalRecords, $base_url);}
	function renderPagingNavigation($totalRecords, $base_url='') {
		global $_lang, $modx;
		
		if(!isset($_GET['page'])||!preg_match('@^[1-9][0-9]*$@',$_GET['page']))
			$currentPage = 1;
		else
			$currentPage = $_GET['page'];
		
		$totalPages= ceil($totalRecords / $this->pageLimit);
		if ($totalPages<2) return '';
		
		$navlink = array();
		if(!empty($base_url)) $base_url = "?{$base_url}";
		if (1<$currentPage)
		{
			$navlink[] = $this->createPageLink($base_url, 1, $_lang['pagination_table_first']);
			$navlink[] = $this->createPageLink($base_url, $currentPage -1, '&lt;');
		} else {
			$navlink[] = sprintf('<li><span>%s</span></li>',$_lang['pagination_table_first']);
			$navlink[] = '<li><span>&lt;</span></li>';
		}
		$offset= -4 + ($currentPage < 5 ? (5 - $currentPage) : 0);
		$i= 1;
		while ($i < 10 && ($currentPage + $offset <= $totalPages))
		{
			if ($currentPage == $currentPage + $offset)
				$navlink[] = $this->createPageLink($base_url, $currentPage + $offset, $currentPage + $offset, true);
			else
				$navlink[] = $this->createPageLink($base_url, $currentPage + $offset, $currentPage + $offset);
			$i++;
			$offset++;
		}
		if (0<$totalPages-$currentPage) {
			$navlink[] = $this->createPageLink($base_url, $currentPage +1, '&gt;');
			$navlink[] = $this->createPageLink($base_url, $totalPages, $_lang['pagination_table_last']);
		} else {
			$navlink[] = '<li><span>&gt;</span></li>';
			$navlink[] = sprintf('<li><span>%s</span></li>',$_lang['pagination_table_last']);
		}
		
		if (empty($navlink)) return '';
		else
			return sprintf('<div id="pagination" class="paginate"><ul>%s</ul></div>',join("\n",$navlink));
	}
	
	/**
	 * Creates an individual page link for the paging navigation.
	 * 
	 * @param $path The link for the page, defaulted to the current document.
	 * @param $pageNum The page number of the link.
	 * @param $displayText The text of the link.
	 * @param $currentPage Indicates if the link is to the current page.
	 * @param $qs And optional query string to be appended to the link.
	 */
	function createPageLink($path='', $pageNum, $displayText, $currentPage=false, $qs='') {
		global $modx;
		
		if(empty($path))
		{
    		$p = array();
    		$p[] = "page={$pageNum}";
    		if(!empty($_GET['orderby']))  $p[] = 'orderby='  . $_GET['orderby'];
    		if(!empty($_GET['orderdir'])) $p[] = 'orderdir=' . $_GET['orderdir'];
    		if(!empty($qs))               $p[] = trim($qs,'?&');
			$path = $modx->makeUrl($modx->documentIdentifier, $modx->documentObject['alias'], '?' . join('&',$p));
		}
		else
			$path = $this->prepareLink($path) . "page={$pageNum}";
		
		$currentClass = $currentPage ? 'class="currentPage"' : '';
		$path = htmlspecialchars($path, ENT_QUOTES, $modx->config['modx_charset']);
		return sprintf('<li %s><a href="%s" %s>%s</a></li>', $currentClass, $path, $currentClass, $displayText);
	}
	
	/**
	 * Adds an INPUT form element column to the table.
	 * 
	 * @param $value The value attribute of the element.
	 * @param $isChecked Indicates if the checked attribute should apply to the 
	 * element.
	 */
	function addFormField($value, $isChecked) {
		if (!$this->formElementType) return '';
		
		$checked= $isChecked ? 'checked ': '';
		$formElementName = ($this->formElementName) ? $this->formElementName : $value;
		
		return sprintf('<td><input type="%s" name="%s" value="%s" %s /></td>',$this->formElementType,$formElementName,$value,$checked);
	}
	
	/**
	 * Generates the proper LIMIT clause for queries to retrieve paged results in
	 * a MakeTable $fieldsArray.
	 */
	function handlePaging() {
		$offset= (preg_match('@^[1-9][0-9]*$@',$_GET['page'])) ? $_GET['page'] - 1 : 0;
		return sprintf(' LIMIT %s,%s', $offset*$this->pageLimit, $this->pageLimit);
	}
	
	/**
	 * Generates the SORT BY clause for queries used to retrieve a MakeTable 
	 * $fieldsArray
	 * 
	 * @param $natural_order If true, the results are returned in natural order.
	 */
	function handleSorting($natural_order=false) {
		if($natural_order) return '';
		if(!isset($_GET['orderby'])||empty($_GET['orderby']))
			return '';
		
		$target = $_GET['orderby'];
		$order  = !empty($_GET['orderdir']) ? $_GET['orderdir'] : 'DESC';
		
		return " ORDER BY {$target} {$order}";
	}
	
	/**
	 * Generates a link to order by a specific $fieldsArray key; use to generate
	 * sort by links in the MakeTable $fieldHeadingsArray values.
	 * 
	 * @param $key The $fieldsArray key for the column to sort by.
	 * @param $text The text for the link (e.g. table column header).
	 * @param $qs An optional query string to append to the order by link.
	 */
	function prepareOrderByLink($key, $text, $qs='') {
		global $modx;
		if (!isset($_GET['orderdir'])||empty($_GET['orderdir'])) $orderDir = 'asc';
		elseif(strtolower($_GET['orderdir'])!=='desc')           $orderDir = 'asc';
		else                                                     $orderDir = 'desc';
		
		$qs = rtrim($qs,'&');
		
		return sprintf('<a href="[~%s~]?%s&orderby=%s&orderdir=%s">%s</a>', $modx->documentIdentifier,$qs,$key,$orderDir,$text);
	}
	
	/**
	 * Sets the default link href for all cells in the table.
	 * 
	 * @param $value A URL to execute when table cells are clicked.
	 */
	function setCellAction($path) {
		$this->cellAction= $this->prepareLink($path);
	}
	
	/**
	 * Sets the default link href for the text presented in a cell.
	 * 
	 * @param $value A URL to execute when text within table cells are clicked.
	 */
	function setLinkAction($path) {
		$this->linkAction= $this->prepareLink($path);
	}
	
	/**
	 * Sets the width attribute of the main HTML TABLE.
	 * 
	 * @param $value A valid width attribute for the HTML TABLE tag
	 */
	function setTableWidth($value) {
		$this->tableWidth= $value;
	}
	
	/**
	 * Sets the class attribute of the main HTML TABLE.
	 * 
	 * @param $value A class for the main HTML TABLE. 
	 */
	function setTableClass($value) {
		$this->tableClass= $value;
	}
	
	/**
	 * Sets the id attribute of the main HTML TABLE.
	 * 
	 * @param $value A class for the main HTML TABLE. 
	 */
	function setTableID($value) {
		$this->tableID= $value;
	}
	
	/**
	 * Sets the class attribute of the table header row.
	 * 
	 * @param $value A class for the table header row.
	 */
	function setRowHeaderClass($value) {
		$this->rowHeaderClass= $value;
	}
	
		/**
	 * Sets the class attribute of the table header row.
	 * 
	 * @param $value A class for the table header row.
	 */
	function setThHeaderClass($value) {
		$this->thClass= $value;
	}
	
	/**
	 * Sets the class attribute of the column header row.
	 * 
	 * @param $value A class for the column header row.
	 */
	function setColumnHeaderClass($value) {
		$this->columnHeaderClass= $value;
	}
	
	/**
	 * Sets the class attribute of regular table rows.
	 * 
	 * @param $value A class for regular table rows.
	 */
	
	function setRowRegularClass($value) {$this->setRowDefaultClass($value);}
	function setRowDefaultClass($value) {
		$this->rowDefaultClass= $value;
	}
	
	/**
	 * Sets the class attribute of alternate table rows.
	 * 
	 * @param $value A class for alternate table rows.
	 */	
	function setRowAlternateClass($value) {
		$this->rowAlternateClass= $value;
	}
	
	/**
	 * Sets the type of INPUT form element to be presented as the first column.
	 * 
	 * @param $value Indicates the INPUT form element type attribute.
	 */
	function setFormElementType($value) {
		$this->formElementType= $value;
	}
	
	/**
	 * Sets the name of the INPUT form element to be presented as the first column.
	 * 
	 * @param $value Indicates the INPUT form element name attribute.
	 */
	function setFormElementName($value) {
		$this->formElementName= $value;
	}
	
	/**
	 * Sets the name of the FORM to wrap the table in when a form element has 
	 * been indicated.
	 * 
	 * @param $value Indicates the FORM name attribute.
	 */
	function setFormName($value) {
		$this->formName= $value;
	}
	
	/**
	 * Sets the action of the FORM element.
	 * 
	 * @param $value Indicates the FORM action attribute.
	 */
	function setFormAction($value) {
		$this->formAction= $value;
	}
	
	/**
	 * Excludes fields from the table by array key.
	 * 
	 * @param $value An Array of field keys to exclude from the table.
	 */
	function setExcludeFields($value) {
		$this->excludeFields= $value;
	}

	/**
	 * Sets the table to provide alternate row colors using ODD or EVEN rows
	 * 
	 * @param $value 'ODD' or 'EVEN' to indicate the alternate row scheme.
	 */
	function setRowAlternatingScheme($value) {
		$this->rowAlternatingScheme= $value;
	}
	
	/**
	 * Sets the default field value to be used when appending query parameters
	 * to link actions.
	 * 
	 * @param $value The key of the field to add as a query string parameter.
	 */
	function setActionFieldName($value) {
		$this->actionField= $value;
	}
	
	/**
	 * Sets the width attribute of each column in the array.
	 * 
	 * @param $value An Array of column widths in the order of the keys in the
	 * 			source table array.
	 */
	function setColumnWidths($widthArray) {
		if(!is_array($widthArray)) $widthArray = explode(',', $widthArray);
		foreach($widthArray as $i=>$v) {
			$widthArray[$i] = trim($v);
		}
		$this->columnWidths= $widthArray;
	}
	
	/**
	 * An optional array of values that can be preselected when using 
	 * 
	 * @param $value Indicates the INPUT form element type attribute.
	 */
	function setSelectedValues($valueArray) {
		$this->selectedValues= $valueArray;
	}
	
	/**
	 * Sets extra content to be presented following the table (but within
	 * the form, if a form is being rendered with the table).
	 * 
	 * @param $value A string of additional content.
	 */
	function setExtra($value) {
		$this->extra= $value;
	}
	
	/**
	 * Sets an option to generate a check all link when checkbox is indicated 
	 * as the table formElementType.
	 */
	function setAllOption() {
		$this->allOption= 1;
	}
	function setPageLimit($total) {
		$this->pageLimit = $total;
	}
}
