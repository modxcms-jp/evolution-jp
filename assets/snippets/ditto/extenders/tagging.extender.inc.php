<?php
if (!defined('MODX_BASE_PATH') || strpos(str_replace('\\', '/', __FILE__), MODX_BASE_PATH) !== 0) exit;
/*
 * Title: Tagging
 * Purpose:
 *  	Collection of parameters, functions, and classes that expand
 *  	Ditto's functionality to include tagging
*/

// ---------------------------------------------------
// Tagging Parameters
// ---------------------------------------------------

$landing = $tagDocumentID ?? evo()->documentObject['id'] ?? '';
$source = $tagData ?? '';
$caseSensitive = $caseSensitive ?? 0;
$mode = $tagMode ?? 'onlyTags';
$delimiter = $tagDelimiter ?? ' ';
$displayDelimiter = $tagDisplayDelimiter ?? $delimiter;
$sort = $tagSort ?? 1;
$displayMode = $tagDisplayMode ?? 1;
$givenTags = !empty($tags) ? trim($tags) : false;
$templateInstance = new template();
$tplTagLinks = !empty($tplTagLinks) ? $templateInstance->fetch($tplTagLinks) : false;
// バインディングが無効のときは、引数で指定したコールバックを呼ばない（既定の組み立てに倒す）
$callback = (!empty($tagCallback) && evo()->config('enable_bindings') == 1) ? trim($tagCallback) : false;

