<?php

/**
 * ファイルブラウザが扱う範囲（ピッカー・管理モード）ごとに答えが変わるものを並べた interface。
 * 範囲が何かを尋ねるメソッド（isManage() など）は置かない。範囲ごとに違うのは、ここに並ぶ yes / no の問いと値だけ。
 * 共通の処理は、範囲を見て分けずに、この問いの答えを受け取るだけにする。
 */
interface BrowserScope
{
    /**
     * この範囲のルートのフォルダ（PathResolver と guard() の基準）
     *
     * @return string
     */
    public function baseDir();

    /**
     * ルートと守る場所を持つ PathGuard
     *
     * @return PathGuard
     */
    public function guard();

    /**
     * 一覧に出し、ダウンロードの対象にしてよいファイルか
     *
     * @param string $name ファイル名（フォルダを含まない）
     * @return bool
     */
    public function listsFile($name);

    /**
     * 一覧とツリーに出してよいフォルダか
     *
     * @param string $name フォルダ名
     * @return bool
     */
    public function listsFolder($name);

    /**
     * 新しい名前のファイルを作ってよい拡張子か（アップロード・画像の別名保存）。
     * 名前は DocumentParser::sanitizeFilename() を通した後のものを渡す。
     *
     * @param string $name
     * @return bool
     */
    public function isAllowedNewName($name);

    /**
     * ファイルの名前を $oldName から $newName に変えてよいか
     *
     * @param string $oldName
     * @param string $newName sanitizeFilename() を通した後のもの
     * @return bool
     */
    public function isAllowedRename($oldName, $newName);

    /**
     * ファイルを複製してよいか
     *
     * @param string $name 複製先の名前（元の名前を sanitizeFilename() に通したもの）
     * @return bool
     */
    public function isAllowedCopy($name);

    /**
     * アップロードの上限（バイト）。null は本体では制限しない（PHP の上限だけが効く）
     *
     * @return int|null
     */
    public function maxUploadSize();

    /**
     * 複数のファイルを zip にして送るときの、ファイル名の先頭の部分
     *
     * @return string
     */
    public function downloadName();

    /**
     * ファイルを保存したあとに発火するイベントの名前（アップロード・画像の別名保存）。
     * ピッカーは OnFileBrowserUpload、管理モードは旧ファイル管理と同じ OnFileManagerUpload
     *
     * @return string
     */
    public function uploadEvent();

    /**
     * この範囲にだけある操作の表（action の名前 => ['handler' => 関数名, 'csrf' => bool, 'post' => bool]）。
     * 共通の操作は api.php が持つ。表に無い action は 400 になる（操作の中で範囲を確かめない）。
     *
     * @return array
     */
    public function actions();
}
