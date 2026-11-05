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
     * zip を展開先のフォルダへ展開する(管理モードだけが使う)。旧ファイル管理の unzip() の確認を引き継ぎ、足りなかった所を足してある。
     *
     * 展開の前に全エントリを照らし合わせ、次のどれかに当たるものが 1 件でもあれば、何も展開しない(reason: unsafe)。
     * - 名前に .. の要素がある(バックスラッシュは / に置き換えてから見る)
     * - 展開先がルートの外になる、または守る場所に当たる(存在する部分は realpath() で解決するので、リンク先が外を指す場合も含む)
     * - 展開先のパスの途中に、シンボリックリンクがある(リンク先が無いぶら下がったリンクは realpath() で解決できず、
     *   「まだ無いパス」として通ってしまい、書くときにリンクをたどってルートの外へ書けるため。リンクは一切たどらない)
     * 名前は要素ごとに sanitizeFilename() で無害化する(先頭の / やドライブの名前はルートの中の相対パスになる)。
     * ファイルのエントリは、無害化した後の名前を $isAllowedName に渡して確かめ、通らないものは飛ばして skipped に入れる。
     * 同じ zip の中で、無害化した名前が重なったものには (1) などを付ける。展開先に前からあるふつうのファイルは上書きする。
     * zip の中のシンボリックリンクは、リンクとしては作らず、リンク先の文字列を中身にしたふつうのファイルとして書く。
     * 展開後の大きさとエントリの数には上限を設けない(旧と同じ。ディスクがいっぱいになったら、展開できたものと失敗したものを返す)。
     *
     * 照らし合わせから書き込みまでの間に、書く先とその親がリンクにされた場合に備え、エントリを書く直前にもパスを確かめ直す
     * (付け直した後のパスは、事前に照らし合わせたパスと同じとは限らないため)。通らないものは書かず、failed に入れる。
     * umask は変えず、作ったフォルダに newFolderPermissions()、新しく作ったファイルに newFilePermissions() を当てる(上書きしたファイルは変えない)。
     *
     * @param string $zipPath 展開する zip(PathResolver::resolveFile() が返した実パス)
     * @param string $destDir 展開先のフォルダ(PathResolver::resolveDir() が返した実パス。末尾の / は有っても無くてもよい)
     * @param PathResolver $resolver 書く直前の確かめに使う
     * @param PathGuard $guard ルートと守る場所
     * @param callable $isAllowedName 無害化した後のファイル名を受け取り、新しく作ってよい拡張子かを返す
     * @param DocumentParser $modx
     * @return array{ok:bool,reason:string,extracted:int,files:string[],failed:string[],skipped:string[]}
     *         reason は unavailable(zip 拡張が無い)・open_failed(zip を開けない)・unsafe(上の照らし合わせに当たった)・''。
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

        // 事前の照らし合わせ。1 件でも当たれば、何も展開しない
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
            // フォルダーのエントリ（名前が / で終わる）は、フォルダーを作るだけにする
            $isDirEntry = substr(str_replace('\\', '/', $entryName), -1) === '/';
            // 拡張子は、無害化した後の名前の最後の要素で確かめる。飛ばすエントリは、次の名前の割り当てに加えない
            if (!$isDirEntry && !call_user_func($isAllowedName, self::lastSegment($relative))) {
                $result['skipped'][] = $relative;
                continue;
            }
            // 無害化で、この zip で先に展開したファイル・フォルダーと名前が重なったときは上書きせず、(1) などを付けた名前にする
            $completeRelative = self::uniquePath($modx, $path, $relative, $isDirEntry, $assigned);

            // 書く直前の確かめ。通らないものは書かない
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

    /**
     * zip のエントリ名を UTF-8 で返す。
     * Windows で作った zip は名前を Shift_JIS（CP932）で持つことが多い。getNameIndex() の既定では
     * CP437 とみなして変換した別の文字列（バックスラッシュを含むこともある）になるため、元のバイト列から変換する。
     */
    private static function entryName(ZipArchive $zip, $index)
    {
        $raw = $zip->getNameIndex($index, ZipArchive::FL_ENC_RAW);
        if (mb_check_encoding($raw, 'UTF-8') || !mb_check_encoding($raw, 'SJIS-win')) {
            return $raw;
        }
        return mb_convert_encoding($raw, 'UTF-8', 'SJIS-win');
    }

    /**
     * zip のエントリ名を、展開先からの相対パスにする。
     * 各要素をアップロードと同じ規則（sanitizeFilename()）で無害化する。
     * UTF-8 にも Shift_JIS にもならない名前は、英数字に置き換わる。
     */
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

    /**
     * 展開先($path)の下の相対パス($relative)の、存在する要素に、シンボリックリンク(リンク先の無いものも)があるか。
     * ある要素より先は、その要素が無ければ無いので、見つからない要素で打ち切る
     */
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

    /**
     * 書く直前の確かめ。付け直した後のパスの、各要素を 1 つずつ確かめる。
     * 要素として使える名前で、守る場所に当たらず、存在するものがリンクでないこと。
     * 途中の要素はフォルダ、最後の要素は、フォルダのエントリならフォルダ、ファイルのエントリならふつうのファイル(置き換えてよいもの)であること。
     *
     * @return string|null 書くパス。通らなければ null
     */
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

    /**
     * 展開先($path)の下に、相対パスのフォルダを、足りない階層ごとに作る。作ったフォルダに $permissions を当てる(umask は変えない)
     */
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
     * zip のエントリ $index の中身を $target へ書き出す。
     * zip://<アーカイブのパス>#<エントリ名> の形は、アーカイブのパスにある # を区切りと読むため使わず、
     * 開いている ZipArchive から読む。大きなエントリでもメモリに載せないよう、ストリームで写す。
     * getStream() には getNameIndex() の既定の名前を渡す（zip の中で名前を探すときと同じ形。Shift_JIS の名前も開ける）。
     * 前からあるファイルは中身だけを置き換えて(パーミッションは変えない)、新しいファイルは排他的に作って(x: 同時に置かれたリンクをたどらない)
     * $filePermissions を当てる。新しく作ったファイルが書けなかったときは、途中のファイルを残さない
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
     * 展開するエントリの相対パスを、同じ zip で先に展開したファイル・フォルダーと重ならないパスにして返す。
     * パスの各要素を確かめ、重なったときは、ファイルブラウザのアップロードと同じく名前に (1)・(2)… を付ける。
     * - ファイルは、先に展開したファイル・フォルダーと同じ名前にしない
     * - フォルダーは、同じ名前のフォルダーとは一つにまとめ、ファイルとは同じ名前にしない
     * - 付け直したフォルダーは、同じ zip の配下のエントリすべてに同じ付け直しを当てる
     * 展開先に前からあるファイルは、上書きする（同じ zip を展開し直して更新する使い方のため）。
     *
     * @param string $path 展開先（末尾に /）
     * @param string $relative entryPath() の結果
     * @param bool $isDir フォルダーのエントリか
     * @param array $assigned これまでの割り当て。dirs: 無害化した後のフォルダーのパス => 付け直した後のパス、
     *                        paths: 割り当てたパス => 'file' | 'dir'。この関数が書き足す
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

    /**
     * uniquePath() の下請け。$parent の下で $name が使えるか確かめ、使えなければ (1)・(2)… を付けた名前を返す。
     */
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
