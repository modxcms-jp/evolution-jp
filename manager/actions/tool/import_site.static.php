<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('import_static')) {
    alert()->setError(3);
    alert()->dumpError();
}

// Files to upload
$allowedfiles = ['html', 'htm', 'shtml', 'xml'];
?>
<script type="text/javascript">
    parent.tree.ca = "parent";

    function setParent(pId, pName) {
        document.importFrm.parent.value = pId;
        document.getElementById('parentName').innerHTML = pId + " (" + pName + ")";
        document.getElementById('reset').disabled = pId != 0;
    }
</script>

<h1><?= $_lang['import_site_html'] ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate"><a href="#"
                onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                    alt="icons_cancel"
                    src="<?= $_style["icons_cancel"] ?>" /> <?= $_lang['cancel'] ?></a></li>
    </ul>
</div>

<div class="section">
    <div class="sectionBody">
        <?php

        if (!postv('import')) {
            echo "<p>" . $_lang['import_site_message'] . "</p>";
        ?>

            <fieldset style="padding:10px;border:1px solid #ccc;background-color:#fff;">
                <legend><?= $_lang['import_site'] ?></legend>
                <form action="index.php" method="post" name="importFrm">
                    <input type="hidden" name="import" value="import" />
                    <input type="hidden" name="a" value="95" />
                    <input type="hidden" name="parent" value="0" />
                    <table border="0" cellspacing="0" cellpadding="2">
                        <tr>
                            <td nowrap="nowrap"><b><?= $_lang['import_parent_resource'] ?></b></td>
                            <td>&nbsp;</td>
                            <td><b><span id="parentName">0 (<?= evo()->config('site_name') ?>)</span></b>
                            </td>
                        </tr>
                        <tr>
                            <td nowrap="nowrap" valign="top"><b><?= $_lang['import_site_maxtime'] ?></b>
                            </td>
                            <td>&nbsp;</td>
                            <td><input type="text" name="maxtime" value="30" />
                                <br />
                                <?= $_lang['import_site_maxtime_message'] ?>
                            </td>
                        </tr>
                        <tr>
                            <td nowrap="nowrap" valign="top"><b><?= $_lang['import_site.static.php1'] ?></b>
                            </td>
                            <td>&nbsp;</td>
                            <td><input type="checkbox" id="reset" name="reset" value="on" />
                                <br />
                                <?= $_lang['import_site.static.php2'] ?>
                            </td>
                        </tr>
                        <tr>
                            <td nowrap="nowrap" valign="top"><b><?= $_lang['import_site.static.php3'] ?></b>
                            </td>
                            <td>&nbsp;</td>
                            <td>
                                <label><input
                                        type="radio"
                                        name="object"
                                        value="body"
                                        checked="checked" /> <?= $_lang['import_site.static.php4'] ?>
                                </label>
                                <label><input
                                        type="radio"
                                        name="object"
                                        value="all" /> <?= $_lang['import_site.static.php5'] ?></label>
                                <br />
                            </td>
                        </tr>
                        <tr>
                            <td nowrap="nowrap" valign="top"><b><?= $_lang['a95_convert_link'] ?></b></td>
                            <td>&nbsp;</td>
                            <td><label><input type="checkbox" id="convert_link" name="convert_link" value="on" />
                                    <?= $_lang['a95_convert_link_msg'] ?></label>
                            </td>
                        </tr>
                    </table>
                    <ul class="actionButtons">
                        <li><a href="#" class="default" onclick="document.importFrm.submit();"><img
                                    src="<?= $_style["icons_save"] ?>" /> <?= $_lang['import_site_start'] ?>
                            </a></li>
                    </ul>
                </form>
            </fieldset>
        <?php
        } else {
            run();
            evo()->clearCache();
        ?>
            <ul class="actionButtons">
                <li><a href="#" onclick="document.location.href='index.php?a=2';"><img
                            src="<?= $_style["icons_close"] ?>" /> <?= $_lang['close'] ?></a></li>
            </ul>
            <script type="text/javascript">
                top.mainMenu.reloadtree();
                parent.tree.ca = 'open';
            </script>
        <?php
        }
        ?>
    </div>
</div>