// ---------------------------------------------------
// Tagging Class
// ---------------------------------------------------
if (!class_exists('tagging')) {
    class tagging
    {
        public $delimiter, $source, $landing, $mode, $format, $givenTags, $caseSensitive, $displayDelimiter, $sort, $displayMode, $tpl, $callback;

        function __construct($delimiter, $source, $mode, $landing, $givenTags, $format, $caseSensitive, $displayDelimiter, $callback, $sort, $displayMode, $tpl)
        {
            $this->delimiter = $delimiter;
            $this->source = $this->parseTagData($source);
            $this->mode = $mode;
            $this->landing = $landing;
            $this->format = $format;
            $this->givenTags = $this->prepGivenTags($givenTags);
            $this->caseSensitive = $caseSensitive;
            $this->displayDelimiter = $displayDelimiter;
            $this->sort = $sort;
            $this->displayMode = $displayMode;
            $this->tpl = $tpl;
            $this->callback = $callback;
        }

        function prepGivenTags($givenTags)
        {
            global $dittoID;

            $rawTags = get_strvar($dittoID . 'tags');
            $getTags = $rawTags !== '' ? trim($rawTags) : false;

            $tags1 = [];
            $tags2 = [];

            if ($getTags !== false) {
                $tags1 = explode($this->delimiter, $getTags);
            }

            if ($givenTags !== false) {
                $tags2 = explode($this->delimiter, $givenTags);
            }

            return $this->keyTags(array_merge($tags1, $tags2));
        }

        // tag => tag, keyed in lower case unless caseSensitive
        private function keyTags($tags)
        {
            $kTags = [];
            foreach ($tags as $tag) {
                if (empty($tag)) {
                    continue;
                }
                $tag = trim($tag);
                $kTags[$this->caseSensitive ? $tag : strtolower($tag)] = $tag;
            }
            return $kTags;
        }

        function tagFilter($value)
        {
            if ($this->caseSensitive == false) {
                $documentTags = array_values(array_flip($this->givenTags));
                $filterTags = array_values(array_flip($this->combineTags($this->source, $value, true)));
            } else {
                $documentTags = $this->givenTags;
                $filterTags = $this->combineTags($this->source, $value, true);
            }
            $compare = array_intersect($filterTags, $documentTags);
            $commonTags = count($compare);
            $totalTags = count((array)$filterTags);
            $docTags = count($documentTags);
            $unset = 1;

            switch ($this->mode) {
                case 'onlyAllTags' :
                    if ($commonTags != $docTags)
                        $unset = 0;
                    break;
                case 'removeAllTags' :
                    if ($commonTags == $docTags)
                        $unset = 0;
                    break;
                case 'onlyTags' :
                    if ($commonTags > $totalTags || $commonTags == 0)
                        $unset = 0;
                    break;
                case 'removeTags' :
                    if ($commonTags <= $totalTags && $commonTags != 0)
                        $unset = 0;
                    break;
            }
            return $unset;
        }

        function makeLinks($resource)
        {
            return $this->tagLinks($this->combineTags($this->source, $resource, true), $this->delimiter, $this->landing, $this->format);
        }

        function parseTagData($tagData, $names = [])
        {
            return explode(',', $tagData);
        }

        function combineTags($tagData, $resource, $array = false)
        {
            if ($this->callback !== false) {
                // positional arguments (string keys would be named arguments in PHP 8)
                return call_user_func_array($this->callback, [$tagData, $resource, $array]);
            }
            $tags = [];
            foreach ($tagData as $source) {
                if (!empty($resource[$source])) {
                    $tags[] = $resource[$source];
                }
            }
            $kTags = $this->keyTags(explode($this->delimiter, implode($this->delimiter, $tags)));
            return ($array == true) ? $kTags : implode($this->delimiter, $kTags);
        }

        function tagLinks($tags, $tagDelimiter, $tagID = false, $format = 'html')
        {
            global $ditto_lang;
            if (!$tags && $format === 'html') {
                return $ditto_lang['none'];
            }

            if (!$tags && ($format === 'rss' || $format === 'xml' || $format === 'atom')) {
                return sprintf('<category>%s</category>', $ditto_lang['none']);
            }

            $output = '';
            if ($this->sort) {
                ksort($tags);
            }

            $tplRss = "\r\n" . '				<category>[+tag+]</category>';
            $tpl = ($this->tpl == false) ? '<a href="[+url+]" class="ditto_tag" rel="tag">[+tag+]</a>' : $this->tpl;

            $tpl = (($format === 'rss' || $format === 'xml' || $format === 'atom') && $this->tpl == false) ? $tplRss : $tpl;

            if ($this->displayMode == 1) {
                foreach ($tags as $tag) {
                    $tagDocID = (!$tagID) ? (evo()->documentObject['id'] ?? '') : $tagID;
                    $url = ditto::buildURL(['tags' => $tag, 'start' => 0], $tagDocID);
                    $output .= template::replace(['url' => $url, 'tag' => hsc($tag)], $tpl);
                    $output .= ($format !== 'rss' && $format !== 'xml' && $format !== 'atom') ? $this->displayDelimiter : '';
                }
            } else if ($format !== 'rss' && $format !== 'xml' && $format !== 'atom' && $this->displayMode == 2) {
                $tagList = [];
                foreach ($tags as $tag) {
                    $tagDocID = (!$tagID) ? (evo()->documentObject['id'] ?? '') : $tagID;
                    $url = ditto::buildURL(['tags' => $tag, 'start' => 0], $tagDocID);
                    $tagList[] = template::replace(['url' => $url, 'tag' => hsc($tag)], $tpl);
                }
                $output = $this->makeList($tagList, $ulroot = 'ditto_tag_list', $ulprefix = 'ditto_tag_', $type = '', $ordered = false, $tablevel = 0);
            }

            // remove the last delimiter (only displayMode 1 adds them)
            return ($this->displayMode == 1 && $format !== 'rss' && $format !== 'xml' && $format !== 'atom' && $output !== '')
                ? substr($output, 0, -1 * strlen($this->displayDelimiter))
                : $output;
        }

        function makeList($array, $ulroot = 'root', $ulprefix = 'sub_', $type = '', $ordered = false, $tablevel = 0)
        {
            if (!is_array($array)) return '<ul><li>Bad list</li></ul>';

            $tabs = '';
            for ($i = 0; $i < $tablevel; $i++) {
                $tabs .= "\t";
            }

            $tag = ($ordered == true) ? 'ol' : 'ul';

            if (!empty($type)) $typestr = " style='list-style-type: {$type}'";
            else              $typestr = '';

            $listhtml = "{$tabs}<{$tag} class='{$ulroot}'{$typestr}>\n";
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $line = $this->makeList($value, "{$ulprefix}{$ulroot}", $ulprefix, $type, $ordered, $tablevel + 2);
                    $listhtml .= "{$tabs}\t<li>{$key}\n{$line}{$tabs}\t</li>\n";
                } else {
                    $listhtml .= "{$tabs}\t<li>{$value}</li>\n";
                }
            }
            $listhtml .= "{$tabs}</{$tag}>\n";
            return $listhtml;
        }
    }
}

$tags = new tagging($delimiter, $source, $mode, $landing, $givenTags, $format, $caseSensitive, $displayDelimiter, $callback, $sort, $displayMode, $tplTagLinks);

if ($tags->givenTags) {
    $filters['custom']['tagging'] = [$source, [$tags, 'tagFilter']];
}

evo()->setPlaceholder($dittoID . 'tagLinks', $tags->tagLinks($tags->givenTags, $delimiter, $landing, $format));
evo()->setPlaceholder($dittoID . 'tags', hsc(implode($delimiter, $tags->givenTags)));
$placeholders['tagLinks'] = [[$source, '*'], [$tags, 'makeLinks']];
