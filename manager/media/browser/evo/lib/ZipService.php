<?php

/**
 * 複数のファイルを 1 つの zip にまとめる(create())。zip を展開する(extract())。
 *
 * 一時ファイルは temp/zip/ に作る。呼び出し側が送り終えたら消す（失敗したときはここで消す）。
 * 古い一時ファイルは、確率的なごみ集めで消す（ThumbnailService と同じ作り）。
 */
class ZipService
{
    const GC_PROBABILITY_DIVISOR = 20;
    const GC_MAX_AGE = 3600;
    const TEMP_PREFIX = 'fbzip_';

    /** 圧縮済みで、さらに圧縮しても小さくならない拡張子(zip には圧縮せずに入れる) */
    const STORED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp3', 'mp4', 'm4a', 'webm', 'ogg', 'mov', 'zip', 'gz', '7z', 'rar', 'pdf', 'docx', 'xlsx', 'pptx'];

    /** @var string temp/zip/ の絶対パス(末尾スラッシュ付き) */
    private $tempDir;

    public function __construct($tempDir)
    {
        $this->tempDir = rtrim($tempDir, '/') . '/';
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * @param array $files [zip 内の名前(UTF-8) => 実ファイルの絶対パス]
     * @return string|null 一時ファイルの絶対パス。1 つでも入れられなかったとき・作れなかったときは null(一時ファイルは消してある)
     */
    public function create(array $files)
    {
        $this->maybeCollectGarbage();

        if (!$files || !class_exists('ZipArchive') || !is_dir($this->tempDir)) {
            return null;
        }

        $tmp = @tempnam($this->tempDir, self::TEMP_PREFIX);
        if ($tmp === false) {
            return null;
        }

        // temp/zip/ に作れないと、tempnam() はシステムの一時フォルダに作る。そこには残さない
        if (realpath(dirname($tmp)) !== realpath($this->tempDir)) {
            @unlink($tmp);
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            return null;
        }

        $ok = true;
        foreach ($files as $entryName => $path) {
            $entryName = (string) $entryName;
            if (!$zip->addFile($path, $entryName)) {
                $ok = false;
                break;
            }
            $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            if (in_array($ext, self::STORED_EXTENSIONS, true)) {
                $zip->setCompressionName($entryName, ZipArchive::CM_STORE);
            }
        }

        // addFile() は、ファイルを実際に読むのが close() のときなので、失敗は close() の戻り値にも出る
        if ($ok) {
            $ok = @$zip->close();
        } else {
            // 失敗した zip は閉じても使わない。close() が一時ファイルを残すことがあるので、あとで必ず消す
            @$zip->close();
        }

        if (!$ok || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            return null;
        }

        return $tmp;
    }

    /**
     * zip を展開先のフォルダへ展開する(管理モードだけ)。展開の前に全エントリを照らし合わせ、次のどれかに当たるものが
     * 1 件でもあれば何も展開しない(reason: unsafe): 名前に .. の要素がある(\ は / にしてから見る) / 展開先がルートの外か
     * 守る場所(存在する部分は realpath() で解決する) / 展開先のパスの途中にシンボリックリンクがある(リンク先の無いリンクは
     * realpath() で解決できず、書くときにたどってルートの外へ書けるため、リンクは一切たどらない)。
     * ファイルは無害化後の名前を $isAllowedName で確かめ、通らないものは skipped。書く直前にもパスを確かめ直し(付け直した後の
     * パスは事前と同じとは限らない)、通らないものは failed。zip 内のシンボリックリンクは、リンク先の文字列を中身にしたふつうのファイルにする。
     * 前からあるふつうのファイルは上書きし、展開後の大きさと数に上限は設けない(旧と同じ)。umask は変えない。
     *
     * @param string $zipPath resolveFile() で確かめた zip の実パス
     * @param string $destDir resolveDir() で確かめた展開先の実パス
     * @param callable $isAllowedName 無害化した後のファイル名を受け取り、新しく作ってよい拡張子かを bool で返す
     * @return array{ok:bool,reason:string,extracted:int,files:string[],failed:string[],skipped:string[]} reason は unavailable・open_failed・unsafe・''。
     *         files は展開できたファイル(フォルダは含まない)の、展開先からの相対パス
     */
    public static function extract($zipPath, $destDir, PathResolver $resolver, PathGuard $guard, callable $isAllowedName, DocumentParser $modx)
    {
        $result = ['ok' => false, 'reason' => '', 'extracted' => 0, 'files' => [], 'failed' => [], 'skipped' => []];

        if (!extension_loaded('zip') || !class_exists('ZipArchive')) {
            $result['reason'] = 'unavailable';
            return $result;
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $result['reason'] = 'open_failed';
            return $result;
        }

        $path = rtrim(str_replace('\\', '/', $destDir), '/') . '/';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $rawName = str_replace('\\', '/', self::entryName($zip, $i));
            $relative = self::entryPath($modx, $rawName);
            if (preg_match('@(^|/)\.\.(/|$)@', $rawName)
                || !$guard->isWithinBase($path . $relative)
                || $guard->isProtected($path . $relative)
                || self::hasLinkInPath($path, $relative)) {
                $zip->close();
                $result['reason'] = 'unsafe';
                return $result;
            }
        }

        $folderPermissions = $modx->newFolderPermissions();
        $filePermissions = $modx->newFilePermissions();
        $assigned = ['dirs' => [], 'paths' => []];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = self::entryName($zip, $i);
            $relative = self::entryPath($modx, $entryName);
            if ($relative === '') {
                continue;
            }
            $isDirEntry = substr(str_replace('\\', '/', $entryName), -1) === '/';
            // 拡張子は、無害化した後の名前の最後の要素で確かめる。飛ばすエントリは、次の名前の割り当てに加えない
            if (!$isDirEntry && !call_user_func($isAllowedName, self::lastSegment($relative))) {
                $result['skipped'][] = $relative;
                continue;
            }
            // 無害化で先に展開したものと名前が重なったときは、上書きせず (1) などを付ける
            $completeRelative = self::uniquePath($modx, $path, $relative, $isDirEntry, $assigned);

            $targetPath = self::checkedTarget($resolver, $guard, $path, $completeRelative, $isDirEntry);
            $folderRelative = $isDirEntry ? $completeRelative : self::parentOf($completeRelative);
            if ($targetPath === null || !self::makeFolders($path, $folderRelative, $folderPermissions)) {
                $result['failed'][] = $relative;
                continue;
            }
            if ($isDirEntry) {
                continue;
            }
            if (!self::copyEntry($zip, $i, $targetPath, $filePermissions)) {
                $result['failed'][] = $relative;
                continue;
            }
            $result['files'][] = $completeRelative;
        }
        if ($result['failed']) {
            $modx->logEvent(0, 2, 'Some zip entries could not be extracted', 'unzip', [
                'zip' => $zipPath,
                'entries' => $result['failed'],
            ]);
        }
        $zip->close();

