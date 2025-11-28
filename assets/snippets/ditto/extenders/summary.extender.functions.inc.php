<?php
// ---------------------------------------------------
// Truncate Functions
// ---------------------------------------------------
function determineLink($resource)
{
    global $ditto_object, $ditto_summary_params, $ditto_summary_link;
    if ($ditto_summary_link === false) {
        return '';
    }
    return $ditto_object->template->replace(
        [
            "url" => $ditto_summary_link,
            "text" => $ditto_summary_params["text"],
        ],
        $ditto_summary_params['trunc_tpl'] === false
            ? '<a href="[+url+]" title="[+text+]">[+text+]</a>'
            : $ditto_summary_params['trunc_tpl']
    );
}

function determineSummary($resource)
{
    global $ditto_summary_params;
    $trunc = new truncate();
    $p = $ditto_summary_params;
    $output = $trunc->execute(
        $resource,
        $p['trunc'],
        $p['splitter'],
        $p['length'],
        $p['offset'],
        $p['splitter'],
        true
    );
    $GLOBALS['ditto_summary_link'] = $trunc->link(
        $resource,
        $p['trunc'],
        $p['splitter'],
        $p['length'],
        $p['splitter']
    );
    $GLOBALS['ditto_summary_type'] = $trunc->summaryType(
        $resource,
        $p['trunc'],
        $p['splitter'],
        $p['length'],
        $p['splitter']
    );
    return $output;
}

// ---------------------------------------------------
// Truncate Class
// ---------------------------------------------------
class truncate
{
    private function html_substr($posttext, $minimum_length = 200, $length_offset = 20, $truncChars = false)
    {
        if (mb_strlen($posttext) <= $minimum_length || $truncChars == 1) {
            return $this->textTrunc($posttext, $minimum_length + $length_offset);
        }

        // Reset tag counter & quote checker
        $tag_counter = 0;
        $quotes_on = false;
        $c = 0;
        for ($i = 0, $iMax = mb_strlen($posttext); $i < $iMax; $i++) {
            $current_char = mb_substr($posttext, $i, 1);
            $next_char = $i >= (strlen($posttext) - 1) ? '' : mb_substr($posttext, $i + 1, 1);
            if (!$quotes_on) {
                if ($current_char === '<') {
                    $tag_counter = $next_char === '/' ? $tag_counter + 1 : $tag_counter + 3;
                }
                if ($current_char === '/' && $tag_counter <> 0) $tag_counter -= 2;
                if ($current_char === '>') $tag_counter -= 1;
                if ($current_char === '"') $quotes_on = true;
            } else {
                if ($current_char === '"') {
                    $quotes_on = false;
                }
            }

            if ($tag_counter == 2 || $tag_counter == 0) {
                $c++;
            }

            if ($c > $minimum_length - $length_offset && $tag_counter == 0) {
                $posttext = mb_substr($posttext, 0, $i + 1);
                return $posttext;
            }
        }
        return $this->textTrunc($posttext, $minimum_length + $length_offset);
    }

    private function textTrunc($string, $limit, $break = "。")
    {
        global $modx;

        mb_internal_encoding($modx->config['modx_charset']);
        if (mb_strwidth($string) <= $limit) {
            return $string;
        }

        $string = mb_strimwidth($string, 0, $limit);
        if (false !== ($breakpoint = mb_strrpos($string, $break))) {
            $string = mb_substr($string, 0, $breakpoint + 1);
        }

        return $string;
    }

    private function closeTags($text)
    {
        global $debug;
        $openPattern = "/<([^\/].*?)>/";
        $closePattern = "/<\/(.*?)>/";
        $endTags = '';

        preg_match_all($openPattern, $text, $openTags);
        preg_match_all($closePattern, $text, $closeTags);

        if ($debug == 1) {
            print_r($openTags);
            print_r($closeTags);
        }

        $c = 0;
        $loopCounter = count($closeTags[1]);
        while ($c < count($closeTags[1]) && $loopCounter) {
            $i = 0;
            while ($i < count($openTags[1])) {
                $tag = trim($openTags[1][$i]);
                if (strpos($tag, ' ') !== false) {
                    $tag = substr($tag, 0, strpos($tag, ' '));
                }
                if ($debug == 1) {
                    echo $tag . '==' . $closeTags[1][$c] . "\n";
                }
                if ($tag == $closeTags[1][$c]) {
                    $openTags[1][$i] = '';
                    $c++;
                    break;
                }
                $i++;
            }
            $loopCounter--;
        }

        $results = $openTags[1];

        if (is_array($results)) {
            $results = array_reverse($results);

            foreach ($results as $tag) {
                $tag = trim($tag);

                if (strpos($tag, ' ') !== false) {
                    $tag = substr($tag, 0, strpos($tag, ' '));
                }
                if (stripos($tag, 'br') === false && stripos($tag, 'img') === false && !empty ($tag)) {
                    $endTags .= '</' . $tag . '>';
                }
            }
        }
        return $text . $endTags;
    }

    public function execute($resource, $trunc, $splitter, $truncLen, $truncOffset, $truncsplit, $truncChars)
    {
        $content = is_array($resource) ? ($resource['content'] ?? '') : '';
        $introtext = is_array($resource) ? ($resource['introtext'] ?? '') : '';

        if ((strpos($content, $splitter) !== false) && $truncsplit) {
            $summary = explode('<p>' . $splitter . '</p>', $content);
            // For TinyMCE or if it isn't wrapped inside paragraph tags
            $_ = explode($splitter, $summary[0]);
            return $this->closeTags($_[0]);
        }
        if ($introtext) {
            return $introtext;
            // fall back to the summary text count of characters
        }
        if (strlen($content) > $truncLen && $trunc == 1) {
            // and back to where we started if all else fails (short post)
            return $this->closeTags(
                $this->html_substr($content, $truncLen, $truncOffset, $truncChars)
            );
        }
        return $this->closeTags($content);
    }

    public function summaryType($resource, $trunc, $splitter, $truncLen, $truncsplit)
    {
        $content = is_array($resource) ? ($resource['content'] ?? '') : '';
        $introtext = is_array($resource) ? ($resource['introtext'] ?? '') : '';

        if (strpos($content, $splitter) !== false && $truncsplit) {
            return 'content';
        }
        if (mb_strlen($introtext) > 0) {
            return 'introtext';
        }
        if (strlen($content) > $truncLen && $trunc == 1) {
            return 'content';
        }
        return 'content';
    }

    public function link($resource, $trunc, $splitter, $truncLen, $truncsplit)
    {
        $content = is_array($resource) ? ($resource['content'] ?? '') : '';
        $introtext = is_array($resource) ? ($resource['introtext'] ?? '') : '';
        $id = is_array($resource) ? ($resource['id'] ?? 0) : 0;

        if (strpos($content, $splitter) !== false && $truncsplit) {
            return '[~' . $id . '~]';
        }
        if (mb_strlen($introtext) > 0) {
            return '[~' . $id . '~]';
        }
        if (strlen($content) > $truncLen && $trunc == 1) {
            return '[~' . $id . '~]';
        }
        return false;
    }
}
