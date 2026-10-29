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
    // UTF-8 でない名前（EUC-JP のサーバーに FTP で置いたものなど）があっても一覧全体を返せるよう、
    // 不正なバイトは置き換え文字（U+FFFD）にする
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fbError($code, $message, $status = 400)
{
    fbSendJson(['error' => ['code' => $code, 'message' => $message]], $status);
}

/**
 * 名前が見つからないときの文言。一覧で置き換え文字（U+FFFD）になった名前は実体と一致せず操作できないため、その旨を返す
 */
function fbNotFoundMessage($name, $message)
{
    if (strpos((string) $name, "\u{FFFD}") !== false) {
        return '名前の文字コードが UTF-8 でないため、この画面では操作できません。FTP などで名前を付け直してください。';
    }
    return $message;
}

// 管理セッションは常に許可。ウェブユーザーのセッションは、システム設定
// 「ウェブユーザーのファイルブラウザ利用」(rb_webuser) が有効なときだけ許可する（既定は無効）。
if (!sessionv('mgrValidated')) {
    if (!sessionv('webValidated') || (int) $modx->config('rb_webuser') !== 1) {
        fbError('unauthorized', 'ログインセッションが確認できません。', 403);
    }
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
        fbActionRename($resolver, $modx, $type);
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
        fbError('not_found', fbNotFoundMessage($folder, '指定されたフォルダが見つかりません。'), 404);
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

        // 拡張子は、実際に保存する名前（無害化した後）で確かめる
        $safeName = $modx->sanitizeFilename($originalName);
        $dotPos = strrpos($safeName, '.');
        $ext = $dotPos === false ? '' : strtolower(substr($safeName, $dotPos + 1));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = ['name' => $originalName, 'message' => '許可されていない拡張子です。'];
            continue;
        }

        if ($uploadedFiles['size'][$i] > $maxSize) {
            $errors[] = ['name' => $originalName, 'message' => '許可されている最大サイズを超えています。'];
            continue;
        }

        // $modx->move_uploaded_file() は保存先の名前をもう一度無害化する（設定によっては括弧などが _ になる）。
        // 空きを確かめた名前と実際に保存される名前がずれて上書きしないよう、候補ごとに同じ無害化をかける
        $baseName = substr($safeName, 0, $dotPos);
        $extension = substr($safeName, $dotPos);
        $targetPath = null;
        for ($suffix = 0; $suffix <= 200; $suffix++) {
            $candidate = $suffix === 0 ? $safeName : $baseName . '(' . $suffix . ')' . $extension;
            $candidatePath = $modx->sanitizeUploadedFilename($dir['real'] . $candidate);
            if (!is_file($candidatePath)) {
                $targetPath = $candidatePath;
                break;
            }
        }
        if ($targetPath === null) {
            $errors[] = ['name' => $originalName, 'message' => '同じ名前のファイルが多すぎるため保存できません。'];
            continue;
        }
        $targetName = basename($targetPath);

        // 画像の縮小(image_limit_width)とパーミッションの設定は $modx->move_uploaded_file() が行う
        if (!$modx->move_uploaded_file($tmpPath, $targetPath)) {
            $errors[] = ['name' => $originalName, 'message' => '保存に失敗しました。'];
            continue;
        }

        // プラグインがファイルを移動・削除してもよいよう、サイズはイベントの前に取る
        $uploaded[] = [
            'name' => $targetName,
            'size' => filesize($targetPath),
        ];

        // invokeEvent() の第2引数は参照渡しなので、変数に入れてから渡す
        $eventParams = [
            'filepath' => $dir['real'],
            'filename' => $targetName,
        ];
        $modx->invokeEvent('OnFileBrowserUpload', $eventParams);
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

    $name = $modx->sanitizeFilename(postv('name', ''), false);
    if ($name === '') {
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

function fbActionRename(PathResolver $resolver, DocumentParser $modx, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', '指定されたフォルダが見つかりません。', 404);
    }

    // target は既にある名前を指すので、名前を変えずに 1 要素であることだけを確かめる
    $target = PathResolver::sanitizeSegment(postv('target', ''));
    $newName = $modx->sanitizeFilename(postv('newName', ''), false);
    if ($target === '' || $newName === '') {
        fbError('invalid_name', '不正な名前です。', 400);
    }

    $sourcePath = $dir['real'] . $target;
    if (!file_exists($sourcePath)) {
        fbError('not_found', fbNotFoundMessage($target, '対象が見つかりません。'), 404);
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
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, 'ファイルが見つかりません。')];
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
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, 'フォルダが見つかりません。')];
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
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, 'ファイルが見つかりません。')];
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
