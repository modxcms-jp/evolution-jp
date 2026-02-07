# ExecPlan: システムログ機構の改修（AI自走デバッグの実現）

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
- **別プラン作成予定**: `2026-XX-XX-manager-log-refactor.md`（方針未定、DB継続も検討）
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

### 2026-02-07: ログタイプの分離
- **決定**: システムログ (`system/`) と管理操作ログ (`manager/`) をルートから分離
- **理由**: 用途が異なる（システム診断 vs 監査）、保存期間・アクセス権限を個別管理
- **命名変更**: 「イベントログ」→「システムログ」（より直感的、UNIX慣習と一致）

### 2026-02-07: 管理画面の設計方針
- **決定**: リッチUIを廃止し、textarea による生ログ表示 + 検索・フィルタ機能
- **理由**: 既存画面は見やすいが実用性が低い。開発者はログファイルのそのままの形式を好む
- **表示件数**: 最新10-20件のみ。それ以前は手動でサーバアクセス（セキュリティ面でも良い）

### 2026-02-07: ファイルパスのセキュリティ設計
- **決定**: すべてのファイルパス（スタックトレース、caller情報等）を相対パスで記録
- **理由**:
  - OSSプロジェクトのため、ユーザーがフォーラムにログを貼り付けることがある
  - 物理パス（例：`/home/user/www/evo/`）が露出するとサーバー構成が推測される
  - 相対パス（例：`manager/actions/document/edit.php`）ならセキュリティリスクが低い
- **実装**: `MODX_BASE_PATH` を基準に `str_replace()` で変換
- **多層防御**: ログ書き込み時にも物理パスをプレースホルダ `{BASE_PATH}` で置換（最終防御層）
- **効果**: ログをそのままコピペしても、サーバーのディレクトリ構造が漏洩しない
- **既知の制約**:
  - 物理パスが短い場合（例：`/app/`）、ログメッセージ中の文字列も置換される可能性
  - ただしプレースホルダ置換なら意図が明確で、安全側に倒れる挙動なので許容範囲
  - 例：「/app/config.php を読み込み」→「{BASE_PATH}/config.php を読み込み」（意味は通じる）

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
（実装完了後に記載）

## Next Steps
このプラン完了後、管理操作ログの改修方針を決定する：
- **オプションA**: ファイルベース移行（システムログと統一）
- **オプションB**: DB保存継続 + 構造化（JSONカラム追加、検索性向上）
- **オプションC**: ハイブリッド（短期: DB、長期: ファイルアーカイブ）

検討事項：
- 管理操作ログの主要ユースケース（誰がいつ何を変更したか）
- 検索性の要求（期間・ユーザー・操作種別による絞り込み）
- 統計レポート機能の必要性
- 法令対応（監査ログの保存期間・改ざん防止）

## Context and Orientation

### 現在のログ機構
Evolution CMS は2種類のログをDBに保存している：

**1. システムログ** (`event_log` テーブル) ← **このプランの対象**
- エラー、警告、情報メッセージ
- `logEvent($evtid, $type, $msg, $title)` で記録
- 実装: `manager/includes/traits/document.parser.subparser.trait.php`
- ログレベルは3段階（1=情報, 2=警告, 3=エラー）
- 影響範囲: 26箇所

**2. 管理操作ログ** (`manager_log` テーブル) ← **別プラン（未作成）で対応**
- 管理者の操作履歴（ドキュメント編集、ユーザー作成など）
- `logHandler` クラスで記録
- 実装: `manager/includes/log.class.inc.php`
- 影響範囲: 28箇所
- **方針未定**: DBのメリット（高速検索、統計分析）も考慮し、ファイルベース移行を再検討中

### 問題点（システムログに限定）
- **構造化されていない**: `<pre>` タグや `print_r()` の文字列がそのまま保存
- **AIが解析できない**: エラーコンテキスト（スタックトレース、変数値等）が失われる
- **ログレベルの制約**: 3段階のみで、業界標準（PSR-3）との乖離
- **検索性の低さ**: DB クエリでの全文検索は重く、パターン抽出が困難
- **DB肥大化**: バックアップ失敗の一因（副次的問題）

### 影響範囲（システムログのみ）
- **ログ記録箇所**: 26箇所（`logEvent` 呼び出し）
- **管理画面**:
  - `manager/actions/report/eventlog.dynamic.php` (a=114)
  - `manager/actions/report/eventlog_details.dynamic.php` (詳細表示)
- **プロセッサ**:
  - `manager/processors/delete_eventlog.processor.php`
  - `manager/processors/export_eventlog.processor.php`
- **DBテーブル**: `event_log`

### AI自走デバッグの実現方法

1. **エラーコンテキストの保存**
   ```json
   {
     "timestamp": "2026-02-07T14:30:45+09:00",
     "level": "error",
     "message": "Undefined variable: document",
     "context": {
       "file": "manager/actions/document/edit.php",
       "line": 123,
       "trace": ["..."],
       "variables": {"id": 5, "action": "edit"},
       "user": 1,
       "url": "/manager/?a=27&id=5"
     }
   }
   ```

2. **AIエージェントのワークフロー**
   - `./evo log:search "Undefined variable" --level=error` でエラーパターン抽出
   - コンテキストから該当ファイル・行を特定
   - コード解析して修正案を生成
   - パッチ適用後、同一操作で再現テスト
   - ログに同じエラーが出なければ完了

3. **従来との比較**
   - 従来: 「Undefined variable: document」という文字列のみ → 手動でファイル検索
   - 新方式: ファイル・行・スタックトレースが構造化 → AI が自動特定・修正

## Plan of Work

### アプローチ
1. **PSR-3準拠ロガー実装** → 新しいログ基盤を構築
2. **コンテキスト収集強化** → スタックトレース、変数ダンプを自動取得
3. **互換レイヤー維持** → 既存 `logEvent()` を段階的に移行
4. **管理画面刷新** → JSONLines 表示、検索・フィルタ機能
5. **CLIツール拡充** → AIエージェントが使いやすいインターフェース

