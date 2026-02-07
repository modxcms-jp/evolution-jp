# ExecPlan: システムログ機構の改修（AI自走デバッグの実現）

> このExecPlanはOpenAI公式推奨の「What/Why/How to verify」重視型で記述されています。

## Purpose / Big Picture
Evolution CMS のシステムログ（エラー・警告・情報）をファイルベースのJSONLines形式に移行し、AI エージェントがログを解析してコード修正を自律的に行えるようにする。構造化されたログにより、エラー箇所の特定・再現・修正のサイクルを自動化する。

**対象範囲**: システムログ（`event_log` テーブル）のみ。管理操作ログ（`manager_log`）は別プランで対応。

## Progress
- [ ] (2026-02-07) PSR-3準拠ロガークラスの作成
- [ ] (2026-02-07) ファイル書き込み・ローテーション機構の実装
- [ ] (2026-02-07) 既存 `logEvent()` の互換レイヤー実装
- [ ] (2026-02-07) システムログ画面の改修（ファイル一覧・表示）
- [ ] (2026-02-07) CLI コマンドの実装（log:tail system, log:search system）
- [ ] (2026-02-07) issue-resolver スキルの更新（ログ解析機能統合）
- [ ] (2026-02-07) 多言語対応（メニュー名「イベントログ」→「システムログ」）
- [ ] (2026-02-07) インストールスクリプト修正・旧クラス削除
- [ ] (2026-02-07) 統合テスト・動作確認

## Surprises & Discoveries
（実装中に遭遇した予期しない挙動や知見をここに記録）

## Decision Log

### 2026-02-07: 目的の明確化
- **修正**: 「DB肥大化問題の解決」→「AI自走デバッグの実現」
- **理由**: 現在のログ機構では構造化データがないため、AIがエラー箇所を特定できない
- **効果**:
  - JSONLines形式でエラーコンテキスト（スタックトレース、変数値、ユーザーID等）を保存
  - AI が `log:search` でエラーパターンを解析
  - 該当コードを自動修正し、再現テストまで実行可能
- **副次的効果**: DB肥大化の解決、運用性向上

### 2026-02-07: スコープの分離
- **決定**: システムログのみを対象とし、管理操作ログは別プランで対応
- **理由**:
  - データ取得方法が違う（`logEvent()` vs `logHandler`）
  - 用途が違う（デバッグ vs 監査）
  - テスト方法が違う（エラー再現 vs 操作履歴確認）
  - **管理操作ログはDB保存のメリットもある**（期間検索、統計分析、ユーザー絞り込み）
- **実装順序**: システムログを優先（AI開発に直結）
- **別プラン作成予定**: `2026-02-07-manager-log-refactor.md`（方針未定、DB継続も検討）

### 2026-02-07: ログ保存先の設計方針
- **決定**: ファイルベース、`temp/logs/{type}/YYYY/MM/{type}-YYYY-MM-DD.log` 形式
- **理由**: DB肥大化がバックアップ失敗の主原因。ファイルシステムでログローテーションと長期保存を分離
- **代替案**: JSONをDBに保存 → 肥大化問題が残る
- **代替案**: 1ファイルに全ログ蓄積 → サイズ問題が発生
- **選択した方式**: 日付・タイプ別ディレクトリで分割し、1ディレクトリあたり最大31ファイル

### 2026-02-07: PSR-3準拠の採用
- **決定**: PSR-3 Logger Interface に準拠
- **理由**: 業界標準の8レベルログ（RFC 5424）、コンテキスト配列による構造化データ
- **代替案**: 独自ログレベル（1=info, 2=warning, 3=error）継続 → 拡張性が低い
- **実装方針**: 既存の3段階は PSR-3 レベルにマッピング（1→info, 2→warning, 3→error）

### 2026-02-07: JSONLines形式の採用
- **決定**: 1行1ログのJSONLines形式で保存
- **理由**:
  - `grep` / `jq` / `tail -f` などUNIXツールで解析可能
  - ログローテーション時の部分読み込みが容易
  - PSR-3のコンテキスト配列を自然に保存
- **代替案**: 平文 → 構造化データを保存できない
- **代替案**: XML → 可読性が低い

### 2026-02-07: ファイルパスのセキュリティ設計
- **決定**: すべてのファイルパス（スタックトレース、caller情報等）を相対パスで記録
- **理由**:
  - OSSプロジェクトのため、ユーザーがフォーラムにログを貼り付けることがある
  - 物理パス（例：`/home/user/www/evo/`）が露出するとサーバー構成が推測される
  - 相対パス（例：`manager/actions/document/edit.php`）ならセキュリティリスクが低い
