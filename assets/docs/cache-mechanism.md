# キャッシュメカニズム

## ページキャッシュ方式

- `DocumentParser::getCache()` は `core/cache/<uaType>/` 以下のファイルを読み込み、URL ハッシュ（タイプ 2）または `docid_<id>`（タイプ 1）でページを判定します。TTL の検証や管理画面セッションの除外、シリアライズ済みドキュメントオブジェクトの復元をここで行います。
- `postProcess()` はレンダー完了後にキャッシュを書き戻します。タイプ 1 はメタ情報とコンテンツをまとめて保存し、タイプ 2 は MIME タイプと生の出力を URI ハッシュで管理します。404 などのステータスはファイル名として反映されます。
- プレビューのリクエスト（管理画面にログイン中で、GET の `mgr_preview`（管理画面からのプレビュー）、または `revision` があるとき）は、POST と同じく `executeParser()` の冒頭で `cache_type=0` にし、ページキャッシュを読まず書きません。`getCache()` は `cache_type` が 0 なら空を返し、`postProcess()` もキャッシュを書かないため、プレビューの内容が通常のキャッシュに混ざりません。ログインしていないときは `mgr_preview` を無視し、通常どおりのキャッシュで配信します。
- キャッシュはユーザーエージェントごとに `pages` / `pc` / `smartphone` / `tablet` / `mobile` といったバケットに分割され、必要に応じてディレクトリを生成します。

## 静的 HTML キャッシュ

- `get_static_pages()` は `temp/public_html/` の事前生成ファイルを配信し、動的パーサーの手前でレスポンスを返します。スニペットやプレースホルダーなど一部の要素は、必要に応じて最新の状態で評価される場合がありますが、通常は `parseDocumentSource()` は実行されません。

## サイトキャッシュインデックス

- `synccache::buildCache()` が設定・コンテンツマップ・要素キャッシュを再生成し、`config.siteCache.idx.php` やエイリアスマップ、ドキュメントマップ、要素別キャッシュをまとめて書き出します。`OnBeforeCacheUpdate` / `OnCacheUpdate` イベントでプラグインフックが提供されます。

## キャッシュクリア

- `DocumentParser::clearCache()` はドキュメント ID や `full`、`['target' => 'pagecache']` のようなオプションを受け取り、対象ページのキャッシュをユーザーエージェントバケット横断で削除します。その後 `synccache::emptyCache()` を呼び出してサイトキャッシュを再生成します。
- コンテンツや設定を変更する際は手動でファイルを削除せず、`evo()->clearCache()` を利用してください。

## basicConfig.php とセッション名