### 技術選定
- **PSR-3 Logger Interface**: 8レベルログ（emergency, alert, critical, error, warning, notice, info, debug）
- **JSONLines形式**: 1行1ログ、`jq` / `grep` で解析可能
- **ファイル構成**: `temp/logs/system/YYYY/MM/system-YYYY-MM-DD.log`
- **自動コンテキスト収集**: `debug_backtrace()` でスタックトレース取得

### 実装の順序
1. ロガー基盤（`logger.class.php`）+ コンテキスト収集
2. 互換レイヤー（既存 `logEvent()` を新ロガーで実装）
3. システムログ画面（ファイル一覧・JSONLines表示）
4. CLIコマンド（AI向けに最適化）
5. **issue-resolver スキルの更新**（ログ解析機能統合）
6. ドキュメント・多言語対応

## Concrete Steps

### ステップ1: PSR-3準拠ロガークラスの作成（コンテキスト収集強化）

**ファイル**: `manager/includes/logger.class.php`

```php
<?php
/**
 * PSR-3準拠ログクラス（AI自走デバッグ対応）
 */
class Logger
{
    private $logDir;
    private $logType = 'system';
    private $maxFileSize = 104857600; // 100MB
    private $retentionDays = 30;

    // PSR-3ログレベル定義
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    public function __construct()
    {
        $this->logDir = MODX_BASE_PATH . 'temp/logs/system/';
    }

    // PSR-3 メソッド
    public function emergency($message, array $context = []) { $this->log(self::EMERGENCY, $message, $context); }
    public function alert($message, array $context = []) { $this->log(self::ALERT, $message, $context); }
    public function critical($message, array $context = []) { $this->log(self::CRITICAL, $message, $context); }
    public function error($message, array $context = []) { $this->log(self::ERROR, $message, $context); }
    public function warning($message, array $context = []) { $this->log(self::WARNING, $message, $context); }
    public function notice($message, array $context = []) { $this->log(self::NOTICE, $message, $context); }
    public function info($message, array $context = []) { $this->log(self::INFO, $message, $context); }
    public function debug($message, array $context = []) { $this->log(self::DEBUG, $message, $context); }

    public function log($level, $message, array $context = [])
    {
        // 自動コンテキスト収集（AIデバッグ用）
        if (empty($context['trace']) && in_array($level, [self::ERROR, self::WARNING])) {
            $context = array_merge($context, $this->collectContext());
        }

        $logEntry = [
            'timestamp' => date('c'), // ISO 8601
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $logFile = $this->getCurrentLogFile();
        $this->ensureLogDirectory(dirname($logFile));
        $this->writeLog($logFile, $logEntry);
        $this->checkRotation($logFile);
    }

    /**
     * エラーコンテキストの自動収集（AI自走デバッグ用）
     *
     * セキュリティ考慮：すべてのファイルパスを相対パスに変換
     * （フォーラム投稿時にサーバーの物理パスが露出しないように）
     */
    private function collectContext()
    {
        $context = [];

        // スタックトレース（簡略版、すべて相対パス）
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $context['trace'] = array_map(function($t) {
            return [
                'file' => $this->toRelativePath($t['file'] ?? ''),
                'line' => $t['line'] ?? 0,
                'function' => $t['function'] ?? '',
                'class' => $t['class'] ?? '',
            ];
        }, $trace);

        // 呼び出し元ファイル・行（相対パス）
        if (!empty($trace[2])) {
            $context['caller'] = [
                'file' => $this->toRelativePath($trace[2]['file'] ?? ''),
                'line' => $trace[2]['line'] ?? 0,
            ];
        }

        // リクエスト情報
        $context['request'] = [
            'url' => serverv('REQUEST_URI'),
            'method' => serverv('REQUEST_METHOD'),
            'ip' => serverv('REMOTE_ADDR'),
        ];

        // ユーザー情報
        if (function_exists('evo')) {
            $context['user'] = evo()->getLoginUserID();
        }

        return $context;
    }

    /**
     * ファイルパスを相対パスに変換（セキュリティ対策）
     *
     * @param string $path 絶対パス
     * @return string CMSルートからの相対パス
     */
    private function toRelativePath($path)
    {
        if (empty($path)) {
            return '';
        }

        // MODX_BASE_PATH からの相対パスに変換
        $relativePath = str_replace(MODX_BASE_PATH, '', $path);

        // Windows のバックスラッシュを統一
        $relativePath = str_replace('\\', '/', $relativePath);

        // 先頭のスラッシュを削除
        return ltrim($relativePath, '/');
    }

    private function getCurrentLogFile()
    {
        $date = date('Y-m-d');
        $year = date('Y');
        $month = date('m');
        $dir = $this->logDir . $year . '/' . $month . '/';
        return $dir . 'system-' . $date . '.log';
    }

    private function ensureLogDirectory($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function writeLog($logFile, array $logEntry)
    {
        $jsonLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        // セキュリティ：多層防御として物理パスをプレースホルダに置換
        // toRelativePath() で変換済みだが、万が一残っていた場合の最終防御
        $jsonLine = str_replace(MODX_BASE_PATH, '{BASE_PATH}/', $jsonLine);

        // Windowsのバックスラッシュパスも対応
        $winPath = str_replace('/', '\\', MODX_BASE_PATH);
        $jsonLine = str_replace($winPath, '{BASE_PATH}\\', $jsonLine);

        file_put_contents($logFile, $jsonLine, FILE_APPEND | LOCK_EX);
    }

    private function checkRotation($logFile)
    {
        if (!file_exists($logFile)) {
            return;
        }

        $fileSize = filesize($logFile);
        if ($fileSize > $this->maxFileSize) {
            $this->rotateFile($logFile);
        }
    }

    private function rotateFile($logFile)
    {
        $counter = 1;
        $rotatedFile = $logFile . '.' . $counter;
        while (file_exists($rotatedFile)) {
            $counter++;
            $rotatedFile = $logFile . '.' . $counter;
        }
        rename($logFile, $rotatedFile);
    }

    public function cleanOldLogs()
    {
        $cutoffTime = time() - ($this->retentionDays * 86400);
        $this->cleanDirectory($this->logDir, $cutoffTime);
    }

    private function cleanDirectory($dir, $cutoffTime)
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . $item;
            if (is_dir($path)) {
                $this->cleanDirectory($path . '/', $cutoffTime);
                // 空ディレクトリの削除
                if (count(scandir($path)) === 2) {
                    rmdir($path);
                }
            } elseif (is_file($path)) {
                if (filemtime($path) < $cutoffTime) {
                    unlink($path);
                }
            }
        }
    }

    public function getLogFiles($limit = 20)
    {
        $files = [];
        $this->collectLogFiles($this->logDir, $files);

        // 更新日時でソート（新しい順）
        usort($files, function($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });

        return array_slice($files, 0, $limit);
    }

    private function collectLogFiles($dir, &$files)
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . $item;
            if (is_dir($path)) {
                $this->collectLogFiles($path . '/', $files);
            } elseif (is_file($path) && preg_match('/\\.log(\\.\\d+)?$/', $item)) {
                $files[] = [
                    'path' => $path,
                    'name' => $item,
                    'size' => filesize($path),
                    'mtime' => filemtime($path),
                    'lines' => $this->countLines($path),
                ];
            }
        }
    }

    private function countLines($file)
    {
        $count = 0;
        $handle = fopen($file, 'r');
        if ($handle) {
            while (!feof($handle)) {
                fgets($handle);
                $count++;
            }
            fclose($handle);
        }
        return $count;
    }

    public function readLogFile($filePath, $filter = [])
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $logs = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $log = json_decode($line, true);
            if (!$log) {
                continue;
            }

            // フィルタリング
            if (!empty($filter['level']) && $log['level'] !== $filter['level']) {
                continue;
            }
            if (!empty($filter['keyword']) && stripos(json_encode($log), $filter['keyword']) === false) {
                continue;
            }

            $logs[] = $log;
        }

        fclose($handle);
        return $logs;
    }
}
```

