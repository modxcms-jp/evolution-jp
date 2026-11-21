# ファイルの置き場所とシンボリックリンク

ファイルブラウザ（`manager/media/browser/evo/`）や zip の展開、キャッシュ・エクスポート・インストーラーのフォルダ削除など、ファイルを読む・書く・消す処理のシンボリックリンクの扱いと、ファイルの URL（`rb_base_url`）の決まり。

本体はシンボリックリンクを作らない。zip の中のリンクは普通のファイルとして書き、アップロードでもリンクは作れない。そのため、あるリンクは運用者が置いたものとして扱う。

## 決まり

1. **境界は、設定で決めたルートの実体で比べる。** ルートは `rb_base_dir`（ピッカー）と `filemanager_path`（管理モード）。ルートそのものがリンクなら、運用者が置き場所を選んだ結果としてたどる。種類のフォルダ（`rb_base_dir/images` など）は設定ではないので境界にしない。ピッカーは、種類のフォルダの実体が `rb_base_dir` の実体の中にないときは断る（`PickerScope::authorize()`、エラーコード `invalid_root`）。
2. **ルートの中のリンクは、外を指すならたどらない。** 一覧・ダウンロード・表示・コピーなどは `PathResolver` が、実体をルートの実体と比べて断る。
3. **消すときは、リンクそのものを消し、リンク先の中身を消しに行かない。** フォルダの削除は `Filesystem::deleteTree()`（`manager/includes/filesystem.class.inc.php`）で、ファイルブラウザのほか、キャッシュの削除・静的エクスポートの出力先の掃除・インストーラーの後始末もこれを使う。中へ入ってよい範囲は渡されたフォルダの実体の中だけ。`$keepRoot = true`（中身だけ消す）のときに限り、ルート自体がリンクなら決まり 1 と同じく運用者が選んだ置き場所としてたどる。
4. **書くときは、リンクを通して外へ書かない。** 書く先と親がリンクでないことを確かめる（zip の展開のぶら下がったリンクなど）。

`temp/` は外から読ませない前提の置き場所。`temp/thumbs/` には、ファイルブラウザが見せる範囲（管理モードではファイル管理のルート）の画像の縮小版が入る。nginx では `temp/` への外からの要求を止める。サムネイルの名前は `<元ファイルのパスの md5>_<更新時刻>_v<版>` で、作り方を変えたら `ThumbnailService::CACHE_VERSION` を上げる（JPEG は EXIF の Orientation を反映して作る）。版はサムネイルの URL（`v`）にも入るので、ブラウザーのキャッシュも切り替わる。アップロードを `image_limit_width` に縮小して保存するときも、同じく向きを反映する（上限は向き反映後の幅で判定。縮小しない画像は元のファイルのまま置く）。向きの読み取りは exif 拡張が前提で、無い環境では反映しない（README の動作要件）。

## エディターに貼り付けた画像の置き場所

エディター（TinyMCE 7）に貼り付けた・ドロップした画像は、ファイルピッカーの操作 `uploadPasted`（`api.php`）で `{rb_base_dir}/images/年/月/` に保存し、本文にはその URL を入れる。年・月と時刻は、サイトの時刻（`server_offset_time` を足した今の時刻）。

