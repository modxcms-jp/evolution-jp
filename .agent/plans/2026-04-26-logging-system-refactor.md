# ExecPlan: システムログ機構の改修（AI自走デバッグの実現）

> このExecPlanはOpenAI公式推奨の「What/Why/How to verify」重視型で記述されています。

## Purpose / Big Picture

Evolution CMS のシステムログ（エラー・警告・情報）をファイルベースのJSONLines形式に移行し、AI エージェントがログを解析してコード修正を自律的に行えるようにする。構造化されたログにより、エラー箇所の特定・再現・修正のサイクルを自動化する。

**対象範囲**: システムログ（`event_log` テーブル）のみ。管理操作ログ（`manager_log`）は別プランで対応。

## Progress

- [x] (2026-04-26) PSR-3準拠ロガークラスの作成
- [x] (2026-04-26) ファイル書き込み・ローテーション機構の実装
- [x] (2026-04-26) 既存 `logEvent()` の互換レイヤー実装
- [x] (2026-04-26) システムログ画面の改修（ファイル一覧・表示）
- [x] (2026-04-28) 管理画面側の未捕捉 Throwable / fatal error の捕捉
- [x] (2026-04-29) フロントエンド側の未捕捉 Throwable / fatal error の捕捉
- [x] (2026-04-29) CLI コマンドの実装（log:tail, log:search）
- [x] (2026-04-29) issue-resolver スキルの更新（ログ解析機能統合）
- [x] (2026-04-29) 多言語対応（メニュー名「イベントログ」→「システムログ」）
- [x] (2026-04-29) インストールスクリプト修正・旧DBログ入口削除
- [ ] (2026-04-26) 統合テスト・動作確認

## Surprises & Discoveries

- (2026-04-28) `actionlist.inc.php` は `$GLOBALS['action_list']` をSSOTとしているため、manager action 名の記録も `globalv('action_list', [])` 経由にした。
- (2026-04-28) リモート環境で `configcheck_installer_msg` に含まれる「必」がログ画面上で `�` に置換される現象を確認。`config_check.inc.php` の `logEvent()` 直前で `bin2hex($this->_lang['configcheck_installer_msg'])` を表示した結果、`の必要` は `e381ae e5bf85 e8a681` として正常だったため、言語ファイル読み込み時点では壊れていない。次は `normalizeLogMessage()` 後、Logger 書き込み時、ログ画面API/表示時のどこで置換されるかを切り分ける。
- (2026-04-28) 同じ `configcheck_installer_msg` の確認用 `echo` がダッシュボードで2回表示された。`config_check.inc.php` / ダッシュボードタブ / config check 呼び出し経路が重複している可能性があるため、別途調査する。

## Decision Log

### 2026-04-26: 目的の明確化

- **修正**: 「DB肥大化問題の解決」→「AI自走デバッグの実現」
- **理由**: 現在のログ機構では構造化データがないため、AIがエラー箇所を特定できない
- **効果**:
  - JSONLines形式でエラーコンテキスト（スタックトレース、変数値、ユーザーID等）を保存
  - AI が `log:search` でエラーパターンを解析
  - 該当コードを自動修正し、再現テストまで実行可能
- **副次的効果**: DB肥大化の解決、運用性向上

### 2026-04-26: スコープの分離

- **決定**: システムログのみを対象とし、管理操作ログは別プランで対応
- **理由**:
  - データ取得方法が違う（`logEvent()` vs `logHandler`）
  - 用途が違う（デバッグ vs 監査）
  - テスト方法が違う（エラー再現 vs 操作履歴確認）
  - **管理操作ログはDB保存のメリットもある**（期間検索、統計分析、ユーザー絞り込み）
- **実装順序**: システムログを優先（AI開発に直結）
- **別プラン**: 管理操作ログ機構の改修プランで扱う（方針未定、DB継続も検討）

### 2026-04-26: ログ保存先の設計方針

- **決定**: ファイルベース、`temp/logs/{type}/YYYY/MM/{type}-YYYY-MM-DD.log` 形式
- **理由**: DB肥大化がバックアップ失敗の主原因。ファイルシステムでログローテーションと長期保存を分離
- **代替案**: JSONをDBに保存 → 肥大化問題が残る
- **代替案**: 1ファイルに全ログ蓄積 → サイズ問題が発生
- **選択した方式**: 日付・タイプ別ディレクトリで分割し、1ディレクトリあたり最大31ファイル