- **実装**: `MODX_BASE_PATH` を基準に `str_replace()` で変換
- **多層防御**: ログ書き込み時にも物理パスをプレースホルダ `{BASE_PATH}` で置換（最終防御層）
- **効果**: ログをそのままコピペしても、サーバーのディレクトリ構造が漏洩しない

### 2026-02-07: event_log テーブルの廃止方針
- **決定**: 新規インストールでは `event_log` テーブルを作成せず、既存インストールでは並行運用後に廃止
- **新規インストール**:
  - `install/sql/create_tables.sql` から `event_log` テーブル定義を削除
  - 最初からファイルベースログのみで動作
- **既存インストール**:
  - 互換レイヤー（`logEvent()`）を介してファイルベースログに記録
  - DB書き込みは行わない（フォールバック機能としてのみ保持）
  - 将来的に `event_log` テーブルは手動削除（マイグレーション機構で対応）
- **廃止対象ファイル**:
  - `manager/processors/delete_eventlog.processor.php`
  - `manager/processors/export_eventlog.processor.php`
  - `manager/actions/report/eventlog_details.dynamic.php`
  - これらは新ログ機構で不要（削除機能は管理画面から、エクスポートはJSON形式で対応）
- **理由**: 新規ユーザーには最初から新方式を提供し、既存ユーザーには移行期間を確保

## Outcomes & Retrospective
（完了時に記入）

## Next Steps
1. ロガー基盤（`logger.class.php`）+ コンテキスト収集
2. 互換レイヤー（既存 `logEvent()` を新ロガーで実装）
3. システムログ画面（ファイル一覧・JSONLines表示）
4. CLIコマンド（AI向けに最適化）
5. **issue-resolver スキルの更新**（ログ解析機能統合）
6. ドキュメント・多言語対応

---

## Context and Orientation

### 現在のログ機構の問題点
Evolution CMS は `event_log` テーブルにログを記録しているが、以下の問題がある:
- **構造化データなし**: エラー箇所（ファイル・行番号）が記録されない
- **DB肥大化**: 大量のログでバックアップが失敗する（特にエラー多発時）
- **AI解析不可**: スタックトレース・リクエスト情報がないため、AIが自動修正できない

### 新しいログ機構の設計
- **ファイルベース**: `temp/logs/system/YYYY/MM/system-YYYY-MM-DD.log`
- **JSONLines形式**: 1行1JSON、UNIXツール（grep/jq）で解析可能
- **自動コンテキスト収集**: エラー発生時に自動でスタックトレース・リクエスト情報を記録
- **AI自走デバッグ**: `log:search` でエラーパターンを検出 → コード修正 → 検証

### 関連ファイル
- `manager/includes/logger.class.php`: 新ロガークラス（新規作成）
- `manager/includes/traits/document.parser.subparser.trait.php`: `logEvent()` メソッド（互換レイヤー実装）
- `manager/actions/report/eventlog.dynamic.php`: システムログ画面（全面刷新）
- `manager/includes/cli/commands/log-*.php`: CLIコマンド群（新規作成）

---

## Plan of Work

### ステップ1: PSR-3準拠ロガークラスの作成

**目的**: AI自走デバッグに必要な構造化ログ（JSONLines形式）とコンテキスト自動収集を実現する。

**ファイル**: `manager/includes/logger.class.php`（新規作成）

**設計要点**:

1. **PSR-3準拠のインターフェース**
   - 8つのログレベル（emergency/alert/critical/error/warning/notice/info/debug）
   - 各レベルに対応するメソッド: `error($message, $context)`, `warning(...)` 等
   - 内部で `log($level, $message, $context)` に集約

2. **自動コンテキスト収集**（AI解析用）
   - エラー・警告レベルで自動実行
   - スタックトレース（`debug_backtrace()` 最大5階層）
   - 呼び出し元ファイル・行番号（`$trace[2]`）
   - リクエスト情報（URL/メソッド/IP）
   - ユーザーID（`evo()->getLoginUserID()`）

3. **セキュリティ対策：相対パス変換**
   - すべてのファイルパスを `MODX_BASE_PATH` からの相対パスに変換
   - フォーラム投稿時にサーバーの物理パスが露出しないように
   - 多層防御: `toRelativePath()` メソッド + `writeLog()` 内でプレースホルダ置換

