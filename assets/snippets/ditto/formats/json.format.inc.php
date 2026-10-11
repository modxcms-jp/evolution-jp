<?php
/*
 * Title: JSON
 * Purpose:
 *  	Collection of parameters, functions, and classes that expand
 *  	Ditto's output capabilities to include JSON
*/

if (!defined('MODX_BASE_PATH') || strpos(str_replace('\\', '/', __FILE__), MODX_BASE_PATH) !== 0) exit;
$modx->documentObject['contentType'] = 'application/json';
// set json placeholders
$json_placeholders['json_lang'] = (isset($abbrLanguage)) ? $abbrLanguage : $_lang['abbr_lang'];
/*
	Param: abbrLanguage

	Purpose:
	Language for the JSON document

	Options:
	Any valid 2 character language abbreviation

	Default:
	[LANG]

	Related:
	- <language>
*/
$json_placeholders['json_copyright'] = isset($copyright) ? $copyright : $_lang['default_copyright'];
/*
	Param: copyright

	Purpose:
	Copyright message to embed in the JSON document

	Options:
	Any text

	Default:
	[LANG]
*/
$json_placeholders['json_link'] = MODX_SITE_URL . "[~" . $modx->documentObject['id'] . "~]";
$json_placeholders['json_ttl'] = isset($ttl) ? intval($ttl) : 120;
/*
	Param: ttl

	Purpose:
	Time to live for the JSON feed

	Options:
	Any integer greater than 1

	Default:
	120
*/
// JSON string body (without the quotes); values keep the HTML escaping as before
if (!function_exists('ditto_json_str')) {
    function ditto_json_str($value)
    {
        $json = json_encode(
            (string)$value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
        );
        return $json === false ? '' : substr($json, 1, -1);
    }
}
// JSONP: only a JavaScript identifier (e.g. callback or obj.method) is allowed as the callback name
$jsonp = anyv($dittoID . 'jsonp', '');
$jsonp = (is_string($jsonp) && preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*(\.[A-Za-z_$][A-Za-z0-9_$]*)*$/', $jsonp)) ? $jsonp : '';
// values put inside the JSON strings of the header
foreach (['json_lang', 'json_copyright', 'json_link'] as $k) {
    $json_placeholders[$k] = ditto_json_str($json_placeholders[$k] ?? '');
}
$json_placeholders['json_title'] = ditto_json_str(evo()->documentObject['pagetitle'] ?? '');
$json_placeholders['json_doc_description'] = ditto_json_str(evo()->documentObject['description'] ?? '');

// set tpl JSON placeholders
$placeholders['*'] = "json_parameters";
if (!function_exists("json_parameters")) {
    function json_parameters($placeholders)
    {
        global $modx;
        $jsonArr = [];
        foreach ($placeholders as $name => $value) {
            if (is_array($value)) {
                continue;
            }
            if ($name == 'date' && !preg_match('@^[0-9]+$@', (string)$value)) {
                $value = $modx->getUnixtimeFromDateString($value);
            }
            $value = htmlspecialchars((string)$value, ENT_QUOTES, $modx->config['modx_charset']);
            $jsonArr["json_{$name}"] = ditto_json_str($value);
        }
        $placeholders = array_merge($jsonArr, $placeholders);
        return $placeholders;
    }
}
// ---------------------------------------------------
// JSON Templates
// ---------------------------------------------------

$json_header = '
{
 "title":"[+json_title+]",
 "link":"[+json_link+]",
 "description":"[+json_doc_description+]",
 "language":"[+json_lang+]",
 "copyright":"[+json_copyright+]",
 "ttl":"[+json_ttl+]",
 "entries":[
';
// not heredoc because { cannont be used as a char in heredoc

$json_tpl = <<<TPL
	{
		"title":"[+json_pagetitle+]",
		"link":"[(site_url)][~[+id+]~]",
		"date":"[+json_date+]",
		"guid":"[(site_url)][~[+id+]~]",
		"author":"[+json_author+]",
		"description":"[+json_description+]",
		"introtext":"[+json_introtext+]"
	},

TPL;

$json_tpl_last = <<<TPL
	{
		"title":"[+json_pagetitle+]",
		"link":"[(site_url)][~[+id+]~]",
		"date":"[+json_date+]",
		"guid":"[(site_url)][~[+id+]~]",
		"author":"[+json_author+]",
		"description":"[+json_description+]",
		"introtext":"[+json_introtext+]"
	}
TPL;

$json_footer = <<<TPL

]
}
TPL;

// ---------------------------------------------------
// Pass JSON Templates To Snippet
// ---------------------------------------------------

$header = isset($header) ? $header : template::replace($json_placeholders, $json_header);

$tpl = isset($tpl) ? $tpl : "@CODE:{$json_tpl}";

$tplLast = isset($tplLast) ? $tplLast : "@CODE:{$json_tpl_last}";

$footer = isset($footer) ? $footer : $json_footer;

// JSONP wraps the whole output, whichever header / footer is used
if ($jsonp !== '') {
    $header = $jsonp . '(' . $header;
    $footer .= ');';
}

// set emptytext
$noResults = '      ';