### ステップ2: 互換レイヤーの実装

**ファイル**: `manager/includes/traits/document.parser.subparser.trait.php` (既存の `logEvent()` を置き換え)

```php
function logEvent($evtid, $type, $msg, $title = 'Parser')
{
    // 既存の引数をPSR-3にマッピング
    $levelMap = [
        1 => Logger::INFO,
        2 => Logger::WARNING,
        3 => Logger::ERROR,
    ];
    $level = $levelMap[$type] ?? Logger::INFO;

    // コンテキスト情報（collectContext()で自動収集されるが、追加情報も渡す）
    $context = [
        'eventid' => $evtid,
        'source' => $title,
    ];

    // 新しいロガーで記録
    if (!class_exists('Logger')) {
        require_once MODX_CORE_PATH . 'logger.class.php';
    }
    $logger = new Logger();
    $logger->log($level, $msg, $context);

    // メール通知（既存機能を維持）
    if (config('send_errormail') && config('send_errormail') <= $type) {
        // ... 既存のメール送信処理 ...
        $body['URL'] = MODX_SITE_URL . ltrim(evo()->server('REQUEST_URI'), '/');
        $body['Source'] = $title;
        $body['IP'] = evo()->server('REMOTE_ADDR');
        if (evo()->server('REMOTE_ADDR')) {
            $hostname = gethostbyaddr(evo()->server('REMOTE_ADDR'));
        }
        if ($hostname) {
            $body['Host'] = $hostname;
        }
        $body['Message'] = $msg;

        $to = config('emailsender');
        $subject = '[Evolution CMS] Error Log';
        $headers = 'From: ' . config('emailsender');
        $message = '';
        foreach ($body as $key => $value) {
            $message .= "{$key}: {$value}\n";
        }
        @mail($to, $subject, $message, $headers);
    }
}
```

### ステップ3: システムログ画面の改修

**ファイル**: `manager/actions/report/eventlog.dynamic.php` (全面刷新)

主要機能：
- ログファイル一覧表示（最新10-20件）
- ファイル選択による内容表示（Ajax、JSONLines整形表示）
- レベル・キーワード検索
- ダウンロード・削除機能
- **AI向け**: JSON形式そのままでダウンロード可能

UI設計（Vanilla JS、jQueryなし）：
```html
<div class="section">
    <div class="sectionHeader">システムログ</div>
    <div class="sectionBody">
        <!-- ログファイル一覧 -->
        <table id="logFileList">
            <thead>
                <tr>
                    <th>ファイル名</th>
                    <th>サイズ</th>
                    <th>レコード数</th>
                    <th>更新日時</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <!-- JavaScriptで動的生成 -->
            </tbody>
        </table>

        <!-- ログ内容表示 -->
        <div id="logViewer">
            <div class="filters">
                <select id="levelFilter">
                    <option value="">全レベル</option>
                    <option value="error">エラー</option>
                    <option value="warning">警告</option>
                    <option value="info">情報</option>
                </select>
                <input type="text" id="keywordFilter" placeholder="キーワード検索">
                <button onclick="applyFilter()">フィルタ</button>
            </div>
            <textarea id="logContent" readonly style="width:100%; height:500px; font-family:monospace;"></textarea>
        </div>
    </div>
</div>
```

### ステップ4: CLIコマンドの実装（AI向けに最適化）

**ファイル**: `manager/includes/cli/commands/log-tail.php`

```php
<?php
// Usage: ./evo log:tail system [--lines=20] [--follow]

$lines = 20;
$follow = false;
foreach ($args as $arg) {
    if (strpos($arg, '--lines=') === 0) {
        $lines = (int)substr($arg, 8);
    }
    if ($arg === '--follow' || $arg === '-f') {
        $follow = true;
    }
}

$logger = new Logger();
$files = $logger->getLogFiles(1);
if (empty($files)) {
    echo "ログファイルがありません。\n";
    exit(1);
}

$logFile = $files[0]['path'];
if ($follow) {
    $command = sprintf('tail -f %s', escapeshellarg($logFile));
} else {
    $command = sprintf('tail -n %d %s', $lines, escapeshellarg($logFile));
}
passthru($command);
```

