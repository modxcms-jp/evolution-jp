<?php

/**
 * ファイルブラウザ新JSON API。
 *
 * scope=manage ならメニューの「ファイル管理」(管理モード)、それ以外はピッカー。範囲は下の入口の 1 行で決まり、権限は要求のたびに
 * 範囲のクラス(ManageScope / PickerScope)が確かめる。範囲ごとの操作は BrowserScope::actions() の表に載り、表に無い action は 400。
 * type はピッカーだけが付けるパラメーター(images / media / files)。管理モードは付けない。
 *
 * GET  action=list   (type, folder, foldersOnly?)             -> {folder, folders:[{name,hasChildren,mtime}], files:[...], writable}
 *                     ピッカーの files: {name,size,mtime,url,path,thumb,width,height}。thumb はラスター画像なら 'api'(action=thumb で取る)、svg なら url、それ以外は null。
 *                     urlはパスの要素ごとにURLエンコード済み。pathは生のパスで、% # ? だけをエンコード。nameは生の名前
 *                     管理モードの files: {name,size,mtime,url,writable}。ブラウザが <img> で表示できる画像なら image: true と thumb(ラスターは 'api'、svg は url、それ以外は null)、ラスターなら width,height。
 *                     動画・音声(BrowserConfig::mediaKindOf())で url が null でなければ media: 'video' / 'audio' を付ける(url が null のときは付けない)。
 *                     全ファイル(ドットで始まる名前も)を出す。一覧ではサムネイルを作らず、action=thumb で作る。urlはサイトのルートの外なら null
 *                     writable は今のフォルダに書き込めるか(管理モードの files の writable はファイルごと)
 *                     foldersOnly=1はツリー展開用(filesは空。ファイルの走査を省く)
 *                     守る場所(PathGuard)は、どちらの範囲でも一覧に出さない
 * GET  action=thumb  (type, folder, file)                     -> 画像バイナリ(縮小したもの)
 * POST action=upload (type, folder, files[], overwrite?)       -> {uploaded:[{index,name,size,url}], errors:[{index,name,message,code?,targetName?}]}
 *                     index は files[] の順番(0 から)。画面はこれで、送ったファイルと結果を対応づける
 *                     ピッカーは同名があれば 名前(1).拡張子 で別の名前にし、OnFileBrowserUpload を発火する。
 *                     管理モードは overwrite=1 のときだけ同名のファイルを上書きし(ファイルだけ。フォルダ・リンクは断る)、
 *                     OnFileManagerUpload を発火する。overwrite が無いときは、保存せずに errors へ code: exists と、
 *                     保存する名前(無害化した後)の targetName を入れて返す。画面は確かめた上で、該当のファイルだけ overwrite=1 で送り直す
 * POST action=mkdir  (type, folder, name)                      -> {name}
 * POST action=rename (type, folder, target, newName)           -> {name}
 * POST action=delete (type, folder, files[], folders[])        -> {deleted:[...], errors:[{name,message}]}
 * POST action=move   (type, folder, files[], dest)              -> {moved:[...], errors:[{name,message}]}
 * POST action=copy   (type, folder, files[], dest)              -> {copied:[{from,name}], errors:[{name,message}]}
 *                     同名があれば 名前(1).拡張子 のように連番を付ける。上書きはしない。ファイルのみ(フォルダは errors)
 * POST action=download (type, folder, files[])                  -> ファイルのバイナリ(1件はそのまま、2件以上は zip)。エラーは JSON
 *                     一覧に出す対象(BrowserScope::listsFile())だけ。1つでも満たさないものがあれば全体を拒否する。zip は PHP の zip 拡張が必要(無ければ 501 zip_unavailable)
 * POST action=saveImage (type, folder, target, mode, image(ファイル1つ)) -> {name}
 *                     画面で編集した画像を保存する。mode は copy(別名。名前(1).拡張子 のように連番。範囲のアップロードのイベントを発火) か
 *                     overwrite(target を置き換える。リンクは not_editable で断る。イベントは発火しない)。編集できるのは jpg・jpeg・png だけ。
 *                     画像の種類は target の拡張子と一致し、長い辺は 4096px 以下、大きさはアップロードの上限以下であること。
 *                     image_limit_width では縮めない。エラーコード: method_not_allowed / invalid_mode / invalid_name /
 *                     extension_not_allowed / not_editable / not_found / no_image / upload_failed / too_large /
 *                     invalid_image / type_mismatch / image_too_big / too_many_same_name / save_failed
 *
 * 管理モードだけ(詳しくは lib/ManageActions.php):
 * GET  action=readText (folder, file)                          -> {name,content,hash,readOnly,readOnlyReason,encoding}
 * POST action=saveText (folder, target, content, hash)         -> {name,hash}(hash は開いたときの中身の sha1。違えば 409 modified)
 * POST action=newfile  (folder, name)                          -> {name}(空のテキストファイルを作る)
 * POST action=unzip    (folder, target)                        -> {extracted,failed:[...]}(zip を同じフォルダへ展開する)
 *
 * 成功した変更は、ChangeLog::record() が管理操作ログ(1 件ごとに 1 行)とシステムログ(1 操作につき 1 行)に残す。表示系は記録しない。
 * 守る場所(権限によって触らせないパス)に当たる操作は 403 protected(複数の名前を受ける操作は、その名前の errors)。
 * 読み取り系(list/thumb)はセッション検証のみ。書き込み系とdownloadはCSRFトークン必須。
 */

define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);

$self = 'manager/media/browser/evo/api.php';
$base_path = str_replace($self, '', str_replace('\\', '/', __FILE__));
require_once $base_path . 'manager/includes/document.parser.class.inc.php';
require_once $base_path . 'manager/includes/filesystem.class.inc.php';
require_once __DIR__ . '/lib/PathGuard.php';
require_once __DIR__ . '/lib/BrowserScope.php';
require_once __DIR__ . '/lib/PickerScope.php';
require_once __DIR__ . '/lib/ManageScope.php';
require_once __DIR__ . '/lib/PathResolver.php';
require_once __DIR__ . '/lib/BrowserConfig.php';
require_once __DIR__ . '/lib/ThumbnailService.php';
require_once __DIR__ . '/lib/ZipService.php';
require_once __DIR__ . '/lib/ChangeLog.php';
require_once __DIR__ . '/lib/ManageActions.php';

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
    if (is_string($name) && strpos($name, "\u{FFFD}") !== false) {
        return lang('fb_err_bad_encoding');
    }
    return $message;
}

