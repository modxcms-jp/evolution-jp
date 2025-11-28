<?php

/**
 * A utility class for presenting a provided array as a table view.  Includes
 * support for pagination, sorting by any column, providing optional header arrays,
 * providing classes for styling the table, rows, and cells (including alternate
 * row styling), as well as adding form controls to each row.
 *
 * @author Jason Coward <jason@opengeek.com> (MODx)
 */
class MakeTable
{
    public $fieldHeaders;
    public $actionField;
    public $cellAction;
    public $linkAction;
    public $tableWidth;
    public $tableClass;
    public $tableID;
    public $thClass;
    public $rowHeaderClass;
    public $columnHeaderClass;
    public $rowDefaultClass;
    public $rowAlternateClass;
    public $formName;
    public $formAction;
    public $formElementType;
    public $formElementName;
    public $rowAlternatingScheme;
    public $excludeFields;
    public $allOption;
    public $pageNav;
    public $columnWidths;
    public $selectedValues;
    public $extra;
    public $pageLimit;

    public function __construct()
    {
        $this->fieldHeaders = [];
        $this->excludeFields = [];
        $this->actionField = '';
        $this->cellAction = '';
        $this->linkAction = '';
        $this->tableWidth = '';
        $this->tableClass = '';
        $this->rowHeaderClass = '';
        $this->columnHeaderClass = '';
        $this->rowDefaultClass = '';
        $this->rowAlternateClass = 'alt';
        $this->formName = 'tableForm';
        $this->formAction = '[~[*id*]~]';
        $this->formElementName = '';
        $this->formElementType = '';
        $this->rowAlternatingScheme = 'EVEN';
        $this->allOption = 0;
        $this->selectedValues = [];
        $this->extra = '';
        $this->pageLimit = evo()->config('number_of_results');
    }

    /**
     * Retrieves the width of a specific table column by index position.
     *
     * @param $columnPosition The index of the column to get the width for.
     */
    public function getColumnWidth($columnPosition)
    {

        if (!is_array($this->columnWidths) || !$this->columnWidths[$columnPosition]) {
            return '';
        }

        return sprintf(' width="%s" ', $this->columnWidths[$columnPosition]);
    }

    /**
     * Determines what class the current row should have applied.
     *
     * @param $value The position of the current row being rendered.
     */
    public function determineRowClass($pos)
    {
        if ($pos % 2 == 0 && $this->rowAlternatingScheme === 'EVEN') {
            return sprintf('class="%s"', $this->rowAlternateClass);
        }
        return sprintf('class="%s"', $this->rowDefaultClass);

    }

    /**
     * Generates an onclick action applied to the current cell, to execute
     * any specified cell actions.
     *
     * @param $value Indicates the INPUT form element type attribute.
     */
    public function getCellAction($currentActionFieldValue)
    {
        if (!$this->cellAction) {
            return '';
        }
        return sprintf(
            ' onclick="javascript:window.location=\'%s=%s\'" ',
            $this->cellAction . $this->actionField,
            urlencode($currentActionFieldValue)
        );
    }

    /**
     * Generates the cell content, including any specified action fields values.
     *
     * @param $currentActionFieldValue The value to be applied to the link action.
     * @param $value The value of the cell.
     */
    public function createCellText($currentActionFieldValue, $cellText)
    {
        if (!$this->linkAction) {
            return $cellText;
        }
        return sprintf(
            '<a href="%s=%s">%s</a>',
            $this->linkAction . $this->actionField,
            urlencode($currentActionFieldValue),
            $cellText
        );
    }

