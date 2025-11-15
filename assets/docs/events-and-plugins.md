# イベントとプラグイン統合

## SystemEvent オブジェクト

- `SystemEvent` はイベント名・パラメータ・収集された出力・伝播フラグを保持します。プラグインは UI アラートの追加、出力の追記、伝播停止などをこの API 経由で行います。

## ディスパッチの仕組み

- `DocumentParser::invokeEvent()` がプラグインキャッシュからリスナーを解決し、既定プロパティを注入した上でプラグインコードを実行します。`$modx->event->stopPropagation()` が呼ばれると後続のプラグインがスキップされます。
- プラグイン定義とイベント紐付けは `synccache::buildCache()` が生成する `siteCache.idx.php` に保存され、データベースへの追加アクセスを避けます。
- キャッシュにはプラグインコード・プロパティ・エラーレポート設定も含まれ、再利用時の初期化コストを削減します。

## フロントエンドイベントの時系列

1. `OnWebPageInit`: リクエストのサニタイズとリソース解決が完了した直後に実行されます。
2. `OnLoadWebPageCache`: キャッシュ済みページを利用する前に発火し、キャッシュ参照を抑止する機会を提供します。
3. `OnLoadWebDocument`: ドキュメントオブジェクトが構築された直後で、テンプレート解析の前に実行されます。
4. `OnParseDocument`: `parseDocumentSource()` の各ループで呼ばれ、`documentOutput` を検査・変更できます。
5. `OnLogPageHit`: 訪問ログ取得が有効な場合に `outputContent()` 内で呼び出されます。
6. `OnWebPagePrerender`: レスポンス出力の直前で、最終的なプレースホルダー調整に対応します。
7. `OnBeforeSaveWebPageCache`: `postProcess()` 内でキャッシュ書き込みの前に呼ばれ、キャッシュ処理をスキップする判断を行えます。
8. `OnWebPageComplete`: レスポンス完了後、静的キャッシュ経由のショートサーキットも含めて発火します。

## プラグイン実装のヒント

- ヘルパーの `evo()->invokeEvent()` を利用し、常に共有の `DocumentParser` インスタンスを介してイベントを発行する。
- 同一イベントで複数回実行される可能性があるため、`$modx->event->name` や `$modx->event->activePlugin` を確認して副作用を制御する。
- テンプレートへデータを渡す場合は `$modx->setPlaceholder()` を用いてプレースホルダーに値を仕込み、標準の解析サイクルに乗せる。
