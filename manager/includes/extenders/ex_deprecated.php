<?php
$this->old = new OldFunctions();

class OldFunctions
{
    public function makeList($array, $ulroot = 'root', $ulprefix = 'sub_', $type = '', $ordered = false, $tablevel = 0)
    {
        global $modx;
        // first find out whether the value passed is an array
        if (!is_array($array)) {
            return "<ul><li>Bad list</li></ul>";
        }

        $tabs = '';
        for ($i = 0; $i < $tablevel; $i++) {
            $tabs .= "\t";
        }

        $tag = ($ordered == true) ? 'ol' : 'ul';

        if (!empty($type)) {
            $typestr = " style='list-style-type: {$type}'";
        } else {
            $typestr = '';
        }

        $listhtml = "{$tabs}<{$tag} class='{$ulroot}'{$typestr}>\n";
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $line = $modx->makeList($value, "{$ulprefix}{$ulroot}", $ulprefix, $type, $ordered, $tablevel + 2);
                $listhtml .= "{$tabs}\t<li>{$key}\n{$line}{$tabs}\t</li>\n";
            } else {
                $listhtml .= "{$tabs}\t<li>{$value}</li>\n";
            }
        }
        $listhtml = "{$tabs}</{$tag}>\n";
        return $listhtml;
    }

    public function getUserData()
    {
        $client['host'] = $_SERVER['REMOTE_ADDR'];
        $client['ip'] = $_SERVER['REMOTE_ADDR'];
        $client['ua'] = $_SERVER['HTTP_USER_AGENT'];
        return $client;
    }

    public function insideManager()
    {
        $m = false;
        if (defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true') {
            $m = true;
            if (defined('SNIPPET_INTERACTIVE_MODE') && SNIPPET_INTERACTIVE_MODE == 'true') {
                $m = "interact";
            } elseif (defined('SNIPPET_INSTALL_MODE') && SNIPPET_INSTALL_MODE == 'true') {
                $m = "install";
            }
        }
        return $m;
    }

    public function putChunk($chunkName)
    {
        global $modx;
        return $modx->getChunk($chunkName);
    }// deprecated alias name >.<

    public function getDocGroups()
    {
        global $modx;
        return $modx->getUserDocGroups();
    } // deprecated

    public function changePassword($o, $n)
    {
        return evo()->changeWebUserPassword($o, $n);
    } // deprecated

    public function userLoggedIn()
    {
        global $modx;
        $userdetails = [];
        if ($modx->isFrontend() && isset ($_SESSION['webValidated'])) {
            // web user
            $userdetails['loggedIn'] = true;
            $userdetails['id'] = $_SESSION['webInternalKey'];
            $userdetails['username'] = $_SESSION['webShortname'];
            $userdetails['usertype'] = 'web'; // added by Raymond
            return $userdetails;
        } elseif ($modx->isBackend() && isset ($_SESSION['mgrValidated'])) {
            // manager user
            $userdetails['loggedIn'] = true;
            $userdetails['id'] = $_SESSION['mgrInternalKey'];
            $userdetails['username'] = $_SESSION['mgrShortname'];
            $userdetails['usertype'] = 'manager'; // added by Raymond
            return $userdetails;
        } else {
            return false;
        }
    }

    public function makeFriendlyURL($pre, $suff, $path)
    {
        global $modx;
        $elements = explode('/', $path);
        $alias = array_pop($elements);
        $dir = implode('/', $elements);
        unset($elements);
        if ((strpos($alias, '.') !== false)) {
            if (isset($modx->config['suffix_mode']) && $modx->config['suffix_mode'] == 1) {
                $suff = '';
            } // jp-edition only
        }
        //container_suffix
        if (substr($alias, 0, 1) === '[' && substr($alias, -1) === ']') {
            return '[~' . $alias . '~]';
        }
        return ($dir !== '' ? $dir . '/' : '') . $pre . $alias . $suff;
    }

    # Displays a javascript alert message in the web browser
    public function webAlert($msg, $url = '')
    {
        global $modx;

        $msg = addslashes(db()->escape($msg));
        if (substr(strtolower($url), 0, 11) == 'javascript:') {
            $act = '__WebAlert();';
            $fnc = 'function __WebAlert(){' . substr($url, 11) . '};';
        } else {
            $act = $url ? "window.location.href='" . addslashes($url) . "';" : '';
        }
        $html = "<script>{$fnc} window.setTimeout(\"alert('{$msg}');{$act}\",100);</script>";
        if ($modx->isFrontend()) {
            $modx->regClientScript($html);
        } else {
            echo $html;
        }
    }

    public function makeDocumentListing()
    {
        global $modx;

        $cache_path = MODX_CACHE_PATH . 'documentListing.siteCache.idx.php';
        if (is_file($cache_path)) {
            $d = @include_once $cache_path;
        }
        if ($d) {
            $modx->documentListing = $d;
            return $modx->documentListing;
        }

        $field = "IF(alias='', id, alias) AS alias, id, parent";
        $rs = db()->select($field, '[+prefix+]site_content', 'deleted=0', 'parent, menuindex');
        while ($row = db()->getRow($rs)) {
            $docs[$row['id']] = ['alias' => $row['alias'], 'parent' => $row['parent']];
        }

        foreach ($docs as $docid => $doc) {
            if ($modx->config['friendly_urls'] !== '1' || $modx->config['use_alias_path'] !== '1') {
                $key = $doc['alias'];
            } else {
                $_ = [];
                $_[] = $doc['alias'];
                $pid = $doc['parent'];
                if ($pid !== '0') {
                    $c = 0;
                    while ($c < 50) {
                        if (!isset($docs[$pid])) {
                            exit('error');
                        }
                        $doc = $docs[$pid];
                        $_[] = $doc['alias'];
                        $pid = $doc['parent'];
                        if ($pid === '0') {
                            break;
                        }
                        $c++;
                        if ($c === 50) {
                            exit('over');
                        }
                    }
                    $_ = array_reverse($_);
                }
                $key = implode('/', $_);
            }
            $modx->documentListing[$key] = $docid;
            $str = "<?php\n// Deprecated since 1.0.6\nreturn " . var_export($modx->documentListing, true) . ';';
            file_put_contents($cache_path, $str, LOCK_EX);
        }
        return $modx->documentListing;
    }
}