**ファイル**: `manager/includes/cli/commands/log-search.php`

```php
<?php
// Usage: ./evo log:search system [keyword] [--level=error] [--json]
// AI向け: JSON形式で出力可能

$keyword = $args[0] ?? '';
$level = '';
$jsonOutput = false;

foreach ($args as $arg) {
    if (strpos($arg, '--level=') === 0) {
        $level = substr($arg, 8);
    }
    if ($arg === '--json') {
        $jsonOutput = true;
    }
}

$logger = new Logger();
$files = $logger->getLogFiles(20);

$results = [];
foreach ($files as $file) {
    $logs = $logger->readLogFile($file['path'], [
        'level' => $level,
        'keyword' => $keyword,
    ]);

    if (!empty($logs)) {
        if ($jsonOutput) {
            $results = array_merge($results, $logs);
        } else {
            echo "=== {$file['name']} ===\n";
            foreach ($logs as $log) {
                echo json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            }
        }
    }
}

if ($jsonOutput && !empty($results)) {
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
```

**ファイル**: `manager/includes/cli/commands/log-rotate.php`

```php
<?php
// Usage: ./evo log:rotate system

$logger = new Logger();
$logger->cleanOldLogs();
echo "システムログのローテーションを実行しました。\n";
```

**ファイル**: `manager/includes/cli/commands/log-clean.php`

```php
<?php
// Usage: ./evo log:clean system [--days=30] [--auto] [--dry-run]

$days = 30;
$auto = false;
$dryRun = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $days = (int)substr($arg, 7);
    }
    if ($arg === '--auto') {
        $auto = true;
        $days = (int)evo()->getConfig('log_retention_days', 30);
    }
    if ($arg === '--dry-run') {
        $dryRun = true;
    }
}

$logger = new Logger();
$result = $logger->deleteOldLogs($days, $dryRun);

if ($dryRun) {
    echo "[プレビュー] 削除対象ファイル数: {$result['count']}件 ({$result['size']} MB)\n";
    foreach ($result['files'] as $file) {
        echo "  - {$file}\n";
    }
} else {
    echo "削除したファイル数: {$result['count']}件 ({$result['size']} MB を解放)\n";
}
```

**ファイル**: `manager/includes/cli/commands/log-compress.php`

```php
<?php
// Usage: ./evo log:compress system [--days=7] [--auto]

$days = 7;
$auto = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $days = (int)substr($arg, 7);
    }
    if ($arg === '--auto') {
        $auto = true;
        // グローバル設定 log_compress が無効なら何もしない
        if (!evo()->getConfig('log_compress', true)) {
            exit(0);
        }
    }
}

$logger = new Logger();
$result = $logger->compressOldLogs($days);

echo "圧縮したファイル数: {$result['count']}件\n";
echo "圧縮前: {$result['before_size']} MB → 圧縮後: {$result['after_size']} MB\n";
echo "節約: {$result['saved_size']} MB ({$result['ratio']}% 削減)\n";
```

### ステップ5: issue-resolver スキルの更新

**ファイル**: `.claude/skills/issue-resolver/SKILL.md`

新しいログ機構を活用した不具合解析機能を追加：

```markdown
### analyze-issue <URL|テキスト>
1. URLならfetchで内容取得
2. **【NEW】エラー関連なら最新ログから自動解析**
   ```bash
   ./evo log:search system "error_keyword" --level=error --json
   ```
3. `AGENTS.md` のドキュメントマップから関連ファイルを特定
4. **【NEW】ログのcontext.callerからエラー箇所を自動特定**
5. 現象の要約と原因仮説を3つ提示（ログ情報を根拠に含める）
6. 情報不足時はユーザーへの質問リストを作成

### reproduce
- 現象を再現する最小限のPHPコードを作成
- **【NEW】再現時のログ出力を確認**:
  ```bash
  ./evo log:tail system --follow
  ```
- デバッグ用ログの挿入は新ロガー使用:
  ```php
  $logger = new Logger();
  $logger->debug('Debug point', ['variable' => $value]);
  ```

### implement-fix
- draft-planに基づきコードを修正
- **【NEW】修正前後でログを比較検証**:
  ```bash
  # 修正前のエラー確認
  ./evo log:search system "error_pattern" --json > before.json

  # 修正後、同じ操作を実行
  ./evo log:search system "error_pattern" --json > after.json

  # エラーが消えたことを確認
  diff before.json after.json
  ```

### archive
- Conventional Commits形式のコミットメッセージ生成
- `assets/docs/troubleshooting/solved-issues.md` にナレッジを追記
  - **【NEW】エラーログの例（JSON形式）を含める**
  - **【NEW】修正後のログ出力例を含める**
```

**追加ドキュメント**: `.claude/skills/issue-resolver/log-analysis-guide.md`

