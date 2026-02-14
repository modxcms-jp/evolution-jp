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
4. 修正方針を先に判定し、軽微な修正は ExecPlan 作成を省略して `implement-fix` へ進んでよい（ただし調査報告への承認後に限る）。
5. 問題が分かりにくい場合、影響範囲が読みにくい場合、または実装が複雑な場合は `.agent/PLANS.md` に従って ExecPlan を作成する。
6. 不具合調査では手動確認より先に `docker compose exec <app-service> php evo` で設定・DB状態・キャッシュ状態を確認する。
7. このスキルはローカル開発環境専用とし、本番環境を前提にした慎重運用は行わない。
8. 調査と修正は効率優先・トークン節約優先で進め、過剰な説明や確認を省く。
9. 検証に必要な範囲でローカルDBの更新・削除・初期化を許容し、必要なら即時にリセットする。
10. フォーラムURLの本文取得は最初から制限外実行で行い、`curl` / `wget` を順に試す。
11. URL取得コマンド実行時は都度 `require_escalated` を使い、承認付きで実行する。
12. `Could not resolve host` や `Temporary failure in name resolution` はサンドボックス内制限として扱い、制限外実行へ切り替える。
13. URL取得に失敗した状態でローカルコードだけから原因を推測しない。取得不能時は必ずエンジニアへ対応（本文提供・制限外実行）を依頼する。
14. 原因が判明した時点で必ず先に調査報告を提出し、エンジニア承認前に修正へ進まない。
15. `evo` コマンドとDB参照はホスト側で直接実行しない。必ず Docker コンテナ内で実行する。
16. エラー隠蔽を目的とした修正を禁止する。警告/例外を消すための握りつぶしや無条件の値変換を行わず、原因となる不正データの発生源を修正する。
17. 例: `strpos()` に `null` が渡って落ちる場合、`null` を空文字へ変換して通すのではなく、`null` が渡る経路を特定して上流で是正する。

## evo CLI（実用最小）

- `docker compose exec <app-service> php evo config:show <key>`
- `docker compose exec <app-service> php evo db:describe <table>`
- `docker compose exec <app-service> php evo db:count <table> --where=...`
- `docker compose exec <app-service> php evo db:query "SELECT ..."`
- `docker compose exec <app-service> php evo cache:clear`

詳細リファレンスは `manager/includes/cli/README.md` を参照する。
本番接続情報や本番データを扱う前提は置かない。

## Workflow

### analyze-issue <URL|テキスト>
1. 入力が URL の場合は、最初の取得を制限外実行（承認付き）で行う。
2. 取得コマンドは `curl -fsSL -A "Mozilla/5.0" "<URL>" | php -r '$h=stream_get_contents(STDIN); $t=strip_tags($h); $t=preg_replace("/\s+/u"," ",$t); echo trim($t), PHP_EOL;'` を使う。
3. `curl` が失敗した場合は、制限外実行（承認付き）で `wget -qO- "<URL>" | php -r '$h=stream_get_contents(STDIN); $t=strip_tags($h); $t=preg_replace("/\s+/u"," ",$t); echo trim($t), PHP_EOL;'` を実行する。
4. 取得したテキストから事実情報（環境、手順、実際結果、期待結果）を抽出する。
5. `AGENTS.md` のドキュメントマップを参照し、関連コンポーネントと確認ドキュメントを特定する。
6. `docker compose exec <app-service> php evo config:show <key>` で関連設定値を確認する。
7. `docker compose exec <app-service> php evo db:describe <table>` で対象テーブル構造を確認する。
8. 必要時のみ `docker compose exec <app-service> php evo db:count <table> --where=...` で件数を確認する。
9. 現象の要約、再現条件、原因仮説（最大3件）を作る。
10. 原因が判明した場合は、修正前に「原因・影響範囲・修正方針案」を短く報告する。
11. URL本文を取得できない場合は、失敗理由を1行で記録し、環境制限またはアクセス制限の種別を明記する。
12. 制限外実行でも取得できない場合は、投稿本文の貼り付けを依頼する。
13. URL本文が得られるまでは原因推測や修正方針の断定を行わず、エンジニアの対応を待つ。
14. 情報不足がある場合は、追加確認項目を短く列挙する。


### reproduce
1. 失敗条件を固定し、最小再現コードまたは最小再現手順を作る。
2. `docker compose exec <app-service> php evo db:query "SELECT ..."` で再現に必要なレコード状態を確認する。
3. 再現前後で `docker compose exec <app-service> php evo cache:clear` を実行し、キャッシュ要因を切り分ける。
4. 再現できない場合は「再現不能」のまま進めず、前提差分を明示する。

### create-branch
1. Issue 番号と症状からブランチ名を提案する（例: `fix/10705-tv-saving-error`）。
2. ベースブランチを確認してから分岐する。

### draft-plan (必要時)
1. 問題が分かりにくい場合、影響範囲が読みにくい場合、または実装が複雑な場合にのみ ExecPlan を作成する。
2. `analyze-issue` の結果を、目的・制約・検証方法つきで計画へ反映する。

### implement-fix
1. 調査報告に対するエンジニア承認を確認してから実装を開始する。
2. `draft-plan` に沿って最小差分で実装する。
3. 既存パターンを優先し、ヘルパー利用・DB安全性・イベント/キャッシュ影響を確認する。
4. 必要なテストまたは手動確認を実施し、結果を記録する。
5. 必要に応じて `docker compose exec <app-service> php evo health:check` を実行し、基本健全性を確認する。
6. CLI 実行結果は検証ログへ要約して残す。

### archive
1. 修正内容を再現条件とセットで要約する。
2. Conventional Commits 形式のコミットメッセージ案を作る。
3. 再発防止の観点で、必要なら `assets/docs/troubleshooting/solved-issues.md` を更新する。

### pull-request
1. PR タイトルと本文を `What / Why / How / Test` の順で下書きする。
2. 破壊的変更、互換性影響、運用手順の有無を明記する。
