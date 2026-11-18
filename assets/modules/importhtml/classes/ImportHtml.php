<?php
/**
 * HTML ファイルで作られたサイトを、文書としてまとめて取り込む
 *
 * temp/import/（無ければ assets/import/）の下に置いた HTML ファイルを、ディレクトリの
 * 構造のまま文書にする。ファイル名がエイリアス、<title> が文書名になる。
 * ディレクトリはフォルダの文書になり、中の index.html（または index.htm）がその本文になる。
 */
class ImportHtml
{
    const EXTENSIONS = ['html', 'htm', 'shtml'];
    const INDEX_FILES = ['index.html', 'index.htm'];

    /** @var int */
    private $moduleId;

    /** @var array */
    private $lang;

    /** @var array 取り込みの指定（フォームの値） */
    private $options = [];

    /** @var string[] この取り込みで作った文書の id => 元のファイルがあるディレクトリ（取り込み元からの相対。'' か 'blog/'） */
    private $created = [];

    /** @var int[] 取り込み元からのパス（拡張子なし。'about'・'blog'・'blog/post1'）=> 作った文書の id */
    private $pathToId = [];

    /** @var string[] 取り込みの経過（HTML の断片） */
    private $log = [];

    public function __construct($moduleId, array $lang)
    {
        $this->moduleId = (int) $moduleId;
        $this->lang = $lang;
    }

    /**
     * 管理画面の言語に合う言語ファイルを読む。無ければ英語を使う
     */
    public static function loadLang($language)
    {
        $dir = dirname(__DIR__) . '/lang/';
        $lang = include $dir . 'english.inc.php';
        if (preg_match('/^[\w\-]+$/', (string) $language) && is_file("{$dir}{$language}.inc.php")) {
            $lang = array_merge($lang, include "{$dir}{$language}.inc.php");
        }
        return $lang;
    }

    public function handle()
    {
        if (!$this->canImport()) {
            return $this->page('<p class="fail">' . hsc($this->text('no_permission')) . '</p>');
        }
        if (!is_post() || post_choicevar('importhtml', ['import'], '') !== 'import') {
            return $this->page($this->renderForm());
        }
        return $this->page($this->renderResult());
    }

    /**
     * サイトを移すときの作業なので、システム設定の権限を求める。
     * 取り込みは、公開の権限や文書グループによる制限を確かめずに文書を作るため、編集の担当者には使わせない
     */
    private function canImport()
    {
        return evo()->hasPermission('settings')
            && evo()->hasPermission('new_document')
            && evo()->hasPermission('save_document');
    }

    /**
     * ツリーを空にする指定は、ごみ箱を通さずに文書をすべて消し、ID も振り直す（戻せない）ので、管理者だけに許す
     */
    private function canReset()
    {
        return $this->canImport() && manager()->isAdmin();
    }

    private function text($key)
    {
        return $this->lang[$key] ?? $key;
    }

    private function moduleUrl()
    {
        return "index.php?a=112&id={$this->moduleId}";
    }

    private function page($body)
    {
        return '<h1>' . hsc($this->text('title')) . "</h1>\n"
            . '<div id="actions"><ul class="actionButtons"><li class="mutate"><a href="index.php?a=2">'
            . hsc(lang('cancel')) . "</a></li></ul></div>\n"
            . '<div class="section"><div class="sectionBody" id="importHtml">' . $body . "</div></div>\n";
    }

    private function importDir()
    {
        foreach (['temp/import/', 'assets/import/'] as $dir) {
            if (is_dir(MODX_BASE_PATH . $dir)) {
                return MODX_BASE_PATH . $dir;
            }
        }
        return '';
    }

