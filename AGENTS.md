# 開発ガイドライン

## 基本原則

以下の原則を常に尊重してください。

- **SOLID**: 各クラス・モジュールは単一責務を保ち、拡張に開かれつつも既存コードの修正を最小限に抑えます。
- **KISS**: 実装は可能な限りシンプルにし、過度に複雑な抽象化を避けます。
- **YAGNI**: 要求されていない機能や柔軟性は追加しません。
- **DRY**: 重複コードを避け、共通処理は再利用可能な形に切り出します。
- **PIE**: 自己検証可能な実装を意識し、テストや検証が容易な構成にします。
- **SSOT**: 真実の単一情報源を保ち、設定値やドキュメントが一貫するようにします。

### 補助ルール

- **既存パターンの踏襲**: 新しい機能を実装する際は、必ず既存コードのパターンを調査し、同じアプローチを採用する。個別に新しい方式を導入すると、システム全体の一貫性が失われ、保守が困難になる。
  - 例: ログ記録、バリデーション、エラーハンドリング、データアクセスパターンなど
  - 新しいアプローチが必要な場合は、システム全体への適用を前提として設計・実装する。提案は歓迎する。

- **ヘルパー利用**: `manager/includes/helpers.php` に定義されている `evo()` / `db()` / `manager()` を経由してグローバルオブジェクトへアクセスする。

- **スーパーグローバル変数の禁止**: `$_GET` / `$_POST` / `$_REQUEST` / `$_SERVER` / `$_SESSION` / `$_COOKIE` に直接アクセスせず、必ずヘルパー関数を使用する。
  - `$_GET` → `getv($key, $default)`
  - `$_POST` → `postv($key, $default)`
  - `$_REQUEST` → `anyv($key, $default)`
  - `$_SERVER` → `serverv($key, $default)`
  - `$_SESSION` → `sessionv($key, $default)` （読み取り） / `sessionv('*key', $value)` （書き込み）
  - `$_COOKIE` → `cookiev($key, $default)`
  - リクエストメソッド判定 → `is_post()` / `is_get()`

- **ログ記録**: Evolution CMS標準の `evo()->logEvent()` を使用する。
  - イベントログは管理画面の「ツール」→「イベントログ」で確認可能

- **レビュー言語**: レビューコメントは日本語でまとめること。

- **コミットメッセージ**: コミットメッセージは英語で記述すること。

## DocumentParser を中心に考える

Evolution CMS JP Edition の中心は `DocumentParser`（`manager/includes/document.parser.class.inc.php`）です。`index.php` から初期化され、リクエストのサニタイズ、リソース特定、テンプレート解析、イベント発火、キャッシュ生成までを一貫して担当します。各処理段階で `invokeEvent()` を通じてプラグインが呼び出され、ヘルパー関数によって `DBAPI` や `ManagerAPI` を必要なタイミングでロードします。

実装時には「どの段階（例: `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()`）にフックすべきか」を常に意識してください。処理位置を誤ると、キャッシュ整合性やイベント順序が乱れ、プラグインやテンプレートの副作用が発生します。

## ドキュメントマップ

詳細な設計情報は `documents/` 配下のドキュメントに整理されています。該当領域を編集する前に、必ず関連ドキュメントを確認してください。

| ドキュメント | 主題 | 活用ポイント |
| --- | --- | --- |
| `documents/architecture.md` | DocumentParser の処理フロー・バックエンド判定・テンプレート取得 | 影響調査と実装方針の初期検討に使用 |
| `documents/template-system.md` | テンプレート継承、MODX タグ解析、各種要素の評価順序 | テンプレート・TV・スニペットの追加や修正時に参照 |
| `documents/events-and-plugins.md` | イベントライフサイクル、プラグインキャッシュ、SystemEvent API | プラグイン開発やイベントフック検討時に参照 |
| `documents/cache-mechanism.md` | ページキャッシュ、静的キャッシュ、TTL と無効化手順 | キャッシュポリシーの変更や公開フロー調整時に参照 |

## 推奨ワークフロー

