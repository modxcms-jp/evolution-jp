<?php
// ---------------------------------------------------
// Truncate Functions
// ---------------------------------------------------
function determineLink($resource) {
	global $ditto_object,$ditto_summary_params,$ditto_summary_link;
	if ($ditto_summary_link === false) {
        return '';
    }
    return $ditto_object->template->replace(
        array(
            "url" => $ditto_summary_link,
            "text" => $ditto_summary_params["text"],
        ),
        $ditto_summary_params['trunc_tpl'] === false
            ? '<a href="[+url+]" title="[+text+]">[+text+]</a>'
            : $ditto_summary_params['trunc_tpl']
    );
}
function determineSummary($resource) {
	global $ditto_summary_params;
	$trunc = new truncate();
	$p = $ditto_summary_params;
	$output = $trunc->execute(
	    $resource,
        $p['trunc'],
        $p['splitter'],
        $p['text'],
        $p['length'],
        $p['offset'],
        $p['splitter'],
        true
    );
	$GLOBALS['ditto_summary_link'] = $trunc->link;
	$GLOBALS['ditto_summary_type'] = $trunc->summaryType;
	return $output;
}

// ---------------------------------------------------
// Truncate Class
// ---------------------------------------------------
class truncate{
	public $summaryType,$link;

	function html_substr($posttext, $minimum_length = 200, $length_offset = 20, $truncChars=false) {
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

	function textTrunc($string, $limit, $break="。") {
		global $modx;

		mb_internal_encoding($modx->config['modx_charset']);
		if(mb_strwidth($string) <= $limit) {
            return $string;
        }

		$string = mb_strimwidth($string, 0, $limit);
		if(false !== ($breakpoint = mb_strrpos($string, $break))) {
			$string = mb_substr($string, 0, $breakpoint+1);
		}

		return $string;
	}

	function closeTags($text) {
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

	function execute($resource, $trunc, $splitter, $linktext, $truncLen, $truncOffset, $truncsplit, $truncChars) {
		$this->summaryType = "content";
		$this->link = false;
		$closeTags = true;
		// summary is turned off

		if ((strpos($resource['content'], $splitter) !== false) && $truncsplit) {
		    // HTMLarea/XINHA encloses it in paragraph's
			$summary = explode('<p>' . $splitter . '</p>', $resource['content']);

			// For TinyMCE or if it isn't wrapped inside paragraph tags
			$summary = explode($splitter, $summary['0']);

			$summary = $summary['0'];
			$this->link = '[~' . $resource['id'] . '~]';
			$this->summaryType = "content";

			// fall back to the summary text
		} elseif (mb_strlen($resource['introtext']) > 0) {
				$summary = $resource['introtext'];
				$this->link = '[~' . $resource['id'] . '~]';
				$this->summaryType = 'introtext';
				$closeTags = false;
				// fall back to the summary text count of characters
		} elseif (strlen($resource['content']) > $truncLen && $trunc == 1) {
				$summary = $this->html_substr($resource['content'], $truncLen, $truncOffset, $truncChars);
				$this->link = '[~' . $resource['id'] . '~]';
				$this->summaryType = "content";
				// and back to where we started if all else fails (short post)
		} else {
			$summary = $resource['content'];
			$this->summaryType = 'content';
			$this->link = false;
		}

		// Post-processing to clean up summaries
        return !$closeTags ? $summary : $this->closeTags($summary);
	}
}
