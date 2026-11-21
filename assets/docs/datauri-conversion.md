# Data URI 自動ファイル化機能

## 概要

リソース保存時に本文の Data URI スキーム (`data:image/png;base64,...`) の画像を検出し、ファイルに変換する機能。エディターで画像を貼り付けると Data URI として埋め込まれ、HTML が肥大化する。

TinyMCE 7 では、貼り付け・ドロップした画像はエディター側でファイルピッカーの API にアップロードし、本文には URL を入れる（置き場所は `assets/docs/file-storage.md` の「エディターに貼り付けた画像の置き場所」、仕組みは `assets/plugins/tinymce7/docs/TECHNICAL.md`）。アップロードが有効なときは、HTML の貼り付けやコード表示で入れた `data:` の画像も、エディターがアップロードして `images/年/月/` に置く。ファイルピッカーを使えない利用者と、フロントのエディターでは、貼り付けた画像を受けない。

保存時のこの変換（`images/年/月/pasted-doc-{文書ID}-{連番}.拡張子`）が効くのは、エディターを通らない経路だけ。リッチテキストエディターを使わない文書、API や外部からの書き込みなど。すでに本文に入っている `data:` の画像も対象になる。TinyMCE 7 は、開いたときから本文にある `data:` 画像をアップロードしないので、そのまま保存すればこの変換でファイルになる。既存のリソースは、利用者に「リソースを開いて、そのまま保存してください」と伝えれば直る。

## 対象と保存先

- 対象フィールド: `content`（本文）だけ。`introtext`（要約）は対象にしない。ウェブリンク（`type` が `reference`）の本文も対象にしない
- 対象の位置: `<img>` 要素の `src` 属性の値になっている Data URI だけ（`isImgSrcValue()`）。属性を 1 つずつ読むので、`data-src` やほかの属性の値の中の `src=` は対象にならない。本文の文章、`<script>`・`<style>`・`<textarea>` の中、HTML コメントの中の `data:` は書き換えない。本文を頭からタグ単位でたどり、引用符で囲んだ属性の値は読み飛ばすので、属性の値の中に `<textarea>` などの文字があっても要素とは見なさない（`openTagStartAtEnd()`）
- 新規のリソースは、INSERT で ID が決まったあとに変換し、本文だけを UPDATE し直す（`convertDataUriAfterInsert()`）。既存のリソースは保存の前に変換する。どちらも同じ `convertDataUriToFiles()` を通る
- 保存先: ファイルピッカーの画像の置き場所 `{rb_base_dir}/images/年/月/`（年月は保存のときのサイトの時刻）。名前は `pasted-doc-{リソースID}-{連番}.拡張子`（例 `pasted-doc-12-1.png`）。連番は、そのフォルダで同じ ID の画像の最大の番号 + 1（1 から。消えた番号は使い回さない。ブラウザーや CDN が古い画像を出し続けないため）。置き場所・名前の確保・拡張子（`upload_images`）・大きさ（`upload_maxsize`）・`OnFileBrowserUpload`・管理操作ログ（`Uploaded File`）は、エディターに貼り付けた画像と同じ `FileStore` を通る（`assets/docs/file-storage.md`）。拡張子や大きさの決まりに合わない Data URI（既定では webp など）は、置き換えずに本文に残し、システムログに残す
- ロールにファイルのアップロードの権限（`file_upload`）が無いときは、変換せずに Data URI のまま残し、システムログに残す（ソースコードの画面で `data:` を書いて保存しても、ピッカーの置き場所にファイルができない抜け道を作らないため。CLI は管理者の操作なので見ない）
- 新規のリソースは、`OnBeforeDocFormSave` の時点ではまだ `data:` のまま（INSERT のあとに変換する）。プラグインがそこで本文を書き換えると、書き換えたあとの本文が変換の対象になる
- ファイルは DB の更新が済んでから確定する（管理操作ログに書く）。更新に失敗したら、書いたファイルを消す
- images のフォルダの実体が `rb_base_dir` の外を指すとき（シンボリックリンクなど）は、ピッカーと同じく範囲を作らず、変換せずに Data URI のまま残して、システムログに理由を残す。CLI は何も変えずに終了コード 1 で終わる
- 以前の版が `content/images/datauri/{リソースID}/` に書いたファイルと、本文のその URL は動かさず、書き換えない
- 復号後の大きさの上限: 50MB（`EvoDataUri::MAX_BYTES`）。保存時の変換では、さらにピッカーの `upload_maxsize`（空なら 5000000）が効く。これを超える `data:` は、保存時には変換されずに残る（CLI は 50MB まで移せる）
- 対応 MIME: `image/png`, `jpeg`, `gif`, `webp`, `svg+xml`, `bmp`, `application/pdf`, `text/plain`, `text/html`, `text/css`, `application/json`, `application/xml`