    private function renderForm()
    {
        $siteName = hsc(config('site_name'));
        $canReset = $this->canReset();
        $html = [];
        $html[] = '<p>' . hsc($this->text('message')) . '</p>';
        if (!$this->importDir()) {
            $html[] = '<p class="fail">' . hsc($this->text('no_import_dir')) . '</p>';
        }
        $html[] = '<form action="' . hsc($this->moduleUrl()) . '" method="post" name="importFrm">';
        $html[] = '<input type="hidden" name="importhtml" value="import" />';
        $html[] = '<input type="hidden" name="parent" value="0" />';
        $html[] = '<table class="importhtml-settings">';
        $html[] = '<tr><th>' . hsc($this->text('parent')) . '</th>'
            . '<td><b id="parentName">0 (' . $siteName . ')</b><br />'
            . hsc($this->text('parent_message')) . '</td></tr>';
        $html[] = '<tr><th>' . hsc($this->text('maxtime')) . '</th>'
            . '<td><input type="number" name="maxtime" value="30" min="0" /><br />'
            . hsc($this->text('maxtime_message')) . '</td></tr>';
        if ($canReset) {
            $html[] = '<tr><th>' . hsc($this->text('reset_tree')) . '</th>'
                . '<td><label><input type="checkbox" id="importhtmlReset" name="reset" value="on" /> '
                . hsc($this->text('reset_tree_message')) . '</label></td></tr>';
        }
        $html[] = '<tr><th>' . hsc($this->text('target')) . '</th><td>'
            . '<label><input type="radio" name="object" value="body" checked="checked" /> '
            . hsc($this->text('target_body')) . '</label> '
            . '<label><input type="radio" name="object" value="all" /> '
            . hsc($this->text('target_all')) . '</label></td></tr>';
        $html[] = '<tr><th>' . hsc($this->text('convert_link')) . '</th>'
            . '<td><label><input type="checkbox" name="convert_link" value="on" /> '
            . hsc($this->text('convert_link_message')) . '</label></td></tr>';
        $html[] = '</table>';
        $html[] = '<ul class="actionButtons"><li><button type="submit" class="default">'
            . hsc($this->text('start')) . '</button></li></ul>';
        $html[] = '</form>';
        $html[] = '<style>'
            . '#importHtml .importhtml-settings th{text-align:left;vertical-align:top;white-space:nowrap;padding-right:20px;}'
            . '#importHtml .importhtml-settings td{padding-bottom:var(--space-sm,8px);}'
            . '</style>';
        // ツリーで文書を選ぶと、親の文書として受け取る（tree.php が main.setParent を呼ぶ）
        $html[] = '<script>(function () {'
            . 'if (window.tree) { window.tree.ca = "parent"; }'
            . 'window.setParent = function (id, name) {'
            . 'document.importFrm.parent.value = id;'
            . 'document.getElementById("parentName").textContent = id + " (" + name + ")";'
            . 'var reset = document.getElementById("importhtmlReset");'
            . 'if (reset) { reset.disabled = String(id) !== "0"; if (reset.disabled) { reset.checked = false; } }'
            . '};'
            . '})();</script>';
        return implode("\n", $html);
    }

    private function renderResult()
    {
        $parent = post_intvar('parent');
        $this->options = [
            'parent' => $parent,
            'maxtime' => post_uintvar('maxtime', 30),
            // ツリーを空にするのは、サイトの最上位へ取り込むときだけ
            'reset' => post_boolvar('reset') && $parent === 0 && $this->canReset(),
            'body_only' => post_choicevar('object', ['body', 'all'], 'body') === 'body',
            'convert_link' => post_boolvar('convert_link'),
        ];

        $html = $this->import();
        evo()->clearCache();

        $html .= '<ul class="actionButtons"><li><a href="' . hsc($this->moduleUrl()) . '">'
            . hsc($this->text('back')) . '</a></li></ul>';
        $html .= '<script>if (window.top.mainMenu && window.top.mainMenu.reloadtree) { window.top.mainMenu.reloadtree(); }</script>';
        return $html;
    }

