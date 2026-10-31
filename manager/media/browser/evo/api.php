<?php

/**
 * ファイルブラウザ新JSON API。
 *
 * GET  action=list   (type, folder, light?)                   -> {folders:[{name,hasChildren}], files:[{name,size,mtime,url,path,thumb,width,height}]}
 *                     light=1はツリー展開用(filesはname/pathのみ。画像寸法等の収集を省く)
 *                     urlはパスの要素ごとにURLエンコード済み。pathは生のパスで、% # ? だけをエンコード。nameは生の名前
 * GET  action=thumb  (type, folder, file)                     -> 画像バイナリ
 * POST action=upload (type, folder, files[])                  -> {uploaded:[{name,size,url}], errors:[{name,message}]}
 * POST action=mkdir  (type, folder, name)                      -> {name}
 * POST action=rename (type, folder, target, newName)           -> {name}
 * POST action=delete (type, folder, files[], folders[])        -> {deleted:[...], errors:[{name,message}]}
 * POST action=move   (type, folder, files[], dest)              -> {moved:[...], errors:[{name,message}]}
 * POST action=copy   (type, folder, files[], dest)              -> {copied:[{from,name}], errors:[{name,message}]}
 *                     同名があれば 名前(1).拡張子 のように連番を付ける。上書きはしない。ファイルのみ(フォルダは errors)
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
        return lang('fb_err_bad_encoding');
    }
    return $message;
}

// 管理セッションは常に許可。ウェブユーザーのセッションは、システム設定
// 「ウェブユーザーのファイルブラウザ利用」(rb_webuser) が有効なときだけ許可する（既定は無効）。
if (!sessionv('mgrValidated')) {
    if (!sessionv('webValidated') || (int) $modx->config('rb_webuser') !== 1) {
        fbError('unauthorized', lang('fb_err_unauthorized'), 403);
    }
}

$config = new BrowserConfig($modx);
if (!$config->isBrowserEnabled()) {
    fbError('forbidden', lang('fb_err_forbidden'), 403);
}

$type = anyv('type', 'images');
if (!$config->isValidType($type)) {
    fbError('invalid_type', lang('fb_err_invalid_type'), 400);
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
        fbActionRename($resolver, $config, $modx, $type);
        break;

    case 'delete':
        checkCsrfToken();
        fbActionDelete($resolver, $type);
        break;

    case 'move':
        checkCsrfToken();
        fbActionMove($resolver, $type);
        break;

    case 'copy':
        checkCsrfToken();
        fbActionCopy($resolver, $config, $modx, $type);
        break;

    default:
        fbError('invalid_action', lang('fb_err_invalid_action'), 400);
}

function fbActionList(PathResolver $resolver, BrowserConfig $config, $type)
{
    $folder = getv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', fbNotFoundMessage($folder, lang('fb_err_folder_not_found')), 404);
    }

    $entries = @scandir($dir['real']);
    if ($entries === false) {
        fbError('not_found', lang('fb_err_folder_unreadable'), 404);
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
        // 壊れたシンボリックリンクは使えないので一覧に出さない
        if (is_link($fullPath) && !file_exists($fullPath)) {
            continue;
        }
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

        // path: SetUrl(TV値等)へ渡す相対パス(旧mcpuk同等)。読める形のまま渡し、
        // FTP などで置かれた名前の % # ? だけは URL が切れないようエンコードする(% を最初に置き換える)
        $filePath = ($urlPrefix !== '' ? $urlPrefix . '/' : '')
            . str_replace(['%', '#', '?'], ['%25', '%23', '%3F'], $relPath);

        if ($light) {
            $files[] = [
                'name' => $entry,
                'path' => $filePath,
            ];
            continue;
        }

        $isSvg = ($ext === 'svg');
        $isRaster = ThumbnailService::isRasterImageExtension($ext);

        // url: ブラウザ内表示用の絶対URL。パスの要素ごとにエンコードする
        $fileUrl = $baseUrl . implode('/', array_map('rawurlencode', explode('/', $relPath)));

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
            'size' => @filesize($fullPath),
            'mtime' => @filemtime($fullPath),
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
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $file = $resolver->resolveFile($dir['real'], getv('file', ''));
    if ($file === null) {
        fbError('not_found', lang('fb_err_file_not_found'), 404);
    }

    $thumbs = new ThumbnailService(MODX_BASE_PATH . 'temp/thumbs/');
    $cacheFile = $thumbs->getOrCreate($file);

    if ($cacheFile === null) {
        fbError('not_an_image', lang('fb_err_not_an_image'), 404);
    }

    $thumbs->outputWithCacheHeaders($cacheFile);
    exit;
}

function fbActionUpload(PathResolver $resolver, BrowserConfig $config, DocumentParser $modx, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $uploadedFiles = $_FILES['files'] ?? null;
    if (!$uploadedFiles || !is_array($uploadedFiles['name'])) {
        fbError('no_files', lang('fb_err_no_files'), 400);
    }

    $maxSize = $config->maxUploadSize();

    $uploaded = [];
    $errors = [];
    $count = count($uploadedFiles['name']);

    for ($i = 0; $i < $count; $i++) {
        $originalName = $uploadedFiles['name'][$i];
        $tmpPath = $uploadedFiles['tmp_name'][$i];
        $errorCode = $uploadedFiles['error'][$i];

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = ['name' => $originalName, 'message' => sprintf(lang('fb_err_upload_failed'), $errorCode)];
            continue;
        }

        // 拡張子は、実際に保存する名前（無害化した後）で確かめる
        $safeName = $modx->sanitizeFilename($originalName);
        if (!$config->isAllowedFilename($type, $safeName)) {
            $errors[] = ['name' => $originalName, 'message' => lang('fb_err_extension_not_allowed')];
            continue;
        }

        if ($uploadedFiles['size'][$i] > $maxSize) {
            $errors[] = ['name' => $originalName, 'message' => lang('fb_err_too_large')];
            continue;
        }

        $targetPath = fbFindFreePath($modx, $dir['real'], $safeName);
        if ($targetPath === null) {
            $errors[] = ['name' => $originalName, 'message' => lang('fb_err_too_many_same_name')];
            continue;
        }
        // basename() は PHP 7.4 ではロケールによって先頭のマルチバイト文字を落とすため使わない
        $targetName = substr($targetPath, strrpos($targetPath, '/') + 1);

        // 画像の縮小(image_limit_width)とパーミッションの設定は $modx->move_uploaded_file() が行う
        if (!$modx->move_uploaded_file($tmpPath, $targetPath)) {
            $errors[] = ['name' => $originalName, 'message' => lang('fb_err_save_failed')];
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

/**
 * 保存先のフォルダで空いているファイルのフルパスを探す。同名があれば 名前(1).拡張子 のように連番を付ける。
 * 見つからなければ null。
 *
 * $modx->move_uploaded_file() は保存先の名前をもう一度無害化する（設定によっては括弧などが _ になる）。
 * 空きを確かめた名前と実際に保存される名前がずれて上書きしないよう、候補ごとに同じ無害化をかける
 */