<?php
function run()
{
    global $_lang;

    $output = '';

    $maxtime = postv('maxtime', 120);
    if (!is_numeric($maxtime)) {
        $maxtime = 30;
    }
    @set_time_limit($maxtime);

    $mtime = microtime();
    $mtime = explode(' ', $mtime);
    $mtime = $mtime[1] + $mtime[0];
    $importstart = $mtime;

    if (postv('reset') === 'on') {
        db()->delete('[+prefix+]site_content');
        db()->query(sprintf('ALTER TABLE %s AUTO_INCREMENT = 1', evo()->getFullTableName('site_content')));
        db()->update(['setting_value' => ''], '[+prefix+]system_settings', "setting_name='error_page'");
    }

    $parent = (int)postv('parent', 0);

    if (is_dir(MODX_BASE_PATH . 'temp/import')) {
        $filedir = MODX_BASE_PATH . 'temp/import/';
    } elseif (is_dir(MODX_BASE_PATH . 'assets/import')) {
        $filedir = MODX_BASE_PATH . 'assets/import/';
    }

    $filesfound = 0;

    $files = pop_index(getFiles($filedir));

    // no. of files to import
    $output .= sprintf('<p>%s %s</p>', lang('import_files_found'), $filesfound);

    // import files
    if (0 < count($files)) {
        db()->update(['isfolder' => 1], '[+prefix+]site_content', "id={$parent}");
        importFiles($parent, $filedir, $files, 'root');
    }

    $mtime = microtime();
    $mtime = explode(' ', $mtime);
    $mtime = $mtime[1] + $mtime[0];
    $importend = $mtime;
    $totaltime = ($importend - $importstart);
    $output .= sprintf('<p>%s %s</p>', $_lang['import_site_time'], round($totaltime, 3));

    if (postv('convert_link') === 'on') {
        convertLink();
    }

    return $output;
}

