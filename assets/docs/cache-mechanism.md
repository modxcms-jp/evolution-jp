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

## TTL と失効

- `config('cache_ttl')` が設定されている場合、`getCache()` がファイルの更新時刻を比較し、期限切れのキャッシュを自動削除します。次のアクセスで再生成が行われます。
- 公開・非公開の切り替えは `cacheRefreshTime` を更新し、`updatePublishStatus()` によってキャッシュ状態が再確認されます。