$config = new BrowserConfig($modx);

// scope=manage は file_manager の権限が無ければ 403 で、ピッカーへは戻さない
$scope = anyv('scope') === 'manage' ? ManageScope::authorize($modx) : PickerScope::authorize($modx, $config);
if (is_array($scope)) {
    fbError($scope['code'], lang($scope['lang']), $scope['status']);
}

$resolver = new PathResolver($scope);
$action = anyv('action', '');

// 合計が post_max_size を超えると PHP が $_POST と $_FILES を空にするため、action が読めず invalid_action になってしまう
if (is_post() && !postv() && !filev() && (int) serverv('CONTENT_LENGTH', 0) > 0) {
    fbError('too_large', lang('fb_err_too_large'), 413);
}

// 表に無い action(範囲に無い操作を含む)は 400
$actions = fbCommonActions() + $scope->actions();
if (!is_string($action) || !isset($actions[$action])) {
    fbError('invalid_action', lang('fb_err_invalid_action'), 400);
}
$operation = $actions[$action];
// download と saveImage は、csrf_token が GET でも受け取れてしまうため POST だけを受ける
if (!empty($operation['post']) && !is_post()) {
    fbError('method_not_allowed', lang('fb_err_method_not_allowed'), 405);
}
if (!empty($operation['csrf'])) {
    checkCsrfToken();
}
call_user_func($operation['handler'], $resolver, $scope, $modx);

function fbCommonActions()
{
    return [
        'mkdir' => ['handler' => 'fbActionMkdir', 'csrf' => true],
        'rename' => ['handler' => 'fbActionRename', 'csrf' => true],
        'delete' => ['handler' => 'fbActionDelete', 'csrf' => true],
        'move' => ['handler' => 'fbActionMove', 'csrf' => true],
        'copy' => ['handler' => 'fbActionCopy', 'csrf' => true],
        'download' => ['handler' => 'fbActionDownload', 'csrf' => true, 'post' => true],
        'saveImage' => ['handler' => 'fbActionSaveImage', 'csrf' => true, 'post' => true],
    ];
}

/**
 * フォルダを読み、守る場所と一覧に出さないものを除き、フォルダとファイルに分けて並べて返す(範囲によらず共通)。
 * 何を出すかは BrowserScope の listsFolder() / listsFile() に尋ねる。
 *
 * @param bool $foldersOnly true ならファイルは集めない(ツリー展開用)
 * @return array{dir:array{rel:string,real:string},folders:array,files:array}
 *         dir は PathResolver::resolveDir() の戻り値(real は末尾 / 付き)。folders の項目は {name, hasChildren, mtime}、
 *         files の項目は {name, fullPath, relPath(ルートからの相対), size, mtime}
 */
