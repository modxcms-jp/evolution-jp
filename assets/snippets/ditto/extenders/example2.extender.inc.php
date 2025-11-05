<?php

/*
 * Title: Example2
 * Purpose: Example file for basing new Extenders on
*/

// ---------------------------------------------------
// Group: Placeholders
// Defin the values of custom placeholders for access in the tpl like so [+phname+]
// ---------------------------------------------------

$placeholders['example'] = [
    ['pagetitle', '*'],
    'exampleFunction',
    'pagetitle'
];
// Variable: $placeholders['example']
// Add the placeholder example to the custom placeholders list
// with the source pagetitle in both display and backend using the
// exampleFunction callback and pagetitle as the field for QuickEdit.
// If you only needed the placeholder in the frontent you would just
// use 'pagetitle'  as the first value of the array. If the callback
// was in a class use the array($initialized_class,'member') method.

if (!function_exists('exampleFunction')) {
    // wrap functions in !functino_exists statements to ensure that they are not defined twice

    // ---------------------------------------------------
    // Function: exampleFunction
    //
    // Takes the resource array for an individual document
    // and returns the value of the placeholder, in this
    // case the uppercase version of the pagetitle
    // ---------------------------------------------------
    function exampleFunction($resource)
    {
        return strtoupper($resource['pagetitle']);
    }
}
