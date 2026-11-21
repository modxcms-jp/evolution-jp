<?php

/**
 * data: 画像（Data URI）の解析・復号・縮小。ファイルシステムも DB も触らない。
 * 保存時の変換（manager/actions/document/mutate_content/functions.php の convertDataUriToFiles()）と、
 * CLI の content:extract-datauri が同じ処理を使う（置き場所は EvoDataUriStore が受け持つ）。
 */
class EvoDataUri
{
    /** 復号したあとの大きさの上限（バイト） */
    const MAX_BYTES = 50 * 1024 * 1024;

    /**
     * HTML の中の、img 要素の src 属性の値になっている Data URI を順に $convert に渡し、戻り値の文字列に置き換える。
     * $convert は function (string $dataUri, array|null $dimensions): string|null。null か空文字なら置き換えない。
     *
     * @param string $content 処理対象のコンテンツ
     * @return string 置き換えたコンテンツ
     */
    public static function replaceImgSrc($content, callable $convert)
    {
        if (empty($content) || !is_string($content)) {
            return $content;
        }

        $offset = 0;
        while (($pos = strpos($content, 'data:', $offset)) !== false) {
            if (!self::isImgSrcValue($content, $pos)) {
                $offset = $pos + 5;
                continue;
            }
            $result = self::extractDataUri($content, $pos);

            if ($result === null) {
                $offset = $pos + 5;
                continue;
            }

            list($dataUri, $endPos) = $result;
            $replacement = $convert($dataUri, self::extractImageDimensions($content, $pos));

            if ($replacement !== null && $replacement !== '') {
                $content = substr_replace($content, $replacement, $pos, $endPos - $pos);
                $offset = $pos + strlen($replacement);
            } else {
                $offset = $endPos;
            }
        }

        return $content;
    }

    /**
     * 値そのものが 1 つの画像の Data URI（前後の空白は許す）のとき、その Data URI を返す。そうでなければ null
     * （画像の TV の値など。img の src の中ではないので、replaceImgSrc() は通らない）
     */
    public static function wholeImageValue($value)
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if (strpos($trimmed, 'data:image/') !== 0) {
            return null;
        }
        $result = self::extractDataUri($trimmed, 0);