function fbReadDir(PathResolver $resolver, BrowserScope $scope, $folder, $foldersOnly)
{
    $dir = $resolver->resolveDir($folder);
    if ($dir === null) {
        fbError('not_found', fbNotFoundMessage($folder, lang('fb_err_folder_not_found')), 404);
    }

    $entries = @scandir($dir['real']);
    if ($entries === false) {
        fbError('not_found', lang('fb_err_folder_unreadable'), 404);
    }

    $guard = $scope->guard();
    $folders = [];
    $files = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = $dir['real'] . $entry;
        // 壊れたシンボリックリンクは使えないので一覧に出さない
        if (is_link($fullPath) && !file_exists($fullPath)) {
            continue;
        }
        if ($guard->isProtected($fullPath)) {
            continue;
        }

        if (is_dir($fullPath)) {
            if ($scope->listsFolder($entry)) {
                $folders[] = ['name' => $entry, 'hasChildren' => fbDirHasSubdirs($fullPath, $scope), 'mtime' => @filemtime($fullPath)];
            }
            continue;
        }
        if ($foldersOnly || !$scope->listsFile($entry)) {
            continue;
        }

        $files[] = [
            'name' => $entry,
            'fullPath' => $fullPath,
            'relPath' => ($dir['rel'] !== '' ? $dir['rel'] . '/' : '') . $entry,
            'size' => @filesize($fullPath),
            'mtime' => @filemtime($fullPath),
        ];
    }

    usort($folders, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    usort($files, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

    return ['dir' => $dir, 'folders' => $folders, 'files' => $files];
}

/**
 * 一覧の thumb の値。ラスター画像は 'api'(フロントが apiUrl 基準で action=thumb の URL を組み立てる。
 * モーダル時の相対パス差異を吸収する)、svg は元の URL、それ以外は null
 */
function fbThumbSource($ext, $fileUrl)
{
    if (ThumbnailService::isRasterImageExtension($ext)) {
        return 'api';
    }
    return strtolower($ext) === 'svg' ? $fileUrl : null;
}

function fbPickerList(PathResolver $resolver, PickerScope $scope, DocumentParser $modx)
{
    $folder = getv('folder', '');
    // ツリー展開用: ツリーにはフォルダだけを出すので、ファイルは集めない
    $list = fbReadDir($resolver, $scope, $folder, anyv('foldersOnly') === '1');

    $config = $scope->config();
    $baseUrl = $config->baseUrl();
    $urlPrefix = $config->urlPrefix();

    $files = [];
    foreach ($list['files'] as $file) {
        // 種類のフォルダ(images など)を頭に付けたパスが、rb_base_url の下の URL になる
        $relPath = $scope->type() . '/' . $file['relPath'];

        // path: SetUrl(TV値等)へ渡す相対パス(旧mcpuk同等)。読める形のまま渡し、
        // FTP などで置かれた名前の % # ? だけは URL が切れないようエンコードする(% を最初に置き換える)
        $filePath = ($urlPrefix !== '' ? $urlPrefix . '/' : '')
            . str_replace(['%', '#', '?'], ['%25', '%23', '%3F'], $relPath);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $isRaster = ThumbnailService::isRasterImageExtension($ext);

        // url: ブラウザ内表示用の絶対URL。パスの要素ごとにエンコードする
        $fileUrl = $baseUrl . implode('/', array_map('rawurlencode', explode('/', $relPath)));

        $width = null;
        $height = null;
        if ($isRaster) {
            $dimensions = @getimagesize($file['fullPath']);
            if ($dimensions) {
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
        }

        $item = [
            'name' => $file['name'],
            'size' => $file['size'],
            'mtime' => $file['mtime'],
            'url' => $fileUrl,
            'path' => $filePath,
            'thumb' => fbThumbSource($ext, $fileUrl),
            'width' => $width,
            'height' => $height,
        ];
        if (ThumbnailService::isDisplayableImageExtension($ext)) {
            $item['image'] = true;
        }
        $files[] = $item;
    }

    fbSendJson([
        'folder' => $folder,
        'folders' => $list['folders'],
        'files' => $files,
        'writable' => is_writable($list['dir']['real']),
    ]);
}

/** 管理モードの一覧。サムネイルは一覧では作らず、画面が <img loading="lazy"> で、見える位置に近づいた分だけ action=thumb で取りに来る */
function fbManageList(PathResolver $resolver, ManageScope $scope, DocumentParser $modx)
{
    $folder = getv('folder', '');
    $list = fbReadDir($resolver, $scope, $folder, anyv('foldersOnly') === '1');

    $files = [];
    foreach ($list['files'] as $file) {
        $item = [
            'name' => $file['name'],
            'size' => $file['size'],
            'mtime' => $file['mtime'],
            'url' => $scope->publicUrl($file['relPath']),
            'writable' => is_writable($file['fullPath']),
        ];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // 画像と決まったものだけ <img> で出す(.php を GET で実行したり、大きなファイルを丸ごと読んだりしない)
        if (ThumbnailService::isDisplayableImageExtension($ext)) {
            $item['image'] = true;
            $item['thumb'] = fbThumbSource($ext, $item['url']);
        }
        // 再生できる動画・音声は、プレビューが <video>・<audio> で出す(種別の判定は BrowserConfig が持つ)
        $media = BrowserConfig::mediaKindOf($file['name']);
        if ($media !== '' && $item['url'] !== null) {
            $item['media'] = $media;
        }
        if (ThumbnailService::isRasterImageExtension($ext)) {
            $dimensions = @getimagesize($file['fullPath']);
            if ($dimensions) {
                $item['width'] = $dimensions[0];
                $item['height'] = $dimensions[1];
            }
        }
        $files[] = $item;
    }

    fbSendJson([
        'folder' => $folder,
        'folders' => $list['folders'],
        'files' => $files,
        'writable' => is_writable($list['dir']['real']),
    ]);
}

function fbDirHasSubdirs($dir, BrowserScope $scope)
{
    $entries = @scandir($dir);
    if ($entries === false) {
        return false;
    }

    $guard = $scope->guard();
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || !$scope->listsFolder($entry)) {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_dir($path) && !$guard->isProtected($path)) {
            return true;
        }
    }

    return false;
}

function fbActionThumb(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx)
{
    $folder = getv('folder', '');
    $dir = $resolver->resolveDir($folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $file = $resolver->resolveFile($dir['real'], get_strvar('file'));
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

function fbUploadedFiles()
{
    $uploadedFiles = filev('files');
    if (!$uploadedFiles || !is_array($uploadedFiles['name'])) {
        fbError('no_files', lang('fb_err_no_files'), 400);
    }

    $files = [];
    $count = count($uploadedFiles['name']);
    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'index' => $i,
            'name' => $uploadedFiles['name'][$i],
            'tmp' => $uploadedFiles['tmp_name'][$i],
            'error' => $uploadedFiles['error'][$i],
            'size' => $uploadedFiles['size'][$i],
        ];
    }

    return $files;
}

/**
 * アップロードされたファイルを保存先へ置く。ピッカーと管理モードで共通なのは、この呼び出しだけ
 * (拡張子・大きさ・同名のときの扱いの確認とイベントは、範囲ごとの関数が行う)。
 * 縮小(image_limit_width)とパーミッションの設定は $modx->move_uploaded_file() が行う。失敗したらシステムログに残して false を返す
 */
function fbStoreUpload(DocumentParser $modx, $originalName, $tmpPath, $targetPath)
{
    $saved = Filesystem::attempt(function () use ($modx, $tmpPath, $targetPath) {
        return $modx->move_uploaded_file($tmpPath, $targetPath);
    }, $reason);
    if (!$saved) {
        evo()->logEvent(0, 3, 'Failed to save an uploaded file in the file browser', 'File browser', [
            'name' => $originalName,
            'to' => $targetPath,
            'reason' => $reason,
        ]);
    }

    return $saved;
}

/** アップロードの errors の 1 件。画面は name でなく index で、送ったファイルと対応づける(名前に " や \ があると一致しない) */
function fbUploadError(array $file, $message, array $extra = [])
{
    return ['index' => $file['index'], 'name' => $file['name'], 'message' => $message] + $extra;
}

/** パスの最後の要素。basename() は PHP 7.4 ではロケールによって先頭のマルチバイト文字を落とすため使わない */
function fbBaseName($path)
{
    return substr($path, strrpos($path, '/') + 1);
}

