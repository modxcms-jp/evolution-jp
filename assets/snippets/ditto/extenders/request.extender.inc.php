<?php
/*
 * Title: Request
 * Purpose:
 *  	Adds support for changing Ditto parameters via URL
 *
 * Note:
 * 		- All variables must be prefixed with ditto_ for the snippet to recognize them!
 * 		- If a Ditto id is set use the sntax
*/
$variables = [];
$stripTags = isset($stripTags) ? $stripTags : 1;
/*
	Param: stripTags

	Purpose:
	Remove HTML tags from the parameters provided

	Options:
	0 - off
	1 - on

	Default:
	1 - on
*/
$bad = isset($bad) ? explode(",", $bad) : ["seeThruUnpub", "showInMenuOnly", "showPublishedOnly", "debug", "start", "config", "extenders", "dittoID"];
/*
	Param: bad

	Purpose:
	Parameters that are not allowed to be set

	Options:
	Any valid Ditto options separated by commas

	Default:
	"seeThruUnpub,showInMenuOnly,showPublishedOnly,debug,start,config,extenders,dittoID"
*/
// Never set from the request: SQL conditions, access control, the documents to list,
// and variables used inside the snippet
$never = [
    "where", "hidePrivate", "seeThruUnpub", "showInMenuOnly", "showPublishedOnly", "hideFolders",
    "parents", "documents", "depth", "orderBy", "queryLimit", "debug", "config", "extenders", "dittoID",
    "ditto", "modx", "placeholders", "filters", "_lang", "language", "format", "save",
];
$good = isset($good)
    ? explode(",", $good)
    : ["display", "total", "paginate", "paginateAlwaysShowLinks", "randomize", "summarize", "noResults",
       "dateSource", "dateFormat", "filter"];

$request = (array) anyv();
/*
        Param: good

        Purpose:
        Parameters that are allowed to be set

	Options:
	Any valid Ditto options separated by commas

	Default:
	"display,total,paginate,paginateAlwaysShowLinks,randomize,summarize,noResults,
	dateSource,dateFormat,filter" (and filter_1, filter_2, ...)
	Templates (tpl, tplAlt, ...) are set from the request only when listed in &good
*/
$variables = [];
foreach ($request as $name => $value) {
    if (!is_string($value) || strpos($name, "ditto_") !== 0) {
        continue;
    }
    $saneName = str_replace($dittoID, "", substr($name, 6));
    $dID = ($dittoID == "") ? true : strpos($name, $dittoID);
    $allowed = in_array($saneName, $good, true)
        || (in_array("filter", $good, true) && preg_match('/^filter_[0-9]+$/', $saneName));
    if ($dID && $allowed && !in_array($saneName, $bad, true) && !in_array($saneName, $never, true)
        && !preg_match("/[\^`~!\/@\\#\}\$%:;\)\(\{&\*=\|'\+]/", $value)) {
        if ($stripTags) {
            $value = $modx->stripTags($value);
        }
        $variables[$saneName] = trim($value);
    }
}
/*
	Param: dbg

	Purpose:
	Output variables being set (only with &debug)

        Options:
        0 - off
        1 - on

        Default:
        0 - off
*/
$debugRequest = anyv($dittoID . "dbg", 0);
if ($debugRequest == 1 && !empty($debug)) {
    echo hsc(print_r($variables, true));
}
extract($variables);

// ------------------------------------------------------------------------------//
// Kudo's MultiFilter Code 														 //
// ------------------------------------------------------------------------------//
// Accepts ditto_filter, ditto_filter_2, with continuous numbering				 //
// Note: For complex filtering start with ditto_filter_1 (with one as number)!   //
// ------------------------------------------------------------------------------//

if (isset($filter) && isset($filter_2)) {
    $i = 2;
    while (isset(${'filter_' . $i})) {
        $filter .= '|' . ${'filter_' . $i};
        $i++;
    }
} elseif (!isset($filter) && isset($filter_1)) {
    $filter = $filter_1;
    $i = 2;
    while (isset(${'filter_' . $i})) {
        $filter .= '|' . ${'filter_' . $i};
        $i++;
    }
}