        $result['ok'] = true;
        $result['extracted'] = count($result['files']);
        return $result;
    }

    /** エントリ名を UTF-8 で返す。Windows の zip は Shift_JIS(CP932) が多く、getNameIndex() の既定は CP437 とみなして崩すため、元のバイト列から変換する */
    private static function entryName(ZipArchive $zip, $index)
    {
        $raw = $zip->getNameIndex($index, ZipArchive::FL_ENC_RAW);
        if (mb_check_encoding($raw, 'UTF-8') || !mb_check_encoding($raw, 'SJIS-win')) {
            return $raw;
        }
        return mb_convert_encoding($raw, 'UTF-8', 'SJIS-win');
    }

    private static function entryPath(DocumentParser $modx, $entryName)
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $entryName)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $segments[] = $modx->sanitizeFilename($segment);
        }
        return implode('/', $segments);
    }

    /** 相対パスの存在する要素に、シンボリックリンク(リンク先の無いものも)があるか */
    private static function hasLinkInPath($path, $relative)
    {
        $current = rtrim($path, '/');
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '') {
                continue;
            }
            $current .= '/' . $segment;
            if (is_link($current)) {
                return true;
            }
            if (!file_exists($current)) {
                return false;
            }
        }
        return false;
    }

    /** 書く直前の確かめ。各要素が使える名前で、守る場所に当たらず、リンクでなく、途中はフォルダ・最後は種類が合うこと。通らなければ null */
    private static function checkedTarget(PathResolver $resolver, PathGuard $guard, $path, $relative, $isDir)
    {
        $segments = explode('/', $relative);
        $last = count($segments) - 1;
        $parent = rtrim($path, '/') . '/';
        $current = '';
        foreach ($segments as $i => $segment) {
            $current = $resolver->resolveNewChild($parent, $segment);
            if ($current === null || is_link($current)) {
                return null;
            }
            if (file_exists($current)) {
                $mustBeDir = $i < $last || $isDir;
                if ($mustBeDir ? !is_dir($current) : !is_file($current)) {
                    return null;
                }
            }
            $parent = $current . '/';
        }
        if (!$guard->isWithinBase($current)) {
            return null;
        }
        return $current;
    }

    private static function makeFolders($path, $relative, $permissions)
    {
        $current = rtrim($path, '/');
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '') {
                continue;
            }
            $current .= '/' . $segment;
            if (is_dir($current)) {
                continue;
            }
            if (!@mkdir($current)) {
                return false;
            }
            @chmod($current, $permissions);
        }
        return true;
    }

    /**
     * エントリ $index の中身を $target へ書き出す。zip://パス#名前 は、パスの # を区切りと読むため使わず、開いている ZipArchive から読む。
     * 大きなエントリでもメモリに載せないよう、ストリームで写す。
     * getStream() には getNameIndex() の既定の名前を渡す(Shift_JIS の名前も開ける)。前からあるファイルは中身だけを置き換え(パーミッションは変えない)、
     * 新しいファイルは排他的に作り(x: 同時に置かれたリンクをたどらない)、書けなければ途中のファイルを残さない
     */
    private static function copyEntry(ZipArchive $zip, $index, $target, $filePermissions)
    {
        $stat = $zip->statIndex($index);
        $in = $stat ? $zip->getStream($zip->getNameIndex($index)) : false;
        if (!$in) {
            return false;
        }
        $isNew = !file_exists($target);
        $out = @fopen($target, $isNew ? 'xb' : 'wb');
        if (!$out) {
            fclose($in);
            return false;
        }
        $copied = stream_copy_to_stream($in, $out);
        fclose($in);
        $closed = fclose($out);
        $ok = $closed && $copied === (int) $stat['size'];
        if ($isNew) {
            if ($ok) {
                @chmod($target, $filePermissions);
            } else {
                @unlink($target);
            }
        }
        return $ok;
    }

    /**
     * 展開するエントリの相対パスを、先に展開したものと重ならないパスにして返す(重なったら (1)・(2)… を付ける)。
     * フォルダーは同名のフォルダーとは一つにまとめ、ファイルとは重ねない。付け直したフォルダーは配下にも同じ付け直しを当てる。
     * 展開先に前からあるファイルは上書きする（同じ zip を展開し直して更新する使い方のため）。
     * $assigned: dirs は 無害化後のフォルダーのパス => 付け直した後のパス、paths は割り当てたパス => 'file' | 'dir'
     */
    private static function uniquePath(DocumentParser $modx, $path, $relative, $isDir, array &$assigned)
    {
        $segments = explode('/', $relative);
        $last = count($segments) - 1;
        $key = '';
        $parent = '';
        foreach ($segments as $i => $segment) {
            $key .= ($key === '' ? '' : '/') . $segment;
            if ($i < $last || $isDir) {
                if (!isset($assigned['dirs'][$key])) {
                    $dir = self::freeName($modx, $path, $parent, $segment, 'dir', $assigned['paths']);
                    $assigned['dirs'][$key] = $dir;
                    $assigned['paths'][$dir] = 'dir';
                }
                $parent = $assigned['dirs'][$key];
                continue;
            }
            $file = self::freeName($modx, $path, $parent, $segment, 'file', $assigned['paths']);
            $assigned['paths'][$file] = 'file';
            return $file;
        }
        return $parent;
    }

    private static function freeName(DocumentParser $modx, $path, $parent, $name, $kind, array $paths)
    {
        $prefix = $parent === '' ? '' : $parent . '/';
        $dotPos = $kind === 'file' ? strrpos($name, '.') : false;
        $base = $dotPos === false ? $name : substr($name, 0, $dotPos);
        $ext = $dotPos === false ? '' : substr($name, $dotPos);
        for ($i = 0; ; $i++) {
            $candidate = $prefix . ($i === 0 ? $name : $modx->sanitizeFilename($base . '(' . $i . ')' . $ext));
            if ($kind === 'dir') {
                $taken = (isset($paths[$candidate]) && $paths[$candidate] !== 'dir') || is_file($path . $candidate);
            } else {
                $taken = isset($paths[$candidate]) || is_dir($path . $candidate);
            }
            if (!$taken) {
                return $candidate;
            }
        }
    }

    // basename() / dirname() は PHP 7.4 ではロケールによってマルチバイトの名前を崩すことがあるため使わない
    private static function lastSegment($relative)
    {
        $pos = strrpos($relative, '/');
        return $pos === false ? $relative : substr($relative, $pos + 1);
    }

    private static function parentOf($relative)
    {
        $pos = strrpos($relative, '/');
        return $pos === false ? '' : substr($relative, 0, $pos);
    }

    /**
     * 送り忘れ・異常終了で残った古い一時ファイルを確率的に消して temp/zip/ に溜めない。
     */
    private function maybeCollectGarbage()
    {
        if (mt_rand(1, self::GC_PROBABILITY_DIVISOR) !== 1) {
            return;
        }

        $expireBefore = time() - self::GC_MAX_AGE;
        foreach (glob($this->tempDir . self::TEMP_PREFIX . '*') ?: [] as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $expireBefore) {
                @unlink($file);
            }
        }
    }
}
