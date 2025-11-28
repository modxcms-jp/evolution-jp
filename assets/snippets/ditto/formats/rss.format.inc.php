<?php

/*
 * Title: RSS
 * [[Ditto?format=rss]]
 * Purpose:
 *  Collection of parameters, functions, and classes that expand
 *  Ditto's output capabilities to include RSS
*/

if (!defined('MODX_BASE_PATH') || strpos(str_replace('\\', '/', __FILE__), MODX_BASE_PATH) !== 0) exit;

$modx->documentObject['contentType'] = 'application/rss+xml';

global $dateSource;
$dateSource = $modx->event->params['dateSource'] ?? 'publishedon';
if (!isset($orderBy ['unparsed'])) {
    $orderBy ['unparsed'] = "{$dateSource} DESC";
}

// date type to display (values can be createdon, pub_date, editedon)

// set tpl rss placeholders
$placeholders['rss_date']      = [$dateSource, "rss_date"];
$placeholders['rss_pagetitle'] = ["pagetitle", "rss_pagetitle"];
$placeholders['rss_author']    = ["createdby", "rss_author"];

$extenders[] = 'summary';

// set template values
if(!isset($header)) {
    $header = $modx->parseText(
        dittoRssHeaderTpl(), [
            'rss_copyright' => $copyright ?? $_lang['default_copyright'],
            'rss_lang'      => $abbrLanguage ?? $_lang['abbr_lang'],
            'rss_link'      => MODX_SITE_URL . '[~' . $modx->documentIdentifier . "~]",
            'rss_ttl'       => (int) ($ttl ?? 120),
            'rss_charset'   => $charset ?? $modx->config['modx_charset'],
            'rss_xsl'       => isset($xsl) ? "\n" . '<?xml-stylesheet type="text/xsl" href="' . MODX_SITE_URL . $xsl . '" ?>' : ''
        ]
    );
}

if(!isset($tpl)) {
    $tpl = '@CODE:' . dittoRssTpl();
}

if(!isset($footer)) {
    $footer = dittoRssFooterTpl();
}

// set emptytext
$noResults = "      ";



function dittoRssHeaderTpl() {
    return <<<TPL
<?xml version="1.0" encoding="[+rss_charset+]" ?>[+rss_xsl+]
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>[*pagetitle*]</title>
        <link>[(site_url)]</link>
        <description>[*description:strip*]</description>
        <language>[+rss_lang+]</language>
        <copyright>[+rss_copyright+]</copyright>
        <ttl>[+rss_ttl+]</ttl>
TPL;
}

function dittoRssFooterTpl() {
    return <<<TPL
    </channel>
</rss>
TPL;
}

function dittoRssTpl() {
    return <<<TPL
    <item>
        <title>[+rss_pagetitle+]</title>
        <link>[+url+]</link>
        <description><![CDATA[ [+summary:strip+] ]]></description>
        <pubDate>[+rss_date+]</pubDate>
        <guid isPermaLink="true">[+url+]</guid>
        <dc:creator>[+rss_author+]</dc:creator>
        [+tagLinks+]
    </item>
TPL;
}

if (!function_exists("rss_date")) {
    function rss_date($resource) {
        global $modx, $dateSource;
        return date("r", intval($resource[$dateSource]) + $modx->config["server_offset_time"]);
    }
}

if (!function_exists("rss_pagetitle")) {
    function rss_pagetitle($resource) {
        return htmlspecialchars(html_entity_decode($resource['pagetitle'], ENT_QUOTES));
    }
}
if (!function_exists("rss_author")) {
    function rss_author($resource) {
        if (!is_array($resource) || !array_key_exists('createdby', $resource)) {
            return '';
        }

        $createdBy = $resource['createdby'];
        if ($createdBy === '' || $createdBy === null) {
            return '';
        }

        return htmlspecialchars(html_entity_decode(ditto::getAuthor($createdBy), ENT_QUOTES));
    }
}