    private function import()
    {
        $dir = $this->importDir();
        if (!$dir) {
            return '<p class="fail">' . hsc($this->text('no_import_dir')) . '</p>';
        }

        $parent = $this->options['parent'];
        if ($parent && !db()->getValue(db()->select('id', '[+prefix+]site_content', 'id=' . (int) $parent))) {
            return '<p class="fail">' . hsc($this->text('parent_not_found')) . '</p>';
        }

        @set_time_limit($this->options['maxtime']);
        $start = microtime(true);

        $files = $this->scan($dir);
        $count = $this->countFiles($files);

        if ($this->options['reset']) {
            $this->resetTree();
            $parent = 0;
        }

        $ok = true;
        if ($files) {
            if ($parent) {
                db()->update(['isfolder' => 1], '[+prefix+]site_content', 'id=' . (int) $parent);
            }
            $ok = $this->importFiles($parent, $dir, $files, '');
        }

        if ($ok && $this->options['convert_link']) {
            $this->convertLinks();
        }

        evo()->logEvent(0, 1, 'Imported HTML files as documents', 'Import HTML', [
            'dir' => $dir,
            'parent' => $parent,
            'reset' => $this->options['reset'],
            'created' => count($this->created),
            'completed' => $ok,
        ]);

        return '<p>' . sprintf(hsc($this->text('files_found')), $count) . '</p>'
            . '<div class="importhtml-log">' . implode("<br />\n", $this->log) . '</div>'
            . '<p>' . sprintf(hsc($this->text('time')), round(microtime(true) - $start, 3)) . '</p>';
    }

