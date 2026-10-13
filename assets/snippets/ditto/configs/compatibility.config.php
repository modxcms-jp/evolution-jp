<?php

/*
 * Title: Compatibility
 * Purpose:
 *  	Backwards compatibility file for use with Ditto 1.0.2 calls
*/

// ---------------------------------------------------
// Group: Parameters
// $newParameters = $oldParameter;
// Only the old parameters given to the call are moved to the new ones.
// ---------------------------------------------------

if (isset($startID)) {
    $parents = isset($parents) ? $parents . ',' . $startID : $startID;
}

if (isset($commentsChunk)) {
    $removeChunk = isset($removeChunk) ? $commentsChunk . ',' . $removeChunk : $commentsChunk;
}

if (isset($hiddenTVs)) {
    $hiddenFields = isset($hiddenFields) ? $hiddenTVs . ',' . $hiddenFields : $hiddenTVs;
}

if (isset($dateFormatType)) {
    $dateSource = $dateFormatType;
}

if (isset($descendentDepth)) {
    $depth = $descendentDepth;
}

if (isset($rssLanguage)) {
    $abbrLanguage = $rssLanguage;
}

if (isset($tplAltRows)) {
    $tplAlt = $tplAltRows;
}

if (isset($tplFirstRow)) {
    $tplFirst = $tplFirstRow;
}

if (isset($tplLastRow)) {
    $tplLast = $tplLastRow;
}

if (isset($tplArchivePrevious)) {
    $tplPaginatePrevious = $tplArchivePrevious;
}

if (isset($tplArchiveNext)) {
    $tplPaginateNext = $tplArchiveNext;
}

if (isset($emptyText)) {
    $noResults = $emptyText;
}

if (!isset($extenders)) {
    $extenders = [];
} elseif (!is_array($extenders)) {
    $extenders = explode(',', $extenders);
}
$extenders[] = "summary";
// load the summary extender