function importFiles($parent, $filedir, $files, $mode)
{
    global $_lang, $allowedfiles;
    global $search_default, $cache_default, $publish_default;

    $createdby = evo()->getLoginUserID();
    if (!is_array($files)) {
        return;
    }
    if (postv('object') === 'all') {
        evo()->config('*default_template', 0);
        $richtext = '0';
    } else {
        $richtext = '1';
    }

    foreach ($files as $alias => $value) {
        if (is_array($value)) {
            // create folder
            if (substr($alias, 0, 2) === 'd#') {
                $alias = substr($alias, 2);
            }
            echo "<span>{$alias}/</span>";
            $field = [];
            $field['type'] = 'document';
            $field['contentType'] = 'text/html';
            $field['published'] = $publish_default;
            $field['parent'] = $parent;
            $field['alias'] = evo()->stripAlias($alias);
            $field['richtext'] = $richtext;
            $field['template'] = evo()->config('default_template');
            $field['searchable'] = $search_default;
            $field['cacheable'] = $cache_default;
            $field['createdby'] = $createdby;
            $field['isfolder'] = 1;
            $field['menuindex'] = 1;
            $find = false;
            foreach (['index.html', 'index.htm'] as $filename) {
                $filepath = $filedir . $alias . '/' . $filename;
                if ($find !== false || !is_file($filepath)) {
                    continue;
                }

                $file = getFileContent($filepath);
                [$pagetitle, $content, $description] = treatContent($file, $filename, $alias);
                $field['pagetitle'] = $pagetitle;
                $field['longtitle'] = $pagetitle;
                $field['description'] = $description;
                $field['content'] = db()->escape($content);
                $date = filemtime($filepath);
                $field['createdon'] = $date;
                $field['editedon'] = $date;
                $newid = db()->insert($field, '[+prefix+]site_content');
                if (!$newid) {
                    $vs = [
                        $_lang['import_site_failed'],
                        $_lang['import_site_failed_db_error'],
                        db()->getLastError()
                    ];
                    echo vsprintf(' - <span class="fail">%s</span> %s %s', $vs);
                    exit;
                }
                $find = true;
                echo sprintf(
                    ' - <span class="success">%s</span><br />' . "\n",
                    $_lang['import_site_success']
                );
                importFiles($newid, $filedir . $alias . '/', $value, 'sub');
            }
            if ($find === false) {
                $date = request_time();
                $field['pagetitle'] = $field['alias'];
                $field['content'] = '';
                $field['createdon'] = $date;
                $field['editedon'] = $date;
                $field['hidemenu'] = '1';
                $newid = db()->insert($field, '[+prefix+]site_content');
                if ($newid) {
                    echo sprintf(' - <span class="success">%s</span><br />', $_lang['import_site_success']) . "\n";
                    importFiles($newid, $filedir . $alias . '/', $value, 'sub');
                } else {
                    $vs = [
                        $_lang['import_site_failed'],
                        $_lang['import_site_failed_db_error'],
                        db()->getLastError()
                    ];
                    echo vsprintf('<span class="fail">%s</span> %s %s', $vs);
                    exit;
                }
            }
        } else {
            // create document
            if ($mode === 'sub' && $value === 'index.html') {
                continue;
            }
            $filename = $value;
            $fparts = explode('.', $value);
            $alias = $fparts[0];
            $ext = (count($fparts) > 1) ? $fparts[count($fparts) - 1] : "";
            echo "<span>{$filename}</span>";

            if (!in_array($ext, $allowedfiles)) {
                echo sprintf(' - <span class="fail">%s</span><br />', $_lang['import_site_skip']) . "\n";
            } else {
                $filepath = $filedir . $filename;
                $file = getFileContent($filepath);
                [$pagetitle, $content, $description] = treatContent($file, $filename, $alias);

                $date = filemtime($filepath);
                $field = [];
                $field['type'] = 'document';
                $field['contentType'] = 'text/html';
                $field['pagetitle'] = $pagetitle;
                $field['longtitle'] = $pagetitle;
                $field['description'] = $description;
                $field['alias'] = evo()->stripAlias($alias);
                $field['published'] = $publish_default;
                $field['parent'] = $parent;
                $field['content'] = db()->escape($content);
                $field['richtext'] = $richtext;
                $field['template'] = evo()->config('default_template');
                $field['searchable'] = $search_default;
                $field['cacheable'] = $cache_default;
                $field['createdby'] = $createdby;
                $field['createdon'] = $date;
                $field['editedon'] = $date;
                $field['isfolder'] = 0;
                $field['menuindex'] = ($alias == 'index') ? 0 : 2;
                $newid = db()->insert($field, '[+prefix+]site_content');
                if (!$newid) {
                    $vs = [
                        $_lang['import_site_failed'],
                        $_lang['import_site_failed_db_error'],
                        db()->getLastError()
                    ];
                    echo vsprintf('<span class="fail">%s</span> %s %s', $vs);
                    exit;
                }
                echo sprintf(' - <span class="success">%s</span><br />', $_lang['import_site_success']) . "\n";

                $is_site_start = false;
                if ($filename === 'index.html') {
                    $is_site_start = true;
                }
                if ($is_site_start == true && postv('reset') === 'on') {
                    db()->update(
                        "setting_value=" . $newid,
                        '[+prefix+]system_settings',
                        "setting_name='site_start'"
                    );
                    db()->update(
                        'menuindex=0',
                        '[+prefix+]site_content',
                        "id='" . $newid . "'"
                    );
                }
            }
        }
    }
}

function getFiles($directory, $listing = [], $count = 0)
{
    global $_lang;
    global $filesfound;
    $c = $count;
    if ($files = scandir($directory)) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_dir($directory . $file)) {
                $count = -1;
                $listing['d#' . $file] = getFiles(
                    sprintf(
                        '%s%s/',
                        $directory,
                        $file
                    ),
                    [],
                    $count + 1
                );
            } elseif (strpos($file, '.htm') !== false) {
                $listing[$c] = $file;
                $c++;
                $filesfound++;
            }
        }
    } else {
        echo sprintf(
            '<p><span class="fail">%s</span> %s %s</p>',
            $_lang['import_site_failed'],
            $_lang['import_site_failed_no_open_dir'],
            $directory
        );
    }
    return ($listing);
}

function getFileContent($filepath)
{
    global $_lang;
    // get the file
    if (!$buffer = file_get_contents($filepath)) {
        $vs = [$_lang['import_site_failed'], $_lang['import_site_failed_no_retrieve_file'], $filepath];
        echo vsprintf('<p><span class="fail">%s</span> %s %s</p>', $vs);
    } else {
        return $buffer;
    }
    return '';
}

function pop_index($array)
{
    $new_array = [];
    foreach ($array as $k => $v) {
        if ($v !== 'index.html' && $v !== 'index.htm') {
            $new_array[$k] = $v;
        } else {
            array_unshift($new_array, $v);
        }
    }
    foreach ($array as $k => $v) {
        if (is_array($v)) {
            $new_array[$k] = $v;
        }
    }
    return $new_array;
}

