---
name: issue-resolver
description: 不具合報告（フォーラム/Issue）に基づく調査・再現・修正・記録のフルサイクルを支援するスキル。URLからの情報取得や再現コード作成を含みます。
---

# Issue Resolver

不具合報告を起点に、調査から修正・記録までを行うワークフロー。
実装時のコーディング規約は `project-worker` スキルに従う。

## 実行ルール

1. このスキルはローカル開発環境専用として扱う。
2. 調査と修正は効率優先・トークン節約優先で進める。
3. 修正方針を先に判定し、軽微な修正はそのまま実装へ進む。
4. 問題が分かりにくい場合や修正が複雑な場合は `exec-plan` を作成してから進める。
5. 検証に必要な範囲でローカルDBの更新・削除・初期化を許容する。
6. 本番接続情報や本番データを扱う前提は置かない。

## コマンド

### analyze-issue <URL|テキスト>
1. URLならfetchで内容取得
2. `AGENTS.md` のドキュメントマップから関連ファイルを特定
3. `evo config:show` で関連設定値、`evo db:describe` で関連テーブル構造を確認
4. 現象の要約と原因仮説を3つ提示
5. 情報不足時はユーザーへの質問リストを作成

### reproduce
- 現象を再現する最小限のPHPコードを作成
- `evo db:query` でデータ状態を確認し再現条件を特定
- デバッグ用ログ (`evo()->logEvent(...)`) の挿入箇所を提案
- `evo cache:clear` でキャッシュクリアしてから再現確認

### create-branch
- Issue番号・内容からブランチ名を提案（例: `fix/10705-tv-saving-error`）
- mainから分岐して作成

### draft-plan (必要時)
- 問題が分かりにくい場合や修正が複雑な場合のみ `exec-plan` スキルの `/create-plan` に委譲する
- analyze-issue の調査結果をタスク概要として渡す

### implement-fix
- draft-planに基づきコードを修正
- `project-worker` スキルの規約を厳守
- 修正後 `evo cache:clear` でキャッシュクリア

### archive
- Conventional Commits形式のコミットメッセージ生成（例: `fix(manager): resolve tv saving error on php8.2 (Ref forum#10705)`）
- `assets/docs/troubleshooting/solved-issues.md` にナレッジを追記

### pull-request
- `gh` CLIでPR作成（push → pr create）
- PR本文（What / Why / How）をドラフト生成
