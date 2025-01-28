<?php
// cache & synchronise class

class synccache
{
    public $cachePath;
    public $showReport;
    public $aliases = [];
    public $parents = [];
    public $target;
    public $config = [];
    public $cacheRefreshTime;

    private $current_time;

    public function __construct()
    {
        if (!$this->target) {
            $this->target = 'pagecache,sitecache';
        }
        if (defined('MODX_CACHE_PATH')) {
            $this->cachePath = MODX_CACHE_PATH;
        }

        $this->current_time = request_time() + config('server_offset_time', 0);
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function setTarget($target)
    {
        $this->target = $target;
    }

    public function setCachepath($path)
    {
        $this->cachePath = rtrim($path, '/') . '/';
    }

    public function setReport($bool)
    {
        $this->showReport = $bool;
    }

    private function getParents($id, $path = '')
    { // modx:returns child's parent
        if (empty($this->aliases)) {
            $qh = db()->select(
                "id, IF(alias='', id, alias) AS alias, parent",
                '[+prefix+]site_content',
                'deleted=0 and isfolder=1'
            );
            if ($qh && db()->count($qh)) {
                while ($row = db()->getRow($qh)) {
                    $this->aliases[$row['id']] = $row['alias'];
                    $this->parents[$row['id']] = $row['parent'];
                }
            }
        }

        if (!isset($this->aliases[$id])) {
            return $path;
        }

        if ($path === '') {
            return $this->getParents($this->parents[$id], $this->aliases[$id]);
        }

        return $this->getParents(
            $this->parents[$id],
            sprintf(
                '%s/%s',
                $this->aliases[$id],
                $path
            )
        );
    }

    public function emptyCache()
    {
        global $modx;

        $instance_name = '';
        if (is_object($modx)) {
            $instance_name = get_class($modx);
        }
        if (strtolower($instance_name) !== 'documentparser') {
            global $modx;
        }

        if (strpos($this->target, 'pagecache') !== false) {
            $result = $this->purgeCacheFiles();
        }
        if (strpos($this->target, 'sitecache') !== false) {
            $this->purgeCacheFiles('siteCache');
            $this->buildCache();
        }
        $this->publishBasicConfig();

        evo()->purgeDBCache();

        if (!isset($result) || $this->showReport != true) {
            return;
        }
        $this->showReport($result);
    }

    private function purgeCacheFiles($target = 'pageCache')
    {
        if (!defined('MODX_BASE_PATH') || !strlen(MODX_BASE_PATH)) {
            return false;
        }
        if (strpos($this->cachePath, MODX_BASE_PATH) !== 0) {
            return false;
        }

        if ($target === 'pageCache') {
            $pattern = '@\.pageCache\.php$@';
        } else {
            $pattern = '@\.*$@';
        }

        $files = $this->getFileList($this->cachePath, $pattern);
        $files = array_reverse($files);

        $filesincache = ($files && $files[0] !== $pattern) ? count($files) : 0;

        if (!$filesincache) {
            return [0, 0, []];
        }
        $deletedfiles = [];
        $cachedir_len = strlen($this->cachePath);
        while ($file_path = array_shift($files)) {
            $name = substr($file_path, $cachedir_len);
            if (in_array($name, $deletedfiles)) {
                continue;
            }
            $rs = null;
            if (is_file($file_path)) {
                $rs = @unlink($file_path);
            } elseif (is_dir($file_path)) {
                $rs = @rmdir($file_path);
            }
            if ($rs) {
                $deletedfiles[] = $name;
            }
        }
        return [$filesincache, count($deletedfiles), $deletedfiles];
    }

    public function showReport($info)
    {
        global $_lang;
        list($filesincache, $deletedfilesincache, $deletedfiles) = $info;
        // finished cache stuff.
        if (0 < $filesincache) {
            echo sprintf($_lang['refresh_cache'], $filesincache, $deletedfilesincache);
        } else {
            echo '<p>削除対象のページキャッシュはありません。</p>';
        }

        if (!$deletedfiles) {
            return;
        }
        echo sprintf('<p>%s</p><ul style="margin-bottom:1em;">', $_lang['cache_files_deleted']);
        foreach ($deletedfiles as $i => $deletedfile) {
            echo sprintf('<li>%s</li>', $deletedfile);
        }
        echo '</ul>';
    }

    private function recent_update()
    {
        $f = [
            'setting_value' => $this->current_time,
            'setting_name' => 'recent_update'
        ];
        $rs = db()->select(
            'setting_name,setting_value',
            '[+prefix+]system_settings',
            "setting_name='recent_update'"
        );
        if (db()->count($rs)) {
            db()->update($f, '[+prefix+]system_settings', "setting_name='recent_update'");
        } else {
            db()->insert($f, '[+prefix+]system_settings');
        }
    }

    public function publishBasicConfig()
    {
        global $site_sessionname;

        $this->recent_update();
        $config = $this->_get_settings();

        $content = [];
        $content[] = '<?php';
        $content[] = sprintf(
            '$recent_update = %s; // %s',
            config('recent_update', 0),
            date('Y-m-d H:i:s', config('recent_update', 0))
        );

        $cacheRefreshTime = $this->getCacheRefreshTime();
        $content[] = sprintf(
            '$cacheRefreshTime = %s; // %s',
            $cacheRefreshTime,
            date('Y-m-d H:i:s', $cacheRefreshTime)
        );
        $content[] = sprintf(
            '$cache_type = %s;',
            config('cache_type', 1)
        );
        if (isset($site_sessionname) && $site_sessionname) {
            $content[] = sprintf(
                '$site_sessionname = "%s";',
                $site_sessionname
            );
        }

        $content[] = sprintf(
            '$site_status = %s;',
            config('site_status', 1)
        );
        $content[] = sprintf(
            '$error_reporting = "%s";',
            config('error_reporting', 1)
        );

        if (evo()->array_get($config, 'site_url') && strpos(evo()->array_get($config, 'site_url'), '[(') === false) {
            $content[] = sprintf(
                '$site_url = "%s";',
                evo()->array_get($config, 'site_url')
            );
        }

        if (evo()->array_get($config, 'base_url') && strpos(evo()->array_get($config, 'base_url'), '[(') === false) {
            $content[] = sprintf(
                '$base_url = "%s";',
                evo()->array_get($config, 'base_url')
            );
        }

        if (config('conditional_get')) {
            $content[] = sprintf(
                '$conditional_get = "%s";',
                config('conditional_get')
            );
        }

        if (!evo()->saveToFile($this->cachePath . 'basicConfig.php', join("\n", $content))) {
            exit(sprintf('Cannot open file (%sbasicConfig.php)', $this->cachePath));
        }
    }

    public function setCacheRefreshTime($unixtime)
    {
        $this->cacheRefreshTime = $unixtime;
    }

    private function getCacheRefreshTime()
    {
        $time = ['cacheRefreshTime' => $this->cacheRefreshTime];

        $time['content_pub_date'] = $this->minTime(
            'site_content',
            'pub_date',
            sprintf(
                '(%s < pub_date and published=0) and (unpub_date=0 OR pub_date<=unpub_date)',
                $this->current_time
            )
        );

        $time['content_unpub_date'] = $this->minTime(
            'site_content',
            'unpub_date',
            sprintf(
                '(%s < unpub_date and published=1) and (pub_date=0 OR pub_date<=unpub_date)',
                $this->current_time
            )
        );

        $time['chunk_pub_date'] = $this->minTime(
            'site_htmlsnippets',
            'pub_date',
            sprintf(
                '(%s < pub_date and published=0) and (unpub_date=0 OR pub_date<=unpub_date)',
                $this->current_time
            )
        );

        $time['chunk_unpub_date'] = $this->minTime(
            'site_htmlsnippets',
            'unpub_date',
            sprintf(
                '(%s < unpub_date and published=1) and (pub_date=0 OR pub_date<=unpub_date)',
                $this->current_time
            )
        );

        $time['revision_standby'] = $this->minTime(
            'site_revision',
            'pub_date',
            sprintf(
                "(%s < pub_date and status = 'standby')",
                $this->current_time
            )
        );

        $time = array_filter($time, function($v) {
            return !empty($v);
        });

        if (!$time) {
            return 0;
        }

        return $this->current_time < min($time)
            ? min($time)
            : 0
        ;
    }

    private function minTime($table_name, $field_name, $where)
    {
        $rs = db()->select(
            sprintf('MIN(%s) AS result', $field_name),
            '[+prefix+]' . $table_name,
            $where
        );
        if (!$rs) {
            return 0;
        }
        return db()->getValue($rs);
    }

    /**
     * build siteCache file
     * @return boolean success
     */
    public function buildCache()
    {
        global $modx, $_lang;

        // invoke OnBeforeCacheUpdate event
        evo()->invokeEvent('OnBeforeCacheUpdate');

        $config = $this->_get_settings(); // get settings
        $this->cache_put_contents('config.siteCache.idx.php', $config);

        $content = "<?php\n";
        $content .= "if(!defined('MODX_BASE_PATH') || strpos(str_replace('\\\\','/',__FILE__), MODX_BASE_PATH)!==0) exit;\n";
        $content .= $this->_get_content_types(); // get content types
        $content .= $this->_get_events();   // WRITE system event triggers
        $content .= "\n";
        $content = str_replace(["\x0d\x0a", "\x0a", "\x0d"], "\x0a", $content);


        if (!evo()->saveToFile($this->cachePath . 'siteCache.idx.php', $content)) {
            exit(sprintf('siteCache.idx.php - %s', $_lang['file_not_saved']));
        }

        if (evo()->config('legacy_cache')) {
            $this->_legacy_cache();
            $this->cache_put_contents('aliasListing.siteCache.idx.php', $modx->aliasListing);
            $this->cache_put_contents('documentMap.siteCache.idx.php', $modx->documentMap);
        }
        $this->cache_put_contents('chunk.siteCache.idx.php', $this->_get_chunks());
        $this->cache_put_contents('snippet.siteCache.idx.php', $this->_get_snippets());
        $this->cache_put_contents('plugin.siteCache.idx.php', $this->_get_plugins());

        evo()->saveToFile($this->cachePath . '.htaccess', "order deny,allow\ndeny from all\n");

        // invoke OnCacheUpdate event
        evo()->invokeEvent('OnCacheUpdate');

        return true;
    }

    private function cache_put_contents($filename, $content)
    {
        global $_lang;
        if (!$content) {
            return;
        }
        if (is_array($content)) {
            $content = var_export($content, 'true');
            if (strpos($filename, 'documentMap') !== false) {
                $content = str_replace(
                    ["\n", '),'],
                    ['', "),\n"],
                    $content
                );
            }
            $content = sprintf("<?php \n return %s;", $content);
            if (strpos($filename, 'documentMap') !== false) {
                $content = str_replace('return array (', "return array (\n", $content);
            }
        }

        $cache_path = $this->cachePath . $filename;

        if (evo()->saveToFile($cache_path, $content)) {
            return;
        }

        if (!defined('IN_MANAGER_MODE')) {
            exit(sprintf('%s - %s', $cache_path, $_lang['file_not_saved']));
        }

        header('Content-Type: text/html; charset=' . evo()->config('modx_charset', 'utf-8'));
        echo evo()->parseText(
            '<link rel="stylesheet" type="text/css" href="[+manager_url+]media/style/[+theme+]/style.css" />',
            [
                'manager_url' => MODX_MANAGER_URL,
                'theme' => evo()->config('manager_theme', '')
            ]
        );
        exit(sprintf(
            '<div class="section"><div class="sectionBody">%s - %s</div></div>',
            $cache_path,
            $_lang['file_not_saved']
        ));
    }

    private function _get_settings()
    {
        global $modx;
        static $config = null;
        if ($config) {
            return $config;
        }
        if ($this->config) {
            return $this->config;
        }

        $rs = db()->select('setting_name,setting_value', '[+prefix+]system_settings');
        $config = [];
        while ($row = db()->getRow($rs)) {
            $config[$row['setting_name']] = $row['setting_value'];
        }
        foreach ($config as $k => $v) {
            $modx->config[$k] = $v;
        }
        return $config;
    }

    private function _legacy_cache()
    {
        global $modx;

        $rs = db()->select(
            "IF(alias='', id, alias) AS alias, id, parent, isfolder",
            '[+prefix+]site_content',
            'deleted=0',
            'parent, menuindex'
        );
        $modx->aliasListing = [];
        $modx->documentMap = [];
        while ($row = db()->getRow($rs)) {
            $modx->aliasListing[$row['id']] = [
                'id' => $row['id'],
                'alias' => $row['alias'],
                'path' => $this->alias_path($row['parent']),
                'parent' => $row['parent'],
                'isfolder' => $row['isfolder']
            ];
            $modx->documentMap[] = [$row['parent'] => $row['id']];
        }
    }

    private function alias_path($parent_id)
    {
        if (!evo()->config('friendly_urls')) {
            return $parent_id;
        }
        if (evo()->config('use_alias_path')) {
            return $this->getParents($parent_id);
        }
        return '';
    }

    private function _get_content_types()
    {
        $rs = db()->select(
            'id, contentType',
            '[+prefix+]site_content',
            "contentType != 'text/html'"
        );
        $_ = ['$c = &$this->contentTypes;'];
        while ($row = db()->getRow($rs)) {
            $_[] = sprintf(
                '$c[%s] = \'%s\';',
                $row['id'],
                $row['contentType']
            );
        }
        return implode("\n", $_) . "\n";
    }

    private function _get_chunks()
    {
        global $modx;

        $rs = db()->select(
            'name,snippet',
            '[+prefix+]site_htmlsnippets',
            "`published`='1'"
        );
        $modx->chunkCache = [];
        while ($row = db()->getRow($rs)) {
            $modx->chunkCache[db()->escape($row['name'])] = $row['snippet'];
        }
        return $modx->chunkCache;
    }

    private function _get_snippets()
    {
        global $modx;
        $rs = db()->select('*', '[+prefix+]site_snippets');
        $modx->snippetCache = [];
        while ($row = db()->getRow($rs)) {
            $name = $row['name'];
            $modx->snippetCache[$name] = $row['snippet'];
            $modx->snippetCache[$name . 'Props'] = $row['properties'];
        }
        return $modx->snippetCache;
    }

    private function _get_plugins()
    {
        global $modx;

        $rs = db()->select('*', '[+prefix+]site_plugins', 'disabled=0');
        $modx->pluginCache = [];
        while ($row = db()->getRow($rs)) {
            $name = db()->escape($row['name']);
            $modx->pluginCache[$name] = $row['plugincode'];
            $modx->pluginCache[$name . 'Props'] = $row['properties'];
        }
        return $modx->pluginCache;
    }

    private function _get_events()
    {
        $rs = db()->select(
            'sysevt.name as `evtname`, plugs.name as plgname',
            [
                '[+prefix+]system_eventnames sysevt',
                'INNER JOIN [+prefix+]site_plugin_events pe ON pe.evtid = sysevt.id',
                'INNER JOIN [+prefix+]site_plugins plugs ON plugs.id = pe.pluginid'
            ],
            'plugs.disabled=0',
            'sysevt.name,pe.priority'
        );
        $_ = ['$e = &$this->pluginEvent;'];
        $events = [];
        while ($row = db()->getRow($rs)) {
            $evtname = $row['evtname'];
            if (!isset($events[$evtname])) {
                $events[$evtname] = [$row['plgname']];
            } else {
                $events[$evtname][] = $row['plgname'];
            }
        }
        foreach ($events as $evtname => $pluginnames) {
            $_[] = sprintf(
                "\$e['%s'] = array('%s');",
                $evtname,
                implode(
                    "','",
                    str_replace(
                        ["\\", "'"],
                        ["\\\\", "\\'"],
                        $pluginnames
                    )
                )
            );
        }
        return implode("\n", $_) . "\n";
    }

    private function getFileList($dir, $pattern = '@\.*$@')
    {
        $dir = rtrim($dir, '/');
        $tmp = array_diff(scandir($dir), ['..', '.']);
        $files = [];
        foreach ($tmp as $val) {
            $files[] = $dir . '/' . $val;
        }

        $list = [];
        foreach ($files as $obj) {
            if (is_file($obj) && preg_match($pattern, $obj)) {
                $list[] = $obj;
            } elseif (is_dir($obj)) {
                $list[] = $obj;
                $_ = $this->getFileList($obj, $pattern);
                $list = array_merge($list, $_);
            }
        }
        return $list;
    }
}