function fbPickerUpload(PathResolver $resolver, PickerScope $scope, DocumentParser $modx)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $uploadedFiles = fbUploadedFiles();
    $maxSize = $scope->maxUploadSize();

    $uploaded = [];
    $errors = [];
    $changed = [];

    foreach ($uploadedFiles as $file) {
        $originalName = $file['name'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = fbUploadError($file, sprintf(lang('fb_err_upload_failed'), $file['error']));
            continue;
        }

        // 拡張子は、実際に保存する名前（無害化した後）で確かめる
        $safeName = $modx->sanitizeFilename($originalName);
        if (!$scope->isAllowedNewName($safeName)) {
            $errors[] = fbUploadError($file, lang('fb_err_extension_not_allowed'));
            continue;
        }

        if ($file['size'] > $maxSize) {
            $errors[] = fbUploadError($file, lang('fb_err_too_large'));
            continue;
        }

        $targetPath = fbFindFreePath($resolver, $modx, $dir['real'], $safeName);
        if ($targetPath === null) {
            $errors[] = fbUploadError($file, lang('fb_err_too_many_same_name'));
            continue;
        }
        $targetName = fbBaseName($targetPath);

        if (!fbStoreUpload($modx, $originalName, $file['tmp'], $targetPath)) {
            $errors[] = fbUploadError($file, lang('fb_err_save_failed'));
            continue;
        }

        // プラグインがファイルを移動・削除してもよいよう、サイズはイベントの前に取る
        $uploaded[] = [
            'index' => $file['index'],
            'name' => $targetName,
            'size' => filesize($targetPath),
        ];
        $changed[] = ChangeLog::relPath($dir, $targetName);

        // invokeEvent() の第2引数は参照渡しなので、変数に入れてから渡す
        $eventParams = [
            'filepath' => $dir['real'],
            'filename' => $targetName,
        ];
        $modx->invokeEvent($scope->uploadEvent(), $eventParams);
    }

    ChangeLog::record($scope, 'Uploaded File', $changed);
    fbSendJson(['uploaded' => $uploaded, 'errors' => $errors]);
}

function fbManageUpload(PathResolver $resolver, ManageScope $scope, DocumentParser $modx)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $uploadedFiles = fbUploadedFiles();
    $maxSize = $scope->maxUploadSize();
    $overwrite = postv('overwrite') === '1';

    $uploaded = [];
    $errors = [];
    $changed = [];

    foreach ($uploadedFiles as $file) {
        $originalName = $file['name'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = fbUploadError($file, sprintf(lang('fb_err_upload_failed'), $file['error']));
            continue;
        }

        $safeName = $modx->sanitizeFilename($originalName);

        // null は本体では制限しない(PHP の upload_max_filesize と post_max_size だけが効く)
        if ($maxSize !== null && $file['size'] > $maxSize) {
            $errors[] = fbUploadError($file, lang('fb_err_too_large'));
            continue;
        }

        // $modx->move_uploaded_file() は保存先の名前をもう一度無害化するので、確かめるパスもそれに合わせる
        $targetPath = $modx->sanitizeUploadedFilename($dir['real'] . $safeName);
        $targetName = fbBaseName($targetPath);
        if ($resolver->resolveNewChild($dir['real'], $targetName) === null) {
            $errors[] = fbUploadError($file, lang('fb_err_protected'));
            continue;
        }

        // 同じ名前があるときは上書きする。置き換えてよいのは、書き込めるふつうのファイルだけ
        if (file_exists($targetPath) || is_link($targetPath)) {
            if (is_link($targetPath) || !is_file($targetPath)) {
                $errors[] = fbUploadError($file, lang('fb_err_already_exists'));
                continue;
            }
            if (!is_writable($targetPath)) {
                $errors[] = fbUploadError($file, lang('fb_err_save_failed'));
                continue;
            }
            if (!$overwrite) {
                $errors[] = fbUploadError($file, lang('fb_err_already_exists'), ['code' => 'exists', 'targetName' => $targetName]);
                continue;
            }
        }

        if (!fbStoreUpload($modx, $originalName, $file['tmp'], $targetPath)) {
            $errors[] = fbUploadError($file, lang('fb_err_save_failed'));
            continue;
        }

        // 上書きで置き換えたファイルの古いサムネイルを捨てる(同じ秒のうちの置き直しでも古い縮小が返らないように)
        $thumbSource = realpath($targetPath);
        if ($thumbSource !== false) {
            (new ThumbnailService(MODX_BASE_PATH . 'temp/thumbs/'))->forget(str_replace('\\', '/', $thumbSource));
        }

        // プラグインがファイルを移動・削除してもよいよう、サイズはイベントの前に取る
        $uploaded[] = [
            'index' => $file['index'],
            'name' => $targetName,
            'size' => filesize($targetPath),
        ];
        $changed[] = ChangeLog::relPath($dir, $targetName);

        // 旧ファイル管理と同じイベント(付けてあるプラグインが動き続けるように)
        $eventParams = [
            'filepath' => $dir['real'],
            'filename' => $targetName,
        ];
        $modx->invokeEvent($scope->uploadEvent(), $eventParams);
    }

    ChangeLog::record($scope, 'Uploaded File', $changed);
    fbSendJson(['uploaded' => $uploaded, 'errors' => $errors]);
}

/**
 * 保存先のフォルダで空いているファイルのフルパスを探す。同名があれば 名前(1).拡張子 のように連番を付ける。
 * 見つからなければ null。
 *
 * $modx->move_uploaded_file() は保存先の名前をもう一度無害化する（設定によっては括弧などが _ になる）。
 * 空きを確かめた名前と実際に保存される名前がずれて上書きしないよう、候補ごとに同じ無害化をかける。
 * 守る場所に当たる候補は使えないものとして次の番号へ進む
 */
function fbFindFreePath(PathResolver $resolver, DocumentParser $modx, $dirReal, $safeName)
{
    $dotPos = strrpos($safeName, '.');
    $baseName = $dotPos === false ? $safeName : substr($safeName, 0, $dotPos);
    $extension = $dotPos === false ? '' : substr($safeName, $dotPos);
    for ($suffix = 0; $suffix <= 200; $suffix++) {
        $candidate = $suffix === 0 ? $safeName : $baseName . '(' . $suffix . ')' . $extension;
        $candidatePath = $modx->sanitizeUploadedFilename($dirReal . $candidate);
        if ($resolver->resolveNewChild($dirReal, fbBaseName($candidatePath)) === null) {
            continue;
        }
        // 壊れたシンボリックリンクや同名のフォルダも「使用中」として次の番号へ進む
        if (!file_exists($candidatePath) && !is_link($candidatePath)) {
            return $candidatePath;
        }
    }
    return null;
}