function fbFindFreePath(DocumentParser $modx, $dirReal, $safeName)
{
    $dotPos = strrpos($safeName, '.');
    $baseName = $dotPos === false ? $safeName : substr($safeName, 0, $dotPos);
    $extension = $dotPos === false ? '' : substr($safeName, $dotPos);
    for ($suffix = 0; $suffix <= 200; $suffix++) {
        $candidate = $suffix === 0 ? $safeName : $baseName . '(' . $suffix . ')' . $extension;
        $candidatePath = $modx->sanitizeUploadedFilename($dirReal . $candidate);
        // 壊れたシンボリックリンクや同名のフォルダも「使用中」として次の番号へ進む
        if (!file_exists($candidatePath) && !is_link($candidatePath)) {
            return $candidatePath;
        }
    }
    return null;
}

function fbActionMkdir(PathResolver $resolver, DocumentParser $modx, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $name = $modx->sanitizeFilename(postv('name', ''), false);
    if ($name === '') {
        fbError('invalid_name', lang('fb_err_invalid_folder_name'), 400);
    }

    $target = $dir['real'] . $name;
    if (file_exists($target)) {
        fbError('already_exists', lang('fb_err_already_exists'), 409);
    }

    $folderPermissions = octdec($modx->config('new_folder_permissions') ?: '0755');
    if (!mkdir($target, $folderPermissions)) {
        fbError('mkdir_failed', lang('fb_err_mkdir_failed'), 500);
    }
    @chmod($target, $folderPermissions);

    fbSendJson(['name' => $name]);
}

