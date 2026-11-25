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
            // 旧ファイルブラウザ。1.2 系が配布していた 56 ファイル（後継は manager/media/browser/evo/）
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
                    'frmactualfolder.html',
                    'frmcreatefolder.html',
                    'frmfolders.html',
                    'frmresourceslist.html',
                    'frmresourcetype.html',
                    'frmupload.html',
                    'images/Folder.gif',
                    'images/FolderOpened.gif',
                    'images/FolderOpened32.gif',
                    'images/FolderUp.gif',
                    'images/delete.gif',
                    'images/edit.gif',
                    'images/editImage.gif',
                    'images/folder64.png',
                    'images/spacer.gif',
                    'js/common.js',
                    'js/fckxml.js',
                    'seturl.js',
                ],
            ],
        ];
    }

    /**
     * グループのファイルを消す。2 回目は、消すものが無いので何もしない
     *
     * @return array{removed: string[], failed: string[], kept: bool} removed・failed は MODX_BASE_PATH からの相対のパス。
     *         kept は、利用者が足したファイルなどで、フォルダーが残ったか
     */
    public static function remove(string $group): array
    {
        $def = self::groups()[$group];
        $result = ['removed' => [], 'failed' => [], 'kept' => false];
        foreach ($def['files'] as $file) {
            $path = $def['dir'] . $file;
            if (!is_file(MODX_BASE_PATH . $path)) {
                continue;
            }
            if (@unlink(MODX_BASE_PATH . $path)) {
                $result['removed'][] = $path;
            } else {
                $result['failed'][] = $path;
            }
        }
        if ($result['failed']) {
            return $result;
        }
        // 深いフォルダーから、空のものだけ消す（rmdir は空でなければ失敗する）
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
        foreach ($dirs as $d) {
            $path = MODX_BASE_PATH . $def['dir'] . ($d === '' ? '' : $d . '/');
            if (is_dir($path) && !@rmdir($path)) {
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
            foreach ($def['files'] as $file) {
                if (is_file(MODX_BASE_PATH . $def['dir'] . $file)) {
                    $dirs[] = $def['dir'];
                    break;
                }
            }
        }
        return $dirs;
    }
}
