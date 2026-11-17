<?php

/*
 * Title: Date Filter
 * Purpose:
 *  	Filtering companion to Reflect or other date based filtering
*/

// ---------------------------------------------------
// Date Filter Class
// ---------------------------------------------------

if (!class_exists('dateFilter')) {
    class dateFilter
    {
        public $month, $year, $day, $dateSource;

        function __construct($month, $year, $day, $dateSource)
        {
            $this->month = $month;
            $this->year = $year;
            $this->day = $day;
            $this->dateSource = $dateSource;
        }

        public function execute($value)
        {
            $month = $this->month;
            $year = $this->year;
            $day = $this->day;
            $unset = 1;
            $min = $max = null;

            if ($year && !$month) { // Year only e.g. 2007 (a day without a month is ignored)
                $min = mktime(0, 0, 0, 1, 1, $year);
                $max = mktime(23, 59, 59, 12, 31, $year);
            } else if ($year && $month && !$day) { // Year and month e.g. 2007-01
                $min = mktime(0, 0, 0, $month, 1, $year);
                $max = mktime(23, 59, 59, $month, date('t', $min), $year);
            } else if ($year && $month && $day) { // Year month and day e.g. 2007-01-11
                $min = mktime(0, 0, 0, $month, $day, $year);
                $max = mktime(23, 59, 59, $month, $day, $year);
            }

            if ($min === null) { // no year: nothing to filter by
                return $unset;
            }
            $compare = $value[$this->dateSource] ?? 0;
            if ($compare < $min || $compare > $max) {
                $unset = 0;
            }
            return $unset;
        }
    }
}

// ---------------------------------------------------
// Date Filter Parameters
// ---------------------------------------------------

$source = $dateFilterSource ?? 'get';
$dateSource = $dateSource ?? 'createdon';
$dateFilterDefault = $dateFilterDefault ?? 0;
if ($source === 'get') {
    // 先頭のゼロも含めて 4 桁まで（'02' は 2、'02026' は 0）
    $readDatePart = static function ($name) use ($dittoID) {
        $raw = get_strvar($dittoID . $name);
        return strlen($raw) <= 4 ? to_uintvar($raw, 0) : 0;
    };
    $year = $readDatePart('year');
    $month = $readDatePart('month');
    $day = $readDatePart('day');
} elseif ($source === 'params') {
    $month = isset($month) ? (int)$month : 0;
    $year = isset($year) ? (int)$year : 0;
    $day = isset($day) ? (int)$day : 0;
} else {
    $year = $month = $day = 0;
    $sourceValue = request_strvar($dittoID . $source);
    if ($sourceValue) {
        $date = getdate(strtotime($sourceValue));
        $year = $date['year'];
        $month = $date['mon'];
        $day = $date['mday'];
    }
}

// ---------------------------------------------------
// Date Filter Defaults
// ---------------------------------------------------


switch ($dateFilterDefault) {
    case 1:
        $cDate = getdate();
        $year = ($year) ? $year : $cDate['year'];
        break;

    case 2:
        $cDate = getdate();
        $year = ($year) ? $year : $cDate['year'];
        $month = ($month) ? $month : $cDate['mon'];
        break;

    case 3:
        $cDate = getdate();
        $year = ($year) ? $year : $cDate['year'];
        $month = ($month) ? $month : $cDate['mon'];
        $day = ($day) ? $day : $cDate['mday'];
        break;
}

// ---------------------------------------------------
// Date Filter Placeholders
// ---------------------------------------------------

if ($year) {
    evo()->setPlaceholder($dittoID . 'year', $year);
}
if ($month && $year) {
    $month_text = date('F', mktime(10, 10, 10, $month, 10, $year));
    evo()->setPlaceholder($dittoID . 'month', $month_text);
    evo()->setPlaceholder($dittoID . 'month_numeric', $month);
}
if ($day && $month && $year) {
    evo()->setPlaceholder($dittoID . 'day', $day);
}

// ---------------------------------------------------
// Date Filter Execution
// ---------------------------------------------------
if ($year) {
    $dateFilterOject = new dateFilter($month, $year, $day, $dateSource);
    $filters['custom']['dateFilter'] = [$dateSource, [$dateFilterOject, 'execute']];
}
