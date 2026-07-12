<?php

/**
 * ファイルブラウザ新JSON API。
 *
 * GET  action=list   (type, folder, light?)                   -> {folders:[{name,hasChildren}], files:[{name,size,mtime,url,path,thumb,width,height}]}
 *                     light=1はツリー展開用(filesはname/pathのみ。画像寸法等の収集を省く)
 * GET  action=thumb  (type, folder, file)                     -> 画像バイナリ
 * POST action=upload (type, folder, files[])                  -> {uploaded:[{name,size,url}], errors:[{name,message}]}
 * POST action=mkdir  (type, folder, name)                      -> {name}
 * POST action=rename (type, folder, target, newName)           -> {name}
 * POST action=delete (type, folder, files[], folders[])        -> {deleted:[...], errors:[{name,message}]}
 * POST action=move   (type, folder, files[], dest)              -> {moved:[...], errors:[{name,message}]}
 *
 * 読み取り系(list/thumb)はセッション検証のみ。書き込み系はCSRFトークン必須。
 */

define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);

$self = 'manager/media/browser/evo/api.php';
$base_path = str_replace($self, '', str_replace('\\', '/', __FILE__));
require_once $base_path . 'manager/includes/document.parser.class.inc.php';
require_once __DIR__ . '/lib/PathResolver.php';
require_once __DIR__ . '/lib/BrowserConfig.php';
require_once __DIR__ . '/lib/ThumbnailService.php';

$modx = new DocumentParser();
$modx->getSettings();

function fbSendJson($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fbError($code, $message, $status = 400)
{
    fbSendJson(['error' => ['code' => $code, 'message' => $message]], $status);
}

if (!isset($_SESSION['mgrValidated']) && !isset($_SESSION['webValidated'])) {
    fbError('unauthorized', 'ログインセッションが確認できません。', 403);
}

$config = new BrowserConfig($modx);
if (!$config->isBrowserEnabled()) {
    fbError('forbidden', 'ファイルブラウザの利用が許可されていません。', 403);
}

$type = anyv('type', 'images');
if (!$config->isValidType($type)) {
    fbError('invalid_type', '不正なファイル種別です。', 400);
}

$resolver = new PathResolver($config->baseDir());
$action = anyv('action', '');

switch ($action) {
    case 'list':
        fbActionList($resolver, $config, $type);
        break;

    case 'thumb':
        fbActionThumb($resolver, $type);
        break;

    case 'upload':
        checkCsrfToken();
        fbActionUpload($resolver, $config, $modx, $type);
        break;

    case 'mkdir':
        checkCsrfToken();
        fbActionMkdir($resolver, $modx, $type);
        break;

    case 'rename':
        checkCsrfToken();
        fbActionRename($resolver, $type);
        break;

    case 'delete':
        checkCsrfToken();
        fbActionDelete($resolver, $type);
        break;

    case 'move':
        checkCsrfToken();
        fbActionMove($resolver, $type);
        break;

    default:
        fbError('invalid_action', '不明なactionです。', 400);
}

function fbActionList(PathResolver $resolver, BrowserConfig $config, $type)
{
    $folder = getv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', '指定されたフォルダが見つかりません。', 404);
    }

    $entries = @scandir($dir['real']);
    if ($entries === false) {
        fbError('not_found', 'フォルダを読み取れません。', 404);
    }

    $folders = [];
    $files = [];
    $baseUrl = $config->baseUrl();
    $urlPrefix = $config->urlPrefix();
    $allowedExt = $config->allowedExtensions($type);
    // ツリー展開用の軽量モード: filesはname/pathのみ(getimagesize等の重い処理を省く)
    $light = anyv('light') === '1';

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) {
            continue;
        }

        $fullPath = $dir['real'] . $entry;
        $relPath = $dir['rel'] . '/' . $entry;

        if (is_dir($fullPath)) {
            $folders[] = ['name' => $entry, 'hasChildren' => fbDirHasSubdirs($fullPath)];
            continue;
        }

        // typeに応じた許可拡張子のみを一覧に含める(旧mcpukのGetFoldersAndFilesと同様、
        // imagesブラウザにindex.html等の非対象ファイルが混ざらないようにする)
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }

        if ($light) {
            $files[] = [
                'name' => $entry,
                'path' => ($urlPrefix !== '' ? $urlPrefix . '/' : '') . $relPath,
            ];
            continue;
        }

        $isSvg = ($ext === 'svg');
        $isRaster = ThumbnailService::isRasterImageExtension($ext);

        $encodedRelPath = implode('/', array_map('rawurlencode', explode('/', $relPath)));
        // url: ブラウザ内表示用の絶対URL / path: SetUrl(TV値等)互換の相対パス(旧mcpuk同等)
        $fileUrl = $baseUrl . $encodedRelPath;
        $filePath = ($urlPrefix !== '' ? $urlPrefix . '/' : '') . $relPath;

        $width = null;
        $height = null;
        if ($isRaster) {
            $dimensions = @getimagesize($fullPath);
            if ($dimensions) {
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
        }

        // 'api': フロントがapiUrl基準でthumb URLを組み立てる(モーダル時の相対パス差異を吸収)
        $thumbUrl = null;
        if ($isRaster) {
            $thumbUrl = 'api';
        } elseif ($isSvg) {
            $thumbUrl = $fileUrl;
        }

        $files[] = [
            'name' => $entry,
            'size' => filesize($fullPath),
            'mtime' => filemtime($fullPath),
            'url' => $fileUrl,
            'path' => $filePath,
            'thumb' => $thumbUrl,
            'width' => $width,
            'height' => $height,
        ];
    }

    usort($folders, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    usort($files, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

    fbSendJson(['folder' => $folder, 'folders' => $folders, 'files' => $files]);
}

function fbDirHasSubdirs($dir)
{
    $entries = @scandir($dir);
    if ($entries === false) {
        return false;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) {
            continue;
        }
        if (is_dir($dir . '/' . $entry)) {
            return true;
        }
    }

    return false;
}

