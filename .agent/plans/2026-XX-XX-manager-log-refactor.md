# ExecPlan: 管理操作ログ機構の改修（監査機能の強化）

## Status: 方針検討中

このプランは未着手です。システムログ機構の改修（`2026-02-07-logging-system-refactor.md`）完了後に方針を決定します。

## Purpose / Big Picture
Evolution CMS の管理操作ログ（管理者の操作履歴）を改修し、監査機能を強化する。誰がいつ何を変更したかを追跡可能にし、コンプライアンス要件に対応する。

**対象範囲**: 管理操作ログ（`manager_log` テーブル）のみ。

## Current State

### 既存実装
- **テーブル**: `manager_log` (timestamp, internalKey, username, action, itemid, itemname, message)
- **記録クラス**: `logHandler` (`manager/includes/log.class.inc.php`)
- **管理画面**: `manager/actions/report/logging.static.php` (a=13)
- **影響範囲**: 28箇所

### 主要ユースケース
- 管理者が特定のドキュメントをいつ編集したか
- 特定期間にどのユーザーがログインしたか
- ユーザー削除などの重要な操作履歴
- 統計レポート（月次・年次の操作傾向）

### 現在の問題点
- DB肥大化（バックアップ失敗の一因）
- 構造化されていない（`message` カラムは平文）
- ローテーション機能が非効率（TRIM方式）
- 長期保存の仕組みがない

## Design Options

### オプションA: ファイルベース移行（システムログと統一）
**メリット**:
- システムログとの一貫性
- DB肥大化の解決
- 長期アーカイブが容易

**デメリット**:
- 検索性の低下（期間・ユーザーによる絞り込みが遅い）
- 統計レポートが困難
- 既存の管理画面を全面刷新が必要

**実装方針**:
- `temp/logs/manager/YYYY/MM/manager-YYYY-MM-DD.log` (JSONLines)
- CLIコマンド: `log:search manager --user=5 --action=edit`

### オプションB: DB保存継続 + 構造化
**メリット**:
- 高速な検索（WHERE句による絞り込み）
- 統計レポートが容易（GROUP BY, COUNT等）
- 既存の管理画面を流用可能

**デメリット**:
- DB肥大化問題は別途対処が必要
- 長期保存の仕組みを別途実装

**実装方針**:
- `message` カラムを廃止、構造化カラム追加（`context` JSON型）
- パーティショニング導入（月次テーブル分割）
- 古いデータの自動アーカイブ（CSV/JSONファイル出力）

### オプションC: ハイブリッド（短期: DB、長期: ファイルアーカイブ）
**メリット**:
- 直近データは高速検索（DB）
- 古いデータはコスト削減（ファイル）
- 両方のメリットを活用

**デメリット**:
- 実装が複雑
- 検索時に両方を見る必要がある

**実装方針**:
- 直近90日: `manager_log` テーブル（DB）
- 91日以降: `temp/logs/manager/archive/YYYY-MM.jsonl`
- 定期的なアーカイブジョブ（CLI: `log:archive manager`）

## Questions to Answer

1. **保存期間の要件**: 最低何日間保存する必要があるか？（法令対応）
2. **検索頻度**: 管理操作ログを検索する頻度は？（日次・週次・月次）
3. **統計レポート**: 操作傾向の統計は必要か？
4. **パフォーマンス**: 現在のDB検索に問題があるか？
5. **改ざん防止**: 監査ログとして改ざん防止機能は必要か？

## Decision Log
（方針決定後に記載）

## Next Steps

1. システムログ機構の改修を完了
2. 現在の管理操作ログのユースケース・要件を整理
3. 3つのオプションを評価
4. 方針決定後、このプランを更新して実装開始

## References
- [システムログ改修プラン](./2026-02-07-logging-system-refactor.md)
- [ロードマップ](../../assets/docs/roadmap.md)
- 現在の実装: `manager/includes/log.class.inc.php`
- 現在の管理画面: `manager/actions/report/logging.static.php`