- `index.php` の早い返し（フルページキャッシュと条件付き GET）は、`temp/cache/basicConfig.php` の `$site_sessionname` でセッションを始め、ログイン中の人を見分けます。早い返しを軽く保つため、`index.php` の側で名前を計算しません。
- 名前は Web サーバーから見た設置パスで決まります（`cms_session_name()`）。Web で書き出すときはこの名前を書き、CLI（`php evo.php cache:clear` など）ではパスが違うことがあるので計算せず、今ある `basicConfig.php` の名前を引き継ぎます。
- 設置パスを変えたサイトで CLI だけで作り直すと、前のパスの名前が残ります。Web 側で一度作り直してください（リソースの保存や「サイトをリフレッシュ」など）。
- `basicConfig.php` が無い状態で CLI が書き出すと名前が分からないので、`$cache_type` と `$conditional_get` を書かず、早い返しを使いません。`cache_type=2` では毎回ページを生成します。次に Web 側でキャッシュを作り直したときに元に戻ります（早い返しを使う設定のときは、システムログに警告を残します）。デプロイなどで `temp/cache/` を消してから CLI で作り直さないでください。
- 早い返しの経路も、`session_start()` の前にセッションの cookie の属性（`HttpOnly`・`SameSite`・寿命）を揃えます。`init::session_cookie_params()` の値のうち path 以外を `basicConfig.php` の `$site_cookie_params` に書き出し、早い返しはそのまま `session_set_cookie_params()` に渡します（読むファイルを増やさず、値の写しを作らないため）。`$site_cookie_params` は CLI からの書き出しでも書かれます。
- `secure` は接続ごとに決まるので、早い返しが `HTTPS` が `on` か `X-Forwarded-Proto` が `https` かで決めます。通常の経路（`init::is_ssl()`）より狭く、`HTTP_HTTPS`・`HTTP_X_SAKURA_HTTPS`・`HTTPS=1`・`autoload.php` で立てる `HTTPS` は見ません。これらだけで https と分かる環境では、早い返しの cookie に `secure` が付きません。
- path は書き出しません。早い返しは今までどおり php.ini の既定（`/`）で、サブディレクトリ設置でも通常の経路（`/sub/` など）とは揃えません（作り直した要求と訪問者の設置パスが違っても cookie が戻るようにするため）。`$site_cookie_params` が無い（古い `basicConfig.php`）ときは属性を付けず、次に書き出したときから効きます。
- 公開側のセッションは、何も書かれなければリクエストの終わりに消します（`index.php` の `evo_drop_empty_front_session()`）。対象は、そのリクエストで新しく作ったセッション（リクエストにセッションの cookie が無い）で、MODX が自分で入れる値（`evo_sid_hash`）のほかに何も入っていないものです。セッションを destroy し、応答の `Set-Cookie` からセッションの cookie を外すので、cookie も送りません。ログイン・フォーム・スニペットが何か書いたセッション、cookie を持ってきた訪問者のセッション、管理画面のリクエストは消しません。キャッシュを返す経路は、返す前（`session_write_close()` の前）に呼び、通常の経路（`DocumentParser::executeParser()` の中のスニペットなどが終わった後）と 304 を返す経路は、`register_shutdown_function` で呼びます。`ob_start()` の出力を flush する前なので cookie を外せます。ヘッダーを送り済みのときは何もしません。
- セッションの置き場所は PHP の既定（`session.save_path`）のままで、有効期限は `session.gc_maxlifetime` を 30 日にしています。`/tmp` を他のサイトと共有するサーバーでは、他のサイトの GC 設定の影響を受け、ファイルが早く消えることがあります。
- `saveToFile()` は書き出した直後に、そのファイルの OPcache を `opcache_invalidate()` で捨てます。`revalidate_freq` の間は古い `basicConfig.php` が使われ、保存の直後の条件付き GET が古い `ETag` に 304 を返すのを防ぎます（CLI からの書き出しは Web の OPcache に届きません）。

## 条件付き GET とキャッシュ有効（cacheable）

- 条件付き GET（`conditional_get`）の 304 は、`index.php` の早い返しがリソースを読む前に、サイト全体の更新時刻だけで決めます。リソースごとの設定は見られません。
- そのため `prepareResponse()` は、リソースを読んだ直後（スニペットと、`OnLoadWebDocument` 以降のプラグインが動く前）に、「キャッシュ有効」（`cacheable`）を外したページの応答から `ETag` と `Last-Modified` を外します。ブラウザーの手元に条件が残らず、再訪のたびにページを作ります。`Cache-Control: no-store` は付けません（「戻る」でのフォームの入力を妨げないため）。
- すでにブラウザーに残っている分は、サイトのキャッシュが作り直される（保存・リフレッシュ・アップデートなど）か、ブラウザーがその分を捨てるまで 304 になります。`[!…!]` を含むだけのページ（`cacheable` は有効）は対象外です。

## TTL と失効

- `config('cache_ttl')` が設定されている場合、`getCache()` がファイルの更新時刻を比較し、期限切れのキャッシュを自動削除します。次のアクセスで再生成が行われます。
- 公開・非公開の切り替えは `cacheRefreshTime` を更新し、`updatePublishStatus()` によってキャッシュ状態が再確認されます。
