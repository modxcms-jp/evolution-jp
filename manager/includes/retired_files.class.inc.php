<?php

/**
 * 配布から外したファイルの一覧と、その削除。マイグレーションの削除と、設定の確認の画面の「残っている」の判定が、同じ一覧を使う。
 *
 * 一覧に入れるのは、過去の版が配布していたファイルだけ（利用者が足したファイルは入れない）。パスは MODX_BASE_PATH からの相対で、
 * 固定の文字列だけを書く。消すのは一覧のファイルと、空になったフォルダーだけ。利用者が足したファイルがあるフォルダーは残す。
 * グループは足せるが、配布していた一覧は変えない（マイグレーションが同じ結果になるように）。
 */
class EvoRetiredFiles
{
    /**
     * @return array<string, array{dir: string, files: string[]}> グループ名 => フォルダーと、その下の配布していたファイル
     */
    public static function groups(): array
    {
        return [
            // 旧ファイルブラウザ。1.0.0J〜1.2.6J の全リリースが配布したファイル（全タグの和集合の 179 件。後継は manager/media/browser/evo/）
            'mcpuk' => [
                'dir' => 'manager/media/browser/mcpuk/',
                'files' => [
                    'browse.php',
                    'browser.css',
                    'browser.html',
                    'browser.html.inc',
                    'browser.php',
                    'connectors/Commands/Base.php',
                    'connectors/Commands/CreateFolder.php',
                    'connectors/Commands/DeleteFile.php',
                    'connectors/Commands/DeleteFolder.php',
                    'connectors/Commands/FileUpload.php',
                    'connectors/Commands/GetFolders.php',
                    'connectors/Commands/GetFoldersAndFiles.php',
                    'connectors/Commands/GetUploadProgress.php',
                    'connectors/Commands/RenameFile.php',
                    'connectors/Commands/RenameFolder.php',
                    'connectors/Commands/Thumbnail.php',
                    'connectors/Commands/helpers/ConnectorXmlBuilder.php',
                    'connectors/Commands/helpers/iconlookup.php',
                    'connectors/ConnectorConfigBuilder.php',
                    'connectors/ConnectorKernel.php',
                    'connectors/ConnectorRequest.php',
                    'connectors/Docs/license.txt',
                    'connectors/config.php',
                    'connectors/connector.php',
                    'connectors/ht.access',
                    'connectors/images/binary.png',
                    'connectors/images/doc.png',
                    'connectors/images/empty.png',
                    'connectors/images/flash.png',
                    'connectors/images/html.png',
                    'connectors/images/image.png',
                    'connectors/images/info.png',
                    'connectors/images/pdf.png',
                    'connectors/images/postscript.png',
                    'connectors/images/sound.png',
                    'connectors/images/spreadsheet.png',
                    'connectors/images/tar.png',
                    'connectors/images/video.png',
                    'connectors/php/Auth/Default.php',
                    'connectors/php/Commands/CreateFolder.php',
                    'connectors/php/Commands/DeleteFile.php',
                    'connectors/php/Commands/DeleteFolder.php',
                    'connectors/php/Commands/FileUpload.php',
                    'connectors/php/Commands/GetFolders.php',
                    'connectors/php/Commands/GetFoldersAndFiles.php',
                    'connectors/php/Commands/GetUploadProgress.php',
                    'connectors/php/Commands/RenameFile.php',
                    'connectors/php/Commands/RenameFolder.php',
                    'connectors/php/Commands/Thumbnail.php',
                    'connectors/php/Commands/helpers/iconlookup.php',
                    'connectors/php/Docs/AuthenticationHandlers.txt',
                    'connectors/php/Docs/license.txt',
                    'connectors/php/Docs/readme.txt',
                    'connectors/php/config.php',
                    'connectors/php/connector.php',
                    'connectors/php/dtd/iso-lat1.ent',
                    'connectors/php/ht.access',
                    'connectors/php/images/ascii.jpg',
                    'connectors/php/images/binary.jpg',
                    'connectors/php/images/binary.png',
                    'connectors/php/images/doc.png',
                    'connectors/php/images/document2.jpg',
                    'connectors/php/images/document2.png',
                    'connectors/php/images/email.jpg',
                    'connectors/php/images/email.png',
                    'connectors/php/images/empty.jpg',
                    'connectors/php/images/empty.png',
                    'connectors/php/images/flash.png',
                    'connectors/php/images/html.jpg',
                    'connectors/php/images/html.png',
                    'connectors/php/images/image.jpg',
                    'connectors/php/images/image.png',
                    'connectors/php/images/info.jpg',
                    'connectors/php/images/info.png',
                    'connectors/php/images/kmplot.jpg',
                    'connectors/php/images/kmplot.png',
                    'connectors/php/images/kmultiple.jpg',
                    'connectors/php/images/kmultiple.png',
                    'connectors/php/images/pdf.jpg',
                    'connectors/php/images/pdf.png',
                    'connectors/php/images/php.jpg',
                    'connectors/php/images/postscript.jpg',
                    'connectors/php/images/postscript.png',
                    'connectors/php/images/real_doc.jpg',
                    'connectors/php/images/real_doc.png',
                    'connectors/php/images/sound.jpg',
                    'connectors/php/images/sound.png',
                    'connectors/php/images/spreadsheet.jpg',
                    'connectors/php/images/spreadsheet.png',
                    'connectors/php/images/tar.jpg',
                    'connectors/php/images/tar.png',
                    'connectors/php/images/video.jpg',
                    'connectors/php/images/video.png',
                    'frmactualfolder.html',
                    'frmcreatefolder.html',
                    'frmfolders.html',
                    'frmimageeditor.html',
                    'frmresourceslist.html',
                    'frmresourcetype.html',
                    'frmupload.html',
                    'images/2x2.gif',
                    'images/2x2_w.gif',
                    'images/ButtonArrow.gif',
                    'images/Folder.gif',
                    'images/Folder32.gif',
                    'images/Folder96.jpg',
                    'images/FolderOpened.gif',
                    'images/FolderOpened32.gif',
                    'images/FolderUp.gif',
                    'images/close.gif',
                    'images/delete.gif',
                    'images/edit.gif',
                    'images/editImage.gif',
                    'images/folder64.png',
                    'images/icons/32/ai.gif',
                    'images/icons/32/avi.gif',
                    'images/icons/32/bmp.gif',
                    'images/icons/32/cs.gif',
                    'images/icons/32/default.icon.gif',
                    'images/icons/32/dll.gif',
                    'images/icons/32/doc.gif',
                    'images/icons/32/exe.gif',
                    'images/icons/32/fla.gif',
                    'images/icons/32/gif.gif',
                    'images/icons/32/htm.gif',
                    'images/icons/32/html.gif',
                    'images/icons/32/jpg.gif',
                    'images/icons/32/js.gif',
                    'images/icons/32/mdb.gif',
                    'images/icons/32/mp3.gif',
                    'images/icons/32/pdf.gif',
                    'images/icons/32/ppt.gif',
                    'images/icons/32/rdp.gif',
                    'images/icons/32/swf.gif',
                    'images/icons/32/swt.gif',
                    'images/icons/32/txt.gif',
                    'images/icons/32/vsd.gif',
                    'images/icons/32/xls.gif',
                    'images/icons/32/xml.gif',
                    'images/icons/32/zip.gif',
                    'images/icons/ai.gif',
                    'images/icons/avi.gif',
                    'images/icons/bmp.gif',
                    'images/icons/cs.gif',
                    'images/icons/default.icon.gif',
                    'images/icons/dll.gif',
                    'images/icons/doc.gif',
                    'images/icons/exe.gif',
                    'images/icons/fla.gif',
                    'images/icons/gif.gif',
                    'images/icons/htm.gif',
                    'images/icons/html.gif',
                    'images/icons/jpg.gif',
                    'images/icons/js.gif',
                    'images/icons/mdb.gif',
                    'images/icons/mp3.gif',
                    'images/icons/pdf.gif',
                    'images/icons/ppt.gif',
                    'images/icons/rdp.gif',
                    'images/icons/swf.gif',
                    'images/icons/swt.gif',
                    'images/icons/txt.gif',
                    'images/icons/vsd.gif',
                    'images/icons/xls.gif',
                    'images/icons/xml.gif',
                    'images/icons/zip.gif',
                    'images/spacer.gif',
                    'images/toolbar/close.gif',
                    'images/toolbar/crop.gif',
                    'images/toolbar/flip.gif',
                    'images/toolbar/resize.gif',
                    'images/toolbar/rotate.gif',
                    'images/toolbar/save.gif',
                    'js/EditorContent.js',
                    'js/common.js',
                    'js/fckxml.js',
                    'js/wz_jsgraphics.js',
                    'seturl.js',
                    'seturl_js_tinymce.inc',
                ],
            ],
        ];
    }