function fbActionThumb(PathResolver $resolver, $type)
{
    $folder = getv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', '指定されたフォルダが見つかりません。', 404);
    }

    $file = $resolver->resolveFile($dir['real'], getv('file', ''));
    if ($file === null) {
        fbError('not_found', '指定されたファイルが見つかりません。', 404);
    }

    $thumbs = new ThumbnailService(MODX_BASE_PATH . 'temp/thumbs/');
    $cacheFile = $thumbs->getOrCreate($file);

    if ($cacheFile === null) {
        fbError('not_an_image', 'サムネイルを生成できないファイルです。', 404);
    }

    $thumbs->outputWithCacheHeaders($cacheFile);
    exit;
}

function fbActionUpload(PathResolver $resolver, BrowserConfig $config, DocumentParser $modx, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', '指定されたフォルダが見つかりません。', 404);
    }

    $uploadedFiles = $_FILES['files'] ?? null;
    if (!$uploadedFiles || !is_array($uploadedFiles['name'])) {
        fbError('no_files', 'アップロードするファイルがありません。', 400);
    }

    $allowedExt = $config->allowedExtensions($type);
    $maxSize = $config->maxUploadSize();
    $filePermissions = octdec($modx->config('new_file_permissions') ?: '0644');

    $uploaded = [];
    $errors = [];
    $count = count($uploadedFiles['name']);

    for ($i = 0; $i < $count; $i++) {
        $originalName = $uploadedFiles['name'][$i];
        $tmpPath = $uploadedFiles['tmp_name'][$i];
        $errorCode = $uploadedFiles['error'][$i];

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = ['name' => $originalName, 'message' => 'アップロードに失敗しました(エラーコード ' . $errorCode . ')。'];
            continue;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = ['name' => $originalName, 'message' => '許可されていない拡張子です。'];
            continue;
        }

        if ($uploadedFiles['size'][$i] > $maxSize) {
            $errors[] = ['name' => $originalName, 'message' => '許可されている最大サイズを超えています。'];
            continue;
        }

        $sanitizedPath = $modx->sanitizeUploadedFilename('/' . PathResolver::sanitizeSegment(basename($originalName)));
        $safeName = basename($sanitizedPath);
        if ($safeName === '') {
            $errors[] = ['name' => $originalName, 'message' => '不正なファイル名です。'];
            continue;
        }

        $targetName = $safeName;
        $targetPath = $dir['real'] . $targetName;
        $nameInfo = pathinfo($safeName);
        $baseName = $nameInfo['filename'];
        $extension = isset($nameInfo['extension']) ? '.' . $nameInfo['extension'] : '';
        $suffix = 1;
        while (is_file($targetPath) && $suffix <= 200) {
            $targetName = $baseName . '(' . $suffix . ')' . $extension;
            $targetPath = $dir['real'] . $targetName;
            $suffix++;
        }

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            $errors[] = ['name' => $originalName, 'message' => '保存に失敗しました。'];
            continue;
        }
        @chmod($targetPath, $filePermissions);

        $uploaded[] = [
            'name' => $targetName,
            'size' => filesize($targetPath),
        ];
    }

    fbSendJson(['uploaded' => $uploaded, 'errors' => $errors]);
}