function treatContent($src, $filename, $alias)
{
    $src = mb_convert_encoding($src, evo()->config('modx_charset'), 'UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP,ASCII');

    if (preg_match("@<title>(.*)</title>@i", $src, $matches)) {
        $pagetitle = ($matches[1] !== '') ? $matches[1] : $filename;
        $pagetitle = str_replace('[*pagetitle*]', '', $pagetitle);
    } else {
        $pagetitle = $alias;
    }
    if (!$pagetitle || strpos($pagetitle, 'index.htm') !== false) {
        $pagetitle = $alias;
    }

    if (preg_match('@<meta[^>]+"description"[^>]+content=[\'"](.*)[\'"].+>@i', $src, $matches)) {
        $description = ($matches[1] !== '') ? $matches[1] : $filename;
        $description = str_replace('[*description*]', '', $description);
    } else {
        $description = '';
    }

    if ((preg_match("@<body[^>]*>(.*)[^<]+</body>@is", $src, $matches)) && postv('object') == 'body') {
        $content = $matches[1];
    } else {
        $content = $src;
        $s = '/(<meta[^>]+charset\s*=)[^>"\'=]+(.+>)/i';
        $r = '$1' . evo()->config('modx_charset') . '$2';
        $content = preg_replace($s, $r, $content);
        $content = preg_replace('@<title>.*</title>@i', "<title>[*pagetitle*]</title>", $content);
    }
    $content = str_replace('[*content*]', '[ *content* ]', $content);
    $content = trim($content);
    $pagetitle = db()->escape($pagetitle);
    return [$pagetitle, $content, $description];
}

function convertLink()
{
    $rs = db()->select('*', '[+prefix+]site_content', 'deletedon!=1');
    $alias = [];
    $linkList = [];
    while ($row = db()->getRow($rs)) {
        $id = $row['id'];
        $_ = explode('<a href="', $row['content']);
        $i = 0;
        $s = [];
        $r = [];
        foreach ($_ as $v) {
            if (strpos($v, '"') !== false) {
                $v = substr($v, 0, strpos($v, '"'));
            } else {
                continue;
            }

            $bv = $v;
            if (isset($linkList[$bv])) {
                $rv = $linkList[$bv];
            } else {
                switch ($v) {
                    case '/':
                    case MODX_SITE_URL:
                    case MODX_SITE_URL . 'index.html':
                    case MODX_SITE_URL . 'index.htm':
                        $v = '[(site_url)]';
                        break;
                    default:
                        if (substr($v, -11) === '/index.html') {
                            $v = substr($v, 0, -11);
                        } elseif (substr($v, -10) === '/index.htm') {
                            $v = substr($v, 0, -10);
                        } elseif (substr($v, -5) === '.html') {
                            $v = substr($v, 0, -5);
                        } elseif (substr($v, -4) === '.htm') {
                            $v = substr($v, 0, -4);
                        }

                        if (substr($v, 0, strlen(MODX_BASE_URL)) === MODX_BASE_URL) {
                            $v = substr($v, strlen(MODX_BASE_URL));
                        } elseif (substr($v, 0, strlen(MODX_SITE_URL)) === MODX_SITE_URL) {
                            $v = substr($v, strlen(MODX_SITE_URL));
                        }
                }

                $v = trim($v, '/');
                if (isset($alias[$v])) {
                    $docid = $alias[$v];
                } else {
                    $docid = $alias[$v] = evo()->getIdFromAlias($v);
                }

                if ($docid) {
                    if ($docid == evo()->config('site_start')) {
                        $rv = '[(site_url)]';
                    } else {
                        $rv = "[~{$docid}~]";
                    }
                } else {
                    $rv = false;
                }
            }
            if ($rv) {
                $s[$i] = sprintf('<a href="%s"', $bv);
                $r[$i] = sprintf('<a href="%s"', $rv);
            }
            if (!isset($linkList[$bv])) {
                $linkList[$bv] = $rv;
            }
            $i++;
        }
        db()->update(
            [
                'content' => db()->escape(str_replace($s, $r, $row['content']))
            ],
            '[+prefix+]site_content',
            sprintf("id='%s'", $row['id'])
        );
    }
}
