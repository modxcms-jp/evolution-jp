<?php

if (!defined('MODX_CORE_PATH')) {
    define('MODX_CORE_PATH', MODX_BASE_PATH . 'manager/includes/');
}

$this->filter = new MODIFIERS;

class MODIFIERS
{

    public $documentObject;
    public $placeholders = [];
    public $vars = [];
    public $bt;
    public $srcValue;
    public $condition = [];
    public $condModifiers;

    public $key;
    public $value;
    public $opt;

    public function __construct()
    {
        global $modx;

        if (!$modx->config) {
            $modx->config = $modx->getSiteCache();
        }

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding(evo()->config('modx_charset', 'utf-8'));
        }

        $this->condModifiers = '=,is,eq,equals,ne,neq,notequals,isnot,isnt,not,%,isempty,isnotempty,isntempty,>=,gte,eg,gte,greaterthan,>,gt,isgreaterthan,isgt,lowerthan,<,lt,<=,lte,islte,islowerthan,islt,el,find,in,inarray,in_array,fnmatch,wcard,wcard_match,wildcard,wildcard_match,is_file,is_dir,file_exists,is_readable,is_writable,is_image,regex,preg,preg_match,memberof,mo,isinrole,ir';
    }

    public function phxFilter($key, $value, $modifiers)
    {
        if (strpos($modifiers, 'id(') !== 0) {
            $value = $this->parseDocumentSource($value);
        }
        $this->srcValue = $value;
        $modifiers = trim($modifiers);
        $modifiers = ':' . trim($modifiers, ':');
        $modifiers = str_replace(["\r\n", "\r"], "\n", $modifiers);
        $modifiers = $this->splitEachModifiers($modifiers);

        $this->placeholders = ['phx' => '', 'dummy' => ''];
        $this->condition = [];
        $this->vars = [];
        $this->vars['name'] = &$key;
        $value = $this->parsePhx($key, $value, $modifiers);
        $this->vars = [];
        return $value;
    }

    public function _getDelim($mode, $modifiers)
    {
        $c = substr($modifiers, 0, 1);
        if (!in_array($c, ['"', "'", '`'])) {
            return false;
        }

        $modifiers = substr($modifiers, 1);
        $closure = $mode === '(' ? sprintf('%s)', $c) : $c;
        if (strpos($modifiers, $closure) === false) {
            return false;
        }
        return $c;
    }

    public function _getOpt($mode, $delim, $modifiers)
    {
        if ($delim) {
            if ($mode === '(') {
                return substr($modifiers, 1, strpos($modifiers, $delim . ')') - 1);
            }
            return substr($modifiers, 1, strpos($modifiers, $delim, 1) - 1);
        }

        if ($mode === '(') {
            return substr($modifiers, 0, strpos($modifiers, ')'));
        }

        $chars = str_split($modifiers);
        $opt = '';
        foreach ($chars as $c) {
            if ($c === ':' || $c === ')') {
                break;
            }
            $opt .= $c;
        }
        return $opt;
    }

    public function _getRemainModifiers($mode, $delim, $modifiers)
    {
        if ($delim) {
            if ($mode === '(') {
                return $this->_fetchContent($modifiers, $delim . ')');
            }
            return $this->_fetchContent(
                substr(trim($modifiers), 1),
                $delim
            );
        }

        if ($mode === '(') {
            return $this->_fetchContent($modifiers, ')');
        }
        $chars = str_split($modifiers);
        foreach ($chars as $c) {
            if ($c === ':') {
                return $modifiers;
            }
            $modifiers = substr($modifiers, 1);
        }
        return $modifiers;
    }

    public function _fetchContent($string, $delim)
    {
        $len = strlen($delim);
        $string = $this->parseDocumentSource($string);
        return substr($string, strpos($string, $delim) + $len);
    }

    public function splitEachModifiers($modifiers)
    {
        global $modx;

        $cmd = '';
        $bt = '';
        while ($bt !== $modifiers) {
            $bt = $modifiers;
            $c = substr($modifiers, 0, 1);
            $modifiers = substr($modifiers, 1);

            if ($c === ':' && preg_match('@^(!?[<>=]{1,2})@', $modifiers, $match)) {
                // :=, :!=, :<=, :>=, :!<=, :!>=
                $c = substr($modifiers, strlen($match[1]), 1);
                $debuginfo = sprintf('#i=0 #c=[%s] #m=[%s]', $c, $modifiers);
                if ($c === '(') {
                    $modifiers = substr($modifiers, strlen($match[1]) + 1);
                } else {
                    $modifiers = substr($modifiers, strlen($match[1]));
                }

                $delim = $this->_getDelim($c, $modifiers);
                $opt = $this->_getOpt($c, $delim, $modifiers);
                $modifiers = trim($this->_getRemainModifiers($c, $delim, $modifiers));

                $result[] = ['cmd' => trim($match[1]), 'opt' => $opt, 'debuginfo' => $debuginfo];
                $cmd = '';
            } elseif (in_array($c, ['+', '-', '*', '/']) && preg_match('@^[0-9]+@', $modifiers, $match)) {
                // :+3, :-3, :*3 ...
                $modifiers = substr($modifiers, strlen($match[0]));
                $result[] = ['cmd' => 'math', 'opt' => '%s' . $c . $match[0]];
                $cmd = '';
            } elseif ($c === '(' || $c === '=') {
                $modifiers = $m1 = trim($modifiers);
                $delim = $this->_getDelim($c, $modifiers);
                $opt = $this->_getOpt($c, $delim, $modifiers);
                $modifiers = trim($this->_getRemainModifiers($c, $delim, $modifiers));
                $debuginfo = sprintf('#i=1 #c=[%s] #delim=[%s] #m1=[%s] remainMdf=[%s]', $c, $delim, $m1, $modifiers);

                $result[] = ['cmd' => trim($cmd), 'opt' => $opt, 'debuginfo' => $debuginfo];

                $cmd = '';
            } elseif ($c === ':') {
                $debuginfo = sprintf('#i=2 #c=[%s] #m=[%s]', $c, $modifiers);
                if ($cmd !== '') {
                    $result[] = ['cmd' => trim($cmd), 'opt' => '', 'debuginfo' => $debuginfo];
                }

                $cmd = '';
            } elseif (trim($modifiers) == '' && trim($cmd) !== '') {
                $debuginfo = sprintf('#i=3 #c=[%s] #m=[%s]', $c, $modifiers);
                $cmd .= $c;
                $result[] = ['cmd' => trim($cmd), 'opt' => '', 'debuginfo' => $debuginfo];
                break;
            } else {
                $cmd .= $c;
            }
        }

        if (empty($result)) {
            return [];
        }

        foreach ($result as $i => $a) {
            $a['opt'] = $this->parseDocumentSource($a['opt']);
            $result[$i]['opt'] = evo()->parseText($a['opt'], $this->placeholders);
        }

        return $result;
    }

    public function parsePhx($key, $value, $modifiers)
    {
        static $cache = [];
        $cacheKey = hash('crc32b', sprintf('parsePhx#%s#%s#%s', $key, $value, print_r($modifiers, true)));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        if (empty($modifiers)) {
            return '';
        }

        foreach ($modifiers as $m) {
            $lastKey = strtolower($m['cmd']);
        }

        $_ = explode(',', $this->condModifiers);
        if (in_array($lastKey, $_)) {
            $modifiers[] = ['cmd' => 'then', 'opt' => '1'];
            $modifiers[] = ['cmd' => 'else', 'opt' => '0'];
        }

        foreach ($modifiers as $i => $a) {
            if (evo()->debug) {
                $fstart = evo()->getMicroTime();
            }
            $value = $this->Filter($key, $value, $a['cmd'], $a['opt']);
            if (evo()->debug) {
                evo()->addLogEntry(
                    sprintf(
                        'evo()->filter->%s(:%s)',
                        __FUNCTION__,
                        $a['cmd']
                    ),
                    $fstart
                );
            }
        }
        $cache[$cacheKey] = $value;
        return $value;
    }

    // Parser: modifier detection and eXtended processing if needed
    public function Filter($key, $value, $cmd, $opt = '')
    {
        if ($key === 'documentObject') {
            $value = evo()->documentIdentifier;
        }
        $cmd = $this->parseDocumentSource($cmd);
        if (preg_match('@^[1-9][/0-9]*$@', $cmd)) {
            if (strpos($cmd, '/') !== false) {
                $cmd = $this->substr($cmd, strrpos($cmd, '/') + 1);
            }
            $opt = $cmd;
            $cmd = 'id';
        }

        $cmd = strtolower($cmd);
        if ($cmd === 'encode_sha1') {
            $cmd = 'sha1';
        }

        if ($this->snippet_exists($cmd)) {
            $value = $this->getValueFromPHP($key, $value, $cmd, $opt);
        } elseif ($this->chunk_exists($cmd)) {
            if (strlen(trim($value))) {
                $value = $this->getValueFromHTML($key, $value, $cmd, $opt);
            }
        } else {
            $value = $this->getValueFromPreset($key, $value, $cmd, $opt);
        }

        if ($value === null) {
            return '';
        }

        return str_replace('[+key+]', $key, $value);
    }

    public function snippet_exists($cmd)
    {
        global $modx;

        if (!$cmd) {
            return 0;
        }

        if (isset($modx->snippetCache['phx:' . $cmd])) {
            if (!$modx->snippetCache['phx:' . $cmd]) {
                return 0;
            }
            return 1;
        }

        if (isset($modx->snippetCache[$cmd])) {
            if (!$modx->snippetCache[$cmd]) {
                return 0;
            }
            return 1;
        }

        $code = $this->getSnippetFromDB($cmd);
        if ($code) {
            $modx->snippetCache['phx:' . $cmd] = $code;
            $modx->snippetCache['phx:' . $cmd . 'Props'] = '';
            return 1;
        }

        $code = $this->getSnippetFromFile($cmd);
        if ($code) {
            $modx->snippetCache['phx:' . $cmd] = $code;
            $modx->snippetCache['phx:' . $cmd . 'Props'] = '';
            return 1;
        }

        $modx->snippetCache['phx:' . $cmd] = false;
        return 0;
    }

    public function chunk_exists($cmd)
    {
        if (evo()->getChunk('phx:' . $cmd)) {
            return 1;
        }

        if (evo()->getChunk($cmd) && strpos(evo()->getChunk($cmd), '[+value+]') !== false) {
            return 1;
        }

        return 0;
    }

    public function getValueFromPHP($key, $value, $cmd, $opt)
    {
        global $modx;

        if (array_get($modx->snippetCache, 'phx:' . $cmd)) {
            $code = array_get($modx->snippetCache, 'phx:' . $cmd);
        } elseif (array_get($modx->snippetCache, $cmd)) {
            $code = array_get($modx->snippetCache, $cmd);
        }

        ob_start();
        $options = $opt;
        $output = $value;
        $name = $key;
        $this->bt = $value;
        $this->vars['value'] = &$value;
        $this->vars['input'] = &$value;
        $this->vars['option'] = &$opt;
        $this->vars['options'] = &$opt;

        if (strpos($code, ';') !== false) {
            $return = eval($code);
        } else {
            $return = call_user_func_array($code, [$value, $opt]);
        }

        $msg = ob_get_contents();
        if ($value === $this->bt) {
            $value = $msg . $return;
        }
        ob_end_clean();

        return $value;
    }

    public function getSnippetFromDB($cmd)
    {
        $rs = db()->select(
            'snippet',
            '[+prefix+]site_snippets',
            sprintf('name=\'phx:%1$s\' OR name=\'%1$s\'', db()->escape($cmd)),
            '',
            1
        );
        if (!db()->count($rs)) {
            return false;
        }

        return db()->getValue($rs);
    }

    public function getSnippetFromFile($cmd)
    {
        $_ = [
            sprintf('%sassets/modifiers/mdf_%s.inc.php', MODX_BASE_PATH, $cmd),
            sprintf('%sassets/modifiers/%s.php', MODX_BASE_PATH, $cmd),
            sprintf('%sassets/plugins/phx/modifiers%s.phx.php', MODX_BASE_PATH, $cmd),
            sprintf('%sextenders/modifiers/mdf_%s.inc.php', MODX_CORE_PATH, $cmd),
        ];
        foreach ($_ as $mdf_path) {
            if (is_file($mdf_path)) {
                break;
            }
            $mdf_path = false;
        }

        if (empty($mdf_path)) {
            return false;
        }

        $code = trim(@file_get_contents($mdf_path));
        if (substr($code, 0, 5) === '<?php') {
            return substr($code, 6);
        }
        if (substr($code, 0, 2) === '<?') {
            return substr($code, 3);
        }
        if (substr($code, -2) === '?>') {
            return substr($code, 0, -2);
        }
        return $code;
    }

    public function getValueFromHTML($key, $value, $cmd, $opt)
    {
        if (evo()->getChunk('phx:' . $cmd)) {
            $html = evo()->getChunk('phx:' . $cmd);
        } elseif (evo()->getChunk($cmd) && strpos(evo()->getChunk($cmd), '[+value+]') !== false) {
            $html = evo()->getChunk($cmd);
        } else {
            return false;
        }

        $html = str_replace(['[+value+]', '[+output+]'], $value, $html);
        $value = str_replace(['[+options+]', '[+param+]'], $opt, $html);

        return $value;
    }

    public function isEmpty($cmd, $value)
    {
        if ($value !== '') {
            return false;
        }

        $_ = explode(
            ',',
            $this->condModifiers . ',_default,default,if,input,or,and,show,this,select,switch,then,else,id,ifempty,smart_desc,smart_description,summary'
        );
        if (in_array($cmd, $_)) {
            return false;
        }
        return true;
    }

    public function getValueFromPreset($key, $value, $cmd, $opt)
    {
        global $modx;

        if ($this->isEmpty($cmd, $value)) {
            return '';
        }

        $this->key = $key;
        $this->value = $value;
        $this->opt = $opt;
        switch ($cmd) {
            // Conditional Modifiers
            case 'input':
            case 'if':
                if (!$opt) {
                    return $value;
                }
                return $opt;
            case '=':
            case 'eq':
            case 'is':
            case 'equals':
                $this->condition[] = (int)($value == $opt);
                break;
            case 'neq':
            case 'ne':
            case 'notequals':
            case 'isnot':
            case 'isnt':
            case 'not':
                $this->condition[] = (int)($value != $opt);
                break;
            case '%':
                $this->condition[] = (int)($value % $opt == 0);
                break;
            case 'isempty':
                $this->condition[] = (int)empty($value);
                break;
            case 'isntempty':
            case 'isnotempty':
                $this->condition[] = (int)!empty($value);
                break;
            case '>=':
            case 'gte':
            case 'eg':
            case 'isgte':
                $this->condition[] = (int)($value >= $opt);
                break;
            case '<=':
            case 'lte':
            case 'el':
            case 'islte':
                $this->condition[] = (int)($value <= $opt);
                break;
            case '>':
            case 'gt':
            case 'greaterthan':
            case 'isgreaterthan':
            case 'isgt':
                $this->condition[] = (int)($value > $opt);
                break;
            case '<':
            case 'lt':
            case 'lowerthan':
            case 'islowerthan':
            case 'islt':
                $this->condition[] = (int)($value < $opt);
                break;
            case 'find':
                $this->condition[] = (int)(strpos($value, $opt) !== false);
                break;
            case 'inarray':
            case 'in_array':
            case 'in':
                $opt = explode(',', $opt);
                $this->condition[] = (int)(in_array($value, $opt) !== false);
                break;
            case 'wildcard_match':
            case 'wcard_match':
            case 'wildcard':
            case 'wcard':
            case 'fnmatch':
                $this->condition[] = (int)(fnmatch($opt, $value) !== false);
                break;
            case 'is_file':
            case 'is_dir':
            case 'file_exists':
            case 'is_readable':
            case 'is_writable':
                if (!$opt) {
                    $path = $value;
                } else {
                    $path = $opt;
                }
                if (strpos($path, MODX_MANAGER_PATH) !== false) {
                    exit('Can not read core path');
                }
                if (strpos($path, MODX_BASE_PATH) === false) {
                    $path = ltrim($path, '/');
                }
                $this->condition[] = (int)($cmd($path) !== false);
                break;
            case 'is_image':
                if (!$opt) {
                    $path = $value;
                } else {
                    $path = $opt;
                }
                if (!is_file($path)) {
                    $this->condition[] = '0';
                    break;
                }
                $_ = getimagesize($path);
                $this->condition[] = (int)$_[0];
                break;
            case 'regex':
            case 'preg':
            case 'preg_match':
                $this->condition[] = (int)preg_match($opt, $value);
                break;
            case 'isinrole':
            case 'ir':
            case 'memberof':
            case 'mo':
                // Is Member Of  (same as inrole but this one can be stringed as a conditional)
                $this->condition[] = $this->includeMdfFile('memberof');
                break;
            case 'or':
                $this->condition[] = '||';
                break;
            case 'and':
                $this->condition[] = '&&';
                break;
            case 'show':
            case 'this':
                $conditional = implode(' ', $this->condition);
                $isvalid = (int)eval(sprintf("return (%s);", $conditional));
                if ($isvalid) {
                    return $this->srcValue;
                }
                return null;
            case 'then':
                $conditional = implode(' ', $this->condition);
                $isvalid = (int)eval(sprintf("return (%s);", $conditional));
                if ($isvalid) {
                    $opt = str_replace(['[+value+]', '[+output+]', '{value}', '%s'], $value, $opt);
                    return $opt;
                }
                return null;
            case 'else':
                $conditional = implode(' ', $this->condition);
                $isvalid = (int)eval(sprintf('return (%s);', $conditional));
                if (!$isvalid) {
                    $opt = str_replace(['[+value+]', '[+output+]', '{value}', '%s'], $value, $opt);
                    return $opt;
                }
                break;
            case 'select':
            case 'switch':
                $raw = explode('&', $opt);
                $map = [];
                $c = count($raw);
                for ($m = 0; $m < $c; $m++) {
                    $mi = explode('=', $raw[$m], 2);
                    $map[$mi[0]] = $mi[1];
                }
                if (isset($map[$value])) {
                    return $map[$value];
                }
                return '';
            // End of Conditional Modifiers

            // Encode / Decode / Hash / Escape
            case 'htmlent':
            case 'htmlentities':
                return htmlentities($value, ENT_QUOTES, evo()->config('modx_charset', 'utf-8'));
            case 'html_entity_decode':
            case 'decode_html':
            case 'html_decode':
                return html_entity_decode($value, ENT_QUOTES, evo()->config('modx_charset', 'utf-8'));
            case 'htmlspecialchars':
            case 'hsc':
            case 'encode_html':
            case 'html_encode':
                return evo()->hsc($value, ENT_QUOTES);
            case 'esc':
            case 'escape':
                return str_replace(
                    ['[', ']', '`'],
                    ['&#91;', '&#93;', '&#96;'],
                    evo()->hsc($value, ENT_QUOTES)
                );
            case 'sql_escape':
            case 'encode_js':
                return db()->escape($value);
            case 'spam_protect':
                return str_replace(['@', '.'], ['&#64;', '&#46;'], $value);
            case 'strip_linefeeds':
                return str_replace(["\n", "\r"], '', $value);
            case 'strip':
                if ($opt === '') {
                    $opt = ' ';
                }
                return preg_replace('/[\n\r\t\s]+/', $opt, $value);
            case 'notags':
            case 'strip_tags':
            case 'remove_html':
                if ($opt !== '') {
                    $param = [];
                    foreach (explode(',', $opt) as $v) {
                        $v = trim($v, '</> ');
                        $param[] = "<" . $v . ">";
                    }
                    $params = implode(',', $param);
                } else {
                    $params = '';
                }
                if (!strpos($params, '<br>') === false) {
                    $value = preg_replace(
                        '@<br[ /]*>@',
                        "\n",
                        preg_replace(
                            '@(<br[ /]*>)\n@',
                            '$1',
                            $value
                        )
                    );
                }
                return remove_tags($value, $params);
            case 'urlencode':
            case 'url_encode':
            case 'encode_url':
                return urlencode($value);
            case 'base64_decode':
                if ($opt !== 'false') {
                    $opt = true;
                } else {
                    $opt = false;
                }
                return base64_decode($value, $opt);
            case 'addslashes':
            case 'urldecode':
            case 'url_decode':
            case 'rawurlencode':
            case 'rawurldecode':
            case 'base64_encode':
            case 'md5':
            case 'sha1':
            case 'json_encode':
            case 'json_decode':
                return $cmd($value);
            // String Modifiers
            case 'lcase':
            case 'strtolower':
            case 'lower_case':
                return $this->strtolower($value);
            case 'ucase':
            case 'strtoupper':
            case 'upper_case':
                return $this->strtoupper($value);
            case 'capitalize':
                $_ = explode(' ', $value);
                foreach ($_ as $i => $v) {
                    $_[$i] = ucfirst($v);
                }
                return implode(' ', $_);
            case 'zenhan':
                return mb_convert_kana(
                    $value,
                    $opt ? $opt : 'VKas',
                    evo()->config('modx_charset', 'utf-8')
                );
            case 'hanzen':
                return mb_convert_kana(
                    $value,
                    $opt ? $opt : 'VKAS',
                    evo()->config('modx_charset', 'utf-8')
                );
            case 'str_shuffle':
            case 'shuffle':
                return $this->str_shuffle($value);
            case 'reverse':
            case 'strrev':
                return $this->strrev($value);
            case 'length':
            case 'len':
            case 'strlen':
            case 'count_characters':
                return $this->strlen($value);
            case 'count_words':
                return count(preg_split('/\s+/', trim($value)));
            case 'str_word_count':
            case 'word_count':
            case 'wordcount':
                return $this->str_word_count($value);
            case 'count_paragraphs':
                return count(
                    preg_split(
                        '/\n+/',
                        preg_replace(
                            '/\r/',
                            '',
                            trim($value)
                        )
                    )
                );
            case 'strpos':
                if ($opt != 0 && empty($opt)) {
                    return $value;
                }
                return $this->strpos($value, $opt);
            case 'wordwrap':
                // default: 70
                return $this->includeMdfFile('wordwrap');
            case 'wrap_text':
                $width = preg_match('/^[1-9][0-9]*$/', $opt) ? $opt : 70;
                if (evo()->config('manager_language') !== 'japanese-utf8') {
                    return wordwrap($value, $width, "\n", true);
                }
                $chunk = [];
                $bt = '';
                while ($bt != $value) {
                    $bt = $value;
                    if ($this->strlen($value) < $width) {
                        $chunk[] = $value;
                        break;
                    }
                    $chunk[] = $this->substr($value, 0, $width);
                    $value = $this->substr($value, $width);
                }
                return implode("\n", $chunk);
            case 'substr':
                if (!$opt) {
                    break;
                }
                if (strpos($opt, ',') === false) {
                    return $this->substr($value, $opt);
                }
                [$b, $e] = explode(',', $opt, 2);
                return $this->substr($value, $b, (int)$e);
            case 'limit':
            case 'trim_to': // http://www.movabletype.jp/documentation/appendices/modifiers/trim_to.html
                if (strpos($opt, '+') === false) {
                    $len = $opt;
                    $str = '';
                } else {
                    [$len, $str] = explode('+', $opt, 2);
                }
                if ($len === '') {
                    $len = 100;
                }
                if (abs($len) > $this->strlen($value)) {
                    $str = '';
                }
                if (preg_match('/^[1-9][0-9]*$/', $len)) {
                    return $this->substr($value, 0, $len) . $str;
                }
                if (preg_match('/^-[1-9][0-9]*$/', $len)) {
                    return $str . $this->substr($value, $len);
                }
                break;
            case 'summary':
            case 'smart_description':
            case 'smart_desc':
                return $this->includeMdfFile('summary');
            case 'replace':
            case 'str_replace':
                if (empty($opt)) {
                    break;
                }
                if (substr_count($opt, ',') == 1 && $this->substr($opt, 0, 1) !== ',') {
                    $delim = ',';
                } elseif (substr_count($opt, '|') == 1) {
                    $delim = '|';
                } elseif (substr_count($opt, '=>') == 1) {
                    $delim = '=>';
                } elseif (substr_count($opt, '/') == 1) {
                    $delim = '/';
                } else {
                    break;
                }
                [$s, $r] = explode($delim, $opt);
                if ($value !== '') {
                    return str_replace($s, $r, $value);
                }
                break;
            case 'replace_to':
            case 'tpl':
                if ($value !== '') {
                    return str_replace(['[+value+]', '[+output+]', '{value}', '%s'], $value, $opt);
                }
                break;
            case 'eachtpl':
                if (strpos($value, '||') !== false) {
                    $delim = '||';
                } else {
                    $delim = ',';
                }
                $value = explode($delim, $value);
                $_ = [];
                foreach ($value as $v) {
                    $_[] = str_replace(['[+value+]', '[+output+]', '{value}', '%s'], $v, $opt);
                }
                return implode("\n", $_);
            case 'array_pop':
            case 'array_shift':
                if (strpos($value, '||') !== false) {
                    $delim = '||';
                } else {
                    $delim = ',';
                }
                return $cmd(explode($delim, $value));
            case 'preg_replace':
            case 'regex_replace':
                if (empty($opt) || strpos($opt, ',') === false) {
                    break;
                }
                [$s, $r] = explode(',', $opt, 2);
                if ($value !== '') {
                    return preg_replace($s, $r, $value);
                }
                break;
            case 'cat':
            case 'concatenate':
            case '.':
                if ($value !== '') {
                    return $value . $opt;
                }
                break;
            case 'sprintf':
            case 'string_format':
                if ($value !== '') {
                    return sprintf($opt, $value);
                }
                break;
            case 'number_format':
                if ($opt == '') {
                    $opt = 0;
                }
                return number_format($value, $opt);
            case 'money_format':
                setlocale(LC_MONETARY, setlocale(LC_TIME, 0));
                if ($value !== '') {
                    $fmt = new NumberFormatter('ja_JP', NumberFormatter::CURRENCY);
                    return $fmt->format((float)$value);
                }
                break;
            case 'tobool':
                return (bool)$value;
            case 'nl2lf':
                if ($value !== '') {
                    return str_replace(["\r\n", "\n", "\r"], '\n', $value);
                }
                break;
            case 'br2nl':
                return preg_replace('@<br[\s/]*>@i', "\n", $value);
            case 'nl2br':
                if ($opt !== '') {
                    $opt = strtolower(trim($opt));
                    if ($opt === 'false') {
                        $opt = false;
                    }
                    return nl2br($value, (boolean) $opt);
                }
                return nl2br(
                    $value,
                    (evo()->config('mce_element_format') === 'html')
                );
            case 'ltrim':
            case 'rtrim':
            case 'trim': // ref http://mblo.info/modifiers/custom-modifiers/rtrim_opt.html
                if ($opt === '') {
                    return $cmd($value);
                }
                return $cmd($value, $opt);
            // These are all straight wrappers for PHP functions
            case 'ucfirst':
            case 'lcfirst':
            case 'ucwords':
                return $this->$cmd($value);

            // Date time format
            case 'strftime':
            case 'date':
            case 'dateformat':
                if (!$value) {
                    return '';
                }
                if (empty($opt)) {
                    $opt = evo()->toDateFormat(null, 'formatOnly');
                }
                return datetime_format($opt, $value);
            case 'time':
                if (empty($opt)) {
                    $opt = '%H:%M';
                }
                if (!preg_match('@^[0-9]+$@', $value)) {
                    $value = strtotime($value);
                }
                return evo()->mb_strftime($opt, 0 + $value);
            case 'strtotime':
                return strtotime($value);
            // mathematical function
            case 'toint':
                return (int)$value;
            case 'tofloat':
                return (float)$value;
            case 'round':
                if (!$opt) {
                    $opt = 0;
                }
                return $cmd($value, $opt);
            case 'max':
            case 'min':
                return $cmd(explode(',', $value));
            case 'floor':
            case 'ceil':
            case 'abs':
                return $cmd($value);
            case 'math':
            case 'calc':
                $value = (int)$value;
                if (empty($value)) {
                    $value = '0';
                }
                $filter = str_replace(['[+value+]', '[+output+]', '{value}', '%s'], '?', $opt);
                $filter = preg_replace('@([a-zA-Z\n\r\t\s])@', '', $filter);
                if (strpos($filter, '?') === false) {
                    $filter = "?" . $filter;
                }
                $filter = str_replace('?', $value, $filter);
                return eval(sprintf('return %s;', $filter));
            case 'count':
                if ($value == '') {
                    return 0;
                }
                $value = explode(',', $value);
                return count($value);
            case 'sort':
            case 'rsort':
                if (strpos($value, "\n") !== false) {
                    $delim = "\n";
                } else {
                    $delim = ',';
                }
                $swap = explode($delim, $value);
                if (!$opt) {
                    $opt = SORT_REGULAR;
                } else {
                    $opt = constant($opt);
                }
                $cmd($swap, $opt);
                return implode($delim, $swap);
            // Resource fields
            case 'id':
                if ($opt) {
                    return $this->getDocumentObject($opt, $key);
                }
                break;
            case 'type':
            case 'contenttype':
            case 'pagetitle':
            case 'longtitle':
            case 'description':
            case 'alias':
            case 'introtext':
            case 'link_attributes':
            case 'published':
            case 'pub_date':
            case 'unpub_date':
            case 'parent':
            case 'isfolder':
            case 'content':
            case 'richtext':
            case 'template':
            case 'menuindex':
            case 'searchable':
            case 'cacheable':
            case 'createdby':
            case 'createdon':
            case 'editedby':
            case 'editedon':
            case 'deleted':
            case 'deletedon':
            case 'deletedby':
            case 'publishedon':
            case 'publishedby':
            case 'menutitle':
            case 'donthit':
            case 'haskeywords':
            case 'privateweb':
            case 'privatemgr':
            case 'content_dispo':
            case 'hidemenu':
                if ($cmd === 'contenttype') {
                    $cmd = 'contentType';
                }
                return $this->getDocumentObject($value, $cmd);
            case 'title':
                $pagetitle = $this->getDocumentObject($value, 'pagetitle');
                $longtitle = $this->getDocumentObject($value, 'longtitle');
                return $longtitle ? $longtitle : $pagetitle;
            case 'shorttitle':
                $pagetitle = $this->getDocumentObject($value, 'pagetitle');
                $menutitle = $this->getDocumentObject($value, 'menutitle');
                return $menutitle ? $menutitle : $pagetitle;
            case 'templatename':
                $rs = db()->select('templatename', '[+prefix+]site_templates', "id='" . $value . "'");
                $templateName = db()->getValue($rs);
                return !$templateName ? '(blank)' : $templateName;
            case 'getfield':
                if (!$opt) {
                    $opt = 'content';
                }
                return evo()->getField($opt, $value);
            case 'children':
            case 'childids':
                if ($value == '') {
                    $value = 0;
                } // 値がない場合はルートと見なす
                $published = 1;
                if ($opt == '') {
                    $opt = 'page';
                }
                $options = explode(',', $opt);
                $where = [];
                foreach ($options as $option) {
                    switch (trim($option)) {
                        case 'page';
                        case '!folder';
                        case '!isfolder':
                            $where[] = 'sc.isfolder=0';
                            break;
                        case 'folder';
                        case 'isfolder':
                            $where[] = 'sc.isfolder=1';
                            break;
                        case  'menu';
                        case  'show_menu':
                            $where[] = 'sc.hidemenu=0';
                            break;
                        case '!menu';
                        case '!show_menu':
                            $where[] = 'sc.hidemenu=1';
                            break;
                        case  'published':
                            $published = 1;
                            break;
                        case '!published':
                            $published = 0;
                            break;
                    }
                }
                $where = implode(' AND ', $where);
                $children = evo()->getDocumentChildren($value, $published, '0', 'id', $where);
                $result = [];
                foreach ((array)$children as $child) {
                    $result[] = $child['id'];
                }
                return implode(',', $result);
            case 'fullurl':
                if (!is_numeric($value)) {
                    return $value;
                }
                return evo()->makeUrl($value);
            case 'makeurl':
                if (!is_numeric($value)) {
                    return $value;
                }
                if (!$opt) {
                    $opt = 'full';
                }
                return evo()->makeUrl($value, '', '', $opt);

            // File system
            case 'getimageinfo':
            case 'imageinfo':
                if (!is_file($value)) {
                    return '';
                }
                $_ = getimagesize($value);
                if (!$_[0]) {
                    return '';
                }
                $info['width'] = $_[0];
                $info['height'] = $_[1];
                if ($_[0] > $_[1]) {
                    $info['aspect'] = 'landscape';
                } elseif ($_[0] < $_[1]) {
                    $info['aspect'] = 'portrait';
                } else {
                    $info['aspect'] = 'square';
                }
                switch ($_[2]) {
                    case IMAGETYPE_GIF:
                        $info['type'] = 'gif';
                        break;
                    case IMAGETYPE_JPEG:
                        $info['type'] = 'jpg';
                        break;
                    case IMAGETYPE_PNG:
                        $info['type'] = 'png';
                        break;
                    default:
                        $info['type'] = 'unknown';
                }
                $info['attrib'] = $_[3];
                switch ($opt) {
                    case 'width':
                        return $info['width'];
                    case 'height':
                        return $info['height'];
                    case 'aspect':
                        return $info['aspect'];
                    case 'type':
                        return $info['type'];
                    case 'attrib':
                        return $info['attrib'];
                    default:
                        return print_r($info, true);
                }

            case 'file_get_contents':
            case 'readfile':
                if (!is_file($value)) {
                    return $value;
                }
                $value = realpath($value);
                if (strpos($value, MODX_MANAGER_PATH) !== false) {
                    exit('Can not read core file');
                }
                $ext = strtolower(substr($value, -4));
                if ($ext === '.php') {
                    exit('Can not read php file');
                }
                if ($ext === '.cgi') {
                    exit('Can not read cgi file');
                }
                return file_get_contents($value);
            case 'filesize':
                if ($value == '') {
                    return '';
                }
                $filename = $value;

                if (strpos($filename, MODX_SITE_URL) === 0) {
                    $filename = substr($filename, 0, strlen(MODX_SITE_URL));
                }
                $filename = trim($filename, '/');

                $opt = trim($opt, '/');
                if ($opt !== '') {
                    $opt .= '/';
                }

                $filename = MODX_BASE_PATH . $opt . $filename;

                if (is_file($filename)) {
                    clearstatcache();
                    return filesize($filename);
                }
                return '';
            // User info
            case 'username':
            case 'fullname':
            case 'role':
            case 'email':
            case 'phone':
            case 'mobilephone':
            case 'blocked':
            case 'blockeduntil':
            case 'blockedafter':
            case 'logincount':
            case 'lastlogin':
            case 'thislogin':
            case 'failedlogincount':
            case 'dob':
            case 'gender':
            case 'country':
            case 'street':
            case 'city':
            case 'state':
            case 'zip':
            case 'fax':
            case 'photo':
            case 'comment':
                $this->opt = $cmd;
                return $this->includeMdfFile('moduser');
            case 'userinfo':
                if (empty($opt)) {
                    $this->opt = 'username';
                }
                return $this->includeMdfFile('moduser');
            case 'webuserinfo':
                if (empty($opt)) {
                    $this->opt = 'username';
                }
                $this->value = -$value;
                return $this->includeMdfFile('moduser');
            // Special functions
            case 'ifempty':
            case '_default':
            case 'default':
                if (empty($value)) {
                    return $opt;
                }
                break;
            case 'ifnotempty':
                if (empty($value)) {
                    return null;
                }
                $opt = str_replace(['[+output+]', '{value}', '%s'], '[+value+]', $opt);
                $opt = evo()->parseText($opt, ['value' => $value]);
                return $opt;
            case 'datagrid':
                include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
                $grd = new DataGrid();
                $grd->ds = trim($value);
                $grd->itemStyle = '';
                $grd->altItemStyle = '';
                $pos = strpos($value, "\n");
                if ($pos) {
                    $_ = substr($value, 0, $pos);
                } else {
                    $_ = $pos;
                }
                $grd->cdelim = strpos($_, "\t") !== false ? 'tab' : ',';
                return $grd->render();
            case 'rotate':
            case 'evenodd':
                if (strpos($opt, ',') === false) {
                    $opt = 'odd,even';
                }
                $_ = explode(',', $opt);
                $c = count($_);
                $i = $value + $c;
                $i = $i % $c;
                return $_[$i];
            case 'takeval':
                $arr = explode(",", $opt);
                $idx = $value;
                if (!is_numeric($idx)) {
                    return $value;
                }
                return $arr[$idx];
            case 'getimage':
                return $this->includeMdfFile('getimage');
            case 'nicesize':
                return evo()->nicesize($value);
            case 'googlemap':
            case 'googlemaps':
                if (empty($opt)) {
                    $opt = 'border:none;width:500px;height:350px;';
                }
                return evo()->parseText(
                    '<iframe style="[+style+]" src="https://maps.google.com/maps?ll=[+value+]&output=embed&z=15"></iframe>',
                    [
                        'style' => $opt,
                        'value' => $value,
                    ]
                );
            case 'youtube':
            case 'youtube16x9':
                if (empty($opt)) {
                    $opt = 560;
                }
                return sprintf(
                    '<iframe width="%s" height="%s" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe>',
                    $opt,
                    round($opt * 0.5625),
                    $value
                );
            //case 'youtube4x3':%s*0.75＋25
            case 'setvar':
                $modx->placeholders[$opt] = $value;
                return '';
            case 'csstohead':
                evo()->regClientCSS($value);
                return '';
            case 'htmltohead':
                evo()->regClientStartupHTMLBlock($value);
                return '';
            case 'htmltobottom':
                evo()->regClientHTMLBlock($value);
                return '';
            case 'jstohead':
                evo()->regClientStartupScript($value);
                return '';
            case 'jstobottom':
                evo()->regClientScript($value);
                return '';
            case 'dummy':
                return $value;

            // If we haven't yet found the modifier, let's look elsewhere
            default:
                $_ = compact('key', 'value', 'cmd', 'opt');
                $_['url'] = $_SERVER['REQUEST_URI'];
                $_['ip'] = $_SERVER['REMOTE_ADDR'];
                $_['ua'] = $_SERVER['HTTP_USER_AGENT'];
                $_['docid'] = evo()->documentIdentifier;
                evo()->addLog('unparsed modifire', $_, 2);
        }
        return $value;
    }

    public function includeMdfFile($cmd)
    {
        global $modx;
        $key = $this->key;
        $value = $this->value;
        $opt = $this->opt;
        return include(MODX_CORE_PATH . "extenders/modifiers/mdf_" . $cmd . ".inc.php");
    }

    public function parseDocumentSource($content = '')
    {
        global $modx;

        if (strpos($content, '[') === false && strpos($content, '{') === false) {
            return $content;
        }

        if (!$modx->maxParserPasses) {
            $modx->maxParserPasses = 10;
        }
        $bt = '';
        $i = 0;
        while ($bt !== $content) {
            $bt = $content;
            if ($modx->documentIdentifier && strpos($content, '[*') !== false) {
                $content = $modx->mergeDocumentContent($content);
            }
            if (strpos($content, '[(') !== false) {
                $content = $modx->mergeSettingsContent($content);
            }
            if (strpos($content, '{{') !== false) {
                $content = $modx->mergeChunkContent($content);
            }
            if (strpos($content, '[!') !== false) {
                $content = str_replace(['[!', '!]'], ['[[', ']]'], $content);
            }
            if (strpos($content, '[[') !== false) {
                $content = $modx->evalSnippets($content);
            }

            if ($content === $bt) {
                break;
            }
            if ($modx->maxParserPasses < $i) {
                break;
            }
            $i++;
        }
        return $content;
    }

    public function getDocumentObject($target = '', $field = 'pagetitle')
    {
        $target = trim($target);
        if (!$target) {
            $target = evo()->config('site_start');
        }

        if (!isset($this->documentObject[$target])) {
            $this->documentObject[$target] = evo()->getDocumentObject(
                preg_match('@^[1-9][0-9]*$@', $target) ? 'id' : 'alias',
                $target,
                'direct'
            );
        }

        if ($this->documentObject[$target]['publishedon'] == 0) {
            return '';
        }

        if (isset($this->documentObject[$target][$field])) {
            if (is_array($this->documentObject[$target][$field])) {
                $a = evo()->getTemplateVarOutput($field, $target);
                $this->documentObject[$target][$field] = $a[$field];
            }
        } else {
            $this->documentObject[$target][$field] = false;
        }

        return $this->documentObject[$target][$field];
    }

    public function setPlaceholders($value = '', $key = '', $path = '')
    {
        if ($path !== '') {
            $key = $path . "." . $key;
        }
        if (is_array($value)) {
            foreach ($value as $subkey => $subval) {
                $this->setPlaceholders($subval, $subkey, $key);
            }
        } else {
            $this->setModifiersVariable($key, $value);
        }
    }

    // Sets a placeholder variable which can only be access by Modifiers
    public function setModifiersVariable($key, $value)
    {
        if ($key !== 'phx' && $key !== 'dummy') {
            $this->placeholders[$key] = $value;
        }
    }

    //mbstring
    private function substr($str, $s, $l = null)
    {
        if ($l === null) {
            $l = $this->strlen($str);
        }
        if (!function_exists('mb_substr')) {
            return substr($str, $s, $l);
        }
        if (strpos($str, "\r") !== false) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }
        return mb_substr($str, $s, $l, evo()->config('modx_charset', 'utf-8'));
    }

    private function strpos($haystack, $needle, $offset = 0)
    {
        if (!function_exists('mb_strpos')) {
            return strpos($haystack, $needle, $offset);
        }
        return mb_strpos(
            $haystack,
            $needle,
            $offset,
            evo()->config('modx_charset', 'utf-8')
        );
    }

    private function strlen($str)
    {
        if (!function_exists('mb_strlen')) {
            return strlen($str);
        }
        return mb_strlen(
            str_replace("\r\n", "\n", $str),
            evo()->config('modx_charset', 'utf-8')
        );
    }

    private function strtolower($str)
    {
        if (!function_exists('mb_strtolower')) {
            return strtolower($str);
        }
        return mb_strtolower($str);
    }

    private function strtoupper($str)
    {
        if (!function_exists('mb_strtoupper')) {
            return strtoupper($str);
        }
        return mb_strtoupper($str);
    }

    private function ucfirst($str)
    {
        if (!function_exists('mb_strtoupper')) {
            return ucfirst($str);
        }
        return mb_strtoupper(
                $this->substr(
                    $str,
                    0,
                    1
                )
            ) . $this->substr(
                $str,
                1,
                $this->strlen($str)
            );
    }

    private function lcfirst($str)
    {
        if (!function_exists('mb_strtolower')) {
            return lcfirst($str);
        }
        return mb_strtolower(
                $this->substr(
                    $str,
                    0,
                    1
                )
            ) . $this->substr(
                $str,
                1,
                $this->strlen($str)
            );
    }

    private function ucwords($str)
    {
        if (!function_exists('mb_convert_case')) {
            return ucwords($str);
        }
        return mb_convert_case($str, MB_CASE_TITLE);
    }

    private function strrev($str)
    {
        preg_match_all('/./us', $str, $ar);
        return implode(array_reverse($ar[0]));
    }

    private function str_shuffle($str)
    {
        preg_match_all('/./us', $str, $ar);
        shuffle($ar[0]);
        return implode($ar[0]);
    }

    private function str_word_count($str)
    {
        return count(preg_split('~[^\p{L}\p{N}\']+~u', $str));
    }
}
