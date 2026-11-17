<?php
if (!defined('MODX_BASE_PATH') || strpos(str_replace('\\', '/', __FILE__), MODX_BASE_PATH) !== 0) exit;

/**
 * XML format
 *
 * [[Ditto?format=xml]]
 *
 * Collection of parameters, functions, and classes that expand
 * Ditto's output capabilities to include XML
 *
 * @link https://modx.jp/docs/extras/snippets/ditto/params/main/body.html
*/

if (!function_exists('dittoFormatXmlHeaderTpl')) {
    function dittoFormatXmlHeaderTpl() {
        return <<<TPL
<?xml version="1.0" encoding="[+xml_charset+]" ?>
[+xml_xsl+]
<xml version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>[+xml_title+]</title>
        <link>[+xml_link+]</link>
        <description>[+xml_description+]</description>
        <language>[+xml_lang+]</language>
        <copyright>[+xml_copyright+]</copyright>
        <ttl>[+xml_ttl+]</ttl>
TPL;
    }
}

if (!function_exists('dittoFormatXmlTpl')) {
    function dittoFormatXmlTpl() {
        return <<<TPL
    <item>
        <title>[+xml_pagetitle+]</title>
        <link>[(site_url)][~[+id+]~]</link>
        <guid isPermaLink="true">[(site_url)][~[+id+]~]</guid>
        <summary><![CDATA[ [+introtext_cdata+] ]]></summary>
        <lastmod>[+xml_editedon:date=`%Y-%m-%dT%H:%M:%S`+]</lastmod>
        <author>[+xml_author+]</author>
        [+tags+]
    </item>
TPL;
    }
}

if (!function_exists('dittoFormatXmlFooterTpl')) {
    function dittoFormatXmlFooterTpl() {
        return <<<TPL
    </channel>
</xml>
TPL;
    }
}

// set template values
if(!isset($header)) {
    $header = evo()->parseText(
        dittoFormatXmlHeaderTpl(), [
            'xml_title'     => ditto::xmlText(evo()->documentObject['pagetitle'] ?? ''),
            'xml_description' => ditto::xmlText(evo()->documentObject['description'] ?? ''),
            'xml_copyright' => ditto::xmlText(
                evo()->mergeSettingsContent($copyright ?? $_lang['default_copyright'])
            ),
            'xml_lang'      => $abbrLanguage ?? $_lang['abbr_lang'],
            'xml_ttl'       => (int) ($ttl ?? 120),
            'xml_charset'   => $charset ?? config('modx_charset'),
            'xml_xsl'       => (isset($xsl)
                            ? '<?xml-stylesheet type="text/xsl" href="' . MODX_SITE_URL . $xsl . '" ?>'
                            : ''),
            'xml_link'      => MODX_SITE_URL . '[~' . evo()->documentIdentifier . '~]',
        ]
    );
}

if(!isset($tpl)) {
    $tpl = '@CODE:' . dittoFormatXmlTpl();
}

if(!isset($footer)) {
    $footer = dittoFormatXmlFooterTpl();
}

// set emptytext
$noResults = '      ';

evo()->documentObject['contentType'] = 'application/xml';


// set tpl xml placeholders
$placeholders['*'] = 'xml_parameters';
$placeholders['xml_pagetitle'] = ['pagetitle', 'xml_pagetitle'];
$placeholders['xml_author'] = ['createdby', 'xml_author'];
$placeholders['introtext_cdata'] = ['introtext', 'dittoIntrotextCdata'];




if (!function_exists('xml_parameters')) {
    function xml_parameters($placeholders) {
        $xmlArr = [];
        foreach ($placeholders as $name => $value) {
            $xmlArr['xml_' . $name] = ditto::xmlEscape($value);
        }
        return array_merge($xmlArr, $placeholders);
    }
}

if (!function_exists('xml_pagetitle')) {
    function xml_pagetitle($resource) {
        return ditto::xmlText($resource['pagetitle']);
    }
}

if (!function_exists('dittoIntrotextCdata')) {
    function dittoIntrotextCdata($resource) {
        return ditto::cdata(ditto::relToAbs($resource['introtext'] ?? '', MODX_SITE_URL));
    }
}

if (!function_exists('xml_author')) {
    function xml_author($resource) {
        return ditto::xmlText(ditto::getAuthor($resource['createdby']));
    }
}