        return ($result !== null && $result[1] === strlen($trimmed)) ? $result[0] : null;
    }

    /**
     * Data URI を復号する。縮小はしない
     *
     * @return array{mime:string,extension:string,bytes:string}|array{error:string,level:int,param:mixed}
     *         失敗は error（sprintf のフォーマット。param を 1 つ受ける）・level（ログの重さ。1:情報, 2:警告, 3:エラー）・param
     */
    public static function decode($dataUri)
    {
        if (!preg_match('/^data:([^;,]+)(;base64)?,(.+)$/s', $dataUri, $matches)) {
            return ['error' => 'Invalid Data URI', 'level' => 3, 'param' => null];
        }

        $mimeType = $matches[1];
        $extension = self::getExtensionFromMimeType($mimeType);
        if (!$extension) {
            return ['error' => 'Unsupported MIME type: %s', 'level' => 2, 'param' => $mimeType];
        }

        $fileData = !empty($matches[2])
            ? base64_decode(preg_replace('/\s+/', '', $matches[3]), true)
            : urldecode($matches[3]);
        if ($fileData === false) {
            return ['error' => 'Base64 decode failed', 'level' => 3, 'param' => null];
        }

        $fileSize = strlen($fileData);
        if ($fileSize > self::MAX_BYTES) {
            return ['error' => 'Data URI too large: %d bytes', 'level' => 3, 'param' => $fileSize];
        }

        return ['mime' => $mimeType, 'extension' => ltrim($extension, '.'), 'bytes' => $fileData];
    }

    /**
     * MIME タイプから拡張子を取得
     *
     * @param string $mimeType MIME タイプ
     * @return string|null 拡張子（ドット付き）
     */
    public static function getExtensionFromMimeType($mimeType)
    {
        static $mimeMap = [
            'image/png' => '.png',
            'image/jpeg' => '.jpg',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'image/svg+xml' => '.svg',
            'image/bmp' => '.bmp',
            'application/pdf' => '.pdf',
            'text/plain' => '.txt',
            'text/html' => '.html',
            'text/css' => '.css',
            'application/json' => '.json',
            'application/xml' => '.xml',
        ];

        return $mimeMap[$mimeType] ?? null;
    }


    /**
     * data: が img 要素の src 属性の値の先頭にあるか
     *
     * 本文の文章、ほかの属性の値、script・style・textarea・コメントの中の data: は書き換えない
     *
     * @param string $content コンテンツ全体
     * @param int $dataUriPos data: の位置
     * @return bool
     */
    private static function isImgSrcValue($content, $dataUriPos)
    {
        $before = substr($content, 0, $dataUriPos);
        $tagStart = self::openTagStartAtEnd($before);
        if ($tagStart === null) {
            return false;
        }

        // 属性を 1 つずつ読み、最後が src= のときだけ一致させる（ほかの属性の値の中の src= は値として読み飛ばす）
        return (bool)preg_match(
            '/^<img(?:\s+[^\s"\'<>\/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'<>`=]+))?)*\s+src\s*=\s*["\']?$/i',
            substr($before, $tagStart)
        );
    }

    /**
     * HTML を頭からたどり、末尾が開始タグの途中なら、そのタグの < の位置を返す
     *
     * 末尾がタグの外、script・style・textarea の中身（タグとして読まれない）、コメントの中なら null
     *
     * @param string $html 判定する位置より前の HTML
     * @return int|null
     */
    private static function openTagStartAtEnd($html)
    {
        $pos = 0;
        while (($lt = strpos($html, '<', $pos)) !== false) {
            if (substr($html, $lt, 4) === '<!--') {
                $end = strpos($html, '-->', $lt + 4);
                if ($end === false) {
                    return null;
                }
                $pos = $end + 3;
                continue;
            }
            if (!preg_match('/\G<\/?[a-z]/i', $html, $m, 0, $lt)) {
                $pos = $lt + 1;
                continue;
            }
            $gt = self::tagEndPos($html, $lt);
            if ($gt === null) {
                return $lt;
            }
            $pos = $gt + 1;
            if (preg_match('/\G<(script|style|textarea)\b/i', $html, $m, 0, $lt)) {
                $close = stripos($html, '</' . $m[1], $pos);
                if ($close === false) {
                    return null;
                }
                $pos = $close;
            }
        }

        return null;
    }

    /**
     * タグを閉じる > の位置。= のあとの引用符で囲んだ値の中の > は飛ばす
     *
     * @param string $html HTML
     * @param int $lt タグの < の位置
     * @return int|null 閉じていなければ null
     */
    private static function tagEndPos($html, $lt)
    {
        $len = strlen($html);
        $afterEquals = false;
        for ($i = $lt + 1; $i < $len; $i++) {
            $c = $html[$i];
            if ($c === '>') {
                return $i;
            }
            if ($afterEquals && ($c === '"' || $c === "'")) {
                $i = strpos($html, $c, $i + 1);
                if ($i === false) {
                    return null;
                }
                $afterEquals = false;
                continue;
            }
            if ($c === '=') {
                $afterEquals = true;
            } elseif (!ctype_space($c)) {
                $afterEquals = false;
            }
        }

        return null;
    }

    /**
     * 指定位置から Data URI を抽出
     *
     * @param string $content コンテンツ全体
     * @param int $startPos data: の開始位置
     * @return array|null [dataUri文字列, 終了位置] または null
     */
    private static function extractDataUri($content, $startPos)
    {
        $len = strlen($content);
        $pos = $startPos + 5; // "data:" の長さ

        // MIME タイプの終了（; または ,）を探す
        $mimeEnd = $pos;
        while ($mimeEnd < $len && !in_array($content[$mimeEnd], [';', ',', '"', "'", ' ', '>'])) {
            $mimeEnd++;
        }

        if ($mimeEnd >= $len || in_array($content[$mimeEnd], ['"', "'", ' ', '>'])) {
            return null;
        }

        $mimeType = substr($content, $pos, $mimeEnd - $pos);

        if (empty($mimeType) || strpos($mimeType, '/') === false) {
            return null;
        }

        $pos = $mimeEnd;
        $isBase64 = false;

        // ;base64 チェック
        if ($content[$pos] === ';') {
            if (substr($content, $pos, 8) === ';base64,') {
                $isBase64 = true;
                $pos += 8;
            } else {
                // その他のパラメータをスキップ
                $pos = strpos($content, ',', $pos);
                if ($pos === false || $pos >= $len) {
                    return null;
                }
                $pos++;
            }
        } elseif ($content[$pos] === ',') {
            $pos++;
        } else {
            return null;
        }

        // データ部分の終了を探す
        $dataStart = $pos;
        $dataEnd = self::findDataEnd($content, $pos, $len, $isBase64);

        if (($dataEnd - $dataStart) < 10) {
            return null;
        }

        return [substr($content, $startPos, $dataEnd - $startPos), $dataEnd];
    }

    /**
     * Data URI のデータ部分の終了位置を検索
     *
     * @param string $content コンテンツ
     * @param int $pos 開始位置
     * @param int $len コンテンツ長
     * @param bool $isBase64 Base64 エンコードか
     * @return int 終了位置
     */
    private static function findDataEnd($content, $pos, $len, $isBase64)
    {
        $terminators = ['"', "'", ' ', '>', '<'];
        $validPattern = $isBase64 ? '/[A-Za-z0-9+\/=\s]/' : '/[A-Za-z0-9%\-._~]/';

        while ($pos < $len) {
            $char = $content[$pos];
            if (in_array($char, $terminators) || !preg_match($validPattern, $char)) {
                break;
            }
            $pos++;
        }

        return $pos;
    }

    /**
     * img タグから width と height 属性を抽出
     *
     * @param string $content コンテンツ全体
     * @param int $dataUriPos data: の位置
     * @return array|null ['width' => int, 'height' => int] または null
     */
    private static function extractImageDimensions($content, $dataUriPos)
    {
        $searchStart = max(0, $dataUriPos - 500);
        $searchContent = substr($content, $searchStart, $dataUriPos - $searchStart);

        $imgPos = strrpos($searchContent, '<img');
        if ($imgPos === false) {
            return null;
        }

        $imgStart = $searchStart + $imgPos;
        $imgEnd = strpos($content, '>', $imgStart);
        if ($imgEnd === false) {
            return null;
        }

        $imgTag = substr($content, $imgStart, $imgEnd - $imgStart + 1);
        $dimensions = [];

        foreach (['width', 'height'] as $attr) {
            if (preg_match('/' . $attr . '=["\']?(\d+)["\']?/i', $imgTag, $matches)) {
                $dimensions[$attr] = (int)$matches[1];
            }
        }

        return !empty($dimensions) ? $dimensions : null;
    }

    /**
     * 画像データをリサイズ（縮小のみ）
     *
     * @param string $imageData 元の画像データ
     * @param string $mimeType MIME タイプ
     * @param array $dimensions ['width' => int, 'height' => int]
     * @param int $docid ドキュメント ID（ログ用）
     * @return string|null リサイズ後の画像データ または null
     */
    public static function resize($imageData, $mimeType, $dimensions, $docid)
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        // 向きは JPEG だけが持つ。縮める大きさは表示上の向きで決める
        $info = @getimagesizefromstring($imageData);
        $orientation = (($info[2] ?? null) === IMAGETYPE_JPEG) ? jpeg_exif_orientation_from_string($imageData) : 1;

        // 読み込む前にサイズを調べ、メモリが足りない巨大な画像は縮めずに元のデータのまま保存する
        if ($info && $info[0] > 0 && $info[1] > 0) {
            [$displayWidth, $displayHeight] = image_display_size_by_orientation($info[0], $info[1], $orientation);
            $resize = self::calculateResizeDimensions($displayWidth, $displayHeight, $dimensions);
            if ($resize === null) {
                return $imageData;
            }
            [$loadWidth, $loadHeight] = image_display_size_by_orientation($resize[0], $resize[1], $orientation);
            if (!gd_can_load_image($info[0], $info[1], $info[2] ?? 0, $loadWidth, $loadHeight)) {
                evo()->logEvent(
                    0, 2, 'Image not resized: not enough memory', 'EvoDataUri',
                    ['docid' => $docid, 'mime' => $mimeType, 'width' => $info[0], 'height' => $info[1], 'target_width' => $resize[0], 'target_height' => $resize[1], 'memory_limit' => ini_get('memory_limit')]
                );
                return null;
            }
        }

        $sourceImage = @imagecreatefromstring($imageData);
        if ($sourceImage === false) {
            return null;
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        [$displayWidth, $displayHeight] = image_display_size_by_orientation($originalWidth, $originalHeight, $orientation);
        $resize = self::calculateResizeDimensions($displayWidth, $displayHeight, $dimensions);

        if ($resize === null) {
            return $imageData;
        }

        // 元の画像の向きのまま縮め、あとで向きを反映する
        [$finalWidth, $finalHeight] = image_display_size_by_orientation($resize[0], $resize[1], $orientation);

        // リサイズ実行
        $resizedImage = self::createResizedImage(
            $sourceImage,
            $finalWidth,
            $finalHeight,
            $originalWidth,
            $originalHeight,
            $mimeType
        );

        // imagedestroy() は PHP 8.5 で非推奨。変数を手放せば解放される
        // 向きの反映(回転で縮小した大きさの画像がもう 1 枚できる)の前に、元の画像を手放す
        unset($sourceImage);

        if ($resizedImage !== false) {
            $resizedImage = gd_apply_orientation($resizedImage, $orientation);
        }

        if ($resizedImage === false) {
            return null;
        }

        // 画像を出力
        return self::outputImage($resizedImage, $mimeType);
    }

    /**
     * リサイズ後のサイズを計算
     *
     * @param int $originalWidth 元の幅
     * @param int $originalHeight 元の高さ
     * @param array $dimensions 指定サイズ
     * @return array|null [width, height] または null（リサイズ不要）
     */
    private static function calculateResizeDimensions($originalWidth, $originalHeight, $dimensions)
    {
        // 0 は「指定なし」とみなす
        $requestedWidth = !empty($dimensions['width']) ? $dimensions['width'] : null;
        $requestedHeight = !empty($dimensions['height']) ? $dimensions['height'] : null;
        if ($requestedWidth === null && $requestedHeight === null) {
            return null;
        }

        // 指定の無い側はアスペクト比から求める（丸めずに小数のまま比べる）
        $targetWidth = $requestedWidth ?? $originalWidth * ($requestedHeight / $originalHeight);
        $targetHeight = $requestedHeight ?? $originalHeight * ($requestedWidth / $originalWidth);

        // 拡大しない（縮小のみ）
        if ($targetWidth >= $originalWidth && $targetHeight >= $originalHeight) {
            return null;
        }

        // 指定サイズに収める（アスペクト比維持）
        $ratio = min($targetWidth / $originalWidth, $targetHeight / $originalHeight, 1);

        $finalWidth = max(1, (int)round($originalWidth * $ratio));
        $finalHeight = max(1, (int)round($originalHeight * $ratio));

        // サイズが変わらない場合はリサイズ不要
        if ($finalWidth == $originalWidth && $finalHeight == $originalHeight) {
            return null;
        }

        return [$finalWidth, $finalHeight];
    }

    /**
     * リサイズ後の画像リソースを作成
     *
     * @param \GdImage $sourceImage 元画像リソース
     * @param int $finalWidth リサイズ後の幅
     * @param int $finalHeight リサイズ後の高さ
     * @param int $originalWidth 元の幅
     * @param int $originalHeight 元の高さ
     * @param string $mimeType MIME タイプ
     * @return \GdImage|false リサイズ後の画像リソース
     */
    private static function createResizedImage($sourceImage, $finalWidth, $finalHeight, $originalWidth, $originalHeight, $mimeType)
    {
        $resizedImage = imagecreatetruecolor($finalWidth, $finalHeight);
        if ($resizedImage === false) {
            return false;
        }

        // 透明度をサポート
        if (in_array($mimeType, ['image/png', 'image/gif', 'image/webp'])) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
            imagefilledrectangle($resizedImage, 0, 0, $finalWidth, $finalHeight, $transparent);
        }

        $success = imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $finalWidth, $finalHeight,
            $originalWidth, $originalHeight
        );

        if (!$success) {
            return false;
        }

        return $resizedImage;
    }

    /**
     * 画像リソースを出力バッファに保存
     *
     * @param \GdImage $image 画像リソース
     * @param string $mimeType MIME タイプ
     * @return string|null 画像データ
     */
    private static function outputImage($image, $mimeType)
    {
        ob_start();
        $success = false;

        switch ($mimeType) {
            case 'image/jpeg':
                $success = imagejpeg($image, null, 90);
                break;
            case 'image/png':
                $success = imagepng($image, null, 6);
                break;
            case 'image/gif':
                $success = imagegif($image);
                break;
            case 'image/webp':
                $success = function_exists('imagewebp') && imagewebp($image, null, 90);
                break;
            case 'image/bmp':
                $success = function_exists('imagebmp') && imagebmp($image);
                break;
        }

        $output = ob_get_clean();
        return $success ? $output : null;
    }
}