1. 対象処理の流れを `architecture.md` と `events-and-plugins.md` で確認し、影響範囲を整理する。
2. テンプレート・プレースホルダー・TV を触る場合は `template-system.md` を読み、解析順序やタグ種別の制約を確認する。
3. キャッシュや公開状態へ影響する変更は `cache-mechanism.md` を参照し、`evo()->clearCache()` の呼び出し条件を明確にする。
4. 実装ではヘルパー関数を活用し、直接 `$modx` に触れない。

## ファイル管理とディレクトリ構造

### 主要ディレクトリ

| ディレクトリ | 用途 | 備考 |
| --- | --- | --- |
| `assets/files/` | 一般ファイル | グローバル設定 `filemanager_path` で変更可能 |
| `{rb_base_dir}images/` | 画像ファイル | グローバル設定 `rb_base_dir` に依存（デフォルト: `content/`） |
| `{rb_base_dir}files/` | ファイル | グローバル設定 `rb_base_dir` に依存 |
| `{rb_base_dir}media/` | メディアファイル | グローバル設定 `rb_base_dir` に依存 |
| `assets/templates/` | テンプレートファイル | チャンクやテンプレートの物理ファイル配置 |
| `temp/` | 一時ファイル | キャッシュ、バックアップ、インポート/エクスポート |

**注意**:

- `rb_base_dir` のデフォルトは `content/` だが、グローバル設定で変更可能
- ファイルパス関連の実装時は `evo()->getConfig('rb_base_dir')` を参照すること
- `[(base_path)]` プレースホルダーは `MODX_BASE_PATH` に置換される

## グローバル設定の拡張

新しいシステム設定を追加する際の手順:

### 1. デフォルト値の定義

`manager/includes/default.config.php` に設定のデフォルト値を追加:

```php
'new_setting_name' => 'default_value',
```

### 2. 言語ファイルへの追加

`manager/includes/lang/japanese-utf8.inc.php` と `english.inc.php` に翻訳を追加:

```php
$_lang['setting_new_setting_name'] = '設定名';
$_lang['setting_new_setting_name_desc'] = '設定の説明文';
```

### 3. 設定画面への表示

`manager/actions/tool/mutate_settings/tab*.inc.php` のいずれかに設定項目を追加:

```php
<tr>
    <th><?= lang('setting_new_setting_name') ?></th>
    <td>
        <?= wrap_label(
            lang('yes'),
            form_radio('new_setting_name', 1, config('new_setting_name') == 1)
        ); ?><br/>
        <?= wrap_label(
            lang('no'),
            form_radio('new_setting_name', 0, config('new_setting_name') == 0)
        ); ?><br/>
        <?= lang('setting_new_setting_name_desc') ?>
    </td>
</tr>
```

### 4. 設定値の反映

- **新規インストール**: `default.config.php` の値が自動的に使用される
- **既存インストール**: グローバル設定を一度保存することで、`save_settings.processor.php` が `default.config.php` の値を `system_settings` テーブルに反映

### 設定画面のタブ構成

| ファイル | タブ名 | 主な設定項目 |
| --- | --- | --- |
| `tab1_site_settings.inc.php` | サイト設定 | サイト名、URL、ステータスなど |
| `tab1_doc_settings.inc.php` | リソース設定 | デフォルトテンプレート、公開設定など |
| `tab2_cache_settings.inc.php` | キャッシュ設定 | キャッシュ有効化、TTL など |
| `tab2_furl_settings.inc.php` | Friendly URL 設定 | URL エイリアス、サフィックスなど |
| `tab3_user_settings.inc.php` | ユーザー設定 | セッション、認証関連 |
| `tab4_manager_settings.inc.php` | 管理画面設定 | エディタ、言語、テーマなど |
| `tab6_filemanager_settings.inc.php` | ファイル管理設定 | アップロード許可、パス設定など |

### 注意事項

- SQL ファイルでの設定追加は不要（`default.config.php` のみで管理）
- `save_settings.processor.php` は POST データと `default.config.php` をマージし、`REPLACE INTO` で一括保存
- 設定値の取得は `evo()->getConfig('setting_name', 'default')` を使用

## ドキュメント運用

- `AGENTS.md` は AI 向けのハブドキュメントとして継続的に更新してください。新しいモジュールやベストプラクティスを追加する際は、`documents/` 配下に詳細ドキュメントを設け、ここからリンクする構成を守ります。
- README は人間開発者向けの概要に留め、ここでは AI が迅速に全体像を把握できる粒度を意識します。