/**
 * fbFindFreePath() で見つけた空きの名前を、排他的に作成(x)して確保する。
 * 空きを確かめてから作るまでの間に同名ができても上書きしないよう、使われていたら次の候補を探す。
 *
 * @return array{path:string,handle:resource|false,reason:string}|null 空きが見つからなければ null。
 *         handle は作成したファイルのハンドル(作れなかった場合は false で、path は最後に試した候補、reason は警告の文)
 */
function fbCreateFreeFile(PathResolver $resolver, DocumentParser $modx, $dirReal, $safeName)
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $targetPath = fbFindFreePath($resolver, $modx, $dirReal, $safeName);
        if ($targetPath === null) {
            return null;
        }
        $handle = Filesystem::attempt(function () use ($targetPath) {
            return fopen($targetPath, 'xb');
        }, $reason);
        if ($handle !== false || (!file_exists($targetPath) && !is_link($targetPath))) {
            // 'reason' は handle が false(権限など)のときの失敗の理由。名前が既にあった場合はループで次を探す
            return ['path' => $targetPath, 'handle' => $handle, 'reason' => $reason];
        }
    }
    return null;
}

function fbActionMkdir(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $name = $modx->sanitizeFilename(post_strvar('name'), false);
    if ($name === '') {
        fbError('invalid_name', lang('fb_err_invalid_folder_name'), 400);
    }

    $target = $resolver->resolveNewChild($dir['real'], $name);
    if ($target === null) {
        fbError('protected', lang('fb_err_protected'), 403);
    }
    if (file_exists($target)) {
        fbError('already_exists', lang('fb_err_already_exists'), 409);
    }

    $folderPermissions = $modx->newFolderPermissions();
    $created = Filesystem::attempt(function () use ($target, $folderPermissions) {
        return mkdir($target, $folderPermissions);
    }, $reason);
    if (!$created) {
        evo()->logEvent(0, 3, 'Failed to create a folder in the file browser', 'File browser', [
            'to' => $target,
            'reason' => $reason,
        ]);
        fbError('mkdir_failed', lang('fb_err_mkdir_failed'), 500);
    }
    @chmod($target, $folderPermissions);

    ChangeLog::record($scope, 'Created Folder', [ChangeLog::relPath($dir, $name)]);
    fbSendJson(['name' => $name]);
}

function fbActionRename(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    // target は既にある名前を指すので、名前を変えずに 1 要素であることだけを確かめる
    $target = PathResolver::sanitizeSegment(post_strvar('target'));
    $newName = $modx->sanitizeFilename(post_strvar('newName'), false);
    if ($target === '' || $newName === '') {
        fbError('invalid_name', lang('fb_err_invalid_name'), 400);
    }

    $sourcePath = $dir['real'] . $target;
    if (!file_exists($sourcePath)) {
        fbError('not_found', fbNotFoundMessage($target, lang('fb_err_target_not_found')), 404);
    }
    // 守る場所そのもの、フォルダなら中に守る場所を含むものは、名前を変えられない(中の守る場所ごと動いてしまう)
    $guard = $scope->guard();
    if ($guard->isProtected($sourcePath) || (is_dir($sourcePath) && $guard->containsProtected($sourcePath))) {
        fbError('protected', lang('fb_err_protected'), 403);
    }
    // ファイルの名前は、範囲ごとの拡張子の確かめ方で確かめる（フォルダーは確かめない）
    if (!is_dir($sourcePath) && !$scope->isAllowedRename($target, $newName)) {
        fbError('invalid_extension', lang('fb_err_extension_not_allowed'), 400);
    }

    $destPath = $resolver->resolveNewChild($dir['real'], $newName);
    if ($destPath === null) {
        fbError('protected', lang('fb_err_protected'), 403);
    }
    if (file_exists($destPath)) {
        fbError('already_exists', lang('fb_err_already_exists'), 409);
    }

    $renamed = Filesystem::attempt(function () use ($sourcePath, $destPath) {
        return rename($sourcePath, $destPath);
    }, $reason);
    if (!$renamed) {
        evo()->logEvent(0, 3, 'Failed to rename an entry in the file browser', 'File browser', [
            'from' => $sourcePath,
            'to' => $destPath,
            'reason' => $reason,
        ]);
        fbError('rename_failed', lang('fb_err_rename_failed'), 500);
    }

    ChangeLog::record($scope, 'Renamed', [[ChangeLog::relPath($dir, $target), ChangeLog::relPath($dir, $newName)]]);
    fbSendJson(['name' => $newName]);
}

function fbActionDelete(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $files = (array) postv('files', []);
    $folders = (array) postv('folders', []);

    $deleted = [];
    $deletedFiles = [];
    $deletedFolders = [];
    $errors = [];
    $failures = [];
    $guard = $scope->guard();

    foreach ($files as $name) {
        $name = PathResolver::sanitizeSegment($name);
        $path = $dir['real'] . $name;
        if ($name === '' || !is_file($path)) {
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, lang('fb_err_entry_file_not_found'))];
            continue;
        }
        if ($guard->isProtected($path)) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_protected')];
            continue;
        }
        $unlinked = Filesystem::attempt(function () use ($path) {
            return unlink($path);
        }, $reason);
        if ($unlinked) {
            $deleted[] = $name;
            $deletedFiles[] = ChangeLog::relPath($dir, $name);
        } else {
            $failures[$path] = $reason;
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
        // 守る場所そのもの、中に守る場所を含むフォルダは、消すと中の守る場所ごと消えてしまう
        if ($guard->isProtected($path) || $guard->containsProtected($path)) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_protected')];
            continue;
        }
        if (Filesystem::deleteTree($path, $failures)) {
            $deleted[] = $name;
            $deletedFolders[] = ChangeLog::relPath($dir, $name);
        } else {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_delete_partial')];
        }
    }

    if ($failures) {
        evo()->logEvent(0, 3, 'Failed to delete entries in the file browser', 'File browser', [
            'count' => count($failures),
            'paths' => array_slice(array_keys($failures), 0, 20),
            'reason' => (array_values(array_filter($failures)) ?: [''])[0],
        ]);
    }

    ChangeLog::record($scope, 'Deleted File', $deletedFiles);
    ChangeLog::record($scope, 'Deleted Folder', $deletedFolders);
    fbSendJson(['deleted' => $deleted, 'errors' => $errors]);
}

