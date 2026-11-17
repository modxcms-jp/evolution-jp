<?php
/*
 * Title: Request
 * Purpose:
 *  	Adds support for changing Ditto parameters via URL (ditto_NAME, or ditto_ID_NAME with &id)
*/
$stripTags = $stripTags ?? 1;
$bad = isset($bad) ? explode(",", $bad) : ["seeThruUnpub", "showInMenuOnly", "showPublishedOnly", "start", "config", "extenders", "dittoID"];
// Never set from the request: SQL conditions, access control, the documents to list,
// and variables used inside the snippet.
// startID and limit are aliases that snippet.ditto.php copies to parents and queryLimit
// after the extenders run, so they are listed here as well.
$never = [
    "where", "hidePrivate", "seeThruUnpub", "showInMenuOnly", "showPublishedOnly", "hideFolders",
    "parents", "startID", "documents", "depth", "orderBy", "queryLimit", "limit", "config",
    "extenders", "dittoID", "ditto", "modx", "placeholders", "filters", "_lang", "language", "format", "save",
];
$good = isset($good)
    ? explode(",", $good)
    : ["display", "total", "paginate", "paginateAlwaysShowLinks", "randomize", "summarize", "noResults",
       "dateSource", "dateFormat", "filter"];

$request = (array) anyv();
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
            $value = evo()->stripTags($value);
        }
        $variables[$saneName] = trim($value);
    }
}
// Numeric settings: read as a non-negative integer (leading zeros allowed, "05" is 5, "0" stays 0).
// An empty or malformed value is the same as not specified, so the default or the snippet call value is used.
// display, total and summarize (an alias of display) also accept "all".
foreach (['display', 'total', 'summarize', 'paginate', 'paginateAlwaysShowLinks'] as $numericName) {
    if (!isset($variables[$numericName])) {
        continue;
    }
    if (in_array($numericName, ['display', 'total', 'summarize'], true) && $variables[$numericName] === 'all') {
        continue;
    }
    $numericValue = to_uintvar($variables[$numericName]);
    if ($numericValue === null) {
        unset($variables[$numericName]);
    } else {
        $variables[$numericName] = $numericValue;
    }
}
// randomize takes 0, 1 or a field name, so it is not numeric. An empty value is the same as not specified.
if (isset($variables['randomize']) && $variables['randomize'] === '') {
    unset($variables['randomize']);
}
extract($variables);

// ditto_filter, ditto_filter_2, ... (or ditto_filter_1, ditto_filter_2, ...) with continuous numbering
// are joined into one &filter

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