    /**
     * ディレクトリの中身を、ファイル名の配列（ディレクトリは名前をキーにした入れ子）で返す。
     * index.html（index.htm）を先頭、ディレクトリを最後に並べる
     */
    private function scan($dir)
    {
        $names = scandir($dir);
        if ($names === false) {
            $this->log[] = $this->fail($this->text('no_open_dir') . ' ' . $dir);
            return [];
        }
        $index = [];
        $files = [];
        $dirs = [];
        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (is_dir($dir . $name)) {
                $dirs[$name] = $this->scan("{$dir}{$name}/");
                continue;
            }
            if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
                continue;
            }
            if (in_array($name, self::INDEX_FILES, true)) {
                $index[] = $name;
            } else {
                $files[] = $name;
            }
        }
        return ['files' => array_merge($index, $files), 'dirs' => $dirs];
    }

    private function countFiles(array $tree)
    {
        if (!$tree) {
            return 0;
        }
        $count = count($tree['files']);
        foreach ($tree['dirs'] as $sub) {
            $count += $this->countFiles($sub);
        }
        return $count;
    }

    private function resetTree()
    {
        db()->delete('[+prefix+]document_groups');
        db()->delete('[+prefix+]site_tmplvar_contentvalues');
        db()->delete('[+prefix+]site_content');
        db()->query(sprintf('ALTER TABLE %s AUTO_INCREMENT = 1', evo()->getFullTableName('site_content')));
        db()->update(['setting_value' => ''], '[+prefix+]system_settings', "setting_name='error_page'");
    }

    /**
     * @return bool 途中で DB への書き込みに失敗したら false（そこで止める）
     */
    private function importFiles($parent, $dir, array $tree, $relDir)
    {
        $isRoot = $relDir === '';
        foreach ($tree['files'] as $filename) {
            // ディレクトリの index は、フォルダの文書の本文として取り込み済み
            if (!$isRoot && in_array($filename, self::INDEX_FILES, true)) {
                continue;
            }
            $alias = pathinfo($filename, PATHINFO_FILENAME);
            $fields = $this->fieldsFromFile($dir . $filename, $filename, $alias);
            $fields['parent'] = $parent;
            $fields['isfolder'] = 0;
            $fields['menuindex'] = $alias === 'index' ? 0 : 2;
            $id = $this->insert($fields, $relDir . $filename, $relDir, [$relDir . $alias]);
            if (!$id) {
                return false;
            }
            if ($isRoot && $filename === 'index.html' && $this->options['reset']) {
                db()->update(['setting_value' => $id], '[+prefix+]system_settings', "setting_name='site_start'");
            }
        }

        foreach ($tree['dirs'] as $name => $sub) {
            $fields = null;
            foreach (self::INDEX_FILES as $index) {
                if (in_array($index, $sub['files'], true)) {
                    $fields = $this->fieldsFromFile("{$dir}{$name}/{$index}", $index, $name);
                    break;
                }
            }
            if ($fields === null) {
                $fields = $this->baseFields($name);
                $fields['pagetitle'] = $fields['alias'];
                $fields['content'] = '';
                $fields['createdon'] = $fields['editedon'] = request_time();
                $fields['hidemenu'] = 1;
            }
            $fields['parent'] = $parent;
            $fields['isfolder'] = 1;
            $fields['menuindex'] = 1;
            $id = $this->insert($fields, "{$relDir}{$name}/", "{$relDir}{$name}/", [$relDir . $name, "{$relDir}{$name}/index"]);
            if (!$id || !$this->importFiles($id, "{$dir}{$name}/", $sub, "{$relDir}{$name}/")) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $relDir 元のファイルがあるディレクトリ（相対リンクを解く起点）
     * @param string[] $paths この文書を指すパス（リンクの変換で引く）
     */
    private function insert(array $fields, $label, $relDir, array $paths)
    {
        $id = db()->insert(db()->escape($fields), '[+prefix+]site_content');
        if (!$id) {
            $this->log[] = hsc($label) . ' - ' . $this->fail(
                $this->text('failed_db_error') . ' ' . db()->getLastError()
            );
            return 0;
        }
        $this->created[(int) $id] = $relDir;
        foreach ($paths as $path) {
            $this->pathToId[$path] = (int) $id;
        }
        $this->log[] = hsc($label) . ' - <span class="success">' . hsc($this->text('success')) . '</span>';
        return (int) $id;
    }

    private function fail($message)
    {
        return '<span class="fail">' . hsc($this->text('failed')) . '</span> ' . hsc($message);
    }

    private function baseFields($alias)
    {
        return [
            'type' => 'document',
            'contentType' => 'text/html',
            'alias' => evo()->stripAlias($alias),
            'published' => config('publish_default'),
            'richtext' => $this->options['body_only'] ? 1 : 0,
            // ファイルまるごと取り込むときは、HTML 自体がページなのでテンプレートを使わない
            'template' => $this->options['body_only'] ? config('default_template') : 0,
            'searchable' => config('search_default'),
            'cacheable' => config('cache_default'),
            'createdby' => evo()->getLoginUserID(),
        ];
    }

    private function fieldsFromFile($path, $filename, $alias)
    {
        $src = file_get_contents($path);
        if ($src === false) {
            $this->log[] = $this->fail($this->text('no_retrieve_file') . ' ' . $path);
            $src = '';
        }
        $src = mb_convert_encoding(
            $src,
            config('modx_charset'),
            'UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP,ASCII'
        );

        $pagetitle = '';
        if (preg_match('@<title>(.*)</title>@is', $src, $m)) {
            $pagetitle = trim(str_replace('[*pagetitle*]', '', $m[1]));
        }
        if ($pagetitle === '' || strpos($pagetitle, 'index.htm') !== false) {
            $pagetitle = $alias;
        }

        $description = '';
        if (preg_match('@<meta[^>]+"description"[^>]+content=[\'"](.*)[\'"].+>@i', $src, $m)) {
            $description = str_replace('[*description*]', '', $m[1]);
        }

        if ($this->options['body_only'] && preg_match('@<body[^>]*>(.*)</body>@is', $src, $m)) {
            $content = $m[1];
        } else {
            $content = preg_replace(
                '/(<meta[^>]+charset\s*=)[^>"\'=]+(.+>)/i',
                '$1' . config('modx_charset') . '$2',
                $src
            );
            $content = preg_replace('@<title>.*</title>@is', '<title>[*pagetitle*]</title>', $content);
        }
        // 取り込んだ本文の中の [*content*] が自分自身を呼んで循環しないよう崩しておく
        $content = trim(str_replace('[*content*]', '[ *content* ]', $content));

        $mtime = filemtime($path) ?: request_time();
        return $this->baseFields($alias) + [
            'pagetitle' => $pagetitle,
            'longtitle' => $pagetitle,
            'description' => $description,
            'content' => $content,
            'createdon' => $mtime,
            'editedon' => $mtime,
        ];
    }

    /**
     * 取り込んだ文書の中の、サイト内のページへのリンクを [~id~] に置き換える。
     * 取り込んだファイルへのリンクは、取り込みで作った文書に結びつける（親の文書の下へ取り込んでも解ける）。
     * それ以外は、サイトのエイリアスのパスとして探す
     */
    private function convertLinks()
    {
        if (!$this->created) {
            return;
        }
        $siteStart = (int) db()->getValue(db()->select(
            'setting_value',
            '[+prefix+]system_settings',
            "setting_name='site_start'"
        ));
        $rs = db()->select(
            'id, content',
            '[+prefix+]site_content',
            sprintf('id IN (%s) AND deleted!=1', implode(',', array_keys($this->created)))
        );
        while ($row = db()->getRow($rs)) {
            if (!preg_match_all('@<a href="([^"]*)"@', $row['content'], $m)) {
                continue;
            }
            $search = [];
            $replace = [];
            foreach (array_unique($m[1]) as $href) {
                $tag = $this->linkTag($href, $this->created[(int) $row['id']], $siteStart);
                if ($tag !== '') {
                    $search[] = "<a href=\"{$href}\"";
                    $replace[] = "<a href=\"{$tag}\"";
                }
            }
            if (!$search) {
                continue;
            }
            db()->update(
                db()->escape(['content' => str_replace($search, $replace, $row['content'])]),
                '[+prefix+]site_content',
                'id=' . (int) $row['id']
            );
        }
    }

    /**
     * @param string $relDir リンクを書いた文書の元のファイルがあるディレクトリ（相対リンクの起点）
     * @return string 置き換えるリンク。サイト内のページに当たらなければ ''
     */
    private function linkTag($href, $relDir, $siteStart)
    {
        if (!preg_match('@^([^?#]*)(.*)$@s', $href, $m) || $m[1] === '') {
            return '';
        }
        [, $path, $suffix] = $m;

        if (strpos($path, MODX_SITE_URL) === 0) {
            $path = substr($path, strlen(MODX_SITE_URL));
        } elseif (preg_match('@^([a-z][a-z0-9+.\-]*:|//)@i', $path)) {
            return '';
        } elseif ($path[0] === '/') {
            if (strpos($path, MODX_BASE_URL) !== 0) {
                return '';
            }
            $path = substr($path, strlen(MODX_BASE_URL));
        } else {
            $path = $relDir . $path;
        }

        $path = $this->normalizePath($path);
        if ($path === null) {
            return '';
        }
        // ディレクトリへのリンクは、その index を指す
        $path = preg_replace('@\.(html?|shtml)$@', '', $path);
        if ($path === '' || substr($path, -1) === '/') {
            $path .= 'index';
        }

        $id = $this->pathToId[$path] ?? 0;
        if (!$id) {
            $alias = preg_replace('@(^|/)index$@', '', $path);
            $id = $alias === '' ? $siteStart : (int) evo()->getIdFromAlias($alias);
        }
        if (!$id) {
            return '';
        }
        return ($id === $siteStart ? '[(site_url)]' : "[~{$id}~]") . $suffix;
    }

    /**
     * . と .. を解く。取り込み元より上へ出るときは null
     */
    private function normalizePath($path)
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (!$parts) {
                    return null;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        $normalized = implode('/', $parts);
        return substr($path, -1) === '/' ? "{$normalized}/" : $normalized;
    }
}