## 画像リサイズ

`<img>` タグの `width` / `height` 属性が元画像より小さい場合のみ縮小（拡大しない）。アスペクト比維持、透明度サポート（PNG/GIF/WebP）。JPEG は EXIF の Orientation を画素に反映して書き出す（書き出すと EXIF は残らないため）。大きさの判定も、向きを反映した幅・高さで行う（`jpeg_exif_orientation_from_string()` / `gd_apply_orientation()`、`manager/includes/helpers.php`）。縮小は `EvoDataUri::resize()` が行い、保存時の変換と CLI が同じ処理を使う（バイト列をファイルに書く `FileStore::bytesWriter()` 自体は縮小しない）。

## 設定

- システム設定キー: `convert_datauri_to_file`（`1`: 有効（デフォルト）、`0`: 無効）
- DB に行が無い既存インストールでも、読み込み時に `default.config.php` の値で補われる（グローバル設定を保存しなくてよい）

## 実装ファイル

- `manager/includes/datauri.class.inc.php`（`EvoDataUri`）— `<img>` の `src` の Data URI の検出・復号・縮小。ファイルも DB も触らない
- `manager/includes/datauri_store.class.inc.php`（`EvoDataUriStore`）— ファイルピッカーの置き場所へ書く（`FileStore` を通す）
- `manager/actions/document/mutate_content/functions.php` — `convertDataUriToFiles()`（保存時の変換の入口）
- `manager/processors/document/save_resource.processor.php` — リソース保存時の呼び出し
- `manager/includes/cli/commands/content-extract-datauri.php` — 既存のリソースをまとめて移す CLI
- `manager/includes/default.config.php` — デフォルト設定
- `manager/includes/lang/japanese-utf8.inc.php` / `english.inc.php` — 言語ファイル

## 動作フロー

リソース保存 → 既存のリソースは `getInputValues()`、新規のリソースは INSERT のあと → 設定チェック → `convertDataUriToFiles()` で `<img>` の `src` の Data URI を検出（`EvoDataUri::replaceImgSrc()`）→ `<img>` タグから寸法抽出 → 復号・必要に応じリサイズ・拡張子と大きさの確かめ（`EvoDataUriStore::prepare()`）→ `images/年/月/` に保存（`EvoDataUriStore::place()`）→ パスに置換 → 管理操作ログ（`record()`）

## 既存のリソースをまとめて移す（CLI）

`php evo.php content:extract-datauri [--dry-run] [--ids=1,2,3] [--limit=N]`（使い方と出力は `manager/includes/cli/README.md`）。

- 対象: `site_content` の `content`・`introtext`、`site_tmplvar_contentvalues` の `value`、`site_revision` の下書き（`element='resource'`、`version=0`）の本文・要約・TV。スニペット・テンプレートなどのコードの表は対象外
- 位置: 本文・要約・下書きは `<img>` の `src`。TV の値は、`<img>` の `src` に加えて、値そのものが 1 つの `data:image/...` のとき（画像の TV）も、その値を URL に置き換える
- 置き場所: 保存時の変換と同じ `images/年/月/pasted-doc-{リソースID}-{連番}.拡張子`。年月は、そのリソースの更新日時（`editedon`。下書きは下書きの更新日時）で決めるので、何度流しても同じ場所になる
- 大きさの上限は 50MB（ピッカーの `upload_maxsize` とは別）。拡張子は `upload_images` に従い、外れるものは移さずに一覧へ理由を出す。縮小は保存時の変換と同じ
- 書き換えの前に、DB の控え（スナップショット）を自動で取る。取れなければ何もせず終了コード 1。`--dry-run` では取らない。控えの世代の上限（既定 10）を超えると、古い自動の控えが消える
- 書き戻しは読んだときの値（`editedon`・元の値・下書きの `checksum`）を条件に入れ、実行中に同じ文書が書き換えられていたら、その文書は飛ばして書いたファイルを消す（終了コード 1。もう一度流せば移せる）
- ファイルを書いてから DB を直す。DB の更新に失敗したら、そのファイルを消す。`editedon` は変えず、直したあとにページキャッシュを消す。2 回目は何も変わらない

## トラブルシューティング

変換されない場合: (1) `convert_datauri_to_file` が `1` か確認 (2) システムログ確認 (3) `{rb_base_dir}/images/` の書き込み権限確認。50MB 超・非対応 MIME・ピッカーの拡張子（`upload_images`）と大きさ（`upload_maxsize`）に合わない Data URI は変換されずシステムログに記録される。
