<?php

/*
 * Title: Example3
 * Purpose:
 *  	Example file for basing new Extenders on
*/

// ---------------------------------------------------
// Group: Filters
// Define custom or basic filters within the extender to expand Ditto's filtering capabilities
// ---------------------------------------------------

$filters['custom']['exampleFilter'] = array(
	'pagetitle'
	, 'exampleFilter'
);

// Variable: $filters['custom']['exampleFilter']
// Add the filter exampleFilter to the custom filters 
// list with the source pagetitle and the callback
// exampleFilter

$filters['parsed'][] = array(
	'exampleFilter' => array(
		'source' => 'id'
		, 'value' => '9239423942'
		, 'mode' => '2'
	)
);
	// Variable: $filters['parsed'][]
	// Add the pre-parsed filter to the parsed filters list with the
	// source as id, the value of 9239423942 and the mode 2

if (!function_exists('exampleFilter')) {
	// wrap functions in !functino_exists statements to ensure that they are not defined twice
	
	// ---------------------------------------------------
	// Function: exampleFilter
	// 
	// Takes the resource array for an individual document
	// and asks for the return of a 0 or 1 with 1 removing 
	// the document and 0 leaving it in the result set. 
	// In this case, if the lower case value of the pagetitle
	// is foo, it is removed while all other documents are shown
	// ---------------------------------------------------
	function exampleFilter($resource) {
		if (strtolower($resource['pagetitle']) === 'foo') {
			return 1;
		}
        return 0;
    }
}
