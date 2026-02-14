---
name: issue-resolver
description: 不具合報告（GitHub Issue、フォーラム投稿、社内報告）を起点に、調査・再現・修正・検証・記録までを一貫実行するスキル。症状の切り分け、原因仮説の整理、最小再現、修正実装、ExecPlan作成、PR下書き、ナレッジ追記が必要なときに使う。
---

# Issue Resolver

不具合対応を「再現可能な事実」ベースで前進させる。
実装規約はプロジェクトの `AGENTS.md` を最優先とし、必要に応じて `project-worker` の規約へ委譲する。

## 実行ルール

1. 先に現象と期待値を定義してから実装する。
2. 推測で修正せず、再現手順か観測ログを必ず作る。
3. 変更は最小単位に分割し、影響範囲を明示する。
4. 複雑修正は `.agent/PLANS.md` に従って ExecPlan を作成する。
5. 不具合調査では手動確認より先に `php evo` で設定・DB状態・キャッシュ状態を確認する。
6. このスキルはローカル開発環境専用とし、本番環境を前提にした慎重運用は行わない。
7. 調査と修正は効率優先・トークン節約優先で進め、過剰な説明や確認を省く。
8. 検証に必要な範囲でローカルDBの更新・削除・初期化を許容し、必要なら即時にリセットする。

## evo CLI（実用最小）

- `php evo config:show <key>`
- `php evo db:describe <table>`
- `php evo db:count <table> --where=...`
- `php evo db:query "SELECT ..."`
- `php evo cache:clear`

詳細リファレンスは `manager/includes/cli/README.md` を参照する。
本番接続情報や本番データを扱う前提は置かない。

## Workflow

### analyze-issue <URL|テキスト>
1. 入力が URL の場合は内容を取得し、事実情報（環境、手順、実際結果、期待結果）を抽出する。
2. `AGENTS.md` のドキュメントマップを参照し、関連コンポーネントと確認ドキュメントを特定する。
3. `php evo config:show <key>` で関連設定値を確認する。
4. `php evo db:describe <table>` で対象テーブル構造を確認する。
5. 必要時のみ `php evo db:count <table> --where=...` で件数を確認する。
6. 現象の要約、再現条件、原因仮説（最大3件）を作る。
7. 情報不足がある場合は、追加確認項目を短く列挙する。

### reproduce
1. 失敗条件を固定し、最小再現コードまたは最小再現手順を作る。
2. `php evo db:query "SELECT ..."` で再現に必要なレコード状態を確認する。
3. 再現前後で `php evo cache:clear` を実行し、キャッシュ要因を切り分ける。
4. 再現できない場合は「再現不能」のまま進めず、前提差分を明示する。

### create-branch
1. Issue 番号と症状からブランチ名を提案する（例: `fix/10705-tv-saving-error`）。
2. ベースブランチを確認してから分岐する。

### draft-plan
1. 変更が複数ファイル・複数段階に及ぶ場合は ExecPlan を作成する。
2. `analyze-issue` の結果を、目的・制約・検証方法つきで計画へ反映する。

### implement-fix
1. `draft-plan` に沿って最小差分で実装する。
2. 既存パターンを優先し、ヘルパー利用・DB安全性・イベント/キャッシュ影響を確認する。
3. 必要なテストまたは手動確認を実施し、結果を記録する。
4. 必要に応じて `php evo health:check` を実行し、基本健全性を確認する。
5. CLI 実行結果は検証ログへ要約して残す。

### archive
1. 修正内容を再現条件とセットで要約する。
2. Conventional Commits 形式のコミットメッセージ案を作る。
3. 再発防止の観点で、必要なら `assets/docs/troubleshooting/solved-issues.md` を更新する。

### pull-request
1. PR タイトルと本文を `What / Why / How / Test` の順で下書きする。
2. 破壊的変更、互換性影響、運用手順の有無を明記する。