function fbActionRename(PathResolver $resolver, BrowserConfig $config, DocumentParser $modx, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    // target は既にある名前を指すので、名前を変えずに 1 要素であることだけを確かめる
    $target = PathResolver::sanitizeSegment(postv('target', ''));
    $newName = $modx->sanitizeFilename(postv('newName', ''), false);
    if ($target === '' || $newName === '') {
        fbError('invalid_name', lang('fb_err_invalid_name'), 400);
    }

    $sourcePath = $dir['real'] . $target;
    if (!file_exists($sourcePath)) {
        fbError('not_found', fbNotFoundMessage($target, lang('fb_err_target_not_found')), 404);
    }
    // ファイルの名前は、アップロードと同じ拡張子の許可リストで確かめる（フォルダーは確かめない）
    if (!is_dir($sourcePath) && !$config->isAllowedFilename($type, $newName)) {
        fbError('invalid_extension', lang('fb_err_extension_not_allowed'), 400);
    }

    $destPath = $dir['real'] . $newName;
    if (file_exists($destPath)) {
        fbError('already_exists', lang('fb_err_already_exists'), 409);
    }

    if (!rename($sourcePath, $destPath)) {
        fbError('rename_failed', lang('fb_err_rename_failed'), 500);
    }

    fbSendJson(['name' => $newName]);
}

function fbActionDelete(PathResolver $resolver, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $files = (array) postv('files', []);
    $folders = (array) postv('folders', []);

    $deleted = [];
    $errors = [];

    foreach ($files as $name) {
        $name = PathResolver::sanitizeSegment($name);
        $path = $dir['real'] . $name;
        if ($name === '' || !is_file($path)) {
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, lang('fb_err_entry_file_not_found'))];
            continue;
        }
        if (@unlink($path)) {
            $deleted[] = $name;
        } else {
            fbLogDeleteFailure($path);
            $errors[] = ['name' => $name, 'message' => lang('fb_err_delete_failed')];
        }
    }

    foreach ($folders as $name) {
        $name = PathResolver::sanitizeSegment($name);
        $path = $dir['real'] . $name;
        if ($name === '' || !is_dir($path)) {
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, lang('fb_err_entry_folder_not_found'))];
            continue;
        }
        if (fbDeleteTree($path)) {
            $deleted[] = $name;
        } else {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_delete_partial')];
        }
    }

    fbSendJson(['deleted' => $deleted, 'errors' => $errors]);
}

function fbActionMove(PathResolver $resolver, $type)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($type, $folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $destFolder = postv('dest', '');
    $destDir = $resolver->resolveDir($type, $destFolder);
    if ($destDir === null) {
        fbError('not_found', lang('fb_err_dest_not_found'), 404);
    }

    if (rtrim($destDir['real'], '/') === rtrim($dir['real'], '/')) {
        fbError('same_folder', lang('fb_err_same_folder'), 400);
    }

    $files = (array) postv('files', []);
    $moved = [];
    $errors = [];

    foreach ($files as $name) {
        $name = PathResolver::sanitizeSegment($name);
        $sourcePath = $dir['real'] . $name;
        $destPath = $destDir['real'] . $name;

        if ($name === '' || !is_file($sourcePath)) {
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, lang('fb_err_entry_file_not_found'))];
            continue;
        }
        if (file_exists($destPath)) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_dest_exists')];
            continue;
        }
        if (rename($sourcePath, $destPath)) {
            $moved[] = $name;
        } else {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_move_failed')];
        }
    }

    fbSendJson(['moved' => $moved, 'errors' => $errors]);
}