    /**
     * グループのファイルを消す。2 回目は、消すものが無いので何もしない
     *
     * @return array{removed: string[], failed: string[], kept: bool} removed・failed は MODX_BASE_PATH からの相対のパス。
     *         kept は、消せなかったファイルや利用者が足したファイルなどで、フォルダーが残ったか
     */
    public static function remove(string $group): array
    {
        $def = self::groups()[$group];
        $result = ['removed' => [], 'failed' => [], 'kept' => false];
        foreach (self::existing($def) as $file) {
            $path = $def['dir'] . $file;
            if (@unlink(MODX_BASE_PATH . $path)) {
                $result['removed'][] = $path;
            } else {
                $result['failed'][] = $path;
            }
        }
        if ($result['failed']) {
            $result['kept'] = true;
            return $result;
        }
        // 深いフォルダーから、空のものだけ消す（rmdir は空でなければ失敗する）。リンクのフォルダーには触らない
        $dirs = [];
        foreach ($def['files'] as $file) {
            for ($d = dirname($file); $d !== '.'; $d = dirname($d)) {
                $dirs[$d] = true;
            }
        }
        $dirs = array_keys($dirs);
        usort($dirs, static function ($a, $b) {
            return substr_count($b, '/') <=> substr_count($a, '/');
        });
        $dirs[] = '';
        clearstatcache();
        foreach ($dirs as $d) {
            $path = MODX_BASE_PATH . $def['dir'] . $d;
            if (!is_dir($path) || self::viaLink($def, $d)) {
                continue;
            }
            if (!@rmdir($path)) {
                $result['kept'] = true;
            }
        }
        return $result;
    }

