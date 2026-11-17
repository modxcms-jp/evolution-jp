<?php

/**
 * サムネイル生成・キャッシュ。
 *
 * 保存先は temp/thumbs/ (コンテンツフォルダ直下に .thumb/ を作らない)。
 * キャッシュキーは元ファイルの実体の絶対パスの md5 に元ファイルのmtimeを連結し、
 * 元ファイルの更新で自動的に新キーへ切り替わる(rename/move/delete追随処理が不要)。
 * 透過を持つ形式(PNG/GIF/WebP)はPNGで、JPEGはJPEGで出力し(形式は拡張子でなく中身で決める)、白背景パディングをしない
 * (キャンバスは常に画像そのもののリサイズ後サイズにする)。
 */
class ThumbnailService
{
    const MAX_DIM = 128;
    const GC_PROBABILITY_DIVISOR = 200;
    const GC_MAX_AGE = 2592000;

    /** サムネイルを作れる拡張子と、その形式(IMAGETYPE_*) */
    const EXTENSION_TYPES = [
        'jpg' => IMAGETYPE_JPEG,
        'jpeg' => IMAGETYPE_JPEG,
        'png' => IMAGETYPE_PNG,
        'gif' => IMAGETYPE_GIF,
        'webp' => IMAGETYPE_WEBP,
    ];

    /** @var string temp/thumbs/ の絶対パス(末尾スラッシュ付き) */
    private $cacheDir;

    public function __construct($cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /** この環境の GD で読み込める形式か(WebP は GD が WebP 付きで組まれているときだけ)。対応の判定はここだけ */
    public static function canLoadType($type)
    {
        if ($type === IMAGETYPE_WEBP) {
            return (imagetypes() & IMG_WEBP) !== 0;
        }
        return in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true);
    }

    public static function isRasterImageExtension($ext)
    {
        $type = self::EXTENSION_TYPES[strtolower($ext)] ?? null;
        return $type !== null && self::canLoadType($type);
    }

    /**
     * 拡張子と中身の形式が食い違うときだけ、中身の形式名('jpeg'・'png'・'gif'・'webp' など)を返す。
     *
     * @param string $ext 拡張子
     * @param array|false $info getimagesize() の結果
     * @return string|null
     */
    public static function mismatchedFormat($ext, $info)
    {
        $extType = self::EXTENSION_TYPES[strtolower($ext)] ?? null;
        $contentType = is_array($info) ? ($info[2] ?? null) : null;
        if ($extType === null || !$contentType || $contentType === $extType) {
            return null;
        }
        return image_type_to_extension($contentType, false) ?: null;
    }

    /** ブラウザが <img> で表示できる形式の拡張子か(一覧の image: true の元。サムネイルを作れるのは isRasterImageExtension() だけ) */
    public static function isDisplayableImageExtension($ext)
    {
        return in_array(strtolower($ext), BrowserConfig::DISPLAYABLE_IMAGE_EXTENSIONS, true);
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

        // 形式は中身で決める。拡張子に頼るのは、中身を読めない(getimagesize() が失敗する)ときだけ
        $info = @getimagesize($sourceFile);
        $type = $info ? $info[2] : self::EXTENSION_TYPES[$ext];
        if (!self::canLoadType($type)) {
            return null;
        }
        $keepsAlpha = ($type !== IMAGETYPE_JPEG);
        $outExt = $keepsAlpha ? 'png' : 'jpg';
        $cacheFile = $this->cacheDir . md5($sourceFile) . '_' . $mtime . '.' . $outExt;

        if (is_file($cacheFile)) {
            return $cacheFile;
        }

        $this->cleanupStale($sourceFile, $cacheFile);

        if (!$this->canLoad($info, $type)) {
            return null;
        }

        $image = $this->loadImage($sourceFile, $type);
        if ($image === null) {
            return null;
        }

        $resized = $this->resize($image, $keepsAlpha);
        // imagedestroy() は PHP 8.5 で非推奨。変数を手放せば解放される
        unset($image);

        if ($keepsAlpha) {
            $saved = imagepng($resized, $cacheFile, 6);
        } else {
            $saved = imagejpeg($resized, $cacheFile, 85);
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
     * GD で読み込んでもメモリが足りるかを、読み込む前に見積もる(見積もりは gd_can_load_image())。
     * 足りないものは作らずアイコン表示に回し、一覧のほかの画像まで巻き込んで止まるのを防ぐ。
     * 画像のサイズを読めない(壊れた画像など)ときは従来どおり loadImage() に任せる。
     */
    private function canLoad($info, $type)
    {
        if (!$info || empty($info[0]) || empty($info[1])) {
            return true;
        }

        return gd_can_load_image($info[0], $info[1], $type, self::MAX_DIM, self::MAX_DIM);
    }

    private function loadImage($file, $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($file) ?: null;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($file);
                if ($img) {
                    imagesavealpha($img, true);
                }
                return $img ?: null;
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($file) ?: null;
            case IMAGETYPE_WEBP:
                $img = @imagecreatefromwebp($file);
                if ($img) {
                    imagesavealpha($img, true);
                }
                return $img ?: null;
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