```markdown
# ログ解析ガイド（issue-resolver向け）

## エラーログの構造

JSONLines形式のログエントリ例：
\`\`\`json
{
  "timestamp": "2026-02-07T14:30:45+09:00",
  "level": "error",
  "message": "Undefined variable: document",
  "context": {
    "caller": {
      "file": "manager/actions/document/edit.php",
      "line": 123
    },
    "trace": [
      {"file": "manager/includes/document.class.php", "line": 100, "function": "editDocument"},
      {"file": "manager/actions/footer.inc.php", "line": 50, "function": "processAction"}
    ],
    "request": {
      "url": "/manager/?a=27&id=5",
      "method": "POST",
      "ip": "192.168.1.1"
    },
    "user": 1
  }
}
\`\`\`

## CLI活用パターン

### 1. エラーパターンの検出
\`\`\`bash
# 特定エラーメッセージを検索
./evo log:search system "Undefined variable" --level=error --json

# 特定ファイルのエラーを検索
./evo log:search system "document/edit.php" --level=error --json

# 直近のエラーを確認
./evo log:tail system --lines=50 | grep '"level":"error"'
\`\`\`

### 2. 再現確認
\`\`\`bash
# リアルタイムでログを監視
./evo log:tail system --follow

# 特定操作後のログを確認
./evo log:tail system --lines=10
\`\`\`

### 3. 修正検証
\`\`\`bash
# エラー件数のカウント
./evo log:search system "error_pattern" --json | jq 'length'

# 特定期間のエラー推移
for date in 2026-02-{05..07}; do
  count=$(cat temp/logs/system/2026/02/system-$date.log | grep '"level":"error"' | wc -l)
  echo "$date: $count errors"
done
\`\`\`

## analyze-issue でのログ活用

不具合報告に「エラーメッセージ」が含まれる場合：

1. **ログ検索**: エラーメッセージをキーワードに `log:search` 実行
2. **エラー箇所特定**: `context.caller.file` と `context.caller.line` から該当コードを開く
3. **コンテキスト確認**: `context.request` と `context.user` で再現条件を把握
4. **スタックトレース**: `context.trace` で呼び出し経路を追跡

これにより、フォーラム報告から実際のエラー箇所まで自動で到達可能。
\`\`\`
```

### ステップ6: 多言語対応

**ファイル**: `manager/includes/lang/japanese-utf8.inc.php`

```php
$_lang["system_log"] = 'システムログ';
$_lang["log_file_list"] = 'ログファイル一覧';
$_lang["log_content"] = 'ログ内容';
$_lang["log_filter"] = 'フィルタ';
$_lang["log_download"] = 'ダウンロード';
$_lang["log_delete"] = '削除';
$_lang["log_level"] = 'ログレベル';
```

**ファイル**: `manager/includes/lang/english.inc.php`

```php
$_lang["system_log"] = 'System Log';
$_lang["log_file_list"] = 'Log Files';
$_lang["log_content"] = 'Log Content';
$_lang["log_filter"] = 'Filter';
$_lang["log_download"] = 'Download';
$_lang["log_delete"] = 'Delete';
$_lang["log_level"] = 'Log Level';
```

メニュー名の変更箇所を検索：
```bash
./evo grep:search "イベントログ|eventlog" --include="*.inc.php"
```

### ステップ7: インストールスクリプト修正・旧クラス削除

**1. 新規インストール時の対応**

**ファイル**: `install/sql/create_tables.sql`

```sql
-- event_log テーブル定義を削除（コメントアウトまたは完全削除）
-- CREATE TABLE IF NOT EXISTS `event_log` (
--   `id` int(11) NOT NULL AUTO_INCREMENT,
--   `eventid` int(11) DEFAULT '0',
--   ...
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

新規インストールでは最初からファイルベースログのみで動作。

**2. 旧プロセッサー・画面の削除**

以下のファイルを削除（既にファイルベースログで代替済み）：

```bash
# 削除対象ファイル
rm manager/processors/delete_eventlog.processor.php
rm manager/processors/export_eventlog.processor.php
rm manager/actions/report/eventlog_details.dynamic.php
```

- `delete_eventlog.processor.php`: 新ログ機構では管理画面から削除可能
- `export_eventlog.processor.php`: JSON形式でダウンロード機能で代替
- `eventlog_details.dynamic.php`: ログ詳細表示は新画面で実現

**3. 既存インストールへのマイグレーション案内**

`install/upgrade.php` または README に記載：

```markdown
### v1.3.0: システムログ機構の変更

システムログがファイルベースに変更されました。既存の `event_log` テーブルは使用されません。

- **新ログ保存先**: `temp/logs/system/YYYY/MM/`
- **旧ログの確認**: 管理画面「システムログ」から参照可能
- **event_log テーブルの削除**: 手動で削除可能（データベースツールで実行）
  ```sql
  DROP TABLE IF EXISTS `event_log`;
  ```
```

**4. 削除確認**

```bash
# git管理下から削除
git rm manager/processors/delete_eventlog.processor.php
git rm manager/processors/export_eventlog.processor.php
git rm manager/actions/report/eventlog_details.dynamic.php

# コミット
git commit -m "Remove legacy event_log related files

- Replaced by new file-based logging system
- event_log table no longer created for new installations"
```

### ステップ8: 統合テスト

テストシナリオ：

**1. ログ記録のテスト（コンテキスト収集・セキュリティの確認）**
```php
// 意図的にエラーを発生させる
evo()->logEvent(0, 3, 'テストエラー: 変数未定義', 'Test');

// ログファイルを確認
$logFile = sprintf(
    'temp/logs/system/%s/%s/system-%s.log',
    date('Y'),
    date('m'),
    date('Y-m-d')
);
$lastLine = exec("tail -n 1 {$logFile}");
$log = json_decode($lastLine, true);

// 検証項目
assert($log['level'] === 'error');
assert(!empty($log['context']['trace'])); // スタックトレース
assert(!empty($log['context']['caller'])); // 呼び出し元
assert(!empty($log['context']['request'])); // リクエスト情報

// セキュリティ検証：すべて相対パスであること
assert(strpos($log['context']['caller']['file'], MODX_BASE_PATH) === false); // 絶対パスが含まれないこと
assert(strpos($log['context']['caller']['file'], '/') !== 0); // 先頭が / でないこと
foreach ($log['context']['trace'] as $t) {
    assert(strpos($t['file'], MODX_BASE_PATH) === false); // 各トレースも相対パス
    assert(strpos($t['file'], '{BASE_PATH}') === false); // プレースホルダも含まれない（正常に相対パス化されている）
}
echo "✓ ファイルパスがすべて相対パスに変換されている\n";

// 多層防御のテスト：意図的に絶対パスを含むログを書き込む
$logger = new Logger();
$testLog = [
    'timestamp' => date('c'),
    'level' => 'debug',
    'message' => 'Test: ' . MODX_BASE_PATH . 'config.php',
    'context' => ['test_path' => MODX_BASE_PATH . 'manager/includes/test.php']
];
$logger->writeLog($logFile, $testLog);

