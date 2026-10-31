<?php

/**
 * 複数のファイルを 1 つの zip にまとめる。
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
