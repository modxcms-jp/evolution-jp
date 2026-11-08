<?php

/**
 * サムネイル生成・キャッシュ。
 *
 * 保存先は temp/thumbs/ (コンテンツフォルダ直下に .thumb/ を作らない)。
 * キャッシュキーは元ファイルの実体の絶対パスの md5 に元ファイルのmtimeを連結し、
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

    /** ブラウザが <img> で表示できる形式の拡張子か(一覧の image: true の元。サムネイルを作れるのは isRasterImageExtension() だけ) */
    public static function isDisplayableImageExtension($ext)
    {
        return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg'], true);
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

        if (!$this->canLoad($sourceFile)) {
            return null;
        }

        $image = $this->loadImage($sourceFile, $ext);
        if ($image === null) {
            return null;
        }

        $resized = $this->resize($image, !$isJpeg);
        // imagedestroy() は PHP 8.5 で非推奨。変数を手放せば解放される
        unset($image);

        if ($isJpeg) {
            $saved = imagejpeg($resized, $cacheFile, 85);
        } else {
            $saved = imagepng($resized, $cacheFile, 6);
        }

        return $saved ? $cacheFile : null;
    }

    /**
     * 元ファイルのサムネイルのキャッシュをすべて消す(元ファイルを上書きしたとき用)。
     * キーに元ファイルの更新時刻を含めているが、同じ秒のうちに上書きすると同じキーになるため、明示的に消す。
     * キャッシュのキーの作りを知っているのは、このクラスだけにする
     *
     * @param string $sourceFile getOrCreate() に渡す元ファイルの絶対パス(実パス)
     */
    public function forget($sourceFile)
    {
        $this->cleanupStale($sourceFile, '');
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

    /**
     * GD で読み込んでもメモリが足りるかを、読み込む前に見積もる。
     * imagecreatefrom*() はメモリ不足だと例外にならず致命的エラーで処理ごと止まり、
     * 一覧のほかの画像まで巻き込むため、足りないものは作らずアイコン表示に回す。
     * 固定の画素数の上限にしないのは、実害が memory_limit との差で決まり、
     * 固定値だと小さい上限のサーバでは守れず、大きい上限のサーバでは不要に断るため。
     * 画像のサイズを読めない(壊れた画像など)ときは従来どおり loadImage() に任せる。
     */
    private function canLoad($file)
    {
        $info = @getimagesize($file);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return true;
        }

        $limit = $this->memoryLimitBytes();
        if ($limit < 0) {
            return true;
        }

        // GD は常に 4 バイト/画素のトゥルーカラーで展開する。デコード中の作業領域として 1.7 倍、
        // 縮小先(最大 MAX_DIM 四方)の分を足す
        $need = (int) ($info[0] * $info[1] * 4 * 1.7) + self::MAX_DIM * self::MAX_DIM * 4;
        return $need <= $limit - memory_get_usage();
    }

    /** memory_limit をバイト数で返す(128M・1G・数値のみに対応)。無制限(-1・0)は -1 */
    private function memoryLimitBytes()
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || (int) $raw <= 0) {
            return -1;
        }
        $bytes = (int) $raw;
        switch (strtolower(substr($raw, -1))) {
            case 'g':
                $bytes *= 1024;
                // no break
            case 'm':
                $bytes *= 1024;
                // no break
            case 'k':
                $bytes *= 1024;
        }
        return $bytes;
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
     * 元ファイルのmtimeが変わって参照されなくなった古いキャッシュを間引く($currentCacheFile 以外の、同じ元ファイルのキャッシュを消す)。
     */
    private function cleanupStale($sourceFile, $currentCacheFile)
    {
        $prefix = md5($sourceFile) . '_';
        // 残すキャッシュが無いとき(forget())は空文字。basename('') も空文字なので、どのキャッシュとも一致しない
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