    /**
     * Function to prepare a link generated in the table cell/link actions.
     *
     * @param $value Indicates the INPUT form element type attribute.
     */
    public function prepareLink($path)
    {
        if (strpos($path, '?') === false) {
            return $path . '?';
        }
        return $path . '&';
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

    public function create($fieldsArray, $fieldHeadersArray = [])
    {
        return $this->renderTable($fieldsArray, $fieldHeadersArray);
    }

    public function renderTable($fieldsArray, $fieldHeadersArray = [])
    {
        if (!is_array($fieldsArray)) {
            return '';
        }
        if (!$this->formElementType) {
            return $this->_render($fieldsArray, $fieldHeadersArray);
        }
        return $this->_renderWithForm($fieldsArray, $fieldHeadersArray);
    }

    public function _render($fieldsArray, $fieldHeadersArray = [])
    {
        $i = 0;
        $header = '';
        $tr = [];
        foreach ($fieldsArray as $fieldName => $fieldValue) {
            $colPosition = 0;
            $_ = [];
            foreach ($fieldValue as $key => $value) {
                if (in_array($key, $this->excludeFields)) {
                    continue;
                }
                if ($i == 0) {
                    $header .= sprintf(
                        '<th %s %s>%s</th>',
                        $this->getColumnWidth($colPosition),
                        $this->thClass ? sprintf('class="%s"', $this->thClass) : '',
                        isset($fieldHeadersArray[$key]) ? $fieldHeadersArray[$key] : $key
                    );
                }
                $fieldText = $fieldValue[$this->actionField] ?? '';
                $_[] = sprintf(
                    '<td>%s</td>',
                    $this->createCellText(
                        $fieldText,
                        $value
                    )
                );
                $colPosition++;
            }
            $i++;
            $tr[] = sprintf(
                "<tr %s>\n%s</tr>",
                $this->determineRowClass($i),
                implode('', $_)
            );
        }
        $_ = [];
        if ($this->tableWidth) {
            $_[] = sprintf('width="%s"', $this->tableWidth);
        }
        if ($this->tableClass) {
            $_[] = sprintf('class="%s"', $this->tableClass);
        }
        if ($this->tableID) {
            $_[] = sprintf('id="%s"', $this->tableID);
        }
        return str_replace(
            ['\t', '\n'],
            ["\t", "\n"],
            vsprintf(
                '<table %s><thead><tr class="%s">%s</tr></thead>%s</table>',
                [
                    implode(' ', $_),
                    $this->rowHeaderClass,
                    $header,
                    implode("\n", $tr)
                ]
            )
        );
    }

    public function _renderWithForm($fieldsArray, $fieldHeadersArray = [])
    {
        $i = 0;
        $table = '';
        $header = '';
        foreach ($fieldsArray as $fieldName => $fieldValue) {
            $table .= sprintf('<tr %s>', $this->determineRowClass($i));
            $currentActionFieldValue = $fieldValue[$this->actionField];
            if (is_array($this->selectedValues)) {
                $isChecked = in_array($currentActionFieldValue, $this->selectedValues) ? 1 : 0;
            } else {
                $isChecked = false;
            }

            $table .= $this->addFormField(
                $currentActionFieldValue,
                $isChecked
            );

            $colPosition = 0;
            foreach ($fieldValue as $key => $value) {
                if (in_array($key, $this->excludeFields)) {
                    continue;
                }
                if ($i == 0) {
                    if (empty ($header)) {
                        $header .= sprintf(
                            '<th style="width:32px" %s>%s</th>',
                            $this->thClass ? 'class="' . $this->thClass . '"' : '',
                            $this->allOption ? '<a href="javascript:clickAll()">all</a>' : ''
                        );
                    }
                    $header .= sprintf(
                        '<th %s %s>%s</th>',
                        $this->getColumnWidth($colPosition),
                        $this->thClass ? 'class="' . $this->thClass . '"' : '',
                        isset($fieldHeadersArray[$key]) ? $fieldHeadersArray[$key] : $key
                    );
                }
                $table .= sprintf(
                    '<td %s>%s</td>',
                    $this->getCellAction($currentActionFieldValue),
                    $this->createCellText($currentActionFieldValue, $value)
                );
                $colPosition++;
            }
            $i++;
            $table .= '</tr>';
        }
        $_ = [];
        if ($this->tableWidth) {
            $_[] = sprintf('width="%s"', $this->tableWidth);
        }
        if ($this->tableClass) {
            $_[] = sprintf('class="%s"', $this->tableClass);
        }
        if ($this->tableID) {
            $_[] = sprintf('id="%s"', $this->tableID);
        }
        $table = str_replace(
            ['\t', '\n'],
            ["\t", "\n"],
            vsprintf(
                '\n<table %s>\n\t<thead>\n\t<tr class="%s">\n%s\t</tr>\n\t</thead>\n%s</table>\n',
                [
                    implode(' ', $_),
                    $this->rowHeaderClass,
                    $header,
                    $table
                ]
            )
        );
        if ($this->allOption) {
            $table .= $this->_getClickAllScript();
        }
        if ($this->extra) {
            $table .= "\n" . $this->extra . "\n";
        }

        return sprintf(
            '<form id="%s" name="%s" action="%s" method="POST">%s</form>',
            $this->formName,
            $this->formName,
            $this->formAction,
            $table
        );
    }

    protected function _getClickAllScript()
    {
        return <<< EOT
<script>
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
</script>'
EOT;
    }

    /**
     * Generates optional paging navigation controls for the table.
     *
     * @param $totalRecords The number of records to show per page.
     * @param $base_url An optional query string to be appended to the paging links
     */
    public function createPagingNavigation($totalRecords, $base_url = '')
    {
        return $this->renderPagingNavigation($totalRecords, $base_url);
    }

    public function renderPagingNavigation($totalRecords, $base_url = '')
    {
        global $_lang, $modx;

        $currentPage = getv('page') && preg_match('@^[1-9][0-9]*$@', getv('page')) ? getv('page') : 1;

        $totalPages = ceil($totalRecords / $this->pageLimit);
        if ($totalPages < 2) {
            return '';
        }

        $navlink = [];
        if (!empty($base_url)) {
            $base_url = "?{$base_url}";
        }
        if (1 < $currentPage) {
            $navlink[] = $this->createPageLink($base_url, 1, $_lang['pagination_table_first']);
            $navlink[] = $this->createPageLink($base_url, $currentPage - 1, '&lt;');
        } else {
            $navlink[] = sprintf('<li><span>%s</span></li>', $_lang['pagination_table_first']);
            $navlink[] = '<li><span>&lt;</span></li>';
        }
        $offset = -4 + ($currentPage < 5 ? (5 - $currentPage) : 0);
        $i = 1;
        while ($i < 10 && ($currentPage + $offset <= $totalPages)) {
            if ($currentPage == $currentPage + $offset) {
                $navlink[] = $this->createPageLink($base_url, $currentPage + $offset, $currentPage + $offset, true);
            } else {
                $navlink[] = $this->createPageLink($base_url, $currentPage + $offset, $currentPage + $offset);
            }
            $i++;
            $offset++;
        }
        if (0 < $totalPages - $currentPage) {
            $navlink[] = $this->createPageLink($base_url, $currentPage + 1, '&gt;');
            $navlink[] = $this->createPageLink($base_url, $totalPages, $_lang['pagination_table_last']);
        } else {
            $navlink[] = '<li><span>&gt;</span></li>';
            $navlink[] = sprintf('<li><span>%s</span></li>', $_lang['pagination_table_last']);
        }

        if (empty($navlink)) {
            return '';
        }
        return sprintf('<div id="pagination" class="paginate"><ul>%s</ul></div>', implode("\n", $navlink));
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
    public function createPageLink($path = '', $pageNum=0, $displayText='', $currentPage = false, $qs = '')
    {
        global $modx;

        if (empty($path)) {
            $p = [];
            $p[] = "page={$pageNum}";
            if (getv('orderby')) {
                $p[] = 'orderby=' . getv('orderby');
            }
            if (getv('orderdir')) {
                $p[] = 'orderdir=' . getv('orderdir');
            }
            if (!empty($qs)) {
                $p[] = trim($qs, '?&');
            }
            $path = evo()->makeUrl(
                evo()->documentIdentifier,
                $modx->documentObject['alias'],
                '?' . implode('&', $p)
            );
        } else {
            $path = $this->prepareLink($path) . "page={$pageNum}";
        }

        return sprintf(
            '<li %s><a href="%s" %s>%s</a></li>',
            $currentPage ? 'class="currentPage"' : '',
            hsc($path, ENT_QUOTES),
            $currentPage ? 'class="currentPage"' : '',
            $displayText
        );
    }

    /**
     * Adds an INPUT form element column to the table.
     *
     * @param $value The value attribute of the element.
     * @param $isChecked Indicates if the checked attribute should apply to the
     * element.
     */
    public function addFormField($value, $isChecked)
    {
        if (!$this->formElementType) {
            return '';
        }

        return sprintf(
            '<td><input type="%s" name="%s" value="%s" %s /></td>',
            $this->formElementType,
            $this->formElementName ? $this->formElementName : $value,
            $value,
            $isChecked ? 'checked ' : ''
        );
    }

    /**
     * Generates the proper LIMIT clause for queries to retrieve paged results in
     * a MakeTable $fieldsArray.
     */
    public function handlePaging()
    {
        $offset = (preg_match('@^[1-9][0-9]*$@', getv('page'))) ? getv('page') - 1 : 0;
        return sprintf(' LIMIT %s,%s', $offset * $this->pageLimit, $this->pageLimit);
    }

    /**
     * Generates the SORT BY clause for queries used to retrieve a MakeTable
     * $fieldsArray
     *
     * @param $natural_order If true, the results are returned in natural order.
     */
    public function handleSorting($natural_order = false)
    {
        if ($natural_order) {
            return '';
        }
        if (!getv('orderby')) {
            return '';
        }

        return sprintf(
            ' ORDER BY %s %s',
            getv('orderby'),
            !getv('orderdir') ? 'DESC' : getv('orderdir')
        );
    }

    /**
     * Generates a link to order by a specific $fieldsArray key; use to generate
     * sort by links in the MakeTable $fieldHeadingsArray values.
     *
     * @param $key The $fieldsArray key for the column to sort by.
     * @param $text The text for the link (e.g. table column header).
     * @param $qs An optional query string to append to the order by link.
     */
    public function prepareOrderByLink($key, $text, $qs = '')
    {
        return sprintf(
            '<a href="[~%s~]?%s&orderby=%s&orderdir=%s">%s</a>',
            evo()->documentIdentifier,
            rtrim($qs, '&'),
            $key,
            getv('orderdir') && strtolower(getv('orderdir')) === 'desc' ? 'desc' : 'asc',
            $text
        );
    }

    /**
     * Sets the default link href for all cells in the table.
     *
     * @param $value A URL to execute when table cells are clicked.
     */
    public function setCellAction($path)
    {
        $this->cellAction = $this->prepareLink($path);
    }

    /**
     * Sets the default link href for the text presented in a cell.
     *
     * @param $value A URL to execute when text within table cells are clicked.
     */
    public function setLinkAction($path)
    {
        $this->linkAction = $this->prepareLink($path);
    }

    /**
     * Sets the width attribute of the main HTML TABLE.
     *
     * @param $value A valid width attribute for the HTML TABLE tag
     */
    public function setTableWidth($value)
    {
        $this->tableWidth = $value;
    }

    /**
     * Sets the class attribute of the main HTML TABLE.
     *
     * @param $value A class for the main HTML TABLE.
     */
    public function setTableClass($value)
    {
        $this->tableClass = $value;
    }

    /**
     * Sets the id attribute of the main HTML TABLE.
     *
     * @param $value A class for the main HTML TABLE.
     */
    public function setTableID($value)
    {
        $this->tableID = $value;
    }

    /**
     * Sets the class attribute of the table header row.
     *
     * @param $value A class for the table header row.
     */
    public function setRowHeaderClass($value)
    {
        $this->rowHeaderClass = $value;
    }

    /**
     * Sets the class attribute of the table header row.
     *
     * @param $value A class for the table header row.
     */
    public function setThHeaderClass($value)
    {
        $this->thClass = $value;
    }

    /**
     * Sets the class attribute of the column header row.
     *
     * @param $value A class for the column header row.
     */
    public function setColumnHeaderClass($value)
    {
        $this->columnHeaderClass = $value;
    }

    /**
     * Sets the class attribute of regular table rows.
     *
     * @param $value A class for regular table rows.
     */

    public function setRowRegularClass($value)
    {
        $this->setRowDefaultClass($value);
    }

    public function setRowDefaultClass($value)
    {
        $this->rowDefaultClass = $value;
    }

    /**
     * Sets the class attribute of alternate table rows.
     *
     * @param $value A class for alternate table rows.
     */
    public function setRowAlternateClass($value)
    {
        $this->rowAlternateClass = $value;
    }

    /**
     * Sets the type of INPUT form element to be presented as the first column.
     *
     * @param $value Indicates the INPUT form element type attribute.
     */
    public function setFormElementType($value)
    {
        $this->formElementType = $value;
    }

    /**
     * Sets the name of the INPUT form element to be presented as the first column.
     *
     * @param $value Indicates the INPUT form element name attribute.
     */
    public function setFormElementName($value)
    {
        $this->formElementName = $value;
    }

    /**
     * Sets the name of the FORM to wrap the table in when a form element has
     * been indicated.
     *
     * @param $value Indicates the FORM name attribute.
     */
    public function setFormName($value)
    {
        $this->formName = $value;
    }

    /**
     * Sets the action of the FORM element.
     *
     * @param $value Indicates the FORM action attribute.
     */
    public function setFormAction($value)
    {
        $this->formAction = $value;
    }

    /**
     * Excludes fields from the table by array key.
     *
     * @param $value An Array of field keys to exclude from the table.
     */
    public function setExcludeFields($value)
    {
        $this->excludeFields = $value;
    }

    /**
     * Sets the table to provide alternate row colors using ODD or EVEN rows
     *
     * @param $value 'ODD' or 'EVEN' to indicate the alternate row scheme.
     */
    public function setRowAlternatingScheme($value)
    {
        $this->rowAlternatingScheme = $value;
    }

    /**
     * Sets the default field value to be used when appending query parameters
     * to link actions.
     *
     * @param $value The key of the field to add as a query string parameter.
     */
    public function setActionFieldName($value)
    {
        $this->actionField = $value;
    }

    /**
     * Sets the width attribute of each column in the array.
     *
     * @param $value An Array of column widths in the order of the keys in the
     *            source table array.
     */
    public function setColumnWidths($widthArray)
    {
        if (!is_array($widthArray)) {
            $widthArray = explode(',', $widthArray);
        }
        foreach ($widthArray as $i => $v) {
            $widthArray[$i] = trim($v);
        }
        $this->columnWidths = $widthArray;
    }

    /**
     * An optional array of values that can be preselected when using
     *
     * @param $value Indicates the INPUT form element type attribute.
     */
    public function setSelectedValues($valueArray)
    {
        $this->selectedValues = $valueArray;
    }

    /**
     * Sets extra content to be presented following the table (but within
     * the form, if a form is being rendered with the table).
     *
     * @param $value A string of additional content.
     */
    public function setExtra($value)
    {
        $this->extra = $value;
    }

    /**
     * Sets an option to generate a check all link when checkbox is indicated
     * as the table formElementType.
     */
    public function setAllOption()
    {
        $this->allOption = 1;
    }

    public function setPageLimit($total)
    {
        $this->pageLimit = $total;
    }
}
