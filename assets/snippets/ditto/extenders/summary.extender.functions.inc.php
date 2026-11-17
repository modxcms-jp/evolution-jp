<?php
// ---------------------------------------------------
// Summary Placeholders
// ---------------------------------------------------
class dittoSummary
{
    private $trunc, $splitter, $length, $offset, $text, $tpl;

    public function __construct($trunc, $splitter, $length, $offset, $text, $tpl)
    {
        $this->trunc = $trunc;
        $this->splitter = $splitter;
        $this->length = $length;
        $this->offset = $offset;
        $this->text = $text;
        $this->tpl = $tpl;
    }

    public function summary($resource)
    {
        return (new truncate())->execute(
            $resource,
            $this->trunc,
            $this->splitter,
            $this->length,
            $this->offset,
            $this->splitter,
            true
        );
    }

    public function link($resource)
    {
        $url = (new truncate())->link($resource, $this->trunc, $this->splitter, $this->length, $this->splitter);
        if ($url === false) {
            return '';
        }
        return template::replace(
            ['url' => $url, 'text' => $this->text],
            $this->tpl === false ? '<a href="[+url+]" title="[+text+]">[+text+]</a>' : $this->tpl
        );
    }
}

// ---------------------------------------------------
// Truncate Class
// ---------------------------------------------------
class truncate
{
    // lengths (truncLen, truncOffset) are counted in characters
    private function charset()
    {
        return evo()->config('modx_charset') ?: 'UTF-8';
    }

    private function html_substr($posttext, $minimum_length = 200, $length_offset = 20, $truncChars = false)
    {
        $charset = $this->charset();
        if (mb_strlen($posttext, $charset) <= $minimum_length || $truncChars == 1) {
            return $this->truncWithoutPartial($posttext, $minimum_length + $length_offset);
        }

        // Reset tag counter & quote checker
        $tag_counter = 0;
        $quotes_on = false;
        $c = 0;
        for ($i = 0, $iMax = mb_strlen($posttext, $charset); $i < $iMax; $i++) {
            $current_char = mb_substr($posttext, $i, 1, $charset);
            $next_char = mb_substr($posttext, $i + 1, 1, $charset);
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
                return mb_substr($posttext, 0, $i + 1, $charset);
            }
        }
        return $this->truncWithoutPartial($posttext, $minimum_length + $length_offset);
    }

    // 切り詰めたときだけ、末尾の途中のタグ（<英字・!・/・? で始まり > なし）と途中の文字参照（& と英数字・# だけで ; なし）を落とす。
    // タグでない < や & の後ろの日本語は残す。UTF-8 前提で、< > & は複数バイト文字に含まれないので /u は付けない
    private function truncWithoutPartial($string, $limit)
    {
        $result = $this->textTrunc($string, $limit);
        if ($result === $string) {
            return $result;
        }
        // 途中のタグを落としたときは、その手前の文字参照は完結しているので判定しない
        $stripped = preg_replace('/<[!\/?A-Za-z][^>]*$/', '', $result);
        if ($stripped !== null && $stripped !== $result) {
            return $stripped;
        }
        return preg_replace('/&(#[0-9]*|#[xX][0-9a-fA-F]*|[A-Za-z][A-Za-z0-9]*)?$/', '', $result) ?? $result;
    }

    private function textTrunc($string, $limit, $break = "。")
    {
        $charset = $this->charset();
        if (mb_strlen($string, $charset) <= $limit) {
            return $string;
        }

        $string = mb_substr($string, 0, $limit, $charset);
        if (false !== ($breakpoint = mb_strrpos($string, $break, 0, $charset))) {
            $string = mb_substr($string, 0, $breakpoint + 1, $charset);
        }

        return $string;
    }

    private function closeTags($text)
    {
        $openPattern = "/<([^\/].*?)>/";
        $closePattern = "/<\/(.*?)>/";
        $endTags = '';

        preg_match_all($openPattern, $text, $openTags);
        preg_match_all($closePattern, $text, $closeTags);

        $c = 0;
        $loopCounter = count($closeTags[1]);
        while ($c < count($closeTags[1]) && $loopCounter) {
            $i = 0;
            while ($i < count($openTags[1])) {
                $tag = trim($openTags[1][$i]);
                if (strpos($tag, ' ') !== false) {
                    $tag = substr($tag, 0, strpos($tag, ' '));
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
        if (mb_strlen($content, $this->charset()) > $truncLen && $trunc == 1) {
            // and back to where we started if all else fails (short post)
            return $this->closeTags(
                $this->html_substr($content, $truncLen, $truncOffset, $truncChars)
            );
        }
        return $this->closeTags($content);
    }

    public function link($resource, $trunc, $splitter, $truncLen, $truncsplit)
    {
        $content = is_array($resource) ? ($resource['content'] ?? '') : '';
        $introtext = is_array($resource) ? ($resource['introtext'] ?? '') : '';
        $id = is_array($resource) ? ($resource['id'] ?? 0) : 0;

        if (strpos($content, $splitter) !== false && $truncsplit) {
            return '[~' . $id . '~]';
        }
        if ($introtext !== '') {
            return '[~' . $id . '~]';
        }
        if (mb_strlen($content, $this->charset()) > $truncLen && $trunc == 1) {
            return '[~' . $id . '~]';
        }
        return false;
    }
}