### 2026-04-26: PSR-3準拠の採用

- **決定**: PSR-3 Logger Interface に準拠
- **理由**: 業界標準の8レベルログ（RFC 5424）、コンテキスト配列による構造化データ
- **代替案**: 独自ログレベル（1=info, 2=warning, 3=error）継続 → 拡張性が低い
- **実装方針**: 既存の3段階は PSR-3 レベルにマッピング（1→info, 2→warning, 3→error）

### 2026-04-26: JSONLines形式の採用

- **決定**: 1行1ログのJSONLines形式で保存
- **理由**:
  - `grep` / `jq` / `tail -f` などUNIXツールで解析可能
  - ログローテーション時の部分読み込みが容易
  - PSR-3のコンテキスト配列を自然に保存
- **代替案**: 平文 → 構造化データを保存できない
- **代替案**: XML → 可読性が低い

### 2026-04-26: ファイルパスのセキュリティ設計

- **決定**: すべてのファイルパス（スタックトレース、caller情報等）を相対パスで記録
- **理由**:
  - OSSプロジェクトのため、ユーザーがフォーラムにログを貼り付けることがある
  - 物理パス（例：`/home/user/www/evo/`）が露出するとサーバー構成が推測される
  - 相対パス（例：`manager/actions/document/edit.php`）ならセキュリティリスクが低い
- **実装**: `MODX_BASE_PATH` を基準に `str_replace()` で変換
- **多層防御**: ログ書き込み時にも物理パスをプレースホルダ `{BASE_PATH}` で置換（最終防御層）
- **効果**: ログをそのままコピペしても、サーバーのディレクトリ構造が漏洩しない

### 2026-04-26: event_log テーブルの廃止方針

- **決定**: 新規インストールでは `event_log` テーブルを作成せず、既存インストールでは並行運用後に廃止
- **新規インストール**:
  - `install/sql/create_tables.sql` から `event_log` テーブル定義を削除
  - 最初からファイルベースログのみで動作
- **既存インストール**:
  - 互換レイヤー（`logEvent()`）を介してファイルベースログに記録
  - 通常経路での `event_log` へのDB書き込みは行わない
  - 既存の `event_log` テーブルは移行期間中に残置するが、新ログ機構のフォールバック保存先にはしない
  - 将来的に `event_log` テーブルは手動削除（マイグレーション機構で対応）
- **廃止対象ファイル**:
  - `manager/processors/delete_eventlog.processor.php`
  - `manager/processors/export_eventlog.processor.php`
  - `manager/actions/report/eventlog_details.dynamic.php`
  - これらは新ログ機構で不要（削除機能は管理画面から、エクスポートはJSON形式で対応）
- **理由**: 新規ユーザーには最初から新方式を提供し、既存ユーザーには移行期間を確保

### 2026-04-26: Sentry連携の扱い（今回は実装しない）

- **決定**: 本プランではSentry送信実装は行わず、将来連携可能な拡張ポイントのみを設計に含める
- **理由**:
  - 先にローカルJSONLinesの安定運用を確立し、観測データの品質を担保する
  - 外部送信は運用ポリシー（PII・マスキング・サンプリング）の合意後に導入する方が安全
- **実装方針**:
  - ロガー内部に `LogTransportInterface`（例: `send(array $entry): void`）を導入できる構造にする
  - 初期実装は `FileTransport` のみを有効化し、外部送信トランスポートは未実装のままにする
  - 送信前サニタイズは `Sanitizer` コンポーネントとして分離し、設定駆動（allowlist/mask）で拡張可能にする
  - 処理順序は常に「ローカル保存 → 外部送信（将来）」を前提とし、フォールバック可能性を担保する

### 2026-04-28: fatal error / Throwable 捕捉の追加方針