function fbActionMkdir(PathResolver $resolver, DocumentParser $modx, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', '指定されたフォルダが見つかりません。', 404);
    }

    $name = PathResolver::sanitizeSegment(postv('name', ''));
    if ($name === '' || strpos($name, '.') === 0) {
        fbError('invalid_name', '不正なフォルダ名です。', 400);
    }

    $target = $dir['real'] . $name;
    if (file_exists($target)) {
        fbError('already_exists', '同名のフォルダまたはファイルが既に存在します。', 409);
    }

    $folderPermissions = octdec($modx->config('new_folder_permissions') ?: '0755');
    if (!mkdir($target, $folderPermissions)) {
        fbError('mkdir_failed', 'フォルダを作成できませんでした。', 500);
    }
    @chmod($target, $folderPermissions);

    fbSendJson(['name' => $name]);
}

function fbActionRename(PathResolver $resolver, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', '指定されたフォルダが見つかりません。', 404);
    }

    $target = PathResolver::sanitizeSegment(postv('target', ''));
    $newName = PathResolver::sanitizeSegment(postv('newName', ''));
    if ($target === '' || $newName === '' || strpos($newName, '.') === 0) {
        fbError('invalid_name', '不正な名前です。', 400);
    }

    $sourcePath = $dir['real'] . $target;
    if (!file_exists($sourcePath)) {
        fbError('not_found', '対象が見つかりません。', 404);
    }

    $destPath = $dir['real'] . $newName;
    if (file_exists($destPath)) {
        fbError('already_exists', '同名のフォルダまたはファイルが既に存在します。', 409);
    }

    if (!rename($sourcePath, $destPath)) {
        fbError('rename_failed', '名前を変更できませんでした。', 500);
    }

    fbSendJson(['name' => $newName]);
}

function fbActionDelete(PathResolver $resolver, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', '指定されたフォルダが見つかりません。', 404);
    }

    $files = (array) postv('files', []);
    $folders = (array) postv('folders', []);

    $deleted = [];
    $errors = [];

    foreach ($files as $name) {
        $name = PathResolver::sanitizeSegment($name);
        $path = $dir['real'] . $name;
        if ($name === '' || !is_file($path)) {
            $errors[] = ['name' => $name, 'message' => 'ファイルが見つかりません。'];
            continue;
        }
        if (unlink($path)) {
            $deleted[] = $name;
        } else {
            $errors[] = ['name' => $name, 'message' => '削除できませんでした。'];
        }
    }

    foreach ($folders as $name) {
        $name = PathResolver::sanitizeSegment($name);
        $path = $dir['real'] . $name;
        if ($name === '' || !is_dir($path)) {
            $errors[] = ['name' => $name, 'message' => 'フォルダが見つかりません。'];
            continue;
        }
        if (fbDeleteTree($path)) {
            $deleted[] = $name;
        } else {
            $errors[] = ['name' => $name, 'message' => '削除できませんでした。'];
        }
    }

    fbSendJson(['deleted' => $deleted, 'errors' => $errors]);
}

function fbActionMove(PathResolver $resolver, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', '指定されたフォルダが見つかりません。', 404);
    }

    $destFolder = postv('dest', '');
    $destDir = $resolver->resolveDir($type, $destFolder);
    if ($destDir === null) {
        fbError('not_found', '移動先フォルダが見つかりません。', 404);
    }

    if (rtrim($destDir['real'], '/') === rtrim($dir['real'], '/')) {
        fbError('same_folder', '移動先が現在のフォルダと同じです。', 400);
    }

    $files = (array) postv('files', []);
    $moved = [];
    $errors = [];

    foreach ($files as $name) {
        $name = PathResolver::sanitizeSegment($name);
        $sourcePath = $dir['real'] . $name;
        $destPath = $destDir['real'] . $name;

        if ($name === '' || !is_file($sourcePath)) {
            $errors[] = ['name' => $name, 'message' => 'ファイルが見つかりません。'];
            continue;
        }
        if (file_exists($destPath)) {
            $errors[] = ['name' => $name, 'message' => '移動先に同名のファイルが既に存在します。'];
            continue;
        }
        if (rename($sourcePath, $destPath)) {
            $moved[] = $name;
        } else {
            $errors[] = ['name' => $name, 'message' => '移動できませんでした。'];
        }
    }

    fbSendJson(['moved' => $moved, 'errors' => $errors]);
}

function fbDeleteTree($path)
{
    $entries = @scandir($path);
    if ($entries === false) {
        return false;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $path . '/' . $entry;
        if (is_dir($full)) {
            if (!fbDeleteTree($full)) {
                return false;
            }
        } elseif (!unlink($full)) {
            return false;
        }
    }

    return rmdir($path);
}