- フォルダーは、無ければ 1 階層ずつ作る（`FileStore::ensureFolderPath()`）。ピッカーの「フォルダーを作る」と同じ決まりで、守る場所には作らず、同名のファイルがあれば作らない。パーミッションは `new_folder_permissions`、管理操作ログには `[picker] Created Folder` を 1 階層ずつ残す
- 名前は、貼り付けた画像（名前が `image.拡張子`）なら `pasted-年月日-時分秒.拡張子`（例 `pasted-20261121-182249.png`）。ファイルをドロップしたときなど、名前のあるファイルは元の名前のまま。同名があれば `名前(1).拡張子`（ピッカーのアップロードと同じ）
- 拡張子（`upload_images`）・大きさ（`upload_maxsize`）・名前の無害化・`image_limit_width` による縮小・ファイルのパーミッション（`new_file_permissions`）・`OnFileBrowserUpload` の発火は、ピッカーのアップロード（`upload`）と同じ
- 画像の置き場所はこの 1 か所に決まっていて、リクエストでは変えられない。種類が `images` 以外の要求は断る
- 月のフォルダーは、保存できるファイルが 1 つ以上あるときだけ作る（拡張子・大きさの確かめ `FileStore::checkNewFile()` が通ってから）。確かめで全部はねられた要求では、空のフォルダーを作らない
- ファイルを置く操作（ピッカーの `upload`・`uploadPasted`・`saveImage` の `mode=copy`）は、管理画面のセッションではロールの権限 `file_upload` を求め、無ければ 403 `forbidden`。判定は `BrowserScope::allows()`（ピッカーはロールから読み直す。ウェブユーザーのセッションと管理モードは対象外）に寄せ、`api.php` の振り分けが操作の表の `perm` で見る（`saveImage` は `mode` が分かる操作の中）。画面は `BrowserView::jsConfig()` の `canUpload` でボタン・ドロップ・別名保存を無効にし、TinyMCE は `canUploadPasted()` が false になる。削除・名前の変更・移動・複製・新規フォルダー・`saveImage` の上書きは求めない
- 管理画面のエディターで、ピッカーを使えない利用者（`use_browser` が無効など）には貼り付けのアップロードを出さず、貼り付けた画像は受けない（本文に `data:` の画像が入らない）。フロントのエディターでは、貼り付けた画像を受けない
- 保存する名前は、空きを `fopen('xb')` で排他的に確保（`FileStore::createFreeFile()`）してから、その空のファイルへ書く。ファイルブラウザの copy・saveImage（別名）も同じ関数を使う

保存の処理は `manager/media/browser/evo/lib/FileStore.php` にあり、`api.php` を読み込まなくても（CLI などから）使える。範囲は `PickerScope::forType()`（セッションを見ない）で作り、`PathResolver` と合わせて渡す。アップロードの経路は `FileStore::uploadedWriter()`（`image_limit_width` による縮小を含む）、バイト列の経路は `FileStore::bytesWriter()` で書く。バイト列の経路は縮小しない（足すなら、DocumentParser の縮小（今は private の `resizeImage()`）を公開する入口を設けてから、`bytesWriter()` で書いた後に通す）。管理操作ログの `Uploaded File` は 1 回の操作でまとめて書くため、呼ぶ側が `ChangeLog::record()` で記録する（`OnFileBrowserUpload` は `saveNewFile()` が発火する）。

## 外の置き場所（EFS・NFS など）の使い方

ルートを丸ごと外へ向ける。次のどれかを使う。

- `content/` に外の置き場所を直にマウントする
- `content/` ごとシンボリックリンクにする
- `rb_base_dir` で外の置き場所を直に指定する

`content/images` など、種類のフォルダだけを外へのリンクにする形は使えない（ピッカーが断る）。

## rb_base_url（ファイルの URL の頭）の値

`rb_base_url` には、サイトの URL からの相対パス（例 `content/`）を保存する。自サイトのホストを含む値（`http://example.com/content/`）や、設置パスから始まるパス（サブディレクトリ設置の `/sub/content/`）は、保存時と読むとき（`BrowserConfig`）に `init::normalize_rb_base_url()` で相対パスに直す。別ホスト（CDN など）の値はそのまま使う。サイトの直下は `/`。

ユーザー設定の `rb_base_url` も、保存時に同じく直す。

- 「自サイト」かは、サイトの URL（`config('site_url')`、`assets/docs/site-url.md`）のホストと比べて決める。管理画面を開いているホストと違うホストの URL（リバースプロキシの内側・トンネルなど）は、別ホストとしてそのまま残る。
- サブディレクトリ設置で、設置パスの外を指すルートからのパス（`/sub/` 設置の `/content/`）は正しく扱えない。ホスト付きの URL で書けば扱える（入力エラーにするのは #1151 の候補）。