- **決定**: 通常の PHP warning handler だけでなく、管理画面アクションおよびフロントエンドリクエストの未捕捉 `Throwable` と shutdown 時の fatal error をシステムログに記録する
- **理由**:
  - `set_error_handler()` は `throw new RuntimeException()` のような未捕捉 `Throwable` を捕捉しない
  - parse error / fatal error は画面に出るだけではAI解析に使えず、ログファイルへ構造化して残す必要がある
  - 管理画面の action include 境界で捕捉すると、`manager_action`・request・user を合わせて記録できる
  - フロントエンドでは Snippet / Plugin / Template 由来の重大停止を `DocumentParser` のリクエスト文脈付きで残す必要がある
- **実装方針**:
  - `manager/index.php` の action dispatch を `try/catch (Throwable)` で包む
  - `index.php` の `executeParser()` 呼び出しを `try/catch (Throwable)` で包む
  - 捕捉した `Throwable` は `Logger` へ直接 `critical` 以上で記録し、`exception` context に class/message/file/line/trace を保存する
  - shutdown handler は管理画面とフロントエンドそれぞれの入口で `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR` などの fatal 系のみを対象にし、通常 warning と重複記録しない
  - ログ書き込み失敗時は画面表示を優先し、追加例外を投げない
  - 既存の `phpError()` / `messageQuit()` 経路は warning・notice・user warning 用として維持する
- **画面表示方針**:
  - 管理者には簡潔な fatal error 画面を表示し、HTML混じりの詳細はログに寄せる
  - フロントエンドでは公開サイトに詳細なスタックトレースを出さず、既存のエラー表示方針を尊重する
  - 物理パスは画面・ログとも相対パスまたは `{BASE_PATH}` へ正規化する

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

       tail -1 temp/logs/system/2026/04/system-2026-04-26.log | jq .

