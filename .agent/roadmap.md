# Evolution CMS JP Edition ロードマップ (v1.3.0 – v1.5.0)

AI実装を前提とした長期計画の正本。実装計画（ExecPlan）と連動して更新する。

最終更新: 2026-02-14

## APIプラットフォーム計画（2026-02-14追加）

- [ ] **Phase 1: REST API基盤とセキュリティ**
    - [ ] `/api.php` 単一エントリ
    - [ ] ルート登録/ディスパッチャ/統一JSONエラー
    - [ ] APIキー + HMAC署名、timestamp + nonce、レート制限、監査ログ
    - [ ] ExecPlan: `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- [ ] **Phase 2: Headless公開Read API**
    - [ ] `resources` / `media` の read-only API
    - [ ] ページング/フィルタ/fields指定
    - [ ] 非公開データ遮断
    - [ ] ExecPlan: `.agent/plans/2026-02-14-headless-read-api.md`
- [ ] **Phase 3: 管理操作Write API**
    - [ ] resource の create/update/publish/unpublish/delete
    - [ ] 既存 `hasPermission()` と同一ルール適用
    - [ ] 失敗時監査ログと回復導線
    - [ ] ExecPlan: `.agent/plans/2026-02-14-manager-write-api.md`

## Phase 1: 基盤整備 (v1.2.0J安定化 + v1.3.0着手)

- [ ] **CLI機能拡充**
    - [ ] `cli.php` エントリーポイントの整備（`evo` として実装予定）
    - [ ] コマンドルーティングの実装（コロン区切りコマンド対応、11種のコマンド実装予定）
- [ ] **システムログ機構の改修** (AI自走デバッグの実現)
    - [ ] PSR-3準拠のロガークラス作成 (`manager/includes/logger.class.php`)
        - [ ] 自動コンテキスト収集（スタックトレース、呼び出し元、リクエスト情報）
        - [ ] ファイルパスの相対パス化（セキュリティ対策、多層防御）
    - [ ] ファイルベースログ保存 (JSONLines形式)
        - [ ] `temp/logs/system/YYYY/MM/system-YYYY-MM-DD.log`
    - [ ] ローテーション機構 (30日経過ファイルの削除、100MB超過時の分割)
        - [ ] 手動実行: `./evo log:clean system --days=30`
        - [ ] 7日経過ファイルのgzip圧縮（オプション）
        - [ ] cron設定による定期実行（オプション）
        - [ ] 自動クリーンアップ機能（グローバル設定で有効化、オプション）
    - [ ] 管理画面改修「システムログ」
        - [ ] ログファイル一覧表示（最新10-20件、日付・サイズ）
        - [ ] ファイル選択による内容表示（textarea、JSONLines整形表示）
        - [ ] 検索・フィルタ機能（レベル、キーワード）
        - [ ] JSON形式でダウンロード
        - [ ] メニュー名変更（「イベントログ」→「システムログ」）
    - [ ] CLI コマンド
        - [ ] `log:tail system` (リアルタイム監視)
        - [ ] `log:search system` (エラーパターン検索、JSON出力)
        - [ ] `log:rotate system` (ファイル分割)
        - [ ] `log:compress system` (古いログの圧縮)
        - [ ] `log:clean system` (古いログの削除)
    - [ ] issue-resolver スキルの更新（ログ解析機能統合）
        - [ ] analyze-issue でのログ自動検索
        - [ ] reproduce でのリアルタイムログ監視
        - [ ] implement-fix での修正前後ログ比較
    - [ ] 既存 `logEvent()` の互換レイヤー維持
    - [ ] インストールスクリプト修正・旧クラス削除
        - [ ] `install/sql/create_tables.sql` から `event_log` テーブル定義削除
        - [ ] 旧プロセッサー削除（delete_eventlog, export_eventlog, eventlog_details）
    - [ ] `event_log` テーブルの段階的廃止（新規インストールでは作成しない、既存は手動削除）
- [ ] **マイグレーション機構**
    - [ ] `up`/`down` メソッドを持つクラス設計
    - [ ] DBバージョン管理テーブルの作成
- [ ] **管理操作ログ機構の改修** (監査機能の強化)
    - [ ] ファイルベースログ保存 (`temp/logs/manager/YYYY/MM/manager-YYYY-MM-DD.log`)
    - [ ] 管理画面改修「管理操作ログ」
    - [ ] CLI コマンド (`log:tail manager`, `log:search manager`)
    - [ ] 既存 `logHandler` の互換レイヤー維持
    - [ ] `manager_log` テーブルの段階的廃止
- [ ] **管理画面URL変更機能**
    - [ ] `.env` または設定ファイルによるパス変更対応
- [ ] **オンラインアップデート機構** (基本設計)

## Phase 2: 優先基盤完成 & 大規模改修 (v1.3.0 – v1.4.0)

- [ ] **PDO移行** (最高優先度)
    - [ ] `DBAPI` クラス内での PDO ラッパー実装
    - [ ] 既存 `mysql_` 系関数の互換レイヤー作成
- [ ] **frame要素廃止** (最高優先度)
    - [ ] HTML5 + Ajax + Flexbox/Grid へのレイアウト変更
    - [ ] ヘッダー、サイドバー、メインエリアの段階的移行
- [ ] **jQuery廃止** (高優先度)
    - [ ] Vanilla JS (ES6+) への書き換え
    - [ ] `querySelector`, `fetch` api の活用