function fbActionMove(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx)
{
    $folder = anyv('folder', '');
    $dir = $resolver->resolveDir($folder);
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $destFolder = postv('dest', '');
    $destDir = $resolver->resolveDir($destFolder);
    if ($destDir === null) {
        fbError('not_found', lang('fb_err_dest_not_found'), 404);
    }

    if (rtrim($destDir['real'], '/') === rtrim($dir['real'], '/')) {
        fbError('same_folder', lang('fb_err_same_folder'), 400);
    }

    $files = (array) postv('files', []);
    $moved = [];
    $movedPaths = [];
    $errors = [];
    $failures = [];
    $guard = $scope->guard();

    foreach ($files as $name) {
        $name = PathResolver::sanitizeSegment($name);
        $sourcePath = $dir['real'] . $name;
        $destPath = $resolver->resolveNewChild($destDir['real'], $name);

        if ($name === '' || !is_file($sourcePath)) {
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, lang('fb_err_entry_file_not_found'))];
            continue;
        }
        // 元も移す先も、守る場所に当たるものは動かさない
        if ($destPath === null || $guard->isProtected($sourcePath)) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_protected')];
            continue;
        }
        if (file_exists($destPath)) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_dest_exists')];
            continue;
        }
        $renamed = Filesystem::attempt(function () use ($sourcePath, $destPath) {
            return rename($sourcePath, $destPath);
        }, $reason);
        if ($renamed) {
            $moved[] = $name;
            $movedPaths[] = [ChangeLog::relPath($dir, $name), ChangeLog::relPath($destDir, $name)];
        } else {
            $failures[] = ['from' => $sourcePath, 'to' => $destPath, 'reason' => $reason];
            $errors[] = ['name' => $name, 'message' => lang('fb_err_move_failed')];
        }
    }

    if ($failures) {
        evo()->logEvent(0, 3, 'Failed to move entries in the file browser', 'File browser', [
            'count' => count($failures),
            'entries' => array_slice($failures, 0, 20),
        ]);
    }

    ChangeLog::record($scope, 'Moved', $movedPaths);
    fbSendJson(['moved' => $moved, 'errors' => $errors]);
}

function fbActionCopy(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx)
{
    $dir = $resolver->resolveDir(anyv('folder', ''));
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    // 同じフォルダへの複製も許す（連番の名前になる）
    $destDir = $resolver->resolveDir(postv('dest', ''));
    if ($destDir === null) {
        fbError('not_found', lang('fb_err_dest_not_found'), 404);
    }

    $files = (array) postv('files', []);
    $copied = [];
    $copiedPaths = [];
    $errors = [];

    foreach ($files as $name) {
        $name = PathResolver::sanitizeSegment($name);

        // シンボリックリンクの先の実体が許可された場所の中にあるものだけを複製する
        $sourcePath = $resolver->resolveFile($dir['real'], $name);
        if ($sourcePath === null) {
            $errors[] = ['name' => $name, 'message' => fbNotFoundMessage($name, lang('fb_err_entry_file_not_found'))];
            continue;
        }

        // 複製先の名前も、範囲ごとの拡張子の確かめ方で確かめる
        $safeName = $modx->sanitizeFilename($name);
        if ($safeName === '' || !$scope->isAllowedCopy($name, $safeName)) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_copy_not_allowed')];
            continue;
        }

        // 空きを確かめてから作るまでの間に同名ができても上書きしないよう、排他的に作成して確保する
        $free = fbCreateFreeFile($resolver, $modx, $destDir['real'], $safeName);
        if ($free === null) {
            $errors[] = ['name' => $name, 'message' => lang('fb_err_too_many_same_name')];
            continue;
        }
        $targetPath = $free['path'];
        $out = $free['handle'];

        list($in, $expectedSize, $copiedSize) = Filesystem::attempt(function () use ($out, $sourcePath) {
            $in = $out === false ? false : fopen($sourcePath, 'rb');
            $expectedSize = filesize($sourcePath);
            $copiedSize = $in !== false ? stream_copy_to_stream($in, $out) : false;
            return [$in, $expectedSize, $copiedSize];
        }, $reason);
        if ($out === false) {
            $reason = $free['reason'];
        }
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
                'reason' => $reason,
            ]);
            $errors[] = ['name' => $name, 'message' => lang('fb_err_copy_failed')];
            continue;
        }
        // パーミッションを変えられない環境でも、複製できていれば成功として扱う
        @chmod($targetPath, $modx->newFilePermissions());

        $copied[] = ['from' => $name, 'name' => fbBaseName($targetPath)];
        $copiedPaths[] = [ChangeLog::relPath($dir, $name), ChangeLog::relPath($destDir, fbBaseName($targetPath))];
    }

    ChangeLog::record($scope, 'Copied', $copiedPaths);
    fbSendJson(['copied' => $copied, 'errors' => $errors]);
}

/**
 * 画面(canvas)で編集した画像を保存する。入力は信用せず、拡張子・画像の種類・大きさ・名前をここで確かめる。
 * - copy: 元の名前から空いている名前(名前(1).拡張子)を探して新しいファイルとして保存し、範囲のアップロードのイベント(BrowserScope::uploadEvent())を発火する
 * - overwrite: target のファイルを置き換える(一時ファイルに受けてから rename するので、途中で失敗しても元は壊れない)。
 *   元のパーミッションを引き継ぐ。target がシンボリックリンクのときは断る
 * image_limit_width による縮小は行わない(利用者が編集で決めた大きさを保つため)。
 * canvas で書き出すと EXIF は消える(画面でその旨を表示している)。
 */
