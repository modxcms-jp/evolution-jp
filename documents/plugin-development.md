# プラグイン開発ガイド

Evolution CMS JP Edition でプラグインを作成する際のベストプラクティスと作業手順をまとめたガイドです。`documents/events-and-plugins.md` を併読しながら、どのイベントにフックすべきかを判断してください。

## 前提条件

- DocumentParser の処理フローとイベントライフサイクルを理解していること。
- `manager/includes/helpers.php` のヘルパー関数（`evo()`、`db()`、`manager()`）の利用方法を把握していること。
- プラグインを格納するためのバックエンド権限（`Elements > Plugins` 画面へのアクセス権）を保有していること。

## 開発フロー

1. **要件整理**: 対応したいイベントと副作用（プレースホルダー設定、レスポンス加工、キャッシュ制御など）を洗い出す。
2. **イベント選定**: `documents/events-and-plugins.md` のタイムラインを参照し、目的に合致するイベントを選択する。必要であれば複数イベントを組み合わせる。
3. **依存要素の確認**: テンプレートタグや TV を操作する場合は `documents/template-system.md` を参照し、解析順序と利用可能なプレースホルダーを確認する。
4. **実装**: プラグインコード内では `evo()` を介して `$modx`（`DocumentParser`）インスタンスへアクセスする。DB 操作が必要な場合は `db()`、管理画面 API を利用する場合は `manager()` を使用する。
5. **キャッシュ戦略の決定**: コンテンツを変更する処理では `documents/cache-mechanism.md` を参照し、キャッシュのクリア条件や `evo()->clearCache()` の呼び出し有無を検討する。
6. **テスト**: フロントエンド・バックエンドの双方で対象イベントが期待通りに発火しているかを確認する。`OnPluginFormSave` などの管理画面イベントも活用して自動設定を行える。
7. **デプロイと保守**: プラグインをエクスポートしてバージョン管理し、バージョン番号や変更履歴を整備する。

## プラグインの雛形

```php
<?php
if (!defined('MODX_BASE_PATH')) {
    exit('No direct access allowed.');
}

$modx = evo();
$event = $modx->event;

switch ($event->name) {
    case 'OnWebPageInit':
        // リクエスト初期化直後の処理
        break;

    case 'OnParseDocument':
        // $modx->documentOutput を直接編集せず、プレースホルダーを介して変更する
        $modx->setPlaceholder('example', 'value');
        break;

    case 'OnManagerPageInit':
        // 管理画面での初期化処理
        break;
}
```

- `MODX_BASE_PATH` チェックによりプラグインファイルへの直接アクセスを防ぎます。
- `$modx->event->name` で現在処理中のイベントを判別し、ケースごとにロジックを分岐します。
- 複数イベントへ対応する場合は `switch` 文または `if` 文で分岐し、不要な処理を避けます。

## プロパティ定義

- プラグインはプロパティをキーと値のペアで受け取ります。`Elements > Plugins` で JSON 形式または `name==value||` 形式を指定できます。
- プロパティは `$modx->event->params` から取得するか、`extract($modx->event->params);` で展開します。
- デフォルト値を設定する場合は `manager/includes/default.plugin.config.inc.php` の形式に倣い、配列で保持して `array_merge()` するパターンが一般的です。

## キャッシュと副作用の管理

- コンテンツを変更する場合は `OnBeforeSaveWebPageCache` で `stopPropagation()` を呼び出し、後続プラグインとの競合を防ぐことを検討します。
- 静的ファイルの生成や外部 API 呼び出しは `OnWebPagePrerender` や `OnManagerPageAfterSave` のような終端イベントで行い、リクエストの主要処理を阻害しないようにします。
- キャッシュクリアは必要最低限に留め、`evo()->clearCache(array('target' => 'pagecache'))` のように対象を絞るとパフォーマンス低下を避けられます。

## デバッグとログ

- `$modx->logEvent()` を用いると、管理画面のイベントログにメッセージを出力できます。`source` 引数でプラグイン名を渡すと識別しやすくなります。
- ローカル開発では `config.inc.php` の `error_reporting` や `site_status` を利用して詳細なエラーログを確認してください。
- 複数イベントを扱う場合は、`$modx->event->name` と主要パラメータをログに出力し、意図した順序で呼ばれているかを確認します。

## 配布とバージョニング

- プラグインは `Elements > Export` 機能でパッケージ化し、Git リポジトリに含めて共有すると差分管理が容易になります。
- 配布前にドキュメント（要件、インストール手順、プロパティの説明）を整備し、依存するイベントやシステム設定を明記してください。
- バージョンアップ時は `OnPluginVersionCompare` などのイベントを利用し、初回インストールやアップデート時のマイグレーション処理を自動化できます。

## よくある落とし穴

- `$modx->documentContent` などの内部プロパティを直接変更すると、キャッシュ整合性が乱れる可能性があります。常に公式 API (`setPlaceholder` / `clearCache` / `invokeEvent`) を利用してください。
- `require` や `include` で外部ファイルを読み込む場合は `MODX_BASE_PATH` を基準にパスを組み立て、環境差異によるパスエラーを防ぎます。
- イベントによってはバックエンドからも発火されるため、`$modx->isFrontend()` や `$modx->isBackend()` を用いて実行条件を制御します。

## チェックリスト

- [ ] 対応するイベントと副作用が明確になっている
- [ ] プラグインコード内で `evo()` などのヘルパーを利用している
- [ ] プロパティにデフォルト値と説明を設定した
- [ ] キャッシュ戦略とクリア条件を定義した
- [ ] ログ出力やドキュメントを整備した
- [ ] バージョン管理・配布手段を決定した