    /**
     * 配布していたファイルが今も残っているグループのフォルダー（MODX_BASE_PATH からの相対）の一覧
     *
     * @return string[]
     */
    public static function remainingDirs(): array
    {
        $dirs = [];
        foreach (self::groups() as $def) {
            if (self::existing($def)) {
                $dirs[] = $def['dir'];
            }
        }
        return $dirs;
    }

    /**
     * グループのうち、今あるファイル（グループのフォルダーの下の相対パス）。remove() と remainingDirs() が同じ対象を数える。
     * フォルダーやファイルがシンボリックリンクのものは含めない（リンク先は利用者が選んだ置き場所なので、触らない）
     *
     * @return string[]
     */
    private static function existing(array $def): array
    {
        clearstatcache();
        $root = MODX_BASE_PATH . $def['dir'];
        if (!is_dir($root)) {
            return [];
        }
        $found = [];
        foreach ($def['files'] as $file) {
            $dir = dirname($file);
            if (self::viaLink($def, $dir === '.' ? '' : $dir)) {
                continue;
            }
            if (is_file($root . $file) && !is_link($root . $file)) {
                $found[] = $file;
            }
        }
        return $found;
    }

    /**
     * グループのフォルダーの下の相対のフォルダー（空なら、グループのフォルダー自身）が、シンボリックリンクか、
     * リンクの下にあるか。ファイルの対象と空フォルダーの削除が、この 1 つの判定を使う
     */
    private static function viaLink(array $def, string $relDir): bool
    {
        $path = rtrim(MODX_BASE_PATH . $def['dir'], '/');
        if (is_link($path)) {
            return true;
        }
        foreach ($relDir === '' ? [] : explode('/', $relDir) as $part) {
            $path .= '/' . $part;
            if (is_link($path)) {
                return true;
            }
        }
        return false;
    }
}