function fbActionCopy(PathResolver $resolver, BrowserConfig $config, DocumentParser $modx, $type)
{
    $dir = $resolver->resolveDir($type, anyv('folder', ''));
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    // 同じフォルダへの複製も許す（連番の名前になる）
    $destDir = $resolver->resolveDir($type, postv('dest', ''));
    if ($destDir === null) {
        fbError('not_found', lang('fb_err_dest_not_found'), 404);
    }

    $files = (array) postv('files', []);
    $copied = [];
    $errors = [];

    foreach ($files as $name) {
        $name = PathResolver::sanitizeSegment($name);

        // シンボリックリンクの先の実体が許可された場所の中にあるものだけを複製する
        $sourcePath = $resolver->resolveFile($dir['real'], $name);
        if ($sourcePath === null) {
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, lang('fb_err_entry_file_not_found'))];
            continue;
        }

        // 複製先の名前も、アップロード・名前の変更と同じ拡張子の許可リストで確かめる
        $safeName = $modx->sanitizeFilename($name);
        if ($safeName === '' || !$config->isAllowedFilename($type, $safeName)) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_extension_not_allowed')];
            continue;
        }

        // 空きを確かめてから作るまでの間に同名ができても上書きしないよう、排他的に作成（x）して、使われていたら次の候補へ
        $targetPath = null;
        $out = false;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $targetPath = fbFindFreePath($modx, $destDir['real'], $safeName);
            if ($targetPath === null) {
                break;
            }
            $out = @fopen($targetPath, 'xb');
            if ($out !== false || (!file_exists($targetPath) && !is_link($targetPath))) {
                break;
            }
        }
        if ($targetPath === null) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_too_many_same_name')];
            continue;
        }

        $in = $out === false ? false : @fopen($sourcePath, 'rb');
        $expectedSize = @filesize($sourcePath);
        $copiedSize = $in !== false ? @stream_copy_to_stream($in, $out) : false;
        // 書き込みの途中で失敗した（容量不足など）ときも、元のサイズと一致しなければ失敗として扱う
        $ok = $copiedSize !== false && $expectedSize !== false && $copiedSize === $expectedSize;
        if ($in !== false) {
            fclose($in);
        }
        if ($out !== false) {
            fclose($out);
        }
        if (!$ok) {
            if ($out !== false) {
                @unlink($targetPath);
            }
            evo()->logEvent(0, 3, 'Failed to copy a file in the file browser', 'File browser', [
                'from' => $sourcePath,
                'to' => $targetPath,
            ]);
            $errors[] = ['name' => $name, 'message' => lang('fb_err_copy_failed')];
            continue;
        }
        // パーミッションを変えられない環境でも、複製できていれば成功として扱う
        @chmod($targetPath, $modx->newFilePermissions());

        // basename() は PHP 7.4 ではロケールによって先頭のマルチバイト文字を落とすため使わない
        $copied[] = ['from' => $name, 'name' => substr($targetPath, strrpos($targetPath, '/') + 1)];
    }

    fbSendJson(['copied' => $copied, 'errors' => $errors]);
}

function fbLogDeleteFailure($path)
{
    evo()->logEvent(0, 3, 'Failed to delete an entry in the file browser', 'File browser', [
        'path' => $path,
    ]);
}

// 最後まで消して、消せないものがあれば false を返す。シンボリックリンクはリンクだけを消し、先へは入らない
function fbDeleteTree($path)
{
    if (is_link($path)) {
        if (@unlink($path)) {
            return true;
        }
        fbLogDeleteFailure($path);
        return false;
    }

    $entries = @scandir($path);
    if ($entries === false) {
        fbLogDeleteFailure($path);
        return false;
    }

    $ok = true;
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $path . '/' . $entry;
        if (is_link($full) || !is_dir($full)) {
            if (!@unlink($full)) {
                fbLogDeleteFailure($full);
                $ok = false;
            }
        } elseif (!fbDeleteTree($full)) {
            $ok = false;
        }
    }

    if ($ok && !@rmdir($path)) {
        fbLogDeleteFailure($path);
        $ok = false;
    }

    return $ok;
}
