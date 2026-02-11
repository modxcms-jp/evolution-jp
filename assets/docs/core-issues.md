# コア改善課題

改修作業を通じて発見されたコア側の課題を記録する。各項目は発見日・発見元・関連するロードマップ項目を明記する。

## 記録ルール

- 新しい課題は末尾に追記
- 解決済みの課題は項目に `[解決済]` と日付を付記（削除しない）
- 優先度は記載しない（ロードマップ側で管理する）

---

## ConfigCheck クラスの UI 結合

- **発見日**: 2026-02-11
- **発見元**: CLI self-bootstrap プラン (`health:check` 実装時)
- **ファイル**: `manager/includes/config_check.inc.php`
- **課題**: チェックロジックと HTML レンダリングが密結合している
  - `IN_MANAGER_MODE` ガードで CLI から `require` できない
  - `run()` 内で HTML `<fieldset>` を直接組み立てている
  - `$_SESSION` / `sessionv()` に依存（admin 判定、TemplateSwitcher チェック等）
  - ファイル末尾でクラスを即座にインスタンス化（定義と実行が混在）
- **改善案**: `generateWarnings()` が既に構造化配列を返しているので、UI 依存を `run()` 側に閉じ込め、チェックロジックを公開メソッドとして CLI・管理画面・API から共有可能にする。`sessionv()` 依存の除去も必要。
- **関連ロードマップ**: なし（新規候補）

## Mysqldumper クラスの設計上の制約

- **発見日**: 2026-02-11
- **発見元**: CLI self-bootstrap プラン (`db:export` mysqldump 改修時)
- **ファイル**: `manager/includes/mysql_dumper.class.inc.php`
- **課題**:
  - `addslashes` ベースのエスケープ（MySQL ネイティブエスケープではない）
  - トランザクション整合性なし（`LOCK TABLES` / `--single-transaction` 相当がない）
  - 全体を `file_get_contents` でメモリに読み込む設計（大規模 DB でメモリ不足のリスク）
  - ファイル先頭の `$modx` グローバル変数チェックがクラス定義として不適切
- **改善案**: CLI では `mysqldump` ラッパーをデフォルトにすることで回避済み。管理画面のバックアップ機能でも同様に `mysqldump` を優先候補にすることを検討。クラス自体の改修は PDO 移行と合わせて行うのが効率的。
- **関連ロードマップ**: Phase 2 PDO 移行

## logEvent の HTML 混入

- **発見日**: 2026-02-11
- **発見元**: CLI self-bootstrap プラン (`log:show` 方針検討時)
- **ファイル**: `manager/includes/traits/document.parser.subparser.trait.php` (logEvent メソッド)
- **課題**: `event_log` テーブルの `description` カラムに HTML タグが混入している。呼び出し元が管理画面表示を前提として HTML を含むメッセージを渡しているため、CLI やAPI で消費する際に `strip_tags` 等の後処理が必要になる。
- **改善案**: ロードマップに記載済みの「構造化データ（JSON等）による保存設計」で解決予定。移行期間中は CLI 側で軽い HTML 除去処理を行う。
- **関連ロードマップ**: Phase 1 ログ機構の改修（構造化データ保存・HTML 保存の廃止）
