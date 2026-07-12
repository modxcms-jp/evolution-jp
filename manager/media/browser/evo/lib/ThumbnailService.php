<?php

/**
 * サムネイル生成・キャッシュ。
 *
 * 保存先は temp/thumbs/ (コンテンツフォルダ直下に .thumb/ を作らない)。
 * キャッシュキーは type+相対パス+ファイル名のハッシュに元ファイルのmtimeを連結し、
 * 元ファイルの更新で自動的に新キーへ切り替わる(rename/move/delete追随処理が不要)。
 * 透過を持つ形式(PNG/GIF)はPNGで、JPEGはJPEGで出力し、白背景パディングをしない
 * (キャンバスは常に画像そのもののリサイズ後サイズにする)。
 */
class ThumbnailService
{
    const MAX_DIM = 128;
    const GC_PROBABILITY_DIVISOR = 200;
    const GC_MAX_AGE = 2592000;

    /** @var string temp/thumbs/ の絶対パス(末尾スラッシュ付き) */
    private $cacheDir;

    public function __construct($cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    public static function isRasterImageExtension($ext)
    {
        return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'], true);
    }

    /**
     * サムネイルファイルの絶対パスを返す(必要なら生成する)。失敗時は null。
     */
    public function getOrCreate($sourceFile)
    {
        $this->maybeCollectGarbage();

        $ext = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));
        if (!self::isRasterImageExtension($ext)) {
            return null;
        }

        $mtime = filemtime($sourceFile);
        if ($mtime === false) {
            return null;
        }

        $isJpeg = ($ext === 'jpg' || $ext === 'jpeg');
        $outExt = $isJpeg ? 'jpg' : 'png';
        $cacheFile = $this->cacheDir . md5($sourceFile) . '_' . $mtime . '.' . $outExt;

        if (is_file($cacheFile)) {
            return $cacheFile;
        }

        $this->cleanupStale($sourceFile, $cacheFile);

        $image = $this->loadImage($sourceFile, $ext);
        if ($image === null) {
            return null;
        }

        $resized = $this->resize($image, !$isJpeg);
        imagedestroy($image);

        if ($isJpeg) {
            $saved = imagejpeg($resized, $cacheFile, 85);
        } else {
            $saved = imagepng($resized, $cacheFile, 6);
        }
        imagedestroy($resized);

        return $saved ? $cacheFile : null;
    }

    public function outputWithCacheHeaders($cacheFile)
    {
        $mime = (strtolower(pathinfo($cacheFile, PATHINFO_EXTENSION)) === 'jpg') ? 'image/jpeg' : 'image/png';
        $mtime = filemtime($cacheFile);
        $etag = '"' . md5($cacheFile . $mtime) . '"';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=2592000');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('ETag: ' . $etag);

        $ifNoneMatch = serverv('HTTP_IF_NONE_MATCH');
        $ifModifiedSince = serverv('HTTP_IF_MODIFIED_SINCE');
        if ($ifNoneMatch === $etag || ($ifModifiedSince && strtotime($ifModifiedSince) >= $mtime)) {
            header('HTTP/1.1 304 Not Modified');
            return;
        }

        readfile($cacheFile);
    }

    private function loadImage($file, $ext)
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return @imagecreatefromjpeg($file) ?: null;
            case 'png':
                $img = @imagecreatefrompng($file);
                if ($img) {
                    imagesavealpha($img, true);
                }
                return $img ?: null;
            case 'gif':
                return @imagecreatefromgif($file) ?: null;
        }
        return null;
    }

    private function resize($img, $preserveAlpha)
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $maxDim = self::MAX_DIM;

        if ($width >= $height) {
            $newWidth = $maxDim;
            $newHeight = max(1, (int) round($height * $maxDim / $width));
        } else {
            $newHeight = $maxDim;
            $newWidth = max(1, (int) round($width * $maxDim / $height));
        }

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if ($preserveAlpha) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
        }

        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    /**
     * 元ファイルのmtimeが変わって参照されなくなった古いキャッシュを間引く。
     */
    private function cleanupStale($sourceFile, $currentCacheFile)
    {
        $prefix = md5($sourceFile) . '_';
        $keepName = basename($currentCacheFile);
        foreach (glob($this->cacheDir . $prefix . '*') ?: [] as $stale) {
            if (basename($stale) !== $keepName) {
                @unlink($stale);
            }
        }
    }

    /**
     * 孤児化した古いサムネイルを確率的に掃除して temp/thumbs/ の単調増加を防ぐ。
     */
    private function maybeCollectGarbage()
    {
        if (mt_rand(1, self::GC_PROBABILITY_DIVISOR) !== 1) {
            return;
        }

        $expireBefore = time() - self::GC_MAX_AGE;
        foreach (glob($this->cacheDir . '*') ?: [] as $cacheFile) {
            $name = basename($cacheFile);
            if (!preg_match('/^[a-f0-9]{32}_[0-9]+\.(jpg|png)$/', $name)) {
                continue;
            }

            $mtime = filemtime($cacheFile);
            if ($mtime !== false && $mtime < $expireBefore) {
                @unlink($cacheFile);
            }
        }
    }
}
