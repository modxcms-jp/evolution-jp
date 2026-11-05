<?php

/**
 * 範囲（ピッカー・管理モード）ごとに答えが変わるものを並べた interface。範囲が何かを尋ねるメソッド（isManage() など）は置かない。
 * 共通の処理は範囲を見て分けず、この問いの答えを受け取るだけにする。
 */
interface BrowserScope
{
    public function baseDir();

    public function guard();

    /** 一覧に出し、ダウンロードの対象にしてよいファイルか（$name はフォルダを含まない） */
    public function listsFile($name);

    public function listsFolder($name);

    /** 新しい名前のファイルを作ってよい拡張子か（アップロード・画像の別名保存）。名前は sanitizeFilename() を通した後のもの */
    public function isAllowedNewName($name);

    /** ファイルの名前を $oldName から $newName（sanitizeFilename() を通した後）に変えてよいか */
    public function isAllowedRename($oldName, $newName);

    /** ファイルを複製してよいか（$newName は元の名前を sanitizeFilename() に通したもので、先頭のドットは落ちている） */
    public function isAllowedCopy($sourceName, $newName);

    /** アップロードの上限（バイト）。null は本体では制限しない（PHP の上限だけが効く） */
    public function maxUploadSize();

    public function downloadName();

    /** 保存したあとに発火するイベント名（アップロード・画像の別名保存）。ピッカーは OnFileBrowserUpload、管理モードは旧ファイル管理と同じ OnFileManagerUpload */
    public function uploadEvent();

    public function name();

    /** 記録に書くパスの頭に付ける部分（ピッカーは "images/" などの種類のフォルダ、管理モードは空。相対パスのままでは種類が分からなくなるため） */
    public function logRoot();

    public function logLabel();

    /** この範囲にだけある操作の表（action => ['handler' => 関数名, 'csrf' => bool, 'post' => bool]）。共通の操作は api.php が持つ */
    public function actions();
}