function fbActionSaveImage(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx)
{
    $dir = $resolver->resolveDir(anyv('folder', ''));
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $mode = postv('mode', 'copy');
    if ($mode !== 'copy' && $mode !== 'overwrite') {
        fbError('invalid_mode', lang('fb_err_invalid_mode'), 400);
    }

    // target は既にある名前を指すので、名前を変えずに 1 要素であることだけを確かめる(rename と同じ)
    $target = PathResolver::sanitizeSegment(post_strvar('target'));
    if ($target === '' || strpos($target, '.') === 0) {
        fbError('invalid_name', lang('fb_err_invalid_name'), 400);
    }
    if (!$scope->isAllowedNewName($target)) {
        fbError('extension_not_allowed', lang('fb_err_extension_not_allowed'), 400);
    }
    if (!BrowserConfig::isEditableFilename($target)) {
        fbError('not_editable', lang('fb_err_not_editable'), 400);
    }

    // シンボリックリンクの先の実体が許可された場所の中にあるものだけを対象にする
    $realPath = $resolver->resolveFile($dir['real'], $target);
    if ($realPath === null) {
        fbError('not_found', fbNotFoundMessage($target, lang('fb_err_target_not_found')), 404);
    }

    $image = filev('image');
    if (!is_array($image) || !isset($image['error'], $image['tmp_name']) || is_array($image['error'])) {
        fbError('no_image', lang('fb_err_no_image'), 400);
    }
    if ($image['error'] === UPLOAD_ERR_INI_SIZE || $image['error'] === UPLOAD_ERR_FORM_SIZE) {
        fbError('too_large', lang('fb_err_too_large'), 413);
    }
    if ($image['error'] !== UPLOAD_ERR_OK) {
        fbError('upload_failed', sprintf(lang('fb_err_upload_failed'), $image['error']), 400);
    }
    $tmpPath = (string) $image['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        fbError('invalid_image', lang('fb_err_invalid_image'), 400);
    }

    // 保存するファイルにも、アップロードと同じサイズの上限を当てる(null は本体では制限しない)
    $size = @filesize($tmpPath);
    $maxSize = $scope->maxUploadSize();
    if ($size === false || ($maxSize !== null && $size > $maxSize)) {
        fbError('too_large', lang('fb_err_too_large'), 413);
    }

    // 画像の中身を確かめる(画面の判定は信用しない)。展開はしないので、寸法の上限を先に確かめる
    $info = @getimagesize($tmpPath);
    if (!$info || $info[0] < 1 || $info[1] < 1) {
        fbError('invalid_image', lang('fb_err_invalid_image'), 400);
    }
    $ext = strtolower(substr($target, strrpos($target, '.') + 1));
    $expectedType = $ext === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
    if ($info[2] !== $expectedType) {
        fbError('type_mismatch', lang('fb_err_image_type_mismatch'), 400);
    }
    if (max($info[0], $info[1]) > BrowserConfig::EDIT_MAX_DIMENSION) {
        fbError('image_too_big', sprintf(lang('fb_err_image_too_big'), BrowserConfig::EDIT_MAX_DIMENSION), 413);
    }

    if ($mode === 'copy') {
        $safeName = $modx->sanitizeFilename($target);
        if ($safeName === '' || !$scope->isAllowedNewName($safeName) || !BrowserConfig::isEditableFilename($safeName)) {
            fbError('extension_not_allowed', lang('fb_err_extension_not_allowed'), 400);
        }

        // 空きを確かめてから作るまでの間に同名ができても上書きしないよう、排他的に作成して確保する
        $free = fbCreateFreeFile($resolver, $modx, $dir['real'], $safeName);
        if ($free === null) {
            fbError('too_many_same_name', lang('fb_err_too_many_same_name'), 409);
        }
        $targetPath = $free['path'];
        if ($free['handle'] !== false) {
            fclose($free['handle']);
        }
        $savedName = fbBaseName($targetPath);

        // 確保した(空の)ファイルへ保存する。第 3 引数 false で image_limit_width による縮小をしない
        $reason = $free['reason'];
        $saved = $free['handle'] !== false && Filesystem::attempt(function () use ($modx, $tmpPath, $targetPath) {
            return $modx->move_uploaded_file($tmpPath, $targetPath, false);
        }, $reason);
        if (!$saved) {
            if ($free['handle'] !== false) {
                @unlink($targetPath);
            }
            evo()->logEvent(0, 3, 'Failed to save an edited image in the file browser', 'File browser', [
                'mode' => $mode,
                'to' => $targetPath,
                'reason' => $reason,
            ]);
            fbError('save_failed', lang('fb_err_save_failed'), 500);
        }

        $eventParams = [
            'filepath' => $dir['real'],
            'filename' => $savedName,
        ];
        $modx->invokeEvent($scope->uploadEvent(), $eventParams);

        ChangeLog::record($scope, 'Saved Image', [ChangeLog::relPath($dir, $savedName)]);
        fbSendJson(['name' => $savedName]);
    }

    // overwrite: 一覧に見えているファイルそのものだけを置き換える。リンクの先の実体は、名前・拡張子・置き場所を
    // 確かめていないので、リンクは断る(別名で保存すれば、一覧に見える場所に新しいファイルができる)
    if (is_link($dir['real'] . $target)) {
        fbError('not_editable', lang('fb_err_overwrite_link'), 400);
    }

    // 同じフォルダの一時ファイルに受けてから置き換える
    $realDir = substr($realPath, 0, strrpos($realPath, '/') + 1);
    $stagePath = Filesystem::attempt(function () use ($realDir) {
        return tempnam($realDir, '.evo-save-');
    }, $stageReason);
    // Windows の tempnam() は区切りに \ を返すので、$realDir(/ 区切り)と比べる前にそろえる
    if ($stagePath !== false) {
        $stagePath = str_replace('\\', '/', $stagePath);
    }
    $oldMtime = @filemtime($realPath);
    // 元のファイルのパーミッションを引き継ぐ(FTP と共同で編集していても、上書き後に書き換えられなくならないように)。取れなければ新しいファイルの既定
    $oldPerms = @fileperms($realPath);
    $newPerms = $oldPerms !== false ? ($oldPerms & 0777) : $modx->newFilePermissions();
    $saved = false;
    $reason = '';
    if ($stagePath !== false && strpos($stagePath, $realDir) !== 0) {
        // 書き込めないフォルダだと、tempnam() は別の場所(システムの一時フォルダ)に作る。そこからの置き換えはしない
        $reason = 'Could not create a temporary file in the target folder';
        @unlink($stagePath);
        $stagePath = false;
    }
    if ($stagePath !== false) {
        // 一時ファイル(tempnam が作った空のファイル)を、アップロードされた中身で置き換える
        $saved = Filesystem::attempt(function () use ($tmpPath, $stagePath, $newPerms, $realPath) {
            return move_uploaded_file($tmpPath, $stagePath)
                && chmod($stagePath, $newPerms)
                && rename($stagePath, $realPath);
        }, $reason);
        if (!$saved) {
            @unlink($stagePath);
        }
    }
    if (!$saved) {
        evo()->logEvent(0, 3, 'Failed to save an edited image in the file browser', 'File browser', [
            'mode' => $mode,
            'to' => $realPath,
            'reason' => $reason ?: $stageReason,
        ]);
        fbError('save_failed', lang('fb_err_save_failed'), 500);
    }

    // 更新時刻が必ず前より新しくなるようにする(同じ秒の上書きでも、更新時刻をキーにしたキャッシュや URL が切り替わる)
    clearstatcache(true, $realPath);
    if ($oldMtime !== false && (int) @filemtime($realPath) <= $oldMtime) {
        @touch($realPath, $oldMtime + 1);
    }
    (new ThumbnailService(MODX_BASE_PATH . 'temp/thumbs/'))->forget($realPath);

    ChangeLog::record($scope, 'Saved Image', [ChangeLog::relPath($dir, $target)]);
    fbSendJson(['name' => $target]);
}

