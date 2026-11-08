# アーキテクチャ概要

## 主要エントリポイント

- `index.php` がフロントコントローラとして動作し、`define-path.php` のロード後に `DocumentParser` を生成して `executeParser()` を呼び出します。ここで静的キャッシュの判定や初期タイミングの計測が行われます。
- `DocumentParser`（`manager/includes/document.parser.class.inc.php`）がフロントエンド表示・管理画面判定・イベント発行・キャッシュ制御を一手に担います。

## リクエストライフサイクル

1. **ブートストラップ**: `executeParser()` がグローバル値をサニタイズし、ユーザーエージェント分類とドキュメント識別子の解決を行います。静的 HTML キャッシュが存在する場合はここでショートサーキットします。
2. **初期化**: サイト公開状態や公開期限、HTTP ステータスの検証を行った後、最初のイベント `OnWebPageInit` が発火します。
3. **ドキュメント解決**: `prepareResponse()` がページキャッシュを確認し、ミスした場合はリソース取得・権限チェック・`reference` タイプの解決・テンプレート継承の展開を実施します。
   - 投稿画面のプレビューは、`index.php?a=309`（`preview_draft.processor.php`）が編集中の値をセッションの `preview_drafts` に一時的に置き、フロントを `?mgr_preview=<キー>`（保存済みの内容を見るときは `?mgr_preview=1`）の GET で開きます。`getDocumentObject()` が `loadPreviewDraft()` で読み、`previewObject` として本文・TV に反映します（件数の上限と期限は `previewDraftPolicy()`）。画面は受け取ったキーを次の送信で `preview_key` に入れて使い回し（`storePreviewDraft()` が同じ文書の下書きなら同じキーで上書きする）、プレビューの窓が開いている間は編集の変更を入力が止まって 1.5 秒後に自動で送り、窓を再読み込みします（直前に送った内容と同じなら自動では送りません。窓の中で別のページへ移っていても、同じオリジンなら下書きの URL に戻します。別オリジンでは再読み込みのたびにスクロールの位置が先頭に戻ります）。保存で画面が読み直されても、開いたままの同じ文書の窓は引き継いで自動更新を続け（窓が同じオリジンでこの文書のプレビューを表示しているとき。そうでなければもう一度「プレビュー」で再開）、日本語などの変換中は送りません（確定で送ります）。
4. **テンプレート組み立て**: TV をドキュメントオブジェクトへマージし、`parseDocumentSource()` がテンプレート内の MODX タグを段階的に解析します。
5. **出力**: `outputContent()` が登録済みスクリプトの注入、`OnLogPageHit`/`OnWebPagePrerender` の発火、レスポンス出力を担当し、`postProcess()` を遅延実行として登録します。
6. **後処理**: `postProcess()` がキャッシュエントリを生成し、セキュリティメタデータを付与した上でユーザーエージェント別に書き出し、最後に `OnWebPageComplete` を発火します。

## コンテキスト判定

- `DocumentParser::isBackend()` が `IN_MANAGER_MODE` 定数を確認し、`isFrontend()` がその逆を返します。
- ヘルパーの `manager()` や `hasPermission()` はこれらの判定を内包しており、直接グローバル変数を参照する代わりに利用します。

## データアクセスと拡張

- `loadExtension()` によって `DBAPI`（`manager/includes/extenders/dbapi/mysqli.inc.php` など）が遅延ロードされ、必要な拡張のみを読み込みます。
- TV は `getDocumentObject()` 内でドキュメントと同時に取得され、後続の解析パスでコアフィールドと同じ扱いを受けます。

## イベントフローの概要

- 通常のフロントエンドリクエストでは `OnWebPageInit` → `OnLoadWebPageCache` → `OnLoadWebDocument` → `OnParseDocument`（解析ループ内）→ `OnLogPageHit` / `OnWebPagePrerender` → `OnBeforeSaveWebPageCache` → `OnWebPageComplete` の順でイベントが進行します。詳細は `events-and-plugins.md` を参照してください。