// 書き込まれたログを確認
$lastLine = exec("tail -n 1 {$logFile}");
assert(strpos($lastLine, MODX_BASE_PATH) === false); // 物理パスが置換されている
assert(strpos($lastLine, '{BASE_PATH}') !== false); // プレースホルダに置換されている
echo "✓ 多層防御：物理パスが {BASE_PATH} プレースホルダに置換されている\n";
```

**2. AI自走デバッグのシミュレーション**
```bash
# エラーパターンを検索
./evo log:search system "Undefined variable" --level=error --json > errors.json

# JSON解析して修正箇所を特定
jq '.[] | {file: .context.caller.file, line: .context.caller.line, message: .message}' errors.json

# 期待出力:
# {
#   "file": "manager/actions/document/edit.php",
#   "line": 123,
#   "message": "Undefined variable: document"
# }
```

**3. issue-resolver スキルの統合テスト**
```bash
# 不具合報告URLから解析
/analyze-issue "https://forum.example.com/thread/10705"

# 期待動作:
# 1. フォーラムから「Undefined variable」エラーを抽出
# 2. log:search で関連ログを検索
# 3. context.caller から該当ファイル・行を特定
# 4. 原因仮説を提示（ログ情報を根拠に）
```

**4. 管理画面のテスト**
- http://localhost/manager/?a=114 にアクセス
- ログファイル一覧が表示されること
- ファイルをクリックして JSONLines が整形表示されること
- レベル・キーワードフィルタが機能すること
- JSON形式でダウンロード可能なこと

**5. ローテーション・圧縮のテスト**
```bash
# ファイル分割（100MB超過時）
./evo log:rotate system

# 圧縮テスト（7日以上前のファイル）
./evo log:compress system --days=7
ls -lh temp/logs/system/*/*/*.gz  # 圧縮ファイル確認

# クリーンアップテスト（30日以上前を削除）
./evo log:clean system --days=30 --dry-run  # プレビュー
./evo log:clean system --days=30            # 実行

# 自動クリーンアップ（cron用）
./evo log:compress system --auto
./evo log:clean system --auto
```

**6. セキュリティ検証（相対パス記録の確認）**
```bash
# 意図的にエラーを発生させる
php -r "require 'manager/includes/logger.class.php'; (new Logger())->error('Test error');"

# ログを確認し、物理パスが含まれていないことを検証
LATEST_LOG=$(find temp/logs/system -name "*.log" -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2)
cat "$LATEST_LOG" | tail -n 1 | jq '.context.caller.file, .context.trace[].file'

# 期待出力: 相対パス（例：manager/includes/...）のみ、絶対パス（/var/www/...）は含まれない
# NGパターン: /home/user/www/evo/manager/... （物理パスが露出）
```

## Validation and Acceptance

### 完了条件

**1. PSR-3準拠ロガーが動作し、コンテキストが自動収集される**
```bash
php -r "
require 'manager/includes/logger.class.php';
\$logger = new Logger();
\$logger->error('テストエラー', ['test' => true]);
"
cat temp/logs/system/$(date +%Y)/$(date +%m)/system-$(date +%Y-%m-%d).log | tail -n 1 | jq '.'
```
期待出力: `context.trace`, `context.caller`, `context.request` が含まれる

**2. 既存APIが動作し、コンテキストが保存される**
```php
evo()->logEvent(0, 3, 'Legacy API Test', 'Test');
```
期待結果: `temp/logs/system/` 配下にJSONLines形式で記録され、スタックトレース含む

**3. AI自走デバッグが可能**
```bash
# エラー検索
./evo log:search system "error" --level=error --json

# 期待出力: 各エラーに file/line/trace が含まれる
```

**4. 管理画面が動作する**
- システムログ画面 (a=114) でファイル一覧表示
- JSONLines を整形して表示
- フィルタ・検索が機能
- JSON ダウンロード可能

**5. CLIコマンドが動作する**
```bash
./evo log:tail system --lines=50 --follow
./evo log:search system "test" --level=error --json
./evo log:rotate system
./evo log:compress system --days=7        # 圧縮
./evo log:clean system --days=30          # 削除
./evo log:clean system --auto             # 自動（設定値使用）
```

**6. issue-resolver スキルがログを活用できる**
```bash
# analyze-issue で自動ログ検索
/analyze-issue "Error: Undefined variable"
# → log:search が自動実行され、エラー箇所が特定される

# reproduce でリアルタイム監視
/reproduce
# → log:tail --follow でログを監視しながら再現

# implement-fix で修正前後を比較
/implement-fix
# → before.json と after.json でエラー消失を確認
```

**7. セキュリティ要件を満たしている**
```bash
# ログから物理パスが漏洩していないことを確認
grep -r "$(pwd)" temp/logs/system/
# → 何もヒットしないこと（相対パスのみ記録されている）

# JSONからファイルパスを抽出してチェック
cat temp/logs/system/2026/02/system-2026-02-07.log | jq '.context.caller.file, .context.trace[].file' | grep -E '^"/(home|var|usr|opt)'
# → 何もヒットしないこと（絶対パスが記録されていない）

# 多層防御の確認：物理パスが含まれていたらプレースホルダに置換されている
cat temp/logs/system/2026/02/system-2026-02-07.log | grep -c '{BASE_PATH}'
# → 0以上の数値（意図的に残された場合のみプレースホルダが出現）

# フォーラム投稿シミュレーション
cat temp/logs/system/2026/02/system-2026-02-07.log | head -n 5
# → ファイルパスが「manager/...」形式で表示され、サーバー構成が推測できないこと
```

**6. issue-resolver スキルがログを活用できる**
```bash
# analyze-issue で自動ログ検索
/analyze-issue "Error: Undefined variable"
# → log:search が自動実行され、エラー箇所が特定される

# reproduce でリアルタイム監視
/reproduce
# → log:tail --follow でログを監視しながら再現