4. **JSONLines形式で書き込み**
   - 1行1ログ、`grep`/`jq` で解析可能
   - ファイルパス: `temp/logs/system/YYYY/MM/system-YYYY-MM-DD.log`
   - オプション: `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`

5. **ファイルローテーション**
   - 100MB超過で自動ローテーション（`.1`, `.2` 等）
   - 保持期間設定（デフォルト30日）に基づく自動削除
   - gzip圧縮機能（ストレージ節約）

**重要なロジック（抜粋）**:

    // エラーコンテキストの自動収集
    private function collectContext()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        return [
            'caller' => [
                'file' => $this->toRelativePath($trace[2]['file'] ?? ''),
                'line' => $trace[2]['line'] ?? 0,
            ],
            'trace' => array_map(fn($t) => [
                'file' => $this->toRelativePath($t['file'] ?? ''),
                'line' => $t['line'] ?? 0,
                'function' => $t['function'] ?? '',
            ], $trace),
            'request' => [
                'url' => serverv('REQUEST_URI'),
                'method' => serverv('REQUEST_METHOD'),
                'ip' => serverv('REMOTE_ADDR'),
            ],
            'user' => evo()->getLoginUserID(),
        ];
    }

    // セキュリティ：相対パス変換
    private function toRelativePath($path)
    {
        if (empty($path)) return '';
        $relativePath = str_replace(MODX_BASE_PATH, '', $path);
        $relativePath = str_replace('\\', '/', $relativePath);
        return ltrim($relativePath, '/');
    }

    // 多層防御：書き込み時にも物理パスを除去
    private function writeLog($logFile, array $logEntry)
    {
        $jsonLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        // 万が一物理パスが残っていた場合の最終防御
        $jsonLine = str_replace(MODX_BASE_PATH, '{BASE_PATH}/', $jsonLine);
        file_put_contents($logFile, $jsonLine, FILE_APPEND | LOCK_EX);
    }

**検証方法**:

1. エラーを発生させる:

       evo()->logEvent(0, 3, 'テストエラー', 'Test');

2. ログファイルを確認:

       tail -1 temp/logs/system/2026/02/system-2026-02-08.log | jq .

3. 期待される出力:

       {
         "timestamp": "2026-02-08T15:30:45+09:00",
         "level": "error",
         "message": "テストエラー",
         "context": {
           "caller": {
             "file": "manager/includes/test.php",  // 相対パス
             "line": 123
           },
           "trace": [{...}],
           "request": {"url": "/manager/?a=1", ...},
           "user": 1
         }
       }

