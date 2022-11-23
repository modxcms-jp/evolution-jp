<?php
if (!defined('MODX_BASE_PATH') || strpos(str_replace('\\', '/', __FILE__), MODX_BASE_PATH) !== 0) exit;

if(PHP_MAJOR_VERSION < 7) {
    exit('PHP 7 or higher required');
}

/**
 * XML format
 *
 * [Ditto?format=xml]
 *
 * Collection of parameters, functions, and classes that expand
 * Ditto's output capabilities to include XML
 *
 * @link https://modx.jp/docs/extras/snippets/ditto/params/main/body.html
*/

// additional placeholders
/*
    Param:   &copyright
    Purpose: Copyright message to embed in the XML feed
    Options: Any text
    Default: $_lang['default_copyright']
*/
if(!isset($copyright)) {
    $copyright = $_lang['default_copyright'];
}

/*
    Param  : &abbrLanguage
    Purpose: Language for the XML feed
    Options: Any valid 2 character language abbreviation
    Default: [LANG]
    Related: $_lang['abbr_lang']
*/
if(!isset($abbrLanguage)) {
    $abbrLanguage = $_lang['abbr_lang'];
}

/*
    Param  : &ttl
    Purpose: Time to live for the RSS feed
    Options: Any integer greater than 1
    Default: 120
*/
if(!isset($ttl)) {
    $ttl = 120;
}

/*
    Param  : &charset
    Purpose: Charset to use for the RSS feed
    Options: Any valid charset identifier
    Default: MODX default charset
*/
if(!isset($charset)) {
    $charset = $modx->config['modx_charset'];
}

/*
    Param  : &xsl
    Purpose: XSL Stylesheet to format the XML feed with
    Options: The path to any valid XSL Stylesheet
    Default: None
*/
$xsl = isset($xsl)
    ? '<?xml-stylesheet type="text/xsl" href="' . MODX_SITE_URL . $xsl . '" ?>'
    : ''
;


// set template values
if(!isset($header)) {
    $header = $modx->parseText(
        dittoFormatXmlHeaderTpl(), [
            'xml_copyright' => $copyright,
            'xml_lang'      => $abbrLanguage,
            'xml_ttl'       => (int) $ttl,
            'xml_charset'   => $charset,
            'xml_xsl'       => $xsl,
            'xml_link'      => MODX_SITE_URL . '[~' . $modx->documentIdentifier . '~]',
        ]
    );
}

if(!isset($tpl)) {
    $tpl = '@CODE:' . dittoFormatXmlTpl();
}

if(!isset($footer)) {
    $footer = dittoFormatXmlFooterTpl();
}

if(!isset($parents)) {
    $parents = 0;
}

// set emptytext
$noResults = '      ';

$modx->documentObject['contentType'] = 'application/xml';


// set tpl xml placeholders
$placeholders['*'] = 'xml_parameters';




if (!function_exists('xml_parameters')) {
    function xml_parameters($placeholders) {
        global $modx;
        $xmlArr = [];
        foreach ($placeholders as $name => $value) {
            $xmlArr['xml_' . $name] = htmlentities(
                $value,
                ENT_NOQUOTES,
                $modx->config['modx_charset'] ?: 'UTF-8'
            );
        }
        return array_merge($xmlArr, $placeholders);
    }
}


function dittoFormatXmlHeaderTpl() {
    return <<<TPL
<?xml version="1.0" encoding="[+xml_charset+]" ?>
[+xml_xsl+]
<xml version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>[*pagetitle*]</title>
        <link>[+xml_link+]</link>
        <description>[*description*]</description>
        <language>[+xml_lang+]</language>
        <copyright>[+xml_copyright+]</copyright>
        <ttl>[+xml_ttl+]</ttl>
TPL;
}

function dittoFormatXmlTpl() {
    return <<< TPL
    <item>
        <title>[+xml_pagetitle+]</title>
        <link>[(site_url)][~[+id+]~]</link>
        <guid isPermaLink="true">[(site_url)][~[+id+]~]</guid>
        <summary><![CDATA[ [+xml_introtext+] ]]></summary>
        <lastmod>[+xml_editedon:date=`%Y-%m-%dT%H:%M:%S`+]</lastmod>
        <author>[+xml_author+]</author>
        [+tags+]
    </item>
TPL;
}

function dittoFormatXmlFooterTpl() {
    return <<<TPL
    </channel>
</xml>
TPL;
}