/**
 * 選んだファイルを送る。1 件はそのまま、2 件以上は zip にして送る。
 * ファイルを外へ出す操作なので、1 つでも条件(許可された拡張子・ドットで始まらない・許可された場所の中の実体・読める)を
 * 満たさなければ全体を拒否する(中身が黙って欠けた zip を作らない)。
 */
function fbActionDownload(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx)
{
    $config = new BrowserConfig($modx);
    $dir = $resolver->resolveDir(anyv('folder', ''));
    if ($dir === null) {
        fbError('not_found', lang('fb_err_folder_not_found'), 404);
    }

    $names = postv('files', []);
    if (!is_array($names) || !$names) {
        fbError('no_files', lang('fb_err_download_no_files'), 400);
    }
    foreach ($names as $name) {
        if (!is_string($name)) {
            fbError('invalid_name', lang('fb_err_invalid_name'), 400);
        }
    }
    $names = array_values(array_unique($names));

    $isZip = count($names) > 1;
    if ($isZip && !$config->canCreateZip()) {
        fbError('zip_unavailable', lang('fb_err_zip_unavailable'), 501);
    }
    if (count($names) > BrowserConfig::ZIP_MAX_FILES) {
        fbError('too_many_files', sprintf(lang('fb_err_download_too_many'), BrowserConfig::ZIP_MAX_FILES), 413);
    }

    $entries = [];
    $totalSize = 0;
    foreach ($names as $name) {
        // UTF-8 でない名前（一覧では置き換え文字になる）は、zip に正しく入れられず実体とも一致しない
        if (strpos($name, "\u{FFFD}") !== false || preg_match('//u', $name) !== 1) {
            fbError('bad_encoding', lang('fb_err_bad_encoding'), 400);
        }
        $clean = PathResolver::sanitizeSegment($name);
        if ($clean === '' || $clean !== $name || !$scope->listsFile($clean)) {
            fbError('extension_not_allowed', lang('fb_err_extension_not_allowed'), 400);
        }

        // シンボリックリンクの先の実体が許可された場所の中にあるものだけを送る
        $real = $resolver->resolveFile($dir['real'], $clean);
        if ($real === null || !is_readable($real)) {
            fbError('not_found', lang('fb_err_entry_file_not_found'), 404);
        }

        $size = @filesize($real);
        if ($size === false) {
            fbError('not_found', lang('fb_err_entry_file_not_found'), 404);
        }
        $totalSize += $size;
        $entries[$clean] = $real;
    }

    // 1 件はそのまま送るので、サイズの上限は zip にするときだけ
    if ($isZip && $totalSize > BrowserConfig::ZIP_MAX_BYTES) {
        fbError('too_large', sprintf(lang('fb_err_download_too_large'), (int) (BrowserConfig::ZIP_MAX_BYTES / 1048576)), 413);
    }

    // 確かめは済んだ。送信に時間がかかる間、同じ利用者の別の操作を待たせないよう、セッションのロックを手放す
    session_write_close();

    if (!$isZip) {
        $name = (string) array_key_first($entries);
        $sendPath = $entries[$name];
        $contentType = 'application/octet-stream';
        // ASCII だけの名前は filename= で足り、それ以外は RFC 5987 の filename* で UTF-8 の名前を渡す
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        $disposition = 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    } else {
        @set_time_limit(0);
        $zipPath = (new ZipService(MODX_BASE_PATH . 'temp/zip/'))->create($entries);
        if ($zipPath === null) {
            evo()->logEvent(0, 3, 'Failed to create a zip in the file browser', 'File browser', [
                'folder' => $dir['rel'],
                'count' => count($entries),
            ]);
            fbError('zip_failed', lang('fb_err_zip_failed'), 500);
        }
        // 送り終えたあと(途中で切れたときも)一時ファイルを消す
        register_shutdown_function(function () use ($zipPath) {
            @unlink($zipPath);
        });
        $sendPath = $zipPath;
        $contentType = 'application/zip';
        $disposition = 'attachment; filename="' . $scope->downloadName() . '-' . date('Ymd-His') . '.zip"';
    }

    $length = @filesize($sendPath);
    if ($length === false) {
        fbError('not_found', lang('fb_err_entry_file_not_found'), 404);
    }

    // 警告などの出力がバイナリに混ざらないようにする
    @ini_set('display_errors', '0');
    @set_time_limit(0);
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code(200);
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: ' . $disposition);
    header('Content-Length: ' . $length);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    @readfile($sendPath);
    exit;
}