3. 期待される出力:

       {
         "timestamp": "2026-04-26T15:30:45+09:00",
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

       grep -E '/home/|/var/www/|C:\\' temp/logs/system/2026/04/*.log
       # 何も出力されなければOK

---

### ステップ2: 互換レイヤーの実装

**目的**: 既存コードを変更せずに新ログ機構に切り替える。`logEvent()` の公開インターフェースとメール通知の互換性は維持しつつ、永続化先をDBからファイルベースログへ置き換える。

**ファイル**: `manager/includes/traits/document.parser.subparser.trait.php`

**設計要点**:

1. **既存の引数をPSR-3にマッピング**
   - `$type = 1` → `Logger::INFO`
   - `$type = 2` → `Logger::WARNING`
   - `$type = 3` → `Logger::ERROR`

2. **追加コンテキスト情報**
   - `eventid`: 既存の `$evtid` を保持（互換性）
   - `source`: 既存の `$title`（例: 'Parser', 'DocumentParser'）
   - `legacy_type`: 既存の `$type`（1/2/3）を保持（移行時の検索互換性）

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
            'legacy_type' => $type,
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

       tail -1 temp/logs/system/2026/04/system-2026-04-26.log | jq '.context'
       # 出力: {"eventid": 101, "source": "DocumentParser", ...}

3. プラグイン・モジュールでの動作確認（後方互換性）:

       // 既存のプラグインコード（変更不要）
       $modx->logEvent(1, 1, 'Plugin executed', 'MyPlugin');
       # → 正常に新ログ機構に記録される

---

### ステップ3: システムログ画面の改修

**目的**: ログファイル選択と内容表示を提供。大量ログでも破綻しにくく、フォーラム報告やAI解析へ渡しやすい開発者向け画面を目指す。

**ファイル**: `manager/actions/report/eventlog.dynamic.php`（全面刷新）

**設計要点**:

1. **単一ペインのログ表示**
   - ログ本文は横幅いっぱいを使って表示する
   - 既定は「最新ログ」とし、複数ログファイルを横断して最新のログを表示する
   - 個別ファイルを確認したい場合は、画面上部の期間プルダウンで直近範囲または年を選ぶ
   - 年を選んだ場合は月プルダウンを表示し、最大365ファイルが一度に並ばないようにする

2. **フィルタリング機能**
   - ログレベル（PSR-3各レベル）
   - キーワード検索（メッセージ・コンテキスト全体を対象、該当文字列をハイライト）
   - 「最新ログ」では複数ファイルを横断して絞り込む

3. **操作機能**
   - 個別ファイル表示時のダウンロード（JSON形式そのまま、AI解析用）
   - ログ1件単位のコピー（フォーラム報告・Issue報告用）

4. **Ajax通信と大量ログ対応**（Vanilla JS）
   - 初期表示は直近の数件のみ取得
   - 古いログを読むために上方向へスクロールした時、未表示分を複数ファイル横断で追加取得する
   - 1ログ1 textarea ではなく、チャットログに近い連続表示にする

**UI構成**（ASCII art）:

    [期間: 最新ログ ▼] [Level: All ▼] [検索]
    最新ログを複数ファイルから横断表示しています。
    ┌──────────────────────────────────────────────────────────┐
    │ ERROR 2026-04-27T20:14:43+09:00 2026/04/system-... copy   │
    │ permission smoke test                                     │
    │ ▶ context                                                 │
    │                                                          │
    │ ERROR 2026-04-27T20:15:35+09:00 2026/04/system-... copy   │
    │ インストーラーが残されています                              │
    │ ▶ context                                                 │
    └──────────────────────────────────────────────────────────┘

**バックエンド処理**:

    // ログ画面
    GET /manager/index.php?a=114&period=latest

    // ログエントリ取得
    GET /manager/index.php?a=114&ajax=entries&period=latest&level=error&cursor_file=2026/04/system-2026-04-27.log&before=120
    → {"entries": [{...}, {...}], "has_more": true, "cursor_file": "2026/04/system-2026-04-27.log", "before_line": 101}

    // ログファイルダウンロード
    GET /manager/index.php?a=114&download=1&file=2026/04/system-2026-04-27.log

**検証方法**:

1. 管理画面「ツール」→「システムログ」にアクセス
2. 既定の期間が「最新ログ」になり、複数ファイル横断の最新ログが表示されることを確認
3. 期間プルダウンに「最新ログ」「直近30日」とログが存在する年が表示されることを確認
4. 年を選ぶと月プルダウンが表示され、その年月のログファイルだけがファイルプルダウンに表示されることを確認
5. ログが古い順から新しい順へ表示され、上方向スクロールで古いログが追加取得されることを確認
6. レベルフィルタとキーワードハイライトが機能することを確認
7. ログ1件コピーと、個別ファイル表示時のファイルダウンロードが動作することを確認

---

### ステップ4: 未捕捉 Throwable / fatal error の捕捉

**目的**: `set_error_handler()` では捕捉できない未捕捉例外・fatal error をJSONLinesログへ記録し、管理画面の重大停止もAI解析可能にする。

**ファイル**:

- `manager/index.php`
- `manager/includes/logger.class.php`
- 必要に応じて `manager/includes/traits/document.parser.subparser.trait.php`

**設計要点**:

1. **管理画面 action 境界の Throwable 捕捉**
   - `case 114` など既存 action dispatch の include 実行境界を `try/catch (Throwable)` で保護する
   - 捕捉時は `Logger::critical()` へ直接記録する
   - context に `exception.class`, `exception.message`, `exception.file`, `exception.line`, `exception.trace`, `manager_action` を含める

2. **shutdown fatal 捕捉**
   - `register_shutdown_function()` で `error_get_last()` を確認する
   - 対象は `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_CORE_WARNING`, `E_COMPILE_ERROR`, `E_COMPILE_WARNING` など、通常 error handler に届かない致命的エラーに限定する
   - `messageQuit()` 経由で既に処理済みの warning/error と重複しないようにする

3. **ログ内容**
   - `message`: 例外またはfatal errorの短い本文
   - `context.exception`: class/message/file/line/trace
   - `context.fatal`: type/number/file/line/source
   - `context.request`: 既存 Logger の自動収集を利用
   - パスは相対化し、物理パスをログに残さない

4. **画面表示**
   - 管理者向けには簡潔なエラー画面を返す
   - 詳細HTMLをログmessageに混ぜない
   - raw JSON/download リクエスト中に例外が起きた場合はJSONエラーを返す

**検証方法**:

    # warning は既存 phpError 経路で記録される
    trigger_error('mutate_content warning', E_USER_WARNING);

    # 未捕捉 Throwable が critical/error として記録される
    throw new RuntimeException('mutate_content fatal');

    # 構文エラーまたは include 中の fatal が shutdown handler で記録される
    # 期待: temp/logs/system/YYYY/MM/system-YYYY-MM-DD.log に exception または fatal context が出る

---

### ステップ5: フロントエンド側の未捕捉 Throwable / fatal error の捕捉

**目的**: 公開側リクエストで発生した未捕捉例外・fatal error をJSONLinesログへ記録し、Snippet / Plugin / Template / parser処理に由来する重大停止をAI解析可能にする。

**ファイル**:

- `index.php`
- `manager/includes/logger.class.php`
- 必要に応じて `manager/includes/document.parser.class.inc.php`

**DocumentParser への影響範囲**:

- `executeParser()`: フロントエンドの主入口。ここを `try/catch (Throwable)` の境界として扱う。
- `prepareResponse()`: ドキュメント解決・権限判定・テンプレート取得中の例外が捕捉対象になる。
- `parseDocumentSource()`: Snippet / Plugin / Template 解析中の未捕捉例外が捕捉対象になる。
- `postProcess()`: shutdown 登録後に発生する致命的エラーは shutdown handler の対象として扱う。通常の `postProcess()` 処理自体は変更しない。

**設計要点**:

1. **フロントコントローラ境界の Throwable 捕捉**
   - `index.php` の `executeParser()` 呼び出しを `try/catch (Throwable)` で保護する
   - 捕捉時は `Logger::critical()` へ直接記録する
   - context に `exception.class`, `exception.message`, `exception.file`, `exception.line`, `exception.trace`, `document_identifier`, `request` を含める

2. **shutdown fatal 捕捉**
   - フロントエンド用の `register_shutdown_function()` を追加し、`error_get_last()` を確認する
   - 対象は `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_CORE_WARNING`, `E_COMPILE_ERROR`, `E_COMPILE_WARNING` など、通常 error handler に届かない致命的エラーに限定する
   - 既存 `phpError()` / `messageQuit()` 経由の warning・notice と重複記録しない

3. **表示方針**
   - 公開側では詳細なスタックトレース・物理パスを画面に出さない
   - 既存の `messageQuit()` / 404 / site unavailable 表示と競合しない範囲で、簡潔なエラー応答に留める
   - `MODX_API_MODE` や captcha など、`executeParser()` を通らない入口は今回の対象外として明記する

4. **ログ内容**
   - `source`: `Frontend request` または `Frontend shutdown`
   - `context.exception`: class/message/file/line/trace
   - `context.fatal`: type/number/file/line/source
   - `context.document`: identifier, method, content type が取得可能な範囲で入る
   - パスは相対化し、物理パスをログに残さない

**検証方法**:

    # Snippet / Template 解析経路で未捕捉 Throwable が記録される
    throw new RuntimeException('frontend fatal smoke test');

    # parseDocumentSource() 以降で発生しても context.exception が記録される
    # 期待: temp/logs/system/YYYY/MM/system-YYYY-MM-DD.log に source=Frontend request の critical エントリが出る

    # 構文エラーまたは fatal が shutdown handler で記録される
    # 期待: context.fatal に type/number/file/line/source が出る

---

### ステップ6: CLIコマンドの実装

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

### ステップ7-10: 残りの実装

**ステップ7**: issue-resolver スキルの更新

- `.codex/skills/issue-resolver/SKILL.md` に新しいログ機構を活用した不具合解析機能を追加
- `/analyze-issue` コマンドで自動的にログ検索を実行
- `/reproduce` コマンドで `log:tail --follow` を使用したリアルタイム確認

**ステップ8**: 多言語対応

- `manager/includes/lang/*.inc.php` に新しい言語キーを追加
- 「イベントログ」→「システムログ」にメニュー名変更

**ステップ9**: インストールスクリプト修正

- `install/sql/create_tables.sql` から `event_log` テーブル定義を削除
- 旧DBログ用プロセッサー・詳細画面・CLIコマンドの削除

**ステップ10**: 統合テスト

- 全機能の動作確認
- セキュリティ検証（相対パス変換）
- 後方互換性確認（既存プラグイン・モジュール）

---

## Concrete Steps

1. PSR-3 準拠ロガーを追加する。
   編集対象ファイル: `manager/includes/logger.class.php`（新規）
   実行コマンド: `php -l manager/includes/logger.class.php`
   期待される観測結果: `No syntax errors detected` が表示され、`evo()->logEvent()` 実行で `temp/logs/system/YYYY/MM/system-YYYY-MM-DD.log` に JSONLines が追記される。
2. 既存 `logEvent()` の互換レイヤーを置換する。
   編集対象ファイル: `manager/includes/traits/document.parser.subparser.trait.php`
   実行コマンド: `php -l manager/includes/traits/document.parser.subparser.trait.php`
   期待される観測結果: 既存の `logEvent($evtid, $type, $msg, $title)` 呼び出しでファイルログへ記録され、呼び出し側コード変更が不要であり、通常経路では `event_log` に新規レコードが追加されない。
3. 管理画面のログ表示をファイルベースへ更新する。
   編集対象ファイル: `manager/actions/report/eventlog.dynamic.php`
   実行コマンド: `php -l manager/actions/report/eventlog.dynamic.php`
   期待される観測結果: 管理画面でログファイル一覧と選択ファイルの内容表示が可能で、レベルフィルタが機能する。
4. 未捕捉 `Throwable` と shutdown fatal error を捕捉する。
   編集対象ファイル: `manager/index.php`, `manager/includes/logger.class.php`
   実行コマンド: `php -l manager/index.php manager/includes/logger.class.php`
   期待される観測結果: 管理画面 action 内で `throw new RuntimeException('mutate_content fatal')` を実行すると、画面には簡潔なエラーが表示され、JSONLinesログに `context.exception` を含むエントリが記録される。
5. フロントエンド側の未捕捉 `Throwable` と shutdown fatal error を捕捉する。
   編集対象ファイル: `index.php`, `manager/includes/logger.class.php`, 必要に応じて `manager/includes/document.parser.class.inc.php`
   実行コマンド: `php -l index.php manager/includes/logger.class.php manager/includes/document.parser.class.inc.php`
   期待される観測結果: フロントエンドの Snippet / Template / Plugin 解析経路で `throw new RuntimeException('frontend fatal smoke test')` を実行すると、公開画面に詳細スタックトレースを出さず、JSONLinesログに `source=Frontend request` と `context.exception` を含むエントリが記録される。
6. CLI コマンド群を追加する。
   編集対象ファイル: `manager/includes/cli/commands/log-tail.php`, `manager/includes/cli/commands/log-search.php`, `manager/includes/cli/commands/log-clean.php`, `manager/includes/cli/commands/log-compress.php`
   実行コマンド: `php -l manager/includes/cli/commands/log-tail.php manager/includes/cli/commands/log-search.php manager/includes/cli/commands/log-clean.php manager/includes/cli/commands/log-compress.php`
   期待される観測結果: 各コマンドの構文エラーがなく、`./evo log:search system "error" --level=error --json` が JSON を返す。
7. 多言語・インストール定義・不要コードを更新する。
   編集対象ファイル: `manager/includes/lang/*.inc.php`, `install/sql/create_tables.sql`, `install/sql/fix_settings.php`, `install/convert2utf8mb4.php`, `manager/includes/upgrades/upd_db_structure.php`, `manager/index.php`, `manager/includes/actionlist.inc.php`, `manager/includes/cli/README.md`, `manager/processors/delete_eventlog.processor.php`, `manager/processors/export_eventlog.processor.php`, `manager/actions/report/eventlog_details.dynamic.php`, `manager/includes/cli/commands/log-show.php`, `manager/includes/cli/commands/log-clear.php`
   実行コマンド: `rg -n "event_log|delete_eventlog.processor|export_eventlog.processor|eventlog_details|log:show|log:clear" install manager/actions/report manager/processors manager/includes/cli manager/includes/upgrades manager/index.php`
   期待される観測結果: 新規インストールで `event_log` テーブル定義が除外され、旧DBログ用画面・プロセッサー・CLIコマンドへの入口が残っていない。
8. 外部連携の拡張ポイントを固定する（Sentry実装はしない）。
   編集対象ファイル: `manager/includes/logger.class.php`（インターフェース導入方針のコメント/構造）, `assets/docs/architecture.md` または本Planの `Interfaces and Dependencies`
   実行コマンド: `rg -n "Transport|Sanitizer|fallback|external" manager/includes/logger.class.php .agent/plans/2026-04-26-logging-system-refactor.md`
   期待される観測結果: 外部送信を差し込む責務境界が明記され、現時点では `FileTransport` のみ有効であることが確認できる。

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

3. **致命的エラーの観測**
   - 管理画面 action 内の未捕捉 `Throwable` がログに記録される
   - フロントエンドの `executeParser()` / `prepareResponse()` / `parseDocumentSource()` 経路で発生した未捕捉 `Throwable` がログに記録される
   - shutdown 時に検出可能な fatal error がログに記録される
   - warning/error handler 経由のログと重複しない

4. **セキュリティ**
   - ログ内のすべてのファイルパスが相対パスになっている
   - 物理パス（/home/, /var/www/, C:\）が含まれない

5. **運用性**
   - ログファイルが日付別に分割されている
   - 100MB超過で自動ローテーションされる
   - 古いログが自動削除される（設定可能）

6. **将来拡張性（Sentry対応準備）**
   - 外部送信トランスポートを差し込める拡張ポイントが定義されている
   - 現時点で外部送信が無効でも、ローカル保存のみで完結して動作する
   - 送信前サニタイズ方針（allowlist/mask）が設計として明記されている

### テストシナリオ（コマンドと期待観測結果）

1. `php -l manager/includes/logger.class.php manager/includes/traits/document.parser.subparser.trait.php manager/actions/report/eventlog.dynamic.php`
   期待観測結果: すべて `No syntax errors detected`。
2. `./evo log:search system "error" --level=error --json | jq 'length'`
   期待観測結果: 0 以上の整数が返り、JSON として解釈できる。
3. `./evo log:tail system --lines=5`
   期待観測結果: 最新5件のログが時系列で表示される。
4. `grep -E '/home/|/var/www/|C:\\\\' temp/logs/system/*/*/*.log`
   期待観測結果: 物理パスに一致する行が出力されない。
5. 管理画面「ツール → システムログ」で最新ログを選択する。
   期待観測結果: JSON 表示、レベルフィルタ、ダウンロードが動作する。
6. 既存コードから `evo()->logEvent(101, 3, 'Document not found', 'DocumentParser')` を実行する。
   期待観測結果: 新ログへ `eventid=101` と `source=DocumentParser` を含むエントリが記録される。
7. 管理画面 action 内で `throw new RuntimeException('fatal smoke test')` を一時的に実行する。
   期待観測結果: 画面には簡潔なエラーが表示され、ログには `level=critical` または `level=error`、`context.exception.class=RuntimeException`、相対パス化された `file` が記録される。
8. フロントエンドの Snippet / Template / Plugin 解析経路で `throw new RuntimeException('frontend fatal smoke test')` を一時的に実行する。
   期待観測結果: 公開画面には詳細なスタックトレースや物理パスが表示されず、ログには `source=Frontend request`、`context.exception.class=RuntimeException`、相対パス化された `file` が記録される。

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

中断・失敗時は次の手順で復帰する。

1. `git diff -- manager/includes/logger.class.php manager/includes/traits/document.parser.subparser.trait.php manager/actions/report/eventlog.dynamic.php manager/includes/cli/commands/log-tail.php manager/includes/cli/commands/log-search.php manager/includes/cli/commands/log-clean.php manager/includes/cli/commands/log-compress.php install/sql/create_tables.sql` で差分を確認する。
2. 直近作業を破棄する場合は `git restore --source=HEAD -- manager/includes/logger.class.php manager/includes/traits/document.parser.subparser.trait.php manager/actions/report/eventlog.dynamic.php manager/includes/cli/commands/log-tail.php manager/includes/cli/commands/log-search.php manager/includes/cli/commands/log-clean.php manager/includes/cli/commands/log-compress.php install/sql/create_tables.sql` を実行する。
3. 既存ログデータのみ退避したい場合は `cp -a temp/logs temp/logs.backup.$(date +%Y%m%d%H%M%S)` を実行してから再開する。
4. 復帰後に `php -l` と `./evo log:search system "error" --level=error --json` を再実行し、動作基線を確認する。

---

## Artifacts and Notes

### ログエントリの例

    {
      "timestamp": "2026-04-26T14:30:45+09:00",
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
- **将来の外部送信先（例: Sentry）**: `LogTransportInterface` を介して追加可能とし、現時点では未接続

### 破壊的変更なし

既存APIは互換レイヤーで維持されるため、プラグイン・モジュールへの影響なし。
