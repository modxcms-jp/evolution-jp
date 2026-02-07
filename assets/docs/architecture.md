# アーキテクチャ概要

## 主要エントリポイント

- `index.php` がフロントコントローラとして動作し、`define-path.php` のロード後に `DocumentParser` を生成して `executeParser()` を呼び出します。ここで静的キャッシュの判定や初期タイミングの計測が行われます。
- `DocumentParser`（`manager/includes/document.parser.class.inc.php`）がフロントエンド表示・管理画面判定・イベント発行・キャッシュ制御を一手に担います。

## リクエストライフサイクル

1. **ブートストラップ**: `executeParser()` がグローバル値をサニタイズし、ユーザーエージェント分類とドキュメント識別子の解決を行います。静的 HTML キャッシュが存在する場合はここでショートサーキットします。
2. **初期化**: サイト公開状態や公開期限、HTTP ステータスの検証を行った後、最初のイベント `OnWebPageInit` が発火します。
3. **ドキュメント解決**: `prepareResponse()` がページキャッシュを確認し、ミスした場合はリソース取得・権限チェック・`reference` タイプの解決・テンプレート継承の展開を実施します。
4. **テンプレート組み立て**: TV をドキュメントオブジェクトへマージし、`parseDocumentSource()` がテンプレート内の MODX タグを段階的に解析します。
5. **出力**: `outputContent()` が登録済みスクリプトの注入、`OnLogPageHit`/`OnWebPagePrerender` の発火、レスポンス出力を担当し、`postProcess()` を遅延実行として登録します。
6. **後処理**: `postProcess()` がキャッシュエントリを生成し、セキュリティメタデータを付与した上でユーザーエージェント別に書き出し、最後に `OnWebPageComplete` を発火します。

## コンテキスト判定

- `DocumentParser::isBackend()` が `IN_MANAGER_MODE` 定数を確認し、`isFrontend()` がその逆を返します。
- ヘルパーの `manager()` や `hasPermission()` はこれらの判定を内包しており、直接グローバル変数を参照する代わりに利用します。

## データアクセスと拡張

- `loadExtension()` によって `DBAPI`（`manager/includes/extenders/dbapi/mysqli.inc.php` など）が遅延ロードされ、必要な拡張のみを読み込みます。
- TV は `getDocumentObject()` 内でドキュメントと同時に取得され、後続の解析パスでコアフィールドと同じ扱いを受けます。

## イベント統合

DocumentParser は処理の各段階で `invokeEvent()` によりプラグインフックを提供します。イベントライフサイクルの詳細、発火タイミング、プラグイン実装パターンについては [`events-and-plugins.md`](events-and-plugins.md) を参照してください。