4. セキュリティ検証（物理パスが含まれないこと）:

       grep -E '/home/|/var/www/|C:\\' temp/logs/system/2026/02/*.log
       # 何も出力されなければOK

---

### ステップ2: 互換レイヤーの実装

**目的**: 既存コードを変更せずに新ログ機構に切り替える。`logEvent()` の引数とDB書き込みロジックは維持しつつ、内部でファイルベースログを使用。

**ファイル**: `manager/includes/traits/document.parser.subparser.trait.php`

**設計要点**:

1. **既存の引数をPSR-3にマッピング**
   - `$type = 1` → `Logger::INFO`
   - `$type = 2` → `Logger::WARNING`
   - `$type = 3` → `Logger::ERROR`

2. **追加コンテキスト情報**
   - `eventid`: 既存の `$evtid` を保持（互換性）
   - `source`: 既存の `$title`（例: 'Parser', 'DocumentParser'）

3. **メール通知は継続**
   - `send_errormail` 設定に基づき、エラー発生時にメール送信
   - 既存のメール本文フォーマットをそのまま維持

**実装の要点**:

    function logEvent($evtid, $type, $msg, $title = 'Parser')
    {
        $levelMap = [1 => 'info', 2 => 'warning', 3 => 'error'];
        $level = $levelMap[$type] ?? 'info';

        $logger = new Logger();
        $logger->log($level, $msg, [
            'eventid' => $evtid,
            'source' => $title,
        ]);

        // メール通知処理（既存コード維持）
        if (config('send_errormail') <= $type) {
            // ... メール送信 ...
        }
    }

**検証方法**:

1. 既存コードから呼び出してログが記録されることを確認:

       evo()->logEvent(101, 3, 'Document not found', 'DocumentParser');

2. ログファイルで確認:

       tail -1 temp/logs/system/2026/02/system-2026-02-08.log | jq '.context'
       # 出力: {"eventid": 101, "source": "DocumentParser", ...}

3. プラグイン・モジュールでの動作確認（後方互換性）:

       // 既存のプラグインコード（変更不要）
       $modx->logEvent(1, 1, 'Plugin executed', 'MyPlugin');
       # → 正常に新ログ機構に記録される

---

### ステップ3: システムログ画面の改修

**目的**: ログファイル一覧と内容表示を提供。リッチUIではなく、開発者向けの実用的な画面を目指す。

**ファイル**: `manager/actions/report/eventlog.dynamic.php`（全面刷新）

**設計要点**:

1. **2カラムレイアウト**
   - 左: ログファイル一覧（最新20件、ファイル名/サイズ/行数/更新日時）
   - 右: 選択したファイルの内容表示（textarea、整形済みJSON）

2. **フィルタリング機能**
   - ログレベル（error/warning/info）
   - キーワード検索（メッセージ・コンテキスト全体を対象）

3. **操作機能**
   - ダウンロード（JSON形式そのまま、AI解析用）
   - 削除（個別ファイル、または古いログ一括削除）

4. **Ajax通信**（Vanilla JS、jQueryなし）
   - ファイル選択時に内容を取得
   - フィルタ変更時にサーバー側で絞り込み

**UI構成**（ASCII art）:

    [ログファイル一覧]           [ログ内容]
    ┌─────────────────┐       ┌─────────────────────┐
    │ system-2026-02-08.log│  →  │ [Level: All ▼][🔍]  │
    │ 2.5MB  123 lines    │       │ ┌─────────────────┐│
    │ system-2026-02-07.log│       │ │ JSON表示        ││
    │ 1.8MB  89 lines     │       │ │ (整形済み)      ││
    │ ...                 │       │ └─────────────────┘│
    │ [削除] [ダウンロード]│       │ [全選択] [保存]      │
    └─────────────────┘       └─────────────────────┘

**バックエンド処理**（Ajax API）:

    // ログファイル一覧取得
    GET /manager/ajax.php?action=log_list&type=system
    → {"files": [{"name": "...", "size": ..., "mtime": ...}]}

    // ログ内容取得
    GET /manager/ajax.php?action=log_read&file=system-2026-02-08.log&level=error
    → {"logs": [{...}, {...}]}

    // ログ削除
    POST /manager/ajax.php?action=log_delete&file=system-2026-02-08.log
    → {"success": true}

**検証方法**:

1. 管理画面「ツール」→「システムログ」にアクセス
2. 最新のログファイルが一覧表示されることを確認
3. ファイルをクリックし、JSONが整形表示されることを確認
4. レベルフィルタで「error」のみ表示されることを確認
5. ダウンロードボタンでJSON形式でダウンロードできることを確認

---

### ステップ4: CLIコマンドの実装

**目的**: AI向けにログ解析を簡易化するCLIインターフェース。

**実装コマンド**:

1. **log:tail system** `[--lines=20] [--follow]`
   - 最新N件のログ表示、`-f` でリアルタイム監視
   - 実装: `tail -f` コマンドをラップ
   - AI用途: エラー再現時のリアルタイム確認

2. **log:search system** `[keyword] [--level=error] [--json]`
   - レベル・キーワードでフィルタリング
   - `--json` でAI解析用に構造化出力
   - 実装: `Logger::readLogFile()` + フィルタ処理
   - AI用途: エラーパターン検出、過去のエラー履歴調査

3. **log:clean system** `[--days=30] [--dry-run]`
   - 古いログの削除（プレビュー機能付き）
   - 実装: `Logger::deleteOldLogs($days, $dryRun)`
   - 運用用途: ストレージ管理

4. **log:compress system** `[--days=7] [--auto]`
   - 古いログのgzip圧縮（ストレージ節約）
   - 実装: `Logger::compressOldLogs($days)`
   - 運用用途: 長期保存前の圧縮

**ファイル配置**: `manager/includes/cli/commands/log-{tail,search,clean,compress}.php`

**検証方法**:

    # エラー検索（AI用途）
    ./evo log:search system "error" --level=error --json | jq 'length'
    # → エラー件数が表示される（例: 15）

    # リアルタイム監視
    ./evo log:tail system --follow
    # → 別端末でエラーを発生させると即座に表示される

    # 古いログのプレビュー
    ./evo log:clean system --days=30 --dry-run
    # → 削除予定のファイル一覧が表示される（削除はされない）

    # 圧縮実行
    ./evo log:compress system --days=7
    # → 7日以上前のログが .gz に圧縮される

---

### ステップ5-8: 残りの実装

**ステップ5**: issue-resolver スキルの更新
- `.claude/skills/issue-resolver/SKILL.md` に新しいログ機構を活用した不具合解析機能を追加
- `/analyze-issue` コマンドで自動的にログ検索を実行
- `/reproduce` コマンドで `log:tail --follow` を使用したリアルタイム確認

**ステップ6**: 多言語対応
- `manager/includes/lang/*.inc.php` に新しい言語キーを追加
- 「イベントログ」→「システムログ」にメニュー名変更

**ステップ7**: インストールスクリプト修正
- `install/sql/create_tables.sql` から `event_log` テーブル定義を削除
- 旧プロセッサー・画面の削除

**ステップ8**: 統合テスト
- 全機能の動作確認
- セキュリティ検証（相対パス変換）
- 後方互換性確認（既存プラグイン・モジュール）

---

## Validation and Acceptance

### 受け入れ基準

1. **AI自走デバッグの実現**
   - エラーログから自動的にファイル・行番号を特定できる
   - `log:search --json` で構造化データを取得できる
   - スタックトレース・リクエスト情報が記録されている

2. **後方互換性**
   - 既存の `logEvent()` 呼び出しが正常に動作する
   - プラグイン・モジュールの変更不要

3. **セキュリティ**
   - ログ内のすべてのファイルパスが相対パスになっている
   - 物理パス（/home/, /var/www/, C:\）が含まれない

4. **運用性**
   - ログファイルが日付別に分割されている
   - 100MB超過で自動ローテーションされる
   - 古いログが自動削除される（設定可能）

### テストシナリオ

1. エラー発生 → ログ記録 → 管理画面で確認
2. CLI `log:search` でエラーパターン検出
3. AI が `log:search --json` でエラー情報を取得し、該当コードを修正
4. 修正後、同じ操作を実行してエラーが消えたことを確認

---

## Security Considerations

### ファイルパスの相対パス化（多層防御）

1. **第1層**: `collectContext()` 内で `toRelativePath()` により変換
2. **第2層**: `writeLog()` 内で `str_replace()` により物理パスをプレースホルダに置換
3. **効果**: フォーラム投稿時にサーバー構成が漏洩しない

### ログファイルのアクセス制御

- `temp/logs/` ディレクトリは `.htaccess` で外部アクセス禁止
- 管理画面からのアクセスは権限チェック必須

---

## Idempotence and Recovery

### 冪等性

- ディレクトリ作成: `mkdir()` は既存ディレクトリでもエラーにならない
- ログ書き込み: `FILE_APPEND` により追記モード
- CLIコマンド: `--dry-run` オプションで事前確認可能

### リカバリ

- ログ書き込み失敗時はサイレントに継続（サービスを止めない）
- ログファイル削除時は確認プロンプト表示
- バックアップ推奨: `temp/logs/` ディレクトリ全体

---

## Artifacts and Notes

### ログエントリの例

    {
      "timestamp": "2026-02-07T14:30:45+09:00",
      "level": "error",
      "message": "Undefined variable: document",
      "context": {
        "eventid": 0,
        "source": "DocumentParser",
        "caller": {
          "file": "manager/actions/document/edit.php",
          "line": 123
        },
        "trace": [
          {"file": "manager/includes/document.class.php", "line": 100, "function": "editDocument"}
        ],
        "request": {
          "url": "/manager/?a=27&id=5",
          "method": "POST",
          "ip": "192.168.1.1"
        },
        "user": 1
      }
    }

---

## Interfaces and Dependencies

### 依存関係

- **PHP 7.4+**: `json_encode()`, `FILE_APPEND | LOCK_EX`, `debug_backtrace()`
- **ファイルシステム**: `temp/logs/` への書き込み権限
- **既存ヘルパー**: `evo()`, `config()`, `serverv()`

### 外部モジュールとのインターフェース

- **DocumentParser**: `logEvent()` メソッドを通じてログ記録
- **CLI**: 新規コマンドを `manager/includes/cli/commands/` に追加
- **AI エージェント**: `log:search --json` でエラーパターンを取得
- **issue-resolver スキル**: ログ解析機能を活用した不具合調査・修正

### 破壊的変更なし

既存APIは互換レイヤーで維持されるため、プラグイン・モジュールへの影響なし。