# implement-fix で修正前後を比較
/implement-fix
# → before.json と after.json でエラー消失を確認
```

**7. セキュリティ要件を満たしている**
```bash
# ログから物理パスが漏洩していないことを確認
grep -r "$(pwd)" temp/logs/system/
# → 何もヒットしないこと（相対パスのみ記録されている）

# フォーラム投稿シミュレーション
cat temp/logs/system/2026/02/system-2026-02-07.log | head -n 5
# → ファイルパスが「manager/...」形式で表示され、サーバー構成が推測できないこと
```

### 後方互換性の確認

- 既存の `logEvent()` 呼び出し（26箇所）が正常動作
- **新規インストール**: `event_log` テーブルは作成されない（`create_tables.sql` から削除）
- **既存インストール**: `event_log` テーブルは残るが使用しない（手動削除可能）
- エラー発生時のフォールバック機能（ファイル書き込み失敗時はDBに記録、互換性のため保持）

## Security Considerations

### ファイルパスの相対パス化

**課題**: Evolution CMS はOSSとして公開されており、ユーザーがフォーラムやIssueで相談する際にログをそのまま貼り付けることがある。サーバーの物理パス（例：`/home/user/www/evo/`）が露出すると、サーバー構成が推測されるセキュリティリスクがある。

**対策**: 多層防御アプローチ

1. **第一防御層**: `toRelativePath()` メソッドで相対パス変換
   - 絶対パス: `/var/www/html/evo/manager/actions/document/edit.php`
   - 相対パス: `manager/actions/document/edit.php`

2. **第二防御層（最終防御）**: ログ書き込み時に `MODX_BASE_PATH` をプレースホルダ `{BASE_PATH}` で置換
   - 万が一 `toRelativePath()` を通過しなかったパスも保護
   - 例：`/var/www/html/evo/config.php` → `{BASE_PATH}/config.php`
   - プレースホルダにより「ここがベースパスだった」とデバッグ時に分かる

**実装**: `toRelativePath()` + `writeLog()` 内で `str_replace()`

**効果**:
- ユーザーがログをコピペしても、サーバーのディレクトリ構造が漏洩しない
- 開発者間でログを共有しやすい（環境依存のパスが含まれない）
- AI解析時もファイル特定が容易（相対パスの方が統一的）

### 多層防御の実装

1. **第一防御層**: `toRelativePath()` で明示的に相対パス変換（`collectContext()` 内）
2. **第二防御層**: `writeLog()` で物理パスを `{BASE_PATH}` プレースホルダに置換
   - `str_replace(MODX_BASE_PATH, '{BASE_PATH}/', $jsonLine)`
   - Windows環境のバックスラッシュパスも対応
   - パフォーマンス影響：`str_replace()` は高速で問題なし

**プレースホルダを選んだ理由**:
- 空文字だと「パスが途中から始まっている」と誤解される
- `{BASE_PATH}` なら「ここがベースパスだった」とデバッグ時に分かる
- フォーラム投稿時も意図が明確

**既知の制約**:
- 物理パスが短い場合（例：`/app/`、`/var/`）、ログメッセージ中の文字列も置換される可能性がある
- ただしプレースホルダ置換なら意図が判別可能で、安全側に倒れる挙動なので許容範囲内
- 例：「/app/config.php を読み込み」→「{BASE_PATH}/config.php を読み込み」（意味は通じる）
- 完全な検出（正規表現で行頭の絶対パスのみ置換等）は複雑すぎるため、シンプルな `str_replace()` を採用

### その他のセキュリティ考慮

- パスワード・APIキー等の機密情報は自動マスキング（今後の拡張で対応）
- ログファイルへのWebアクセス防止（`temp/` に `.htaccess` 配置）
- 管理画面でのログ閲覧は管理者権限必須

## Idempotence and Recovery

### 中断時の復帰

1. **ロガークラスのみ実装済み**: 互換レイヤーから実装を再開
2. **管理画面改修中に中断**: 既存画面をバックアップから復元し、新画面の再実装
3. **CLIコマンド実装中**: 既存コマンドは動作するため、未実装コマンドのみ追加

### 安全性

- 既存DBテーブルは残すため、新ログシステムに問題があっても既存データは保全
- ファイル書き込みは `FILE_APPEND | LOCK_EX` で排他制御
- ログディレクトリは自動作成（`mkdir -p`）
- エラー時のフォールバック: ファイル書き込み失敗 → DBに記録
- **ファイルパスは相対パス記録で情報漏洩リスクを最小化**

## Artifacts and Notes

### 関連ファイル

**新規作成**:
- `manager/includes/logger.class.php` — PSR-3準拠ロガー（コンテキスト自動収集）
- `manager/includes/cli/commands/log-tail.php`
- `manager/includes/cli/commands/log-search.php`
- `manager/includes/cli/commands/log-rotate.php`
- `manager/includes/cli/commands/log-clean.php`
- `manager/includes/cli/commands/log-compress.php` — 古いログファイルをgzip圧縮
- `manager/includes/cli/commands/log-compress.php`
- `.claude/skills/issue-resolver/log-analysis-guide.md` — ログ解析ガイド

**変更**:
- `manager/includes/traits/document.parser.subparser.trait.php` — `logEvent()` 互換レイヤー
- `manager/actions/report/eventlog.dynamic.php` — システムログ画面（全面刷新）
- `manager/includes/lang/japanese-utf8.inc.php` — 多言語対応
- `manager/includes/lang/english.inc.php` — 多言語対応
- `.claude/skills/issue-resolver/SKILL.md` — ログ解析機能の統合

**将来廃止** （ステップ7で削除）:
- `manager/processors/delete_eventlog.processor.php`
- `manager/processors/export_eventlog.processor.php`
- `manager/actions/report/eventlog_details.dynamic.php`

**新規インストール時の変更** （ステップ7で対応）:
- `install/sql/create_tables.sql` から `event_log` テーブル定義を削除

**このプランの対象外**（管理操作ログ、別プランで対応）:
- `manager/includes/log.class.inc.php`
- `manager/actions/report/logging.static.php`
- `manager/processors/db/empty_table.processor.php`
- `manager_log` テーブル

### 外部リンク

- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [RFC 5424: Syslog Protocol](https://tools.ietf.org/html/rfc5424)
- [JSONLines](https://jsonlines.org/)

### メモ

- ログファイルの `.gitignore` 追加が必要: `temp/logs/system/`
- `temp/logs/system/` ディレクトリのパーミッション確認（書き込み可能）
- AI エージェント向けドキュメント作成: JSONLines形式の仕様、コンテキスト構造
- **新規インストールでは `event_log` テーブルを作成しない**（`create_tables.sql` から削除）
- **既存インストールでは `event_log` テーブルは手動削除**（並行運用なし、即時ファイルベース移行）
- 旧プロセッサー・画面ファイルを削除（delete_eventlog, export_eventlog, eventlog_details）
- **管理操作ログの改修方針は別途策定**（DB保存のメリットも考慮）

### logrotate方式との比較

| 機能 | Apache logrotate | Evolution CMS ログ改修 |
| --- | --- | --- |
| ローテーション | 日次（外部ツール） | ファイル名に日付（自動） |
| 保持期間 | 設定可能（例：52週） | グローバル設定（デフォルト30日） |
| 自動削除 | cronで実行 | cron または書き込み時の確率的実行 |
| 圧縮 | gzip（遅延圧縮） | gzip（7日経過後、オプション） |
| 設定方法 | `/etc/logrotate.d/` | グローバル設定 + cron |
| 手動実行 | `logrotate -f` | `./evo log:clean --auto` |

**設計方針**: Unix/Linuxの `logrotate` 方式を参考に、PHP環境で実現可能な範囲で自動化。cron設定が困難な環境では、ロガー内蔵の確率的クリーンアップで対応。

### logrotate方式との比較

Apache等のWebサーバーでは、システムツール `logrotate` がログの自動削除・圧縮を行います：

```bash
# /etc/logrotate.d/apache2 の典型的な設定
/var/log/apache2/*.log {
    daily              # 日次ローテーション
    rotate 52          # 52世代（約1年）保持
    compress           # gzip圧縮
    delaycompress      # 最新ファイルは圧縮しない
    missingok
    notifempty
    create 640 root adm
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null
    endscript
}
```

Evolution CMSでは同様の機能を実現：

| 機能 | Apache logrotate | Evolution CMS ログ改修 |
| --- | --- | --- |
| ローテーション | 日次（外部ツール） | ファイル名に日付（自動） |
| 保持期間 | 設定可能（例：52週） | グローバル設定（デフォルト30日） |
| 自動削除 | cronで実行 | cron または書き込み時の確率的実行 |
| 圧縮 | gzip（遅延圧縮） | gzip（7日経過後、オプション） |
| 設定方法 | `/etc/logrotate.d/` | グローバル設定 + cron |
| 手動実行 | `logrotate -f` | `./evo log:clean --auto` |

### cron設定例（自動クリーンアップ）

Unix/Linux環境での設定例（`crontab -e`）：

```cron
# 毎日午前3時にログクリーンアップ＆圧縮（logrotate方式）
0 3 * * * cd /var/www/html/evo && ./evo log:compress system --auto >> temp/logs/cron.log 2>&1
5 3 * * * cd /var/www/html/evo && ./evo log:clean system --auto >> temp/logs/cron.log 2>&1
```

Windows環境では「タスクスケジューラ」で同等の設定を行う。

**cron不要の自動クリーンアップ**（ロガー内蔵、グローバル設定 `log_auto_clean=1` で有効化）：

```php
// Logger::write() 内で確率的に実行（1%の確率）
if (rand(1, 100) === 1 && evo()->getConfig('log_auto_clean')) {
    $this->autoCleanup();
}

private function autoCleanup()
{
    $days = (int)evo()->getConfig('log_retention_days', 30);
    $compress = (bool)evo()->getConfig('log_compress', true);

    // 7日以上経過したファイルを圧縮
    if ($compress) {
        $this->compressOldLogs(7);
    }

    // 保持期間を超えたファイルを削除
    $this->deleteOldLogs($days);
}
```

### グローバル設定の追加

システム設定 > サーバー設定に追加：

| キー | デフォルト値 | 説明 |
| --- | --- | --- |
| `log_retention_days` | `30` | ログファイル保持期間（日数） |
| `log_compress` | `1` | 7日以上経過したログをgzip圧縮（0=無効, 1=有効） |
| `log_auto_clean` | `1` | ログ書き込み時の自動クリーンアップ（0=無効, 1=有効） |

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

**管理操作ログとのインターフェース**: なし（別システムとして分離）

### 破壊的変更なし

既存APIは互換レイヤーで維持されるため、プラグイン・モジュールへの影響なし。

### AI自走デバッグのインターフェース

AIエージェントは以下の流れでログを活用：

1. **エラー検出**: `./evo log:search "error" --level=error --json`
2. **コンテキスト解析**: JSON から `context.caller.file`, `context.caller.line`, `context.trace` を抽出
3. **コード特定**: 該当ファイル・行を開いてエラー原因を分析
4. **修正適用**: パッチ生成・適用
5. **再現テスト**: 同じ操作を実行し、ログに同じエラーが出ないことを確認
6. **完了通知**: 修正内容とテスト結果を報告

### issue-resolver スキルのワークフロー統合

フォーラム/Issue報告 → ログ解析 → 修正 → 検証の流れ：

```bash
# 1. 不具合報告を解析
/analyze-issue "https://forum.example.com/thread/12345"
# → 自動でログ検索し、エラー箇所を特定

# 2. 再現コード作成
/reproduce
# → log:tail でリアルタイムログ確認

# 3. 修正実装
/implement-fix
# → 修正前後のログを比較検証

# 4. アーカイブ
/archive
# → solved-issues.md にログ例を含めて記録
```

これにより、フォーラム報告から修正・検証までを自動化可能。
